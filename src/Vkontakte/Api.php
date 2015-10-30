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

/** Api for the vk.com, that uses Zend Framework 1 */
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
    * @param  mixed  $scope
    */
    public function __construct($vkId, $vkKey, $urlAuth, $scope)
    {
        // access rules
        $this->_scope = (array)$scope;

        // default config
        $this->_config = array(
            'urlAccessToken'  => 'https://oauth.vk.com/access_token',
            'urlAuthorize'    => 'https://oauth.vk.com/authorize',
            'urlApi'          => 'https://api.vk.com/method/%s',
            'urlAuth'         => $urlAuth,
            'apiVersion'      => '5.37',
            'client_id'       => $vkId,
            'client_secret'   => $vkKey
        );

        // http client
        $this->_httpClient = new \Zend_Http_Client();
        $this->_httpClient->setConfig(array(
            'storeResponse'   => true,
            'strictRedirects' => true,
            'timeout'         => 10,
            'userAgent'       => 'ZF-Vkontakte-SDK'
        ));
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
                'redirect_uri' => $this->_config['urlAuth'],
                'code' => $code
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
     * Returns hash for the storage engine
     *
     * @return string
     */
    public function getStorageId()
    {
        return hash('crc32', implode(',', $this->getScope()));
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
     * Returns scope set
     *
     * @return array
     */
    public function getScope()
    {
        return $this->_scope;
    }

    /**
     * Singleton instance for the storage object
     *
     * @return Storage\StorageInterface
     */
    public function getStorage()
    {
        if (!$this->_storageObject) {
            $this->setStorage(new Storage\Session($this->getStorageId()));
        }
        return $this->_storageObject;
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
                'scope'         => implode(',', $this->getScope()),
                'display'       => 'popup',
                'redirect_uri'  => $this->_uriBuildRedirect($redirectUri)
            )
        );
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
    * @throws \InvalidArgumentException if no access_token found
    * @return stdClass
    */
    public function call($method, array $params = array())
    {
        // we should reset the error holder first
        $this->_errorMessage = null;

        // params injection
        $params += array('access_token' => $this->getAccessToken());

        // do we have access token?
        if (empty($params['access_token'])) {
            throw new \InvalidArgumentException(
                'No "access_token" found. Get uri first, then authorize.'
            );
        }

        // making request
        return $this->_request(
            $this->_uriBuild(sprintf($this->_config['urlApi'], $method), $params)
        );
    }

    /**
    * Makes request and checks the result according uri
    *
    * @param  string $uri
    * @return stdClass
    * @throws \Exception when the result was unsuccessful
    * @throws \UnexpectedValueException JSON parsing error
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
        try {
            $decoded = \Zend_Json::decode($response->getBody(), \Zend_Json::TYPE_OBJECT);
        } catch (\Zend_Json_Exception $e) {
            throw new \UnexpectedValueException(
                'Response is not JSON: ' . $response->getBody()
            );
        }

        // do we have something interesting?
        if (isset($decoded->error)) {
            if (!isset($decoded->error->error_code)) {
                $this->_errorMessage = $decoded->error;
            } else {
                throw new \Exception(
                    "Response contains error({$decoded->error->error_code}): " .
                        $decoded->error->error_msg
                );
            }
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
        $params += array(
            'client_id' => $this->_config['client_id'],
            'format' => 'json'
        );

        // sorting
        ksort($params);

        // params append
        $uriParams = $sig = '';
        foreach ($params as $key => $value) {
            if ($value != '') {
                $uriParams .= "&{$key}=" . urlencode($value);
                $sig .= $key . '=' . $value;
            }
        }

        // signature param
        $uriParams .= '&sig=' . md5($sig . $this->_config['client_secret']);

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
        $parts = preg_split(
            '~([A-Z][a-z]+)~', ucfirst($name), null,
            PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY
        );

        // call rerouting
        return $this->call(
            strtolower(array_shift($parts)) . '.' . lcfirst(implode('', $parts)),
            current($arguments)
        );
    }
}
