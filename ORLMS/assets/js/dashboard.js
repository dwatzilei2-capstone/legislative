'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById(
        'statusDistributionChart'
    );

    const dashboardData =
        window.ORLMS_DASHBOARD_DATA || {};

    if (
        !canvas ||
        typeof Chart === 'undefined' ||
        !Array.isArray(dashboardData.statusLabels) ||
        dashboardData.statusLabels.length === 0
    ) {
        return;
    }

    new Chart(canvas, {
        type: 'doughnut',

        data: {
            labels: dashboardData.statusLabels,

            datasets: [{
                data: dashboardData.statusValues,

                backgroundColor: [
                    '#b8860b',
                    '#071426',
                    '#1a3a5c',
                    '#0284c7',
                    '#d97706',
                    '#059669',
                    '#64748b',
                    '#3b82f6',
                ],

                borderWidth: 0,
                hoverOffset: 5,
            }],
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            cutout: '63%',

            plugins: {
                legend: {
                    position: 'bottom',

                    labels: {
                        boxWidth: 11,
                        padding: 14,

                        font: {
                            size: 10,
                        },
                    },
                },
            },
        },
    });
});