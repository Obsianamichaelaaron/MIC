from functools import wraps
from django.shortcuts import redirect
from django.http import HttpResponseRedirect
from django.contrib.auth.hashers import make_password, check_password
import bcrypt

def verify_password(plain_password: str, hashed_password: str) -> bool:
    """
    Verifies passwords hashed using PHP's password_hash() (bcrypt $2y$, $2b$, $2a$, $2x$),
    Django PBKDF2/Argon2 hashes, legacy hashes (MD5, SHA256), or plain text.
    """
    if not plain_password or not hashed_password:
        return False
        
    plain_str = str(plain_password)
    plain_bytes = plain_str.encode('utf-8')
    hashed_str = str(hashed_password).strip()

    # 1. Plain text comparison fallback (for unhashed seed/dev passwords)
    if plain_str == hashed_str or plain_str.strip() == hashed_str:
        return True

    # 2. Bcrypt hash format ($2y$, $2b$, $2a$, $2x$)
    if hashed_str.startswith(('$2y$', '$2b$', '$2a$', '$2x$')):
        try:
            h_bytes = hashed_str.encode('utf-8')
            if h_bytes.startswith((b'$2y$', b'$2a$', b'$2x$')):
                h_bytes = b'$2b$' + h_bytes[4:]
            if bcrypt.checkpw(plain_bytes, h_bytes):
                return True
            if bcrypt.checkpw(plain_str.strip().encode('utf-8'), h_bytes):
                return True
        except Exception:
            pass

    # 3. Legacy MD5 / SHA256 / SHA1
    import hashlib
    try:
        if len(hashed_str) == 32:
            if hashlib.md5(plain_bytes).hexdigest() == hashed_str.lower():
                return True
        elif len(hashed_str) == 40:
            if hashlib.sha1(plain_bytes).hexdigest() == hashed_str.lower():
                return True
        elif len(hashed_str) == 64:
            if hashlib.sha256(plain_bytes).hexdigest() == hashed_str.lower():
                return True
    except Exception:
        pass

    # 4. Django check_password fallback (pbkdf2_sha256, etc.)
    try:
        if check_password(plain_str, hashed_str):
            return True
        if check_password(plain_str.strip(), hashed_str):
            return True
    except Exception:
        pass

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
    role = request.session.get('role')
    return str(role).lower().strip() if role else ''

def hasRole(request, role: str) -> bool:
    return isLoggedIn(request) and getUserRole(request) == str(role).lower().strip()

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
