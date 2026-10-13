import hashlib
import os
import re
import sqlite3
import tempfile
from pathlib import Path
from urllib.parse import quote, unquote, urlsplit

import requests
from django.apps import apps
from django.conf import settings
from django.core.management import call_command
from django.core.management.base import BaseCommand, CommandError
from django.db import connections

from app.models import Applicant, Application, Employer


class Command(BaseCommand):
    max_sqlite_source_bytes = 800 * 1024 * 1024
    help = (
        'Copy existing application records and storage files into the '
        'persistent SQLite and media directories.'
    )

    def handle(self, *args, **options):
        temporary_source_path = None
        if 'legacy' not in connections.databases:
            temporary_source_path = self._configure_sqlite_source()
        if 'legacy' not in connections.databases:
            raise CommandError(
                'Configure MIGRATION_SOURCE_DATABASE_URL, '
                'MIGRATION_SOURCE_SQLITE_PATH, or '
                'MIGRATION_SOURCE_SQLITE_URL before importing.'
            )

        target_database = connections['default']
        target_path = Path(connections.databases['default']['NAME'])
        if target_database.vendor == 'sqlite':
            target_path.parent.mkdir(parents=True, exist_ok=True)
        temporary_path = None
        try:
            self._ensure_target_is_empty()
            self._validate_source(
                target_path if target_database.vendor == 'sqlite' else None
            )
            self._require_storage_credentials()

            if target_database.vendor == 'postgresql':
                self._import_to_postgresql()
                self.stdout.write(self.style.SUCCESS(
                    'Imported legacy records into the persistent PostgreSQL '
                    'database. Restart the web service before switching traffic.'
                ))
                return
            if target_database.vendor != 'sqlite':
                raise CommandError(
                    'The target database must be SQLite or PostgreSQL.'
                )

            descriptor, temporary_name = tempfile.mkstemp(
                prefix=f'{target_path.stem}-import-',
                suffix='.sqlite3',
                dir=target_path.parent,
            )
            os.close(descriptor)
            temporary_path = Path(temporary_name)
            temporary_path.unlink()

            original_database_name = connections.databases['default']['NAME']
            connections['default'].close()
            connections.databases['default']['NAME'] = str(temporary_path)
            call_command('migrate', database='default', interactive=False, verbosity=0)
            self._import_fixture()
            self._import_storage_files()
            connections['default'].close()
            os.replace(temporary_path, target_path)
        except Exception as exc:
            connections['default'].close()
            if temporary_path:
                temporary_path.unlink(missing_ok=True)
            if isinstance(exc, CommandError):
                raise
            raise CommandError(f'Legacy data import failed: {exc}') from exc
        finally:
            connections['default'].close()
            if 'original_database_name' in locals():
                connections.databases['default']['NAME'] = original_database_name
            if temporary_source_path:
                connections['legacy'].close()
                temporary_source_path.unlink(missing_ok=True)

        self.stdout.write(self.style.SUCCESS(
            f'Imported legacy records into {target_path}. Restart the web service '
            'before switching traffic to it.'
        ))

    def _configure_sqlite_source(self):
        source_path = os.environ.get('MIGRATION_SOURCE_SQLITE_PATH')
        source_url = os.environ.get('MIGRATION_SOURCE_SQLITE_URL')
        if bool(source_path) == bool(source_url):
            return None

        if source_url:
            parsed_url = urlsplit(source_url)
            checksum = os.environ.get('MIGRATION_SOURCE_SQLITE_SHA256', '')
            if parsed_url.scheme != 'https' or not parsed_url.netloc:
                raise CommandError(
                    'MIGRATION_SOURCE_SQLITE_URL must be a private HTTPS '
                    'download URL.'
                )
            if not re.fullmatch(r'[0-9a-fA-F]{64}', checksum):
                raise CommandError(
                    'Set MIGRATION_SOURCE_SQLITE_SHA256 to the source file '
                    'SHA-256 checksum.'
                )

            settings.PERSISTENT_DATA_DIR.mkdir(parents=True, exist_ok=True)
            descriptor, temporary_name = tempfile.mkstemp(
                prefix='local-database-import-',
                suffix='.sqlite3',
                dir=settings.PERSISTENT_DATA_DIR,
            )
            temporary_path = Path(temporary_name)
            digest = hashlib.sha256()
            total_bytes = 0
            try:
                with os.fdopen(descriptor, 'wb') as destination:
                    with requests.get(
                        source_url,
                        stream=True,
                        timeout=(10, 60),
                    ) as response:
                        response.raise_for_status()
                        if urlsplit(response.url).scheme != 'https':
                            raise CommandError(
                                'The source database download redirected away '
                                'from HTTPS.'
                            )
                        for chunk in response.iter_content(chunk_size=1024 * 1024):
                            if not chunk:
                                continue
                            total_bytes += len(chunk)
                            if total_bytes > self.max_sqlite_source_bytes:
                                raise CommandError(
                                    'The source database exceeds the 800 MiB '
                                    'import limit.'
                                )
                            digest.update(chunk)
                            destination.write(chunk)
                if digest.hexdigest().lower() != checksum.lower():
                    raise CommandError(
                        'The downloaded source database checksum does not match.'
                    )
            except CommandError:
                temporary_path.unlink(missing_ok=True)
                raise
            except requests.RequestException:
                temporary_path.unlink(missing_ok=True)
                raise CommandError(
                    'Could not download the source database from the configured '
                    'HTTPS URL.'
                ) from None
            except OSError as exc:
                temporary_path.unlink(missing_ok=True)
                raise CommandError(
                    'Could not write the downloaded source database.'
                ) from exc
            source_path = temporary_path
            temporary_source_path = temporary_path
        else:
            temporary_source_path = None

        source_path = Path(source_path).expanduser().resolve()
        if not source_path.is_file():
            raise CommandError('The configured SQLite source file was not found.')
        legacy_database = connections.databases['default'].copy()
        legacy_database.update({
            'ENGINE': 'django.db.backends.sqlite3',
            'NAME': str(source_path),
            'OPTIONS': {'timeout': 20},
        })
        connections.databases['legacy'] = legacy_database
        return temporary_source_path

    @staticmethod
    def _validate_source(target_path=None):
        source_path = Path(connections.databases['legacy']['NAME']).resolve()
        if target_path and source_path == target_path.resolve():
            raise CommandError('The source and target database paths must differ.')
        if connections['legacy'].vendor != 'sqlite':
            return
        source = None
        try:
            source = sqlite3.connect(
                f'{source_path.as_uri()}?mode=ro',
                uri=True,
                timeout=20,
            )
            integrity = source.execute('PRAGMA integrity_check').fetchone()
            tables = {
                row[0]
                for row in source.execute(
                    "SELECT name FROM sqlite_master WHERE type='table'"
                )
            }
        except sqlite3.Error as exc:
            raise CommandError(
                'The source file is not a readable SQLite database.'
            ) from exc
        finally:
            if source:
                source.close()
        if not integrity or integrity[0] != 'ok':
            raise CommandError('The source SQLite database failed its integrity check.')
        if 'users' not in tables:
            raise CommandError(
                'The source SQLite database does not contain application tables.'
            )

    def _ensure_target_is_empty(self):
        connection = connections['default']
        existing_tables = set(connection.introspection.table_names())
        for model in apps.get_app_config('app').get_models():
            if model._meta.db_table not in existing_tables:
                continue
            has_records = model.objects.using('default').exists()
            if has_records:
                raise CommandError(
                    'The target SQLite database already contains application '
                    'records; refusing to overwrite it.'
                )

    def _import_to_postgresql(self):
        target_database_config = connections.databases['default'].copy()
        settings.PERSISTENT_DATA_DIR.mkdir(parents=True, exist_ok=True)
        descriptor, staging_name = tempfile.mkstemp(
            prefix='legacy-import-stage-',
            suffix='.sqlite3',
            dir=settings.PERSISTENT_DATA_DIR,
        )
        os.close(descriptor)
        staging_path = Path(staging_name)
        fixture_descriptor, fixture_name = tempfile.mkstemp(
            prefix='legacy-import-fixture-',
            suffix='.json',
            dir=settings.PERSISTENT_DATA_DIR,
        )
        os.close(fixture_descriptor)
        fixture_path = Path(fixture_name)
        try:
            connections['default'].close()
            connections.databases['default'] = {
                'ENGINE': 'django.db.backends.sqlite3',
                'NAME': str(staging_path),
                'OPTIONS': {'timeout': 20},
            }
            call_command('migrate', database='default', interactive=False, verbosity=0)
            self._import_fixture()
            self._import_storage_files()

            with fixture_path.open('w', encoding='utf-8') as fixture:
                call_command(
                    'dumpdata',
                    'app',
                    database='default',
                    natural_foreign=True,
                    natural_primary=True,
                    stdout=fixture,
                    verbosity=0,
                )
        finally:
            connections['default'].close()
            connections.databases['default'] = target_database_config
            staging_path.unlink(missing_ok=True)

        try:
            call_command('migrate', database='default', interactive=False, verbosity=0)
            from django.db import transaction

            with transaction.atomic(using='default'):
                call_command(
                    'loaddata',
                    str(fixture_path),
                    database='default',
                    verbosity=0,
                )
        finally:
            fixture_path.unlink(missing_ok=True)

    def _import_fixture(self):
        settings.PERSISTENT_DATA_DIR.mkdir(parents=True, exist_ok=True)
        descriptor, fixture_name = tempfile.mkstemp(
            prefix='legacy-data-',
            suffix='.json',
            dir=settings.PERSISTENT_DATA_DIR,
        )
        os.close(descriptor)
        fixture_path = Path(fixture_name)
        try:
            with fixture_path.open('w', encoding='utf-8') as fixture:
                call_command(
                    'dumpdata',
                    'app',
                    database='legacy',
                    natural_foreign=True,
                    natural_primary=True,
                    stdout=fixture,
                    verbosity=0,
                )
            call_command(
                'loaddata',
                str(fixture_path),
                database='default',
                verbosity=0,
            )
        finally:
            fixture_path.unlink(missing_ok=True)

    @staticmethod
    def _require_storage_credentials():
        has_supabase_files = any((
            Applicant.objects.using('legacy').filter(
                resume_file__startswith='supabase://'
            ).exists(),
            Applicant.objects.using('legacy').filter(
                profile_pic__contains='/storage/v1/object/public/'
            ).exists(),
            Application.objects.using('legacy').filter(
                resume_file__startswith='supabase://'
            ).exists(),
            Employer.objects.using('legacy').filter(
                company_logo__contains='/storage/v1/object/public/'
            ).exists(),
        ))
        if (
            has_supabase_files
            and (
                not os.environ.get('SUPABASE_URL')
                or not os.environ.get('SUPABASE_SERVICE_ROLE_KEY')
            )
        ):
            raise CommandError(
                'Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY to copy the '
                'existing resumes and profile images.'
            )

    def _import_storage_files(self):
        copied_objects = {}
        for applicant in Applicant.objects.using('default').iterator():
            changed_fields = []
            if applicant.resume_file and applicant.resume_file.startswith('supabase://'):
                applicant.resume_file = self._copy_private_resume(
                    applicant.resume_file,
                    copied_objects,
                )
                changed_fields.append('resume_file')
            if applicant.profile_pic:
                local_reference = self._copy_public_storage_reference(
                    applicant.profile_pic,
                    'profile_pics',
                    copied_objects,
                )
                if local_reference:
                    applicant.profile_pic = local_reference
                    changed_fields.append('profile_pic')
            if changed_fields:
                applicant.save(using='default', update_fields=changed_fields)

        for application in Application.objects.using('default').iterator():
            if application.resume_file and application.resume_file.startswith(
                'supabase://'
            ):
                application.resume_file = self._copy_private_resume(
                    application.resume_file,
                    copied_objects,
                )
                application.save(using='default', update_fields=['resume_file'])

        for employer in Employer.objects.using('default').iterator():
            if not employer.company_logo:
                continue
            local_reference = self._copy_public_storage_reference(
                employer.company_logo,
                'company_logos',
                copied_objects,
            )
            if local_reference:
                employer.company_logo = f'/{local_reference.lstrip("/")}'
                employer.save(using='default', update_fields=['company_logo'])

        remaining_resumes = (
            Applicant.objects.using('default')
            .filter(resume_file__startswith='supabase://').count()
            + Application.objects.using('default')
            .filter(resume_file__startswith='supabase://').count()
        )
        remaining_images = (
            Applicant.objects.using('default')
            .filter(profile_pic__contains='/storage/v1/object/public/').count()
            + Employer.objects.using('default')
            .filter(company_logo__contains='/storage/v1/object/public/').count()
        )
        if remaining_resumes or remaining_images:
            raise CommandError(
                f'{remaining_resumes} Supabase resume and {remaining_images} '
                'Supabase image references were not copied.'
            )

    def _copy_private_resume(self, reference, copied_objects):
        object_key = reference.removeprefix('supabase://')
        if not object_key.startswith('resumes/'):
            raise CommandError('Found an unsupported private resume reference.')
        bucket = os.environ.get('RESUME_STORAGE_BUCKET', 'private-resumes')
        return self._download_object(
            bucket,
            object_key,
            'resumes',
            copied_objects,
        )

    def _copy_public_storage_reference(self, reference, directory, copied_objects):
        parsed = urlsplit(reference)
        marker = '/storage/v1/object/public/'
        if marker not in parsed.path:
            return None
        bucket_and_key = parsed.path.split(marker, 1)[1].split('/')
        if len(bucket_and_key) < 2:
            raise CommandError('Found an invalid Supabase Storage image URL.')
        bucket = unquote(bucket_and_key[0])
        object_key = unquote('/'.join(bucket_and_key[1:]))
        prefix = 'profile-pictures/' if directory == 'profile_pics' else ''
        if prefix and not object_key.startswith(prefix):
            return None
        return self._download_object(
            bucket,
            object_key,
            directory,
            copied_objects,
        )

    def _download_object(self, bucket, object_key, directory, copied_objects):
        source_key = f'{bucket}/{object_key}'
        if source_key in copied_objects:
            return copied_objects[source_key]

        digest = hashlib.sha256(source_key.encode('utf-8')).hexdigest()[:16]
        suffix = Path(object_key).suffix
        filename = f'{digest}{suffix}'
        destination_dir = settings.MEDIA_ROOT / directory
        destination_dir.mkdir(parents=True, exist_ok=True)
        destination = destination_dir / filename

        base_url = os.environ['SUPABASE_URL'].rstrip('/')
        service_key = os.environ['SUPABASE_SERVICE_ROLE_KEY']
        url = (
            f'{base_url}/storage/v1/object/{quote(bucket, safe="")}/'
            f'{quote(object_key, safe="/")}'
        )
        response = requests.get(
            url,
            headers={
                'apikey': service_key,
                'Authorization': f'Bearer {service_key}',
            },
            timeout=(5, 60),
        )
        try:
            response.raise_for_status()
        except requests.RequestException as exc:
            raise CommandError(
                f'Could not copy a file from legacy storage (HTTP '
                f'{response.status_code}).'
            ) from exc
        destination.write_bytes(response.content)
        reference = f'uploads/{directory}/{filename}'
        copied_objects[source_key] = reference
        return reference
