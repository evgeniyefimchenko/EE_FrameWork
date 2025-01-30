<?php

namespace classes\system;

use classes\plugins\SafeMySQL;
use classes\helpers\ClassNotifications;

class FileSystem {

    /**
     * Проверяет сигнатуру файла на основе его содержимого
     * Функция открывает файл, считывает первые 12 байт и сравнивает с известными сигнатурами файлов
     * Поддерживаются различные типы файлов, включая изображения, аудио, видео, архивы и документы
     * @param string $tmpFilePath Путь к временно загруженному файлу
     * @return bool Возвращает true, если сигнатура файла соответствует поддерживаемому формату, иначе false
     */
    private static function checkFileSignature(string $tmpFilePath): bool {
        if (!is_readable($tmpFilePath)) {
            return false;
        }       
        // Открываем файл и считываем первые 12 байт
        $file = fopen($tmpFilePath, 'rb');
        $header = fread($file, 12);
        fclose($file);
        if (empty($header)) {
            $message = 'Неудалось считать заголовок файла!';
            SysClass::preFile('errors', 'checkFileSignature', $message, $tmpFilePath);
            return false; // Файл пуст или недоступен
        }
        // Список сигнатур файлов
        $signatures = [
            // Изображения
            'jpg' => "\xFF\xD8\xFF",
            'png' => "\x89\x50\x4E\x47",
            'gif' => "GIF",
            'bmp' => "\x42\x4D",
            'tiff' => ["\x49\x49\x2A\x00", "\x4D\x4D\x00\x2A"],
            'webp' => "\x52\x49\x46\x46", // Доп. проверка байтов 8-11
            'ico' => "\x00\x00\x01\x00",
            'psd' => "\x38\x42\x50\x53",
            'svg' => "<svg",
            // Аудио
            'wav' => "\x52\x49\x46\x46", // Доп. проверка байтов 8-11
            'flac' => "fLaC",
            'midi' => "MThd",
            'mp3' => "ID3",
            // Видео
            'avi' => "\x52\x49\x46\x46", // Доп. проверка байтов 8-11
            'mkv' => "\x1A\x45\xDF\xA3",
            'mov' => "\x66\x74\x79\x70",
            'mp4' => "ftyp",
            '3gp' => "\x66\x74\x79\x70\x33\x67",
            'wmv' => "\x30\x26\xB2\x75",
            // Архивы
            'zip' => "PK\x03\x04",
            'rar' => "Rar!",
            '7z' => "\x37\x7A\xBC\xAF\x27\x1C",
            'gzip' => "\x1F\x8B",
            'bz2' => "\x42\x5A\x68",
            // Документы
            'pdf' => "%PDF",
            'doc' => "\xD0\xCF\x11\xE0",
            'xlsx' => "PK\x03\x04", // Доп. проверка содержимого
            'odt' => "PK\x03\x04", // ODT
            'ods' => "PK\x03\x04", // ODS
            'odp' => "PK\x03\x04", // ODP
            'rtf' => "\x7B\x5C\x72\x74\x66",
            'epub' => "PK\x03\x04", // Доп. проверка содержимого
            // Шрифты
            'woff' => "wOFF",
            'woff2' => "wOF2",
            'ttf' => "\x00\x01\x00\x00",
            'otf' => "OTTO",
            // Другие форматы
            'iso' => "\x43\x44\x30\x30\x31",
            'sqlite' => "\x53\x51\x4C\x69\x74\x65",
            'xml' => "<?xml",
            'json' => ["\x7B", "["], // JSON (объект или массив)
        ];
        foreach ($signatures as $format => $signature) {
            if (is_array($signature)) {
                foreach ($signature as $sig) {
                    if (strpos($header, $sig) === 0) {
                        return true;
                    }
                }
            } else {
                if (strpos($header, $signature) === 0) {
                    // Дополнительные проверки для сложных форматов
                    if (in_array($format, ['webp', 'wav', 'avi'])) {
                        return self::checkRiffFormat($tmpFilePath, $format);
                    }
                    if (in_array($format, ['xlsx', 'epub'])) {
                        return self::checkZipBasedFormat($tmpFilePath, $format);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Проверка форматов на основе RIFF (например, AVI, WAV, WEBP)
     * @param string $tmpFilePath
     * @param string $format
     * @return bool
     */
    private static function checkRiffFormat(string $tmpFilePath, string $format): bool {
        $file = fopen($tmpFilePath, 'rb');
        fseek($file, 8); // Перемещаем указатель на байты 8-11
        $subHeader = fread($file, 4);
        fclose($file);

        $riffSubFormats = [
            'webp' => 'WEBP',
            'wav' => 'WAVE',
            'avi' => 'AVI ',
        ];

        return isset($riffSubFormats[$format]) && $subHeader === $riffSubFormats[$format];
    }

    /**
     * Проверка форматов, основанных на ZIP (например, XLSX, EPUB)
     * @param string $tmpFilePath
     * @param string $format
     * @return bool
     */
    private static function checkZipBasedFormat(string $tmpFilePath, string $format): bool {
        $zip = new \ZipArchive();
        if ($zip->open($tmpFilePath) === true) {
            $checkFiles = [
                'xlsx' => 'xl/workbook.xml',
                'epub' => 'mimetype',
            ];
            $result = isset($checkFiles[$format]) && $zip->locateName($checkFiles[$format]) !== false;
            $zip->close();
            return $result;
        }
        return false;
    }

    /**
     * Безопасно перемещает загруженный файл в указанную директорию, проверяя его расширение, MIME-тип и сигнатуру
     * Основные этапы:
     * - Проверка допустимых расширений файла и опасных расширений
     * - Проверка допустимых MIME-типов
     * - Проверка сигнатуры файла для дополнительной безопасности
     * - Создание уникального имени файла и его перемещение в соответствующую папку
     * - Создание директории для каждого MIME-типа, если она не существует
     * @param array $file Массив, содержащий информацию о загруженном файле (обычно это $_FILES['file'])
     * @return array массив с данными файла, если файл успешно загружен и перемещён
     * @throws Exception В случае ошибки перемещения файла или недопустимого файла
     * @global int ENV_MAX_FILE_SIZE Максимальный размер загружаемого файла
     * @global string ENV_SITE_PATH Путь к корневой директории сайта
     * @global string ENV_DIRSEP Разделитель директорий (например, '/' для Unix-подобных систем)
     * @global string ENV_DB_PREF Префикс для базы данных (используется для генерации уникального имени файла)
     */
    public static function safeMoveUploadedFile(array $file): ?array {
        $allowedExtensions = [
            // Изображения
            'jpeg', 'jpg', // image/jpeg
            'png', // image/png
            'gif', // image/gif
            'bmp', // image/bmp
            'tiff', 'tif', // image/tiff
            'webp', // image/webp
            'ico', // image/x-icon
            'psd', // image/vnd.adobe.photoshop
            'svg', // image/svg+xml
            // Аудио
            'wav', // audio/wav
            'flac', // audio/flac
            'midi', // audio/midi
            'mp3', // audio/mpeg
            // Видео
            'avi', // video/x-msvideo
            'mkv', // video/x-matroska
            'mov', // video/quicktime
            'mp4', // video/mp4
            '3gp', // video/3gpp
            'wmv', // video/x-ms-wmv
            // Архивы
            'zip', // application/zip
            'rar', // application/x-rar-compressed
            '7z', // application/x-7z-compressed
            'gz', // application/gzip
            'bz2', // application/x-bzip2
            // Документы
            'pdf', // application/pdf
            'doc', 'docx', // application/msword
            'xlsx', // application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
            'odt', // application/vnd.oasis.opendocument.text
            'ods', // application/vnd.oasis.opendocument.spreadsheet
            'odp', // application/vnd.oasis.opendocument.presentation
            'rtf', // application/rtf
            'epub', // application/epub+zip
            // Шрифты
            'woff', // font/woff
            'woff2', // font/woff2
            'ttf', // font/ttf
            'otf', // font/otf
            // Другие форматы
            'iso', // application/x-iso9660-image
            'sqlite', // application/vnd.sqlite3
            'csv', // text/csv
            'txt', // text/plain
            'xml', // application/xml
            'json'            // application/json
        ];
        $dangerousExtensions = [
            'php', // PHP скрипты
            'phtml', // PHP шаблоны
            'php3', // Старые версии PHP
            'php4', // Старые версии PHP
            'php5', // Старые версии PHP
            'php7', // Старые версии PHP
            'phar', // PHP архивы
            'exe', // Исполняемые файлы Windows
            'bat', // Батники (Windows Batch files)
            'sh', // Скрипты shell (Linux/Unix)
            'cmd', // Командные файлы Windows
            'com', // Старые DOS-команды
            'msi', // Установочные файлы Windows Installer
            'jar', // Java-архивы
            'vb', // Visual Basic скрипты
            'vbs', // Visual Basic Scripts
            'js', // JavaScript
            'jse', // Обфусцированные JavaScript
            'wsf', // Windows Script Files
            'wsh', // Windows Script Host
            'hta', // HTML Applications
            'scr', // Screensavers (может содержать код)
            'cpl', // Панели управления Windows
            'dll', // Библиотеки динамической компоновки
            'sys', // Системные файлы Windows
            'drv', // Драйверы Windows
            'ps1', // PowerShell скрипты
            'psm1', // PowerShell модули
            'vxd', // Virtual Device Drivers
            'pl', // Perl скрипты
            'cgi', // Common Gateway Interface скрипты
            'asp', // Active Server Pages
            'aspx', // ASP.NET Pages
        ];
        $tmpFilePath = $file['tmp_name'];
        $originalFileName = $file['name'];
        $mime = $file['type'];
        // Проверка размера файла
        if ($file['size'] > ENV_MAX_FILE_SIZE) {
            $message = 'Размер файла превышает допустимый лимит ENV_MAX_FILE_SIZE = ' . ENV_MAX_FILE_SIZE;
            SysClass::preFile('errors', 'safeMoveUploadedFile', $message, $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return NULL;
        }
        // Проверка расширения
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions) || in_array($fileExtension, $dangerousExtensions)) {
            $message = 'Недопустимое расширение файла ' . $originalFileName;
            SysClass::preFile('errors', 'safeMoveUploadedFile', $message, $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return NULL;
        }
        // Проверка MIME-типа
        // Список разрешённых MIME-типов
        $allowedMimeTypes = [
            // Изображения
            'image/jpeg', // JPEG/JPG
            'image/png', // PNG
            'image/gif', // GIF
            'image/bmp', // BMP
            'image/tiff', // TIFF
            'image/webp', // WEBP
            'image/x-icon', // ICO
            'image/vnd.adobe.photoshop', // PSD
            'image/svg+xml', // SVG
            // Аудио
            'audio/wav', // WAV
            'audio/flac', // FLAC
            'audio/midi', // MIDI
            'audio/mpeg', // MP3
            // Видео
            'video/x-msvideo', // AVI
            'video/x-matroska', // MKV
            'video/quicktime', // MOV
            'video/mp4', // MP4
            'video/3gpp', // 3GP
            'video/x-ms-wmv', // WMV
            // Архивы
            'application/zip', // ZIP/PKZip
            'application/x-zip-compressed', // ZIP
            'application/x-rar-compressed', // RAR
            'application/x-7z-compressed', // 7z
            'application/gzip', // GZIP
            'application/x-bzip2', // Bzip2
            // Документы
            'application/pdf', // PDF
            'application/msword', // DOC (старый формат Word)
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // XLSX
            'application/vnd.oasis.opendocument.text', // ODT
            'application/vnd.oasis.opendocument.spreadsheet', // ODS
            'application/vnd.oasis.opendocument.presentation', // ODP
            'application/rtf', // RTF
            'application/epub+zip', // EPUB
            // Шрифты
            'font/woff', // WOFF
            'font/woff2', // WOFF2
            'font/ttf', // TTF
            'font/otf', // OTF
            // Другие форматы
            'application/x-iso9660-image', // ISO
            'application/vnd.sqlite3', // SQLite Database
            'text/csv', // CSV
            'text/plain', // TXT
            'application/xml', // XML
            'text/xml',
            'application/json', // JSON
            'text/json',
        ];
        if (!in_array($mime, $allowedMimeTypes)) {
            $message = 'Недопустимый MIME-тип файла ' . $originalFileName . ' MIME:' . $mime;
            SysClass::preFile('errors', 'safeMoveUploadedFile', $message, $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return NULL;
        }
        // Проверка сигнатуры файла
        if (strpos($mime, 'text/') !== false) {
            // Пропустить проверку сигнатуры для текстовых файлов
        } elseif (!self::checkFileSignature($tmpFilePath)) {
            $message = 'Файл имеет недопустимую сигнатуру ' . $originalFileName;
            SysClass::preFile('errors', 'safeMoveUploadedFile', $message, $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return NULL;
        }
        // Создать уникальное имя файла и проверить его уникальность
        do {
            // Генерируем новое уникальное имя файла
            $fileName = md5(uniqid(ENV_DB_PREF, true)) . '.' . $fileExtension;
            $transliterateFileName = SysClass::transliterateFileName($file['type']);
            $targetDirectory = ENV_SITE_PATH . 'uploads' . ENV_DIRSEP . 'files' . ENV_DIRSEP . $transliterateFileName;
            $destination = $targetDirectory . ENV_DIRSEP . $fileName;
        } while (file_exists($destination));
        // Создать папку, если ещё не существует, для каждого MIME типа файла и поместить туда файл
        if (!SysClass::createDirectoriesForFile($destination) || !move_uploaded_file($file['tmp_name'], $destination)) {
            $message = "Не удалось переместить файл в: $destination";
            SysClass::preFile('errors', 'safeMoveUploadedFile', $message, $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return NULL;
        }
        $fileData['name'] = $fileName;
        $fileData['file_path'] = $destination;
        $fileData['file_url'] = ENV_URL_SITE . '/uploads/files/' . $transliterateFileName . '/' . $fileName;
        $fileData['mime_type'] = $mime;
        $fileData['size'] = $file['size'];
        $fileData['uploaded_at'] = date('Y-m-d H:i:s');
        return $fileData;
    }

    /**
     * Применяет набор трансформаций к изображению и сохраняет изменённую версию
     * Поддерживаются форматы: jpg, jpeg, png, gif, bmp, tiff, webp, ico
     * Используются библиотеки GD или Imagick (приоритетно)
     * @param string $filePath Путь к файлу изображения
     * @param array $transformations Массив трансформаций с необязательными ключами:
     * - 'rotation' (int): Угол поворота в градусах (по часовой стрелке)
     * - 'flipH' (bool): Если true, выполняется горизонтальное отражение
     * - 'flipV' (bool): Если true, выполняется вертикальное отражение
     * @throws Exception Если ни GD, ни Imagick не установлены
     * @throws Exception Если формат файла не поддерживается
     * @return void
     */
    public static function applyImageTransformations($filePath, $transformations): void {
        if (extension_loaded('imagick')) {
            self::applyWithImagick($filePath, $transformations);
        } elseif (extension_loaded('gd')) {
            self::applyWithGD($filePath, $transformations);
        } else {
            $message = 'Библиотеки GD или Imagick не установлены.';
            SysClass::preFile('errors', 'applyImageTransformations', $message, [$filePath, $transformations]);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
        }
    }

    /**
     * Применяет трансформации с использованием Imagick
     * @param string $filePath Путь к файлу изображения
     * @param array $transformations Массив трансформаций
     * @throws Exception Если произошла ошибка при обработке изображения
     */
    private static function applyWithImagick($filePath, $transformations): void {
        $image = new Imagick($filePath);
        if ($image) {
            if (isset($transformations['rotation']) && $transformations['rotation'] != 0) {
                $image->rotateImage(new ImagickPixel('none'), $transformations['rotation']);
            }
            if (isset($transformations['flipH']) && $transformations['flipH']) {
                $image->flopImage();
            }
            if (isset($transformations['flipV']) && $transformations['flipV']) {
                $image->flipImage();
            }
            $image->writeImage($filePath);
            $image->clear();
            $image->destroy();
        }
    }

    /**
     * Применяет трансформации с использованием GD
     * @param string $filePath Путь к файлу изображения
     * @param array $transformations Массив трансформаций (может включать 'rotation', 'flipH', 'flipV')
     * Если формат файла не поддерживается или возникает ошибка, добавляет запись в лог и уведомляет пользователя
     */
    private static function applyWithGD(string $filePath, array $transformations): void {
        $image = self::createImageFromFile($filePath);
        if ($image === false) {
            $message = "Не удалось загрузить изображение для обработки: $filePath";
            SysClass::preFile('errors', 'applyWithGD', $message, $filePath);
            ClassNotifications::addNotificationUser(
                    SysClass::getCurrentUserId(),
                    ['text' => $message, 'status' => 'danger']
            );
            return;
        }
        if (isset($transformations['rotation']) && $transformations['rotation'] != 0) {
            $rotationAngle = 360 - $transformations['rotation'];
            $image = imagerotate($image, $rotationAngle, 0);
        }
        if (isset($transformations['flipH']) && $transformations['flipH']) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if (isset($transformations['flipV']) && $transformations['flipV']) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        self::saveImageToFile($image, $filePath);
        imagedestroy($image);
    }

    /**
     * Создает изображение из файла, поддерживая различные форматы
     * Эта функция принимает путь к файлу изображения, определяет его расширение или MIME-тип,
     * и создает изображение с помощью соответствующей функции PHP. Если формат файла не поддерживается,
     * функция записывает ошибку и уведомляет пользователя
     * @param string $filePath Путь к файлу изображения
     * @return resource|false Ресурс изображения или `false` в случае ошибки
     */
    private static function createImageFromFile(string $filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        // Если расширения нет, определяем его с помощью MIME-типа
        if (empty($extension)) {
            $extension = self::getExtensionFromMimeType($filePath);
            if ($extension === null) {
                $message = "Неподдерживаемый формат файла или ошибка определения MIME-типа: $filePath";
                SysClass::preFile('errors', 'createImageFromFile', $message, $filePath);
                ClassNotifications::addNotificationUser(
                        SysClass::getCurrentUserId(),
                        ['text' => $message, 'status' => 'danger']
                );
                return false;
            }
        }
        // Определяем функцию для создания изображения по расширению
        $image = match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($filePath),
            'png' => imagecreatefrompng($filePath),
            'gif' => imagecreatefromgif($filePath),
            'bmp' => imagecreatefrombmp($filePath),
            'webp' => imagecreatefromwebp($filePath),
            'ico' => imagecreatefromstring(file_get_contents($filePath)),
            default => null,
        };
        // Если формат не поддерживается
        if ($image === null) {
            $message = "Неподдерживаемый формат файла: $extension";
            SysClass::preFile('errors', 'createImageFromFile', $message, $filePath);
            ClassNotifications::addNotificationUser(
                    SysClass::getCurrentUserId(),
                    ['text' => $message, 'status' => 'danger']
            );
            return false;
        }
        return $image;
    }

    /**
     * Сохраняет изображение с использованием GD
     * @param resource $image Изображение GD
     * @param string $filePath Путь к файлу изображения
     * Если формат файла не поддерживается или отсутствует, добавляет запись в лог и уведомляет пользователя
     */
    private static function saveImageToFile($image, string $filePath): void {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (empty($extension)) {
            $extension = self::getExtensionFromMimeType($filePath);

            if ($extension === null) {
                $message = "Неподдерживаемый формат файла или ошибка определения MIME-типа для сохранения: $filePath";
                SysClass::preFile('errors', 'saveImageToFile', $message, $filePath);
                ClassNotifications::addNotificationUser(
                        SysClass::getCurrentUserId(),
                        ['text' => $message, 'status' => 'danger']
                );
                return;
            }
        }
        $success = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $filePath),
            'png' => imagepng($image, $filePath),
            'gif' => imagegif($image, $filePath),
            'bmp' => imagebmp($image, $filePath),
            'webp' => imagewebp($image, $filePath),
            'ico' => file_put_contents($filePath, file_get_contents($filePath)) !== false,
            default => false,
        };
        if (!$success) {
            $message = "Не удалось сохранить изображение: неподдерживаемый формат файла $extension для $filePath";
            SysClass::preFile('errors', 'saveImageToFile', $message, $filePath);
            ClassNotifications::addNotificationUser(
                    SysClass::getCurrentUserId(),
                    ['text' => $message, 'status' => 'danger']
            );
        }
    }

    /**
     * Получает расширение файла на основе MIME-типа
     * Эта функция принимает путь к файлу, определяет его MIME-тип с помощью `mime_content_type` и
     * возвращает соответствующее расширение файла. Если MIME-тип не поддерживается, функция возвращает `null`
     * и отправляет сообщение об ошибке в журнал и пользователю
     * @param string $filePath Путь к файлу, для которого необходимо определить расширение
     * @return string|null Расширение файла (например, 'jpg', 'png'), или `null`, если MIME-тип не поддерживается
     */
    private static function getExtensionFromMimeType(string $filePath): ?string {
        $mimeType = mime_content_type($filePath);
        if ($mimeType === false) {
            $message = "Не удалось определить MIME-тип для файла: $filePath";
            SysClass::preFile('errors', 'getExtensionFromMimeType', $message, $filePath);
            ClassNotifications::addNotificationUser(
                    SysClass::getCurrentUserId(),
                    ['text' => $message, 'status' => 'danger']
            );
            return null;
        }
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            'image/x-icon' => 'ico',
            default => null,
        };
    }

    /**
     * Сохраняет информацию о загруженном файле в таблицу файлов и возвращает его ID
     * @param array $fileData Ассоциативный массив с данными о файле
     * @return int|null Возвращает ID файла или NULL в случае ошибки
     */
    public static function saveFileInfo(array $fileData): ?int {
        $fileData = SafeMySQL::gi()->filterArray($fileData, SysClass::ee_getFieldsTable(Constants::FILES_TABLE));
        $fileData = array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $fileData);
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::FILES_TABLE, $fileData);
        if ($result) {
            return SafeMySQL::gi()->insertId();
        }
        return NULL;
    }

    /**
     * Вернёт данные файла из БД
     * Выполнив проверку на существование файла на диске
     * @param int $fileId ID файла в БД
     * @return array|null массив данных или NULL
     */
    public static function getFileData(int $fileId): ?array {
        $sql = 'SELECT * FROM ?n WHERE file_id = ?i';
        $result = SafeMySQL::gi()->getRow($sql, Constants::FILES_TABLE, $fileId);
        if ($result) {
            if (!file_exists($result['file_path'])) {
                $message = 'Не удалось найти файл на диске: ' . $result['file_path'] . ' fileId: ' . $fileId;
                SysClass::preFile('errors', 'getFileData', $message, $result);
                ClassNotifications::addNotificationUser(
                        SysClass::getCurrentUserId(),
                        ['text' => $message, 'status' => 'danger']
                );
            }
        }
        return $result ? $result : NULL;
    }

    /**
     * Удалит данные файла из таблицы и файл с диска
     * @param int $fileId - ID файла в таблице
     * @return void
     */
    public static function deleteFileData(int $fileId): void {
        $fileData = self::getFileData($fileId);
        if (!@unlink($fileData['file_path'])) {
            $fileData['file_path'] = !empty($fileData['file_path']) ? $fileData['file_path'] : 'Нет записи в БД';
            $message = 'Не удалось удалить файл с диска: ' . $fileData['file_path'];
            SysClass::preFile('errors', 'deleteFileData', $message, $fileData['file_path']);
            ClassNotifications::addNotificationUser(
                    SysClass::getCurrentUserId(),
                    ['text' => $message, 'status' => 'danger']
            );
        }
        $sql = 'DELETE FROM ?n WHERE file_id = ?i';
        SafeMySQL::gi()->query($sql, Constants::FILES_TABLE, $fileId);
    }

    /**
     * Обновить данные файла
     * @param int $fileId - ID файла в таблице
     * @param array $fileData - Данные для обновления
     * @return int|null
     */
    public static function updateFileData(int $fileId, array $fileData): ?int {
        $fileData = SafeMySQL::gi()->filterArray($fileData, SysClass::ee_getFieldsTable(Constants::FILES_TABLE));
        $fileData = array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $fileData);
        $fileData['updated_at'] = date('Y-m-d H:i:s');
        $sql = "UPDATE ?n SET ?u WHERE file_id = ?i";
        $result = SafeMySQL::gi()->query($sql, Constants::FILES_TABLE, $fileData, $fileId);
        return $result ? $result : NULL;
    }

    /**
     * Возвращает текстовое описание ошибки загрузки файла по коду ошибки
     * @param int $code Код ошибки загрузки файла из $_FILES
     * @param string $lang_code Язык возвращаемого сообщения. 'RU' для русского, любой другой код для английского
     * @return string Описание ошибки на выбранном языке
     */
    public static function getErrorDescriptionByUploadCode(int $code, string $lang_code = ENV_DEF_LANG): string {
        $errors = [
            'RU' => [
                UPLOAD_ERR_OK => 'Файл успешно загружен.',
                UPLOAD_ERR_INI_SIZE => 'Размер файла превышает максимально допустимый размер, заданный директивой upload_max_filesize в php.ini ' . ini_get('upload_max_filesize'),
                UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает указанное значение в HTML-форме.',
                UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично.',
                UPLOAD_ERR_NO_FILE => 'Файл не был загружен.',
                UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка.',
                UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
                UPLOAD_ERR_EXTENSION => 'PHP-расширение остановило загрузку файла.',
            ],
            'EN' => [
                UPLOAD_ERR_OK => 'The file was uploaded successfully.',
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.',
                UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            ],
        ];
        $language = strtoupper($lang_code) === 'RU' ? 'RU' : 'EN';
        return $errors[$language][$code] ?? 'Неизвестная ошибка загрузки файла.';
    }

    /**
     * Получает HTML-код иконки для указанного расширения файла
     * @param string $extension - Расширение файла
     * @return string - HTML-код иконки
     */
    public static function getFileIcon(string $extension, string $addStyle = 'fa-4x'): string {
        return match ($extension) {
            'pdf' => '<i class="fas fa-file-pdf ' . $addStyle . '"></i>',
            'doc', 'docx' => '<i class="fas fa-file-word ' . $addStyle . '"></i>',
            'xls', 'xlsx' => '<i class="fas fa-file-excel ' . $addStyle . '"></i>',
            'ppt', 'pptx' => '<i class="fas fa-file-powerpoint ' . $addStyle . '"></i>',
            'zip', 'rar', 'tar', 'gz' => '<i class="fas fa-file-archive ' . $addStyle . '"></i>',
            'txt' => '<i class="fas fa-file-alt ' . $addStyle . '"></i>',
            default => '<i class="fas fa-file ' . $addStyle . '"></i>',
        };
    }
}
