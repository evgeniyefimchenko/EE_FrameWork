<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Реализует предварительную загрузку скриптов в память модуля opcache при запуске движка
 * /inc/preloader.php
 */
require_once('inc/bootstrap.php');

ee_bootstrap_preload();
