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
            'dsn' => 'sqlite:/shareVolume/data/fuerp-api-dev/database.db',
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
            // send all mails to a file by default.
            'useFileTransport' => true,
            // You have to set
            //
            // 'useFileTransport' => false,
            //
            // and configure a transport for the mailer to send real emails.
            //
            // SMTP server example:
            //    'transport' => [
            //        'scheme' => 'smtps',
            //        'host' => '',
            //        'username' => '',
            //        'password' => '',
            //        'port' => 465,
            //        'dsn' => 'native://default',
            //    ],
            //
            // DSN example:
            //    'transport' => [
            //        'dsn' => 'smtp://user:pass@smtp.example.com:25',
            //    ],
            //
            // See: https://symfony.com/doc/current/mailer.html#using-built-in-transports
            // Or if you use a 3rd party service, see:
            // https://symfony.com/doc/current/mailer.html#using-a-3rd-party-transport
        ],
    ],
];
