# SQLite deployment on Render

The application uses SQLite and local media files. The Render blueprint
mounts a persistent disk at `/var/data` for the database, uploads, and cache.
Do not use this configuration on Vercel or another ephemeral filesystem.

## Migrate existing data

1. Create the Render service from `render.yaml`. Keep the current live domain
   pointed at the existing service until the import is complete.
2. To import the local SQLite database instead of a PostgreSQL source, stop
   local writes and create a consistent copy of `db.sqlite3`. Upload that copy
   to a private object-storage bucket and generate a short-lived HTTPS download
   URL. Do not put the database in a public bucket or commit it.
3. Calculate the copied file's SHA-256 locally:

   ```powershell
   Get-FileHash .\db.sqlite3 -Algorithm SHA256
   ```

   In the Render service environment, temporarily set
   `MIGRATION_SOURCE_SQLITE_URL` to the short-lived download URL and
   `MIGRATION_SOURCE_SQLITE_SHA256` to that hash. The importer verifies the
   checksum before reading the file. Alternatively, if the source file is
   already available on the Render disk, set `MIGRATION_SOURCE_SQLITE_PATH`
   to its path. Configure only one source type.

   For the PostgreSQL source flow, use `MIGRATION_SOURCE_DATABASE_URL`. If
   source records reference Supabase Storage, set `SUPABASE_URL` and
   `SUPABASE_SERVICE_ROLE_KEY` as temporary service environment variables.
   Never commit these values.
4. Open the Render service shell and run:

   ```sh
   python manage.py import_legacy_data
   ```

   The command validates the source SQLite file, copies app records into a
   temporary SQLite database, downloads referenced Supabase resumes and
   profile images into the persistent media directory, and replaces the empty
   target database only after the import succeeds. It refuses to overwrite a
   target database that already has application records. The local SQLite
   database is read-only as the source; only Django `app` records are imported,
   not old sessions.
5. Copy any local files referenced by records from `uploads/` to the matching
   paths under `/var/data/uploads`. The importer can copy Supabase Storage
   objects, but local files must be transferred separately.
6. Restart the Render service and verify sign-in, job listings, applications,
   profile images, and private resume downloads before changing DNS.
7. Remove the temporary database URL, checksum, and any storage credentials
   from Render after verifying the migration. Delete the temporary uploaded
   database object and its signed URL. Point the domain to Render only when
   ready.

The import preserves application model records and Supabase Storage objects.
Existing files stored only in a local `uploads/` directory must be copied to
`/var/data/uploads` before cutover.

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
