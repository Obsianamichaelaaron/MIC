import os
import time
import json
import io
import csv
from decimal import Decimal
from datetime import date, timedelta
from django.core.exceptions import ValidationError
from django.core.validators import validate_email
from django.shortcuts import render, redirect, get_object_or_404
from django.http import JsonResponse, HttpResponseForbidden, HttpResponse
from django.views.decorators.csrf import csrf_exempt
from django.db import IntegrityError, transaction
from django.db.models import Q, Count
from django.conf import settings
from django.utils import timezone
from django.views.decorators.http import require_POST
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
    send_job_posted_live_notification, send_job_request_rejected_notification,
    send_applicant_forwarded_to_employer_email, send_interview_scheduled_email
)
from app.services.ml_ranking import calculate_candidate_ml_score, compute_job_match_result

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


def _automatic_tier_counts():
    applications = Application.objects.all()
    return {
        'high': applications.filter(applicant__employability_score__gt=60).count(),
        'medium': applications.filter(
            applicant__employability_score__gte=40,
            applicant__employability_score__lte=60,
        ).count(),
        'low': applications.filter(applicant__employability_score__lt=40).count(),
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
    match_class_counts = _automatic_tier_counts()
    new_users_30d = User.objects.filter(created_at__gte=thirty_days_ago).count()
    new_apps_30d = Application.objects.filter(applied_at__gte=thirty_days_ago).count()
    total_admins = User.objects.filter(role='admin').count()
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
        'match_tier_labels': ['High employability', 'Medium employability', 'Low employability'],
        'match_tier_data': [
            match_class_counts.get('high', 0),
            match_class_counts.get('medium', 0),
            match_class_counts.get('low', 0),
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
        'automatic_tier_counts': match_class_counts,
        'new_users_30d': new_users_30d,
        'new_apps_30d': new_apps_30d,
        'total_admins': total_admins,
        'pending_inquiries': unread_inquiries,
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
    
    search = (request.GET.get('q') or request.GET.get('search') or '').strip()
    role_filter = request.GET.get('role', '').strip()
    status_filter = request.GET.get('status', '').strip()

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action')

        if action == 'create_employer':
            email = request.POST.get('email', '').strip().lower()
            password = request.POST.get('password', '')
            first_name = request.POST.get('first_name', '').strip()
            last_name = request.POST.get('last_name', '').strip()
            phone = request.POST.get('phone', '').strip()
            company_name = request.POST.get('company_name', '').strip()
            industry = request.POST.get('industry', '').strip()
            company_address = request.POST.get('company_address', '').strip()
            company_website = request.POST.get('company_website', '').strip()

            if not email or not password or not company_name or not first_name or not last_name:
                error_msg = "Please provide the contact's name, email, password, and company name."
            elif len(password) < 6:
                error_msg = "Password must be at least 6 characters."
            else:
                try:
                    validate_email(email)
                except ValidationError:
                    error_msg = "Please provide a valid email address."

                if not error_msg and User.objects.filter(email__iexact=email).exists():
                    error_msg = "Email already in use."

                if not error_msg:
                    try:
                        with transaction.atomic():
                            new_user = User.objects.create(
                                email=email,
                                password=hash_password(password),
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
                            log_audit_trail(
                                request,
                                admin_id,
                                'create_employer',
                                f"Created employer account for {company_name} ({email})",
                                'employer',
                                new_user.user_id,
                                company_name
                            )
                    except IntegrityError:
                        error_msg = "Email already in use."
                    else:
                        success_msg = f"Employer partner account for {company_name} created successfully."

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
        search_filter = (
            Q(email__icontains=search) |
            Q(first_name__icontains=search) |
            Q(last_name__icontains=search) |
            Q(phone__icontains=search) |
            Q(employer_profile__company_name__icontains=search)
        )
        words = search.split()
        if len(words) > 1:
            multi_word_q = Q()
            for word in words:
                multi_word_q &= (
                    Q(first_name__icontains=word) |
                    Q(last_name__icontains=word) |
                    Q(email__icontains=word) |
                    Q(phone__icontains=word) |
                    Q(employer_profile__company_name__icontains=word)
                )
            search_filter |= multi_word_q

        users_qs = users_qs.filter(search_filter).distinct()

    if role_filter:
        users_qs = users_qs.filter(role=role_filter)

    if status_filter:
        users_qs = users_qs.filter(status=status_filter)

    context = {
        'page_title': 'User Management - MultiBiz',
        'current_page': 'users.php',
        'admin_user': get_object_or_404(User, pk=admin_id),
        'users': users_qs,
        'search': search,
        'query': search,
        'q': search,
        'role_filter': role_filter,
        'status_filter': status_filter,
        'success_msg': success_msg,
        'error_msg': error_msg,
    }
    return render(request, 'admin/users.html', context)


def _get_or_create_employer_company(company_name: str, admin_user: User) -> Employer:
    clean_name = company_name.strip() if company_name else 'MultiBiz Partner'
    emp = Employer.objects.filter(
        company_name__iexact=clean_name,
        user__role='employer',
    ).order_by('employer_id').first()
    if emp:
        return emp

    emp = Employer.objects.filter(company_name__iexact=clean_name).first()
    if not emp:
        emp = Employer.objects.create(
            user=admin_user,
            company_name=clean_name,
            industry='Recruitment Partner'
        )
    return emp


def _parse_inquiry_job_specs(message_text: str, subject_text: str = '') -> dict:
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
    if not message_text and not subject_text:
        return specs

    msg_str = str(message_text or '')
    sub_str = str(subject_text or '')

    if '[Staffing Request]' in sub_str or 'TALENT REQUEST' in msg_str or '<!-- JSON:' in msg_str or 'position needed:' in msg_str.lower() or 'job description' in msg_str.lower() or 'staffing request' in sub_str.lower():
        specs['is_staffing_request'] = True

    # Check for embedded JSON payload
    if '<!-- JSON:' in msg_str:
        try:
            json_part = msg_str.split('<!-- JSON:')[1].split('-->')[0].strip()
            parsed = json.loads(json_part)
            specs.update(parsed)
            specs['is_staffing_request'] = True
            return specs
        except Exception:
            pass

    # Text parsing fallback
    lines = msg_str.splitlines()
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
                if 'position' in k_lower or 'job title' in k_lower or k_lower == 'title':
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
    elif not specs['is_staffing_request']:
        specs['description'] = msg_str
    else:
        clean_desc = re.sub(r'<!--\s*JSON:[\s\S]*?-->', '', msg_str).strip()
        specs['description'] = clean_desc

    if req_lines:
        specs['requirements'] = '\n'.join(req_lines).strip()
    if note_lines:
        specs['special_notes'] = '\n'.join(note_lines).strip()

    if not specs['title'] and sub_str:
        sub_clean = sub_str.replace('[Staffing Request]', '').replace('[Company Request]', '').replace('General Inquiry', '').strip()
        if '-' in sub_clean:
            specs['title'] = sub_clean.split('-')[0].strip()
        elif sub_clean:
            specs['title'] = sub_clean

    return specs


@require_role('admin')
def post_job_view(request):
    """
    Admin Post Job matching admin/post_job.php.
    Allows admin to post job vacancies on behalf of client companies.
    Supports 1-click prefill when reviewing from inquiries (?inquiry_id=123).
    """
    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)

    error = None
    inquiry_id = request.GET.get('inquiry_id') or request.POST.get('inquiry_id')
    initial_inquiry = None
    if inquiry_id and str(inquiry_id).isdigit():
        initial_inquiry = ContactInquiry.objects.filter(pk=int(inquiry_id)).first()

    if request.method == 'POST':
        company_name = request.POST.get('company_name', '').strip()
        contact_first_name = request.POST.get('contact_first_name', '').strip()
        contact_last_name = request.POST.get('contact_last_name', '').strip()
        account_email = request.POST.get('account_email', '').strip().lower()
        account_password = request.POST.get('account_password', '')
        industry = request.POST.get('industry', '').strip()
        company_address = request.POST.get('company_address', '').strip()
        company_website = request.POST.get('company_website', '').strip()
        title = request.POST.get('title', '').strip()
        description = request.POST.get('description', '').strip()
        requirements = request.POST.get('requirements', '').strip()
        skills_required = request.POST.get('skills_required', '').strip()
        location = request.POST.get('location', '').strip()
        employment_type = request.POST.get('employment_type', 'full-time')
        salary_range = request.POST.get('salary_range', '').strip()
        status = request.POST.get('status', 'active')
        qualification_ids = request.POST.getlist('qualifications')

        if not company_name:
            error = "Company name is required."
        elif not title:
            error = "Job title is required."
        else:
            employer = Employer.objects.filter(
                company_name__iexact=company_name,
                user__role='employer',
            ).select_related('user').order_by('employer_id').first()
            create_partner_account = employer is None

            if create_partner_account:
                if not contact_first_name or not contact_last_name or not account_email or not account_password:
                    error = "For a new company, provide the contact's name, login email, and temporary password."
                elif len(account_password) < 6:
                    error = "Temporary password must be at least 6 characters."
                elif len(account_password.encode('utf-8')) > 72:
                    error = "Temporary password must be no longer than 72 UTF-8 bytes."
                else:
                    try:
                        validate_email(account_email)
                    except ValidationError:
                        error = "Please provide a valid employer login email."

                    if not error and User.objects.filter(email__iexact=account_email).exists():
                        error = "That login email is already in use. Select the existing partner company or use another email."

            if not error:
                with transaction.atomic():
                    if create_partner_account:
                        try:
                            with transaction.atomic():
                                partner_user = User.objects.create(
                                    email=account_email,
                                    password=hash_password(account_password),
                                    role='employer',
                                    first_name=contact_first_name,
                                    last_name=contact_last_name,
                                    phone=request.POST.get('contact_phone', '').strip(),
                                    status='active',
                                    created_by_admin=True,
                                )
                        except IntegrityError:
                            error = "That employer login email is already in use."

                        if not error:
                            employer = Employer.objects.create(
                                user=partner_user,
                                company_name=company_name,
                                industry=industry,
                                company_address=company_address,
                                company_website=company_website,
                            )

                    if not error:
                        JobPosting.objects.filter(
                            employer__company_name__iexact=company_name,
                            employer__user__role='admin',
                        ).exclude(employer=employer).update(employer=employer)

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

                        if initial_inquiry:
                            initial_inquiry.status = 'replied'
                            initial_inquiry.is_read = True
                            initial_inquiry.save()
                            ContactReply.objects.create(
                                inquiry=initial_inquiry,
                                admin=admin_user,
                                reply_text=f"Job posting #{job.job_id} ('{job.title}') was created and published to portal from this request."
                            )

                        log_audit_trail(request, admin_id, 'create_job', f"Posted new job: {title} for {company_name}", 'job', job.job_id, title)

                if not error:
                    return redirect('/admin/jobs.php?posted=success')

    # Initial prefill computation
    initial_company_name = request.GET.get('company_name', '')
    initial_title = request.GET.get('title', '')
    initial_description = request.GET.get('description', '')
    initial_requirements = request.GET.get('requirements', '')
    initial_skills = request.GET.get('skills_required', '')
    initial_location = request.GET.get('location', '')
    initial_salary_range = request.GET.get('salary_range', '')
    initial_employment_type = request.GET.get('employment_type', 'full-time')

    if initial_inquiry:
        specs = _parse_inquiry_job_specs(initial_inquiry.message, initial_inquiry.subject)
        initial_company_name = specs.get('company_name') or initial_inquiry.name
        initial_title = specs.get('title') or (initial_inquiry.subject.replace('[Staffing Request]', '').strip() if initial_inquiry.subject else '')
        initial_description = specs.get('description') or initial_inquiry.message
        initial_requirements = specs.get('requirements') or ''
        initial_skills = specs.get('skills_required') or ''
        initial_location = specs.get('location') or 'Metro Manila, Philippines'
        initial_salary_range = specs.get('salary_range') or ''
        initial_employment_type = specs.get('employment_type') or 'full-time'

    selected_employment_type = (
        request.POST.get('employment_type') if request.method == 'POST' else initial_employment_type
    ) or 'full-time'
    selected_posting_status = (request.POST.get('status') if request.method == 'POST' else 'active') or 'active'
    qualifications = Qualification.objects.filter(status='active').order_by('name')
    existing_companies = list(Employer.objects.values_list('company_name', flat=True).distinct())
    existing_partner_companies = list(
        Employer.objects.filter(user__role='employer')
        .exclude(company_name__isnull=True)
        .values_list('company_name', flat=True)
        .distinct()
    )
    inquiry_name_parts = initial_inquiry.name.split(maxsplit=1) if initial_inquiry and initial_inquiry.name else []

    context = {
        'page_title': 'Post a Job - MultiBiz Admin',
        'current_page': 'post_job.php',
        'qualifications': qualifications,
        'existing_companies': [c for c in existing_companies if c],
        'existing_partner_companies': [c for c in existing_partner_companies if c],
        'initial_contact_first_name': inquiry_name_parts[0] if inquiry_name_parts else '',
        'initial_contact_last_name': inquiry_name_parts[1] if len(inquiry_name_parts) > 1 else '',
        'initial_contact_email': initial_inquiry.email if initial_inquiry else '',
        'form_values': request.POST if request.method == 'POST' else {},
        'selected_employment_type': selected_employment_type,
        'selected_posting_status': selected_posting_status,
        'error': error,
        'initial_inquiry': initial_inquiry,
        'initial_company_name': initial_company_name,
        'initial_title': initial_title,
        'initial_description': initial_description,
        'initial_requirements': initial_requirements,
        'initial_skills': initial_skills,
        'initial_location': initial_location,
        'initial_salary_range': initial_salary_range,
        'initial_employment_type': initial_employment_type,
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
    Allows admin to:
    - View all job requests submitted by companies
    - Review complete job information
    - Approve or reject job requests (with rejection reason)
    - Post/Publish approved job requests live to applicant-facing job listings
    - Filter by Pending, Approved, Active/Posted, Rejected, Closed
    """
    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)
    search = request.GET.get('search', '').strip()
    status_filter = request.GET.get('status', '').strip()

    if request.method == 'POST':
        action = request.POST.get('action')
        job_id = request.POST.get('job_id')
        job = get_object_or_404(JobPosting.objects.select_related('employer', 'employer__user'), pk=job_id)
        employer = job.employer
        emp_user = employer.user if employer else None
        company_name = employer.company_name if employer else 'MultiBiz Partner'

        if action == 'approve':
            job.status = 'approved'
            job.approved_at = timezone.now()
            job.reviewed_by_admin = admin_user
            job.admin_notes = request.POST.get('admin_notes', '').strip() or job.admin_notes
            job.save()

            if emp_user:
                Notification.objects.create(
                    user=emp_user,
                    title=f"Job Request Approved: {job.title}",
                    message=f"Your job vacancy request for '{job.title}' has been reviewed and approved by Admin. It is ready for publication.",
                    type='job'
                )
            log_audit_trail(request, admin_id, 'approve_job_request', f"Approved job request #{job.job_id} ({job.title}) for {company_name}", 'job', job.job_id, job.title)
            return redirect('/admin/jobs.php?approved=success')

        elif action in ['post_live', 'publish']:
            job.status = 'active'
            if not job.approved_at:
                job.approved_at = timezone.now()
            job.posted_at = timezone.now()
            job.reviewed_by_admin = admin_user
            job.save()

            if emp_user:
                Notification.objects.create(
                    user=emp_user,
                    title=f"Job Published Live: {job.title}",
                    message=f"Great news! Your job opening '{job.title}' is now live and accepting applicant submissions.",
                    type='job'
                )
                try:
                    send_job_posted_live_notification(
                        employer_email=emp_user.email,
                        employer_name=f"{emp_user.first_name} {emp_user.last_name}".strip() or company_name,
                        job_title=job.title,
                        company_name=company_name,
                        job_id=job.job_id
                    )
                except Exception as e:
                    print(f"[Admin Post Live Notification Error]: {e}")

            log_audit_trail(request, admin_id, 'post_job_live', f"Published job #{job.job_id} ({job.title}) live for {company_name}", 'job', job.job_id, job.title)
            return redirect('/admin/jobs.php?posted=success')

        elif action == 'reject':
            reason = request.POST.get('rejection_reason', '').strip() or "Job request details did not meet criteria."
            job.status = 'rejected'
            job.rejection_reason = reason
            job.reviewed_by_admin = admin_user
            job.save()

            if emp_user:
                Notification.objects.create(
                    user=emp_user,
                    title=f"Job Request Rejected: {job.title}",
                    message=f"Your job vacancy request for '{job.title}' was rejected. Reason: {reason}",
                    type='job'
                )
                try:
                    send_job_request_rejected_notification(
                        employer_email=emp_user.email,
                        employer_name=f"{emp_user.first_name} {emp_user.last_name}".strip() or company_name,
                        job_title=job.title,
                        company_name=company_name,
                        rejection_reason=reason
                    )
                except Exception as e:
                    print(f"[Admin Reject Notification Error]: {e}")

            log_audit_trail(request, admin_id, 'reject_job_request', f"Rejected job request #{job.job_id} ({job.title}) for {company_name}. Reason: {reason}", 'job', job.job_id, job.title)
            return redirect('/admin/jobs.php?rejected=success')

        elif action == 'toggle_status':
            job.status = 'closed' if job.status == 'active' else 'active'
            job.save()
            log_audit_trail(request, admin_id, 'toggle_job_status', f"Changed status of job {job.title} to {job.status}", 'job', job.job_id, job.title)
            return redirect('/admin/jobs.php')

        elif action == 'delete_job':
            title = job.title
            job.delete()
            log_audit_trail(request, admin_id, 'delete_job', f"Deleted job {title}", 'job', int(job_id), title)
            return redirect('/admin/jobs.php')

    jobs_base_qs = JobPosting.objects.select_related('employer', 'employer__user').annotate(
        applicant_count=Count('applications')
    )

    counts = {
        'all': jobs_base_qs.count(),
        'pending': jobs_base_qs.filter(status='pending').count(),
        'approved': jobs_base_qs.filter(status='approved').count(),
        'active': jobs_base_qs.filter(status='active').count(),
        'rejected': jobs_base_qs.filter(status='rejected').count(),
        'closed': jobs_base_qs.filter(status='closed').count(),
    }

    jobs_qs = jobs_base_qs.order_by('-posted_at')

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
        'page_title': 'All Jobs & Requests - MultiBiz Admin',
        'current_page': 'jobs.php',
        'jobs': jobs_qs,
        'counts': counts,
        'search': search,
        'status_filter': status_filter,
        'posted_success': request.GET.get('posted') == 'success',
        'approved_success': request.GET.get('approved') == 'success',
        'rejected_success': request.GET.get('rejected') == 'success',
        'updated_success': request.GET.get('updated') == 'success',
    }
    return render(request, 'admin/jobs.html', context)


@csrf_exempt
@require_role('admin')
def job_request_action_api(request):
    """
    AJAX endpoint for admin job request moderation actions (approve, post_live, reject).
    """
    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'POST method required'})

    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)
    job_id = request.POST.get('job_id')
    action = request.POST.get('action')
    reason = request.POST.get('reason', '').strip()
    admin_notes = request.POST.get('admin_notes', '').strip()

    job = get_object_or_404(JobPosting.objects.select_related('employer', 'employer__user'), pk=job_id)
    emp_user = job.employer.user if job.employer else None
    company_name = job.employer.company_name if job.employer else 'MultiBiz Partner'

    if action == 'approve':
        job.status = 'approved'
        job.approved_at = timezone.now()
        job.reviewed_by_admin = admin_user
        if admin_notes:
            job.admin_notes = admin_notes
        job.save()

        if emp_user:
            Notification.objects.create(
                user=emp_user,
                title=f"Job Request Approved: {job.title}",
                message=f"Your job vacancy request for '{job.title}' has been approved by Admin.",
                type='job'
            )
        log_audit_trail(request, admin_id, 'approve_job_request', f"Approved job request #{job.job_id} ({job.title})", 'job', job.job_id, job.title)
        return JsonResponse({'success': True, 'message': f"Job '{job.title}' approved successfully.", 'status': 'approved'})

    elif action in ['post_live', 'publish']:
        job.status = 'active'
        if not job.approved_at:
            job.approved_at = timezone.now()
        job.posted_at = timezone.now()
        job.reviewed_by_admin = admin_user
        job.save()

        if emp_user:
            Notification.objects.create(
                user=emp_user,
                title=f"Job Published Live: {job.title}",
                message=f"Your job '{job.title}' is now live and accepting applications.",
                type='job'
            )
            try:
                send_job_posted_live_notification(
                    employer_email=emp_user.email,
                    employer_name=f"{emp_user.first_name} {emp_user.last_name}".strip() or company_name,
                    job_title=job.title,
                    company_name=company_name,
                    job_id=job.job_id
                )
            except Exception as e:
                print(f"[Admin Post Live Error]: {e}")

        log_audit_trail(request, admin_id, 'post_job_live', f"Published job #{job.job_id} ({job.title}) live", 'job', job.job_id, job.title)
        return JsonResponse({'success': True, 'message': f"Job '{job.title}' is now active and posted live!", 'status': 'active'})

    elif action == 'reject':
        if not reason:
            return JsonResponse({'success': False, 'message': 'Please provide a rejection reason.'})
        job.status = 'rejected'
        job.rejection_reason = reason
        job.reviewed_by_admin = admin_user
        job.save()

        if emp_user:
            Notification.objects.create(
                user=emp_user,
                title=f"Job Request Rejected: {job.title}",
                message=f"Your job request for '{job.title}' was rejected. Reason: {reason}",
                type='job'
            )
            try:
                send_job_request_rejected_notification(
                    employer_email=emp_user.email,
                    employer_name=f"{emp_user.first_name} {emp_user.last_name}".strip() or company_name,
                    job_title=job.title,
                    company_name=company_name,
                    rejection_reason=reason
                )
            except Exception as e:
                print(f"[Admin Reject Error]: {e}")

        log_audit_trail(request, admin_id, 'reject_job_request', f"Rejected job #{job.job_id} ({job.title}). Reason: {reason}", 'job', job.job_id, job.title)
        return JsonResponse({'success': True, 'message': f"Job request rejected. Employer has been notified.", 'status': 'rejected'})

    return JsonResponse({'success': False, 'message': 'Unknown action'})


@require_role('admin')
def candidates_view(request):
    """
    Job-centric Candidates Pipeline.
    Each row = one job posting, showing total applicants + qualified/high-match counts.
    """
    search = request.GET.get('search', '').strip()

    jobs_qs = JobPosting.objects.select_related('employer').order_by('-posted_at')

    if search:
        jobs_qs = jobs_qs.filter(
            Q(title__icontains=search) |
            Q(employer__company_name__icontains=search) |
            Q(location__icontains=search)
        )

    job_summaries = []
    total_applicants = 0
    total_qualified = 0
    total_high_match = 0
    total_forwarded = 0

    for job in jobs_qs:
        apps = Application.objects.filter(job=job).select_related('applicant', 'job')
        count_all = apps.count()
        if count_all == 0:
            continue  # skip jobs with no applicants

        class_counts = {'high': 0, 'medium': 0, 'low': 0, 'unavailable': 0}
        for application in apps:
            result = compute_job_match_result(application.applicant, job)
            class_counts[result['match_class'] or 'unavailable'] += 1
        count_qualified = class_counts['high']
        count_high_match = class_counts['high']
        count_under_qualified = class_counts['medium']
        count_unclassified = 0
        count_not_qualified = class_counts['low']
        count_forwarded = apps.filter(forwarded_to_employer=True).count()
        count_pending = apps.filter(status='pending').count()

        total_applicants += count_all
        total_qualified += count_qualified
        total_high_match += count_high_match
        total_forwarded += count_forwarded

        employment_type = ''
        if hasattr(job, 'get_employment_type_display'):
            try:
                employment_type = job.get_employment_type_display()
            except Exception:
                employment_type = job.employment_type or ''
        else:
            employment_type = job.employment_type or ''

        job_summaries.append({
            'job': job,
            'job_id': job.job_id,
            'job_title': job.title,
            'company_name': job.employer.company_name if job.employer else 'MultiBiz Partner',
            'location': job.location or '',
            'employment_type': employment_type,
            'status': job.status,
            'count_all': count_all,
            'count_qualified': count_qualified,
            'count_under_qualified': count_under_qualified,
            'count_unclassified': count_unclassified,
            'count_not_qualified': count_not_qualified,
            'count_ml_unavailable': class_counts['unavailable'],
            'count_high_match': count_high_match,
            'count_forwarded': count_forwarded,
            'count_pending': count_pending,
            'posted_at': job.posted_at,
        })

    # Sort: most qualified applicants first
    job_summaries.sort(key=lambda x: x['count_qualified'], reverse=True)

    context = {
        'page_title': 'Candidate Pipeline - MultiBiz Admin',
        'current_page': 'candidates.php',
        'job_summaries': job_summaries,
        'search': search,
        'total_applicants': total_applicants,
        'total_qualified': total_qualified,
        'total_high_match': total_high_match,
        'total_forwarded': total_forwarded,
        'total_jobs': len(job_summaries),
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

            if new_status in ['pending', 'reviewed', 'shortlisted', 'rejected']:
                application.status = new_status
                application.reviewed_by_name = f"MultiBiz Admin ({admin_user.first_name})"

                timestamp_str = timezone.now().strftime('%Y-%m-%d %H:%M')
                new_entry = f"[{timestamp_str}] Admin screening status changed to {new_status.title()}."
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

                log_audit_trail(request, admin_id, 'update_application_status', f"Updated application screening status for {candidate_user.email} on {job.title} to {new_status}", 'application', application.application_id, candidate_user.email)
                success_msg = f"Candidate screening status updated to {new_status.title()}! Notification email sent to candidate."

        elif action == 'schedule_interview':
            error_msg = "Interview scheduling and hiring approvals are managed directly by the employer from their portal."

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

        elif action == 'forward_to_employer':
            admin_notes = request.POST.get('admin_notes', '').strip()
            match_result = compute_job_match_result(applicant, job)
            
            application.forwarded_to_employer = True
            application.forwarded_at = timezone.now()
            application.forwarded_by_admin = admin_user
            application.admin_notes = admin_notes
            if not application.employer_status or application.employer_status == 'for_review':
                application.employer_status = 'for_review'
            if application.status in ['pending', 'reviewed']:
                application.status = 'shortlisted'

            timestamp_str = timezone.now().strftime('%Y-%m-%d %H:%M')
            entry = f"[{timestamp_str}] Candidate forwarded to Employer by Admin."
            if admin_notes:
                entry += f" Notes: {admin_notes}"
            if application.remarks_history:
                application.remarks_history = f"{application.remarks_history}\n{entry}"
            else:
                application.remarks_history = entry

            application.save()

            # Notify employer
            emp_user = job.employer.user if (job.employer and job.employer.user) else None
            candidate_name = f"{candidate_user.first_name} {candidate_user.last_name}".strip() or candidate_user.email
            if emp_user:
                Notification.objects.create(
                    user=emp_user,
                    title=f"New Candidate Forwarded: {candidate_name}",
                    message=f"Admin has forwarded candidate {candidate_name} for '{job.title}'. Check your Applicants dashboard to review their profile.",
                    type='application'
                )
                try:
                    send_applicant_forwarded_to_employer_email(
                        to_email=emp_user.email,
                        applicant_name=candidate_name,
                        company_name=company_name,
                        job_title=job.title,
                        match_score=match_result['match_score'],
                        admin_notes=admin_notes
                    )
                except Exception as e:
                    print(f"[Admin Forward Candidate Mailer Error]: {e}")

            log_audit_trail(request, admin_id, 'forward_candidate_to_employer', f"Forwarded candidate {candidate_name} ({candidate_user.email}) for {job.title} to {company_name}", 'application', application.application_id, candidate_name)
            success_msg = f"Candidate {candidate_name} has been successfully forwarded to {company_name}!"

    interviews = InterviewSchedule.objects.filter(application=application).order_by('-interview_date')
    chatbot_answers = ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number')

    match_result = compute_job_match_result(applicant, job)
    application.match_score = match_result['match_score']
    cand_dict = {
        'ml_match_score': match_result['match_score'],
        'ml_match_class': match_result['match_class'],
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
        'qualification_status': match_result['qualification_status'],
        'success_msg': success_msg,
        'error_msg': error_msg,
    }
    return render(request, 'admin/view_candidate.html', context)


@csrf_exempt
@require_role('admin')
def forward_candidate_api(request):
    """
    Admin AJAX endpoint to forward an individual applicant to the employer.
    """
    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'POST method required'})

    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)
    app_id = request.POST.get('application_id')
    admin_notes = request.POST.get('admin_notes', '').strip()

    application = get_object_or_404(
        Application.objects.select_related('applicant', 'applicant__user', 'job', 'job__employer', 'job__employer__user'),
        pk=app_id
    )

    application.forwarded_to_employer = True
    application.forwarded_at = timezone.now()
    application.forwarded_by_admin = admin_user
    application.admin_notes = admin_notes
    if not application.employer_status or application.employer_status == 'for_review':
        application.employer_status = 'for_review'
    if application.status in ['pending', 'reviewed']:
        application.status = 'shortlisted'

    timestamp_str = timezone.now().strftime('%Y-%m-%d %H:%M')
    entry = f"[{timestamp_str}] Candidate forwarded to Employer by Admin."
    if admin_notes:
        entry += f" Notes: {admin_notes}"
    if application.remarks_history:
        application.remarks_history = f"{application.remarks_history}\n{entry}"
    else:
        application.remarks_history = entry

    emp_user = application.job.employer.user if (application.job and application.job.employer and application.job.employer.user) else None
    cand_user = application.applicant.user if (application.applicant and application.applicant.user) else None
    candidate_name = f"{cand_user.first_name} {cand_user.last_name}".strip() if cand_user else "Candidate"
    company_name = application.job.employer.company_name if (application.job and application.job.employer) else "MultiBiz Partner"
    job_title = application.job.title if application.job else "Position"
    match_result = (
        compute_job_match_result(application.applicant, application.job)
        if application.applicant and application.job else {'match_score': None}
    )
    application.save()

    if emp_user:
        Notification.objects.create(
            user=emp_user,
            title=f"New Candidate Forwarded: {candidate_name}",
            message=f"Admin has forwarded {candidate_name} for '{job_title}'. Review their application in your Employer Dashboard.",
            type='application'
        )
        try:
            send_applicant_forwarded_to_employer_email(
                to_email=emp_user.email,
                applicant_name=candidate_name,
                company_name=company_name,
                job_title=job_title,
                match_score=match_result['match_score'],
                admin_notes=admin_notes
            )
        except Exception as e:
            print(f"[forward_candidate_api Mailer Error]: {e}")

    log_audit_trail(request, admin_id, 'forward_candidate_to_employer', f"Forwarded candidate {candidate_name} for {job_title} to {company_name}", 'application', application.application_id, candidate_name)
    return JsonResponse({'success': True, 'message': f"Candidate {candidate_name} forwarded to {company_name}!"})


@csrf_exempt
@require_role('admin')
def batch_forward_candidates_api(request):
    """
    Admin AJAX endpoint to forward multiple selected applicants to their respective employer.
    """
    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'POST method required'})

    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)
    raw_ids = request.POST.get('application_ids', '')
    job_id = request.POST.get('job_id', '')
    scope = request.POST.get('scope', 'selected')
    admin_notes = request.POST.get('admin_notes', '').strip()

    app_ids = []
    if raw_ids:
        try:
            app_ids = json.loads(raw_ids) if raw_ids.startswith('[') else [int(x) for x in raw_ids.split(',') if x.strip().isdigit()]
        except Exception:
            app_ids = []

    if scope == 'unclassified':
        return JsonResponse({
            'success': False,
            'message': 'The legacy unclassified score band is no longer supported.',
        }, status=400)

    if job_id and str(job_id).isdigit():
        job = JobPosting.objects.filter(pk=int(job_id)).first()
        if job:
            if scope == 'all':
                apps = Application.objects.filter(job=job)
            elif scope in {'qualified', 'under_qualified', 'not_qualified'}:
                expected_class = {
                    'qualified': 'high',
                    'under_qualified': 'medium',
                    'not_qualified': 'low',
                }[scope]
                scoped_apps = list(
                    Application.objects.filter(job=job).select_related('applicant', 'job')
                )
                scored_apps = [
                    (application, compute_job_match_result(application.applicant, job))
                    for application in scoped_apps
                ]
                if any(not result['ready'] for _, result in scored_apps):
                    return JsonResponse({
                        'success': False,
                        'message': 'Employability scores are unavailable for one or more applicants. No candidates were forwarded.',
                    }, status=409)
                apps = [
                    application for application, result in scored_apps
                    if result['match_class'] == expected_class
                ]
            else:
                apps = Application.objects.filter(job=job, pk__in=app_ids)
        else:
            apps = Application.objects.filter(pk__in=app_ids)
    else:
        apps = Application.objects.filter(pk__in=app_ids)

    apps = list(apps.select_related(
        'applicant', 'applicant__user', 'job', 'job__employer', 'job__employer__user'
    )) if hasattr(apps, 'select_related') else list(apps)

    if not apps:
        return JsonResponse({'success': False, 'message': 'No candidates found for the selected scope'})
    count = 0
    for application in apps:
        application.forwarded_to_employer = True
        application.forwarded_at = timezone.now()
        application.forwarded_by_admin = admin_user
        if admin_notes:
            application.admin_notes = admin_notes
        if not application.employer_status or application.employer_status == 'for_review':
            application.employer_status = 'for_review'
        if application.status in ['pending', 'reviewed']:
            application.status = 'shortlisted'

        timestamp_str = timezone.now().strftime('%Y-%m-%d %H:%M')
        entry = f"[{timestamp_str}] Forwarded to Employer by Admin."
        if admin_notes:
            entry += f" Notes: {admin_notes}"
        if application.remarks_history:
            application.remarks_history = f"{application.remarks_history}\n{entry}"
        else:
            application.remarks_history = entry

        application.save()
        count += 1

        # Notify employer
        emp_user = application.job.employer.user if (application.job and application.job.employer and application.job.employer.user) else None
        cand_user = application.applicant.user if (application.applicant and application.applicant.user) else None
        if emp_user and cand_user:
            candidate_name = f"{cand_user.first_name} {cand_user.last_name}".strip() or cand_user.email
            job_title = application.job.title if application.job else "Job"
            Notification.objects.create(
                user=emp_user,
                title=f"New Candidate Forwarded: {candidate_name}",
                message=f"Admin has forwarded {candidate_name} for '{job_title}'.",
                type='application'
            )

    log_audit_trail(request, admin_id, 'batch_forward_candidates', f"Batch forwarded {count} candidates to employers")
    return JsonResponse({'success': True, 'message': f"Successfully forwarded {count} candidate(s) to employer(s)!", 'count': count})


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


def generate_candidates_excel_workbook(job=None, applications=None):
    """
    Generates an executive-styled, professional Excel (.xlsx) workbook
    containing ALL qualified and not-qualified applicants for a job.
    """
    import openpyxl
    from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
    from openpyxl.utils import get_column_letter

    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "Applicant Dossier"
    ws.views.sheetView[0].showGridLines = True

    # MultiBiz Brand Colors
    PRIMARY_NAVY = "0A3D6B"
    PRIMARY_BLUE = "1866A3"
    LIGHT_SLATE  = "F8FAFC"
    CARD_BG      = "F1F5F9"
    BORDER_CLR   = "CBD5E1"

    font_title     = Font(name="Calibri", size=14, bold=True, color="FFFFFF")
    font_meta_lbl  = Font(name="Calibri", size=9, bold=True, color="0A3D6B")
    font_meta_val  = Font(name="Calibri", size=9, color="334155")
    font_kpi_text  = Font(name="Calibri", size=10, bold=True, color="0A3D6B")
    font_tbl_head  = Font(name="Calibri", size=10, bold=True, color="FFFFFF")
    font_cell_main = Font(name="Calibri", size=9.5, color="1E293B")
    font_cell_bold = Font(name="Calibri", size=9.5, bold=True, color="0A3D6B")

    # Qualification Status Styling
    font_qual_ok    = Font(name="Calibri", size=9, bold=True, color="15803D")
    fill_qual_ok    = PatternFill(start_color="DCFCE7", end_color="DCFCE7", fill_type="solid") # Green

    font_qual_under = Font(name="Calibri", size=9, bold=True, color="B45309")
    fill_qual_under = PatternFill(start_color="FEF3C7", end_color="FEF3C7", fill_type="solid") # Amber

    font_qual_unclassified = Font(name="Calibri", size=9, bold=True, color="475569")
    fill_qual_unclassified = PatternFill(start_color="E2E8F0", end_color="E2E8F0", fill_type="solid")

    font_qual_not   = Font(name="Calibri", size=9, bold=True, color="B91C1C")
    fill_qual_not   = PatternFill(start_color="FEE2E2", end_color="FEE2E2", fill_type="solid") # Red

    # Application Status Styling
    font_status_acc = Font(name="Calibri", size=9, bold=True, color="15803D")
    font_status_rej = Font(name="Calibri", size=9, bold=True, color="B91C1C")
    font_status_pen = Font(name="Calibri", size=9, bold=True, color="C2410C")
    font_status_shl = Font(name="Calibri", size=9, bold=True, color="0369A1")

    fill_title   = PatternFill(start_color=PRIMARY_NAVY, end_color=PRIMARY_NAVY, fill_type="solid")
    fill_header  = PatternFill(start_color=PRIMARY_BLUE, end_color=PRIMARY_BLUE, fill_type="solid")
    fill_card    = PatternFill(start_color=CARD_BG, end_color=CARD_BG, fill_type="solid")
    fill_zebra_w = PatternFill(start_color="FFFFFF", end_color="FFFFFF", fill_type="solid")
    fill_zebra_g = PatternFill(start_color=LIGHT_SLATE, end_color=LIGHT_SLATE, fill_type="solid")

    thin_border = Border(
        left=Side(style="thin", color=BORDER_CLR),
        right=Side(style="thin", color=BORDER_CLR),
        top=Side(style="thin", color=BORDER_CLR),
        bottom=Side(style="thin", color=BORDER_CLR)
    )

    # 1. Main Header Title
    ws.merge_cells("A1:Q1")
    ws.row_dimensions[1].height = 34
    cell_a1 = ws["A1"]
    cell_a1.value = "MULTIBIZ INTERNATIONAL CORPORATION — APPLICANT EVALUATION MATRIX"
    cell_a1.font = font_title
    cell_a1.fill = fill_title
    cell_a1.alignment = Alignment(horizontal="center", vertical="center")

    # 2. Metadata Block
    job_title_str   = job.title if job else "All Job Vacancies"
    company_name_str = (job.employer.company_name if (job and job.employer) else "") or "MultiBiz International Partners"
    location_str    = f"{job.location} • {job.employment_type.title()}" if job else "Platform-Wide"
    export_time_str = timezone.now().strftime("%Y-%m-%d %H:%M:%S (PHT)")

    ws.row_dimensions[2].height = 6
    ws.row_dimensions[3].height = 20
    ws.row_dimensions[4].height = 22

    ws["A3"] = "Target Job:"
    ws["A3"].font = font_meta_lbl
    ws["B3"] = job_title_str
    ws["B3"].font = font_meta_val

    ws["C3"] = "Employer / Partner:"
    ws["C3"].font = font_meta_lbl
    ws["D3"] = company_name_str
    ws["D3"].font = font_meta_val

    ws["E3"] = "Work Location:"
    ws["E3"].font = font_meta_lbl
    ws["F3"] = location_str
    ws["F3"].font = font_meta_val

    ws["G3"] = "Export Generated:"
    ws["G3"].font = font_meta_lbl
    ws["H3"] = export_time_str
    ws["H3"].font = font_meta_val

    # 3. KPI Statistics Row
    total_cand = len(applications) if applications else 0
    qual_count = sum(1 for a in (applications or []) if a.get('qualification_status') == 'qualified')
    under_count = sum(1 for a in (applications or []) if a.get('qualification_status') == 'under_qualified')
    not_count  = sum(1 for a in (applications or []) if a.get('qualification_status') == 'not_qualified')
    unavailable_count = sum(1 for a in (applications or []) if a.get('qualification_status') == 'unavailable')

    ws.merge_cells("A4:Q4")
    kpi_cell = ws["A4"]
    kpi_cell.value = f"TOTAL APPLICANTS: {total_cand}   |   HIGH MATCH: {qual_count}   |   MEDIUM MATCH: {under_count}   |   LOW MATCH: {not_count}   |   MODEL UNAVAILABLE: {unavailable_count}   |   ALL APPLICANT STATUSES INCLUDED"
    kpi_cell.font = font_kpi_text
    kpi_cell.fill = fill_card
    kpi_cell.alignment = Alignment(horizontal="center", vertical="center")

    ws.row_dimensions[5].height = 6

    # 4. Table Headers (Row 6)
    headers = [
        ("No.", 5, "center"),
        ("App ID", 9, "center"),
        ("Applicant Full Name", 24, "left"),
        ("Email Address", 26, "left"),
        ("Contact Number", 16, "center"),
        ("Job Title Applied For", 24, "left"),
        ("Employer / Company", 22, "left"),
        ("Experience (Yrs)", 15, "center"),
        ("Highest Education", 18, "left"),
        ("Key Skills & Competencies", 30, "left"),
        ("Declared Qualifications", 28, "left"),
        ("AI Match Score", 15, "center"),
        ("Qualification Tier", 19, "center"),
        ("Application Status", 18, "center"),
        ("Applied Date", 16, "center"),
        ("Resume File", 20, "left"),
        ("Admin Remarks / History", 32, "left"),
    ]

    header_row = 6
    ws.row_dimensions[header_row].height = 26

    for col_idx, (head_text, width, align) in enumerate(headers, start=1):
        cell = ws.cell(row=header_row, column=col_idx)
        cell.value = head_text
        cell.font = font_tbl_head
        cell.fill = fill_header
        cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        cell.border = thin_border
        col_letter = get_column_letter(col_idx)
        ws.column_dimensions[col_letter].width = max(width, len(head_text) + 2)

    # 5. Data Rows (Row 7+)
    start_row = 7
    for idx, cand in enumerate(applications or [], start=1):
        curr_row = start_row + idx - 1
        ws.row_dimensions[curr_row].height = 22
        zebra_fill = fill_zebra_g if (idx % 2 == 0) else fill_zebra_w

        q_status = cand.get('qualification_status', 'not_qualified')
        if q_status == 'qualified':
            tier_label = "QUALIFIED"
            tier_font = font_qual_ok
            tier_fill = fill_qual_ok
        elif q_status == 'under_qualified':
            tier_label = "UNDER-QUALIFIED"
            tier_font = font_qual_under
            tier_fill = fill_qual_under
        elif q_status == 'unclassified':
            tier_label = "UNCLASSIFIED"
            tier_font = font_qual_unclassified
            tier_fill = fill_qual_unclassified
        elif q_status == 'unavailable':
            tier_label = "ML UNAVAILABLE"
            tier_font = font_qual_unclassified
            tier_fill = fill_qual_unclassified
        else:
            tier_label = "NOT QUALIFIED"
            tier_font = font_qual_not
            tier_fill = fill_qual_not

        app_status_raw = cand.get('status', 'pending').lower()
        if app_status_raw in ['accepted', 'hired']:
            status_font = font_status_acc
        elif app_status_raw in ['rejected', 'archived']:
            status_font = font_status_rej
        elif app_status_raw in ['shortlisted', 'interviewed']:
            status_font = font_status_shl
        else:
            status_font = font_status_pen

        row_data = [
            (idx, "center", font_cell_main, zebra_fill),
            (f"#{cand.get('application_id', '')}", "center", font_cell_bold, zebra_fill),
            (cand.get('full_name', ''), "left", font_cell_bold, zebra_fill),
            (cand.get('email', ''), "left", font_cell_main, zebra_fill),
            (cand.get('phone', '') or "N/A", "center", font_cell_main, zebra_fill),
            (cand.get('job_title', ''), "left", font_cell_main, zebra_fill),
            (cand.get('company_name', ''), "left", font_cell_main, zebra_fill),
            (cand.get('experience_years', 0), "center", font_cell_main, zebra_fill),
            (cand.get('education_level', 'Not specified'), "left", font_cell_main, zebra_fill),
            (cand.get('skills', 'None recorded'), "left", font_cell_main, zebra_fill),
            (cand.get('qualifications', 'None recorded'), "left", font_cell_main, zebra_fill),
            (
                f"{float(cand['match_score']):.1f}%" if cand.get('match_score') is not None else 'Unavailable',
                "center",
                font_cell_bold,
                zebra_fill,
            ),
            (tier_label, "center", tier_font, tier_fill),
            (cand.get('status', 'pending').title(), "center", status_font, zebra_fill),
            (cand.get('applied_at_formatted', ''), "center", font_cell_main, zebra_fill),
            (cand.get('resume_file', '') or "No resume file", "left", font_cell_main, zebra_fill),
            (cand.get('remarks', '') or "No remarks recorded", "left", font_cell_main, zebra_fill),
        ]

        for col_idx, (val, align, f_style, p_fill) in enumerate(row_data, start=1):
            cell = ws.cell(row=curr_row, column=col_idx)
            cell.value = val
            cell.font = f_style
            cell.fill = p_fill
            cell.alignment = Alignment(horizontal=align, vertical="center", wrap_text=(col_idx in [10, 11, 17]))
            cell.border = thin_border

    ws.freeze_panes = "A7"
    buf = io.BytesIO()
    wb.save(buf)
    buf.seek(0)
    return buf


@require_role('admin')
def export_candidates_excel_view(request, job_id=None):
    """
    Exports all qualified and not-qualified applicants for a given job (or all jobs)
    into a styled Excel (.xlsx) spreadsheet.
    Supports direct Excel download and Google Drive/Sheets import workflow.
    """
    admin_id = getCurrentUserId(request)
    j_id = job_id or request.GET.get('job_id') or request.GET.get('id')
    
    job_obj = None
    if j_id and str(j_id).isdigit() and int(j_id) > 0:
        job_obj = JobPosting.objects.filter(pk=int(j_id)).select_related('employer').first()

    apps_qs = Application.objects.select_related(
        'applicant', 'applicant__user', 'job', 'job__employer'
    ).order_by('-applied_at')

    if job_obj:
        apps_qs = apps_qs.filter(job=job_obj)

    # Specific selections if filtered
    selected_raw = request.GET.get('selected_candidates') or request.GET.getlist('selected_candidates')
    if selected_raw:
        if isinstance(selected_raw, list):
            s_ids = [int(x) for x in selected_raw if str(x).isdigit()]
        else:
            s_ids = [int(x) for x in str(selected_raw).split(',') if str(x).strip().isdigit()]
        if s_ids:
            apps_qs = apps_qs.filter(application_id__in=s_ids)

    qual_filter = request.GET.get('qual', '').strip()
    status_filter = request.GET.get('status', '').strip()
    search_q = request.GET.get('q', '').strip() or request.GET.get('search', '').strip()

    if status_filter:
        apps_qs = apps_qs.filter(status=status_filter)

    if search_q:
        apps_qs = apps_qs.filter(
            Q(applicant__user__first_name__icontains=search_q) |
            Q(applicant__user__last_name__icontains=search_q) |
            Q(applicant__user__email__icontains=search_q) |
            Q(job__title__icontains=search_q)
        )

    candidates_list = []
    for app in apps_qs:
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        if not user_obj:
            continue

        match_result = compute_job_match_result(applicant, app.job)
        match_score = match_result['match_score']
        q_status = match_result['qualification_status']

        # Filter by qualification tier if explicitly requested
        if qual_filter and qual_filter != 'all' and q_status != qual_filter:
            continue

        candidates_list.append({
            'application_id': app.application_id,
            'full_name': f"{user_obj.first_name} {user_obj.last_name}".strip() or user_obj.email,
            'email': user_obj.email,
            'phone': user_obj.phone or '',
            'job_title': app.job.title if app.job else 'N/A',
            'company_name': (app.job.employer.company_name if (app.job and app.job.employer) else '') or 'MultiBiz Partner',
            'experience_years': applicant.experience_years or 0,
            'education_level': applicant.education_level or 'Not specified',
            'skills': applicant.skills or 'None recorded',
            'qualifications': applicant.qualifications or 'None recorded',
            'match_score': match_score,
            'qualification_status': q_status,
            'status': app.status,
            'applied_at_formatted': app.applied_at.strftime('%Y-%m-%d %H:%M') if app.applied_at else '',
            'resume_file': app.resume_file or applicant.resume_file or '',
            'remarks': app.remarks_history or '',
        })

    # Sort descending by match score
    candidates_list.sort(
        key=lambda x: (x['match_score'] is not None, x['match_score'] or 0),
        reverse=True,
    )

    excel_file = generate_candidates_excel_workbook(job=job_obj, applications=candidates_list)
    
    clean_job_name = "".join(c if c.isalnum() else "_" for c in (job_obj.title if job_obj else "All_Jobs")).strip("_")
    filename = f"MultiBiz_Applicants_{clean_job_name}_{timezone.now().strftime('%Y%m%d_%H%M')}.xlsx"

    log_audit_trail(request, admin_id, 'export_applicants_excel', f"Exported {len(candidates_list)} applicants to Excel for job: {job_obj.title if job_obj else 'All Jobs'}")

    response = HttpResponse(
        excel_file.getvalue(),
        content_type='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    )
    response['Content-Disposition'] = f'attachment; filename="{filename}"'
    return response


@require_role('admin')
def upload_candidates_google_drive_api(request, job_id=None):
    """
    Directly uploads the generated Excel candidates workbook into the Google Drive folder
    without forcing a local browser download.
    """
    from django.http import JsonResponse
    from app.services.google_drive_service import upload_excel_to_google_drive, TARGET_DRIVE_FOLDER_URL

    admin_id = getCurrentUserId(request)
    j_id = job_id or request.GET.get('job_id') or request.POST.get('job_id') or request.GET.get('id')
    
    job_obj = None
    if j_id and str(j_id).isdigit() and int(j_id) > 0:
        job_obj = JobPosting.objects.filter(pk=int(j_id)).select_related('employer').first()

    apps_qs = Application.objects.select_related(
        'applicant', 'applicant__user', 'job', 'job__employer'
    ).order_by('-applied_at')

    if job_obj:
        apps_qs = apps_qs.filter(job=job_obj)

    # Specific selections if filtered
    selected_raw = request.GET.get('selected_candidates') or request.POST.get('selected_candidates') or request.GET.getlist('selected_candidates')
    if selected_raw:
        if isinstance(selected_raw, list):
            s_ids = [int(x) for x in selected_raw if str(x).isdigit()]
        else:
            s_ids = [int(x) for x in str(selected_raw).split(',') if str(x).strip().isdigit()]
        if s_ids:
            apps_qs = apps_qs.filter(application_id__in=s_ids)

    qual_filter = (request.GET.get('qual') or request.POST.get('qual') or '').strip()
    status_filter = (request.GET.get('status') or request.POST.get('status') or '').strip()
    search_q = (request.GET.get('q') or request.GET.get('search') or '').strip()

    if status_filter:
        apps_qs = apps_qs.filter(status=status_filter)

    if search_q:
        apps_qs = apps_qs.filter(
            Q(applicant__user__first_name__icontains=search_q) |
            Q(applicant__user__last_name__icontains=search_q) |
            Q(applicant__user__email__icontains=search_q) |
            Q(job__title__icontains=search_q)
        )

    candidates_list = []
    for app in apps_qs:
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        if not user_obj:
            continue

        match_result = compute_job_match_result(applicant, app.job)
        match_score = match_result['match_score']
        q_status = match_result['qualification_status']

        if qual_filter and qual_filter != 'all' and q_status != qual_filter:
            continue

        candidates_list.append({
            'application_id': app.application_id,
            'full_name': f"{user_obj.first_name} {user_obj.last_name}".strip() or user_obj.email,
            'email': user_obj.email,
            'phone': user_obj.phone or '',
            'job_title': app.job.title if app.job else 'N/A',
            'company_name': (app.job.employer.company_name if (app.job and app.job.employer) else '') or 'MultiBiz Partner',
            'experience_years': applicant.experience_years or 0,
            'education_level': applicant.education_level or 'Not specified',
            'skills': applicant.skills or 'None recorded',
            'qualifications': applicant.qualifications or 'None recorded',
            'match_score': match_score,
            'qualification_status': q_status,
            'status': app.status,
            'applied_at_formatted': app.applied_at.strftime('%Y-%m-%d %H:%M') if app.applied_at else '',
            'resume_file': app.resume_file or applicant.resume_file or '',
            'remarks': app.remarks_history or '',
        })

    candidates_list.sort(
        key=lambda x: (x['match_score'] is not None, x['match_score'] or 0),
        reverse=True,
    )
    excel_file = generate_candidates_excel_workbook(job=job_obj, applications=candidates_list)
    clean_job_name = "".join(c if c.isalnum() else "_" for c in (job_obj.title if job_obj else "All_Jobs")).strip("_")
    filename = f"MultiBiz_Applicants_{clean_job_name}_{timezone.now().strftime('%Y%m%d_%H%M')}.xlsx"

    upload_res = upload_excel_to_google_drive(excel_file.getvalue(), filename)

    if upload_res.get('success'):
        log_audit_trail(request, admin_id, 'export_applicants_gdrive', f"Uploaded {filename} with {len(candidates_list)} records directly to Google Drive")
        return JsonResponse({
            'success': True,
            'message': upload_res.get('message', f'Exported {filename} directly to Google Drive!'),
            'file_name': filename,
            'folder_url': upload_res.get('folder_url', TARGET_DRIVE_FOLDER_URL),
            'file_url': upload_res.get('file_url', TARGET_DRIVE_FOLDER_URL),
        })
    else:
        return JsonResponse({
            'success': False,
            'error': upload_res.get('error'),
            'message': upload_res.get('message', 'Google Drive upload requires authorization.'),
            'folder_url': upload_res.get('folder_url', TARGET_DRIVE_FOLDER_URL),
        }, status=400 if upload_res.get('error') != 'no_credentials' else 200)


@require_role('admin')
def export_candidates_csv_view(request, job_id=None):
    """
    Exports candidates in UTF-8 CSV format suitable for live Google Sheets import (=IMPORTDATA)
    or standard spreadsheet processing.
    """
    j_id = job_id or request.GET.get('job_id') or request.GET.get('id')
    job_obj = None
    if j_id and str(j_id).isdigit() and int(j_id) > 0:
        job_obj = JobPosting.objects.filter(pk=int(j_id)).select_related('employer').first()

    apps_qs = Application.objects.select_related(
        'applicant', 'applicant__user', 'job', 'job__employer'
    ).order_by('-applied_at')

    if job_obj:
        apps_qs = apps_qs.filter(job=job_obj)

    response = HttpResponse(content_type='text/csv; charset=utf-8-sig')
    clean_job_name = "".join(c if c.isalnum() else "_" for c in (job_obj.title if job_obj else "All_Jobs")).strip("_")
    response['Content-Disposition'] = f'attachment; filename="MultiBiz_Applicants_{clean_job_name}.csv"'

    writer = csv.writer(response)
    writer.writerow([
        'No.', 'App ID', 'Applicant Name', 'Email', 'Phone',
        'Job Title', 'Company', 'Experience (Yrs)', 'Education Level',
        'Skills', 'Qualifications', 'AI Match Score (%)',
        'Qualification Tier', 'Application Status', 'Applied Date',
        'Resume File', 'Admin Remarks'
    ])

    for idx, app in enumerate(apps_qs, start=1):
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        if not user_obj:
            continue

        match_result = compute_job_match_result(applicant, app.job)
        match_score = match_result['match_score']
        q_status = match_result['qualification_status'].replace('_', ' ').upper()
        if q_status == 'UNDER QUALIFIED':
            q_status = 'UNDER-QUALIFIED'

        writer.writerow([
            idx,
            app.application_id,
            f"{user_obj.first_name} {user_obj.last_name}".strip() or user_obj.email,
            user_obj.email,
            user_obj.phone or '',
            app.job.title if app.job else 'N/A',
            (app.job.employer.company_name if (app.job and app.job.employer) else '') or 'MultiBiz Partner',
            applicant.experience_years or 0,
            applicant.education_level or 'Not specified',
            applicant.skills or '',
            applicant.qualifications or '',
            f"{match_score:.1f}%" if match_score is not None else 'Unavailable',
            q_status,
            app.status.title(),
            app.applied_at.strftime('%Y-%m-%d %H:%M') if app.applied_at else '',
            app.resume_file or applicant.resume_file or '',
            app.remarks_history or '',
        ])

    return response


@require_role('admin')
def job_candidates_view(request, job_id):
    """
    Renders the dedicated Candidate Management dossier for a specific job posting,
    including automatic employability scores and High, Medium, and Low tiers,
    and direct Excel & Google Drive export capabilities.
    """
    job = get_object_or_404(JobPosting.objects.select_related('employer'), pk=job_id)
    # Ensure template compat properties
    job.id = job.job_id
    job.company_name = job.employer.company_name if job.employer else 'MultiBiz Partner'

    selected_qual   = request.GET.get('qual', '').strip()
    selected_status = request.GET.get('status', '').strip()
    query           = request.GET.get('q', '').strip()
    selected_sort   = request.GET.get('sort', 'score').strip()

    apps_qs = Application.objects.filter(job=job).select_related(
        'applicant', 'applicant__user'
    ).order_by('-applied_at')

    all_candidates = []
    for app in apps_qs:
        applicant = app.applicant
        user_obj = applicant.user if applicant else None
        if not user_obj:
            continue

        match_result = compute_job_match_result(applicant, job)
        match_score = match_result['match_score']
        q_status = match_result['qualification_status']
        app.match_score = match_score

        cand_info = {
            'application': app,
            'applicant': applicant,
            'user': user_obj,
            'match_score': match_score,
            'qualification_status': q_status,
            'is_top_match': False,
            'notice_sent': bool(app.status in ['reviewed', 'shortlisted', 'accepted', 'interviewed']),
            'forwarded_to_employer': app.forwarded_to_employer,
            'forwarded_at': app.forwarded_at,
            'employer_status': app.employer_status or 'for_review',
            'admin_notes': app.admin_notes or '',
            'admin_qualification': app.admin_qualification or q_status,
            'ml_match_class': match_result['match_class'],
            'ml_unavailable_reason': match_result['reason'],
        }
        all_candidates.append(cand_info)

    # Sort candidates
    if selected_sort == 'score':
        all_candidates.sort(
            key=lambda x: (
                x['match_score'] is not None,
                x['match_score'] or 0,
            ),
            reverse=True,
        )
    elif selected_sort == 'experience':
        all_candidates.sort(key=lambda x: x['applicant'].experience_years or 0, reverse=True)
    elif selected_sort == 'date':
        all_candidates.sort(key=lambda x: x['application'].applied_at, reverse=True)

    # Flag top 3 qualified matches
    qual_count = 0
    for c in all_candidates:
        if c['qualification_status'] == 'qualified':
            qual_count += 1
            if qual_count <= 3:
                c['is_top_match'] = True

    forwarded_filter = request.GET.get('forwarded', '').strip()

    # Compute KPI overview counters
    counts = {
        'all': len(all_candidates),
        'qualified': sum(1 for c in all_candidates if c['qualification_status'] == 'qualified'),
        'under_qualified': sum(1 for c in all_candidates if c['qualification_status'] == 'under_qualified'),
        'unclassified': sum(1 for c in all_candidates if c['qualification_status'] == 'unclassified'),
        'not_qualified': sum(1 for c in all_candidates if c['qualification_status'] == 'not_qualified'),
        'unavailable': sum(1 for c in all_candidates if c['qualification_status'] == 'unavailable'),
        'forwarded': sum(1 for c in all_candidates if c['forwarded_to_employer']),
        'not_forwarded': sum(1 for c in all_candidates if not c['forwarded_to_employer']),
        'under_review': sum(1 for c in all_candidates if c['application'].status in ['pending', 'reviewed']),
        'shortlisted': sum(1 for c in all_candidates if c['application'].status == 'shortlisted'),
    }

    # Filter for display
    displayed = all_candidates
    if selected_qual:
        displayed = [c for c in displayed if c['qualification_status'] == selected_qual]
    if selected_status:
        displayed = [c for c in displayed if c['application'].status == selected_status]
    if forwarded_filter == 'yes':
        displayed = [c for c in displayed if c['forwarded_to_employer']]
    elif forwarded_filter == 'no':
        displayed = [c for c in displayed if not c['forwarded_to_employer']]

    if query:
        q_low = query.lower()
        displayed = [
            c for c in displayed
            if q_low in f"{c['user'].first_name} {c['user'].last_name}".lower()
            or q_low in c['user'].email.lower()
            or q_low in (c['applicant'].skills or '').lower()
        ]

    context = {
        'job': job,
        'candidates': displayed,
        'counts': counts,
        'selected_qual': selected_qual,
        'selected_status': selected_status,
        'forwarded_filter': forwarded_filter,
        'query': query,
        'selected_sort': selected_sort,
        'current_page': 'candidates.php',
    }
    return render(request, 'admin/job_candidates.html', context)


@require_role('admin')
def notify_qualified_applicants_view(request, job_id):
    """Dispatches review notifications to all qualified applicants for a job."""
    admin_id = getCurrentUserId(request)
    job = get_object_or_404(JobPosting, pk=job_id)
    apps = Application.objects.filter(job=job).select_related('applicant__user', 'applicant', 'job')
    scored_apps = [
        (app, compute_job_match_result(app.applicant, job))
        for app in apps
    ]
    if any(not result['ready'] for _, result in scored_apps):
        return redirect(f'/admin/jobs/{job_id}/candidates/?notified=score_unavailable')

    count = 0
    for app, result in scored_apps:
        if result['match_class'] == 'high' and app.status == 'pending':
            app.status = 'reviewed'
            app.save()
            count += 1
            Notification.objects.create(
                user=app.applicant.user,
                title=f"Application Under Review: {job.title}",
                message=f"Good news! Your application for {job.title} meets the required qualifications and is now under active review.",
                type='application'
            )
    log_audit_trail(request, admin_id, 'notify_qualified_applicants', f"Notified {count} qualified applicants for job #{job_id}")
    return redirect(f'/admin/jobs/{job_id}/candidates/?notified=success')


@require_role('admin')
def forward_qualified_candidates_view(request, job_id):
    """
    Forwards all qualified candidates for a specific job posting to the employer company.
    """
    admin_id = getCurrentUserId(request)
    admin_user = get_object_or_404(User, pk=admin_id)
    job = get_object_or_404(JobPosting.objects.select_related('employer', 'employer__user'), pk=job_id)

    apps = Application.objects.filter(job=job).select_related('applicant', 'applicant__user', 'job', 'job__employer')
    scored_apps = [
        (app, compute_job_match_result(app.applicant, job))
        for app in apps
    ]
    if any(not result['ready'] for _, result in scored_apps):
        if request.headers.get('x-requested-with') == 'XMLHttpRequest':
            return JsonResponse({
                'success': False,
                'message': 'Employability scores are unavailable for one or more applicants. No candidates were forwarded.',
            }, status=409)
        return redirect(f'/admin/jobs/{job_id}/candidates/?notified=score_unavailable')

    count = 0
    now_ts = timezone.now()
    timestamp_str = now_ts.strftime('%Y-%m-%d %H:%M')

    for app, result in scored_apps:
        is_qual = result['match_class'] == 'high'
        if is_qual:
            app.forwarded_to_employer = True
            app.forwarded_at = now_ts
            app.forwarded_by_admin = admin_user
            if not app.employer_status or app.employer_status == 'for_review':
                app.employer_status = 'for_review'
            if app.status in ['pending', 'reviewed']:
                app.status = 'shortlisted'

            entry = f"[{timestamp_str}] Qualified candidate forwarded to Employer by Admin."
            if app.remarks_history:
                app.remarks_history = f"{app.remarks_history}\n{entry}"
            else:
                app.remarks_history = entry
            app.save()
            count += 1

    emp_user = job.employer.user if (job.employer and job.employer.user) else None
    company_name = job.employer.company_name if job.employer else "Employer"

    if emp_user and count > 0:
        Notification.objects.create(
            user=emp_user,
            title=f"Qualified Candidates Forwarded ({count}): {job.title}",
            message=f"Admin has forwarded {count} qualified applicant(s) for '{job.title}' to your portal for review.",
            type='application'
        )

    log_audit_trail(request, admin_id, 'forward_qualified_candidates', f"Forwarded {count} qualified candidates for {job.title} to {company_name}")

    if request.headers.get('x-requested-with') == 'XMLHttpRequest':
        return JsonResponse({
            'success': True,
            'message': f"Successfully forwarded {count} qualified candidate(s) to {company_name}!",
            'count': count
        })

    return redirect(f'/admin/jobs/{job_id}/candidates/?forwarded_qualified={count}')


@require_role('admin')
@require_POST
def notify_single_applicant_view(request, application_id):
    """Dispatches a single candidate review notification."""
    admin_id = getCurrentUserId(request)
    app = get_object_or_404(
        Application.objects.select_related('applicant__user', 'job', 'job__employer'),
        pk=application_id,
    )
    match_result = compute_job_match_result(app.applicant, app.job)
    if not match_result['ready']:
        log_audit_trail(
            request,
            admin_id,
            'notify_single_applicant_score_unavailable',
            f"Skipped screening notification for application #{application_id}: {match_result['reason']}",
        )
        return redirect(f'/admin/jobs/{app.job.job_id}/candidates/?notified=score_unavailable')

    if app.status == 'pending':
        app.status = 'reviewed'
        app.save()
    applicant_user = app.applicant.user
    applicant_name = f"{applicant_user.first_name or ''} {applicant_user.last_name or ''}".strip() or applicant_user.email
    company_name = app.job.employer.company_name if app.job.employer else 'MultiBiz'
    match_score = match_result['match_score']
    qualification_status = match_result['qualification_status']
    qualification_label = {
        'qualified': 'Qualified',
        'under_qualified': 'Under-Qualified',
        'not_qualified': 'Not Qualified',
    }[qualification_status]
    notice_message = (
        f"Your application for {app.job.title} was screened as {qualification_label} "
        f"with an employability score of {match_score:.0f}%. This is an initial screening result, "
        "not a final hiring decision."
    )
    Notification.objects.create(
        user=applicant_user,
        title=f"Screening Result: {qualification_label} - {app.job.title}",
        message=notice_message,
        type='application'
    )
    email_result = send_application_status_update_email(
        to_email=applicant_user.email,
        applicant_name=applicant_name,
        job_title=app.job.title,
        company_name=company_name,
        new_status=qualification_status,
        match_score=match_score,
    )
    notice_status = 'email_sent' if email_result.get('success') else 'email_failed'
    if not email_result.get('success'):
        print(f"[Admin Applicant Notice] Email delivery failed for application #{application_id}: {email_result.get('error')}")
    log_audit_trail(
        request,
        admin_id,
        'notify_single_applicant',
        f"Created dashboard notice for {applicant_user.email} for application #{application_id}; email {notice_status}",
    )
    return redirect(f'/admin/jobs/{app.job.job_id}/candidates/?notified={notice_status}')


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
    status_counts = Application.objects.values('status').annotate(count=Count('application_id')).order_by('status')
    jobs_by_type = JobPosting.objects.values('employment_type').annotate(count=Count('job_id')).order_by('employment_type')
    automatic_tier_counts = _automatic_tier_counts()

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
        'avg_match_score': None,
        'new_users_30d': User.objects.filter(created_at__gte=thirty_days_ago).count(),
        'new_apps_30d': Application.objects.filter(applied_at__gte=thirty_days_ago).count(),
        'status_counts': status_counts,
        'jobs_by_type': jobs_by_type,
        'automatic_tier_counts': automatic_tier_counts,
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

    def classify_inquiry(inq):
        """
        Classifies a ContactInquiry into:
        - 'company': Company staffing / talent hiring request from employers
        - 'applicant': Job seeker / applicant questions and application inquiries
        - 'general': Public website general inquiry
        """
        sub = (inq.subject or '').lower()
        msg = (inq.message or '').lower()
        email = (inq.email or '').lower().strip()

        # 1. Company Request check
        if '[staffing request]' in sub or 'position needed:' in msg or 'talent request' in msg or 'company talent' in sub:
            emp = Employer.objects.filter(Q(user__email__iexact=email) | Q(company_name__icontains=inq.name)).first()
            return {
                'type': 'company',
                'label': 'Company Request',
                'badge_class': 'badge-talent',
                'icon': 'fas fa-building',
                'employer_id': emp.employer_id if emp else None,
                'company_name': emp.company_name if emp else inq.name,
            }

        emp = Employer.objects.filter(user__email__iexact=email).first()
        if emp:
            return {
                'type': 'company',
                'label': 'Company Request',
                'badge_class': 'badge-talent',
                'icon': 'fas fa-building',
                'employer_id': emp.employer_id,
                'company_name': emp.company_name,
            }

        # 2. Applicant Inquiry check
        app = Applicant.objects.filter(user__email__iexact=email).select_related('user').first()
        if app:
            full_app_name = f"{app.user.first_name} {app.user.last_name}".strip() if app.user else inq.name
            return {
                'type': 'applicant',
                'label': 'Applicant Inquiry',
                'badge_class': 'badge-applicant',
                'icon': 'fas fa-user-graduate',
                'applicant_id': app.applicant_id,
                'applicant_name': full_app_name or inq.name,
                'skills': app.skills or '',
                'experience_years': app.experience_years or 0,
                'resume_file': app.resume_file or '',
                'qualifications': app.qualifications or '',
            }

        if any(k in sub or k in msg for k in ['application', 'resume', 'cv', 'job seeker', 'applicant', 'applied for', 'interview', 'job vacancy']):
            return {
                'type': 'applicant',
                'label': 'Applicant Inquiry',
                'badge_class': 'badge-applicant',
                'icon': 'fas fa-user-graduate',
                'applicant_id': None,
                'applicant_name': inq.name,
                'skills': '',
                'experience_years': 0,
                'resume_file': '',
                'qualifications': '',
            }

        # 3. General Public Inquiry
        return {
            'type': 'general',
            'label': 'Public Inquiry',
            'badge_class': 'badge-general',
            'icon': 'fas fa-globe',
        }

    # 1. LIST INQUIRIES
    if action == 'list_inquiries':
        filter_type = request.GET.get('filter', 'all')
        try:
            page = int(request.GET.get('page', 1))
        except (ValueError, TypeError):
            page = 1
        limit = 15

        all_inqs = list(ContactInquiry.objects.all().order_by('-created_at'))

        # Pre-classify and calculate counts
        classified_list = []
        count_all = len(all_inqs)
        count_company = 0
        count_applicant = 0
        count_general = 0
        count_unread = 0

        for inq in all_inqs:
            meta = classify_inquiry(inq)
            if not inq.is_read:
                count_unread += 1
            if meta['type'] == 'company':
                count_company += 1
            elif meta['type'] == 'applicant':
                count_applicant += 1
            else:
                count_general += 1
            classified_list.append((inq, meta))

        # Filter according to selected tab
        if filter_type in ('company', 'staffing'):
            filtered = [item for item in classified_list if item[1]['type'] == 'company']
        elif filter_type == 'applicant':
            filtered = [item for item in classified_list if item[1]['type'] == 'applicant']
        elif filter_type == 'general':
            filtered = [item for item in classified_list if item[1]['type'] == 'general']
        elif filter_type == 'new':
            filtered = [item for item in classified_list if item[0].status == 'new']
        elif filter_type == 'unread':
            filtered = [item for item in classified_list if not item[0].is_read]
        elif filter_type == 'replied':
            filtered = [item for item in classified_list if item[0].status == 'replied']
        else:
            filtered = classified_list

        total = len(filtered)
        start = (page - 1) * limit
        rows_data = []

        for inq, meta in filtered[start:start + limit]:
            if meta['type'] == 'company':
                sp = parse_job_specs(inq.message, inq.subject)
                clean_excerpt = f"Role: {sp['title'] or inq.subject} • {sp['employment_type'].title()} • {sp['location']}"
            else:
                clean_excerpt = inq.message[:80]
                if len(inq.message) > 80:
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
                'is_staffing_request': (meta['type'] == 'company'),
                'sender_type': meta['type'],
                'sender_label': meta['label'],
                'badge_class': meta['badge_class'],
                'badge_icon': meta['icon'],
            })

        return JsonResponse({
            'success': True,
            'rows': rows_data,
            'total': total,
            'limit': limit,
            'counts': {
                'all': count_all,
                'company': count_company,
                'applicant': count_applicant,
                'general': count_general,
                'unread': count_unread,
            }
        })

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
        sender_meta = classify_inquiry(inquiry)

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
            'sender_meta': sender_meta,
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
        all_inqs = list(ContactInquiry.objects.all())
        unread_count = 0
        company_count = 0
        applicant_count = 0
        general_count = 0
        for inq in all_inqs:
            if not inq.is_read:
                unread_count += 1
            meta = classify_inquiry(inq)
            if meta['type'] == 'company':
                company_count += 1
            elif meta['type'] == 'applicant':
                applicant_count += 1
            else:
                general_count += 1

        return JsonResponse({
            'success': True,
            'count': unread_count,
            'company_count': company_count,
            'applicant_count': applicant_count,
            'general_count': general_count,
            'total_count': len(all_inqs)
        })

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
