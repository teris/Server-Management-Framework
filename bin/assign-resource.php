<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/sys.conf.php';
require_once dirname(__DIR__) . '/framework.php';
require_once dirname(__DIR__) . '/src/core/ResourceAccess.php';
$options = getopt('', ['realm:', 'subject:', 'module:', 'type:', 'external-id:', 'node:', 'service:', 'instance-id:', 'group-id:', 'write']);
$realm = $options['realm'] ?? ''; $subject = (int) ($options['subject'] ?? 0); $module = $options['module'] ?? '';
if (!in_array($realm, ['backend', 'customer'], true) || $subject < 1 || !isset(ResourceAccess::TYPES[$module])
    || !in_array($options['type'] ?? '', ResourceAccess::TYPES[$module][1], true)) {
    fwrite(STDERR, "Specify --realm=customer|backend --subject=ID --module=proxmox|... --type=qemu|... --external-id=ID [--node=NODE] [--write].\n"); exit(1);
}
$db = HttpSecurity::connection();
$stmt = $db->prepare($realm === 'customer' ? "SELECT id FROM customers WHERE id=? AND status='active'" : "SELECT id FROM users WHERE id=? AND active='y'");
$stmt->execute([$subject]);
if (!$stmt->fetchColumn()) { fwrite(STDERR, "Active owner not found.\n"); exit(1); }
$id = bin2hex(random_bytes(16)); $provider = ResourceAccess::TYPES[$module][0];
$db->beginTransaction();
try {
    $stmt = $db->prepare('INSERT INTO security_resource_assignments (id,subject_realm,subject_id,provider,connection_id,resource_type,external_id,metadata,can_write) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$id,$realm,$subject,$provider,ResourceAccess::connectionId($provider),$options['type'],$options['external-id'] ?? '',json_encode(['node'=>$options['node'] ?? '', 'service'=>$options['service'] ?? '', 'instance_id'=>$options['instance-id'] ?? '', 'group_id'=>(int)($options['group-id'] ?? 0)]),isset($options['write'])?1:0]);
    // Validate the binding and allowed identifier shape without contacting any provider.
    ResourceAccess::execute(['realm'=>'backend','id'=>0,'role'=>'admin'], $module, $id, 'view', function () use ($options) { return ['vmgenid'=>$options['instance-id'] ?? '', 'description'=>'SMF-RESOURCE:'.($options['instance-id'] ?? ''), 'sys_groupid'=>(int)($options['group-id'] ?? 0)]; });
    $db->commit(); echo "Resource assignment created: $id\n";
} catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); fwrite(STDERR, 'Assignment failed: '.get_class($e)."\n"); exit(1); }
