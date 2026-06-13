const chartColors = {
    blue: '#2563eb',
    blueSoft: 'rgba(37, 99, 235, 0.14)',
    green: '#16a34a',
    amber: '#f59e0b',
    red: '#dc2626',
    slate: '#475569',
    purple: '#7c3aed'
};

function currentDashboardUrl() {
    return new URL('attendance_dashboard.php', window.location.href);
}

function filterByClub(clubName) {
    const url = currentDashboardUrl();
    const current = new URLSearchParams(window.location.search);
    current.forEach((value, key) => {
        if (value !== '' && key !== 'club_name') {
            url.searchParams.set(key, value);
        }
    });
    url.searchParams.set('club_name', clubName);
    window.location.href = url.toString();
}

function emptyChartPlugin(message) {
    return {
        id: 'emptyState',
        afterDraw(chart) {
            const hasData = chart.data.datasets.some((dataset) =>
                dataset.data.some((value) => Number(value) > 0)
            );
            if (hasData) {
                return;
            }

            const { ctx, chartArea } = chart;
            if (!chartArea) {
                return;
            }

            ctx.save();
            ctx.font = '600 14px Segoe UI, sans-serif';
            ctx.fillStyle = '#64748b';
            ctx.textAlign = 'center';
            ctx.fillText(message, (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2);
            ctx.restore();
        }
    };
}

Chart.defaults.font.family = "'Segoe UI', Tahoma, sans-serif";
Chart.defaults.color = '#475569';

const barCanvas = document.getElementById('barChart');
if (barCanvas) {
    new Chart(barCanvas, {
        type: 'bar',
        data: {
            labels: barChartLabels,
            datasets: [{
                label: 'Attendance Rate',
                data: barChartData,
                backgroundColor: chartColors.blue,
                borderRadius: 8,
                maxBarThickness: 52
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
            },
            onClick: (event, elements) => {
                if (!elements.length) {
                    return;
                }
                filterByClub(barChartLabels[elements[0].index]);
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.parsed.y}% attendance`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 30, minRotation: 0 }
                },
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20,
                        callback: (value) => `${value}%`
                    }
                }
            }
        },
        plugins: [emptyChartPlugin('No attendance data found')]
    });
}

const donutCanvas = document.getElementById('donutChart');
if (donutCanvas) {
    new Chart(donutCanvas, {
        type: 'doughnut',
        data: {
            labels: donutChartLabels,
            datasets: [{
                label: 'Participants',
                data: donutChartData,
                backgroundColor: [
                    chartColors.green,
                    chartColors.amber,
                    chartColors.red,
                    chartColors.slate
                ],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8
                    }
                }
            }
        },
        plugins: [emptyChartPlugin('No attendance status yet')]
    });
}

const lineCanvas = document.getElementById('lineChart');
if (lineCanvas) {
    new Chart(lineCanvas, {
        type: 'line',
        data: {
            labels: lineChartLabels,
            datasets: [{
                label: 'Registered Participants',
                data: lineChartData,
                borderColor: chartColors.purple,
                backgroundColor: 'rgba(124, 58, 237, 0.12)',
                pointBackgroundColor: '#ffffff',
                pointBorderColor: chartColors.purple,
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.parsed.y} registered participants`
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        },
        plugins: [emptyChartPlugin('No monthly registrations found')]
    });
}

const eventCanvas = document.getElementById('eventChart');
if (eventCanvas) {
    new Chart(eventCanvas, {
        type: 'bar',
        data: {
            labels: eventChartLabels,
            datasets: [{
                label: 'Registered Participants',
                data: eventChartData,
                backgroundColor: chartColors.blue,
                borderRadius: 8,
                maxBarThickness: 52
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.parsed.y} registered participants`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 30, minRotation: 0 }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        },
        plugins: [emptyChartPlugin('No event registrations found')]
    });
}

document.querySelectorAll('[data-club]').forEach((row) => {
    row.addEventListener('click', () => filterByClub(row.dataset.club));
});
