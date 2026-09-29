import io
import os
import json
import base64
import requests
from django.conf import settings

TARGET_FOLDER_ID = "1DLJ68NVmD-j6zHhrDSLKouFvDO-9WW6K"
TARGET_DRIVE_FOLDER_URL = f"https://drive.google.com/drive/folders/{TARGET_FOLDER_ID}?usp=sharing"


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

    # 2. Check for Google Service Account credentials file
    creds_candidates = [
        getattr(settings, 'GOOGLE_SERVICE_ACCOUNT_FILE', None),
        str(settings.BASE_DIR / 'candidaites-11716fdcdc8d.json'),
        str(settings.BASE_DIR / 'service_account.json'),
        str(settings.BASE_DIR / 'credentials.json'),
        str(settings.BASE_DIR / 'gdrive_credentials.json'),
    ]

    # Auto-detect any service account json in base dir
    try:
        import glob
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
                from googleapiclient.discovery import build
                from googleapiclient.http import MediaIoBaseUpload

                creds = service_account.Credentials.from_service_account_file(
                    cred_path,
                    scopes=['https://www.googleapis.com/auth/drive']
                )
                service = build('drive', 'v3', credentials=creds)

                media = MediaIoBaseUpload(
                    io.BytesIO(file_bytes),
                    mimetype='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    resumable=True
                )
                file_metadata = {
                    'name': filename,
                    'parents': [folder_id]
                }
                file_obj = service.files().create(
                    body=file_metadata,
                    media_body=media,
                    fields='id, name, webViewLink'
                ).execute()

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
                return {
                    'success': False,
                    'error': str(e),
                    'message': f"Google Drive API error: {str(e)}"
                }

    return {
        'success': False,
        'error': 'no_credentials',
        'folder_url': TARGET_DRIVE_FOLDER_URL,
        'message': 'Google Drive authorization is needed to upload directly without downloading.'
    }
