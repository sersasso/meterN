/**
 * meterN Client-Side Application Scaffold
 *
 * Fetches configuration from config.json.php, initializes chart placeholders,
 * polls batched endpoints (programlive.php and programmeter.php), and
 * minimizes DOM thrashing through batched updates.
 *
 * @package meterN
 */

(function(window, document) {
    'use strict';

    /**
     * Default configuration values
     */
    var DEFAULTS = {
        pollInterval: 5000,         // 5 seconds
        configEndpoint: 'programs/config.json.php',
        liveEndpoint: 'programs/programlive.php',
        meterEndpoint: 'programs/programmeter.php',
        chartContainerId: 'charts-container',
        liveContainerId: 'live-container'
    };

    /**
     * Application state
     */
    var state = {
        config: null,
        meters: [],
        locale: {},
        settings: {},
        tokens: {},
        pollTimers: {},
        charts: {},
        initialized: false
    };

    /**
     * Fetch JSON from an endpoint with error handling
     *
     * @param {string} url - The URL to fetch
     * @param {function} callback - Callback function(error, data)
     */
    function fetchJSON(url, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        callback(null, data);
                    } catch (e) {
                        callback(new Error('Failed to parse JSON response'), null);
                    }
                } else {
                    callback(new Error('HTTP ' + xhr.status + ': ' + xhr.statusText), null);
                }
            }
        };

        xhr.onerror = function() {
            callback(new Error('Network error'), null);
        };

        xhr.send();
    }

    /**
     * Initialize the application by fetching configuration
     *
     * @param {object} options - Override options
     */
    function init(options) {
        options = options || {};

        var configUrl = options.configEndpoint || DEFAULTS.configEndpoint;

        fetchJSON(configUrl, function(err, data) {
            if (err) {
                console.error('meterN: Failed to load configuration:', err);
                return;
            }

            state.config = data;
            state.meters = data.meters || [];
            state.locale = data.locale || {};
            state.settings = data.settings || {};
            state.tokens = data.tokens || {};
            state.initialized = true;

            // Initialize UI components
            initChartPlaceholders();
            initLivePlaceholders();

            // Start polling
            startPolling();

            // Trigger custom event for extensions
            if (typeof CustomEvent === 'function') {
                document.dispatchEvent(new CustomEvent('meternReady', { detail: state }));
            }
        });
    }

    /**
     * Create chart placeholder elements for each meter graph group
     */
    function initChartPlaceholders() {
        var container = document.getElementById(DEFAULTS.chartContainerId);
        if (!container) {
            return;
        }

        // Group meters by graph number
        var graphGroups = {};
        state.meters.forEach(function(meter) {
            var graphNum = meter.graph || 0;
            if (graphNum > 0) {
                if (!graphGroups[graphNum]) {
                    graphGroups[graphNum] = [];
                }
                graphGroups[graphNum].push(meter);
            }
        });

        // Create placeholder for each graph group using DocumentFragment for batching
        var fragment = document.createDocumentFragment();

        Object.keys(graphGroups).forEach(function(graphNum) {
            var div = document.createElement('div');
            div.id = 'chart-' + graphNum;
            div.className = 'chart-placeholder';
            div.setAttribute('data-graph', graphNum);

            var title = document.createElement('h3');
            title.className = 'chart-title';
            title.textContent = state.tokens.lgLOADING || 'Loading...';
            div.appendChild(title);

            var chartArea = document.createElement('div');
            chartArea.className = 'chart-area';
            chartArea.id = 'chart-area-' + graphNum;
            div.appendChild(chartArea);

            fragment.appendChild(div);
        });

        container.appendChild(fragment);
    }

    /**
     * Create live data placeholder elements for each meter
     */
    function initLivePlaceholders() {
        var container = document.getElementById(DEFAULTS.liveContainerId);
        if (!container) {
            return;
        }

        // Use DocumentFragment for batched DOM updates
        var fragment = document.createDocumentFragment();

        state.meters.forEach(function(meter) {
            var div = document.createElement('div');
            div.id = 'live-' + meter.index;
            div.className = 'live-placeholder';
            div.setAttribute('data-meter', meter.index);

            var label = document.createElement('span');
            label.className = 'live-label';
            label.textContent = meter.name + ': ';
            div.appendChild(label);

            var value = document.createElement('span');
            value.className = 'live-value';
            value.id = 'live-value-' + meter.index;
            value.textContent = '--';
            div.appendChild(value);

            var unit = document.createElement('span');
            unit.className = 'live-unit';
            unit.textContent = ' ' + (meter.liveUnit || meter.unit);
            div.appendChild(unit);

            fragment.appendChild(div);
        });

        container.appendChild(fragment);
    }

    /**
     * Start polling for live data and meter data
     */
    function startPolling() {
        var interval = state.settings.pollInterval || DEFAULTS.pollInterval;

        // Poll live data
        pollLiveData();
        state.pollTimers.live = setInterval(pollLiveData, interval);

        // Poll meter data (less frequently, every 30 seconds)
        pollMeterData();
        state.pollTimers.meter = setInterval(pollMeterData, interval * 6);
    }

    /**
     * Stop all polling
     */
    function stopPolling() {
        if (state.pollTimers.live) {
            clearInterval(state.pollTimers.live);
        }
        if (state.pollTimers.meter) {
            clearInterval(state.pollTimers.meter);
        }
        state.pollTimers = {};
    }

    /**
     * Poll live data endpoint and update display
     */
    function pollLiveData() {
        fetchJSON(DEFAULTS.liveEndpoint, function(err, data) {
            if (err) {
                console.warn('meterN: Failed to fetch live data:', err);
                return;
            }

            updateLiveDisplay(data);
        });
    }

    /**
     * Poll meter data endpoint and update charts
     */
    function pollMeterData() {
        fetchJSON(DEFAULTS.meterEndpoint, function(err, data) {
            if (err) {
                console.warn('meterN: Failed to fetch meter data:', err);
                return;
            }

            updateCharts(data);
        });
    }

    /**
     * Update live display with new data (minimizes DOM thrashing)
     *
     * @param {object} data - Live data response
     */
    function updateLiveDisplay(data) {
        if (!data) {
            return;
        }

        // Batch all updates
        var updates = [];

        state.meters.forEach(function(meter) {
            var key = meter.name + meter.index;
            var value = data[key];

            if (value !== undefined) {
                updates.push({
                    id: 'live-value-' + meter.index,
                    value: formatNumber(value, meter.precision)
                });
            }
        });

        // Apply all updates in one batch using requestAnimationFrame
        if (updates.length > 0) {
            requestAnimationFrame(function() {
                updates.forEach(function(update) {
                    var el = document.getElementById(update.id);
                    if (el) {
                        el.textContent = update.value;
                    }
                });
            });
        }

        // Update timestamp if present
        if (data.stamp) {
            var stampEl = document.getElementById('live-timestamp');
            if (stampEl) {
                stampEl.textContent = data.stamp;
            }
        }
    }

    /**
     * Update chart displays with new data
     *
     * @param {object} data - Meter data response
     */
    function updateCharts(data) {
        if (!data || !data.title) {
            return;
        }

        // Update chart titles (batched)
        requestAnimationFrame(function() {
            data.title.forEach(function(title, index) {
                var titleEl = document.querySelector('#chart-' + (index + 1) + ' .chart-title');
                if (titleEl && title) {
                    titleEl.textContent = title;
                }
            });
        });

        // Trigger event for Highcharts integration
        if (typeof CustomEvent === 'function') {
            document.dispatchEvent(new CustomEvent('meternChartData', { detail: data }));
        }
    }

    /**
     * Format a number according to locale settings
     *
     * @param {number} value - The number to format
     * @param {number} precision - Decimal places
     * @return {string} Formatted number
     */
    function formatNumber(value, precision) {
        if (value === null || value === undefined || isNaN(value)) {
            return '--';
        }

        precision = precision || 0;
        var decimalPoint = state.locale.decimalPoint || '.';
        var thousandsSep = state.locale.thousandsSep || ',';

        var fixed = parseFloat(value).toFixed(precision);
        var parts = fixed.split('.');

        // Add thousands separator
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);

        // Join with locale decimal point
        return parts.length > 1 ? parts[0] + decimalPoint + parts[1] : parts[0];
    }

    /**
     * Get current application state
     *
     * @return {object} Current state
     */
    function getState() {
        return state;
    }

    /**
     * Check if application is initialized
     *
     * @return {boolean} Initialization status
     */
    function isReady() {
        return state.initialized;
    }

    // Export public API
    window.meterN = {
        init: init,
        getState: getState,
        isReady: isReady,
        startPolling: startPolling,
        stopPolling: stopPolling,
        pollLiveData: pollLiveData,
        pollMeterData: pollMeterData,
        formatNumber: formatNumber
    };

    // Auto-initialize on DOMContentLoaded if config endpoint exists
    document.addEventListener('DOMContentLoaded', function() {
        // Check if auto-init is disabled
        var script = document.querySelector('script[data-metern-no-auto]');
        if (script) {
            return;
        }

        // Auto-initialize
        meterN.init();
    });

})(window, document);
