<?php

OCP\User::checkAdminUser();

$params = [
    'rest_auth_api_url',
    'rest_auth_api_access_key'
];

if ($_POST) {
    // CSRF check
    OCP\JSON::callCheck();

    foreach ($params as $param) {
        if (isset($_POST[$param])) {
            \OCP\Config::setAppValue('rest_auth_app', $param, $_POST[$param]);
        }
    }

    foreach ($_POST as $key => $value) {
        if (strstr($key, 'tag-')) {
            $groupName = $_POST[$key];
            \OCP\Config::setAppValue('rest_auth_app', $key, $groupName);

            // Create a group if there is a mapping specified for a tag

            if (!empty($groupName)) {
                if (!OC::$server->getGroupManager()->groupExists($groupName)) {
                    \OCP\Util::writeLog('OC_rest_auth_app', "Group does not exist, creating: " . $groupName, \OCP\Util::DEBUG);
                    OC::$server->getGroupManager()->createGroup($groupName);
                }
            }
        }
    }
}

// fill template
$tmpl = new \OCP\Template('rest_auth_app', 'settings');
$tmpl->assign('rest_auth_api_url', \OCP\Config::getAppValue('rest_auth_app', 'rest_auth_api_url', OC_USER_BACKEND_REST_AUTH_API_URL));
$tmpl->assign('rest_auth_api_access_key', \OCP\Config::getAppValue('rest_auth_app', 'rest_auth_api_access_key', OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY));

$client = new \GuzzleHttp\Client([
    'base_url' => \OC::$server->getAppConfig()->getValue('rest_auth_app', 'rest_auth_api_url', OC_USER_BACKEND_REST_AUTH_API_URL)
]);

try {
    $response = $client->get('call/tag', [
        'query' => [
            'call'       => 'getAll',
            'api_key'    => \OC::$server->getAppConfig()->getValue('rest_auth_app', 'rest_auth_api_access_key',
                OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY),
            'api_output' => 'json'
        ]
    ]);

    $tags = json_decode($response->getBody());

    $tmpl->assign("tag_count", count($tags));

    \OCP\Util::writeLog('OC_rest_auth_app', "# of tags: " . count($tags), \OCP\Util::DEBUG);

    foreach ($tags as $tag) {
        \OCP\Util::writeLog('OC_rest_auth_app', "Tag: " . $tag->id . " -> " . $tag->name, \OCP\Util::DEBUG);

        $variableId = 'tag-' . $tag->id;

        $tmpl->assign($variableId, \OCP\Config::getAppValue('rest_auth_app', $variableId, ""));
        $tmpl->assign($variableId . "-original", $tag->name);
    }

} catch (Exception $e) {
    \OCP\Util::writeLog('OC_rest_auth_app', "Error getting tags: " . $e, \OCP\Util::ERROR);

    $tmpl->assign("rest_connection_error", true);
    $tmpl->assign("tag_count", 0);
}

return $tmpl->fetchPage();

?>
