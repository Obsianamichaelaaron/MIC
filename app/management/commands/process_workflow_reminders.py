from django.core.management.base import BaseCommand

from app.services.workflow import process_workflow_reminders


class Command(BaseCommand):
    help = 'Create reminders for overdue application reviews and upcoming interviews.'

    def handle(self, *args, **options):
        counts = process_workflow_reminders()
        self.stdout.write(
            self.style.SUCCESS(
                'Created reminders: '
                f"admin={counts['admin']}, "
                f"employer={counts['employer']}, "
                f"interview={counts['interview']}"
            )
        )