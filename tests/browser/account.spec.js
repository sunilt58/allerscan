import { test, expect } from "@playwright/test";

let errors;
test.beforeEach(async ({ context }) => {
    errors = [];
    context.on("page", (page) =>
        page.on("pageerror", (error) => errors.push(error.message)),
    );
});
test.afterEach(() => expect(errors).toEqual([]));

const storedPreferences = (page) =>
    page.evaluate(() =>
        JSON.parse(
            localStorage.getItem("allerscan-display-preferences") || "{}",
        ),
    );

test("an account carries allergens and saved products to another device, and deleting it removes them", async ({
    page,
    browser,
}) => {
    // The account is created in the development database and deleted again at the end of this test.
    const email = `browser-test-${Date.now()}@example.com`;
    const password = "browser-test-password";

    // Choices made before signing up join the new account.
    await page.goto("/settings");
    await page.getByRole("checkbox", { name: "Milk", exact: false }).check();
    await page.goto("/scan/lookup?barcode=DEMO002");
    await page
        .getByRole("button", { name: "Save product", exact: true })
        .click();

    await page.goto("/register");
    await page.getByLabel("Name").fill("Browser Test");
    await page.getByLabel("Email address").fill(email);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByLabel("Confirm password").fill(password);
    await page.getByRole("checkbox", { name: /I agree/ }).check();
    const merged = page.waitForResponse(
        (response) =>
            response.url().endsWith("/account/preferences") && response.ok(),
    );
    await page
        .getByRole("button", { name: "Create account", exact: true })
        .click();
    await expect(page.getByRole("status").first()).toContainText(
        "Your account is ready",
    );
    await merged;
    await expect(page.getByText(`Signed in as Browser Test (${email}).`))
        .toBeVisible();

    // A change made while signed in is saved to the account.
    const synced = page.waitForResponse(
        (response) =>
            response.url().endsWith("/account/preferences") && response.ok(),
    );
    await page.getByRole("checkbox", { name: "Egg", exact: false }).check();
    await synced;
    await expect(page.locator("[data-preferences-status]")).toHaveText(
        "Saved to your account",
    );

    // Signing out clears this browser but not the account.
    await page
        .locator("#account")
        .getByRole("button", { name: "Sign out" })
        .click();
    await expect(page).toHaveURL(/\/login$/);
    const afterSignOut = await storedPreferences(page);
    expect(afterSignOut.allergens).toBeUndefined();
    expect(afterSignOut.savedProducts).toBeUndefined();

    // A second device starts empty and receives the account's lists after signing in.
    const secondDevice = await browser.newContext();
    const phone = await secondDevice.newPage();
    phone.on("pageerror", (error) => errors.push(error.message));
    await phone.goto("/language/en");
    await phone.goto("/login");
    await phone.getByLabel("Email address").fill(email);
    await phone.getByLabel("Password").fill(password);
    await phone.getByRole("button", { name: "Sign in", exact: true }).click();
    await phone.goto("/settings");
    await expect(
        phone.getByRole("checkbox", { name: "Milk", exact: false }),
    ).toBeChecked();
    await expect(
        phone.getByRole("checkbox", { name: "Egg", exact: false }),
    ).toBeChecked();
    await phone.goto("/saved");
    await expect(phone.locator("[data-product-card]")).toHaveCount(1);
    await expect(phone.locator("[data-product-card]")).toContainText(
        "Soft white bread",
    );
    await expect(phone.locator("[data-card-match]")).toHaveText(
        "2 of your selected allergens listed",
    );

    // Deleting the account needs the password and leaves nothing to sign in to.
    await phone.goto("/settings");
    await phone.getByText("Delete my account").first().click();
    await phone.getByLabel("Enter your password to confirm").fill("wrong");
    await phone.getByRole("button", { name: "Delete my account" }).click();
    await expect(phone.locator("#account").getByRole("alert")).toBeVisible();
    await phone.getByLabel("Enter your password to confirm").fill(password);
    await phone.getByRole("button", { name: "Delete my account" }).click();
    await expect(phone.getByRole("status").first()).toContainText(
        "Your account and its saved information were deleted.",
    );
    expect((await storedPreferences(phone)).allergens).toBeUndefined();
    await phone.goto("/login");
    await phone.getByLabel("Email address").fill(email);
    await phone.getByLabel("Password").fill(password);
    await phone.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(phone.getByRole("alert")).toContainText(
        "Incorrect email or password.",
    );
    await secondDevice.close();
});
