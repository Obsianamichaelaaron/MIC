import os
import secrets
import sys
from pathlib import Path

# Build paths inside the project like this: BASE_DIR / 'subdir'.
BASE_DIR = Path(__file__).resolve().parent.parent

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = os.environ.get('DJANGO_DEBUG', 'false').lower() == 'true'

SECRET_KEY = os.environ.get('DJANGO_SECRET_KEY')
if not SECRET_KEY:
    if os.environ.get('VERCEL') == '1':
        raise RuntimeError('DJANGO_SECRET_KEY must be configured in production.')
    SECRET_KEY = secrets.token_urlsafe(50)

ALLOWED_HOSTS = ['*']

# Application definition
INSTALLED_APPS = [
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'app',
    'django.contrib.messages',
    'django.contrib.staticfiles',
]

MIDDLEWARE = [
    # Timing (local dev only - no-op when DEBUG=False)
    'app.timing_middleware.RequestTimingMiddleware',
    'django.middleware.security.SecurityMiddleware',
    'whitenoise.middleware.WhiteNoiseMiddleware',
    'django.middleware.gzip.GZipMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    # 'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
]

ROOT_URLCONF = 'multibiz.urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [
            BASE_DIR / 'templates',
            BASE_DIR / 'app' / 'templates',
            os.path.join(str(BASE_DIR), 'templates'),
            os.path.join(str(BASE_DIR), 'app', 'templates'),
        ],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.debug',
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
                'django.template.context_processors.static',
                'django.template.context_processors.media',
                'app.context_processors.global_context',
            ],
            'builtins': [
                'app.templatetags.app_tags',
            ],
        },
    },
]

WSGI_APPLICATION = 'multibiz.wsgi.application'
ASGI_APPLICATION = 'multibiz.asgi.application'

# Database - SQLite (simple, no server needed)
# To switch to PostgreSQL later just set DATABASE_URL in your .env
DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.sqlite3',
        'NAME': BASE_DIR / 'db.sqlite3',
    }
}

# Password validation
AUTH_PASSWORD_VALIDATORS = [
    {
        'NAME': 'django.contrib.auth.password_validation.MinimumLengthValidator',
        'OPTIONS': {'min_length': 6},
    },
]

# Password Hashers - supports PHP's bcrypt ($2y$) hashes
PASSWORD_HASHERS = [
    'django.contrib.auth.hashers.BCryptSHA256PasswordHasher',
    'django.contrib.auth.hashers.BCryptPasswordHasher',
    'django.contrib.auth.hashers.PBKDF2PasswordHasher',
    'django.contrib.auth.hashers.PBKDF2SHA1PasswordHasher',
    'django.contrib.auth.hashers.Argon2PasswordHasher',
]

AUTHENTICATION_BACKENDS = [
    'app.auth_backends.PHPPasswordAuthBackend',
    'django.contrib.auth.backends.ModelBackend',
]

# Internationalization
LANGUAGE_CODE = 'en-us'
TIME_ZONE = 'UTC'
USE_I18N = True
USE_TZ = True

# Static files (CSS, JavaScript, Images)
STATIC_URL = '/static/'
STATICFILES_DIRS = [
    BASE_DIR / 'static',
]
STATIC_ROOT = BASE_DIR / 'staticfiles'
STATICFILES_STORAGE = 'whitenoise.storage.CompressedManifestStaticFilesStorage'
WHITENOISE_MANIFEST_STRICT = False

# Media / Uploaded files
MEDIA_URL = '/uploads/'
MEDIA_ROOT = BASE_DIR / 'uploads'

# Email Configuration (PHPMailer replacement)
EMAIL_BACKEND = 'django.core.mail.backends.smtp.EmailBackend'
EMAIL_HOST = os.environ.get('EMAIL_HOST', 'smtp.gmail.com')
EMAIL_PORT = int(os.environ.get('EMAIL_PORT', '587'))
EMAIL_USE_TLS = os.environ.get('EMAIL_USE_TLS', 'true').lower() == 'true'
EMAIL_USE_SSL = os.environ.get('EMAIL_USE_SSL', 'false').lower() == 'true'
if EMAIL_USE_TLS and EMAIL_USE_SSL:
    raise RuntimeError('Configure only one of EMAIL_USE_TLS or EMAIL_USE_SSL.')
EMAIL_HOST_USER = os.environ.get('EMAIL_HOST_USER', 'reccapinto8@gmail.com')
EMAIL_HOST_PASSWORD = os.environ.get('EMAIL_HOST_PASSWORD', '')
EMAIL_TIMEOUT = int(os.environ.get('EMAIL_TIMEOUT', '15'))
DEFAULT_FROM_EMAIL = os.environ.get(
    'DEFAULT_FROM_EMAIL',
    f'MultiBiz Global <{EMAIL_HOST_USER}>',
)
SITE_URL = os.environ.get('SITE_URL', 'https://multibiz.global').rstrip('/')
ADMIN_NOTIFICATION_EMAIL = os.environ.get(
    'ADMIN_NOTIFICATION_EMAIL',
    EMAIL_HOST_USER,
)

# Session Configuration
# Use cached_db: reads from fast local cache, only writes to DB when session changes.
# Saves ~50ms per request vs. plain db backend (avoids remote Supabase read every page).
SESSION_ENGINE = 'django.contrib.sessions.backends.cached_db'
SESSION_COOKIE_NAME = 'multibiz_sessionid'
SESSION_COOKIE_AGE = 86400 * 7 # 7 days
SESSION_EXPIRE_AT_BROWSER_CLOSE = False

# Default primary key field type
DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'

# ── Caching ──────────────────────────────────────────────────────────────────
CACHE_DIR = BASE_DIR / '.django_cache'
CACHE_BACKEND = os.environ.get('CACHE_BACKEND', 'django.core.cache.backends.filebased.FileBasedCache')
CACHE_LOCATION = os.environ.get('CACHE_LOCATION', str(CACHE_DIR))
CACHES = {
    'default': {
        'BACKEND': CACHE_BACKEND,
        'LOCATION': CACHE_LOCATION,
        'TIMEOUT': 86400 * 7,  # 7 days
        'OPTIONS': {
            'MAX_ENTRIES': 5000,
        },
    }
}

# Cache middleware (optional — adds per-view HTTP caching for anonymous users)
# CACHE_MIDDLEWARE_SECONDS = 60
# CACHE_MIDDLEWARE_KEY_PREFIX = 'mb'
