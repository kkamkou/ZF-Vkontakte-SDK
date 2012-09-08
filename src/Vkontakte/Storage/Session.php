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

    /**
     * Constructor
     *
     * @param array $scope
     */
    public function __construct(array $scope)
    {
        // the session id
        $sessionId = __CLASS__ . hash('crc32', implode(',', $scope));

        // session storage
        $this->_session = new \Zend_Session_Namespace($sessionId);
        $this->_session->setExpirationSeconds($this->_expiration); // 3 hours
    }

    /**
     * Returns the user id
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->_session->userId;
    }

    /**
     * Returns seconds before vk de-auth you
     *
     * @return int
     */
    public function getExpiresIn()
    {
        return $this->_session->expiresIn;
    }

    /**
     * Returns the access token
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->_session->accessToken;
    }

    /**
     * Sets the current user id
     *
     * @param int $uid
     */
    public function setUserId($uid)
    {
        $this->_session->userId = $uid;
    }

    /**
     * Sets time of the token expiration
     *
     * @param int $expires
     */
    public function setExpiresIn($expires)
    {
        $this->_session->expiresIn = $expires;
    }

    /**
     * Sets the access token
     *
     * @param string $token
     */
    public function setAccessToken($token)
    {
        $this->_session->accessToken = $token;
    }
}
