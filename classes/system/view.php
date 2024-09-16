<?php

namespace classes\system;

/**
 * Класс представлений
 * Устанавливает переменные и загружает шаблоны представления.
 */
class View {

    private array $vars = [];
    private $templateEngine = 'plain'; // Текущий шаблонизатор (может быть 'plain', 'smarty', 'twig')

    // Установка шаблонизатора
    public function setTemplateEngine(string $engine): void {
        $this->templateEngine = $engine;
    }

    /**
     * Устанавливает переменную представления.
     * @param string $varname Имя переменной.
     * @param mixed $value Значение переменной.
     * @param bool $overwrite Флаг, указывающий на возможность перезаписи переменной.
     * @return bool Возвращает true, если переменная установлена.
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
            trigger_error('Переменная `' . $varname . '`. Уже установлена, перезапись не разрешена.' . $add_trace, E_USER_NOTICE);
            return false;
        }
        $this->vars[$varname] = $value;
        return true;
    }

    /**
     * Получает значение установленной переменной представления.
     * @param string $varname Имя переменной.
     * @return mixed Значение переменной или FALSE, если переменная не установлена.
     */
    public function get(string $varname): mixed {
        return $this->vars[$varname] ?? false;
    }

    /**
     * Возвращает все установленные переменные представления.
     * @return array Ассоциативный массив всех переменных представления.
     */
    public function getVars(): array {
        return $this->vars;
    }    
    
    /**
     * Удаляет переменную представления.
     * @param string $varname Имя переменной.
     */
    public function remove(string $varname): void {
        unset($this->vars[$varname]);
    }

    public function read(string $name, string $add_path = '', bool $full_path = false): string {
        $path = $full_path ? $name : dirname(ENV_CONTROLLER_PATH) . ENV_DIRSEP . 'views' . ENV_DIRSEP . $add_path . $name;
        $content = '';

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
                    return 'Шаблон `' . $name . '` не существует. Путь поиска: ' . $path;
                }
                extract($this->vars);
                ob_start();
                try {
                    include_once($path . '.php');
                } catch (Throwable $e) {
                    ob_end_clean();
                    throw $e;
                }
                $content = ob_get_clean();
                break;
        }

        return $content;
    }

}