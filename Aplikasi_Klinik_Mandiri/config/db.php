<?php
/**
 * Database Configuration - Klinik Praktek Mandiri
 * Koneksi PDO ke MySQL (XAMPP default)
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'klinik_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

/**
 * Helper: JSON Response
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Helper: Require POST method
 */
function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
}

/**
 * Helper: Get JSON body from POST
 */
function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return $data ?: [];
}

/**
 * Helper: Require active session
 */
function requireAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

/**
 * Helper: Generate Nomor Rekam Medis (RM-YYYYMM-XXXX)
 */
function generateNoRM($pdo) {
    $prefix = 'RM-' . date('Ym') . '-';
    $stmt = $pdo->query("SELECT no_rm FROM patients WHERE no_rm LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = (int) substr($last, -4) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

/**
 * Helper: Generate Invoice Number (INV-YYYYMMDD-XXXX)
 */
function generateInvoiceNumber($pdo) {
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $pdo->query("SELECT invoice_number FROM invoices WHERE invoice_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = (int) substr($last, -4) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}
