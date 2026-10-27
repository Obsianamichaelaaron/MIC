import os
import shutil
import tempfile
from pathlib import Path


def prepare_vercel_database():
    if os.environ.get('VERCEL') != '1':
        return

    from django.conf import settings
    from django.core.management import call_command

    database = settings.DATABASES['default']
    seed_path = getattr(settings, 'SQLITE_SEED_PATH', None)
    if (
        database['ENGINE'] == 'django.db.backends.sqlite3'
        and seed_path
    ):
        seed_path = Path(seed_path)
        database_path = Path(database['NAME'])
        if not seed_path.is_file():
            raise FileNotFoundError(
                f'SQLITE_SEED_PATH does not exist: {seed_path}'
            )
        if seed_path.resolve() != database_path.resolve() and (
            not database_path.exists() or database_path.stat().st_size == 0
        ):
            database_path.parent.mkdir(parents=True, exist_ok=True)
            temporary_path = None
            try:
                with tempfile.NamedTemporaryFile(
                    dir=database_path.parent,
                    prefix=f'.{database_path.name}.',
                    delete=False,
                ) as temporary_file:
                    temporary_path = Path(temporary_file.name)
                shutil.copyfile(seed_path, temporary_path)
                os.replace(temporary_path, database_path)
            finally:
                if temporary_path and temporary_path.exists():
                    temporary_path.unlink()

    call_command('migrate', interactive=False, verbosity=0)
