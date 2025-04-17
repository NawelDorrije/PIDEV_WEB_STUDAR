document.addEventListener('DOMContentLoaded', function() {
    fetch('/gestiontransport/stats')
        .then(response => response.json())
        .then(data => {
            console.log("Received stats data:", data);
            initCharts(data);
        })
        .catch(error => console.error('Error fetching stats:', error));
});

function initCharts(data) {
    console.log("Initializing charts with:", data);
    
    // Vehicles Chart
    if (data.vehicles && data.vehicles.labels) {
        new Chart(
            document.getElementById('vehiclesChart').getContext('2d'),
            createChartConfig('bar', 'Véhicules Ajoutés', data.vehicles)
        );
    }

    // Transports Chart
    if (data.transports && data.transports.labels) {
        const transportsCtx = document.getElementById('transportsChart').getContext('2d');
        new Chart(transportsCtx, {
            type: 'line',
            data: {
                labels: data.transports.labels,
                datasets: [
                    createDataset('Complété', data.transports.completed, '#28a745'),
                    createDataset('Actif', data.transports.active, '#ffc107')
                ]
            },
            options: createChartOptions('Nombre de Transports')
        });
    }

    // Revenue Chart
    if (data.revenue && data.revenue.labels) {
        initRevenueChart(data.revenue);
    }
}

let revenueChart = null;
function initRevenueChart(data) {
    console.log("Revenue chart data:", data);
    

    if (!data || !data.labels || !data.values) {
        console.error("Invalid revenue data structure:", data);
        return;
    }

    console.log("Value types:", data.values.map(val => typeof val));

    // Ensure values are numbers
    const values = data.values.map(val => parseFloat(val) || 0);
    console.log("Processed revenue values:", data.values);

    // Calculate the maximum value for y-axis scaling
    const maxRevenue = Math.max(...values);
    const suggestedMax = maxRevenue > 0 ? maxRevenue * 1.1 : 10; // Add 10% padding, or default to 10 if all values are 0

    const ctx = document.getElementById('revenueChart').getContext('2d');
    console.log("Canvas context:", ctx);
    
    if (revenueChart) {
        revenueChart.destroy();
    }
    
    // Revenue Chart
    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Revenus (TND)',
                data: values,
                backgroundColor: '#6FE69B',
                borderColor: '#4CAF50',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y.toFixed(3) + ' TND';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: suggestedMax,
                    title: {
                        display: true,
                        text: 'Revenus (TND)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toFixed(3); // Ensure y-axis labels show 3 decimal places
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Mois'
                    }
                }
            }
        }
    });
}

function createChartOptions(title) {
    return {
        responsive: true,
        scales: {
            y: { beginAtZero: true, title: { display: true, text: title } },
            x: { title: { display: true, text: 'Mois' } }
        }
    };
}

function createDataset(label, data, color) {
    return {
        label: label,
        data: data,
        backgroundColor: color + '20',
        borderColor: color,
        borderWidth: 2,
        tension: 0.1
    };
}

function createChartConfig(type, label, data) {
    return {
        type: type,
        data: {
            labels: data.labels,
            datasets: [{
                label: label,
                data: data.values,
                backgroundColor: type === 'bar' ? 'rgba(54, 162, 235, 0.7)' : 'rgba(75, 192, 192, 0.2)',
                borderColor: type === 'bar' ? 'rgba(54, 162, 235, 1)' : 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { 
                    beginAtZero: true, 
                    title: { 
                        display: true, 
                        text: label 
                    } 
                },
                x: { 
                    title: { 
                        display: true, 
                        text: 'Mois' 
                    } 
                }
            }
        }
    };
}