import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for the E2E tests of the Packeta VirtueMart plugin.
 *
 * The suite runs against one Joomla + VirtueMart stack. Set the stack URL in VIRTUEMART_BASE_URL.
 * The tests change the stack (they create orders), so they run in one worker.
 */
const baseURL = process.env.VIRTUEMART_BASE_URL;
if (!baseURL) {
    throw new Error('Set VIRTUEMART_BASE_URL, for example http://localhost:8080');
}
// The module configuration page shows the API password as the value of a text field.
// An aria snapshot of a page contains the value of each text field, also of a field with the type "password".
// What protects the password:
// - The init script maskApiPass() (helpers/steps.ts) removes the password from the field in each page.
//   Thus, the aria snapshot that a failed locator matcher attaches to its error (error-context.md, HTML report) has no password.
// - This variable stops the page snapshot that Playwright takes when a test fails without its own error context
//   (node_modules/playwright/lib/index.js, _takePageSnapshot).
// - Traces and screenshots are off (see `use`).
// What does not protect the password: the type "password" of the field.
// The password is still in the server response. A HAR file or a network log has it. The tests write neither.
// Check this again when you upgrade Playwright.
process.env.PLAYWRIGHT_NO_COPY_PROMPT ??= '1';
// One run ID for the whole run. A worker that starts again after a failed test gets the same value
// (main-flows.spec.ts keeps its orders in .state/main-flows.json under this ID).
process.env.PACKETA_E2E_RUN_ID ??= Date.now().toString(36);

export default defineConfig({
    testDir: '.',
    testMatch: ['*.spec.ts'],
    timeout: 120_000,
    expect: { timeout: 15_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    forbidOnly: !!process.env.CI,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL,
        // Traces and screenshots are always off. The module configuration page shows the API password.
        // A trace or a screenshot of that page writes the password to a file.
        trace: 'off',
        screenshot: 'off',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
