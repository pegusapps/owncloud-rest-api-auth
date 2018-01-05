<?php
namespace OCA\rest_auth_app\AppInfo;

use \OCA\rest_auth_app\Controller\SettingsController;
use \OCP\AppFramework\App;

class Application extends App {
    public function __construct(array $urlParams=array()){
        parent::__construct('rest_auth_app', $urlParams);

        $container = $this->getContainer();
        $server = $container->getServer();

        $container->registerService('SettingsController', function($c)  use ($server){
            return new SettingsController(
                $c->query('AppName'),
                $c->query('Request'),
                $server->getConfig(),
                $server->getGroupManager()
            );
        });
    }
}