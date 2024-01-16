<?php

namespace classes\helpers;

use classes\system\SysClass;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Класс для работы с почтой.
 * Подключается в необходимых моделях и используется для совместимости и масштабируемости с разными классами отправки почты.
 */
class ClassMail {

    private PHPMailer $mail_class;

    /**
     * Конструктор класса.
     * Инициализирует объект PHPMailer и устанавливает необходимые параметры.
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
     * Отправка письма от имени сайта.
     *
     * @param int|string $to - кому, адрес почты или ID пользователя.
     * @param string $subject - заголовок.
     * @param string|array $body - тело письма.
     * @param string|false $template - название шаблона письма, должно соответствовать его папке в ENV_EMAIL_TEMPLATE.
     *
     * @return string|true - сообщение об ошибке или TRUE.
     */
    public function send_mail(string $to, string $subject, string|array $body, string|false $template = false): mixed {
        $email = is_numeric($to) ? (new Users([$to]))->data['email'] : $to;

        if ($template && file_exists($filename = ENV_EMAIL_TEMPLATE . ENV_DIRSEP . $template . ENV_DIRSEP . 'mail.html')) {
            $templ_content = file_get_contents($filename);
            if (is_array($body)) {
                foreach ($body as $key => $value) {
                    $templ_content = str_replace($key, $value, $templ_content);
                }
                $body = $templ_content;
            }
        }

        $this->mail_class->addAddress($email, ENV_ADMIN_EMAIL);
        $this->mail_class->Subject = $subject;
        $this->mail_class->msgHTML($body);

        return $this->mail_class->send() ? true : $this->mail_class->ErrorInfo;
    }

}
