<?php
// api/encadreurs.php — Liste et création des encadreurs

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST']);
requireAuthentication();

function normalizeSupervisor(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['nom'],
        'role' => $row['role'],
        'phone' => $row['telephone'] ?? '',
        'email' => $row['email'],
        'account' => ((int) $row['a_compte'] === 1) ? 'avec' : 'sans',
        'invited' => ((int) $row['invitation_envoyee'] === 1),
    ];
}

try {
    $pdo = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query(
            "SELECT id, nom, role, telephone, email, a_compte, invitation_envoyee
               FROM encadreurs
              ORDER BY created_at DESC, id DESC"
        );

        jsonResponse([
            'success' => true,
            'encadreurs' => array_map('normalizeSupervisor', $stmt->fetchAll()),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    $body = readJsonBody();

    $nom = trim($body['name'] ?? '');
    $role = trim($body['role'] ?? '');
    $telephone = trim($body['phone'] ?? '');
    $email = trim($body['email'] ?? '');
    $account = trim($body['account'] ?? 'sans');
    $invited = !empty($body['invited']) ? 1 : 0;

    if ($nom === '' || $role === '' || $email === '') {
        jsonResponse(['success' => false, 'message' => 'Nom, fonction et email sont requis'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Adresse email invalide'], 400);
    }

    if (!in_array($account, ['avec', 'sans'], true)) {
        jsonResponse(['success' => false, 'message' => 'Type de compte invalide'], 400);
    }

    $insert = $pdo->prepare(
        "INSERT INTO encadreurs (nom, role, telephone, email, a_compte, invitation_envoyee)
         VALUES (:nom, :role, :telephone, :email, :a_compte, :invitation_envoyee)"
    );

    $insert->execute([
        ':nom' => $nom,
        ':role' => $role,
        ':telephone' => ($telephone !== '') ? $telephone : null,
        ':email' => $email,
        ':a_compte' => ($account === 'avec') ? 1 : 0,
        ':invitation_envoyee' => $invited,
    ]);

    $select = $pdo->prepare(
        "SELECT id, nom, role, telephone, email, a_compte, invitation_envoyee
           FROM encadreurs
          WHERE id = :id
          LIMIT 1"
    );
    $select->execute([':id' => (int) $pdo->lastInsertId()]);
    $created = $select->fetch();

    jsonResponse([
        'success' => true,
        'message' => 'Encadreur enregistré avec succès',
        'encadreur' => $created ? normalizeSupervisor($created) : null,
    ], 201);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        jsonResponse(['success' => false, 'message' => 'Cet email existe déjà'], 409);
    }

    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
