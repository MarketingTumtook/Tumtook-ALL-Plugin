(function ($) {
  function updatePreview(wrapper, url) {
    const preview = wrapper.find(".ttbs-image-preview");
    preview.attr("src", url || "").toggle(!!url);
  }

  function bindUploader(context) {
    context.find(".ttbs-upload").off("click").on("click", function (event) {
      event.preventDefault();

      const wrapper = $(this).closest(".ttbs-admin-field");
      const frame = wp.media({
        title: "Select image",
        library: { type: "image" },
        button: {
          text: "Use this image",
        },
        multiple: false,
      });

      frame.on("select", function () {
        const attachment = frame.state().get("selection").first().toJSON();
        wrapper.find(".ttbs-media-id").val(attachment.id || "");
        wrapper.find(".ttbs-media-url").val(attachment.url || "");
        updatePreview(wrapper, attachment.url || "");
      });

      frame.open();
    });

    context.find(".ttbs-clear").off("click").on("click", function (event) {
      event.preventDefault();

      const wrapper = $(this).closest(".ttbs-admin-field");
      wrapper.find(".ttbs-media-id").val("");
      wrapper.find(".ttbs-media-url").val("");
      updatePreview(wrapper, "");
    });
  }

  $(function () {
    bindUploader($(document.body));
  });
})(jQuery);
