from django.core.cache import cache

from app.models import Message, Notification


UNREAD_MESSAGE_COUNT_TTL_SECONDS = 30
UNREAD_NOTIFICATION_COUNT_TTL_SECONDS = 5


def get_unread_message_count(user_id):
    if not user_id:
        return 0

    cache_key = f'unread_msgs_{user_id}'
    unread_count = cache.get(cache_key)
    if unread_count is None:
        unread_count = Message.objects.filter(
            receiver_id=user_id,
            is_read=False,
        ).count()
        cache.set(cache_key, unread_count, UNREAD_MESSAGE_COUNT_TTL_SECONDS)
    return unread_count


def get_unread_notification_count(user_id):
    if not user_id:
        return 0

    cache_key = f'unread_notifications_{user_id}'
    unread_count = cache.get(cache_key)
    if unread_count is None:
        unread_count = Notification.objects.filter(
            user_id=user_id,
            is_read=False,
        ).count()
        cache.set(cache_key, unread_count, UNREAD_NOTIFICATION_COUNT_TTL_SECONDS)
    return unread_count
