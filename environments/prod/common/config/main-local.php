<?php

return [
    'components' => [
        // 'db' => [
        //     'class' => \yii\db\Connection::class,
        //     'dsn' => 'mysql:host=localhost;dbname=yii2advanced',
        //     'username' => 'root',
        //     'password' => '',
        //     'charset' => 'utf8',
        // ],
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'sqlite:/shareVolume/web/data/fuerp-api-prod/database.db',
            'charset' => 'utf8',
            'on afterOpen' => function ($event) {
                $db = $event->sender;
                $db->createCommand('PRAGMA foreign_keys = ON')->execute();
                $db->createCommand('PRAGMA journal_mode = WAL')->execute();
                $db->createCommand('PRAGMA synchronous = NORMAL')->execute();
            },
            'attributes' => [
                // use a smaller connection timeout
                // PDO::ATTR_TIMEOUT => 10,
            ],
        ],
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,

            // 可选但推荐
            'retries' => 1,
            'retryInterval' => 100,
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@common/mail',
        ],
    ],
];
