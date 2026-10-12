import shutil
import tempfile
import uuid
from contextlib import contextmanager
from pathlib import Path

from django.conf import settings


class ResumeStorageError(RuntimeError):
    pass


@contextmanager
def stage_uploaded_file(uploaded_file, extension):
    temp_file = tempfile.NamedTemporaryFile(
        mode='wb',
        suffix=extension,
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
    upload_dir = settings.MEDIA_ROOT / 'resumes'
    upload_dir.mkdir(parents=True, exist_ok=True)
    destination = upload_dir / filename
    shutil.copyfile(source_path, destination)
    return f'uploads/resumes/{filename}'


def store_profile_picture(source_path, extension):
    filename = f'{uuid.uuid4().hex}{extension}'
    upload_dir = settings.MEDIA_ROOT / 'profile_pics'
    upload_dir.mkdir(parents=True, exist_ok=True)
    destination = upload_dir / filename
    shutil.copyfile(source_path, destination)
    return f'uploads/profile_pics/{filename}'


def delete_resume(reference):
    relative_path = reference.replace('\\', '/').removeprefix('uploads/')
    media_root = settings.MEDIA_ROOT.resolve()
    local_path = (media_root / relative_path).resolve()
    if media_root not in local_path.parents:
        raise ValueError('Invalid local resume path.')
    local_path.unlink(missing_ok=True)


def read_resume(reference):
    relative_path = reference.replace('\\', '/').removeprefix('uploads/')
    media_root = settings.MEDIA_ROOT.resolve()
    local_path = (media_root / relative_path).resolve()
    if media_root not in local_path.parents or not local_path.is_file():
        raise FileNotFoundError('Resume file was not found.')
    return local_path.read_bytes(), local_path.name
