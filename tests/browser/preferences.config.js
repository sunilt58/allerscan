import { defineConfig } from "@playwright/test";
import base from "../../playwright.config.js";

// These preference checks use the existing Herd site and change browser-local settings only.
export default defineConfig({
    ...base,
    testDir: ".",
    testMatch: "preferences.spec.js",
    webServer: undefined,
    use: {
        ...base.use,
        baseURL: process.env.PLAYWRIGHT_BASE_URL || "https://allerscan_demo.test",
    },
});
