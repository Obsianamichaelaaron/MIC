import os
import time
import json
from decimal import Decimal
from datetime import date, timedelta
from django.shortcuts import render, redirect, get_object_or_404
from django.http import JsonResponse, HttpResponseForbidden
from django.views.decorators.csrf import csrf_exempt
from django.db.models import Q, Count, Avg
from django.conf import settings
from django.utils import timezone
from app.models import (
    User, Employer, Applicant, JobPosting, Application,
    JobQualificationMapping, Qualification, InterviewSchedule,
    CandidateFeedback, CandidateRecommendation, ChatbotAnswer,
    ChatbotRecommendation, ResumeAnalysis, Notification,
    ContactInquiry, ContactReply, AuditTrail, CmsHeroSlide,
    CmsTestimonial, CmsBrand, CmsNews, CmsSection, CmsContent
)
from app.auth_utils import require_role, getCurrentUserId, hash_password
from app.services.audit_trail import log_audit_trail
from app.services.mailer import (
    send_contact_reply, send_application_status_update_email,
    send_job_posted_live_notification
)
from app.services.ml_ranking import calculate_candidate_ml_score, compute_job_match_score

LANDING_CMS_DEFAULTS = {
    'hero': {
        'eyebrow': 'Trusted Since 2002',
        'secondary_button_text': 'Our Services',
        'secondary_button_link': 'services.php',
    },
    'stats': {
        'years': '23+',
        'years_label': 'Years of Excellence',
        'clients': '500+',
        'clients_label': 'Corporate Clients',
        'efficiency': '95%',
        'efficiency_label': 'Efficiency Rating',
        'units': '3K+',
        'units_label': 'Units Maintained',
    },
    'partners_title': {
        'label': 'Trusted Partners',
        'title': 'Our Partner Brands',
    },
    'testimonials_title': {
        'label': 'Client Success',
        'title': 'What Our Clients Say',
        'subtitle': 'Discover why leading organizations across the Philippines trust MULTIBIZ for their managed services needs.',
    },
    'contact': {
        'label': 'Get In Touch',
        'title': "Let's Start a Conversation",
        'subtitle': 'Have questions or ready to get started? Our team is here to help you find the right solution.',
        'website': 'https://multibiz.global',
        'phone': '+63 917 544 1674',
        'email': 'inquiry@multibiz.global',
    },
    'news': {
        'label': 'Latest Updates',
        'title': 'Events',
    },
    'featured_jobs_title': {
        'label': 'Opportunities',
        'title': 'Featured Job Openings',
        'subtitle': 'Discover exciting career opportunities posted by our trusted employer network.',
    },
    'footer': {
        'brand_name': 'MULTIBIZ INTERNATIONAL',
        'description': 'Your trusted career matching platform and managed services partner. Connecting talented professionals with leading employers across the Philippines.',
        'copyright': '2023 MULTIBIZ INTERNATIONAL CORPORATION. All Rights Reserved.',
    },
    'seo': {
        'title': 'MULTIBIZ INTERNATIONAL CORPORATION',
        'description': 'MULTIBIZ INTERNATIONAL CORPORATION - Your Career Matching Platform',
        'keywords': 'MULTIBIZ INTERNATIONAL CORPORATION',
    },
}


def _ensure_landing_cms_content():
    for section_key, fields in LANDING_CMS_DEFAULTS.items():
        section, _ = CmsSection.objects.get_or_create(
            section_key=section_key,
            defaults={'section_name': section_key.replace('_', ' ').title(), 'content_type': 'text'},
        )
        for sort_order, (field_key, default_value) in enumerate(fields.items()):
            CmsContent.objects.get_or_create(
                section=section,
                field_key=field_key,
                defaults={'field_value': default_value, 'sort_order': sort_order},
            )


@require_role('admin')
def dashboard_view(request):
    """
    Admin Dashboard matching admin/dashboard.php.
    """
    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)

    total_users = User.objects.count()
    total_applicants = User.objects.filter(role='applicant').count()
    total_employers = User.objects.filter(role='employer').count()
    total_jobs = JobPosting.objects.count()
    active_jobs = JobPosting.objects.filter(status='active').count()
    total_applications = Application.objects.count()
    unread_inquiries = ContactInquiry.objects.filter(is_read=False).count()
    total_audit_logs = AuditTrail.objects.count()
    thirty_days_ago = timezone.now() - timedelta(days=30)
    avg_match_score = Application.objects.aggregate(value=Avg('match_score'))['value'] or 0
    new_users_30d = User.objects.filter(created_at__gte=thirty_days_ago).count()
    new_apps_30d = Application.objects.filter(applied_at__gte=thirty_days_ago).count()
    total_admins = User.objects.filter(role='admin').count()
    elite_matches = Application.objects.filter(match_score__gte=85).count()
    strong_matches = Application.objects.filter(
        match_score__gte=70,
        match_score__lt=85,
    ).count()

    month_starts = []
    month_cursor = timezone.now().replace(day=1, hour=0, minute=0, second=0, microsecond=0)
    for _ in range(6):
        month_starts.append(month_cursor)
        month_cursor = (month_cursor - timedelta(days=1)).replace(day=1)
    month_starts.reverse()
    trend_users = []
    trend_apps = []
    for month_start in month_starts:
        next_month = (month_start + timedelta(days=32)).replace(day=1)
        trend_users.append(User.objects.filter(
            created_at__gte=month_start,
            created_at__lt=next_month,
        ).count())
        trend_apps.append(Application.objects.filter(
            applied_at__gte=month_start,
            applied_at__lt=next_month,
        ).count())

    chart_data = {
        'trend_labels': [month.strftime('%b') for month in month_starts],
        'trend_users': trend_users,
        'trend_apps': trend_apps,
        'user_role_labels': ['Applicants', 'Administrators'],
        'user_role_data': [total_applicants, total_admins],
        'pipeline_labels': ['Pending Review', 'Reviewed', 'Shortlisted', 'Interview Scheduled', 'Accepted / Hired', 'Rejected'],
        'pipeline_data': [
            Application.objects.filter(status='pending').count(),
            Application.objects.filter(status='reviewed').count(),
            Application.objects.filter(status='shortlisted').count(),
            Application.objects.filter(status='interviewed').count(),
            Application.objects.filter(status='accepted').count(),
            Application.objects.filter(status='rejected').count(),
        ],
        'job_type_labels': ['Full-Time', 'Part-Time', 'Contract', 'Internship'],
        'job_type_data': [
            JobPosting.objects.filter(employment_type='full-time').count(),
            JobPosting.objects.filter(employment_type='part-time').count(),
            JobPosting.objects.filter(employment_type='contract').count(),
            JobPosting.objects.filter(employment_type='internship').count(),
        ],
        'match_tier_labels': ['Elite Match (85-100%)', 'Strong Fit (70-84%)', 'Moderate (50-69%)', 'Developing (<50%)'],
        'match_tier_data': [
            Application.objects.filter(match_score__gte=85).count(),
            Application.objects.filter(match_score__gte=70, match_score__lt=85).count(),
            Application.objects.filter(match_score__gte=50, match_score__lt=70).count(),
            Application.objects.filter(match_score__lt=50).count(),
        ],
    }

    recent_users = User.objects.order_by('-created_at')[:5]
    recent_jobs = JobPosting.objects.select_related('employer').order_by('-posted_at')[:5]
    recent_inquiries = ContactInquiry.objects.order_by('-created_at')[:5]
    recent_audit_logs = AuditTrail.objects.order_by('-created_at')[:5]

    stats = {
        'total_users': total_users,
        'total_applicants': total_applicants,
        'total_employers': total_employers,
        'total_jobs': total_jobs,
        'active_jobs': active_jobs,
        'total_applications': total_applications,
    }

    formatted_jobs = []
    for j in recent_jobs:
        formatted_jobs.append({
            'job_id': j.job_id,
            'title': j.title,
            'company_name': j.employer.company_name if j.employer else 'MultiBiz Partner',
            'status': j.status,
            'posted_at': j.posted_at,
        })

    context = {
        'page_title': 'Admin Dashboard - MultiBiz',
        'current_page': 'dashboard.php',
        'admin_user': admin_user,
        'stats': stats,
        'total_users': total_users,
        'total_applicants': total_applicants,
        'total_employers': total_employers,
        'total_jobs': total_jobs,
        'active_jobs': active_jobs,
        'total_applications': total_applications,
        'unread_inquiries': unread_inquiries,
        'total_audit_logs': total_audit_logs,
        'avg_match_score': round(float(avg_match_score), 2),
        'new_users_30d': new_users_30d,
        'new_apps_30d': new_apps_30d,
        'total_admins': total_admins,
        'pending_inquiries': unread_inquiries,
        'elite_matches': elite_matches,
        'strong_matches': strong_matches,
        'chart_data_json': json.dumps(chart_data),
        'recent_users': recent_users,
        'recent_jobs': formatted_jobs,
        'recent_inquiries': recent_inquiries,
        'recent_audit_logs': recent_audit_logs,
    }
    return render(request, 'admin/dashboard.html', context)


@require_role('admin')
def users_view(request):
    """
    User Management matching admin/users.php.
    """
    admin_id = getCurrentUserId(request)
    
    search = request.GET.get('search', '').strip()
    role_filter = request.GET.get('role', '')
    status_filter = request.GET.get('status', '')

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action')

        if action == 'create_employer':
            email = request.POST.get('email', '').strip()
            password = request.POST.get('password', '')
            first_name = request.POST.get('first_name', '').strip()
            last_name = request.POST.get('last_name', '').strip()
            phone = request.POST.get('phone', '').strip()
            company_name = request.POST.get('company_name', '').strip()
            industry = request.POST.get('industry', '').strip()
            company_address = request.POST.get('company_address', '').strip()
            company_website = request.POST.get('company_website', '').strip()

            if not email or not password or not company_name:
                error_msg = "Please provide email, password, and company name."
            elif User.objects.filter(email=email).exists():
                error_msg = "Email already in use."
            else:
                hashed = hash_password(password)
                new_user = User.objects.create(
                    email=email,
                    password=hashed,
                    role='employer',
                    first_name=first_name,
                    last_name=last_name,
                    phone=phone,
                    status='active',
                    created_by_admin=True
                )
                Employer.objects.create(
                    user=new_user,
                    company_name=company_name,
                    industry=industry,
                    company_address=company_address,
                    company_website=company_website
                )
                log_audit_trail(request, admin_id, 'create_employer', f"Created employer account for {company_name} ({email})", 'employer', new_user.user_id, company_name)
                success_msg = f"Employer account for {company_name} created successfully!"

        elif action == 'create_admin':
            email = request.POST.get('email', '').strip()
            password = request.POST.get('password', '')
            first_name = request.POST.get('first_name', '').strip()
            last_name = request.POST.get('last_name', '').strip()

            if not email or not password:
                error_msg = "Please provide email and password."
            elif User.objects.filter(email=email).exists():
                error_msg = "Email already in use."
            else:
                hashed = hash_password(password)
                new_admin = User.objects.create(
                    email=email,
                    password=hashed,
                    role='admin',
                    first_name=first_name,
                    last_name=last_name,
                    status='active',
                    created_by_admin=True
                )
                log_audit_trail(request, admin_id, 'create_admin', f"Created admin account ({email})", 'admin', new_admin.user_id, email)
                success_msg = f"Admin account {email} created successfully!"

        elif action == 'toggle_status':
            target_id = request.POST.get('user_id')
            new_status = request.POST.get('status')
            user_to_update = get_object_or_404(User, pk=target_id)
            user_to_update.status = new_status
            user_to_update.save()
            log_audit_trail(request, admin_id, 'update_user_status', f"Changed status of user {user_to_update.email} to {new_status}", 'user', user_to_update.user_id, user_to_update.email)
            success_msg = f"User status updated to {new_status}."

        elif action == 'change_password':
            target_id = request.POST.get('user_id')
            new_pass = request.POST.get('new_password', '')
            if len(new_pass) >= 6:
                user_to_update = get_object_or_404(User, pk=target_id)
                user_to_update.password = hash_password(new_pass)
                user_to_update.save()
                log_audit_trail(request, admin_id, 'change_password', f"Reset password for user {user_to_update.email}", 'user', user_to_update.user_id, user_to_update.email)
                success_msg = f"Password updated for {user_to_update.email}."
            else:
                error_msg = "Password must be at least 6 characters."

        elif action == 'delete_user':
            target_id = request.POST.get('user_id')
            user_to_delete = get_object_or_404(User, pk=target_id)
            if user_to_delete.user_id != admin_id:
                email_deleted = user_to_delete.email
                user_to_delete.delete()
                log_audit_trail(request, admin_id, 'delete_user', f"Deleted user {email_deleted}", 'user', int(target_id), email_deleted)
                success_msg = f"User {email_deleted} deleted."
            else:
                error_msg = "Cannot delete your own admin account."

    users_qs = User.objects.all().order_by('-created_at')

    if search:
        users_qs = users_qs.filter(
            Q(email__icontains=search) |
            Q(first_name__icontains=search) |
            Q(last_name__icontains=search) |
            Q(phone__icontains=search)
        )

    if role_filter:
        users_qs = users_qs.filter(role=role_filter)

    if status_filter:
        users_qs = users_qs.filter(status=status_filter)

    context = {
        'page_title': 'User Management - MultiBiz',
        'current_page': 'users.php',
        'users': users_qs,
        'search': search,
        'role_filter': role_filter,
        'status_filter': status_filter,
        'success_msg': success_msg,
        'error_msg': error_msg,
    }
    return render(request, 'admin/users.html', context)


def _get_or_create_employer_company(company_name: str, admin_user: User) -> Employer:
    clean_name = company_name.strip() if company_name else 'MultiBiz Partner'
    emp = Employer.objects.filter(company_name__iexact=clean_name).first()
    if not emp:
        emp = Employer.objects.create(
            user=admin_user,
            company_name=clean_name,
            industry='Recruitment Partner'
        )
    return emp


@require_role('admin')
def post_job_view(request):
    """
    Admin Post Job matching admin/post_job.php.
    Allows admin to post job vacancies on behalf of client companies.
    """
    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)

    error = None
    if request.method == 'POST':
        company_name = request.POST.get('company_name', '').strip()
        title = request.POST.get('title', '').strip()
        description = request.POST.get('description', '').strip()
        requirements = request.POST.get('requirements', '').strip()
        skills_required = request.POST.get('skills_required', '').strip()
        location = request.POST.get('location', '').strip()
        employment_type = request.POST.get('employment_type', 'full-time')
        salary_range = request.POST.get('salary_range', '').strip()
        status = request.POST.get('status', 'active')
        qualification_ids = request.POST.getlist('qualifications')

        if not title:
            error = "Job title is required."
        else:
            employer = _get_or_create_employer_company(company_name, admin_user)
            target_quals_str = ','.join(qualification_ids) if qualification_ids else ''

            job = JobPosting.objects.create(
                employer=employer,
                title=title,
                description=description,
                requirements=requirements,
                skills_required=skills_required,
                location=location,
                employment_type=employment_type,
                salary_range=salary_range,
                status=status,
                target_qualifications=target_quals_str
            )

            for q_id in qualification_ids:
                if str(q_id).isdigit():
                    q_obj = Qualification.objects.filter(pk=int(q_id)).first()
                    if q_obj:
                        JobQualificationMapping.objects.create(job=job, qualification=q_obj)

            log_audit_trail(request, admin_id, 'create_job', f"Posted new job: {title} for {company_name or 'MultiBiz Partner'}", 'job', job.job_id, title)
            return redirect('/admin/jobs.php?posted=success')

    qualifications = Qualification.objects.filter(status='active').order_by('name')
    existing_companies = list(Employer.objects.values_list('company_name', flat=True).distinct())

    context = {
        'page_title': 'Post a Job - MultiBiz Admin',
        'current_page': 'post_job.php',
        'qualifications': qualifications,
        'existing_companies': [c for c in existing_companies if c],
        'error': error,
    }
    return render(request, 'admin/post_job.html', context)


@require_role('admin')
def edit_job_view(request, job_id=None):
    """
    Admin Edit Job matching admin/edit_job.php.
    """
    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)

    j_id = job_id or request.GET.get('id') or request.GET.get('job_id')
    job = get_object_or_404(JobPosting.objects.select_related('employer'), pk=j_id)

    error = None
    if request.method == 'POST':
        company_name = request.POST.get('company_name', '').strip()
        title = request.POST.get('title', '').strip()
        description = request.POST.get('description', '').strip()
        requirements = request.POST.get('requirements', '').strip()
        skills_required = request.POST.get('skills_required', '').strip()
        location = request.POST.get('location', '').strip()
        employment_type = request.POST.get('employment_type', 'full-time')
        salary_range = request.POST.get('salary_range', '').strip()
        status = request.POST.get('status', 'active')
        qualification_ids = request.POST.getlist('qualifications')

        if not title:
            error = "Job title is required."
        else:
            employer = _get_or_create_employer_company(company_name, admin_user)
            job.employer = employer
            job.title = title
            job.description = description
            job.requirements = requirements
            job.skills_required = skills_required
            job.location = location
            job.employment_type = employment_type
            job.salary_range = salary_range
            job.status = status
            job.target_qualifications = ','.join(qualification_ids) if qualification_ids else ''
            job.save()

            JobQualificationMapping.objects.filter(job=job).delete()
            for q_id in qualification_ids:
                if str(q_id).isdigit():
                    q_obj = Qualification.objects.filter(pk=int(q_id)).first()
                    if q_obj:
                        JobQualificationMapping.objects.create(job=job, qualification=q_obj)

            log_audit_trail(request, admin_id, 'edit_job', f"Edited job: {title}", 'job', job.job_id, title)
            return redirect('/admin/jobs.php?updated=success')

    qualifications = Qualification.objects.filter(status='active').order_by('name')
    selected_qual_ids = list(job.qualification_mappings.values_list('qualification_id', flat=True))
    existing_companies = list(Employer.objects.values_list('company_name', flat=True).distinct())

    context = {
        'page_title': f"Edit {job.title} - MultiBiz Admin",
        'current_page': 'jobs.php',
        'job': job,
        'qualifications': qualifications,
        'selected_qual_ids': selected_qual_ids,
        'existing_companies': [c for c in existing_companies if c],
        'error': error,
    }
    return render(request, 'admin/edit_job.html', context)


@require_role('admin')
def jobs_view(request):
    """
    All Jobs Moderation & Management matching admin/jobs.php.
    """
    admin_id = getCurrentUserId(request)
    search = request.GET.get('search', '').strip()
    status_filter = request.GET.get('status', '')

    if request.method == 'POST':
        action = request.POST.get('action')
        job_id = request.POST.get('job_id')
        job = get_object_or_404(JobPosting, pk=job_id)

        if action == 'toggle_status':
            job.status = 'closed' if job.status == 'active' else 'active'
            job.save()
            log_audit_trail(request, admin_id, 'toggle_job_status', f"Changed status of job {job.title} to {job.status}", 'job', job.job_id, job.title)
        elif action == 'delete_job':
            title = job.title
            job.delete()
            log_audit_trail(request, admin_id, 'delete_job', f"Deleted job {title}", 'job', int(job_id), title)
        return redirect('/admin/jobs.php')

    jobs_qs = JobPosting.objects.select_related('employer').annotate(
        applicant_count=Count('applications')
    ).order_by('-posted_at')

    if search:
        jobs_qs = jobs_qs.filter(
            Q(title__icontains=search) |
            Q(employer__company_name__icontains=search) |
            Q(location__icontains=search) |
            Q(skills_required__icontains=search)
        )

    if status_filter:
        jobs_qs = jobs_qs.filter(status=status_filter)

    context = {
        'page_title': 'All Jobs - MultiBiz',
        'current_page': 'jobs.php',
        'jobs': jobs_qs,
        'search': search,
        'status_filter': status_filter,
        'posted_success': request.GET.get('posted') == 'success',
        'updated_success': request.GET.get('updated') == 'success',
    }
    return render(request, 'admin/jobs.html', context)


@require_role('admin')
def candidates_view(request):
    """
    AI-Ranked Candidates Pipeline & Kanban Board matching admin/candidates.php.
    Allows admin to review applicant resumes, match scores, and submissions across all jobs.
    """
    job_id = request.GET.get('job_id', '0')
    search = request.GET.get('search', '').strip()
    status_filter = request.GET.get('status', '')

    apps_qs = Application.objects.select_related(
        'applicant', 'applicant__user', 'job', 'job__employer'
    ).order_by('-applied_at')

    if job_id and job_id.isdigit() and int(job_id) > 0:
        apps_qs = apps_qs.filter(job_id=int(job_id))

    if search:
        apps_qs = apps_qs.filter(
            Q(applicant__user__first_name__icontains=search) |
            Q(applicant__user__last_name__icontains=search) |
            Q(applicant__user__email__icontains=search) |
            Q(job__title__icontains=search) |
            Q(job__employer__company_name__icontains=search)
        )

    if status_filter:
        apps_qs = apps_qs.filter(status=status_filter)

    candidates = []
    for app in apps_qs:
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        if not user_obj:
            continue

        cand_dict = {
            'application_id': app.application_id,
            'status': app.status,
            'match_score': float(app.match_score),
            'applied_at': app.applied_at,
            'applicant_id': applicant.applicant_id,
            'employability_score': float(applicant.employability_score),
            'experience_years': applicant.experience_years,
            'education_level': applicant.education_level or 'Not specified',
            'first_name': user_obj.first_name or '',
            'last_name': user_obj.last_name or '',
            'email': user_obj.email,
            'phone': user_obj.phone or '',
            'job_id': app.job.job_id if app.job else 0,
            'job_title': app.job.title if app.job else '',
            'company_name': (app.job.employer.company_name if (app.job and app.job.employer) else '') or 'MultiBiz Partner',
            'resume_file': app.resume_file or applicant.resume_file or '',
        }

        score_info = calculate_candidate_ml_score(cand_dict)
        cand_dict['ml_ranking_score'] = score_info['ml_ranking_score']
        cand_dict['ranking_category'] = score_info['ranking_category']
        candidates.append(cand_dict)

    candidates.sort(key=lambda x: x['ml_ranking_score'], reverse=True)

    excellent_candidates = [c for c in candidates if c['ranking_category'] == 'excellent']
    good_candidates = [c for c in candidates if c['ranking_category'] == 'good']
    average_candidates = [c for c in candidates if c['ranking_category'] == 'average']
    poor_candidates = [c for c in candidates if c['ranking_category'] == 'poor']

    jobs_for_filter = JobPosting.objects.select_related('employer').order_by('-posted_at')

    context = {
        'page_title': 'Candidate Pipeline - MultiBiz Admin',
        'current_page': 'candidates.php',
        'candidates': candidates,
        'excellent_candidates': excellent_candidates,
        'good_candidates': good_candidates,
        'average_candidates': average_candidates,
        'poor_candidates': poor_candidates,
        'jobs': jobs_for_filter,
        'selected_job_id': int(job_id) if job_id.isdigit() else 0,
        'search': search,
        'status_filter': status_filter,
        'total_candidates': len(candidates),
        'pending_count': sum(1 for c in candidates if c['status'] == 'pending'),
        'accepted_count': sum(1 for c in candidates if c['status'] == 'accepted'),
        'rejected_count': sum(1 for c in candidates if c['status'] == 'rejected'),
        'interviewed_count': sum(1 for c in candidates if c['status'] == 'interviewed'),
    }
    return render(request, 'admin/candidates.html', context)


@require_role('admin')
def view_candidate_view(request, application_id=None):
    """
    Candidate Dossier Review, Status Updates & Interview Scheduling matching admin/view_candidate.php.
    Allows admin to:
    - Review resume, skills, answers, scores
    - Update status (Under Review, Shortlisted, Interviewed, Accepted/Approved, Rejected) + remarks
    - Schedule interview + remarks
    - Automatically sends email to candidate!
    """
    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)

    app_id = application_id or request.GET.get('id') or request.GET.get('application_id')
    application = get_object_or_404(
        Application.objects.select_related('applicant', 'applicant__user', 'job', 'job__employer'),
        pk=app_id
    )

    applicant = application.applicant
    candidate_user = applicant.user
    job = application.job
    company_name = (job.employer.company_name if job.employer else '') or 'MultiBiz International'

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action')

        if action == 'update_status':
            new_status = request.POST.get('status')
            remarks = request.POST.get('remarks', '').strip()

            if new_status in ['pending', 'reviewed', 'shortlisted', 'interviewed', 'accepted', 'rejected']:
                application.status = new_status
                application.reviewed_by_name = f"MultiBiz Admin ({admin_user.first_name})"

                timestamp_str = timezone.now().strftime('%Y-%m-%d %H:%M')
                new_entry = f"[{timestamp_str}] Status changed to {new_status.title()} by Admin."
                if remarks:
                    new_entry += f" Remarks: {remarks}"

                if application.remarks_history:
                    application.remarks_history = f"{application.remarks_history}\n{new_entry}"
                else:
                    application.remarks_history = new_entry

                application.save()

                Notification.objects.create(
                    user=candidate_user,
                    title=f"Application Update: {job.title}",
                    message=f"Your application status for {job.title} at {company_name} was updated to {new_status.title()}.",
                    type='application'
                )

                try:
                    send_application_status_update_email(
                        to_email=candidate_user.email,
                        applicant_name=f"{candidate_user.first_name} {candidate_user.last_name}".strip() or candidate_user.email,
                        job_title=job.title,
                        company_name=company_name,
                        new_status=new_status,
                        remarks=remarks
                    )
                except Exception as e:
                    print(f"[Admin Status Update Mailer] Error: {e}")

                log_audit_trail(request, admin_id, 'update_application_status', f"Updated application status for {candidate_user.email} on {job.title} to {new_status}", 'application', application.application_id, candidate_user.email)
                success_msg = f"Candidate application status updated to {new_status.title()}! Notification email sent to candidate."

        elif action == 'schedule_interview':
            interview_date = request.POST.get('interview_date')
            start_time = request.POST.get('start_time')
            end_time = request.POST.get('end_time')
            interview_type = request.POST.get('interview_type', 'video')
            location = request.POST.get('location', '').strip()
            meeting_link = request.POST.get('meeting_link', '').strip()
            notes = request.POST.get('notes', '').strip()

            if not interview_date or not start_time or not end_time:
                error_msg = "Please provide interview date, start time, and end time."
            else:
                emp_obj = job.employer if job.employer else _get_or_create_employer_company(company_name, admin_user)
                InterviewSchedule.objects.create(
                    application=application,
                    employer=emp_obj,
                    interview_date=interview_date,
                    start_time=start_time,
                    end_time=end_time,
                    interview_type=interview_type,
                    location=location,
                    meeting_link=meeting_link,
                    notes=notes,
                    status='scheduled'
                )

                application.status = 'interviewed'
                application.save()

                Notification.objects.create(
                    user=candidate_user,
                    title=f"Interview Scheduled: {job.title}",
                    message=f"An interview has been scheduled on {interview_date} at {start_time} for {job.title}.",
                    type='application'
                )

                try:
                    send_application_status_update_email(
                        to_email=candidate_user.email,
                        applicant_name=f"{candidate_user.first_name} {candidate_user.last_name}".strip() or candidate_user.email,
                        job_title=job.title,
                        company_name=company_name,
                        new_status='interviewed',
                        remarks=f"Interview scheduled on {interview_date} ({start_time} - {end_time})." + (f" Location/Link: {meeting_link or location}" if (meeting_link or location) else "")
                    )
                except Exception as e:
                    print(f"[Admin Interview Scheduled Mailer] Error: {e}")

                log_audit_trail(request, admin_id, 'schedule_interview', f"Scheduled interview for {candidate_user.email} on {job.title} for {interview_date}", 'application', application.application_id, candidate_user.email)
                success_msg = "Interview scheduled successfully and email invitation sent to candidate!"

        elif action == 'submit_feedback':
            feedback_text = request.POST.get('feedback_text', '').strip()
            if feedback_text:
                emp_obj = job.employer if job.employer else _get_or_create_employer_company(company_name, admin_user)
                CandidateFeedback.objects.create(
                    application_id=application.application_id,
                    applicant_id=applicant.applicant_id,
                    employer_id=emp_obj.employer_id,
                    employability_score=applicant.employability_score,
                    feedback_message=feedback_text,
                    feedback_type='manual'
                )
                success_msg = "Candidate remarks & feedback submitted!"

    interviews = InterviewSchedule.objects.filter(application=application).order_by('-interview_date')
    chatbot_answers = ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number')

    cand_dict = {
        'match_score': float(application.match_score),
        'experience_years': applicant.experience_years,
        'employability_score': float(applicant.employability_score),
        'education_level': applicant.education_level or '',
    }
    score_info = calculate_candidate_ml_score(cand_dict)

    resume_analysis = ResumeAnalysis.objects.filter(applicant=applicant).order_by('-analysis_date', '-analysis_id').first()

    context = {
        'page_title': f"Candidate Review: {candidate_user.first_name} {candidate_user.last_name} - MultiBiz Admin",
        'current_page': 'candidates.php',
        'application': application,
        'applicant': applicant,
        'candidate_user': candidate_user,
        'job': job,
        'company_name': company_name,
        'interviews': interviews,
        'chatbot_answers': chatbot_answers,
        'resume_analysis': resume_analysis,
        'ml_score': score_info['ml_ranking_score'],
        'tier': score_info['ranking_category'],
        'success_msg': success_msg,
        'error_msg': error_msg,
    }
    return render(request, 'admin/view_candidate.html', context)


@csrf_exempt
@require_role('admin')
def update_status_api(request):
    """
    Admin AJAX endpoint for candidate status updates.
    """
    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'Invalid method'})

    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)

    app_id = request.POST.get('application_id')
    status = request.POST.get('status')
    remarks = request.POST.get('remarks', '').strip()

    application = get_object_or_404(Application.objects.select_related('applicant', 'applicant__user', 'job', 'job__employer'), pk=app_id)
    if status in ['pending', 'reviewed', 'shortlisted', 'interviewed', 'accepted', 'rejected']:
        application.status = status
        application.reviewed_by_name = f"MultiBiz Admin ({admin_user.first_name})"

        timestamp_str = timezone.now().strftime('%Y-%m-%d %H:%M')
        new_entry = f"[{timestamp_str}] Status changed to {status.title()} by Admin."
        if remarks:
            new_entry += f" Remarks: {remarks}"

        if application.remarks_history:
            application.remarks_history = f"{application.remarks_history}\n{new_entry}"
        application.save()

        try:
            cand_user = application.applicant.user if (application.applicant and application.applicant.user) else None
            company_name = (application.job.employer.company_name if application.job and application.job.employer else '') or 'MultiBiz'
            if cand_user:
                Notification.objects.create(
                    user=cand_user,
                    title=f"Application Update: {application.job.title}",
                    message=f"Your application status for {application.job.title} at {company_name} was updated to {status.title()}.",
                    type='application'
                )
                send_application_status_update_email(
                    to_email=cand_user.email,
                    applicant_name=f"{cand_user.first_name} {cand_user.last_name}".strip() or cand_user.email,
                    job_title=application.job.title,
                    company_name=company_name,
                    new_status=status,
                    remarks=remarks
                )
        except Exception as e:
            print(f"[Admin update_status_api Mailer] Error: {e}")

        log_audit_trail(request, admin_id, 'update_application_status', f"Updated application status for app #{app_id} to {status}", 'application', application.application_id, status)
        return JsonResponse({'success': True, 'message': f'Status updated to {status.title()}'})

    return JsonResponse({'success': False, 'message': 'Invalid status'})


@require_role('admin')
def job_candidates_view(request, job_id):
    """Show applicants for one job from the admin jobs listing."""
    return redirect(f'/admin/candidates.php?job_id={job_id}')


@require_role('admin')
def analytics_view(request):
    """
    Analytics & Reporting matching admin/analytics.php.
    """
    total_users = User.objects.count()
    applicants_count = User.objects.filter(role='applicant').count()
    employers_count = User.objects.filter(role='employer').count()
    
    total_jobs = JobPosting.objects.count()
    active_jobs = JobPosting.objects.filter(status='active').count()
    closed_jobs = JobPosting.objects.filter(status='closed').count()
    
    total_apps = Application.objects.count()
    pending_apps = Application.objects.filter(status='pending').count()
    shortlisted_apps = Application.objects.filter(status='shortlisted').count()
    accepted_apps = Application.objects.filter(status='accepted').count()
    rejected_apps = Application.objects.filter(status='rejected').count()
    thirty_days_ago = timezone.now() - timedelta(days=30)
    avg_match_score = Application.objects.aggregate(value=Avg('match_score'))['value'] or 0
    status_counts = Application.objects.values('status').annotate(count=Count('application_id')).order_by('status')
    jobs_by_type = JobPosting.objects.values('employment_type').annotate(count=Count('job_id')).order_by('employment_type')

    top_qualifications = Qualification.objects.annotate(
        job_count=Count('jobqualificationmapping')
    ).order_by('-job_count')[:6]

    context = {
        'page_title': 'Platform Analytics - MultiBiz',
        'current_page': 'analytics.php',
        'total_users': total_users,
        'applicants_count': applicants_count,
        'employers_count': employers_count,
        'total_jobs': total_jobs,
        'active_jobs': active_jobs,
        'closed_jobs': closed_jobs,
        'total_apps': total_apps,
        'pending_apps': pending_apps,
        'shortlisted_apps': shortlisted_apps,
        'accepted_apps': accepted_apps,
        'rejected_apps': rejected_apps,
        'avg_match_score': round(float(avg_match_score), 2),
        'new_users_30d': User.objects.filter(created_at__gte=thirty_days_ago).count(),
        'new_apps_30d': Application.objects.filter(applied_at__gte=thirty_days_ago).count(),
        'status_counts': status_counts,
        'jobs_by_type': jobs_by_type,
        'top_qualifications': top_qualifications,
    }
    return render(request, 'admin/analytics.html', context)


@require_role('admin')
def cms_view(request):
    """
    CMS Management matching admin/cms.php.
    """
    admin_id = getCurrentUserId(request)
    _ensure_landing_cms_content()
    tab = request.GET.get('tab', 'hero')
    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action')

        # Hero slide management
        if action == 'add_hero_slide':
            title = request.POST.get('title', '')
            subtitle = request.POST.get('subtitle', '')
            button_text = request.POST.get('button_text', 'Learn More')
            button_link = request.POST.get('button_link', '#')
            image_file = request.FILES.get('image')
            
            image_path = 'images/picture1.png'
            if image_file:
                upload_dir = settings.STATICFILES_DIRS[0] / 'images'
                os.makedirs(upload_dir, exist_ok=True)
                fname = f"slide_{int(time.time())}{os.path.splitext(image_file.name)[1]}"
                with open(upload_dir / fname, 'wb+') as dest:
                    for chunk in image_file.chunks():
                        dest.write(chunk)
                image_path = f"images/{fname}"

            CmsHeroSlide.objects.create(
                title=title,
                subtitle=subtitle,
                button_text=button_text,
                button_link=button_link,
                image_path=image_path,
                sort_order=CmsHeroSlide.objects.count() + 1
            )
            log_audit_trail(request, admin_id, 'add_hero_slide', f"Added hero slide: {title}")
            success_msg = "Hero slide added!"

        elif action == 'delete_hero_slide':
            slide_id = request.POST.get('slide_id')
            CmsHeroSlide.objects.filter(pk=slide_id).delete()
            log_audit_trail(request, admin_id, 'delete_hero_slide', f"Deleted hero slide ID {slide_id}")
            success_msg = "Hero slide deleted!"

        # Testimonial management
        elif action == 'add_testimonial':
            author_name = request.POST.get('author_name', '')
            author_role = request.POST.get('author_role', '')
            company = request.POST.get('company', '')
            content = request.POST.get('content', '')
            rating = int(request.POST.get('rating', '5'))

            CmsTestimonial.objects.create(
                author_name=author_name,
                author_role=author_role,
                company=company,
                content=content,
                rating=rating,
                sort_order=CmsTestimonial.objects.count() + 1
            )
            log_audit_trail(request, admin_id, 'add_testimonial', f"Added testimonial from {author_name}")
            success_msg = "Testimonial added!"

        elif action == 'delete_testimonial':
            t_id = request.POST.get('testimonial_id')
            CmsTestimonial.objects.filter(pk=t_id).delete()
            success_msg = "Testimonial deleted!"

        # Brand management
        elif action == 'add_brand':
            brand_name = request.POST.get('brand_name', '')
            brand_description = request.POST.get('brand_description', '')
            brand_overlay_title = request.POST.get('brand_overlay_title', '')
            brand_overlay_description = request.POST.get('brand_overlay_description', '')
            brand_category = request.POST.get('brand_category', 'cor')
            logo_file = request.FILES.get('logo')

            logo_path = 'images/b1.png'
            if logo_file:
                upload_dir = settings.STATICFILES_DIRS[0] / 'images' / 'brands'
                os.makedirs(upload_dir, exist_ok=True)
                fname = f"brand_{int(time.time())}{os.path.splitext(logo_file.name)[1]}"
                with open(upload_dir / fname, 'wb+') as dest:
                    for chunk in logo_file.chunks():
                        dest.write(chunk)
                logo_path = f"images/brands/{fname}"

            CmsBrand.objects.create(
                brand_name=brand_name,
                brand_description=brand_description,
                brand_overlay_title=brand_overlay_title,
                brand_overlay_description=brand_overlay_description,
                brand_category=brand_category,
                brand_logo=logo_path,
                sort_order=CmsBrand.objects.count() + 1
            )
            log_audit_trail(request, admin_id, 'add_brand', f"Added brand partner {brand_name}")
            success_msg = "Brand added!"

        elif action == 'delete_brand':
            b_id = request.POST.get('brand_id')
            CmsBrand.objects.filter(pk=b_id).delete()
            success_msg = "Brand deleted!"

        elif action == 'delete_news':
            news_id = request.POST.get('news_id')
            CmsNews.objects.filter(pk=news_id).delete()
            success_msg = "News article deleted!"

        elif action == 'add_news':
            news_date_value = request.POST.get('news_date', '').strip()
            news_date = date.fromisoformat(news_date_value) if news_date_value else None
            title = request.POST.get('title', '').strip()
            CmsNews.objects.create(
                title=title,
                category=request.POST.get('category', 'Corporate').strip(),
                news_date=news_date,
                excerpt=request.POST.get('excerpt', '').strip(),
                content=request.POST.get('content', '').strip(),
                is_featured=request.POST.get('is_featured') == '1',
            )
            log_audit_trail(request, admin_id, 'add_news', f"Published news article: {title}")
            success_msg = "News article published!"

        # Section content text update
        elif action == 'update_section_content':
            for key, val in request.POST.items():
                if key.startswith('content_'):
                    c_id = key.replace('content_', '')
                    if c_id.isdigit():
                        c_obj = CmsContent.objects.filter(pk=int(c_id)).first()
                        if c_obj:
                            c_obj.field_value = val
                            c_obj.save()
            log_audit_trail(request, admin_id, 'update_cms_content', "Updated page section content")
            success_msg = "Content updated successfully!"

    hero_slides = CmsHeroSlide.objects.order_by('sort_order', 'id')
    testimonials = CmsTestimonial.objects.order_by('sort_order', 'id')
    brands = CmsBrand.objects.order_by('sort_order', 'id')
    news_items = CmsNews.objects.order_by('-created_at')
    sections = CmsSection.objects.prefetch_related('contents').all()

    context = {
        'page_title': 'CMS Management - MultiBiz',
        'current_page': 'cms.php',
        'tab': tab,
        'hero_slides': hero_slides,
        'slides': hero_slides,
        'testimonials': testimonials,
        'brands': brands,
        'news_items': news_items,
        'news_articles': news_items,
        'sections': sections,
        'success_msg': success_msg,
        'error_msg': error_msg,
    }
    return render(request, 'admin/cms.html', context)


@require_role('admin')
def messages_view(request):
    """
    Contact Inquiries Inbox matching admin/messages.php.
    """
    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)

    inquiry_id = request.GET.get('id')
    selected_inquiry = None
    if inquiry_id:
        selected_inquiry = get_object_or_404(ContactInquiry, pk=inquiry_id)
        if not selected_inquiry.is_read:
            selected_inquiry.is_read = True
            selected_inquiry.save()

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action')

        if action == 'reply':
            inq_id = request.POST.get('inquiry_id')
            reply_text = request.POST.get('reply_text', '').strip()
            inquiry = get_object_or_404(ContactInquiry, pk=inq_id)

            if not reply_text:
                error_msg = "Reply message cannot be empty."
            else:
                # Send email via SMTP
                res = send_contact_reply(
                    to_email=inquiry.email,
                    to_name=inquiry.name,
                    subject=f"Re: {inquiry.subject}",
                    body_text=reply_text,
                    original_message=inquiry.message
                )

                ContactReply.objects.create(
                    inquiry=inquiry,
                    admin=admin_user,
                    reply_text=reply_text,
                    email_sent=res.get('success', False)
                )

                inquiry.status = 'replied'
                inquiry.is_read = True
                inquiry.save()

                log_audit_trail(request, admin_id, 'reply_contact_inquiry', f"Replied to contact inquiry from {inquiry.name} ({inquiry.email})", 'contact_inquiry', inquiry.id, inquiry.subject)
                success_msg = f"Reply sent to {inquiry.name} ({inquiry.email})!"

        elif action == 'delete_inquiry':
            inq_id = request.POST.get('inquiry_id')
            ContactInquiry.objects.filter(pk=inq_id).delete()
            return redirect('/admin/messages.php')

    inquiries = ContactInquiry.objects.prefetch_related('replies').order_by('-created_at')

    context = {
        'page_title': 'Contact Inquiries - MultiBiz',
        'current_page': 'messages.php',
        'inquiries': inquiries,
        'selected_inquiry': selected_inquiry,
        'success_msg': success_msg,
        'error_msg': error_msg,
    }
    return render(request, 'admin/messages.html', context)


@csrf_exempt
@require_role('admin')
def contact_api_view(request):
    """
    Contact Inquiries AJAX API matching admin/contact_api.php.
    Supports inquiry listing, full conversation retrieval, replying via SMTP,
    and 1-click Review & Post Job from company talent requests.
    """
    action = request.POST.get('action') or request.GET.get('action')
    admin_id = getCurrentUserId(request)
    admin_user = User.objects.filter(pk=admin_id).first()

    def parse_job_specs(message_text, subject_text=''):
        """Extracts structured job specification fields from inquiry message."""
        specs = {
            'is_staffing_request': False,
            'title': '',
            'company_name': '',
            'employer_id': None,
            'headcount': 1,
            'employment_type': 'full-time',
            'location': 'Metro Manila, Philippines',
            'salary_range': '',
            'skills_required': '',
            'urgency': 'Normal',
            'description': '',
            'requirements': '',
            'special_notes': '',
        }
        if not message_text:
            return specs

        if '[Staffing Request]' in subject_text or 'TALENT REQUEST' in message_text or '<!-- JSON:' in message_text:
            specs['is_staffing_request'] = True

        # Check for embedded JSON payload
        if '<!-- JSON:' in message_text:
            try:
                json_part = message_text.split('<!-- JSON:')[1].split('-->')[0].strip()
                parsed = json.loads(json_part)
                specs.update(parsed)
                specs['is_staffing_request'] = True
                return specs
            except Exception:
                pass

        # Text parsing fallback
        lines = message_text.splitlines()
        current_section = None
        desc_lines, req_lines, note_lines = [], [], []

        for line in lines:
            line_str = line.strip()
            if not line_str:
                continue
            line_lower = line_str.lower()
            if 'job description' in line_lower and '---' in line_str:
                current_section = 'desc'
                continue
            elif ('requirements' in line_lower or 'credentials' in line_lower) and '---' in line_str:
                current_section = 'req'
                continue
            elif ('special notes' in line_lower or 'notes' in line_lower) and '---' in line_str:
                current_section = 'notes'
                continue

            if current_section == 'desc':
                desc_lines.append(line)
            elif current_section == 'req':
                req_lines.append(line)
            elif current_section == 'notes':
                note_lines.append(line)
            else:
                if ':' in line_str:
                    k, v = line_str.split(':', 1)
                    k_lower = k.lower().strip()
                    v_clean = v.strip()
                    if 'position' in k_lower or 'job title' in k_lower or 'title' in k_lower:
                        specs['title'] = v_clean
                    elif 'company' in k_lower:
                        specs['company_name'] = v_clean
                    elif 'headcount' in k_lower:
                        specs['headcount'] = int(v_clean) if v_clean.isdigit() else 1
                    elif 'employment' in k_lower:
                        specs['employment_type'] = v_clean.lower()
                    elif 'location' in k_lower:
                        specs['location'] = v_clean
                    elif 'salary' in k_lower:
                        specs['salary_range'] = v_clean
                    elif 'urgency' in k_lower:
                        specs['urgency'] = v_clean
                    elif 'skill' in k_lower:
                        specs['skills_required'] = v_clean

        if desc_lines:
            specs['description'] = '\n'.join(desc_lines).strip()
        if req_lines:
            specs['requirements'] = '\n'.join(req_lines).strip()
        if note_lines:
            specs['special_notes'] = '\n'.join(note_lines).strip()

        if not specs['title'] and '[Staffing Request]' in subject_text:
            # Extract from subject: "[Staffing Request] Senior Dev - ABC Corp"
            sub_clean = subject_text.replace('[Staffing Request]', '').strip()
            if '-' in sub_clean:
                specs['title'] = sub_clean.split('-')[0].strip()
            else:
                specs['title'] = sub_clean

        return specs

    # 1. LIST INQUIRIES
    if action == 'list_inquiries':
        filter_type = request.GET.get('filter', 'all')
        try:
            page = int(request.GET.get('page', 1))
        except (ValueError, TypeError):
            page = 1
        limit = 15

        inqs_qs = ContactInquiry.objects.all().order_by('-created_at')

        if filter_type == 'new':
            inqs_qs = inqs_qs.filter(status='new')
        elif filter_type == 'unread':
            inqs_qs = inqs_qs.filter(is_read=False)
        elif filter_type == 'replied':
            inqs_qs = inqs_qs.filter(status='replied')
        elif filter_type == 'staffing':
            inqs_qs = inqs_qs.filter(
                Q(subject__icontains='[Staffing Request]') |
                Q(subject__icontains='Talent') |
                Q(message__icontains='Position Needed') |
                Q(message__icontains='TALENT REQUEST')
            )

        total = inqs_qs.count()
        start = (page - 1) * limit
        rows_data = []

        for inq in inqs_qs[start:start + limit]:
            is_staffing = '[Staffing Request]' in inq.subject or 'Position Needed:' in inq.message or 'TALENT REQUEST' in inq.message
            if is_staffing:
                sp = parse_job_specs(inq.message, inq.subject)
                clean_excerpt = f"Role: {sp['title'] or inq.subject} • {sp['employment_type'].title()} • {sp['location']}"
            else:
                clean_excerpt = inq.message[:75]
                if len(inq.message) > 75:
                    clean_excerpt += '…'

            rows_data.append({
                'id': inq.id,
                'name': inq.name,
                'email': inq.email,
                'subject': inq.subject,
                'excerpt': clean_excerpt or inq.subject,
                'created_at': inq.created_at.strftime('%Y-%m-%d %H:%M:%S'),
                'is_read': 1 if inq.is_read else 0,
                'status': inq.status,
                'is_staffing_request': is_staffing,
            })

        return JsonResponse({'success': True, 'rows': rows_data, 'total': total, 'limit': limit})

    # 2. GET SINGLE INQUIRY CONVERSATION
    elif action == 'get_inquiry':
        inq_id = request.GET.get('id') or request.POST.get('id')
        inquiry = get_object_or_404(ContactInquiry, pk=inq_id)

        if not inquiry.is_read:
            inquiry.is_read = True
            inquiry.save()

        replies_list = []
        for rep in inquiry.replies.select_related('admin').order_by('sent_at'):
            admin_name = f"{rep.admin.first_name} {rep.admin.last_name}".strip() if rep.admin else "MultiBiz Admin"
            replies_list.append({
                'admin_name': admin_name or 'Admin',
                'reply_text': rep.reply_text,
                'sent_at': rep.sent_at.strftime('%Y-%m-%d %H:%M:%S'),
                'email_sent': rep.email_sent,
            })

        job_specs = parse_job_specs(inquiry.message, inquiry.subject)
        
        # Match potential employer account by email or company name
        matched_employer_id = None
        matched_emp = Employer.objects.filter(Q(user__email__iexact=inquiry.email) | Q(company_name__iexact=job_specs.get('company_name', ''))).first()
        if matched_emp:
            matched_employer_id = matched_emp.employer_id

        # Employers list for dropdown selection
        employers_data = list(Employer.objects.values('employer_id', 'company_name').order_by('company_name'))
        qualifications_data = list(Qualification.objects.filter(status='active').values('qualification_id', 'name').order_by('name'))

        return JsonResponse({
            'success': True,
            'inquiry': {
                'id': inquiry.id,
                'name': inquiry.name,
                'email': inquiry.email,
                'subject': inquiry.subject,
                'message': inquiry.message,
                'is_read': 1 if inquiry.is_read else 0,
                'status': inquiry.status,
                'created_at': inquiry.created_at.strftime('%Y-%m-%d %H:%M:%S'),
            },
            'job_specs': job_specs,
            'matched_employer_id': matched_employer_id,
            'replies': replies_list,
            'employers': employers_data,
            'qualifications': qualifications_data,
        })

    # 3. SEND REPLY TO INQUIRY
    elif action == 'send_reply':
        inq_id = request.POST.get('id') or request.POST.get('inquiry_id')
        reply_text = request.POST.get('reply_text', '').strip()
        inquiry = get_object_or_404(ContactInquiry, pk=inq_id)

        if not reply_text:
            return JsonResponse({'success': False, 'error': 'Reply message cannot be empty.'})

        # Send email via SMTP
        email_res = send_contact_reply(
            to_email=inquiry.email,
            to_name=inquiry.name,
            subject=f"Re: {inquiry.subject}",
            body_text=reply_text,
            original_message=inquiry.message
        )

        ContactReply.objects.create(
            inquiry=inquiry,
            admin=admin_user,
            reply_text=reply_text,
            email_sent=email_res.get('success', False)
        )

        inquiry.status = 'replied'
        inquiry.is_read = True
        inquiry.save()

        log_audit_trail(request, admin_id, 'reply_contact_inquiry', f"Replied to inquiry #{inquiry.id} from {inquiry.name}", 'contact_inquiry', inquiry.id, inquiry.subject)
        return JsonResponse({
            'success': True,
            'email_sent': email_res.get('success', False),
            'message': 'Reply recorded and email sent!'
        })

    # 4. MARK READ / UNREAD
    elif action == 'mark_read':
        inq_id = request.POST.get('id') or request.POST.get('inquiry_id')
        ContactInquiry.objects.filter(pk=inq_id).update(is_read=True)
        return JsonResponse({'success': True})

    elif action == 'mark_unread':
        inq_id = request.POST.get('id') or request.POST.get('inquiry_id')
        ContactInquiry.objects.filter(pk=inq_id).update(is_read=False)
        return JsonResponse({'success': True})

    # 5. DELETE INQUIRY
    elif action == 'delete_inquiry' or action == 'delete':
        inq_id = request.POST.get('id') or request.POST.get('inquiry_id')
        ContactInquiry.objects.filter(pk=inq_id).delete()
        log_audit_trail(request, admin_id, 'delete_contact_inquiry', f"Deleted inquiry #{inq_id}", 'contact_inquiry', int(inq_id) if str(inq_id).isdigit() else 0, 'Inquiry')
        return JsonResponse({'success': True})

    # 6. GET UNREAD COUNT
    elif action == 'get_unread_count':
        unread_count = ContactInquiry.objects.filter(is_read=False).count()
        staffing_count = ContactInquiry.objects.filter(status='new', subject__icontains='[Staffing Request]').count()
        return JsonResponse({'success': True, 'count': unread_count, 'staffing_count': staffing_count})

    # 7. APPROVE AND POST JOB FROM INQUIRY
    elif action == 'approve_and_post_job':
        inq_id = request.POST.get('inquiry_id') or request.POST.get('id')
        inquiry = get_object_or_404(ContactInquiry, pk=inq_id)

        employer_id = request.POST.get('employer_id')
        title = request.POST.get('title', '').strip()
        description = request.POST.get('description', '').strip()
        requirements = request.POST.get('requirements', '').strip()
        skills_required = request.POST.get('skills_required', '').strip()
        location = request.POST.get('location', 'Metro Manila, Philippines').strip()
        employment_type = request.POST.get('employment_type', 'full-time')
        salary_range = request.POST.get('salary_range', '').strip()
        job_status = request.POST.get('status', 'active')
        qualification_ids = request.POST.getlist('qualifications') or request.POST.getlist('qualifications[]')

        if not title:
            return JsonResponse({'success': False, 'error': 'Job Title is required.'})

        employer = None
        if employer_id and str(employer_id).isdigit():
            employer = Employer.objects.filter(pk=int(employer_id)).first()

        # If employer not specified, match or assign to first employer or create partner
        if not employer:
            employer = Employer.objects.filter(Q(user__email__iexact=inquiry.email) | Q(company_name__icontains=inquiry.name)).first()
        if not employer:
            employer = Employer.objects.first()

        target_quals_str = ','.join(str(q) for q in qualification_ids if str(q).isdigit())

        # Create JobPosting
        job = JobPosting.objects.create(
            employer=employer,
            title=title,
            description=description or f"Job position for {title} at {employer.company_name if employer else 'MultiBiz Partner'}.",
            requirements=requirements,
            skills_required=skills_required,
            location=location,
            employment_type=employment_type,
            salary_range=salary_range,
            status=job_status,
            target_qualifications=target_quals_str
        )

        # Qualification mappings
        for q_id in qualification_ids:
            if str(q_id).isdigit():
                q_obj = Qualification.objects.filter(pk=int(q_id)).first()
                if q_obj:
                    JobQualificationMapping.objects.create(job=job, qualification=q_obj)

        # Mark inquiry as replied and add confirmation reply
        inquiry.status = 'replied'
        inquiry.is_read = True
        inquiry.save()

        company_name = employer.company_name if (employer and employer.company_name) else (f"{employer.user.first_name} {employer.user.last_name}".strip() if (employer and employer.user) else inquiry.name or 'MultiBiz Partner')
        system_reply_text = (
            f"✅ JOB APPROVED & POSTED LIVE!\n\n"
            f"Position: {job.title}\n"
            f"Job ID: #{job.job_id}\n"
            f"Company: {company_name}\n"
            f"Status: {job.status.title()}\n"
            f"Salary: {job.salary_range or 'Competitive'}\n"
            f"Location: {job.location}\n\n"
            f"Your job has been published on the MultiBiz live careers board. Applicants can now apply and AI candidate ranking is active."
        )

        email_result = send_job_posted_live_notification(
            to_email=inquiry.email,
            company_name=company_name,
            contact_name=inquiry.name,
            job_title=job.title,
            job_id=job.job_id,
            location=job.location,
            salary_range=job.salary_range
        )

        ContactReply.objects.create(
            inquiry=inquiry,
            admin=admin_user,
            reply_text=system_reply_text,
            email_sent=email_result.get('success', False)
        )

        # Notify employer in portal
        if employer and employer.user:
            Notification.objects.create(
                user=employer.user,
                title=f"🎉 Job Approved: {job.title}",
                message=f"Your job request '{job.title}' has been reviewed, approved, and posted live by MultiBiz Admin (Job #{job.job_id}).",
                type='job_update'
            )

        log_audit_trail(
            request, admin_id, 'approve_and_post_job',
            f"Approved and posted job '{job.title}' (ID #{job.job_id}) for {company_name} from inquiry #{inquiry.id}",
            'job', job.job_id, job.title
        )

        return JsonResponse({
            'success': True,
            'job_id': job.job_id,
            'email_sent': email_result.get('success', False),
            'message': f"Job '{job.title}' has been approved and posted live on MultiBiz!"
        })

    return JsonResponse({'success': False, 'message': 'Unknown action'})



@require_role('admin')
def audit_trail_view(request):
    """
    Audit Trail Viewer matching admin/audit_trail.php.
    """
    search = request.GET.get('search', '').strip()
    action_filter = request.GET.get('action_type', '')

    audit_qs = AuditTrail.objects.select_related('admin_user').order_by('-created_at')

    if search:
        audit_qs = audit_qs.filter(
            Q(admin_name__icontains=search) |
            Q(action_description__icontains=search) |
            Q(target_name__icontains=search)
        )

    if action_filter:
        audit_qs = audit_qs.filter(action_type=action_filter)

    action_types = list(AuditTrail.objects.values_list('action_type', flat=True).distinct())

    context = {
        'page_title': 'Audit Trail - MultiBiz',
        'current_page': 'audit_trail.php',
        'audit_logs': audit_qs[:200],
        'action_types': action_types,
        'search': search,
        'selected_action': action_filter,
    }
    return render(request, 'admin/audit_trail.html', context)


@require_role('admin')
def chatbot_review_view(request):
    """
    Admin Chatbot Review matching admin/chatbot_review.php.
    """
    applicants_with_answers = Applicant.objects.filter(
        chatbotanswer__isnull=False
    ).distinct().select_related('user')

    selected_applicant_id = request.GET.get('applicant_id')
    selected_applicant = None
    answers = []
    recommendation = None

    if selected_applicant_id:
        selected_applicant = get_object_or_404(Applicant.objects.select_related('user'), pk=selected_applicant_id)
        answers = ChatbotAnswer.objects.filter(applicant=selected_applicant).order_by('question_number')
        recommendation = ChatbotRecommendation.objects.filter(applicant=selected_applicant).order_by('-created_at').first()

    context = {
        'page_title': 'Chatbot Quiz Review - MultiBiz',
        'current_page': 'chatbot_review.php',
        'applicants': applicants_with_answers,
        'selected_applicant': selected_applicant,
        'answers': answers,
        'recommendation': recommendation,
    }
    return render(request, 'admin/chatbot_review.html', context)
