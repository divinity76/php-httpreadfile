# php-httpreadfile
php readfile() with 304 Not Modified and 206 Partial Content support

# installation
the script is a standalone .php file, you can copypaste it,
or composer:
```
composer require 'divinity76/php-httpreadfile'
```
# Usage

```
<?php
require_once(__DIR__ . '/vendor/autoload.php');
\Divinity76\httpreadfile\httpreadfile(__DIR__);
```
