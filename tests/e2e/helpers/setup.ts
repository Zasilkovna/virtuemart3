import { Page } from '@playwright/test';
import { apiPassHasValidLength, codFlags, publishCountry, updateModuleConfig } from './admin';
import { ENV } from './env';
import { ensurePaymentMethod, ensureShipmentMethod, ShipmentMethod } from './methods';
import { deleteOriginalCodFlags, keepOriginalCodFlags, readOriginalCodFlags, readState, RunState, updateState } from './state';
import { expect } from './steps';

const METHOD_NAMES = { pp: 'E2E Packeta PP', box: 'E2E Packeta BOX', hd: 'E2E Packeta HD', hdExt: 'E2E Packeta HD EXT' };
const COD_PAYMENT_NAME = 'E2E Packeta COD';
const STANDARD_PAYMENT_NAME = 'E2E Packeta standard';

async function expectCodFlag(page: Page, paymentId: string, checked: boolean, message: string): Promise<void> {
    await expect(page.locator(`input[type="checkbox"][name="zasilkovna_payment_method_${paymentId}"]`), message).toBeChecked({ checked });
}

/**
 * Creates (or reuses) the shipment methods PP, BOX, HD and HD EXT, the payment methods "standard" and COD,
 * and the COD flags of these two payment methods. It does not change the COD flag of other payment methods.
 * HD EXT is the home delivery of an external carrier. The country of that carrier must be published,
 * because the carrier select lists only carriers of a published country. Thus, the function publishes it.
 * It runs once per run. The state file keeps the IDs for the next tests.
 * Before it changes a COD flag, it records the original flags. The test "Cleanup" restores them.
 */
export async function prepareStack(page: Page): Promise<RunState> {
    if (readState().methods.pp) return readState();
    const pp = await ensureShipmentMethod(page, { name: METHOD_NAMES.pp, shippingType: 'pickuppoints', vendorGroups: ['zpoint'], cost: 79 });
    const box = await ensureShipmentMethod(page, { name: METHOD_NAMES.box, shippingType: 'pickuppoints', vendorGroups: ['zbox'], cost: 69 });
    const hd = await ensureShipmentMethod(page, { name: METHOD_NAMES.hd, shippingType: 'hdcarriers', cost: 99 });
    expect(hd.hdCarrierId, 'the HD method has a carrier').toBeTruthy();
    // Only the order of the external carrier needs HD EXT. A failure here does not stop the other tests. That order step reports it.
    let hdExt: ShipmentMethod = { id: '' };
    let hdExtError = '';
    try {
        await publishCountry(page, ENV.hdExtCountryId);
        hdExt = await ensureShipmentMethod(page, {
            name: METHOD_NAMES.hdExt, shippingType: 'hdcarriers', cost: 149, hdCarrierId: ENV.hdExtCarrierId,
        });
    } catch (error) {
        hdExtError = (error as Error).message.split('\n').slice(0, 2).join(' ');
    }
    const cod = await ensurePaymentMethod(page, COD_PAYMENT_NAME);
    const standard = await ensurePaymentMethod(page, STANDARD_PAYMENT_NAME);
    const flags = await codFlags(page);
    let codReady = false;
    // The plugin does not save the configuration with a password of another length.
    if (await apiPassHasValidLength(page)) {
        const original = (id: string) => flags.find((f) => f.id === id)?.checked ?? false;
        keepOriginalCodFlags({ [cod]: original(cod), [standard]: original(standard) });
        await updateModuleConfig(page, {
            [`zasilkovna_payment_method_${cod}`]: true,
            [`zasilkovna_payment_method_${standard}`]: false,
        });
        await expectCodFlag(page, cod, true, 'the COD flag of the COD payment');
        codReady = true;
    }
    return updateState((s) => {
        s.methods = { pp: pp.id, box: box.id, hd: hd.id };
        if (hdExt.id) s.methods.hdExt = hdExt.id;
        s.hdCarrierName = hd.hdCarrierName;
        s.hdExtCarrierName = hdExt.hdCarrierName;
        s.hdExtError = hdExtError || undefined;
        s.payments = { standard, cod };
        s.codReady = codReady;
    });
}

/**
 * Restores the original COD flags and then deletes the file with them.
 * Returns false when no run changed a COD flag.
 */
export async function restoreCodFlags(page: Page): Promise<boolean> {
    const original = readOriginalCodFlags();
    if (!original || Object.keys(original).length === 0) return false;
    const values: Record<string, boolean> = {};
    for (const [id, checked] of Object.entries(original)) values[`zasilkovna_payment_method_${id}`] = checked;
    await updateModuleConfig(page, values);
    for (const [id, checked] of Object.entries(original)) {
        await expectCodFlag(page, id, checked, `COD flag of payment ${id} is restored`);
    }
    deleteOriginalCodFlags();
    return true;
}
