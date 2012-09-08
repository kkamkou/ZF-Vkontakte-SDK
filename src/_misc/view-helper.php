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
 * template example:
 *  <?=$this->vk()->uid()?>
 *  <?=$this->vk()->uri('/my/page/')?>
 */
class Application_View_Helper_Vk
{
    /**
     * @return Application_View_Helper_Vk
     */
    public function vk()
    {
        return $this;
    }

    /**
     * Returns the user id if it exists
     *
     * @return mixed
     */
    public function uid()
    {
        return MyProject_Social_Vkontakte::getInstance()->getUid();
    }

    /**
     * Makes URI
     *
     * @param  string $urn
     * @return string
     */
    public function uri($urn)
    {
        // the api object
        $api = MyProject_Social_Vkontakte::getInstance()
            ->setRedirectUrl($urn);

        // auth urn
        return $api->getUid() ? $urn : $api->getAuthUri();
    }
}
