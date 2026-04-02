<?php
// ──────────────────────────────────────────────────────────────
//  api/auth.php — Authentification (POST)
//  Attend : { "identifiant": "...", "mot_de_passe": "..." }
//  Répond  : JSON { success, user: { id, nom, identifiant, role } }
// ──────────────────────────────────────────────────────────────

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['POST']);
ensureMethod('POST');


$body = readJsonBody();

$identifiant  = trim($body['identifiant']  ?? '');
$mot_de_passe = trim($body['mot_de_passe'] ?? '');

if ($identifiant === '' || $mot_de_passe === '') {
    jsonResponse(['success' => false, 'message' => 'Identifiant et mot de passe requis'], 400);
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, nom, identifiant, mot_de_passe, role
           FROM utilisateurs
          WHERE identifiant = :id AND actif = 1
          LIMIT 1'
    );
    $stmt->execute([':id' => $identifiant]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($mot_de_passe, $user['mot_de_passe'])) {
        // Délai intentionnel pour ralentir les attaques brute-force
        sleep(1);
        jsonResponse(['success' => false, 'message' => 'Identifiant ou mot de passe incorrect'], 401);
    }

    // Démarrer la session
    startAppSession();
    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['nom']      = $user['nom'];
    $_SESSION['login']    = $user['identifiant'];
    $_SESSION['role']     = $user['role'];

    jsonResponse([
        'success' => true,
        'user'    => [
            'id'          => $user['id'],
            'nom'         => $user['nom'],
            'identifiant' => $user['identifiant'],
            'role'        => $user['role'],
        ]
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
