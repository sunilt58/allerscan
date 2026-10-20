import { defineConfig } from "@playwright/test";
import { readFileSync } from "node:fs";

// The team account's password comes from the local .env (ADMIN_PASSWORD), so it isn't written in the tests.
try {
    const match = readFileSync(".env", "utf8").match(/^ADMIN_PASSWORD=(.*)$/m);
    process.env.ADMIN_PASSWORD ??= match?.[1].trim().replace(/^["']|["']$/g, "");
} catch {
    /* Without .env the default from .env.example is used. */
}

const baseURL = process.env.PLAYWRIGHT_BASE_URL || "https://allerscan.test";

export default defineConfig({
    testDir: "./tests/browser",
    timeout: 30000,
    workers: 1,
    use: {
        baseURL,
        // Existing checks assert English copy; Japanese (the default) is covered in language.spec.js.
        storageState: {
            cookies: [
                {
                    name: "locale",
                    value: "en",
                    domain: new URL(baseURL).hostname,
                    path: "/",
                    expires: -1,
                    httpOnly: false,
                    secure: false,
                    sameSite: "Lax",
                },
            ],
            origins: [],
        },
        browserName: "chromium",
        channel: "chrome",
        viewport: { width: 1440, height: 1000 },
        trace: "retain-on-failure",
    },
});
