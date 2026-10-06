from django.conf import settings
from django.db import connection, transaction


class PostgreSQLRLSMiddleware:
    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        if connection.vendor != 'postgresql':
            return self.get_response(request)

        with transaction.atomic():
            if getattr(settings, 'RLS_USE_RESTRICTED_ROLE', False):
                role = connection.ops.quote_name(settings.RLS_DATABASE_ROLE)
                with connection.cursor() as cursor:
                    cursor.execute(f'SET LOCAL ROLE {role}')
            return self.get_response(request)

    def process_view(self, request, view_func, view_args, view_kwargs):
        if connection.vendor != 'postgresql':
            return None

        from app.models import User

        session = request.session
        self._set_context('app.session_key', session.session_key or '')
        self._set_context('app.user_id', '')
        self._set_context('app.user_role', '')
        self._set_context('app.user_email', '')

        session_user_id = session.get('user_id')
        if not str(session_user_id or '').isdigit():
            return None

        self._set_context('app.user_id', str(session_user_id))
        user = User.objects.filter(pk=int(session_user_id)).only(
            'user_id', 'email', 'role', 'status'
        ).first()

        if user and user.status == 'active':
            self._set_context('app.user_role', user.role)
            self._set_context('app.user_email', user.email)
        else:
            self._set_context('app.user_id', '')
            session.flush()

        return None

    @staticmethod
    def _set_context(name, value):
        with connection.cursor() as cursor:
            cursor.execute('SELECT set_config(%s, %s, true)', [name, value])
