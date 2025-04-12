<?php

namespace classes\helpers;

use PHPMailer\PHPMailer\PHPMailer;
use classes\system\SysClass;
use classes\system\Constants;

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
     * таких как использование SMTP, настройки сервера, авторизации и безопасности
     * В зависимости от конфигурации ENV_SMTP, конфигурирует PHPMailer для отправки 
     * писем через SMTP или стандартную функцию mail(). Также устанавливает базовые 
     * параметры, такие как кодировка, адрес отправителя и адрес для ответов.
     */
    public function __construct() {
        $this->mail_class = new PHPMailer(true); // Enable exceptions
        try {
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
        } catch (\Exception $e) {
            error_log("ClassMail::__construct() - PHPMailer init error: " . $e->getMessage());
            throw new RuntimeException("Ошибка инициализации PHPMailer: " . $e->getMessage());
        }
    }

    /**
     * Отправляет электронное письмо
     * @param string $to Адрес электронной почты получателя или ID пользователя
     * @param string $subject Тема письма - может определяться динамически
     * @param array $fields Данные для шаблона
     * @param string|false $template Имя файла шаблона (без расширения), если требуется использовать шаблон
     * @return bool Возвращает true в случае успеха
     * @throws InvalidArgumentException Если адрес электронной почты невалиден
     * @throws RuntimeException Если отправка письма не удалась
     */
    private function send(mixed $to, string $subject = '', array $fields = [], string|false $template = false): bool {
        try {
            $email = $this->resolveEmail($to);
            if ($template) {
                list($body, $subjectTemplate) = $this->processTemplate($template, $fields);
                if (!$body) {
                    return false;
                }
            } elseif (!empty($fields)) {
                $body = var_export($fields, true);
            }
            if (!SysClass::validEmail($email)) {
                error_log("ClassMail::send() - Invalid email address: $email");
                return false;
            }
            $subject = !empty($subject) ? $subject : $subjectTemplate;
            $this->prepareEmail($email, $subject, $body);
            $this->mail_class->send();
            return true;
        } catch (\Exception $e) {
            error_log("ClassMail::send() - Send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Обрабатывает HTML-шаблон, заменяя указанные плейсхолдеры на значения из массива данных
     * Плейсхолдеры в шаблоне должны быть обозначены двойными фигурными скобками, например, {{placeholder}}
     * Метод читает файл HTML-шаблона, находит плейсхолдеры и заменяет их на соответствующие значения из массива данных
     * Если файл шаблона не найден, выбрасывается исключение
     * @param string $template Имя шаблона, который будет обработан
     * @param array $fields Ассоциативный массив данных для шаблона
     * @return string Обработанный HTML-код, готовый к отправке в виде тела электронного письма
     */
    protected function processTemplate(string $template, array $fields = []): array|bool {
        $objectModelEmailTemplates = SysClass::getModelObject('admin', 'm_email_templates');
        if (is_numeric($template)) {
            $templateData = $objectModelEmailTemplates->getEmailTemplateData($template);
        } else {
            $templateData = $objectModelEmailTemplates->getEmailTemplateDataByName($template);
        }
        if (empty($templateData)) {
            return false;
        }
        $body = $this->replaceCodeAndSnippets($templateData['body']);
        $subject = $this->replaceCodeAndSnippets($templateData['subject']);
        foreach ($fields as $fieldName => $fieldValue) {
            $body = str_replace('[' . $fieldName . ']', $fieldValue, $body);
            $subject = str_replace('[' . $fieldName . ']', $fieldValue, $subject);
        }
        return [$body, $subject];
    }

    /**
     * Метод для отправки писем
     * @param string $to Адрес электронной почты получателя или ID пользователя
     * @param string $subject Тема письма
     * @param array $fields Данные для шаблона
     * @param string|false $template Имя файла шаблона (без расширения), если требуется использовать шаблон
     * @return bool Возвращает true в случае успеха
     */
    public static function sendMail(mixed $to, string $subject = '', string|false $template = false, array $fields = []): bool {
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

    /**
     * Добавляет вложение к письму
     * @param string $path Путь к файлу
     * @param string $name Отображаемое имя файла (необязательно)
     * @return void
     */
    public function addAttachment(string $path, string $name = ''): void {
        try {
            $this->mail_class->addAttachment($path, $name);
        } catch (\Exception $e) {
            error_log("ClassMail::addAttachment() - Attachment error: " . $e->getMessage());
            throw new RuntimeException("Ошибка добавления вложения: " . $e->getMessage());
        }
    }

    /**
     * Заменит сниппеты и глобальные переменные на значения
     * @param string $body
     * @return string
     */
    public function replaceCodeAndSnippets(string $body): string {
        $objectModelEmailTemplates = SysClass::getModelObject('admin', 'm_email_templates');
        $maxIterations = 10; // Ограничиваем количество итераций для вложенных сниппетов
        $iteration = 0;
        do {
            $previousBody = $body;
            $body = $objectModelEmailTemplates->replaceSnippets($body);
            preg_match_all('/\{\{([^}]+)\}\}/', $body, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $constant) {
                    if (isset(Constants::PUBLIC_CONSTANTS[$constant]) && defined($constant)) {
                        $constantValue = constant($constant);
                        if ($constantValue !== '{{' . $constant . '}}') { // Защита от бесконечной замены
                            $body = str_replace('{{' . $constant . '}}', $constantValue, $body);
                        }
                    }
                }
            }
            $iteration++;
            if ($iteration >= $maxIterations) {
                break;
            }
        } while ($body !== $previousBody);

        return htmlspecialchars_decode($body);
    }
}
