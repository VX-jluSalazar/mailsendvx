(function () {
  const $ = window.jQuery || window.$;

  if (!$ || typeof $.fn.colorpicker !== 'function') {
    return;
  }

  const initColorPickers = function initColorPickers(scope) {
    $(scope)
      .find('.mailsendvx-colorpicker')
      .each(function initPicker() {
        const $picker = $(this);

        if ($picker.data('colorpicker')) {
          return;
        }

        $picker.colorpicker({
          format: 'hex',
          useAlpha: false,
        });
      });
  };

  $(function onReady() {
    initColorPickers(document);
  });
})();
