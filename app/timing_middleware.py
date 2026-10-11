"""
Request timing middleware - prints a breakdown of every request to the console.
Shows total time, DB query count, and DB query total time so you can spot
which views are slow and whether the bottleneck is Python or database.

Enable by adding to MIDDLEWARE in settings.py (for local dev only):
    'app.timing_middleware.RequestTimingMiddleware',
"""
import time
import sys
import logging
from django.db import connection, reset_queries
from django.conf import settings

logger = logging.getLogger('timing')

# Use ANSI colours only when stdout is a real terminal that supports them.
# On Windows cp1252 consoles, isatty() returns False so colours are skipped.
_USE_COLOUR = hasattr(sys.stdout, 'isatty') and sys.stdout.isatty()
_RED    = '\033[91m' if _USE_COLOUR else ''
_YELLOW = '\033[93m' if _USE_COLOUR else ''
_GREEN  = '\033[92m' if _USE_COLOUR else ''
_RESET  = '\033[0m'  if _USE_COLOUR else ''
SEP = '-' * 70  # plain ASCII - safe on all Windows terminals


def _colour(ms, warn=500, bad=1500):
    if ms >= bad:
        return _RED
    if ms >= warn:
        return _YELLOW
    return _GREEN


class RequestTimingMiddleware:
    """
    Lightweight profiling middleware.
    Only activates when DEBUG=True so it never runs in production.
    """

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        if not settings.DEBUG:
            return self.get_response(request)

        reset_queries()
        t0 = time.perf_counter()

        response = self.get_response(request)

        elapsed_ms = (time.perf_counter() - t0) * 1000
        queries    = connection.queries
        n_queries  = len(queries)
        db_ms      = sum(float(q.get('time', 0)) * 1000 for q in queries)
        python_ms  = elapsed_ms - db_ms

        colour = _colour(elapsed_ms)
        status = response.status_code

        msg = (
            '\n' + SEP + '\n'
            '  ' + colour + f'{elapsed_ms:>7.1f}ms' + _RESET + '  '
            + request.method + ' ' + request.path + '  [' + str(status) + ']\n'
            '  DB: ' + _YELLOW + f'{db_ms:>7.1f}ms' + _RESET
            + ' (' + str(n_queries) + ' queries)  |  '
            'Python: ' + _YELLOW + f'{python_ms:>7.1f}ms' + _RESET
        )
        sys.stdout.write(msg + '\n')

        # If slow, print the 5 slowest individual queries
        if elapsed_ms > 300 and queries:
            slow_qs = sorted(queries, key=lambda q: float(q.get('time', 0)), reverse=True)[:5]
            sys.stdout.write('  ' + _RED + '>> Top slow queries:' + _RESET + '\n')
            for i, q in enumerate(slow_qs, 1):
                q_ms = float(q.get('time', 0)) * 1000
                sql_short = q['sql'][:120].replace('\n', ' ').strip()
                sys.stdout.write(
                    '    ' + str(i) + '. ' + _RED + f'{q_ms:>6.1f}ms' + _RESET
                    + '  ' + sql_short + '\n'
                )

        sys.stdout.write(SEP + '\n\n')
        sys.stdout.flush()
        return response
