from functools import wraps
from django.shortcuts import redirect
from django.http import HttpResponseRedirect
from django.contrib.auth.hashers import make_password, check_password
import bcrypt

def verify_password(plain_password: str, hashed_password: str) -> bool:
    """
    Verifies passwords hashed using PHP's password_hash() (bcrypt $2y$)
    or standard Django password hashes.
    """
    if not plain_password or not hashed_password:
        return False
        
    # If it's a bcrypt hash ($2y$, $2b$, $2a$)
    if hashed_password.startswith(('$2y$', '$2b$', '$2a$')):
        try:
            h_bytes = hashed_password.encode('utf-8')
            if h_bytes.startswith(b'$2y$'):
                h_bytes = b'$2b$' + h_bytes[4:]
            return bcrypt.checkpw(plain_password.encode('utf-8'), h_bytes)
        except Exception:
            pass
            
    # Try Django check_password fallback
    try:
        return check_password(plain_password, hashed_password)
    except Exception:
        return False

def hash_password(plain_password: str) -> str:
    """
    Hashes password using bcrypt ($2y$/$2b$) compatible with PHP password_verify.
    """
    salt = bcrypt.gensalt(rounds=10, prefix=b'2b')
    return bcrypt.hashpw(plain_password.encode('utf-8'), salt).decode('utf-8')

def isLoggedIn(request) -> bool:
    return bool(request.session.get('user_id'))

def getUserRole(request) -> str:
    return request.session.get('role')

def hasRole(request, role: str) -> bool:
    return isLoggedIn(request) and getUserRole(request) == role

def getCurrentUserId(request):
    return request.session.get('user_id')

def getCurrentUserEmail(request):
    return request.session.get('email')

def getCurrentUserName(request):
    first = request.session.get('first_name', '')
    last = request.session.get('last_name', '')
    return f"{first} {last}".strip()

def require_login(view_func):
    @wraps(view_func)
    def _wrapped(request, *args, **kwargs):
        if not isLoggedIn(request):
            return redirect('/loginregister.php')
        response = view_func(request, *args, **kwargs)
        if isinstance(response, HttpResponseRedirect):
            return response
        response['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0'
        response['Pragma'] = 'no-cache'
        response['Expires'] = 'Sat, 01 Jan 2000 00:00:00 GMT'
        return response
    return _wrapped

def require_role(role: str):
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped(request, *args, **kwargs):
            if not isLoggedIn(request):
                return redirect('/loginregister.php?error=login_required')
            if not hasRole(request, role):
                return redirect('/loginregister.php?error=unauthorized')
            response = view_func(request, *args, **kwargs)
            if isinstance(response, HttpResponseRedirect):
                return response
            response['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0'
            response['Pragma'] = 'no-cache'
            response['Expires'] = 'Sat, 01 Jan 2000 00:00:00 GMT'
            return response
        return _wrapped
    return decorator
