<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Реализует предварительную загрузку скриптов в память модуля opcache при запуске движка
 * /inc/preloader.php
 */
require_once('inc/configuration.php');
require_once ('inc/startup.php');
require_once ('inc/hooks.php');

AutoloadManager::init();
