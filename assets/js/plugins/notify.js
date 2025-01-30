/**
 * Плагин для создания уведомлений на основе Bootstrap 5 и FontAwesome 6.
 * Позволяет отображать уведомления с различными параметрами, такими как заголовок, сообщение, тип уведомления, иконка и др.
 *
 * @param {jQuery} $ jQuery объект
 * @param {object} content Контент уведомления. Может быть строкой или объектом.
 * @param {string} content.message Сообщение уведомления
 * @param {string} [content.title] Заголовок уведомления (опционально)
 * @param {string} [content.icon] Иконка FontAwesome для уведомления (опционально)
 * @param {string} [content.url] Ссылка для уведомления (опционально)
 * @param {string} [content.target] Атрибут target для ссылки (опционально)
 * @param {object} [options] Дополнительные опции для уведомления
 * @param {string} [options.type='info'] Тип уведомления (например, 'info', 'success', 'warning', 'danger')
 * @param {boolean} [options.allow_dismiss=true] Разрешить ли пользователю закрывать уведомление
 * @param {boolean} [options.allow_duplicates=true] Разрешить ли дубликаты уведомлений
 * @param {boolean} [options.newest_on_top=false] Показывать ли новые уведомления выше старых
 * @param {boolean} [options.showProgressbar=false] Показывать ли прогресс-бар
 * @param {object} [options.placement] Параметры расположения уведомления
 * @param {string} [options.placement.from='top'] Расположение уведомления по вертикали ('top' или 'bottom')
 * @param {string} [options.placement.align='right'] Расположение уведомления по горизонтали ('left', 'center' или 'right')
 * @param {number} [options.offset=20] Отступ от края экрана
 * @param {number} [options.spacing=10] Отступ между уведомлениями
 * @param {number} [options.z_index=1031] z-index для уведомления
 * @param {number} [options.delay=5000] Задержка перед закрытием уведомления (в миллисекундах)
 * @param {number} [options.timer=1000] Интервал обновления прогресс-бара (в миллисекундах)
 * @param {string} [options.url_target='_blank'] Атрибут target для ссылки (если указан content.url)
 * @param {string} [options.icon_type='class'] Тип иконки (например, 'class' для FontAwesome)
 * @param {object} [options.animate] Параметры анимации для появления и исчезновения уведомления
 * @param {string} [options.animate.enter='animate__animated animate__fadeInDown'] Анимация появления
 * @param {string} [options.animate.exit='animate__animated animate__fadeOutUp'] Анимация исчезновения
 * @param {function} [options.onShow] Callback, вызываемый при показе уведомления
 * @param {function} [options.onShown] Callback, вызываемый после показа уведомления
 * @param {function} [options.onClose] Callback, вызываемый при закрытии уведомления
 * @param {function} [options.onClosed] Callback, вызываемый после закрытия уведомления
 * @param {function} [options.onClick] Callback, вызываемый при клике на уведомление
 * @returns {object} notify Экземпляр уведомления с методами для обновления и закрытия
 */
(function(factory) {
    if (typeof define === 'function' && define.amd) {
        define(['jquery'], factory);
    } else if (typeof exports === 'object') {
        factory(require('jquery'));
    } else {
        factory(jQuery);
    }
}(function($) {
    var defaults = {
        element: 'body',
        position: null,
        type: "info",
        allow_dismiss: true,
        allow_duplicates: false,
        newest_on_top: false,
        showProgressbar: false,
        placement: {
            from: "top",
            align: "right"
        },
        offset: 20,
        spacing: 10,
        z_index: 100000,
        delay: 5000,
        timer: 1000,
        url: 'javascript:void(0);', // URL по умолчанию
        url_target: '_blank',        
        mouse_over: null,
        animate: {
            enter: 'animate__animated animate__fadeInDown',
            exit: 'animate__animated animate__fadeOutUp'
        },
        onShow: null,
        onShown: null,
        onClose: null,
        onClosed: null,
        onClick: null,
        icon_type: 'class',
        template: '<div data-notify="container" class="col-11 col-sm-4 alert alert-{0} d-flex align-items-center" role="alert">' +
                  '<button type="button" class="btn-close" data-notify="dismiss"></button>' +
                  '<span data-notify="icon" class="me-2"></span>' +
                  '<span class="flex-grow-1">' +
                    '<span data-notify="title" class="fw-bold">{1}</span>' +
                    '<span data-notify="message">{2}</span>' +
                  '</span>' +
                  '<div class="progress" data-notify="progressbar">' +
                    '<div class="progress-bar progress-bar-{0}" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>' +
                  '</div>' +
                  '<a href="{3}" target="{4}" data-notify="url" class="stretched-link"></a>' +
                  '</div>'
    };

    String.format = function() {
        var args = arguments;
        var str = arguments[0];
        return str.replace(/(\{\{\d\}\}|\{\d\})/g, function(str) {
            if (str.substring(0, 2) === "{{") return str;
            var num = parseInt(str.match(/\d/)[0]);
            return args[num + 1];
        });
    };

    function isDuplicateNotification(notification) {
        var isDupe = false;

        $('[data-notify="container"]').each(function(i, el) {
            var $el = $(el);
            var title = $el.find('[data-notify="title"]').html().trim();
            var message = $el.find('[data-notify="message"]').html().trim();

            var isSameTitle = title === $("<div>" + notification.settings.content.title + "</div>").html().trim();
            var isSameMsg = message === $("<div>" + notification.settings.content.message + "</div>").html().trim();
            var isSameType = $el.hasClass('alert-' + notification.settings.type);

            if (isSameTitle && isSameMsg && isSameType) {
                isDupe = true;
            }
            return !isDupe;
        });

        return isDupe;
    }

    function Notify(element, content, options) {
        var contentObj = {
            content: {
                message: typeof content === 'object' ? content.message : content,
                title: content.title ? content.title : '',
                icon: content.icon ? content.icon : '',
                url: content.url ? content.url : 'javascript:void(0);',
                target: content.target ? content.target : '-'
            }
        };

        options = $.extend(true, {}, contentObj, options);
        this.settings = $.extend(true, {}, defaults, options);
        this._defaults = defaults;
        if (this.settings.content.target === "-") {
            this.settings.content.target = this.settings.url_target;
        }
        this.animations = {
            start: 'webkitAnimationStart oanimationstart MSAnimationStart animationstart',
            end: 'webkitAnimationEnd oanimationend MSAnimationEnd animationend'
        };

        if (typeof this.settings.offset === 'number') {
            this.settings.offset = {
                x: this.settings.offset,
                y: this.settings.offset
            };
        }

        if (this.settings.allow_duplicates || (!this.settings.allow_duplicates && !isDuplicateNotification(this))) {
            this.init();
        }
    }

    $.extend(Notify.prototype, {
        init: function() {
            var self = this;

            this.buildNotify();
            if (this.settings.content.icon) {
                this.setIcon();
            }
            if (this.settings.content.url != "#") {
                this.styleURL();
            }
            this.styleDismiss();
            this.placement();
            this.bind();

            this.notify = {
                $ele: this.$ele,
                update: function(command, update) {
                    var commands = {};
                    if (typeof command === "string") {
                        commands[command] = update;
                    } else {
                        commands = command;
                    }
                    for (var cmd in commands) {
                        switch (cmd) {
                            case "type":
                                this.$ele.removeClass('alert-' + self.settings.type);
                                this.$ele.find('[data-notify="progressbar"] > .progress-bar').removeClass('progress-bar-' + self.settings.type);
                                self.settings.type = commands[cmd];
                                this.$ele.addClass('alert-' + commands[cmd]).find('[data-notify="progressbar"] > .progress-bar').addClass('progress-bar-' + commands[cmd]);
                                break;
                            case "icon":
                                var $icon = this.$ele.find('[data-notify="icon"]');
                                if (self.settings.icon_type.toLowerCase() === 'class') {
                                    $icon.removeClass(self.settings.content.icon).addClass(commands[cmd]);
                                } else {
                                    if (!$icon.is('img')) {
                                        $icon.find('img');
                                    }
                                    $icon.attr('src', commands[cmd]);
                                }
                                self.settings.content.icon = commands[command];
                                break;
                            case "progress":
                                var newDelay = self.settings.delay - (self.settings.delay * (commands[cmd] / 100));
                                this.$ele.data('notify-delay', newDelay);
                                this.$ele.find('[data-notify="progressbar"] > div').attr('aria-valuenow', commands[cmd]).css('width', commands[cmd] + '%');
                                break;
                            case "url":
                                this.$ele.find('[data-notify="url"]').attr('href', commands[cmd]);
                                break;
                            case "target":
                                this.$ele.find('[data-notify="url"]').attr('target', commands[cmd]);
                                break;
                            default:
                                this.$ele.find('[data-notify="' + cmd + '"]').html(commands[cmd]);
                        }
                    }
                    var posX = this.$ele.outerHeight() + parseInt(self.settings.spacing) + parseInt(self.settings.offset.y);
                    self.reposition(posX);
                },
                close: function() {
                    self.close();
                }
            };

        },
        buildNotify: function() {
            var content = this.settings.content;
            this.$ele = $(String.format(
                this.settings.template,
                this.settings.type,
                content.title || '',
                content.message || '',
                content.url || 'javascript:void(0);',
                content.target || '_blank'
            ));

            // Удалить ссылку, если URL не задан
            if (!content.url || content.url === 'javascript:void(0);') {
                this.$ele.find('[data-notify="url"]').remove();
            }

            if (!this.settings.allow_dismiss) {
                this.$ele.find('[data-notify="dismiss"]').css('display', 'none');
            }
            if ((this.settings.delay <= 0 && !this.settings.showProgressbar) || !this.settings.showProgressbar) {
                this.$ele.find('[data-notify="progressbar"]').remove();
            }
        },
        setIcon: function() {
            this.$ele.addClass('alert-with-icon');

            if (this.settings.icon_type.toLowerCase() === 'class') {
                this.$ele.find('[data-notify="icon"]').addClass(this.settings.content.icon);
            } else {
                if (this.$ele.find('[data-notify="icon"]').is('img')) {
                    this.$ele.find('[data-notify="icon"]').attr('src', this.settings.content.icon);
                } else {
                    this.$ele.find('[data-notify="icon"]').append('<img src="' + this.settings.content.icon + '" alt="Notify Icon" />');
                }
            }
        },
        styleDismiss: function() {
            this.$ele.find('[data-notify="dismiss"]').css({
                position: 'absolute',
                right: '10px',
                top: '17px',
                zIndex: this.settings.z_index + 2
            });
        },
        styleURL: function() {
            this.$ele.find('[data-notify="url"]').css({
                backgroundImage: 'none',
                height: '100%',
                left: 0,
                position: 'absolute',
                top: 0,
                width: '100%',
                zIndex: this.settings.z_index + 1
            });
        },
        placement: function() {
            var self = this,
                offsetAmt = this.settings.offset.y,
                css = {
                    display: 'inline-block',
                    margin: '0px auto',
                    position: this.settings.position ? this.settings.position : (this.settings.element === 'body' ? 'fixed' : 'absolute'),
                    transition: 'all .5s ease-in-out',
                    zIndex: this.settings.z_index
                },
                hasAnimation = false,
                settings = this.settings;
            $('[data-notify-position="' + this.settings.placement.from + '-' + this.settings.placement.align + '"]:not([data-closing="true"])').each(function() {
                offsetAmt = Math.max(offsetAmt, parseInt($(this).css(settings.placement.from)) + parseInt($(this).outerHeight()) + parseInt(settings.spacing));
            });
            if (this.settings.newest_on_top === true) {
                offsetAmt = this.settings.offset.y;
            }
            css[this.settings.placement.from] = offsetAmt + 'px';
            switch (this.settings.placement.align) {
                case "left":
                case "right":
                    css[this.settings.placement.align] = this.settings.offset.x + 'px';
                    break;
                case "center":
                    css.left = 0;
                    css.right = 0;
                    break;
            }
            this.$ele.css(css).addClass(this.settings.animate.enter);
            $.each(Array('webkit-', 'moz-', 'o-', 'ms-', ''), function(index, prefix) {
                self.$ele[0].style[prefix + 'AnimationIterationCount'] = 1;
            });
            $(this.settings.element).append(this.$ele);
            if (this.settings.newest_on_top === true) {
                offsetAmt = (parseInt(offsetAmt) + parseInt(this.settings.spacing)) + this.$ele.outerHeight();
                this.reposition(offsetAmt);
            }
            if ($.isFunction(self.settings.onShow)) {
                self.settings.onShow.call(this.$ele);
            }
            this.$ele.one(this.animations.start, function() {
                hasAnimation = true;
            }).one(this.animations.end, function() {
                self.$ele.removeClass(self.settings.animate.enter);
                if ($.isFunction(self.settings.onShown)) {
                    self.settings.onShown.call(this);
                }
            });
            setTimeout(function() {
                if (!hasAnimation) {
                    if ($.isFunction(self.settings.onShown)) {
                        self.settings.onShown.call(this);
                    }
                }
            }, 600);
        },
        bind: function() {
            var self = this;
            this.$ele.find('[data-notify="dismiss"]').on('click', function() {
                self.close();
            });
            this.$ele.find('[data-notify="url"]').on('click', function(e) {
                if ($(this).attr('href') === 'javascript:void(0);') {
                    e.preventDefault(); // Предотвратить открытие пустой вкладки
                }
            });
            if ($.isFunction(this.settings.onClick)) {
                this.$ele.on('click', function(event) {
                    if (event.target != self.$ele.find('[data-notify="dismiss"]')[0]) {
                        self.settings.onClick.call(this, event);
                    }
                });
            }
            this.$ele.mouseover(function() {
                $(this).data('data-hover', "true");
            }).mouseout(function() {
                $(this).data('data-hover', "false");
            });
            this.$ele.data('data-hover', "false");

            if (this.settings.delay > 0) {
                self.$ele.data('notify-delay', self.settings.delay);
                var timer = setInterval(function() {
                    var delay = parseInt(self.$ele.data('notify-delay')) - self.settings.timer;
                    if ((self.$ele.data('data-hover') === 'false' && self.settings.mouse_over === "pause") || self.settings.mouse_over != "pause") {
                        var percent = ((self.settings.delay - delay) / self.settings.delay) * 100;
                        self.$ele.data('notify-delay', delay);
                        self.$ele.find('[data-notify="progressbar"] > div').attr('aria-valuenow', percent).css('width', percent + '%');
                    }
                    if (delay <= -(self.settings.timer)) {
                        clearInterval(timer);
                        self.close();
                    }
                }, self.settings.timer);
            }
        },
        close: function() {
            var self = this,
                posX = parseInt(this.$ele.css(this.settings.placement.from)),
                hasAnimation = false;

            this.$ele.attr('data-closing', 'true').addClass(this.settings.animate.exit);
            self.reposition(posX);

            if ($.isFunction(self.settings.onClose)) {
                self.settings.onClose.call(this.$ele);
            }

            this.$ele.one(this.animations.start, function() {
                hasAnimation = true;
            }).one(this.animations.end, function() {
                $(this).remove();
                if ($.isFunction(self.settings.onClosed)) {
                    self.settings.onClosed.call(this);
                }
            });

            setTimeout(function() {
                if (!hasAnimation) {
                    self.$ele.remove();
                    if (self.settings.onClosed) {
                        self.settings.onClosed(self.$ele);
                    }
                }
            }, 600);
        },
        reposition: function(posX) {
            var self = this,
                notifies = '[data-notify-position="' + this.settings.placement.from + '-' + this.settings.placement.align + '"]:not([data-closing="true"])',
                $elements = this.$ele.nextAll(notifies);
            if (this.settings.newest_on_top === true) {
                $elements = this.$ele.prevAll(notifies);
            }
            $elements.each(function() {
                $(this).css(self.settings.placement.from, posX);
                posX = (parseInt(posX) + parseInt(self.settings.spacing)) + $(this).outerHeight();
            });
        }
    });

    $.notify = function(content, options) {
        var plugin = new Notify(this, content, options);
        return plugin.notify;
    };
    $.notifyDefaults = function(options) {
        defaults = $.extend(true, {}, defaults, options);
        return defaults;
    };

    $.notifyClose = function(selector) {

        if (typeof selector === "undefined" || selector === "all") {
            $('[data-notify]').find('[data-notify="dismiss"]').trigger('click');
        } else if (selector === 'success' || selector === 'info' || selector === 'warning' || selector === 'danger') {
            $('.alert-' + selector + '[data-notify]').find('[data-notify="dismiss"]').trigger('click');
        } else if (selector) {
            $(selector + '[data-notify]').find('[data-notify="dismiss"]').trigger('click');
        } else {
            $('[data-notify-position="' + selector + '"]').find('[data-notify="dismiss"]').trigger('click');
        }
    };

    $.notifyCloseExcept = function(selector) {

        if (selector === 'success' || selector === 'info' || selector === 'warning' || selector === 'danger') {
            $('[data-notify]').not('.alert-' + selector).find('[data-notify="dismiss"]').trigger('click');
        } else {
            $('[data-notify]').not(selector).find('[data-notify="dismiss"]').trigger('click');
        }
    };

}));
