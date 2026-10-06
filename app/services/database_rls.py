from django.db import connection


def set_authenticated_rls_context(user):
    if connection.vendor != 'postgresql':
        return

    with connection.cursor() as cursor:
        cursor.execute(
            """
            SELECT set_config('app.user_id', %s, true),
                   set_config('app.user_role', %s, true),
                   set_config('app.user_email', %s, true),
                   set_config('app.registration', '', true)
            """,
            [str(user.pk), user.role, user.email],
        )


def prepare_registration_user_id():
    if connection.vendor != 'postgresql':
        return None

    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT nextval(pg_get_serial_sequence('public.users', 'user_id'))"
        )
        user_id = cursor.fetchone()[0]
        cursor.execute(
            """
            SELECT set_config('app.user_id', %s, true),
                   set_config('app.user_role', '', true),
                   set_config('app.user_email', '', true),
                   set_config('app.registration', 'true', true)
            """,
            [str(user_id)],
        )
    return user_id


def user_email_exists(email):
    if connection.vendor == 'postgresql':
        with connection.cursor() as cursor:
            cursor.execute('SELECT public.mic_rls_email_exists(%s)', [email])
            return cursor.fetchone()[0]

    from app.models import User

    return User.objects.filter(email__iexact=email).exists()


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
