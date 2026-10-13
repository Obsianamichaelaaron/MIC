# Deploying the Django app on Vercel

Vercel Functions have an ephemeral filesystem. The app therefore requires a
persistent PostgreSQL database; local SQLite files and `/tmp` are not used as
the Vercel production database. Uploaded files also need persistent object
storage if they must survive function invocations.

## Configure the project

1. Import the repository into Vercel with the project root set to the
   repository root. Vercel detects Django from `manage.py`.
2. In **Project Settings → Environment Variables**, set `DATABASE_URL` to the
   same persistent PostgreSQL connection string that the working local app
   uses. Use a database owner/migration-capable connection string. Do not use
   SQLite, a short-lived branch database, or a restricted runtime role.
   Keeping the same database means existing local-app accounts remain
   available; no SQLite data copy is needed if local already uses PostgreSQL.
3. Configure `DJANGO_SECRET_KEY` in the same Vercel environments. Generate a
   private value locally:

   ```sh
   python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"
   ```

   Keep it private and do not commit it. If connecting a new database that
   still needs migration `0006_signed_rls_context`, also configure
   `RLS_CONTEXT_SECRET` with a private random value of at least 32 characters.
4. Redeploy after saving the environment variables. The function applies
   database migrations at startup.

## Import a local SQLite database when needed

Only use this import if the working local app uses SQLite rather than the
shared PostgreSQL database. Keep the local `db.sqlite3` private. In PowerShell,
point the one-time importer at the persistent PostgreSQL database and the
local SQLite file:

```powershell
$env:DATABASE_URL = "<the persistent PostgreSQL connection string>"
$env:RLS_CONTEXT_SECRET = "<the same private value configured in Vercel>"
$env:MIGRATION_SOURCE_SQLITE_PATH = (Resolve-Path .\db.sqlite3).Path
python manage.py import_legacy_data
```

The importer checks that the target contains no application records, stages
the import, and refuses to overwrite an already populated database. Run it
once. Verify sign-in before enabling traffic. Do not paste database connection
strings or secret values into chat or commit them.

The importer moves database records only. Files in the local `uploads/`
directory need a separate transfer to persistent object storage and the
corresponding media storage configuration before relying on those files.

`vercel.json` explicitly includes both Django template directories in the
Python function bundle because Django resolves template files dynamically at
request time.
