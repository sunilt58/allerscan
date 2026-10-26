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
}
window.addEventListener("preferences-updated", applyShopperPreferences);
applyShopperPreferences();
// Catalog results update while the shopper types, without waiting for the Search button.
// Japanese input waits until the IME conversion is confirmed.
const catalogSearch = document.querySelector(".catalog-search");
const catalogResults = document.querySelector("[data-catalog-results]");
if (catalogSearch && catalogResults) {
    const input = catalogSearch.querySelector('input[name="q"]');
    let composing = false;
    let timer;
    let request;
    const updateResults = async () => {
        const url = new URL(catalogSearch.action);
        url.hash = "";
        for (const [key, value] of new FormData(catalogSearch)) {
            if (String(value).trim()) url.searchParams.set(key, value);
        }
        request?.abort();
        request = new AbortController();
        try {
            const response = await fetch(url, { signal: request.signal });
            if (!response.ok) return;
            const page = new DOMParser().parseFromString(
                await response.text(),
                "text/html",
            );
            const results = page.querySelector("[data-catalog-results]");
            if (!results) return;
            catalogResults.innerHTML = results.innerHTML;
            const count = page.querySelector("[data-product-count]");
            if (count)
                document.querySelector("[data-product-count]").textContent =
                    count.textContent;
            history.replaceState(
                null,
                "",
                `${url.pathname}${url.search}#catalog`,
            );
            applyShopperPreferences();
        } catch {
            /* Aborted by newer typing, or offline: the Search button still works. */
        }
    };
    const scheduleUpdate = () => {
        clearTimeout(timer);
        timer = setTimeout(updateResults, 250);
    };
    input.addEventListener("compositionstart", () => (composing = true));
    input.addEventListener("compositionend", () => {
        composing = false;
        scheduleUpdate();
    });
    input.addEventListener("input", () => {
        if (!composing) scheduleUpdate();
    });
}
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
// onBlocked runs when the browser refuses to speak without a tap (for example iPhone Safari after a scan).
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
        if (currentUtterance !== utterance) return;
        if (status)
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
        const aiDraft = detail.dataset.information === "ai_draft";
        const unknown = !aiDraft && detail.dataset.information !== "recorded";
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
        else if (aiDraft)
            // An AI estimate is never read out as "no allergens".
            parts.push(
                names.length
                    ? (isJa
                          ? "AIが推定したアレルゲンは、"
                          : "Allergens estimated by AI: ") + names.join(", ")
                    : isJa
                      ? "AIではアレルゲンが見つかりませんでした。"
                      : "AI did not find any allergens.",
                isJa
                    ? "これはチームが確認する前の、AIによる推定です。"
                    : "This is an AI estimate that our team has not checked yet.",
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
// After a lookup from the Scan page, open the product page at its photo, past the header and safety notice.
const scannedKey = "allerscan-scanned";
function takeScannedRequest() {
    try {
        const requestedAt = Number(sessionStorage.getItem(scannedKey));
        sessionStorage.removeItem(scannedKey);
        return requestedAt > 0 && Date.now() - requestedAt < 60000;
    } catch {
        return false;
    }
}
if (takeScannedRequest()) {
    document
        .querySelector("[data-product-detail]")
        ?.scrollIntoView({ block: "start" });
}
document.getElementById("barcode-form")?.addEventListener("submit", () => {
    try {
        sessionStorage.setItem(scannedKey, String(Date.now()));
    } catch {
        /* Without storage the product page simply opens at the top. */
    }
});
document.querySelector("[data-stop-speech]")?.addEventListener("click", () => {
    stopSpeech();
    document.querySelector("[data-speech-status]").textContent =
        t("Reading stopped.");
});

let scannerControls = null;
let cameraGeneration = 0;
// Barcodes on Japanese products (JAN/EAN, UPC), plus Code 128 and Code 39 for printed sample labels.
const retailFormats = ["ean_13", "ean_8", "upc_a", "upc_e"];
const scanFormats = [...retailFormats, "code_128", "code_39"];
// DecodeHintType.POSSIBLE_FORMATS from @zxing/library, which @zxing/browser does not re-export.
const zxingPossibleFormats = 2;
// ZXing reads a barcode tilted up to about 20°, so turning each frame by the next of these angles reads any angle.
const zxingAngles = [0, 45, 90, 135];
/**
 * Returns a function that reads one barcode from the video, or null. The browser's own detector
 * (Android Chrome and others) is fast and reads any angle. Otherwise ZXing reads each frame turned
 * by the next angle in zxingAngles. Its own "try harder" rotation is not used: it does not rotate canvases.
 */
async function createBarcodeDetector() {
    if ("BarcodeDetector" in window) {
        try {
            const supported =
                await window.BarcodeDetector.getSupportedFormats();
            const formats = scanFormats.filter((format) =>
                supported.includes(format),
            );
            if (formats.includes("ean_13")) {
                const detector = new window.BarcodeDetector({ formats });
                return async (video) => {
                    const [code] = await detector.detect(video);
                    return code
                        ? {
                              text: code.rawValue.trim(),
                              isRetail: retailFormats.includes(code.format),
                          }
                        : null;
                };
            }
        } catch {
            /* Fall back to ZXing below. */
        }
    }
    const { BrowserMultiFormatOneDReader, BarcodeFormat } =
        await import("@zxing/browser");
    const zxingRetail = [
        BarcodeFormat.EAN_13,
        BarcodeFormat.EAN_8,
        BarcodeFormat.UPC_A,
        BarcodeFormat.UPC_E,
    ];
    const reader = new BrowserMultiFormatOneDReader(
        new Map([
            [
                zxingPossibleFormats,
                [...zxingRetail, BarcodeFormat.CODE_128, BarcodeFormat.CODE_39],
            ],
        ]),
    );
    const canvas = document.createElement("canvas");
    const context = canvas.getContext("2d", { willReadFrequently: true });
    let frame = 0;
    return async (video) => {
        const { videoWidth: width, videoHeight: height } = video;
        if (!width || !height) return null;
        // A square from the middle of the picture, scaled down for speed.
        const side = Math.min(width, height);
        const size = Math.min(side, 800);
        canvas.width = canvas.height = size;
        context.translate(size / 2, size / 2);
        context.rotate(
            (zxingAngles[frame++ % zxingAngles.length] * Math.PI) / 180,
        );
        context.drawImage(
            video,
            (width - side) / 2,
            (height - side) / 2,
            side,
            side,
            -size / 2,
            -size / 2,
            size,
            size,
        );
        try {
            const result = reader.decodeFromCanvas(canvas);
            return {
                text: result.getText().trim(),
                isRetail: zxingRetail.includes(result.getBarcodeFormat()),
            };
        } catch {
            return null;
        }
    };
}
/**
 * Starts the rear camera in HD with continuous focus and reads every frame until a barcode is found.
 * Retail barcodes carry a check digit, so one read is enough; other formats must read the same twice.
 */
async function startBarcodeScan(video, generation, onBarcode) {
    const stream = await navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: { ideal: "environment" },
            width: { ideal: 1920 },
            height: { ideal: 1080 },
        },
        audio: false,
    });
    if (generation !== cameraGeneration) {
        stream.getTracks().forEach((track) => track.stop());
        return null;
    }
    video.srcObject = stream;
    stream
        .getVideoTracks()[0]
        ?.applyConstraints({ advanced: [{ focusMode: "continuous" }] })
        .catch(() => {});
    await video.play();
    const detect = await createBarcodeDetector();
    const nextFrame = (callback) =>
        video.requestVideoFrameCallback
            ? video.requestVideoFrameCallback(callback)
            : requestAnimationFrame(callback);
    let stopped = false;
    let previous = "";
    const scanFrame = async () => {
        if (stopped || generation !== cameraGeneration) return;
        const found = await detect(video).catch(() => null);
        if (stopped || generation !== cameraGeneration) return;
        if (found?.text && (found.isRetail || found.text === previous)) {
            stopped = true;
            onBarcode(found.text);
            return;
        }
        previous = found?.text ?? "";
        nextFrame(scanFrame);
    };
    nextFrame(scanFrame);
    return {
        stop() {
            stopped = true;
        },
    };
}
function stopCamera() {
    cameraGeneration++;
    scannerControls?.stop();
    scannerControls = null;
    const video = document.getElementById("camera-video");
    video?.srcObject?.getTracks().forEach((track) => track.stop());
    if (video) {
        video.srcObject = null;
        video.classList.remove("is-mirrored");
    }
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
        const controls = await startBarcodeScan(video, generation, (code) => {
            if (handled) return;
            handled = true;
            dialog.close();
            document.getElementById(target.input).value = code;
            document.getElementById(target.form).requestSubmit();
        });
        if (!controls) return;
        if (generation !== cameraGeneration || !dialog.open) {
            controls.stop();
            return;
        }
        scannerControls = controls;
        // Mirror the preview like a mirror for front cameras and laptop webcams (which usually report no
        // direction), but never for a phone's rear camera. Decoding reads the unflipped frames.
        const facing = video.srcObject
            ?.getVideoTracks()[0]
            ?.getSettings().facingMode;
        video.classList.toggle("is-mirrored", facing !== "environment");
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
// "Scan a product" links here with ?camera=1 to open the camera straight away.
if (new URLSearchParams(location.search).get("camera") === "1") {
    history.replaceState(null, "", location.pathname);
    document
        .querySelector('[data-camera-open][data-camera-form="barcode-form"]')
        ?.click();
}
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
