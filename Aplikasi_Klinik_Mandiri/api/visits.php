<?php
/**
 * API: Visits - Kunjungan & Antrean
 */
require_once __DIR__ . '/../config/db.php';
requireAuth();

$action = $_GET['action'] ?? '';
$pdo = getDB();

switch ($action) {
    case 'today':
        getTodayVisits();
        break;
    case 'create':
        createVisit();
        break;
    case 'get':
        getVisit();
        break;
    case 'update_status':
        updateVisitStatus();
        break;
    case 'cancel':
        cancelVisit();
        break;
    case 'stats':
        getDashboardStats();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function getTodayVisits() {
    global $pdo;
    $date = $_GET['date'] ?? date('Y-m-d');
    $status = $_GET['status'] ?? null;

    $sql = "SELECT v.*, p.no_rm, p.name as patient_name, p.gender, p.birth_date, p.phone,
                   vt.complaint, vt.diagnosis,
                   i.grand_total, i.status as payment_status
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN vitals vt ON vt.visit_id = v.id
            LEFT JOIN invoices i ON i.visit_id = v.id
            WHERE v.visit_date = :visit_date";

    $params = ['visit_date' => $date];

    if ($status && $status !== 'all') {
        $sql .= " AND v.status = :status";
        $params['status'] = $status;
    }

    $sql .= " ORDER BY v.queue_number ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function createVisit() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $patientId = (int)($input['patient_id'] ?? 0);

    if ($patientId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Patient ID tidak valid'], 400);
    }

    // Check if patient already has active visit today
    $today = date('Y-m-d');
    $check = $pdo->prepare("SELECT id FROM visits WHERE patient_id = :pid AND visit_date = :vd AND status NOT IN ('done','cancelled')");
    $check->execute(['pid' => $patientId, 'vd' => $today]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Pasien sudah memiliki kunjungan aktif hari ini'], 400);
    }

    // Get next queue number
    $qStmt = $pdo->prepare("SELECT COALESCE(MAX(queue_number), 0) + 1 FROM visits WHERE visit_date = :vd");
    $qStmt->execute(['vd' => $today]);
    $queueNumber = (int)$qStmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO visits (patient_id, visit_date, queue_number, status) VALUES (:pid, :vd, :qn, 'waiting')");
    $stmt->execute([
        'pid' => $patientId,
        'vd'  => $today,
        'qn'  => $queueNumber,
    ]);

    $visitId = $pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => "Kunjungan berhasil dibuat. Nomor antrean: {$queueNumber}",
        'data'    => ['id' => $visitId, 'queue_number' => $queueNumber]
    ], 201);
}

function getVisit() {
    global $pdo;
    $id = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT v.*, p.no_rm, p.nik, p.name as patient_name, p.birth_date, p.gender, 
               p.address, p.phone, p.blood_type, p.allergy
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        WHERE v.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $visit = $stmt->fetch();

    if (!$visit) {
        jsonResponse(['success' => false, 'message' => 'Kunjungan tidak ditemukan'], 404);
    }

    // Get vitals
    $vStmt = $pdo->prepare("SELECT * FROM vitals WHERE visit_id = :vid");
    $vStmt->execute(['vid' => $id]);
    $visit['vitals'] = $vStmt->fetch() ?: null;

    // Get procedures
    $pStmt = $pdo->prepare("SELECT vp.*, mp.code, mp.name as procedure_name, mp.category 
                            FROM visit_procedures vp 
                            JOIN master_procedures mp ON mp.id = vp.procedure_id 
                            WHERE vp.visit_id = :vid");
    $pStmt->execute(['vid' => $id]);
    $visit['procedures'] = $pStmt->fetchAll();

    // Get drugs
    $dStmt = $pdo->prepare("SELECT vd.*, md.code, md.name as drug_name, md.unit, md.category 
                            FROM visit_drugs vd 
                            JOIN master_drugs md ON md.id = vd.drug_id 
                            WHERE vd.visit_id = :vid");
    $dStmt->execute(['vid' => $id]);
    $visit['drugs'] = $dStmt->fetchAll();

    // Get invoice
    $iStmt = $pdo->prepare("SELECT * FROM invoices WHERE visit_id = :vid");
    $iStmt->execute(['vid' => $id]);
    $visit['invoice'] = $iStmt->fetch() ?: null;

    jsonResponse(['success' => true, 'data' => $visit]);
}

function updateVisitStatus() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);
    $status = $input['status'] ?? '';

    $valid = ['waiting','examination','action','pharmacy','cashier','done','cancelled'];
    if (!in_array($status, $valid)) {
        jsonResponse(['success' => false, 'message' => 'Status tidak valid'], 400);
    }

    $stmt = $pdo->prepare("UPDATE visits SET status = :status WHERE id = :id");
    $stmt->execute(['status' => $status, 'id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Status kunjungan diperbarui']);
}

function cancelVisit() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    // Restore drug stock if any drugs were prescribed
    $drugs = $pdo->prepare("SELECT drug_id, quantity FROM visit_drugs WHERE visit_id = :vid");
    $drugs->execute(['vid' => $id]);
    foreach ($drugs->fetchAll() as $drug) {
        $pdo->prepare("UPDATE master_drugs SET stock = stock + :qty WHERE id = :did")
            ->execute(['qty' => $drug['quantity'], 'did' => $drug['drug_id']]);
    }

    $pdo->prepare("UPDATE visits SET status = 'cancelled' WHERE id = :id")->execute(['id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Kunjungan dibatalkan, stok obat dikembalikan']);
}

function getDashboardStats() {
    global $pdo;
    $today = date('Y-m-d');

    // Today's summary
    $todayStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_visits,
            SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
            SUM(CASE WHEN status = 'examination' THEN 1 ELSE 0 END) as examination,
            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done
        FROM visits WHERE visit_date = :today
    ");
    $todayStats->execute(['today' => $today]);
    $stats = $todayStats->fetch();

    // Today's revenue
    $revStmt = $pdo->prepare("
        SELECT COALESCE(SUM(i.grand_total), 0) as revenue
        FROM invoices i
        JOIN visits v ON v.id = i.visit_id
        WHERE v.visit_date = :today AND i.status = 'paid'
    ");
    $revStmt->execute(['today' => $today]);
    $stats['revenue'] = $revStmt->fetchColumn();

    // Total patients
    $stats['total_patients'] = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

    // Low stock alerts
    $lowStock = $pdo->query("SELECT COUNT(*) FROM master_drugs WHERE stock <= min_stock AND is_active = 1")->fetchColumn();
    $stats['low_stock_count'] = (int)$lowStock;

    jsonResponse(['success' => true, 'data' => $stats]);
}
