(function ($) {
  function initFaqAdmin() {
    var $items = $("#ttfq-admin-items");
    var $add = $("#ttfq-admin-add");
    var $template = $("#ttfq-admin-item-template");
    var $deleted = $("#ttfq-deleted-item-ids");

    if (!$items.length || !$add.length || !$template.length || !$deleted.length) {
      return;
    }

    function syncFieldNames() {
      $items.find("[data-ttfq-item]").each(function (index) {
        $(this).find("[data-ttfq-field]").each(function () {
          var field = $(this).attr("data-ttfq-field");

          if (!field) {
            return;
          }

          $(this).attr("name", "ttfq_settings[items][" + index + "][" + field + "]");
        });
      });
    }

    function rememberDeleted(id) {
      var ids;

      if (!id) {
        return;
      }

      ids = ($deleted.val() || "").split(",").map(function (value) {
        return $.trim(value);
      }).filter(Boolean);

      if (ids.indexOf(id) === -1) {
        ids.push(id);
        $deleted.val(ids.join(","));
      }
    }

    $add.off("click.ttfaq").on("click.ttfaq", function (event) {
      var html;

      event.preventDefault();
      html = ($template.html() || "").replace(/__INDEX__/g, String(Date.now()));
      $items.append(html);
      syncFieldNames();
    });

    $(document).off("click.ttfaqRemove").on("click.ttfaqRemove", "[data-ttfq-remove]", function (event) {
      var $item;
      var itemId;

      event.preventDefault();
      event.stopPropagation();
      $item = $(this).closest("[data-ttfq-item]");
      itemId = $item.find("[data-ttfq-item-id]").val() || "";
      rememberDeleted(itemId);
      $item.find("[data-ttfq-deleted-flag]").val("1");
      $item.find("[data-ttfq-field='question']").val("");
      $item.find("[data-ttfq-field='answer']").val("");
      $item.hide();
      syncFieldNames();
    });

    syncFieldNames();
  }

  $(initFaqAdmin);
  $(document).on("elementor/popup/show", initFaqAdmin);
})(jQuery);
