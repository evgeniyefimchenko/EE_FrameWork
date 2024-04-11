<?php

namespace classes\helpers;

use PHPMailer\PHPMailer\PHPMailer;
use classes\system\SysClass;

/**
 * Класс для работы с почтой.
 * Подключается в необходимых моделях и используется для совместимости и масштабируемости с разными классами отправки почты.
 */
class ClassMail {

    private PHPMailer $mail_class;

    /**
     * Конструктор класса.
     * Создает и инициализирует экземпляр PHPMailer, устанавливая основные параметры 
     * для отправки писем. Конфигурация PHPMailer зависит от глобальных настроек проекта,
     * таких как использование SMTP, настройки сервера, авторизации и безопасности.
     * В зависимости от конфигурации ENV_SMTP, конфигурирует PHPMailer для отправки 
     * писем через SMTP или стандартную функцию mail(). Также устанавливает базовые 
     * параметры, такие как кодировка, адрес отправителя и адрес для ответов.
     */
    public function __construct() {        
        $this->mail_class = new PHPMailer();        
        $this->mail_class->CharSet = 'UTF-8';
        if (ENV_SMTP) {            
            $this->mail_class->isSMTP();
            $this->mail_class->SMTPAuth = true;
            $this->mail_class->SMTPDebug = 0;
            $this->mail_class->SMTPSecure = "ssl";
            $this->mail_class->Host = ENV_SMTP_SERVER;
            $this->mail_class->Port = ENV_SMTP_PORT;
            $this->mail_class->Username = ENV_SMTP_LOGIN;
            $this->mail_class->Password = ENV_SMTP_PASSWORD;
        } else {            
            $this->mail_class->isMail();            
        }
        $this->mail_class->setFrom(ENV_SITE_EMAIL, ENV_SITE_NAME);        
        $this->mail_class->addReplyTo(ENV_ADMIN_EMAIL);        
    }

    /**
     * Отправляет электронное письмо
     * @param string $to Адрес электронной почты получателя или ID пользователя
     * @param string $subject Тема письма
     * @param array $fields Данные для шаблона
     * @param string|false $template Имя файла шаблона (без расширения), если требуется использовать шаблон
     * @return bool Возвращает true в случае успеха
     * @throws InvalidArgumentException Если адрес электронной почты невалиден
     * @throws RuntimeException Если отправка письма не удалась
     */
    private function send(mixed $to, string $subject, array $fields, string|false $template = false): bool {        
        $email = $this->resolveEmail($to);
        if ($template) {
            $body = $this->processTemplate($template, $fields);
            if (!$body)
                return false;
        } else {
            $body = var_export($fields, true);
        }
        if (!SysClass::validEmail($email)) {
            pre_file('email_errors', 'ClassMail', "Invalid email address: $email");
            return false;
        }
        $this->prepareEmail($email, $subject, $body);
        $res = $this->mail_class->send() ? true : $this->mail_class->ErrorInfo;
        if ($res) {
            return true;
        } else {
            pre_file('email_errors', 'ClassMail', 'Send error', var_export($res, true));
            return false;
        }
    }

    // Статический метод для отправки писем
    public static function send_mail(mixed $to, string $subject, array $fields, string|false $template = false): bool {
        $mailer = new self();        
        return $mailer->send($to, $subject, $fields, $template);
    }

    /**
     * Определяет адрес электронной почты на основе переданного параметра
     * @param string $to Адрес электронной почты или ID пользователя
     * @return string Адрес электронной почты
     */
    protected function resolveEmail(mixed $to): string {
        return is_numeric($to) ? (new Users([$to]))->data['email'] : $to;
    }

    /**
     * Обрабатывает HTML-шаблон, заменяя указанные плейсхолдеры на значения из массива данных
     * Плейсхолдеры в шаблоне должны быть обозначены двойными фигурными скобками, например, {{placeholder}}
     * Метод читает файл HTML-шаблона, находит плейсхолдеры и заменяет их на соответствующие значения из массива данных
     * Если файл шаблона не найден, выбрасывается исключение
     * @param string $template Имя шаблона, который будет обработан. Путь к файлу шаблона строится на основе этого имени
     * @param array $data Ассоциативный массив данных, где ключ соответствует имени плейсхолдера в шаблоне, а значение будет вставлено вместо плейсхолдера
     * @return string Обработанный HTML-код, готовый к отправке в виде тела электронного письма
     * @throws RuntimeException Если файл шаблона не существует, выбрасывается исключение с описанием ошибки
     */
    protected function processTemplate(string $template, array $data): string {
        // Добавление предопределённых констант в массив данных
        $data = array_merge([
            'ENV_URL_SITE' => ENV_URL_SITE,
            'ENV_SITE_NAME' => ENV_SITE_NAME,
            'ENV_SITE_AUTHOR' => ENV_SITE_AUTHOR,
            'YEAR' => date('Y')
                ], $data);        
        $filename[] = ENV_EMAIL_TEMPLATE . ENV_DIRSEP . 'general' . ENV_DIRSEP . 'header.html';
        $filename[] = ENV_EMAIL_TEMPLATE . ENV_DIRSEP . $template . ENV_DIRSEP . 'body' . ENV_DIRSEP . $template . '.html';
        $filename[] = ENV_EMAIL_TEMPLATE . ENV_DIRSEP . 'general' . ENV_DIRSEP . 'footer.html';
        $contents = '';
        foreach ($filename as $name) {
            if (!file_exists($name)) {
                pre_file('email_errors', 'ClassMail', "Шаблон письма не найден: $name");
                return false;
            }
            $contents .= file_get_contents($name);
        }
        foreach ($data as $key => $value) {
            $contents = str_replace('{{' . $key . '}}', $value, $contents);
        }
        return $contents;
    }

    /**
     * Подготавливает и настраивает письмо к отправке
     * @param string $email Адрес получателя
     * @param string $subject Тема письма
     * @param string $body Тело письма
     */
    protected function prepareEmail(string $email, string $subject, string $body): void {
        $this->mail_class->addAddress($email);
        $this->mail_class->Subject = $subject;
        $this->mail_class->msgHTML($body);        
    }

}
