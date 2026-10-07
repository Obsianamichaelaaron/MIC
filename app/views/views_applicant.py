import hashlib
import os
import time
import random
from decimal import Decimal
from django.shortcuts import render, redirect, get_object_or_404
from django.http import JsonResponse, HttpResponseForbidden
from django.core.cache import cache
from django.views.decorators.csrf import csrf_exempt
from django.db.models import Q
from django.conf import settings
from django.utils import timezone
from app.models import (
    User, Applicant, JobPosting, Application, SavedJob,
    InterviewSchedule, Qualification, Skill, ChatbotAnswer,
    ChatbotRecommendation, ResumeAnalysis, Employer
)
from app.auth_utils import require_role, getCurrentUserId
from app.services.resume_parser import verify_resume_document, parse_and_save_applicant_resume
from app.services.resume_storage import (
    ResumeStorageError,
    delete_resume,
    stage_uploaded_file,
    store_profile_picture,
    store_verified_resume,
)
from app.services.semantic_job_matching import (
    SemanticMatchingUnavailable,
    compute_semantic_match,
)
from app.services.mailer import send_application_submitted_email

MAX_JOB_MATCHES_FOR_DASHBOARD = 12
SEMANTIC_MATCH_CACHE_TTL_SECONDS = 300


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
        'reason': 'Uses a pre-trained sentence-transformer model; no admin-reviewed outcomes are required.',
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


def get_applicant_job_match(
    applicant: Applicant,
    job: JobPosting,
    resume_text: str = '',
) -> dict[str, object]:
    """Return pre-trained semantic relevance without application outcome labels."""
    if not _has_semantic_profile_content(applicant, resume_text):
        return {
            'score': None,
            'score_type': None,
            'semantic_breakdown': [],
            'reason': 'Add skills, qualifications, experience, or a resume to get an AI match estimate.',
            'model_ready': False,
        }

    cache_key = _semantic_match_cache_key(applicant, job, resume_text)
    cached_result = cache.get(cache_key)
    if cached_result is not None:
        return cached_result

    try:
        semantic_match = compute_semantic_match(applicant, job, resume_text)
    except SemanticMatchingUnavailable as exc:
        result = {
            'score': None,
            'score_type': None,
            'semantic_breakdown': [],
            'reason': str(exc),
            'model_ready': False,
        }
    else:
        result = {
            'score': semantic_match['score'],
            'score_type': 'semantic_model' if semantic_match['score'] is not None else None,
            'semantic_breakdown': semantic_match['breakdown'],
            'reason': semantic_match['reason'],
            'model_ready': True,
        }

    cache.set(cache_key, result, timeout=SEMANTIC_MATCH_CACHE_TTL_SECONDS)
    return result


@require_role('applicant')
def dashboard_view(request):
    """
    Applicant Dashboard matching applicant/dashboard.php.
    """
    user_id = getCurrentUserId(request)
    user = get_object_or_404(User, pk=user_id)
    applicant, _ = Applicant.objects.get_or_create(user=user)

    # Application counts
    app_count = Application.objects.filter(applicant=applicant).count()
    pending_count = Application.objects.filter(applicant=applicant, status='pending').count()
    shortlisted_count = Application.objects.filter(applicant=applicant, status='shortlisted').count()
    accepted_count = Application.objects.filter(applicant=applicant, status='accepted').count()

    # Chatbot completion check
    chatbot_answers = list(ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number'))
    chatbot_completed = bool(chatbot_answers)

    # Sub-scores for Employability Breakdown
    candidate_skills = [s.strip() for s in (applicant.skills or '').split(',') if s.strip()]
    if applicant.profile_completed:
        completion_pct = 100
    else:
        profile_fields = [
            applicant.skills,
            applicant.qualifications,
            applicant.experience_years,
            applicant.resume_file,
            applicant.education_level or applicant.profile_pic,
        ]
        completion_pct = round(sum(bool(value) for value in profile_fields) / len(profile_fields) * 100) if profile_fields else 0

    skills_score = min(100, len(candidate_skills) * 15) if candidate_skills else 0
    exp_score = min(100, int(applicant.experience_years or 0) * 20) if (applicant.experience_years and int(applicant.experience_years) > 0) else 0
    resume_score = 100 if applicant.resume_file else (40 if candidate_skills else 0)

    # Keep the profile-readiness indicator separate from supervised job matching.
    if applicant.employability_score and float(applicant.employability_score) > 0:
        base_score = float(applicant.employability_score)
        score_source = 'Resume Assessment'
    elif chatbot_completed:
        total_val = sum(a.score_value or 0 for a in chatbot_answers)
        base_score = min(100.0, max(40.0, (total_val / max(1, len(chatbot_answers) * 40)) * 100.0))
        score_source = 'Chatbot Quiz'
    elif candidate_skills or applicant.resume_file or (applicant.experience_years and int(applicant.experience_years) > 0):
        # Dynamic composite score based on profile strength
        base_score = (skills_score * 0.35) + (exp_score * 0.25) + (resume_score * 0.20) + (completion_pct * 0.20)
        score_source = 'Profile Strength'
    else:
        base_score = 0.0
        score_source = 'Not assessed'

    latest_score = min(100.0, max(0.0, round(base_score, 1)))
    display_score = f"{latest_score:.0f}" if latest_score.is_integer() else f"{latest_score:.1f}"

    if latest_score >= 80:
        score_color = '#28a745'
        score_description = "Excellent"
    elif latest_score >= 70:
        score_color = '#17a2b8'
        score_description = "Very Good"
    elif latest_score >= 60:
        score_color = '#ff9800'
        score_description = "Good"
    elif latest_score >= 45:
        score_color = '#fd7e14'
        score_description = "Average"
    elif latest_score > 0:
        score_color = '#dc3545'
        score_description = "Needs Improvement"
    else:
        score_color = '#687386'
        score_description = "Not assessed"

    # Fresh account check
    is_fresh_account = bool(app_count == 0 and not chatbot_completed and completion_pct < 75)

    # Recommendations use a pre-trained semantic model and do not require admin labels.
    recommendations = []
    all_jobs = JobPosting.objects.filter(status='active').select_related(
        'employer'
    ).prefetch_related('qualification_mappings__qualification').order_by('-posted_at')[:MAX_JOB_MATCHES_FOR_DASHBOARD]
    resume_text = ResumeAnalysis.objects.filter(
        applicant=applicant
    ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
    for job in all_jobs:
        match = get_applicant_job_match(applicant, job, resume_text)
        recommendations.append({
            'job': job,
            'job_id': job.job_id,
            'title': job.title,
            'location': job.location,
            'employment_type': job.employment_type,
            'company_name': job.employer.company_name if job.employer else 'MultiBiz Partner',
            'calculated_match_score': match['score'],
            'match_score_type': match['score_type'],
            'match_reason': match['reason'],
        })
    recommendations.sort(
        key=lambda x: (
            x['calculated_match_score'] is not None,
            x['calculated_match_score'] or 0,
        ),
        reverse=True,
    )
    recommendations = recommendations[:4]

    # Feedback list
    feedback_list = []
    feedback_count = 0
    try:
        from app.models import CandidateFeedback
        feedback_qs = CandidateFeedback.objects.filter(applicant=applicant).select_related('employer', 'employer__user', 'application', 'application__job').order_by('-created_at')
        feedback_count = feedback_qs.count()
        for fb in feedback_qs[:3]:
            feedback_list.append({
                'company_name': fb.employer.company_name if fb.employer else '',
                'employer_first': fb.employer.user.first_name if fb.employer and fb.employer.user else '',
                'employer_last': fb.employer.user.last_name if fb.employer and fb.employer.user else '',
                'job_title': fb.application.job.title if fb.application and fb.application.job else '',
                'application_status': fb.application.status if fb.application else '',
                'feedback_message': getattr(fb, 'feedback', ''),
                'created_at': fb.created_at,
            })
    except Exception:
        pass
    # Unread messages count
    unread_count = 0
    try:
        from app.models import Message
        unread_count = Message.objects.filter(receiver_id=user_id, is_read=False).count()
    except Exception:
        pass

    # Recent applications
    applications = Application.objects.filter(
        applicant=applicant
    ).select_related('job', 'job__employer').order_by('-applied_at')[:5]
    for application in applications:
        result = get_applicant_job_match(applicant, application.job, resume_text)
        application.match_score = result['score']
        application.match_score_type = result['score_type']
        application.match_reason = result['reason']

    # Upcoming interviews
    interviews = InterviewSchedule.objects.filter(
        application__applicant=applicant,
        status__in=['scheduled', 'rescheduled']
    ).select_related('employer', 'application', 'application__job').order_by('interview_date', 'start_time')

    shortlisted_count = Application.objects.filter(
        applicant=applicant, status='shortlisted'
    ).count()
    accepted_count = Application.objects.filter(
        applicant=applicant, status='accepted'
    ).count()
    recommended_jobs = []
    for recommendation in recommendations:
        recommended_job = JobPosting.objects.filter(
            pk=recommendation['job_id']
        ).select_related('employer').first()
        if recommended_job:
            recommended_jobs.append({
                'job': recommended_job,
                'match_score': recommendation['calculated_match_score'],
                'match_score_type': recommendation['match_score_type'],
                'match_reason': recommendation['match_reason'],
                'matched_skills': [],
                'missing_skills': [],
            })

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
        'saved_count': SavedJob.objects.filter(applicant=applicant).count(),
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
    jobs_qs = JobPosting.objects.filter(status='active').select_related(
        'employer'
    ).prefetch_related('qualification_mappings__qualification')

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
    all_jobs = list(jobs_qs.order_by('-posted_at'))

    # Saved & applied IDs
    saved_job_ids = set(SavedJob.objects.filter(applicant=applicant).values_list('job_id', flat=True)) if applicant else set()
    applied_job_ids = set(Application.objects.filter(applicant=applicant).values_list('job_id', flat=True)) if applicant else set()

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

    profile_completed = bool(applicant and (applicant.profile_completed or (applicant.skills and applicant.qualifications) or applicant.resume_file))

    now = timezone.now()
    jobs_list = []
    if applicant and all_jobs:
        match_model_status = _semantic_model_status()
        resume_text = ResumeAnalysis.objects.filter(
            applicant=applicant
        ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
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
            if applicant else {
                'score': None,
                'score_type': None,
                'semantic_breakdown': [],
                'reason': 'Applicant profile is not available.',
            }
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

        # Check qualification match
        qual_match = False
        if selected_qualification_id:
            qual_match = job.qualification_mappings.filter(qualification_id=selected_qualification_id).exists()

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
        })

    if model_failure:
        match_model_status = {'ready': False, 'reason': model_failure}

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
        JobPosting.objects.select_related('employer').prefetch_related(
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
    match = get_applicant_job_match(applicant, job, resume_text)
    match_score = match['score']

    # Check if profile is complete (skills & qualifications present or profile_completed flag is True or resume uploaded)
    profile_completed = bool(applicant and (applicant.profile_completed or (applicant.skills and applicant.qualifications) or applicant.resume_file))

    # Job qualifications
    job_qualifications = [qm.qualification for qm in job.qualification_mappings.select_related('qualification').all() if qm.qualification]

    job_skills = [
        skill.strip()
        for skill in (job.skills_required or '').split(',')
        if skill.strip()
    ]

    color = '#1866a3' if match_score is not None else '#687386'
    match_rating = 'Pre-trained AI semantic relevance' if match_score is not None else 'Estimate unavailable'

    similar_jobs = JobPosting.objects.filter(
        status='active'
    ).exclude(pk=job.job_id).select_related('employer')[:4]

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
    user = get_object_or_404(User, pk=user_id)
    applicant, _ = Applicant.objects.get_or_create(user=user)
    chatbot_answers = list(ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number'))

    j_id = job_id or request.GET.get('id') or request.GET.get('job_id') or request.POST.get('job_id')
    job = get_object_or_404(JobPosting.objects.select_related('employer'), pk=j_id)

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
                            )

        if not resume_path and not use_existing_resume:
            error = "Please provide or upload a resume to complete your application."

        if not error:
            resume_text = ResumeAnalysis.objects.filter(
                applicant=applicant
            ).order_by('-analysis_date').values_list(
                'extracted_text', flat=True
            ).first() or ''
            match = get_applicant_job_match(applicant, job, resume_text)
            match_score = match['score']
            classification = (
                'Pre-trained AI semantic relevance estimate'
                if match['score_type'] == 'semantic_model'
                else 'AI semantic estimate unavailable'
            )

            Application.objects.create(
                job=job,
                applicant=applicant,
                status='pending',
                match_score=Decimal('0'),
                cover_letter=cover_letter,
                resume_file=resume_path,
                classification=classification,
                classified_at=timezone.now(),
                remarks_history=f"[{timezone.now().strftime('%Y-%m-%d %H:%M')}] Application submitted."
            )

            # Send under review email to applicant
            try:
                applicant_name = f"{user.first_name} {user.last_name}".strip() or user.email
                company_name = job.employer.company_name if (job.employer and job.employer.company_name) else (job.company_name or 'MultiBiz Employer')
                send_application_submitted_email(
                    to_email=user.email,
                    applicant_name=applicant_name,
                    job_title=job.title,
                    company_name=company_name
                )
            except Exception as e:
                print(f"[Application Submit Mailer] Failed to dispatch email: {e}")

            return redirect('/applicant/applications.php?applied=success')

    resume_text = ResumeAnalysis.objects.filter(
        applicant=applicant
    ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
    match = get_applicant_job_match(applicant, job, resume_text)
    match_score = match['score']

    context = {
        'page_title': f"Apply for {job.title}",
        'current_page': 'jobs.php',
        'job': job,
        'applicant': applicant,
        'profile': applicant,
        'user': user,
        'match_score': match_score,
        'match_score_type': match['score_type'],
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

    if status_filter:
        apps_qs = apps_qs.filter(status=status_filter)

    # Get interviews for these applications
    interviews_map = {}
    interviews = InterviewSchedule.objects.filter(
        application__applicant=applicant
    ).select_related('employer')
    for inv in interviews:
        interviews_map[inv.application_id] = inv

    resume_text = ResumeAnalysis.objects.filter(
        applicant=applicant
    ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
    apps_list = []
    for app_item in apps_qs:
        result = get_applicant_job_match(applicant, app_item.job, resume_text)
        app_item.match_score = result['score']
        app_item.match_score_type = result['score_type']
        app_item.match_reason = result['reason']
        apps_list.append({
            'app': app_item,
            'interview': interviews_map.get(app_item.application_id)
        })

    context = {
        'page_title': 'My Applications - MultiBiz',
        'current_page': 'applications.php',
        'applications': apps_list,
        'status_filter': status_filter,
        'total_count': len(apps_list),
        'match_model_status': {
            'ready': True,
            'reason': 'Uses a pre-trained sentence-transformer model; no admin-reviewed outcomes are required.',
        },
        'applied_success': request.GET.get('applied') == 'success',
    }
    return render(request, 'applicant/applications.html', context)


@require_role('applicant')
def profile_view(request):
    """
    Applicant Profile Editor matching applicant/profile.php.
    """
    user_id = getCurrentUserId(request)
    user = get_object_or_404(User, pk=user_id)
    applicant, _ = Applicant.objects.get_or_create(user=user)

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action', 'update_profile')

        if action == 'update_profile':
            first_name = request.POST.get('first_name', '').strip()
            last_name = request.POST.get('last_name', '').strip()
            phone = request.POST.get('phone', '').strip()
            education_level = request.POST.get('education_level', '')
            experience_years = request.POST.get('experience_years', '0')
            qualifications = request.POST.get('qualifications', '').strip()
            skills = request.POST.get('skills', '').strip()

            if first_name and last_name:
                user.first_name = first_name
                user.last_name = last_name
                user.phone = phone
                user.save()
                request.session['first_name'] = first_name
                request.session['last_name'] = last_name

            applicant.education_level = education_level
            if experience_years.isdigit():
                applicant.experience_years = int(experience_years)
            applicant.qualifications = qualifications
            applicant.skills = skills
            applicant.profile_completed = True
            applicant.save()
            success_msg = "Profile updated successfully!"

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
                            )
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
                    applicant.save()
                    success_msg = "Resume deleted successfully!"
            else:
                error_msg = "No active resume found to delete."

    # Qualifications list for dropdown
    all_qualifications = Qualification.objects.filter(status='active').order_by('name')
    chatbot_answers = ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number')
    profile_fields = [
        applicant.skills,
        applicant.qualifications,
        applicant.experience_years,
        applicant.resume_file,
        applicant.education_level,
    ]
    completion_pct = round(sum(bool(value) for value in profile_fields) / len(profile_fields) * 100)
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
    saved_jobs = SavedJob.objects.filter(applicant=applicant).select_related('job', 'job__employer')
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
    user = get_object_or_404(User, pk=user_id)
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
            application.status = 'rejected'
            application.applicant_remarks_history = f"[{timezone.now().strftime('%Y-%m-%d %H:%M')}] Application withdrawn by applicant."
            application.save()
            return JsonResponse({'success': True, 'message': 'Application withdrawn successfully'})

    return JsonResponse({'success': False, 'message': 'Invalid request'})


def employer_view(request):
    """
    Public / Applicant view of employer profile matching applicant/employer.php.
    """
    emp_id = request.GET.get('id') or request.GET.get('employer_id')
    employer = get_object_or_404(Employer.objects.select_related('user'), pk=emp_id) if emp_id else None
    jobs = JobPosting.objects.filter(employer=employer, status='active') if employer else []

    context = {
        'page_title': f"{employer.company_name if employer else 'Employer'} - MultiBiz",
        'employer': employer,
        'jobs': jobs,
    }
    return render(request, 'applicant/employer_profile.html', context)
