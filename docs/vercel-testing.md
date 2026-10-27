# Deploying the Django app on Vercel

Vercel Functions have an ephemeral filesystem. Use PostgreSQL for persistent
production data. For temporary panel checks only, `DATABASE_BACKEND=sqlite`
selects SQLite at `/tmp/db.sqlite3`; its data may disappear or differ between
function instances. Uploaded files have the same persistence limitation.

## Configure the project

1. Import the repository into Vercel with the project root set to the
   repository root. Vercel detects Django from `manage.py`.
2. In **Project Settings → Environment Variables**, set `DATABASE_URL` to the
   same persistent PostgreSQL connection string that the working local app
   uses. Use a database owner/migration-capable connection string. Do not use
   a short-lived branch database or a restricted runtime role. Keeping the
   same database means existing local-app accounts remain
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

For temporary SQLite testing instead, set `DATABASE_BACKEND=sqlite` in the
Vercel environment and redeploy. The local SQLite copy is not uploaded to
Vercel, so this creates a separate, ephemeral database and does not include
the copied accounts. Remove `DATABASE_BACKEND` (or set it to `postgresql`)
and redeploy to return to PostgreSQL.

To test existing accounts on Vercel SQLite, create a private seed file named
`vercel_sqlite_seed.sqlite3` from the local database and set
`SQLITE_SEED_PATH=vercel_sqlite_seed.sqlite3` in Vercel. Deploy from a private
staging directory with the Vercel CLI and verify the seed is in the function
bundle; the repository intentionally ignores the database. Do not add it to
Git or GitHub, and do not include `.env` files or other credentials in the
staging directory. If the configured seed is absent from the function bundle,
startup logs a warning and continues with an empty database; remove
`SQLITE_SEED_PATH` or deploy the seed to load its data. Each function instance
copies the seed to its own `/tmp/db.sqlite3` on first startup and runs
migrations. This is only a temporary sign-in/testing snapshot: changes are not
shared across instances and may be lost when an instance restarts or is
redeployed. Remove the seed environment variable and redeploy when finished.
Vercel SQLite deployments use encrypted, signed-cookie sessions so logins
remain valid across function instances; keep `DJANGO_SECRET_KEY` unchanged
between deployments.

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
