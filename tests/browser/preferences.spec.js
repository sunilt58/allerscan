import { test, expect } from "@playwright/test";

let errors;
test.beforeEach(async ({ context }) => {
    errors = [];
    context.on("page", (page) =>
        page.on("pageerror", (error) => errors.push(error.message)),
    );
});
test.afterEach(() => expect(errors).toEqual([]));

test("themes synchronize tabs, survive navigation, and preserve voice and accessibility preferences", async ({
    page,
    context,
}) => {
    await page.goto("/settings");
    const companion = await context.newPage();
    await companion.goto("/");
    await page.evaluate(() =>
        localStorage.setItem(
            "allerscan-display-preferences",
            JSON.stringify({
                voices: { ja: "saved-ja", en: "saved-en" },
                allergens: ["milk"],
            }),
        ),
    );
    await page.getByRole("radio", { name: "Ocean" }).check();
    await expect(companion.locator("html")).toHaveAttribute(
        "data-theme",
        "ocean",
    );
    await page.getByRole("radio", { name: "Midnight" }).check();
    await page
        .getByRole("checkbox", { name: "High contrast", exact: true })
        .check();
    await page
        .getByRole("checkbox", { name: "Larger text", exact: true })
        .check();
    await expect(companion.locator("html")).toHaveAttribute(
        "data-theme",
        "midnight",
    );
    await expect(companion.locator("html")).toHaveClass(/high-contrast/);
    await expect(companion.locator("html")).toHaveClass(/large-text/);
    expect(
        await page.evaluate(
            () =>
                JSON.parse(
                    localStorage.getItem("allerscan-display-preferences"),
                ).voices,
        ),
    ).toEqual({ ja: "saved-ja", en: "saved-en" });
    await page.reload();
    await expect(
        page.getByRole("radio", { name: "Midnight" }),
    ).toBeChecked();
    await expect(
        page.getByRole("checkbox", { name: "Milk", exact: false }),
    ).toBeChecked();
    await expect(
        page.getByRole("checkbox", { name: "High contrast", exact: true }),
    ).toBeChecked();
    await page.setViewportSize({ width: 390, height: 844 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: "storage/app/testing/shopper-settings-midnight-mobile.png",
        fullPage: true,
    });
    await page.goto("/scan");
    await expect(page.locator("html")).toHaveAttribute(
        "data-theme",
        "midnight",
    );
});

test("invalid preferences and blocked browser storage recover with an honest save status", async ({
    page,
}) => {
    await page.goto("/settings");
    await page.evaluate(() =>
        localStorage.setItem("allerscan-display-preferences", "null"),
    );
    await page.reload();
    await expect(
        page.getByRole("radio", { name: "Forest" }),
    ).toBeChecked();
    await page.evaluate(() =>
        localStorage.setItem(
            "allerscan-display-preferences",
            JSON.stringify({
                theme: "unknown",
                allergens: "not-an-array",
                savedProducts: {},
                contrast: true,
            }),
        ),
    );
    await page.reload();
    await expect(
        page.getByRole("radio", { name: "Forest" }),
    ).toBeChecked();
    await expect(
        page.getByRole("checkbox", { name: "High contrast", exact: true }),
    ).toBeChecked();
    await page.evaluate(() =>
        Object.defineProperty(Storage.prototype, "setItem", {
            value: () => {
                throw new DOMException(
                    "Storage unavailable",
                    "QuotaExceededError",
                );
            },
        }),
    );
    await page.getByRole("radio", { name: "Ocean" }).check();
    await expect(page.locator("[data-preferences-status]")).toContainText(
        "Applied to this window",
    );
    await page.getByRole("checkbox", { name: "Milk", exact: false }).check();
    await expect(page.locator("[data-allergen-count]")).toHaveText(
        "1 selected",
    );
    await expect(page.locator("html")).toHaveAttribute("data-theme", "ocean");
});

test("chosen voices preview and read actual product information in both languages", async ({
    page,
    context,
}) => {
    await context.addInitScript(() => {
        const speech = new EventTarget();
        const voices = [
            {
                voiceURI: "ja-one",
                name: "Japanese One",
                lang: "ja-JP",
                localService: true,
            },
            {
                voiceURI: "ja-two",
                name: "Japanese Two",
                lang: "ja-JP",
                localService: true,
            },
            {
                voiceURI: "en-one",
                name: "English One",
                lang: "en-US",
                localService: true,
            },
            {
                voiceURI: "en-two",
                name: "English Two",
                lang: "en-GB",
                localService: true,
            },
        ];
        window.spoken = [];
        speech.getVoices = () => voices;
        speech.cancel = () => {};
        speech.speak = (utterance) =>
            window.spoken.push({
                voice: utterance.voice?.voiceURI,
                lang: utterance.lang,
                text: utterance.text,
            });
        Object.defineProperty(window, "speechSynthesis", { value: speech });
        window.SpeechSynthesisUtterance = class {
            constructor(text) {
                this.text = text;
            }
        };
    });
    await page.goto("/settings");
    await page
        .getByRole("combobox", { name: "Japanese voice" })
        .selectOption("ja-two");
    await page
        .getByRole("combobox", { name: "English voice" })
        .selectOption("en-two");
    await page.locator('[data-voice-test="ja"]').click();
    await expect
        .poll(() => page.evaluate(() => window.spoken.at(-1)?.voice))
        .toBe("ja-two");
    await page.locator('[data-voice-test="en"]').click();
    await expect
        .poll(() => page.evaluate(() => window.spoken.at(-1)?.voice))
        .toBe("en-two");
    await page.getByRole("radio", { name: "Midnight" }).check();
    await page.getByRole("checkbox", { name: "Milk", exact: false }).check();
    await page.reload();
    await expect(
        page.getByRole("combobox", { name: "Japanese voice" }),
    ).toHaveValue("ja-two");
    await page.goto("/scan/lookup?barcode=DEMO001");
    await page.getByRole("button", { name: "English", exact: true }).click();
    await expect
        .poll(() => page.evaluate(() => window.spoken.at(-1)?.voice))
        .toBe("en-two");
    const en = await page.evaluate(() => window.spoken.at(-1).text);
    expect(en).toContain("Everyday milk");
    expect(en).toContain("sample record, not real label information");
    expect(en).toContain("Matches your selected allergens: Milk");
    expect(en).toContain("does not mean allergen-free");
    await page.getByRole("button", { name: "日本語", exact: true }).click();
    await expect
        .poll(() => page.evaluate(() => window.spoken.at(-1)?.voice))
        .toBe("ja-two");
    expect(await page.evaluate(() => window.spoken.at(-1).text)).toContain(
        "選択したアレルゲンと一致する項目は、乳",
    );
    await page.goto("/scan/lookup?barcode=DEMO009");
    await page.getByRole("button", { name: "English", exact: true }).click();
    await expect
        .poll(() => page.evaluate(() => window.spoken.at(-1)?.text))
        .toContain("Allergen information is unconfirmed");
    expect(await page.evaluate(() => window.spoken.at(-1).text)).not.toContain(
        "No allergens recorded",
    );
});
