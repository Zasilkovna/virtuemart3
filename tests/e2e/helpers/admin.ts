import { Locator, Page } from '@playwright/test';
import { ENV, PATHS } from './env';
import { API_PASS_FIELD, API_PASS_READER, expect, fillSecret } from './steps';

const ERROR_TEXT = /error|chyba|fatal|exception/i;

const MESSAGES = '#system-message-container';
const ERROR_ALERTS = `${MESSAGES} joomla-alert[type="danger"]`;
export const WARNING_ALERTS = `${MESSAGES} joomla-alert[type="warning"]`;
/** Joomla shows the message type "message" as the alert type "success". */
export const SUCCESS_ALERTS = `${MESSAGES} joomla-alert[type="success"]`;

const MODULE_STATUS = 'HTTP status of the Packeta admin (view=zasilkovna)';

export async function loginToAdmin(page: Page): Promise<void> {
    if (!ENV.adminPass) throw new Error('Set VM_ADMIN_PASS (the password of the Joomla administrator VM_ADMIN_USER).');
    // The shipment method list, not the dashboard: a dashboard module of Joomla can fail on some stacks.
    await page.goto(PATHS.shipmentMethods);
    if ((await page.locator('input[name="passwd"]').count()) === 0) return;
    await page.locator('input[name="username"]').fill(ENV.adminUser);
    await fillSecret(page.locator('input[name="passwd"]'), ENV.adminPass);
    // The submit button of the login form, not its label: the label depends on the admin language.
    await page.locator('form#form-login button[type="submit"]').click();
    await expect(page.locator('input[name="passwd"]'), 'Login failed. Check VM_ADMIN_USER and VM_ADMIN_PASS.').toHaveCount(0);
}

/** Text of the Joomla message container, on one line. */
export async function messageText(page: Page): Promise<string> {
    const container = page.locator(MESSAGES);
    if ((await container.count()) === 0) return '';
    return (await container.innerText()).replace(/\s+/g, ' ').trim();
}

/**
 * No error and no warning in the Joomla message container.
 * `allowedWarning` ignores the warning messages that it matches. All other warnings and all errors still fail.
 */
export async function expectNoErrorMessage(page: Page, allowedWarning?: RegExp): Promise<void> {
    await expect(page.locator(ERROR_ALERTS), 'Joomla error messages').toHaveCount(0);
    if (allowedWarning) {
        // One Joomla alert can hold more messages of the same type, so the check uses each message, not the alert.
        const texts = await page.locator(WARNING_ALERTS).evaluateAll((alerts) => alerts.flatMap((alert) => {
            const messages = Array.from(alert.querySelectorAll('.alert-message'));
            return (messages.length > 0 ? messages : [alert]).map((el) => (el.textContent ?? '').replace(/\s+/g, ' ').trim());
        }));
        expect(texts.filter((text) => !allowedWarning.test(text)), 'Joomla warning messages').toEqual([]);
    } else {
        await expect(page.locator(WARNING_ALERTS), `Joomla warning messages: ${await messageText(page)}`).toHaveCount(0);
    }
    expect(await messageText(page), 'Joomla message container').not.toMatch(ERROR_TEXT);
}

/** Maximum time for the page load after a form submit. */
const SUBMIT_TIMEOUT = 30_000;
/** Maximum time for the AJAX requests of the new page after its load. */
const NETWORK_IDLE_TIMEOUT = 15_000;

/**
 * Runs the action and waits for the next page load of the main frame.
 * When no page load starts in `timeout`, it fails with a clear message.
 * Example: a form validation of Joomla blocks the submit.
 */
export async function withPageLoad(page: Page, what: string, action: () => Promise<unknown>, timeout = SUBMIT_TIMEOUT): Promise<void> {
    const navigation = page
        .waitForEvent('framenavigated', { predicate: (frame) => frame === page.mainFrame(), timeout })
        .catch((error: Error) => {
            // Keep the original message: the cause can also be a closed page, not only a blocked submit.
            throw new Error(`${what}: no page load in ${timeout / 1000} s. Something blocked the form submit `
                + `(for example a form validation), or the page closed. Cause: ${error.message}`);
        });
    await Promise.all([navigation, action()]);
    await page.waitForLoadState('load', { timeout });
    await page.waitForLoadState('networkidle', { timeout: NETWORK_IDLE_TIMEOUT });
}

/** Runs a toolbar task (`Joomla.submitbutton`) and waits for the next page. */
export async function submitTask(page: Page, task: string, timeout = SUBMIT_TIMEOUT): Promise<void> {
    await withPageLoad(page, `Task "${task}"`, () => page.evaluate((t) => (window as any).Joomla.submitbutton(t), task), timeout);
}

export async function findOrderId(page: Page, orderNumber: string): Promise<string> {
    await page.goto(`${PATHS.adminOrders}&search=${encodeURIComponent(orderNumber)}`);
    const href = await page.getByRole('link', { name: orderNumber, exact: true }).first().getAttribute('href');
    const id = href?.match(/virtuemart_order_id=(\d+)/)?.[1] ?? '';
    expect(id, 'link to the admin order detail').not.toBe('');
    return id;
}

export async function openOrderDetail(page: Page, orderId: string): Promise<void> {
    await page.goto(PATHS.adminOrder(orderId));
    await expect(page.locator('body')).not.toContainText(/Fatal error|Warning:/);
}

export const MODULE_TABS = '.uk-tab a, .nav-tabs a, joomla-tab button[role="tab"]';
export const ORDERS_TAB = /^\s*(Orders|Objednávky)\s*$/;
export const CONFIG_TAB = /^\s*(Configuration|Konfigurace)\s*$/;
export const CARRIERS_TAB = /^\s*(Carriers list|Seznam dopravců)\s*$/;

export async function openModuleTab(page: Page, tab: RegExp, query = ''): Promise<void> {
    const response = await page.goto(`${PATHS.module}${query}`);
    expect(response?.status(), MODULE_STATUS).toBeLessThan(500);
    await page.locator(MODULE_TABS).filter({ hasText: tab }).first().click();
}

/**
 * Sets form values by field name, also for fields that `showon` or Chosen hide.
 * radio: checks the option with the value; checkbox: string[] = checked values, boolean = state; others: value + `change`.
 */
export async function setFormValues(page: Page, values: Record<string, string | boolean | string[]>): Promise<void> {
    const missing = await page.evaluate((vals) => {
        const notFound: string[] = [];
        for (const [name, value] of Object.entries(vals)) {
            const fields = Array.from(document.querySelectorAll<HTMLInputElement>(`[name="${name}"]`));
            if (fields.length === 0) {
                notFound.push(name);
                continue;
            }
            for (const field of fields) {
                if (field.type === 'radio') field.checked = field.value === String(value);
                else if (field.type === 'checkbox') field.checked = Array.isArray(value) ? value.includes(field.value) : value === true || value === field.value;
                else if (field.type === 'hidden' && fields.length > 1) continue;
                else (field as any).value = String(value);
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        return notFound;
    }, values);
    expect(missing, 'form fields that the page does not have').toEqual([]);
}

/** Sets the form values, saves the edit form (task "apply") and checks that Joomla shows no error. */
export async function saveForm(page: Page, values: Record<string, string | boolean | string[]>, allowedWarning?: RegExp): Promise<void> {
    await setFormValues(page, values);
    await submitTask(page, 'apply');
    await expectNoErrorMessage(page, allowedWarning);
}

/**
 * Saves the module configuration with its "Save" button in the toolbar.
 * Why not submitTask('apply'): the button runs validateForm() (media/media/js/admin.js) before the submit. A direct task skips this validation.
 */
export async function saveModuleConfig(page: Page): Promise<void> {
    const save = page.locator('button.button-apply[onclick*="validateForm"]');
    await expect(save, 'the "Save" button of the module configuration').toHaveCount(1);
    await withPageLoad(page, 'Module configuration save', () => save.click());
    await expectNoErrorMessage(page);
}

export async function updateModuleConfig(page: Page, values: Record<string, string | boolean | string[]>): Promise<void> {
    await openModuleTab(page, CONFIG_TAB);
    await setFormValues(page, values);
    await saveModuleConfig(page);
    await openModuleTab(page, CONFIG_TAB);
}

/**
 * API password in the module configuration.
 * The init script maskApiPass() (steps.ts) removes the password from the field and keeps it in a page function.
 * Thus, the function reads it only with page.evaluate().
 * Do not put the value into an assertion, an assertion message or a log.
 */
async function readApiPass(page: Page): Promise<string> {
    await openModuleTab(page, CONFIG_TAB);
    const field = await page.evaluate(({ name, reader }) => {
        const read = (window as any)[reader] as (() => string | null) | undefined;
        return { found: document.querySelector(`input[name="${name}"]`) !== null, value: read?.() ?? null };
    }, { name: API_PASS_FIELD, reader: API_PASS_READER });
    if (!field.found) throw new Error(`The module configuration has no API password field (${API_PASS_FIELD}).`);
    if (field.value === null) throw new Error('The init script did not remove the API password from the field (maskApiPass in steps.ts).');
    return field.value;
}

/** The plugin accepts only a password with 32 characters. This check does not call the Packeta API. */
export async function apiPassHasValidLength(page: Page): Promise<boolean> {
    return (await readApiPass(page)).length === 32;
}

/**
 * Skip reason of a step that calls the Packeta API. Empty = the step can run.
 * The step runs only when PACKETA_STAGE_API_PASS is the API password in the module configuration.
 * The function does not check the API target of the plugin.
 * Precondition: the plugin points to the Packeta stage API before you set PACKETA_STAGE_API_PASS.
 */
export async function packetaApiSkipReason(page: Page): Promise<string> {
    const prefix = 'The step calls the Packeta API.';
    if (!ENV.stageApiPass) return `${prefix} It runs only when PACKETA_STAGE_API_PASS is set to the API password of a Packeta stage account.`;
    if ((await readApiPass(page)) !== ENV.stageApiPass) {
        return `${prefix} PACKETA_STAGE_API_PASS is not the API password in the module configuration. The step does not run.`;
    }
    return '';
}

/** Payment method IDs in the COD box of the module configuration, with the checked state. */
export async function codFlags(page: Page): Promise<Array<{ id: string; checked: boolean }>> {
    await openModuleTab(page, CONFIG_TAB);
    return page.evaluate(() =>
        Array.from(document.querySelectorAll<HTMLInputElement>('input[type="checkbox"][name^="zasilkovna_payment_method_"]'))
            .map((i) => ({ id: i.name.replace('zasilkovna_payment_method_', ''), checked: i.checked })),
    );
}

export async function publishCountry(page: Page, countryId: string): Promise<void> {
    await loginToAdmin(page);
    await page.goto(PATHS.country(countryId));
    await expect(page.locator('input[name="published"]').first(), `the edit form of VirtueMart country ${countryId}`).toHaveCount(1);
    await saveForm(page, { published: '1' });
}

/**
 * Inner HTML of the element in which the shop shows the shipment name and the plugin data.
 * The plugin renders its data as a table. The innermost table with `text` is that table,
 * and its parent element also contains the shipment name of the shop.
 */
export async function shipmentBlockHtml(page: Page, text: string): Promise<string> {
    const table: Locator = page.locator('table').filter({ hasText: text }).last();
    await expect(table, `the plugin table with "${text}"`).toBeVisible();
    return table.locator('xpath=..').innerHTML();
}

/** Raw markup that must not be in the shipment block: a leaked inline script or style. */
export const LEAKED_MARKUP = /<script|<style/i;
/** Text that must not show in a shipment name: a leaked inline script, style or language key. */
export const DIRTY_TEXT = /<script|<style|function\s*\(|jQuery\(|PLG_VMSHIPMENT_/i;
export const CLEAN_NAME_MESSAGE = 'shipment name without leaked script, style or language key';
