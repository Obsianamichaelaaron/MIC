# PostgreSQL row-level security

Migration `app.0004_postgresql_row_level_security` enables row-level security on
the existing public tables and adds policies for public content, applicants,
employers, administrators, sessions, and related records. PostgreSQL's default
deny behavior applies where no policy grants access.

Each PostgreSQL web request assumes the non-login `mic_app_rls` role locally
for its request transaction. This also works with transaction-pooled
connections; the role resets when the transaction ends.

The existing `DATABASE_URL` role must be able to assume `mic_app_rls`; the
migration grants that membership to the role used to run migrations. Keep the
privileged `DATABASE_URL` secret private.

The RLS middleware wraps each PostgreSQL request in a transaction, limits
session-table access to the request's session key, and loads the account role
from the database before setting request-local policy context. Authentication
lookup is performed by narrowly scoped database functions because anonymous
users cannot read the `users` table directly. SQLite development databases do
not use PostgreSQL RLS; the migration skips PostgreSQL setup on SQLite.

Run database migrations with the privileged `DATABASE_URL` connection:

```powershell
python manage.py migrate
```

Management commands that need schema access use the configured privileged
connection. Runtime web requests use `mic_app_rls`. When adding a public table,
add an explicit RLS policy and the required grants in a migration; otherwise
that table remains inaccessible to the runtime role.

RLS policies restrict rows, not individual columns. Keep sensitive writes
behind the existing application authorization checks as well.
