<?php
namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Базовый абстрактный класс для всех моделей проекта
 */
abstract class ModelBase {

    protected $params;

    public function __construct(array $params = []) {
        $this->params = $params;
    }

}


