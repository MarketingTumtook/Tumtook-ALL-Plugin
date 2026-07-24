(function ($) {
  function initImagePicker() {
    var $selectButton = $('#ttpc-page-image-select');
    var $clearButton = $('#ttpc-page-image-clear');
    var $idField = $('#ttpc-page-image-id');
    var $preview = $('#ttpc-page-image-preview');

    if (!$selectButton.length || !$clearButton.length || !$idField.length || !$preview.length || !window.wp || !wp.media) {
      return;
    }

    var frame;

    $selectButton.on('click', function (event) {
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

    $clearButton.on('click', function (event) {
      event.preventDefault();
      $idField.val('');
      $preview.attr('src', '');
      $preview.css('display', 'none');
    });
  }

  function initPagePicker() {
    var $picker = $('#ttpc-related-page-picker');
    var $hidden = $('#ttpc-related-page-ids');
    var $selected = $('#ttpc-selected-pages');

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
      $selected.find('.ttpc-page-chip').each(function () {
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
        items: '.ttpc-page-chip',
        placeholder: 'ttpc-page-chip-placeholder',
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
        'class': 'ttpc-page-chip',
        'data-page-id': id
      });

      $('<span />', {
        'class': 'ttpc-page-chip__label',
        text: label
      }).appendTo($chip);

      $('<button />', {
        type: 'button',
        'class': 'ttpc-page-chip__remove',
        text: '×',
        'aria-label': 'Remove page'
      }).appendTo($chip);

      $selected.append($chip);
      syncHidden();
      initSortable();
      $picker.val('');
    }

    $picker.off('change.ttpc').on('change.ttpc', function () {
      var id = $(this).val();
      var label = $(this).find('option:selected').text();
      addChip(id, label);
    });

    $selected.off('click.ttpc').on('click.ttpc', '.ttpc-page-chip__remove', function (event) {
      event.preventDefault();
      $(this).closest('.ttpc-page-chip').remove();
      syncHidden();
    });

    initSortable();
  }

  $(initImagePicker);
  $(initPagePicker);
  $(document).on('elementor/popup/show', initImagePicker);
  $(document).on('elementor/popup/show', initPagePicker);
})(jQuery);
