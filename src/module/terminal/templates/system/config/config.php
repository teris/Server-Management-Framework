<?php
/**
 * Terminal Module Konfiguration
 */

return [
    "vnc" => [
        "enabled" => true,
        "default_port" => 5900,
        "websocket_path" => "/websockify",
        "timeout" => 30
    ],
    "ssh" => [
        "enabled" => true,
        "default_port" => 22,
        "websocket_path" => "/ssh-proxy",
        "timeout" => 30
    ],
    "security" => [
        "max_connections" => 5,
        "session_timeout" => 3600,
        "require_authentication" => true
    ]
];
?>