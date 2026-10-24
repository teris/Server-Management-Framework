import http.server, json, os, ssl, subprocess, tempfile, threading
from pathlib import Path
from php_runtime import php_command, fixture_environment
from tls_helpers import certificate_files

class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200);self.end_headers();self.wfile.write(b'isolated TLS fixture')
    def log_message(self,*args):pass

root=Path(__file__).resolve().parent
with tempfile.TemporaryDirectory(prefix='smf-tls-') as tmp:
    certs=certificate_files(tmp);servers=[]
    try:
        for kind in ['valid','expired']:
            server=http.server.HTTPServer(('127.0.0.1',0),Handler)
            context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.load_cert_chain(str(certs/(kind+'.pem')),str(certs/(kind+'.key')))
            server.socket=context.wrap_socket(server.socket,server_side=True)
            threading.Thread(target=server.serve_forever,daemon=True).start();servers.append(server)
        valid,expired=servers
        def probe(url,trusted):
            env=fixture_environment();env.pop('SMF_CA_FILE',None);env.pop('SMF_PROXMOX_CA_FILE',None)
            if trusted:env['SMF_CA_FILE']=str(certs/'ca.pem')
            result=subprocess.run(php_command()+[str(root/'tls_probe.php'),url],capture_output=True,text=True,env=env,check=True)
            return json.loads(result.stdout)
        tests={
            'configured private CA accepted':probe(f'https://localhost:{valid.server_port}/',True)['ok'],
            'unknown CA rejected':not probe(f'https://localhost:{valid.server_port}/',False)['ok'],
            'hostname mismatch rejected':not probe(f'https://127.0.0.1:{valid.server_port}/',True)['ok'],
            'expired certificate rejected':not probe(f'https://localhost:{expired.server_port}/',True)['ok'],
            'plaintext HTTP rejected':not probe(f'http://localhost:{valid.server_port}/',True)['ok'],
        }
        print(json.dumps({'checks':len(tests),'failed':[name for name,ok in tests.items() if not ok]}));assert all(tests.values()),tests
    finally:
        for server in servers:server.shutdown();server.server_close()
