<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

class FileSystem {

    /**
     * Проверяет сигнатуру файла на основе его содержимого
     * Функция открывает файл, считывает первые 12 байт и сравнивает с известными сигнатурами файлов
     * Поддерживаются различные типы файлов, включая изображения, аудио, видео, архивы и документы
     * @param string $tmpFilePath Путь к временно загруженному файлу
     * @return bool Возвращает true, если сигнатура файла соответствует поддерживаемому формату, иначе false
     */
    public static function checkFileSignature(string $tmpFilePath): bool {
        $file = fopen($tmpFilePath, 'rb');
        $header = fread($file, 12); // Читаем первые 12 байт
        fclose($file);
        // Список сигнатур файлов
        $signatures = [
            // Изображения
            'jpg' => "\xFF\xD8\xFF", // JPEG/JPG
            'png' => "\x89\x50\x4E\x47", // PNG
            'gif' => "GIF", // GIF
            'bmp' => "\x42\x4D", // BMP (Windows Bitmap)
            'tiff' => ["\x49\x49\x2A\x00", "\x4D\x4D\x00\x2A"], // TIFF (Intel/Big-endian)
            'webp' => "\x52\x49\x46\x46", // WEBP (RIFF-based format)
            'ico' => "\x00\x00\x01\x00", // ICO (Windows Icon)
            'psd' => "\x38\x42\x50\x53", // PSD (Photoshop)
            'svg' => "<svg", // SVG (Scalable Vector Graphics)
            // Аудио
            'wav' => "\x52\x49\x46\x46", // WAV
            'flac' => "fLaC", // FLAC
            'midi' => "MThd", // MIDI
            'mp3' => "ID3", // MP3
            // Видео
            'avi' => "\x52\x49\x46\x46", // AVI (RIFF)
            'mkv' => "\x1A\x45\xDF\xA3", // MKV
            'mov' => "\x66\x74\x79\x70", // MOV/QuickTime
            'mp4' => "ftyp", // MP4
            '3gp' => "\x66\x74\x79\x70\x33\x67", // 3GP
            'wmv' => "\x30\x26\xB2\x75", // WMV
            // Архивы
            'zip' => "PK\x03\x04", // ZIP/PKZip
            'rar' => "Rar!", // RAR
            '7z' => "\x37\x7A\xBC\xAF\x27\x1C", // 7z
            'gzip' => "\x1F\x8B", // GZIP
            'bz2' => "\x42\x5A\x68", // Bzip2
            // Документы
            'pdf' => "%PDF", // PDF
            'doc' => "\xD0\xCF\x11\xE0", // DOC (старый формат Word)
            'xlsx' => "PK\x03\x04", // DOCX/XLSX/PPTX (новый формат Office)
            'odt' => "PK\x03\x04", // ODT (OpenDocument Text)
            'ods' => "PK\x03\x04", // ODS (OpenDocument Spreadsheet)
            'odp' => "PK\x03\x04", // ODP (OpenDocument Presentation)            
            'rtf' => "\x7B\x5C\x72\x74\x66", // RTF (Rich Text Format)
            'epub' => "PK\x03\x04", // EPUB (совпадает с форматом ZIP)
            // Шрифты
            'woff' => "wOFF", // WOFF
            'woff2' => "wOF2", // WOFF2
            'ttf' => "\x00\x01\x00\x00", // TTF (TrueType Font)
            'otf' => "OTTO", // OTF (OpenType Font)
            // Другие форматы
            'iso' => "\x43\x44\x30\x30\x31", // ISO9660 CD/DVD
            'sqlite' => "\x53\x51\x4C\x69\x74\x65", // SQLite Database
            //'exe' => "\x4D\x5A", // EXE (Windows Executable)
            'csv' => "\x30", // CSV (Comma Separated Values)
            'txt' => "\x41", // TXT (простой текстовый формат)
            'xml' => "<?xml", // XML (Extensible Markup Language)
            'json' => "\x7B", // JSON (JavaScript Object Notation)
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
                    return true;
                }
            }
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
            $message = 'Размер файла превышает допустимый лимит ' . ENV_MAX_FILE_SIZE;
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
            'application/json', // JSON
        ];
        if (!in_array($mime, $allowedMimeTypes)) {
            $message = 'Недопустимый MIME-тип файла ' . $originalFileName;
            SysClass::preFile('errors', 'safeMoveUploadedFile', $message, $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return NULL;
        }
        // Проверка сигнатуры файла
        if (!self::checkFileSignature($tmpFilePath)) {
            $message = 'Файл имеет недопустимую сигнатуру ' . $originalFileName;
            SysClass::preFile('errors', 'safeMoveUploadedFile', $message, $file);
            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            return NULL;
        }
        // Создать уникальное имя файла и проверить его уникальность
        do {
            // Генерируем новое уникальное имя файла
            $fileName = md5(uniqid(ENV_DB_PREF, true)) . '.' . $fileExtension;
            // Создать под каждый mime type свою папку
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
            throw new Exception('Библиотеки GD или Imagick не установлены.');
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

    /**
     * Применяет трансформации с использованием GD
     * @param string $filePath Путь к файлу изображения
     * @param array $transformations Массив трансформаций
     * @throws Exception Если формат файла не поддерживается
     */
    private static function applyWithGD($filePath, $transformations): void {
        $image = self::createImageFromFile($filePath);
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
     * Создаёт изображение с использованием GD
     * @param string $filePath Путь к файлу изображения
     * @return resource Изображение GD
     * @throws Exception Если формат файла не поддерживается
     */
    private static function createImageFromFile($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($filePath);
            case 'png':
                return imagecreatefrompng($filePath);
            case 'gif':
                return imagecreatefromgif($filePath);
            case 'bmp':
                return imagecreatefrombmp($filePath);
            case 'webp':
                return imagecreatefromwebp($filePath);
            case 'ico':
                return imagecreatefromstring(file_get_contents($filePath));
            default:
                throw new Exception("Неподдерживаемый формат файла: $extension");
        }
    }

    /**
     * Сохраняет изображение с использованием GD
     * @param resource $image Изображение GD
     * @param string $filePath Путь к файлу изображения
     * @throws Exception Если формат файла не поддерживается
     */
    private static function saveImageToFile($image, $filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $filePath);
                break;
            case 'png':
                imagepng($image, $filePath);
                break;
            case 'gif':
                imagegif($image, $filePath);
                break;
            case 'bmp':
                imagebmp($image, $filePath);
                break;
            case 'webp':
                imagewebp($image, $filePath);
                break;
            case 'ico':
                file_put_contents($filePath, file_get_contents($filePath));
                break;
            default:
                throw new Exception("Неподдерживаемый формат файла: $extension");
        }
    }
    
    /**
     * Сохраняет информацию о загруженном файле в таблицу файлов и возвращает его ID
     * @param array $fileData Ассоциативный массив с данными о файле
     * @return int|null Возвращает ID файла или NULL в случае ошибки
     */
    public static function saveFileInfo(array $fileData): ?int {
        $fileData = SafeMySQL::gi()->filterArray($fileData, SysClass::ee_getFieldsTable(Constants::FILES_TABLE));
        $fileData = array_map(function($value) {
            return is_string($value) ? trim($value) : $value;
        }, $fileData);        
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::FILES_TABLE, $fileData);
        if ($result) {
            return SafeMySQL::gi()->insertId();
        }
        return NULL;
    }
    
}
