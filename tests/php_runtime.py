import json, os, shlex, shutil

def php_command():
    binary = os.environ.get('PHP_BIN') or shutil.which('php')
    if not binary:
        raise RuntimeError('Set PHP_BIN to a PHP executable with pdo_sqlite, mbstring, simplexml, ctype and curl')
    return [binary] + shlex.split(os.environ.get('PHP_ARGS', ''))
