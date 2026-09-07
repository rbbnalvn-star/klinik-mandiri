<?php
/**
 * API: Authentication (Login / Logout / Check Session)
 */
require_once __DIR__ . '/../config/db.php';

session_start();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkSession();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleLogin() {
    requirePost();
    $input = getJsonInput();
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Username dan password harus diisi'], 400);
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Username atau password salah'], 401);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];

    jsonResponse([
        'success' => true,
        'message' => 'Login berhasil',
        'user' => [
            'id'       => $user['id'],
            'username' => $user['username'],
            'name'     => $user['name'],
            'role'     => $user['role'],
        ]
    ]);
}

function handleLogout() {
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logout berhasil']);
}

function checkSession() {
    if (isset($_SESSION['user_id'])) {
        jsonResponse([
            'success'       => true,
            'authenticated' => true,
            'user' => [
                'id'       => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'name'     => $_SESSION['name'],
                'role'     => $_SESSION['role'],
            ]
        ]);
    } else {
        jsonResponse(['success' => true, 'authenticated' => false]);
    }
}
