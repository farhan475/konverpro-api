<?php

return [
    'roles' => [
        'superadmin',
        'admin_pt',
        'staff',
        'akademik',
        'kaprodi',
    ],

    'role_dashboards' => [
        'superadmin' => 'superadmin.dashboard',
        'admin_pt' => 'admin-pt.dashboard',
        'staff' => 'staff.dashboard',
        'akademik' => 'akademik.dashboard',
        'kaprodi' => 'kaprodi.dashboard',
    ],

    'api_allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('API_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000')),
    )),
];
