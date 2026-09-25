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
from app.services.mailer import (
    send_application_status_update_email,
    send_talent_request_admin_notification,
    send_talent_request_employer_confirmation
)

@require_role('employer')
def dashboard_view(request):
    """
    Employer Dashboard matching employer/dashboard.php.
    """
    user_id = getCurrentUserId(request)
    user = get_object_or_404(User, pk=user_id)
    employer, _ = Employer.objects.get_or_create(user=user)

    # Job metrics
    total_jobs = JobPosting.objects.filter(employer=employer).count()
    active_jobs = JobPosting.objects.filter(employer=employer, status='active').count()

    # Application metrics
    total_applications = Application.objects.count()
    pending_applications = Application.objects.filter(status='pending').count()

    # Recent applications matching PHP SELECT
    raw_recent = Application.objects.select_related('job', 'applicant', 'applicant__user', 'job__employer').order_by('-applied_at')[:5]
    recent_applications = []
    for a in raw_recent:
        u = a.applicant.user if (a.applicant and a.applicant.user) else None
        recent_applications.append({
            'application_id': a.application_id,
            'job_title': a.job.title if a.job else '',
            'first_name': u.first_name if u else '',
            'last_name': u.last_name if u else '',
            'email': u.email if u else '',
            'employability_score': a.applicant.employability_score if a.applicant else None,
            'company_name': a.job.employer.company_name if (a.job and a.job.employer) else '',
            'status': a.status,
            'applied_at': a.applied_at,
        })

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
        'total_jobs': total_jobs,
        'active_jobs': active_jobs,
        'total_applications': total_applications,
        'pending_applications': pending_applications,
        'recent_applications': recent_applications,
        'unread_count': unread_count,
    }
    return render(request, 'employer/dashboard.html', context)


@require_role('employer')
def post_job_view(request):
    """
    Post New Job matching employer/post_job.php.
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    error = None
    if request.method == 'POST':
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
            error = "Job title is required"
        else:
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

            # Save qualification mappings
            for q_id in qualification_ids:
                if q_id.isdigit():
                    q_obj = Qualification.objects.filter(pk=int(q_id)).first()
                    if q_obj:
                        JobQualificationMapping.objects.create(job=job, qualification=q_obj)

            return redirect('/employer/jobs.php?posted=success')

    qualifications = Qualification.objects.filter(status='active').order_by('name')

    context = {
        'page_title': 'Post a Job - MultiBiz',
        'current_page': 'post_job.php',
        'employer': employer,
        'qualifications': qualifications,
        'error': error,
    }
    return render(request, 'employer/post_job.html', context)


@require_role('employer')
def request_talent_view(request):
    """
    Company Talent & Job Description Request to Admin.
    Allows employer to specify hiring needs/job description and send directly to MultiBiz Admin for review & posting.
    """
    user_id = getCurrentUserId(request)
    user = get_object_or_404(User, pk=user_id)
    employer, _ = Employer.objects.get_or_create(user=user)

    error = None
    success = None

    if request.method == 'POST':
        title = request.POST.get('title', '').strip()
        headcount = request.POST.get('headcount', '1').strip()
        employment_type = request.POST.get('employment_type', 'full-time')
        location = request.POST.get('location', '').strip() or getattr(employer, 'company_address', '') or 'Metro Manila'
        salary_range = request.POST.get('salary_range', '').strip()
        skills_required = request.POST.get('skills_required', '').strip()
        urgency = request.POST.get('urgency', 'Normal').strip()
        description = request.POST.get('description', '').strip()
        requirements = request.POST.get('requirements', '').strip()
        special_notes = request.POST.get('special_notes', '').strip()

        if not title:
            error = "Position Title is required."
        elif not description:
            error = "Job Description is required."
        else:
            job_specs = {
                'title': title,
                'company_name': employer.company_name or f"{user.first_name} {user.last_name}",
                'employer_id': employer.employer_id,
                'headcount': int(headcount) if headcount.isdigit() else 1,
                'employment_type': employment_type,
                'location': location,
                'salary_range': salary_range,
                'skills_required': skills_required,
                'urgency': urgency,
                'description': description,
                'requirements': requirements,
                'special_notes': special_notes,
            }

            formatted_message = (
                f"Position Needed: {title}\n"
                f"Company: {job_specs['company_name']}\n"
                f"Headcount: {job_specs['headcount']}\n"
                f"Employment Type: {employment_type}\n"
                f"Location: {location}\n"
                f"Salary Range: {salary_range or 'Competitive / Negotiable'}\n"
                f"Urgency: {urgency}\n"
                f"Skills: {skills_required}\n\n"
                f"--- Job Description ---\n"
                f"{description}\n\n"
                f"--- Candidate Requirements ---\n"
                f"{requirements or 'Not specified'}\n\n"
                f"--- Special Notes ---\n"
                f"{special_notes or 'None'}"
            )

            subject_line = f"[Staffing Request] {title} - {job_specs['company_name']}"

            inquiry = ContactInquiry.objects.create(
                name=f"{user.first_name} {user.last_name}".strip() or employer.company_name,
                email=user.email,
                subject=subject_line,
                message=formatted_message,
                status='new'
            )

            # Send Email to Admin
            send_talent_request_admin_notification(
                inquiry_id=inquiry.id,
                company_name=job_specs['company_name'],
                contact_name=f"{user.first_name} {user.last_name}".strip(),
                contact_email=user.email,
                job_title=title,
                job_specs=job_specs
            )

            # Send Confirmation Email to Employer
            send_talent_request_employer_confirmation(
                to_email=user.email,
                company_name=job_specs['company_name'],
                contact_name=user.first_name or employer.company_name,
                job_title=title
            )

            # Create in-app notification
            Notification.objects.create(
                user=user,
                title=f"Talent Request Submitted: {title}",
                message=f"Your hiring request for '{title}' has been submitted to MultiBiz Admin. We will notify you once reviewed and posted.",
                type='job_update'
            )

            success = f"Your talent request for '{title}' has been sent to MultiBiz Admin! You will receive an email as soon as it is reviewed and posted live."

    qualifications = Qualification.objects.filter(status='active').order_by('name')

    context = {
        'page_title': 'Request Talent / Sourcing - MultiBiz',
        'current_page': 'request_talent.php',
        'employer': employer,
        'user': user,
        'qualifications': qualifications,
        'success': success,
        'error': error,
    }
    return render(request, 'employer/request_talent.html', context)


@require_role('employer')
def jobs_view(request):
    """
    Manage Posted Jobs matching employer/jobs.php.
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    status_filter = request.GET.get('status', '')
    
    # Handle action POSTs (toggle status, delete)
    if request.method == 'POST':
        action = request.POST.get('action')
        job_id = request.POST.get('job_id')
        job = get_object_or_404(JobPosting, pk=job_id, employer=employer)

        if action == 'toggle_status':
            job.status = 'closed' if job.status == 'active' else 'active'
            job.save()
            return redirect('/employer/jobs.php')
        elif action == 'delete':
            job.delete()
            return redirect('/employer/jobs.php?deleted=1')

    jobs_qs = JobPosting.objects.filter(employer=employer).annotate(
        applicant_count=Count('applications')
    ).order_by('-posted_at')

    if status_filter:
        jobs_qs = jobs_qs.filter(status=status_filter)

    context = {
        'page_title': 'My Jobs - MultiBiz',
        'current_page': 'jobs.php',
        'jobs': jobs_qs,
        'status_filter': status_filter,
        'posted_success': request.GET.get('posted') == 'success',
    }
    return render(request, 'employer/jobs.html', context)


@require_role('employer')
def edit_job_view(request, job_id=None):
    """
    Edit Posted Job matching employer/edit_job.php.
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
        status = request.POST.get('status', 'active')
        qualification_ids = request.POST.getlist('qualifications')

        if not title:
            error = "Job title is required"
        else:
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
    AI-Ranked Candidate Kanban Board matching employer/candidates.php & includes/ml/candidate_ranking.php.
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    job_id = request.GET.get('job_id', '0')
    
    # Query applications for employer's jobs
    apps_qs = Application.objects.filter(
        job__employer=employer
    ).select_related('applicant', 'applicant__user', 'job')

    if job_id and job_id.isdigit() and int(job_id) > 0:
        apps_qs = apps_qs.filter(job_id=int(job_id))

    candidates = []
    for app in apps_qs:
        applicant = app.applicant
        user_obj = applicant.user
        
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
            'job_id': app.job.job_id,
            'job_title': app.job.title,
            'company_name': employer.company_name or '',
            'resume_file': app.resume_file or applicant.resume_file or '',
        }
        
        # Calculate AI ranking score and tier
        score_info = calculate_candidate_ml_score(cand_dict)
        cand_dict['ml_ranking_score'] = score_info['ml_ranking_score']
        cand_dict['ranking_category'] = score_info['ranking_category']
        candidates.append(cand_dict)

    # Sort descending by ML score
    candidates.sort(key=lambda x: x['ml_ranking_score'], reverse=True)

    excellent_candidates = [c for c in candidates if c['ranking_category'] == 'excellent']
    good_candidates = [c for c in candidates if c['ranking_category'] == 'good']
    average_candidates = [c for c in candidates if c['ranking_category'] == 'average']
    poor_candidates = [c for c in candidates if c['ranking_category'] == 'poor']

    jobs_for_filter = JobPosting.objects.filter(employer=employer).order_by('-posted_at')

    context = {
        'page_title': 'Candidates - MultiBiz',
        'current_page': 'candidates.php',
        'candidates': candidates,
        'excellent_candidates': excellent_candidates,
        'good_candidates': good_candidates,
        'average_candidates': average_candidates,
        'poor_candidates': poor_candidates,
        'jobs': jobs_for_filter,
        'selected_job_id': int(job_id) if job_id.isdigit() else 0,
    }
    return render(request, 'employer/candidates.html', context)


@require_role('employer')
def recommended_candidates_view(request):
    """
    AI Recommended Candidates matching employer/recommended_candidates.php.
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    job_id = request.GET.get('job_id', '0')

    # All applicants
    applicants = Applicant.objects.select_related('user').all()
    jobs = JobPosting.objects.filter(employer=employer, status='active')

    recommended_list = []
    for app in applicants:
        # Match against active jobs
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
        'current_page': 'candidates.php',
        'recommended_list': recommended_list,
        'jobs': jobs,
        'selected_job_id': int(job_id) if job_id.isdigit() else 0,
    }
    return render(request, 'employer/recommended_candidates.html', context)


@require_role('employer')
def view_candidate_view(request, application_id=None):
    """
    Candidate Profile Review, Interview Scheduling & Status Updates
    matching employer/view_candidate.php.
    """
    user_id = getCurrentUserId(request)
    employer, _ = Employer.objects.get_or_create(user_id=user_id)

    app_id = application_id or request.GET.get('id') or request.GET.get('application_id')
    application = get_object_or_404(
        Application.objects.select_related('applicant', 'applicant__user', 'job'),
        pk=app_id,
        job__employer=employer
    )

    applicant = application.applicant
    candidate_user = applicant.user
    job = application.job

    success_msg = None
    error_msg = None

    if request.method == 'POST':
        action = request.POST.get('action')

        if action == 'update_status':
            new_status = request.POST.get('status')
            remarks = request.POST.get('remarks', '').strip()
            
            if new_status in ['pending', 'reviewed', 'shortlisted', 'interviewed', 'accepted', 'rejected']:
                application.status = new_status
                application.reviewed_by_employer_id = employer.employer_id
                application.reviewed_by_name = employer.company_name or 'Employer'
                
                timestamp_str = timezone.now().strftime('%Y-%m-%d %H:%M')
                new_entry = f"[{timestamp_str}] Status changed to {new_status.title()}."
                if remarks:
                    new_entry += f" Remarks: {remarks}"
                
                if application.remarks_history:
                    application.remarks_history = f"{application.remarks_history}\n{new_entry}"
                else:
                    application.remarks_history = new_entry
                
                application.save()

                # Notify applicant
                Notification.objects.create(
                    user=candidate_user,
                    title=f"Application Update: {job.title}",
                    message=f"Your application status for {job.title} at {employer.company_name} was updated to {new_status.title()}.",
                    type='application'
                )

                # Send email notification to applicant
                try:
                    send_application_status_update_email(
                        to_email=candidate_user.email,
                        applicant_name=f"{candidate_user.first_name} {candidate_user.last_name}".strip() or candidate_user.email,
                        job_title=job.title,
                        company_name=employer.company_name or 'Employer',
                        new_status=new_status,
                        remarks=remarks
                    )
                except Exception as e:
                    print(f"[Employer Status Update Mailer] Error: {e}")

                success_msg = f"Status updated to {new_status.title()}!"

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
                InterviewSchedule.objects.create(
                    application=application,
                    employer=employer,
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

                # Notify candidate
                Notification.objects.create(
                    user=candidate_user,
                    title=f"Interview Scheduled: {job.title}",
                    message=f"An interview has been scheduled on {interview_date} at {start_time} for {job.title}.",
                    type='application'
                )

                # Send email notification
                try:
                    send_application_status_update_email(
                        to_email=candidate_user.email,
                        applicant_name=f"{candidate_user.first_name} {candidate_user.last_name}".strip() or candidate_user.email,
                        job_title=job.title,
                        company_name=employer.company_name or 'Employer',
                        new_status='interviewed',
                        remarks=f"Interview scheduled on {interview_date} ({start_time} - {end_time})."
                    )
                except Exception as e:
                    print(f"[Interview Scheduled Mailer] Error: {e}")

                success_msg = "Interview scheduled successfully!"

        elif action == 'submit_feedback':
            rating = request.POST.get('rating', '5')
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

    # Scheduled interviews for this candidate
    interviews = InterviewSchedule.objects.filter(application=application).order_by('-interview_date')
    
    # Chatbot answers
    chatbot_answers = ChatbotAnswer.objects.filter(applicant=applicant).order_by('question_number')

    # Calculate ML scores
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
        'applicant': applicant,
        'candidate_user': candidate_user,
        'job': job,
        'interviews': interviews,
        'chatbot_answers': chatbot_answers,
        'ml_score': score_info['ml_ranking_score'],
        'tier': score_info['ranking_category'],
        'success_msg': success_msg,
        'error_msg': error_msg,
    }
    return render(request, 'employer/view_candidate.html', context)


@csrf_exempt
@require_role('employer')
def update_status_api(request):
    """
    AJAX endpoint for candidate status update matching employer/update_status.php.
    """
    user_id = getCurrentUserId(request)
    employer = get_object_or_404(Employer, user_id=user_id)

    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'Invalid method'})

    app_id = request.POST.get('application_id')
    status = request.POST.get('status')
    remarks = request.POST.get('remarks', '').strip()

    application = get_object_or_404(Application, pk=app_id, job__employer=employer)
    if status in ['pending', 'reviewed', 'shortlisted', 'interviewed', 'accepted', 'rejected']:
        application.status = status
        application.reviewed_by_employer_id = employer.employer_id
        application.reviewed_by_name = employer.company_name or 'Employer'
        
        timestamp_str = timezone.now().strftime('%Y-%m-%d %H:%M')
        new_entry = f"[{timestamp_str}] Status changed to {status.title()}."
        if remarks:
            new_entry += f" Remarks: {remarks}"
        
        if application.remarks_history:
            application.remarks_history = f"{application.remarks_history}\n{new_entry}"
        application.save()

        # Send email notification and in-app notification
        try:
            cand_user = application.applicant.user if (application.applicant and application.applicant.user) else None
            if cand_user:
                Notification.objects.create(
                    user=cand_user,
                    title=f"Application Update: {application.job.title}",
                    message=f"Your application status for {application.job.title} at {employer.company_name} was updated to {status.title()}.",
                    type='application'
                )
                send_application_status_update_email(
                    to_email=cand_user.email,
                    applicant_name=f"{cand_user.first_name} {cand_user.last_name}".strip() or cand_user.email,
                    job_title=application.job.title,
                    company_name=employer.company_name or 'Employer',
                    new_status=status,
                    remarks=remarks
                )
        except Exception as e:
            print(f"[update_status_api Mailer] Error: {e}")

        return JsonResponse({'success': True, 'message': f'Status updated to {status.title()}'})

    return JsonResponse({'success': False, 'message': 'Invalid status'})


@require_role('employer')
def chatbot_review_view(request):
    """
    Employer view of applicant chatbot assessment answers matching employer/chatbot_review.php.
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
