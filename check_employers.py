import os, django
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'multibiz.settings')
django.setup()

from app.models import User, Employer

employers = User.objects.filter(role='employer')
print(f"Total employer accounts: {employers.count()}")
print("-" * 60)
for u in employers:
    emp = u.employer_profile.first()
    company = emp.company_name if emp else "N/A"
    print(f"Email   : {u.email}")
    print(f"Name    : {u.full_name}")
    print(f"Company : {company}")
    print(f"Status  : {u.status}")
    print("-" * 60)

if employers.count() == 0:
    print("No employer accounts found. Creating one now...")
    from app.auth_utils import hash_password
    new_user = User.objects.create(
        email="employer@multibiz.com",
        password=hash_password("employer123"),
        role="employer",
        first_name="Employer",
        last_name="Admin",
        status="active",
        created_by_admin=True
    )
    Employer.objects.create(
        user=new_user,
        company_name="MultiBiz Demo Company"
    )
    print("Created employer account:")
    print("  Email   : employer@multibiz.com")
    print("  Password: employer123")
    print("  Company : MultiBiz Demo Company")
