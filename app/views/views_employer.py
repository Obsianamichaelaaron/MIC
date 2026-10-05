import os
import datetime
from decimal import Decimal
from django.shortcuts import render, redirect, get_object_or_404
from django.http import JsonResponse, HttpResponseForbidden
from django.views.decorators.csrf import csrf_exempt
from django.db.models import Q, Count
from django.conf import settings
from django.utils import timezone
import json
from app.models import (
    User, Employer, Applicant, JobPosting, Application,
    JobQualificationMapping, Qualification, InterviewSchedule,
    CandidateFeedback, CandidateRecommendation, ChatbotAnswer,
    Notification, ContactInquiry
)
from app.auth_utils import require_role, getCurrentUserId
from app.services.ml_ranking import calculate_candidate_ml_score, compute_job_match_score
from app.services.qualification import classify_match_score
from app.services.mailer import (
    send_application_status_update_email,
    send_talent_request_admin_notification,
    send_talent_request_employer_confirmation,
    send_interview_scheduled_email
)


def _sync_admin_postings_to_employer(employer):
    if not employer.company_name:
        return

    JobPosting.objects.filter(
        employer__company_name__iexact=employer.company_name,
        employer__user__role='admin',
    ).exclude(employer=employer).update(employer=employer)


def log_status_history_event(application, stage_title, status_key, actor_name, actor_role, notes=''):
    """Helper to log workflow stages into structured status_history on Application."""
    history = list(application.status_history or [])
    history.append({
        'stage': stage_title,
        'status': status_key,
        'actor': actor_name,
        'role': actor_role,
        'timestamp': timezone.now().strftime('%Y-%m-%d %H:%M'),
        'notes': notes or '',
    })
    application.status_history = history


@require_role('employer')
def dashboard_view(request):
    """
    Complete Company/Employer Dashboard with real-time workflow statistics,
    recent job requests with status badges, and recently forwarded candidates.
    """
    user_id = getCurrentUserId(request)
    user = get_object_or_404(User, pk=user_id)
    employer, _ = Employer.objects.get_or_create(user=user)
    _sync_admin_postings_to_employer(employer)

    # Job Requests metrics
    total_job_requests = JobPosting.objects.filter(employer=employer).count()
    active_jobs = JobPosting.objects.filter(employer=employer, status='active').count()
    pending_job_requests = JobPosting.objects.filter(employer=employer, status='pending').count()
    approved_job_requests = JobPosting.objects.filter(employer=employer, status='approved').count()
    rejected_job_requests = JobPosting.objects.filter(employer=employer, status='rejected').count()

    # Forwarded Candidates Metrics (Only applicants forwarded by Admin)
    forwarded_qs = Application.objects.filter(
        job__employer=employer,
        forwarded_to_employer=True
    ).select_related('job', 'applicant', 'applicant__user')

    total_applicants = forwarded_qs.count()
    applicants_for_review = forwarded_qs.filter(employer_status='for_review').count()
    qualified_applicants = forwarded_qs.filter(employer_status='qualified').count()
    not_qualified_applicants = forwarded_qs.filter(employer_status='not_qualified').count()
    applicants_for_interview = forwarded_qs.filter(employer_status='for_interview').count()
    interview_completed_count = forwarded_qs.filter(employer_status='interview_completed').count()
    hired_applicants = forwarded_qs.filter(employer_status='hired').count()
    rejected_applicants = forwarded_qs.filter(employer_status='rejected').count()

    # Upcoming scheduled interviews
    upcoming_interviews_qs = InterviewSchedule.objects.filter(
        employer=employer,
        interview_date__gte=timezone.now().date(),
        status='scheduled'
    ).select_related('application', 'application__applicant', 'application__applicant__user', 'application__job').order_by('interview_date', 'start_time')
    upcoming_interviews_count = upcoming_interviews_qs.count()

    # Recent Forwarded Applicants (6 latest)
    recent_candidates = []
    for app in forwarded_qs.order_by('-forwarded_at', '-applied_at')[:6]:
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        
        cand_dict = {
            'application_id': app.application_id,
            'first_name': user_obj.first_name if user_obj else '',
            'last_name': user_obj.last_name if user_obj else '',
            'email': user_obj.email if user_obj else '',
            'job_title': app.job.title if app.job else '',
            'job_id': app.job.job_id if app.job else 0,
            'match_score': float(app.match_score),
            'qualification_status': classify_match_score(app.match_score),
            'employer_status': app.employer_status,
            'status': app.status,
            'forwarded_at': app.forwarded_at or app.applied_at,
            'admin_qualification': app.admin_qualification,
            'resume_file': app.resume_file or (applicant.resume_file if applicant else ''),
            'employability_score': float(applicant.employability_score) if applicant else 0.0,
            'experience_years': applicant.experience_years if applicant else 0,
            'education_level': applicant.education_level or 'Not specified' if applicant else 'Not specified',
        }
        score_info = calculate_candidate_ml_score(cand_dict)
        cand_dict['ml_ranking_score'] = score_info['ml_ranking_score']
        cand_dict['ranking_category'] = score_info['ranking_category']
        recent_candidates.append(cand_dict)

    # Recent Job Requests (5 latest)
    recent_job_requests = JobPosting.objects.filter(employer=employer).annotate(
        applicant_count=Count('applications', filter=Q(applications__forwarded_to_employer=True))
    ).order_by('-posted_at')[:5]

    # Unread messages
    unread_count = 0
    try:
        from app.models import Message
        unread_count = Message.objects.filter(receiver_id=user_id, is_read=False).count()
    except Exception:
        pass

    context = {
        'page_title': 'Employer Dashboard - MultiBiz',
        'current_page': 'dashboard.php',
        'employer': employer,
        'user': user,
        'total_job_requests': total_job_requests,
        'active_jobs': active_jobs,
        'pending_job_requests': pending_job_requests,
        'approved_job_requests': approved_job_requests,
        'rejected_job_requests': rejected_job_requests,
        'total_applicants': total_applicants,
        'applicants_for_review': applicants_for_review,
        'qualified_applicants': qualified_applicants,
        'not_qualified_applicants': not_qualified_applicants,
        'applicants_for_interview': applicants_for_interview,
        'interview_completed_count': interview_completed_count,
        'hired_applicants': hired_applicants,
        'rejected_applicants': rejected_applicants,
        'upcoming_interviews_count': upcoming_interviews_count,
        'upcoming_interviews': upcoming_interviews_qs[:4],
        'recent_candidates': recent_candidates,
        'recent_job_requests': recent_job_requests,
        'unread_count': unread_count,
    }
    return render(request, 'employer/dashboard.html', context)


@require_role('employer')
def post_job_view(request):
    """
    Company submits a Job Request to the Admin.
    Employer CANNOT directly publish jobs; status defaults to 'pending' awaiting Admin Review.
    """
    user_id = getCurrentUserId(request)
    user = get_object_or_404(User, pk=user_id)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    error = None
    success = None
    form_data = {}

    if request.method == 'POST':
        title = request.POST.get('title', '').strip()
        description = request.POST.get('description', '').strip()
        requirements = request.POST.get('requirements', '').strip()
        skills_required = request.POST.get('skills_required', '').strip()
        location = request.POST.get('location', '').strip() or employer.company_address or 'Metro Manila'
        employment_type = request.POST.get('employment_type', 'full-time')
        salary_range = request.POST.get('salary_range', '').strip()
        positions_available = request.POST.get('positions_available', '1').strip()
        urgency = request.POST.get('urgency', 'Normal').strip()
        special_notes = request.POST.get('special_notes', '').strip()
        qualification_ids = request.POST.getlist('qualifications')
        custom_qualifications = request.POST.getlist('custom_qualifications[]')

        form_data = {
            'title': title,
            'description': description,
            'requirements': requirements,
            'skills_required': skills_required,
            'location': location,
            'employment_type': employment_type,
            'salary_range': salary_range,
            'positions_available': positions_available,
            'urgency': urgency,
            'special_notes': special_notes,
            'custom_qualifications': custom_qualifications,
        }

        if not title:
            error = "Job Title is required."
        elif not description:
            error = "Job Description is required."
        else:
            all_quals = list(qualification_ids) + [cq.strip() for cq in custom_qualifications if cq.strip()]
            target_quals_str = ','.join(all_quals) if all_quals else ''
            headcount_val = int(positions_available) if positions_available.isdigit() else 1

            # Create Job Request with pending status (Employers cannot directly publish)
            job = JobPosting.objects.create(
                employer=employer,
                title=title,
                description=description,
                requirements=requirements,
                skills_required=skills_required,
                location=location,
                employment_type=employment_type,
                salary_range=salary_range,
                positions_available=headcount_val,
                urgency=urgency,
                special_notes=special_notes,
                status='pending', # Under Admin Review
                target_qualifications=target_quals_str
            )

            # Qualification mappings
            for q_id in qualification_ids:
                if q_id.isdigit():
                    q_obj = Qualification.objects.filter(pk=int(q_id)).first()
                    if q_obj:
                        JobQualificationMapping.objects.create(job=job, qualification=q_obj)

            # In-App Notification for Admin
            admin_users = User.objects.filter(role='admin', status='active')
            for adm in admin_users:
                Notification.objects.create(
                    user=adm,
                    title=f"New Job Request: {title}",
                    message=f"{employer.company_name or user.full_name} submitted a job request for '{title}' ({headcount_val} position(s)). Ready for admin review.",
                    type='job_update'
                )

            # In-App Notification for Employer
            Notification.objects.create(
                user=user,
                title=f"Job Request Submitted: {title}",
                message=f"Your job request for '{title}' has been submitted to MultiBiz Admin for review. You will be notified once reviewed.",
                type='job_update'
            )

            # Email notifications
            job_specs = {
                'title': title,
                'company_name': employer.company_name or user.full_name,
                'employer_id': employer.employer_id,
                'headcount': headcount_val,
                'employment_type': employment_type,
                'location': location,
                'salary_range': salary_range,
                'skills_required': skills_required,
                'urgency': urgency,
                'description': description,
                'requirements': requirements,
                'special_notes': special_notes,
            }
            try:
                send_talent_request_admin_notification(
                    inquiry_id=job.job_id,
                    company_name=job_specs['company_name'],
                    contact_name=user.full_name or employer.company_name,
                    contact_email=user.email,
                    job_title=title,
                    job_specs=job_specs
                )
                send_talent_request_employer_confirmation(
                    to_email=user.email,
                    company_name=job_specs['company_name'],
                    contact_name=user.first_name or employer.company_name,
                    job_title=title
                )
            except Exception as e:
                print(f"[Job Request Mailer] Error: {e}")

            return redirect('/employer/jobs.php?submitted=success')

    qualifications = Qualification.objects.filter(status='active').order_by('name')

    context = {
        'page_title': 'Submit Job Request - MultiBiz',
        'current_page': 'post_job.php',
        'employer': employer,
        'user': user,
        'qualifications': qualifications,
        'form_data': form_data,
        'error': error,
        'success': success,
    }
    return render(request, 'employer/post_job.html', context)


@require_role('employer')
def request_talent_view(request):
    """Talent Sourcing & Job Specification Request alias redirecting to post_job_view."""
    return post_job_view(request)


@require_role('employer')
def jobs_view(request):
    """
    Manage & View Status of Job Requests (Pending, Approved, Posted/Active, Rejected, Closed).
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)
    _sync_admin_postings_to_employer(employer)

    status_filter = request.GET.get('status', 'all').strip()
    search = request.GET.get('search', '').strip()

    # Handle action POSTs
    if request.method == 'POST':
        action = request.POST.get('action')
        job_id = request.POST.get('job_id')
        job = get_object_or_404(JobPosting, pk=job_id, employer=employer)

        if action == 'close_job' and job.status == 'active':
            job.status = 'closed'
            job.save()
            return redirect('/employer/jobs.php?closed=1')
        elif action == 'reopen_request' and job.status in ['closed', 'rejected']:
            job.status = 'pending'
            job.rejection_reason = None
            job.save()
            return redirect('/employer/jobs.php?reopened=1')

    jobs_qs = JobPosting.objects.filter(employer=employer).annotate(
        applicant_count=Count('applications', filter=Q(applications__forwarded_to_employer=True))
    ).order_by('-posted_at')

    # Counts for tab badges
    total_count = jobs_qs.count()
    pending_count = jobs_qs.filter(status='pending').count()
    approved_count = jobs_qs.filter(status='approved').count()
    active_count = jobs_qs.filter(status='active').count()
    rejected_count = jobs_qs.filter(status='rejected').count()
    closed_count = jobs_qs.filter(status='closed').count()

    if status_filter and status_filter != 'all':
        jobs_qs = jobs_qs.filter(status=status_filter)

    if search:
        jobs_qs = jobs_qs.filter(
            Q(title__icontains=search) |
            Q(location__icontains=search) |
            Q(skills_required__icontains=search)
        )

    context = {
        'page_title': 'My Job Requests & Postings - MultiBiz',
        'current_page': 'jobs.php',
        'employer': employer,
        'jobs': jobs_qs,
        'status_filter': status_filter,
        'search': search,
        'total_count': total_count,
        'pending_count': pending_count,
        'approved_count': approved_count,
        'active_count': active_count,
        'rejected_count': rejected_count,
        'closed_count': closed_count,
        'submitted_success': request.GET.get('submitted') == 'success',
        'posted_success': request.GET.get('posted') == 'success',
        'updated_success': request.GET.get('updated') == 'success',
    }
    return render(request, 'employer/jobs.html', context)


@require_role('employer')
def edit_job_view(request, job_id=None):
    """
    Edit / Revise Job Request matching employer/edit_job.php.
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    j_id = job_id or request.GET.get('id') or request.GET.get('job_id')
    job = get_object_or_404(JobPosting, pk=j_id, employer=employer)

    error = None
    if request.method == 'POST':
        title = request.POST.get('title', '').strip()
        description = request.POST.get('description', '').strip()
        requirements = request.POST.get('requirements', '').strip()
        skills_required = request.POST.get('skills_required', '').strip()
        location = request.POST.get('location', '').strip()
        employment_type = request.POST.get('employment_type', 'full-time')
        salary_range = request.POST.get('salary_range', '').strip()
        positions_available = request.POST.get('positions_available', '1').strip()
        urgency = request.POST.get('urgency', 'Normal').strip()
        special_notes = request.POST.get('special_notes', '').strip()
        qualification_ids = request.POST.getlist('qualifications')

        if not title:
            error = "Job Title is required."
        elif not description:
            error = "Job Description is required."
        else:
            job.title = title
            job.description = description
            job.requirements = requirements
            job.skills_required = skills_required
            job.location = location
            job.employment_type = employment_type
            job.salary_range = salary_range
            job.positions_available = int(positions_available) if positions_available.isdigit() else 1
            job.urgency = urgency
            job.special_notes = special_notes
            
            # If job was rejected, editing resets it to pending review
            if job.status == 'rejected':
                job.status = 'pending'
                job.rejection_reason = None

            job.target_qualifications = ','.join(qualification_ids) if qualification_ids else ''
            job.save()

            # Refresh mappings
            JobQualificationMapping.objects.filter(job=job).delete()
            for q_id in qualification_ids:
                if q_id.isdigit():
                    q_obj = Qualification.objects.filter(pk=int(q_id)).first()
                    if q_obj:
                        JobQualificationMapping.objects.create(job=job, qualification=q_obj)

            return redirect('/employer/jobs.php?updated=success')

    qualifications = Qualification.objects.filter(status='active').order_by('name')
    selected_qual_ids = list(job.qualification_mappings.values_list('qualification_id', flat=True))

    context = {
        'page_title': f"Edit {job.title}",
        'current_page': 'jobs.php',
        'job': job,
        'qualifications': qualifications,
        'selected_qual_ids': selected_qual_ids,
        'error': error,
    }
    return render(request, 'employer/edit_job.html', context)


@require_role('employer')
def candidates_view(request):
    """
    Dedicated Applicants Section in Employer Dashboard.
    CRITICAL RULE: Only applicants forwarded by Admin (forwarded_to_employer=True) are shown!
    Clearly separated tabs:
      - For Review
      - Qualified
      - Not Qualified
      - For Interview
      - Interview Completed
      - Hired
      - Rejected
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    requested_tab = request.GET.get('tab', 'review').strip()
    tab_status_map = {
        'all': 'all',
        'review': 'for_review',
        'for_review': 'for_review',
        'qualified': 'qualified',
        'not_qualified': 'not_qualified',
        'interview': 'for_interview',
        'for_interview': 'for_interview',
        'interview_completed': 'interview_completed',
        'hired': 'hired',
        'rejected': 'rejected',
    }
    selected_tab = tab_status_map.get(requested_tab, 'for_review')
    job_id = request.GET.get('job_id', '0').strip()
    search = request.GET.get('search', '').strip()
    date_filter = request.GET.get('date_filter', '').strip()

    # Query ONLY applications forwarded by the Admin for this Employer's jobs
    apps_qs = Application.objects.filter(
        job__employer=employer,
        forwarded_to_employer=True
    ).select_related('applicant', 'applicant__user', 'job')

    if job_id and job_id.isdigit() and int(job_id) > 0:
        apps_qs = apps_qs.filter(job_id=int(job_id))

    if search:
        apps_qs = apps_qs.filter(
            Q(applicant__user__first_name__icontains=search) |
            Q(applicant__user__last_name__icontains=search) |
            Q(applicant__user__email__icontains=search) |
            Q(job__title__icontains=search)
        )

    if date_filter == 'today':
        apps_qs = apps_qs.filter(applied_at__date=timezone.now().date())
    elif date_filter == 'week':
        apps_qs = apps_qs.filter(applied_at__gte=timezone.now() - datetime.timedelta(days=7))
    elif date_filter == 'month':
        apps_qs = apps_qs.filter(applied_at__gte=timezone.now() - datetime.timedelta(days=30))

    # Calculate Tab Counts across all forwarded applications
    all_forwarded = Application.objects.filter(job__employer=employer, forwarded_to_employer=True)
    if job_id and job_id.isdigit() and int(job_id) > 0:
        all_forwarded = all_forwarded.filter(job_id=int(job_id))

    counts = {
        'for_review': all_forwarded.filter(employer_status='for_review').count(),
        'qualified': all_forwarded.filter(employer_status='qualified').count(),
        'not_qualified': all_forwarded.filter(employer_status='not_qualified').count(),
        'for_interview': all_forwarded.filter(employer_status='for_interview').count(),
        'interview_completed': all_forwarded.filter(employer_status='interview_completed').count(),
        'hired': all_forwarded.filter(employer_status='hired').count(),
        'rejected': all_forwarded.filter(employer_status='rejected').count(),
        'total': all_forwarded.count(),
    }

    # Filter to current tab
    if selected_tab in counts:
        apps_qs = apps_qs.filter(employer_status=selected_tab)
    elif selected_tab != 'all':
        selected_tab = 'for_review'
        apps_qs = apps_qs.filter(employer_status='for_review')

    candidates = []
    for app in apps_qs.order_by('-forwarded_at', '-applied_at'):
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        
        # Latest scheduled interview for this application
        latest_interview = InterviewSchedule.objects.filter(application=app).order_by('-interview_date').first()

        cand_dict = {
            'application_id': app.application_id,
            'first_name': user_obj.first_name if user_obj else '',
            'last_name': user_obj.last_name if user_obj else '',
            'email': user_obj.email if user_obj else '',
            'phone': user_obj.phone if user_obj else '',
            'job_id': app.job.job_id if app.job else 0,
            'job_title': app.job.title if app.job else '',
            'company_name': employer.company_name or '',
            'match_score': float(app.match_score),
            'qualification_status': classify_match_score(app.match_score),
            'employer_status': app.employer_status,
            'status': app.status,
            'applied_at': app.applied_at,
            'forwarded_at': app.forwarded_at or app.applied_at,
            'admin_qualification': app.admin_qualification,
            'admin_notes': app.admin_notes or '',
            'employer_notes': app.employer_notes or '',
            'resume_file': app.resume_file or (applicant.resume_file if applicant else ''),
            'employability_score': float(applicant.employability_score) if applicant else 0.0,
            'experience_years': applicant.experience_years if applicant else 0,
            'education_level': applicant.education_level or 'Not specified' if applicant else 'Not specified',
            'latest_interview': latest_interview,
        }
        score_info = calculate_candidate_ml_score(cand_dict)
        cand_dict['ml_ranking_score'] = score_info['ml_ranking_score']
        cand_dict['ranking_category'] = score_info['ranking_category']
        candidates.append(cand_dict)

    # Sort candidates by ML score
    candidates.sort(key=lambda x: x['ml_ranking_score'], reverse=True)

    jobs_for_filter = JobPosting.objects.filter(employer=employer).order_by('-posted_at')

    context = {
        'page_title': 'Applicants Review - MultiBiz Employer',
        'current_page': 'candidates.php',
        'employer': employer,
        'candidates': candidates,
        'applications': apps_qs.order_by('-forwarded_at', '-applied_at'),
        'selected_tab': selected_tab,
        'current_tab': {
            'for_review': 'review',
            'for_interview': 'interview',
        }.get(selected_tab, selected_tab),
        'counts': counts,
        'all_count': counts['total'],
        'review_count': counts['for_review'],
        'qualified_count': counts['qualified'],
        'interview_count': counts['for_interview'],
        'hired_count': counts['hired'],
        'not_qualified_count': counts['not_qualified'],
        'jobs': jobs_for_filter,
        'jobs_list': jobs_for_filter,
        'selected_job_id': int(job_id) if (job_id and job_id.isdigit()) else 0,
        'search': search,
        'search_query': search,
        'date_filter': date_filter,
        'total_candidates': len(candidates),
    }
    return render(request, 'employer/candidates.html', context)


@require_role('employer')
def recommended_candidates_view(request):
    """AI Recommended Candidates matching employer/recommended_candidates.php."""
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    job_id = request.GET.get('job_id', '0')
    applicants = Applicant.objects.select_related('user').all()
    jobs = JobPosting.objects.filter(employer=employer, status='active')

    recommended_list = []
    for app in applicants:
        best_score = 0
        best_job = None
        for j in jobs:
            if job_id and job_id.isdigit() and int(job_id) > 0 and j.job_id != int(job_id):
                continue
            sc = compute_job_match_score(app, j)
            if sc > best_score:
                best_score = sc
                best_job = j

        if best_job:
            cand_dict = {
                'applicant': app,
                'user': app.user,
                'job': best_job,
                'match_score': best_score,
                'employability_score': float(app.employability_score),
                'experience_years': app.experience_years,
                'education_level': app.education_level or 'Not specified',
            }
            score_info = calculate_candidate_ml_score(cand_dict)
            cand_dict['ml_ranking_score'] = score_info['ml_ranking_score']
            cand_dict['ranking_category'] = score_info['ranking_category']
            recommended_list.append(cand_dict)

    recommended_list.sort(key=lambda x: x['ml_ranking_score'], reverse=True)

    context = {
        'page_title': 'AI Recommended Candidates - MultiBiz',
        'current_page': 'recommended_candidates.php',
        'recommended_list': recommended_list,
        'jobs': jobs,
        'selected_job_id': int(job_id) if job_id.isdigit() else 0,
    }
    return render(request, 'employer/recommended_candidates.html', context)


@require_role('employer')
def view_candidate_view(request, application_id=None):
    """
    Complete Applicant Dossier Review, Qualification Decision, and Interview Scheduling.
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    app_id = application_id or request.GET.get('id') or request.GET.get('application_id')
    application = get_object_or_404(
        Application.objects.select_related('applicant', 'applicant__user', 'job'),
        pk=app_id,
        job__employer=employer,
        forwarded_to_employer=True # Must be forwarded by admin
    )

    applicant = application.applicant
    candidate_user = applicant.user
    job = application.job

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action')

        if action == 'update_status':
            new_status = request.POST.get('status', '').strip()
            employer_notes = request.POST.get('employer_notes', '').strip()

            valid_statuses = ['for_review', 'qualified', 'not_qualified', 'for_interview', 'interview_completed', 'hired', 'rejected']
            if new_status in valid_statuses:
                application.employer_status = new_status
                if employer_notes:
                    application.employer_notes = employer_notes
                
                # Sync overall application status
                if new_status == 'hired':
                    application.status = 'accepted'
                elif new_status == 'rejected':
                    application.status = 'rejected'
                elif new_status in ['for_interview', 'interview_completed']:
                    application.status = 'interviewed'
                elif new_status == 'qualified':
                    application.status = 'shortlisted'
                elif new_status == 'not_qualified':
                    application.status = 'reviewed'

                application.reviewed_by_employer_id = employer.employer_id
                application.reviewed_by_name = employer.company_name or 'Employer'

                # Log status history event
                status_label = new_status.replace('_', ' ').title()
                log_status_history_event(
                    application=application,
                    stage_title=f"Employer Marked as {status_label}",
                    status_key=new_status,
                    actor_name=employer.company_name or user_obj_name(candidate_user),
                    actor_role='Employer',
                    notes=employer_notes
                )
                application.save()

                # In-App Notification
                Notification.objects.create(
                    user=candidate_user,
                    title=f"Application Update: {job.title}",
                    message=f"Your application status at {employer.company_name} was updated to '{status_label}'.",
                    type='application'
                )

                # Send email update
                try:
                    send_application_status_update_email(
                        to_email=candidate_user.email,
                        applicant_name=candidate_user.full_name or candidate_user.email,
                        job_title=job.title,
                        company_name=employer.company_name or 'Employer',
                        new_status=new_status,
                        remarks=employer_notes
                    )
                except Exception as e:
                    print(f"[Employer Status Update Mailer] Error: {e}")

                success_msg = f"Candidate moved to '{status_label}' successfully!"

        elif action == 'schedule_interview':
            interview_date = request.POST.get('interview_date', '').strip()
            interview_time = request.POST.get('interview_time', '').strip()
            start_time_val = request.POST.get('start_time', '').strip() or None
            end_time_val = request.POST.get('end_time', '').strip() or None
            interview_type = request.POST.get('interview_type', 'Online').strip()
            location_or_link = request.POST.get('location_or_link', '').strip()
            interviewer_name = request.POST.get('interviewer_name', '').strip()
            instructions = request.POST.get('instructions', '').strip()
            additional_notes = request.POST.get('notes', '').strip()

            if not interview_date:
                error_msg = "Please specify the interview date."
            elif not interview_time and not start_time_val:
                error_msg = "Please specify the interview time."
            else:
                formatted_time = interview_time or start_time_val or '10:00 AM'
                
                # Create Interview Schedule Record
                schedule = InterviewSchedule.objects.create(
                    application=application,
                    employer=employer,
                    interview_date=interview_date,
                    interview_time=formatted_time,
                    start_time=start_time_val if start_time_val else None,
                    end_time=end_time_val if end_time_val else None,
                    interview_type=interview_type,
                    location=location_or_link if interview_type != 'Online' else None,
                    meeting_link=location_or_link if interview_type == 'Online' else None,
                    interviewer_name=interviewer_name,
                    instructions=instructions,
                    notes=additional_notes,
                    status='scheduled'
                )

                application.employer_status = 'for_interview'
                application.status = 'interviewed'
                application.save()

                # Log status history event
                log_status_history_event(
                    application=application,
                    stage_title="Interview Scheduled by Employer",
                    status_key="for_interview",
                    actor_name=employer.company_name or 'Employer',
                    actor_role="Employer",
                    notes=f"Interview set for {interview_date} at {formatted_time} ({interview_type}). Interviewer: {interviewer_name or 'Hiring Team'}."
                )
                application.save()

                # Send In-App Notification to Applicant
                Notification.objects.create(
                    user=candidate_user,
                    title=f"📅 Interview Scheduled: {job.title}",
                    message=f"{employer.company_name} scheduled an interview for {job.title} on {interview_date} at {formatted_time}.",
                    type='application'
                )

                # Send Automated Email with Complete Details to Applicant
                try:
                    send_interview_scheduled_email(
                        to_email=candidate_user.email,
                        applicant_name=candidate_user.full_name or candidate_user.email,
                        company_name=employer.company_name or 'Employer',
                        job_title=job.title,
                        interview_date=interview_date,
                        interview_time=formatted_time,
                        interview_type=interview_type,
                        location_or_link=location_or_link,
                        interviewer_name=interviewer_name,
                        instructions=instructions,
                        additional_notes=additional_notes
                    )
                except Exception as e:
                    print(f"[Interview Scheduled Mailer] Error: {e}")

                success_msg = f"Interview successfully scheduled for {interview_date} at {formatted_time}! Invitation email sent to {candidate_user.email}."

        elif action == 'submit_feedback':
            feedback_text = request.POST.get('feedback_text', '').strip()
            CandidateFeedback.objects.create(
                application_id=application.application_id,
                applicant_id=applicant.applicant_id,
                employer_id=employer.employer_id,
                employability_score=applicant.employability_score,
                feedback_message=feedback_text,
                feedback_type='manual'
            )
            success_msg = "Candidate feedback submitted!"

    # Scheduled interviews
    interviews = InterviewSchedule.objects.filter(application=application).order_by('-interview_date')
    
    # Chatbot assessment answers
    chatbot_answers = ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number')

    # Calculate AI ranking score
    cand_dict = {
        'match_score': float(application.match_score),
        'experience_years': applicant.experience_years,
        'employability_score': float(applicant.employability_score),
        'education_level': applicant.education_level or '',
    }
    score_info = calculate_candidate_ml_score(cand_dict)

    context = {
        'page_title': f"Candidate: {candidate_user.full_name} - MultiBiz",
        'current_page': 'candidates.php',
        'application': application,
        'qualification_status': classify_match_score(application.match_score),
        'applicant': applicant,
        'candidate_user': candidate_user,
        'job': job,
        'employer': employer,
        'interviews': interviews,
        'chatbot_answers': chatbot_answers,
        'ml_score': score_info['ml_ranking_score'],
        'tier': score_info['ranking_category'],
        'status_history': application.status_history or [],
        'success_msg': success_msg,
        'error_msg': error_msg,
    }
    return render(request, 'employer/view_candidate.html', context)


@csrf_exempt
@require_role('employer')
def update_status_api(request):
    """
    AJAX endpoint for candidate status update from the Employer portal.
    """
    user_id = getCurrentUserId(request)
    employer = get_object_or_404(Employer, user_id=user_id)

    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'Invalid method'})

    app_id = request.POST.get('application_id')
    status = request.POST.get('status', '').strip()
    notes = request.POST.get('notes', '').strip() or request.POST.get('remarks', '').strip()

    application = get_object_or_404(Application, pk=app_id, job__employer=employer, forwarded_to_employer=True)
    valid_statuses = ['for_review', 'qualified', 'not_qualified', 'for_interview', 'interview_completed', 'hired', 'rejected']

    if status in valid_statuses:
        application.employer_status = status
        if notes:
            application.employer_notes = notes

        if status == 'hired':
            application.status = 'accepted'
        elif status == 'rejected':
            application.status = 'rejected'
        elif status in ['for_interview', 'interview_completed']:
            application.status = 'interviewed'
        elif status == 'qualified':
            application.status = 'shortlisted'
        elif status == 'not_qualified':
            application.status = 'reviewed'

        application.reviewed_by_employer_id = employer.employer_id
        application.reviewed_by_name = employer.company_name or 'Employer'

        status_label = status.replace('_', ' ').title()
        log_status_history_event(
            application=application,
            stage_title=f"Employer Marked as {status_label}",
            status_key=status,
            actor_name=employer.company_name or 'Employer',
            actor_role='Employer',
            notes=notes
        )
        application.save()

        # In-App Notification and Email
        cand_user = application.applicant.user if (application.applicant and application.applicant.user) else None
        if cand_user:
            try:
                Notification.objects.create(
                    user=cand_user,
                    title=f"Application Update: {application.job.title}",
                    message=f"Your application status at {employer.company_name} was updated to '{status_label}'.",
                    type='application'
                )
                send_application_status_update_email(
                    to_email=cand_user.email,
                    applicant_name=cand_user.full_name or cand_user.email,
                    job_title=application.job.title,
                    company_name=employer.company_name or 'Employer',
                    new_status=status,
                    remarks=notes
                )
            except Exception as e:
                print(f"[update_status_api Mailer] Error: {e}")

        return JsonResponse({'success': True, 'message': f'Status updated to {status_label}', 'new_status': status})

    return JsonResponse({'success': False, 'message': 'Invalid status'})


@csrf_exempt
@require_role('employer')
def schedule_interview_api(request):
    """
    AJAX endpoint for scheduling an interview directly from the candidate list modal.
    """
    user_id = getCurrentUserId(request)
    employer = get_object_or_404(Employer, user_id=user_id)

    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'Invalid request method'})

    app_id = request.POST.get('application_id')
    interview_date = request.POST.get('interview_date', '').strip()
    interview_time = request.POST.get('interview_time', '').strip()
    interview_type = request.POST.get('interview_type', 'Online').strip()
    location_or_link = request.POST.get('location_or_link', '').strip()
    interviewer_name = request.POST.get('interviewer_name', '').strip()
    instructions = request.POST.get('instructions', '').strip()
    notes = request.POST.get('notes', '').strip()

    if not app_id or not interview_date or not interview_time:
        return JsonResponse({'success': False, 'message': 'Missing required interview date or time'})

    application = get_object_or_404(Application, pk=app_id, job__employer=employer, forwarded_to_employer=True)
    candidate_user = application.applicant.user

    # Create interview schedule
    InterviewSchedule.objects.create(
        application=application,
        employer=employer,
        interview_date=interview_date,
        interview_time=interview_time,
        interview_type=interview_type,
        location=location_or_link if interview_type != 'Online' else None,
        meeting_link=location_or_link if interview_type == 'Online' else None,
        interviewer_name=interviewer_name,
        instructions=instructions,
        notes=notes,
        status='scheduled'
    )

    application.employer_status = 'for_interview'
    application.status = 'interviewed'
    
    log_status_history_event(
        application=application,
        stage_title="Interview Scheduled by Employer",
        status_key="for_interview",
        actor_name=employer.company_name or 'Employer',
        actor_role="Employer",
        notes=f"Scheduled for {interview_date} at {interview_time} ({interview_type}). Interviewer: {interviewer_name}."
    )
    application.save()

    # Send Notification and Email
    Notification.objects.create(
        user=candidate_user,
        title=f"📅 Interview Scheduled: {application.job.title}",
        message=f"{employer.company_name} scheduled an interview for {application.job.title} on {interview_date} at {interview_time}.",
        type='application'
    )

    try:
        send_interview_scheduled_email(
            to_email=candidate_user.email,
            applicant_name=candidate_user.full_name or candidate_user.email,
            company_name=employer.company_name or 'Employer',
            job_title=application.job.title,
            interview_date=interview_date,
            interview_time=interview_time,
            interview_type=interview_type,
            location_or_link=location_or_link,
            interviewer_name=interviewer_name,
            instructions=instructions,
            additional_notes=notes
        )
    except Exception as e:
        print(f"[Schedule Interview API Mailer] Error: {e}")

    return JsonResponse({
        'success': True,
        'message': f"Interview scheduled successfully for {interview_date} at {interview_time}!",
        'interview_date': interview_date,
        'interview_time': interview_time,
        'interview_type': interview_type
    })


@require_role('employer')
def chatbot_review_view(request):
    """
    Employer view of applicant chatbot assessment answers.
    """
    user_id = getCurrentUserId(request)
    employer = get_object_or_404(Employer, user_id=user_id)

    applicant_id = request.GET.get('applicant_id')
    applicant = get_object_or_404(Applicant.objects.select_related('user'), pk=applicant_id) if applicant_id else None
    answers = ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number') if applicant else []

    context = {
        'page_title': 'Chatbot Skills Review - MultiBiz',
        'current_page': 'chatbot_review.php',
        'applicant': applicant,
        'answers': answers,
    }
    return render(request, 'employer/chatbot_review.html', context)


def user_obj_name(user):
    return user.full_name if user else 'Employer'
