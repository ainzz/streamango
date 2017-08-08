# StreaMango

[![Packagist](https://img.shields.io/packagist/v/burakboz/streamango.svg?style=flat-square)](https://packagist.org/packages/BurakBoz/streamango)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://raw.githubusercontent.com/BurakBoz/streamango/master/LICENSE)
[![Travis branch](https://travis-ci.org/BurakBoz/streamango.svg?branch=master)](https://travis-ci.org/BurakBoz/streamango)

It's just a php client of the [streamango.com](https://streamango.com/) service.

## Install

```
composer require burakboz/streamango:~1.0
```

## Usage

All api features are implemented, from retrieve account info

```php
<?php

include_once './vendor/autoload.php';

use BurakBoz\streamango\streamangoClient;

$openload = new streamangoClient('apiLogin', 'apiKey');

$accountInfo = $openload->getAccountInfo();
echo $accountInfo->getEmail(); //account@email.com
```

to upload a file

```php
<?php

include_once './vendor/autoload.php';

use BurakBoz\streamango\streamangoClient;

$openload = new streamangoClient('apiLogin', 'apiKey');

$openload->uploadFile('/home/user/Pictures/image.jpg');
```

It's also possible find more about what you can to do at [streamango API](https://streamango.com/api).

## Author

Burak Boz

## License

MIT

## Info
Forked from Ideneal/OpenLoad 