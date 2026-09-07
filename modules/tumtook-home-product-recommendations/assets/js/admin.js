(function () {
  function init(root) {
    if (root.__tthprAdminInitialized) {
      return;
    }
    root.__tthprAdminInitialized = true;
    var rows = root.querySelector('[data-tthpr-rows]');
    var template = root.querySelector('[data-tthpr-row-template]');

    function updateRows() {
      var items = Array.prototype.slice.call(rows.querySelectorAll('[data-tthpr-row]'));
      var selected = items.map(function (row) {
        return row.querySelector('select').value;
      }).filter(Boolean);

      items.forEach(function (row, index) {
        row.querySelector('[data-tthpr-number]').textContent = index + 1;
        row.querySelector('[data-tthpr-move="up"]').disabled = index === 0;
        row.querySelector('[data-tthpr-move="down"]').disabled = index === items.length - 1;
        var select = row.querySelector('select');
        Array.prototype.forEach.call(select.options, function (option) {
          option.disabled = option.value !== '' && option.value !== select.value && selected.indexOf(option.value) !== -1;
        });
      });
    }

    root.addEventListener('change', function (event) {
      if (event.target.matches('[data-tthpr-page-select]')) {
        updateRows();
      }
    });

    root.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button || !root.contains(button)) {
        return;
      }
      if (button.hasAttribute('data-tthpr-add')) {
        rows.appendChild(template.content.cloneNode(true));
        updateRows();
        rows.lastElementChild.querySelector('select').focus();
        return;
      }
      var row = button.closest('[data-tthpr-row]');
      if (!row) {
        return;
      }
      if (button.hasAttribute('data-tthpr-remove')) {
        var nextRow = row.nextElementSibling || row.previousElementSibling;
        row.remove();
        if (!rows.children.length) {
          rows.appendChild(template.content.cloneNode(true));
        }
        (nextRow || rows.firstElementChild).querySelector('select').focus();
      } else if (button.getAttribute('data-tthpr-move') === 'up' && row.previousElementSibling) {
        rows.insertBefore(row, row.previousElementSibling);
      } else if (button.getAttribute('data-tthpr-move') === 'down' && row.nextElementSibling) {
        rows.insertBefore(row.nextElementSibling, row);
      }
      updateRows();
    });

    updateRows();
  }

  function ready() {
    document.querySelectorAll('[data-tthpr-admin]').forEach(init);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready);
  } else {
    ready();
  }
})();
