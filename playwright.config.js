import { defineConfig } from "@playwright/test";
export default defineConfig({
    testDir: "./tests/browser",
    timeout: 30000,
    workers: 1,
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || "https://allerscan_demo.test",
        browserName: "chromium",
        channel: "chrome",
        viewport: { width: 1440, height: 1000 },
        trace: "retain-on-failure",
    },
});
