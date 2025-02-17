<?php

namespace classes\system;

use classes\plugins\SafeMySQL;
use classes\helpers\ClassNotifications;

class FileSystem {

    private static $allowedExtensions = [
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
        'json' // application/json
    ];
    private static $dangerousExtensions = [
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
    // Список разрешённых MIME-типов
    private static $allowedMimeTypes = [
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
    // Список сигнатур файлов
    private static $signatures = [
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
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $tmpFilePath);
            return false; // Файл пуст или недоступен
        }
        foreach (self::$signatures as $format => $signature) {
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
     * Если файл является изображением, определяет его размер (small, medium, large)
     * Если ENV_CREATE_WEBP = true, преобразует изображение в формат WebP и сохраняет только его
     * @param array $file Массив, содержащий информацию о загруженном файле (обычно это $_FILES['file'])
     * @return array|null Массив с данными файла, если файл успешно загружен и перемещён, иначе null
     * @throws Exception В случае ошибки обработки файла
     * @global int ENV_MAX_FILE_SIZE Максимальный размер загружаемого файла
     * @global string ENV_SITE_PATH Путь к корневой директории сайта
     * @global string ENV_DIRSEP Разделитель директорий (например, '/' для Unix-подобных систем)
     * @global string ENV_DB_PREF Префикс для базы данных (используется для генерации уникального имени файла)
     * @global bool ENV_CREATE_WEBP Флаг, определяющий необходимость преобразования изображений в WebP
     */
    public static function safeMoveUploadedFile(array $file): ?array {
        $tmpFilePath = $file['tmp_name'];
        $originalFileName = $file['name'];
        $mime = $file['type'];
        $fileData = [];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        // 1. Валидация файла
        if (!self::validateFile($file, $fileExtension)) {
            unlink($tmpFilePath);
            return NULL;
        }
        // 2. Генерация уникального имени файла        
        $transliterateFileTypeName = SysClass::transliterateFileName($file['type']);
        $targetDirectory = ENV_SITE_PATH . 'uploads' . ENV_DIRSEP . 'files' . ENV_DIRSEP . $transliterateFileTypeName . ENV_DIRSEP . date('d.m.Y');
        $fileName = self::generateUniqueFileName($fileExtension, $targetDirectory);
        $destination = $targetDirectory . ENV_DIRSEP . $fileName;
        // 3. Обработка изображения (конвертация в WebP и определение размера)
        $imageSize = null;
        $shouldConvertToWebp = defined('ENV_CREATE_WEBP') && ENV_CREATE_WEBP === true && strpos($mime, 'image/') !== false;
        try {
            if ($shouldConvertToWebp) {
                $fileExtension = 'webp';
                $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . $fileExtension;
                $mime = 'image/webp';
                $transliterateFileTypeName = 'webp';
                $targetDirectory = ENV_SITE_PATH . 'uploads' . ENV_DIRSEP . 'files' . ENV_DIRSEP . $transliterateFileTypeName . ENV_DIRSEP . date('d.m.Y');
                $destination = $targetDirectory . ENV_DIRSEP . $fileName;
                $imageSize = self::processImage($tmpFilePath);
            } else {
                $imageSize = self::getImageSizeFromFile($tmpFilePath);
            }
            // Проверка хеша файла
            $fileHash = md5_file($tmpFilePath);
            $checkDataFiles = SafeMySQL::gi()->getRow('SELECT * FROM ?n WHERE file_hash = ?s', Constants::FILES_TABLE, $fileHash);
            if ($checkDataFiles) { // Есть дубль файла в системе
                $fileData['name'] = $checkDataFiles['name'];
                $fileData['file_path'] = $checkDataFiles['file_path'];
                $fileData['file_url'] = $checkDataFiles['file_url'];
                $fileData['mime_type'] = $checkDataFiles['mime_type'];
                $fileData['size'] = $checkDataFiles['size'];
                $fileData['uploaded_at'] = $checkDataFiles['uploaded_at'];
                $fileData['updated_at'] = date('Y-m-d H:i:s');
                $fileData['image_size'] = $checkDataFiles['image_size'];
                $fileData['file_hash'] = $fileHash;
                $fileData['file_id'] = $checkDataFiles['file_id'];
            } else {
                // Создать папку, если ещё не существует, для каждого MIME типа файла
                if (!SysClass::createDirectoriesForFile($destination) || !move_uploaded_file($tmpFilePath, $destination)) {
                    $message = "Не удалось переместить файл в: $destination";
                    new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $file);
                    ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                    return NULL;
                } else {
                    $fileData['name'] = $fileName;
                    $fileData['file_path'] = $destination;
                    $fileData['file_url'] = ENV_URL_SITE . '/uploads/files/' . $transliterateFileTypeName . '/' . date('d.m.Y') . '/' . $fileName;
                    $fileData['mime_type'] = $mime;
                    $fileData['size'] = $file['size'];
                    $fileData['uploaded_at'] = date('Y-m-d H:i:s');
                    $fileData['image_size'] = $imageSize;
                    $fileData['file_hash'] = $fileHash;
                    $fileData['file_id'] = 0;
                }
            }
        } catch (\Exception $e) {
            $message = 'Ошибка при обработке файла: ' . $e->getMessage();
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return NULL;
        } finally {
            // Удалять исходный файл после перемещения
            @unlink($tmpFilePath);
        }
        return $fileData;
    }

    /**
     * Определяет размер изображения ('small', 'medium', 'large') из файла без изменения формата.
     * @param string $tmpFilePath Временный путь к файлу изображения.
     * @return string Размер изображения ('small', 'medium', 'large').
     * @throws Exception В случае ошибки при чтении размера изображения.
     */
    private static function getImageSizeFromFile(string $tmpFilePath): string {
        try {
            if (extension_loaded('imagick')) {
                $image = new \Imagick($tmpFilePath);
                $width = $image->getImageWidth();
                $height = $image->getImageHeight();
                $image->destroy();
            } elseif (function_exists('getimagesize')) {
                $imageSize = getimagesize($tmpFilePath);
                if ($imageSize === false) {
                    $message = 'Не удалось получить размер изображения с помощью getimagesize.';
                    new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $tmpFilePath);
                    ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                }
                $width = $imageSize[0];
                $height = $imageSize[1];
            } else {
                $message = 'Необходимы расширения GD или Imagick.';
                new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $tmpFilePath);
                ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            }
            return self::getImageSize($width, $height);
        } catch (\Exception $e) {
            $message = 'Ошибка при определении размера изображения!';
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $e->getMessage());
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
        }
    }

    /**
     * Определяет размер изображения
     * @param int $width Ширина изображения
     * @param int $height Высота изображения
     * @return string Размер изображения ('small', 'medium', 'large')
     * Изображение размером 200x200 пикселей: 200 * 200 = 40000 пикселей (маленькое)
     * Изображение размером 500x500 пикселей: 500 * 500 = 250000 пикселей (среднее)
     * Изображение размером 1000x1000 пикселей: 1000 * 1000 = 1000000 пикселей (большое)
     */
    private static function getImageSize(int $width, int $height): string {
        $size = $width * $height;
        if ($size <= 100000) {
            return 'small';
        } elseif ($size <= 500000) {
            return 'medium';
        } else {
            return 'large';
        }
    }

    /**
     * Преобразует изображение в формат WebP, определяет его размер и сохраняет
     * @param string $tmpFilePath Временный путь к файлу
     * @return string Размер изображения ('small', 'medium', 'large')
     */
    private static function processImage(string $tmpFilePath): string {
        try {
            if (extension_loaded('imagick')) {
                $image = new Imagick($tmpFilePath);
                $image->setImageFormat('webp');
                $image->writeImage($tmpFilePath);
                $width = $image->getImageWidth();
                $height = $image->getImageHeight();
                $image->clear();
                $image->destroy();
            } elseif (function_exists('imagecreatefromstring')) {
                $imageContent = file_get_contents($tmpFilePath);
                if ($imageContent === false) {
                    $message = 'Не удалось прочитать содержимое файла';
                    new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', ['tmpFilePath' => $tmpFilePath]);
                    ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                }
                $image = imagecreatefromstring($imageContent);
                if ($image === false) {
                    $message = 'Не удалось создать изображение из содержимого файла';
                    new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', ['tmpFilePath' => $tmpFilePath]);
                    ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                }
                $width = imagesx($image);
                $height = imagesy($image);
                if (!imagewebp($image, $tmpFilePath, ENV_WEBP_QUALITY)) {
                    $message = 'Не удалось преобразовать изображение в WebP';
                    new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', ['tmpFilePath' => $tmpFilePath]);
                    ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                }
                imagedestroy($image);
            } else {
                $message = 'Необходимы расширения GD или Imagick';
                new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', ['tmpFilePath' => $tmpFilePath]);
                ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            }
            return self::getImageSize($width, $height);
        } catch (\Exception $e) {
            $message = 'Ошибка при обработке изображения!';
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', ['error' => $e->getMessage(), 'tmpFilePath' => $tmpFilePath]);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
        }
    }

    /**
     * Валидирует загруженный файл по размеру, расширению, MIME-типу и сигнатуре
     * @param array $file Массив $_FILES
     * @param string $fileExtension Расширение файла
     * @return bool true, если файл прошел валидацию, иначе false
     */
    private static function validateFile(array $file, string $fileExtension): bool {
        $tmpFilePath = $file['tmp_name'];
        $originalFileName = $file['name'];
        $mime = $file['type'];
        // Проверка размера файла
        if ($file['size'] > ENV_MAX_FILE_SIZE) {
            $message = 'Размер файла превышает допустимый лимит ENV_MAX_FILE_SIZE = ' . ENV_MAX_FILE_SIZE;
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return false;
        }
        // Проверка расширения
        if (!in_array($fileExtension, self::$allowedExtensions) || in_array($fileExtension, self::$dangerousExtensions)) {
            $message = 'Недопустимое расширение файла ' . $originalFileName;
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return false;
        }
        // Проверка MIME-типа
        if (!in_array($mime, self::$allowedMimeTypes)) {
            $message = 'Недопустимый MIME-тип файла ' . $originalFileName . ' MIME:' . $mime;
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return false;
        }
        // Проверка сигнатуры файла
        if (strpos($mime, 'text/') !== false) {
            // Пропустить проверку сигнатуры для текстовых файлов
        } elseif (!self::checkFileSignature($tmpFilePath)) {
            $message = 'Файл имеет недопустимую сигнатуру ' . $originalFileName;
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return false;
        }
        return true;
    }

    /**
     * Генерирует уникальное имя файла
     * @param string $fileExtension Расширение файла
     * @return string Уникальное имя файла
     */
    private static function generateUniqueFileName(string $fileExtension, string $targetDirectory): string {
        do {
            $fileName = md5(uniqid(ENV_DB_PREF, true)) . '.' . $fileExtension;
            $destination = $targetDirectory . ENV_DIRSEP . $fileName;
        } while (file_exists($destination));
        return $fileName;
    }

    /**
     * Перемещает загруженный файл в указанное место назначения
     * @param string $tmpFilePath Временный путь к файлу
     * @param string $destination Путь назначения
     * @param array $file  Массив $_FILES
     * @return bool true, если файл успешно перемещен, иначе false
     */
    private static function moveFile(string $tmpFilePath, string $destination, array $file): bool {
        if (!SysClass::createDirectoriesForFile($destination) || !move_uploaded_file($tmpFilePath, $destination)) {
            $message = "Не удалось переместить файл в: $destination";
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return false;
        }
        return true;
    }

    /**
     * Создает массив с данными о файле
     * @param string $fileName Имя файла
     * @param string $destination Путь к файлу
     * @param string $mime Mime тип файла
     * @param int $fileSize Размер файла
     * @return array Массив с данными о файле
     */
    private static function createFileDataArray(string $fileName, string $destination, string $mime, int $fileSize, string $transliterateFileName): array {
        $fileData = [];
        $fileData['name'] = $fileName;
        $fileData['file_path'] = $destination;
        $fileData['file_url'] = ENV_URL_SITE . '/uploads/files/' . $transliterateFileName . '/' . $fileName;
        $fileData['mime_type'] = $mime;
        $fileData['size'] = $fileSize;
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
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', [$filePath, $transformations]);
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
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $filePath);
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
                new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $filePath);
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
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $filePath);
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
                new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $filePath);
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
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $filePath);
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
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $filePath);
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
        if (!empty($fileData['file_id'])) { // Если передан file_id предполагаем наличие дубля md5_file
            return $fileData['file_id'];
        }
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
        $error = false;
        if ($result) {
            if (!file_exists($result['file_path'])) {
                $message = 'Не удалось найти файл на диске: ' . $result['file_path'] . ' fileId: ' . $fileId;
                $error = true;
            }
        } else {
            $message = 'Нет информации о файле в БД!';
            $error = true;
        }
        if ($error) {
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', ['result' => $result, 'fileId' => $fileId]);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
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
        if ($fileData) {
            $result = 0;
            // Проверка по дефолтным полям свойств
            $result += self::checkFileIdInTables(Constants::PROPERTIES_TABLE, 'default_values', 'default', $fileId);
            // Проверка по действующим полям свойств
            $result += self::checkFileIdInTables(Constants::PROPERTY_VALUES_TABLE, 'property_values', 'value', $fileId);
            if ($result > 1) { // Файл используется в нескольких сущностях, не удаляем его физически
            } else {
                if (!@unlink($fileData['file_path'])) { // Удаляем файл с диска, обрабатываем ошибку
                    $fileData['file_path'] = !empty($fileData['file_path']) ? $fileData['file_path'] : 'Нет записи в БД';
                    $message = 'Не удалось удалить файл с диска: ' . $fileData['file_path'];
                    new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $fileData);
                    ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                }
                // Удаляем информацию по файлу в БД
                $sql = 'DELETE FROM ?n WHERE file_id = ?i';
                SafeMySQL::gi()->query($sql, Constants::FILES_TABLE, $fileId);
            }
        }
    }

    /**
     * Проверяет наличие указанного fileId в JSON-полях таблицы
     * Функция ищет в указанной таблице записи, где в JSON-поле содержатся объекты
     * с типом "image" или "file", и проверяет, содержится ли fileId в указанном поле
     * @param string $tableName Имя таблицы для поиска
     * @param string $jsonField Имя JSON-поля в таблице
     * @param string $searchField Поле в JSON-объекте, в котором ищется fileId (например, "default" или "value")
     * @param int $fileId Идентификатор файла, который ищется
     * @return int Количество совпадений
     */
    private static function checkFileIdInTables(string $tableName, string $jsonField, string $searchField, int $fileId): int {
        $data = SafeMySQL::gi()->getAll(
                "SELECT $jsonField FROM ?n WHERE JSON_SEARCH($jsonField, 'one', 'image', NULL, '$[*].type') IS NOT NULL 
                                            OR JSON_SEARCH($jsonField, 'one', 'file', NULL, '$[*].type') IS NOT NULL;", $tableName);
        $result = 0;
        // Обрабатываем каждую запись
        foreach ($data as $row) {
            $jsonData = json_decode($row[$jsonField], true);
            // Проверяем каждый элемент JSON-массива
            foreach ($jsonData as $item) {
                // Если тип "image" или "file" и поле для поиска существует
                if (($item['type'] === 'image' || $item['type'] === 'file') && isset($item[$searchField])) {
                    if (in_array($fileId, explode(',', $item[$searchField]))) {
                        $result += 1;
                    }
                }
            }
        }
        return $result;
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

    /**
     * Извлекает изображения в формате base64 из текста, преобразует их в формат webp и сохраняет на диск
     * Возвращает текст с замененными src base64 на пути к сохраненным файлам
     * @param string $text Текст для обработки
     * @return string Обработанный текст с замененными путями к изображениям
     */
    public static function extractBase64Images(string $text): string {
        if (empty(ENV_CREATE_WEBP)) return $text;
        $decodedText = html_entity_decode($text);
        $targetDirectory = ENV_SITE_PATH . 'uploads' . ENV_DIRSEP . 'files' . ENV_DIRSEP . 'images' . ENV_DIRSEP . date('d.m.Y');
        SysClass::createDirectoriesForFile($targetDirectory . '/temp.txt');
        $pattern = '/<img[^>]*src="data:image\/(png|jpeg|gif);base64,([^"]*)"[^>]*>/i';        
        $result = preg_replace_callback($pattern, function ($matches) use ($targetDirectory) {
            $message = 'Ошибка при декодировании!';
            $imageData = $matches[2];
            $imageFormat = $matches[1];
            $decodedData = base64_decode($imageData);
            if ($decodedData === false) {
                ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                return $matches[0];
            }
            $fileName = self::generateUniqueFileName('webp', $targetDirectory);
            $filePath = $targetDirectory . ENV_DIRSEP . $fileName;
            if (self::saveBase64ImageAsWebp($decodedData, $filePath)) {
                $fileUrl = ENV_URL_SITE . '/uploads/files/images/' . date('d.m.Y') . '/' . $fileName;
                return '<img src="' . $fileUrl . '">';
            } else {
                ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                return $matches[0];
            }
        }, $decodedText);
        // Кодируем HTML entities обратно
        $encodedResult = htmlentities($result, ENT_QUOTES, 'UTF-8');
        return $encodedResult;
    }

    /**
     * Сохраняет base64-encoded image в формате WebP
     * @param string $data Декодированные данные изображения
     * @param string $filePath Полный путь для сохранения изображения
     * @return bool True в случае успеха, false в случае неудачи
     */
    private static function saveBase64ImageAsWebp(string $data, string $filePath): bool {
        try {
            $image = imagecreatefromstring($data);
            if ($image === false) {
                return false;
            }
            // Сохраняем как WebP с качеством 80
            $result = imagewebp($image, $filePath, 80);
            imagedestroy($image);
            return $result;
        } catch (\Exception $e) {
            $message = 'Ошибка при сохранении изображения: ' . $e->getMessage();
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'file', $filePath);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return false;
        }
    }
}
