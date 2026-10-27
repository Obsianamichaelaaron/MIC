import base64
import hashlib

from cryptography.fernet import Fernet
from django.conf import settings
from django.contrib.sessions.serializers import JSONSerializer


class EncryptedJSONSerializer:
    @staticmethod
    def _fernet():
        secret = settings.SECRET_KEY.encode('utf-8')
        key = base64.urlsafe_b64encode(
            hashlib.sha256(b'vercel-session-encryption:' + secret).digest()
        )
        return Fernet(key)

    @classmethod
    def dumps(cls, obj):
        return cls._fernet().encrypt(JSONSerializer().dumps(obj))

    @classmethod
    def loads(cls, data):
        decrypted = cls._fernet().decrypt(data)
        return JSONSerializer().loads(decrypted)
