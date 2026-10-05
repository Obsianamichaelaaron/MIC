import os
import time
import random
from django.shortcuts import render, redirect
from django.http import JsonResponse, HttpResponseRedirect
from django.views.decorators.csrf import csrf_exempt
from django.conf import settings
from app.models import User, Applicant, Qualification, Employer
from app.auth_utils import verify_password, hash_password, isLoggedIn, getUserRole
from app.services.mailer import send_otp_email, send_welcome_email, send_employer_welcome_email
from app.services.resume_parser import verify_resume_document, parse_and_save_applicant_resume

@csrf_exempt
def login_register_view(request):
    """
    Unified Login / Register Page matching loginregister.php.
    Supports both Job Seeker / Applicant and Employer / Company Registration.
    """
    redirect_target = request.GET.get('redirect') or request.POST.get('redirect') or ''
    job_id = request.GET.get('job_id') or request.POST.get('job_id') or '0'
    default_role = request.GET.get('role') or request.POST.get('role') or 'applicant'
    if default_role not in ('applicant', 'employer'):
        default_role = 'applicant'

    is_login_attempt = request.method == 'POST' and not (
        'first_name' in request.POST or 'confirm_password' in request.POST or 'company_name' in request.POST
    )
    
    # Check if user is already logged in
    if isLoggedIn(request) and not is_login_attempt:
        role = getUserRole(request)
        if redirect_target == 'apply' and job_id and job_id != '0' and role == 'applicant':
            return redirect(f'/applicant/apply_job.php?id={job_id}')
        if role == 'applicant':
            return redirect('/applicant/dashboard.php')
        elif role == 'employer':
            return redirect('/employer/dashboard.php')
        elif role == 'admin':
            return redirect('/admin/dashboard.php')
        return redirect('/')

    # Check for session expired flash
    session_expired = request.session.pop('session_expired', False)
    error = None
    if request.GET.get('error') == 'unauthorized':
        error = 'Please log in with an active applicant account to browse jobs.'
    elif request.GET.get('error') == 'login_required':
        error = 'Please log in with an active applicant account to browse jobs.'
    success = None

    if request.method == 'POST':
        # Check if this is a registration request
        is_registration = 'first_name' in request.POST or 'confirm_password' in request.POST or 'company_name' in request.POST

        if is_registration:
            role = request.POST.get('role', 'applicant').strip().lower()
            if role not in ('applicant', 'employer'):
                role = 'applicant'

            first_name = request.POST.get('first_name', '').strip()
            last_name = request.POST.get('last_name', '').strip()
            email = request.POST.get('email', '').strip().lower()
            phone = request.POST.get('phone', '').strip()
            password = request.POST.get('password', '')
            confirm_password = request.POST.get('confirm_password', '')

            # Employer fields
            company_name = request.POST.get('company_name', '').strip()
            industry = request.POST.get('industry', '').strip()
            company_size = request.POST.get('company_size', '').strip()
            company_website = request.POST.get('company_website', '').strip()
            company_address = request.POST.get('company_address', '').strip()

            # Applicant fields
            qualification_id = request.POST.get('qualification_id', '0')

            if not email or not password or not first_name or not last_name:
                error = "Please fill in all required fields"
            elif role == 'employer' and not company_name:
                error = "Company name is required for employer registration"
            elif password != confirm_password:
                error = "Passwords do not match"
            elif len(password) < 6:
                error = "Password must be at least 6 characters"
            elif User.objects.filter(email__iexact=email).exists():
                error = "This email is already registered. Please log in instead."
            else:
                if role == 'employer':
                    hashed_pwd = hash_password(password)
                    new_user = User.objects.create(
                        email=email,
                        password=hashed_pwd,
                        role='employer',
                        first_name=first_name.capitalize(),
                        last_name=last_name.capitalize(),
                        phone=phone,
                        status='active',
                        created_by_admin=False
                    )

                    employer = Employer.objects.create(
                        user=new_user,
                        company_name=company_name,
                        industry=industry,
                        company_size=company_size,
                        company_website=company_website,
                        company_address=company_address
                    )

                    # Auto-login newly registered employer
                    request.session.flush()
                    request.session['user_id'] = new_user.user_id
                    request.session['email'] = new_user.email
                    request.session['role'] = 'employer'
                    request.session['first_name'] = new_user.first_name or ''
                    request.session['last_name'] = new_user.last_name or ''

                    try:
                        send_employer_welcome_email(email, new_user.first_name, company_name)
                    except Exception:
                        pass

                    return redirect('/employer/dashboard.php')
                else:
                    # Handle applicant resume upload
                    resume_rel_path = None
                    uploaded_resume = request.FILES.get('resume')
                    if uploaded_resume:
                        file_ext = os.path.splitext(uploaded_resume.name)[1].lower()
                        if file_ext not in {'.pdf', '.doc', '.docx'}:
                            error = "Only PDF, DOC, and DOCX files are allowed for resume upload"
                        elif uploaded_resume.size > 5 * 1024 * 1024:
                            error = "File size must be less than 5MB"
                        else:
                            upload_dir = settings.MEDIA_ROOT / 'resumes'
                            os.makedirs(upload_dir, exist_ok=True)
                            new_fname = f"resume_{int(time.time())}_{random.randint(1000, 9999)}{file_ext}"
                            file_path = upload_dir / new_fname
                            with open(file_path, 'wb+') as dest:
                                for chunk in uploaded_resume.chunks():
                                    dest.write(chunk)

                            # AI Resume Verification Check
                            verification = verify_resume_document(str(file_path))
                            if not verification.get('is_valid', False):
                                if os.path.exists(file_path):
                                    try:
                                        os.remove(file_path)
                                    except Exception:
                                        pass
                                error = verification.get('rejection_reason', 'AI Verification Failed: The uploaded file is not recognized as a valid resume.')
                            else:
                                resume_rel_path = f"uploads/resumes/{new_fname}"

                    if not error:
                        hashed_pwd = hash_password(password)
                        new_user = User.objects.create(
                            email=email,
                            password=hashed_pwd,
                            role='applicant',
                            first_name=first_name.capitalize(),
                            last_name=last_name.capitalize(),
                            phone=phone,
                            status='active',
                            created_by_admin=False
                        )

                        # Qualification name
                        qual_name = None
                        if qualification_id and qualification_id.isdigit() and int(qualification_id) > 0:
                            q_obj = Qualification.objects.filter(pk=int(qualification_id), status='active').first()
                            if q_obj:
                                qual_name = q_obj.name

                        applicant = Applicant.objects.create(
                            user=new_user,
                            resume_file=resume_rel_path,
                            qualifications=qual_name
                        )

                        # If resume was uploaded, parse it
                        if resume_rel_path:
                            abs_pdf = settings.BASE_DIR / resume_rel_path
                            parse_and_save_applicant_resume(applicant, str(abs_pdf))

                        # Auto-login newly registered applicant
                        request.session.flush()
                        request.session['user_id'] = new_user.user_id
                        request.session['email'] = new_user.email
                        request.session['role'] = new_user.role
                        request.session['first_name'] = new_user.first_name or ''
                        request.session['last_name'] = new_user.last_name or ''

                        try:
                            send_welcome_email(email, new_user.first_name)
                        except Exception:
                            pass

                        if redirect_target == 'apply' and job_id and job_id != '0':
                            return redirect(f'/applicant/apply_job.php?id={job_id}')
                        return redirect('/applicant/dashboard.php')
        else:
            # Handle Login
            email = request.POST.get('email', '').strip()
            password = request.POST.get('password', '')

            if not email or not password:
                error = "Please fill in all fields"
            else:
                user = User.objects.filter(email__iexact=email).first()
                if user and verify_password(password, user.password):
                    if user.status == 'active':
                        request.session.flush()
                        request.session['user_id'] = user.user_id
                        request.session['email'] = user.email
                        request.session['role'] = user.role
                        request.session['first_name'] = user.first_name or ''
                        request.session['last_name'] = user.last_name or ''

                        # Role redirect
                        if user.role == 'applicant':
                            if redirect_target == 'apply' and job_id and job_id != '0':
                                return redirect(f'/applicant/apply_job.php?id={job_id}')
                            return redirect('/applicant/dashboard.php')
                        elif user.role == 'employer':
                            return redirect('/employer/dashboard.php')
                        elif user.role == 'admin':
                            return redirect('/admin/dashboard.php')
                        return redirect('/')
                    else:
                        error = f"Your account is {user.status}"
                else:
                    error = "Invalid email or password"

    active_tab = 'register' if (request.method == 'POST' and ('first_name' in request.POST or 'confirm_password' in request.POST or 'company_name' in request.POST)) else ('register' if request.GET.get('tab') == 'register' else 'login')
    qualifications = Qualification.objects.filter(status='active').order_by('name')

    form_values = request.POST.dict() if request.method == 'POST' else {}

    context = {
        'page_title': 'Login / Register - MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'loginregister.php',
        'error': error,
        'success': success,
        'active_tab': active_tab,
        'default_role': default_role,
        'form_values': form_values,
        'session_expired': session_expired,
        'redirect': redirect_target,
        'job_id': job_id,
        'qualifications': qualifications,
    }
    return render(request, 'public/loginregister.html', context)


def logout_view(request):
    """
    Logout matching includes/auth/logout.php.
    """
    request.session.flush()
    request.session['session_expired'] = True
    return redirect('/loginregister.php')


@csrf_exempt
def send_otp_view(request):
    """
    AJAX endpoint generating and sending registration OTP matching send_otp.php.
    """
    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'Invalid request method.'})

    email = request.POST.get('email', '').strip()
    first_name = request.POST.get('first_name', 'User').strip()

    if not email or '@' not in email or '.' not in email:
        return JsonResponse({'success': False, 'message': 'A valid email address is required.'})

    if User.objects.filter(email=email).exists():
        return JsonResponse({'success': False, 'message': 'This email is already registered. Please log in instead.'})

    # Rate limiting: max 3 OTP requests per 10 minutes
    rate_key = f"otp_rate_{email}"
    now = time.time()
    rate_data = request.session.get(rate_key)
    if rate_data:
        if rate_data['count'] >= 3 and (now - rate_data['first_request']) < 600:
            wait = int(600 - (now - rate_data['first_request']))
            return JsonResponse({'success': False, 'message': f"Too many OTP requests. Please wait {wait} seconds before trying again."})
        if (now - rate_data['first_request']) >= 600:
            request.session[rate_key] = {'count': 1, 'first_request': now}
        else:
            request.session[rate_key]['count'] += 1
    else:
        request.session[rate_key] = {'count': 1, 'first_request': now}

    # Generate 6-digit OTP
    otp = f"{random.randint(0, 999999):06d}"
    request.session['email_otp'] = {
        'code': otp,
        'email': email,
        'expires': now + 600,
        'verified': False,
        'attempts': 0
    }

    # Dispatch email
    result = send_otp_email(email, first_name, otp)
    if result['success']:
        return JsonResponse({'success': True, 'message': f'Verification code sent to {email}. Please check your inbox.'})
    else:
        return JsonResponse({'success': False, 'message': f"Could not send email: {result.get('error', 'Unknown error')}"})


@csrf_exempt
def verify_otp_view(request):
    """
    AJAX endpoint verifying registration OTP matching verify_otp.php.
    """
    if request.method != 'POST':
        return JsonResponse({'success': False, 'message': 'Invalid request method.'})

    entered_otp = request.POST.get('otp', '').strip()
    email = request.POST.get('email', '').strip()

    session_otp = request.session.get('email_otp')
    if not session_otp:
        return JsonResponse({'success': False, 'message': 'No verification code found. Please request a new one.'})

    if session_otp.get('email') != email:
        return JsonResponse({'success': False, 'message': 'Email mismatch. Please request a new code.'})

    if time.time() > session_otp.get('expires', 0):
        request.session.pop('email_otp', None)
        return JsonResponse({'success': False, 'message': 'Verification code has expired. Please request a new one.'})

    if session_otp.get('attempts', 0) >= 5:
        request.session.pop('email_otp', None)
        return JsonResponse({'success': False, 'message': 'Too many incorrect attempts. Please request a new code.'})

    session_otp['attempts'] += 1
    request.session['email_otp'] = session_otp

    if entered_otp != session_otp.get('code'):
        remaining = 5 - session_otp['attempts']
        return JsonResponse({
            'success': False,
            'message': f"Incorrect code. You have {remaining} attempt(s) remaining.",
            'attempts': session_otp['attempts']
        })

    # OTP is valid
    session_otp['verified'] = True
    request.session['email_otp'] = session_otp
    request.session['otp_verified_email'] = email

    return JsonResponse({
        'success': True,
        'message': 'Email verified successfully! Completing your registration...'
    })


def check_session_api(request):
    """
    Session check API matching includes/auth/check_session.php.
    """
    user_id = request.session.get('user_id')
    return JsonResponse({
        'logged_in': bool(user_id),
        'user_id': user_id,
        'role': request.session.get('role')
    })
