import { test, expect } from "@playwright/test";

let errors;
test.beforeEach(async ({ context }) => {
    errors = [];
    context.on("page", (page) =>
        page.on("pageerror", (error) => errors.push(error.message)),
    );
});
test.afterEach(() => expect(errors).toEqual([]));

test("a shopper's suggestion reaches the team's review list and its outcome is shown back", async ({
    page,
    browser,
}) => {
    // The account and its suggestion are created in the development database and deleted at the end.
    const stamp = Date.now();
    const email = `browser-test-${stamp}@example.com`;
    const password = "browser-test-password";
    const allergenName = `Test allergen ${stamp}`;

    await page.goto("/scan");
    await page.getByLabel("Product barcode").fill("NOT-IN-CATALOG");
    await page.getByRole("button", { name: "Look up product" }).click();
    await page
        .getByRole("link", { name: /Suggest this product to our team/ })
        .click();
    await expect(
        page.getByRole("heading", { name: "Sign in to suggest." }),
    ).toBeVisible();

    await page.goto("/register");
    await page.getByLabel("Name").fill("Suggestion Test");
    await page.getByLabel("Email address").fill(email);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByLabel("Confirm password").fill(password);
    await page.getByRole("checkbox", { name: /I agree/ }).check();
    await page
        .getByRole("button", { name: "Create account", exact: true })
        .click();

    await page.goto("/suggest?barcode=NOT-IN-CATALOG");
    const productForm = page.locator("#suggest-product");
    await expect(productForm.getByLabel("Product barcode")).toHaveValue(
        "NOT-IN-CATALOG",
    );
    const allergenForm = page.locator("#suggest-allergen");
    await allergenForm.getByLabel("English name").fill(allergenName);
    await allergenForm
        .getByRole("button", { name: "Send allergen suggestion" })
        .click();
    await expect(page.getByRole("status").first()).toContainText(
        "Our team will review your suggestion.",
    );
    const mine = page.locator(".my-suggestion", { hasText: allergenName });
    await expect(mine).toContainText("Waiting for review");

    // The team sees it as soon as they sign in.
    const teamContext = await browser.newContext();
    const team = await teamContext.newPage();
    team.on("pageerror", (error) => errors.push(error.message));
    await team.goto("/language/en");
    await team.goto("/login");
    await team.getByLabel("Email address").fill("admin@allerscan.test");
    await team.getByLabel("Password").fill("AllerScanDemo2027!");
    await team.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(team.getByText(/suggestions? waiting for review/)).toBeVisible();
    await team.getByRole("link", { name: /Review now/ }).click();
    const row = team.locator(".suggestion-row", { hasText: allergenName });
    await expect(row).toContainText("From Suggestion Test");
    team.on("dialog", (dialog) => dialog.accept());
    await row.getByRole("button", { name: "Dismiss" }).click();
    await expect(team.getByRole("status")).toContainText(
        "Suggestion dismissed.",
    );
    await expect(row).toHaveCount(0);
    await teamContext.close();

    await page.reload();
    await expect(mine).toContainText("Not added");

    // Phone width: the suggestion page fits without sideways scrolling.
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);

    await page.goto("/settings");
    await page.getByText("Delete my account").first().click();
    await page.getByLabel("Enter your password to confirm").fill(password);
    await page.getByRole("button", { name: "Delete my account" }).click();
    await expect(page.getByRole("status").first()).toContainText(
        "Your account and its saved information were deleted.",
    );
});
