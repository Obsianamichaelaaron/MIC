from .models import User, Applicant, ContactInquiry, Message

def global_context(request):
    """
    Context processor providing global variables to all templates
    mirroring PHP's session and header.php global context.
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
        try:
            applicant = Applicant.objects.filter(user_id=user_id).first()
            if applicant and applicant.profile_pic:
                profile_pic = applicant.profile_pic
        except Exception:
            profile_pic = ''
            
    # Unread inquiries count for Admin
    unread_inquiries_count = 0
    if role == 'admin':
        try:
            unread_inquiries_count = ContactInquiry.objects.filter(is_read=False).count()
        except Exception:
            unread_inquiries_count = 0
            
    # Unread messages count
    unread_messages_count = 0
    if user_id:
        try:
            unread_messages_count = Message.objects.filter(receiver_id=user_id, is_read=False).count()
        except Exception:
            unread_messages_count = 0

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
    }
