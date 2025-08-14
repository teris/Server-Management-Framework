<?php
/**
 * Support Tickets Module Configuration
 */

return [
    'name' => 'Support Tickets',
    'key' => 'support-tickets',
    'version' => '1.0.0',
    'description' => 'Support Ticket Management System for Administrators',
    'author' => 'Server Management Team',
    'icon' => 'bi-headset',
    'path' => __DIR__,
    'permissions' => [
        'admin' => ['read', 'write', 'delete', 'manage'],
        'manager' => ['read', 'write'],
        'support' => ['read', 'write'],
        'user' => []
    ],
    'menu' => [
        'position' => 50,
        'parent' => null,
        'children' => []
    ],
    'settings' => [
        'tickets_per_page' => 20,
        'auto_assignment' => false,
        'notification_email' => '',
        'default_priority' => 'medium',
        'default_status' => 'open',
        'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'],
        'max_file_size' => 5242880, // 5MB
        'auto_close_days' => 30,
        'escalation_hours' => 24
    ],
    'hooks' => [
        'on_ticket_created' => [],
        'on_ticket_updated' => [],
        'on_ticket_closed' => [],
        'on_reply_added' => []
    ]
];
