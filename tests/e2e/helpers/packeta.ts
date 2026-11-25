import fs from 'fs';
import { Locator, Page } from '@playwright/test';
import { expectNoErrorMessage, messageText, openModuleTab, openOrderDetail, ORDERS_TAB, withPageLoad, submitTask } from './admin';
import { escapeRegExp, TRACKING_LINK, TRACKING_URL } from './env';
import { expect } from './steps';

/** Maximum time for the page load after a packet submission. The plugin calls the Packeta SOAP API in the request. */
const PACKET_SUBMIT_TIMEOUT = 90_000;
/** Maximum time for the PDF after a label print action. */
const PDF_TIMEOUT = 60_000;

/** Form with the packet detail (weight, COD, dimensions) in the admin order detail. */
export const PACKET_DETAIL_FORM = '#packeteryUpdateOrderDetailForm';

/** Opens the order list of the Packeta admin, with all orders on one page. */
export async function openPacketaList(page: Page): Promise<void> {
    await openModuleTab(page, ORDERS_TAB, '&limit=100');
    await expect(page.locator('table.zasilkovna')).toBeVisible();
}

export function packetaRow(page: Page, orderNumber: string): Locator {
    const exactText = new RegExp(`^\\s*${escapeRegExp(orderNumber)}\\s*$`);
    return page.locator('table.zasilkovna tbody tr', { has: page.locator('a', { hasText: exactText }) });
}

export function rowInput(row: Locator, field: string): Locator {
    return row.locator(`input[name$="[${field}]"]`);
}

export async function rowNumber(row: Locator, field: string): Promise<number> {
    return Number((await rowInput(row, field).inputValue()).replace(',', '.'));
}

/** The pickup point or carrier column (second column from the end). */
export function rowPointCell(row: Locator): Locator {
    return row.locator('td:nth-last-child(2)');
}

/** Checks only the rows of the given orders in one checkbox column (`js-cbExport` = submit, `js-cbPrint` = labels). */
export async function checkOnly(page: Page, column: 'js-cbExport' | 'js-cbPrint', orderNumbers: string[]): Promise<void> {
    // The header checkbox (id = column class without `js-`) sets all enabled rows at once (zasilkovnaCheckAll).
    const header = page.locator(`#${column.replace(/^js-/, '')}`);
    await setBox(header, true);
    await setBox(header, false);
    await expect(page.locator(`table.zasilkovna input.${column}:checked`), `no ${column} row is checked`).toHaveCount(0);
    for (const number of orderNumbers) {
        const box = packetaRow(page, number).locator(`input.${column}`);
        await expect(box, `${column} for order ${number}`).toBeEnabled();
        await setBox(box, true);
        await expect(box).toBeChecked();
    }
}

async function setBox(box: Locator, checked: boolean): Promise<void> {
    if ((await box.isChecked()) === checked) return;
    // Away from the sticky toolbar of Joomla.
    await box.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
    await box.setChecked(checked);
}

/** Called for each packet that a submission created, before the checks of the submission. */
export type RecordPacket = (orderNumber: string, packetId: string) => void;

/** Runs the check and returns its error. The caller throws the error later. */
async function deferError(check: () => Promise<void>): Promise<unknown> {
    try {
        await check();
        return undefined;
    } catch (error) {
        return error;
    }
}

/** How long the tests read the packets again after a failed submit. */
const LATE_PACKET_WAIT = 60_000;

/**
 * Calls `read`, which loads a page and records the packet IDs that it finds. `read` returns true when it found all packets.
 * After a failed submit, it calls `read` again until `read` returns true or LATE_PACKET_WAIT ends.
 * Why: the PHP request of a submit that timed out continues and can create the packets later.
 * When `read` fails after a failed submit, it throws the submit error, because the submit error is the cause.
 */
async function readPacketsAfterSubmit(page: Page, submitError: unknown, read: () => Promise<boolean>): Promise<void> {
    const end = Date.now() + LATE_PACKET_WAIT;
    for (;;) {
        let done: boolean;
        try {
            done = await read();
        } catch (error) {
            if (!submitError) throw error;
            // The test throws the submit error. The log keeps the error of the new page load.
            console.log(`The page did not load again after the failed submit: ${String(error)}`);
            throw submitError;
        }
        if (done || !submitError || Date.now() >= end) return;
        await page.waitForTimeout(5_000);
    }
}

/**
 * Submits the packets of the orders from the Packeta list.
 * It records each packet ID that it finds before it checks the result.
 * Why: a packet on Packeta stage exists also when a later check fails, and you must cancel it.
 */
export async function submitFromList(page: Page, orderNumbers: string[], record: RecordPacket): Promise<void> {
    await openPacketaList(page);
    await checkOnly(page, 'js-cbExport', orderNumbers);
    // A submit that timed out can still create packets. Thus, the list is read also after a failed submit.
    const submitError = await deferError(() => submitTask(page, 'submitToZasilkovna', PACKET_SUBMIT_TIMEOUT));
    const answer = submitError ? '(the submit failed)' : (await messageText(page)) || '(no message)';
    const messageError = submitError ?? await deferError(() => expectNoErrorMessage(page));
    const missing = new Set(orderNumbers);
    await readPacketsAfterSubmit(page, submitError, async () => {
        await openPacketaList(page);
        for (const number of [...missing]) {
            const link = packetaRow(page, number).locator(TRACKING_LINK);
            if ((await link.count()) === 0) continue;
            record(number, (await link.first().innerText()).replace(/^Z/, '').trim());
            missing.delete(number);
        }
        return missing.size === 0;
    });
    if (messageError) throw messageError;
    for (const number of orderNumbers) {
        await expect(packetaRow(page, number).locator(TRACKING_LINK), `tracking link for order ${number}; messages after the submit: ${answer}`)
            .toHaveCount(1);
    }
}

/**
 * Submits the packet from the admin order detail. Returns the packet ID.
 * It records the packet ID before it checks the result (see submitFromList()).
 */
export async function submitFromDetail(page: Page, order: { id: string; number: string }, record: RecordPacket): Promise<string> {
    const orderId = order.id;
    await openOrderDetail(page, orderId);
    // The toolbar button of the plugin links to task=submitPacket. The text is the fallback (en, cs, sk).
    const action = page
        .locator('a[href*="task=submitPacket"]')
        .or(page.getByRole('link', { name: /submit to packeta|podat na zásilkovnu|podať na packetu/i }))
        .first();
    await expect(action, 'the submit action in the order detail').toHaveCount(1);
    // A submit that timed out can still create the packet. Thus, the detail is read also after a failed submit.
    const submitError = await deferError(() => withPageLoad(page, 'Packet submission', () => action.click(), PACKET_SUBMIT_TIMEOUT));
    const answer = submitError ? '(the submit failed)' : (await messageText(page)) || '(no message)';
    const messageError = submitError ?? await deferError(() => expectNoErrorMessage(page));
    const link = page.locator(TRACKING_LINK).first();
    let packetId = '';
    await readPacketsAfterSubmit(page, submitError, async () => {
        await openOrderDetail(page, orderId);
        packetId = (await link.count()) > 0 ? ((await link.getAttribute('href')) ?? '').replace(TRACKING_URL, '').trim() : '';
        return packetId !== '';
    });
    if (packetId) record(order.number, packetId);
    if (messageError) throw messageError;
    // After the submit, the order has a packet ID. The toolbar shows the label button and the detail shows the tracking link.
    await expect(page.locator('[id*="packetaPrintLabel"]').first(), 'the label button after the submit').toBeVisible();
    await expect(link, `tracking link; messages after the submit: ${answer}`).toBeVisible();
    return packetId;
}

type PacketDetail = {
    weight?: string;
    packet_cod?: string;
    length?: string;
    width?: string;
    height?: string;
};

export async function editPacketDetail(page: Page, orderId: string, detail: PacketDetail): Promise<void> {
    await openOrderDetail(page, orderId);
    await page.locator('#showPacketeryUpdateOrderDetail').click();
    const form = page.locator(PACKET_DETAIL_FORM);
    await expect(form).toBeVisible();
    for (const [name, value] of Object.entries(detail)) await form.locator(`input[name="${name}"]`).fill(value);
    await withPageLoad(page, 'Packet detail save', () => form.locator('a[onclick*="savePacketeryUpdateOrderDetail"]').click());
}

export async function readPacketDetail(page: Page, orderId: string): Promise<Required<PacketDetail>> {
    await openOrderDetail(page, orderId);
    const form = page.locator(PACKET_DETAIL_FORM);
    const value = (name: string) => form.locator(`input[name="${name}"]`).inputValue();
    return {
        weight: await value('weight'),
        packet_cod: await value('packet_cod'),
        length: await value('length'),
        width: await value('width'),
        height: await value('height'),
    };
}

/** Runs the action and checks the PDF that the browser gets (as a download or as an `application/pdf` response). */
export async function capturePdf(page: Page, action: () => Promise<unknown>): Promise<void> {
    const download = page.waitForEvent('download', { timeout: PDF_TIMEOUT }).then(async (d) => fs.readFileSync((await d.path()) as string));
    const response = page
        .waitForResponse((r) => (r.headers()['content-type'] ?? '').includes('application/pdf'), { timeout: PDF_TIMEOUT })
        .then((r) => r.body());
    download.catch(() => undefined);
    response.catch(() => undefined);
    await action();
    const body = await Promise.any([download, response]).catch(() => {
        throw new Error(`No PDF in ${PDF_TIMEOUT / 1000} s. Expected a download or a response with the type application/pdf.`);
    });
    expect(body.subarray(0, 5).toString('latin1'), 'PDF signature').toBe('%PDF-');
}
