import os
import datetime
from decimal import Decimal
from django.contrib import messages
from django.shortcuts import render, redirect, get_object_or_404
from django.http import JsonResponse, HttpResponseBadRequest, HttpResponseForbidden
from django.views.decorators.csrf import csrf_exempt
from django.db.models import Q, Count, Window
from django.conf import settings
from django.utils import timezone
from django.utils.dateparse import parse_date, parse_time
import json
from app.models import (
    User, Employer, Applicant, JobPosting, Application,
    JobQualificationMapping, Qualification, InterviewSchedule,
    CandidateFeedback, CandidateRecommendation, ChatbotAnswer,
    Notification, ContactInquiry
)
from app.auth_utils import (
    require_role,
    require_verified_employer,
    getCurrentUser,
    getCurrentUserId,
)
from app.services.ml_ranking import (
    calculate_candidate_ml_score,
    compute_job_match_result,
    compute_job_match_score,
)
from app.services.dashboard_counts import get_unread_message_count
from app.services.qualification import classify_match_score
from app.services.mailer import (
    send_application_status_update_email,
    send_talent_request_admin_notification,
    send_talent_request_employer_confirmation,
    send_interview_scheduled_email
)
from app.services.workflow import (
    close_job_and_notify_applicants as _close_job_and_notify_applicants,
    close_job_if_positions_filled as _close_job_if_positions_filled,
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
        'actor_name': actor_name,
        'actor_role': actor_role,
        'timestamp': timezone.now().strftime('%Y-%m-%d %H:%M'),
        'notes': notes or '',
    })
    application.status_history = history


def _is_employer_interview_stage(application):
    return (
        application.employer_status in ('for_interview', 'interview_completed')
        or application.status in ('interviewed', 'interview_completed', 'for_interview')
        or application.interviews.filter(status__in=('scheduled', 'completed')).exists()
    )


def _allowed_employer_statuses(application):
    if application.employer_status in ('hired', 'rejected') or application.status in (
        'accepted', 'hired', 'rejected', 'reviewed', 'not_qualified',
        'withdrawn', 'job_closed', 'offer_declined',
    ):
        return ()
    if application.employer_status in ('withdrawn', 'job_closed'):
        return ()
    if application.employer_status == 'offer_sent' or application.status == 'offer_sent':
        return ('rejected',)
    if _is_employer_interview_stage(application):
        return ('offer_sent', 'hired', 'rejected')
    if application.employer_status == 'qualified' or application.status == 'shortlisted':
        return ('for_interview', 'not_qualified', 'rejected')
    return (
        'qualified',
        'not_qualified',
        'rejected',
    )


def _normalize_employer_status(value):
    """Accept both legacy application-status values and internal employer-status values."""
    normalized = (value or '').strip()
    status_map = {
        'pending': 'for_review',
        'for_review': 'for_review',
        'shortlisted': 'qualified',
        'qualified': 'qualified',
        'reviewed': 'not_qualified',
        'not_qualified': 'not_qualified',
        'for_interview': 'for_interview',
        'interviewed': 'for_interview',
        'interview_completed': 'interview_completed',
        'offer_sent': 'offer_sent',
        'offer_declined': 'offer_declined',
        'accepted': 'hired',
        'hired': 'hired',
        'rejected': 'rejected',
    }
    return status_map.get(normalized, normalized)


def _notify_applicant_for_employer_application(application, title, message):
    Notification.objects.create(
        user=application.applicant.user,
        title=title,
        message=message,
        type='application',
    )


@require_role('employer')
def dashboard_view(request):
    """
    Complete Company/Employer Dashboard with real-time workflow statistics,
    recent job requests with status badges, and recently forwarded candidates.
    """
    user_id = getCurrentUserId(request)
    user = getCurrentUser(request)
    employer, _ = Employer.objects.get_or_create(user=user)
    _sync_admin_postings_to_employer(employer)

    # Fetch all job-status totals in one database round trip.
    job_metrics = JobPosting.objects.filter(employer=employer).aggregate(
        total=Count('pk'),
        active=Count('pk', filter=Q(status='active')),
        pending=Count('pk', filter=Q(status='pending')),
        approved=Count('pk', filter=Q(status='approved')),
        rejected=Count('pk', filter=Q(status='rejected')),
    )
    total_job_requests = job_metrics['total']
    active_jobs = job_metrics['active']
    pending_job_requests = job_metrics['pending']
    approved_job_requests = job_metrics['approved']
    rejected_job_requests = job_metrics['rejected']

    # Forwarded Candidates Metrics (Only applicants forwarded by Admin)
    forwarded_qs = Application.objects.filter(
        job__employer=employer,
        forwarded_to_employer=True
    ).select_related('job', 'applicant', 'applicant__user')

    applicant_metrics = forwarded_qs.aggregate(
        total=Count('pk'),
        for_review=Count('pk', filter=Q(employer_status='for_review')),
        qualified=Count('pk', filter=Q(employer_status='qualified')),
        not_qualified=Count('pk', filter=Q(employer_status='not_qualified')),
        for_interview=Count('pk', filter=Q(employer_status='for_interview')),
        interview_completed=Count('pk', filter=Q(employer_status='interview_completed')),
        hired=Count('pk', filter=Q(employer_status='hired')),
        rejected=Count('pk', filter=Q(employer_status='rejected')),
    )
    total_applicants = applicant_metrics['total']
    applicants_for_review = applicant_metrics['for_review']
    qualified_applicants = applicant_metrics['qualified']
    not_qualified_applicants = applicant_metrics['not_qualified']
    applicants_for_interview = applicant_metrics['for_interview']
    interview_completed_count = applicant_metrics['interview_completed']
    hired_applicants = applicant_metrics['hired']
    rejected_applicants = applicant_metrics['rejected']

    # Upcoming scheduled interviews
    upcoming_interviews_qs = InterviewSchedule.objects.filter(
        employer=employer,
        interview_date__gte=timezone.now().date(),
        status='scheduled'
    ).select_related(
        'application',
        'application__applicant',
        'application__applicant__user',
        'application__job',
    ).annotate(
        total_interviews=Window(expression=Count('pk')),
    ).order_by('interview_date', 'start_time')
    upcoming_interviews = list(upcoming_interviews_qs[:4])
    upcoming_interviews_count = (
        upcoming_interviews[0].total_interviews if upcoming_interviews else 0
    )

    # Recent Forwarded Applicants (6 latest)
    recent_candidates = []
    for app in forwarded_qs.order_by('-forwarded_at', '-applied_at')[:6]:
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        match_result = (
            compute_job_match_result(applicant, app.job)
            if applicant and app.job else {
                'match_score': None,
                'match_class': None,
                'qualification_status': 'unavailable',
            }
        )
        
        cand_dict = {
            'application_id': app.application_id,
            'first_name': user_obj.first_name if user_obj else '',
            'last_name': user_obj.last_name if user_obj else '',
            'email': user_obj.email if user_obj else '',
            'job_title': app.job.title if app.job else '',
            'job_id': app.job.job_id if app.job else 0,
            'match_score': match_result['match_score'],
            'qualification_status': match_result['qualification_status'],
            'employer_status': app.employer_status,
            'status': app.status,
            'forwarded_at': app.forwarded_at or app.applied_at,
            'admin_qualification': app.admin_qualification,
            'resume_file': app.resume_file or (applicant.resume_file if applicant else ''),
            'employability_score': float(applicant.employability_score) if applicant else 0.0,
            'experience_years': applicant.experience_years if applicant else 0,
            'education_level': applicant.education_level or 'Not specified' if applicant else 'Not specified',
            'ml_match_score': match_result['match_score'],
            'ml_match_class': match_result['match_class'],
        }
        score_info = calculate_candidate_ml_score(cand_dict)
        cand_dict['ml_ranking_score'] = score_info['ml_ranking_score']
        cand_dict['ranking_category'] = score_info['ranking_category']
        recent_candidates.append(cand_dict)

    # Recent Job Requests (5 latest)
    recent_job_requests = JobPosting.objects.filter(employer=employer).annotate(
        applicant_count=Count('applications', filter=Q(applications__forwarded_to_employer=True))
    ).order_by('-posted_at')[:5]

    unread_count = get_unread_message_count(user_id)

    # Match the dashboard template contract used by the employer portal.
    recent_applications = [
        {
            'application_id': app['application_id'],
            'first_name': app['first_name'],
            'last_name': app['last_name'],
            'email': app['email'],
            'job_title': app['job_title'],
            'match_score': app.get('match_score') or 0,
            'qualification_status': app.get('qualification_status') or 'unavailable',
            'employer_status': app.get('employer_status') or app.get('status') or 'for_review',
        }
        for app in recent_candidates
    ]

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
        'total_applications': total_applicants,
        'applicants_for_review': applicants_for_review,
        'qualified_applicants': qualified_applicants,
        'qualified_count': qualified_applicants,
        'not_qualified_applicants': not_qualified_applicants,
        'not_qualified_count': not_qualified_applicants,
        'applicants_for_interview': applicants_for_interview,
        'interview_completed_count': interview_completed_count,
        'hired_applicants': hired_applicants,
        'hired_count': hired_applicants,
        'rejected_applicants': rejected_applicants,
        'upcoming_interviews_count': upcoming_interviews_count,
        'upcoming_interviews': upcoming_interviews,
        'recent_candidates': recent_candidates,
        'recent_applications': recent_applications,
        'recent_job_requests': recent_job_requests,
        'unread_count': unread_count,
    }
    return render(request, 'employer/dashboard.html', context)


@require_role('employer')
def notifications_view(request):
    """Show the signed-in employer's notifications and support new-application polling."""
    employer_user_id = getCurrentUserId(request)

    if request.method == 'GET' and request.GET.get('poll') == 'applications':
        cursor_key = f'employer_application_notification_cursor_{employer_user_id}'
        notification_cursor = request.session.get(cursor_key)
        if notification_cursor is None:
            latest_notification_id = (
                Notification.objects.filter(user_id=employer_user_id)
                .order_by('-notification_id')
                .values_list('notification_id', flat=True)
                .first()
            ) or 0
            request.session[cursor_key] = latest_notification_id
            latest_unread_application = Notification.objects.filter(
                user_id=employer_user_id,
                type='application',
                is_read=False,
                notification_id__lte=latest_notification_id,
            ).order_by('-notification_id').first()
            new_notifications = (
                [latest_unread_application] if latest_unread_application else []
            )
        else:
            new_notifications = list(
                Notification.objects.filter(
                    user_id=employer_user_id,
                    type='application',
                    notification_id__gt=notification_cursor,
                ).order_by('notification_id')[:20]
            )
            if new_notifications:
                request.session[cursor_key] = new_notifications[-1].notification_id

        return JsonResponse({
            'notifications': [
                {
                    'notification_id': notification.notification_id,
                    'title': notification.title,
                    'message': notification.message or '',
                }
                for notification in new_notifications
            ],
            'unread_notification_count': Notification.objects.filter(
                user_id=employer_user_id,
                is_read=False,
            ).count(),
        })

    if request.method == 'POST':
        action = request.POST.get('action')
        if action == 'mark_all_read':
            Notification.objects.filter(
                user_id=employer_user_id,
                is_read=False,
            ).update(is_read=True)
        elif action == 'mark_read':
            notification_id = request.POST.get('notification_id', '')
            if not notification_id.isdigit():
                return HttpResponseBadRequest('A valid notification ID is required.')
            notification = get_object_or_404(
                Notification,
                pk=int(notification_id),
                user_id=employer_user_id,
            )
            if not notification.is_read:
                notification.is_read = True
                notification.save(update_fields=['is_read'])
        else:
            return HttpResponseBadRequest('Invalid notification action.')

        return redirect('employer_notifications')

    Notification.objects.filter(
        user_id=employer_user_id,
        is_read=False,
    ).update(is_read=True)
    notifications = Notification.objects.filter(
        user_id=employer_user_id,
    ).order_by('-created_at', '-notification_id')[:100]
    context = {
        'page_title': 'Notifications - MultiBiz',
        'current_page': 'notifications.php',
        'notifications': notifications,
        'unread_notification_count': Notification.objects.filter(
            user_id=employer_user_id,
            is_read=False,
        ).count(),
    }
    return render(request, 'admin/notifications.html', context)


@require_role('employer')
def post_job_view(request):
    """
    Company submits a Job Request to the Admin.
    Employer CANNOT directly publish jobs; status defaults to 'pending' awaiting Admin Review.
    """
    user_id = getCurrentUserId(request)
    user = getCurrentUser(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    error = None
    success = None
    form_data = {}

    if employer.verification_status != 'verified':
        context = {
            'page_title': 'Submit Job Request - MultiBiz',
            'current_page': 'post_job.php',
            'employer': employer,
            'user': user,
            'qualifications': Qualification.objects.filter(status='active').order_by('name'),
            'form_data': form_data,
            'error_msg': (
                'Your company verification was rejected. Contact the administrator to continue.'
                if employer.verification_status == 'rejected'
                else 'Your company is awaiting verification. You can post jobs after admin approval.'
            ),
            'success': None,
        }
        return render(request, 'employer/post_job.html', context)

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
            email_results = []
            try:
                email_results.append(send_talent_request_admin_notification(
                    inquiry_id=job.job_id,
                    company_name=job_specs['company_name'],
                    contact_name=user.full_name or employer.company_name,
                    contact_email=user.email,
                    job_title=title,
                    job_specs=job_specs
                ))
                email_results.append(send_talent_request_employer_confirmation(
                    to_email=user.email,
                    company_name=job_specs['company_name'],
                    contact_name=user.first_name or employer.company_name,
                    job_title=title
                ))
            except Exception as e:
                print(f"[Job Request Mailer] Error: {e}")
                email_results.append({'success': False})

            email_status = (
                '' if len(email_results) == 2
                and all(result.get('success') for result in email_results)
                else '&email_failed=1'
            )
            return redirect(f'/employer/jobs.php?submitted=success{email_status}')

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
            reason = request.POST.get('closure_reason', '').strip()
            if not reason:
                reason = 'Closed by employer.'
            _close_job_and_notify_applicants(job, reason)
            return redirect('/employer/jobs.php?closed=1')
        elif action == 'reopen_request' and job.status in ['closed', 'rejected']:
            job.status = 'pending'
            job.rejection_reason = None
            job.closure_reason = None
            job.save(update_fields=['status', 'rejection_reason', 'closure_reason', 'updated_at'])
            for admin_user in User.objects.filter(role='admin', status='active'):
                Notification.objects.create(
                    user=admin_user,
                    title=f'Job reopening request: {job.title}',
                    message=f'{employer.company_name or user_obj_name(admin_user)} requested to reopen {job.title}. Admin review is required.',
                    type='job_update',
                )
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
        'email_failed': request.GET.get('email_failed') == '1',
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
@require_verified_employer
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
    display_applications = []
    for app in apps_qs.order_by('-forwarded_at', '-applied_at'):
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        match_result = (
            compute_job_match_result(applicant, app.job)
            if applicant and app.job else {
                'match_score': None,
            }
        )
        app.match_score = match_result['match_score']
        app.qualification_status = (
            classify_match_score(app.match_score)
            if app.match_score is not None else 'unavailable'
        )
        app.match_class = {
            'qualified': 'high',
            'under_qualified': 'medium',
            'not_qualified': 'low',
            'unavailable': 'unavailable',
        }[app.qualification_status]
        display_applications.append(app)
        
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
            'match_score': match_result['match_score'],
            'qualification_status': match_result['qualification_status'],
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
            'ml_match_score': match_result['match_score'],
            'ml_match_class': match_result['match_class'],
        }
        score_info = calculate_candidate_ml_score(cand_dict)
        cand_dict['ml_ranking_score'] = score_info['ml_ranking_score']
        cand_dict['ranking_category'] = score_info['ranking_category']
        candidates.append(cand_dict)

    # Sort candidates by ML score
    candidates.sort(
        key=lambda x: (
            x['ml_ranking_score'] is not None,
            x['ml_ranking_score'] or 0,
        ),
        reverse=True,
    )

    jobs_for_filter = JobPosting.objects.filter(employer=employer).order_by('-posted_at')

    context = {
        'page_title': 'Applicants Review - MultiBiz Employer',
        'current_page': 'candidates.php',
        'employer': employer,
        'candidates': candidates,
        'applications': display_applications,
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
@require_verified_employer
def recommended_candidates_view(request):
    """AI Recommended Candidates matching employer/recommended_candidates.php."""
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    job_id = request.GET.get('job_id', '0')
    applicants = Applicant.objects.select_related('user').all()
    jobs = JobPosting.objects.filter(employer=employer, status='active')

    recommended_list = []
    for app in applicants:
        best_score = None
        best_job = None
        for j in jobs:
            if job_id and job_id.isdigit() and int(job_id) > 0 and j.job_id != int(job_id):
                continue
            sc = compute_job_match_score(app, j)
            if sc is not None and (best_score is None or sc > best_score):
                best_score = sc
                best_job = j

        if best_job:
            cand_dict = {
                'applicant': app,
                'user': app.user,
                'job': best_job,
                'match_score': best_score,
                'ml_match_score': best_score,
                'employability_score': float(app.employability_score),
                'experience_years': app.experience_years,
                'education_level': app.education_level or 'Not specified',
            }
            score_info = calculate_candidate_ml_score(cand_dict)
            cand_dict['ml_ranking_score'] = score_info['ml_ranking_score']
            cand_dict['ranking_category'] = score_info['ranking_category']
            recommended_list.append(cand_dict)

    recommended_list.sort(
        key=lambda x: (
            x['ml_ranking_score'] is not None,
            x['ml_ranking_score'] or 0,
        ),
        reverse=True,
    )

    context = {
        'page_title': 'AI Recommended Candidates - MultiBiz',
        'current_page': 'recommended_candidates.php',
        'recommended_list': recommended_list,
        'jobs': jobs,
        'selected_job_id': int(job_id) if job_id.isdigit() else 0,
    }
    return render(request, 'employer/recommended_candidates.html', context)


@require_role('employer')
@require_verified_employer
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
        forwarded_to_employer=True,
    )

    applicant = application.applicant
    candidate_user = applicant.user
    job = application.job

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action')

        if action == 'update_status':
            new_status = _normalize_employer_status(
                request.POST.get('employer_status', request.POST.get('status', '')),
            )
            employer_notes = request.POST.get(
                'employer_notes',
                request.POST.get('remarks', ''),
            ).strip()

            if new_status in ('rejected', 'not_qualified') and not employer_notes:
                error_msg = 'Add a reason before rejecting or marking the candidate not qualified.'
            elif new_status == 'offer_sent' and not employer_notes:
                error_msg = 'Add the offer details before sending it to the applicant.'
            elif new_status in _allowed_employer_statuses(application):
                application.employer_status = new_status
                if employer_notes:
                    application.employer_notes = employer_notes
                if new_status == 'offer_sent':
                    application.offer_details = employer_notes
                    application.offer_sent_at = timezone.now()
                
                # Sync overall application status
                if new_status == 'hired':
                    application.status = 'accepted'
                elif new_status == 'offer_sent':
                    application.status = 'offer_sent'
                elif new_status == 'interview_completed':
                    application.status = 'interview_completed'
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
                if new_status == 'hired':
                    _close_job_if_positions_filled(job)

                # In-App Notification
                notification_title = f"Application Update: {job.title}"
                notification_message = (
                    f"Your application status at {employer.company_name} "
                    f"was updated to '{status_label}'."
                )
                if new_status == 'hired':
                    applicant_name = candidate_user.full_name or candidate_user.email
                    notification_title = f"Congratulations, {applicant_name}!"
                    notification_message = (
                        f"{applicant_name} has been hired for {job.title} "
                        f"at {employer.company_name}."
                    )
                elif new_status == 'offer_sent':
                    notification_title = f'Job offer: {job.title}'
                    notification_message = f'{employer.company_name} sent you an offer. Review the details in your applications page.'
                _notify_applicant_for_employer_application(
                    application,
                    notification_title,
                    notification_message,
                )

                # Send email update
                try:
                    email_result = send_application_status_update_email(
                        to_email=candidate_user.email,
                        applicant_name=candidate_user.full_name or candidate_user.email,
                        job_title=job.title,
                        company_name=employer.company_name or 'Employer',
                        new_status=new_status,
                        remarks=employer_notes
                    )
                except Exception as e:
                    print(f"[Employer Status Update Mailer] Error: {e}")
                    email_result = {'success': False}

                if new_status == 'hired':
                    applicant_name = candidate_user.full_name or candidate_user.email
                    messages.success(
                        request,
                        f"{applicant_name} has been hired.",
                    )
                    return redirect('employer_dashboard')

                email_status = (
                    f"Email sent to {candidate_user.email}."
                    if email_result.get('success')
                    else "The applicant has an in-app notification, but email delivery failed."
                )
                success_msg = f"Candidate moved to '{status_label}' successfully! {email_status}"
            else:
                error_msg = 'That application stage transition is not allowed.'

        elif action == 'schedule_interview':
            interview_date = request.POST.get('interview_date', '').strip()
            start_time_val = request.POST.get('start_time', '').strip() or None
            end_time_val = request.POST.get('end_time', '').strip() or None
            interview_type = request.POST.get('interview_type', 'Online').strip()
            location_or_link = request.POST.get('location_or_link', '').strip()
            interviewer_name = request.POST.get('interviewer_name', '').strip()
            instructions = request.POST.get('instructions', '').strip()
            additional_notes = request.POST.get('notes', '').strip()
            parsed_date = parse_date(interview_date)
            parsed_start_time = parse_time(start_time_val) if start_time_val else None
            parsed_end_time = parse_time(end_time_val) if end_time_val else None

            if 'for_interview' not in _allowed_employer_statuses(application):
                error_msg = 'This application is not at a stage where an interview can be scheduled.'
            elif application.interviews.filter(status='scheduled').exists():
                error_msg = 'There is already an active interview scheduled for this application.'
            elif not parsed_date:
                error_msg = "Please provide a valid interview date."
            elif parsed_date < timezone.localdate():
                error_msg = 'Interview date must be today or later.'
            elif not parsed_start_time:
                error_msg = "Please provide a valid interview start time."
            elif not parsed_end_time:
                error_msg = "Please provide a valid interview end time."
            elif parsed_end_time <= parsed_start_time:
                error_msg = "Interview end time must be later than the start time."
            else:
                formatted_start_time = parsed_start_time.strftime('%I:%M %p')
                formatted_end_time = parsed_end_time.strftime('%I:%M %p')
                formatted_time = f'{formatted_start_time} - {formatted_end_time}'
                
                # Create Interview Schedule Record
                schedule = InterviewSchedule.objects.create(
                    application=application,
                    employer=employer,
                    interview_date=parsed_date,
                    interview_time=formatted_time,
                    start_time=parsed_start_time,
                    end_time=parsed_end_time,
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

                log_status_history_event(
                    application=application,
                    stage_title="Interview Scheduled by Employer",
                    status_key="for_interview",
                    actor_name=employer.company_name or 'Employer',
                    actor_role="Employer",
                    notes=f"Interview set for {interview_date} at {formatted_time} ({interview_type}). Interviewer: {interviewer_name or 'Hiring Team'}."
                )
                application.save()
                _notify_applicant_for_employer_application(
                    application,
                    f"Interview Scheduled: {job.title}",
                    f"{employer.company_name} scheduled an interview for {job.title} on {interview_date} at {formatted_time}.",
                )

                try:
                    email_result = send_interview_scheduled_email(
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
                    email_result = {'success': False}

                email_status = (
                    f"Invitation email sent to {candidate_user.email}."
                    if email_result.get('success')
                    else "The applicant has an in-app notification, but email delivery failed."
                )
                success_msg = f"Interview successfully scheduled for {interview_date} at {formatted_time}! {email_status}"

        elif action == 'manage_interview':
            interview = get_object_or_404(
                InterviewSchedule,
                pk=request.POST.get('interview_id'),
                application=application,
                employer=employer,
            )
            decision = request.POST.get('decision', '').strip()
            now = timezone.now()
            if interview.status != 'scheduled':
                error_msg = 'Only scheduled interviews can be changed.'
            elif decision == 'complete':
                interview.status = 'completed'
                interview.save(update_fields=['status', 'updated_at'])
                application.employer_status = 'interview_completed'
                application.status = 'interview_completed'
                log_status_history_event(
                    application,
                    'Interview Completed',
                    'interview_completed',
                    employer.company_name or 'Employer',
                    'Employer',
                )
                application.save(update_fields=[
                    'employer_status', 'status', 'status_history', 'updated_at',
                ])
                _notify_applicant_for_employer_application(
                    application,
                    f'Interview completed: {job.title}',
                    f'Your interview for {job.title} has been marked complete.',
                )
                success_msg = 'Interview marked complete.'
            elif decision == 'cancel':
                interview.status = 'cancelled'
                interview.save(update_fields=['status', 'updated_at'])
                application.employer_status = 'qualified'
                application.status = 'shortlisted'
                log_status_history_event(
                    application,
                    'Interview Cancelled by Employer',
                    'qualified',
                    employer.company_name or 'Employer',
                    'Employer',
                    request.POST.get('notes', '').strip(),
                )
                application.save(update_fields=[
                    'employer_status', 'status', 'status_history', 'updated_at',
                ])
                _notify_applicant_for_employer_application(
                    application,
                    f'Interview cancelled: {job.title}',
                    'The employer cancelled the interview. They may schedule a new time.',
                )
                success_msg = 'Interview cancelled.'
            elif decision == 'reschedule':
                parsed_date = parse_date(request.POST.get('interview_date', '').strip())
                parsed_start = parse_time(request.POST.get('start_time', '').strip())
                parsed_end = parse_time(request.POST.get('end_time', '').strip())
                if not parsed_date or parsed_date < timezone.localdate() or not parsed_start or not parsed_end or parsed_end <= parsed_start:
                    error_msg = 'Provide a valid reschedule date and time range.'
                else:
                    interview.interview_date = parsed_date
                    interview.start_time = parsed_start
                    interview.end_time = parsed_end
                    interview.interview_time = (
                        f'{parsed_start.strftime("%I:%M %p")} - '
                        f'{parsed_end.strftime("%I:%M %p")}'
                    )
                    interview.applicant_response = 'pending'
                    interview.applicant_response_notes = ''
                    interview.applicant_responded_at = None
                    interview.reminder_sent_at = None
                    interview.overdue_notified_at = None
                    interview.save(update_fields=[
                        'interview_date', 'start_time', 'end_time', 'interview_time',
                        'applicant_response', 'applicant_response_notes',
                        'applicant_responded_at', 'reminder_sent_at',
                        'overdue_notified_at', 'updated_at',
                    ])
                    log_status_history_event(
                        application,
                        'Interview Rescheduled',
                        'for_interview',
                        employer.company_name or 'Employer',
                        'Employer',
                    )
                    application.save(update_fields=['status_history', 'updated_at'])
                    _notify_applicant_for_employer_application(
                        application,
                        f'Interview rescheduled: {job.title}',
                        f'Your interview is now scheduled for {parsed_date} at {interview.interview_time}.',
                    )
                    success_msg = 'Interview rescheduled and applicant notified.'
            else:
                error_msg = 'Choose a valid interview action.'

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
    interviews = InterviewSchedule.objects.filter(application=application).order_by('-interview_date', '-created_at')
    interview_stage = (
        application.employer_status in ('for_interview', 'interview_completed')
        or application.status in ('interviewed', 'interview_completed', 'for_interview')
        or interviews.exists()
    )
    can_schedule_interview = 'for_interview' in _allowed_employer_statuses(application)
    
    # Chatbot assessment answers
    chatbot_answers = ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number')

    match_result = compute_job_match_result(applicant, job)
    application.match_score = match_result['match_score']
    cand_dict = {
        'ml_match_score': match_result['match_score'],
        'ml_match_class': match_result['match_class'],
    }
    score_info = calculate_candidate_ml_score(cand_dict)

    context = {
        'page_title': f"Candidate: {candidate_user.full_name} - MultiBiz",
        'current_page': 'candidates.php',
        'application': application,
        'qualification_status': match_result['qualification_status'],
        'applicant': applicant,
        'candidate_user': candidate_user,
        'job': job,
        'employer': employer,
        'interviews': interviews,
        'interview_stage': interview_stage,
        'can_schedule_interview': can_schedule_interview,
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
@require_verified_employer
def update_status_api(request):
    """
    AJAX endpoint for candidate status update from the Employer portal.
    """
    user_id = getCurrentUserId(request)
    employer = get_object_or_404(Employer, user_id=user_id)

    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'Invalid method'})

    app_id = request.POST.get('application_id')
    requested_status = request.POST.get('employer_status', request.POST.get('status', '')).strip()
    status = _normalize_employer_status(requested_status)
    notes = request.POST.get('notes', '').strip() or request.POST.get('remarks', '').strip()

    application = get_object_or_404(
        Application,
        pk=app_id,
        job__employer=employer,
        forwarded_to_employer=True,
    )
    valid_statuses = _allowed_employer_statuses(application)

    if status in valid_statuses:
        if status in ('rejected', 'not_qualified') and not notes:
            return JsonResponse({'success': False, 'message': 'Add a reason before rejecting or marking the candidate not qualified.'}, status=400)
        if status == 'offer_sent' and not notes:
            return JsonResponse({'success': False, 'message': 'Add the offer details before sending it to the applicant.'}, status=400)
        application.employer_status = status
        if notes:
            application.employer_notes = notes
        if status == 'offer_sent':
            application.offer_details = notes
            application.offer_sent_at = timezone.now()

        if status == 'hired':
            application.status = 'accepted'
        elif status == 'offer_sent':
            application.status = 'offer_sent'
        elif status == 'interview_completed':
            application.status = 'interview_completed'
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
        if status == 'hired':
            _close_job_if_positions_filled(application.job)

        # In-App Notification and Email
        cand_user = application.applicant.user if (application.applicant and application.applicant.user) else None
        email_result = {'success': False}
        if cand_user:
            notification_title = f"Application Update: {application.job.title}"
            notification_message = f"Your application status at {employer.company_name} was updated to '{status_label}'."
            if status == 'offer_sent':
                notification_title = f'Job offer: {application.job.title}'
                notification_message = f'{employer.company_name} sent you an offer. Review the details in your applications page.'
            _notify_applicant_for_employer_application(
                application,
                notification_title,
                notification_message,
            )
            try:
                email_result = send_application_status_update_email(
                    to_email=cand_user.email,
                    applicant_name=cand_user.full_name or cand_user.email,
                    job_title=application.job.title,
                    company_name=employer.company_name or 'Employer',
                    new_status=status,
                    remarks=notes
                )
            except Exception as e:
                print(f"[update_status_api Mailer] Error: {e}")
                email_result = {'success': False}

        return JsonResponse({
            'success': True,
            'message': (
                f'Status updated to {status_label}.'
                if email_result.get('success')
                else f'Status updated to {status_label}; in-app notification sent, but email delivery failed.'
            ),
            'email_sent': bool(email_result.get('success')),
            'new_status': status,
        })

    return JsonResponse({'success': False, 'message': 'Invalid status'})


@csrf_exempt
@require_role('employer')
@require_verified_employer
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

    application = get_object_or_404(
        Application,
        pk=app_id,
        job__employer=employer,
        forwarded_to_employer=True,
    )
    if 'for_interview' not in _allowed_employer_statuses(application):
        return JsonResponse({'success': False, 'message': 'This application is not at a stage where an interview can be scheduled.'}, status=400)
    if application.interviews.filter(status='scheduled').exists():
        return JsonResponse({'success': False, 'message': 'There is already an active interview scheduled for this application.'}, status=400)
    parsed_interview_date = parse_date(interview_date)
    if not parsed_interview_date or parsed_interview_date < timezone.localdate():
        return JsonResponse({'success': False, 'message': 'Interview date must be today or later.'}, status=400)
    parsed_start_time = parse_time(interview_time)
    if not parsed_start_time:
        time_candidates = interview_time.split(' - ', 1)
        for time_candidate in time_candidates:
            try:
                parsed_start_time = datetime.datetime.strptime(
                    time_candidate.strip(), '%I:%M %p'
                ).time()
                break
            except ValueError:
                continue
    candidate_user = application.applicant.user

    # Create interview schedule
    InterviewSchedule.objects.create(
        application=application,
        employer=employer,
        interview_date=parsed_interview_date,
        interview_time=interview_time,
        start_time=parsed_start_time,
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
    _notify_applicant_for_employer_application(
        application,
        f"📅 Interview Scheduled: {application.job.title}",
        f"{employer.company_name} scheduled an interview for {application.job.title} on {interview_date} at {interview_time}.",
    )

    try:
        email_result = send_interview_scheduled_email(
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
        email_result = {'success': False}

    return JsonResponse({
        'success': True,
        'message': (
            f"Interview scheduled successfully for {interview_date} at {interview_time}!"
            if email_result.get('success')
            else f"Interview scheduled for {interview_date} at {interview_time}; "
                 "the applicant has an in-app notification, but email delivery failed."
        ),
        'email_sent': bool(email_result.get('success')),
        'interview_date': interview_date,
        'interview_time': interview_time,
        'interview_type': interview_type
    })


@require_role('employer')
@require_verified_employer
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
