<?php

namespace OCA\rest_auth_app\AppInfo;

use \OCA\rest_auth_app\Controller\SettingsController;
use \OCA\rest_auth_app\RestAuthApp;
use \OCP\AppFramework\App;
use OCP\IUserManager;

class Application extends App
{
    public function __construct(array $urlParams = [])
    {
        parent::__construct('rest_auth_app', $urlParams);

        $container = $this->getContainer();
        $server = $container->getServer();

        $container->registerService('SettingsController', function ($c) use ($server) {
            return new SettingsController(
                $c->query('AppName'),
                $c->query('Request'),
                $server->getConfig(),
                $server->getGroupManager()
            );
        });

        $container->registerService('RestAuthApp', function ($c) use ($server) {
            return new RestAuthApp(
                $c->query('AppName'),
                $server->getUserManager(),
                $server->getGroupManager(),
                $server->getConfig()
            );
        });

        $container->registerService('UserManager', function ($c) use ($server) {
            return $server->getUserManager();
        });
        $container->registerService('GroupManager', function ($c) use ($server) {
            return $server->getGroupManager();
        });

//        // The Rest Auth app as extra backend for user management
        \OC_User::useBackend($container->query('RestAuthApp'));

        $container->query(IUserManager::class)->registerBackend($container->query(RestAuthApp::class));
    }
}