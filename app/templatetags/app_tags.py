from django import template
import re
import os

register = template.Library()

@register.filter(name='get_item')
def get_item(dictionary, key):
    if dictionary is None:
        return None
    if isinstance(dictionary, dict):
        return dictionary.get(key)
    if isinstance(dictionary, (list, tuple)):
        try:
            return dictionary[int(key)]
        except (ValueError, IndexError):
            return None
    return getattr(dictionary, str(key), None)

@register.filter(name='trim')
def trim(value):
    if value is None:
        return ''
    return str(value).strip()

@register.filter(name='split')
def split(value, delimiter=','):
    if not value:
        return []
    return [s.strip() for s in str(value).split(delimiter) if s.strip()]

@register.filter(name='initials')
def initials(user_or_applicant):
    if not user_or_applicant:
        return 'U'
    first = getattr(user_or_applicant, 'first_name', '') or ''
    last = getattr(user_or_applicant, 'last_name', '') or ''
    if not first and hasattr(user_or_applicant, 'user'):
        first = user_or_applicant.user.first_name or ''
        last = user_or_applicant.user.last_name or ''
    res = (first[:1] + last[:1]).upper()
    return res if res else 'U'

@register.filter(name='multiply')
def multiply(value, arg):
    try:
        return float(value) * float(arg)
    except (ValueError, TypeError):
        return 0

@register.filter(name='divide')
def divide(value, arg):
    try:
        return float(value) / float(arg)
    except (ValueError, TypeError, ZeroDivisionError):
        return 0

@register.filter(name='percentage')
def percentage(value, max_val):
    try:
        val = float(value)
        mx = float(max_val)
        if mx <= 0:
            return 0
        return min(100, round((val / mx) * 100, 1))
    except (ValueError, TypeError):
        return 0

@register.filter(name='file_ext')
def file_ext(path):
    if not path:
        return ''
    return os.path.splitext(str(path))[1].lstrip('.').upper()


@register.filter(name='parse_remarks')
def parse_remarks(value):
    """
    Parses application.remarks_history (JSON array or plain text lines)
    into a structured list of dicts:
    [{'timestamp': '...', 'status': '...', 'remarks': '...', 'reviewed_by': '...'}, ...]
    """
    import json
    if not value:
        return []
    if isinstance(value, list):
        return value

    raw = str(value).strip()
    if not raw:
        return []

    # 1. Try parsing JSON
    if (raw.startswith('[') and raw.endswith(']')) or (raw.startswith('{') and raw.endswith('}')):
        try:
            parsed = json.loads(raw)
            if isinstance(parsed, list):
                result = []
                for item in parsed:
                    if isinstance(item, dict):
                        result.append({
                            'timestamp': item.get('timestamp') or item.get('date') or item.get('time') or '',
                            'status': item.get('status') or '',
                            'remarks': item.get('remarks') or item.get('comment') or item.get('note') or '',
                            'reviewed_by': item.get('reviewed_by') or item.get('user') or item.get('by') or 'Recruitment Team',
                        })
                    elif isinstance(item, str):
                        result.append({'timestamp': '', 'status': '', 'remarks': item, 'reviewed_by': ''})
                return result
            elif isinstance(parsed, dict):
                return [{
                    'timestamp': parsed.get('timestamp') or '',
                    'status': parsed.get('status') or '',
                    'remarks': parsed.get('remarks') or '',
                    'reviewed_by': parsed.get('reviewed_by') or 'Recruitment Team',
                }]
        except Exception:
            pass

    # 2. Try parsing line by line
    lines = [l.strip() for l in raw.splitlines() if l.strip()]
    result = []
    for line in lines:
        match = re.match(r'^\[(.*?)\]\s*(.*)$', line)
        if match:
            ts, rest = match.group(1), match.group(2)
            status_match = re.search(r'Status changed to ([A-Za-z\s]+?)(?:\.|$)', rest, re.IGNORECASE)
            remarks_match = re.search(r'Remarks:\s*(.*)$', rest, re.IGNORECASE)
            status_val = status_match.group(1).strip() if status_match else ''
            remarks_val = remarks_match.group(1).strip() if remarks_match else rest
            result.append({
                'timestamp': ts,
                'status': status_val,
                'remarks': remarks_val,
                'reviewed_by': 'Recruitment Team',
            })
        else:
            result.append({
                'timestamp': '',
                'status': '',
                'remarks': line,
                'reviewed_by': '',
            })
    return result

