(function ($) {
    /**
     * Класс Tour управляет созданием и отображением шагов тура по пользовательскому интерфейсу.
     */
    class Tour {
        /**
         * Создает экземпляр тура с предоставленными опциями.
         * @param {Object} options Настройки тура.
         */
        constructor(options) {
            this.settings = $.extend(true, {
                steps: [],
                name: 'ee_wizard',
                onStart: $.noop,
                onEnd: $.noop,
                onNext: $.noop,
                onPrev: $.noop,
                onClose: $.noop,
                container: 'body',
                stepClass: 'custom-tour-step',
                storageKey: 'tourCurrentStep',
                highlightClass: 'custom-tour-highlight',
                template: {
                    step: '<div class="custom-tour-step" style="display: none;"><div class="tour-arrow"></div></div>',
                    header: '<div class="tour-header"></div>',
                    content: '<div class="tour-content"></div>',
                    navigation: '<div class="tour-navigation"></div>',
                    closeBtn: '<button class="btn-sm tour-close">Закрыть</button>',
                    prevBtn: '<button class="btn-sm tour-prev">Назад</button>',
                    nextBtn: '<button class="btn-sm tour-next">Вперед</button>',
                    endBtn: '<button class="btn-sm tour-end">Закончить</button>'
                }
            }, options);

            this.settings.storageKey += '_' + this.settings.name;
            const savedStepIndex = parseInt(localStorage.getItem(this.settings.storageKey), 10);
            this.currentStepIndex = (!isNaN(savedStepIndex) && savedStepIndex >= 0 && savedStepIndex < this.settings.steps.length) ? savedStepIndex : 0;
        }

        /**
         * Запускает тур.
         */
        start() {
            if (typeof this.settings.onStart === 'function')
                this.settings.onStart();
            this.showStep(this.currentStepIndex);
        }

        /**
         * Отображает указанный шаг тура.
         * @param {number} index Индекс шага тура для отображения.
         */
        showStep(index) {
            $('.custom-tour-step').remove();
            if (index < 0 || index >= this.settings.steps.length) {
                console.error("Tour step index out of bounds");
                return;
            }

            $('.highlight').removeClass('highlight');
            const step = this.settings.steps[index];
            this.currentStepIndex = index;

            const $stepElement = $(this.settings.template.step)
                    .addClass(this.settings.stepClass)
                    .appendTo(this.settings.container);
            $stepElement.append($(this.settings.template.header).html(step.title));
            $stepElement.append($(this.settings.template.content).html(step.content));

            const $navigation = $(this.settings.template.navigation);
            if (index > 0)
                $navigation.append($(this.settings.template.prevBtn).on('click', () => this.prevStep()));
            if (index < this.settings.steps.length - 1)
                $navigation.append($(this.settings.template.nextBtn).on('click', () => this.nextStep()));
            $navigation.append($(this.settings.template.endBtn).on('click', () => this.endTour()));
            $navigation.append($(this.settings.template.closeBtn).on('click', () => this.closeTour()));
            $stepElement.append($navigation).show();

            this.positionStep($stepElement, step.element, step);
            $(step.element).addClass('highlight');
            if (typeof step.onShow === 'function')
                step.onShow($stepElement, step);
            localStorage.setItem(this.settings.storageKey, index);
        }

        /**
         * Переходит к следующему шагу тура.
         */
        nextStep() {
            if (this.currentStepIndex < this.settings.steps.length - 1) {
                this.showStep(++this.currentStepIndex);
                if (typeof this.settings.onNext === 'function')
                    this.settings.onNext(this.currentStepIndex);
            }
        }

        /**
         * Возвращает к предыдущему шагу тура.
         */
        prevStep() {
            if (this.currentStepIndex > 0) {
                this.showStep(--this.currentStepIndex);
                if (typeof this.settings.onPrev === 'function')
                    this.settings.onPrev(this.currentStepIndex);
            }
        }

        /**
         * Завершает тур.
         */
        endTour() {
            localStorage.setItem(this.settings.storageKey, this.currentStepIndex + 1);
            localStorage.setItem('ee_end_tour_' + this.settings.name, 1);
            $('.highlight').removeClass('highlight');
            $('.custom-tour-step').remove();
            if (typeof this.settings.onEnd === 'function')
                this.settings.onEnd();
        }

        /**
         * Закрывает тур и удаляет его элементы.
         */
        closeTour() {
            $('.custom-tour-step').remove();
            $('.highlight').removeClass('highlight');
            if (typeof this.settings.onClose === 'function')
                this.settings.onClose();
        }

        /**
         * Позиционирует шаг тура относительно целевого элемента.
         * @param {jQuery} $step Элемент шага тура.
         * @param {string} targetSelector Селектор целевого элемента.
         * @param {object} текущая подсказка
         */
        positionStep($step, targetSelector, step) {
            const $target = $(targetSelector);
            const targetOffset = $target.offset();
            const targetHeight = $target.outerHeight();
            const targetWidth = $target.outerWidth();
            const stepHeight = $step.outerHeight();
            const stepWidth = $step.outerWidth();
            const windowWidth = $(window).width();
            const windowHeight = $(window).height();

            // Определение доступного пространства вокруг целевого элемента
            const availableSpace = {
                top: targetOffset.top - $(window).scrollTop(),
                right: windowWidth - targetOffset.left - targetWidth,
                bottom: windowHeight - (targetOffset.top + targetHeight - $(window).scrollTop()),
                left: targetOffset.left
            };

            let placement = step.placement;

            if (!placement) {
                placement = Object.keys(availableSpace).reduce((best, key) =>
                    availableSpace[key] > availableSpace[best] ? key : best, 'bottom');
            }
            
            const $arrow = $step.find('.tour-arrow');
            $arrow.removeClass('tour-arrow-up tour-arrow-down tour-arrow-left tour-arrow-right');

            switch (placement) {
                case 'top':
                    $arrow.addClass('tour-arrow-down');
                    break;
                case 'right':
                    $arrow.addClass('tour-arrow-left');
                    break;
                case 'bottom':
                    $arrow.addClass('tour-arrow-up');
                    break;
                case 'left':
                    $arrow.addClass('tour-arrow-right');
                    break;
            }
             
            // Позиционирование шага тура
            switch (placement) {
                case 'top':
                    $step.css({
                        top: targetOffset.top - stepHeight - 10,
                        left: targetOffset.left + targetWidth / 2 - stepWidth / 2
                    });
                    break;
                case 'right':
                    $step.css({
                        top: targetOffset.top + targetHeight / 2 - stepHeight / 2,
                        left: targetOffset.left + targetWidth + 10
                    });
                    break;
                case 'bottom':
                    $step.css({
                        top: targetOffset.top + targetHeight + 10,
                        left: targetOffset.left + targetWidth / 2 - stepWidth / 2
                    });
                    break;
                case 'left':
                    $step.css({
                        top: targetOffset.top + targetHeight / 2 - stepHeight / 2,
                        left: targetOffset.left - stepWidth - 10
                    });
                    break;
            }

            // Дополнительно: Автоматическая прокрутка до элемента, если он не полностью видим
            $('html, body').animate({
                scrollTop: $step.offset().top - $(window).height() / 2 + stepHeight / 2
            }, 300);
        }

    }

    /**
     * Расширение jQuery для инициализации и управления туром.
     * @param {Object} options Настройки для создания тура.
     */
    $.fn.ee_wizard = function (options) {
        const tour = new Tour(options);
        if (localStorage.getItem('ee_end_tour_' + tour.settings.name) === null) {
            tour.start();
        }
        return this;
    };

    /**
     * Очищает данные тура из localStorage.
     * @param {string} tourName Название тура для очистки.
     */
    $.cleanTour = function (tourName) {
        localStorage.removeItem('tourCurrentStep_' + tourName);
        localStorage.removeItem('ee_end_tour_' + tourName);
    };
})(jQuery);
