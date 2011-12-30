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

namespace vkontakte\storage;

/**
* @see Vkontakte_Storage_Interface
*/
class Session implements StorageInterface
{
    /**
    * Storage object
    * @var Zend_Session_Namespace
    */
    protected $_session;

    /**
    * Session expiration time (in seconds)
    * @var int
    */
    protected $_expiration = 10800; // 3 hours

    /**
    * Constructor
    *
    * @return void
    */
    public function __construct()
    {
        $this->_session = new \Zend_Session_Namespace(__CLASS__, true);
        $this->_session->setExpirationSeconds($this->_expiration); // 3 hours
    }

    public function getUserId()
    {
        return $this->_session->userId;
    }

    public function getExpiresIn()
    {
        return $this->_session->expiresIn;
    }

    public function getAccessToken()
    {
        return $this->_session->accessToken;
    }

    public function setUserId($uid)
    {
        $this->_session->userId = $uid;
    }

    public function setExpiresIn($expires)
    {
        $this->_session->expiresIn = $expires;
    }

    public function setAccessToken($token)
    {
        $this->_session->accessToken = $token;
    }
}
