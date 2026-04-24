/**
 * @file starmus-sparxstar-integration.js
 * @version 7.2.0
 * @description Integration layer between Sparxstar UEC and Starmus Recorder.
 * Maps environment profiles to recorder tiers and manages data caching.
 * Includes battery status monitoring and network-aware configuration for
 * African mobile markets (2G/3G-first design).
 */

"use strict";

/* 1. WP/ACF INTEGRATION LOGIC */
(function ($) {
    /**
     * Initializes ACF and AIWA recorder bridge once the DOM is ready.
     *
     * @function attachAiwaRecorderHandlers
     * @returns {void}
     */
    function attachAiwaRecorderHandlers() {
        const acfInstance = typeof window.acf !== "undefined" ? window.acf : null;
        const tinyMCEInstance = typeof window.tinyMCE !== "undefined" ? window.tinyMCE : null;
        const aiwaRecorderData =
            typeof window.aiwa_recorder_data !== "undefined" ? window.aiwa_recorder_data : null;

        if (!$ || !$.fn) {
            console.warn("[SparxstarIntegration] jQuery missing; recorder bridge skipped.");
            return;
        }

        // Listen for the ACF AJAX success event
        if (acfInstance) {
            /**
             * Handles ACF AJAX post creation success.
             *
             * @function handleAcfAjaxSuccess
             * @param {Object} response - ACF AJAX response payload
             * @returns {void}
             */
            acfInstance.add_action("wp_ajax_success", function handleAcfAjaxSuccess(response) {
                if (response && response.data && response.data.post_id) {
                    const newTargetId = response.data.post_id;
                    const titleText = $("#acf-_post_title").val();
                    let contentText = "";

                    if (tinyMCEInstance && tinyMCEInstance.get("acf-editor-58")) {
                        contentText = tinyMCEInstance
                            .get("acf-editor-58")
                            .getContent({ format: "text" });
                    } else {
                        contentText = $("#acf-_post_content").val();
                    }

                    /**
                     * Handles transition to recorder step after ACF save.
                     *
                     * @function handleStepOneFade
                     * @returns {void}
                     */
                    $("#aiwa-step-1").fadeOut(300, function handleStepOneFade() {
                        $("#script-title").text(titleText);
                        $("#script-content").text(contentText);
                        $("#aiwa-step-2").fadeIn(300);

                        /**
                         * Processes recorder loader AJAX success.
                         *
                         * @function handleAjaxSuccess
                         * @param {string} html - Rendered recorder markup
                         * @returns {void}
                         */
                        $.ajax({
                            url: aiwaRecorderData
                                ? aiwaRecorderData.ajax_url
                                : "/wp-admin/admin-ajax.php",
                            type: "POST",
                            data: {
                                action: "aiwa_load_prompter_recorder",
                                target_post_id: newTargetId,
                                audio_post_id: 0,
                            },
                            success: function handleAjaxSuccess(html) {
                                $("#aiwa-recorder-load-point").html(html);
                            },
                        });
                    });
                }
            });
        }

        if (typeof $.fn.on === "function") {
            /**
             * Handles recorder completion notifications from child frames.
             *
             * @function handleRecorderComplete
             * @param {Object} event - Message event wrapper provided by jQuery
             * @returns {void}
             */
            $(window).on("message", function handleRecorderComplete(event) {
                const data = event.originalEvent.data;
                if (data && data.type === "starmusRecordingComplete") {
                    $("#aiwa-step-2").html(`
            <div style="text-align:center; padding:50px;">
                <h2 style="color:green;">✓ Saved and Recorded</h2>
                <p>Your entry and audio have been successfully linked.</p>
                <button onclick="window.location.reload();" class="button button-large">Add Another Entry</button>
            </div>
          `);
                }
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", attachAiwaRecorderHandlers);
    } else {
        attachAiwaRecorderHandlers();
    }
})(typeof jQuery !== "undefined" ? jQuery : null);

/* 2. BATTERY STATUS CACHE */

/**
 * Cached battery state populated by {@link _readBattery}.
 * Defaults to a safe "full and charging" assumption so that
 * isBatteryCritical() returns false before the API responds.
 *
 * @private
 * @type {{ level: number, charging: boolean, lastUpdated: number }}
 */
const _batteryCache = { level: 1, charging: true, lastUpdated: 0 };

/**
 * Subscribes to the Battery Status API and keeps _batteryCache current.
 * Attaches `levelchange` and `chargingchange` event listeners so the cache
 * stays fresh without polling. Safe to call in browsers that lack the API.
 *
 * @private
 * @async
 * @returns {Promise<void>}
 */
async function _readBattery() {
    if (!("getBattery" in navigator)) {
        return;
    }
    try {
        const battery = await navigator.getBattery();
        _batteryCache.level = battery.level;
        _batteryCache.charging = battery.charging;
        _batteryCache.lastUpdated = Date.now();

        battery.addEventListener("levelchange", () => {
            _batteryCache.level = battery.level;
            _batteryCache.lastUpdated = Date.now();
        });
        battery.addEventListener("chargingchange", () => {
            _batteryCache.charging = battery.charging;
            _batteryCache.lastUpdated = Date.now();
        });
    } catch (e) {
        void e; // Battery API unavailable — safe defaults already set above.
    }
}

/* 3. NETWORK HELPERS */

/**
 * Per-connection upload chunk sizes optimised for African network tiers.
 * Values are in bytes; slow-2g is deliberately tiny to avoid stalling.
 *
 * @private
 * @type {Object<string, number>}
 */
const _NETWORK_CHUNK_SIZES = {
    "slow-2g": 64 * 1024,
    "2g": 128 * 1024,
    "3g": 256 * 1024,
    "4g": 512 * 1024,
};

/**
 * Resolves the live Network Information object, with vendor prefix fallbacks.
 *
 * @private
 * @returns {NetworkInformation|null}
 */
function _getConnection() {
    return navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;
}

/**
 * Returns the effective network type string from the Network Information API,
 * or 'unknown' when the API is unavailable.
 *
 * @private
 * @returns {string}
 */
function _getEffectiveType() {
    return _getConnection()?.effectiveType || "unknown";
}

/**
 * Detects the browser capability tier when no Sparxstar environment data is
 * present. This mirrors the authoritative fallback logic described in
 * AUDIO-TIER-STANDARDS.md: C → no MediaRecorder, B → no AudioContext, A → full.
 *
 * @private
 * @returns {'A'|'B'|'C'}
 */
function _detectTier() {
    if (
        typeof window.MediaRecorder === "undefined" ||
        typeof navigator.mediaDevices?.getUserMedia !== "function"
    ) {
        return "C";
    }
    if (
        typeof window.AudioContext === "undefined" &&
        typeof window.webkitAudioContext === "undefined"
    ) {
        return "B";
    }
    return "A";
}

/**
 * Resolves the upload chunk size based on the live effective network type.
 * Falls back to the tier-derived default when the network type is unknown.
 *
 * @private
 * @param {string} effectiveType - Value from NetworkInformation.effectiveType
 * @returns {number} Chunk size in bytes
 */
function _resolveChunkSize(effectiveType) {
    return _NETWORK_CHUNK_SIZES[effectiveType] ?? 512 * 1024;
}

/**
 * Per-country approximate mobile data cost per MB (USD) for African markets.
 * Costs are derived from prepaid 1 GB bundle prices divided by 1024.
 *
 * Sources: GSMA Intelligence, Alliance for Affordable Internet (A4AI) 2024.
 * Review and update these figures annually.
 *
 * @private
 * @type {Object<string, number>}
 */
const _COUNTRY_COST_PER_MB = {
    BF: 0.17, // Burkina Faso
    CD: 0.18, // Dem. Rep. Congo
    CI: 0.1, // Côte d'Ivoire
    CM: 0.13, // Cameroon
    ET: 0.11, // Ethiopia
    GH: 0.08, // Ghana
    GM: 0.15, // Gambia
    KE: 0.05, // Kenya
    MG: 0.11, // Madagascar
    ML: 0.18, // Mali
    MW: 0.14, // Malawi
    MZ: 0.12, // Mozambique
    NG: 0.06, // Nigeria
    RW: 0.07, // Rwanda
    SN: 0.1, // Senegal
    TZ: 0.09, // Tanzania
    UG: 0.12, // Uganda
    ZA: 0.04, // South Africa
    ZM: 0.09, // Zambia
    ZW: 0.16, // Zimbabwe
};

/* 4. SPARXSTAR INTEGRATION OBJECT */
/**
 * Provides integration hooks for Sparxstar and Starmus components.
 *
 * @global
 * @constant
 * @type {Object}
 */
const sparxstarIntegration = {
    /**
     * Indicates whether the integration layer is available.
     *
     * @type {boolean}
     */
    isAvailable: true,

    /**
     * Initializes integration: starts battery monitoring and resolves
     * current environment data.
     *
     * @function init
     * @returns {Promise<Object>} Resolved environment payload
     */
    init: () => {
        _readBattery();
        return Promise.resolve(sparxstarIntegration.getEnvironmentData());
    },

    /**
     * Returns a capability-detected environment data object.
     * Tier is derived from `window.MediaRecorder` / `AudioContext` availability
     * (see AUDIO-TIER-STANDARDS.md) and network settings are sourced from the
     * Network Information API when available.
     *
     * @function getEnvironmentData
     * @returns {{tier: string, recordingSettings: {uploadChunkSize: number}, network: {type: string, downlink: number, rtt: number}}}
     */
    getEnvironmentData: () => {
        const conn = _getConnection();
        const effectiveType = _getEffectiveType();
        const tier = _detectTier();

        return {
            tier,
            recordingSettings: {
                uploadChunkSize: _resolveChunkSize(effectiveType),
            },
            network: {
                type: effectiveType,
                downlink: conn?.downlink ?? 0.5,
                rtt: conn?.rtt ?? 300,
                saveData: conn?.saveData ?? false,
            },
        };
    },

    /**
     * Returns true when the battery level is below 20 % and not charging.
     * Reads the live cache maintained by _readBattery(), making this a
     * zero-cost synchronous call suitable for hot paths (e.g. processQueue).
     *
     * @function isBatteryCritical
     * @returns {boolean}
     */
    isBatteryCritical: () => {
        return _batteryCache.level < 0.2 && !_batteryCache.charging;
    },

    /**
     * Returns the approximate mobile data cost per MB (USD) for a given
     * ISO 3166-1 alpha-2 country code. Falls back to the Gambia default
     * (0.15 USD/MB) for unknown codes.
     *
     * @function getDataCostPerMb
     * @param {string} countryCode - ISO 3166-1 alpha-2 country code (e.g. 'GM')
     * @returns {number} Cost in USD per MB
     */
    getDataCostPerMb: (countryCode) => {
        const code = typeof countryCode === "string" ? countryCode.toUpperCase() : "";
        return Object.prototype.hasOwnProperty.call(_COUNTRY_COST_PER_MB, code)
            ? _COUNTRY_COST_PER_MB[code]
            : 0.15;
    },

    /**
     * Reports integration errors to the console.
     *
     * @function reportError
     * @param {string} msg - Message describing the error
     * @param {Object} data - Supplemental error data
     * @returns {void}
     */
    reportError: (msg, data) => {
        console.warn("[Integration] Error:", msg, data);
    },
};

export default sparxstarIntegration;
