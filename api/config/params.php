<?php

return [
    'adminEmail' => 'admin@example.com',
    'apiName' => 'Fuerp API',
    'cors' => [
        'origins' => [
            'https://www.fuerp.com',
            'https://www.fuerp.net',
            'https://www.fuerp.cn',
            'http://www.fuerp.com.cn',
        ],
        'headers' => [
            'Content-Type',
            'Authorization',
            'X-Signature',
            'X-Timestamp',
            'X-Nonce',
        ],
    ],
];
