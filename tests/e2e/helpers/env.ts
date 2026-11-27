import fs from 'fs';
import path from 'path';

// Configuration of the tests. The README lists the environment variables.
// VIRTUEMART_BASE_URL and VM_ADMIN_PASS have no default.

/** Plugin version in the manifest of this repository. */
function manifestVersion(): string {
    const xml = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'zasilkovna.xml'), 'utf8');
    return xml.match(/<version>\s*([^<\s]+)\s*<\/version>/)?.[1] ?? '';
}

const STAGE_API_PASS = process.env.PACKETA_STAGE_API_PASS ?? '';

export const ENV = {
    adminUser: process.env.VM_ADMIN_USER ?? 'admin',
    /** No default. loginToAdmin() fails with a clear message when the variable is not set. */
    adminPass: process.env.VM_ADMIN_PASS ?? '',
    storeUser: process.env.VM_STORE_USER ?? process.env.VM_ADMIN_USER ?? 'admin',
    storePass: process.env.VM_STORE_PASS ?? process.env.VM_ADMIN_PASS ?? '',
    productId: process.env.VM_PRODUCT_ID ?? '1',
    productWeightKg: Number(process.env.VM_PRODUCT_WEIGHT_KG ?? '1.5'),
    /** VirtueMart country IDs (56 = Czech Republic, 189 = Slovakia). They do not change with the site language. */
    countryCz: process.env.VM_COUNTRY_ID ?? '56',
    countrySk: process.env.VM_COUNTRY_SK_ID ?? '189',
    /** Pickup point and Z-BOX of the orders. Both must exist on Packeta stage (79 and 8167 do). */
    pickupPointId: process.env.VM_PICKUP_POINT_ID ?? '79',
    zboxId: process.env.VM_ZBOX_ID ?? '8167',
    /** Home-delivery carrier of the HD method. Empty = methods.ts selects the default (see defaultHdCarrierId()). */
    hdCarrierId: process.env.VM_HD_CARRIER_ID ?? '',
    /** Home-delivery carrier of another country. Example: 6830 = AT DPD HD (carrier feed, country "at"). */
    hdExtCarrierId: process.env.VM_HD_EXT_CARRIER_ID ?? '6830',
    /** VirtueMart country ID of hdExtCarrierId. Example: 14 = Austria. The order uses an address in this country. */
    hdExtCountryId: process.env.VM_HD_EXT_COUNTRY_ID ?? '14',
    /** Mailpit of the stack, for example http://localhost:8025. Empty = the e-mail check does not run. */
    mailpitUrl: process.env.MAILPIT_URL ?? '',
    pluginVersion: process.env.PACKETA_PLUGIN_VERSION ?? manifestVersion(),
    /**
     * API password of the Packeta stage account. The steps that call the Packeta API run only when
     * this value is the API password in the module configuration (see packetaApiSkipReason()).
     */
    stageApiPass: STAGE_API_PASS,
};

export const PATHS = {
    cart: '/index.php?option=com_virtuemart&view=cart',
    product: (id: string) => `/index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=${id}`,
    billingAddress: '/index.php?option=com_virtuemart&view=user&task=editaddresscart&addrtype=BT',
    feOrder: (number: string) =>
        `/index.php?option=com_virtuemart&view=orders&layout=details&order_number=${encodeURIComponent(number)}`,
    feOrderPrint: (number: string) =>
        `/index.php?option=com_virtuemart&view=orders&layout=details&order_number=${encodeURIComponent(number)}&tmpl=component&print=1`,
    /** Query part of the AJAX URL that saves the picked point. */
    savePointQuery: 'task=saveSelectedPoint',
    adminOrders: '/administrator/index.php?option=com_virtuemart&view=orders',
    adminOrder: (id: string) => `/administrator/index.php?option=com_virtuemart&view=orders&task=edit&virtuemart_order_id=${id}`,
    module: '/administrator/index.php?option=com_virtuemart&view=zasilkovna',
    shipmentMethods: '/administrator/index.php?option=com_virtuemart&view=shipmentmethod',
    shipmentMethod: (id: string) => `/administrator/index.php?option=com_virtuemart&view=shipmentmethod&task=edit&cid[]=${id}`,
    paymentMethods: '/administrator/index.php?option=com_virtuemart&view=paymentmethod',
    country: (id: string) => `/administrator/index.php?option=com_virtuemart&view=country&task=edit&cid[]=${id}`,
    extensions: '/administrator/index.php?option=com_installer&view=manage&filter[search]=Packeta&list[limit]=100',
    plugins: '/administrator/index.php?option=com_plugins&view=plugins&filter[folder]=vmshipment&filter[search]=&list[limit]=100',
};

/** Start of the tracking URL. The packet ID follows it. */
export const TRACKING_URL = 'https://tracking.app.packeta.com/';
/** Link that the plugin renders for a submitted packet. */
export const TRACKING_LINK = `a[href^="${TRACKING_URL}"]`;

export function escapeRegExp(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Matches exactly one of the texts (for example the en-GB and the cs-CZ text of one language key), with optional white space around it. */
export function exactMessage(texts: string[]): RegExp {
    return new RegExp(`^\\s*(?:${texts.map(escapeRegExp).join('|')})\\s*$`);
}

/**
 * Price in the cart, for example "55,00" or "55 Kč".
 * The leading word boundary keeps "155,00" from a match.
 */
export function priceText(amount: number, currencies = 'Kč|CZK'): RegExp {
    return new RegExp(`\\b${amount}[,.]00|\\b${amount}\\s*(${currencies})`);
}
