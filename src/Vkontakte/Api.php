<?php
/**
 * Licensed under the MIT License
 * Redistributions of files must retain the copyright notice below.
 *
 * @category ThirdParty
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
    * Holds last error message
    * @var string
    */
    protected $_errorMessage;

    /**
    * Constructor
    *
    * @param  string $vkId  app id
    * @param  string $vkKey security key
    * @return void
    */
    public function __construct($vkId, $vkKey)
    {
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
                'useragent'       => 'ZF-Vkontakte-SDK'
            )
        );
    }

    /**
    * Returns authorize link (login or access form)
    *
    * @param  string $redirectUri full-path for the redirect page, includes http(s)
    * @param  array  $access (Default: null)
    * @link   http://tinyurl.com/bm5htmu
    * @return string
    */
    public function getAuthUri($redirectUri, $access = null)
    {
        // authorize link
        return $this->_uriBuild(
            $this->_config['urlAuthorize'], array(
                'scope'         => implode(',', (array)$access),
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

        // should we get code from a session? (do we need this code?)
        if (null === $code) {
            $code = $this->_session->code;
            if (empty($code)) {
                $this->_errorMessage = 'authorize requires "$code" to be specified';
                return false;
            }
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
        } catch (Exception $e) {
            $this->_errorMessage = $e->getMessage();
            return false;
        }

        // so, we have oAuth information, lets store it
        $this->_session->userId = $response->user_id;
        $this->_session->expiresIn = $response->expires_in;
        $this->_session->accessToken = $response->access_token;
        $this->_session->code = $code;

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
    * Returns message with error description
    *
    * @return string
    */
    public function getErrorMessage()
    {
        return $this->_errorMessage;
    }

    /**
    * Calls method
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
            throw new Exception(
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
            throw new Exception(
                "Request failed({$response->getStatus()}): {$response->getMessage()} at " .
                    $request->getLastRequest()
            );
        }

        // response has JSON format, we should decode it
        $decoded = Zend_Json::decode($response->getBody(), Zend_Json::TYPE_OBJECT);
        if ($decoded === null) {
            throw new Exception(
                'Response is not JSON: ' . $response->getBody()
            );
        }

        // do we have something interesting?
        if (isset($decoded->error)) {
            throw new Exception(
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
