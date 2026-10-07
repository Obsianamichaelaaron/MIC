from django.db import migrations


FORWARD_SQL = """
CREATE OR REPLACE FUNCTION public.mic_rls_create_employer_notification(
    target_application_id integer,
    notification_title text,
    notification_message text,
    notification_type text
)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
DECLARE
    recipient_user_id integer;
BEGIN
    IF public.mic_rls_user_role() <> 'employer' THEN
        RAISE EXCEPTION 'Only employers can send candidate notifications'
            USING ERRCODE = '42501';
    END IF;

    SELECT ap.user_id
    INTO recipient_user_id
    FROM public.applications a
    JOIN public.applicants ap ON ap.applicant_id = a.applicant_id
    JOIN public.job_postings j ON j.job_id = a.job_id
    JOIN public.employers e ON e.employer_id = j.employer_id
    WHERE a.application_id = target_application_id
      AND a.forwarded_to_employer
      AND e.user_id = public.mic_rls_user_id();

    IF recipient_user_id IS NULL THEN
        RAISE EXCEPTION 'Employer cannot notify this applicant'
            USING ERRCODE = '42501';
    END IF;

    INSERT INTO public.notifications (
        user_id, title, message, type, is_read, created_at
    )
    VALUES (
        recipient_user_id,
        notification_title,
        notification_message,
        notification_type,
        false,
        CURRENT_TIMESTAMP
    );
END;
$$;

REVOKE ALL ON FUNCTION public.mic_rls_create_employer_notification(
    integer, text, text, text
) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.mic_rls_create_employer_notification(
    integer, text, text, text
) TO mic_app_rls;
"""


REVERSE_SQL = """
REVOKE ALL ON FUNCTION public.mic_rls_create_employer_notification(
    integer, text, text, text
) FROM mic_app_rls;
DROP FUNCTION public.mic_rls_create_employer_notification(
    integer, text, text, text
);
"""


def apply_employer_notification_function(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(FORWARD_SQL)


def remove_employer_notification_function(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(REVERSE_SQL)


class Migration(migrations.Migration):
    dependencies = [
        ('app', '0008_grant_runtime_migration_ledger_read'),
    ]

    operations = [
        migrations.RunPython(
            apply_employer_notification_function,
            remove_employer_notification_function,
        ),
    ]
