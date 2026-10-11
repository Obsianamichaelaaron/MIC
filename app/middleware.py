from django.conf import settings
from django.db import connection, transaction

from app.services.database_rls import (
    clear_rls_identity,
    set_authenticated_rls_context,
    set_rls_identity_for_lookup,
)


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
        clear_rls_identity()

        session_user_id = session.get('user_id')
        if not str(session_user_id or '').isdigit():
            return None

        set_rls_identity_for_lookup(session_user_id)
        user = User.objects.filter(pk=int(session_user_id)).only(
            'user_id', 'email', 'role', 'status'
        ).first()

        if user and user.status == 'active':
            session['role'] = user.role
            session['email'] = user.email
            set_authenticated_rls_context(user)
        else:
            clear_rls_identity()
            session.flush()

        return None

    @staticmethod
    def _set_context(name, value):
        with connection.cursor() as cursor:
            cursor.execute('SELECT set_config(%s, %s, true)', [name, value])
