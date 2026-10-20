import { test, expect } from "@playwright/test";

let errors;
test.beforeEach(async ({ context }) => {
    errors = [];
    context.on("page", (page) =>
        page.on("pageerror", (error) => errors.push(error.message)),
    );
});
test.afterEach(() => expect(errors).toEqual([]));

test("personal allergen highlights and saved products persist across the shopper journey", async ({
    page,
}) => {
    await page.goto("/settings");
    await page.getByRole("checkbox", { name: "Milk", exact: false }).check();
    await page.getByRole("checkbox", { name: "Peanut", exact: false }).check();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.addInitScript(() => {
        window.spokenCount = 0;
        speechSynthesis.speak = () => window.spokenCount++;
    });
    await page.goto("/scan");
    await page.getByLabel("Product barcode").fill("DEMO001");
    await page.getByRole("button", { name: "Look up product" }).click();
    await expect(page.locator(".detail-chips .is-match")).toHaveText("Milk");
    // A scan opens the product at its photo, past the header, and nothing is read aloud by itself.
    await expect
        .poll(() =>
            page.evaluate(() =>
                Math.round(
                    document
                        .querySelector("[data-product-detail]")
                        .getBoundingClientRect().top,
                ),
            ),
        )
        .toBeLessThan(60);
    expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(100);
    await page.waitForTimeout(500);
    expect(await page.evaluate(() => window.spokenCount)).toBe(0);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page
        .getByRole("button", { name: "Save product", exact: true })
        .click();
    await expect(
        page.getByRole("button", { name: "Saved · Remove" }),
    ).toHaveAttribute("aria-pressed", "true");
    await page.goto("/saved");
    await expect(page.locator("[data-product-card]")).toHaveCount(1);
    await expect(page.locator("[data-product-card]")).toContainText(
        "Everyday milk",
    );
    await expect(page.locator("[data-card-match]")).toHaveText(
        "1 of your selected allergens listed",
    );
    await page.reload();
    await expect(page.locator("[data-save-product]")).toHaveAttribute(
        "aria-pressed",
        "true",
    );
    await page.locator("[data-save-product]").click();
    await expect(
        page.getByRole("heading", {
            name: "A little space for your favorites.",
        }),
    ).toBeVisible();
    await page.goto("/scan/lookup?barcode=DEMO004");
    await expect(page.locator(".recorded-allergens")).toContainText(
        "None recorded",
    );
    await expect(page.locator(".is-match")).toHaveCount(0);
    await page.goto("/scan/lookup?barcode=DEMO009");
    await expect(page.locator(".unknown-message")).toHaveText(
        "Information unconfirmed",
    );
});

test("search and manual barcode lookup handle missing products and camera denial", async ({
    page,
    context,
}) => {
    await context.addInitScript(() => {
        if (navigator.mediaDevices)
            navigator.mediaDevices.getUserMedia = async () => {
                throw new DOMException("Denied for test", "NotAllowedError");
            };
    });
    await page.goto("/");
    await page.getByLabel("Search products").fill("milk");
    await page.getByRole("button", { name: "Search", exact: true }).click();
    // The team may add real products, so check the sample matches rather than an exact count.
    const cards = page.locator("[data-product-card]");
    await expect(cards.filter({ hasText: "Everyday milk" })).toHaveCount(1);
    await expect(cards.filter({ hasText: "Milk chocolate" })).toHaveCount(1);
    await expect(cards.filter({ hasText: "Green tea" })).toHaveCount(0);
    await page
        .getByRole("navigation", { name: "Product categories" })
        .getByRole("link", { name: "Snacks", exact: true })
        .click();
    await expect(page.locator("[data-product-card]")).toHaveCount(1);
    await expect(page.locator("[data-product-card]")).toContainText(
        "Milk chocolate",
    );
    await page.goto("/scan");
    await page.getByLabel("Product barcode").fill("MISSING");
    await page.getByRole("button", { name: "Look up product" }).click();
    await expect(page.getByRole("alert")).toContainText("Product not found");
    await page.getByRole("button", { name: "Open camera" }).click();
    await expect(page.locator("#camera-message")).toContainText(
        "Camera unavailable",
    );
    await page
        .getByRole("button", { name: "Close camera", exact: true })
        .click();
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await page.getByLabel("Product barcode").fill("DEMO001");
    await page.getByRole("button", { name: "Look up product" }).click();
    await expect(
        page.getByRole("heading", { name: "Everyday milk", exact: true }),
    ).toBeVisible();
});

test("catalog results update while typing a partial name", async ({ page }) => {
    await page.goto("/");
    await page.locator("#product-search").pressSequentially("みるく");
    const cards = page.locator("[data-product-card]");
    await expect(cards.filter({ hasText: "Milk chocolate" })).toHaveCount(1);
    await expect(cards.filter({ hasText: "Everyday milk" })).toHaveCount(1);
    await expect(cards.filter({ hasText: "Green tea" })).toHaveCount(0);
    await expect(page).toHaveURL(/q=%E3%81%BF%E3%82%8B%E3%81%8F/);
    await page.locator("#product-search").fill("");
    await expect(cards.filter({ hasText: "Green tea" })).toHaveCount(1);
});

test("the camera reads JAN barcodes held at any angle", async ({ page }) => {
    // Without the browser's own detector, as on iPhone, so the ZXing reader is what gets tested.
    await page.addInitScript(() => {
        delete window.BarcodeDetector;
        const digits = "4901234567894";
        const left = [
            "0001101",
            "0011001",
            "0010011",
            "0111101",
            "0100011",
            "0110001",
            "0101111",
            "0111011",
            "0110111",
            "0001011",
        ];
        const even = [
            "0100111",
            "0110011",
            "0011011",
            "0100001",
            "0011101",
            "0111001",
            "0000101",
            "0010001",
            "0001001",
            "0010111",
        ];
        const right = [
            "1110010",
            "1100110",
            "1101100",
            "1000010",
            "1011100",
            "1001110",
            "1010000",
            "1000100",
            "1001000",
            "1110100",
        ];
        const parity = [
            "LLLLLL",
            "LLGLGL",
            "LLGGLG",
            "LLGGGL",
            "LGLLGG",
            "LGGLLG",
            "LGGGLL",
            "LGLGLG",
            "LGLGGL",
            "LGGLGL",
        ][digits[0]];
        let bits = "101";
        for (let i = 1; i <= 6; i++)
            bits += (parity[i - 1] === "L" ? left : even)[digits[i]];
        bits += "01010";
        for (let i = 7; i <= 12; i++) bits += right[digits[i]];
        bits += "101";
        // A fake camera showing the barcode turned by window.barcodeAngle degrees.
        navigator.mediaDevices.getUserMedia = async () => {
            const canvas = document.createElement("canvas");
            canvas.width = 1280;
            canvas.height = 720;
            const context = canvas.getContext("2d");
            const draw = () => {
                context.setTransform(1, 0, 0, 1, 0, 0);
                context.fillStyle = "#fff";
                context.fillRect(0, 0, 1280, 720);
                context.translate(640, 360);
                context.rotate(((window.barcodeAngle ?? 0) * Math.PI) / 180);
                context.fillStyle = "#000";
                [...bits].forEach((bit, index) => {
                    if (bit === "1")
                        context.fillRect(index * 3 - 142, -60, 3, 120);
                });
            };
            draw();
            setInterval(draw, 50);
            return canvas.captureStream(20);
        };
    });
    for (const angle of [0, 30, 45, 90, 135, 160]) {
        await page.goto("/scan");
        await page.evaluate((angle) => (window.barcodeAngle = angle), angle);
        const lookup = page.waitForRequest((request) =>
            request.url().includes("/scan/lookup?barcode=4901234567894"),
        );
        await page.getByRole("button", { name: "Open camera" }).click();
        await lookup;
    }
});

test("saved lists recover after a network failure and synchronize across tabs", async ({
    page,
    context,
}) => {
    await page.goto("/scan/lookup?barcode=DEMO001");
    await page
        .getByRole("button", { name: "Save product", exact: true })
        .click();
    const saved = await context.newPage();
    await saved.route("**/saved/items?*", (route) =>
        route.fulfill({ status: 503, body: "Unavailable" }),
    );
    await saved.goto("/saved");
    await expect(
        saved.getByRole("heading", {
            name: "Unable to refresh your saved products.",
        }),
    ).toBeVisible();
    await saved.unroute("**/saved/items?*");
    await saved.getByRole("button", { name: "Try again", exact: true }).click();
    await expect(saved.locator("[data-product-card]")).toHaveCount(1);
    await page.getByRole("button", { name: "Saved · Remove" }).click();
    await expect(
        saved.getByRole("heading", {
            name: "A little space for your favorites.",
        }),
    ).toBeVisible();
});

test("installation assets work and offline navigation never shows cached allergen records", async ({
    page,
    context,
}) => {
    await page.goto("/scan/lookup?barcode=DEMO001");
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.waitForFunction(
        () => navigator.serviceWorker.controller !== null,
    );
    const manifestUrl = await page
        .locator("link[rel=manifest]")
        .getAttribute("href");
    const manifest = await page.evaluate(
        async (url) => (await fetch(url)).json(),
        manifestUrl,
    );
    expect(manifest.display).toBe("standalone");
    for (const icon of manifest.icons) {
        const response = await page.evaluate(async (url) => {
            const result = await fetch(url);
            return { ok: result.ok, type: result.headers.get("content-type") };
        }, icon.src);
        expect(response.ok).toBe(true);
        expect(response.type).toContain("image/png");
    }
    await context.setOffline(true);
    await page.goto("/scan");
    await expect(page.getByRole("heading")).toContainText("You’re offline");
    await expect(page.getByText("Recorded allergens")).toHaveCount(0);
    await context.setOffline(false);
    await page.getByRole("link", { name: "Try again" }).click();
    await expect(
        page.getByRole("heading", { name: "A little scan. A clearer choice." }),
    ).toBeVisible();
});

test("phone layouts support the full app without horizontal scrolling", async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    for (const path of [
        "/",
        "/scan",
        "/settings",
        "/saved",
        "/scan/lookup?barcode=DEMO001",
    ]) {
        await page.goto(path);
        await expect(
            page.getByRole("navigation", { name: "Main navigation" }),
        ).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
            path,
        ).toBe(true);
    }
    await page.screenshot({
        path: "storage/app/testing/shopper-product-mobile.png",
        fullPage: true,
    });
    await page.goto("/settings");
    await page
        .getByRole("checkbox", { name: "Larger text", exact: true })
        .check();
    await page.getByRole("radio", { name: "Midnight" }).check();
    await page.setViewportSize({ width: 320, height: 740 });
    for (const path of [
        "/settings",
        "/",
        "/scan",
        "/scan/lookup?barcode=DEMO008",
    ]) {
        await page.goto(path);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
            path,
        ).toBe(true);
    }
});

test("team catalog editing still opens an accessible form without checkout fields", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill("admin@allerscan.test");
    await page
        .getByLabel("Password")
        .fill(process.env.ADMIN_PASSWORD || "AllerScanDemo2027!");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    // The team lands in its own area, which has no shopper navigation.
    await expect(page).toHaveURL(/\/admin\/products$/);
    await expect(
        page.getByRole("navigation", { name: "Team navigation" }),
    ).toBeVisible();
    await expect(
        page.getByRole("navigation", { name: "Main navigation" }),
    ).toHaveCount(0);
    await page.getByRole("button", { name: "Add product" }).click();
    const dialog = page.getByRole("dialog", { name: "Add product" });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel("Barcode", { exact: true })).toBeFocused();
    await expect(dialog.getByLabel("Price before tax")).toHaveCount(0);
    await page.getByRole("button", { name: "Close product form" }).click();
    await expect(dialog).not.toBeVisible();
});
