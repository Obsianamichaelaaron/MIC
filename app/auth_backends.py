from django.contrib.auth.backends import BaseBackend
from .models import User
from .auth_utils import verify_password

class PHPPasswordAuthBackend(BaseBackend):
    """
    Authenticates against our custom User model verifying bcrypt hashes.
    """
    def authenticate(self, request, email=None, password=None, **kwargs):
        if not email or not password:
            return None
        try:
            user = User.objects.filter(email=email.strip()).first()
            if user and user.status == 'active':
                if verify_password(password, user.password):
                    return user
        except Exception:
            return None
        return None

    def get_user(self, user_id):
        try:
            return User.objects.filter(pk=user_id).first()
        except Exception:
            return None
