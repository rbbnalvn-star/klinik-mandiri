<?php
/**
 * API: Clinical - Vital Signs & Diagnosa
 */
require_once __DIR__ . '/../config/db.php';
requireAuth();

$action = $_GET['action'] ?? '';
$pdo = getDB();

switch ($action) {
    case 'save_vitals':
        saveVitals();
        break;
    case 'get_vitals':
        getVitals();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function saveVitals() {
    requirePost();
    global $pdo;
    $input = getJsonInput();
    $visitId = (int)($input['visit_id'] ?? 0);

    if ($visitId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Visit ID tidak valid'], 400);
    }

    // Upsert vitals
    $exists = $pdo->prepare("SELECT id FROM vitals WHERE visit_id = :vid");
    $exists->execute(['vid' => $visitId]);

    if ($exists->fetch()) {
        $stmt = $pdo->prepare("UPDATE vitals SET 
            blood_pressure = :bp, weight = :w, height = :h, temperature = :t,
            pulse = :p, respiratory_rate = :rr, complaint = :c, physical_exam = :pe,
            diagnosis = :d, icd_code = :icd, notes = :n
            WHERE visit_id = :vid");
    } else {
        $stmt = $pdo->prepare("INSERT INTO vitals 
            (visit_id, blood_pressure, weight, height, temperature, pulse, respiratory_rate, complaint, physical_exam, diagnosis, icd_code, notes)
            VALUES (:vid, :bp, :w, :h, :t, :p, :rr, :c, :pe, :d, :icd, :n)");
    }

    $stmt->execute([
        'vid' => $visitId,
        'bp'  => $input['blood_pressure'] ?? null,
        'w'   => $input['weight'] ?? null,
        'h'   => $input['height'] ?? null,
        't'   => $input['temperature'] ?? null,
        'p'   => $input['pulse'] ?? null,
        'rr'  => $input['respiratory_rate'] ?? null,
        'c'   => $input['complaint'] ?? null,
        'pe'  => $input['physical_exam'] ?? null,
        'd'   => $input['diagnosis'] ?? null,
        'icd' => $input['icd_code'] ?? null,
        'n'   => $input['notes'] ?? null,
    ]);

    // Update visit status to examination
    $pdo->prepare("UPDATE visits SET status = 'examination' WHERE id = :vid AND status = 'waiting'")
        ->execute(['vid' => $visitId]);

    jsonResponse(['success' => true, 'message' => 'Data pemeriksaan berhasil disimpan']);
}

function getVitals() {
    global $pdo;
    $visitId = (int)($_GET['visit_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM vitals WHERE visit_id = :vid");
    $stmt->execute(['vid' => $visitId]);
    $vitals = $stmt->fetch();

    jsonResponse(['success' => true, 'data' => $vitals ?: null]);
}
