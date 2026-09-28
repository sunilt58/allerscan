import { test, expect } from "@playwright/test";

// Start without the English cookie that the other browser checks use.
test.use({ storageState: { cookies: [], origins: [] } });

test("Japanese is the default and the header switch changes page and script text", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));

    await page.goto("/");
    await expect(page.locator("html")).toHaveAttribute("lang", "ja");
    await expect(
        page
            .getByRole("navigation", { name: "メインナビゲーション" })
            .getByRole("link", { name: "探す" }),
    ).toBeVisible();
    await expect(page.locator("[data-allergen-count]")).toHaveText(
        "設定してください",
    );

    await page.getByRole("link", { name: "English", exact: true }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.locator("html")).toHaveAttribute("lang", "en");
    await expect(
        page
            .getByRole("navigation", { name: "Main navigation" })
            .getByRole("link", { name: "Discover" }),
    ).toBeVisible();

    await page.goto("/scan/lookup?barcode=DEMO001");
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(
        "Everyday milk",
    );
    await page.getByRole("link", { name: "日本語", exact: true }).click();
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(
        "まいにちミルク",
    );
    await expect(page.locator("[data-match-title]")).toHaveText(
        "あなた向けに表示しましょう。",
    );
    expect(errors).toEqual([]);
});
