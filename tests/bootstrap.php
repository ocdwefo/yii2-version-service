<?php

declare(strict_types=1);

// Yii applications created by the tests must not register their error and exception handlers:
// PHPUnit owns them, and leaves the test risky when a handler is not removed.
define('YII_ENABLE_ERROR_HANDLER', false);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
