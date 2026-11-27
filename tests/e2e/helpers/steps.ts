import { test as base, expect, Locator, TestInfo } from '@playwright/test';
import { OrderRef, readState } from './state';

/**
 * Stub of the Packeta widget library (`/v6/www/js/library.js`). The tests check the plugin callback, not the widget.
 * The stub gives the callback the point from `window.__packetaNextPoint`.
 */
const WIDGET_STUB = `
window.Packeta = { Widget: { pick: function (apiKey, callback, options) {
    var point = window.__packetaNextPoint || null;
    setTimeout(function () { callback(point); }, 0);
} } };
`;

/** Name of the API password field in the module configuration. */
export const API_PASS_FIELD = 'zasilkovna_api_pass';
/** Page function that returns the API password that maskApiPass() removed from the field. */
export const API_PASS_READER = '__packetaApiPass';

/**
 * Runs in each page before the page scripts. It removes the API password from the field of the module configuration.
 * Why: a failed locator matcher attaches an aria snapshot of the page to its error (error-context.md, HTML report).
 * The aria snapshot contains the value of each text input, also of an input with the type "password"
 * (playwright-core 1.61, generateAriaTree). The log of a locator also shows the `value` attribute of the element.
 * Thus, the field must have no value while the tests use the page.
 * The `formdata` event puts the password back into the submitted form data, so that a save of the configuration keeps it.
 * The event fires for form.submit() and for a click on a submit button. Thus, the way that Joomla.submitbutton submits the form is not important.
 */
function maskApiPass({ field, reader }: { field: string; reader: string }): void {
    let password: string | null = null;
    const mask = () => {
        const input = document.querySelector<HTMLInputElement>(`input[name="${field}"]`);
        if (!input || password !== null) return;
        password = input.value;
        input.removeAttribute('value');
        input.value = '';
        Object.defineProperty(window, reader, { value: () => password, enumerable: false });
        input.form?.addEventListener('formdata', (event) => {
            // A value that a test typed into the field wins over the removed password.
            // Limit: a test cannot save an empty password. An empty field sends the removed password.
            if (input.value === '') event.formData.set(field, password ?? '');
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mask);
    else mask();
}

/**
 * The browser reaches only the stack host. All other hosts (Packeta API and widget too) are blocked.
 * The widget library gets the stub. Each page gets maskApiPass().
 */
export const test = base.extend<{ networkGuard: void }>({
    networkGuard: [
        async ({ context, baseURL }, use) => {
            await context.addInitScript(maskApiPass, { field: API_PASS_FIELD, reader: API_PASS_READER });
            const ownHost = new URL(baseURL as string).hostname;
            await context.route('**/*', (route) => {
                const url = new URL(route.request().url());
                if (/\/v6\/www\/js\/library\.js$/.test(url.pathname)) {
                    return route.fulfill({ status: 200, contentType: 'application/javascript', body: WIDGET_STUB });
                }
                return url.hostname === ownHost ? route.continue() : route.abort();
            });
            await use();
        },
        { auto: true },
    ],
});
export { expect };

/**
 * Sets the value of a password field.
 * Why not locator.fill(): Playwright writes the value into the step title (`Fill "<value>"`).
 * The step title goes to the HTML report, to error-context.md and to the list output.
 */
export async function fillSecret(locator: Locator, value: string): Promise<void> {
    await locator.evaluate((el, v) => {
        (el as HTMLInputElement).value = v;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }, value);
}

/** Error that skips the current `softStep`. The step shows as skipped, with the reason. */
class StepSkip extends Error {}

export function skipStep(reason: string): never {
    throw new StepSkip(reason);
}

/** Returns the order of an earlier test of this run, or undefined when the order does not exist. */
export function findOrder(key: string): OrderRef | undefined {
    const order = readState().orders[key];
    return order?.id ? order : undefined;
}

export function missingOrderReason(key: string): string {
    return `The order "${key}" does not exist in this run. The test "Orders" creates it. Run the whole file.`;
}

/**
 * Returns the order of an earlier test of this run.
 * When the order does not exist, the current `softStep` is skipped with the reason.
 * Example: a `-g` run without the test "Orders", or a failed order step.
 */
export function requireOrder(key: string): OrderRef {
    const order = findOrder(key);
    if (!order) skipStep(missingOrderReason(key));
    return order;
}

/** Soft steps of each test: how many ran and the skip reasons. */
const stepCounts = new Map<string, { ran: number; skipped: string[] }>();

function countsOf(info: TestInfo): { ran: number; skipped: string[] } {
    let counts = stepCounts.get(info.testId);
    if (!counts) {
        counts = { ran: 0, skipped: [] };
        stepCounts.set(info.testId, counts);
    }
    return counts;
}

/**
 * Short failure text for the soft assertion of softStep().
 * The soft assertion points to this file. Thus, the text adds the first stack frame in a spec file.
 * The error can be a value that is not an Error.
 */
function failureText(error: unknown): string {
    const message = ((error as Error)?.message ?? String(error)).split('\n').slice(0, 4).join(' ');
    const specFrame = String((error as Error)?.stack ?? '').split('\n').find((line) => /\.spec\.ts:\d+/.test(line))?.trim();
    return specFrame ? `${message} [${specFrame}]` : message;
}

/**
 * Runs an independent part of a multi-step test. A failure in the part is recorded as a soft failure,
 * and the test continues with the next part. Checks inside the part that depend on each other stay hard.
 *
 * A step that does not run must not look passed:
 * - `skipReason` set: the step does not run and shows as skipped.
 * - `skipStep()` or `requireOrder()` in the body: the step stops and shows as skipped.
 * Each skip also adds a test annotation "skipped step" with the reason (the list reporter does not show skipped steps).
 * When all steps of a test skip, skipWhenAllStepsSkipped() marks the whole test as skipped.
 */
export async function softStep(title: string, body: () => Promise<void>, skipReason = ''): Promise<void> {
    let failure = '';
    const counts = countsOf(test.info());
    await test.step(title, async (step) => {
        let skip = skipReason;
        if (!skip) {
            try {
                await body();
            } catch (error) {
                if (error instanceof StepSkip) skip = error.message;
                else failure = failureText(error);
            }
        }
        if (skip) {
            counts.skipped.push(`${title}: ${skip}`);
            test.info().annotations.push({ type: 'skipped step', description: `${title}: ${skip}` });
            step.skip(true, skip);
        } else {
            counts.ran++;
        }
    });
    expect.soft(failure, `step "${title}"`).toBe('');
}

/**
 * Use it in `test.afterEach`. A test in which every soft step skipped is not green: it shows as skipped.
 * A test with a failure stays failed (Playwright changes only a passed test to skipped).
 */
export function skipWhenAllStepsSkipped(info: TestInfo): void {
    const counts = stepCounts.get(info.testId);
    stepCounts.delete(info.testId);
    if (!counts || counts.ran > 0 || counts.skipped.length === 0) return;
    info.skip(true, `All steps skipped. ${counts.skipped.join(' | ')}`);
}
