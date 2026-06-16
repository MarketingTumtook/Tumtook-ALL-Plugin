(function ($) {
  function bindUploader(context) {
    context.find(".video-rollup-upload").off("click").on("click", function (event) {
      event.preventDefault();

      const trigger = $(this);
      const wrapper = trigger.closest(".video-rollup-admin-field");
      const mediaType = trigger.data("media-type") || "image";

      const frame = wp.media({
        title: mediaType === "video" ? "Select video" : "Select image",
        library: { type: mediaType },
        button: {
          text: mediaType === "video" ? "Use this video" : "Use this image",
        },
        multiple: false,
      });

      frame.on("select", function () {
        const attachment = frame.state().get("selection").first().toJSON();
        wrapper.find(".video-rollup-media-id").val(attachment.id || "");
        wrapper.find(".video-rollup-media-url").val(attachment.url || "");

        const preview = wrapper.find(".video-rollup-image-preview");

        if (preview.length) {
          preview.attr("src", attachment.url || "").toggle(!!attachment.url);
        }
      });

      frame.open();
    });
  }

  $(function () {
    bindUploader($(document.body));
  });
})(jQuery);
