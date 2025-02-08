<?php

namespace classes\system;

/**
 * Класс представлений
 * Устанавливает переменные и загружает шаблоны представления
 */
class View {

    private array $vars = [];
    private $templateEngine = 'plain'; // Текущий шаблонизатор (может быть 'plain', 'smarty', 'twig')

    /**
     * Экземпляр менеджера кеша
     */
    private $cacheManager = NULL;

    /**
     * Имя шаблона представления
     */
    private $templateName = '';
    
    /**
     * Не кешируемые блоки
     */
    private $nonCachedBlocks = [];
    
    function __construct() {
        if (ENV_CACHE) {
            $this->cacheManager = new CacheManager(ENV_CACHE_LIFETIME);
        }
    }

    // Установка шаблонизатора
    public function setTemplateEngine(string $engine): void {
        $this->templateEngine = $engine;
    }

    /**
     * Устанавливает переменную представления
     * @param string $varname Имя переменной
     * @param mixed $value Значение переменной
     * @param bool $overwrite Флаг, указывающий на возможность перезаписи переменной
     * @return bool Возвращает true, если переменная установлена
     */
    public function set(string $varname, mixed $value, bool $overwrite = false): bool {
        if (isset($this->vars[$varname]) && !$overwrite) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach ($trace as $item) {
                $formattedTrace[] = [
                    'function' => $item['function'] ?? 'N/A',
                    'line' => $item['line'] ?? 'N/A',
                    'file' => $item['file'] ?? 'N/A',
                    'class' => $item['class'] ?? 'N/A',
                    'type' => $item['type'] ?? 'N/A',
                    'object' => $item['object'] ?? 'N/A',
                ];
            }
            $add_trace = PHP_EOL . 'Полный стек вызовов: ' . print_r($formattedTrace, true) . PHP_EOL;
            SysClass::pre('Переменная `' . $varname . '`. Уже установлена, перезапись не разрешена.' . $add_trace);
            return false;
        }
        $this->vars[$varname] = $value;
        return true;
    }

    /**
     * Получает значение установленной переменной представления
     * @param string $varname Имя переменной
     * @return mixed Значение переменной или FALSE, если переменная не установлена
     */
    public function get(string $varname): mixed {
        return $this->vars[$varname] ?? false;
    }

    /**
     * Возвращает все установленные переменные представления
     * @return array Ассоциативный массив всех переменных представления
     */
    public function getVars(): array {
        return $this->vars;
    }

    /**
     * Удаляет переменную представления
     * @param string $varname Имя переменной
     */
    public function remove(string $varname): void {
        unset($this->vars[$varname]);
    }

    /**
     * Выполняет и возвращает содержимое указанного шаблона
     * В зависимости от используемого шаблонизатора (PHP, Smarty, Twig), функция загружает и обрабатывает шаблон
     * Если указан полный путь к шаблону, он используется напрямую. Если путь неполный, то шаблон ищется в каталоге views
     * Поддерживаются следующие шаблонизаторы:
     * - **PHP**: По умолчанию используется встроенный PHP для рендеринга шаблонов
     * - **Smarty**: Поддержка шаблонизатора Smarty (если настроен)
     * - **Twig**: Поддержка шаблонизатора Twig (если настроен)
     * Если файл шаблона не найден или возникает ошибка во время его выполнения, генерируется исключение
     * @param string $templateName Название шаблона (имя файла шаблона)
     * @param bool $cache Использовать кеширование блока
     * @param string $add_path Дополнительный путь к шаблону (если требуется), который будет добавлен к основному пути
     * @param bool $full_path Указывает, является ли переданный путь к шаблону полным
     *                        Если true, то путь будет использоваться напрямую
     *                        Если false, будет построен путь из каталога views
     * @return string Возвращает содержимое шаблона после его выполнения
     * @throws \classes\system\Throwable Исключение выбрасывается, если файл шаблона не найден или произошла ошибка при его выполнении
     * Пример использования:
     * ```php
     * $content = $view->read('header');  // Загрузить шаблон header.php из каталога views
     * $content = $view->read('email', 'mail_templates/');  // Загрузить шаблон email.php из каталога views/mail_templates/
     * $content = $view->read('/var/www/html/custom_view.php', '', true);  // Загрузить шаблон по полному пути
     * ```
     */
    public function read(string $templateName, bool $cache = true, string $add_path = '', bool $full_path = false): string {
        $content = '';
        // Никогда не кешировать админскую часть
        if (ENV_CONTROLLER_FOLDER == 'admin') {
            $cache = false;
            $this->cacheManager = NULL;
        }
        $this->templateName = $templateName;
        $path = $full_path ? $this->templateName : dirname(ENV_CONTROLLER_PATH) . ENV_DIRSEP . 'views' . ENV_DIRSEP . $add_path . $this->templateName;
        if ($this->cacheManager && $cache) {
            if ($cachedPage = $this->cacheManager->isCached($path)) { // Вернёт закешированный блок
                $content = $this->cacheManager->getCache($cachedPage);
            }
        }
        if ($content) { // Есть кешированные данные заменяем все динамические блоки
            return $this->replaceDynamicBlocksContents($content);
        }
        switch ($this->templateEngine) {
            case 'smarty':
                // Здесь должен быть код для обработки шаблона с использованием Smarty
                // Пример:
                // $smarty = new Smarty;
                // $smarty->assign($this->vars);
                // $content = $smarty->fetch($path . '.tpl');
                break;

            case 'twig':
                // Здесь должен быть код для обработки шаблона с использованием Twig
                // Пример:
                // $loader = new \Twig\Loader\FilesystemLoader(dirname($path));
                // $twig = new \Twig\Environment($loader);
                // $content = $twig->render(basename($path) . '.twig', $this->vars);
                break;
            default:
                // Обработка стандартного PHP-шаблона                
                if (!file_exists($path . '.php')) {
                    return 'Шаблон `' . $this->templateName . '` не существует. Путь поиска: ' . $path;
                }
                extract($this->vars);
                ob_start();
                try {
                    if ($this->cacheManager && !$cache) { // Non-cacheable block
                        echo '<!-- START DYNAMIC BLOCK ' . $this->templateName . ' -->';
                        include_once($path . '.php');
                        echo '<!-- END DYNAMIC BLOCK ' . $this->templateName . ' -->';
                    } elseif (!$content) { // Cached block or not use cache
                        include_once($path . '.php');
                    }
                } catch (Throwable $e) {
                    ob_end_clean();
                    throw $e;
                }
                $content = ob_get_clean();
                if ($this->cacheManager && !$cache) { // Записали некешированные блоки в память
                    $this->nonCachedBlocks[$this->templateName] = $content;
                }
                break;
        }
        if ($this->cacheManager && $cache) {
            $this->cacheManager->setCache($this->clearDynamicBlockContent($content), $path);
        }
        return $content;
    }

    /**
     * Заменяет динамические блоки в строке контента на значения из переданного массива переменных
     * Функция ищет блоки, обрамленные тегами <!-- START DYNAMIC BLOCK v_{block_name} --> и <!-- END DYNAMIC BLOCK v_{block_name} -->.
     * Затем заменяет их содержимое на значения, соответствующие ключам в массиве $vars
     * @param string $contents Строка контента, в которой нужно заменить динамические блоки
     * @return string Строка контента с замененными динамическими блоками
     */
    private function replaceDynamicBlocksContents($contents) {
        foreach ($this->nonCachedBlocks as $blockName => $replacementContent) {
            if (is_string($replacementContent)) {
                $startMarker = "<!-- START DYNAMIC BLOCK {$blockName} -->";
                $endMarker = "<!-- END DYNAMIC BLOCK {$blockName} -->";
                $pattern = "/{$startMarker}(.*?){$endMarker}/si";
                $contents = preg_replace($pattern, $startMarker . $replacementContent . $endMarker, $contents);
            }
        }
        return $contents;
    }
 
    private function clearDynamicBlockContent($content) {
        if (count($this->nonCachedBlocks)) {
            foreach ($this->nonCachedBlocks as $name => $saveContent) {
                $startMarker = "<!-- START DYNAMIC BLOCK {$name} -->";
                $endMarker = "<!-- END DYNAMIC BLOCK {$name} -->";
                $pattern = "/{$startMarker}(.*?){$endMarker}/si";
                $content = preg_replace($pattern, $startMarker . $endMarker, $content);
            }
        }
        return $content;
    }    
    
}
