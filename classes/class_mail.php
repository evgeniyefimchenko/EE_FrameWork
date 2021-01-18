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
            $this->mail_class->SMTPDebug = 3;

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
     * @param type $to - кому
     * @param type $subject - заголовок
     * @param type $body - тело письма
     * @return boolean - сообщение о ошибке или TRUE
     */
    public function send_mail($to, $subject, $body) {
        $this->mail_class->addAddress($to, '');
        $this->mail_class->Subject = $subject;
        $this->mail_class->msgHTML($body);
        if ($this->mail_class->send() == FALSE) {
            if (ENV_LOG) {
                SysClass::SetLog('Отправка письма на ' . $to . ' завершилась неудачей! Ошибка: ' . $this->mail_class->ErrorInfo, 'error');
            }            
            return $this->mail_class->ErrorInfo;
        } else {
            return TRUE;
        }
    }

}
