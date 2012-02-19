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

class MyProject_VkontakteController
{
    /**
     * Default action
     */
    public function indexAction()
    {
        // no default output here
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        // the api object
        $api = MyProject_Social_Vkontakte::getInstance();

        // we haven't error, closing and forward
        if (is_null($this->getParam('error'))) {
            if ($api->authorize($this->getParam('code'))) {
                return $this->_exit($this->getParam('forward'));
            }
        }

        // let's close popup, something wrong
        return $this->_exit();
    }

    /**
     * Shows html to close dialog
     *
     * @param  string $urn (Default: null)
     * @return void
     */
    protected function _exit($urn = null)
    {
        $this->view->urn = $urn;
        $this->renderScript('popup-social.phtml');
    }
}
