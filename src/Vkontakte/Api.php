<?php
/**
 * Author(s): Kanstantsin A Kamkou <2ka.by>
 *
 * Licensed under the MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @category ThirdParty
 * @package  Vkontakte
 * @author   Copyright (c) 2011 Kanstantsin A Kamkou (2ka.by)
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     https://github.com/kkamkou/ZF-Vkontakte-SDK
 */

require_once dirname(__FILE__) . '/Exception/Decode.php';
require_once dirname(__FILE__) . '/Exception/Unauthorized.php';
require_once dirname(__FILE__) . '/Exception/Request.php';
require_once dirname(__FILE__) . '/Exception/Unauthorized.php';

/**
* Api for the vkontakte.ru, that uses Zend Framework
*
* @example
*
*   $vkApi = new Vkontakte_Api(ID, KEY);
*   if (!$vkApi->getUid()) {
*       if (empty($_GET['code'])) {
*           echo $vkApi->getAuthUri(array('offline'), 'http://mysite.com/');
*       } else {
*           $vkApi->authorize($_GET['code']);
*       }
*   } else {
*       var_dump($vkApi->call('getProfiles')); // stdClass here!
*   }
*/
class Vkontakte_Api
{
    /**
    * Default information
    * @var array
    */
    protected $_config = array();

    /**
    * Storage object
    * @var Zend_Session_Namespace
    */
    protected $_session;

    /**
    * Object for the http client
    * @var Zend_Http_Client
    */
    protected $_httpClient;

    /**
    * Constructor
    *
    * @param  string $vkId  app id
    * @param  string $vkKey security key
    * @return void
    */
    public function __construct($vkId, $vkKey)
    {
        $config = Zend_Registry::get('config');

        // default config
        $this->_config = array(
            'urlAccessToken'  => 'https://api.vk.com/oauth/access_token',
            'urlAuthorize'    => 'https://api.vk.com/oauth/authorize',
            'urlMethod'       => 'https://api.vk.com/method',
            'client_id'       => $vkId,
            'client_secret'   => $vkKey
        );

        // default data storage
        $this->_session = new Zend_Session_Namespace(__CLASS__, true);
        $this->_session->setExpirationSeconds(10800); // 3 hours

        // http client
        $this->_httpClient = new Zend_Http_Client();
        $this->_httpClient->setConfig(
            array(
                'storeresponse'   => true,
                'strictredirects' => true,
                'timeout'         => 10,
                'useragent'       => 'CDDISKI'
            )
        );
    }

    /**
    * Returns authorize link (login or access form)
    *
    * @param  array  $settings (Default: array)
    * @param  string $redirectUri
    * @return string
    */
    public function getAuthUri($settings = array(), $redirectUri)
    {
        // authorize link
        return $this->_uriBuild(
            $this->_config['urlAuthorize'], array(
                'scope'         => implode(',', (array)$settings),
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
        } catch (Exception $e) {
            return false;
        }

        // so, we have information about user, lets store it
        $this->_session->userId = $response->user_id;
        $this->_session->expiresIn = $response->expires_in;
        $this->_session->accessToken = $response->access_token;

        return true;
    }

    /**
    * Returns id of a user
    *
    * @return int
    */
    public function getUid()
    {
        return $this->_session->userId;
    }

    /**
    * Returns access token from the oAuth
    *
    * @return string
    */
    public function getAccessToken()
    {
        return $this->_session->accessToken;
    }

    /**
    * Returns token expire time in seconds
    *
    * @return int
    */
    public function getExpiresIn()
    {
        return $this->_session->expiresIn;
    }

    /**
    * Calls method
    *
    * @param  string $method
    * @param  array  $params (Default: array)
    * @return stdClass
    */
    public function call($method, array $params = array())
    {
        // do we have access token
        if (!$this->getAccessToken()) {
            throw new Vkontakte_Api_Exception_Unauthorized(
                "No access_token found. Use getAuthUri, then authorize methods"
            );
        }

        // default set
        $params['access_token'] = $this->getAccessToken();
        $params['uid'] = $this->getUid();

        // auth uri
        $uri = $this->_uriBuild(
            $this->_config['urlMethod'] . '/' . $method, $params
        );

        // making request
        return $this->_request($uri);
    }

    /**
    * Makes request and checks result according uri
    *
    * @param  string $uri
    * @return stdClass
    * @throws Cddiski_Exception when result was unsuccessful
    */
    protected function _request($uri)
    {
        // request object
        $request = $this->_httpClient->resetParameters(true)
            ->setUri($uri);

        // lets send request and check what we have
        $response = $request->request();
        if (!$response->isSuccessful()) {
            throw new Vkontakte_Api_Exception_Request(
                'Request failed: ' . $request->getLastRequest()
            );
        }

        // response has JSON format, we should decode it
        $decoded = @json_decode($response->getBody());
        if ($decoded === null) {
            throw new Vkontakte_Api_Exception_Decode(
                'Response is not JSON: ' . $response->getBody()
            );
        }

        // do we have something interesting?
        if (isset($decoded->error)) {
            throw new Vkontakte_Api_Exception_Error(
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