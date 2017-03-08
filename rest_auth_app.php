<?php

namespace OCA\rest_auth_app;

use GuzzleHttp\Client as GuzzleClient;

class RestAuthApp extends Base implements \OCP\UserInterface
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
        return (bool)((\OC_User_Backend::CHECK_PASSWORD)
            | \OC_User_Backend::GET_DISPLAYNAME
            & $actions);
    }

    public function checkPassword($uid, $password)
    {
        \OCP\Util::writeLog('OC_rest_auth_app', 'Checking password of user: ' . $uid, \OCP\Util::DEBUG);

        $response = $this->client->get('call/customer', [
            'query' => [
                'call' => 'authenticate',
                'api_key' => \OC::$server->getAppConfig()->getValue('rest_auth_app', 'rest_auth_api_access_key',
                    OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY),
                'api_output' => 'json',
                'username' => $uid,
                'password' => $password
            ]
        ]);
        \OCP\Util::writeLog('OC_rest_auth_app', "Response from REST API: " . $response->getBody(), \OCP\Util::DEBUG);

        if (((string)$response->getBody()) === "true")
        {
            $this->storeUser($uid);
            $this->updateDisplayNameAndGroupsOfUser($uid);

            return $uid;
        }
        else
        {
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

    private function updateDisplayNameAndGroupsOfUser($uid)
    {
        // Find out in what groups the user needs to be added

        try
        {
            \OCP\Util::writeLog('OC_rest_auth_app', "Updating display name and groups...", \OCP\Util::DEBUG);
            $user = $this->userManager->get($uid);
            \OCP\Util::writeLog('OC_rest_auth_app', "User: ".$user->getDisplayName(), \OCP\Util::DEBUG);
            $oldGroupsOfUser = $this->groupManager->getUserGroups($user);

            \OCP\Util::writeLog('OC_rest_auth_app', "Calling REST API...", \OCP\Util::DEBUG);
            $response = $this->client->get('call/customer', [
                'query' => [
                    'call' => 'getInfo',
                    'api_key' => \OC::$server->getAppConfig()->getValue('rest_auth_app', 'rest_auth_api_access_key',
                        OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY),
                    'api_output' => 'json',
                    'username' => $uid
                ]
            ]);
            \OCP\Util::writeLog('OC_rest_auth_app', "customer/getInfo Response from REST API: " . $response->getBody(), \OCP\Util::DEBUG);



            $responseBody = json_decode($response->getBody(), true);

            $displayName = $responseBody['firstname'] . " " . $responseBody['lastname'];
            \OCP\Util::writeLog('OC_rest_auth_app', "Setting display name to ".$displayName, \OCP\Util::DEBUG);
            $this->setDisplayName($uid, $displayName);

            $tagIds = $responseBody['tag_ids'];
            $currentGroupsOfUser = array();
            foreach ($tagIds as $tagId)
            {
                \OCP\Util::writeLog('OC_rest_auth_app', "Tag Id: " . $tagId, \OCP\Util::DEBUG);

                $variableId = 'tag-' . $tagId;
                $owncloudGroupName = \OCP\Config::getAppValue('rest_auth_app', $variableId, "");
                if( !empty($owncloudGroupName)) {

                    $group = $this->groupManager->get($owncloudGroupName);
                    if( $group )
                    {
                        if (!$group->inGroup($user))
                        {
                            \OCP\Util::writeLog('OC_rest_auth_app',
                                "User " . $user->getDisplayName() . " is not yet in group " . $group->getGID() . ". Adding now...",
                                \OCP\Util::DEBUG);
                            $group->addUser($user);
                        }
                        else
                        {
                            \OCP\Util::writeLog('OC_rest_auth_app',
                                "User " . $user->getDisplayName() . " is already in group " . $group->getGID() . ".",
                                \OCP\Util::DEBUG);
                        }

                        array_push($currentGroupsOfUser, $group);
                    }
                    else {
                        \OCP\Util::writeLog('OC_rest_auth_app',
                            "The group ".$owncloudGroupName." does not exist. Cannot add user to group. Edit the settings to create the groups.",
                            \OCP\Util::WARN);
                    }
                }
                else {
                    \OCP\Util::writeLog('OC_rest_auth_app', "There is no mapping for tag " . $tagId. ". User will not be added to a group for that tag.", \OCP\Util::DEBUG);
                }
            }

            foreach ($oldGroupsOfUser as $oldGroup) {
                \OCP\Util::writeLog('OC_rest_auth_app', "old group: " . $oldGroup->getGID(), \OCP\Util::DEBUG);
                if(!in_array($oldGroup, $currentGroupsOfUser)) {
                    \OCP\Util::writeLog('OC_rest_auth_app', "user no longer in group: " . $oldGroup->getGID(), \OCP\Util::DEBUG);
                    $oldGroup->removeUser($user);
                }
            }


        } catch (\GuzzleHttp\Exception\BadResponseException $e)
        {
            if ($e->getResponse()->getStatusCode() == "404")
            {
                \OCP\Util::writeLog('OC_rest_auth_app', "User with username '$uid' does not exist.", \OCP\Util::ERROR);
            }
            else
            {
                \OCP\Util::writeLog('OC_rest_auth_app', "Error checking groups for username '$uid': " . $e, \OCP\Util::ERROR);
            }
        }
    }
}

?>
