import json,os,re,shutil,socket,socketserver,ssl,subprocess,tempfile,threading,time,urllib.request
from pathlib import Path
from php_runtime import php_command, fixture_environment
from tls_helpers import certificate_files
ROOT=Path(__file__).resolve().parents[1]
class SMTPHandler(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 fixture SMTP\r\n');message=[];data=False
        while True:
            line=self.rfile.readline(1048576)
            if not line:return
            if data:
                if line==b'.\r\n':self.server.messages.append(b''.join(message).decode());message=[];data=False;self.wfile.write(b'250 accepted\r\n')
                else:message.append(line)
            elif line.upper().startswith(b'EHLO'):self.wfile.write(b'250 fixture\r\n')
            elif line.upper().startswith((b'MAIL FROM:',b'RCPT TO:')):self.wfile.write(b'250 ok\r\n')
            elif line.upper().startswith(b'DATA'):data=True;self.wfile.write(b'354 continue\r\n')
            elif line.upper().startswith(b'QUIT'):self.wfile.write(b'221 bye\r\n');return
            else:self.wfile.write(b'500 unsupported\r\n')
class SMTPServer(socketserver.ThreadingTCPServer):allow_reuse_address=True;daemon_threads=True
class HTTPFixture:
    def __init__(self,shop_routes=False,config_storage=False):self.shop_routes=shop_routes;self.config_storage=config_storage
    def __enter__(self):
        self.temporary=tempfile.TemporaryDirectory(prefix='smf-pages-');self.tmp=Path(self.temporary.name);self.app=self.tmp/'app'
        shutil.copytree(ROOT,self.app,ignore=shutil.ignore_patterns('.git','__pycache__','config.inc.php','node_modules','.codex','screenshoots'))
        certs=certificate_files(self.tmp/'certs');self.smtp=SMTPServer(('127.0.0.1',0),SMTPHandler);self.smtp.messages=[]
        context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.load_cert_chain(str(certs/'valid.pem'),str(certs/'valid.key'));self.smtp.socket=context.wrap_socket(self.smtp.socket,server_side=True);threading.Thread(target=self.smtp.serve_forever,daemon=True).start()
        config=(ROOT/'config/config.example.php').read_text()
        for key,value in {'DB_TYPE':"'sqlite'",'DB_SQLITE_USEING':'true','DB_SQLITE_PATH':"'"+str(self.tmp/'application.sqlite')+"'",'FRONTPANEL_SITE_URL':"'https://portal.example.invalid'"}.items():config=re.sub(r'\bconst '+key+r'\s*=\s*[^;]+;',lambda _: 'const '+key+' = '+value+';',config)
        if self.config_storage:config=re.sub(r'const PRIVATE_STORAGE_DIR\s*=\s*[^;]+;',lambda _: "const PRIVATE_STORAGE_DIR = '"+str(self.tmp/'private')+"';",config)
        (self.tmp/'config.php').write_text(config)
        self.env=fixture_environment(SMF_CONFIG_FILE=str(self.tmp/'config.php'),SMF_PRIVATE_DIR=str(self.tmp/'private'),SMF_SMTP_HOST='localhost',SMF_SMTP_PORT=str(self.smtp.server_address[1]),SMF_SMTP_CA_FILE=str(certs/'ca.pem'))
        if self.config_storage:self.env.pop('SMF_PRIVATE_DIR',None)
        installed=subprocess.run(php_command()+[str(self.app/'bin/install.php'),'--username=test-admin','--email=admin@example.invalid'],input='Admin fixture password 123\n',text=True,env=self.env,capture_output=True)
        if installed.returncode:raise RuntimeError(installed.stderr)
        self.install_output=installed.stdout
        sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();self.base=f'http://127.0.0.1:{port}';self.log=(self.tmp/'server.log').open('w+')
        self.process=subprocess.Popen(php_command()+['-d','display_errors=0','-S',f'127.0.0.1:{port}','-t',str(self.app)]+([str(self.app/'tests/shop_router.php')] if self.shop_routes else []),cwd=self.app,env=self.env,stdout=self.log,stderr=self.log)
        for _ in range(100):
            try:urllib.request.urlopen(self.base+'/public/login.php',timeout=1).read();break
            except Exception:time.sleep(.05)
        else:raise RuntimeError('Fixture HTTP server did not start')
        return self
    def __exit__(self,kind,value,traceback):
        self.process.terminate();self.process.wait(timeout=5);self.log.close();self.smtp.shutdown();self.smtp.server_close()
        if kind:print((self.tmp/'server.log').read_text()[-5000:])
        self.temporary.cleanup()
