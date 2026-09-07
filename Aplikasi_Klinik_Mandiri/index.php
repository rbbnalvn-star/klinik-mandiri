<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userName = $_SESSION['name'] ?? 'Admin';
$userRole = $_SESSION['role'] ?? 'admin';
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem Manajemen Klinik Praktek Mandiri - Dashboard">
    <title>Dashboard - Klinik Praktek Mandiri</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
<div class="app-layout">
    <!-- ========== SIDEBAR ========== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
                <div class="logo-text">
                    <h2>Klinik Mandiri</h2>
                    <p>Praktek Dokter</p>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Menu Utama</div>
            <div class="nav-item active" data-page="dashboard" onclick="navigateTo('dashboard')">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </div>
            <div class="nav-item" data-page="registration" onclick="navigateTo('registration')">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                Pendaftaran
            </div>
            <div class="nav-item" data-page="examination" onclick="navigateTo('examination')">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Pemeriksaan
            </div>
            <div class="nav-item" data-page="billing" onclick="navigateTo('billing')">
                <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Kasir
            </div>

            <div class="nav-label">Data & Laporan</div>
            <div class="nav-item" data-page="patients" onclick="navigateTo('patients')">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Data Pasien
            </div>
            <div class="nav-item" data-page="reports" onclick="navigateTo('reports')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Laporan
            </div>

            <div class="nav-label">Pengaturan</div>
            <div class="nav-item" data-page="master-procedures" onclick="navigateTo('master-procedures')">
                <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Master Tindakan
            </div>
            <div class="nav-item" data-page="master-drugs" onclick="navigateTo('master-drugs')">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Master Obat
                <span class="badge" id="lowStockBadge" style="display:none;">0</span>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
                <div class="user-detail">
                    <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="user-role"><?= htmlspecialchars(ucfirst($userRole)) ?></div>
                </div>
            </div>
            <button class="btn-logout" onclick="handleLogout()">
                Keluar
            </button>
        </div>
    </aside>

    <!-- ========== MAIN CONTENT ========== -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <div class="page-title" id="pageTitle">Dashboard</div>
                <div class="page-subtitle" id="pageSubtitle">Ringkasan aktivitas klinik hari ini</div>
            </div>
            <div class="top-bar-right">
                <div class="date-display" id="dateDisplay"></div>
            </div>
        </div>

        <div class="content-area" id="contentArea">

            <!-- ===== DASHBOARD ===== -->
            <div class="page-section active" id="page-dashboard">
                <div class="stats-grid" id="statsGrid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value" id="statTotalPatients">-</div>
                            <div class="stat-label">Total Pasien</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value" id="statTodayVisits">-</div>
                            <div class="stat-label">Kunjungan Hari Ini</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value" id="statWaiting">-</div>
                            <div class="stat-label">Menunggu</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value" id="statRevenue">-</div>
                            <div class="stat-label">Pendapatan Hari Ini</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Antrean Hari Ini</h3>
                        <button class="btn btn-primary btn-sm" onclick="navigateTo('registration')">
                            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Pasien Baru
                        </button>
                    </div>
                    <div class="queue-list" id="queueList">
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            <h4>Belum ada antrean</h4>
                            <p>Daftarkan pasien baru untuk memulai</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== PENDAFTARAN ===== -->
            <div class="page-section" id="page-registration">
                <div class="card mb-3">
                    <div class="card-header">
                        <h3>Cari Pasien Terdaftar</h3>
                    </div>
                    <div class="search-box" style="max-width:100%;">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="searchPatient" placeholder="Ketik nama, No. RM, atau NIK pasien..." autocomplete="off">
                        <div class="autocomplete-dropdown" id="searchDropdown"></div>
                    </div>
                    <div id="selectedPatientInfo" class="mt-2" style="display:none;"></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 id="regFormTitle">Daftarkan Pasien Baru</h3>
                    </div>
                    <form id="patientForm" onsubmit="return false;">
                        <input type="hidden" id="patientId" value="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="required">*</span></label>
                                <input type="text" class="form-control" id="patName" placeholder="Nama lengkap pasien" required>
                            </div>
                            <div class="form-group">
                                <label>NIK</label>
                                <input type="text" class="form-control" id="patNIK" placeholder="16 digit NIK" maxlength="16">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tanggal Lahir</label>
                                <input type="date" class="form-control" id="patBirthDate">
                            </div>
                            <div class="form-group">
                                <label>Jenis Kelamin</label>
                                <select class="form-control" id="patGender">
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Golongan Darah</label>
                                <select class="form-control" id="patBlood">
                                    <option value="-">Tidak diketahui</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="AB">AB</option>
                                    <option value="O">O</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>No. Telepon</label>
                                <input type="tel" class="form-control" id="patPhone" placeholder="08xxxxxxxxxx">
                            </div>
                            <div class="form-group">
                                <label>Alergi</label>
                                <input type="text" class="form-control" id="patAllergy" placeholder="Jika ada alergi obat/makanan">
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label>Alamat</label>
                            <textarea class="form-control" id="patAddress" placeholder="Alamat lengkap" rows="2"></textarea>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary" onclick="savePatient()">
                                <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Simpan & Daftarkan Kunjungan
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetPatientForm()">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ===== PEMERIKSAAN ===== -->
            <div class="page-section" id="page-examination">
                <div class="card mb-3" id="examSelectVisit">
                    <div class="card-header"><h3>Pilih Pasien untuk Diperiksa</h3></div>
                    <div class="queue-list" id="examQueueList"></div>
                </div>

                <div id="examFormContainer" style="display:none;">
                    <div class="card mb-3" style="background: rgba(86,124,141,0.04); border-color: var(--secondary);">
                        <div class="flex-between">
                            <div>
                                <strong id="examPatientName" style="font-size:16px;"></strong>
                                <div class="text-muted mt-1" id="examPatientDetail"></div>
                            </div>
                            <button class="btn btn-outline btn-sm" onclick="deselectVisit()">Kembali</button>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3>Data Pemeriksaan</h3></div>
                        <form id="vitalsForm" onsubmit="return false;">
                            <input type="hidden" id="examVisitId">
                            <div class="form-row">
                                <div class="form-group"><label>Tekanan Darah</label><input class="form-control" id="vBP" placeholder="120/80 mmHg"></div>
                                <div class="form-group"><label>Berat Badan (kg)</label><input type="number" class="form-control" id="vWeight" placeholder="kg"></div>
                                <div class="form-group"><label>Tinggi Badan (cm)</label><input type="number" class="form-control" id="vHeight" placeholder="cm"></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Suhu (°C)</label><input type="number" class="form-control" id="vTemp" placeholder="°C"></div>
                                <div class="form-group"><label>Nadi (x/mnt)</label><input type="number" class="form-control" id="vPulse" placeholder="x/menit"></div>
                                <div class="form-group"><label>Respiratory Rate</label><input type="number" class="form-control" id="vRR" placeholder="x/menit"></div>
                            </div>
                            <div class="form-group mb-2">
                                <label>Keluhan Utama <span class="required">*</span></label>
                                <textarea class="form-control" id="vComplaint" rows="3" placeholder="Keluhan utama pasien..."></textarea>
                            </div>
                            <div class="form-group mb-2">
                                <label>Pemeriksaan Fisik</label>
                                <textarea class="form-control" id="vPhysicalExam" rows="2" placeholder="Hasil pemeriksaan fisik..."></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Diagnosa <span class="required">*</span></label>
                                    <input class="form-control" id="vDiagnosis" placeholder="Diagnosis utama">
                                </div>
                                <div class="form-group">
                                    <label>Kode ICD-10</label>
                                    <input class="form-control" id="vICD" placeholder="Contoh: J00">
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label>Catatan Tambahan</label>
                                <textarea class="form-control" id="vNotes" rows="2" placeholder="Catatan dokter..."></textarea>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="saveVitals()">
                                <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Simpan Pemeriksaan
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ===== KASIR / BILLING ===== -->
            <div class="page-section" id="page-billing">
                <div class="card mb-3" id="billingSelectVisit">
                    <div class="card-header"><h3>Pilih Kunjungan untuk Proses Pembayaran</h3></div>
                    <div class="queue-list" id="billingQueueList"></div>
                </div>

                <div id="billingContainer" style="display:none;">
                    <div class="card mb-3" style="background: rgba(86,124,141,0.04); border-color: var(--secondary);">
                        <div class="flex-between">
                            <div>
                                <strong id="billPatientName" style="font-size:16px;"></strong>
                                <div class="text-muted mt-1" id="billPatientDetail"></div>
                            </div>
                            <button class="btn btn-outline btn-sm" onclick="deselectBilling()">Kembali</button>
                        </div>
                    </div>

                    <!-- Tabs: Tindakan / Obat / Pembayaran -->
                    <div class="content-tabs">
                        <button class="content-tab active" onclick="switchBillingTab('procedures')">Tindakan</button>
                        <button class="content-tab" onclick="switchBillingTab('drugs')">Resep Obat</button>
                        <button class="content-tab" onclick="switchBillingTab('payment')">Pembayaran</button>
                    </div>

                    <!-- Tab: Tindakan -->
                    <div class="card mb-3 billing-tab" id="tab-procedures">
                        <div class="card-header">
                            <h3>Tambah Tindakan</h3>
                        </div>
                        <div class="form-row">
                            <div class="form-group flex-1">
                                <label>Pilih Tindakan</label>
                                <select class="form-control" id="selProcedure"><option value="">-- Pilih tindakan --</option></select>
                            </div>
                            <div class="form-group" style="width:100px;">
                                <label>Jumlah</label>
                                <input type="number" class="form-control" id="procQty" value="1" min="1">
                            </div>
                            <div class="form-group" style="align-self:flex-end;">
                                <button class="btn btn-primary btn-sm" onclick="addProcedure()">Tambah</button>
                            </div>
                        </div>
                        <div class="divider"></div>
                        <table class="data-table">
                            <thead><tr><th>Kode</th><th>Tindakan</th><th>Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th><th></th></tr></thead>
                            <tbody id="procTableBody"></tbody>
                            <tfoot><tr><td colspan="4" class="text-right text-bold">Total Tindakan</td><td class="text-right text-bold" id="totalProcedures">Rp 0</td><td></td></tr></tfoot>
                        </table>
                    </div>

                    <!-- Tab: Obat -->
                    <div class="card mb-3 billing-tab" id="tab-drugs" style="display:none;">
                        <div class="card-header"><h3>Tambah Resep Obat</h3></div>
                        <div class="form-row">
                            <div class="form-group flex-1">
                                <label>Cari Obat</label>
                                <div class="search-box" style="max-width:100%;">
                                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" id="searchDrug" placeholder="Ketik nama obat..." autocomplete="off">
                                    <div class="autocomplete-dropdown" id="drugDropdown"></div>
                                </div>
                                <input type="hidden" id="selDrugId">
                                <div class="text-muted mt-1" id="selDrugInfo" style="font-size:12px;"></div>
                            </div>
                            <div class="form-group" style="width:80px;">
                                <label>Qty</label>
                                <input type="number" class="form-control" id="drugQty" value="1" min="1">
                            </div>
                            <div class="form-group flex-1">
                                <label>Aturan Pakai</label>
                                <input type="text" class="form-control" id="drugDosage" placeholder="3x1 sesudah makan">
                            </div>
                            <div class="form-group" style="align-self:flex-end;">
                                <button class="btn btn-primary btn-sm" onclick="addDrug()">Tambah</button>
                            </div>
                        </div>
                        <div class="divider"></div>
                        <table class="data-table">
                            <thead><tr><th>Kode</th><th>Obat</th><th>Qty</th><th>Aturan Pakai</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th><th></th></tr></thead>
                            <tbody id="drugTableBody"></tbody>
                            <tfoot><tr><td colspan="5" class="text-right text-bold">Total Obat</td><td class="text-right text-bold" id="totalDrugs">Rp 0</td><td></td></tr></tfoot>
                        </table>
                    </div>

                    <!-- Tab: Pembayaran -->
                    <div class="card billing-tab" id="tab-payment" style="display:none;">
                        <div class="card-header"><h3>Ringkasan Pembayaran</h3></div>
                        <div style="max-width:500px;">
                            <div class="flex-between mb-1"><span>Total Tindakan</span><strong id="payTotalProc">Rp 0</strong></div>
                            <div class="flex-between mb-1"><span>Total Obat</span><strong id="payTotalDrugs">Rp 0</strong></div>
                            <div class="divider"></div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Diskon (Rp)</label>
                                    <input type="number" class="form-control" id="payDiscount" value="0" min="0" oninput="calcPayment()">
                                </div>
                                <div class="form-group">
                                    <label>Metode Pembayaran</label>
                                    <select class="form-control" id="payMethod">
                                        <option value="cash">Tunai</option>
                                        <option value="debit">Debit</option>
                                        <option value="credit">Kredit</option>
                                        <option value="transfer">Transfer</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex-between mb-2" style="font-size:20px; font-weight:800; color:var(--primary);">
                                <span>Grand Total</span>
                                <span id="payGrandTotal">Rp 0</span>
                            </div>
                            <div class="form-group mb-2">
                                <label>Jumlah Bayar</label>
                                <input type="number" class="form-control" id="payAmount" min="0" oninput="calcPayment()" style="font-size:18px; font-weight:700;">
                            </div>
                            <div class="flex-between mb-3" style="font-size:16px;">
                                <span>Kembalian</span>
                                <strong id="payChange" style="color:var(--success);">Rp 0</strong>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-success btn-lg" onclick="processPayment()">
                                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    Proses Pembayaran
                                </button>
                                <button class="btn btn-outline" onclick="printInvoice()">
                                    <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    Cetak Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== DATA PASIEN ===== -->
            <div class="page-section" id="page-patients">
                <div class="card">
                    <div class="card-header">
                        <h3>Daftar Pasien</h3>
                        <div class="search-box">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="searchPatientList" placeholder="Cari pasien..." oninput="searchPatientsList()">
                        </div>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr><th>No. RM</th><th>Nama</th><th>NIK</th><th>L/P</th><th>Tgl Lahir</th><th>Telepon</th><th>Terdaftar</th><th></th></tr>
                            </thead>
                            <tbody id="patientsTableBody"></tbody>
                        </table>
                    </div>
                    <div class="flex-between mt-2" id="patientsPagination"></div>
                </div>
            </div>

            <!-- ===== LAPORAN ===== -->
            <div class="page-section" id="page-reports">
                <div class="content-tabs">
                    <button class="content-tab active" onclick="switchReportTab('revenue')">Pendapatan</button>
                    <button class="content-tab" onclick="switchReportTab('visits')">Kunjungan</button>
                    <button class="content-tab" onclick="switchReportTab('diagnoses')">Diagnosa</button>
                    <button class="content-tab" onclick="switchReportTab('detail')">Detail Harian</button>
                </div>

                <div class="report-tab" id="report-revenue">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3>Grafik Pendapatan</h3>
                            <div class="btn-group">
                                <select class="form-control" id="revPeriod" style="width:120px;" onchange="loadRevenueChart()">
                                    <option value="daily">Harian</option>
                                    <option value="monthly" selected>Bulanan</option>
                                </select>
                                <select class="form-control" id="revYear" style="width:100px;" onchange="loadRevenueChart()"></select>
                                <select class="form-control" id="revMonth" style="width:120px;" onchange="loadRevenueChart()">
                                    <option value="1">Januari</option><option value="2">Februari</option><option value="3">Maret</option>
                                    <option value="4">April</option><option value="5">Mei</option><option value="6">Juni</option>
                                    <option value="7">Juli</option><option value="8">Agustus</option><option value="9">September</option>
                                    <option value="10">Oktober</option><option value="11">November</option><option value="12">Desember</option>
                                </select>
                                <button class="btn btn-outline btn-sm" onclick="exportRevenueExcel()">📊 Excel</button>
                                <button class="btn btn-outline btn-sm" onclick="exportRevenuePDF()">📄 PDF</button>
                            </div>
                        </div>
                        <div style="height:350px; position:relative;"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>

                <div class="report-tab" id="report-visits" style="display:none;">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3>Demografi Pasien</h3>
                            <div class="btn-group">
                                <button class="btn btn-outline btn-sm" onclick="exportDemographicsExcel()">📊 Excel</button>
                                <button class="btn btn-outline btn-sm" onclick="exportDemographicsPDF()">📄 PDF</button>
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
                            <div style="height:300px; position:relative;"><canvas id="genderChart"></canvas></div>
                            <div style="height:300px; position:relative;"><canvas id="ageChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="report-tab" id="report-diagnoses" style="display:none;">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3>10 Diagnosa Terbanyak</h3>
                            <div class="btn-group">
                                <input type="date" class="form-control" id="diagFrom" style="width:150px;">
                                <input type="date" class="form-control" id="diagTo" style="width:150px;">
                                <button class="btn btn-primary btn-sm" onclick="loadTopDiagnoses()">Filter</button>
                                <button class="btn btn-outline btn-sm" onclick="exportDiagnosesExcel()">📊 Excel</button>
                                <button class="btn btn-outline btn-sm" onclick="exportDiagnosesPDF()">📄 PDF</button>
                            </div>
                        </div>
                        <div style="height:350px; position:relative;"><canvas id="diagnosesChart"></canvas></div>
                    </div>
                </div>

                <div class="report-tab" id="report-detail" style="display:none;">
                    <div class="card">
                        <div class="card-header">
                            <h3>Laporan Detail Harian</h3>
                            <div class="btn-group">
                                <input type="date" class="form-control" id="detailFrom" style="width:150px;">
                                <input type="date" class="form-control" id="detailTo" style="width:150px;">
                                <button class="btn btn-primary btn-sm" onclick="loadDailyDetail()">Filter</button>
                                <button class="btn btn-outline btn-sm" onclick="exportDetailExcel()">📊 Excel</button>
                                <button class="btn btn-outline btn-sm" onclick="exportDetailPDF()">📄 PDF</button>
                            </div>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead><tr><th>Tanggal</th><th>No. Antrean</th><th>No. RM</th><th>Nama Pasien</th><th>Diagnosa</th><th>No. Invoice</th><th class="text-right">Total</th><th>Status</th></tr></thead>
                                <tbody id="detailTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== MASTER TINDAKAN ===== -->
            <div class="page-section" id="page-master-procedures">
                <div class="card">
                    <div class="card-header">
                        <h3>Daftar Tindakan Medis</h3>
                        <button class="btn btn-primary btn-sm" onclick="showProcedureModal()">
                            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Tindakan
                        </button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead><tr><th>Kode</th><th>Nama Tindakan</th><th>Kategori</th><th class="text-right">Tarif</th><th>Status</th><th></th></tr></thead>
                            <tbody id="masterProcBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ===== MASTER OBAT ===== -->
            <div class="page-section" id="page-master-drugs">
                <div class="card">
                    <div class="card-header">
                        <h3>Inventaris Obat & Alkes</h3>
                        <button class="btn btn-primary btn-sm" onclick="showDrugModal()">
                            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Obat
                        </button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead><tr><th>Kode</th><th>Nama Obat</th><th>Kategori</th><th>Satuan</th><th class="text-right">Harga</th><th class="text-right">Stok</th><th>Status</th><th></th></tr></thead>
                            <tbody id="masterDrugBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- /content-area -->
    </div><!-- /main-content -->
</div><!-- /app-layout -->

<!-- ===== MODAL: Tindakan ===== -->
<div class="modal-overlay" id="modalProcedure">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalProcTitle">Tambah Tindakan</h3>
            <button class="modal-close" onclick="closeModal('modalProcedure')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="mpId">
            <div class="form-row">
                <div class="form-group"><label>Kode</label><input class="form-control" id="mpCode" placeholder="TDK-XXX"></div>
                <div class="form-group"><label>Kategori</label><input class="form-control" id="mpCategory" placeholder="Konsultasi"></div>
            </div>
            <div class="form-group mb-2"><label>Nama Tindakan</label><input class="form-control" id="mpName" placeholder="Nama tindakan"></div>
            <div class="form-group"><label>Tarif (Rp)</label><input type="number" class="form-control" id="mpPrice" placeholder="0"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalProcedure')">Batal</button>
            <button class="btn btn-primary" onclick="saveMasterProcedure()">Simpan</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: Obat ===== -->
<div class="modal-overlay" id="modalDrug">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalDrugTitle">Tambah Obat</h3>
            <button class="modal-close" onclick="closeModal('modalDrug')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="mdId">
            <div class="form-row">
                <div class="form-group"><label>Kode</label><input class="form-control" id="mdCode" placeholder="OBT-XXX"></div>
                <div class="form-group"><label>Kategori</label><input class="form-control" id="mdCategory" placeholder="Analgesik"></div>
            </div>
            <div class="form-group mb-2"><label>Nama Obat</label><input class="form-control" id="mdName" placeholder="Nama obat"></div>
            <div class="form-row">
                <div class="form-group"><label>Satuan</label><input class="form-control" id="mdUnit" placeholder="Tablet"></div>
                <div class="form-group"><label>Harga (Rp)</label><input type="number" class="form-control" id="mdPrice" placeholder="0"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Stok</label><input type="number" class="form-control" id="mdStock" placeholder="0"></div>
                <div class="form-group"><label>Stok Minimum</label><input type="number" class="form-control" id="mdMinStock" value="10"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalDrug')">Batal</button>
            <button class="btn btn-primary" onclick="saveMasterDrug()">Simpan</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: Invoice Preview ===== -->
<div class="modal-overlay" id="modalInvoice">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Preview Invoice</h3>
            <button class="modal-close" onclick="closeModal('modalInvoice')">&times;</button>
        </div>
        <div class="modal-body" id="invoicePreviewContent"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalInvoice')">Tutup</button>
            <button class="btn btn-primary" onclick="printInvoiceContent()">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Cetak
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script src="assets/js/app.js"></script>
<script src="assets/js/reports.js"></script>
</body>
</html>
