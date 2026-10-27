import os, django
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'multibiz.settings')
django.setup()

from app.models import User
from app.auth_utils import hash_password

# Reset password for employer@gmail.com to "employer123"
target_email = "employer@gmail.com"
user = User.objects.filter(email=target_email).first()
if user:
    user.password = hash_password("employer123")
    user.save()
    print(f"Password reset for {target_email}")
    print(f"  Login URL : http://127.0.0.1:8000/login/")
    print(f"  Email     : {target_email}")
    print(f"  Password  : employer123")
    print(f"  Portal URL: http://127.0.0.1:8000/employer/dashboard/")
else:
    print("User not found")
