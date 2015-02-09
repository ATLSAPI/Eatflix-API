<?php
require_once __DIR__.'/../vendor/autoload.php';
$host ='smtp.gmail.com';
$username ='eatflix@gmail.com';
$password = 'eatflix2014';
$port = 465;
$app = new Silex\Application();
$app->register(new \Silex\Provider\DoctrineServiceProvider(),['db.options' => ['driver' => 'pdo_sqlite', 'path' => __DIR__.'/../data.db']]);
$app->register(new \Silex\Provider\ValidatorServiceProvider());
$app->register(new \Silex\Provider\SecurityServiceProvider());
$app->register(new \SimpleUser\UserServiceProvider());
$app->register(new \Silex\Provider\SwiftmailerServiceProvider());
$app['security.firewalls'] = [
    'secured_area' => [
        'pattern' => '^.*$',
        'anonymous' => true,
        'http' => true,
        'users' => $app->share(function($app){ return $app['user.manager']; })
    ]
];
$app->register(new Silex\Provider\SwiftmailerServiceProvider(), array(
    'swiftmailer.options' => array(
        'host' => $host,
        'port' => $port,
        'username' => $username,
        'password' => $password,
        'encryption' => 'ssl',
        'auth_mode' => 'login')
));
return $app;