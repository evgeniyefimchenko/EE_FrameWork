<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Функции работы с логами
 */
trait systems_trait {

    /**
     * Вывод страницы с логами
     */
    public function logs($params = array()) {
        $this->access = array(1);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_systems', [$this->logged_in]);
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->models['m_systems']->data;
        $this->get_user_data($user_data);
        $log_items = $this->models['m_systems']->get_general_logs();
        $get_API_logs = $this->models['m_systems']->get_API_logs();
        $text_logs = [];
        krsort($text_logs);
        foreach ($log_items as $key => $value) {
            $log_items[$key]['who'] = $this->models['m_systems']->get_text_role($value['who']);
        }

        $files = ['test', 'test2'];

        /* view */
        $this->get_standart_view();
        $this->view->set('text_logs', $text_logs);
        $this->view->set('get_API_logs', $get_API_logs);
        $this->view->set('log_items', $log_items);
        $this->view->set('files', $files);
        $this->view->set('body_view', $this->view->read('v_logs'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'logs';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Очистить все таблицы без удаления проекта.
     * Таблицы нужно дополнять на своё усмотрение.
     * Оставит единственного пользователя admin с паролем admin
     */
    public function kill_em_all($params = []) {
        $this->access = array(1);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_systems', [$this->logged_in]);
        $this->models['m_systems']->kill_db($this->logged_in);
        SysClass::return_to_main(200, '/admin');
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
     * Создает тестовые данные, если они еще не были созданы.
     * @param array $params Параметры для создания тестовых данных.
     * @return bool Возвращает false, если тестовые данные уже были созданы.
     */
    public function create_test($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::return_to_main();
            exit();
        }
        // Путь к файлу-флагу
        $flagFilePath = ENV_LOGS_PATH . '/test_data_created.txt';
        // Проверяем, существует ли файл-флаг
        if (file_exists($flagFilePath)) {
            $text_sessage = 'Уже есть тестовые данные, для создания дополнительных удалите файл ' . $flagFilePath;
            $status = 'danger';
        } else {
            $text_sessage = 'Тестовые данны записаны!';
            $status = 'info';
            // Создаем тестовые данные
            $this->load_model('m_categories');
            $this->load_model('m_categories_types');
            $this->load_model('m_entities');
            $this->load_model('m_properties');
            $users = $this->generate_test_users();
            $cats = $this->generate_test_categories();
            $ent = $this->generate_test_entities();
            $prop = $this->generate_test_properties();
            $sets_prop = $this->generate_test_sets_prop();
            if (!$users || !$cats || !$ent || !$prop || !$sets_prop) {
                $text_sessage = 'Ошибка создания тестовых данных!<br/>Users: ' . var_export($users, true) . '<br/>Categories: <br/>'
                        . var_export($cats, true) . '<br/>Entities<br/>' . var_export($ent, true) . '<br/>Properties<br/>' . var_export($prop, true)
                         . '<br/>Sets Properties<br/>' . var_export($sets_prop, true);
                $status = 'danger';
            } else {
                // Создаем файл-флаг                
                file_put_contents($flagFilePath, 'Test data created on ' . date('Y-m-d H:i:s'));
            }
        }
        Class_notifications::add_notification_user($this->logged_in, ['text' => $text_sessage, 'status' => $status]);
        SysClass::return_to_main(200, '/admin');
    }
    
    /**
     * Генерирует тестовые наборы свойств.
     * Для каждого набора свойств выбирает случайное подмножество свойств из всех доступных и добавляет его в базу данных.
     *
     * @param int $count Количество тестовых наборов свойств, которые нужно сгенерировать.
     * @return bool Возвращает true, если все тестовые наборы свойств успешно созданы, иначе возвращает false.
     *
     * Примечание: предполагается, что функция get_all_properties возвращает массив всех доступных свойств, и что 
     * в вашей системе имеется необходимая логика для связывания этих свойств с наборами, если это требуется.
     */
    private function generate_test_sets_prop($count = 10): bool {
        $all_properties = $this->models['m_properties']->get_all_properties();
        if (empty($all_properties)) {
            return false; // Нет свойств для создания наборов
        }

        for ($i = 0; $i < $count; $i++) {
            $random_keys = array_rand($all_properties, rand(1, count($all_properties))); // Получаем случайные ключи
            $random_properties = array_map(function($key) use ($all_properties) {
                return $all_properties[$key]['property_id']; // Извлекаем property_id
            }, (array)$random_keys); // Приведение к массиву нужно для случая, когда выбирается только одно свойство

            $set_name = 'Test Set ' . ($i + rand(1, 1000));
            $property_set_data = [
                'name' => $set_name,
                'description' => SysClass::generate_uuid()
            ];
            $set_id = $this->models['m_properties']->update_property_set_data($property_set_data);
            if (!$set_id) {
                return false;
            }
            $this->models['m_properties']->addPropertiesToSet($set_id, $random_properties);
        }
        return true;
    }
    
    private function generate_test_properties($count = 50) {
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
        $all_property_types = $this->models['m_properties']->get_all_property_types();
        // Смешаем массив типов свойств для случайного выбора
        shuffle($all_property_types);
        $types_count = count($all_property_types);
        $count_name = count($props_name);
        // Смешаем массивы, чтобы каждый раз генерировать разные свойства
        shuffle($props_name);
        shuffle($all_property_types);
        for ($i = 0; $i < $count; $i++) {
            $key_type = $i % $types_count;
            $property_data = [
                'name' => $props_name[rand(0, $count_name - 1)] . '_' . random_int($i, 1000000),
                'type_id' => $all_property_types[$key_type]['type_id'],
                'default_values' => $this->create_default_values($all_property_types[$key_type]['fields'], $props_name),
                'is_multiple' => random_int(0, 1),
                'is_required' => random_int(0, 1)
            ];
            $result = $this->models['m_properties']->update_property_data($property_data);
            if (!$result) {
                SysClass::pre_file('error', 'generate_test_properties');
                return $result;
            }        
        }
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
        foreach (json_decode($params, true) as $field_type => $val) {
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
     * Генерирует тестовых пользователей и добавляет их в базу данных.
     * @param int $count Количество генерируемых пользователей. По умолчанию 50.
     * @param int $role Роль пользователей. По умолчанию 4.
     * @param int $active Статус активности пользователей. По умолчанию 2.
     */
    private function generate_test_users($count = 50, $role = 4, $active = 2) {
        $namePrefixes = [
            'Алексей', 'Наталья', 'Сергей', 'Ольга', 'Дмитрий', 'Елена', 'Иван', 'Мария', 'Павел', 'Анастасия',
            'Андрей', 'Татьяна', 'Роман', 'Светлана', 'Евгений', 'Виктория', 'Михаил', 'Ирина', 'Владимир', 'Екатерина'
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
        $this->load_model('m_systems', [$this->logged_in]);
        for ($i = 0; $i < $count; $i++) {
            $name = $namePrefixes[array_rand($namePrefixes)] . '_' . mt_rand(1000, 9999);
            $email = $name . '@' . ENV_DOMEN_NAME;
            $commentPrefix = $comments[array_rand($comments)];
            $comment = $commentPrefix . " (" . date("Y-m-d H:i:s") . ")";
            $password = bin2hex(openssl_random_pseudo_bytes(4)); // 8-character random password
            $res = $this->models['m_systems']->registration_new_user([
                'name' => $name,
                'email' => $email,
                'active' => $active,
                'user_role' => $role,
                'subscribed' => '1',
                'comment' => $comment,
                'pwd' => $password
                    ], true);
        }
        return $res;
    }

    /**
     * Генерирует тестовые категории с случайной вложенностью
     * @param int $count Количество генерируемых категорий.
     * @param int $parent_depth Максимальная глубина вложенности категорий.
     */
    private function generate_test_categories($count = 20, $parent_depth = 3) {
        // Получаем список существующих типов
        $types = $this->models['m_categories_types']->get_all_types();
        if (empty($types)) {
            echo "No types found in the types table.";
            return;
        }
        // Подготовка данных для вставки
        $categoriesData = [];
        for ($i = 0; $i < $count; $i++) {
            $randomTypeKey = array_rand($types);  // выбираем случайный тип
            $randomTypeId = $types[$randomTypeKey]['type_id'];
            if (!$randomTypeId) {
                SysClass::pre(['randomTypeId', $randomTypeKey]);
            }
            $randomStatus = ['active', 'hidden', 'disabled'][rand(0, 2)];  // выбираем случайный статус
            $randomTimestamp = strtotime('now - ' . rand(0, 30) . ' days');
            $randomDate = date('Y-m-d H:i:s', $randomTimestamp);
            $add_name = SysClass::generate_uuid();
            $categoriesData[] = [
                'type_id' => $randomTypeId,
                'title' => 'Test Category ' . $add_name,
                'description' => 'Description for Test Category ' . $add_name,
                'short_description' => 'Short Description for Test Category ' . $add_name,
                'parent_id' => NULL,
                'status' => $randomStatus,
                'created_at' => $randomDate
            ];
        }
        // Вставка данных в таблицу категорий
        foreach ($categoriesData as $k => $categoryData) {
            $res = $this->models['m_categories']->update_category_data($categoryData);
            $categoriesData[$k]['category_id'] = $res;
        }
        if (!$res) {
            SysClass::pre_file('error', 'ADD test category');
            return $res;
        }
        // Обновление parent_id для половины созданных категорий
        $halfCount = intval($count / 2);
        for ($i = 0; $i < $halfCount; $i++) {
            $categoryId = $categoriesData[$i]['category_id'];  // предполагая, что category_id был сохранен при создании
            $parentId = $categoriesData[rand($halfCount, $count - 1)]['category_id'];  // выбор случайного parent_id из второй половины
            $categoryTitle = $categoriesData[$i]['title'];  // получение title
            $categoryData = [
                'category_id' => $categoryId,
                'parent_id' => $parentId,
                'title' => $categoryTitle
            ];
            if (!$categoryId) {
                SysClass::pre(['categoryData', $categoryData, $categoriesData]);
            }
            $res = $this->models['m_categories']->update_category_data($categoryData);
        }
        if (!$res) {
            SysClass::pre_file('error', 'UPDATE test category');
            return $res;
        }
        return true;
    }

    /**
     * Генерирует тестовые сущности для таблицы сущностей.
     * @param int $count Количество генерируемых сущностей.
     */
    private function generate_test_entities($count = 100) {
        // Получаем список существующих категорий
        $categories = $this->models['m_categories']->get_all_categories();
        if (empty($categories)) {
            SysClass::pre_file('error', "No categories found in the categories table.");
            return false;
        }
        // Массивы с возможными словами для названий и описаний
        $titleWords = ['Тестовая', 'Пример', 'Демонстрация', 'Образец', 'Экземпляр'];
        $descriptionWords = ['описание', 'элемент', 'сущность', 'объект', 'пример'];
        // Подготовка данных для вставки
        $entitiesData = [];
        for ($i = 0; $i < $count; $i++) {
            $randomCategoryKey = array_rand($categories);  // выбираем случайную категорию
            $randomCategoryId = $categories[$randomCategoryKey]['category_id'];
            $randomStatus = ['active', 'hidden', 'disabled'][rand(0, 2)];  // выбираем случайный статус
            // Генерация случайной даты и времени, не ранее текущего времени
            $randomTimestamp = strtotime('now + ' . rand(0, 30) . ' days');
            $randomDate = date('Y-m-d H:i:s', $randomTimestamp);
            // Генерация случайных названий и описаний
            $randomTitle = $titleWords[array_rand($titleWords)] . ' ' . $descriptionWords[array_rand($descriptionWords)];
            $randomShortDescription = 'Краткое ' . $descriptionWords[array_rand($descriptionWords)];
            $randomDescription = 'Полное ' . $descriptionWords[array_rand($descriptionWords)];
            $entitiesData[] = [
                'category_id' => $randomCategoryId,
                'status' => $randomStatus,
                'title' => $randomTitle,
                'short_description' => $randomShortDescription,
                'description' => $randomDescription,
                'created_at' => $randomDate  // добавляем случайную дату и время
            ];
        }
        // Вставка данных в таблицу сущностей
        foreach ($entitiesData as $entityData) {
            $res = $this->models['m_entities']->update_entity_data($entityData);
        }
        return $res;
    }

}
