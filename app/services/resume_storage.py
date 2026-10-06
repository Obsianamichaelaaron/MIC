import os
import shutil
import tempfile
import uuid
from contextlib import contextmanager
from pathlib import Path
from urllib.parse import quote

import requests
from django.conf import settings


SUPABASE_RESUME_PREFIX = 'supabase://'


class ResumeStorageError(RuntimeError):
    pass


def _storage_request(method, object_key, bucket_name=None, **kwargs):
    if not settings.SUPABASE_URL or not settings.SUPABASE_SERVICE_ROLE_KEY:
        raise ResumeStorageError(
            'Configure SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY before '
            'using persistent resume storage.'
        )

    bucket = quote(
        bucket_name or settings.RESUME_STORAGE_BUCKET,
        safe='',
    )
    key = quote(object_key, safe='/')
    headers = {
        'apikey': settings.SUPABASE_SERVICE_ROLE_KEY,
        'Authorization': f'Bearer {settings.SUPABASE_SERVICE_ROLE_KEY}',
        **kwargs.pop('headers', {}),
    }
    try:
        return requests.request(
            method,
            f'{settings.SUPABASE_URL}/storage/v1/object/{bucket}/{key}',
            headers=headers,
            timeout=(5, 30),
            **kwargs,
        )
    except requests.RequestException as exc:
        raise ResumeStorageError(
            'Resume storage is temporarily unavailable.'
        ) from exc


@contextmanager
def stage_uploaded_file(uploaded_file, extension):
    temp_dir = '/tmp' if os.environ.get('VERCEL') == '1' else None
    temp_file = tempfile.NamedTemporaryFile(
        mode='wb',
        suffix=extension,
        dir=temp_dir,
        delete=False,
    )
    path = Path(temp_file.name)
    try:
        with temp_file:
            for chunk in uploaded_file.chunks():
                temp_file.write(chunk)
        yield path
    finally:
        path.unlink(missing_ok=True)


def store_verified_resume(source_path, extension):
    filename = f'{uuid.uuid4().hex}{extension}'
    if os.environ.get('VERCEL') == '1':
        object_key = f'resumes/{filename}'
        with open(source_path, 'rb') as source:
            response = _storage_request(
                'POST',
                object_key,
                data=source,
                headers={
                    'Content-Type': {
                        '.pdf': 'application/pdf',
                        '.doc': 'application/msword',
                        '.docx': (
                            'application/vnd.openxmlformats-officedocument.'
                            'wordprocessingml.document'
                        ),
                    }[extension],
                    'x-upsert': 'false',
                },
            )
        try:
            response.raise_for_status()
        except requests.RequestException as exc:
            raise ResumeStorageError(
                'Resume storage rejected the uploaded file.'
            ) from exc
        return f'{SUPABASE_RESUME_PREFIX}{object_key}'

    upload_dir = settings.MEDIA_ROOT / 'resumes'
    upload_dir.mkdir(parents=True, exist_ok=True)
    destination = upload_dir / filename
    shutil.copyfile(source_path, destination)
    return f'uploads/resumes/{filename}'


def store_profile_picture(source_path, extension):
    filename = f'{uuid.uuid4().hex}{extension}'
    if os.environ.get('VERCEL') == '1':
        bucket = settings.PROFILE_PICTURE_STORAGE_BUCKET
        object_key = f'profile-pictures/{filename}'
        with open(source_path, 'rb') as source:
            response = _storage_request(
                'POST',
                object_key,
                bucket_name=bucket,
                data=source,
                headers={
                    'Content-Type': {
                        '.png': 'image/png',
                        '.jpg': 'image/jpeg',
                        '.jpeg': 'image/jpeg',
                        '.webp': 'image/webp',
                    }[extension],
                    'x-upsert': 'false',
                },
            )
        try:
            response.raise_for_status()
        except requests.RequestException as exc:
            raise ResumeStorageError(
                'Profile picture storage rejected the uploaded file.'
            ) from exc
        return (
            f'{settings.SUPABASE_URL}/storage/v1/object/public/'
            f'{quote(bucket, safe="")}/{quote(object_key, safe="/")}'
        )

    upload_dir = settings.MEDIA_ROOT / 'profile_pics'
    upload_dir.mkdir(parents=True, exist_ok=True)
    destination = upload_dir / filename
    shutil.copyfile(source_path, destination)
    return f'uploads/profile_pics/{filename}'


def delete_resume(reference):
    if reference.startswith(SUPABASE_RESUME_PREFIX):
        object_key = reference[len(SUPABASE_RESUME_PREFIX):]
        if not object_key.startswith('resumes/'):
            raise ValueError('Invalid stored resume reference.')
        response = _storage_request('DELETE', object_key)
        try:
            response.raise_for_status()
        except requests.RequestException as exc:
            raise ResumeStorageError(
                'Resume storage could not delete the file.'
            ) from exc
        return

    relative_path = reference.replace('\\', '/').removeprefix('uploads/')
    media_root = settings.MEDIA_ROOT.resolve()
    local_path = (media_root / relative_path).resolve()
    if media_root not in local_path.parents:
        raise ValueError('Invalid local resume path.')
    local_path.unlink(missing_ok=True)


def read_resume(reference):
    if reference.startswith(SUPABASE_RESUME_PREFIX):
        object_key = reference[len(SUPABASE_RESUME_PREFIX):]
        if not object_key.startswith('resumes/'):
            raise ValueError('Invalid stored resume reference.')
        response = _storage_request('GET', object_key)
        try:
            response.raise_for_status()
        except requests.RequestException as exc:
            raise ResumeStorageError(
                'Resume storage could not retrieve the file.'
            ) from exc
        return response.content, Path(object_key).name

    relative_path = reference.replace('\\', '/').removeprefix('uploads/')
    media_root = settings.MEDIA_ROOT.resolve()
    local_path = (media_root / relative_path).resolve()
    if media_root not in local_path.parents or not local_path.is_file():
        raise FileNotFoundError('Resume file was not found.')
    return local_path.read_bytes(), local_path.name
