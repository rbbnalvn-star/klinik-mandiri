<?php
/**
 * API: Master Data - Tindakan & Obat
 */
require_once __DIR__ . '/../config/db.php';
requireAuth();

$action = $_GET['action'] ?? '';
$pdo = getDB();

switch ($action) {
    // --- Procedures ---
    case 'list_procedures':
        listProcedures();
        break;
    case 'create_procedure':
        createProcedure();
        break;
    case 'update_procedure':
        updateProcedure();
        break;
    case 'delete_procedure':
        deleteProcedure();
        break;

    // --- Drugs ---
    case 'list_drugs':
        listDrugs();
        break;
    case 'search_drugs':
        searchDrugs();
        break;
    case 'create_drug':
        createDrug();
        break;
    case 'update_drug':
        updateDrug();
        break;
    case 'delete_drug':
        deleteDrug();
        break;
    case 'low_stock':
        lowStockDrugs();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

// =================== PROCEDURES ===================

function listProcedures() {
    global $pdo;
    $activeOnly = ($_GET['active_only'] ?? '0') === '1';
    $sql = "SELECT * FROM master_procedures";
    if ($activeOnly) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY category ASC, name ASC";
    jsonResponse(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
}

function createProcedure() {
    requirePost();
    global $pdo;
    $input = getJsonInput();

    $stmt = $pdo->prepare("INSERT INTO master_procedures (code, name, category, price) VALUES (:code, :name, :cat, :price)");
    $stmt->execute([
        'code'  => $input['code'] ?? '',
        'name'  => $input['name'] ?? '',
        'cat'   => $input['category'] ?? 'Umum',
        'price' => (float)($input['price'] ?? 0),
    ]);

    jsonResponse(['success' => true, 'message' => 'Tindakan berhasil ditambahkan', 'data' => ['id' => $pdo->lastInsertId()]], 201);
}

function updateProcedure() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    $stmt = $pdo->prepare("UPDATE master_procedures SET code = :code, name = :name, category = :cat, price = :price, is_active = :active WHERE id = :id");
    $stmt->execute([
        'id'     => $id,
        'code'   => $input['code'] ?? '',
        'name'   => $input['name'] ?? '',
        'cat'    => $input['category'] ?? 'Umum',
        'price'  => (float)($input['price'] ?? 0),
        'active' => (int)($input['is_active'] ?? 1),
    ]);

    jsonResponse(['success' => true, 'message' => 'Tindakan berhasil diperbarui']);
}

function deleteProcedure() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    // Soft delete by deactivating
    $pdo->prepare("UPDATE master_procedures SET is_active = 0 WHERE id = :id")->execute(['id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Tindakan berhasil dinonaktifkan']);
}

// =================== DRUGS ===================

function listDrugs() {
    global $pdo;
    $activeOnly = ($_GET['active_only'] ?? '0') === '1';
    $sql = "SELECT * FROM master_drugs";
    if ($activeOnly) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY category ASC, name ASC";
    jsonResponse(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
}

function searchDrugs() {
    global $pdo;
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        jsonResponse(['success' => true, 'data' => []]);
    }
    $like = "%{$q}%";
    $stmt = $pdo->prepare("SELECT * FROM master_drugs WHERE is_active = 1 AND (name LIKE :q OR code LIKE :q2) ORDER BY name ASC LIMIT 15");
    $stmt->execute(['q' => $like, 'q2' => $like]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function createDrug() {
    requirePost();
    global $pdo;
    $input = getJsonInput();

    $stmt = $pdo->prepare("INSERT INTO master_drugs (code, name, category, unit, price, stock, min_stock) 
                           VALUES (:code, :name, :cat, :unit, :price, :stock, :min_stock)");
    $stmt->execute([
        'code'      => $input['code'] ?? '',
        'name'      => $input['name'] ?? '',
        'cat'       => $input['category'] ?? 'Umum',
        'unit'      => $input['unit'] ?? 'Tablet',
        'price'     => (float)($input['price'] ?? 0),
        'stock'     => (int)($input['stock'] ?? 0),
        'min_stock' => (int)($input['min_stock'] ?? 10),
    ]);

    jsonResponse(['success' => true, 'message' => 'Obat berhasil ditambahkan', 'data' => ['id' => $pdo->lastInsertId()]], 201);
}

function updateDrug() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    $stmt = $pdo->prepare("UPDATE master_drugs SET 
        code = :code, name = :name, category = :cat, unit = :unit, 
        price = :price, stock = :stock, min_stock = :min_stock, is_active = :active
        WHERE id = :id");
    $stmt->execute([
        'id'        => $id,
        'code'      => $input['code'] ?? '',
        'name'      => $input['name'] ?? '',
        'cat'       => $input['category'] ?? 'Umum',
        'unit'      => $input['unit'] ?? 'Tablet',
        'price'     => (float)($input['price'] ?? 0),
        'stock'     => (int)($input['stock'] ?? 0),
        'min_stock' => (int)($input['min_stock'] ?? 10),
        'active'    => (int)($input['is_active'] ?? 1),
    ]);

    jsonResponse(['success' => true, 'message' => 'Obat berhasil diperbarui']);
}

function deleteDrug() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    $pdo->prepare("UPDATE master_drugs SET is_active = 0 WHERE id = :id")->execute(['id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Obat berhasil dinonaktifkan']);
}

function lowStockDrugs() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM master_drugs WHERE stock <= min_stock AND is_active = 1 ORDER BY stock ASC");
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}
