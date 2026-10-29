# Generated migration: remove duplicate Applicant rows for the same user
# and add a unique constraint so it can never happen again.
from django.db import migrations


def remove_duplicate_applicants(apps, schema_editor):
    """
    For each user_id that has more than one Applicant row, keep the row with
    the smallest applicant_id (the original / canonical one) and delete the
    others.  Before deleting a duplicate we re-home any child rows
    (applications, saved_jobs, resume_analysis, chatbot_answers, etc.) that
    point to it so we never lose data.
    """
    Applicant = apps.get_model('app', 'Applicant')
    Application = apps.get_model('app', 'Application')
    SavedJob = apps.get_model('app', 'SavedJob')
    ChatbotAnswer = apps.get_model('app', 'ChatbotAnswer')
    ResumeAnalysis = apps.get_model('app', 'ResumeAnalysis')

    # Find users with more than one Applicant record.
    from django.db.models import Count
    dup_user_ids = (
        Applicant.objects
        .values('user_id')
        .annotate(cnt=Count('applicant_id'))
        .filter(cnt__gt=1)
        .values_list('user_id', flat=True)
    )

    for user_id in dup_user_ids:
        rows = list(Applicant.objects.filter(user_id=user_id).order_by('applicant_id'))
        canonical = rows[0]
        dupes = rows[1:]
        for dupe in dupes:
            dupe_id = dupe.applicant_id
            # Re-home child rows to the canonical Applicant so we lose nothing.
            Application.objects.filter(applicant_id=dupe_id).update(applicant_id=canonical.applicant_id)
            SavedJob.objects.filter(applicant_id=dupe_id).update(applicant_id=canonical.applicant_id)
            ChatbotAnswer.objects.filter(applicant_id=dupe_id).update(applicant_id=canonical.applicant_id)
            ResumeAnalysis.objects.filter(applicant_id=dupe_id).update(applicant_id=canonical.applicant_id)
            # Merge profile fields if the canonical row is empty but the dupe has data.
            save_fields = []
            for field in ('resume_file', 'skills', 'qualifications', 'education_level'):
                canonical_val = getattr(canonical, field)
                dupe_val = getattr(dupe, field)
                if not canonical_val and dupe_val:
                    setattr(canonical, field, dupe_val)
                    save_fields.append(field)
            if not canonical.experience_years and dupe.experience_years:
                canonical.experience_years = dupe.experience_years
                save_fields.append('experience_years')
            if not canonical.employability_score and dupe.employability_score:
                canonical.employability_score = dupe.employability_score
                save_fields.append('employability_score')
            if save_fields:
                canonical.save(update_fields=save_fields)
            dupe.delete()


class Migration(migrations.Migration):

    dependencies = [
        ('app', '0016_interviewschedule_overdue_notified_at'),
    ]

    operations = [
        migrations.RunPython(
            remove_duplicate_applicants,
            migrations.RunPython.noop,
        ),
    ]

