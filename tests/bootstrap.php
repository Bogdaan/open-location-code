<?php

if (!($loader = include __DIR__ . '/../vendor/autoload.php')) {
    die(<<<DOC
Install project dependencies using Composer:
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install --dev
$ phpunit
DOC
    );
}

$loader->add('OpenLocationCode\Tests', __DIR__);
