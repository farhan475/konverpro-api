<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'roles' => [
        'superadmin',
        'admin',
        'akademik',
        'kaprodi',
    ],

    'role_dashboards' => [
        'superadmin' => 'superadmin.dashboard',
        'admin' => 'admin.dashboard',
        'akademik' => 'akademik.dashboard',
        'kaprodi' => 'kaprodi.dashboard',
    ],
];
