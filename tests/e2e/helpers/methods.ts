import { Page } from '@playwright/test';
import { loginToAdmin, packetaApiSkipReason, saveForm } from './admin';
import { ENV, exactMessage, PATHS } from './env';
import { expect } from './steps';

/**
 * The plugin unpublishes a new shipment method until it is configured and shows this warning.
 * The next save configures the method and publishes it.
 * The text comes from PLG_VMSHIPMENT_PACKETERY_SHIPPING_WARNING (en-GB, cs-CZ).
 */
const UNPUBLISHED_WARNING = exactMessage([
    'Shipping method was unpublished. Check configuration and publish it again.',
    'Způsob dopravy byl zneveřejněn. Zkontrolujte konfiguraci a opět způsob dopravy zveřejněte.',
]);

/** Home-delivery carrier that is valid on Packeta stage. */
const STAGE_HD_CARRIER_ID = '106';

/**
 * Default carrier of a home-delivery method without a carrier in its spec.
 * The stage carrier applies only when the steps that call the Packeta API can run (the same check as these steps).
 * With a stage password that is not the configured password, the stack can have no carrier 106.
 * Empty = the first Czech carrier of the method form.
 */
async function defaultHdCarrierId(page: Page): Promise<string> {
    if (ENV.hdCarrierId) return ENV.hdCarrierId;
    return (await packetaApiSkipReason(page)) === '' ? STAGE_HD_CARRIER_ID : '';
}

export type MethodSpec = {
    name: string;
    shippingType: 'pickuppoints' | 'hdcarriers';
    vendorGroups?: Array<'zpoint' | 'zbox'>;
    cost: number;
    freeShipment?: number;
    maxWeight?: number;
    /** Home-delivery carrier. Empty = ENV.hdCarrierId, or the first Czech carrier. */
    hdCarrierId?: string;
};

/**
 * ID of the method with exactly this name. Empty when the method does not exist.
 * The VirtueMart search is a LIKE search, so the function compares the names exactly.
 * Two methods with the same name make the result unclear. Then the function fails.
 * VirtueMart reads `limit=0` as "use the default page size". Thus, the list uses its maximum page size (1000).
 */
async function findMethodId(page: Page, listPath: string, name: string, idField: string): Promise<string> {
    await page.goto(`${listPath}&search=${encodeURIComponent(name)}&limit=1000`);
    const links = page.getByRole('link', { name, exact: true });
    const hrefs = await links.evaluateAll((els) => els.map((el) => el.getAttribute('href') ?? ''));
    const idPattern = new RegExp(`(?:${idField}|cid)(?:\\[\\]|%5B%5D)?=(\\d+)`);
    const ids = [...new Set(hrefs.map((href) => href.match(idPattern)?.[1] ?? '').filter((id) => id !== ''))];
    expect(ids.length, `methods with the name "${name}" (IDs ${ids.join(', ')}); delete the duplicates`).toBeLessThanOrEqual(1);
    return ids[0] ?? '';
}

async function createMethod(page: Page, listPath: string, nameField: string, pluginField: string, pluginOption: RegExp,
    idField: string, name: string, allowedWarning?: RegExp): Promise<string> {
    await page.goto(`${listPath}&task=add`);
    await page.locator(`input[name="${nameField}"]`).fill(name);
    const plugin = page.locator(`select[name="${pluginField}"]`);
    const value = await plugin.locator('option', { hasText: pluginOption }).first().getAttribute('value');
    expect(value, `plugin option ${pluginOption}`).toBeTruthy();
    await plugin.selectOption(value as string, { force: true });
    await saveForm(page, { published: '1' }, allowedWarning);
    const id = await page.locator(`input[name="${idField}"]`).first().inputValue().catch(() => '');
    expect(id, `${idField} after the first save`).toMatch(/^\d+$/);
    return id;
}

export type ShipmentMethod = { id: string; hdCarrierId?: string; hdCarrierName?: string };

/** Creates or updates a Packeta shipment method. Returns its ID and the home-delivery carrier. */
export async function ensureShipmentMethod(page: Page, spec: MethodSpec): Promise<ShipmentMethod> {
    await loginToAdmin(page);
    let id = await findMethodId(page, PATHS.shipmentMethods, spec.name, 'virtuemart_shipmentmethod_id');
    if (!id) {
        id = await createMethod(page, PATHS.shipmentMethods, 'shipment_name', 'shipment_jplugin_id', /^\s*Packeta\s*$/,
            'virtuemart_shipmentmethod_id', spec.name, UNPUBLISHED_WARNING);
    }
    // Before the method form: the check opens the module configuration.
    const defaultCarrier = spec.shippingType === 'hdcarriers' && !spec.hdCarrierId ? await defaultHdCarrierId(page) : '';
    await page.goto(PATHS.shipmentMethod(id));
    await expect(page.locator('input[name="params[shipment_cost]"]'), 'the plugin configuration of the method').toHaveCount(1);
    const values: Record<string, string | boolean | string[]> = {
        'params[delivery_settings][shipping_type]': spec.shippingType,
        'params[maxWeight]': String(spec.maxWeight ?? 10),
        'params[shipment_cost]': String(spec.cost),
        'params[free_shipment]': spec.freeShipment === undefined ? '' : String(spec.freeShipment),
        // -1 = no tax rule, so the cart shows the configured price.
        'params[tax_id]': '-1',
    };
    let hdCarrierId = '';
    let hdCarrierName = '';
    if (spec.shippingType === 'pickuppoints') {
        const groups = page.locator('input[name="params[delivery_settings][vendor_groups][]"]');
        if ((await groups.count()) > 0) values['params[delivery_settings][vendor_groups][]'] = spec.vendorGroups ?? [];
    } else {
        hdCarrierId = spec.hdCarrierId || defaultCarrier || (await firstCzHdCarrier(page));
        const option = page.locator(`select[name="params[delivery_settings][hd_carrier]"] option[value="${hdCarrierId}"]`);
        await expect(option, `carrier ${hdCarrierId} in the method form (update the carriers, publish the country of the carrier)`).toHaveCount(1);
        values['params[delivery_settings][hd_carrier]'] = hdCarrierId;
        hdCarrierName = ((await option.textContent()) ?? '').replace(/\s+/g, ' ').trim();
    }
    await saveForm(page, { ...values, published: '1' });
    return { id, hdCarrierId: hdCarrierId || undefined, hdCarrierName: hdCarrierName || undefined };
}

async function firstCzHdCarrier(page: Page): Promise<string> {
    const id = await page.evaluate(() => {
        const select = document.querySelector<HTMLSelectElement>('select[name="params[delivery_settings][hd_carrier]"]');
        const options = select ? Array.from(select.options).filter((o) => o.value !== '' && o.value !== '0') : [];
        return (options.find((o) => /\bCZ\b|česk|czech/i.test(o.text)) ?? options[0])?.value ?? '';
    });
    expect(id, 'a home-delivery carrier in the method form (update the carriers first)').not.toBe('');
    return id;
}

async function setShipmentMethodPublished(page: Page, id: string, published: boolean): Promise<void> {
    await loginToAdmin(page);
    await page.goto(PATHS.shipmentMethod(id));
    await saveForm(page, { published: published ? '1' : '0' });
}

/** Creates the method, runs the check, and unpublishes the method, so that it does not change other checkouts. */
export async function withMethod(page: Page, spec: MethodSpec, check: (id: string) => Promise<void>): Promise<void> {
    const { id } = await ensureShipmentMethod(page, spec);
    try {
        await check(id);
    } finally {
        await setShipmentMethodPublished(page, id, false);
    }
}

/** Creates a payment method on the VirtueMart plugin "standard" when it does not exist. Returns its ID. */
export async function ensurePaymentMethod(page: Page, name: string): Promise<string> {
    await loginToAdmin(page);
    const id = await findMethodId(page, PATHS.paymentMethods, name, 'virtuemart_paymentmethod_id');
    if (id) return id;
    return createMethod(page, PATHS.paymentMethods, 'payment_name', 'payment_jplugin_id', /standard/i, 'virtuemart_paymentmethod_id', name);
}
