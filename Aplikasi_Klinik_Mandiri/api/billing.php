<?php
/**
 * API: Billing - Tindakan, Obat, Invoice, Pembayaran
 */
require_once __DIR__ . '/../config/db.php';
requireAuth();

$action = $_GET['action'] ?? '';
$pdo = getDB();

switch ($action) {
    case 'add_procedure':
        addProcedure();
        break;
    case 'remove_procedure':
        removeProcedure();
        break;
    case 'add_drug':
        addDrug();
        break;
    case 'remove_drug':
        removeDrug();
        break;
    case 'get_bill':
        getBill();
        break;
    case 'checkout':
        checkout();
        break;
    case 'get_invoice':
        getInvoice();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function addProcedure() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $visitId = (int)($input['visit_id'] ?? 0);
    $procedureId = (int)($input['procedure_id'] ?? 0);
    $qty = max(1, (int)($input['quantity'] ?? 1));

    // Get procedure price
    $proc = $pdo->prepare("SELECT * FROM master_procedures WHERE id = :id AND is_active = 1");
    $proc->execute(['id' => $procedureId]);
    $procedure = $proc->fetch();

    if (!$procedure) {
        jsonResponse(['success' => false, 'message' => 'Tindakan tidak ditemukan'], 404);
    }

    $price = $procedure['price'];
    $subtotal = $price * $qty;

    $stmt = $pdo->prepare("INSERT INTO visit_procedures (visit_id, procedure_id, quantity, price, subtotal)
                           VALUES (:vid, :pid, :qty, :price, :subtotal)");
    $stmt->execute([
        'vid'      => $visitId,
        'pid'      => $procedureId,
        'qty'      => $qty,
        'price'    => $price,
        'subtotal' => $subtotal,
    ]);

    // Update visit status
    $pdo->prepare("UPDATE visits SET status = 'action' WHERE id = :vid AND status IN ('waiting','examination')")
        ->execute(['vid' => $visitId]);

    jsonResponse(['success' => true, 'message' => 'Tindakan berhasil ditambahkan', 'data' => ['id' => $pdo->lastInsertId(), 'subtotal' => $subtotal]]);
}

function removeProcedure() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    $pdo->prepare("DELETE FROM visit_procedures WHERE id = :id")->execute(['id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Tindakan berhasil dihapus']);
}

function addDrug() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $visitId = (int)($input['visit_id'] ?? 0);
    $drugId = (int)($input['drug_id'] ?? 0);
    $qty = max(1, (int)($input['quantity'] ?? 1));
    $dosage = trim($input['dosage_instructions'] ?? '');

    // Get drug & check stock
    $drug = $pdo->prepare("SELECT * FROM master_drugs WHERE id = :id AND is_active = 1");
    $drug->execute(['id' => $drugId]);
    $drugData = $drug->fetch();

    if (!$drugData) {
        jsonResponse(['success' => false, 'message' => 'Obat tidak ditemukan'], 404);
    }

    if ($drugData['stock'] < $qty) {
        jsonResponse(['success' => false, 'message' => "Stok tidak cukup. Tersedia: {$drugData['stock']} {$drugData['unit']}"], 400);
    }

    $price = $drugData['price'];
    $subtotal = $price * $qty;

    // Reduce stock
    $pdo->prepare("UPDATE master_drugs SET stock = stock - :qty WHERE id = :id")
        ->execute(['qty' => $qty, 'id' => $drugId]);

    $stmt = $pdo->prepare("INSERT INTO visit_drugs (visit_id, drug_id, quantity, price, subtotal, dosage_instructions)
                           VALUES (:vid, :did, :qty, :price, :subtotal, :dosage)");
    $stmt->execute([
        'vid'      => $visitId,
        'did'      => $drugId,
        'qty'      => $qty,
        'price'    => $price,
        'subtotal' => $subtotal,
        'dosage'   => $dosage,
    ]);

    // Update visit status
    $pdo->prepare("UPDATE visits SET status = 'pharmacy' WHERE id = :vid AND status IN ('waiting','examination','action')")
        ->execute(['vid' => $visitId]);

    jsonResponse(['success' => true, 'message' => 'Obat berhasil ditambahkan', 'data' => ['id' => $pdo->lastInsertId(), 'subtotal' => $subtotal]]);
}

function removeDrug() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);

    // Restore stock
    $existing = $pdo->prepare("SELECT drug_id, quantity FROM visit_drugs WHERE id = :id");
    $existing->execute(['id' => $id]);
    $row = $existing->fetch();

    if ($row) {
        $pdo->prepare("UPDATE master_drugs SET stock = stock + :qty WHERE id = :did")
            ->execute(['qty' => $row['quantity'], 'did' => $row['drug_id']]);
        $pdo->prepare("DELETE FROM visit_drugs WHERE id = :id")->execute(['id' => $id]);
    }

    jsonResponse(['success' => true, 'message' => 'Obat berhasil dihapus, stok dikembalikan']);
}

function getBill() {
    global $pdo;
    $visitId = (int)($_GET['visit_id'] ?? 0);

    $procStmt = $pdo->prepare("SELECT vp.*, mp.name as procedure_name, mp.code 
                               FROM visit_procedures vp 
                               JOIN master_procedures mp ON mp.id = vp.procedure_id 
                               WHERE vp.visit_id = :vid");
    $procStmt->execute(['vid' => $visitId]);
    $procedures = $procStmt->fetchAll();

    $drugStmt = $pdo->prepare("SELECT vd.*, md.name as drug_name, md.code, md.unit 
                               FROM visit_drugs vd 
                               JOIN master_drugs md ON md.id = vd.drug_id 
                               WHERE vd.visit_id = :vid");
    $drugStmt->execute(['vid' => $visitId]);
    $drugs = $drugStmt->fetchAll();

    $totalProcedures = array_sum(array_column($procedures, 'subtotal'));
    $totalDrugs = array_sum(array_column($drugs, 'subtotal'));

    jsonResponse([
        'success' => true,
        'data' => [
            'procedures'       => $procedures,
            'drugs'            => $drugs,
            'total_procedures' => $totalProcedures,
            'total_drugs'      => $totalDrugs,
            'grand_total'      => $totalProcedures + $totalDrugs,
        ]
    ]);
}

function checkout() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $visitId = (int)($input['visit_id'] ?? 0);
    $discount = max(0, (float)($input['discount'] ?? 0));
    $paymentAmount = (float)($input['payment_amount'] ?? 0);
    $paymentMethod = $input['payment_method'] ?? 'cash';

    // Calculate totals
    $totalProc = (float)$pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM visit_procedures WHERE visit_id = :vid")
        ->execute(['vid' => $visitId]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;

    $procStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM visit_procedures WHERE visit_id = :vid");
    $procStmt->execute(['vid' => $visitId]);
    $totalProc = (float)$procStmt->fetchColumn();

    $drugStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM visit_drugs WHERE visit_id = :vid");
    $drugStmt->execute(['vid' => $visitId]);
    $totalDrug = (float)$drugStmt->fetchColumn();

    $grandTotal = $totalProc + $totalDrug - $discount;
    $changeAmount = $paymentAmount - $grandTotal;

    if ($paymentAmount < $grandTotal) {
        jsonResponse(['success' => false, 'message' => 'Jumlah pembayaran kurang dari total tagihan'], 400);
    }

    $invoiceNumber = generateInvoiceNumber($pdo);

    // Check if invoice already exists
    $existingInv = $pdo->prepare("SELECT id FROM invoices WHERE visit_id = :vid");
    $existingInv->execute(['vid' => $visitId]);

    if ($existingInv->fetch()) {
        $stmt = $pdo->prepare("UPDATE invoices SET 
            total_procedures = :tp, total_drugs = :td, discount = :disc,
            grand_total = :gt, payment_amount = :pa, change_amount = :ca,
            payment_method = :pm, status = 'paid', paid_at = NOW()
            WHERE visit_id = :vid");
        $stmt->execute([
            'tp'   => $totalProc,
            'td'   => $totalDrug,
            'disc' => $discount,
            'gt'   => $grandTotal,
            'pa'   => $paymentAmount,
            'ca'   => $changeAmount,
            'pm'   => $paymentMethod,
            'vid'  => $visitId,
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO invoices 
            (visit_id, invoice_number, total_procedures, total_drugs, discount, grand_total, payment_amount, change_amount, payment_method, status, paid_at)
            VALUES (:vid, :inv, :tp, :td, :disc, :gt, :pa, :ca, :pm, 'paid', NOW())");
        $stmt->execute([
            'vid'  => $visitId,
            'inv'  => $invoiceNumber,
            'tp'   => $totalProc,
            'td'   => $totalDrug,
            'disc' => $discount,
            'gt'   => $grandTotal,
            'pa'   => $paymentAmount,
            'ca'   => $changeAmount,
            'pm'   => $paymentMethod,
        ]);
    }

    // Update visit status to done
    $pdo->prepare("UPDATE visits SET status = 'done' WHERE id = :vid")->execute(['vid' => $visitId]);

    jsonResponse([
        'success' => true,
        'message' => 'Pembayaran berhasil',
        'data'    => [
            'invoice_number' => $invoiceNumber,
            'grand_total'    => $grandTotal,
            'payment_amount' => $paymentAmount,
            'change_amount'  => $changeAmount,
        ]
    ]);
}

function getInvoice() {
    global $pdo;
    $visitId = (int)($_GET['visit_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT i.*, v.visit_date, v.queue_number,
               p.no_rm, p.name as patient_name, p.gender, p.birth_date, p.phone, p.address
        FROM invoices i
        JOIN visits v ON v.id = i.visit_id
        JOIN patients p ON p.id = v.patient_id
        WHERE i.visit_id = :vid
    ");
    $stmt->execute(['vid' => $visitId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        jsonResponse(['success' => false, 'message' => 'Invoice tidak ditemukan'], 404);
    }

    // Get items
    $procStmt = $pdo->prepare("SELECT vp.*, mp.name as procedure_name, mp.code FROM visit_procedures vp JOIN master_procedures mp ON mp.id = vp.procedure_id WHERE vp.visit_id = :vid");
    $procStmt->execute(['vid' => $visitId]);
    $invoice['procedures'] = $procStmt->fetchAll();

    $drugStmt = $pdo->prepare("SELECT vd.*, md.name as drug_name, md.code, md.unit FROM visit_drugs vd JOIN master_drugs md ON md.id = vd.drug_id WHERE vd.visit_id = :vid");
    $drugStmt->execute(['vid' => $visitId]);
    $invoice['drugs'] = $drugStmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $invoice]);
}
