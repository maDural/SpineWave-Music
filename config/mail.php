<?php
// Mail configuration for password resets.
//
// Modes:
//   'display'  — DEV ONLY. Shows the reset link on the page (no mail sent).
//   'log'      — Writes the reset link to a log file under logs/.
//   'mail'     — Uses PHP's mail() function (requires a configured SMTP in php.ini or sendmail).
//
// IMPORTANT: Set mode to 'mail' (and configure SMTP) before going live.
// Never leave 'display' enabled on a public server.

return [
    'mode' => 'display',

    // Used in the email body and logs.
    'from_email' => 'no-reply@spinwave.local',
    'from_name'  => 'SpinWave Music',
    'subject'    => 'Reset your SpinWave password',

    // Base URL used to build the reset link.
    // Auto-detected if left empty. Override for production, e.g. 'https://music.example.com'.
    'base_url' => '',

    // How long a reset link stays valid, in minutes.
    'expires_minutes' => 60,

    // Minimum seconds between two reset requests for the same email.
    'throttle_seconds' => 60,

    // Where 'log' mode writes the reset link (relative to project root).
    'log_file' => __DIR__ . '/../logs/password-resets.log',
];