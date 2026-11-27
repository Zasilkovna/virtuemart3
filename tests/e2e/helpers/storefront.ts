import { Locator, Page, Response } from '@playwright/test';
import { findOrderId, loginToAdmin, withPageLoad } from './admin';
import { ENV, PATHS } from './env';
import { OrderRef, readState } from './state';
import { expect, fillSecret } from './steps';

type WidgetPoint = { id: string; nameStreet: string; zip: string; currency: string; country: string };

/** Text that the plugin stores and shows for a point (widget.js: nameStreet + ", " + zip). */
export function pointLabel(point: WidgetPoint): string {
    return `${point.nameStreet}, ${point.zip}`;
}

/** The widget point of one order, with the order key and the run ID in its name. */
export function testPoint(key: string, id: string, name: string): WidgetPoint {
    return { id, nameStreet: `${name} ${key} ${readState().runId}`, zip: '110 00', currency: 'CZK', country: 'cz' };
}

export async function loginToStorefront(page: Page): Promise<void> {
    if (!ENV.storePass) throw new Error('Set VM_STORE_PASS or VM_ADMIN_PASS (the password of the storefront customer).');
    await page.goto('/index.php?option=com_users&view=login');
    // A logged-in customer gets a logout form (com_users: task in the action URL, mod_login: hidden task field).
    const logout = page.locator('form[action*="user.logout"], form input[name="task"][value="user.logout"]');
    if ((await logout.count()) > 0) return;
    const form = page.locator('form.mod-login');
    if ((await form.locator('input[name="username"]').count()) === 0) {
        // The tests do not continue as a guest. The cart of a guest and the order detail of a guest (order password) work differently.
        throw new Error('The storefront has no Joomla login module (form.mod-login). Publish the login module on the storefront.');
    }
    await form.locator('input[name="username"]').fill(ENV.storeUser);
    await fillSecret(form.locator('input[name="password"]'), ENV.storePass);
    await form.locator('button[type="submit"]').first().click();
    await expect(page.locator('form.mod-login input[name="username"]'), 'Login failed. Check VM_STORE_USER and VM_STORE_PASS.').toHaveCount(0);
}

/** VirtueMart keeps the cart of a registered customer between sessions. Thus, each order starts with an empty cart. */
async function emptyCart(page: Page): Promise<void> {
    await page.goto(PATHS.cart);
    for (let i = 0; i < 10; i++) {
        const remove = page.locator('button[name^="delete."], input[name^="delete."]').first();
        if ((await remove.count()) === 0) return;
        await remove.click();
        await page.waitForLoadState('networkidle');
    }
}

/** Saves the billing address with the given country. It fills the other fields only when they are empty. */
export async function setBillingCountry(page: Page, countryId: string): Promise<void> {
    await page.goto(PATHS.billingAddress);
    await expect(page.locator('#first_name_field'), 'the billing address form').toBeVisible();
    const fillIfEmpty = async (selector: string, value: string) => {
        const field = page.locator(selector);
        if ((await field.count()) > 0 && (await field.inputValue()) === '') await field.fill(value);
    };
    await fillIfEmpty('#first_name_field', 'E2E');
    await fillIfEmpty('#last_name_field', 'Customer');
    await fillIfEmpty('#address_1_field', 'Testovací 5');
    await fillIfEmpty('#zip_field', '11000');
    await fillIfEmpty('#city_field', 'Praha');
    await fillIfEmpty('#email_field', 'customer@localhost.local');
    await fillIfEmpty('#phone_1_field', '+420777123456');
    // Chosen hides the select, so `force` is necessary.
    await page.selectOption('#virtuemart_country_id_field', countryId, { force: true });
    // Wait for the page load after the POST. A goto before it can stop the POST.
    await withPageLoad(page, 'Billing address save', () => page.getByRole('button', { name: /^\s*save\s*$|uložit/i }).first().click());
    // Read the saved country back. A failed save must not go unnoticed.
    await page.goto(PATHS.billingAddress);
    await expect(page.locator('#virtuemart_country_id_field'), 'the saved billing country').toHaveValue(countryId);
}

export async function openCartWithProduct(page: Page, countryId = ENV.countryCz): Promise<void> {
    await loginToStorefront(page);
    await emptyCart(page);
    await setBillingCountry(page, countryId);
    await page.goto(PATHS.product(ENV.productId));
    await page.getByRole('button', { name: /add to cart|do košíku/i }).first().click();
    await page.waitForLoadState('networkidle');
    await page.goto(PATHS.cart);
}

export function shipmentRadio(page: Page, methodId: string): Locator {
    return page.locator(`input[name="virtuemart_shipmentmethod_id"][value="${methodId}"]`);
}

/** The block that the plugin renders around its shipment method. */
export function packetaBox(page: Page, methodId: string): Locator {
    return page.locator('.zasilkovna_box', { has: shipmentRadio(page, methodId) });
}

/** Maximum time for the cart answer after a click on a cart input. */
const CART_UPDATE_TIMEOUT = 15_000;

/** Link in the plugin box that opens the Packeta widget. */
export const WIDGET_LINK = '.open-packeta-widget';

/** Clicks a cart input. An input with `data-dynamic-update` posts the cart form, so the click waits for that answer. */
async function clickWithCartUpdate(page: Page, input: Locator): Promise<void> {
    if ((await input.getAttribute('data-dynamic-update')) !== '1') {
        await input.click({ force: true });
        await page.waitForLoadState('networkidle');
        return;
    }
    await Promise.all([
        page.waitForResponse((r) => r.url().includes('task=checkout') && r.request().method() === 'POST', { timeout: CART_UPDATE_TIMEOUT }),
        input.click({ force: true }),
    ]);
    await page.waitForLoadState('networkidle');
}

export async function selectShipment(page: Page, methodId: string): Promise<void> {
    const radio = shipmentRadio(page, methodId);
    await expect(radio, `the cart offers shipment method ${methodId}`).toHaveCount(1);
    if (!(await radio.isChecked())) await clickWithCartUpdate(page, radio);
    await expect(radio).toBeChecked();
}

async function selectPayment(page: Page, paymentId?: string): Promise<void> {
    const radio = paymentId
        ? page.locator(`input[name="virtuemart_paymentmethod_id"][value="${paymentId}"]`)
        : page.locator('input[name="virtuemart_paymentmethod_id"]').first();
    if ((await radio.count()) === 0) {
        expect(paymentId, `the cart offers payment method ${paymentId}`).toBeUndefined();
        return;
    }
    if (!(await radio.isChecked())) await clickWithCartUpdate(page, radio);
    await expect(radio).toBeChecked();
}

/** Opens the widget from the plugin box. The widget stub calls the plugin callback, which saves the point (AJAX). */
export async function pickPointInWidget(page: Page, methodId: string, point: WidgetPoint): Promise<void> {
    const box = packetaBox(page, methodId);
    const open = box.locator(WIDGET_LINK);
    await expect(open, 'the plugin shows the "choose a point" link').toBeVisible();
    await page.evaluate((p) => ((window as any).__packetaNextPoint = p), point);
    const [response] = await Promise.all([page.waitForResponse((r) => r.url().includes(PATHS.savePointQuery)), open.click()]);
    expect(response.ok(), 'saveSelectedPoint answers 200').toBeTruthy();
    const body = await response.text();
    expect(body).not.toMatch(/Warning|Fatal error|Notice|Deprecated/i);
    expect(JSON.parse(body)?.data?.status).toBe('ok');
    await expect(box.locator('.picked-delivery-place')).toContainText(point.nameStreet);
}

/** The number of the last order that createOrder made in this worker. */
let lastOrderNumber = '';

/**
 * Accepts the terms (when the shop has them) and confirms the order. Returns true when the shop made the order.
 * The cart AJAX (`task=checkout`) replaces the cart form after its response, and then the page sends one or more
 * `viewJS` requests. Their number changes. Thus, no single response tells that the form is ready.
 * A click on the old form sends an old cart hash. Then VirtueMart (`confirmDone`) makes no order and redirects (303)
 * to the cart. The cart can show the "orderdone" layout of the previous order.
 * Thus, the function loads the cart again before each click. The new form has the current cart hash.
 * Only a POST `task=orderdone` with status 200 is a success. A redirect is not a success.
 */
async function confirmOrder(page: Page): Promise<boolean> {
    const orderDone: Response[] = [];
    const onResponse = (r: Response) => {
        if (r.request().method() === 'POST' && r.url().includes('task=orderdone')) orderDone.push(r);
    };
    page.on('response', onResponse);
    try {
        for (let attempt = 0; attempt < 3; attempt++) {
            await page.goto(PATHS.cart);
            const tos = page.locator('input[name="tos"][type="checkbox"]');
            if ((await tos.count()) > 0 && !(await tos.first().isChecked())) {
                const dynamic = (await tos.first().getAttribute('data-dynamic-update')) === '1';
                await clickWithCartUpdate(page, tos.first());
                // The AJAX saved the terms in the cart. Load the cart again to get the new form.
                if (dynamic) await page.goto(PATHS.cart);
                await expect(tos.first(), 'the terms are accepted').toBeChecked();
            }
            const confirm = page.locator('#checkoutFormSubmit, button[name="confirm"], button[name="checkout"]').first();
            if ((await confirm.count()) === 0) return false;
            await confirm.click();
            await page.waitForLoadState('networkidle');
            if (orderDone.some((r) => r.status() === 200)) return true;
        }
        return false;
    } finally {
        page.off('response', onResponse);
    }
}

type OrderPlan = {
    shipmentId: string;
    paymentId?: string;
    point?: WidgetPoint;
    /** Billing country of the order. Default: ENV.countryCz. */
    countryId?: string;
    onCart?: (page: Page) => Promise<void>;
};

/** Creates one order through the storefront and finds its admin ID. */
export async function createOrder(page: Page, plan: OrderPlan): Promise<OrderRef> {
    await openCartWithProduct(page, plan.countryId ?? ENV.countryCz);
    await selectShipment(page, plan.shipmentId);
    if (plan.onCart) await plan.onCart(page);
    if (plan.point) await pickPointInWidget(page, plan.shipmentId, plan.point);
    await selectPayment(page, plan.paymentId);
    if (plan.point) {
        await page.goto(PATHS.cart);
        await expect(page.locator('body'), 'the cart keeps the picked point').toContainText(plan.point.nameStreet);
    }
    expect(await confirmOrder(page), 'the order confirmation shows').toBe(true);
    const text = await page.locator('body').innerText();
    const number = text.match(/(?:order number|číslo objednávky)\s*:?\s*([A-Za-z0-9-]{4,})/i)?.[1] ?? '';
    expect(number, 'order number on the confirmation page').not.toBe('');
    // An old order number means that the page shows the previous order and the shop made no new order.
    const known = [lastOrderNumber, ...Object.values(readState().orders).map((o) => o.number)].filter((n) => n !== '');
    expect(known, `the confirmation page shows a new order number, not the old number ${number}`).not.toContain(number);
    lastOrderNumber = number;
    await loginToAdmin(page);
    return { number, id: await findOrderId(page, number) };
}
