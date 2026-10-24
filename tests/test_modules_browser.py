import json,os,subprocess
from pathlib import Path
from http_fixture import HTTPFixture
from php_runtime import php_command
ROOT=Path(__file__).resolve().parents[1]
with HTTPFixture(shop_routes=True)as f:
 code='''require 'framework.php';require 'src/sys.conf.php';$m=SMF\\Core\\Kernel::boot()->modules();foreach(['shop.storage','shop.catalog','shop.portal','shop.orders','shop.payments','shop.provisioning','shop.billing','shop.contracts','shop.communications','shop.connections','provider.ogp','provider.ispconfig','provider.proxmox','product.gameserver','product.webhosting','product.vps']as$id){$m->install($id);$m->enable($id);}SMF\\Core\\ProviderAccounts::bootstrapShop();query("INSERT INTO customers(first_name,last_name,email,password_hash,status) VALUES (?,?,?,?,'active')",['Module','Customer','module-customer@example.invalid',password_hash('Module customer fixture 123',PASSWORD_DEFAULT)]);'''
 r=subprocess.run(php_command()+['-r',code],cwd=f.app,env=f.env,text=True,capture_output=True)
 if r.returncode:raise RuntimeError(r.stderr)
 options={'url':f.base,'module':os.environ['SMF_PLAYWRIGHT_MODULE'],'browser':os.environ.get('SMF_BROWSER_BIN')}
 r=subprocess.run([os.environ.get('NODE_BIN','node'),'tests/modules_browser.cjs'],cwd=ROOT,input=json.dumps(options),text=True,capture_output=True)
 if r.returncode:raise RuntimeError(r.stderr)
 print(r.stdout.strip())
