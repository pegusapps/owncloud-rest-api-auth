<?php


require_once('apps/rest_auth_app/rest_auth_app.php');

OCP\App::registerAdmin('rest_auth_app','settings');

define('OC_USER_BACKEND_REST_AUTH_API_URL', '');
define('OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY', '');



new Application();

// add settings page to navigation
$entry = array(
	'id' => 'rest_auth_app_settings',
	'order' => 1,
	'href' => \OCP\Util::linkTo('rest_auth_app', 'settings.php'),
	'name' => 'RESTAUTHAPP'
);

use \OCP\AppFramework\App;
use \OCA\rest_auth_app\RestAuthApp;

class Application extends App {

    public function __construct(array $urlParams=array()){
        parent::__construct('myapp', $urlParams);

        $container = $this->getContainer();

        /**
         * Controllers
         */
        $container->registerService('RestAuthApp', function($c) {
            return new RestAuthApp(
                $c->query('UserManager'),
                $c->query('GroupManager')
            );
        });

        $container->registerService('UserManager', function($c) {
            return $c->query('ServerContainer')->getUserManager();
        });
        $container->registerService('GroupManager', function($c) {
            return $c->query('ServerContainer')->getGroupManager();
        });

        // The Rest Auth app as extra backend for user management
        OC_User::useBackend($container->query('RestAuthApp'));
    }
}
?>
