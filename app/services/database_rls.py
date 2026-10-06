import hashlib
import hmac

from django.conf import settings
from django.db import connection


def _set_rls_context(user_id='', role='', email='', registration=False):
    if connection.vendor != 'postgresql':
        return

    secret = settings.RLS_CONTEXT_SECRET
    if len(secret) < 32:
        raise RuntimeError(
            'RLS_CONTEXT_SECRET must be configured with at least 32 characters '
            'before PostgreSQL requests can use row-level security.'
        )

    user_id = str(user_id or '')
    role = str(role or '')
    email = str(email or '')
    registration = bool(registration)

    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT COALESCE(current_setting('app.session_key', true), '')"
        )
        session_key = cursor.fetchone()[0]
        context = '\n'.join((
            user_id,
            role,
            email,
            'true' if registration else 'false',
            session_key,
        ))
        signature = hmac.new(
            secret.encode('utf-8'),
            context.encode('utf-8'),
            hashlib.sha256,
        ).hexdigest()
        cursor.execute(
            """
            SELECT set_config('app.user_id', %s, true),
                   set_config('app.user_role', %s, true),
                   set_config('app.user_email', %s, true),
                   set_config('app.registration', %s, true),
                   set_config('app.context_signature', %s, true)
            """,
            [
                user_id,
                role,
                email,
                'true' if registration else 'false',
                signature,
            ],
        )


def set_authenticated_rls_context(user):
    _set_rls_context(user.pk, user.role, user.email)


def set_rls_identity_for_lookup(user_id):
    _set_rls_context(user_id)


def prepare_registration_user_id(role, email):
    if connection.vendor != 'postgresql':
        return None

    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT nextval(pg_get_serial_sequence('public.users', 'user_id'))"
        )
        user_id = cursor.fetchone()[0]
    _set_rls_context(user_id, role, email, registration=True)
    return user_id


def user_email_exists(email):
    if connection.vendor == 'postgresql':
        with connection.cursor() as cursor:
            cursor.execute('SELECT public.mic_rls_email_exists(%s)', [email])
            return cursor.fetchone()[0]

    from app.models import User

    return User.objects.filter(email__iexact=email).exists()


def clear_rls_identity():
    _set_rls_context()


def find_user_for_login(email):
    if connection.vendor == 'postgresql':
        from app.models import User

        with connection.cursor() as cursor:
            cursor.execute(
                """
                SELECT user_id, email, password, role, status, first_name, last_name
                FROM public.mic_rls_authenticate_user(%s)
                """,
                [email],
            )
            row = cursor.fetchone()
        if row is None:
            return None
        return User(
            user_id=row[0],
            email=row[1],
            password=row[2],
            role=row[3],
            status=row[4],
            first_name=row[5],
            last_name=row[6],
        )

    from app.models import User

    return User.objects.filter(email__iexact=email).first()
