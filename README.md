# EE_FRAMEWORK - Легкий PHP фреймворк на основе MVC с встроенной административной панелью

EE_FRAMEWORK — это гибкий и производительный PHP-фреймворк, предназначенный для разработки веб-проектов различного назначения: от развлекательных сайтов до экспертных систем. Фреймворк отлично подходит для нестандартных проектов, где требуются гибкие решения и кастомизация.

---

## 📖 О проекте

EE_FRAMEWORK построен на основе MVC-подобной архитектуры и включает встроенную административную панель, систему авторизации, поддержку мультиязычности и мощные инструменты для оптимизации производительности. Он разработан с учетом удобства для разработчиков, предоставляя минимальный объем кода, высокую производительность и простоту сопровождения.

---

## 🚀 Основные преимущества

### Реализованные возможности:
1. **Ориентирован на разработчика**  
   Фреймворк спроектирован для удобства и простоты интеграции различных решений, сокращая время разработки.
   
2. **Уникальная система свойств сущностей**  
   Позволяет гибко описывать любую бизнес-модель информационного ресурса, что облегчает реализацию сложных структур данных.

3. **Встроенная система поиска**  
   Поддержка частичного, полного и приблизительного совпадения текста, что значительно улучшает пользовательский опыт при работе с контентом.

4. **Кэширование и сжатие контента**  
   Использование OPcache, Redis и файловой системы для повышения производительности и уменьшения времени загрузки страниц.

5. **Мультиязычность**  
   Поддержка нескольких языков из коробки (например, `/inc/langs/RU.php`), что делает проект доступным для международной аудитории.

6. **Система хуков**  
   Расширяемость функционала без необходимости изменения базового кода фреймворка, что упрощает поддержку и развитие проектов.

7. **Минимальный объем кода**  
   Высокая производительность и легкость сопровождения благодаря оптимизированному коду и отсутствию избыточной сложности.

---

## 🛠️ Технологический стек

| Технология                  | Описание                                            |
|-----------------------------|-----------------------------------------------------|
| PHP 8+                      | Основной язык разработки                            |
| MySQL (SafeMySQL)           | Работа с базой данных                               |
| JavaScript (JQuery)         | Клиентская логика                                   |
| HTML5 & CSS3 (Bootstrap)    | Адаптивный дизайн                                   |
| Summernote                  | WYSIWYG текстовый редактор                          |
| OPcache                     | Оптимизация производительности PHP                  |
| Redis                       | Кэширование данных и роутинга                       |

---

## 📂 Структура проекта

ee_framework/  
├── app/  
│   └── admin/ (панель администратора)  
│       ├── CategoriesTrait.php  
│       ├── EmailsTrait.php  
│       ├── PagesTrait.php  
│       └── другие Traits...  
├── assets/  
│   ├── css/ (CSS стили)  
│   └── js/ (JavaScript файлы)  
├── cache/ (кешированные данные)  
├── classes/  
│   ├── helpers/ (вспомогательные классы)  
│   ├── plugins/ (сторонние библиотеки)  
│   └── system/ (системные классы)  
├── inc/  
│   ├── configuration.php (конфигурация окружения)  
│   ├── hooks.php (система хуков)  
│   └── startup.php (инициализация приложения)  
├── logs/ (логи ошибок и событий)  
├── uploads/ (загруженные файлы пользователей)  
├── error.php (страница обработки ошибок)  
└── index.php (точка входа приложения)  

---

## ⚙️ Установка и запуск проекта

1. Клонируйте репозиторий:
git clone https://github.com/your_username/EE_framework.git

2. Настройте файл конфигурации (`inc/configuration.php`) под своё окружение.

3. Убедитесь, что папки `uploads`, `cache` и `logs` имеют права на запись.

4. При первой авторизации в административную панель база данных будет развёрнута автоматически из класса Users


---

## 🔧 Расширение функционала

### Примеры задач:

Нет задач нет примеров!

---

## 🤝 Участие в проекте

Будем рады любым предложениям по улучшению EE_FRAMEWORK! Чтобы внести свой вклад:

1. Сделайте форк репозитория.
2. Создайте новую ветку (`git checkout -b feature/new-feature`).
3. Закоммитьте изменения (`git commit -m 'Добавил новую функцию'`).
4. Запушьте изменения (`git push origin feature/new-feature`).
5. Откройте Pull Request.

---

## 📄 Лицензия

Проект распространяется под лицензией MIT.

---

## 🙌 Благодарности

Спасибо всем участникам разработки и авторам используемых библиотек:

- [SafeMySQL](https://github.com/colshrapnel/safemysql)
- [Summernote](https://summernote.org/)
- [Bootstrap](https://getbootstrap.com/)
- [JQuery](https://jquery.com/)
- [Redis](https://redis.io/)

