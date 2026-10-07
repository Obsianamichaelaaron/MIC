import os
import json
import glob
import base64
import uuid
import requests
from django.conf import settings

TARGET_FOLDER_ID = "1pDIHRWfljyosJ0mJtVSL9zvSsYl2CjZv"
TARGET_DRIVE_FOLDER_URL = f"https://drive.google.com/drive/folders/{TARGET_FOLDER_ID}?usp=sharing"
DRIVE_UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files'
EXCEL_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'


def _create_drive_file(credentials, file_bytes, filename, folder_id):
    from google.auth.transport.requests import AuthorizedSession

    boundary = f'multibiz-{uuid.uuid4().hex}'
    metadata = json.dumps({'name': filename, 'parents': [folder_id]})
    body = b''.join((
        f'--{boundary}\r\n'.encode(),
        b'Content-Type: application/json; charset=UTF-8\r\n\r\n',
        metadata.encode('utf-8'),
        f'\r\n--{boundary}\r\n'.encode(),
        f'Content-Type: {EXCEL_MIME_TYPE}\r\n\r\n'.encode(),
        file_bytes,
        f'\r\n--{boundary}--'.encode(),
    ))
    headers = {'Content-Type': f'multipart/related; boundary="{boundary}"'}
    params = {
        'uploadType': 'multipart',
        'supportsAllDrives': 'true',
        'fields': 'id,name,webViewLink',
    }
    with AuthorizedSession(credentials) as session:
        response = session.post(
            DRIVE_UPLOAD_URL,
            params=params,
            data=body,
            headers=headers,
            timeout=60,
        )
    try:
        response.raise_for_status()
    except requests.HTTPError as exc:
        try:
            details = json.dumps(response.json())
        except ValueError:
            details = response.text
        raise RuntimeError(details) from exc
    return response.json()


def _find_oauth_client_secret():
    candidates = [
        getattr(settings, 'GOOGLE_OAUTH_CLIENT_SECRET_FILE', None),
        str(settings.BASE_DIR / 'client_secret_*.json'),
        str(settings.BASE_DIR / 'credentials.json'),
    ]
    for cand in candidates:
        if not cand:
            continue
        matches = glob.glob(cand)
        for match in matches:
            try:
                with open(match, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                if isinstance(data, dict) and 'installed' in data:
                    return match
            except Exception:
                continue
    return None


def _upload_with_oauth_user(file_bytes, filename, folder_id):
    token_path = str(settings.BASE_DIR / 'token.json')
    client_secret_path = _find_oauth_client_secret()
    if not client_secret_path or not os.path.exists(client_secret_path):
        return {
            'success': False,
            'error': 'oauth_missing',
            'message': 'Google Drive OAuth is not configured. Add a client_secret_*.json file and authorize a Google account once.'
        }

    if not os.path.exists(token_path):
        return {
            'success': False,
            'error': 'oauth_required',
            'message': 'Google Drive OAuth authorization is required. Run the Google Drive authorization flow once to create token.json.'
        }

    try:
        from google.oauth2.credentials import Credentials
        from google.auth.transport.requests import Request

        creds = Credentials.from_authorized_user_file(token_path, ['https://www.googleapis.com/auth/drive'])
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
            with open(token_path, 'w', encoding='utf-8') as token_file:
                token_file.write(creds.to_json())

        if not creds or not creds.valid:
            return {
                'success': False,
                'error': 'oauth_required',
                'message': 'Google Drive OAuth token is expired or invalid. Re-authorize the Google account to continue.'
            }

        file_obj = _create_drive_file(creds, file_bytes, filename, folder_id)

        return {
            'success': True,
            'method': 'oauth_user',
            'file_id': file_obj.get('id'),
            'file_name': file_obj.get('name'),
            'file_url': file_obj.get('webViewLink', TARGET_DRIVE_FOLDER_URL),
            'folder_url': TARGET_DRIVE_FOLDER_URL,
            'message': f"Uploaded {filename} directly to Google Drive via OAuth!"
        }
    except Exception as e:
        msg = str(e)
        return {
            'success': False,
            'error': 'oauth_failed',
            'message': f"Google Drive OAuth upload failed: {msg}"
        }


def upload_excel_to_google_drive(file_bytes, filename, folder_id=TARGET_FOLDER_ID):
    """
    Directly uploads an Excel workbook to Google Drive without client download.
    Supports:
    1. Google Service Account key file (service_account.json or credentials.json)
    2. Google Apps Script Webhook URL (GOOGLE_DRIVE_WEBHOOK_URL)
    """
    # 1. Check for configured Google Apps Script Webhook URL
    webhook_url = getattr(settings, 'GOOGLE_DRIVE_WEBHOOK_URL', None) or os.getenv('GOOGLE_DRIVE_WEBHOOK_URL')
    if webhook_url:
        try:
            payload = {
                'file_name': filename,
                'file_data': base64.b64encode(file_bytes).decode('utf-8'),
                'mime_type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'folder_id': folder_id
            }
            res = requests.post(webhook_url, json=payload, timeout=40)
            if res.status_code == 200:
                try:
                    res_data = res.json()
                    return {
                        'success': True,
                        'method': 'webhook',
                        'file_id': res_data.get('file_id'),
                        'file_url': res_data.get('url', TARGET_DRIVE_FOLDER_URL),
                        'folder_url': TARGET_DRIVE_FOLDER_URL,
                        'message': f"Uploaded {filename} directly to Google Drive!"
                    }
                except Exception:
                    return {
                        'success': True,
                        'method': 'webhook',
                        'folder_url': TARGET_DRIVE_FOLDER_URL,
                        'message': f"Uploaded {filename} directly to Google Drive!"
                    }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'message': f"Webhook upload failed: {str(e)}"
            }

    # 2. Prefer OAuth user credentials when available (works for normal Drive folders)
    oauth_result = _upload_with_oauth_user(file_bytes, filename, folder_id)
    if oauth_result.get('success'):
        return oauth_result
    if oauth_result.get('error') in {'oauth_required', 'oauth_missing', 'oauth_failed'}:
        # Continue only if the OAuth path is unavailable; otherwise surface a clear OAuth message.
        pass

    # 3. Check for Google Service Account credentials file
    creds_candidates = [
        getattr(settings, 'GOOGLE_SERVICE_ACCOUNT_FILE', None),
        str(settings.BASE_DIR / 'candidaites-11716fdcdc8d.json'),
        str(settings.BASE_DIR / 'service_account.json'),
        str(settings.BASE_DIR / 'credentials.json'),
        str(settings.BASE_DIR / 'gdrive_credentials.json'),
    ]

    # Auto-detect any service account json in base dir
    try:
        for jf in glob.glob(str(settings.BASE_DIR / "*.json")):
            if jf not in creds_candidates:
                creds_candidates.append(jf)
    except Exception:
        pass

    for cred_path in creds_candidates:
        if cred_path and os.path.exists(cred_path):
            try:
                # Verify it is a valid service account json
                with open(cred_path, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    if data.get('type') != 'service_account':
                        continue

                from google.oauth2 import service_account

                creds = service_account.Credentials.from_service_account_file(
                    cred_path,
                    scopes=['https://www.googleapis.com/auth/drive']
                )
                file_obj = _create_drive_file(creds, file_bytes, filename, folder_id)

                return {
                    'success': True,
                    'method': 'service_account',
                    'file_id': file_obj.get('id'),
                    'file_name': file_obj.get('name'),
                    'file_url': file_obj.get('webViewLink', TARGET_DRIVE_FOLDER_URL),
                    'folder_url': TARGET_DRIVE_FOLDER_URL,
                    'message': f"Uploaded {filename} directly to Google Drive!"
                }
            except Exception as e:
                msg = str(e)
                if 'Service Accounts do not have storage quota' in msg or 'storageQuotaExceeded' in msg:
                    return {
                        'success': False,
                        'error': 'storage_quota_exceeded',
                        'message': (
                            'Google Drive service accounts cannot upload to normal My Drive folders because they do not have storage quota. '
                            'Use a Shared Drive or switch to OAuth user delegation.'
                        )
                    }
                return {
                    'success': False,
                    'error': str(e),
                    'message': f"Google Drive API error: {msg}"
                }

    return {
        'success': False,
        'error': 'no_credentials',
        'folder_url': TARGET_DRIVE_FOLDER_URL,
        'message': 'Google Drive authorization is needed to upload directly without downloading.'
    }
