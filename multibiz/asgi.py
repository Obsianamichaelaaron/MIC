import os
from django.core.asgi import get_asgi_application

from multibiz.runtime import prepare_vercel_database

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'multibiz.settings')
application = get_asgi_application()

prepare_vercel_database()
