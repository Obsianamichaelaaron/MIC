from django.db import migrations


FORWARD_SQL = """
CREATE POLICY mic_rls_django_migrations_select ON public.django_migrations
    FOR SELECT USING (current_user = 'mic_app_rls');
"""

REVERSE_SQL = """
DROP POLICY IF EXISTS mic_rls_django_migrations_select
    ON public.django_migrations;
"""


def update_migration_ledger_policy(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(FORWARD_SQL)


def remove_migration_ledger_policy(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(REVERSE_SQL)


class Migration(migrations.Migration):
    dependencies = [
        ('app', '0006_signed_rls_context'),
    ]

    operations = [
        migrations.RunPython(
            update_migration_ledger_policy,
            remove_migration_ledger_policy,
        ),
    ]
