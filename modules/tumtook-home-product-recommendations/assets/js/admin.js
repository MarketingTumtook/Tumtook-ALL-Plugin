(function () {
  function makeSectionId() {
    return 'section-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 7);
  }

  function replaceSectionId(section, oldId, newId) {
    section.setAttribute('data-section-id', newId);
    var namedFields = Array.prototype.slice.call(section.querySelectorAll('[name]'));
    Array.prototype.forEach.call(section.querySelectorAll('template'), function (template) {
      namedFields = namedFields.concat(Array.prototype.slice.call(template.content.querySelectorAll('[name]')));
    });
    namedFields.forEach(function (field) {
      field.name = field.name.split('[' + oldId + ']').join('[' + newId + ']');
    });
    var idField = section.querySelector('[data-tthpr-section-id]');
    if (idField) {
      idField.value = newId;
    }
  }

  function copyControlValues(source, clone) {
    var sourceFields = source.querySelectorAll('input, select, textarea');
    var cloneFields = clone.querySelectorAll('input, select, textarea');
    Array.prototype.forEach.call(sourceFields, function (field, index) {
      if (!cloneFields[index]) {
        return;
      }
      cloneFields[index].value = field.value;
      if (field.type === 'checkbox' || field.type === 'radio') {
        cloneFields[index].checked = field.checked;
      }
    });
  }

  function init(root) {
    if (root.__tthprAdminInitialized) {
      return;
    }
    root.__tthprAdminInitialized = true;
    var sections = root.querySelector('[data-tthpr-sections]');
    var sectionTemplate = root.querySelector('[data-tthpr-section-template]');

    function updateCards(section) {
      var rows = section.querySelector('[data-tthpr-rows]');
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

    function updateSection(section) {
      var sectionId = section.getAttribute('data-section-id');
      var title = section.querySelector('[data-tthpr-section-title]');
      var heading = section.querySelector('[data-tthpr-section-heading]');
      var code = section.querySelector('[data-tthpr-section-shortcode]');
      heading.textContent = title.value.trim() || 'ยังไม่ได้ตั้งหัวข้อ';
      code.textContent = '[tumtook_home_recommended_products section="' + sectionId + '"]';
      updateCards(section);
    }

    function updateSections() {
      var items = Array.prototype.slice.call(sections.querySelectorAll(':scope > [data-tthpr-section]'));
      items.forEach(function (section, index) {
        section.querySelector('[data-tthpr-section-number]').textContent = index + 1;
        section.querySelector('[data-tthpr-section-move="up"]').disabled = index === 0;
        section.querySelector('[data-tthpr-section-move="down"]').disabled = index === items.length - 1;
        updateSection(section);
      });
    }

    function addSection(afterSection) {
      var sectionId = makeSectionId();
      var fragment = sectionTemplate.content.cloneNode(true);
      var section = fragment.querySelector('[data-tthpr-section]');
      replaceSectionId(section, '__SECTION_ID__', sectionId);
      if (afterSection && afterSection.nextSibling) {
        sections.insertBefore(fragment, afterSection.nextSibling);
      } else {
        sections.appendChild(fragment);
      }
      updateSections();
      section.querySelector('[data-tthpr-section-title]').focus();
      return section;
    }

    root.addEventListener('input', function (event) {
      if (event.target.matches('[data-tthpr-section-title]')) {
        updateSection(event.target.closest('[data-tthpr-section]'));
      }
    });

    root.addEventListener('change', function (event) {
      if (event.target.matches('[data-tthpr-page-select]')) {
        updateCards(event.target.closest('[data-tthpr-section]'));
      }
    });

    root.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button || !root.contains(button)) {
        return;
      }

      if (button.hasAttribute('data-tthpr-add-section')) {
        addSection(null);
        return;
      }

      var section = button.closest('[data-tthpr-section]');
      if (!section) {
        return;
      }

      if (button.hasAttribute('data-tthpr-copy-shortcode')) {
        var text = section.querySelector('[data-tthpr-section-shortcode]').textContent;
        var status = section.querySelector('[data-tthpr-copy-status]');
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function () {
            status.textContent = 'คัดลอกแล้ว';
          });
        } else {
          status.textContent = 'เลือกและคัดลอกข้อความในช่อง Shortcode';
        }
        return;
      }

      if (button.hasAttribute('data-tthpr-duplicate-section')) {
        var clone = section.cloneNode(true);
        copyControlValues(section, clone);
        replaceSectionId(clone, section.getAttribute('data-section-id'), makeSectionId());
        sections.insertBefore(clone, section.nextSibling);
        updateSections();
        clone.querySelector('[data-tthpr-section-title]').focus();
        return;
      }

      if (button.hasAttribute('data-tthpr-remove-section')) {
        var nextSection = section.nextElementSibling || section.previousElementSibling;
        section.remove();
        updateSections();
        if (nextSection) {
          nextSection.querySelector('[data-tthpr-section-title]').focus();
        }
        return;
      }

      if (button.getAttribute('data-tthpr-section-move') === 'up' && section.previousElementSibling) {
        sections.insertBefore(section, section.previousElementSibling);
        updateSections();
        return;
      }
      if (button.getAttribute('data-tthpr-section-move') === 'down' && section.nextElementSibling) {
        sections.insertBefore(section.nextElementSibling, section);
        updateSections();
        return;
      }

      var rows = section.querySelector('[data-tthpr-rows]');
      var rowTemplate = section.querySelector('[data-tthpr-row-template]');
      if (button.hasAttribute('data-tthpr-add-card')) {
        rows.appendChild(rowTemplate.content.cloneNode(true));
        updateCards(section);
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
          rows.appendChild(rowTemplate.content.cloneNode(true));
        }
        updateCards(section);
        (nextRow || rows.firstElementChild).querySelector('select').focus();
      } else if (button.getAttribute('data-tthpr-move') === 'up' && row.previousElementSibling) {
        rows.insertBefore(row, row.previousElementSibling);
        updateCards(section);
      } else if (button.getAttribute('data-tthpr-move') === 'down' && row.nextElementSibling) {
        rows.insertBefore(row.nextElementSibling, row);
        updateCards(section);
      }
    });

    updateSections();
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
