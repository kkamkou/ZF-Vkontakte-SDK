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

/**
* Api for the vkontakte.ru, that uses Zend Framework
*
* @example
*   $vkApi = new Vkontakte_Api(ID, KEY);
*   if (!$vkApi->getUid()) {
*       if (empty($_GET['code'])) {
*           echo $vkApi->getAuthUri('http://mysite.com/', array('offline'));
*       } else {
*           $vkApi->authorize($_GET['code']);
*       }
*   } else {
*       var_dump($vkApi->getProfiles(array('uids' => $vkApi->getUid()))); // stdClass here!
*   }
*
* Steps:
*   1. $vkApi->getAuthUri('http://mysite.com/vk/', array('offline'));
*   2. $vkApi->authorize($_GET['code']);
*   3. $vkApi->VK_FUNCTION
*/

namespace vkontakte;

require_once 'Storage/Interface.php';
require_once 'Storage/Session.php';

class Api
{
    /**
    * Default information
    * @var array
    */
    protected $_config = array();

    /**
    * Object for the http client
    * @var Zend_Http_Client
    */
    protected $_httpClient;

    /**
    * Holds last error message
    * @var string
    */
    protected $_errorMessage;

    /**
    * Stores access scope
    * @link http://tinyurl.com/bm5htmu
    * @var array
    */
    protected $_scope = array('offline');

    /**
    * Stores object for the storage engine
    * @var Vkontakte_Storage_Interface
    */
    private $_storageObject;

    /**
    * Constructor
    *
    * @param  string $vkId  app id
    * @param  string $vkKey security key
    * @param  mixed  $scope (Default: null)
    * @return void
    */
    public function __construct($vkId, $vkKey, $scope = null)
    {
        // access rules
        $this->_scope = (array)$scope;

        // default config
        $this->_config = array(
            'urlAccessToken'  => 'https://api.vk.com/oauth/access_token',
            'urlAuthorize'    => 'https://api.vk.com/oauth/authorize',
            'urlMethod'       => 'https://api.vk.com/method',
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
    * @return Zend_Http_Client
    */
    public function getClient()
    {
        return $this->_httpClient;
    }

    /**
    * Singleton instance of the storage object
    *
    * @return Vkontakte_Storage_Interface
    */
    public function getStorage()
    {
        if (!$this->_storageObject) {
            $this->_storageObject = new storage\Session();
        }
        return $this->_storageObject;
    }

    /**
    * Resets default storage engine
    *
    * @return Vkontakte_Storage_Interface
    */
    public function setStorage(Vkontakte_Storage_Interface $storage)
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
                'redirect_uri'  => $redirectUri,
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
        // if there is already a user id, it is authorised
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

        // so, we have oAuth information, lets store it
        $this->getStorage()->setUserId($response->user_id);
        $this->getStorage()->setExpiresIn($response->expires_in);
        $this->getStorage()->setAccessToken($response->access_token);

        return true;
    }

    /**
    * Returns id of a user
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
    * @throws Exception if no access_token found
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
        // we should normalize name of function
        if (isset($arguments[1])) {
            $name = strtolower($arguments[1]) . '.' . $name;
        }

        return $this->call($name, $arguments[0]);
    }

    /**
    * Makes request and checks result according uri
    *
    * @param  string $uri
    * @return stdClass
    * @throws Exception when result was unsuccessful
    */
    protected function _request($uri)
    {
        // request object
        $request = $this->_httpClient->resetParameters(true)
            ->setUri($uri);

        // lets send request and check what we have
        try {
            $response = $request->request();
        } catch (\Zend_Http_Client_Exception $e) {
            throw new Exception('Client request was unsuccessful', $e->getCode(), $e);
        }

        if (!$response->isSuccessful()) {
            throw new \Exception(
                "Request failed({$response->getStatus()}): {$response->getMessage()} at " .
                    $request->getLastRequest()
            );
        }

        // response has JSON format, we should decode it
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
    * @param  string $params (Default: array)
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
}
