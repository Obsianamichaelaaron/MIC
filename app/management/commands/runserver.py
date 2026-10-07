from django.conf import settings
from django.contrib.staticfiles.management.commands.runserver import (
    Command as StaticfilesRunserverCommand,
)
from django.db import connection, transaction


class Command(StaticfilesRunserverCommand):
    def check_migrations(self):
        if (
            connection.vendor == 'postgresql'
            and getattr(settings, 'RLS_USE_RESTRICTED_ROLE', False)
        ):
            role = connection.ops.quote_name(settings.RLS_DATABASE_ROLE)
            with transaction.atomic():
                with connection.cursor() as cursor:
                    cursor.execute(f'SET LOCAL ROLE {role}')
                return super().check_migrations()

        return super().check_migrations()
