(function ($) {
  function initImagePicker() {
    var $selectButton = $('#ttpr-page-image-select');
    var $clearButton = $('#ttpr-page-image-clear');
    var $idField = $('#ttpr-page-image-id');
    var $preview = $('#ttpr-page-image-preview');

    if (!$selectButton.length || !$clearButton.length || !$idField.length || !$preview.length || !window.wp || !wp.media) {
      return;
    }

    var frame;

    $selectButton.off('click.ttprImage').on('click.ttprImage', function (event) {
      event.preventDefault();

      if (!frame) {
        frame = wp.media({
          title: 'Select Card Image',
          button: { text: 'Use this image' },
          multiple: false
        });

        frame.on('select', function () {
          var attachment = frame.state().get('selection').first().toJSON();
          $idField.val(attachment.id || '');
          $preview.attr('src', attachment.url || '');
          $preview.css('display', attachment.url ? 'block' : 'none');
        });
      }

      frame.open();
    });

    $clearButton.off('click.ttprImage').on('click.ttprImage', function (event) {
      event.preventDefault();
      $idField.val('');
      $preview.attr('src', '');
      $preview.css('display', 'none');
    });
  }

  function initPagePicker() {
    var $picker = $('#ttpr-related-page-picker');
    var $hidden = $('#ttpr-related-page-ids');
    var $selected = $('#ttpr-selected-pages');

    if (!$picker.length || !$hidden.length || !$selected.length) {
      return;
    }

    function currentIds() {
      var raw = ($hidden.val() || '').split(',');
      return raw.map(function (id) {
        return $.trim(id);
      }).filter(Boolean);
    }

    function syncHidden() {
      var ids = [];
      $selected.find('.ttpr-page-chip').each(function () {
        ids.push(String($(this).data('page-id')));
      });
      $hidden.val(ids.join(','));
    }

    function initSortable() {
      if (!$.fn.sortable) {
        return;
      }

      if ($selected.data('ui-sortable')) {
        $selected.sortable('destroy');
      }

      $selected.sortable({
        items: '.ttpr-page-chip',
        handle: '.ttpr-page-chip__label',
        cancel: 'button, input, select, textarea, a',
        placeholder: 'ttpr-page-chip-placeholder',
        tolerance: 'pointer',
        forcePlaceholderSize: true,
        start: function (event, ui) {
          ui.item.addClass('is-dragging');
        },
        stop: function (event, ui) {
          ui.item.removeClass('is-dragging');
          syncHidden();
        },
        update: syncHidden
      });
    }

    function addChip(id, label) {
      if (!id || !label) {
        return;
      }

      if (currentIds().indexOf(String(id)) !== -1) {
        $picker.val('');
        return;
      }

      var $chip = $('<span />', {
        'class': 'ttpr-page-chip',
        'data-page-id': id
      });

      $('<span />', {
        'class': 'ttpr-page-chip__label',
        text: label
      }).appendTo($chip);

      $('<button />', {
        type: 'button',
        'class': 'ttpr-page-chip__remove',
        text: '×',
        'aria-label': 'Remove page'
      }).appendTo($chip);

      $selected.append($chip);
      syncHidden();
      initSortable();
      $picker.val('');
    }

    $picker.off('change.ttpr').on('change.ttpr', function () {
      var id = $(this).val();
      var label = $(this).find('option:selected').text();
      addChip(id, label);
    });

    $selected.off('click.ttpr').on('click.ttpr', '.ttpr-page-chip__remove', function (event) {
      event.preventDefault();
      $(this).closest('.ttpr-page-chip').remove();
      syncHidden();
    });

    $(document).off('submit.ttprPagePicker', 'form#post').on('submit.ttprPagePicker', 'form#post', function () {
      syncHidden();
      if ($selected.data('ui-sortable')) {
        $selected.sortable('disable');
      }
    });

    initSortable();
  }

  $(initImagePicker);
  $(initPagePicker);
  $(document).off('elementor/popup/show.ttprAdmin').on('elementor/popup/show.ttprAdmin', function () {
    initImagePicker();
    initPagePicker();
  });
})(jQuery);
