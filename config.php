<?php
/**
 * خبررسانی منطقه آزاد بوشهر — پیکربندی
 * برای هاست اشتراکی/محلی مقادیر را ویرایش کنید.
 */
return [
    // دیتابیس (MySQL)
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'bushehr_news',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    // زبان‌های فعال (ترتیب مهم است؛ اولی زبان پیش‌فرض است)
    'languages'  => ['fa', 'en', 'ar'],
    'site'       => [
        'name'  => 'خبررسانی منطقه آزاد',
        'email' => 'info@bfzo.ir',
        'phone' => '077-0000-0000',
        'address' => 'بوشهر، منطقه آزاد تجاری–صنعتی بوشهر',
    ],
    'uploads_dir'   => __DIR__ . '/uploads',
    'uploads_url'   => 'uploads',
    'per_page'      => 12,
    'timezone'      => 'Asia/Tehran',
    'debug'         => false,
];
