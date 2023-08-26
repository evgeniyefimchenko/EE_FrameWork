<? 
if (ENV_SITE !== 1) {
	header("HTTP/1.1 301 Moved Permanently"); 
	header("Location: http://". $_SERVER['HTTP_HOST']);
	}
/**
* Модель главной страницы
*/
Class Model_index Extends Users {

}