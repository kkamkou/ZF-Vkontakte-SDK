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
    'user_ids' => $api->getUid(),
    'fields' => 'country,city,contacts,notes'
);

// users.get
var_dump($api->usersGet($attrs));

// places.getCityById
var_dump($api->databaseGetCitiesById(array('city_ids' => 123)));

// notes.add
$response = $api->notesAdd(array(
    'title' => 'Buy milk',
    'text' => 'Otherwise she kills me :('
));

var_dump($response);
```

## Method structure
```
@see: https://vk.com/dev/methods
$api->databaseGetCitiesById = database.getCitiesById
$api->authCheckPhone = auth.checkPhone
$api->usersGet = users.get
etc...
```

## Template

```php
<-- socialLogin class opens popup window -->
<a href="<?=$this->vk()->uri('/my/link')?>"<?=$this->vk()->uid() ? '' : ' class="socialLogin"'?>>Link</a>
```
## VK Setup
![](http://2ka.by/tmp/screenshots/2015-10-31-0300ffa.png)
