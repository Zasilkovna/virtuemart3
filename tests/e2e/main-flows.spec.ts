import {
    apiPassHasValidLength,
    CARRIERS_TAB,
    CLEAN_NAME_MESSAGE,
    CONFIG_TAB,
    DIRTY_TEXT,
    expectNoErrorMessage,
    LEAKED_MARKUP,
    loginToAdmin,
    MODULE_TABS,
    openModuleTab,
    openOrderDetail,
    packetaApiSkipReason,
    saveModuleConfig,
    setFormValues,
    shipmentBlockHtml,
    submitTask,
    SUCCESS_ALERTS,
    WARNING_ALERTS,
    withPageLoad,
} from './helpers/admin';
import { ENV, escapeRegExp, exactMessage, PATHS, priceText, TRACKING_LINK, TRACKING_URL } from './helpers/env';
import { MethodSpec, withMethod } from './helpers/methods';
import {
    capturePdf,
    checkOnly,
    editPacketDetail,
    openPacketaList,
    PACKET_DETAIL_FORM,
    packetaRow,
    readPacketDetail,
    rowInput,
    rowNumber,
    rowPointCell,
    submitFromDetail,
    submitFromList,
} from './helpers/packeta';
import { prepareStack, restoreCodFlags } from './helpers/setup';
import { readState, updateState } from './helpers/state';
import { expect, findOrder, missingOrderReason, requireOrder, skipStep, skipWhenAllStepsSkipped, softStep, test } from './helpers/steps';
import {
    createOrder,
    loginToStorefront,
    openCartWithProduct,
    packetaBox,
    pickPointInWidget,
    pointLabel,
    selectShipment,
    setBillingCountry,
    shipmentRadio,
    testPoint,
    WIDGET_LINK,
} from './helpers/storefront';

/**
 * Main flows of the Packeta VirtueMart plugin: multi-step tests.
 *
 * - The tests run in the file order. A later test uses the orders of the test "Orders" (state file
 *   `.state/main-flows.json`, one run ID per run, see playwright.config.ts).
 * - Steps that submit packets or print labels call the Packeta API. They run only when PACKETA_STAGE_API_PASS
 *   is the API password in the module configuration. Cancel the stage packets after the run.
 * - The tests change the stack: they create shipment methods, a payment method and orders, and they save
 *   the module configuration. The test "Cleanup" restores the COD flags. The settings step restores its values.
 */

/** Keys of the orders that the test "Orders" creates. The later tests read the orders by these keys. */
const ORDER = {
    pickupPoint: 'pickupPoint',
    zbox: 'zbox',
    homeDelivery: 'homeDelivery',
    homeDeliveryExt: 'homeDeliveryExt',
    pickupPointCod: 'pickupPointCod',
    homeDeliveryCod: 'homeDeliveryCod',
};

const NO_WIDGET_LINK = 'home delivery has no widget link';
const PACKET_FORM = 'the plugin packet form';
const COD_READY = 'the COD flag is set (the module needs an API password with 32 characters)';
const notSubmitted = (key: string) => `The packet submission step did not submit the packet of the order "${key}".`;

test.afterEach(async ({}, testInfo) => skipWhenAllStepsSkipped(testInfo));

test('Installation', async ({ page }) => {
    await loginToAdmin(page);
    await test.step('the plugin is installed with the version of this repository', async () => {
        expect(ENV.pluginVersion, 'version in zasilkovna.xml').not.toBe('');
        await page.goto(PATHS.extensions);
        const row = page.locator('table tbody tr', { hasText: /Packeta/ }).filter({ hasText: /vmshipment|VirtueMart|Plugin/i });
        await expect(row.first(), 'the Packeta plugin in Extensions: Manage').toBeVisible();
        // Exact version match: "1.5.1" must not match "1.5.10" or "11.5.1".
        const version = escapeRegExp(ENV.pluginVersion);
        await expect.soft(row.first(), 'installed version').toContainText(new RegExp(`(?<![\\d.])${version}(?!\\.?\\d)`));
    });
    await test.step('the plugin is enabled', async () => {
        await page.goto(PATHS.plugins);
        const row = page.getByRole('table', { name: /plugins|pluginy/i }).locator('tr', { hasText: /Packeta|PACKETERY/i });
        await expect(row).toHaveCount(1);
        await expect.soft(row.locator('.icon-publish').first(), 'the plugin is published').toBeVisible();
    });
    await test.step('the Packeta admin answers', async () => {
        const response = await page.goto(PATHS.module);
        expect.soft(response?.status(), 'HTTP status of the Packeta admin').toBeLessThan(400);
        await expect.soft(page.locator(MODULE_TABS).filter({ hasText: CONFIG_TAB }).first(), 'the "Configuration" tab of the Packeta admin').toBeVisible();
    });
});

test('Orders', async ({ page }) => {
    test.setTimeout(600_000);
    await loginToAdmin(page);
    const state = await test.step('prepare shipment methods, payment methods and the COD flag', () => prepareStack(page));
    const { methods, payments } = state;
    const save = (key: string, order: { number: string; id: string }) => updateState((s) => (s.orders[key] = order));

    const listValues = async (number: string) => {
        await loginToAdmin(page);
        await openPacketaList(page);
        const row = packetaRow(page, number);
        await expect(row, `Packeta list row of order ${number}`).toHaveCount(1);
        return { cod: await rowNumber(row, 'packet_cod'), value: await rowNumber(row, 'zasilkovna_packet_price') };
    };

    await softStep('order to a pickup point', async () => {
        const point = testPoint(ORDER.pickupPoint, ENV.pickupPointId, 'Praha 1, Pickup');
        const order = await createOrder(page, { shipmentId: methods.pp, paymentId: payments.standard, point });
        save(ORDER.pickupPoint, order);
        await openOrderDetail(page, order.id);
        await expect.soft(page.locator('body'), 'the admin order stores the picked point').toContainText(pointLabel(point));
    });

    await softStep('order to a Z-BOX', async () => {
        const box = testPoint(ORDER.zbox, ENV.zboxId, 'Z-BOX Praha');
        const order = await createOrder(page, {
            shipmentId: methods.box,
            paymentId: payments.standard,
            point: box,
            onCart: async (p) => {
                const vendors = await packetaBox(p, methods.box).getAttribute('data-packetery-widget-vendors');
                expect.soft(vendors ?? '', 'the BOX method sends the zbox group to the widget').toContain('zbox');
            },
        });
        save(ORDER.zbox, order);
        await openOrderDetail(page, order.id);
        await expect.soft(page.locator('body'), 'the admin order stores the picked box').toContainText(pointLabel(box));
    });

    await softStep('order to a home-delivery carrier', async () => {
        const order = await createOrder(page, {
            shipmentId: methods.hd,
            paymentId: payments.standard,
            onCart: async (p) => {
                await expect.soft(packetaBox(p, methods.hd).locator(WIDGET_LINK), NO_WIDGET_LINK).toHaveCount(0);
            },
        });
        save(ORDER.homeDelivery, order);
        await openOrderDetail(page, order.id);
        await expect.soft(page.locator(PACKET_DETAIL_FORM), PACKET_FORM).toHaveCount(1);
    });

    // Carrier of another country (VM_HD_EXT_CARRIER_ID) and an address in that country (VM_HD_EXT_COUNTRY_ID).
    // The test does not submit the packet.
    await softStep('order to home delivery of an external carrier', async () => {
        expect(methods.hdExt, `the HD EXT method (prepareStack: ${state.hdExtError ?? 'no error'})`).toBeTruthy();
        try {
            const order = await createOrder(page, {
                shipmentId: methods.hdExt,
                paymentId: payments.standard,
                countryId: ENV.hdExtCountryId,
                onCart: async (p) => {
                    await expect.soft(packetaBox(p, methods.hdExt).locator(WIDGET_LINK), NO_WIDGET_LINK).toHaveCount(0);
                },
            });
            save(ORDER.homeDeliveryExt, order);
            await openOrderDetail(page, order.id);
            await expect.soft(page.locator(PACKET_DETAIL_FORM), PACKET_FORM).toHaveCount(1);
            await loginToAdmin(page);
            await openPacketaList(page);
            const row = packetaRow(page, order.number);
            await expect(row, `Packeta list row of order ${order.number}`).toHaveCount(1);
            // The carrier cell shows the carrier of the method form (option value = VM_HD_EXT_CARRIER_ID).
            expect(state.hdExtCarrierName, 'carrier name from the HD EXT method form').toBeTruthy();
            await expect.soft(rowPointCell(row), 'carrier name').toContainText(state.hdExtCarrierName ?? '');
        } finally {
            await setBillingCountry(page, ENV.countryCz);
        }
    });

    await softStep('order to a pickup point with COD', async () => {
        expect(state.codReady, COD_READY).toBe(true);
        const order = await createOrder(page, {
            shipmentId: methods.pp, paymentId: payments.cod, point: testPoint(ORDER.pickupPointCod, ENV.pickupPointId, 'Praha 2, COD'),
        });
        save(ORDER.pickupPointCod, order);
        const { cod, value } = await listValues(order.number);
        expect.soft(value, 'packet value > 0').toBeGreaterThan(0);
        expect.soft(cod, 'COD = packet value').toBe(value);
    });

    await softStep('order to a home-delivery carrier with COD', async () => {
        expect(state.codReady, COD_READY).toBe(true);
        const order = await createOrder(page, { shipmentId: methods.hd, paymentId: payments.cod });
        save(ORDER.homeDeliveryCod, order);
        const { cod, value } = await listValues(order.number);
        expect.soft(value, 'packet value > 0').toBeGreaterThan(0);
        expect.soft(cod, 'COD = packet value').toBe(value);
    });
});

const PRICE_55 = priceText(55);

test('Checkout', async ({ page }) => {
    test.setTimeout(600_000);
    await loginToAdmin(page);
    const { methods } = await test.step('prepare shipment methods', () => prepareStack(page));
    const runId = readState().runId;

    await softStep('a new pickup-point method is offered at checkout', async () => {
        await withMethod(page, { name: `E2E new PP ${runId}`, shippingType: 'pickuppoints', vendorGroups: ['zpoint'], cost: 89 }, async (id) => {
            await openCartWithProduct(page);
            await expect.soft(shipmentRadio(page, id), 'the new method in the cart').toHaveCount(1);
            await expect.soft(packetaBox(page, id), 'the plugin box of the new method').toHaveCount(1);
        });
    });

    await softStep('a new home-delivery method is offered at checkout', async () => {
        await withMethod(page, { name: `E2E new HD ${runId}`, shippingType: 'hdcarriers', cost: 99 }, async (id) => {
            await openCartWithProduct(page);
            await expect.soft(shipmentRadio(page, id), 'the new method in the cart').toHaveCount(1);
        });
    });

    await softStep('the shipping price shows at checkout', async () => {
        await withMethod(page, { name: 'E2E price', shippingType: 'pickuppoints', vendorGroups: ['zpoint'], cost: 55 }, async (id) => {
            await openCartWithProduct(page);
            await expect.soft(packetaBox(page, id), 'the method shows its price').toContainText(PRICE_55);
        });
    });

    await softStep('free shipping over the limit', async () => {
        // Free shipping from 1: each cart is over the limit.
        // The method name has no price and no word "free", so that it cannot satisfy a price assertion.
        const spec: MethodSpec = { name: 'E2E over limit', shippingType: 'pickuppoints', vendorGroups: ['zpoint'], cost: 55, freeShipment: 1 };
        await withMethod(page, spec, async (id) => {
            await openCartWithProduct(page);
            const box = packetaBox(page, id);
            await expect(box, 'the free method is offered').toHaveCount(1);
            // VirtueMart (sublayout methodprices.php) prints no price element when the cost is 0.
            await expect.soft(box.locator('.vmshipment_cost'), 'the free method shows no price').toHaveCount(0);
            await expect.soft(box, 'the free method does not show the normal price').not.toContainText(PRICE_55);
        });
    });

    await softStep('the method is offered when the cart is under the weight limit', async () => {
        // Positive control for the next step: the same kind of method with a limit over the product weight.
        const limit = ENV.productWeightKg + 0.5;
        const spec: MethodSpec = { name: 'E2E weight ok', shippingType: 'pickuppoints', vendorGroups: ['zpoint'], cost: 50, maxWeight: limit };
        await withMethod(page, spec, async (id) => {
            await openCartWithProduct(page);
            await expect.soft(shipmentRadio(page, id), `the method with max ${limit} kg for a ${ENV.productWeightKg} kg cart`).toHaveCount(1);
        });
    });

    await softStep('the weight limit hides the method for a heavier cart', async () => {
        const limit = Math.max(0.1, ENV.productWeightKg - 0.5);
        const spec: MethodSpec = { name: 'E2E weight over', shippingType: 'pickuppoints', vendorGroups: ['zpoint'], cost: 50, maxWeight: limit };
        await withMethod(page, spec, async (id) => {
            await openCartWithProduct(page);
            await expect(shipmentRadio(page, methods.pp), 'the cart offers the other Packeta method').toHaveCount(1);
            await expect.soft(shipmentRadio(page, id), `the method with max ${limit} kg for a ${ENV.productWeightKg} kg cart`).toHaveCount(0);
        });
    });

    await softStep('a country change at checkout drops the picked point', async () => {
        const czPoint = testPoint('countryChange', ENV.pickupPointId, 'Praha 3, Country');
        try {
            await openCartWithProduct(page, ENV.countryCz);
            await selectShipment(page, methods.pp);
            await pickPointInWidget(page, methods.pp, czPoint);
            await setBillingCountry(page, ENV.countrySk);
            await page.goto(PATHS.cart);
            await expect.soft(page.locator('body'), 'the plugin drops the CZ point after the change to SK').not.toContainText(czPoint.nameStreet);
            await expect.soft(shipmentRadio(page, methods.pp), 'the method without a country limit is offered for SK').toHaveCount(1);
            await expect.soft(shipmentRadio(page, methods.pp), 'the Packeta method stays selected after the change to SK').toBeChecked();
        } finally {
            await setBillingCountry(page, ENV.countryCz);
        }
    });

    await softStep('country price and weight rules change the price in the cart', async () => {
        // CZ rule: default 61, weight rule up to 5 kg = 62. SK rule: default 73. The product weighs 1.5 kg.
        const spec: MethodSpec = { name: `E2E rules ${runId}`, shippingType: 'pickuppoints', vendorGroups: ['zpoint'], cost: 70 };
        await withMethod(page, spec, async (id) => {
            await page.goto(PATHS.shipmentMethod(id));
            // The pricing rules are on the "Configuration" tab of the method form.
            await page
                .locator('ul')
                .filter({ has: page.getByRole('link', { name: 'Shipment Method Information', exact: true }) })
                .getByRole('link', { name: CONFIG_TAB })
                .click();
            const root = page.locator('.packetery-pricing-rules').first();
            await expect(root, 'the pricing rules of the method').toBeVisible();
            // The first add button in the document is the top-level button of the country rules.
            const addRule = root.locator('.group-add').first();
            await addRule.click();
            await addRule.click();
            // A country rule has a country select. Its nested weight rules do not.
            const rule = (n: number) => root.locator('.subform-repeatable-group').filter({ has: page.locator('select[name$="[country]"]') }).nth(n);
            await rule(0).locator('select[name$="[country]"]').selectOption(ENV.countryCz, { force: true });
            await rule(0).locator('input[name$="[shipment_cost]"]').fill('61');
            await rule(0).locator('.group-add').last().click();
            await rule(0).locator('input[name$="[maxWeightKg]"]').first().fill('5');
            await rule(0).locator('input[name$="[price]"]').first().fill('62');
            await rule(1).locator('select[name$="[country]"]').selectOption(ENV.countrySk, { force: true });
            await rule(1).locator('input[name$="[shipment_cost]"]').fill('73');
            await submitTask(page, 'apply');
            await expectNoErrorMessage(page);
            try {
                await openCartWithProduct(page, ENV.countryCz);
                await expect.soft(packetaBox(page, id), 'CZ address: weight-rule price').toContainText(priceText(62));
                await setBillingCountry(page, ENV.countrySk);
                await page.goto(PATHS.cart);
                await expect.soft(packetaBox(page, id), 'SK address: country default price').toContainText(priceText(73, 'Kč|CZK|€|EUR'));
            } finally {
                await setBillingCountry(page, ENV.countryCz);
            }
        });
    });
});

test('Packet in the admin', async ({ page }) => {
    test.setTimeout(600_000);
    await loginToAdmin(page);
    const { runId, hdCarrierName } = readState();
    const noApi = await packetaApiSkipReason(page);

    await softStep('the Packeta order list shows the values of the standard orders', async () => {
        const keys = [ORDER.pickupPoint, ORDER.zbox, ORDER.homeDelivery];
        const missing = keys.filter((key) => !findOrder(key));
        if (missing.length === keys.length) skipStep(missingOrderReason(keys.join(', ')));
        for (const key of missing) {
            test.info().annotations.push({ type: 'skipped step', description: `Packeta order list, ${key}: ${missingOrderReason(key)}` });
        }
        await openPacketaList(page);
        for (const key of keys.filter((k) => !missing.includes(k))) {
            const row = packetaRow(page, requireOrder(key).number);
            await expect(row, `one row for ${key}`).toHaveCount(1);
            expect.soft(await rowNumber(row, 'zasilkovna_packet_price'), `${key}: packet value > 0`).toBeGreaterThan(0);
            expect.soft(await rowNumber(row, 'weight'), `${key}: weight = product weight`).toBeCloseTo(ENV.productWeightKg, 3);
            expect.soft(await rowNumber(row, 'packet_cod'), `${key}: no COD for the standard payment`).toBe(0);
            if (key === ORDER.homeDelivery) {
                // The carrier cell shows the carrier of the HD method form (prepareStack stores the option text).
                expect(hdCarrierName, 'carrier name from the HD method form').toBeTruthy();
                await expect.soft(rowPointCell(row), 'carrier name').toContainText(hdCarrierName ?? '');
            } else await expect.soft(rowPointCell(row), `${key}: pickup point name`).toContainText(`${key} ${runId}`);
        }
    });

    await softStep('edit the packet weight and dimensions of the pickup-point COD order; the COD amount stays', async () => {
        const order = requireOrder(ORDER.pickupPointCod);
        const before = await readPacketDetail(page, order.id);
        await editPacketDetail(page, order.id, { weight: '2.5', packet_cod: before.packet_cod, length: '300', width: '200', height: '100' });
        await expect.soft(page.locator(WARNING_ALERTS), 'the plugin accepts the packet detail').toHaveCount(0);
        const after = await readPacketDetail(page, order.id);
        expect.soft(Number(after.weight), 'weight').toBe(2.5);
        expect.soft([after.length, after.width, after.height], 'dimensions').toEqual(['300', '200', '100']);
        expect.soft(Number(after.packet_cod), 'COD stays').toBe(Number(before.packet_cod));
    });

    /** Records the packet ID and prints it, so that you can cancel the packet on Packeta stage after the run. */
    const recordPacket = (key: string, number: string, packetId: string) => {
        updateState((s) => (s.orders[key].packetId = packetId));
        test.info().annotations.push({ type: 'packet', description: `${key}: order ${number}, packet ${packetId}` });
        console.log(`Packet on Packeta stage (cancel it after the run): ${key}, order ${number}, packet ${packetId}`);
    };

    const keys = [ORDER.pickupPoint, ORDER.zbox];
    await softStep('submit the packets of the pickup-point and Z-BOX orders from the Packeta list', async () => {
        const orders = keys.map((key) => requireOrder(key));
        const packets: Record<string, string> = {};
        await submitFromList(page, orders.map((o) => o.number), (number, packetId) => {
            packets[number] = packetId;
            recordPacket(keys[orders.findIndex((o) => o.number === number)], number, packetId);
        });
        await openPacketaList(page);
        for (const [i, key] of keys.entries()) {
            const number = orders[i].number;
            expect(packets[number], `packet number of ${key}`).toMatch(/^\d+$/);
            await expect.soft(rowInput(packetaRow(page, number), 'weight'), `${key} is marked as submitted`).toBeDisabled();
            await expect.soft(packetaRow(page, number).locator('input.js-cbPrint'), `${key} label checkbox`).toBeEnabled();
        }
    }, noApi);

    await softStep('submit the packet of the home-delivery order from the order detail', async () => {
        const order = requireOrder(ORDER.homeDelivery);
        const packetId = await submitFromDetail(page, order, (number, id) => recordPacket(ORDER.homeDelivery, number, id));
        expect(packetId, `packet number of ${ORDER.homeDelivery}`).toMatch(/^\d+$/);
        await openPacketaList(page);
        await expect.soft(rowInput(packetaRow(page, order.number), 'weight'), `${ORDER.homeDelivery} is marked as submitted`).toBeDisabled();
    }, noApi);

    await softStep('print the Packeta label from the Packeta list', async () => {
        const order = requireOrder(ORDER.pickupPoint);
        if (!order.packetId) skipStep(notSubmitted(ORDER.pickupPoint));
        await openPacketaList(page);
        await checkOnly(page, 'js-cbPrint', [order.number]);
        await page.locator('select#print_type').selectOption('A6_on_A4', { force: true });
        await capturePdf(page, () => page.evaluate(() => (window as any).Joomla.submitbutton('printLabels')));
    }, noApi);

    await softStep('print the carrier label from the order detail of the home-delivery order', async () => {
        const order = requireOrder(ORDER.homeDelivery);
        if (!order.packetId) skipStep(notSubmitted(ORDER.homeDelivery));
        await openOrderDetail(page, order.id);
        await page.locator('[id*="packetaPrintLabel"]').first().click();
        const form = page.locator('#packeteryPrintLabelForm');
        await expect(form).toBeVisible();
        const select = form.locator('select[name="print_type"]');
        const carrierFormat = await select.locator('option[value^="carriers_"]').first().getAttribute('value');
        expect(carrierFormat, 'carrier label formats for a carrier order').toBeTruthy();
        await select.selectOption(carrierFormat as string, { force: true });
        await capturePdf(page, () => form.locator('a[onclick*="submitPrintLabel"]').click());
    }, noApi);
});

test('Frontend', async ({ page }) => {
    // Mailpit polling (up to 60 s) and two logins.
    test.setTimeout(240_000);
    const pointText = `${ORDER.pickupPoint} ${readState().runId}`;

    /** The shipment name is clean: no leaked text in the page and no raw script or style in the shipment block. */
    const expectCleanShipmentName = async () => {
        expect.soft(await page.locator('body').innerText(), CLEAN_NAME_MESSAGE).not.toMatch(DIRTY_TEXT);
        expect.soft(await shipmentBlockHtml(page, pointText), `${CLEAN_NAME_MESSAGE} (HTML of the shipment block)`).not.toMatch(LEAKED_MARKUP);
    };

    await softStep('the storefront order detail shows a clean shipment name and the pickup point', async () => {
        const order = requireOrder(ORDER.pickupPoint);
        await loginToStorefront(page);
        await page.goto(PATHS.feOrder(order.number));
        const body = page.locator('body');
        await expect(body).toContainText(order.number);
        await expect.soft(body, 'the pickup point').toContainText(pointText);
        await expectCleanShipmentName();
    });

    await softStep('the admin order detail shows the packet data', async () => {
        const order = requireOrder(ORDER.pickupPoint);
        await loginToAdmin(page);
        await openOrderDetail(page, order.id);
        const body = page.locator('body');
        await expect(body).toContainText(order.number);
        await expect.soft(body, 'the pickup point').toContainText(pointText);
        await expectCleanShipmentName();
    });

    await softStep('the admin order detail shows the tracking link of the packet', async () => {
        const order = requireOrder(ORDER.pickupPoint);
        if (!order.packetId) skipStep(notSubmitted(ORDER.pickupPoint));
        await loginToAdmin(page);
        await openOrderDetail(page, order.id);
        await expect.soft(page.locator(TRACKING_LINK).first(), 'the tracking link of the packet')
            .toHaveAttribute('href', `${TRACKING_URL}${order.packetId}`);
    });

    await softStep('the order e-mail has a clean shipment name with the pickup point', async () => {
        const order = requireOrder(ORDER.pickupPoint);
        let found: Array<{ ID: string }> = [];
        await expect
            .poll(async () => {
                const res = await fetch(`${ENV.mailpitUrl}/api/v1/search?query=${encodeURIComponent(order.number)}`);
                if (!res.ok) throw new Error(`Mailpit search answered HTTP ${res.status} (MAILPIT_URL=${ENV.mailpitUrl}).`);
                found = ((await res.json()) as { messages?: Array<{ ID: string }> }).messages ?? [];
                return found.length;
            }, { message: `Mailpit message with order number ${order.number}`, timeout: 60_000 })
            .toBeGreaterThan(0);
        const messageRes = await fetch(`${ENV.mailpitUrl}/api/v1/message/${found[0].ID}`);
        if (!messageRes.ok) throw new Error(`Mailpit message answered HTTP ${messageRes.status} (MAILPIT_URL=${ENV.mailpitUrl}).`);
        const mail = (await messageRes.json()) as { Text: string; HTML: string };
        // An empty part would pass the checks below without a check.
        expect(mail.Text?.trim() ?? '', 'the plain-text part of the e-mail').not.toBe('');
        expect(mail.HTML?.trim() ?? '', 'the HTML part of the e-mail').not.toBe('');
        expect.soft(mail.Text, 'the plain-text part has no leaked script, style or language key').not.toMatch(DIRTY_TEXT);
        expect.soft(mail.HTML, 'the HTML part has no escaped markup').not.toMatch(/&lt;script|&lt;style/i);
        expect.soft(mail.Text + mail.HTML, 'the e-mail contains the pickup point').toContain(pointText);
    }, ENV.mailpitUrl ? '' : 'MAILPIT_URL is not set.');

    await softStep('the order print view shows a clean shipment name and the pickup point', async () => {
        const order = requireOrder(ORDER.pickupPoint);
        // The print view calls window.print() on load. The stub keeps the headless browser from a print dialog.
        await page.addInitScript(() => {
            window.print = () => undefined;
        });
        await loginToStorefront(page);
        const response = await page.goto(PATHS.feOrderPrint(order.number));
        expect(response?.status(), 'HTTP status of the print view').toBeLessThan(400);
        const body = page.locator('body');
        await expect(body).toContainText(order.number);
        await expect.soft(page.locator('.vm-orders-vendor-image'), 'the print layout of VirtueMart').toHaveCount(1);
        await expect.soft(body, 'the pickup point').toContainText(pointText);
        await expectCleanShipmentName();
    });
});

test('Carriers and settings', async ({ page }) => {
    // Two carrier updates (up to 60 s each) and a wait for the next minute (up to 65 s).
    test.setTimeout(300_000);
    await loginToAdmin(page);

    await softStep('a changed setting is saved and kept after reload', async () => {
        expect(await apiPassHasValidLength(page), 'the API password has 32 characters (the plugin saves the configuration only then)').toBe(true);
        const label = page.locator('input[name="zasilkovna_eshop_label"]');
        const consign = page.locator('input[type="checkbox"][name="zasilkovna_show_consign_password"]');
        await openModuleTab(page, CONFIG_TAB);
        const originalLabel = await label.inputValue();
        const originalConsign = await consign.isChecked();
        const changedLabel = `e2e-${readState().runId}`;
        try {
            await setFormValues(page, { zasilkovna_eshop_label: changedLabel, zasilkovna_show_consign_password: !originalConsign });
            await saveModuleConfig(page);
            await openModuleTab(page, CONFIG_TAB);
            await expect.soft(label, 'the changed sender label').toHaveValue(changedLabel);
            if (originalConsign) await expect.soft(consign, 'the changed checkbox').not.toBeChecked();
            else await expect.soft(consign, 'the changed checkbox').toBeChecked();
        } finally {
            await openModuleTab(page, CONFIG_TAB);
            await setFormValues(page, { zasilkovna_eshop_label: originalLabel, zasilkovna_show_consign_password: originalConsign });
            await saveModuleConfig(page);
            await openModuleTab(page, CONFIG_TAB);
            await expect(label, 'the original sender label is restored').toHaveValue(originalLabel);
            await expect(consign, 'the original checkbox state is restored').toBeChecked({ checked: originalConsign });
        }
    });

    await softStep('the manual carrier update loads the carrier list', async () => {
        /** Value cell of a row in the carriers tab, by the label in the row header. */
        const carriersValue = (label: RegExp) => page.locator('table.admintable tr', { has: page.locator('th', { hasText: label }) }).locator('td').first();
        const lastUpdate = carriersValue(/Last carriers update|Poslední aktualizace přepravců/);
        const total = carriersValue(/Total carriers|Přepravců celkem/);
        const updateCarriers = async () => {
            await openModuleTab(page, CARRIERS_TAB);
            const update = page.locator('a[href*="task=updateCarriers"]').first();
            await expect(update, 'the "update carriers" button').toBeVisible();
            await withPageLoad(page, 'Carrier update', () => update.click(), 60_000);
            // The plugin shows the success message only when the update has no error.
            // The text comes from PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATED (en-GB, cs-CZ).
            await expectNoErrorMessage(page);
            // Count the message elements, not the alerts: on Joomla 5 each message is an `.alert-message` inside `joomla-alert`.
            const carriersUpdated = exactMessage(['Carriers were updated.', 'Seznam dopravců byl aktualizován.']);
            await expect(page.locator(SUCCESS_ALERTS).locator('.alert-message').filter({ hasText: carriersUpdated }),
                'the "carriers updated" message').toHaveCount(1);
        };

        await openModuleTab(page, CARRIERS_TAB);
        const before = (await lastUpdate.innerText()).trim();
        await updateCarriers();
        await openModuleTab(page, CARRIERS_TAB);
        let after = (await lastUpdate.innerText()).trim();
        if (after === before) {
            // The plugin shows the time without seconds. An earlier update in the same minute shows the same value.
            // Wait until the next minute starts and update again.
            // The margin of 5 s is necessary because the clocks of the stack and of the test machine can differ.
            await page.waitForTimeout((60 - new Date().getSeconds() + 5) * 1000);
            await updateCarriers();
            await openModuleTab(page, CARRIERS_TAB);
            after = (await lastUpdate.innerText()).trim();
        }
        expect.soft(after, 'the time of the last carrier update changes').not.toBe(before);
        expect.soft(after, 'the time of the last carrier update').toMatch(/\d{1,4}\D+\d{1,2}\D+\d{2,4}\s+\d{1,2}:\d{2}/);
        expect.soft(Number((await total.innerText()).trim()), 'the total number of carriers').toBeGreaterThan(0);
    });
});

test('Cleanup', async ({ page }) => {
    await loginToAdmin(page);
    await softStep('restore the COD flags that the test "Orders" changed', async () => {
        if (!(await restoreCodFlags(page))) skipStep('No original COD flags to restore (.state/cod-flags-original.json does not exist).');
    });
});
