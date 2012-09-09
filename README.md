# Examples

## Constructor
```php
<?php
// see: _misc/singleton.php

require_once 'Other/Vkontakte/Api.php';

$config = Zend_Registry::get('config')->vk;
$authUri = 'http://mysite.com/vk';

$api = new \Vkontakte\Api(
    $config->id, $config->key, $authUri,
    array('offline', 'notes', 'wall')
);
```

## Auth
```php
<?php
// the api object
$api = MyProject_Social_Vkontakte::getInstance();

// we haven't error, closing and forward
if (!$this->hasParam('error')) {
    if ($api->authorize($this->getParam('code'))) {
        // see: _misc/controller.php
    }
}
```

## Controller

```php
<?php
$api = MyProject_Social_Vkontakte::getInstance();

$attrs = array(
    'uids'   => $api->getUid(),
    'fields' => 'country,city,contacts'
);

// users.get
var_dump($api->usersGet($attrs));

// places.getCityById
var_dump($api->placesGetCityById(array('cids' => 123)));

// notes.add
$response = $api->notesAdd(array(
    'title' => 'Buy milk',
    'text' => 'Otherwise she kills me :(',
    'privacy' => 2
));

var_dump($response);
```

## Template

```php
<-- socialLogin class opens popup window -->
<a href="<?=$this->vk()->uri('/my/link')?>"<?=$this->vk()->uid() ? '' : ' class="socialLogin"'?>>Link</a>
```