import hmac

from django.conf import settings
from django.http import HttpResponseForbidden, JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_POST

from app.services.workflow import process_workflow_reminders


@csrf_exempt
@require_POST
def process_workflow_reminders_api(request):
    expected_token = getattr(settings, 'WORKFLOW_REMINDER_TOKEN', '')
    supplied_token = request.headers.get('Authorization', '').removeprefix('Bearer ').strip()
    if not expected_token or not hmac.compare_digest(supplied_token, expected_token):
        return HttpResponseForbidden('Invalid scheduler token.')
    return JsonResponse({'success': True, 'created': process_workflow_reminders()})