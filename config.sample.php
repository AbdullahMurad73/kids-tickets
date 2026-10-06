<?php
// مثال فقط — معالج التثبيت (install/) ينشئ config.php تلقائياً.
// للإعداد اليدوي: انسخ هذا الملف باسم config.php وعدّل القيم.
return [
    'base_url' => 'https://example.com',
    'timezone' => 'Asia/Riyadh',
    'debug'    => false,
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u123456789_tickets',
        'user' => 'u123456789_user',
        'pass' => '',
    ],
    'twilio' => [
        'account_sid'        => '',
        'auth_token'         => '',
        'verify_service_sid' => '',
        'otp_channel'        => 'sms',
        'whatsapp_from'      => '',
        'sms_from'           => '',
    ],
];
