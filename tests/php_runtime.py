import json, os, shlex, shutil

def php_command():
    binary = os.environ.get('PHP_BIN') or shutil.which('php')
    if not binary:
        raise RuntimeError('Set PHP_BIN to a PHP executable with pdo_sqlite, mbstring, simplexml, ctype and curl')
    return [binary] + shlex.split(os.environ.get('PHP_ARGS', ''))


def fixture_environment(**overrides):
    allowed = ('SMF_TEST_', 'SMF_BROWSER_', 'SMF_PLAYWRIGHT_', 'SMF_MYSQL_', 'SMF_PG_')
    result = {key:value for key,value in os.environ.items() if not key.startswith('SMF_') or key.startswith(allowed)}
    result.update(overrides)
    return result
