from django.shortcuts import render, get_object_or_404
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.db.models import Q, Max, Count
from django.utils import timezone
from app.models import User, Message, Applicant, Employer
from app.auth_utils import require_login, getCurrentUserId

@require_login
def chat_view(request):
    """
    Chat Messenger page for Applicant, Employer, and Admin.
    Matching applicant/chat.php, employer/chat.php, admin/chat.php.
    """
    user_id = getCurrentUserId(request)
    current_user = get_object_or_404(User, pk=user_id)
    active_recipient_id = request.GET.get('recipient_id') or request.GET.get('user_id')

    active_recipient = None
    if active_recipient_id and active_recipient_id.isdigit():
        active_recipient = User.objects.filter(pk=int(active_recipient_id)).first()

    template_name = 'applicant/chat.html'
    if current_user.role == 'employer':
        template_name = 'employer/chat.html'
    elif current_user.role == 'admin':
        template_name = 'admin/chat.html'

    context = {
        'page_title': 'Messages - MultiBiz',
        'current_page': 'chat.php',
        'current_user': current_user,
        'active_recipient': active_recipient,
    }
    return render(request, template_name, context)


@csrf_exempt
@require_login
def message_handler_api(request):
    """
    Message Handler API matching includes/handlers/message_handler.php.
    """
    user_id = getCurrentUserId(request)
    current_user = get_object_or_404(User, pk=user_id)
    action = request.POST.get('action') or request.GET.get('action') or ''

    if action == 'send':
        receiver_id = request.POST.get('receiver_id')
        msg_text = request.POST.get('message', '').strip()

        if not receiver_id or not msg_text:
            return JsonResponse({'success': False, 'message': 'Receiver ID and message are required'})

        if int(receiver_id) == user_id:
            return JsonResponse({'success': False, 'message': 'Cannot send message to yourself'})

        receiver_user = get_object_or_404(User, pk=receiver_id)

        # Communication restriction: applicant cannot message another applicant
        if current_user.role == 'applicant' and receiver_user.role == 'applicant':
            return JsonResponse({'success': False, 'message': 'Applicants cannot message other applicants'})

        msg = Message.objects.create(
            sender=current_user,
            receiver=receiver_user,
            message=msg_text,
            is_read=False
        )

        return JsonResponse({
            'success': True,
            'message_id': msg.message_id,
            'sender_id': current_user.user_id,
            'receiver_id': receiver_user.user_id,
            'message': msg.message,
            'created_at': msg.created_at.strftime('%Y-%m-%d %H:%M:%S')
        })

    elif action == 'get_conversations':
        # Get all distinct counterpart users
        sent_to = list(Message.objects.filter(sender=current_user).values_list('receiver_id', flat=True))
        received_from = list(Message.objects.filter(receiver=current_user).values_list('sender_id', flat=True))
        counterpart_ids = list(set(sent_to + received_from))

        # If user is employer or applicant, also list people they are in contact with
        if current_user.role == 'employer':
            # Add all applicants who applied to this employer's jobs
            from app.models import Application
            app_user_ids = list(Application.objects.filter(
                job__employer__user=current_user
            ).values_list('applicant__user_id', flat=True))
            counterpart_ids = list(set(counterpart_ids + app_user_ids))
        elif current_user.role == 'applicant':
            # Add all employers of jobs applied to
            from app.models import Application
            emp_user_ids = list(Application.objects.filter(
                applicant__user=current_user
            ).values_list('job__employer__user_id', flat=True))
            counterpart_ids = list(set(counterpart_ids + emp_user_ids))

        counterparts = User.objects.filter(user_id__in=counterpart_ids).exclude(user_id=user_id)

        conversations = []
        for other in counterparts:
            last_msg = Message.objects.filter(
                (Q(sender=current_user) & Q(receiver=other)) |
                (Q(sender=other) & Q(receiver=current_user))
            ).order_by('-created_at').first()

            unread = Message.objects.filter(sender=other, receiver=current_user, is_read=False).count()

            # Profile picture or company
            pic = ''
            company = ''
            if other.role == 'applicant':
                app_obj = Applicant.objects.filter(user=other).first()
                if app_obj and app_obj.profile_pic:
                    pic = app_obj.profile_pic
            elif other.role == 'employer':
                emp_obj = Employer.objects.filter(user=other).first()
                if emp_obj:
                    company = emp_obj.company_name or ''

            conversations.append({
                'user_id': other.user_id,
                'name': other.full_name or other.email,
                'email': other.email,
                'role': other.role,
                'company': company,
                'profile_pic': pic,
                'last_message': last_msg.message if last_msg else 'Start a conversation',
                'last_message_time': last_msg.created_at.strftime('%b %d, %H:%M') if last_msg else '',
                'unread_count': unread
            })

        return JsonResponse({'success': True, 'conversations': conversations})

    elif action == 'get_messages':
        other_id = request.GET.get('other_user_id') or request.POST.get('other_user_id')
        if not other_id or not other_id.isdigit():
            return JsonResponse({'success': False, 'message': 'Other user ID required'})

        other_user = get_object_or_404(User, pk=int(other_id))

        messages_qs = Message.objects.filter(
            (Q(sender=current_user) & Q(receiver=other_user)) |
            (Q(sender=other_user) & Q(receiver=current_user))
        ).order_by('created_at')

        # Mark as read
        Message.objects.filter(sender=other_user, receiver=current_user, is_read=False).update(is_read=True)

        msg_list = []
        for m in messages_qs:
            msg_list.append({
                'message_id': m.message_id,
                'sender_id': m.sender_id,
                'receiver_id': m.receiver_id,
                'message': m.message,
                'is_sender': m.sender_id == current_user.user_id,
                'created_at': m.created_at.strftime('%Y-%m-%d %H:%M:%S'),
                'time_formatted': m.created_at.strftime('%I:%M %p')
            })

        return JsonResponse({'success': True, 'messages': msg_list})

    elif action == 'mark_read':
        other_id = request.POST.get('other_user_id')
        if other_id and other_id.isdigit():
            Message.objects.filter(sender_id=int(other_id), receiver=current_user, is_read=False).update(is_read=True)
            return JsonResponse({'success': True})
        return JsonResponse({'success': False})

    elif action == 'get_unread_count':
        count = Message.objects.filter(receiver=current_user, is_read=False).count()
        return JsonResponse({'success': True, 'unread_count': count})

    return JsonResponse({'success': False, 'message': 'Invalid action'})
