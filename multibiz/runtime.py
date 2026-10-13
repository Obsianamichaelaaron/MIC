import os


def prepare_vercel_database():
    if os.environ.get('VERCEL') != '1':
        return

    from django.core.management import call_command

    call_command('migrate', interactive=False, verbosity=0)
