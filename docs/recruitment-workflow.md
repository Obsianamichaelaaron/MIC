# Recruitment workflow

## Employer verification

Self-registered employer companies start as `pending`. Admins review company
details from **Admin → Users**, then verify or reject the company. Only verified
companies can submit job requests. Employers created directly by an admin and
employer records that existed before this workflow are treated as verified.

## Application stages

1. Applicant submits an application to an active job.
2. Admin screens it and forwards qualified candidates to the employer.
3. Employer reviews the candidate, qualifies/rejects them, or schedules an
   interview.
4. Applicant confirms, declines, or requests a reschedule. The employer can
   reschedule/cancel the interview or mark it complete.
5. After an interview, the employer may send offer details. The applicant
   accepts or declines. Existing direct-hire actions remain available for
   compatibility.
6. Applicants can withdraw before a terminal decision. Employers can close a
   job; remaining active applications are marked closed and notified. A job
   also closes when its available positions have all been accepted.

## Scheduled reminders

The reminder job creates in-app notifications for admin reviews pending after
48 hours, employer reviews pending after 72 hours, and interviews scheduled
within the next 24 hours. Review reminders repeat no more than once per day;
each interview receives one reminder.

The project uses SQLite on a Render persistent disk, so a separate cron
instance must not open the database file directly. Instead, configure the
GitHub Actions workflow to call the web service:

1. Set `WORKFLOW_REMINDER_TOKEN` on the Render web service to a long random
   value, and set the same value as a GitHub Actions secret with that name.
2. Set the GitHub Actions repository variable `WORKFLOW_REMINDER_URL` to
   `https://multibiz.global/internal/workflow-reminders/` (or the deployed
   site's equivalent).
3. Enable Actions and run **Workflow reminders** once with `workflow_dispatch`
   to verify the endpoint. The workflow then runs hourly.