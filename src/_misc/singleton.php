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

require_once 'Other/Vkontakte/Api.php';

/**
 * @see \Vkontakte\Api
 */
class MyProject_Social_Vkontakte extends \Vkontakte\Api
{
    /**
     * Stores api for the vk.com
     * @var MyProject_Social_Vkontakte
     */
    static protected $_instance;

    /**
     * Constructor
     */
    public function __construct()
    {
        // the config object
        $config = Zend_Registry::get('config')->social->vk;

        // auth uri
        $urn = MyProject_Utils_Client::getUrl() . u(array(), 'vkontakte', true);

        // parent one
        parent::__construct($config->id, $config->key, $urn, 'offline');
    }

    /**
     * Singleton
     *
     * @return MyProject_Social_Vkontakte
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
}
