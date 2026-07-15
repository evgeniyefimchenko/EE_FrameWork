# Настройка веб-сервера

Этот раздел описывает минимальную настройку Apache2 или Nginx для установки EE_FrameWork. Примеры используют нейтральные значения `example.com` и `/var/www/example.com/public_html`; замените их на путь и домен своего проекта.

Цель конфигурации одна: все динамические запросы должны проходить через front controller `index.php`, а внутренние директории не должны открываться напрямую через HTTP.

## Общие требования

На сервере должны быть установлены:

- PHP `8.0+`;
- PHP-FPM или Apache PHP handler;
- расширения PHP: `mysqli`, `mbstring`, `json`, `session`, `fileinfo`, `openssl`, `curl`;
- MySQL или MariaDB;
- Git для развёртывания из репозитория;
- TLS-сертификат для production-домена.

Web-процесс должен иметь доступ на запись в runtime-директории:

```text
cache/
logs/
uploads/
```

Установщик создаёт вложенные runtime-папки сам, но родительский каталог проекта должен позволять это сделать. Практичный вариант - общий пользователь или общая группа для CLI-пользователя и web-процесса.

ИИ-профили используют `curl` для проверки провайдеров и `openssl` для шифрования ключей. Кеш списков моделей пишется в `cache/ai/`, поэтому права на `cache/` должны позволять web-процессу создавать вложенные каталоги.

```bash
cd /var/www/example.com/public_html
mkdir -p cache logs uploads
chmod -R ug+rwX cache logs uploads
```

Если CLI-пользователь и web-процесс разные, настройте владельца, группу или ACL так, чтобы оба могли писать в `cache/`, `logs/` и `uploads/`.

## База данных

Создайте базу данных и пользователя до запуска установщика или передайте установщику административные данные БД.

Пример ручной подготовки:

```sql
CREATE DATABASE project_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'project_user'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON project_db.* TO 'project_user'@'localhost';
FLUSH PRIVILEGES;
```

Затем запустите установщик через web-мастер `/install/` или CLI:

```bash
php inc/cli.php install:run \
  --site-host=example.com \
  --site-author="Site owner" \
  --site-email=mail@example.com \
  --admin-email=mail@example.com \
  --db-name=project_db \
  --db-user=project_user \
  --db-pass=strong-password
```

Рабочий `inc/configuration.php` создаётся установщиком и не должен попадать в публичный репозиторий.

## Nginx

Для Nginx используйте PHP-FPM и передавайте несуществующие URL в `index.php` через query-параметр `route`. Это важно: роутер EE_FrameWork читает маршрут из `$_GET['route']`.

Минимальный server block:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name example.com www.example.com;

    return 301 https://example.com$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name example.com;

    root /var/www/example.com/public_html;
    index index.php;
    charset utf-8;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    client_max_body_size 100m;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ @ee_framework;
    }

    location @ee_framework {
        rewrite ^/(.*)$ /index.php?route=$1&$query_string last;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_read_timeout 300;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        fastcgi_param HTTP_X_FORWARDED_PROTO https;
    }

    location ~ \.php$ {
        return 403;
    }

    location ^~ /inc/ { return 403; }
    location ^~ /classes/system/js/ { try_files $uri =404; }
    location ^~ /classes/system/css/ { try_files $uri =404; }
    location ^~ /classes/ { return 403; }
    location ^~ /layouts/ { return 403; }
    location ^~ /custom/ { return 403; }
    location ^~ /app/cron/ { return 403; }
    location ^~ /logs/ { return 403; }
    location ^~ /cache/ { return 403; }
    location ^~ /backups/ { return 403; }
    location ^~ /uploads/tmp/ { return 403; }

    location ~ /\.(?!well-known) {
        return 404;
    }

    location ~* \.(sql|bak|old|backup|log|ini|conf|env)$ {
        return 404;
    }
}
```

Если PHP-FPM слушает другой сокет или TCP-порт, замените `fastcgi_pass`. Например:

```nginx
fastcgi_pass 127.0.0.1:9000;
```

После изменения конфигурации:

```bash
nginx -t
systemctl reload nginx
```

## Apache2

В репозитории уже есть `.htaccess` с rewrite-правилами для EE_FrameWork. Для Apache2 нужно разрешить `AllowOverride` в каталоге проекта и включить необходимые модули.

Модули:

```bash
a2enmod rewrite headers expires deflate ssl
systemctl reload apache2
```

Минимальный virtual host:

```apache
<VirtualHost *:80>
    ServerName example.com
    ServerAlias www.example.com
    Redirect permanent / https://example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName example.com
    DocumentRoot /var/www/example.com/public_html

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/example.com/privkey.pem

    <Directory /var/www/example.com/public_html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog ${APACHE_LOG_DIR}/example.com-error.log
    CustomLog ${APACHE_LOG_DIR}/example.com-access.log combined
</VirtualHost>
```

Если `.htaccess` отключён политикой хостинга, перенесите его rewrite- и deny-правила в virtual host. Критичное правило для роутинга:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?route=$1 [B,L,QSA]
```

## Cron

После установки добавьте scheduler в crontab пользователя, от которого должны выполняться фоновые задачи:

```cron
* * * * * cd /var/www/example.com/public_html && php app/cron/run.php >/dev/null 2>&1
```

Если на сервере несколько версий PHP, укажите полный путь к нужному бинарнику:

```cron
* * * * * cd /var/www/example.com/public_html && /usr/bin/php8.2 app/cron/run.php >/dev/null 2>&1
```

## Smoke-проверка

После установки и reload веб-сервера выполните:

```bash
php inc/cli.php install:status
php inc/cli.php ops:health-check
curl -I https://example.com/
curl -I https://example.com/docs
curl -I https://example.com/inc/configuration.php
curl -I https://example.com/app/cron/run.php
```

Ожидаемые результаты:

- `/` и публичные маршруты вроде `/docs` отвечают через приложение;
- внутренние пути возвращают `403` или `404`;
- `inc/configuration.php` не отдаётся наружу;
- health-check не показывает critical errors.

## Частые ошибки

- Nginx передаёт fallback как `/index.php?$query_string`, а не `route=$1`: все красивые URL могут вести на главную.
- Web-процесс не может писать в `cache/`, `logs/` или `uploads/`: установка падает или приложение не пишет логи.
- Apache не включает `mod_rewrite` или `AllowOverride All`: `.htaccess` не применяется.
- Прямое исполнение PHP во внутренних директориях разрешено: это нарушение production security model.
- После изменения конфига забыли выполнить `nginx -t && systemctl reload nginx` или `apachectl configtest && systemctl reload apache2`.
