import concurrent.futures, json, os, sqlite3, subprocess, tempfile
from pathlib import Path
from php_runtime import php_command, fixture_environment
ROOT=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='smf-concurrency-') as directory:
    tmp=Path(directory);env=fixture_environment(SMF_PRIVATE_DIR=str(tmp/'private'))
    limit=f"require {json.dumps(str(ROOT/'src/core/Security.php'))};try{{HttpSecurity::rateLimit('concurrent','one-client',3,60);echo 'allowed';}}catch(SecurityViolation $e){{echo 'limited';}}"
    def worker(code):
        result=subprocess.run(php_command()+['-r',code],env=env,text=True,capture_output=True)
        if result.returncode:raise RuntimeError(result.stdout+result.stderr)
        return result.stdout.strip()
    with concurrent.futures.ThreadPoolExecutor(max_workers=12) as pool:results=list(pool.map(worker,[limit]*18))
    assert results.count('allowed')==3,results
    db=sqlite3.connect(tmp/'tokens.sqlite');db.executescript("PRAGMA journal_mode=WAL; CREATE TABLE customers (id INTEGER PRIMARY KEY,password_hash TEXT,status TEXT); INSERT INTO customers VALUES (1,'test-hash','active'); CREATE TABLE security_remember_tokens (selector TEXT PRIMARY KEY,customer_id INTEGER,token_hash TEXT,credential TEXT,expires_at INTEGER);");db.close()
    bootstrap=f"require {json.dumps(str(ROOT/'src/core/Identity.php'))};$db=new PDO('sqlite:'+{json.dumps(str(tmp/'tokens.sqlite'))},null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);HttpSecurity::useConnection($db);"
    # PHP concatenation, rather than expression interpolation of a file path.
    bootstrap=bootstrap.replace("'sqlite:'+","'sqlite:'.")
    token=worker(bootstrap+"echo RememberTokens::issue(['id'=>1,'password_hash'=>'test-hash']);")
    consume=bootstrap+f"echo RememberTokens::consume({json.dumps(token)})===null?'rejected':'accepted';"
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:tokens=list(pool.map(worker,[consume]*12))
    assert tokens.count('accepted')==1,tokens
    print(json.dumps({'checks':2,'failed':0,'parallel_requests':len(results)+len(tokens)}))
