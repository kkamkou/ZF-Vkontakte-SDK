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

interface StorageInterface
{
    public function setUserId($val);
    public function getUserId();

    public function setEmail($val);
    public function getEmail();

    public function setExpiresIn($val);
    public function getExpiresIn();

    public function setAccessToken($val);
    public function getAccessToken();
}
