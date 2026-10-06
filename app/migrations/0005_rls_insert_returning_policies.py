from django.db import migrations


FORWARD_SQL = """
DROP POLICY mic_rls_applicants_select ON public.applicants;
CREATE POLICY mic_rls_applicants_select ON public.applicants
    FOR SELECT USING (
        public.mic_rls_is_admin()
        OR user_id = public.mic_rls_user_id()
        OR public.mic_rls_can_access_applicant(applicant_id)
    );

DROP POLICY mic_rls_applications_select ON public.applications;
CREATE POLICY mic_rls_applications_select ON public.applications
    FOR SELECT USING (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = applications.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
        OR public.mic_rls_can_access_application(application_id)
    );
"""

REVERSE_SQL = """
DROP POLICY mic_rls_applicants_select ON public.applicants;
CREATE POLICY mic_rls_applicants_select ON public.applicants
    FOR SELECT USING (public.mic_rls_can_access_applicant(applicant_id));

DROP POLICY mic_rls_applications_select ON public.applications;
CREATE POLICY mic_rls_applications_select ON public.applications
    FOR SELECT USING (public.mic_rls_can_access_application(application_id));
"""


def update_postgresql_policies(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(FORWARD_SQL)


def restore_postgresql_policies(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(REVERSE_SQL)


class Migration(migrations.Migration):
    dependencies = [
        ('app', '0004_postgresql_row_level_security'),
    ]

    operations = [
        migrations.RunPython(update_postgresql_policies, restore_postgresql_policies),
    ]
