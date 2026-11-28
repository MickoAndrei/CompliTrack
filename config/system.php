<?php

return [
    'path' => storage_path(env('COMPLITRACK_V2', false) ? 'app/system-v2' : 'app/system'),
    'backup_time' => '02:00',
    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
];
