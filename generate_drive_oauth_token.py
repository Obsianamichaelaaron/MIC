import os
import glob
import json
from google_auth_oauthlib.flow import InstalledAppFlow

def main():
    secrets = glob.glob(os.path.join(os.getcwd(), 'client_secret*.json'))
    if not secrets:
        print("No client_secret*.json found in current directory.")
        return
    client_secret = secrets[0]
    scopes = ['https://www.googleapis.com/auth/drive']
    token_file = os.path.join(os.getcwd(), 'token.json')
    flow = InstalledAppFlow.from_client_secrets_file(client_secret, scopes)
    creds = flow.run_local_server(port=0)
    with open(token_file, 'w', encoding='utf-8') as f:
        json.dump(json.loads(creds.to_json()), f, indent=2)
    print(f"Token saved to {token_file}")

if __name__ == '__main__':
    main()
