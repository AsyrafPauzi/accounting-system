<?php

$s3Connection = [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
    'report' => false,
];

$usesS3ForUploads = env('FILESYSTEM_PUBLIC_DRIVER', 'local') === 's3';

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Tenant uploads: receipts, invoice PDFs, branding (Storage::disk('public')).
         * Production: FILESYSTEM_PUBLIC_DRIVER=s3 + AWS_BUCKET on ECS task role.
         * Alternative: local driver + EFS mounted on storage/.
         */
        'public' => $usesS3ForUploads
            ? array_merge($s3Connection, ['visibility' => 'private'])
            : [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ],

        // Generic S3 disk (optional; app code uses "public" for tenant uploads).
        's3' => $s3Connection,

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
