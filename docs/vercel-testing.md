# Testing the Django app on Vercel

This configuration is for previews and smoke testing only. Vercel Functions
have an ephemeral filesystem: SQLite data, uploaded files, and files in `/tmp`
can disappear between invocations or differ between function instances. Do
not use this deployment for production data.

## Configure the project

1. Import the repository into Vercel with the project root set to the
   repository root. Vercel detects Django from `manage.py`.
2. In **Project Settings → Environment Variables**, add `DJANGO_SECRET_KEY`
   for every environment you deploy (Production, Preview, and Development).
   Generate a value locally with:

   ```sh
   python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"
   ```

   Keep the value private and do not commit it.
3. Redeploy after saving the environment variable.

The app uses SQLite at `/tmp/db.sqlite3` and an in-memory cache when running
on Vercel. Database migrations run when a Vercel function instance starts, so
its schema is initialized before serving requests. The database and media
files are still intentionally disposable: data can disappear between
invocations or differ between function instances. Do not use this setup for
production data. For a production deployment with persistent SQLite and
uploads, use the Render configuration in `render.yaml`.

`vercel.json` explicitly includes both Django template directories in the
Python function bundle because Django resolves template files dynamically at
request time.
