<?php
// ──────────────────────────────────────────────────────────────
//  api/check_auth.php — Vérifie si la session est active
//  Répond : JSON { authenticated, user }
// ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';

handlePreflight(['GET']);
startAppSession();

if (!empty($_SESSION['user_id'])) {
    jsonResponse([
        'authenticated' => true,
        'user' => currentUserFromSession(),
    ]);
} else {
    jsonResponse(['authenticated' => false], 401);
}
