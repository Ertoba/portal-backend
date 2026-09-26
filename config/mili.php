<?php

return [
    'environment' => env('MILI_ENV', 'production'),

    'features' => [
        'admin_panel'     => true,
        'vendor_app'      => true,
        'deliveryman_app' => true,
        'customer_app'    => true,
        'react_web'       => true,
        'taxi'            => true,
    ],
];
