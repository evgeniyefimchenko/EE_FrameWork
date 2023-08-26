<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/*
 * Класс для работы с почтой
 * подключается в необходимых моделях
 * используется для совместимости и масштабируемости с разными классами отправки почты
 */

class Class_mail {

    private $mail_class;

    public function __construct() {
        $this->mail_class = new PHPMailer();
        $this->mail_class->CharSet = 'UTF-8';
        if (ENV_SMTP) {
            // Настройки SMTP
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
     * Отправка письма от имени сайта
     * @param str $to - кому, адресс почты или ID пользователя
     * @param str $subject - заголовок
     * @param str | array $body - тело письма
     * @param str $template название шаблона письма, должно соответствовать его папке в ENV_EMAIL_TEMPLATE
     * @return boolean - сообщение о ошибке или TRUE
     */
    public function send_mail($to, $subject, $body, $template = false) {
        if (is_numeric($to)) {
            $user = new Users([$to]);            
            $email = $user->data['email'];
        } else {
            $email = $to;
        }        
        if ($template) { // передан шаблон письма, ожидаемм в $body массив с параметрами
            $filename = ENV_EMAIL_TEMPLATE . ENV_DIRSEP . $template . ENV_DIRSEP . 'mail.html';
            if (file_exists($filename)) {
                $templ_content = file_get_contents($filename);
                if (is_array($body)) {
                    foreach ($body as $key => $value) {                        
                        $templ_content = str_replace($key, $value, $templ_content);
                    }
                    $body = $templ_content;
                }
            }
        }
        $this->mail_class->addAddress($email, 'evgeniy@efimchenko.ru');
        $this->mail_class->Subject = $subject;
        $this->mail_class->msgHTML($body);
        if ($this->mail_class->send() == false) {
            if (ENV_LOG) {
                SysClass::SetLog('Отправка письма на ' . $email . ' завершилась неудачей! Ошибка: ' . $this->mail_class->ErrorInfo, 'error');
            }            
            return $this->mail_class->ErrorInfo;
        } else {
            return true;
        }      
    }

}
