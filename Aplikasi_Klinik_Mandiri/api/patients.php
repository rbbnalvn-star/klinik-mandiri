<?php
/**
 * API: Patients - CRUD & Search
 */
require_once __DIR__ . '/../config/db.php';
requireAuth();

$action = $_GET['action'] ?? '';
$pdo = getDB();

switch ($action) {
    case 'list':
        listPatients();
        break;
    case 'search':
        searchPatients();
        break;
    case 'get':
        getPatient();
        break;
    case 'create':
        createPatient();
        break;
    case 'update':
        updatePatient();
        break;
    case 'delete':
        deletePatient();
        break;
    case 'history':
        getPatientHistory();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function listPatients() {
    global $pdo;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->query("SELECT COUNT(*) FROM patients");
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM patients ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $patients = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data'    => $patients,
        'total'   => $total,
        'page'    => $page,
        'pages'   => ceil($total / $limit),
    ]);
}

function searchPatients() {
    global $pdo;
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        jsonResponse(['success' => true, 'data' => []]);
    }
    $like = "%{$q}%";
    $stmt = $pdo->prepare("SELECT id, no_rm, nik, name, birth_date, gender, phone 
                           FROM patients 
                           WHERE name LIKE :q OR no_rm LIKE :q2 OR nik LIKE :q3 
                           ORDER BY name ASC LIMIT 15");
    $stmt->execute(['q' => $like, 'q2' => $like, 'q3' => $like]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getPatient() {
    global $pdo;
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $patient = $stmt->fetch();
    if (!$patient) {
        jsonResponse(['success' => false, 'message' => 'Pasien tidak ditemukan'], 404);
    }
    jsonResponse(['success' => true, 'data' => $patient]);
}

function createPatient() {
    requirePost();
    global $pdo;
    $input = getJsonInput();

    $name = trim($input['name'] ?? '');
    if (empty($name)) {
        jsonResponse(['success' => false, 'message' => 'Nama pasien harus diisi'], 400);
    }

    $no_rm = generateNoRM($pdo);

    $stmt = $pdo->prepare("INSERT INTO patients (no_rm, nik, name, birth_date, gender, address, phone, blood_type, allergy)
                           VALUES (:no_rm, :nik, :name, :birth_date, :gender, :address, :phone, :blood_type, :allergy)");
    $stmt->execute([
        'no_rm'      => $no_rm,
        'nik'        => $input['nik'] ?? null,
        'name'       => $name,
        'birth_date' => $input['birth_date'] ?? null,
        'gender'     => $input['gender'] ?? 'L',
        'address'    => $input['address'] ?? null,
        'phone'      => $input['phone'] ?? null,
        'blood_type' => $input['blood_type'] ?? '-',
        'allergy'    => $input['allergy'] ?? null,
    ]);

    $patientId = $pdo->lastInsertId();
    jsonResponse([
        'success' => true,
        'message' => 'Pasien berhasil didaftarkan',
        'data'    => ['id' => $patientId, 'no_rm' => $no_rm]
    ], 201);
}

function updatePatient() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    $stmt = $pdo->prepare("UPDATE patients SET 
        nik = :nik, name = :name, birth_date = :birth_date, gender = :gender,
        address = :address, phone = :phone, blood_type = :blood_type, allergy = :allergy
        WHERE id = :id");
    $stmt->execute([
        'id'         => $id,
        'nik'        => $input['nik'] ?? null,
        'name'       => $input['name'] ?? '',
        'birth_date' => $input['birth_date'] ?? null,
        'gender'     => $input['gender'] ?? 'L',
        'address'    => $input['address'] ?? null,
        'phone'      => $input['phone'] ?? null,
        'blood_type' => $input['blood_type'] ?? '-',
        'allergy'    => $input['allergy'] ?? null,
    ]);

    jsonResponse(['success' => true, 'message' => 'Data pasien berhasil diperbarui']);
}

function deletePatient() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = :id");
    $stmt->execute(['id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Pasien berhasil dihapus']);
}

function getPatientHistory() {
    global $pdo;
    $patientId = (int)($_GET['patient_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT v.*, vt.complaint, vt.diagnosis, vt.icd_code,
               i.grand_total, i.status as payment_status
        FROM visits v
        LEFT JOIN vitals vt ON vt.visit_id = v.id
        LEFT JOIN invoices i ON i.visit_id = v.id
        WHERE v.patient_id = :pid
        ORDER BY v.visit_date DESC, v.id DESC
        LIMIT 50
    ");
    $stmt->execute(['pid' => $patientId]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}
