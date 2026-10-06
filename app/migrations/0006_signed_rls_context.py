import os

from django.db import migrations


CREATE_CONTEXT_SCHEMA = """
CREATE SCHEMA IF NOT EXISTS extensions;
CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA extensions;

CREATE SCHEMA IF NOT EXISTS mic_rls_private;
REVOKE ALL ON SCHEMA mic_rls_private FROM PUBLIC, mic_app_rls;

CREATE TABLE IF NOT EXISTS mic_rls_private.context_keys (
    key_id smallint PRIMARY KEY CHECK (key_id = 1),
    context_secret text NOT NULL
);
REVOKE ALL ON mic_rls_private.context_keys FROM PUBLIC, mic_app_rls;
"""

SIGNED_CONTEXT_FUNCTIONS = """
CREATE OR REPLACE FUNCTION public.mic_rls_context_valid()
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public, extensions, mic_rls_private
AS $$
    SELECT COALESCE(
        current_setting('app.context_signature', true) <> ''
        AND encode(
            extensions.hmac(
                convert_to(
                    COALESCE(current_setting('app.user_id', true), '') || E'\\n' ||
                    COALESCE(current_setting('app.user_role', true), '') || E'\\n' ||
                    COALESCE(current_setting('app.user_email', true), '') || E'\\n' ||
                    COALESCE(current_setting('app.registration', true), 'false') || E'\\n' ||
                    COALESCE(current_setting('app.session_key', true), ''),
                    'UTF8'
                ),
                convert_to(context_keys.context_secret, 'UTF8'),
                'sha256'
            ),
            'hex'
        ) = current_setting('app.context_signature', true),
        false
    )
    FROM mic_rls_private.context_keys
    WHERE key_id = 1
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_user_id()
RETURNS integer
LANGUAGE sql
STABLE
AS $$
    SELECT CASE WHEN public.mic_rls_context_valid()
        THEN NULLIF(current_setting('app.user_id', true), '')::integer
        ELSE NULL
    END
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_user_role()
RETURNS text
LANGUAGE sql
STABLE
AS $$
    SELECT CASE WHEN public.mic_rls_context_valid()
        THEN COALESCE(current_setting('app.user_role', true), '')
        ELSE ''
    END
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_is_registration()
RETURNS boolean
LANGUAGE sql
STABLE
AS $$
    SELECT public.mic_rls_context_valid()
       AND COALESCE(current_setting('app.registration', true), '') = 'true'
$$;

REVOKE ALL ON FUNCTION public.mic_rls_context_valid() FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.mic_rls_context_valid() TO mic_app_rls;
"""

RESTORE_UNSIGNED_CONTEXT_FUNCTIONS = """
CREATE OR REPLACE FUNCTION public.mic_rls_user_id()
RETURNS integer
LANGUAGE sql
STABLE
AS $$
    SELECT NULLIF(current_setting('app.user_id', true), '')::integer
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_user_role()
RETURNS text
LANGUAGE sql
STABLE
AS $$
    SELECT COALESCE(current_setting('app.user_role', true), '')
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_is_registration()
RETURNS boolean
LANGUAGE sql
STABLE
AS $$
    SELECT COALESCE(current_setting('app.registration', true), '') = 'true'
$$;

REVOKE ALL ON FUNCTION public.mic_rls_context_valid() FROM PUBLIC;
DROP FUNCTION public.mic_rls_context_valid();
DROP TABLE mic_rls_private.context_keys;
DROP SCHEMA mic_rls_private;
"""


def enable_signed_context(apps, schema_editor):
    if schema_editor.connection.vendor != 'postgresql':
        return

    secret = os.environ.get('RLS_CONTEXT_SECRET', '')
    if len(secret) < 32:
        raise RuntimeError(
            'Set RLS_CONTEXT_SECRET to a private random value of at least 32 '
            'characters before applying this migration.'
        )

    with schema_editor.connection.cursor() as cursor:
        cursor.execute(CREATE_CONTEXT_SCHEMA)
        cursor.execute(
            """
            INSERT INTO mic_rls_private.context_keys (key_id, context_secret)
            VALUES (1, %s)
            ON CONFLICT (key_id) DO UPDATE
            SET context_secret = EXCLUDED.context_secret
            """,
            [secret],
        )
        cursor.execute(SIGNED_CONTEXT_FUNCTIONS)


def disable_signed_context(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(RESTORE_UNSIGNED_CONTEXT_FUNCTIONS)


class Migration(migrations.Migration):
    dependencies = [
        ('app', '0005_rls_insert_returning_policies'),
    ]

    operations = [
        migrations.RunPython(enable_signed_context, disable_signed_context),
    ]
