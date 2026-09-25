from app.models import AuditTrail, User

def log_audit_trail(request, admin_user_id, action_type, action_description, target_type=None, target_id=None, target_name=None):
    """
    Log an administrative action to the audit trail table.
    Equivalent to PHP's logAuditTrail().
    """
    admin_name = 'Unknown Admin'
    try:
        admin = User.objects.filter(user_id=admin_user_id).first()
        if admin:
            name = f"{admin.first_name or ''} {admin.last_name or ''}".strip()
            if name:
                admin_name = name
    except Exception:
        pass

    ip_address = None
    user_agent = None
    if request:
        x_forwarded_for = request.META.get('HTTP_X_FORWARDED_FOR')
        if x_forwarded_for:
            ip_address = x_forwarded_for.split(',')[0].strip()
        else:
            ip_address = request.META.get('REMOTE_ADDR')
        user_agent = request.META.get('HTTP_USER_AGENT')

    try:
        admin_user_obj = User.objects.filter(user_id=admin_user_id).first()
        if admin_user_obj:
            AuditTrail.objects.create(
                admin_user=admin_user_obj,
                admin_name=admin_name,
                action_type=action_type,
                action_description=action_description,
                target_type=target_type,
                target_id=target_id,
                target_name=target_name,
                ip_address=ip_address,
                user_agent=user_agent
            )
            return True
    except Exception as e:
        print(f"Error logging audit trail: {e}")
        return False
    return False
