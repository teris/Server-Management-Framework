<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }
?>
<!-- Proxmox Module JavaScript - Modular Structure -->
<script src="module/proxmox/templates/assets/proxmox-core.js"></script>
<script src="module/proxmox/templates/assets/proxmox-node-selection.js"></script>
<script src="module/proxmox/templates/assets/proxmox-server-list.js"></script>
<script src="module/proxmox/templates/assets/proxmox-server-management.js"></script>
<script src="module/proxmox/templates/assets/proxmox-vm-creation.js"></script>
<script src="module/proxmox/templates/assets/proxmox-extended-features.js"></script>
<script src="module/proxmox/templates/assets/proxmox-confirmation.js"></script>
<script src="module/proxmox/templates/assets/proxmox-init.js"></script>