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
  }

  $(initImagePicker);
  $(initPagePicker);
  $(document).on('elementor/popup/show', initImagePicker);
  $(document).on('elementor/popup/show', initPagePicker);
})(jQuery);
