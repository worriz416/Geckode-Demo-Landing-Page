/*
 * File: js/site.js
 * Description: Handles shared site interactions such as auth form validation and tabbed auth panel behavior.
 * Primary Owner: Greg Forster
 * Team: Team 418
 */

(function () {
  /**
   * Validates the structure of an email address and returns a user-facing error.
   *
   * @param {string} email Raw email string from the form field.
   * @returns {string} Validation error text, or an empty string when valid.
   */
  function getEmailError(email) {
    if (email === "") return "Email is required.";

    const atIndex = email.indexOf("@");
    if (atIndex < 1) {
      return "Email must include an @ symbol with characters before it (e.g. user@example.com).";
    }

    const domain = email.substring(atIndex + 1);
    if (domain === "") {
      return "Email must have a domain after the @ symbol (e.g. user@example.com).";
    }

    const dotIndex = domain.indexOf(".");
    if (dotIndex < 1) {
      return "The domain must have at least one character before the dot (e.g. user@example.com).";
    }

    if (dotIndex >= domain.length - 1) {
      return "The domain must have at least one character after the dot (e.g. user@example.com).";
    }

    return "";
  }

  /**
   * Resolves a friendly label for a form field based on its surrounding markup.
   *
   * @param {HTMLInputElement} input Input element being validated.
   * @returns {string} Field label text, or a fallback label when none is found.
   */
  function getFieldLabel(input) {
    const label = input.closest(".field");
    const text = label ? label.querySelector("span") : null;
    return text ? text.textContent.trim() : "This field";
  }

  /**
   * Computes the validation message for a single input field.
   *
   * @param {HTMLInputElement} input Input element being validated.
   * @returns {string} Validation error text, or an empty string when valid.
   */
  function getFieldError(input) {
    const value = input.value.trim();

    if (input.name === "email") {
      return getEmailError(value);
    }

    if (value === "") {
      return getFieldLabel(input) + " is required.";
    }

    if (input.name === "password" && input.minLength > 0 && input.value.length < input.minLength) {
      return "Password must be at least " + input.minLength + " characters.";
    }

    return "";
  }

  /**
   * Writes the current validation message into the field UI and aria attributes.
   *
   * @param {HTMLInputElement} input Input element to update.
   * @param {string} message Error message to display, or an empty string to clear it.
   * @returns {void}
   */
  function setFieldError(input, message) {
    const field = input.closest(".field");
    const error = field ? field.querySelector(".field-error") : null;

    if (error) {
      error.textContent = message;
    }

    if (message === "") {
      input.removeAttribute("aria-invalid");
    } else {
      input.setAttribute("aria-invalid", "true");
    }
  }

  /**
   * Validates one field and updates its inline error state.
   *
   * @param {HTMLInputElement} input Input element to validate.
   * @returns {boolean} True when the field passes validation, otherwise false.
   */
  function validateField(input) {
    const message = getFieldError(input);
    setFieldError(input, message);
    return message === "";
  }

  /**
   * Attaches live validation and submit validation to a marked form.
   *
   * @param {HTMLFormElement} form Form element that should receive validation handlers.
   * @returns {void}
   */
  function setupFormValidation(form) {
    const inputs = form.querySelectorAll("input");

    inputs.forEach(function (input) {
      input.addEventListener("input", function () {
        if (input.value.trim() === "") {
          setFieldError(input, "");
          return;
        }

        validateField(input);
      });
    });

    form.addEventListener("submit", function (event) {
      let valid = true;

      inputs.forEach(function (input) {
        if (!validateField(input)) {
          valid = false;
        }
      });

      if (!valid) {
        event.preventDefault();
      }
    });
  }

  /**
   * Sets up tab switching behavior for the shared login and signup auth panel.
   *
   * @param {HTMLElement} panel Container element holding auth tabs and forms.
   * @returns {void}
   */
  function setupAuthPanel(panel) {
    const tabs = Array.from(panel.querySelectorAll("[data-form-target]"));
    const cards = Array.from(panel.querySelectorAll("[data-auth-form]"));
    const targets = tabs.map(function (tab) {
      return tab.dataset.formTarget;
    });
    const activeTab = panel.querySelector(".auth-tab.is-active");
    const defaultTarget = activeTab ? activeTab.dataset.formTarget : targets[0];

    /**
     * Checks whether a requested auth tab name exists in the current panel.
     *
     * @param {string|null} target Candidate panel key from the UI or URL.
     * @returns {boolean} True when the target matches one of the known tabs.
     */
    function isValidTarget(target) {
      return targets.indexOf(target) !== -1;
    }

    /**
     * Reads the currently requested auth panel from the URL query string.
     *
     * @returns {string|null} Valid panel key from the URL, or null when absent or invalid.
     */
    function getUrlTarget() {
      const target = new URL(window.location.href).searchParams.get("panel");
      return isValidTarget(target) ? target : null;
    }

    /**
     * Synchronizes the selected auth panel back into the browser URL.
     *
     * @param {string} target Valid auth panel key to persist in history.
     * @returns {void}
     */
    function updateUrl(target) {
      const url = new URL(window.location.href);

      if (url.searchParams.get("panel") === target) {
        return;
      }

      url.searchParams.set("panel", target);
      window.history.pushState({ panel: target }, "", url);
    }

    /**
     * Marks the requested auth tab and form card as active.
     *
     * @param {string} target Valid auth panel key to activate.
     * @returns {void}
     */
    function setActiveForm(target) {
      if (!isValidTarget(target)) {
        return;
      }

      tabs.forEach(function (tab) {
        const isActive = tab.dataset.formTarget === target;
        tab.classList.toggle("is-active", isActive);
        tab.setAttribute("aria-selected", isActive ? "true" : "false");
        tab.setAttribute("tabindex", isActive ? "0" : "-1");
      });

      cards.forEach(function (card) {
        card.classList.toggle("is-active", card.dataset.authForm === target);
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        const target = tab.dataset.formTarget;
        setActiveForm(target);
        updateUrl(target);
      });
    });

    window.addEventListener("popstate", function () {
      setActiveForm(getUrlTarget() || defaultTarget);
    });

    const initialTarget = getUrlTarget() || defaultTarget;
    if (initialTarget) {
      setActiveForm(initialTarget);
    }
  }

  /**
   * Boots the shared auth-panel and form-validation behaviors for the current page.
   *
   * @returns {void}
   */
  function initializeSiteUi() {
    const authPanel = document.querySelector("[data-auth-panel]");
    if (authPanel) {
      setupAuthPanel(authPanel);
    }

    document.querySelectorAll("[data-validate-form]").forEach(setupFormValidation);
  }

  initializeSiteUi();
})();
