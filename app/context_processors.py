from django.core.cache import cache
from .models import User, Applicant, ContactInquiry, Message, Notification

def global_context(request):
    """
    Context processor providing global variables to all templates
    mirroring PHP's session and header.php global context.

    Unread counts are cached per-user for 60 seconds to avoid N+1 queries
    on every single page load.
    """
    user_id = request.session.get('user_id')
    role = request.session.get('role')
    first_name = request.session.get('first_name', '')
    last_name = request.session.get('last_name', '')
    email = request.session.get('email', '')
    
    full_name = f"{first_name} {last_name}".strip()
    
    avatar_initials = ''
    if full_name:
        parts = full_name.split()
        for p in parts[:2]:
            if p:
                avatar_initials += p[0].upper()
    elif email:
        avatar_initials = email[:2].upper()
    else:
        avatar_initials = 'U'
        
    profile_pic = ''
    if role == 'applicant' and user_id:
        # Cache the profile pic per applicant — changes rarely
        _pp_key = f'profile_pic_{user_id}'
        profile_pic = cache.get(_pp_key)
        if profile_pic is None:
            try:
                applicant = Applicant.objects.filter(user_id=user_id).only('profile_pic').first()
                profile_pic = (applicant.profile_pic if applicant and applicant.profile_pic else '') or ''
            except Exception:
                profile_pic = ''
            cache.set(_pp_key, profile_pic, 120)  # 2 minutes
            
    # Unread inquiries count for Admin — cache 60s
    unread_inquiries_count = 0
    if role == 'admin':
        _inq_key = 'admin_unread_inquiries'
        cached_count = cache.get(_inq_key)
        if cached_count is None:
            try:
                cached_count = ContactInquiry.objects.filter(is_read=False).count()
            except Exception:
                cached_count = 0
            cache.set(_inq_key, cached_count, 60)
        unread_inquiries_count = cached_count
            
    # Unread messages count — cache 30s per user
    unread_messages_count = 0
    if user_id:
        _msg_key = f'unread_msgs_{user_id}'
        cached_msgs = cache.get(_msg_key)
        if cached_msgs is None:
            try:
                cached_msgs = Message.objects.filter(receiver_id=user_id, is_read=False).count()
            except Exception:
                cached_msgs = 0
            cache.set(_msg_key, cached_msgs, 30)
        unread_messages_count = cached_msgs

    unread_notification_count = 0
    if role in {'applicant', 'admin'} and user_id:
        unread_notification_count = Notification.objects.filter(
            user_id=user_id,
            is_read=False,
        ).count()

    return {
        'is_logged_in': bool(user_id),
        'current_user_id': user_id,
        'current_role': role,
        'current_user_name': full_name,
        'current_user_email': email,
        'avatar_initials': avatar_initials,
        'profile_pic': profile_pic,
        'unread_inquiries_count': unread_inquiries_count,
        'unread_messages_count': unread_messages_count,
        'unread_notification_count': unread_notification_count,
    }
