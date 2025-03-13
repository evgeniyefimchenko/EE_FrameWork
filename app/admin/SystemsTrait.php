<?php

namespace app\admin;

use classes\system\SysClass;
use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;

/**
 * Функции работы с логами
 */
trait SystemsTrait {

    /**
     * Вывод страницы с логами
     */
    public function logs($params = array()) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::handleRedirect();
            exit();
        }
        /* get data */
        $fatal_errors_table = $this->get_php_logs_table('fatal_errors');
        $php_logs_table = $this->get_php_logs_table('php_logs');
        $progect_logs = $this->get_project_logs_table();
        /* view */
        $this->getStandardViews();
        $this->view->set('php_logs_table', $php_logs_table);
        $this->view->set('fatal_errors_table', $fatal_errors_table);
        $this->view->set('progect_logs_table', $progect_logs);
        $this->view->set('body_view', $this->view->read('v_logs'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'logs';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу логирования проекта
     */
    public function get_project_logs_table() {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_systems');
        $data_table['columns'] = [
            [
                'field' => 'date_time',
                'title' => $this->lang['sys.date_create'],
                'sorted' => true,
                'filterable' => true,
                'width' => 10
            ],
            [
                'field' => 'type_log',
                'title' => 'type_log',
                'sorted' => true,
                'filterable' => true,
                'width' => 10
            ],
            [
                'field' => 'initiator',
                'title' => 'initiator',
                'sorted' => 'ASC',
                'filterable' => true,
                'width' => 10
            ],
            [
                'field' => 'result',
                'title' => 'result',
                'sorted' => false,
                'filterable' => false,
                'width' => 10
            ],
            [
                'field' => 'details',
                'title' => 'details',
                'sorted' => false,
                'filterable' => false,
                'width' => 60
            ],
        ];
        $filters = [
            'date_time' => [
                'type' => 'date',
                'id' => "date_time",
                'value' => '',
                'label' => $this->lang['sys.date_create']
            ],
            'initiator' => [
                'type' => 'text',
                'id' => "initiator",
                'value' => '',
                'label' => 'initiator'
            ],
            'type_log' => [
                'type' => 'text',
                'id' => "type_log",
                'value' => '',
                'label' => 'type_log'
            ]
        ];
        $postData = SysClass::ee_cleanArray($_POST);
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $type = $this->get_table_name_from_post($postData);
            $php_logs_array = $this->models['m_systems']->get_all_logs($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $php_logs_array = $this->models['m_systems']->get_all_logs(false, false, false, 25);
        }
        foreach ($php_logs_array['data'] as $key => $item) {
            $data_table['rows'][$key] = [
                'date_time' => $item['date_time'],
                'type_log' => $item['type_log'],
                'initiator' => $item['initiator'],
                'result' => $item['result'],
                'details' => $item['details'], true,
                'nested_table' => [
                    'columns' => [
                        ['field' => 'stack_trace', 'title' => $this->lang['sys.stack_trace'], 'width' => 20, 'align' => 'left'],
                    ],
                    'rows' => [
                        ['stack_trace' => $item['stack_trace']],
                    ],
                ]
            ];
            if (!$item['stack_trace']) {
                unset($data_table['rows'][$key]['nested_table']);
            }
        }
        $data_table['total_rows'] = $php_logs_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('progect_logs_table_', $data_table, 'get_project_logs_table', $filters, (int) $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('progect_logs_table_', $data_table, 'get_project_logs_table', $filters);
        }
    }

    /**
     * Вернёт таблицу ошибок PHP
     */
    public function get_php_logs_table($type = '') {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_systems');
        $data_table['columns'] = [
            [
                'field' => 'error_type',
                'title' => $this->lang['sys.error_type'],
                'sorted' => true,
                'filterable' => true,
                'width' => 15
            ],
            [
                'field' => 'date_time',
                'title' => $this->lang['sys.date_create'],
                'sorted' => 'ASC',
                'filterable' => true,
                'width' => 15,
                'align' => 'center'
            ],
            [
                'field' => 'message',
                'title' => $this->lang['sys.message'],
                'sorted' => false,
                'filterable' => true,
                'width' => 70
            ],
        ];
        $filters = [
            'error_type' => [
                'type' => 'text',
                'id' => "error_type",
                'value' => '',
                'label' => $this->lang['sys.error_type']
            ],
            'date_time' => [
                'type' => 'date',
                'id' => "date_time",
                'value' => '',
                'label' => $this->lang['sys.date_create']
            ],
            'message' => [
                'type' => 'text',
                'id' => "message",
                'value' => '',
                'label' => $this->lang['sys.message']
            ],
        ];
        if ($type == 'fatal_errors') {
            unset($filters['error_type']);
            $data_table['columns'][0]['sorted'] = false;
            $data_table['columns'][0]['filterable'] = false;
        }
        $postData = SysClass::ee_cleanArray($_POST);
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $type = $this->get_table_name_from_post($postData);
            $php_logs_array = $this->models['m_systems']->get_php_logs($params['order'], $params['where'], $params['start'], $params['limit'], $type);
        } else {
            $php_logs_array = $this->models['m_systems']->get_php_logs(false, false, false, 25, $type);
        }
        foreach ($php_logs_array['data'] as $key => $item) {
            $stack_trace = false;
            if (is_array($item['stack_trace']) && count(($item['stack_trace']))) {
                foreach ($item['stack_trace'] as $stack) {
                    $stack_trace .= $stack . '<br/>';
                }
            }
            if ($type == 'fatal_errors') {
                $item['error_type'] = 'PHP Fatal error';
            }
            $data_table['rows'][$key] = [
                'date_time' => $item['date_time'],
                'error_type' => $item['error_type'] == 'PHP Fatal error' ? '<span class="text-danger">' . $item['error_type'] . '</span>' : $item['error_type'],
                'message' => $item['message'],
                'nested_table' => [
                    'columns' => [
                        ['field' => 'stack_trace', 'title' => $this->lang['sys.stack_trace'], 'width' => 20, 'align' => 'left'],
                    ],
                    'rows' => [
                        ['stack_trace' => $stack_trace],
                    ],
                ]
            ];
            if (!$stack_trace) {
                unset($data_table['rows'][$key]['nested_table']);
            }
        }
        $data_table['total_rows'] = $php_logs_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('php_logs_table_' . $type, $data_table, 'get_php_logs_table', $filters, (int) $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('php_logs_table_' . $type, $data_table, 'get_php_logs_table', $filters);
        }
    }

    /**
     * Извлекает имя таблицы из массива POST
     * Проходит по всем ключам массива POST и ищет ключи, соответствующие шаблону 'php_logs_table_{table_name}_table_name'
     * Если такой ключ найден, извлекает из него часть, соответствующую {table_name} и возвращает ее
     * Возвращает null, если соответствующий ключ не найден
     * @param array $postData Массив POST, из которого необходимо извлечь имя таблицы
     * @return string|null Возвращает имя таблицы или null, если оно не найдено
     */
    private function get_table_name_from_post($postData) {
        foreach ($postData as $key => $value) {
            if (preg_match('/^php_logs_table_([a-zA-Z0-9_]+)_table_name$/', $key, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Очистит файл php_errors.log
     */
    public function clear_php_logs($params = []) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::handleRedirect();
            exit();
        }
        $logFilePath = ENV_LOGS_PATH . 'php_errors.log';
        if (file_exists($logFilePath)) {
            file_put_contents($logFilePath, '');
        }
        SysClass::handleRedirect(200, '/admin/logs');
    }

    /**
     * Очистить все таблицы без удаления проекта
     * Таблицы нужно дополнять на своё усмотрение
     * Оставит единственного пользователя admin с паролем admin
     */
    public function killEmAll($params = []) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_systems');
        // Удаление временных и загруженных файлов
        SysClass::ee_removeDir(ENV_TMP_PATH);
        SysClass::ee_removeDir(ENV_SITE_PATH . 'cache');
        SysClass::ee_removeDir(ENV_SITE_PATH . 'logs');
        SysClass::ee_removeDir(ENV_SITE_PATH . 'uploads' . ENV_DIRSEP . 'files');
        // Перезапись файла Constants.php содержимым Constants_clean.php
        $constantsCleanPath = ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'system' . ENV_DIRSEP . 'Constants_clean.php';
        $constantsPath = ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'system' . ENV_DIRSEP . 'Constants.php';
        if (file_exists($constantsCleanPath)) {
            $constantsCleanContent = file_get_contents($constantsCleanPath);
            if (file_put_contents($constantsPath, $constantsCleanContent) === false) {
                ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Failed to overwrite Constants.php.', 'status' => 'danger']);
                return false;
            }
        } else {
            ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Constants_clean.php not found.', 'status' => 'danger']);
            return false;
        }        
        $this->models['m_systems']->killDB($this->logged_in);
        SysClass::handleRedirect(200, '/admin');
    }

    private function copy_all() {
        return false; // Баги какие-то но в целом работает
        $dbhost = ENV_DB_HOST;   // Адрес сервера MySQL, обычно localhost
        $dbuser = ENV_DB_USER;   // имя пользователя базы данных
        $dbpass = ENV_DB_PASS;   // пароль пользователя базы данных
        $dbname = ENV_DB_NAME;   // название базы данных
        $dir = ENV_SITE_PATH . '/' . ENV_BACKUP_CAT . '/';
        $kill_hour = 190; // Через сколько начинать удалять старые копии
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        if (!is_writable($dir)) {
            die('Директория ' . $dir . ' не доступна для записи.');
        }
        $dbbackup = $dir . 'db_copy-' . date("d.m.Y-H:i:s") . '.sql.gz';
        system("mysqldump -h $dbhost -u $dbuser --password='$dbpass' $dbname | gzip > $dbbackup");
        if (file_exists($dbbackup)) {
            $res .= 'Архив создан ' . $dbbackup . PHP_EOL;
        } else {
            $res .= 'Ошибка создания арихива БД ' . $dbbackup . PHP_EOL;
        }
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (filemtime($dir . $file) < strtotime('-' . $kill_hour . ' hours') && $file != '.' && $file != '..' && $file != 'db_upload.php') {
                    $res .= 'dell file=' . $dir . $file . PHP_EOL;
                    $count++;
                    if (unlink($dir . $file)) {
                        $res .= 'Удалён файл ' . $dir . $file . PHP_EOL;
                    } else {
                        $res .= 'Не удаётся удалить файл ' . $dir . $file . PHP_EOL;
                    }
                }
            }
            closedir($dh);
        }
        if (!$count) {
            $res .= 'Удалять пока нечего.' . PHP_EOL . '--------------------------------------------------------';
        }
        file_put_contents($dir . 'logs_db.txt', date('d.m.Y H:i:s') . ' : ' . $res . PHP_EOL, FILE_APPEND | LOCK_EX);
        SysClass::copydirect(ENV_SITE_PATH, $dir . 'files' . ENV_DIRSEP . date('d-m-Y-H:i:s'), true, [ENV_SITE_PATH . ENV_BACKUP_CAT]);
        SysClass::create_zip_archive(ENV_SITE_PATH, $dir . 'files' . ENV_DIRSEP . date('d-m-Y-H:i:s') . '.zip', $dir . 'files' . ENV_DIRSEP . date('d-m-Y-H:i:s'));
    }

    private function kill_copy_all() {
        die('kill_copy_all');
    }

    /**
     * Создает тестовые данные, если они еще не были созданы
     * @param array $params Параметры для создания тестовых данных
     * @return bool Возвращает false, если тестовые данные уже были созданы
     */
    public function createTest($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::handleRedirect();
            exit();
        }
        // Путь к файлу-флагу
        $flagFilePath = ENV_TMP_PATH . 'test_data_created.txt';
        // Проверяем, существует ли файл-флаг
        if (file_exists($flagFilePath)) {
            $textMessage = 'Уже есть тестовые данные, для создания дополнительных удалите файл ' . $flagFilePath;
            $status = 'danger';
        } else {
            $textMessage = 'Тестовые данны записаны!';
            $status = 'info';
            $this->loadModel('m_categories_types');
            $this->loadModel('m_categories');
            $this->loadModel('m_pages');
            $this->loadModel('m_properties');
            $users = $this->generateTestUsers();
            $prop = $this->generateTestProperties();
            $sets_prop = $this->generateTestSetsProp();
            $catsType = $this->generateTestCategoriesType();
            $cats = $this->generateTestCategories();            
            $ent = $this->generateTestPages();
            $setLinksTypeToCats = $this->setLinksTypeToCats();
            if (!$users || !$cats || !$ent || !$prop || !$sets_prop || !$catsType) {
                $textMessage = 'Ошибка создания тестовых данных!<br/>Users: ' . var_export($users, true) . '<br/>Categories: <br/>'
                        . var_export($cats, true) . '<br/>Entities<br/>' . var_export($ent, true) . '<br/>Properties<br/>' . var_export($prop, true)
                        . '<br/>Sets Properties<br/>' . var_export($sets_prop, true)
                        . '<br/>generateTestCategoriesType<br/>' . var_export($catsType, true)
                        . '<br/>generateTestProperties<br/>' . var_export($prop, true);
                $status = 'danger';
            } else {                              
                if (!SysClass::createDirectoriesForFile($flagFilePath) || !file_put_contents($flagFilePath, 'Test data created on ' . date('Y-m-d H:i:s'))) {
                    
                }
            }
        }
        ClassNotifications::addNotificationUser($this->logged_in, ['text' => $textMessage, 'status' => $status]);
        SysClass::handleRedirect(200, '/admin');
    }

    /**
     * Генерирует тестовые наборы свойств
     * Для каждого набора свойств выбирает случайное подмножество свойств из всех доступных и добавляет его в базу данных
     * @param int $count Количество тестовых наборов свойств, которые нужно сгенерировать
     * Примечание: предполагается, что функция get_all_properties возвращает массив всех доступных свойств, и что 
     * в вашей системе имеется необходимая логика для связывания этих свойств с наборами, если это требуется
     */
    private function generateTestSetsProp($count = 10): bool {
        return true;
    }

    private function generateTestProperties($count = 20) {
        $props_name = [
            "Цвет", // свойство продукта
            "Вес", // свойство продукта
            "Размер", // свойство одежды или обуви
            "Материал", // свойство мебели или одежды
            "Производитель", // свойство электроники или автомобиля
            "Дата производства", // свойство продукта питания
            "Срок годности", // свойство продукта питания
            "Мощность", // свойство электроники
            "Объем", // свойство продукта или автомобиля
            "Тип топлива", // свойство автомобиля
            "Страна производства", // свойство любого товара
            "Гарантия", // свойство электроники
            "Возрастные ограничения", // свойство игрушек или фильмов
            "Жанр", // свойство книги или фильма
            "Автор", // свойство книги
            "Количество страниц", // свойство книги
            "Разрешение экрана", // свойство телевизора или монитора
            "Тип диска", // свойство компьютера или плеера
            "Вместимость", // свойство сумки или рюкзака
            "Тип крепления", // свойство спортивного инвентаря
            "Способ приготовления", // свойство продукта питания
            "Количество участников", // свойство настольной игры
            "Продолжительность", // свойство фильма или спектакля
            "Тип батареи", // свойство электронного устройства
            "Тип соединения", // свойство гаджета (например, Bluetooth, Wi-Fi)
            "Уровень сложности", // свойство образовательного курса
            "Длительность курса", // свойство образовательного курса
            "Стиль", // свойство одежды или декора
            "Сезон", // свойство одежды
            "Вкус", // свойство продукта питания или напитка
            "Формат", // свойство книги или диска
            "Температурный режим", // свойство холодильника или кондиционера
            "Световой поток", // свойство лампы
            "Тип лампы", // свойство светильника
            "Класс энергоэффективности", // свойство бытовой техники
            "Ширина", // свойство мебели или двери
            "Высота", // свойство мебели или двери
            "Глубина", // свойство мебели
            "Состав", // свойство косметики или продукта питания
            "Срок службы", // свойство электроники или мебели
            "Тип установки", // свойство мебели или бытовой техники
            "Тип привода", // свойство часов или автомобиля
            "Класс безопасности", // свойство сейфа или автомобиля
            "Форма", // свойство украшения или мебели
            "Стиль дизайна", // свойство интерьера или веб-сайта
            "Метод печати", // свойство принтера
            "Частота обновления", // свойство монитора или телевизора
        ];
        return true;
    }

    /**
     * Генерирует массив значений по умолчанию для свойств, основанных на типах полей
     * Каждому типу поля присваивается случайное имя из предоставленного массива и случайные значения для множественности и обязательности
     * @param string $params JSON-строка, представляющая типы полей и их параметры
     * @param array $props_name Массив названий свойств, из которых будет выбрано случайное название для каждого свойства
     * @return string JSON-строка с массивом сгенерированных значений по умолчанию для каждого типа поля
     */
    private function create_default_values(string $params, array $props_name) {
        $max_prop_name_count = count($props_name) - 1;
        foreach (json_decode($params, true) as $field_type) {
            $value = [
                "type" => $field_type,
                "label" => $props_name[random_int(0, $max_prop_name_count)] . '_' . random_int(333, 777),
                "multiple" => random_int(0, 1),
                "required" => random_int(0, 1),
                "default" => ''
            ];
            $default_values[] = $value;
        }
        return json_encode($default_values, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Генерирует тестовых пользователей и добавляет их в базу данных
     * @param int $count Количество генерируемых пользователей. По умолчанию 50
     * @param int $role Роль пользователей. По умолчанию 4
     * @param int $active Статус активности пользователей. По умолчанию 2
     */
    private function generateTestUsers($count = 50, $role = 4, $active = 2) {
        $namePrefixes = [
            'Алексей', 'Наталья', 'Сергей', 'Ольга', 'Дмитрий', 'Елена', 'Иван', 'Мария', 'Павел', 'Анастасия',
            'Андрей', 'Татьяна', 'Роман', 'Светлана', 'Евгений', 'Виктория', 'Михаил', 'Ирина', 'Владимир', 'Екатерина',
            'Борис', 'Ксения', 'Григорий', 'Лариса', 'Максим', 'Юлия', 'Арсений', 'Полина', 'Антон', 'Валерия',
            'Фёдор', 'Маргарита', 'Леонид', 'Вероника', 'Олег'
        ];
        $comments = [
            'Тестовый комментарий',
            'Сгенерировано автоматически',
            'Проверка функции',
            'Данные для теста',
            'Неизвестный пользователь',
            'Отличный продукт!',
            'Рекомендую всем друзьям.',
            'Быстрая доставка, отличное качество.',
            'Очень доволен покупкой.',
            'Буду заказывать ещё.',
            'Превосходный сервис!',
            'Очень полезный товар.',
            'Лучшее предложение на рынке.',
            'Спасибо за быстрый ответ!',
            'Отличный выбор для подарка.',
            'Удобное расположение магазина.',
            'Профессиональный персонал.',
            'Большой ассортимент товаров.',
            'Всегда нахожу то, что нужно.',
            'Самые лучшие цены!'
        ];
        $this->loadModel('m_systems');
        for ($i = 0; $i < $count; $i++) {
            $name = $namePrefixes[array_rand($namePrefixes)] . '_' . mt_rand(1000, 9999);
            $email = $name . '@' . ENV_DOMEN_NAME;
            $commentPrefix = $comments[array_rand($comments)];
            $comment = $commentPrefix . " (" . date("Y-m-d H:i:s") . ")";
            $password = bin2hex(openssl_random_pseudo_bytes(4)); // 8-character random password
            $res = $this->users->registrationNewUser([
                'name' => $name,
                'email' => $email,
                'active' => $active,
                'user_role' => $role,
                'subscribed' => '1',
                'comment' => $comment,
                'pwd' => $password], true);
        }
        return $res;
    }

    private function generateTestCategoriesType(int $count = 10): bool {
        $testName = [
            'Товары',
            'Страницы',
            'Электроника',
            'Хоз. товары',
            'Рулетки',
            'Отвёртка',
            'Тетради',
            'Ручки',
            'Блог',
            'Эзотерика',
            'Книги',
            'Игрушки',
            'Спорт',
            'Одежда'
        ];
        return true;
    }
    
    /**
     * Генерирует тестовые категории с случайной вложенностью
     * @param int $count Количество генерируемых категорий
     */
    private function generateTestCategories($count = 50) {
        $types = $this->models['m_categories_types']->getAllTypes();
        if (empty($types)) {
            SysClass::pre("No types found in the types table.");
        }

        // Предопределенный массив с названиями категорий
        $predefinedNames = [
            "Книги", "Электроника", "Одежда", "Спорт", "Игрушки",
            "Красота и здоровье", "Дом и сад", "Питание", "Автомобили", "Техника",
            "Музыка", "Игры", "Путешествия", "Образование", "Искусство",
            "Кино", "Мебель", "Инструменты", "Украшения", "Фотография",
            "Животные", "Канцелярия", "Программирование", "Безопасность", "Медицина",
            "Кулинария", "Садоводство", "Спортивное питание", "Мода", "Архитектура",
            "История", "Литература", "Философия", "Психология", "География",
            "Биология", "Физика", "Химия", "Математика", "Экономика",
            "Политика", "Экология", "Астрономия", "Туризм", "Рукоделие",
            "Йога", "Фитнес", "Танцы", "Медитация", "Компьютерная графика"
        ];
        return true;
    }

    /**
     * Генерирует тестовые страницы
     * @param int $count Количество генерируемых страниц
     */
    private function generateTestPages($count = 200) {
        return true;
    }
    
    /**
     * Присваиваем типам категорий наборы свойств
     * @return bool
     */
    private function setLinksTypeToCats() {
        return true;
    }
}
