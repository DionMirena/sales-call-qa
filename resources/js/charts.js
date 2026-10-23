import Chart from 'chart.js/auto';

// Colour palette
const OUTCOME_COLORS = {
    pass:      '#10b981',
    fail:      '#f59e0b',
    auto_fail: '#ef4444',
};
const AGENT_PALETTE = ['#6366f1','#8b5cf6','#06b6d4','#f59e0b','#10b981','#ec4899','#f97316'];
const BRAND         = '#6366f1';

const tooltipDefaults = {
    backgroundColor: '#1e293b',
    titleColor: '#e2e8f0',
    bodyColor: '#cbd5e1',
    padding: 10,
    cornerRadius: 8,
    displayColors: true,
};

function legendDefaults() {
    return {
        position: 'bottom',
        labels: { padding: 16, boxWidth: 12, color: '#64748b' },
    };
}

function axisTick() {
    return { color: '#94a3b8', font: { size: 11 } };
}

function gridStyle() {
    return { color: '#f1f5f9' };
}

export function donut(canvas, labels, data, colors) {
    if (!labels.length) return null;
    return new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#f8fafc',
                hoverBorderColor: '#fff',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: legendDefaults(),
                tooltip: {
                    ...tooltipDefaults,
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct   = total ? Math.round((ctx.raw / total) * 100) : 0;
                            return `  ${ctx.label}: ${ctx.raw} (${pct}%)`;
                        },
                    },
                },
            },
            cutout: '62%',
        },
    });
}

export function outcomesDonut(canvas, data) {
    const entries = Object.entries(data).filter(([, v]) => v > 0);
    if (!entries.length) return null;
    const labels = entries.map(([k]) => ({ pass: 'Pass', fail: 'Fail', auto_fail: 'Auto-fail' }[k] ?? k));
    const values = entries.map(([, v]) => v);
    const colors = entries.map(([k]) => OUTCOME_COLORS[k] ?? BRAND);
    return donut(canvas, labels, values, colors);
}

export function agentsDonut(canvas, data) {
    if (!data.length) return null;
    const labels = data.map(d => d.name);
    const values = data.map(d => d.count);
    const colors = data.map((_, i) => AGENT_PALETTE[i % AGENT_PALETTE.length]);
    return donut(canvas, labels, values, colors);
}

export function agentScoreBar(canvas, data) {
    if (!data.length) return null;
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels:   data.map(d => d.name),
            datasets: [{
                label: 'Avg score',
                data:  data.map(d => Math.round(d.avg * 10) / 10),
                backgroundColor: data.map(d =>
                    d.avg >= 80 ? '#10b981' : d.avg >= 60 ? '#f59e0b' : '#ef4444'
                ),
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipDefaults,
                    callbacks: {
                        label: ctx => `  ${ctx.raw}%`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: axisTick() },
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: gridStyle(),
                    ticks: { ...axisTick(), callback: v => v + '%' },
                },
            },
        },
    });
}

export function failedCriteriaBar(canvas, data) {
    if (!data.length) return null;
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels:   data.map(d => d.name),
            datasets: [{
                label: 'Failures',
                data:  data.map(d => d.count),
                backgroundColor: '#ef4444',
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: { ...tooltipDefaults },
            },
            scales: {
                x: {
                    grid: gridStyle(),
                    ticks: { ...axisTick(), callback: v => v + (v === 1 ? ' fail' : ' fails') },
                },
                y: { grid: { display: false }, ticks: axisTick() },
            },
        },
    });
}

export function scoreTrendLine(canvas, data) {
    if (!data.length) return null;
    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.map(d => d.date),
            datasets: [{
                label: 'Avg score',
                data:  data.map(d => d.avg !== null ? Math.round(d.avg * 10) / 10 : null),
                borderColor: BRAND,
                backgroundColor: BRAND + '18',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: BRAND,
                spanGaps: true,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipDefaults,
                    callbacks: {
                        label: ctx => `  Avg score: ${ctx.raw}%`,
                        afterLabel: (ctx) => {
                            const pt = data[ctx.dataIndex];
                            return pt ? `  Calls: ${pt.count}` : '';
                        },
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: axisTick() },
                y: {
                    min: 0,
                    max: 100,
                    grid: gridStyle(),
                    ticks: { ...axisTick(), callback: v => v + '%' },
                },
            },
        },
    });
}

// Register on window so inline script tags can call SalesQaCharts.*
window.SalesQaCharts = { donut, outcomesDonut, agentsDonut, agentScoreBar, failedCriteriaBar, scoreTrendLine };
