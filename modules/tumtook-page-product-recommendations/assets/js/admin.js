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

  $(initImagePicker);
  $(document).off('elementor/popup/show.ttprAdmin').on('elementor/popup/show.ttprAdmin', function () {
    initImagePicker();
  });
})(jQuery);
