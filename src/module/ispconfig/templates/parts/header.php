<?php
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
