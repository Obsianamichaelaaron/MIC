import os
from django.core.wsgi import get_wsgi_application

from multibiz.runtime import prepare_vercel_database

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'multibiz.settings')
application = get_wsgi_application()
app = application

prepare_vercel_database()
