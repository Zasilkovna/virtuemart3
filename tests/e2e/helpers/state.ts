import fs from 'fs';
import path from 'path';

/** Run state. It survives a worker restart after a failed test. */

export type OrderRef = { number: string; id: string; packetId?: string };
/** Packet of an earlier run. */
export type PacketRef = { runId: string; key: string; orderNumber: string; packetId: string };
export type RunState = {
    runId: string;
    /**
     * Packets of the earlier runs. The tests do not cancel packets, so a new run keeps these IDs.
     * Delete an entry after you cancel the packet on Packeta stage.
     */
    previousPackets?: PacketRef[];
    methods: Record<string, string>;
    payments: { standard?: string; cod?: string };
    /** Text of the HD carrier option in the method form. The Packeta list check compares with it. */
    hdCarrierName?: string;
    /** Text of the HD EXT carrier option in the method form. The Packeta list check compares with it. */
    hdExtCarrierName?: string;
    /** Why prepareStack did not create the HD EXT method. */
    hdExtError?: string;
    codReady: boolean;
    orders: Record<string, OrderRef>;
};

const STATE_DIR = path.join(__dirname, '..', '.state');
const STATE_FILE = path.join(STATE_DIR, 'main-flows.json');
/**
 * COD flags before prepareStack changed them. This file does not depend on the run ID.
 * Thus, a new run does not lose the original flags when the test "Cleanup" of an earlier run did not run.
 * prepareStack writes it only when it does not exist. The test "Cleanup" restores the flags and then deletes it.
 */
const COD_FLAGS_FILE = path.join(STATE_DIR, 'cod-flags-original.json');
/** playwright.config.ts sets PACKETA_E2E_RUN_ID once per run, so each worker gets the same value. */
const RUN_ID = process.env.PACKETA_E2E_RUN_ID ?? Date.now().toString(36);

export function readState(): RunState {
    const earlier = fs.existsSync(STATE_FILE) ? (JSON.parse(fs.readFileSync(STATE_FILE, 'utf8')) as RunState) : null;
    if (earlier?.runId === RUN_ID) return earlier;
    const fresh: RunState = {
        runId: RUN_ID,
        previousPackets: earlier ? carriedPackets(earlier) : [],
        methods: {},
        payments: {},
        codReady: false,
        orders: {},
    };
    fs.mkdirSync(STATE_DIR, { recursive: true });
    fs.writeFileSync(STATE_FILE, JSON.stringify(fresh, null, 2));
    return fresh;
}

/**
 * Packet IDs that a new run must keep: the packets of the earlier run and the packets that the earlier run kept.
 * Why: a new run writes a new state file. Without this list, the IDs of packets that nobody cancelled are lost.
 */
function carriedPackets(earlier: RunState): PacketRef[] {
    const packets = Object.entries(earlier.orders ?? {})
        .filter(([, order]) => order.packetId)
        .map(([key, order]) => ({ runId: earlier.runId, key, orderNumber: order.number, packetId: order.packetId as string }));
    return [...(earlier.previousPackets ?? []), ...packets];
}

export function updateState(change: (state: RunState) => void): RunState {
    const state = readState();
    change(state);
    fs.writeFileSync(STATE_FILE, JSON.stringify(state, null, 2));
    return state;
}

export function readOriginalCodFlags(): Record<string, boolean> | null {
    if (!fs.existsSync(COD_FLAGS_FILE)) return null;
    return JSON.parse(fs.readFileSync(COD_FLAGS_FILE, 'utf8')) as Record<string, boolean>;
}

/** Writes the flags only when the file does not exist. A file of an earlier run without "Cleanup" has the real original flags. */
export function keepOriginalCodFlags(flags: Record<string, boolean>): void {
    if (fs.existsSync(COD_FLAGS_FILE)) return;
    fs.mkdirSync(STATE_DIR, { recursive: true });
    fs.writeFileSync(COD_FLAGS_FILE, JSON.stringify(flags, null, 2));
}

export function deleteOriginalCodFlags(): void {
    fs.unlinkSync(COD_FLAGS_FILE);
}
