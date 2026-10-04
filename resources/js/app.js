// Interface strings come translated from the page (see partials/js-translations.blade.php).
const translations = JSON.parse(
    document.getElementById("translations")?.textContent || "{}",
);
function t(key, replacements = {}) {
    let text = translations[key] ?? key;
    for (const [name, value] of Object.entries(replacements))
        text = text.replaceAll(`:${name}`, value);
    return text;
}
const preferencesKey = "allerscan-display-preferences";
const themes = ["forest", "ocean", "midnight"];
let unsavedPreferences = null;
function readPreferences() {
    if (unsavedPreferences) return unsavedPreferences;
    try {
        const value = JSON.parse(localStorage.getItem(preferencesKey) || "{}");
        return value && typeof value === "object" && !Array.isArray(value)
            ? value
            : {};
    } catch {
        return {};
    }
}
function applyPreferences() {
    const preferences = readPreferences();
    const theme = themes.includes(preferences.theme)
        ? preferences.theme
        : "forest";
    document.documentElement.dataset.theme = theme;
    for (const choice of document.querySelectorAll("[data-theme-choice]")) {
        choice.checked = choice.value === theme;
    }
    document.documentElement.classList.toggle(
        "high-contrast",
        !!preferences.contrast,
    );
    document.documentElement.classList.toggle(
        "large-text",
        !!preferences.largeText,
    );
    if (document.querySelector("#contrast-setting"))
        document.querySelector("#contrast-setting").checked =
            !!preferences.contrast;
    if (document.querySelector("#large-text-setting"))
        document.querySelector("#large-text-setting").checked =
            !!preferences.largeText;
    window.dispatchEvent(new Event("preferences-updated"));
}
function savePreferences(changes) {
    const preferences = { ...readPreferences(), ...changes };
    const status = document.querySelector("[data-preferences-status]");
    try {
        localStorage.setItem(preferencesKey, JSON.stringify(preferences));
        unsavedPreferences = null;
        if (status)
            status.textContent = accountState
                ? t("Saved to your account")
                : t("Saved on this browser");
    } catch {
        unsavedPreferences = preferences;
        if (status)
            status.textContent = t(
                "Applied to this window. Browser storage is unavailable.",
            );
    }
    applyPreferences();
    if (accountState && syncedKeys.some((key) => key in changes))
        pushAccountPreferences();
}
// Signed-in shoppers keep allergens and saved products on their account; this browser mirrors them.
const accountState = JSON.parse(
    document.getElementById("account-state")?.textContent || "null",
);
const syncedKeys = ["allergens", "savedProducts"];
function storePreferences(preferences) {
    try {
        localStorage.setItem(preferencesKey, JSON.stringify(preferences));
        unsavedPreferences = null;
    } catch {
        unsavedPreferences = preferences;
    }
}
// Sent immediately and kept alive, so a change made just before leaving the page still reaches the account.
async function pushAccountPreferences() {
    const preferences = readPreferences();
    try {
        const response = await fetch(accountState.url, {
            method: "PUT",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
            body: JSON.stringify({
                allergens: Array.isArray(preferences.allergens)
                    ? preferences.allergens
                    : [],
                savedProducts: Array.isArray(preferences.savedProducts)
                    ? preferences.savedProducts
                          .filter((id) => Number.isSafeInteger(id) && id > 0)
                          .slice(0, 50)
                    : [],
            }),
            keepalive: true,
        });
        if (!response.ok) throw new Error("Account sync failed");
    } catch {
        toast(
            t(
                "Couldn’t save to your account. Check your connection, then change it again.",
            ),
        );
    }
}
if (accountState) {
    const local = readPreferences();
    const union = (first, second) => [
        ...new Set([...first, ...(Array.isArray(second) ? second : [])]),
    ];
    // Right after signing in, what this browser already had joins the account; afterwards the account wins.
    storePreferences({
        ...local,
        allergens: accountState.merge
            ? union(accountState.allergens, local.allergens)
            : accountState.allergens,
        savedProducts: accountState.merge
            ? union(accountState.savedProducts, local.savedProducts).slice(
                  0,
                  50,
              )
            : accountState.savedProducts,
    });
    if (accountState.merge) pushAccountPreferences();
} else if (document.getElementById("account-signed-out")) {
    const { allergens, savedProducts, ...kept } = readPreferences();
    storePreferences(kept);
}
applyPreferences();
window.addEventListener("storage", (event) => {
    if (event.key === preferencesKey || event.key === null) {
        unsavedPreferences = null;
        applyPreferences();
    }
});
for (const choice of document.querySelectorAll("[data-theme-choice]")) {
    choice.addEventListener("change", () => {
        if (choice.checked && themes.includes(choice.value))
            savePreferences({ theme: choice.value });
    });
}
for (const [id, key] of [
    ["contrast-setting", "contrast"],
    ["large-text-setting", "largeText"],
]) {
    document.getElementById(id)?.addEventListener("change", (event) => {
        savePreferences({ [key]: event.target.checked });
    });
}
// Voices are device-specific and stay in this browser.
const speechCodes = { ja: "ja-JP", en: "en-US" };
function voicesFor(lang) {
    const prefix = lang === "ja" ? "ja" : "en";
    return (window.speechSynthesis?.getVoices() || []).filter((voice) =>
        voice.lang.replace("_", "-").toLowerCase().startsWith(prefix),
    );
}
function pickVoice(lang) {
    const voices = voicesFor(lang);
    const saved = readPreferences().voices?.[lang];
    return (
        voices.find((voice) => voice.voiceURI === saved) ||
        voices.find(
            (voice) => voice.lang.replace("_", "-") === speechCodes[lang],
        ) ||
        voices[0] ||
        null
    );
}
const voiceSettings = document.querySelector("[data-voice-settings]");
if (voiceSettings && window.speechSynthesis) {
    const fillVoiceSelects = () => {
        for (const select of voiceSettings.querySelectorAll(
            "[data-voice-select]",
        )) {
            const lang = select.dataset.voiceSelect;
            const voices = voicesFor(lang);
            const saved = readPreferences().voices?.[lang] || "";
            select.replaceChildren(new Option(t("Automatic"), ""));
            for (const voice of voices)
                select.append(
                    new Option(
                        `${voice.name}${voice.localService ? "" : ` · ${t("online")}`}`,
                        voice.voiceURI,
                    ),
                );
            select.value = voices.some((voice) => voice.voiceURI === saved)
                ? saved
                : "";
            voiceSettings.querySelector(`[data-voice-empty="${lang}"]`).hidden =
                voices.length > 0;
        }
    };
    fillVoiceSelects();
    speechSynthesis.addEventListener("voiceschanged", fillVoiceSelects);
    window.addEventListener("preferences-updated", fillVoiceSelects);
    voiceSettings.addEventListener("change", (event) => {
        const lang = event.target.dataset.voiceSelect;
        if (!lang) return;
        const preferences = readPreferences();
        savePreferences({
            voices: { ...preferences.voices, [lang]: event.target.value },
        });
    });
    voiceSettings.addEventListener("click", (event) => {
        const button = event.target.closest("[data-voice-test]");
        if (!button) return;
        const lang = button.dataset.voiceTest;
        const selected = voicesFor(lang).find(
            (voice) =>
                voice.voiceURI ===
                voiceSettings.querySelector(`[data-voice-select="${lang}"]`)
                    .value,
        );
        speakMessage(button.dataset.sample, lang, selected);
    });
} else if (voiceSettings) {
    voiceSettings
        .querySelector("[data-voice-unavailable]")
        ?.removeAttribute("hidden");
    for (const element of voiceSettings.querySelectorAll("select, button"))
        element.disabled = true;
}

const allergenCatalog = JSON.parse(
    document.getElementById("allergen-catalog")?.textContent || "[]",
);
function selectedAllergens() {
    const values = readPreferences().allergens;
    return Array.isArray(values)
        ? allergenCatalog.filter((item) => values.includes(item.code))
        : [];
}
function savedProducts() {
    const values = readPreferences().savedProducts;
    return Array.isArray(values)
        ? [
              ...new Set(
                  values.filter((id) => Number.isSafeInteger(id) && id > 0),
              ),
          ].slice(0, 50)
        : [];
}
let toastTimer;
function toast(message) {
    const node = document.querySelector("[data-toast]");
    if (!node) return;
    clearTimeout(toastTimer);
    node.textContent = message;
    node.hidden = false;
    toastTimer = setTimeout(() => {
        node.hidden = true;
    }, 4500);
}
function applyShopperPreferences() {
    const allergens = selectedAllergens();
    const codes = allergens.map((item) => item.code);
    const saved = savedProducts();
    document.querySelectorAll("[data-allergen-choice]").forEach((input) => {
        input.checked = codes.includes(input.dataset.allergenChoice);
    });
    document.querySelectorAll("[data-allergen-code]").forEach((chip) => {
        chip.classList.toggle(
            "is-match",
            codes.includes(chip.dataset.allergenCode),
        );
    });
    document.querySelectorAll("[data-allergen-count]").forEach((node) => {
        node.textContent = allergens.length
            ? t(":count selected", { count: allergens.length })
            : t("Set your preferences");
    });
    document.querySelectorAll("[data-allergen-summary]").forEach((node) => {
        node.textContent = allergens.length
            ? t("Highlighting :names. You can change this anytime.", {
                  names: allergens.map((item) => item.name).join(t(", ")),
              })
            : t(
                  "Select your allergens to highlight matching ingredients as you explore.",
              );
    });
    document.querySelectorAll("[data-save-product]").forEach((button) => {
        const isSaved = saved.includes(Number(button.dataset.saveProduct));
        button.setAttribute("aria-pressed", String(isSaved));
        const label = button.querySelector("[data-save-label]");
        if (label)
            label.textContent = isSaved
                ? t("Saved · Remove")
                : t("Save product");
    });
    document.querySelectorAll("[data-product-card]").forEach((card) => {
        const matches = card.querySelectorAll(
            "[data-allergen-code].is-match",
        ).length;
        const node = card.querySelector("[data-card-match]");
        node.hidden = !matches || card.dataset.information !== "recorded";
        node.textContent = matches
            ? t(":count of your selected allergens listed", { count: matches })
            : "";
    });
    const detail = document.querySelector("[data-product-detail]");
    if (!detail) return;
    const panel = detail.querySelector("[data-match-panel]");
    const matches = [
        ...detail.querySelectorAll("[data-allergen-code].is-match"),
    ];
    const unknown = detail.dataset.information !== "recorded";
    panel.classList.toggle("is-unknown", unknown);
    panel.classList.toggle("has-match", !unknown && matches.length > 0);
    const title = panel.querySelector("[data-match-title]");
    const message = panel.querySelector("[data-match-message]");
    if (unknown) {
        title.textContent = t("Allergen information is unconfirmed");
        message.textContent = t(
            "Your preferences cannot be checked against an unconfirmed record. Check the actual packaging or manufacturer information.",
        );
    } else if (!codes.length) {
        title.textContent = t("Make these details personal.");
        message.textContent = t(
            "Select your allergens in Settings to highlight matches.",
        );
    } else if (matches.length) {
        title.textContent = t("Selected allergens are listed");
        message.textContent = matches
            .map((chip) => chip.dataset.name)
            .join(t(", "));
    } else {
        title.textContent = t("No selected allergens listed in this record");
        message.textContent = t(
            "This does not confirm absence. Records may be incomplete; check the actual packaging.",
        );
    }
}
window.addEventListener("preferences-updated", applyShopperPreferences);
applyShopperPreferences();
for (const input of document.querySelectorAll("[data-allergen-choice]")) {
    input.addEventListener("change", () => {
        savePreferences({
            allergens: [
                ...document.querySelectorAll("[data-allergen-choice]:checked"),
            ].map((item) => item.value),
        });
    });
}

document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-save-product]");
    if (!button) return;
    const id = Number(button.dataset.saveProduct);
    const saved = savedProducts();
    if (saved.includes(id)) {
        savePreferences({
            savedProducts: saved.filter((value) => value !== id),
        });
        toast(
            unsavedPreferences
                ? t("Removed in this window. Browser storage is unavailable.")
                : t("Removed from your saved products."),
        );
    } else {
        if (saved.length >= 50) {
            toast(
                t(
                    "Your list has 50 products. Remove one before saving another.",
                ),
            );
            return;
        }
        savePreferences({ savedProducts: [...saved, id] });
        toast(
            unsavedPreferences
                ? t("Kept in this window only. Browser storage is unavailable.")
                : t("Saved for another look."),
        );
    }
    if (savedList) loadSavedProducts();
});
const savedList = document.querySelector("[data-saved-list]");
let savedRequest;
async function loadSavedProducts() {
    if (!savedList) return;
    savedRequest?.abort();
    const controller = new AbortController();
    savedRequest = controller;
    savedList.setAttribute("aria-busy", "true");
    const url = new URL(savedList.dataset.url, window.location.origin);
    savedProducts().forEach((id) =>
        url.searchParams.append("ids[]", String(id)),
    );
    try {
        const response = await fetch(url, {
            signal: controller.signal,
            headers: {
                Accept: "text/html",
                "X-Requested-With": "XMLHttpRequest",
            },
            cache: "no-store",
        });
        if (!response.ok) throw new Error("Saved products unavailable");
        const html = await response.text();
        if (controller.signal.aborted) return;
        savedList.innerHTML = html; // Same-origin Blade output; product fields are escaped server-side.
        applyShopperPreferences();
    } catch (error) {
        if (error.name === "AbortError") return;
        const panel = document.createElement("div");
        panel.className = "shop-empty";
        const heading = document.createElement("h2");
        heading.textContent = t("Unable to refresh your saved products.");
        const note = document.createElement("p");
        note.textContent = t(
            "Your saved list is still here. Check your connection and try again.",
        );
        const retry = document.createElement("button");
        retry.className = "button secondary";
        retry.textContent = t("Try again");
        retry.addEventListener("click", loadSavedProducts);
        panel.append(heading, note, retry);
        savedList.replaceChildren(panel);
    } finally {
        if (!controller.signal.aborted)
            savedList.setAttribute("aria-busy", "false");
    }
}
loadSavedProducts();
window.addEventListener("storage", (event) => {
    if (savedList && (event.key === preferencesKey || event.key === null))
        loadSavedProducts();
});

// Keep a strong reference and defer after cancel() for browser speech reliability.
let currentUtterance = null;
let speechTimer;
function stopSpeech() {
    clearTimeout(speechTimer);
    window.speechSynthesis?.cancel();
    currentUtterance = null;
}
function speakMessage(message, lang, voice = null) {
    const status = document.querySelector("[data-speech-status]");
    if (!window.speechSynthesis || !window.SpeechSynthesisUtterance) {
        if (status)
            status.textContent = t(
                "Reading aloud isn’t available in this browser.",
            );
        return;
    }
    stopSpeech();
    const utterance = new SpeechSynthesisUtterance(message);
    utterance.lang = speechCodes[lang];
    utterance.voice = voice || pickVoice(lang);
    utterance.rate = 0.95;
    currentUtterance = utterance;
    utterance.onend = () => {
        if (currentUtterance === utterance) {
            currentUtterance = null;
            if (status) status.textContent = t("Finished reading.");
        }
    };
    utterance.onerror = () => {
        if (currentUtterance === utterance && status)
            status.textContent = t(
                "Unable to read aloud. Try another voice in Settings.",
            );
    };
    if (status) status.textContent = t("Reading product information…");
    speechTimer = setTimeout(() => speechSynthesis.speak(utterance), 100);
}
for (const button of document.querySelectorAll("[data-read-product]")) {
    button.addEventListener("click", () => {
        const detail = document.querySelector("[data-product-detail]");
        const lang = button.dataset.readProduct;
        const isJa = lang === "ja";
        const chips = [...detail.querySelectorAll("[data-allergen-code]")];
        const unknown = detail.dataset.information !== "recorded";
        const names = chips.map((chip) =>
            isJa ? chip.dataset.nameJa : chip.dataset.nameEn,
        );
        const matches = chips
            .filter((chip) => chip.classList.contains("is-match"))
            .map((chip) => (isJa ? chip.dataset.nameJa : chip.dataset.nameEn));
        const parts = [isJa ? detail.dataset.nameJa : detail.dataset.nameEn];
        if (detail.dataset.sample === "true")
            parts.push(
                isJa
                    ? "これはサンプルの記録で、実際の商品表示ではありません。"
                    : "This is a sample record, not real label information.",
            );
        if (unknown)
            parts.push(
                isJa
                    ? "アレルゲン情報は未確認です。"
                    : "Allergen information is unconfirmed.",
            );
        else {
            parts.push(
                names.length
                    ? (isJa
                          ? "記録されているアレルゲンは、"
                          : "Recorded allergens: ") + names.join(", ")
                    : isJa
                      ? "記録上の該当アレルゲンはありません。"
                      : "No allergens recorded.",
            );
            if (selectedAllergens().length)
                parts.push(
                    matches.length
                        ? (isJa
                              ? "選択したアレルゲンと一致する項目は、"
                              : "Matches your selected allergens: ") +
                              matches.join(", ")
                        : isJa
                          ? "選択したアレルゲンとの記録上の一致はありません。"
                          : "No selected allergens listed in this record.",
                );
        }
        parts.push(
            isJa
                ? "記録にないアレルゲンが含まれる可能性があります。必ず実際の商品表示とメーカー情報を確認してください。"
                : "Records may be incomplete. This does not mean allergen-free. Always check the actual package label and manufacturer information.",
        );
        speakMessage(parts.join(". "), lang);
    });
}
document.querySelector("[data-stop-speech]")?.addEventListener("click", () => {
    stopSpeech();
    document.querySelector("[data-speech-status]").textContent =
        t("Reading stopped.");
});

let scannerControls = null;
let cameraGeneration = 0;
function stopCamera() {
    cameraGeneration++;
    scannerControls?.stop();
    scannerControls = null;
    const video = document.getElementById("camera-video");
    video?.srcObject?.getTracks().forEach((track) => track.stop());
    if (video) video.srcObject = null;
}
// Delegated so buttons re-rendered by Livewire keep working. The button names the input and form for the code.
document.addEventListener("click", async (event) => {
    const opener = event.target.closest("[data-camera-open]");
    if (!opener) return;
    const target = {
        input: opener.dataset.cameraInput || "barcode",
        form: opener.dataset.cameraForm || "barcode-form",
    };
    const dialog = document.getElementById("camera-dialog");
    const message = document.getElementById("camera-message");
    const video = document.getElementById("camera-video");
    dialog.showModal();
    message.textContent = t("Starting camera…");
    const generation = ++cameraGeneration;
    let handled = false;
    if (!navigator.mediaDevices?.getUserMedia) {
        message.textContent = t(
            "Camera access needs HTTPS and a supported browser. You can enter a barcode instead.",
        );
        return;
    }
    try {
        const { BrowserMultiFormatReader } = await import("@zxing/browser");
        if (generation !== cameraGeneration) return;
        const reader = new BrowserMultiFormatReader();
        const controls = await reader.decodeFromConstraints(
            { video: { facingMode: "environment" }, audio: false },
            video,
            (result, error, controls) => {
                if (!result || handled || generation !== cameraGeneration)
                    return;
                handled = true;
                controls.stop();
                dialog.close();
                document.getElementById(target.input).value = result
                    .getText()
                    .trim();
                document.getElementById(target.form).requestSubmit();
            },
        );
        if (generation !== cameraGeneration || !dialog.open) {
            controls.stop();
            return;
        }
        scannerControls = controls;
        message.textContent = t("Hold the product barcode in the frame.");
    } catch {
        if (generation !== cameraGeneration) return;
        stopCamera();
        message.textContent = t(
            "Camera unavailable. Check permission, or enter the barcode manually.",
        );
    }
});
document
    .querySelectorAll("[data-camera-close]")
    .forEach((button) =>
        button.addEventListener("click", () =>
            document.getElementById("camera-dialog").close(),
        ),
    );
document.getElementById("camera-dialog")?.addEventListener("close", stopCamera);
window.addEventListener("pagehide", () => {
    stopCamera();
    stopSpeech();
});
document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
        document.getElementById("camera-dialog")?.close();
        stopSpeech();
    }
});

let installPrompt;
const installButton = document.querySelector("[data-install]");
window.addEventListener("beforeinstallprompt", (event) => {
    if (!installButton) return;
    event.preventDefault();
    installPrompt = event;
    installButton.hidden = false;
});
installButton?.addEventListener("click", async () => {
    if (!installPrompt) return;
    installButton.hidden = true;
    try {
        await installPrompt.prompt();
        const result = await installPrompt.userChoice;
        document.querySelector("[data-install-status]").textContent =
            result.outcome === "accepted"
                ? t(
                      "Installation requested. Look for AllerScan with your apps.",
                  )
                : t("You can install later from your browser menu.");
    } catch {
        document.querySelector("[data-install-status]").textContent = t(
            "Use your browser menu to install AllerScan.",
        );
    }
    installPrompt = null;
});
window.addEventListener("appinstalled", () => {
    if (installButton) installButton.hidden = true;
    const status = document.querySelector("[data-install-status]");
    if (status) status.textContent = t("AllerScan has been installed.");
});
if ("serviceWorker" in navigator && window.isSecureContext) {
    navigator.serviceWorker.register("/sw.js").catch(() => {
        /* The website still works without installation support. */
    });
}
