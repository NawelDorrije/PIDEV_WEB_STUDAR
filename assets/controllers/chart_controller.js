/*import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';

export default class extends Controller {
    connect() {
        const data = JSON.parse(this.element.dataset.chartDataValue);
        new Chart(this.element, {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });
    }
}*/