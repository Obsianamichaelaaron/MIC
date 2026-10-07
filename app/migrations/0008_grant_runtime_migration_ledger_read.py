from django.db import migrations


def grant_migration_ledger_read(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(
                'GRANT SELECT ON TABLE public.django_migrations TO mic_app_rls'
            )


class Migration(migrations.Migration):
    dependencies = [
        ('app', '0007_runtime_migration_ledger_read'),
    ]

    operations = [
        migrations.RunPython(
            grant_migration_ledger_read,
            migrations.RunPython.noop,
        ),
    ]
