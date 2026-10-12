from django.conf import settings
from django.db import connection, transaction

from app.services.database_rls import (
    clear_rls_identity,
    set_authenticated_rls_context,
    set_rls_identity_for_lookup,
)

STATIC_PREFIXES = (
    '/static/', '/images/', '/js/', '/css/', '/2024/',
    '/applicant/images/', '/applicant/js/', '/applicant/css/',
    '/employer/images/', '/employer/js/', '/employer/css/',
    '/admin/images/', '/admin/js/', '/admin/css/',
)
STATIC_PATHS = ('/favicon.ico',)


def is_static_request(path):
    lower_path = path.lower()
    return lower_path in STATIC_PATHS or lower_path.startswith(STATIC_PREFIXES)


class PostgreSQLRLSMiddleware:
    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        if is_static_request(request.path_info):
            return self.get_response(request)

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
        session_key = session.session_key or ''
        session_user_id = session.get('user_id')
        if not str(session_user_id or '').isdigit():
            clear_rls_identity(session_key=session_key)
            return None

        set_rls_identity_for_lookup(session_user_id, session_key=session_key)
        user = User.objects.filter(pk=int(session_user_id)).only(
            'user_id', 'email', 'role', 'status'
        ).first()

        if user and user.status == 'active':
            session['role'] = user.role
            session['email'] = user.email
            set_authenticated_rls_context(user, session_key=session_key)
        else:
            clear_rls_identity(session_key=session_key)
            session.flush()

        return None
