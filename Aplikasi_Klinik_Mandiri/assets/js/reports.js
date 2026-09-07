/**
 * KLINIK PRAKTEK MANDIRI - Reports & Export Module
 * Charts (Chart.js), PDF (jsPDF), Excel (SheetJS)
 */

let revenueChart = null;
let genderChart = null;
let ageChart = null;
let diagnosesChart = null;

const chartColors = {
    primary: '#2F4156',
    secondary: '#567C8D',
    accent: '#C8D9E6',
    bg: '#F5EFEB',
    palette: ['#2F4156', '#567C8D', '#7FA8BA', '#C8D9E6', '#A3C4D9', '#3D5A80', '#98C1D9', '#457B9D', '#1D3557', '#E63946'],
};

// =================== INIT ===================
function initReports() {
    // Set default dates
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    const today = now.toISOString().split('T')[0];

    document.getElementById('diagFrom').value = firstDay;
    document.getElementById('diagTo').value = today;
    document.getElementById('detailFrom').value = firstDay;
    document.getElementById('detailTo').value = today;

    // Year dropdown
    const yearSel = document.getElementById('revYear');
    yearSel.innerHTML = '';
    for (let y = now.getFullYear(); y >= now.getFullYear() - 5; y--) {
        yearSel.innerHTML += `<option value="${y}" ${y === now.getFullYear() ? 'selected' : ''}>${y}</option>`;
    }
    document.getElementById('revMonth').value = now.getMonth() + 1;

    loadRevenueChart();
    loadDemographics();
    loadTopDiagnoses();
}

function switchReportTab(tab) {
    document.querySelectorAll('.report-tab').forEach(t => t.style.display = 'none');
    document.getElementById('report-' + tab).style.display = 'block';

    document.querySelectorAll('#page-reports .content-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');

    switch(tab) {
        case 'revenue': loadRevenueChart(); break;
        case 'visits': loadDemographics(); break;
        case 'diagnoses': loadTopDiagnoses(); break;
        case 'detail': loadDailyDetail(); break;
    }
}

// =================== REVENUE CHART ===================
async function loadRevenueChart() {
    const period = document.getElementById('revPeriod').value;
    const year = document.getElementById('revYear').value;
    const month = document.getElementById('revMonth').value;

    // Show/hide month selector
    document.getElementById('revMonth').style.display = period === 'daily' ? 'block' : 'none';

    let url = `api/reports.php?action=revenue&period=${period}&year=${year}&month=${month}`;
    const res = await api(url);
    if (!res.success) return;

    const labels = res.data.map(r => {
        if (period === 'daily') return r.label.split('-').pop();
        if (period === 'monthly') return r.month_name || r.label;
        return r.label;
    });
    const revenues = res.data.map(r => parseFloat(r.revenue));
    const visits = res.data.map(r => parseInt(r.visit_count));

    if (revenueChart) revenueChart.destroy();

    const ctx = document.getElementById('revenueChart').getContext('2d');
    revenueChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Pendapatan (Rp)',
                    data: revenues,
                    backgroundColor: 'rgba(86, 124, 141, 0.7)',
                    borderColor: '#567C8D',
                    borderWidth: 1,
                    borderRadius: 6,
                    yAxisID: 'y',
                },
                {
                    label: 'Jumlah Kunjungan',
                    data: visits,
                    type: 'line',
                    borderColor: '#2F4156',
                    backgroundColor: 'rgba(47, 65, 86, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#2F4156',
                    pointRadius: 4,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font: { family: "'Inter', sans-serif", size: 12 }, usePointStyle: true, padding: 20 } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            if (ctx.dataset.yAxisID === 'y') return 'Pendapatan: ' + formatRupiah(ctx.raw);
                            return 'Kunjungan: ' + ctx.raw;
                        }
                    }
                }
            },
            scales: {
                y: { position: 'left', ticks: { callback: v => formatRupiah(v), font: { size: 11 } }, grid: { color: 'rgba(200,217,230,0.3)' } },
                y1: { position: 'right', ticks: { font: { size: 11 } }, grid: { display: false } },
                x: { ticks: { font: { size: 11 } }, grid: { display: false } }
            }
        }
    });
}

// =================== DEMOGRAPHICS ===================
async function loadDemographics() {
    const res = await api('api/reports.php?action=patient_demographics');
    if (!res.success) return;

    // Gender chart
    if (genderChart) genderChart.destroy();
    const gCtx = document.getElementById('genderChart').getContext('2d');
    genderChart = new Chart(gCtx, {
        type: 'doughnut',
        data: {
            labels: res.data.gender.map(g => g.gender === 'L' ? 'Laki-laki' : 'Perempuan'),
            datasets: [{
                data: res.data.gender.map(g => g.total),
                backgroundColor: ['#2F4156', '#567C8D'],
                borderWidth: 3,
                borderColor: '#FFFFFF',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: "'Inter'", size: 12 }, padding: 16, usePointStyle: true } },
                title: { display: true, text: 'Distribusi Jenis Kelamin', font: { family: "'Inter'", size: 14, weight: 'bold' }, color: '#2F4156' }
            }
        }
    });

    // Age chart
    if (ageChart) ageChart.destroy();
    const aCtx = document.getElementById('ageChart').getContext('2d');
    ageChart = new Chart(aCtx, {
        type: 'bar',
        data: {
            labels: res.data.age.map(a => a.age_group),
            datasets: [{
                label: 'Jumlah Pasien',
                data: res.data.age.map(a => a.total),
                backgroundColor: chartColors.palette.slice(0, res.data.age.length),
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Distribusi Usia Pasien', font: { family: "'Inter'", size: 14, weight: 'bold' }, color: '#2F4156' }
            },
            scales: {
                x: { ticks: { font: { size: 11 } }, grid: { color: 'rgba(200,217,230,0.3)' } },
                y: { ticks: { font: { size: 11 } }, grid: { display: false } }
            }
        }
    });
}

// =================== TOP DIAGNOSES ===================
async function loadTopDiagnoses() {
    const from = document.getElementById('diagFrom').value;
    const to = document.getElementById('diagTo').value;
    const res = await api(`api/reports.php?action=top_diagnoses&from=${from}&to=${to}`);
    if (!res.success) return;

    if (diagnosesChart) diagnosesChart.destroy();
    const ctx = document.getElementById('diagnosesChart').getContext('2d');

    diagnosesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: res.data.map(d => d.diagnosis || 'N/A'),
            datasets: [{
                label: 'Jumlah Kasus',
                data: res.data.map(d => d.total),
                backgroundColor: 'rgba(86, 124, 141, 0.75)',
                borderColor: '#567C8D',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
            },
            scales: {
                x: { ticks: { font: { size: 11 } }, grid: { color: 'rgba(200,217,230,0.3)' } },
                y: { ticks: { font: { size: 11 } }, grid: { display: false } }
            }
        }
    });
}

// =================== DAILY DETAIL ===================
let dailyDetailData = [];

async function loadDailyDetail() {
    const from = document.getElementById('detailFrom').value;
    const to = document.getElementById('detailTo').value;

    const res = await api(`api/reports.php?action=daily_detail&from=${from}&to=${to}`);
    if (!res.success) return;

    dailyDetailData = res.data;

    document.getElementById('detailTableBody').innerHTML = res.data.map(r => `
        <tr>
            <td>${formatDate(r.visit_date)}</td>
            <td>${r.queue_number}</td>
            <td>${r.no_rm}</td>
            <td>${escHtml(r.patient_name)}</td>
            <td>${escHtml(r.diagnosis || '-')}</td>
            <td>${r.invoice_number || '-'}</td>
            <td class="text-right">${r.grand_total ? formatRupiah(r.grand_total) : '-'}</td>
            <td><span class="status-badge ${r.payment_status || 'waiting'}">${r.payment_status || '-'}</span></td>
        </tr>
    `).join('') || '<tr><td colspan="8" class="text-center text-muted">Tidak ada data</td></tr>';
}

// =================== EXPORT: EXCEL ===================
function exportToExcel(data, headers, filename) {
    const ws = XLSX.utils.json_to_sheet(data, { header: headers });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Laporan');

    // Style header row
    const range = XLSX.utils.decode_range(ws['!ref']);
    for (let c = range.s.c; c <= range.e.c; c++) {
        const cell = ws[XLSX.utils.encode_cell({ r: 0, c })];
        if (cell) cell.s = { font: { bold: true } };
    }

    XLSX.writeFile(wb, filename + '.xlsx');
    showToast('File Excel berhasil diunduh', 'success');
}

function exportRevenueExcel() {
    if (!revenueChart) return;
    const labels = revenueChart.data.labels;
    const revenues = revenueChart.data.datasets[0].data;
    const visits = revenueChart.data.datasets[1].data;

    const data = labels.map((l, i) => ({
        'Periode': l,
        'Pendapatan': revenues[i],
        'Kunjungan': visits[i],
    }));

    exportToExcel(data, ['Periode', 'Pendapatan', 'Kunjungan'], 'Laporan_Pendapatan');
}

function exportDemographicsExcel() {
    // Simplified - export gender and age data
    api('api/reports.php?action=patient_demographics').then(res => {
        if (!res.success) return;
        const genderData = res.data.gender.map(g => ({
            'Jenis Kelamin': g.gender === 'L' ? 'Laki-laki' : 'Perempuan',
            'Jumlah': g.total
        }));
        const ageData = res.data.age.map(a => ({
            'Kelompok Usia': a.age_group,
            'Jumlah': a.total
        }));
        const combined = [...genderData, { 'Jenis Kelamin': '', 'Jumlah': '' }, ...ageData.map(a => ({ 'Jenis Kelamin': a['Kelompok Usia'], 'Jumlah': a['Jumlah'] }))];
        exportToExcel(combined, ['Jenis Kelamin', 'Jumlah'], 'Laporan_Demografi');
    });
}

function exportDiagnosesExcel() {
    const from = document.getElementById('diagFrom').value;
    const to = document.getElementById('diagTo').value;
    api(`api/reports.php?action=top_diagnoses&from=${from}&to=${to}`).then(res => {
        if (!res.success) return;
        const data = res.data.map(d => ({
            'Diagnosa': d.diagnosis,
            'Kode ICD': d.icd_code || '-',
            'Jumlah Kasus': d.total
        }));
        exportToExcel(data, ['Diagnosa', 'Kode ICD', 'Jumlah Kasus'], 'Laporan_Diagnosa');
    });
}

function exportDetailExcel() {
    if (!dailyDetailData.length) { showToast('Tidak ada data untuk diexport', 'warning'); return; }
    const data = dailyDetailData.map(r => ({
        'Tanggal': r.visit_date,
        'No. Antrean': r.queue_number,
        'No. RM': r.no_rm,
        'Nama Pasien': r.patient_name,
        'Diagnosa': r.diagnosis || '-',
        'No. Invoice': r.invoice_number || '-',
        'Total (Rp)': r.grand_total || 0,
        'Status': r.payment_status || '-',
    }));
    exportToExcel(data, ['Tanggal', 'No. Antrean', 'No. RM', 'Nama Pasien', 'Diagnosa', 'No. Invoice', 'Total (Rp)', 'Status'], 'Laporan_Detail_Harian');
}

// =================== EXPORT: PDF ===================
function createPDF(title, headers, rows, filename, options = {}) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: options.landscape ? 'landscape' : 'portrait' });

    // Header
    doc.setFontSize(16);
    doc.setTextColor(47, 65, 86);
    doc.text('Klinik Praktek Mandiri', 14, 20);
    doc.setFontSize(11);
    doc.setTextColor(86, 124, 141);
    doc.text(title, 14, 28);
    doc.setFontSize(9);
    doc.text(`Dicetak: ${new Date().toLocaleString('id-ID')}`, 14, 34);

    doc.setDrawColor(47, 65, 86);
    doc.line(14, 37, doc.internal.pageSize.getWidth() - 14, 37);

    doc.autoTable({
        head: [headers],
        body: rows,
        startY: 42,
        styles: { font: 'helvetica', fontSize: 9, cellPadding: 4 },
        headStyles: { fillColor: [47, 65, 86], textColor: [255, 255, 255], fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [245, 239, 235] },
        margin: { left: 14, right: 14 },
    });

    // Footer
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(155);
        doc.text(`Halaman ${i} dari ${pageCount}`, doc.internal.pageSize.getWidth() / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });
    }

    doc.save(filename + '.pdf');
    showToast('File PDF berhasil diunduh', 'success');
}

function exportRevenuePDF() {
    if (!revenueChart) return;
    const labels = revenueChart.data.labels;
    const revenues = revenueChart.data.datasets[0].data;
    const visits = revenueChart.data.datasets[1].data;

    const headers = ['Periode', 'Pendapatan (Rp)', 'Kunjungan'];
    const rows = labels.map((l, i) => [l, formatRupiah(revenues[i]), visits[i]]);

    // Add total row
    const totalRev = revenues.reduce((a, b) => a + b, 0);
    const totalVis = visits.reduce((a, b) => a + b, 0);
    rows.push(['TOTAL', formatRupiah(totalRev), totalVis]);

    createPDF('Laporan Pendapatan', headers, rows, 'Laporan_Pendapatan');
}

function exportDemographicsPDF() {
    api('api/reports.php?action=patient_demographics').then(res => {
        if (!res.success) return;
        const headers = ['Kategori', 'Jumlah'];
        const rows = [];
        rows.push(['--- Jenis Kelamin ---', '']);
        res.data.gender.forEach(g => rows.push([g.gender === 'L' ? 'Laki-laki' : 'Perempuan', g.total]));
        rows.push(['--- Kelompok Usia ---', '']);
        res.data.age.forEach(a => rows.push([a.age_group, a.total]));

        createPDF('Laporan Demografi Pasien', headers, rows, 'Laporan_Demografi');
    });
}

function exportDiagnosesPDF() {
    const from = document.getElementById('diagFrom').value;
    const to = document.getElementById('diagTo').value;
    api(`api/reports.php?action=top_diagnoses&from=${from}&to=${to}`).then(res => {
        if (!res.success) return;
        const headers = ['No', 'Diagnosa', 'Kode ICD', 'Jumlah Kasus'];
        const rows = res.data.map((d, i) => [i + 1, d.diagnosis, d.icd_code || '-', d.total]);
        createPDF(`Laporan Top Diagnosa (${from} s/d ${to})`, headers, rows, 'Laporan_Diagnosa');
    });
}

function exportDetailPDF() {
    if (!dailyDetailData.length) { showToast('Tidak ada data', 'warning'); return; }
    const headers = ['Tanggal', 'Antrean', 'No. RM', 'Pasien', 'Diagnosa', 'Invoice', 'Total', 'Status'];
    const rows = dailyDetailData.map(r => [
        r.visit_date, r.queue_number, r.no_rm, r.patient_name,
        r.diagnosis || '-', r.invoice_number || '-',
        r.grand_total ? formatRupiah(r.grand_total) : '-', r.payment_status || '-'
    ]);

    // Add total
    const totalAmount = dailyDetailData.reduce((s, r) => s + (parseFloat(r.grand_total) || 0), 0);
    rows.push(['', '', '', '', '', 'TOTAL', formatRupiah(totalAmount), '']);

    createPDF('Laporan Detail Harian', headers, rows, 'Laporan_Detail_Harian', { landscape: true });
}
