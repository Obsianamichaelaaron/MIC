# SQLite deployment on Render

The application now uses SQLite and local media files. The Render blueprint
mounts a persistent disk at `/var/data` for the database, uploads, and cache.
Do not run this configuration on Vercel or another ephemeral filesystem.

## Migrate existing data

1. Create the Render service from `render.yaml`. Keep the current live domain
   pointed at the existing service until the import is complete.
2. In the Render service environment, temporarily set
   `MIGRATION_SOURCE_DATABASE_URL` to the privileged, read-only PostgreSQL
   connection for the existing database. Also set `SUPABASE_URL` and
   `SUPABASE_SERVICE_ROLE_KEY` if legacy records refer to Supabase Storage.
   These values are migration-only; never commit them.
3. Open the Render service shell and run:

   ```sh
   python manage.py import_legacy_data
   ```

   The command copies app records into a temporary SQLite database, downloads
   referenced Supabase resumes and profile images into the persistent media
   directory, and atomically replaces the empty target database only after the
   import succeeds. It refuses to overwrite a SQLite database that already has
   application records.
4. Restart the Render service and verify sign-in, job listings, applications,
   profile images, and private resume downloads before changing DNS.
5. Remove the temporary `MIGRATION_SOURCE_DATABASE_URL`,
   `SUPABASE_URL`, and `SUPABASE_SERVICE_ROLE_KEY` values from Render after
   verifying the migration. Point the domain to Render only when ready.

The import preserves application model records and Supabase Storage objects.
Existing files stored only in a local `uploads/` directory must also be copied
to `/var/data/uploads` before cutover.

After the migrated site has been verified and the old database is no longer
needed as a rollback source, remove the one-time importer and its PostgreSQL
driver/URL-parser dependencies in a follow-up code change. Keep the guarded
historical PostgreSQL migrations; they are part of Django's migration history
and do not run PostgreSQL operations on SQLite.

## Backups and operations

SQLite and uploaded files live on the attached persistent disk. Keep scheduled
backups of `/var/data/db.sqlite3` and `/var/data/uploads`; a persistent disk is
not itself a backup. Run one Render web instance because a SQLite database on a
single attached disk is not a multi-instance database.

The PostgreSQL-specific migrations remain in migration history for existing
databases, but their operations are guarded by the database vendor and are
skipped by SQLite. The PostgreSQL driver and URL parser are retained only to
read the source database during the one-time import.
