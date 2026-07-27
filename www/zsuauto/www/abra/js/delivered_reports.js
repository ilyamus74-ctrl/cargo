(function () {
    'use strict';

    function addDeliveredReportButtons() {
        var tables = document.querySelectorAll('table.datatable');

        Array.prototype.forEach.call(tables, function (table) {
            var headers = table.querySelectorAll('thead th');
            var actionIndex = -1;

            Array.prototype.forEach.call(headers, function (header, index) {
                if (header.textContent.trim() === 'Робота з заявкою') {
                    actionIndex = index;
                }
            });

            if (actionIndex < 0) {
                return;
            }

            Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (row) {
                if (row.querySelector('.js-public-report-btn')) {
                    return;
                }

                var idCell = row.querySelector('th[scope="row"]');
                var actionCell = row.cells[actionIndex];
                if (!idCell || !actionCell) {
                    return;
                }

                var match = idCell.textContent.match(/\d+/);
                if (!match) {
                    return;
                }

                var link = document.createElement('a');
                link.className = 'btn btn-warning btn-sm ms-1 js-public-report-btn';
                link.href = '/abra/request_public_report.php?request_id=' + encodeURIComponent(match[0]);
                link.title = 'Звіт для вебсторінки «Передані авто»';
                link.setAttribute('aria-label', link.title);
                link.innerHTML = '<i class="bi bi-images"></i>';
                actionCell.appendChild(link);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', addDeliveredReportButtons);
})();
