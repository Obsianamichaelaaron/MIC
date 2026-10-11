"""
Stub middleware — PostgreSQLRLSMiddleware has been removed.
This file is kept so existing imports don't break; the class does nothing.
Session-based role/email are still stored by views_auth on login.
"""


STATIC_PREFIXES = (
    '/static/', '/images/', '/js/', '/css/', '/2024/',
    '/applicant/images/', '/applicant/js/', '/applicant/css/',
    '/employer/images/', '/employer/js/', '/employer/css/',
    '/admin/images/', '/admin/js/', '/admin/css/',
    '/uploads/', '/favicon.ico',
)
STATIC_EXTENSIONS = (
    '.png', '.webp', '.jpg', '.jpeg', '.gif', '.svg', '.ico',
    '.css', '.js', '.woff', '.woff2', '.ttf', '.eot', '.map',
)


def is_static_request(path: str) -> bool:
    lower_path = path.lower()
    return lower_path.startswith(STATIC_PREFIXES) or lower_path.endswith(STATIC_EXTENSIONS)


class PostgreSQLRLSMiddleware:
    """
    No-op stub — RLS has been removed. Kept so nothing import-crashes.
    """
    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        return self.get_response(request)

    def process_view(self, request, view_func, view_args, view_kwargs):
        return None
