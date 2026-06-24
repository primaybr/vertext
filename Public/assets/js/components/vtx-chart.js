/* vtx-chart.js - Chart.js v4 wrapper for Vertext CMS */
(function (root) {
    'use strict';

    var CHART_JS  = (window.VTX_ASSETS_URL || '') + 'js/vendors/chart.min.js';
    var _loaded   = false;
    var _queue    = [];

    function loadChartJs(cb) {
        if (_loaded) { cb(); return; }
        _queue.push(cb);
        if (_queue.length > 1) return;
        var s   = document.createElement('script');
        s.src   = CHART_JS;
        s.onload = function () {
            _loaded = true;
            _queue.forEach(function (fn) { fn(); });
            _queue = [];
        };
        document.head.appendChild(s);
    }

    /**
     * VtxChart
     *
     * Mount on a <canvas> with data attributes:
     *   data-vtx-chart
     *   data-type="line|bar|doughnut"
     *   data-labels='["Jan","Feb",...]'
     *   data-values='[10,20,...]'
     *   data-label="Posts published"
     *   data-color="#6366f1"        (optional, defaults to primary)
     *   data-fill="true"            (optional, for line charts)
     */
    function VtxChart(opts) {
        this.canvas = opts.canvas;
        this._chart = null;
        this._init();
        if (window.Vtx) Vtx._register('chart', this);
    }

    VtxChart.prototype._init = function () {
        var self   = this;
        var canvas = this.canvas;
        var type   = canvas.dataset.type   || 'line';
        var labels = JSON.parse(canvas.dataset.labels || '[]');
        var values = JSON.parse(canvas.dataset.values || '[]');
        var label  = canvas.dataset.label  || 'Data';
        var color  = canvas.dataset.color  || getComputedStyle(document.documentElement)
                         .getPropertyValue('--ps-primary').trim() || '#6366f1';
        var fill   = canvas.dataset.fill   === 'true';

        loadChartJs(function () {
            var isDark  = document.documentElement.getAttribute('data-theme') === 'dark';
            var gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
            var textColor = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.45)';

            var dataset = {
                label:           label,
                data:            values,
                borderColor:     color,
                backgroundColor: fill
                    ? color.replace(')', ', .12)').replace('rgb(', 'rgba(')
                    : color,
                borderWidth:     2,
                pointRadius:     3,
                pointHoverRadius: 5,
                tension:         0.4,
                fill:            fill
            };

            var options = {
                responsive:          true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: type !== 'line' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: type !== 'doughnut' ? {
                    x: {
                        grid:  { color: gridColor },
                        ticks: { color: textColor, maxTicksLimit: 8 }
                    },
                    y: {
                        beginAtZero: true,
                        grid:  { color: gridColor },
                        ticks: { color: textColor, precision: 0 }
                    }
                } : {}
            };

            self._chart = new Chart(canvas, {
                type:    type,
                data:    { labels: labels, datasets: [dataset] },
                options: options
            });
        });
    };

    VtxChart.prototype.update = function (labels, values) {
        if (!this._chart) return;
        this._chart.data.labels          = labels;
        this._chart.data.datasets[0].data = values;
        this._chart.update();
    };

    VtxChart.prototype.destroy = function () {
        if (this._chart) { this._chart.destroy(); this._chart = null; }
    };

    root.VtxChart = VtxChart;

    // Auto-init all [data-vtx-chart] canvases
    document.querySelectorAll('canvas[data-vtx-chart]').forEach(function (canvas) {
        if (!canvas._vtxChart) canvas._vtxChart = new VtxChart({ canvas: canvas });
    });

}(window));
