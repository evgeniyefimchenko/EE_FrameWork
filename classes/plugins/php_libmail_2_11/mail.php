<?php

/*
  -------------------------------------------------
  php_libmail v 2.11 (28.06.2016) webi.ru
  Универсальный PHP класс для отправки почты
  Может отправлять почту через smtp(используя сокеты), либо через стандартную функцию mail()
  Прикрепление файлов, как просто прикрепленные к письму, либо внедренные в письмо и можно вставлять картинки внутрь html письма.
  Полная поддержка email адресов от национальных доменов(рф и любых других).
  http://webi.ru/webi_files/php_libmail.html
  adm@webi.ru
  -----------------------
  по всем вопросам пишите на adm@webi.ru (email и jabber)
  ________________________________________________

  Что нового:

  ------------------------------------
  Версия 2.11 (28.06.2016)
  Устранение ошибки. В теме сообщения и в именах адресатов поддчерк "_" заменялся на пробел " ". Например "Тема_2" меналось на "Тема 2".
  То же самое было и в именах адресатов. Сейчас эту ошибку устранил - сделал кодирование заголовков с помощью base64
 
  ------------------------------------
  Версия 2.1 (17.11.2014)
  Поддержка email-ов с национальными доменами, не только в кирилице (типа имя.рф), но и на любых других языках.
  По умолчанию данная функция выключена, чтобы ее активировать нужно при запуске класса передать третий параметр true 
  $m=new Mail('','',true); // Первые два параметра остаются так же как были у вас и раньше. Если у вас ничего не было заполнено, то поставьте пустые кавычки как в этом примере.
  Все адреса с национальными доменами должны быть переданы в юникоде (UTF-8)
  
  Для конвертации национальных доменов я добавил еще один файл с классом libmail_idna_convert.php. Этот класс разработан Matthias Sommerfeld - некто из Германии.
  ------------------------------------
  Версия 2.001 (06.10.2014)
  Ничего нового нет, только сделал ValidEmail статическим, чтобы можно было проверять валидность email без создания класса
  Mail::ValidEmail(email);

  ------------------------------------
  Версия 2.0 (18.10.2013)
  Решил полностью уйти от совместимости со старыми версиями PHP. Теперь класс заточен под PHP 5 и выше.
  Совместимость класса с предыдущими версиями сохранилась, все команды работают точно так же как и в старых версиях класса, кроме Get() - получение лога.
  Теперь по умолчанию лог выключен, так как в этой версии появилась возможность отправлять по несколько разных писем за одно соединение и
  если учесть, что к каждому письму можно прикреплять файл, то лог может занимать много памяти.
  Поэтому, если вам нужно получать лог отправки почты, сначала нужно включить веденеие лога $m->log_on(true);
  Установить эту команжу нужно до использования отправки письма, перед $m->Send(); или выше.

  Полностью переработан внутренний механизм формирования письма, служебные заголовки теперь создаются по стандартам.

  Кодировка по умолчанию теперь Юникод.

  Одно из самых больших изменений, это возможность отправлять разные письма в пределах одного соединения.
  Например, вы хотите отправить нескольким адресатам одинаковое письмо, делается так
  $m->To( "aser@asd.ru" );
  $m->To( "dhhhh@asd.ru",'gds' );
  $m->To( "afsgdc@asd.ru",54 );
  Все остальное стандартно. В итоге каждый из этих адресатов получит письмо, но отправленное индивидуально ему, то есть указывая второй параметр,
  после email вы создаете дополнительный рессурс c указанным id. ID руссурса может быть любая строка из цифр и букв латинского алфавита.
  Когда будет идти отправка, то все объединяться в рессурсы и какждый рессурс отправит письмо.
  Например если хотите чтобы и темы письма были индивидуальны, то точно так же следующий параметр после названия темы указываете рессурс,
  который должен совпадать с указанным в To(). Так же и с самим телом письма и файлами.
  Но об этом всем читайте на сайте, так как сильно много писать здесь не буду.

  В этой версии добавлено кодирование письма в base64 и работает по умолчанию, письма в этом формате предпочтительнее.
  Эта настройка выполнятется при инициализации класса
  $m=new Mail('windows-1251','8bit'); 8bit кодировка windows
  $m=new Mail('','8bit');  8bit кодировка Юникод
  $m=new Mail();  base64 кодировка Юникод

  Внесены изменения в формирование HTML письма. Теперь можно не только html версию письма отправлять, но и альтернативную текстовую версию,
  на тот случай, если почтовая программа получателя не отображает html
  $m->Body( 'Текст <br>текст', "html", "А в этом поле можно указать тот же самый текст, но без html");
  Но указывать альтернативную версию текста не обязательно.

  Прикрепление файлов работает как и прежде. Но если хотите, чтобы файл был виден прикрепленным и получатель мог его скачать,
  используйте параметр attachment
  $m->Attach( "1.jpg", "", "image/jpeg", "attachment");
  В этом случае файл будет прикреплен к письму, без указания attachment, файл будет внедренный в письмо,
  например если вы хотите вставить картинки в html, можно не указывать attachment, тогда эти картинки не должны показываться в прикрепленных файлах,
  но их можно использовать в html
  <img src="cid:1.jpg"> например так.

  Исправлена проблема с отображением имен файлов с русскими именами.

  Добавлен массив $status_mail, после отправки письма можно получить статус отправки письма.
  $m->status_mail['status'] = false|true; Если письмо отправлено успешно, то true, в противном случае false
  $m->status_mail['message'] ='текст'; Текстовое сообщение ошибки или удачной отправки

  ----------------------------------
  Версия 1.6.1 (02.10.2013)
  Добавлена возможность встраивать картинки внутрь html
  Для этого как и раньше прикрепляете картинки к письму
  Например две картинки(имена обязательно уникальные, 1.jpg и 1.jpg - ошибка)
  $m->Attach( "1.jpg", "", "image/jpeg" );
  $m->Attach( "2.jpg", "", "image/jpeg" );
  А в самом тексте письма вставляете картинки так
  <img src="cid:1.jpg">
  <img src="cid:2.jpg">
  Например
  $m->Body( 'Текст <img src="cid:1.jpg"><br>текст<img src="cid:2.jpg"><br> И.д.', "html" );
  Обратите внимание, тип сообщения должен быть html

  ---------------------------------
  Версия 1.6.0 (09.12.2011)
  Добавлена возможность добавлять имена к почтовым ящикам
  Действует в From, To и ReplyTo
  Имя добавляется через разделитель ';' например
  $m->To( "Максим;adm@webi.ru" );

  -------------------------------------------
  Версия 1.5.1 (07.02.2011)
  Модернизация функции проверки email адресов на валидность под php 5.2 и выше.

  -------------------------------------------
  Версия 1.5 (28.02.2010)
  Добавлена поддержка отправки почты с помощью SMTP

  -------------------------------------------
  Версия 1.4 (24.02.2010)

  адаптация к русской кодировке
  кодировку теперь нужно указывать не в теле письма как было раньше, а при инициализации класса например $m= new Mail('windows-1251');
  если не указывать кодировку, по умолчанию будет windows-1251
  --
  изменено прикрепление файлов.
  добавил новый параметр, имя файла.
  теперь прикрепляя файл ему можно дать другое имя. это полезно, когда прикрепляете временные файлы загруженные через upload, так как их имена не соотвествуют реальным.
  пример 	$m->Attach( "/toto.gif", "asd.gif" "image/gif" )
  если не указывать новое имя, берется имя из пути прикрепляемого файла
  --
  добавлена возможность отправлять письма в формате html
  _______________________________________________


  пример

  include "libmail.php";

  $m= new Mail('windows-1251');  // можно сразу указать кодировку, можно ничего не указывать ($m= new Mail;)
  $m->From( "Сергей;asd@asd.com" ); // от кого Можно использовать имя, отделяется точкой с запятой
  $m->ReplyTo( 'Сергей Вадимыч;replay@bk.ru' ); // куда ответить, тоже можно указать имя
  $m->To( "kuda@asd.ru" );   // кому, в этом поле так же разрешено указывать имя
  $m->Subject( "тема сообщения" );
  $m->Body("Сообщение. Текст письма");
  $m->Cc( "kopiya@asd.ru");  // кому отправить копию письма
  $m->Bcc( "skritaya_kopiya@asd.ru"); // кому отправить скрытую копию
  $m->Priority(4) ;	// установка приоритета
  $m->Attach( "/toto.gif", "", "image/gif" ) ;	// прикрепленный файл типа image/gif. типа файла указывать не обязательно
  $m->smtp_on("smtp.asd.com","login","passw", 25, 10); // используя эу команду отправка пойдет через smtp
  $m->log_on(true); // включаем лог, чтобы посмотреть служебную информацию
  $m->Send();	// отправка
  echo "Письмо отправлено, вот исходный текст письма:<br><pre>", $m->Get(), "</pre>";

  Подробные инструкции читайте на сайте http://webi.ru/webi_files/php_libmail.html

 */

/**
 * Отправка почты
 * @author WeBi <adm@webi.ru>
 * @link http://webi.ru/webi_files/php_libmail.html
 * @version 2.11 (28.06.2016)
 */
class Mail
{

    /**
     * кодировка письма
     * @var string
     * @access private  
     */
    private $charset = "UTF-8";

    /**
     * Разделитель для письма из нескольких частей
     * @var string
     */
    private $boundary = "";

    /**
     * Массив частей тела письма
     * @var array 
     */
    private $SubBody = array();

    /**
     * Готовое сформированное тело письма. По каждому рессурсу
     * @var array
     */
    private $body = array();

    /**
     * Content-Transfer-Encoding base64|8bit
     * @var string 
     */
    private $ctencoding = "base64";

    /**
     * Счетчик для массива в который добавляются основные части письма
     * @var int
     */
    private $count_body = 1;

    /**
     * проверка валидности email
     * @var boolean 
     */
    private $checkAddress = true;

    /**
     * Массив с заголовками письма
     * @var array 
     */
    private $headers = array();

    /**
     * Готовые заголовки письма
     * @var array
     */
    private $ready_headers = array();

    /**
     * имена для email адресов, чтобы делать вид ("сергей" <asd@wer.re>)
     * @var array
     */
    private $names_email = array();

    /**
     * Добавление заголовка для получения уведомления о прочтении. Не актуален.
     * @var type 
     */
    private $receipt = 0;

    /**
     * Массив адресов для отправки через smtp
     * @var array 
     */
    private $smtpsendto = array();

    /**
     * Массив с адресами куда отправлять
     * @var array
     */
    private $sendto = array();

    /**
     * Кому отправлять открытые копии письма, все будут видеть кому еще было отправлено это письмо
     * @var array 
     */
    private $acc = array();

    /**
     * Кому отправлять скрытые копии
     * @var array
     */
    private $abcc = array();

    /**
     * Массив с настройками для smtp
     * @var array 
     */
    private $smtp = array();

    /**
     * Лог работы SMTP или mail()
     * @var string
     */
    private $smtp_log = '';

    /**
     * Принудительное отключение лога. Если получать лог отправки не нужно, лучше отключить, так как если идет отправка писем по нескольким ресурсам, да еще с файлами, то потребление памяти будет большим
     * По умолчанию находится в выключенном положении
     * @var boolean
     */
    private $log_on = false;

    /**
     * Самый основной заголовок письма
     * @var array
     */
    private $body_header = array();

    /**
     * Статус работы класса
     * @var array
     */
    public $status_mail = array('status' => true, "message" => 'ок');

    /**
     * Конвертация email адресов в национальных доменах. При включении будет работать и с национальными и с международными доменами. Рекомендуется включать, если действительно есть необходимость, так как увеличивает потребление памяти.
     * По умолчанию выключена
     * @var boolean
     */
    private $idna_convert = false;

    /**
     * Инициализаця
     * @param string $charset кодировка письма 
     * @param string $ctencoding Content-Transfer-Encoding
     * @param boolean $idna_convert Нужна ли конвертация email из национальных доменов. При включении будет работать с национальными и международными доменами одновременно. Рекоменуется включать только если вы отправляете с нацонального домена или отправляете на национальные домены, либо неизвестно, будет ли отправка на национальные домены или нет. Так как повышает потребление памяти.
     */
    public function __construct($charset = "", $ctencoding = '', $idna_convert = false)
    {
        // формирование разделителя
        $this->boundary = md5(uniqid("myboundary"));

        // по умолчанию отправку через smtp отключаем
        $this->smtp['on'] = false;

        // Content-Transfer-Encoding по умолчанию base64
        if (strlen($ctencoding) and $ctencoding == '8bit')
        {
            $this->ctencoding = '8bit';
        }

        // кодировка письма
        if (strlen($charset))
        {
            $this->charset = strtolower($charset);
            if ($this->charset == "us-ascii")
            {
                $this->ctencoding = "7bit";
            }
        }
        // если включена конвертация национальных доменов
        if ($idna_convert)
        {
            $this->idna_convert = true;
            require_once(dirname(__FILE__)."/libmail_idna_convert.php"); // подгружаем класс для конвертации
            $this->IDN = new idna_convert(); // и сразу его инициализируем
        }
    }

    /**
     * Текстовая часть письма
     * @param string $text Текст письма
     * @param string $text_html text|html В каком виде письмо, в html или обычный текст.
     * @param string $alternative_text Альтернативный текст. Если письмо в html то здесь может быть текст, который будут показывать почтовики, которые не умеют отображать html
     * @param string $resource Ресурс-для какого ресурса относится данное сообщение.
     */
    public function Body($text, $text_html = "", $alternative_text = '', $resource = 'webi')
    {
        // по умолчанию ресурс webi
        if (!strlen($resource))
            $resource = 'webi';

        if ($text_html == "html")
            $text_html = "text/html";
        else
            $text_html = "text/plain";

        // если письмо формируем в base64, сразу конвертируем в base64
        // base64 разбиваем по строкам с помощью chunk_split, чтобы строка не была очень длиной (стандарт)
        if ($this->ctencoding == 'base64')
        {
            if (strlen($alternative_text))
                $alternative_text = chunk_split(base64_encode($alternative_text));

            if (strlen($text))
                $text = chunk_split(base64_encode($text));
        }


        // если альтернативного текста нет, то эта часть письма будет состоять лишь из одной части
        if (!strlen($alternative_text))
        {
            $body = "Content-Type: ".$text_html."; charset=".$this->charset."\r\n";
            $body.="Content-Transfer-Encoding: ".$this->ctencoding."\r\n\r\n";
            $body.=$text;
        }
        // а если есть альтернаивный текст и это html
        // значит эта часть письма будет состоять из двух частей
        elseif (strlen($alternative_text) and $text_html == 'text/html')
        {
            // начинаем с заголовка в котором указываем что будет несколько частей письма, отображать нужно лишно одно, которое понимает почтовик
            $body = "Content-Type: multipart/alternative; boundary=ALT-".$this->boundary."\r\n\r\n";

            $body.="--ALT-".$this->boundary."\r\n"; // теперь добавляем разделитель
            $body.="Content-Type: text/plain; charset=".$this->charset."\r\n"; // заголовок что сейчас будет текстовая версия
            $body.="Content-Transfer-Encoding: ".$this->ctencoding."\r\n\r\n"; // кодирование 
            $body.=$alternative_text."\r\n"; // и теперь сам текст

            $body.="--ALT-".$this->boundary."\r\n"; // Теперь снова разделитель и пойдет html версия письма
            $body.="Content-Type: text/html; charset=".$this->charset."\r\n"; // заголовок для html версии
            $body.="Content-Transfer-Encoding: ".$this->ctencoding."\r\n\r\n"; // кодирование
            $body.=$text."\r\n"; // текст

            $body.="--ALT-".$this->boundary."--"; // И теперь закрывающий разделитель, показывает, что все части закончились
        }
        // в указанный ресурс добавляем
        $this->SubBody[$resource]['body'][0] = $body; // что получилось забарасываем в нулевой элемент массива частей. Это получилась первая часть тела письма. Возожно дальше будут формироваться еще части
    }

    /**
     * Определение mime type файла по расширению
     * @param type $file
     * @return string
     */
    protected function mime_content_type($file)
    {
        $ext = strtolower(substr(strrchr(basename($file), '.'), 1));
        switch ($ext)
        {
            case 'jpg': return 'image/jpeg';
            case 'jpeg': return 'image/jpeg';
            case 'gif': return 'image/gif';
            case 'png': return 'image/png';
            case 'ico': return 'image/x-icon';
            case 'txt': return 'text/plain';

            default: return 'application/octet-stream';
        }
    }

    /**
     * Прикрепление файла
     * @param string $filename : путь к файлу, который надо отправить
     * @param string $new_name_filename : реальное имя файла. если вдруг вставляется файл временный, то его имя будет не понятно каким
     * @param string $filetype : MIME-тип файла. если не указан, попытается определить по ресширению, если не найдено будет application/octet-stream
     * @param string $disposition по какому принципу вставляется файл. 'attachment' - файл прикрепится как отдельный файл, если ничего нет, тогда файл будет частью письма, например чтобы вставить изображение внутрь html текста, изображение не будет показываться как прикрепленный файл. При 'attachment' в почтовике должно быть видно прикрепленный файл и его можно скачать
     * @param string $resource Ресурс-для какого ресурса относятся файлы
     * @return boolean
     */
    public function Attach($filename, $new_name_filename = "", $filetype = "", $disposition = "", $resource = 'webi')
    {
        if (!strlen($resource))
            $resource = 'webi';

        if (!file_exists($filename))
        {
            return FALSE;
        }

        // получаем имя файла        
        // если имя файла есть в подмене, то берем его от туда, в противном случае имя файла берем из реального пути 
        if (strlen($new_name_filename))
            $basename = basename($new_name_filename); // если есть другое имя файла, то оно будет таким
        else
            $basename = basename($filename); // а если нет другого имени файла, то имя будет выдернуто из самого загружаемого файла

        $charset_name = "=?".$this->charset."?B?".base64_encode($basename)."?=";


// если тип файла не указан, пытаемся определить mime по расширению
        if (!strlen($filetype))
            $filetype = $this->mime_content_type($basename);

        // начинаем формировать очередную часть письма
        // создаем заголовок для этой части, указываем mime файла и имя
        $body = "Content-Type: ".$filetype."; name=\"$charset_name\"\r\n";

        $body.="Content-Transfer-Encoding: base64\r\n";

        if ($disposition == 'attachment') // если файл является просто прицепленным
        {

            $body.="Content-Disposition: attachment; filename=\"$charset_name\"\r\n"; // добавляем заголовок, что файл прикреплен к письму
        }

        $body.="Content-ID: <".$basename.">\r\n"; // id файла, чтобы к нему можно было обратиться из html

        $body.="\r\n";

        // читаем файл
        $body.=chunk_split(base64_encode(file_get_contents($filename)));

        // если файл прикреплен к письму, добавляем это тело в массив для смешанного содержания
        if ($disposition == 'attachment')
            $this->SubBody[$resource]['mixed'][] = $body;
        // а если файл внедренный, значит добавляем его в массив общего тела
        else
        {
            // внедренный файл добавляем на один уровень в общее тело письма, поэтому добавляем его на позицию $this->count_body, чтобы не наложиться на текстовую часть письма
            $this->SubBody[$resource]['body'][$this->count_body] = $body;
            $this->count_body++; // увеличиваем счетчик
        }
    }

    /**
     * собираем письмо
     * @param string $resource Ресурс-для какого ресурса относится собираемое письмо.
     */
    public function BuildMail($resource = 'webi')
    {
        if (!strlen($resource))
            $resource = 'webi';

        $this->ready_headers[$resource] = ''; // готовые заголовки для ресурса
        // узнаем от какого рессурса у нас будет тело
        // если есть тело для текущего руссурса, значит будем работать с этим рессурсом
        // а если нет сформированного тела для этого рессурса, значит будем брать тело из ресурса по умолчанию
        if (isset($this->SubBody[$resource]['body']))
            $resource_body = $resource;
        else
            $resource_body = 'webi';


        if (!is_array($this->sendto[$resource]) OR ! count($this->sendto[$resource]))
        {
            $this->status_mail['status'] = false;
            $this->status_mail['message'] = "ошибка : не указаны получатели в рессурсе ".$resource;
            // return false;
        }



        // если тело письма по этому рессурсу уже было сформированно ранее, 
        // то не будем его собирать повторно, так как часть данных уже была удалена и повторно его не собрать, да и зачем собирать, если оно уже есть
        // а собрано оно может быть, если для текущего ресурса нет писем для формирования, поэтому берем из webi, сформированном ранее
        if (!isset($this->body[$resource_body]))
        {
            // если основная часть письма состоит более чем из одной части, значит все эти части являются одним целым этого письма
            // объеденим их и заголовок ему сделаем Content-Type: multipart/related

            if (count($this->SubBody[$resource_body]['body']) > 1)
            {
                $body = implode("\r\n--REL-".$this->boundary."\r\n", $this->SubBody[$resource_body]['body']);
                $body = "Content-Type: multipart/related; boundary=REL-".$this->boundary."\r\n\r\n"
                        .'--REL-'.$this->boundary."\r\n".$body.'--REL-'.$this->boundary."--";
            }
            // а если в основной части письма лишь одна часть
            else
            {
                $body = $this->SubBody[$resource_body]['body'][0];
            }

            // а если еще сформировались части писем, которые отдельны сами по себе, например приаттаченные файлы, а не внедренные
            // тогда объеденим эти части с признаком mixed и сформированное тело сообщения $body будет одной из частей
            if (isset($this->SubBody[$resource_body]['mixed']) AND count($this->SubBody[$resource_body]['mixed']))
            {
                $bodymix = implode('--MIX-'.$this->boundary."\r\n", $this->SubBody[$resource_body]['mixed']);
                $body = $body."\r\n--MIX-".$this->boundary."\r\n".$bodymix;
                $body = "Content-Type: multipart/mixed; boundary=MIX-".$this->boundary."\r\n\r\n"
                        .'--MIX-'.$this->boundary."\r\n".$body.'--MIX-'.$this->boundary."--";
            }
            unset($this->SubBody[$resource_body]); // удалим массив, он может быть очень уж большим
            // сейчас нужно выдернуть основной заголовок из письма, так как на стадии формирования это сделать не так просто
            $temp_mass = explode("\r\n\r\n", $body); // разбиваем тело по переносам строк.
            $this->body_header[$resource_body] = $temp_mass[0]; // первый элемент и будет основной заголовок, его добавим в самый конец заголовков
            unset($temp_mass[0]); // теперь удаляем этот этот элемент из массива
            $this->body[$resource_body] = implode("\r\n\r\n", $temp_mass); // и формируем тело письма обратно но уже без основного заголовка и сразу добавляем его в основную переменную
            unset($temp_mass); // и удаляем временный массив
            unset($body); // и удалим сразу тело, так как много памяти занимает, а дальше еще заголовки воротить
        }


        // дальше формируем заголовки       
        // создание заголовка TO.
        // добавление имен к адресам
        $temp_mass = array();
        foreach ($this->sendto[$resource] as $key => $value)
        {

            if (strlen($this->names_email[$resource]['To'][$value]))
                $temp_mass[] = "=?".$this->charset."?B?".base64_encode(strtr($this->names_email[$resource]['To'][$value], "\r\n", "  "))."?= <".$value.">";
            else
                $temp_mass[] = $value;
        }

        $this->headers[$resource]['To'] = implode(", ", $temp_mass); // этот заголовок будет не нужен при отправке через mail()

        if (isset($this->acc[$resource]) and count($this->acc[$resource]) > 0)
            $this->headers[$resource]['CC'] = implode(", ", $this->acc[$resource]);

        if (isset($this->abcc[$resource]) and count($this->abcc[$resource]) > 0)
            $this->headers[$resource]['BCC'] = implode(", ", $this->abcc[$resource]);  // этот заголовок будет не нужен при отправке через smtp



// если установлено подтверждение о доставке, берем адрес куда слать уведомление из адрес для ответа, либо из From
        if ($this->receipt)
        {
            if (isset($this->headers["Reply-To"]))
                $this->headers["Disposition-Notification-To"] = $this->headers["Reply-To"];
            else
                $this->headers["Disposition-Notification-To"] = $this->headers['From'];
        }

        if ($this->charset != "")
        {
            $this->headers["Mime-Version"] = "1.0";
        }
        $this->headers["X-Mailer"] = "Php_libMail_v_2.11(webi.ru)";

        // если для текущего ресурса тема не установлена, но установлена для ресурса по умолчанию, возьмем тему от туда
        if (!isset($this->headers[$resource]['Subject']) and isset($this->headers['webi']['Subject']))
            $this->headers[$resource]['Subject'] = $this->headers['webi']['Subject'];

        // дальше уже создаем заголовки из сформированного массива заголовков
        // создание заголовков если отправка идет через smtp
        if ($this->smtp['on'])
        {

            // разбиваем (FROM - от кого) на юзера и домен. домен понадобится в заголовке
            $user_domen = explode('@', $this->headers['From']);

            $this->ready_headers[$resource] .= "Date: ".date("r")."\r\n";
            $this->ready_headers[$resource] .= "Message-ID: <".rand().".".$resource.date("YmjHis")."@".$user_domen[1].">\r\n"; // в id письма добавим на всякий случай еще и рессурс, так как формирование писем с разными ресурсам в одну секунду может сформировать одинаковые id писем
            // так как массив с заголовками создан на разный уровнях (например TO во втором уровне вложенности, внутри ресурса, а FROM в первом уровне, так как FROM незвисит от ресурса он всегда один)
            // поэтому создаем временный массив заголовоков с одним уровнем, для упрощенного перебора
            // сначала перебираем заголовки для ресурса
            foreach ($this->headers[$resource] as $key => $value)
            {
                $new_mass_head[$key] = $value;
            }
            // а теперь перебираем заголовки общие, которые не зависят от ресурса 
            // и если заголовок не массив(не имеет следующей вложенности), берем из него данные
            foreach ($this->headers as $key => $value)
            {
                if (!is_array($value))
                    $new_mass_head[$key] = $value;
            }
            reset($new_mass_head);

            // а теперь уже формируем готовые заголовки
            while (list( $hdr, $value ) = each($new_mass_head))
            {
                if ($hdr == "From" and strlen($this->names_email['from']))
                    $this->ready_headers[$resource] .= $hdr.": =?".$this->charset."?B?".base64_encode(strtr($this->names_email['from'], "\r\n", "  "))."?= <".$value.">\r\n";
                elseif ($hdr == "Reply-To" and strlen($this->names_email['Reply-To']))                                          
                    $this->ready_headers[$resource] .= $hdr.": =?".$this->charset."?B?".base64_encode(strtr($this->names_email['Reply-To'], "\r\n", "  "))."?= <".$value.">\r\n";
                elseif ($hdr != "BCC")
                    $this->ready_headers[$resource] .= $hdr.": ".$value."\r\n"; // пропускаем заголовок для отправки скрытой копии
            }
        }
        // создание заголовоков, если отправка идет через mail()
        else
        {

            // здесь так же как и выше создаем новый одноуровневый массив из двух уровнего массива заголовоков- общих(первый уровень) и заголовков для ресурса
            foreach ($this->headers[$resource] as $key => $value)
            {
                $new_mass_head[$key] = $value;
            }
            foreach ($this->headers as $key => $value)
            {
                if (!is_array($value))
                    $new_mass_head[$key] = $value;
            }
            reset($new_mass_head);
            while (list( $hdr, $value ) = each($new_mass_head))
            {
                if ($hdr == "From" and strlen($this->names_email['from']))                                        
                    $this->ready_headers[$resource] .= $hdr.": =?".$this->charset."?B?".base64_encode(strtr($this->names_email['from'], "\r\n", "  "))."?= <".$value.">\r\n";
                elseif ($hdr == "Reply-To" and strlen($this->names_email['Reply-To']))
                    $this->ready_headers[$resource] .= $hdr.": =?".$this->charset."?B?".base64_encode(strtr($this->names_email['Reply-To'], "\r\n", "  "))."?= <".$value.">\r\n";
                elseif ($hdr != "Subject" and $hdr != "To")
                    $this->ready_headers[$resource] .= "$hdr: $value\r\n"; // пропускаем заголовки кому и тему... они вставятся сами
            }
        }
        // и в завершении добавим заголовки от письма, выдернутые ранее. Это общий заголовок для тела
        $this->ready_headers[$resource].=$this->body_header[$resource_body]."\r\n";
    }

    /**
     * включение выключение проверки валидности email по умолчанию проверка включена
     * @param boolean $bool
     */
    public function autoCheck($bool)
    {
        if ($bool)
            $this->checkAddress = true;
        else
            $this->checkAddress = false;
    }

    /**
     * Принудительное включение выключение сбора лога
     * @param boolean $bool
     */
    public function log_on($bool)
    {
        if ($bool)
            $this->log_on = true;
        else
            $this->log_on = false;
    }

    /**
     * Тема письма
     * @param string $subject
     * @param string $resource Ресурс-для какого ресурса относится данная тема, далее, если для ресурса не будет установлена тема, она возьмется из ресурса по умолчанию
     */
    public function Subject($subject, $resource = 'webi')
    {
        if (!strlen($resource))
            $resource = 'webi';
        $this->headers[$resource]['Subject'] = "=?".$this->charset."?B?".base64_encode(strtr($subject, "\r\n", "  "))."?=";
    }

    /**
     * От кого
     * @param string $from может быть имя и email через разделитель имя;asd@asde.ru либо просто email. From во всех ресурсах одинаковый, его нельзя установить для каждого разный
     * @return boolean
     */
    public function From($from)
    {
        if (!is_string($from))
        {
            $this->status_mail['status'] = false;
            $this->status_mail['message'] = "ошибка, From должен быть строкой";
            return FALSE;
        }

        $temp_mass = explode(';', $from); // разбиваем по разделителю для выделения имени
        if (count($temp_mass) == 2) // если удалось разбить на два элемента
        {
            $temp_mass[1]=str_replace(" ", "", $temp_mass[1]); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
            $this->names_email['from'] = $temp_mass[0]; // имя первая часть
            $this->headers['From'] = $temp_mass[1]; // адрес вторая часть
        }
        else // и если имя не определено
        {
            $from=str_replace(" ", "", $from); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
            $this->names_email['from'] = '';
            $this->headers['From'] = $from;
        }


        // проверка адреса на валидность
        if ($this->checkAddress == true)
        {
           $this->headers['From'] = $this->CheckAdresses($this->headers['From']); // проверка с автоматической конвертацией национального домена. Поэтому если проверка прошла нормально, то возвращаются проверенные адреса и если нужна конвертация, то адреса будут отконвертированы и присваиваем новые адреса в переменную
        }
        
        
    }

    /**
     * На какой адрес отвечать
     * @param string $address Нельзя установить для каждого ресурса разный, отвечать можно будет всегда только на один адрес
     * @return boolean
     */
    public function ReplyTo($address)
    {

        if (!is_string($address))
            return false;

        $temp_mass = explode(';', $address); // разбиваем по разделителю для выделения имени

        if (count($temp_mass) == 2) // если удалось разбить на два элемента
        {
            $temp_mass[1]=str_replace(" ", "", $temp_mass[1]); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
            $this->names_email['Reply-To'] = $temp_mass[0]; // имя первая часть
            $this->headers['Reply-To'] = $temp_mass[1]; // адрес вторая часть
        }
        else // и если имя не определено
        {
            $address=str_replace(" ", "", $address); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
            $this->names_email['Reply-To'] = '';
            $this->headers['Reply-To'] = $address;
        }

        // проверка адреса на валидность
        if ($this->checkAddress == true)
        {
            $this->headers['Reply-To'] = $this->CheckAdresses($this->headers['Reply-To']); // проверка с автоматической конвертацией национального домена. Поэтому если проверка прошла нормально, то возвращаются проверенные адреса и если нужна конвертация, то адреса будут отконвертированы и присваиваем новые адреса в переменную
        }
    }

    /**
     * Добавление заголовка для получения уведомления о прочтении. обратный адрес берется из "From" (или из "ReplyTo" если указан)
     * Данный параметр не актуален, так как многие почтовики игнорируют этот параметр, а некоторые почтовые системы расчитывают письма с этим параметром как спам, так как этот параметр часто использовали спамеры для проверки рабочих адресов
     */
    public function Receipt()
    {
        $this->receipt = 1;
    }

    /**
     * Кому отправлять. 
     * @param string|array $to
     * @param string $resource Ресурс-какому ресурсу принадлежит адресат.
     */
    public function To($to, $resource = 'webi')
    {
        if (!strlen($resource))
            $resource = 'webi';

        // если это массив
        if (is_array($to))
        {
            foreach ($to as $key => $value) // перебираем массив и добавляем в массив для отправки через smtp
            {

                $temp_mass = explode(';', $value); // разбиваем по разделителю для выделения имени

                if (count($temp_mass) == 2) // если удалось разбить на два элемента
                {
                    $temp_mass[1]=str_replace(" ", "", $temp_mass[1]); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
                    $this->smtpsendto[$resource][$temp_mass[1]] = $temp_mass[1]; // ключи и значения одинаковые, чтобы исключить дубли адресов
                    $this->names_email[$resource]['To'][$temp_mass[1]] = $temp_mass[0]; // имя первая часть
                    $this->sendto[$resource][] = $temp_mass[1];
                }
                else // и если имя не определено
                {
                    $value=str_replace(" ", "", $value); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
                    $this->smtpsendto[$resource][$value] = $value; // ключи и значения одинаковые, чтобы исключить дубли адресов
                    $this->names_email[$resource]['To'][$value] = ''; // имя первая часть
                    $this->sendto[$resource][] = $value;
                }
            }
        }
        else
        {
            $temp_mass = explode(';', $to); // разбиваем по разделителю для выделения имени

            if (count($temp_mass) == 2) // если удалось разбить на два элемента
            {
                $temp_mass[1]=str_replace(" ", "", $temp_mass[1]); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
                $this->sendto[$resource][] = $temp_mass[1];
                $this->smtpsendto[$resource][$temp_mass[1]] = $temp_mass[1]; // ключи и значения одинаковые, чтобы исключить дубли адресов
                $this->names_email[$resource]['To'][$temp_mass[1]] = $temp_mass[0]; // имя первая часть
            }
            else // и если имя не определено
            {
                $to=str_replace(" ", "", $to); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
                $this->sendto[$resource][] = $to;
                $this->smtpsendto[$resource][$to] = $to; // ключи и значения одинаковые, чтобы исключить дубли адресов

                $this->names_email[$resource]['To'][$to] = ''; // имя первая часть
            }
        }

                
        // проверка адресов на валидность
        if ($this->checkAddress == true)
        {
           $this->sendto[$resource] = $this->CheckAdresses($this->sendto[$resource]); // проверка с автоматической конвертацией национального домена. Поэтому если проверка прошла нормально, то возвращаются проверенные адреса и если нужна конвертация, то адреса будут отконвертированы и присваиваем новые адреса в переменную
        }
    }

    /**
     * Проверка валидности email. Возвращает ошибку или проверенные адреса, это удобно, если адреса были нациоанальные, то вернуться они уже отконвертированные
     * @param string|array $aad один email или массив email
     * @return boolean|string|array
     */
    // @TODO-WEBI если идет отправка писем через массив, то при обнаружении хоть одного не ваерного адреса, происходит остановка отправки.
    // нужно просто пропускать не валидные ящики и отправлять письма на правильные адреса.
    private function CheckAdresses($aad)
    {
        if (is_array($aad)) // если массив адресов
        {
            foreach ($aad as $key => $value)
            {
                // если включена конвертация национальных доменов, коневертируем email. Нет ничего страшного, если он будет в международном формате, тогда он просто останется как есть
                if ($this->idna_convert)
                {
                    $aad[$key] = $value = $this->IDN->encode($value); // сохраняем преобразованный email обратно в массив, чтобы потом его возвратить
                }

                if (!$this->ValidEmail($value))
                {
                    $this->status_mail['status'] = false;
                    $this->status_mail['message'] = "ошибка : не верный email ".$value;
                    return FALSE;
                }
            }
        }
        elseif (strlen($aad)) // либо если строка
        {
            // если включена конвертация национальных доменов, коневертируем email. Нет ничего страшного, если он будет в международном формате, тогда он просто останется как есть
            if ($this->idna_convert)
            {
              
                $aad = $this->IDN->encode($aad); // сохраняем преобразованный email обратно в переменную, чтобы потом его возвратить
            }

            if (!$this->ValidEmail($aad))
            {
                $this->status_mail['status'] = false;
                $this->status_mail['message'] = "ошибка : не верный email ".$aad;
                return FALSE;
            }
        }
        return $aad; // возвращаем обратно проверенные адреса, так как возможно тут они теперь преобразованные из национального формата в международный
    }

    public static function ValidEmail($address)
    {
//@TODO-WEBI надо валидацию сделать более грамотной (если проверка регулярным выражением, то ошибка в адресах .asia, com.ua и т.д.)

        // если существует современная функция фильтрации данных, то проверять будем этой функцией. появилась в php 5.2
        if (function_exists('filter_list'))
        {
            $valid_email = filter_var($address, FILTER_VALIDATE_EMAIL);
            if ($valid_email !== false)
                return true;
            else
                return false;
        }
        else // а если php еще старой версии, то проверка валидности пойдет старым способом
        {

            if (preg_match('/^([.0-9a-z_-]+)@(([0-9a-z-]+\.)+[0-9a-z-]{2,10})$/i', $address))
                return true;
            else
                return false;
        }
    }

    /**
     * установка заголовка CC ( открытая копия, все получатели будут видеть куда ушла копия )
     * @param array|string $cc
     * @param string $resource Ресурс-для которого будут установлены открытые копии. Если не установить для ресурса, то подставляться ничего НЕ будет, из ресурса по умолчанию брать ничего НЕ будет
     */
    public function Cc($cc, $resource = 'webi')
    {
        if (!strlen($resource))
            $resource = 'webi';

        if (is_array($cc))
        {
            foreach ($cc as $key => $value) // перебираем массив и добавляем в массив для отправки через smtp
            {
                $value=str_replace(" ", "", $value); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
                $this->smtpsendto[$resource][$value] = $value; // ключи и значения одинаковые, чтобы исключить дубли адресов
                $this->acc[$resource][$value] = $value;
            }
        }
        else
        {
            $cc=str_replace(" ", "", $cc); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
            $this->acc[$resource][$cc] = $cc;
            $this->smtpsendto[$resource][$cc] = $cc; // ключи и значения одинаковые, чтобы исключить дубли адресов
        }

        
        // проверка адресов на валидность
        if ($this->checkAddress == true)
        {
            $this->acc[$resource] = $this->CheckAdresses($this->acc[$resource]); // проверка с автоматической конвертацией национального домена. Поэтому если проверка прошла нормально, то возвращаются проверенные адреса и если нужна конвертация, то адреса будут отконвертированы и присваиваем новые адреса в переменную
        }
        
    }

    /**
     * скрытая копия. не будет помещать заголовок кому ушло письмо
     * @param string|array $bcc
     * @param string $resource Ресурс-для которого будут установлены скрытые копии. Если не установить для ресурса, то подставляться ничего НЕ будет, из ресурса по умолчанию брать ничего НЕ будет
     */
    public function Bcc($bcc, $resource = 'webi')
    {
        if (!strlen($resource))
            $resource = 'webi';

        if (is_array($bcc))
        {
            foreach ($bcc as $key => $value) // перебираем массив и добавляем в массив для отправки через smtp
            {
                $value=str_replace(" ", "", $value); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
                $this->smtpsendto[$resource][$value] = $value; // ключи и значения одинаковые, чтобы исключить дубли адресов
                $this->abcc[$resource][$value] = $value;
            }
        }
        else
        {
            $bcc=str_replace(" ", "", $bcc); // удаляем пробелы из адреса, так как пробелов в адресе быть не должно
            $this->abcc[$resource][$bcc] = $bcc;
            $this->smtpsendto[$resource][$bcc] = $bcc; // ключи и значения одинаковые, чтобы исключить дубли адресов
        }

        
        // проверка адреса на валидность
        if ($this->checkAddress == true)
        {
            $this->abcc[$resource] = $this->CheckAdresses($this->abcc[$resource]); // проверка с автоматической конвертацией национального домена. Поэтому если проверка прошла нормально, то возвращаются проверенные адреса и если нужна конвертация, то адреса будут отконвертированы и присваиваем новые адреса в переменную
        }
        
    }

    /**
     * Добавление организации
     * @param string $org
     */
    public function Organization($org)
    {
        if (trim($org != ""))
            $this->headers['Organization'] = $org;
    }

    /**
     * Установка приоритета
     * @param int $priority
     * @return boolean
     */
    public function Priority($priority)
    {
        $priorities = array('1 (Highest)', '2 (High)', '3 (Normal)', '4 (Low)', '5 (Lowest)');
        if (!intval($priority))
            return false;

        if (!isset($priorities[$priority - 1]))
            return false;

        $this->headers["X-Priority"] = $priorities[$priority - 1];

        return true;
    }

    /**
     * включение отправки через smtp используя сокеты
     * после запуска этой функции отправка через smtp включена
     * для отправки через защищенное соединение сервер нужно указывать с добавлением "ssl://" например так "ssl://smtp.gmail.com"
     * @param string $smtp_serv
     * @param string $login
     * @param string $pass
     * @param int $port
     * @param int $timeout
     */
    public function smtp_on($smtp_serv, $login, $pass, $port = 25, $timeout = 5)
    {
        $this->smtp['on'] = true; // включаем отправку через smtp
        $this->smtp['serv'] = $smtp_serv;
        $this->smtp['login'] = $login;
        $this->smtp['pass'] = $pass;
        $this->smtp['port'] = $port;
        $this->smtp['timeout'] = $timeout;
    }

    private function get_data($smtp_conn)
    {
        $data = "";
        while ($str = fgets($smtp_conn, 515))
        {
            $data .= $str;
            if (substr($str, 3, 1) == " ")
            {
                break;
            }
        }
        return $data;
    }

    private function add_log($text)
    {
        // если формирование лога включена, будем добавлять в лог
        if ($this->log_on)
            $this->smtp_log.=$text;
    }

    public function Send()
    {
        // если где-то в классе были ошибки, то обрабатывать не будем
        if (!$this->status_mail['status'])
        {
            return FALSE;
        }



        // если отправка без использования smtp
        if (!$this->smtp['on'])
        {

            // перебираем массив "куда отправлять", но только верхний уровень, то есть пербираем ресурсы и для каждого ресурса организуем отправку отдельного письма
            foreach ($this->sendto as $key => $value)
            {
                $strTo = implode(", ", $this->sendto[$key]);
                // собираем письмо для текущего ресурса
                $this->BuildMail($key);
                // после сборки письма еще проверим статус ошибки
                if (!$this->status_mail['status'])
                {
                    return FALSE;
                }


                // если тело для данного ресурса сформированно, ставим признак ресурса для тела по текущему ресурсу
                if (isset($this->body[$key]))
                    $body_resource = $key;
                // а если тело для текущего ресурса не софрмированно, будем работать с телом от русурса по умолчанию
                else
                    $body_resource = 'webi';

                // отправляем, заголовки берем из текущего рессурса, а тело от ресурса который был выбран выше, от текущего или от по умолчанию
                $res = @mail($strTo, $this->headers[$key]['Subject'], $this->body[$body_resource], $this->ready_headers[$key]);

                // если была ошибка при отправке
                if (!$res)
                {
                    $this->status_mail['status'] = false;
                    $this->status_mail['message'] = "ошибка : функция mail() вернула ошибку";
                }
                // а если ошибки не было и статус отправки пока еще положительный...
                // иначе, если в предыдущем шаге была ошибка, а тут нет ошибки, то ошибку перепишет в true
                // а нужно чтобы ошибка так и осталась если хоть раз была ошибка отправки
                elseif ($this->status_mail['status'])
                {

                    $this->add_log('TO: '.$strTo."\n");
                    $this->add_log("Subject: ".$this->headers[$key]['Subject']."\n");
                    $this->add_log($this->ready_headers[$key]."\n\n");
                    $this->add_log($this->body[$body_resource]."\n\n\n");


                    $this->status_mail['status'] = true;
                    $this->status_mail['message'] = "письмо успешно отправлено с помощью mail()";
                }
                if ($key != 'webi')
                {   // если текущий ресурс не является ресурсом по умолчанию, удалим заголовки этого ресурса, так как они уже не нужны
                    // а вот заголовки от ресурса по умолчанию еще могут понадобиться
                    unset($this->headers[$key]);
                    unset($this->ready_headers[$key]);
                }
                // если ресурс для тела не является ресурсом по умолчанию, удалим тело этого ресурса, оно уже не понадобиться
                // а вот тело ресурса по умолчанию еще может понадобиться
                if ($body_resource != 'webi')
                {
                    unset($this->body[$body_resource]);
                }
            }

            if ($this->status_mail['status'])
            {
                return true;
            }
            else
            {
                return FALSE;
            }
        }
        else // если через smtp
        {

            // если нет хотя бы одного из основных данных для коннекта, выходим с ошибкой
            if (!$this->smtp['serv'] OR ! $this->smtp['login'] OR ! $this->smtp['pass'] OR ! $this->smtp['port'])
            {
                $this->status_mail['status'] = false;
                $this->status_mail['message'] = "ошибка : не все обязательные данные для SMTP указаны";
                return false;
            }


// разбиваем (FROM - от кого) на юзера и домен. юзер понадобится в приветсвии с сервом
            $user_domen = explode('@', $this->headers['From']);


            $smtp_conn = fsockopen($this->smtp['serv'], $this->smtp['port'], $errno, $errstr, $this->smtp['timeout']);
            if (!$smtp_conn)
            {
                $this->add_log("соединение с сервером не прошло\n\n");
                fclose($smtp_conn);
                $this->status_mail['status'] = false;
                $this->status_mail['message'] = "ошибка: соединение с сервером не прошло";
                return false;
            }

            $data = $this->get_data($smtp_conn)."\n";
            $this->add_log($data);

            fputs($smtp_conn, "EHLO ".$user_domen[0]."\r\n");
            $this->add_log("Я: EHLO ".$user_domen[0]."\n");
            $data = $this->get_data($smtp_conn)."\n";
            $this->add_log($data);
            $code = substr($data, 0, 3); // получаем код ответа

            if ($code != 250)
            {
                $this->add_log("ошибка приветсвия EHLO \n");
                fclose($smtp_conn);
                $this->status_mail['status'] = false;
                $this->status_mail['message'] = "ошибка приветсвия EHLO";
                return false;
            }

            fputs($smtp_conn, "AUTH LOGIN\r\n");
            $this->add_log("Я: AUTH LOGIN\n");
            $data = $this->get_data($smtp_conn)."\n";
            $this->add_log($data);
            $code = substr($data, 0, 3);

            if ($code != 334)
            {
                $this->add_log("сервер не разрешил начать авторизацию \n");
                fclose($smtp_conn);
                $this->status_mail['status'] = false;
                $this->status_mail['message'] = "сервер не разрешил начать авторизацию";
                return false;
            }

            fputs($smtp_conn, base64_encode($this->smtp['login'])."\r\n");
            $this->add_log("Я: ".base64_encode($this->smtp['login'])."\n");
            $data = $this->get_data($smtp_conn)."\n";
            $this->add_log($data);
            $code = substr($data, 0, 3);
            if ($code != 334)
            {
                $this->add_log("ошибка доступа к такому юзеру\n");
                fclose($smtp_conn);
                $this->status_mail['status'] = false;
                $this->status_mail['message'] = "ошибка доступа к такому юзеру через SMTP";
                return false;
            }


            fputs($smtp_conn, base64_encode($this->smtp['pass'])."\r\n");
            //$this->add_log("Я: ". base64_encode($this->smtp_pass)."\n"); // тут пароль закодирован будет виден в логах
            $this->add_log("Я: parol_skryt\n"); // а так пароль скрыт в логах
            $data = $this->get_data($smtp_conn)."\n";
            $this->add_log($data);
            $code = substr($data, 0, 3);
            if ($code != 235)
            {
                $this->add_log("не правильный пароль\n");
                fclose($smtp_conn);
                $this->status_mail['status'] = false;
                $this->status_mail['message'] = "не правильный пароль для SMTP";
                return false;
            }

            // а сейчас перебираем ресурсы, чтобы отправить каждый ресурс отдельным письмом
            // перебираем верхний уровень
            foreach ($this->smtpsendto as $key_res => $value_res)
            {
                // сбор письма по текущему ресурсу
                $this->BuildMail($key_res);
                // после сборки письма еще проверим статус ошибки
                if (!$this->status_mail['status'])
                {
                    return FALSE;
                }

                // если для текущего ресурса есть тело, то ресурс для тела ставим
                if (isset($this->body[$key_res]))
                    $body_resource = $key_res;
                // а если для текущего ресурса нет тела, ставим ресурс для тела по умолчанию
                else
                    $body_resource = 'webi';

                // начинаем отправку очередного письма
                fputs($smtp_conn, "MAIL FROM:<".$this->headers['From']."> SIZE=".strlen($this->ready_headers[$key_res]."\r\n".$this->body[$body_resource])."\r\n");
                $this->add_log("Я: MAIL FROM:<".$this->headers['From']."> SIZE=".strlen($this->ready_headers[$key_res]."\r\n".$this->body[$body_resource])."\n");
                $data = $this->get_data($smtp_conn)."\n";
                $this->add_log($data);

                $code = substr($data, 0, 3);
                if ($code != 250)
                {
                    $this->add_log("сервер отказал в команде MAIL FROM\n");
                    fclose($smtp_conn);
                    $this->status_mail['status'] = false;
                    $this->status_mail['message'] = "сервер отказал в команде MAIL FROM через SMTP";
                    return false;
                }



                foreach ($this->smtpsendto[$key_res] as $keywebi => $valuewebi)
                {
                    fputs($smtp_conn, "RCPT TO:<".$valuewebi.">\r\n");
                    $this->add_log("Я: RCPT TO:<".$valuewebi.">\n");
                    $data = $this->get_data($smtp_conn)."\n";
                    $this->add_log($data);
                    $code = substr($data, 0, 3);
                    if ($code != 250 AND $code != 251)
                    {
                        $this->add_log("Сервер не принял команду RCPT TO\n");
                        fclose($smtp_conn);
                        $this->status_mail['status'] = false;
                        $this->status_mail['message'] = "Сервер не принял команду RCPT через SMTP";
                        return false;
                    }
                }

                fputs($smtp_conn, "DATA\r\n");
                $this->add_log("Я: DATA\n");
                $data = $this->get_data($smtp_conn)."\n";
                $this->add_log($data);

                $code = substr($data, 0, 3);
                if ($code != 354)
                {
                    $this->add_log("сервер не принял DATA\n");
                    fclose($smtp_conn);
                    $this->status_mail['status'] = false;
                    $this->status_mail['message'] = "сервер не принял команду DATA черз SMTP";
                    return false;
                }

                fputs($smtp_conn, $this->ready_headers[$key_res]."\r\n".$this->body[$body_resource]."\r\n.\r\n");
                $this->add_log("Я: ".$this->ready_headers[$key_res]."\r\n".$this->body[$body_resource]."\r\n.\r\n");
                $data = $this->get_data($smtp_conn)."\n";
                $this->add_log($data);

                $code = substr($data, 0, 3);
                if ($code != 250)
                {
                    $this->add_log("ошибка отправки письма\n");
                    fclose($smtp_conn);
                    $this->status_mail['status'] = false;
                    $this->status_mail['message'] = "ошибка отправки письма через SMTP";
                    return false;
                }

                fputs($smtp_conn, "RSET\r\n"); // тепер делаем сброс того, что было введено серверу, чтобы можно было отправить еще письмо в следующем шаге цикла 
                $this->add_log("Я: RSET\n");
                $data = $this->get_data($smtp_conn)."\n";
                $this->add_log($data);

                $code = substr($data, 0, 3);
                if ($code != 250)
                {
                    $this->add_log("ошибка отправки письма\n");
                    fclose($smtp_conn);
                    $this->status_mail['status'] = false;
                    $this->status_mail['message'] = "Сервер не принял команду RSET";
                    return false;
                }

                // если ресурс не является ресурсом по умолчанию, удалим заголовки этого ресурса, они уже не понадобятся, а вот заголовки ресурса по умолчанию могут понадобиться                
                if ($key_res != 'webi')
                {
                    unset($this->headers[$key_res]);
                    unset($this->ready_headers[$key_res]);
                }
                // если ресурс для тела не является ресурсом по умолчанию, удалим тело этого ресурса, оно уже не нужно, а вот тело ресурса по умолчанию еще может понадобиться
                if ($body_resource != 'webi')
                {
                    unset($this->body[$body_resource]);
                }
            }
            // после обработки всех ресурсов посылаем выход
            fputs($smtp_conn, "QUIT\r\n");
            $this->add_log("QUIT\r\n");
            $data = $this->get_data($smtp_conn)."\n";
            $this->add_log($data);
            fclose($smtp_conn);

            $this->status_mail['status'] = true;
            $this->status_mail['message'] = "письмо успешно отправлено с помощью SMTP";
            return true;
        }
    }

    public function Get()
    {
        if (!$this->log_on)
            return 'Формирование лога отключено. Чтобы лог формировался нужно включить его $m->log_on(true);';

        if (strlen($this->smtp_log))
        {
            return $this->smtp_log; // если есть лог отправки выведем его   
        }
    }

}

?>
