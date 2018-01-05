<?php

namespace OCA\rest_auth_app\AppInfo;

$application = new Application();
$application->registerRoutes($this, [
    'routes' => [
        [
            'name' => 'settings#post',
            'url'  => '/',
            'verb' => 'POST'
        ],
    ]
]);