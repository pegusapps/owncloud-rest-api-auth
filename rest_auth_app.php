<?php

namespace OCA\rest_auth_app;

use GuzzleHttp\Client as GuzzleClient;

class RestAuthApp extends \OC_User_Backend implements \OCP\UserInterface
{

    private $client;
    private $userManager;
    private $groupManager;

    public function __construct($userManager, $groupManager)
    {
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;

        $this->client = new GuzzleClient([
            'base_url' => \OC::$server->getAppConfig()->getValue('rest_auth_app', 'rest_auth_api_url', OC_USER_BACKEND_REST_AUTH_API_URL)
        ]);
    }

    public function implementsAction($actions)
    {
        return (bool)((\OC_User_Backend::CHECK_PASSWORD
                | 0 //\OC_User_Backend::SET_PASSWORD
            ) & $actions);
    }

    private function userMatchesFilter($user)
    {
        return (strripos($user, $this->user_search) !== false);
    }

    public function deleteUser($_uid)
    {
        // Can't delete user
        \OCP\Util::writeLog('OC_rest_auth_app', 'Not possible to delete local users from web frontend using rest auth user backend',
            \OCP\Util::ERROR);

        return false;
    }

    public function checkPassword($uid, $password)
    {
        \OCP\Util::writeLog('OC_rest_auth_app', 'Checking password: ' . $uid . ' -> ' . $password, \OCP\Util::DEBUG);

        $response = $this->client->get('call/customer', [
            'query' => [
                'call'       => 'authenticate',
                'api_key'    => \OC::$server->getAppConfig()->getValue('rest_auth_app', 'rest_auth_api_access_key',
                    OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY),
                'api_output' => 'json',
                'username'   => $uid,
                'password'   => $password
            ]
        ]);
        \OCP\Util::writeLog('OC_rest_auth_app', "Response from REST API: " . $response->getBody(), \OCP\Util::DEBUG);

        if (((string)$response->getBody()) === "true") {

            $this->createOrUpdateUserInOwnCloud($uid, $password);

            return $uid;
        } else {
            \OCP\Util::writeLog('OC_rest_auth_app', "User '$uid' does not exist or typed wrong password.", \OCP\Util::ERROR);

            return false;
        }
    }

    public function setPassword($uid, $password)
    {
        ## Can't change the password whatever happened
        ## but this is the only way to enable mail address change
        ## in the preferences
        return false;
    }

    public function userExists($uid)
    {
        \OCP\Util::writeLog('OC_rest_auth_app', 'Checking if user exists: ' . $uid, \OCP\Util::DEBUG);

        if (empty($uid)) {
            \OCP\Util::writeLog('OC_rest_auth_app', '$uid was empty, returning that user does not exist.', \OCP\Util::WARN);

            return false;
        }

        try {
            $response = $this->client->get('call/customer', [
                'query' => [
                    'call'       => 'getInfo',
                    'api_key'    => \OC::$server->getAppConfig()->getValue('rest_auth_app', 'rest_auth_api_access_key',
                        OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY),
                    'api_output' => 'json',
                    'username'   => $uid
                ]
            ]);
            \OCP\Util::writeLog('OC_rest_auth_app', "Response from REST API: " . $response->getBody(), \OCP\Util::DEBUG);

            return true;
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() == "404") {
                \OCP\Util::writeLog('OC_rest_auth_app', "User with username '$uid' does not exist.", \OCP\Util::ERROR);
            } else {
                \OCP\Util::writeLog('OC_rest_auth_app', "Error checking if user exists for username '$uid': " . $e,
                    \OCP\Util::ERROR);
            }

            return false;
        }
    }

    public function getUsers($search = '', $limit = 10, $offset = 10)
    {
        return [];
    }

    /**
     * @param $uid
     * @param $password
     */
    private function createOrUpdateUserInOwnCloud($uid, $password)
    {
        // Need to find the owncloud "internal" user backend first

        $dbBackend = null;

        $backends = $this->userManager->getBackends();

        \OCP\Util::writeLog('OC_rest_auth_app', "Backends: " . count($backends), \OCP\Util::DEBUG);

        foreach ($backends as $backend) {
            \OCP\Util::writeLog('OC_rest_auth_app', "Backend: " . get_class($backend), \OCP\Util::DEBUG);
            if (get_class($backend) === "OC\\User\\Database") {
                \OCP\Util::writeLog('OC_rest_auth_app', "Found db backend!", \OCP\Util::DEBUG);
                $dbBackend = $backend;
                break;
            }
        }
        \OCP\Util::writeLog('OC_rest_auth_app', "result backend: " . get_class($dbBackend), \OCP\Util::DEBUG);

        if ($dbBackend && $dbBackend->userExists($uid) === false) {
            \OCP\Util::writeLog('OC_rest_auth_app', "Creating a new account for user: " . $uid, \OCP\Util::INFO);
            $dbBackend->createUser($uid, $password);
        }

        $this->updateGroupsOfUser($uid);
    }

    private function updateGroupsOfUser($uid)
    {
        // Find out in what groups the user needs to be added

        try {
            $user = $this->userManager->get($uid);
            $oldGroupsOfUser = $this->groupManager->getUserGroups($user);

            \OCP\Util::writeLog('OC_rest_auth_app', "old groups of user" . $user . ": " . print_r($oldGroupsOfUser, true), \OCP\Util::DEBUG);

            $response = $this->client->get('call/customer', [
                'query' => [
                    'call'       => 'getInfo',
                    'api_key'    => \OC::$server->getAppConfig()->getValue('rest_auth_app', 'rest_auth_api_access_key',
                        OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY),
                    'api_output' => 'json',
                    'username'   => $uid
                ]
            ]);
            \OCP\Util::writeLog('OC_rest_auth_app', "customer/getInfo Response from REST API: " . $response->getBody(), \OCP\Util::DEBUG);

            $tagIds = json_decode($response->getBody(), true)['tag_ids'];
            $currentGroupsOfUser = [];
            foreach ($tagIds as $tagId) {
                \OCP\Util::writeLog('OC_rest_auth_app', "Tag Id: " . $tagId, \OCP\Util::DEBUG);

                $variableId = 'tag-' . $tagId;
                $owncloudGroupName = \OCP\Config::getAppValue('rest_auth_app', $variableId, "");
                if (!empty($owncloudGroupName)) {

                    $group = $this->groupManager->get($owncloudGroupName);
                    if (!$group->inGroup($user)) {
                        \OCP\Util::writeLog('OC_rest_auth_app', "User " . $user->getDisplayName() . " is not yet in group " . $group->getGID() . ". Adding now...", \OCP\Util::DEBUG);
                        $group->addUser($user);
                    } else {
                        \OCP\Util::writeLog('OC_rest_auth_app', "User " . $user->getDisplayName() . " is already in group " . $group->getGID() . ".", \OCP\Util::DEBUG);
                    }

                    array_push($currentGroupsOfUser, $group);
                } else {
                    \OCP\Util::writeLog('OC_rest_auth_app', "There is no mapping for tag " . $tagId . ". User will not be added to a group for that tag.", \OCP\Util::DEBUG);
                }
            }

            foreach ($oldGroupsOfUser as $oldGroup) {
                \OCP\Util::writeLog('OC_rest_auth_app', "old group: " . $oldGroup, \OCP\Util::DEBUG);
                if (!in_array($oldGroup, $currentGroupsOfUser)) {
                    \OCP\Util::writeLog('OC_rest_auth_app', "user no longer in group: " . $oldGroup, \OCP\Util::DEBUG);
                    $oldGroup->removeUser($user);
                }
            }

        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() == "404") {
                \OCP\Util::writeLog('OC_rest_auth_app', "User with username '$uid' does not exist.", \OCP\Util::ERROR);
            } else {
                \OCP\Util::writeLog('OC_rest_auth_app', "Error checking groups for username '$uid': " . $e, \OCP\Util::ERROR);
            }
        }
    }
}

?>