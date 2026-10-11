"""
database_rls.py — stub after RLS removal.

All functions are kept as no-ops so existing callers don't crash.
The PostgreSQL-specific RLS context is no longer set.
Authentication now uses plain Django ORM queries.
"""
from app.models import User


def _noop(*args, **kwargs):
    pass


def set_authenticated_rls_context(user, session_key=None):
    """No-op: RLS removed."""
    pass


def set_rls_identity_for_lookup(user_id, session_key=None):
    """No-op: RLS removed."""
    pass


def clear_rls_identity(session_key=None):
    """No-op: RLS removed."""
    pass


def prepare_registration_user_id(role, email):
    """
    Previously reserved a Postgres sequence ID before insert.
    Now returns None so User.objects.create() uses the default auto-increment.
    """
    return None


def user_email_exists(email):
    """Plain ORM check — no RLS function needed."""
    return User.objects.filter(email__iexact=email).exists()


def find_user_for_login(email):
    """Plain ORM lookup — no RLS function needed."""
    return User.objects.filter(email__iexact=email).first()
