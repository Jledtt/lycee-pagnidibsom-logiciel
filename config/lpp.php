<?php

return [
    'admin_password' => env('LPP_ADMIN_PASSWORD'),

    'backup' => [
        'export_path' => env('LPP_BACKUP_PATH'),
        'keep_days' => (int) env('LPP_BACKUP_KEEP_DAYS', 14),
        'mysqldump_path' => env('LPP_MYSQLDUMP_PATH'),
        'pg_dump_path' => env('LPP_PGDUMP_PATH'),
    ],
];
