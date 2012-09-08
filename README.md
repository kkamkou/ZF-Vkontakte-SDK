# Examples

## Constructor
```php
<?php
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
$api = MyProject_Social_Vkontakte::getInstance()
    ->authorize($_GET['code']);
```

## Controller

```php
<?php
$api = MyProject_Social_Vkontakte::getInstance();

$attrs = array(
    'uids'   => $api->getUid(),
    'fields' => 'country,city,contacts'
);

// user profile
var_dump($api->getProfiles($attrs));

// notes.add
$response = $api->add(
    array(
        'title' => 'Buy milk',
        'text' => 'Otherwise she kills me :(',
        'privacy' => 2
    ), 'notes'
);

var_dump($response);
```

## Template

```php
<-- socialLogin class opens popup window -->
<a href="<?=$this->vk()->uri('/my/link')?>"<?=$this->vk()->uid() ? '' : ' class="socialLogin"'?>>Link</a>
```