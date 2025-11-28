function navbarmanager() {
    var x = document.getElementById("OTMTopnav");
    if (x.className === "topnav") {
        x.className += " responsive";
    } else {
        x.className = "topnav";
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const STORAGE_KEY = "otm-color-scheme";
    const root = document.documentElement;
    const toggle = document.getElementById("themeToggle");

    if (!toggle) {
        console.warn("themeToggle element not found");
        return;
    }

    function detectInitialMode() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === "light" || stored === "dark") {
            return stored;
        }
        if (
            window.matchMedia &&
            window.matchMedia("(prefers-color-scheme: dark)").matches
        ) {
            return "dark";
        }
        return "light";
    }

    function updateIcon(mode) {
        if (mode === "dark") {
            toggle.innerHTML = '<i class="fa-solid fa-toggle-off"></i>';
        } else {
            toggle.innerHTML = '<i class="fa-solid fa-toggle-on"></i>';
        }
    }

    function applyMode(mode) {
        if (mode !== "light" && mode !== "dark") {
            mode = "light";
        }

        root.setAttribute("data-theme", mode);
        localStorage.setItem(STORAGE_KEY, mode);
        updateIcon(mode);

        const label =
            mode === "dark"
                ? "Dark Theme (click for Light)"
                : "Light Theme (click for Dark)";
        toggle.title = label;
        toggle.setAttribute("aria-label", label);
    }

    let currentMode = detectInitialMode();
    applyMode(currentMode);

    toggle.addEventListener("click", function (event) {
        event.preventDefault();
        currentMode = currentMode === "dark" ? "light" : "dark";
        applyMode(currentMode);
    });
});
