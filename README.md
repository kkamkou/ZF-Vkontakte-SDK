# Examples

## Auth
```php
<?php
// the api object
$api = MyProject_Social_Vkontakte::getInstance();

// we have an error, just closing
if (is_null($this->getParam('error'))) {
    if ($api->authorize($this->getParam('code'))) {
        return $this->_exit($this->getParam('forward'));
    }
}

// let's close popup and redirect
return $this->_exit();
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