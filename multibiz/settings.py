import os
from pathlib import Path

# Build paths inside the project like this: BASE_DIR / 'subdir'.
BASE_DIR = Path(__file__).resolve().parent.parent

# SECURITY WARNING: keep the secret key used in production secret!
SECRET_KEY = 'django-insecure-mb-multibiz-global-key-!$%@#981247192847129'

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = True

ALLOWED_HOSTS = ['*']

# Application definition
INSTALLED_APPS = [
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    'app',
]

MIDDLEWARE = [
    'django.middleware.security.SecurityMiddleware',
    'whitenoise.middleware.WhiteNoiseMiddleware',
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

# Database
# Default to SQLite for seamless execution; easily switched to MySQL/Postgres if desired
# In Vercel serverless environments (/var/task is read-only), copy to /tmp for write access
db_path = BASE_DIR / 'db.sqlite3'
if os.environ.get('VERCEL') == '1' or 'VERCEL' in os.environ:
    import shutil
    tmp_db = '/tmp/db.sqlite3'
    if not os.path.exists(tmp_db) and os.path.exists(db_path):
        try:
            shutil.copyfile(str(db_path), tmp_db)
        except Exception:
            pass
    if os.path.exists(tmp_db):
        db_path = tmp_db

DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.sqlite3',
        'NAME': str(db_path),
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
EMAIL_HOST = 'smtp.gmail.com'
EMAIL_PORT = 587
EMAIL_USE_TLS = True
EMAIL_HOST_USER = 'reccapinto8@gmail.com'
EMAIL_HOST_PASSWORD = 'xbkqkhqvgufpikem'
DEFAULT_FROM_EMAIL = 'MultiBiz Global <reccapinto8@gmail.com>'

# Session Configuration
SESSION_ENGINE = 'django.contrib.sessions.backends.db'
SESSION_COOKIE_NAME = 'multibiz_sessionid'
SESSION_COOKIE_AGE = 86400 * 7 # 7 days
SESSION_EXPIRE_AT_BROWSER_CLOSE = False

# Default primary key field type
DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'
