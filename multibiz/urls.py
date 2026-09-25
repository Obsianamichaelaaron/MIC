from django.contrib import admin
from django.urls import path, include, re_path
from django.conf import settings
from django.conf.urls.static import static
from django.views.static import serve
from app.views.views_public import smart_media_serve

urlpatterns = [
    path('django-admin/', admin.site.urls),
    path('', include('app.urls')),
    # Direct media, static and asset routing
    re_path(r'^uploads/(?P<path>.*)$', smart_media_serve),
    re_path(r'^images/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'images')}),
    re_path(r'^js/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'js')}),
    re_path(r'^css/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'css')}),
    re_path(r'^2024/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / '2024')}),
    re_path(r'^applicant/images/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'images')}),
    re_path(r'^applicant/js/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'js')}),
    re_path(r'^applicant/css/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'css')}),
    re_path(r'^employer/images/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'images')}),
    re_path(r'^employer/js/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'js')}),
    re_path(r'^employer/css/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'css')}),
    re_path(r'^admin/images/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'images')}),
    re_path(r'^admin/js/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'js')}),
    re_path(r'^admin/css/(?P<path>.*)$', serve, {'document_root': str(settings.BASE_DIR / 'static' / 'css')}),
]

if settings.DEBUG:
    urlpatterns += static(settings.STATIC_URL, document_root=settings.STATICFILES_DIRS[0])
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
