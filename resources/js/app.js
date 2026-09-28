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
            status.textContent =
                "Saved on this browser / このブラウザに保存しました";
    } catch {
        unsavedPreferences = preferences;
        if (status)
            status.textContent =
                "Applied to this window. Browser storage is unavailable. / この画面に適用しました。保存はできません。";
    }
    applyPreferences();
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
            select.replaceChildren(new Option("Automatic / 自動", ""));
            for (const voice of voices)
                select.append(
                    new Option(
                        `${voice.name}${voice.localService ? "" : " · online"}`,
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
            ? `${allergens.length} selected`
            : "Set your preferences";
    });
    document.querySelectorAll("[data-allergen-summary]").forEach((node) => {
        node.textContent = allergens.length
            ? `Highlighting ${allergens.map((item) => item.name_en).join(", ")}. You can change this anytime.`
            : "Select your allergens to highlight matching ingredients as you explore.";
    });
    document.querySelectorAll("[data-save-product]").forEach((button) => {
        const isSaved = saved.includes(Number(button.dataset.saveProduct));
        button.setAttribute("aria-pressed", String(isSaved));
        const label = button.querySelector("[data-save-label]");
        if (label)
            label.textContent = isSaved ? "Saved · Remove" : "Save product";
    });
    document.querySelectorAll("[data-product-card]").forEach((card) => {
        const matches = card.querySelectorAll(
            "[data-allergen-code].is-match",
        ).length;
        const node = card.querySelector("[data-card-match]");
        node.hidden = !matches || card.dataset.information !== "recorded";
        node.textContent = matches
            ? `${matches} of your selected allergens listed`
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
        title.textContent = "Allergen information is unconfirmed / 情報未確認";
        message.textContent =
            "Your preferences cannot be checked against an unconfirmed record. Check the actual packaging or manufacturer information.";
    } else if (!codes.length) {
        title.textContent = "Make these details personal.";
        message.textContent =
            "Select your allergens in Settings to highlight matches. / 設定でアレルゲンを選択してください。";
    } else if (matches.length) {
        title.textContent =
            "Selected allergens are listed / 選択したアレルゲンあり";
        message.textContent = matches
            .map((chip) => `${chip.dataset.nameEn} / ${chip.dataset.nameJa}`)
            .join(" · ");
    } else {
        title.textContent =
            "No selected allergens listed in this record / 記録上の一致なし";
        message.textContent =
            "This does not confirm absence. Records may be incomplete; check the actual packaging. / 含まれないことを保証するものではありません。";
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
                ? "Removed in this window. Browser storage is unavailable."
                : "Removed from your saved products.",
        );
    } else {
        if (saved.length >= 50) {
            toast(
                "Your list has 50 products. Remove one before saving another.",
            );
            return;
        }
        savePreferences({ savedProducts: [...saved, id] });
        toast(
            unsavedPreferences
                ? "Kept in this window only. Browser storage is unavailable."
                : "Saved for another look. / 商品を保存しました。",
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
        heading.textContent = "Unable to refresh your saved products.";
        const note = document.createElement("p");
        note.textContent =
            "Your saved list is still here. Check your connection and try again.";
        const retry = document.createElement("button");
        retry.className = "button secondary";
        retry.textContent = "Try again";
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
            status.textContent =
                "Reading aloud isn’t available in this browser.";
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
            if (status) status.textContent = "Finished reading.";
        }
    };
    utterance.onerror = () => {
        if (currentUtterance === utterance && status)
            status.textContent =
                "Unable to read aloud. Try another voice in Settings.";
    };
    if (status)
        status.textContent =
            lang === "ja" ? "読み上げ中…" : "Reading product information…";
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
        if (detail.dataset.demo === "true")
            parts.push(
                isJa
                    ? "架空のデモ商品です。"
                    : "This is a fictional demo product.",
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
        "Reading stopped.";
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
document
    .querySelector("[data-camera-open]")
    ?.addEventListener("click", async () => {
        const dialog = document.getElementById("camera-dialog");
        const message = document.getElementById("camera-message");
        const video = document.getElementById("camera-video");
        dialog.showModal();
        message.textContent = "Starting camera… / カメラを準備しています…";
        const generation = ++cameraGeneration;
        let handled = false;
        if (!navigator.mediaDevices?.getUserMedia) {
            message.textContent =
                "Camera access needs HTTPS and a supported browser. You can enter a barcode instead.";
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
                    const input = document.getElementById("barcode");
                    input.value = result.getText().trim();
                    document.getElementById("barcode-form").requestSubmit();
                },
            );
            if (generation !== cameraGeneration || !dialog.open) {
                controls.stop();
                return;
            }
            scannerControls = controls;
            message.textContent =
                "Hold the product barcode in the frame. / バーコードをカメラに向けてください。";
        } catch {
            if (generation !== cameraGeneration) return;
            stopCamera();
            message.textContent =
                "Camera unavailable. Check permission, or enter the barcode manually. / カメラを利用できません。手入力をご利用ください。";
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
                ? "Installation requested. Look for AllerScan with your apps."
                : "You can install later from your browser menu.";
    } catch {
        document.querySelector("[data-install-status]").textContent =
            "Use your browser menu to install AllerScan.";
    }
    installPrompt = null;
});
window.addEventListener("appinstalled", () => {
    if (installButton) installButton.hidden = true;
    const status = document.querySelector("[data-install-status]");
    if (status) status.textContent = "AllerScan has been installed.";
});
if ("serviceWorker" in navigator && window.isSecureContext) {
    navigator.serviceWorker.register("/sw.js").catch(() => {
        /* The website still works without installation support. */
    });
}
