<?php

require_once('rest_auth_app/rest_auth_app.php');

OCP\App::registerAdmin('rest_auth_app', 'settings');


define('OC_USER_BACKEND_REST_AUTH_API_URL', '');
define('OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY', '');
new Application();

\OC::$server->getNavigationManager()->add(function () {
    return [
        // The string under which your app will be referenced in owncloud
        'id'    => 'rest_auth_app_settings',

        // The sorting weight for the navigation.
        // The higher the number, the higher will it be listed in the navigation
        'order' => 1,

        // The route that will be shown on startup
        'href'  => \OCP\Util::linkTo('rest_auth_app', 'settings.php'),

        // The application's title, used in the navigation & the settings page of your app
        'name'  => 'RESTAUTHAPP',
    ];
});

use OCA\rest_auth_app\RestAuthApp;
use OCP\AppFramework\App;

class Application extends App
{

    public function __construct(array $urlParams = [])
    {
        parent::__construct('myapp', $urlParams);

        $container = $this->getContainer();

        $container->registerService('RestAuthApp', function ($c) {
            return new RestAuthApp(
                $c->query('UserManager'),
                $c->query('GroupManager')
            );
        });

        $container->registerService('UserManager', function ($c) {
            return $c->query('ServerContainer')->getUserManager();
        });
        $container->registerService('GroupManager', function ($c) {
            return $c->query('ServerContainer')->getGroupManager();
        });

        // The Rest Auth app as extra backend for user management
        OC_User::useBackend($container->query('RestAuthApp'));
    }
}

?>
