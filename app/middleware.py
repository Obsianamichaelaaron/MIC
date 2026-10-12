from app.models import User


STATIC_PREFIXES = (
    '/static/', '/images/', '/js/', '/css/', '/2024/',
    '/applicant/images/', '/applicant/js/', '/applicant/css/',
    '/employer/images/', '/employer/js/', '/employer/css/',
    '/admin/images/', '/admin/js/', '/admin/css/',
)
STATIC_PATHS = ('/favicon.ico',)


def is_static_request(path):
    lower_path = path.lower()
    return lower_path in STATIC_PATHS or lower_path.startswith(STATIC_PREFIXES)


class UserSessionValidationMiddleware:
    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        return self.get_response(request)

    def process_view(self, request, view_func, view_args, view_kwargs):
        if is_static_request(request.path_info):
            return None

        session_user_id = request.session.get('user_id')
        if not str(session_user_id or '').isdigit():
            return None

        user = User.objects.filter(
            pk=int(session_user_id),
            status='active',
        ).only(
            'user_id', 'email', 'role', 'first_name', 'last_name',
        ).first()
        if user is None:
            request.session.flush()
            return None

        request.session['email'] = user.email
        request.session['role'] = user.role
        request.session['first_name'] = user.first_name or ''
        request.session['last_name'] = user.last_name or ''
        return None
