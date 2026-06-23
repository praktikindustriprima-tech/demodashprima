import axios from 'axios';
import { readonly, ref, watch } from 'vue';
import type { Ref } from 'vue';

interface ExcludedSn {
    sn: string;
    notes: string | null;
}

const LS_PREFIX = 'olt-pref-';
const LS_TIMESTAMP = 'olt-pref-timestamp';

let fetchPromise: Promise<void> | null = null;
let watchersInitialized = false;
let saveTimeout: ReturnType<typeof setTimeout> | null = null;
let isHydrating = false;

const isLoaded = ref(false);
const autoReconnect: Ref<boolean> = ref(true);
const autoScanInterval: Ref<number> = ref(5);
const autoScanDefault: Ref<boolean> = ref(true);
const excludedSns: Ref<ExcludedSn[]> = ref([]);

function toPrefsObject() {
    return {
        auto_reconnect: autoReconnect.value,
        autoscan_interval: autoScanInterval.value,
        autoscan_default: autoScanDefault.value,
        excluded_sns: excludedSns.value,
    };
}

function saveToLocalStorage() {
    try {
        localStorage.setItem(
            LS_PREFIX + 'auto_reconnect',
            JSON.stringify(autoReconnect.value),
        );
        localStorage.setItem(
            LS_PREFIX + 'autoscan_interval',
            JSON.stringify(autoScanInterval.value),
        );
        localStorage.setItem(
            LS_PREFIX + 'autoscan_default',
            JSON.stringify(autoScanDefault.value),
        );
        localStorage.setItem(
            LS_PREFIX + 'excluded_sns',
            JSON.stringify(excludedSns.value),
        );
        localStorage.setItem(LS_TIMESTAMP, Date.now().toString());
    } catch {
        /* silent */
    }
}

function loadFromLocalStorage() {
    try {
        const reconnect = localStorage.getItem(LS_PREFIX + 'auto_reconnect');

        if (reconnect !== null) {
            autoReconnect.value = JSON.parse(reconnect);
        }

        const interval = localStorage.getItem(LS_PREFIX + 'autoscan_interval');

        if (interval !== null) {
            autoScanInterval.value = JSON.parse(interval);
        }

        const def = localStorage.getItem(LS_PREFIX + 'autoscan_default');

        if (def !== null) {
            autoScanDefault.value = JSON.parse(def);
        }

        const sns = localStorage.getItem(LS_PREFIX + 'excluded_sns');

        if (sns !== null) {
            excludedSns.value = JSON.parse(sns);
        }
    } catch {
        /* silent */
    }
}

async function saveToApi() {
    if (isHydrating) {
        return;
    }

    try {
        await axios.put('/olt/preferences', { batch: toPrefsObject() });
    } catch {
        /* silent */
    }
}

function scheduleSave() {
    if (isHydrating) {
        return;
    }

    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }

    saveTimeout = setTimeout(() => {
        saveToApi();
        saveToLocalStorage();
    }, 500);
}

function initWatchers() {
    if (watchersInitialized) {
        return;
    }

    watchersInitialized = true;
    watch(
        [autoReconnect, autoScanInterval, autoScanDefault, excludedSns],
        scheduleSave,
        { deep: true },
    );
}

function fetchFromApi() {
    if (fetchPromise) {
        return fetchPromise;
    }

    fetchPromise = (async () => {
        try {
            isHydrating = true;
            const response = await axios.get('/olt/preferences');

            if (response.data.status === 'success' && response.data.data) {
                const d = response.data.data;

                if (d.auto_reconnect !== undefined) {
                    autoReconnect.value = d.auto_reconnect;
                }

                if (d.autoscan_interval !== undefined) {
                    autoScanInterval.value = d.autoscan_interval;
                }

                if (d.autoscan_default !== undefined) {
                    autoScanDefault.value = d.autoscan_default;
                }

                if (d.excluded_sns !== undefined) {
                    excludedSns.value = Array.isArray(d.excluded_sns)
                        ? d.excluded_sns
                        : [];
                }

                saveToLocalStorage();
            }
        } catch {
            /* use cached values */
        } finally {
            isHydrating = false;
            isLoaded.value = true;
        }
    })();

    return fetchPromise;
}

loadFromLocalStorage();

function addExcludedSn(sn: string, notes: string | null = null) {
    const upperSn = sn.toUpperCase().trim();

    if (!upperSn || excludedSns.value.some((o) => o.sn === upperSn)) {
        return;
    }

    excludedSns.value = [{ sn: upperSn, notes }, ...excludedSns.value];
}

function removeExcludedSn(sn: string) {
    excludedSns.value = excludedSns.value.filter(
        (o) => o.sn !== sn.toUpperCase(),
    );
}

export function useOltPreferences() {
    initWatchers();
    fetchFromApi();

    return {
        isLoaded: readonly(isLoaded),
        autoReconnect,
        autoScanInterval,
        autoScanDefault,
        excludedSns,
        addExcludedSn,
        removeExcludedSn,
    };
}
