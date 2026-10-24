<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

/**
 * ISPConfig Module Header
 * Enthält die Tab-Navigation und den Hauptheader
 */
?>

<div class="ispconfig-module">
    <!-- Tab-Navigation -->
    <div class="ispconfig-tabs">
        <button class="tab-button active" onclick="switchTab('websites', event)">
            🌐 <?= t('websites') ?>
        </button>
        <button class="tab-button" onclick="switchTab('users', event)">
            👥 <?= t('users_overview') ?>
        </button>
        <button class="tab-button" onclick="switchTab('domains', event)">
            🌐 <?= t('domain_management') ?>
        </button>
    </div>
