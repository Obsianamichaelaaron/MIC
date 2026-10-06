from django.apps import AppConfig


class AppConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'app'
    verbose_name = 'MultiBiz Global App'

    def ready(self):
        from django.conf import settings
        from django.db.backends.signals import connection_created

        def reset_management_role(sender, connection, **kwargs):
            if (
                connection.alias == 'default'
                and connection.vendor == 'postgresql'
                and not getattr(settings, 'RLS_USE_RESTRICTED_ROLE', False)
            ):
                with connection.cursor() as cursor:
                    cursor.execute('RESET ROLE')

        connection_created.connect(
            reset_management_role,
            dispatch_uid='app.reset_management_database_role',
            weak=False,
        )
