/**
 * KLINIK PRAKTEK MANDIRI - Core Application Logic
 * Single Page Application (SPA) Controller
 */

// =================== GLOBAL STATE ===================
const App = {
    currentPage: 'dashboard',
    activeVisitId: null,
    billVisitId: null,
    billData: null,
    patientPage: 1,
    debounceTimers: {},
};

// =================== INITIALIZATION ===================
document.addEventListener('DOMContentLoaded', () => {
    initDateDisplay();
    loadDashboard();
    initSearchPatient();
    initSearchDrug();
});

function initDateDisplay() {
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const now = new Date();
    const dateStr = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
    document.getElementById('dateDisplay').textContent = dateStr;
}

// =================== NAVIGATION ===================
function navigateTo(page) {
    // Hide all sections
    document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));

    // Show selected
    const target = document.getElementById('page-' + page);
    if (target) target.classList.add('active');

    // Update sidebar active
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const navItem = document.querySelector(`.nav-item[data-page="${page}"]`);
    if (navItem) navItem.classList.add('active');

    // Update top bar
    const titles = {
        'dashboard': ['Dashboard', 'Ringkasan aktivitas klinik hari ini'],
        'registration': ['Pendaftaran Pasien', 'Daftarkan pasien baru atau kunjungan ulang'],
        'examination': ['Pemeriksaan', 'Input data vital dan diagnosa pasien'],
        'billing': ['Kasir', 'Proses tindakan, resep obat, dan pembayaran'],
        'patients': ['Data Pasien', 'Kelola data rekam medis pasien'],
        'reports': ['Laporan', 'Analitik dan laporan klinik'],
        'master-procedures': ['Master Tindakan', 'Kelola daftar tarif tindakan medis'],
        'master-drugs': ['Master Obat', 'Kelola inventaris obat dan alat kesehatan'],
    };

    const [title, subtitle] = titles[page] || ['', ''];
    document.getElementById('pageTitle').textContent = title;
    document.getElementById('pageSubtitle').textContent = subtitle;

    App.currentPage = page;

    // Load page data
    switch(page) {
        case 'dashboard': loadDashboard(); break;
        case 'examination': loadExamQueue(); break;
        case 'billing': loadBillingQueue(); break;
        case 'patients': loadPatientsList(); break;
        case 'reports': initReports(); break;
        case 'master-procedures': loadMasterProcedures(); break;
        case 'master-drugs': loadMasterDrugs(); break;
    }
}

// =================== TOAST NOTIFICATIONS ===================
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-exit');
        setTimeout(() => toast.remove(), 250);
    }, 3500);
}

// =================== API HELPER ===================
async function api(url, options = {}) {
    try {
        const res = await fetch(url, options);
        const data = await res.json();
        return data;
    } catch (err) {
        showToast('Koneksi ke server gagal', 'error');
        return { success: false, message: 'Network error' };
    }
}

async function apiPost(url, body) {
    return api(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
}

// =================== FORMAT HELPERS ===================
function formatRupiah(num) {
    return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function debounce(fn, delay, key) {
    clearTimeout(App.debounceTimers[key]);
    App.debounceTimers[key] = setTimeout(fn, delay);
}

// =================== DASHBOARD ===================
async function loadDashboard() {
    const stats = await api('api/visits.php?action=stats');
    if (stats.success) {
        const d = stats.data;
        document.getElementById('statTotalPatients').textContent = d.total_patients || 0;
        document.getElementById('statTodayVisits').textContent = d.total_visits || 0;
        document.getElementById('statWaiting').textContent = d.waiting || 0;
        document.getElementById('statRevenue').textContent = formatRupiah(d.revenue);

        // Low stock badge
        const badge = document.getElementById('lowStockBadge');
        if (d.low_stock_count > 0) {
            badge.textContent = d.low_stock_count;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    }
    loadTodayQueue();
}

async function loadTodayQueue() {
    const res = await api('api/visits.php?action=today');
    const container = document.getElementById('queueList');
    if (!res.success || !res.data || res.data.length === 0) {
        container.innerHTML = `<div class="empty-state">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            <h4>Belum ada antrean</h4><p>Daftarkan pasien baru untuk memulai</p></div>`;
        return;
    }
    container.innerHTML = res.data.map(v => `
        <div class="queue-item" onclick="handleQueueClick(${v.id}, '${v.status}')">
            <div class="queue-number">${v.queue_number}</div>
            <div class="queue-info">
                <div class="q-name">${escHtml(v.patient_name)}</div>
                <div class="q-detail">${v.no_rm} &bull; ${v.gender === 'L' ? 'Laki-laki' : 'Perempuan'}${v.complaint ? ' &bull; ' + escHtml(v.complaint) : ''}</div>
            </div>
            <span class="status-badge ${v.status}">${statusLabel(v.status)}</span>
        </div>
    `).join('');
}

function handleQueueClick(visitId, status) {
    if (status === 'waiting' || status === 'examination') {
        navigateTo('examination');
        setTimeout(() => selectVisitForExam(visitId), 100);
    } else {
        navigateTo('billing');
        setTimeout(() => selectVisitForBilling(visitId), 100);
    }
}

function statusLabel(s) {
    const labels = {
        'waiting': 'Menunggu', 'examination': 'Pemeriksaan', 'action': 'Tindakan',
        'pharmacy': 'Farmasi', 'cashier': 'Kasir', 'done': 'Selesai', 'cancelled': 'Batal'
    };
    return labels[s] || s;
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// =================== PATIENT SEARCH (REGISTRATION) ===================
function initSearchPatient() {
    const input = document.getElementById('searchPatient');
    const dropdown = document.getElementById('searchDropdown');

    input.addEventListener('input', () => {
        debounce(async () => {
            const q = input.value.trim();
            if (q.length < 2) { dropdown.classList.remove('show'); return; }

            const res = await api(`api/patients.php?action=search&q=${encodeURIComponent(q)}`);
            if (!res.success || !res.data.length) {
                dropdown.innerHTML = '<div class="autocomplete-item text-muted">Tidak ditemukan. Silakan daftarkan pasien baru.</div>';
                dropdown.classList.add('show');
                return;
            }

            dropdown.innerHTML = res.data.map(p => `
                <div class="autocomplete-item" onclick="selectExistingPatient(${p.id})">
                    <div class="ac-name">${escHtml(p.name)}</div>
                    <div class="ac-detail">${p.no_rm} &bull; NIK: ${p.nik || '-'} &bull; ${p.gender === 'L' ? 'L' : 'P'}</div>
                </div>
            `).join('');
            dropdown.classList.add('show');
        }, 300, 'searchPatient');
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-box')) dropdown.classList.remove('show');
    });
}

async function selectExistingPatient(id) {
    const res = await api(`api/patients.php?action=get&id=${id}`);
    if (!res.success) return;
    const p = res.data;

    document.getElementById('searchDropdown').classList.remove('show');
    document.getElementById('searchPatient').value = '';

    // Fill form
    document.getElementById('patientId').value = p.id;
    document.getElementById('patName').value = p.name || '';
    document.getElementById('patNIK').value = p.nik || '';
    document.getElementById('patBirthDate').value = p.birth_date || '';
    document.getElementById('patGender').value = p.gender || 'L';
    document.getElementById('patBlood').value = p.blood_type || '-';
    document.getElementById('patPhone').value = p.phone || '';
    document.getElementById('patAllergy').value = p.allergy || '';
    document.getElementById('patAddress').value = p.address || '';

    document.getElementById('regFormTitle').textContent = 'Edit Data & Daftarkan Kunjungan';

    // Show selected patient info
    const infoEl = document.getElementById('selectedPatientInfo');
    infoEl.style.display = 'block';
    infoEl.innerHTML = `
        <div style="padding:12px 16px; background:rgba(86,124,141,0.06); border-radius:12px; border: 1px solid var(--accent);">
            <strong>${escHtml(p.name)}</strong> &bull; ${p.no_rm} 
            <span class="tag" style="margin-left:8px;">${p.gender === 'L' ? 'Laki-laki' : 'Perempuan'}</span>
        </div>
    `;
}

function resetPatientForm() {
    document.getElementById('patientId').value = '';
    document.getElementById('patientForm').reset();
    document.getElementById('regFormTitle').textContent = 'Daftarkan Pasien Baru';
    document.getElementById('selectedPatientInfo').style.display = 'none';
}

async function savePatient() {
    const patientId = document.getElementById('patientId').value;
    const name = document.getElementById('patName').value.trim();
    if (!name) { showToast('Nama pasien harus diisi', 'error'); return; }

    const body = {
        name,
        nik: document.getElementById('patNIK').value.trim(),
        birth_date: document.getElementById('patBirthDate').value,
        gender: document.getElementById('patGender').value,
        blood_type: document.getElementById('patBlood').value,
        phone: document.getElementById('patPhone').value.trim(),
        allergy: document.getElementById('patAllergy').value.trim(),
        address: document.getElementById('patAddress').value.trim(),
    };

    let pid = patientId;

    if (patientId) {
        // Update existing
        body.id = parseInt(patientId);
        const res = await apiPost('api/patients.php?action=update', body);
        if (!res.success) { showToast(res.message, 'error'); return; }
    } else {
        // Create new
        const res = await apiPost('api/patients.php?action=create', body);
        if (!res.success) { showToast(res.message, 'error'); return; }
        pid = res.data.id;
        showToast(`Pasien terdaftar: ${res.data.no_rm}`, 'success');
    }

    // Create visit
    const visitRes = await apiPost('api/visits.php?action=create', { patient_id: parseInt(pid) });
    if (visitRes.success) {
        showToast(visitRes.message, 'success');
        resetPatientForm();
        loadDashboard();
    } else {
        showToast(visitRes.message, 'warning');
    }
}

// =================== EXAMINATION ===================
async function loadExamQueue() {
    const res = await api('api/visits.php?action=today');
    const container = document.getElementById('examQueueList');
    if (!res.success || !res.data || res.data.length === 0) {
        container.innerHTML = '<div class="empty-state"><h4>Belum ada antrean</h4></div>';
        return;
    }

    const active = res.data.filter(v => v.status !== 'done' && v.status !== 'cancelled');
    if (active.length === 0) {
        container.innerHTML = '<div class="empty-state"><h4>Semua pasien sudah selesai</h4></div>';
        return;
    }

    container.innerHTML = active.map(v => `
        <div class="queue-item" onclick="selectVisitForExam(${v.id})">
            <div class="queue-number">${v.queue_number}</div>
            <div class="queue-info">
                <div class="q-name">${escHtml(v.patient_name)}</div>
                <div class="q-detail">${v.no_rm} &bull; ${v.gender === 'L' ? 'L' : 'P'}${v.complaint ? ' &bull; ' + escHtml(v.complaint) : ''}</div>
            </div>
            <span class="status-badge ${v.status}">${statusLabel(v.status)}</span>
        </div>
    `).join('');
}

async function selectVisitForExam(visitId) {
    App.activeVisitId = visitId;
    const res = await api(`api/visits.php?action=get&id=${visitId}`);
    if (!res.success) return;
    const v = res.data;

    document.getElementById('examSelectVisit').style.display = 'none';
    document.getElementById('examFormContainer').style.display = 'block';

    document.getElementById('examPatientName').textContent = v.patient_name;
    document.getElementById('examPatientDetail').textContent = `${v.no_rm} • ${v.gender === 'L' ? 'Laki-laki' : 'Perempuan'} • ${formatDate(v.birth_date)} • Alergi: ${v.allergy || 'Tidak ada'}`;
    document.getElementById('examVisitId').value = visitId;

    // Load existing vitals if any
    if (v.vitals) {
        const vt = v.vitals;
        document.getElementById('vBP').value = vt.blood_pressure || '';
        document.getElementById('vWeight').value = vt.weight || '';
        document.getElementById('vHeight').value = vt.height || '';
        document.getElementById('vTemp').value = vt.temperature || '';
        document.getElementById('vPulse').value = vt.pulse || '';
        document.getElementById('vRR').value = vt.respiratory_rate || '';
        document.getElementById('vComplaint').value = vt.complaint || '';
        document.getElementById('vPhysicalExam').value = vt.physical_exam || '';
        document.getElementById('vDiagnosis').value = vt.diagnosis || '';
        document.getElementById('vICD').value = vt.icd_code || '';
        document.getElementById('vNotes').value = vt.notes || '';
    } else {
        document.getElementById('vitalsForm').reset();
    }
}

function deselectVisit() {
    App.activeVisitId = null;
    document.getElementById('examSelectVisit').style.display = 'block';
    document.getElementById('examFormContainer').style.display = 'none';
    loadExamQueue();
}

async function saveVitals() {
    const visitId = parseInt(document.getElementById('examVisitId').value);
    const complaint = document.getElementById('vComplaint').value.trim();
    const diagnosis = document.getElementById('vDiagnosis').value.trim();

    if (!complaint) { showToast('Keluhan utama harus diisi', 'error'); return; }
    if (!diagnosis) { showToast('Diagnosa harus diisi', 'error'); return; }

    const body = {
        visit_id: visitId,
        blood_pressure: document.getElementById('vBP').value.trim(),
        weight: document.getElementById('vWeight').value || null,
        height: document.getElementById('vHeight').value || null,
        temperature: document.getElementById('vTemp').value || null,
        pulse: document.getElementById('vPulse').value || null,
        respiratory_rate: document.getElementById('vRR').value || null,
        complaint,
        physical_exam: document.getElementById('vPhysicalExam').value.trim(),
        diagnosis,
        icd_code: document.getElementById('vICD').value.trim(),
        notes: document.getElementById('vNotes').value.trim(),
    };

    const res = await apiPost('api/clinical.php?action=save_vitals', body);
    if (res.success) {
        showToast('Data pemeriksaan berhasil disimpan', 'success');
        // Ask if want to continue to billing
        setTimeout(() => {
            if (confirm('Lanjut ke proses tindakan/obat & pembayaran?')) {
                navigateTo('billing');
                setTimeout(() => selectVisitForBilling(visitId), 100);
            }
        }, 500);
    } else {
        showToast(res.message, 'error');
    }
}

// =================== BILLING ===================
async function loadBillingQueue() {
    const res = await api('api/visits.php?action=today');
    const container = document.getElementById('billingQueueList');
    if (!res.success || !res.data || res.data.length === 0) {
        container.innerHTML = '<div class="empty-state"><h4>Belum ada kunjungan</h4></div>';
        return;
    }

    const active = res.data.filter(v => v.status !== 'done' && v.status !== 'cancelled');
    if (active.length === 0) {
        container.innerHTML = '<div class="empty-state"><h4>Semua kunjungan sudah selesai</h4></div>';
        return;
    }

    container.innerHTML = active.map(v => `
        <div class="queue-item" onclick="selectVisitForBilling(${v.id})">
            <div class="queue-number">${v.queue_number}</div>
            <div class="queue-info">
                <div class="q-name">${escHtml(v.patient_name)}</div>
                <div class="q-detail">${v.no_rm} &bull; ${v.diagnosis || 'Belum diperiksa'}</div>
            </div>
            <span class="status-badge ${v.status}">${statusLabel(v.status)}</span>
        </div>
    `).join('');
}

async function selectVisitForBilling(visitId) {
    App.billVisitId = visitId;
    const res = await api(`api/visits.php?action=get&id=${visitId}`);
    if (!res.success) return;
    const v = res.data;

    document.getElementById('billingSelectVisit').style.display = 'none';
    document.getElementById('billingContainer').style.display = 'block';

    document.getElementById('billPatientName').textContent = v.patient_name;
    document.getElementById('billPatientDetail').textContent = `${v.no_rm} • Antrean #${v.queue_number} • ${v.vitals?.diagnosis || 'Belum didiagnosa'}`;

    // Load procedures dropdown
    await loadProcedureDropdown();
    // Load bill
    await refreshBill();
    switchBillingTab('procedures');
}

function deselectBilling() {
    App.billVisitId = null;
    document.getElementById('billingSelectVisit').style.display = 'block';
    document.getElementById('billingContainer').style.display = 'none';
    loadBillingQueue();
}

function switchBillingTab(tab) {
    document.querySelectorAll('.billing-tab').forEach(t => t.style.display = 'none');
    document.getElementById('tab-' + tab).style.display = 'block';

    document.querySelectorAll('#page-billing .content-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');

    if (tab === 'payment') refreshPaymentSummary();
}

async function loadProcedureDropdown() {
    const res = await api('api/master.php?action=list_procedures&active_only=1');
    const sel = document.getElementById('selProcedure');
    sel.innerHTML = '<option value="">-- Pilih tindakan --</option>';
    if (res.success) {
        res.data.forEach(p => {
            sel.innerHTML += `<option value="${p.id}" data-price="${p.price}">${p.name} - ${formatRupiah(p.price)}</option>`;
        });
    }
}

async function addProcedure() {
    const procedureId = parseInt(document.getElementById('selProcedure').value);
    const qty = parseInt(document.getElementById('procQty').value) || 1;
    if (!procedureId) { showToast('Pilih tindakan terlebih dahulu', 'error'); return; }

    const res = await apiPost('api/billing.php?action=add_procedure', {
        visit_id: App.billVisitId, procedure_id: procedureId, quantity: qty
    });

    if (res.success) {
        showToast('Tindakan ditambahkan');
        document.getElementById('selProcedure').value = '';
        document.getElementById('procQty').value = 1;
        await refreshBill();
    } else {
        showToast(res.message, 'error');
    }
}

async function removeProcedureItem(id) {
    if (!confirm('Hapus tindakan ini?')) return;
    const res = await apiPost('api/billing.php?action=remove_procedure', { id });
    if (res.success) { showToast('Tindakan dihapus'); await refreshBill(); }
}

// Drug search
function initSearchDrug() {
    const input = document.getElementById('searchDrug');
    const dropdown = document.getElementById('drugDropdown');

    input.addEventListener('input', () => {
        debounce(async () => {
            const q = input.value.trim();
            if (q.length < 2) { dropdown.classList.remove('show'); return; }
            const res = await api(`api/master.php?action=search_drugs&q=${encodeURIComponent(q)}`);
            if (!res.success || !res.data.length) {
                dropdown.innerHTML = '<div class="autocomplete-item text-muted">Obat tidak ditemukan</div>';
                dropdown.classList.add('show');
                return;
            }
            dropdown.innerHTML = res.data.map(d => `
                <div class="autocomplete-item" onclick="selectDrug(${d.id}, '${escHtml(d.name)}', ${d.price}, ${d.stock}, '${escHtml(d.unit)}')">
                    <div class="ac-name">${escHtml(d.name)}</div>
                    <div class="ac-detail">${d.code} • ${formatRupiah(d.price)}/${d.unit} • Stok: ${d.stock}</div>
                </div>
            `).join('');
            dropdown.classList.add('show');
        }, 300, 'searchDrug');
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#tab-drugs .search-box')) dropdown.classList.remove('show');
    });
}

function selectDrug(id, name, price, stock, unit) {
    document.getElementById('selDrugId').value = id;
    document.getElementById('searchDrug').value = name;
    document.getElementById('selDrugInfo').textContent = `${formatRupiah(price)}/${unit} • Stok tersedia: ${stock}`;
    document.getElementById('drugDropdown').classList.remove('show');
}

async function addDrug() {
    const drugId = parseInt(document.getElementById('selDrugId').value);
    const qty = parseInt(document.getElementById('drugQty').value) || 1;
    const dosage = document.getElementById('drugDosage').value.trim();
    if (!drugId) { showToast('Pilih obat terlebih dahulu', 'error'); return; }

    const res = await apiPost('api/billing.php?action=add_drug', {
        visit_id: App.billVisitId, drug_id: drugId, quantity: qty, dosage_instructions: dosage
    });

    if (res.success) {
        showToast('Obat ditambahkan');
        document.getElementById('searchDrug').value = '';
        document.getElementById('selDrugId').value = '';
        document.getElementById('selDrugInfo').textContent = '';
        document.getElementById('drugQty').value = 1;
        document.getElementById('drugDosage').value = '';
        await refreshBill();
    } else {
        showToast(res.message, 'error');
    }
}

async function removeDrugItem(id) {
    if (!confirm('Hapus obat ini? Stok akan dikembalikan.')) return;
    const res = await apiPost('api/billing.php?action=remove_drug', { id });
    if (res.success) { showToast('Obat dihapus, stok dikembalikan'); await refreshBill(); }
}

async function refreshBill() {
    const res = await api(`api/billing.php?action=get_bill&visit_id=${App.billVisitId}`);
    if (!res.success) return;
    App.billData = res.data;

    // Procedures table
    const procBody = document.getElementById('procTableBody');
    procBody.innerHTML = res.data.procedures.map(p => `
        <tr>
            <td>${p.code}</td>
            <td>${escHtml(p.procedure_name)}</td>
            <td>${p.quantity}</td>
            <td class="text-right">${formatRupiah(p.price)}</td>
            <td class="text-right">${formatRupiah(p.subtotal)}</td>
            <td><button class="btn btn-danger btn-xs" onclick="removeProcedureItem(${p.id})">Hapus</button></td>
        </tr>
    `).join('') || '<tr><td colspan="6" class="text-center text-muted">Belum ada tindakan</td></tr>';

    document.getElementById('totalProcedures').textContent = formatRupiah(res.data.total_procedures);

    // Drugs table
    const drugBody = document.getElementById('drugTableBody');
    drugBody.innerHTML = res.data.drugs.map(d => `
        <tr>
            <td>${d.code}</td>
            <td>${escHtml(d.drug_name)}</td>
            <td>${d.quantity} ${d.unit}</td>
            <td>${escHtml(d.dosage_instructions || '-')}</td>
            <td class="text-right">${formatRupiah(d.price)}</td>
            <td class="text-right">${formatRupiah(d.subtotal)}</td>
            <td><button class="btn btn-danger btn-xs" onclick="removeDrugItem(${d.id})">Hapus</button></td>
        </tr>
    `).join('') || '<tr><td colspan="7" class="text-center text-muted">Belum ada obat</td></tr>';

    document.getElementById('totalDrugs').textContent = formatRupiah(res.data.total_drugs);
}

function refreshPaymentSummary() {
    if (!App.billData) return;
    document.getElementById('payTotalProc').textContent = formatRupiah(App.billData.total_procedures);
    document.getElementById('payTotalDrugs').textContent = formatRupiah(App.billData.total_drugs);
    document.getElementById('payDiscount').value = 0;
    calcPayment();
}

function calcPayment() {
    if (!App.billData) return;
    const totalProc = App.billData.total_procedures || 0;
    const totalDrugs = App.billData.total_drugs || 0;
    const discount = parseFloat(document.getElementById('payDiscount').value) || 0;
    const grandTotal = totalProc + totalDrugs - discount;
    const payAmount = parseFloat(document.getElementById('payAmount').value) || 0;
    const change = payAmount - grandTotal;

    document.getElementById('payGrandTotal').textContent = formatRupiah(grandTotal);
    document.getElementById('payChange').textContent = formatRupiah(Math.max(0, change));
    document.getElementById('payChange').style.color = change >= 0 ? 'var(--success)' : 'var(--danger)';
}

async function processPayment() {
    if (!App.billVisitId) return;
    const discount = parseFloat(document.getElementById('payDiscount').value) || 0;
    const payAmount = parseFloat(document.getElementById('payAmount').value) || 0;
    const grandTotal = (App.billData.total_procedures || 0) + (App.billData.total_drugs || 0) - discount;

    if (payAmount < grandTotal) {
        showToast('Jumlah pembayaran kurang', 'error');
        return;
    }

    const res = await apiPost('api/billing.php?action=checkout', {
        visit_id: App.billVisitId,
        discount,
        payment_amount: payAmount,
        payment_method: document.getElementById('payMethod').value,
    });

    if (res.success) {
        showToast('Pembayaran berhasil! ✓', 'success');
        // Show invoice
        await showInvoicePreview(App.billVisitId);
        deselectBilling();
        loadDashboard();
    } else {
        showToast(res.message, 'error');
    }
}

async function showInvoicePreview(visitId) {
    const res = await api(`api/billing.php?action=get_invoice&visit_id=${visitId}`);
    if (!res.success) return;
    const inv = res.data;

    let procRows = inv.procedures.map(p => `<tr><td>${p.procedure_name}</td><td>${p.quantity}</td><td class="text-right">${formatRupiah(p.subtotal)}</td></tr>`).join('');
    let drugRows = inv.drugs.map(d => `<tr><td>${d.drug_name} (${d.dosage_instructions || '-'})</td><td>${d.quantity}</td><td class="text-right">${formatRupiah(d.subtotal)}</td></tr>`).join('');

    document.getElementById('invoicePreviewContent').innerHTML = `
        <div id="invoicePrintArea" style="font-family: 'Inter', sans-serif;">
            <div style="text-align:center; margin-bottom:24px;">
                <h2 style="margin:0; color:#2F4156;">Klinik Praktek Mandiri</h2>
                <p style="margin:4px 0; color:#567C8D; font-size:13px;">Jl. Contoh No. 123 - Telp: (021) 123-4567</p>
                <hr style="border:none; border-top: 2px solid #2F4156; margin-top:12px;">
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:16px; font-size:13px;">
                <div>
                    <strong>No. Invoice:</strong> ${inv.invoice_number}<br>
                    <strong>Tanggal:</strong> ${formatDate(inv.visit_date)}<br>
                    <strong>Metode:</strong> ${inv.payment_method?.toUpperCase()}
                </div>
                <div style="text-align:right;">
                    <strong>No. RM:</strong> ${inv.no_rm}<br>
                    <strong>Pasien:</strong> ${escHtml(inv.patient_name)}<br>
                    <strong>Alamat:</strong> ${escHtml(inv.address || '-')}
                </div>
            </div>
            ${inv.procedures.length ? `
                <h4 style="color:#2F4156; font-size:14px; margin-bottom:8px;">Tindakan</h4>
                <table style="width:100%; border-collapse:collapse; margin-bottom:16px; font-size:13px;">
                    <thead><tr style="border-bottom:1px solid #C8D9E6;"><th style="text-align:left; padding:6px 0;">Item</th><th style="padding:6px;">Qty</th><th style="text-align:right; padding:6px 0;">Subtotal</th></tr></thead>
                    <tbody>${procRows}</tbody>
                </table>` : ''}
            ${inv.drugs.length ? `
                <h4 style="color:#2F4156; font-size:14px; margin-bottom:8px;">Obat</h4>
                <table style="width:100%; border-collapse:collapse; margin-bottom:16px; font-size:13px;">
                    <thead><tr style="border-bottom:1px solid #C8D9E6;"><th style="text-align:left; padding:6px 0;">Item</th><th style="padding:6px;">Qty</th><th style="text-align:right; padding:6px 0;">Subtotal</th></tr></thead>
                    <tbody>${drugRows}</tbody>
                </table>` : ''}
            <hr style="border:none; border-top:1px solid #C8D9E6;">
            <div style="max-width:300px; margin-left:auto; font-size:13px;">
                <div style="display:flex; justify-content:space-between; padding:4px 0;"><span>Total Tindakan</span><span>${formatRupiah(inv.total_procedures)}</span></div>
                <div style="display:flex; justify-content:space-between; padding:4px 0;"><span>Total Obat</span><span>${formatRupiah(inv.total_drugs)}</span></div>
                ${inv.discount > 0 ? `<div style="display:flex; justify-content:space-between; padding:4px 0;"><span>Diskon</span><span>-${formatRupiah(inv.discount)}</span></div>` : ''}
                <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:18px; font-weight:800; color:#2F4156; border-top:2px solid #2F4156;">
                    <span>Grand Total</span><span>${formatRupiah(inv.grand_total)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:4px 0;"><span>Dibayar</span><span>${formatRupiah(inv.payment_amount)}</span></div>
                <div style="display:flex; justify-content:space-between; padding:4px 0;"><span>Kembalian</span><span>${formatRupiah(inv.change_amount)}</span></div>
            </div>
            <div style="text-align:center; margin-top:32px; font-size:12px; color:#9BB0BD;">
                <p>Terima kasih atas kunjungan Anda</p>
                <p>Semoga lekas sembuh 🙏</p>
            </div>
        </div>
    `;

    document.getElementById('modalInvoice').classList.add('show');
}

function printInvoice() {
    if (App.billVisitId) showInvoicePreview(App.billVisitId);
}

function printInvoiceContent() {
    const content = document.getElementById('invoicePrintArea').innerHTML;
    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>Invoice</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>body{font-family:'Inter',sans-serif;padding:20px;max-width:800px;margin:auto;}table{width:100%;}td,th{padding:6px;}@media print{body{padding:0;}}</style>
        </head><body>${content}</body></html>`);
    win.document.close();
    setTimeout(() => { win.print(); }, 500);
}

// =================== PATIENTS LIST ===================
async function loadPatientsList(page = 1) {
    App.patientPage = page;
    const res = await api(`api/patients.php?action=list&page=${page}&limit=20`);
    if (!res.success) return;

    const body = document.getElementById('patientsTableBody');
    body.innerHTML = res.data.map(p => `
        <tr>
            <td><strong>${p.no_rm}</strong></td>
            <td>${escHtml(p.name)}</td>
            <td>${p.nik || '-'}</td>
            <td>${p.gender}</td>
            <td>${formatDate(p.birth_date)}</td>
            <td>${p.phone || '-'}</td>
            <td>${formatDate(p.created_at)}</td>
            <td>
                <div class="btn-group">
                    <button class="btn btn-outline btn-xs" onclick="editPatientFromList(${p.id})">Edit</button>
                    <button class="btn btn-danger btn-xs" onclick="deletePatient(${p.id})">Hapus</button>
                </div>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="8" class="text-center text-muted">Belum ada data pasien</td></tr>';

    // Pagination
    const pagDiv = document.getElementById('patientsPagination');
    pagDiv.innerHTML = `
        <span class="text-muted" style="font-size:12px;">Halaman ${res.page} dari ${res.pages} (${res.total} pasien)</span>
        <div class="btn-group">
            <button class="btn btn-outline btn-xs" ${res.page <= 1 ? 'disabled' : ''} onclick="loadPatientsList(${res.page - 1})">← Prev</button>
            <button class="btn btn-outline btn-xs" ${res.page >= res.pages ? 'disabled' : ''} onclick="loadPatientsList(${res.page + 1})">Next →</button>
        </div>
    `;
}

function searchPatientsList() {
    debounce(() => {
        const q = document.getElementById('searchPatientList').value.trim();
        if (q.length < 2) { loadPatientsList(); return; }
        // Use search API and render
        api(`api/patients.php?action=search&q=${encodeURIComponent(q)}`).then(res => {
            if (!res.success) return;
            const body = document.getElementById('patientsTableBody');
            body.innerHTML = res.data.map(p => `
                <tr>
                    <td><strong>${p.no_rm}</strong></td>
                    <td>${escHtml(p.name)}</td>
                    <td>${p.nik || '-'}</td>
                    <td>${p.gender}</td>
                    <td>${formatDate(p.birth_date)}</td>
                    <td>${p.phone || '-'}</td>
                    <td>-</td>
                    <td><button class="btn btn-outline btn-xs" onclick="editPatientFromList(${p.id})">Edit</button></td>
                </tr>
            `).join('') || '<tr><td colspan="8" class="text-center text-muted">Tidak ditemukan</td></tr>';
            document.getElementById('patientsPagination').innerHTML = '';
        });
    }, 300, 'searchPatientList');
}

function editPatientFromList(id) {
    navigateTo('registration');
    setTimeout(() => selectExistingPatient(id), 100);
}

async function deletePatient(id) {
    if (!confirm('Yakin ingin menghapus pasien ini? Semua data kunjungan akan ikut terhapus.')) return;
    const res = await apiPost('api/patients.php?action=delete', { id });
    if (res.success) { showToast('Pasien dihapus'); loadPatientsList(); }
    else showToast(res.message, 'error');
}

// =================== MASTER TINDAKAN ===================
async function loadMasterProcedures() {
    const res = await api('api/master.php?action=list_procedures');
    if (!res.success) return;

    document.getElementById('masterProcBody').innerHTML = res.data.map(p => `
        <tr>
            <td><strong>${p.code}</strong></td>
            <td>${escHtml(p.name)}</td>
            <td><span class="tag">${escHtml(p.category)}</span></td>
            <td class="text-right">${formatRupiah(p.price)}</td>
            <td>${p.is_active == 1 ? '<span class="text-success">Aktif</span>' : '<span class="text-muted">Nonaktif</span>'}</td>
            <td>
                <div class="btn-group">
                    <button class="btn btn-outline btn-xs" onclick='editProcedure(${JSON.stringify(p)})'>Edit</button>
                    <button class="btn btn-danger btn-xs" onclick="deleteProcedure(${p.id})">Nonaktifkan</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function showProcedureModal(data) {
    document.getElementById('mpId').value = data?.id || '';
    document.getElementById('mpCode').value = data?.code || '';
    document.getElementById('mpName').value = data?.name || '';
    document.getElementById('mpCategory').value = data?.category || '';
    document.getElementById('mpPrice').value = data?.price || '';
    document.getElementById('modalProcTitle').textContent = data ? 'Edit Tindakan' : 'Tambah Tindakan';
    document.getElementById('modalProcedure').classList.add('show');
}

function editProcedure(data) { showProcedureModal(data); }

async function saveMasterProcedure() {
    const id = document.getElementById('mpId').value;
    const body = {
        code: document.getElementById('mpCode').value.trim(),
        name: document.getElementById('mpName').value.trim(),
        category: document.getElementById('mpCategory').value.trim(),
        price: parseFloat(document.getElementById('mpPrice').value) || 0,
    };

    if (!body.name) { showToast('Nama tindakan harus diisi', 'error'); return; }

    let res;
    if (id) {
        body.id = parseInt(id);
        body.is_active = 1;
        res = await apiPost('api/master.php?action=update_procedure', body);
    } else {
        res = await apiPost('api/master.php?action=create_procedure', body);
    }

    if (res.success) {
        showToast(res.message);
        closeModal('modalProcedure');
        loadMasterProcedures();
    } else {
        showToast(res.message, 'error');
    }
}

async function deleteProcedure(id) {
    if (!confirm('Nonaktifkan tindakan ini?')) return;
    const res = await apiPost('api/master.php?action=delete_procedure', { id });
    if (res.success) { showToast('Tindakan dinonaktifkan'); loadMasterProcedures(); }
}

// =================== MASTER OBAT ===================
async function loadMasterDrugs() {
    const res = await api('api/master.php?action=list_drugs');
    if (!res.success) return;

    document.getElementById('masterDrugBody').innerHTML = res.data.map(d => `
        <tr>
            <td><strong>${d.code}</strong></td>
            <td>${escHtml(d.name)}</td>
            <td><span class="tag">${escHtml(d.category)}</span></td>
            <td>${d.unit}</td>
            <td class="text-right">${formatRupiah(d.price)}</td>
            <td class="text-right ${d.stock <= d.min_stock ? 'stock-low' : 'stock-ok'}">${d.stock}</td>
            <td>${d.is_active == 1 ? '<span class="text-success">Aktif</span>' : '<span class="text-muted">Nonaktif</span>'}</td>
            <td>
                <div class="btn-group">
                    <button class="btn btn-outline btn-xs" onclick='editDrug(${JSON.stringify(d)})'>Edit</button>
                    <button class="btn btn-danger btn-xs" onclick="deleteDrug(${d.id})">Nonaktifkan</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function showDrugModal(data) {
    document.getElementById('mdId').value = data?.id || '';
    document.getElementById('mdCode').value = data?.code || '';
    document.getElementById('mdName').value = data?.name || '';
    document.getElementById('mdCategory').value = data?.category || '';
    document.getElementById('mdUnit').value = data?.unit || 'Tablet';
    document.getElementById('mdPrice').value = data?.price || '';
    document.getElementById('mdStock').value = data?.stock || 0;
    document.getElementById('mdMinStock').value = data?.min_stock || 10;
    document.getElementById('modalDrugTitle').textContent = data ? 'Edit Obat' : 'Tambah Obat';
    document.getElementById('modalDrug').classList.add('show');
}

function editDrug(data) { showDrugModal(data); }

async function saveMasterDrug() {
    const id = document.getElementById('mdId').value;
    const body = {
        code: document.getElementById('mdCode').value.trim(),
        name: document.getElementById('mdName').value.trim(),
        category: document.getElementById('mdCategory').value.trim(),
        unit: document.getElementById('mdUnit').value.trim(),
        price: parseFloat(document.getElementById('mdPrice').value) || 0,
        stock: parseInt(document.getElementById('mdStock').value) || 0,
        min_stock: parseInt(document.getElementById('mdMinStock').value) || 10,
    };

    if (!body.name) { showToast('Nama obat harus diisi', 'error'); return; }

    let res;
    if (id) {
        body.id = parseInt(id);
        body.is_active = 1;
        res = await apiPost('api/master.php?action=update_drug', body);
    } else {
        res = await apiPost('api/master.php?action=create_drug', body);
    }

    if (res.success) {
        showToast(res.message);
        closeModal('modalDrug');
        loadMasterDrugs();
    } else {
        showToast(res.message, 'error');
    }
}

async function deleteDrug(id) {
    if (!confirm('Nonaktifkan obat ini?')) return;
    const res = await apiPost('api/master.php?action=delete_drug', { id });
    if (res.success) { showToast('Obat dinonaktifkan'); loadMasterDrugs(); }
}

// =================== MODAL HELPERS ===================
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

// =================== LOGOUT ===================
async function handleLogout() {
    await api('api/auth.php?action=logout');
    window.location.href = 'login.php';
}
