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

namespace Vkontakte\Storage;

/**
* @see StorageInterface
*/
class Session implements StorageInterface
{
    /**
    * Storage object
    * @var \Zend_Session_Namespace
    */
    protected $_session;

    /**
    * Session expiration time (in seconds)
    * @var int
    */
    protected $_expiration = 10800; // 3 hours

    /** @param string $sessionId */
    public function __construct($sessionId)
    {
        $this->_session = new \Zend_Session_Namespace(__CLASS__ . $sessionId);
        $this->_session->setExpirationSeconds($this->_expiration); // 3 hours
    }

    /**
     * Returns the user id
     * @return int
     */
    public function getUserId()
    {
        return $this->_session->userId;
    }

    /**
     * Returns the user email
     * @return string
     */
    public function getEmail()
    {
        return $this->_session->email;
    }

    /**
     * Returns seconds before vk de-auth you
     * @return int
     */
    public function getExpiresIn()
    {
        return $this->_session->expiresIn;
    }

    /**
     * Returns the access token
     * @return string
     */
    public function getAccessToken()
    {
        return $this->_session->accessToken;
    }

    /**
     * Sets the current user id
     * @param int $uid
     */
    public function setUserId($uid)
    {
        $this->_session->userId = $uid;
    }

    /**
     * Sets the current user email
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->_session->email = $email;
    }

    /**
     * Sets time of the token expiration
     * @param int $expires
     */
    public function setExpiresIn($expires)
    {
        $this->_session->expiresIn = $expires;
    }

    /**
     * Sets the access token
     * @param string $token
     */
    public function setAccessToken($token)
    {
        $this->_session->accessToken = $token;
    }
}
