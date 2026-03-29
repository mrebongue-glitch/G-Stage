<?php
// ──────────────────────────────────────────────────────────────
//  api/logout.php — Déconnexion
// ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';

handlePreflight(['POST', 'GET']);
startAppSession();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

jsonResponse(['success' => true]);
