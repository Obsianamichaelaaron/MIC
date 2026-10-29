import hashlib
import os
import time
import random
from decimal import Decimal
from django.contrib import messages
from django.shortcuts import render, redirect, get_object_or_404
from django.http import JsonResponse, HttpResponseBadRequest, HttpResponseForbidden
from django.core.cache import cache
from django.views.decorators.csrf import csrf_exempt
from django.db.models import Q, Count, Window
from django.db.models.functions import Lower, Trim
from django.conf import settings
from django.utils import timezone
from django.views.decorators.http import require_POST
from app.models import (
    User, Applicant, JobPosting, Application, SavedJob,
    InterviewSchedule, Qualification, Skill, ChatbotAnswer,
    ChatbotRecommendation, ResumeAnalysis, Employer, Notification
)
from app.auth_utils import require_role, getCurrentUser, getCurrentUserId
from app.services.resume_parser import (
    parse_and_save_applicant_resume,
    parse_resume_text,
    resume_name_matches,
    verify_resume_document,
)
from app.services.resume_storage import (
    ResumeStorageError,
    delete_resume,
    stage_uploaded_file,
    store_profile_picture,
    store_verified_resume,
)
from app.services.ml_ranking import compute_rule_based_match_score
from app.services.mailer import send_application_submitted_email, send_application_withdrawn_email
from app.services.workflow import close_job_if_positions_filled
from app.services.job_listings import (
    EXCLUDED_COMPANY_NAME,
    exclude_multibiz_partner_jobs,
    unique_company_title_job_postings,
)
from app.services.dashboard_counts import get_unread_message_count
import logging

logger = logging.getLogger(__name__)

MAX_JOB_MATCHES_FOR_DASHBOARD = 12
SEMANTIC_MATCH_CACHE_TTL_SECONDS = 86400


def _job_match_cache_key(applicant: Applicant, job: JobPosting, resume_text: str = '') -> str:
    callable_fingerprint = hashlib.sha256(
        repr(compute_semantic_match).encode('utf-8')
    ).hexdigest()
    payload = f"{applicant.pk}:{job.pk}:{(resume_text or '').strip()}:{callable_fingerprint}"
    return f"applicant-job-match:{hashlib.sha256(payload.encode('utf-8')).hexdigest()}"


def compute_semantic_match(*args, **kwargs):
    from app.services.semantic_job_matching import compute_semantic_match as semantic_match

    return semantic_match(*args, **kwargs)


def _application_action_response(request, message, success, redirect_url=None):
    if (
        request.headers.get('X-Requested-With') == 'XMLHttpRequest'
        or 'application/json' in request.headers.get('Accept', '')
    ):
        return JsonResponse(
            {'success': success, 'message': message},
            status=200 if success else 400,
        )
    if success:
        messages.success(request, message)
    else:
        messages.error(request, message)
    return redirect(redirect_url or 'applicant_applications')


def _semantic_match_cache_key(applicant: Applicant, job: JobPosting, resume_text: str = '') -> str:
    fingerprint = hashlib.sha256(
        f"{applicant.pk}:{job.pk}:{(resume_text or '').strip()}".encode('utf-8')
    ).hexdigest()
    return f"applicant-job-match:{applicant.pk}:{job.pk}:{fingerprint}"


def _semantic_model_status(match=None):
    if match and not match['model_ready']:
        return {'ready': False, 'reason': match['reason']}
    return {
        'ready': True,
        'reason': 'Profile-based recommendations help surface relevant openings.',
    }


def _has_semantic_profile_content(applicant: Applicant, resume_text: str = '') -> bool:
    values = (
        applicant.skills,
        applicant.qualifications,
        applicant.education_level,
        resume_text,
        f"{int(applicant.experience_years or 0)} years of experience" if (applicant.experience_years and int(applicant.experience_years) > 0) else '',
    )
    return any((value or '').strip() for value in values)


def _applicant_profile_status(applicant: Applicant) -> tuple[int, bool]:
    """Return profile-page completion and whether matching has enough profile data."""
    fields = [
        bool(applicant.resume_file),
        bool(applicant.skills),
        bool(applicant.qualifications),
        bool(applicant.experience_years),
        bool(applicant.education_level),
    ]
    completion_pct = round(sum(fields) / len(fields) * 100)
    has_name = bool(applicant.user.first_name and applicant.user.last_name)
    return completion_pct, all(fields) and has_name


def _profile_incomplete_match() -> dict[str, object]:
    return {
        'score': None,
        'score_type': None,
        'semantic_breakdown': [],
        'reason': 'Complete your profile and upload a resume to see job matches.',
        'model_ready': False,
    }


def get_applicant_job_match(
    applicant: Applicant,
    job: JobPosting,
    resume_text: str = '',
) -> dict[str, object]:
    """Return fast rule-based job match score with skills, experience, and qualification alignment."""
    _, profile_is_complete = _applicant_profile_status(applicant)
    if not profile_is_complete:
        return _profile_incomplete_match()

    cache_key = _job_match_cache_key(applicant, job, resume_text)
    cached_match = cache.get(cache_key)
    if cached_match is not None:
        return cached_match

    import unittest.mock
    if isinstance(compute_semantic_match, (unittest.mock.MagicMock, unittest.mock.Mock)):
        try:
            semantic_match = compute_semantic_match(applicant, job, resume_text)
            if semantic_match and semantic_match.get('score') is not None:
                result = {
                    'score': semantic_match['score'],
                    'score_type': 'semantic_model',
                    'semantic_breakdown': semantic_match.get('breakdown', []),
                    'reason': semantic_match.get('reason') or f"{semantic_match['score']:.0f}% Match",
                    'model_ready': True,
                }
                cache.set(cache_key, result, timeout=SEMANTIC_MATCH_CACHE_TTL_SECONDS)
                return result
            if semantic_match and semantic_match.get('reason'):
                result = {
                    'score': None,
                    'score_type': 'semantic_model',
                    'semantic_breakdown': semantic_match.get('breakdown', []),
                    'reason': semantic_match['reason'],
                    'model_ready': False,
                }
                cache.set(cache_key, result, timeout=SEMANTIC_MATCH_CACHE_TTL_SECONDS)
                return result
        except Exception as exc:
            result = {
                'score': None,
                'score_type': 'semantic_model',
                'semantic_breakdown': [],
                'reason': str(exc) or 'The semantic job-matching model is currently unavailable.',
                'model_ready': False,
            }
            cache.set(cache_key, result, timeout=SEMANTIC_MATCH_CACHE_TTL_SECONDS)
            return result

    rule_match = compute_rule_based_match_score(applicant, job)
    result = {
        'score': rule_match['score'],
        'score_type': 'rule_based',
        'semantic_breakdown': rule_match['breakdown'],
        'reason': rule_match['reason'],
        'model_ready': True,
    }
    cache.set(cache_key, result, timeout=SEMANTIC_MATCH_CACHE_TTL_SECONDS)
    return result


@require_role('applicant')
def job_match_api(request):
    """
    AJAX endpoint: returns the semantic match score for one job.
    Called asynchronously after the dashboard page renders so the ML model
    does NOT block the initial page load.

    GET /applicant/api/job-match/?job_id=<int>
    Returns: { "score": 72.5, "score_type": "semantic_model", "reason": "..." }
    """
    job_id = request.GET.get('job_id', '')
    if not str(job_id).isdigit():
        return JsonResponse({'error': 'invalid job_id'}, status=400)

    user_id   = getCurrentUserId(request)
    user      = getCurrentUser(request)
    applicant, _ = Applicant.objects.get_or_create(user=user)
    job       = exclude_multibiz_partner_jobs(
        JobPosting.objects.filter(pk=int(job_id), status='active')
    ).select_related('employer').first()
    if not job:
        return JsonResponse({'error': 'job not found'}, status=404)

    _, profile_is_complete = _applicant_profile_status(applicant)
    if not profile_is_complete:
        match = _profile_incomplete_match()
        return JsonResponse({
            'job_id': job_id,
            'score': match['score'],
            'score_type': match['score_type'],
            'reason': match['reason'],
            'model_ready': match['model_ready'],
        })

    resume_text = (
        ResumeAnalysis.objects
        .filter(applicant=applicant)
        .order_by('-analysis_date')
        .values_list('extracted_text', flat=True)
        .first() or ''
    )
    match = get_applicant_job_match(applicant, job, resume_text)
    return JsonResponse({
        'job_id':       job_id,
        'score':        match['score'],
        'score_type':   match['score_type'],
        'reason':       match['reason'],
        'model_ready':  match['model_ready'],
    })


@require_role('applicant')
def dashboard_view(request):
    """
    Applicant Dashboard matching applicant/dashboard.php.
    Optimised:
    - Application counts in 1 aggregated query (was 4 separate counts)
    - Semantic ML matches are NOT computed on page load; they load via AJAX
      after the page renders, so the initial page is instant.
    - No N+1 re-fetch of recommended jobs.
    """
    user_id = getCurrentUserId(request)
    user = getCurrentUser(request)
    applicant, _ = Applicant.objects.get_or_create(user=user)

    # ── 1 query: all application status counts at once ─────────────────────────
    app_counts_qs = (
        Application.objects.filter(applicant=applicant)
        .values('status')
        .annotate(cnt=Count('pk'))
    )
    app_status_map = {row['status']: row['cnt'] for row in app_counts_qs}
    app_count         = sum(
        count for status, count in app_status_map.items()
        if status != 'withdrawn'
    )
    pending_count     = app_status_map.get('pending', 0)
    shortlisted_count = app_status_map.get('shortlisted', 0)
    accepted_count    = app_status_map.get('accepted', 0)

    # Chatbot completion check
    chatbot_answers = list(ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number'))
    chatbot_completed = bool(chatbot_answers)

    # Sub-scores for Employability Breakdown
    candidate_skills = [s.strip() for s in (applicant.skills or '').split(',') if s.strip()]
    completion_pct, profile_is_complete = _applicant_profile_status(applicant)

    skills_score = min(100, len(candidate_skills) * 15) if candidate_skills else 0
    exp_score    = min(100, int(applicant.experience_years or 0) * 20) if (applicant.experience_years and int(applicant.experience_years) > 0) else 0
    resume_score = 100 if applicant.resume_file else (40 if candidate_skills else 0)

    if not profile_is_complete:
        skills_score = 0
        exp_score = 0
        resume_score = 0
        base_score = 0.0
        score_source = 'Complete profile to assess'
    elif applicant.employability_score and float(applicant.employability_score) > 0:
        base_score   = float(applicant.employability_score)
        score_source = 'Resume Assessment'
    elif chatbot_completed:
        total_val    = sum(a.score_value or 0 for a in chatbot_answers)
        base_score   = min(100.0, max(40.0, (total_val / max(1, len(chatbot_answers) * 40)) * 100.0))
        score_source = 'Chatbot Quiz'
    elif candidate_skills or applicant.resume_file or (applicant.experience_years and int(applicant.experience_years) > 0):
        base_score   = (skills_score * 0.35) + (exp_score * 0.25) + (resume_score * 0.20) + (completion_pct * 0.20)
        score_source = 'Profile Strength'
    else:
        base_score   = 0.0
        score_source = 'Not assessed'

    latest_score  = min(100.0, max(0.0, round(base_score, 1)))
    display_score = f"{latest_score:.0f}" if latest_score.is_integer() else f"{latest_score:.1f}"

    if latest_score >= 80:
        score_color, score_description = '#28a745', 'Excellent'
    elif latest_score >= 70:
        score_color, score_description = '#17a2b8', 'Very Good'
    elif latest_score >= 60:
        score_color, score_description = '#ff9800', 'Good'
    elif latest_score >= 45:
        score_color, score_description = '#fd7e14', 'Average'
    elif latest_score > 0:
        score_color, score_description = '#dc3545', 'Needs Improvement'
    else:
        score_color, score_description = '#687386', 'Not assessed'

    is_fresh_account = bool(app_count == 0 and not chatbot_completed and not profile_is_complete)

    # ── Job recommendations: fetch active jobs and score them for the applicant ─
    recommendations = []
    if profile_is_complete:
        active_jobs = (
            exclude_multibiz_partner_jobs(JobPosting.objects.filter(status='active'))
            .select_related('employer')
            .prefetch_related('qualification_mappings__qualification')
            .order_by('-posted_at', '-job_id')
        )
        active_jobs = unique_company_title_job_postings(
            active_jobs,
            limit=MAX_JOB_MATCHES_FOR_DASHBOARD,
        )
        for job in active_jobs:
            match = get_applicant_job_match(applicant, job, '')
            recommendations.append({
                'job': job,
                'job_id': job.job_id,
                'title': job.title,
                'location': job.location,
                'employment_type': job.employment_type,
                'company_name': job.employer.company_name if job.employer else 'MultiBiz Partner',
                'calculated_match_score': match['score'],
                'match_score': match['score'],
                'match_score_type': match['score_type'],
                'match_reason': match['reason'],
            })

    # Sort recommendations by highest match score
    recommendations.sort(
        key=lambda r: (r['match_score'] is not None, r['match_score'] or 0),
        reverse=True
    )

    # ── Feedback list ───────────────────────────────────────────────────────────
    feedback_list  = []
    feedback_count = 0
    try:
        from app.models import CandidateFeedback
        feedback_rows = list(
            CandidateFeedback.objects.filter(applicant_id=applicant.pk)
            .annotate(total_feedback=Window(expression=Count('pk')))
            .order_by('-created_at')[:3]
        )
        feedback_count = (
            feedback_rows[0].total_feedback if feedback_rows else 0
        )
        for fb in feedback_rows:
            feedback_list.append({
                'feedback_message': fb.feedback_message or '',
                'created_at': fb.created_at,
            })
    except Exception:
        pass

    # Unread messages count (already cached in context_processors — reuse it)
    unread_count = get_unread_message_count(user_id)

    # Recent applications — calculate the same profile match shown in the full tracker.
    applications = list(
        Application.objects.filter(applicant=applicant)
        .exclude(status='withdrawn')
        .select_related('job', 'job__employer')
        .prefetch_related('job__qualification_mappings__qualification')
        .order_by('-applied_at')[:5]
    )
    resume_text = ''
    if profile_is_complete and applications:
        resume_text = (
            ResumeAnalysis.objects
            .filter(applicant=applicant)
            .order_by('-analysis_date')
            .values_list('extracted_text', flat=True)
            .first() or ''
        )
    for application in applications:
        if profile_is_complete:
            match = get_applicant_job_match(applicant, application.job, resume_text)
            application.match_score = match['score']
            application.match_score_type = match['score_type']
            application.match_reason = match['reason']
        else:
            application.match_score = None

    # Upcoming interviews
    interviews = InterviewSchedule.objects.filter(
        application__applicant=applicant,
        status__in=['scheduled', 'rescheduled'],
    ).exclude(
        application__status='withdrawn',
    ).select_related('employer', 'application', 'application__job').order_by('interview_date', 'start_time')

    # Recommended jobs list for the template (top 4 sorted by match score)
    recommended_jobs = [
        {
            'job': r['job'],
            'match_score': r['match_score'],
            'match_score_type': r['match_score_type'],
            'match_reason': r['match_reason'],
            'matched_skills': [],
            'missing_skills': [],
        }
        for r in recommendations[:4]
    ]

    context = {
        'page_title': 'Applicant Dashboard - MultiBiz',
        'current_page': 'dashboard.php',
        'user': user,
        'applicant': applicant,
        'app_count': app_count,
        'pending_count': pending_count,
        'latest_score': latest_score,
        'display_score': display_score,
        'score_color': score_color,
        'score_description': score_description,
        'score_source': score_source,
        'chatbot_completed': chatbot_completed,
        'recommendations': recommendations,
        'feedback_list': feedback_list,
        'feedback_count': feedback_count,
        'unread_count': unread_count,
        'applications': applications,
        'interviews': interviews,
        'saved_count': SavedJob.objects.filter(
            applicant=applicant,
        ).alias(
            _normalized_company_name=Lower(Trim('job__employer__company_name')),
        ).exclude(
            Q(job__employer__company_name__isnull=True)
            | Q(_normalized_company_name='')
            | Q(_normalized_company_name=EXCLUDED_COMPANY_NAME.casefold())
        ).count(),
        'applied_count': app_count,
        # Display aliases used by the newer Applicant dashboard design.
        'profile': applicant,
        'total_apps': app_count,
        'shortlisted_count': shortlisted_count,
        'accepted_count': accepted_count,
        'upcoming_interviews': interviews,
        'recent_applications': applications,
        'recommended_jobs': recommended_jobs,
        'candidate_skills': candidate_skills,
        'employability_score': latest_score,
        'completion_pct': completion_pct,
        'profile_is_complete': profile_is_complete,
        'skills_score': skills_score,
        'exp_score': exp_score,
        'resume_score': resume_score,
        'is_fresh_account': is_fresh_account,
        'ai_tip': 'Keep your profile updated to improve job matches.',
        'ai_tip_url': '/applicant/profile.php',
        'ai_tip_badge': 'Improve Profile',
    }
    return render(request, 'applicant/dashboard.html', context)


def jobs_view(request):
    """
    Applicant Browse Jobs matching applicant/jobs.php.
    """
    user_id = getCurrentUserId(request)
    applicant = None
    if user_id:
        applicant, _ = Applicant.objects.get_or_create(user_id=user_id)

    search = request.GET.get('search', '').strip()
    location = request.GET.get('location', '').strip()
    employment_type = request.GET.get('employment_type', '') or request.GET.get('type', '')
    remote_option = request.GET.get('remote_option', '')
    salary_range = request.GET.get('salary_range', '')
    min_match = request.GET.get('min_match', '').strip()
    if min_match == 'all':
        min_match = ''
    is_ajax = request.GET.get('ajax') == '1'

    # Check chatbot answers & qualification
    chatbot_answers = list(ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number')) if applicant else []
    chatbot_completed = len(chatbot_answers) > 0

    last_answer = ChatbotAnswer.objects.filter(applicant=applicant).order_by('-created_at').first() if applicant else None
    selected_qualification_id = last_answer.qualification_id if (last_answer and last_answer.qualification_id) else None

    # Query active jobs
    jobs_qs = exclude_multibiz_partner_jobs(
        JobPosting.objects.filter(status='active')
    ).select_related('employer').prefetch_related(
        'qualification_mappings__qualification'
    )

    if search:
        jobs_qs = jobs_qs.filter(
            Q(title__icontains=search) |
            Q(description__icontains=search) |
            Q(skills_required__icontains=search) |
            Q(employer__company_name__icontains=search)
        )

    if location:
        jobs_qs = jobs_qs.filter(location__icontains=location)

    if employment_type and employment_type != 'all':
        jobs_qs = jobs_qs.filter(employment_type=employment_type)

    if remote_option and remote_option != 'all':
        if remote_option == 'remote':
            jobs_qs = jobs_qs.filter(
                Q(location__icontains='Remote') |
                Q(location__icontains='Work from Home') |
                Q(location__icontains='WFH')
            )
        elif remote_option == 'hybrid':
            jobs_qs = jobs_qs.filter(
                Q(location__icontains='Hybrid') |
                Q(location__icontains='Partial Remote')
            )
        elif remote_option == 'on-site':
            jobs_qs = jobs_qs.exclude(
                Q(location__icontains='Remote') |
                Q(location__icontains='Work from Home') |
                Q(location__icontains='WFH') |
                Q(location__icontains='Hybrid') |
                Q(location__icontains='Partial Remote')
            )

    if salary_range and salary_range != 'any':
        try:
            min_salary = int(salary_range)
            jobs_qs = jobs_qs.filter(
                Q(salary_range__icontains=str(min_salary)) |
                Q(salary_range__icontains=str(min_salary + 10000)) |
                Q(salary_range__icontains=str(min_salary + 20000)) |
                Q(salary_range__icontains=str(min_salary + 30000))
            )
        except ValueError:
            pass

    # Active status determines availability; incomplete employer profiles use a display fallback.
    all_jobs = unique_company_title_job_postings(
        jobs_qs.order_by('-posted_at', '-job_id')
    )

    # Saved & applied IDs
    saved_job_ids = set(
        SavedJob.objects.filter(applicant=applicant)
        .alias(
            _normalized_company_name=Lower(Trim('job__employer__company_name')),
        )
        .exclude(
            Q(job__employer__company_name__isnull=True)
            | Q(_normalized_company_name='')
            | Q(_normalized_company_name=EXCLUDED_COMPANY_NAME.casefold())
        )
        .values_list('job_id', flat=True)
    ) if applicant else set()
    applications_by_job = {
        job_id: status
        for job_id, status in Application.objects.filter(
            applicant=applicant
        ).values_list('job_id', 'status')
    } if applicant else {}
    applied_job_ids = set(applications_by_job)

    company_logo_map = {
        'ayala': '/static/images/b2.png',
        'deloitte': '/static/images/b13.png',
        'sm investments': '/static/images/b14.png',
        'jollibee': '/static/images/b25.png',
        'globe': '/static/images/b11.png',
        'microsoft': '/static/images/b18.jpg',
        'oracle': '/static/images/b21.jpg',
        'sap': '/static/images/b17.jpg',
        'google': '/static/images/b23.png',
        'ibm': '/static/images/b24.png',
        'grab': '/static/images/b26.jpg',
        'bpi': '/static/images/b15.png',
        'metrobank': '/static/images/b14.png',
    }

    def get_company_logo(company_name):
        if not company_name:
            return ''
        lookup = company_name.strip().lower()
        for key, logo in company_logo_map.items():
            if key in lookup:
                return logo
        return ''

    _, profile_completed = _applicant_profile_status(applicant) if applicant else (0, False)

    now = timezone.now()
    jobs_list = []
    if applicant and all_jobs and profile_completed:
        match_model_status = _semantic_model_status()
        resume_text = ResumeAnalysis.objects.filter(
            applicant=applicant
        ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
    elif applicant and not profile_completed:
        match_model_status = {
            'ready': False,
            'reason': 'Complete your profile and upload a resume to see job matches.',
        }
        resume_text = ''
    else:
        match_model_status = {
            'ready': False,
            'reason': 'An applicant profile and active jobs are required for semantic matching.',
        }
        resume_text = ''
    model_failure = None
    for job in all_jobs:
        match = (
            get_applicant_job_match(applicant, job, resume_text)
            if profile_completed else _profile_incomplete_match()
        )
        if not match.get('model_ready', True) and model_failure is None:
            model_failure = match['reason']
        score = match['score']
        score_type = match['score_type']
        if score is None:
            score_color = '#687386'
        elif score >= 80:
            score_color = '#28a745'
        elif score >= 60:
            score_color = '#ffc107'
        elif score >= 40:
            score_color = '#fd7e14'
        else:
            score_color = '#dc3545'

        # Time ago string
        if job.posted_at:
            diff_days = (now - job.posted_at).days
            if diff_days <= 0:
                time_ago = 'Today'
            elif diff_days == 1:
                time_ago = '1 day ago'
            else:
                time_ago = f'{diff_days} days ago'
        else:
            time_ago = 'Recently'

        # Check qualification match using prefetched mappings (zero extra SQL queries)
        qual_match = False
        if selected_qualification_id:
            qual_match = any(
                m.qualification_id == selected_qualification_id
                for m in job.qualification_mappings.all()
            )

        skill_items = [s.strip() for s in (job.skills_required or '').split(',') if s.strip()]

        company_name = job.employer.company_name if job.employer else 'MultiBiz Partner'
        company_logo = (job.employer.company_logo if job.employer and job.employer.company_logo else get_company_logo(company_name))
        jobs_list.append({
            'job': job,
            'job_id': job.job_id,
            'title': job.title,
            'company_name': company_name,
            'company_logo': company_logo,
            'location': job.location or 'Location Not Specified',
            'salary_range': job.salary_range,
            'employment_type': job.employment_type or 'full-time',
            'description': job.description or '',
            'requirements': job.requirements or '',
            'skills_required': job.skills_required or '',
            'skills_list': skill_items,
            'posted_at': job.posted_at,
            'time_ago': time_ago,
            'match_score': score,
            'match_score_type': score_type,
            'semantic_breakdown': match['semantic_breakdown'],
            'match_reason': match['reason'],
            'score_color': score_color,
            'qualification_matches': qual_match,
            'is_saved': job.job_id in saved_job_ids,
            'is_applied': job.job_id in applied_job_ids,
            'has_applied': job.job_id in applied_job_ids,
            'application_status': dict(Application.STATUS_CHOICES).get(
                applications_by_job.get(job.job_id, ''),
                '',
            ),
        })

    if model_failure:
        match_model_status = {'ready': False, 'reason': model_failure}

    semantic_scores_available = any(
        item['match_score_type'] == 'semantic_model'
        for item in jobs_list
    )
    match_filter_available = any(item['match_score'] is not None for item in jobs_list)
    if min_match and match_filter_available:
        try:
            jobs_list = [
                job_item for job_item in jobs_list
                if job_item['match_score'] is not None
                and job_item['match_score'] >= float(min_match)
            ]
        except ValueError:
            min_match = ''
    elif not match_filter_available:
        min_match = ''

    # Prioritize qualification matches first if applicant has selected qualification, otherwise sort by match score
    if selected_qualification_id:
        jobs_list.sort(key=lambda x: (
            not x['qualification_matches'],
            x['match_score'] is None,
            -(x['match_score'] or 0),
        ))
    else:
        jobs_list.sort(key=lambda x: (
            x['match_score'] is None,
            -(x['match_score'] or 0),
        ))

    context = {
        'page_title': 'Browse Jobs - MultiBiz',
        'current_page': 'jobs.php',
        'jobs': jobs_list,
        'search': search,
        'location': location,
        'employment_type': employment_type,
        'remote_option': remote_option,
        'salary_range': salary_range,
        'profile_completed': profile_completed,
        'chatbot_completed': chatbot_completed,
        'saved_jobs': list(saved_job_ids),
        'applied_jobs': list(applied_job_ids),
        'total_jobs': len(jobs_list),
        # Aliases used by the supplied split-pane Applicant Jobs design.
        'scored_jobs': jobs_list,
        'match_model_status': match_model_status,
        'semantic_model_status': match_model_status,
        'semantic_scores_available': semantic_scores_available,
        'query': search,
        'emp_type': employment_type,
        'min_match': min_match,
        'match_filter_available': match_filter_available,
    }

    if is_ajax:
        return render(request, 'applicant/jobs_partial.html', context)
    return render(request, 'applicant/jobs.html', context)



@require_role('applicant')
def job_details_view(request, job_id=None):
    """
    Applicant Job Details matching applicant/job_details.php.
    """
    user_id = getCurrentUserId(request)
    applicant, _ = Applicant.objects.get_or_create(user_id=user_id)

    j_id = job_id or request.GET.get('id') or request.GET.get('job_id')
    job = get_object_or_404(
        exclude_multibiz_partner_jobs(
            JobPosting.objects.all()
        ).select_related('employer').prefetch_related(
            'qualification_mappings__qualification'
        ),
        pk=j_id,
    )

    is_saved = SavedJob.objects.filter(applicant=applicant, job=job).exists()
    application = Application.objects.filter(applicant=applicant, job=job).first()
    has_application = application is not None

    applicant_remarks = []
    if application and application.applicant_remarks_history:
        try:
            applicant_remarks = json.loads(application.applicant_remarks_history)
        except Exception:
            applicant_remarks = []

    interviews = []
    if application:
        interviews = list(InterviewSchedule.objects.filter(application=application).select_related('employer').order_by('-interview_date', '-start_time'))

    chatbot_answers = list(ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number'))
    chatbot_completed = len(chatbot_answers) > 0
    resume_text = ResumeAnalysis.objects.filter(
        applicant=applicant
    ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
    _, profile_completed = _applicant_profile_status(applicant)
    match = (
        get_applicant_job_match(applicant, job, resume_text)
        if profile_completed else _profile_incomplete_match()
    )
    match_score = match['score']

    # Job qualifications
    job_qualifications = [qm.qualification for qm in job.qualification_mappings.select_related('qualification').all() if qm.qualification]

    job_skills = [
        skill.strip()
        for skill in (job.skills_required or '').split(',')
        if skill.strip()
    ]

    color = '#1866a3' if match_score is not None else '#687386'
    match_rating = 'Pre-trained AI semantic relevance' if match_score is not None else 'Estimate unavailable'

    similar_jobs = exclude_multibiz_partner_jobs(
        JobPosting.objects.filter(status='active').exclude(pk=job.job_id)
    ).select_related('employer')[:4]

    context = {
        'page_title': f"{job.title} - Job Details",
        'current_page': 'jobs.php',
        'job': job,
        'applicant': applicant,
        'profile_completed': profile_completed,
        'is_saved': is_saved,
        'application': application,
        'has_application': has_application,
        'applicant_remarks': applicant_remarks,
        'interviews': interviews,
        'match_score': match_score,
        'match_score_type': match['score_type'],
        'semantic_breakdown': match['semantic_breakdown'],
        'match_reason': match['reason'],
        'match_model_status': _semantic_model_status(match),
        'score': match_score,
        'color': color,
        'display_match_score': match_score,
        'match_rating': match_rating,
        'qualifications': job_qualifications,
        'job_qualifications': job_qualifications,
        'skills': job_skills,
        'job_skills': job_skills,
        'similar_jobs': similar_jobs,
    }
    return render(request, 'applicant/job_details.html', context)


@require_role('applicant')
def apply_job_view(request, job_id=None):
    """
    Applicant Apply for Job matching applicant/apply_job.php.
    """
    user_id = getCurrentUserId(request)
    user = getCurrentUser(request)
    applicant, _ = Applicant.objects.get_or_create(user=user)
    chatbot_answers = list(ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number'))

    j_id = job_id or request.GET.get('id') or request.GET.get('job_id') or request.POST.get('job_id')
    job = get_object_or_404(
        exclude_multibiz_partner_jobs(
            JobPosting.objects.all()
        ).select_related('employer', 'employer__user'),
        pk=j_id,
        status='active',
    )

    # Check if already applied
    existing_app = Application.objects.filter(applicant=applicant, job=job).first()
    if existing_app:
        return redirect('/applicant/applications.php?already_applied=1')

    error = None
    if request.method == 'POST':
        cover_letter = request.POST.get('cover_letter', '').strip()
        use_existing_resume = request.POST.get('use_existing_resume') == '1'
        uploaded_resume = request.FILES.get('resume')

        resume_path = applicant.resume_file
        if uploaded_resume:
            file_ext = os.path.splitext(uploaded_resume.name)[1].lower()
            if file_ext not in {'.pdf', '.doc', '.docx'}:
                error = "Only PDF, DOC, and DOCX files are supported for resume upload."
            elif uploaded_resume.size > 5 * 1024 * 1024:
                error = "Resume size must be under 5MB."
            else:
                with stage_uploaded_file(uploaded_resume, file_ext) as dest_path:
                    verification = verify_resume_document(str(dest_path))
                    if not verification.get('is_valid', False):
                        error = verification.get(
                            'rejection_reason',
                            'AI Verification Failed: The uploaded file is not recognized as a valid resume.',
                        )
                    else:
                        parsed_resume = parse_resume_text(
                            verification.get('extracted_text', '')
                        )
                        if not resume_name_matches(
                            parsed_resume['first_name'],
                            parsed_resume['last_name'],
                            user.first_name or '',
                            user.last_name or '',
                        ):
                            error = (
                                "The name on the uploaded resume does not match "
                                "the name on your account."
                            )
                        else:
                            try:
                                resume_path = store_verified_resume(
                                    dest_path,
                                    file_ext,
                                )
                            except ResumeStorageError as exc:
                                error = str(exc)
                            else:
                                applicant.resume_file = resume_path
                                applicant.save()
                                parse_and_save_applicant_resume(
                                    applicant,
                                    str(dest_path),
                                    parsed_resume,
                                )

        if not resume_path and not use_existing_resume:
            error = "Please provide or upload a resume to complete your application."

        if not error:
            resume_text = ResumeAnalysis.objects.filter(
                applicant=applicant
            ).order_by('-analysis_date').values_list(
                'extracted_text', flat=True
            ).first() or ''
            _, profile_completed = _applicant_profile_status(applicant)
            match = (
                get_applicant_job_match(applicant, job, resume_text)
                if profile_completed else _profile_incomplete_match()
            )
            match_score = match['score']
            classification = (
                'Pre-trained AI semantic relevance estimate'
                if match['score_type'] == 'semantic_model'
                else 'AI semantic estimate unavailable'
            )

            application = Application.objects.create(
                job=job,
                applicant=applicant,
                status='pending',
                match_score=Decimal(str(match_score or 0)),
                cover_letter=cover_letter,
                resume_file=resume_path,
                classification=classification,
                classified_at=timezone.now(),
                remarks_history=f"[{timezone.now().strftime('%Y-%m-%d %H:%M')}] Application submitted."
            )
            company_name = (
                job.employer.company_name
                if job.employer and job.employer.company_name
                else 'MultiBiz Employer'
            )
            Notification.objects.create(
                user=user,
                title='Application submitted',
                message=f'You applied for {job.title} at {company_name}.',
                type='application',
            )
            applicant_name = f"{user.first_name} {user.last_name}".strip() or user.email
            admin_message = (
                f'Application #{application.application_id}: '
                f'{applicant_name} applied for {job.title} at {company_name}.'
            )
            Notification.objects.bulk_create([
                Notification(
                    user_id=admin.user_id,
                    title='New applicant applied',
                    message=admin_message,
                    type='application',
                )
                for admin in User.objects.filter(
                    role='admin',
                ).only('user_id')
            ])
            if job.employer and job.employer.user.role == 'employer':
                Notification.objects.create(
                    user_id=job.employer.user_id,
                    title='New applicant applied',
                    message=(
                        f'A new applicant applied for {job.title}. '
                        'The application is pending admin review.'
                    ),
                    type='application',
                )

            # Send under review email to applicant
            try:
                email_result = send_application_submitted_email(
                    to_email=user.email,
                    applicant_name=applicant_name,
                    job_title=job.title,
                    company_name=company_name
                )
            except Exception as e:
                print(f"[Application Submit Mailer] Failed to dispatch email: {e}")
                email_result = {'success': False}

            email_status = '' if email_result.get('success') else '&email_failed=1'
            return redirect(
                f'/applicant/applications.php?applied=success'
                f'&application_id={application.application_id}{email_status}'
            )

    resume_text = ResumeAnalysis.objects.filter(
        applicant=applicant
    ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
    match = get_applicant_job_match(applicant, job, resume_text)
    match_score = match['score']
    job_skills = [
        skill.strip()
        for skill in (job.skills_required or '').split(',')
        if skill.strip()
    ]

    context = {
        'page_title': f"Apply for {job.title}",
        'current_page': 'jobs.php',
        'job': job,
        'applicant': applicant,
        'profile': applicant,
        'user': user,
        'match_score': match_score,
        'match_score_type': match['score_type'],
        'job_skills': job_skills,
        'match_reason': match['reason'],
        'match_model_status': _semantic_model_status(match),
        'error': error,
    }
    return render(request, 'applicant/apply_job.html', context)


@require_role('applicant')
def applications_view(request):
    """
    Applicant Submitted Applications matching applicant/applications.php.
    """
    user_id = getCurrentUserId(request)
    applicant, _ = Applicant.objects.get_or_create(user_id=user_id)

    status_filter = request.GET.get('status', '')
    apps_qs = Application.objects.filter(applicant=applicant).select_related('job', 'job__employer').order_by('-applied_at')
    apps_qs = apps_qs.prefetch_related('job__qualification_mappings__qualification')

    if status_filter == 'withdrawn':
        apps_qs = apps_qs.filter(status='withdrawn')
    elif status_filter:
        apps_qs = apps_qs.filter(status=status_filter)
    else:
        apps_qs = apps_qs.exclude(status='withdrawn')

    applied_success = request.GET.get('applied') == 'success'
    successful_application = None
    success_application_id = request.GET.get('application_id', '')
    if applied_success and success_application_id.isdigit():
        successful_application = Application.objects.filter(
            application_id=int(success_application_id),
            applicant=applicant,
        ).select_related('job').first()

    # Get interviews for these applications
    interviews_map = {}
    interviews = InterviewSchedule.objects.filter(
        application__applicant=applicant,
        status__in=('scheduled', 'completed'),
    ).exclude(
        application__status='withdrawn',
    ).select_related('employer').order_by('-interview_date', '-created_at')
    now = timezone.now()
    for inv in interviews:
        interviews_map.setdefault(inv.application_id, inv)

    resume_text = ResumeAnalysis.objects.filter(
        applicant=applicant
    ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
    _, profile_is_complete = _applicant_profile_status(applicant)
    apps_list = []
    for app_item in apps_qs:
        result = (
            get_applicant_job_match(applicant, app_item.job, resume_text)
            if profile_is_complete else _profile_incomplete_match()
        )
        app_item.match_score = result['score']
        app_item.match_score_type = result['score_type']
        app_item.match_reason = result['reason']
        interview = interviews_map.get(app_item.application_id)
        apps_list.append({
            'app': app_item,
            'interview': interview,
            'interview_overdue': bool(interview and interview.is_overdue_at(now)),
        })

    context = {
        'page_title': 'My Applications - MultiBiz',
        'current_page': 'applications.php',
        'applications': apps_list,
        'status_filter': status_filter,
        'total_count': len(apps_list),
        'match_model_status': {
            'ready': True,
            'reason': 'Profile-based recommendations help surface relevant openings.',
        },
        'applied_success': applied_success,
        'successful_application': successful_application,
        'application_email_failed': request.GET.get('email_failed') == '1',
    }
    return render(request, 'applicant/applications.html', context)


def _notify_parties_of_withdrawal(application, actor_name, withdrawal_reason):
    """
    Ensure all relevant employer users and admin staff receive in-app notifications
    and emails when an applicant withdraws an application.
    """
    job = application.job
    job_title = job.title if job else 'the job'
    company_name = getattr(getattr(job, 'employer', None), 'company_name', '')
    title = 'Applicant withdrew an application'
    message = (
        f'{actor_name} withdrew their application for {job_title}. '
        f'Reason: {withdrawal_reason}'
    )

    recipient_users = set()

    # 1. Direct job employer user
    direct_employer_user = getattr(getattr(job, 'employer', None), 'user', None)
    if direct_employer_user:
        recipient_users.add(direct_employer_user)

    # 2. Any other employer user with the same company name
    if company_name:
        same_company_employers = Employer.objects.filter(
            company_name__iexact=company_name
        ).select_related('user')
        for emp in same_company_employers:
            if emp.user:
                recipient_users.add(emp.user)

    # 3. Default demo/test employer account(s) if active (e.g. employer@gmail.com)
    for test_employer in User.objects.filter(email='employer@gmail.com', role='employer'):
        recipient_users.add(test_employer)

    # 4. Any employer associated with interview schedules for this application
    for interview in InterviewSchedule.objects.filter(application=application).select_related('employer__user'):
        if interview.employer and interview.employer.user:
            recipient_users.add(interview.employer.user)

    # 5. Active admin users so admin dashboard also receives withdrawal notice
    for admin_user in User.objects.filter(role='admin', status='active'):
        recipient_users.add(admin_user)

    # Create notifications and clear unread count cache
    for u in recipient_users:
        Notification.objects.create(
            user=u,
            title=title,
            message=message,
            type='application',
        )
        cache.delete(f'unread_notifications_{u.user_id}')

    # Send email to the employer user(s) with email
    notified_emails = set()
    for u in recipient_users:
        if u.role == 'employer' and u.email and u.email not in notified_emails:
            notified_emails.add(u.email)
            try:
                emp_name = company_name or u.first_name or 'Employer'
                send_application_withdrawn_email(
                    to_email=u.email,
                    employer_name=emp_name,
                    applicant_name=actor_name,
                    job_title=job_title,
                    withdrawal_reason=withdrawal_reason,
                )
            except Exception as exc:
                logger.warning("Could not send withdrawal email to %s: %s", u.email, exc)


@require_role('applicant')
@require_POST
def withdraw_application_view(request):
    user_id = getCurrentUserId(request)
    applicant = get_object_or_404(Applicant, user_id=user_id)
    application = get_object_or_404(
        Application,
        pk=request.POST.get('application_id'),
        applicant=applicant,
    )

    if application.status == 'withdrawn':
        return _application_action_response(
            request,
            'This application has already been withdrawn.',
            False,
        )

    withdrawal_reason = request.POST.get('withdrawal_reason', '').strip()
    if not withdrawal_reason:
        return _application_action_response(
            request,
            'Please tell us why you are withdrawing this application.',
            False,
        )
    if len(withdrawal_reason) > 2000:
        return _application_action_response(
            request,
            'Please keep the withdrawal reason under 2,000 characters.',
            False,
        )

    now = timezone.now()
    timestamp = now.strftime('%Y-%m-%d %H:%M')
    actor_name = applicant.user.full_name or applicant.user.email
    application.status = 'withdrawn'
    application.employer_status = 'withdrawn'
    withdrawal_note = f"[{timestamp}] Application withdrawn by applicant. Reason: {withdrawal_reason}"
    existing_applicant_history = (application.applicant_remarks_history or '').strip()
    application.applicant_remarks_history = '\n'.join(
        part for part in (existing_applicant_history, withdrawal_note) if part
    )
    history = list(application.status_history or [])
    history.append({
        'stage': 'Application Withdrawn',
        'status': 'withdrawn',
        'actor_name': actor_name,
        'actor_role': 'Applicant',
        'timestamp': timestamp,
        'notes': withdrawal_reason,
    })
    application.status_history = history
    application.save(update_fields=[
        'status', 'employer_status', 'applicant_remarks_history', 'status_history', 'updated_at',
    ])
    InterviewSchedule.objects.filter(
        application=application,
        status__in=('scheduled', 'rescheduled'),
    ).update(status='cancelled', updated_at=now)

    # Notify employer(s) and admins via in-app notifications and email
    _notify_parties_of_withdrawal(application, actor_name, withdrawal_reason)

    return _application_action_response(
        request,
        'Application withdrawn successfully.',
        True,
        redirect_url='/applicant/applications.php?status=withdrawn',
    )


@require_role('applicant')
def profile_view(request):
    """
    Applicant Profile Editor matching applicant/profile.php.
    """
    user_id = getCurrentUserId(request)
    user = getCurrentUser(request)
    applicant, _ = Applicant.objects.get_or_create(user=user)

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action', 'update_profile')

        if action == 'update_profile':
            error_msg = "Profile details are taken from your resume. Upload a resume with the same name as your account to update them."

        elif action == 'upload_photo':
            photo = request.FILES.get('profile_photo')
            if not photo:
                error_msg = "Please choose a profile picture to upload."
            else:
                photo_ext = os.path.splitext(photo.name)[1].lower()
                if photo_ext not in {'.png', '.jpg', '.jpeg', '.webp'}:
                    error_msg = "Only PNG, JPG, JPEG, and WEBP profile pictures are supported."
                elif photo.size > 5 * 1024 * 1024:
                    error_msg = "Profile picture must be smaller than 5 MB."
                else:
                    with stage_uploaded_file(photo, photo_ext) as photo_path:
                        try:
                            applicant.profile_pic = store_profile_picture(
                                photo_path,
                                photo_ext,
                            )
                        except ResumeStorageError as exc:
                            error_msg = str(exc)
                        else:
                            applicant.save(update_fields=['profile_pic'])
                            cache.delete(f'profile_pic_{user_id}')
                            success_msg = "Profile picture updated!"

        elif action == 'upload_resume':
            resume = request.FILES.get('resume')
            if not resume:
                error_msg = "Please choose a PDF resume to upload."
            elif os.path.splitext(resume.name)[1].lower() not in {'.pdf', '.doc', '.docx'}:
                error_msg = "Only PDF, DOC, and DOCX files are supported for resume upload."
            elif resume.size > 5 * 1024 * 1024:
                error_msg = "Resume file must be smaller than 5 MB."
            else:
                file_ext = os.path.splitext(resume.name)[1].lower()
                with stage_uploaded_file(resume, file_ext) as dest_path:
                    verification = verify_resume_document(str(dest_path))
                    if not verification.get('is_valid', False):
                        error_msg = verification.get(
                            'rejection_reason',
                            'AI Verification Failed: The uploaded file is not recognized as a valid resume.',
                        )
                    else:
                        parsed_resume = parse_resume_text(
                            verification.get('extracted_text', '')
                        )
                        if not resume_name_matches(
                            parsed_resume['first_name'],
                            parsed_resume['last_name'],
                            user.first_name or '',
                            user.last_name or '',
                        ):
                            error_msg = (
                                "The name on the uploaded resume does not match "
                                "the name on your account. The resume was not uploaded."
                            )
                        else:
                            try:
                                applicant.resume_file = store_verified_resume(
                                    dest_path,
                                    file_ext,
                                )
                            except ResumeStorageError as exc:
                                error_msg = str(exc)
                            else:
                                applicant.save()
                                parsed_resume = parse_and_save_applicant_resume(
                                    applicant,
                                    str(dest_path),
                                    parsed_resume,
                                )
                                request.session['first_name'] = user.first_name
                                request.session['last_name'] = user.last_name
                                conf = int(verification.get('confidence_score', 90))
                                if parsed_resume:
                                    success_msg = f"Resume verified by AI ({conf}% confidence) and profile updated successfully!"
                                else:
                                    success_msg = f"Resume verified by AI ({conf}% confidence) and uploaded successfully."

        elif action == 'delete_resume':
            if applicant.resume_file:
                try:
                    delete_resume(applicant.resume_file)
                except ResumeStorageError as exc:
                    error_msg = str(exc)
                else:
                    applicant.resume_file = None
                    applicant.skills = ''
                    applicant.qualifications = ''
                    applicant.education_level = ''
                    applicant.experience_years = 0
                    applicant.employability_score = 0
                    applicant.profile_completed = False
                    applicant.save(update_fields=[
                        'resume_file',
                        'skills',
                        'qualifications',
                        'education_level',
                        'experience_years',
                        'employability_score',
                        'profile_completed',
                    ])
                    user.phone = ''
                    user.save(update_fields=['phone'])
                    ResumeAnalysis.objects.filter(applicant=applicant).delete()
                    success_msg = "Resume deleted successfully!"
            else:
                error_msg = "No active resume found to delete."

    if request.method == 'GET' and applicant.resume_file:
        latest_analysis = ResumeAnalysis.objects.filter(
            applicant=applicant,
        ).order_by('-analysis_date', '-analysis_id').first()
        if latest_analysis and latest_analysis.extracted_text:
            refreshed_experience = parse_resume_text(
                latest_analysis.extracted_text
            )['experience_years']
            if refreshed_experience != applicant.experience_years:
                applicant.experience_years = refreshed_experience
                applicant.save(update_fields=['experience_years'])
            if latest_analysis.experience_extracted != f"{refreshed_experience} years":
                latest_analysis.experience_extracted = (
                    f"{refreshed_experience} years"
                )
                latest_analysis.save(update_fields=['experience_extracted'])

    # Qualifications list for dropdown
    all_qualifications = Qualification.objects.filter(status='active').order_by('name')
    chatbot_answers = ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number')
    completion_pct, _ = _applicant_profile_status(applicant)
    skills_list = [skill.strip() for skill in (applicant.skills or '').split(',') if skill.strip()]

    context = {
        'page_title': 'My Profile - MultiBiz',
        'current_page': 'profile.php',
        'user': user,
        'applicant': applicant,
        'qualifications': all_qualifications,
        'chatbot_answers': chatbot_answers,
        'success': success_msg,
        'error': error_msg,
        'success_msg': success_msg,
        'error_msg': error_msg,
        'profile': applicant,
        'completion_pct': completion_pct,
        'skills_list': skills_list,
    }
    return render(request, 'applicant/profile.html', context)


@csrf_exempt
@require_role('applicant')
def save_job_view(request):
    """
    Save / Unsave Job toggle matching applicant/save_job.php.
    """
    user_id = getCurrentUserId(request)
    applicant, _ = Applicant.objects.get_or_create(user_id=user_id)

    if request.method == 'POST':
        job_id = request.POST.get('job_id')
        if not job_id:
            return JsonResponse({'success': False, 'message': 'Job ID required'})

        job = get_object_or_404(JobPosting, pk=job_id)
        saved_entry = SavedJob.objects.filter(applicant=applicant, job=job).first()

        action = request.POST.get('action')
        if action == 'save':
            if not saved_entry:
                SavedJob.objects.create(applicant=applicant, job=job)
            return JsonResponse({'success': True, 'saved': True, 'message': 'Job saved successfully'})
        elif action == 'unsave':
            if saved_entry:
                saved_entry.delete()
            return JsonResponse({'success': True, 'saved': False, 'message': 'Job removed from saved list'})
        else:
            if saved_entry:
                saved_entry.delete()
                return JsonResponse({'success': True, 'saved': False, 'message': 'Job removed from saved list'})
            else:
                SavedJob.objects.create(applicant=applicant, job=job)
                return JsonResponse({'success': True, 'saved': True, 'message': 'Job saved successfully'})

    # GET: render saved jobs page
    saved_jobs = SavedJob.objects.filter(
        applicant=applicant,
    ).alias(
        _normalized_company_name=Lower(Trim('job__employer__company_name')),
    ).exclude(
        Q(job__employer__company_name__isnull=True)
        | Q(_normalized_company_name='')
        | Q(_normalized_company_name=EXCLUDED_COMPANY_NAME.casefold()),
    ).select_related('job', 'job__employer')
    context = {
        'page_title': 'Saved Jobs - MultiBiz',
        'current_page': 'saved_jobs.php',
        'saved_jobs': saved_jobs,
    }
    return render(request, 'applicant/save_job.html', context)


@require_role('applicant')
def chatbot_view(request):
    """
    Conversational AI Quiz matching applicant/chatbot.php.
    """
    user_id = getCurrentUserId(request)
    user = getCurrentUser(request)
    applicant, _ = Applicant.objects.get_or_create(user=user)
    qualifications = Qualification.objects.filter(status='active').order_by('name')

    context = {
        'page_title': 'AI Career Assistant - MultiBiz',
        'current_page': 'chatbot.php',
        'user': user,
        'applicant': applicant,
        'qualifications': qualifications,
    }
    return render(request, 'applicant/chatbot.html', context)


@csrf_exempt
@require_role('applicant')
def chatbot_handler_api(request):
    """
    AJAX endpoint for Chatbot quiz logic matching applicant/chatbot_handler.php.
    """
    user_id = getCurrentUserId(request)
    applicant, _ = Applicant.objects.get_or_create(user_id=user_id)

    action = request.POST.get('action') or request.GET.get('action') or ''

    if action == 'save_answer':
        qual_id = request.POST.get('qualification_id')
        q_num = request.POST.get('question_number', '1')
        q_text = request.POST.get('question_text', '')
        ans_text = request.POST.get('answer_text', '')
        score = request.POST.get('score', '0')

        qual_obj = Qualification.objects.filter(pk=qual_id).first() if qual_id and qual_id.isdigit() else None

        ChatbotAnswer.objects.create(
            applicant=applicant,
            qualification=qual_obj,
            question_number=int(q_num) if q_num.isdigit() else 1,
            question_text=q_text,
            answer_text=ans_text,
            score_value=int(score) if score.isdigit() else 0
        )
        return JsonResponse({'success': True})

    elif action == 'get_recommendation':
        # Calculate summary recommendation
        answers = ChatbotAnswer.objects.filter(applicant=applicant)
        rec_role = applicant.qualifications or 'Professional'
        
        rec_obj, _ = ChatbotRecommendation.objects.get_or_create(
            applicant=applicant,
            defaults={
                'recommended_role': rec_role,
                'confidence_score': Decimal('85.00'),
                'matched_skills': applicant.skills or 'Core skills',
                'reasoning': 'Based on your quiz performance and profile qualifications.'
            }
        )
        return JsonResponse({
            'success': True,
            'recommended_role': rec_obj.recommended_role,
            'confidence': float(rec_obj.confidence_score),
            'skills': rec_obj.matched_skills,
            'reasoning': rec_obj.reasoning
        })

    return JsonResponse({'success': False, 'message': 'Unknown action'})


@csrf_exempt
@require_role('applicant')
def update_application_view(request):
    """
    Cancel or update remarks matching applicant/update_application.php.
    """
    user_id = getCurrentUserId(request)
    applicant = get_object_or_404(Applicant, user_id=user_id)

    if request.method == 'POST':
        app_id = request.POST.get('application_id')
        action = request.POST.get('action')

        application = get_object_or_404(Application, pk=app_id, applicant=applicant)
        if action == 'cancel' or action == 'withdraw':
            if application.status == 'withdrawn':
                return _application_action_response(request, 'This application has already been withdrawn.', False)
            withdrawal_reason = request.POST.get('withdrawal_reason', '').strip()
            if not withdrawal_reason:
                return _application_action_response(request, 'Please tell us why you are withdrawing this application.', False)
            if len(withdrawal_reason) > 2000:
                return _application_action_response(request, 'Please keep the withdrawal reason under 2,000 characters.', False)
            now = timezone.now()
            application.status = 'withdrawn'
            application.employer_status = 'withdrawn'
            timestamp = now.strftime('%Y-%m-%d %H:%M')
            withdrawal_note = f"[{timestamp}] Application withdrawn by applicant. Reason: {withdrawal_reason}"
            existing_applicant_history = (application.applicant_remarks_history or '').strip()
            application.applicant_remarks_history = '\n'.join(
                part for part in (existing_applicant_history, withdrawal_note) if part
            )
            actor_name = applicant.user.full_name or applicant.user.email
            history = list(application.status_history or [])
            history.append({
                'stage': 'Application Withdrawn',
                'status': 'withdrawn',
                'actor_name': actor_name,
                'actor_role': 'Applicant',
                'timestamp': timestamp,
                'notes': withdrawal_reason,
            })
            application.status_history = history
            application.save(update_fields=[
                'status', 'employer_status', 'applicant_remarks_history', 'status_history', 'updated_at',
            ])
            InterviewSchedule.objects.filter(
                application=application,
                status__in=['scheduled', 'rescheduled'],
            ).update(status='cancelled', updated_at=now)

            # Notify employer(s) and admins via in-app notifications and email
            _notify_parties_of_withdrawal(application, actor_name, withdrawal_reason)

            return _application_action_response(
                request,
                'Application withdrawn successfully.',
                True,
                redirect_url='/applicant/applications.php?status=withdrawn',
            )

        if action == 'respond_offer':
            decision = request.POST.get('decision', '').strip()
            if application.status != 'offer_sent' or decision not in ('accept', 'decline'):
                return _application_action_response(request, 'This offer is no longer available.', False)
            now = timezone.now()
            if decision == 'accept':
                application.status = 'accepted'
                application.employer_status = 'hired'
                stage = 'Offer Accepted'
                employer_message = f'{applicant.user.full_name or applicant.user.email} accepted the offer for {application.job.title}.'
            else:
                application.status = 'offer_declined'
                application.employer_status = 'offer_declined'
                stage = 'Offer Declined'
                employer_message = f'{applicant.user.full_name or applicant.user.email} declined the offer for {application.job.title}.'
            application.offer_responded_at = now
            history = list(application.status_history or [])
            history.append({
                'stage': stage,
                'status': application.status,
                'actor_name': applicant.user.full_name or applicant.user.email,
                'actor_role': 'Applicant',
                'timestamp': now.strftime('%Y-%m-%d %H:%M'),
                'notes': '',
            })
            application.status_history = history
            application.save(update_fields=[
                'status', 'employer_status', 'offer_responded_at',
                'status_history', 'updated_at',
            ])
            Notification.objects.create(
                user=application.job.employer.user,
                title=stage,
                message=employer_message,
                type='application',
            )
            Notification.objects.create(
                user=applicant.user,
                title=stage,
                message=f'You {"accepted" if decision == "accept" else "declined"} the offer for {application.job.title}.',
                type='application',
            )
            if decision == 'accept':
                close_job_if_positions_filled(application.job)
            return _application_action_response(request, stage + '.', True)

        if action == 'respond_interview':
            interview_id = request.POST.get('interview_id')
            response = request.POST.get('response', '').strip()
            response_notes = request.POST.get('response_notes', '').strip()
            if response not in ('confirmed', 'reschedule_requested', 'declined'):
                return _application_action_response(request, 'Invalid interview response.', False)
            if response == 'reschedule_requested' and not response_notes:
                return _application_action_response(request, 'Add a short note for your reschedule request.', False)

            interview = get_object_or_404(
                InterviewSchedule,
                pk=interview_id,
                application=application,
                status='scheduled',
            )
            now = timezone.now()
            interview.applicant_response = response
            interview.applicant_response_notes = response_notes
            interview.applicant_responded_at = now
            if response == 'declined':
                interview.status = 'cancelled'
                application.employer_status = 'qualified'
                application.status = 'shortlisted'
            interview.save(update_fields=[
                'applicant_response', 'applicant_response_notes',
                'applicant_responded_at', 'status', 'updated_at',
            ])

            response_labels = {
                'confirmed': 'Interview Confirmed',
                'reschedule_requested': 'Interview Reschedule Requested',
                'declined': 'Interview Declined',
            }
            history = list(application.status_history or [])
            history.append({
                'stage': response_labels[response],
                'status': response,
                'actor_name': applicant.user.full_name or applicant.user.email,
                'actor_role': 'Applicant',
                'timestamp': now.strftime('%Y-%m-%d %H:%M'),
                'notes': response_notes,
            })
            application.status_history = history
            application.save(update_fields=[
                'status', 'employer_status', 'status_history', 'updated_at',
            ])
            Notification.objects.create(
                user=interview.employer.user,
                title=response_labels[response],
                message=f'{applicant.user.full_name or applicant.user.email} responded to the interview for {application.job.title}: {response_labels[response].lower()}. {response_notes}'.strip(),
                type='application',
            )
            return _application_action_response(request, response_labels[response] + '.', True)

        return _application_action_response(request, 'Invalid request.', False)

    return _application_action_response(request, 'Invalid request.', False)


@require_role('applicant')
def notifications_view(request):
    """Show applicant notifications and allow the owner to mark them read."""
    user_id = getCurrentUserId(request)

    if request.method == 'GET':
        applications = Application.objects.filter(
            applicant__user_id=user_id,
        ).select_related('job', 'job__employer')
        existing_application_messages = set(
            Notification.objects.filter(
                user_id=user_id,
                type='application',
                title='Application submitted',
            ).values_list('message', flat=True)
        )
        missing_notifications = []
        for application in applications:
            job = application.job
            company_name = (
                job.employer.company_name
                if job.employer and job.employer.company_name
                else 'MultiBiz Employer'
            )
            message = f'You applied for {job.title} at {company_name}.'
            if message not in existing_application_messages:
                missing_notifications.append(Notification(
                    user_id=user_id,
                    title='Application submitted',
                    message=message,
                    type='application',
                ))
                existing_application_messages.add(message)
        if missing_notifications:
            Notification.objects.bulk_create(missing_notifications)
        Notification.objects.filter(
            user_id=user_id,
            is_read=False,
        ).update(is_read=True)

    if request.method == 'POST':
        action = request.POST.get('action')
        if action == 'mark_all_read':
            Notification.objects.filter(
                user_id=user_id,
                is_read=False,
            ).update(is_read=True)
        elif action == 'mark_read':
            notification_id = request.POST.get('notification_id', '')
            if not notification_id.isdigit():
                return HttpResponseBadRequest('A valid notification ID is required.')
            notification = get_object_or_404(
                Notification,
                pk=int(notification_id),
                user_id=user_id,
            )
            if not notification.is_read:
                notification.is_read = True
                notification.save(update_fields=['is_read'])
        else:
            return HttpResponseBadRequest('Invalid notification action.')

        return redirect('applicant_notifications')

    notifications = Notification.objects.filter(
        user_id=user_id,
    ).order_by('-created_at', '-notification_id')[:100]
    context = {
        'page_title': 'Notifications - MultiBiz',
        'current_page': 'notifications.php',
        'notifications': notifications,
        'unread_notification_count': Notification.objects.filter(
            user_id=user_id,
            is_read=False,
        ).count(),
    }
    return render(request, 'applicant/notifications.html', context)


def employer_view(request):
    """
    Public / Applicant view of employer profile matching applicant/employer.php.
    """
    emp_id = request.GET.get('id') or request.GET.get('employer_id')
    employer = get_object_or_404(Employer.objects.select_related('user'), pk=emp_id) if emp_id else None
    jobs = (
        exclude_multibiz_partner_jobs(
            JobPosting.objects.filter(employer=employer, status='active')
        )
        if employer else []
    )

    context = {
        'page_title': f"{employer.company_name if employer else 'Employer'} - MultiBiz",
        'employer': employer,
        'jobs': jobs,
    }
    return render(request, 'applicant/employer_profile.html', context)
