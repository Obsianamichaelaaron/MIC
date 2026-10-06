# PostgreSQL row-level security

Migration `app.0004_postgresql_row_level_security` enables row-level security on
the existing public tables and adds policies for public content, applicants,
employers, administrators, sessions, and related records. PostgreSQL's default
deny behavior applies where no policy grants access.

Each PostgreSQL web request assumes the non-login `mic_app_rls` role locally
for its request transaction. This also works with transaction-pooled
connections; the role resets when the transaction ends.

`DATABASE_URL` is used only by management commands and migrations. The web
runtime requires a separate `RLS_DATABASE_URL`; it fails closed if that value
or `RLS_CONTEXT_SECRET` is missing. Configure `RLS_DATABASE_URL` with a
dedicated non-bypass PostgreSQL login that can assume `mic_app_rls`.

For example, create a runtime login through the database provider's SQL console
(use a unique, securely generated password):

```sql
CREATE ROLE mic_web_login LOGIN PASSWORD 'replace-with-a-unique-secret'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
GRANT mic_app_rls TO mic_web_login;
```

Use that login in `RLS_DATABASE_URL`, and configure `RLS_CONTEXT_SECRET` as a
private random value of at least 32 characters in both the production runtime
and the environment used to run migrations. Never commit either secret.

The RLS middleware wraps each PostgreSQL request in a transaction, limits
session-table access to the request's session key, and loads the account role
from the database before setting request-local policy context. Identity settings
are HMAC-signed with `RLS_CONTEXT_SECRET` and checked by a private PostgreSQL
function; changing a user ID or role without the secret invalidates the RLS
identity. Configure the same private random value (at least 32 characters) in
the web runtime and before applying migration `0006_signed_rls_context`.
Authentication lookup is performed by narrowly scoped database functions
because anonymous users cannot read the `users` table directly. SQLite
development databases do not use PostgreSQL RLS; the migration skips
PostgreSQL setup on SQLite.

After setting `RLS_CONTEXT_SECRET` locally, run database migrations with the
privileged `DATABASE_URL` connection:

```powershell
python manage.py migrate
```

Management commands that need schema access use the configured privileged
connection. Runtime web requests assume `mic_app_rls` inside a request
transaction from the non-bypass `RLS_DATABASE_URL` login. When adding a public
table, add an explicit RLS policy and the required grants in a migration;
otherwise that table remains inaccessible to the runtime role.

RLS policies restrict rows, not individual columns. Keep sensitive writes
behind the existing application authorization checks as well.
