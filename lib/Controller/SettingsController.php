<?php

namespace OCA\rest_auth_app\Controller;

use OCP\AppFramework\Controller;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Settings\ISettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Template;

class SettingsController extends Controller implements ISettings
{
    /** @var IConfig */
    private $config;
    /** @var IGroupManager  */
    private $groupManager;

    /** @var string */
    protected $appName;
    /** @var IRequest */
    protected $request;

    const OC_USER_BACKEND_REST_AUTH_API_URL = 'http://api.test.knx.org/';
    const OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY = 'Peek6Iech2hoo5qua7quaimez9eeMoh9IUY';

    const REST_AUTH_API_URL_LABEL = 'rest_auth_api_url';
    const REST_AUTH_API_ACCESS_KEY_LABEL = 'rest_auth_api_access_key';

    public function __construct(string $appName, IRequest $request, IConfig $config, IGroupManager $groupManager)
    {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->groupManager = $groupManager;
    }

    public function post(string $rest_auth_api_url, string $rest_auth_api_access_key, array $tags)
    {

        if (!empty($rest_auth_api_url)) {
            $this->config->setAppValue($this->appName, self::REST_AUTH_API_URL_LABEL, $rest_auth_api_url);
        }

        if(!empty($rest_auth_api_access_key)) {
            $this->config->setAppValue($this->appName, self::REST_AUTH_API_ACCESS_KEY_LABEL, $rest_auth_api_access_key);
        }

        foreach ($tags as $key => $value) {
            if (strstr($key, 'tag-')) {
                $groupName = $value;
                $this->config->setAppValue($this->appName, $key, $groupName);

                // Create a group if there is a mapping specified for a tag

                if (!empty($groupName)) {
                    if (!$this->groupManager->groupExists($groupName)) {
                        \OCP\Util::writeLog('OC_rest_auth_app', "Group does not exist, creating: " . $groupName, \OCP\Util::DEBUG);
                        $this->groupManager->createGroup($groupName);
                    }
                }
            }
        }

        return $templateResponse = new TemplateResponse($this->appName, 'redirectToSettings', [], '');
    }

    public function getPriority()
    {
        return 0;
    }

    public function getSectionID()
    {
        return 'authentication';
    }
    /**
     * @NoCSRFRequired
     * @return TemplateResponse
     */
    public function index(){
        return $templateResponse = new TemplateResponse($this->appName, 'redirectToSettings', [], '');
    }

    public function getPanel()
    {
        return $this->displayPanel();
    }

    /**
     * @return TemplateResponse
     */
    public function displayPanel()
    {

        $params = [
            self::REST_AUTH_API_URL_LABEL        => $this->config->getAppValue($this->appName, self::REST_AUTH_API_URL_LABEL, self::OC_USER_BACKEND_REST_AUTH_API_URL),
            self::REST_AUTH_API_ACCESS_KEY_LABEL => $this->config->getAppValue($this->appName, self::REST_AUTH_API_ACCESS_KEY_LABEL, self::OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY)
        ];

        $client = new \GuzzleHttp\Client([
            'base_url' => $this->config->getAppValue($this->appName, self::REST_AUTH_API_URL_LABEL, self::OC_USER_BACKEND_REST_AUTH_API_URL)
        ]);

        try {
            $response = $client->get('call/tag', [
                'query' => [
                    'call'       => 'getAll',
                    'api_key'    => $this->config->getAppValue($this->appName, self::REST_AUTH_API_ACCESS_KEY_LABEL, self::OC_USER_BACKEND_REST_AUTH_API_ACCESS_KEY),
                    'api_output' => 'json'
                ]
            ]);

            $tags = json_decode($response->getBody());

            $params['tag_count'] = count($tags);

            \OCP\Util::writeLog('OC_rest_auth_app', "# of tags: " . count($tags), \OCP\Util::DEBUG);


            foreach ($tags as $tag) {
                \OCP\Util::writeLog('OC_rest_auth_app', "Tag: " . $tag->id . " -> " . $tag->name, \OCP\Util::DEBUG);

                $variableId = 'tag-' . $tag->id;

                $params[$variableId] = $this->config->getAppValue($this->appName, $variableId, '');
                $params[$variableId.'-original'] = $tag->name;
            }

        } catch (\Exception $e) {
            \OCP\Util::writeLog('OC_rest_auth_app', "Error getting tags: " . $e, \OCP\Util::ERROR);

            $params['rest_connection_error'] = true;
            $params['tag_count'] = 0;
        }

        return $templateResponse = new TemplateResponse($this->appName, 'settings', $params, '');

    }
}