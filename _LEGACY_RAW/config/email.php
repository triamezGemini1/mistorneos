<?php


return [
    'smtp' => [
        // Gmail SMTP con contraseña de aplicación (no la contraseña normal)
        'host' => 'smtp.gmail.com',
        'port' => 587,              // Usa 587 con STARTTLS
        'username' => 'viajacontrino@gmail.com',   // <-- cambia aquí
        'password' => 'gubwkfaiqnywullk',       // <-- contraseña de aplicación
        'encryption' => 'tls',      // tls para 587, ssl para 465
        'from_email' => 'viajacontrino@gmail.com', // mismo remitente para evitar rechazos
        'from_name' => 'Sistema de Inscripciones - Mistorneos'
    ],
    
    'fallback' => [
        'use_mail_function' => false, // evita usar mail() si falla SMTP
        'from_email' => 'noreply@mistorneos.com',
        'from_name' => 'Sistema de Inscripciones - Mistorneos'
    ],
    
    'debug' => [
        'enabled' => true,
        'log_file' => __DIR__ . '/../logs/email_debug.log'
    ]
];
?>















