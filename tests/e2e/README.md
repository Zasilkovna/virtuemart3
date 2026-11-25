# End-to-end tests (Playwright)

These E2E tests check the Packeta plugin on a running Joomla + VirtueMart stack.
They use only HTTP and the browser UI.
They need no special harness. Environment variables configure them.
They are not part of the installable package (`tests/` has `export-ignore`).
The tests are for Joomla 5 and VirtueMart 4.8.

## Quick start

You need Node.js 20 or later and a Joomla 5 + VirtueMart 4.8 stack with the plugin installed.
In the Packeta admin, load the carrier list and set an API password with 32 characters.
Check also the other [Prerequisites](#prerequisites).

Run the tests with one command from the repository root:

```bash
cd tests/e2e && npm ci && npx playwright install chromium && \
  VIRTUEMART_BASE_URL=http://localhost:8080 VM_ADMIN_PASS='<admin password>' npx playwright test
```

The HTML report opens with `npx playwright show-report`.
The next runs need only the last line (`npx playwright test` with the variables).

Optional: enable the steps that skip by default.
- `MAILPIT_URL`: the base URL of Mailpit, for example `http://localhost:8025`. Without it, the e-mail step skips.
- ⚠️ `PACKETA_STAGE_API_PASS`: the API password of a Packeta stage account. Without it, the packet submission and label steps skip.
  The labels and the tracking link in the order detail also skip, because they need a submitted packet.
  Read [Packet submission and labels](#packet-submission-and-labels-packeta-stage) before you set it.

## What the tests check

The file `main-flows.spec.ts` has these tests with steps (`test.step`):

| Test | Steps |
|------|-------|
| Installation | The plugin has the version of `zasilkovna.xml`, it is enabled, the Packeta admin answers. |
| Orders | Orders to a pickup point, to a Z-BOX, to home delivery, to home delivery of an external carrier, to a pickup point with COD, to home delivery with COD. |
| Checkout | A new method is offered, price and free shipping, weight limit, country change, country price and weight rules. |
| Packet in the admin | Packeta order list, packet weight and dimensions, packet submission (from the Packeta list and from the order detail), labels. |
| Frontend | Order detail (storefront and admin), order e-mail, order print view. |
| Carriers and settings | Module settings, manual carrier update. |
| Cleanup | Restores the COD flags that the test "Orders" changed. |

## Rules

- Run the whole file. The tests run in the file order, in one worker, and the later tests use the orders of the test "Orders" (`.state/main-flows.json`).
- A step that the next steps do not need fails soft. A step that does not run shows as skipped, with the reason.
- The Packeta widget gets a stub. The browser reaches only the stack host.
- The module configuration page shows the API password in a text field.
  A trace, a screenshot, an aria snapshot, a HAR file or a network log can write the password to a file.
  `helpers/steps.ts` (`maskApiPass()`, `fillSecret()`) and `playwright.config.ts` keep the password out of the reports.
  Traces and screenshots are off. Keep the reports private anyway.
  This protection depends on the Playwright version. Check it again when you upgrade Playwright.

## Prerequisites

- Node.js 20 or later.
- A Joomla + VirtueMart stack with the plugin, a product, a payment method, a published country and a tax rule.

Prerequisites for the main flows:

- The plugin version on the stack is the version in `zasilkovna.xml` (the test "Installation" checks it).
- The carrier list is loaded. Use the "update carriers" button in the Packeta admin.
- The module configuration has an API password with 32 characters.
  Without it, the orders with COD and the settings step fail (not skip), because the plugin does not save the configuration.
- The product weight is `VM_PRODUCT_WEIGHT_KG`.
- The administrator language is English or Czech.
- The Joomla login module (`form.mod-login`) is published on the storefront. Without it, the storefront steps stop with an error. The tests do not continue as a guest.
- Chromium sends each `*.localhost` host name to the loopback interface of the machine where it runs.
  In a container, forward the port to the stack, or use a host name that is not `*.localhost`.

## Packet submission and labels (Packeta stage)

The steps "packet submission" and "labels" call the Packeta API.
They run only when `PACKETA_STAGE_API_PASS` is the API password in the module configuration.
The tests read the password on the module configuration page and compare the two values.
When the variable is not set or the values are different, the steps skip with the reason.

Hard precondition: the plugin must point to the Packeta stage API before you set `PACKETA_STAGE_API_PASS`.
The tests only check that `PACKETA_STAGE_API_PASS` is the API password in the module configuration.
The tests do not check the API target of the plugin.

By default, the plugin sends packets to the production API, and a stage API password can also work there.
The plugin calls the API from the server, so the tests cannot check the target. Only you can check it.

1. Get the API password (32 characters) of a Packeta stage account.
2. Point the plugin to Packeta stage on your stack.
   The plugin has the SOAP WSDL in the constant `PACKETA_WSDL` in `media/admin/com_virtuemart/models/zasilkovna.php`.
   PHP sends each SOAP call to the `<soap:address>` inside the WSDL.
   Make sure that no `<soap:address>` contains a production host.
   Do not commit this change.
3. Set the stage API password and an empty sender label in the module configuration.
   An empty sender label uses the default sender of the stage account.
4. Run the tests with `PACKETA_STAGE_API_PASS='<stage API password>'`.
5. Cancel each packet that the tests created. See [Cancel the stage packets](#cancel-the-stage-packets).

### Cancel the stage packets

The tests do not cancel the packets.
The run prints each packet ID (`Packet on Packeta stage (cancel it after the run): ...`) and the HTML report shows it as the annotation "packet".
`.state/main-flows.json` keeps the IDs of all runs (`orders.<key>.packetId`, `previousPackets`). Delete an entry after you cancel its packet.
Cancel each packet on Packeta stage only: the SOAP method `cancelPacket`, or the client section of the stage account.

## Manual carrier update and the production carrier feed

The step "manual carrier update" reads the carrier feed from production (`https://pickup-point.api.packeta.com`), with the API key of the module configuration.
The feed has no stage variant. The step only reads the feed. It never writes to production.

## Environment variables

Required: `VIRTUEMART_BASE_URL`, `VM_ADMIN_PASS`. Optional: `MAILPIT_URL`, `PACKETA_STAGE_API_PASS` (see [Quick start](#quick-start)).
Change the others only when your stack has other values:

| Variable | Default | Use |
|----------|---------|-----|
| `VM_ADMIN_USER` | `admin` | Joomla administrator. |
| `VM_STORE_USER` / `VM_STORE_PASS` | the admin values | Storefront customer. |
| `VM_PRODUCT_ID` / `VM_PRODUCT_WEIGHT_KG` | `1` / `1.5` | Product for the cart and its weight. |
| `VM_COUNTRY_ID` / `VM_COUNTRY_SK_ID` | `56` / `189` | Billing country (CZ) and second country (SK). |
| `VM_PICKUP_POINT_ID` / `VM_ZBOX_ID` | `79` / `8167` | Pickup point and Z-BOX. They must exist on Packeta stage. |
| `VM_HD_CARRIER_ID` | `106` with the stage password, else the first Czech carrier | Home-delivery carrier. |
| `VM_HD_EXT_CARRIER_ID` / `VM_HD_EXT_COUNTRY_ID` | `6830` / `14` | External carrier (AT DPD HD) and its country (AT). |
| `PACKETA_PLUGIN_VERSION` | the version in `zasilkovna.xml` | Version that the test "Installation" expects. |
| `PACKETA_E2E_RUN_ID` | a new value for each run | Set it to continue an earlier run. |

## Side effects

- The tests create shipment and payment methods `E2E Packeta …` and publish the country `VM_HD_EXT_COUNTRY_ID`. They stay after the run. A second run uses them again.
- The run sets the COD flag of `E2E Packeta COD` and clears it on `E2E Packeta standard` (other payment methods stay as they are).
  The test "Cleanup" restores the original flags from `.state/cod-flags-original.json`.
  When you run only a part of the file, the flags stay changed until the next full run.
- The test "Checkout" adds new unpublished methods in each run. The tests do not delete methods. When two methods have the same name, the tests stop. Delete the duplicate.
- Each run creates orders. With `PACKETA_STAGE_API_PASS`, it also creates packets on Packeta stage. Cancel them after the run.
