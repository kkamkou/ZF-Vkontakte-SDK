# Examples

## Auth
```php
<?php
// the api object
$api = MyProject_Social_Vkontakte::getInstance();

// see the "_misc" folder
$api->authorize($code);
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
```

## Template

```php
<-- socialLogin class opens popup window -->
<a href="<?=$this->vk()->uri('/my/link')?>"<?=$this->vk()->uid() ? '' : ' class="socialLogin"'?>>Link</a>
```