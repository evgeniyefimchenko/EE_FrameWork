*EE_FRAMEFORK - Лёгкий PHP MVC фреймворк.*
=========================================
***
Предназначен для быстрого разворачивания и тонкой настройки.
---
Каждая функция задокументирована в коде на русском языке.
Все зависимости подключены через CDN (*jQuery - latest*, *Bootstrap 4.1.3*, *Font-Awesome - latest*) в layouts

Используются следующие плагины:
- **Mobile-Detect** https://github.com/serbanghita/Mobile-Detect
- **php_libmail_2_11** http://webi.ru/webi_files/php_libmail.html
- **SafeMySQL** https://github.com/colshrapnel/safemysql (переписан под singletone)
- **validator** https://github.com/evgeniyefimchenko/validator
- **DataTables** https://datatables.net/
- **Bootstrap Notify** https://github.com/mouse0270/bootstrap-notify
- **Bootstrap Switch** https://bttstrp.github.io/bootstrap-switch/
- **Bootstrap Multiselect** http://davidstutz.de/bootstrap-multiselect/
- **Ajax Autocomplete for jQuery** https://github.com/devbridge/jQuery-Autocomplete
## Установка

1. Скопируйте все файлы в корневую папку Вашего будущего сайта.
2. Замените в файле `.htaccess` все `site.ru` на адрес Вашего домена.
3. Настройте файл `inc/configuration.php` под свои нужды (все необходимые строки имеют комментарий).
4. Допишите необходимые мета тэги в файле `layouts/index.php` (опционально)
5. Запустите сайт.

    Если указать параметры подключения к БД то при первом запуске будут созданы следующие таблицы:
	- `geo_ru`(заполнится городами Российской федерации)
	- `logs` (логирование действий на сайте: Пустая)
	- `users_activation` (коды активации при регистрации пользователей: Пустая)
	- `users_message` (сообщения пользователей: Пустая)
	- `users_data` (настройки пользователей: Пустая)
	- `user_roles` (роли пользователей: Администратор, Модератор, Менеджер, Пользователь, Система)
	- `users_dell` (удалённые пользователи: Пустая)
	- `users` (пользователи сайта: Пустая)
	
## Внимание

Файл `robots.txt` и мета тэг `<meta name="robots" />` имеют значение "User-agent: * Disallow: /" и "noindex, nofollow"

Значение настраивается опционально в файле `inc/configuration.php` переменная $C['ENV_SITE_INDEX']

Для включения индексации необходимо установить значение "ALL" и настроить файл `robots.txt` ** самостоятельно! **

Если в дальнейшем вы измените значение на "noindex, nofollow" то текущий файл `robots.txt` будет перезаписан.

Структура проекта:
------------------
/app - Содержит скрипты целевых страниц проекта
	|
	/admin - Админ-панель проекта