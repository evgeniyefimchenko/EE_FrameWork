<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} 

/**
*	Модель главной страницы админ ппанели
*/

Class Model_index Extends Users {
    
}
