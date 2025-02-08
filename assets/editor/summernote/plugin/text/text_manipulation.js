(function (factory) {
  if (typeof define === 'function' && define.amd) {
    define(['jquery'], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory(require('jquery'));
  } else {
    factory(window.jQuery);
  }
}(function ($) {

  // Основные утилиты для манипуляции текстом
  const TextManipulatorUtils = {
    isNumeric: function(v) {
      return /^-{0,1}\d+(\.\d+)?$/.test(v);
    },
    addSpace: function(m, l, i) {
      if (i === 0 && !/[A-Z]/.test(l)) return l.toUpperCase();
      return ' ' + l.toLowerCase();
    },
    capitalize: function(str, lR = false) {
      var r = str.slice(1);
      r = lR ? r.toLowerCase() : r;
      return str.charAt(0).toUpperCase() + r;
    },
    formatNumber: function(n) {
      return parseFloat(n).toLocaleString('ru-RU'); // Разделение тысяч точками
    },
    toWords: function(i) {
      var r = /[A-Z\xC0-\xD6\xD8-\xDE]?[a-z\xDF-\xF6\xF8-\xFF]+|[A-Z\xC0-\xD6\xD8-\xDE]+(?![a-z\xDF-\xF6\xF8-\xFF])|\d+/g;
      return i.match(r);
    },
    toCamelCase: function(iA) {
      var r = "";
      for (let i = 0, l = iA.length; i < l; i++) {
        var cS = iA[i];
        var tS = cS.toLowerCase();
        if (i !== 0) tS = tS.charAt(0).toUpperCase() + tS.slice(1);
        r += tS;
      }
      return r;
    },
    toCamelCaseString: function(i) {
      var w = this.toWords(i);
      return this.toCamelCase(w);
    },
    formatDate: function() {
      const now = new Date();
      return now.toLocaleString('ru-RU', { dateStyle: 'long', timeStyle: 'short' }); // Текущая дата и время
    },
    countWords: function(str) {
      return str.split(/\s+/).filter(word => word.length > 0).length;
    },
    countCharacters: function(str) {
      return str.length;
    }
  };

  // Показ уведомлений в редакторе
  function showAlert(message, options, $note) {
    const alertHtml = `
      <div class="summernote-textManipulatorAlert alert alert-danger alert-dismissible fade show" style="${options.textManipulator.noteSatus}" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.8%;"></button>
      </div>`;
    if ($('.note-status-output').length > 0) {
      $('.note-status-output').html(alertHtml);
    } else {
      $note.find('.note-resizebar').append(alertHtml);
    }
  }

  // Основной обработчик текстовых манипуляций
  function handleTextManipulation(menuSelect, selection, lang, options, $note) {
    let modded;
    switch (menuSelect) {
      case 'camelCase':
        modded = TextManipulatorUtils.toCamelCaseString(selection);
        break;
      case 'Currency':
        if (TextManipulatorUtils.isNumeric(selection)) {
          modded = lang.textManipulator.currency + TextManipulatorUtils.formatNumber(selection);
        } else {
          modded = 'textManipulatorError';
          showAlert(lang.textManipulator.noNumber, options, $note);
        }
        break;
      case 'Humanize':
        const mod = selection.replace(/[_-]+([a-zA-Z])/g, TextManipulatorUtils.addSpace)
                             .replace(/([A-Z])/g, TextManipulatorUtils.addSpace);
        modded = TextManipulatorUtils.capitalize(mod);
        break;
      case 'lowercase':
        modded = selection.toLowerCase();
        break;
      case 'Reverse':
        modded = selection.split("").reverse().join("");
        break;
      case 'Titleize':
        modded = selection.replace(/[^\s+|^-]+/g, (word) => TextManipulatorUtils.capitalize(word, true));
        break;
      case 'UPPERCASE':
        modded = selection.toUpperCase();
        break;
      case 'wordCount':
        alert(`${lang.textManipulator.wordCount}: ${TextManipulatorUtils.countWords(selection)}`);
        return null;
      case 'characterCount':
        alert(`${lang.textManipulator.characterCount}: ${TextManipulatorUtils.countCharacters(selection)}`);
        return null;
      case 'currentDate':
        modded = TextManipulatorUtils.formatDate();
        break;
      default:
        modded = selection;
    }
    return modded;
  }

  // Добавление локализации для нескольких языков
  $.extend(true, $.summernote.lang, {
    'en-US': {
      textManipulator: {
        tooltip: 'Text Manipulator',
        currency: '$',
        noSelection: 'No text selected to manipulate!',
        noNumber: 'Selection is not a valid number!',
        wordCount: 'Word count',
        characterCount: 'Character count',
        currentDate: 'Insert current date',
        menu: {
          camelCase: 'Camel Case',
          Currency: 'Currency',
          Humanize: 'Humanize',
          lowercase: 'Lowercase',
          Reverse: 'Reverse',
          Titleize: 'Titleize',
          UPPERCASE: 'UPPERCASE',
          wordCount: 'Word Count',
          characterCount: 'Character Count',
          currentDate: 'Current Date'
        }
      }
    },
    'ru-RU': {
      textManipulator: {
        tooltip: 'Манипулятор текста',
        currency: '₽',
        noSelection: 'Текст не выделен!',
        noNumber: 'Выделенный текст не является числом!',
        wordCount: 'Количество слов',
        characterCount: 'Количество символов',
        currentDate: 'Вставить текущую дату',
        menu: {
          camelCase: 'ВерблюжийРегистр',
          Currency: 'Валюта',
          Humanize: 'Очеловечить',
          lowercase: 'Нижний регистр',
          Reverse: 'Обратный порядок',
          Titleize: 'Заголовок',
          UPPERCASE: 'ВЕРХНИЙ РЕГИСТР',
          wordCount: 'Количество слов',
          characterCount: 'Количество символов',
          currentDate: 'Текущая дата'
        }
      }
    }
  });

  // Опции плагина
  $.extend($.summernote.options, {
    textManipulator: {
      icon: '<img src="/assets/editor/summernote/plugin/text/i-text.svg" style="width: 20px; height: 20px;" alt="Text Manipulator Icon">',
      noteSatus: 'position:absolute;top:0;left:0;right:0',
      menu: [
        'camelCase',
        'Currency',
        'Humanize',
        'lowercase',
        'Reverse',
        'Titleize',
        'UPPERCASE',
        'wordCount',
        'characterCount',
        'currentDate'
      ]
    }
  });

  // Расширение плагина
  $.extend($.summernote.plugins, {
    'textManipulator': function (context) {
      var ui = $.summernote.ui,
          $note = context.layoutInfo.note,
          options = context.options,
          lang = options.langInfo;

      context.memo('button.textManipulator', function () {
        var button = ui.buttonGroup([
          ui.button({
            className: 'dropdown-toggle',
            contents: options.textManipulator.icon,
            tooltip: lang.textManipulator.tooltip,
            container: 'body',
            data: {
              toggle: 'dropdown'
            }
          }),
          ui.dropdown({
            className: 'dropdown-template',
            items: options.textManipulator.menu.map(item => lang.textManipulator.menu[item]), // Используем переведенные названия
            click: function (e) {
              var $button = $(e.target);
              var menuSelect = options.textManipulator.menu.find((item, index) => {
                return lang.textManipulator.menu[item] === $button.text();
              });
              e.preventDefault();
              var selection = $note.summernote('createRange').toString();
              if ($('.summernote-textManipulatorAlert').length > 0) {
                $('.summernote-textManipulatorAlert').remove();
              }
              if (selection === '') {
                showAlert(lang.textManipulator.noSelection, options, $note);
              } else {
                const modded = handleTextManipulation(menuSelect, selection, lang, options, $note);
                if (modded !== 'textManipulatorError' && modded !== null) {
                  $note.summernote('insertText', modded);
                }
              }
            }
          })
        ]);
        return button.render();
      });
    }
  });
}));