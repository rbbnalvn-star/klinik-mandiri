<?php
/**
 * API: Reports - Laporan & Analitik
 */
require_once __DIR__ . '/../config/db.php';
requireAuth();

$action = $_GET['action'] ?? '';
$pdo = getDB();

switch ($action) {
    case 'revenue':
        revenueReport();
        break;
    case 'visits_summary':
        visitsSummary();
        break;
    case 'top_diagnoses':
        topDiagnoses();
        break;
    case 'top_drugs':
        topDrugs();
        break;
    case 'top_procedures':
        topProcedures();
        break;
    case 'patient_demographics':
        patientDemographics();
        break;
    case 'daily_detail':
        dailyDetail();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function revenueReport() {
    global $pdo;
    $period = $_GET['period'] ?? 'monthly'; // daily, monthly, yearly
    $year = (int)($_GET['year'] ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('m'));

    if ($period === 'daily') {
        $stmt = $pdo->prepare("
            SELECT DATE(v.visit_date) as label, 
                   COUNT(DISTINCT v.id) as visit_count,
                   COALESCE(SUM(i.grand_total), 0) as revenue
            FROM visits v
            LEFT JOIN invoices i ON i.visit_id = v.id AND i.status = 'paid'
            WHERE YEAR(v.visit_date) = :y AND MONTH(v.visit_date) = :m
            GROUP BY DATE(v.visit_date)
            ORDER BY label ASC
        ");
        $stmt->execute(['y' => $year, 'm' => $month]);
    } elseif ($period === 'monthly') {
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(v.visit_date, '%Y-%m') as label,
                   MONTHNAME(v.visit_date) as month_name,
                   COUNT(DISTINCT v.id) as visit_count,
                   COALESCE(SUM(i.grand_total), 0) as revenue
            FROM visits v
            LEFT JOIN invoices i ON i.visit_id = v.id AND i.status = 'paid'
            WHERE YEAR(v.visit_date) = :y
            GROUP BY label, month_name
            ORDER BY label ASC
        ");
        $stmt->execute(['y' => $year]);
    } else {
        $stmt = $pdo->query("
            SELECT YEAR(v.visit_date) as label,
                   COUNT(DISTINCT v.id) as visit_count,
                   COALESCE(SUM(i.grand_total), 0) as revenue
            FROM visits v
            LEFT JOIN invoices i ON i.visit_id = v.id AND i.status = 'paid'
            GROUP BY label
            ORDER BY label ASC
        ");
    }

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function visitsSummary() {
    global $pdo;
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_visits,
            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COUNT(DISTINCT patient_id) as unique_patients
        FROM visits
        WHERE visit_date BETWEEN :from_date AND :to_date
    ");
    $stmt->execute(['from_date' => $from, 'to_date' => $to]);

    jsonResponse(['success' => true, 'data' => $stmt->fetch()]);
}

function topDiagnoses() {
    global $pdo;
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    $limit = min(20, max(5, (int)($_GET['limit'] ?? 10)));

    $stmt = $pdo->prepare("
        SELECT vt.diagnosis, vt.icd_code, COUNT(*) as total
        FROM vitals vt
        JOIN visits v ON v.id = vt.visit_id
        WHERE v.visit_date BETWEEN :from_date AND :to_date
          AND vt.diagnosis IS NOT NULL AND vt.diagnosis != ''
        GROUP BY vt.diagnosis, vt.icd_code
        ORDER BY total DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':from_date', $from);
    $stmt->bindValue(':to_date', $to);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function topDrugs() {
    global $pdo;
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT md.name, md.code, SUM(vd.quantity) as total_qty, SUM(vd.subtotal) as total_revenue
        FROM visit_drugs vd
        JOIN master_drugs md ON md.id = vd.drug_id
        JOIN visits v ON v.id = vd.visit_id
        WHERE v.visit_date BETWEEN :from_date AND :to_date
        GROUP BY md.id, md.name, md.code
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    $stmt->execute(['from_date' => $from, 'to_date' => $to]);

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function topProcedures() {
    global $pdo;
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT mp.name, mp.code, SUM(vp.quantity) as total_qty, SUM(vp.subtotal) as total_revenue
        FROM visit_procedures vp
        JOIN master_procedures mp ON mp.id = vp.procedure_id
        JOIN visits v ON v.id = vp.visit_id
        WHERE v.visit_date BETWEEN :from_date AND :to_date
        GROUP BY mp.id, mp.name, mp.code
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    $stmt->execute(['from_date' => $from, 'to_date' => $to]);

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function patientDemographics() {
    global $pdo;

    // Gender distribution
    $gender = $pdo->query("SELECT gender, COUNT(*) as total FROM patients GROUP BY gender")->fetchAll();

    // Age distribution
    $age = $pdo->query("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 5 THEN 'Balita (0-4)'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 5 AND 11 THEN 'Anak (5-11)'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 12 AND 17 THEN 'Remaja (12-17)'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 18 AND 40 THEN 'Dewasa (18-40)'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 41 AND 60 THEN 'Paruh Baya (41-60)'
                ELSE 'Lansia (60+)'
            END as age_group,
            COUNT(*) as total
        FROM patients
        WHERE birth_date IS NOT NULL
        GROUP BY age_group
        ORDER BY MIN(TIMESTAMPDIFF(YEAR, birth_date, CURDATE()))
    ")->fetchAll();

    jsonResponse(['success' => true, 'data' => ['gender' => $gender, 'age' => $age]]);
}

function dailyDetail() {
    global $pdo;
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT v.visit_date, v.queue_number, p.no_rm, p.name as patient_name,
               vt.diagnosis, i.invoice_number, i.grand_total, i.status as payment_status,
               i.payment_method
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        LEFT JOIN vitals vt ON vt.visit_id = v.id
        LEFT JOIN invoices i ON i.visit_id = v.id
        WHERE v.visit_date BETWEEN :from_date AND :to_date
          AND v.status != 'cancelled'
        ORDER BY v.visit_date DESC, v.queue_number ASC
    ");
    $stmt->execute(['from_date' => $from, 'to_date' => $to]);

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}
