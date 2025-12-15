(function () {
    "use strict";

    // =========================
    // Theme toggle
    // =========================
    const STORAGE_KEY = "otm-color-scheme";

    // Optional: add this class to the main page section container in your HTML for precise targeting:
    // <div class="uk-section uk-section-muted ... otm-theme-section">
    const THEME_SECTION_SELECTOR = ".otm-theme-section";

    let currentMode = null;

    function detectInitialMode() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === "light" || stored === "dark") return stored;

        if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
            return "dark";
        }
        return "light";
    }

    function updateIcon(mode, root) {
        const scope = root && root.querySelector ? root : document;
        const toggle = scope.getElementById ? scope.getElementById("themeToggle") : document.getElementById("themeToggle");
        if (!toggle) return;

        if (mode === "dark") {
            toggle.innerHTML = '<i class="fa-solid fa-toggle-off"></i>';
        } else {
            toggle.innerHTML = '<i class="fa-solid fa-toggle-on"></i>';
        }

        const label =
            mode === "dark"
                ? "Dark Theme (click for Light)"
                : "Light Theme (click for Dark)";
        toggle.title = label;
        toggle.setAttribute("aria-label", label);
    }

    function applyBodyMode(mode) {
        const body = document.body;
        if (!body) return;

        if (mode === "dark") {
            body.classList.add("uk-background-secondary", "uk-light");
            body.classList.remove("uk-background-default");
        } else {
            body.classList.add("uk-background-default");
            body.classList.remove("uk-background-secondary", "uk-light");
        }
    }

    function applySectionMode(mode, root) {
        const scope = root && root.querySelector ? root : document;

        // Prefer explicit hook class for the page's main section.
        let sections = scope.querySelectorAll(THEME_SECTION_SELECTOR);

        // Fallback (less precise): if no hook present, try the first muted section in this scope.
        // This helps if you have not yet added otm-theme-section.
        if (!sections || sections.length === 0) {
            const fallback = scope.querySelector(".uk-section.uk-section-muted");
            sections = fallback ? [fallback] : [];
        }

        if (!sections || sections.length === 0) return;

        sections.forEach(function (section) {
            if (!section || !section.classList) return;

            if (mode === "dark") {
                section.classList.remove("uk-section-muted");
                section.classList.add("uk-section-secondary");
            } else {
                section.classList.remove("uk-section-secondary");
                section.classList.add("uk-section-muted");
            }
        });
    }

    function applyMode(mode, persist) {
        applyBodyMode(mode);
        applySectionMode(mode, document);

        currentMode = mode;

        if (persist !== false) {
            localStorage.setItem(STORAGE_KEY, mode);
        }

        updateIcon(mode, document);
    }

    function bindToggle(root) {
        const scope = root && root.querySelector ? root : document;
        const toggle = scope.getElementById ? scope.getElementById("themeToggle") : document.getElementById("themeToggle");
        if (!toggle) return;

        if (toggle.dataset.themeBound === "1") return;
        toggle.dataset.themeBound = "1";

        toggle.addEventListener("click", function (e) {
            e.preventDefault();
            const nextMode = currentMode === "dark" ? "light" : "dark";
            applyMode(nextMode, true);
        });
    }

    // =========================
    // IconCaptcha integration
    // =========================
    const iconCaptchaConfig = {
        general: {
            endpoint: '/api/captcha-request',
            fontFamily: "inherit",
            showCredits: true,
        },
        security: {
            interactionDelay: 1500,
            hoverProtection: true,
            displayInitialMessage: true,
            initializationDelay: 500,
            incorrectSelectionResetDelay: 3000,
            loadingAnimationDuration: 1000,
        },
        locale: {
            initialization: {
                verify: "Verify that you are human.",
                loading: "Loading challenge...",
            },
            header: "Select the image displayed the <u>least</u> amount of times",
            correct: "Verification complete.",
            incorrect: {
                title: "Uh oh.",
                subtitle: "You've selected the wrong image.",
            },
            timeout: {
                title: "Please wait.",
                subtitle: "You made too many incorrect selections.",
            },
        },
    };

    function initIconCaptcha(target) {
        const root = target && target.querySelector ? target : document;
        if (!root.querySelector) return;

        if (!root.querySelector(".iconcaptcha-widget")) return;

        // Prevent rework on already-seen nodes across swaps
        const widgets = root.querySelectorAll(".iconcaptcha-widget");
        widgets.forEach(function (w) {
            if (w.dataset.iconcaptchaBound === "1") return;
            w.dataset.iconcaptchaBound = "1";
        });

        if (window.IconCaptcha && typeof window.IconCaptcha.init === "function") {
            window.IconCaptcha.init(".iconcaptcha-widget", iconCaptchaConfig);
        }
    }

    // =========================
    // Bootstrapping + HTMX hooks
    // =========================
    function initAll() {
        if (!currentMode) currentMode = detectInitialMode();

        applyMode(currentMode, true);
        bindToggle(document);
        initIconCaptcha(document);
    }

    function afterDomUpdate(target) {
        // Re-bind toggle if new DOM introduced a fresh button
        bindToggle(target);

        // Ensure swapped-in section uses current theme (UIkit section backgrounds override body)
        applySectionMode(currentMode || detectInitialMode(), target);
        updateIcon(currentMode || detectInitialMode(), target);

        // Init captcha widgets if present in swapped-in content
        initIconCaptcha(target);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAll);
    } else {
        initAll();
    }

    if (window.htmx) {
        function resolveHtmxTarget(evt) {
            return (evt && evt.detail && (evt.detail.target || evt.detail.elt)) || document;
        }

        document.body.addEventListener("htmx:afterSwap", function (evt) {
            afterDomUpdate(resolveHtmxTarget(evt));
        });

        document.body.addEventListener("htmx:load", function (evt) {
            afterDomUpdate(resolveHtmxTarget(evt));
        });
    }
})();
