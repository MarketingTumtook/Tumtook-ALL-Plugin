(function () {
  function setExpanded(item, expanded) {
    var button = item.querySelector("[data-ttfaq-toggle]");

    item.classList.toggle("is-open", expanded);

    if (button) {
      button.setAttribute("aria-expanded", expanded ? "true" : "false");
    }
  }

  function animateOpen(answer) {
    var targetHeight;

    if (!answer) {
      return;
    }

    answer.hidden = false;
    answer.style.height = "0px";
    answer.style.opacity = "0";
    targetHeight = answer.scrollHeight;
    answer.offsetHeight;

    window.requestAnimationFrame(function () {
      answer.style.height = targetHeight + "px";
      answer.style.opacity = "1";
    });
  }

  function animateClose(answer) {
    var currentHeight;

    if (!answer) {
      return;
    }

    currentHeight = answer.scrollHeight;
    answer.style.height = currentHeight + "px";
    answer.style.opacity = "1";
    answer.offsetHeight;

    window.requestAnimationFrame(function () {
      answer.style.height = "0px";
      answer.style.opacity = "0";
    });
  }

  function finalizeTransition(event) {
    var answer = event.currentTarget;

    if (event.propertyName !== "height") {
      return;
    }

    if (answer.closest(".ttfaq-item") && answer.closest(".ttfaq-item").classList.contains("is-open")) {
      answer.style.height = "auto";
      answer.style.opacity = "1";
      answer.hidden = false;
      return;
    }

    answer.hidden = true;
    answer.style.height = "0px";
  }

  function closeItem(item) {
    var answer = item.querySelector(".ttfaq-answer");

    if (!item.classList.contains("is-open")) {
      return;
    }

    setExpanded(item, false);
    animateClose(answer);
  }

  function openItem(item) {
    var answer = item.querySelector(".ttfaq-answer");

    if (item.classList.contains("is-open")) {
      return;
    }

    setExpanded(item, true);
    animateOpen(answer);
  }

  function initFaq(root) {
    var items = Array.prototype.slice.call(root.querySelectorAll("[data-ttfaq-item]"));

    if (!items.length) {
      return;
    }

    items.forEach(function (item) {
      var toggle = item.querySelector("[data-ttfaq-toggle]");
      var answer = item.querySelector(".ttfaq-answer");

      if (!toggle || !answer) {
        return;
      }

      answer.addEventListener("transitionend", finalizeTransition);

    if (item.classList.contains("is-open")) {
      answer.hidden = false;
      answer.style.height = "auto";
      answer.style.opacity = "1";
    } else {
      answer.hidden = true;
      answer.style.height = "0px";
      answer.style.opacity = "0";
    }

      toggle.addEventListener("click", function () {
        var isOpen = item.classList.contains("is-open");

        items.forEach(function (candidate) {
          if (candidate !== item) {
            closeItem(candidate);
          }
        });

        if (isOpen) {
          closeItem(item);
          return;
        }

        openItem(item);
      });
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll("[data-ttfaq]"), initFaq);
})();
