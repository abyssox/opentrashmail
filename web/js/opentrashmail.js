(function () {
    const STORAGE_KEY = "otm-color-scheme";
    let currentMode = null;

    function detectInitialMode() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === "light" || stored === "dark") {
            return stored;
        }
        if (window.matchMedia &&
            window.matchMedia("(prefers-color-scheme: dark)").matches) {
            return "dark";
        }
        return "light";
    }

    function updateIcon(mode) {
        const toggle = document.getElementById("themeToggle");
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

    function applyMode(mode) {
        const body = document.body;
        if (!body) return;

        if (mode === "dark") {
            body.classList.add("uk-background-secondary", "uk-light");
            body.classList.remove("uk-background-default");
        } else {
            body.classList.add("uk-background-default");
            body.classList.remove("uk-background-secondary", "uk-light");
        }

        currentMode = mode;
        localStorage.setItem(STORAGE_KEY, mode);
        updateIcon(mode);
    }

    function bindToggle() {
        const toggle = document.getElementById("themeToggle");
        if (!toggle) return;

        if (toggle.dataset.themeBound === "1") return;
        toggle.dataset.themeBound = "1";

        toggle.addEventListener("click", function (e) {
            e.preventDefault();
            const nextMode = currentMode === "dark" ? "light" : "dark";
            applyMode(nextMode);
        });
    }

    function init() {
        if (!currentMode) {
            currentMode = detectInitialMode();
        }
        applyMode(currentMode);
        bindToggle();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    if (window.htmx) {
        document.body.addEventListener("htmx:afterSwap", function () {
            bindToggle();
        });
    }
})();
