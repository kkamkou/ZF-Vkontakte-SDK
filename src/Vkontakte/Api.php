<?php
/**
 * Licensed under the MIT License
 * Redistributions of files must retain the copyright notice below.
 *
 * @category ThirdParty
 * @package  Vkontakte
 * @author   Kanstantsin A Kamkou (2ka.by)
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     https://github.com/kkamkou/ZF-Vkontakte-SDK
 */

namespace Vkontakte;

require_once 'Storage/Interface.php';
require_once 'Storage/Session.php';

/**
* Api for the vk.com, that uses Zend Framework
* @see https://github.com/kkamkou/ZF-Vkontakte-SDK/wiki
*/
class Api
{
    /**
    * Default information
    * @var array
    */
    protected $_config = array();

    /**
    * Object for the http client
    * @var \Zend_Http_Client
    */
    protected $_httpClient;

    /**
    * Holds message with the latest error description
    * @var string
    */
    protected $_errorMessage;

    /**
    * Stores access scope
    * @link http://tinyurl.com/bm5htmu
    * @var array
    */
    protected $_scope;

    /**
    * Stores object for the storage engine
    * @var Storage\StorageInterface
    */
    private $_storageObject;

    /**
    * Constructor
    *
    * @param  string $vkId  app id
    * @param  string $vkKey security key
    * @param  string $urlAuth auth uri
    * @param  mixed  $scope (Default: null)
    */
    public function __construct($vkId, $vkKey, $urlAuth, $scope = null)
    {
        // access rules
        $this->_scope = (array)$scope;

        // default config
        $this->_config = array(
            'urlAccessToken'  => 'https://api.vk.com/oauth/access_token',
            'urlAuthorize'    => 'https://api.vk.com/oauth/authorize',
            'urlMethod'       => 'https://api.vk.com/method',
            'urlAuth'         => $urlAuth,
            'client_id'       => $vkId,
            'client_secret'   => $vkKey
        );

        // http client
        $this->_httpClient = new \Zend_Http_Client();
        $this->_httpClient->setConfig(
            array(
                'storeresponse'   => true,
                'strictredirects' => true,
                'timeout'         => 10,
                'useragent'       => 'ZF-Vkontakte-SDK'
            )
        );
    }

    /**
    * Returns object for the HTTP client
    *
    * @return \Zend_Http_Client
    */
    public function getClient()
    {
        return $this->_httpClient;
    }

    /**
    * Singleton instance for the storage object
    *
    * @return Storage\StorageInterface
    */
    public function getStorage()
    {
        if (!$this->_storageObject) {
            $this->_storageObject = new Storage\Session();
        }
        return $this->_storageObject;
    }

    /**
     * Resets default storage engine
     *
     * @param  Storage\StorageInterface $storage
     * @return Api
     */
    public function setStorage(Storage\StorageInterface $storage)
    {
        $this->_storageObject = $storage;
        return $this;
    }

    /**
    * Returns authorize link (login or access form)
    *
    * @param  string $redirectUri full-path for the redirect page, includes http(s)
    * @return string
    */
    public function getAuthUri($redirectUri)
    {
        // authorize link
        return $this->_uriBuild(
            $this->_config['urlAuthorize'], array(
                'scope'         => implode(',', $this->_scope),
                'display'       => 'popup',
                'redirect_uri'  => $this->_uriBuildRedirect($redirectUri),
                'response_type' => 'code'
            )
        );
    }

    /**
    * Authorizes user if needed
    *
    * @param  string $code (Default: null)
    * @return bool
    */
    public function authorize($code = null)
    {
        // if there is already a user id, he is authorised
        if ($this->getUid()) {
            return true;
        }

        // auth uri
        $uri = $this->_uriBuild(
            $this->_config['urlAccessToken'], array(
                'client_secret' => $this->_config['client_secret'],
                'display'       => 'popup',
                'code'          => $code
            )
        );

        // making request
        try {
            $response = $this->_request($uri);
        } catch (\Exception $e) {
            $this->_errorMessage = $e->getMessage();
            return false;
        }

        // so, we have oAuth information, let's store it
        $this->getStorage()->setUserId($response->user_id);
        $this->getStorage()->setExpiresIn($response->expires_in);
        $this->getStorage()->setAccessToken($response->access_token);

        return true;
    }

    /**
    * Returns id of the user
    *
    * @return int
    */
    public function getUid()
    {
        return $this->getStorage()->getUserId();
    }

    /**
    * Returns access token from the oAuth
    *
    * @return string
    */
    public function getAccessToken()
    {
        return $this->getStorage()->getAccessToken();
    }

    /**
    * Returns token expire time in seconds
    *
    * @return int
    */
    public function getExpiresIn()
    {
        return $this->getStorage()->getExpiresIn();
    }

    /**
    * Returns message with the error description
    *
    * @return string
    */
    public function getErrorMessage()
    {
        return $this->_errorMessage;
    }

    /**
    * Calls specified method
    *
    * @param  string $method
    * @param  array  $params (Default: array)
    * @throws \Exception if no access_token found
    * @return stdClass
    */
    public function call($method, array $params = array())
    {
        // we should reset the error holder first
        $this->_errorMessage = null;

        // default set of parameters
        if (!isset($params['access_token'])) {
            $params['access_token'] = $this->getAccessToken();
        }

        // do we have access token?
        if (!isset($params['access_token'])) {
            throw new \InvalidArgumentException(
                'No "access_token" found. Get uri first, then authorize.'
            );
        }

        // auth uri
        $uri = $this->_uriBuild(
            $this->_config['urlMethod'] . '/' . $method, $params
        );

        // making request
        return $this->_request($uri);
    }

    /**
    * Quick access to vk functions
    *
    * @param  string $name
    * @param  array  $arguments
    * @return stdClass
    */
    public function __call($name, $arguments)
    {
        // we should normalize the name of the function
        if (isset($arguments[1])) {
            $name = strtolower($arguments[1]) . '.' . $name;
        }

        return $this->call($name, $arguments[0]);
    }

    /**
    * Makes request and checks the result according uri
    *
    * @param  string $uri
    * @return stdClass
    * @throws \Exception when the result was unsuccessful
    */
    protected function _request($uri)
    {
        // request object
        $request = $this->_httpClient->resetParameters(true)
            ->setUri($uri);

        // let's send request and check what we have
        try {
            $response = $request->request();
        } catch (\Zend_Http_Client_Exception $e) {
            throw new \Exception('Client request was unsuccessful', $e->getCode(), $e);
        }

        if (!$response->isSuccessful()) {
            throw new \Exception(
                "Request failed({$response->getStatus()}): {$response->getMessage()} at " .
                    $request->getLastRequest()
            );
        }

        // the response has JSON format, we should decode it
        $decoded = \Zend_Json::decode($response->getBody(), \Zend_Json::TYPE_OBJECT);
        if ($decoded === null) {
            throw new \UnexpectedValueException(
                'Response is not JSON: ' . $response->getBody()
            );
        }

        // do we have something interesting?
        if (isset($decoded->error)) {
            throw new \Exception(
                "Response contains error({$decoded->error->error_code}): " .
                $decoded->error->error_msg
            );
        }

        return $decoded;
    }

    /**
    * Creates uri according specified parameters
    *
    * @param  string $uri
    * @param  array  $params (Default: array)
    * @return string
    */
    protected function _uriBuild($uri, array $params = array())
    {
        // url cleanup
        $uri = rtrim($uri, '/');

        // default params
        if (!isset($params['client_id'])) {
            $params = array_merge(
                array('client_id' => $this->_config['client_id']), $params
            );
        }

        // params append
        $uriParams = '';
        foreach ($params as $key => $value) {
            if ($value != '') {
                $uriParams .= "&{$key}=" . urlencode($value);
            }
        }

        // creating full address
        return $uri . '?' . ltrim($uriParams, '&');
    }

    /**
     * Returns redirect uri
     *
     * @param  string $uri
     * @return string
     */
    protected function _uriBuildRedirect($uri)
    {
        // default symbol
        $symbol = '&';
        if (strpos($this->_config['urlAuth'], '?') === false) {
            $symbol = '?';
        }

        // redirect uri
        return $this->_config['urlAuth'] . $symbol . 'forward=' . urlencode($uri);
    }
}
