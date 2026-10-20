from datetime import datetime, timedelta

from django.db.models import Q
from django.utils import timezone

from app.models import Application, InterviewSchedule, Notification, User


def close_job_and_notify_applicants(job, reason):
    if job.status != 'closed':
        job.status = 'closed'
        job.closure_reason = reason
        job.save(update_fields=['status', 'closure_reason', 'updated_at'])

    active_applications = Application.objects.filter(job=job).exclude(
        status__in=('accepted', 'hired', 'rejected', 'withdrawn', 'job_closed', 'offer_declined'),
    ).exclude(
        employer_status__in=('hired', 'rejected', 'not_qualified', 'withdrawn', 'job_closed', 'offer_declined'),
    ).select_related('applicant__user')
    for application in active_applications:
        application.status = 'job_closed'
        application.employer_status = 'job_closed'
        history = list(application.status_history or [])
        history.append({
            'stage': 'Job Closed',
            'status': 'job_closed',
            'actor_name': job.employer.company_name or 'Employer',
            'actor_role': 'Employer',
            'timestamp': timezone.now().strftime('%Y-%m-%d %H:%M'),
            'notes': reason,
        })
        application.status_history = history
        application.save(update_fields=[
            'status', 'employer_status', 'status_history', 'updated_at',
        ])
        Notification.objects.create(
            user=application.applicant.user,
            title=f'Job closed: {job.title}',
            message=(
                f'The hiring process for {job.title} has closed. '
                'Thank you for your application.'
            ),
            type='job_update',
        )


def close_job_if_positions_filled(job):
    hired_count = Application.objects.filter(job=job).filter(
        Q(employer_status='hired') | Q(status__in=('accepted', 'hired')),
    ).count()
    if job.status == 'active' and hired_count >= max(job.positions_available, 1):
        close_job_and_notify_applicants(
            job,
            'All available positions have been filled.',
        )


def process_workflow_reminders(now=None):
    now = now or timezone.now()
    admin_cutoff = now - timedelta(hours=48)
    employer_cutoff = now - timedelta(hours=72)
    repeat_cutoff = now - timedelta(hours=24)
    admins = list(User.objects.filter(role='admin', status='active'))
    admin_reminders = 0
    employer_reminders = 0
    interview_reminders = 0

    pending_admin_review = Application.objects.filter(
        status='pending',
        forwarded_to_employer=False,
        applied_at__lte=admin_cutoff,
        job__status='active',
    ).filter(
        Q(admin_reminder_at__isnull=True) | Q(admin_reminder_at__lte=repeat_cutoff),
    ).select_related('job', 'applicant__user')
    for application in pending_admin_review:
        for admin in admins:
            Notification.objects.create(
                user=admin,
                title='Application awaiting admin review',
                message=f'Application #{application.application_id} for {application.job.title} has awaited screening for 48 hours.',
                type='application',
            )
            admin_reminders += 1
        if admins:
            application.admin_reminder_at = now
            application.save(update_fields=['admin_reminder_at', 'updated_at'])

    pending_employer_review = Application.objects.filter(
        forwarded_to_employer=True,
        employer_status='for_review',
        forwarded_at__isnull=False,
        forwarded_at__lte=employer_cutoff,
        job__status='active',
    ).filter(
        Q(employer_reminder_at__isnull=True) | Q(employer_reminder_at__lte=repeat_cutoff),
    ).select_related('job__employer__user', 'applicant__user')
    for application in pending_employer_review:
        employer_user = application.job.employer.user
        Notification.objects.create(
            user=employer_user,
            title='Candidate review is overdue',
            message=f'Application #{application.application_id} for {application.job.title} has awaited your review for 72 hours.',
            type='application',
        )
        application.employer_reminder_at = now
        application.save(update_fields=['employer_reminder_at', 'updated_at'])
        employer_reminders += 1

    local_now = timezone.localtime(now)
    interview_window_end = local_now + timedelta(hours=24)
    upcoming_interviews = InterviewSchedule.objects.filter(
        status='scheduled',
        reminder_sent_at__isnull=True,
        interview_date__gte=local_now.date(),
        interview_date__lte=interview_window_end.date(),
    ).select_related('application__applicant__user', 'application__job', 'employer__user')
    for interview in upcoming_interviews:
        if not interview.start_time:
            continue
        interview_start = timezone.make_aware(
            datetime.combine(interview.interview_date, interview.start_time),
            timezone.get_current_timezone(),
        )
        if not local_now < interview_start <= interview_window_end:
            continue
        message = (
            f'Interview for {interview.application.job.title} is scheduled for '
            f'{interview.interview_date:%b %d, %Y} at {interview.interview_time or interview.start_time}.'
        )
        for user in (interview.application.applicant.user, interview.employer.user):
            Notification.objects.create(
                user=user,
                title='Interview reminder',
                message=message,
                type='application',
            )
        interview.reminder_sent_at = now
        interview.save(update_fields=['reminder_sent_at', 'updated_at'])
        interview_reminders += 1

    return {
        'admin': admin_reminders,
        'employer': employer_reminders,
        'interview': interview_reminders,
    }