<?php

namespace classes\system;

/**
 * Класс представлений
 * Устанавливает переменные и загружает шаблоны представления.
 */
class View {

    private array $vars = [];

    /**
     * Устанавливает переменную представления.
     * @param string $varname Имя переменной.
     * @param mixed $value Значение переменной.
     * @param bool $overwrite Флаг, указывающий на возможность перезаписи переменной.
     * @return bool Возвращает true, если переменная установлена.
     */
    public function set(string $varname, mixed $value, bool $overwrite = false): bool {
        if (isset($this->vars[$varname]) && !$overwrite) {
            trigger_error('Переменная `' . $varname . '`. Уже установлена, перезапись не разрешена.', E_USER_NOTICE);
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
     * Удаляет переменную представления.
     * @param string $varname Имя переменной.
     */
    public function remove(string $varname): void {
        unset($this->vars[$varname]);
    }

    /**
     * Загружает представление.
     * @param string $name Имя представления.
     * @param string $add_path Дополнительный путь к представлению.
     * @param bool $full_path Если true, используется полный путь.
     * @return string Содержимое загруженного представления.
     */
    public function read(string $name, string $add_path = '', bool $full_path = false): string {
        $path = $full_path ? $name : dirname(ENV_CONTROLLER_PATH) . ENV_DIRSEP . 'views' . ENV_DIRSEP . $add_path . $name . '.php';
        if (!file_exists($path)) {
            return 'Шаблон `' . $name . '` не существует. Путь поиска: ' . $path;
        }
        extract($this->vars);
        ob_start();
        try {
            include_once($path);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }

    /**
     * Проверяет, существует ли переданное представление.
     * @param string $view Имя представления.
     * @return string|bool Полный путь к представлению, если оно существует, иначе FALSE.
     */
    public function view_exists(string $view): string|bool {
        $view_file = $view . '.php';
        $path = dirname(ENV_CONTROLLER_PATH) . ENV_DIRSEP . 'views';
        return Sysclass::search_file($path, $view_file);
    }

}
