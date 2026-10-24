<?php
require_once __DIR__ . '/sys.conf.php';
require_once dirname(__DIR__) . '/framework.php';
require_once __DIR__ . '/auth_handler.php';
require_once __DIR__ . '/core/ResourceAccess.php';
requireLogin();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'logout') { Identity::logout(); header('Location: login.php', true, 303); exit; }
$identity = Identity::requireIdentity('backend');
$resources = ResourceAccess::assignments($identity);
?><!doctype html><html lang="de"><head><meta charset="utf-8"><title>Meine Ressourcen</title>
<meta name="csrf-token" content="<?= HttpSecurity::csrfToken() ?>"><script src="assets/security.js"></script>
</head><body><h1>Meine Ressourcen</h1>
<p><a href="password_change.php">Passwort ändern</a></p>
<?php if (!$resources): ?><p>Es sind noch keine Ressourcen zugeordnet.</p><?php else: ?><ul>
<?php foreach ($resources as $resource): ?><li><?= htmlspecialchars($resource['resource_type'] . ': ' . $resource['external_id'], ENT_QUOTES, 'UTF-8') ?></li><?php endforeach ?>
</ul><?php endif ?>
<form method="post"><?= HttpSecurity::csrfField() ?><input type="hidden" name="action" value="logout"><button>Abmelden</button></form>
</body></html>
