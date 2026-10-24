from datetime import datetime, timedelta, timezone
from pathlib import Path
from cryptography import x509
from cryptography.x509.oid import NameOID
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa

def certificate_files(folder):
    folder = Path(folder); folder.mkdir(parents=True, exist_ok=True)
    now = datetime.now(timezone.utc)
    root_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    name = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, 'SMF isolated test CA')])
    ca = (x509.CertificateBuilder().subject_name(name).issuer_name(name).public_key(root_key.public_key())
          .serial_number(x509.random_serial_number()).not_valid_before(now-timedelta(days=1)).not_valid_after(now+timedelta(days=2))
          .add_extension(x509.BasicConstraints(ca=True,path_length=0),critical=True).sign(root_key,hashes.SHA256()))
    (folder/'ca.pem').write_bytes(ca.public_bytes(serialization.Encoding.PEM))
    for kind in ['valid','expired']:
        key=rsa.generate_private_key(public_exponent=65537,key_size=2048)
        subject=x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,'localhost')])
        cert=(x509.CertificateBuilder().subject_name(subject).issuer_name(name).public_key(key.public_key())
              .serial_number(x509.random_serial_number()).not_valid_before(now-timedelta(days=2))
              .not_valid_after(now+timedelta(days=1) if kind=='valid' else now-timedelta(days=1))
              .add_extension(x509.SubjectAlternativeName([x509.DNSName('localhost')]),critical=False)
              .sign(root_key,hashes.SHA256()))
        (folder/(kind+'.pem')).write_bytes(cert.public_bytes(serialization.Encoding.PEM))
        (folder/(kind+'.key')).write_bytes(key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.TraditionalOpenSSL,serialization.NoEncryption()))
    return folder
