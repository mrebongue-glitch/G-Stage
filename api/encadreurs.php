<?php
// api/encadreurs.php — Liste et création des encadreurs

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST']);
requireAuthentication();
requireAdminAccess();

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

function generateTemporaryPassword(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }

    return $password;
}

function generateLoginFromEmail(PDO $pdo, string $email): string
{
    $base = strtolower(trim($email));
    $base = preg_replace('/[^a-z0-9._@-]/', '', $base) ?? '';

    if ($base === '') {
        $base = 'encadreur';
    }

    $base = substr($base, 0, 80);
    $candidate = $base;
    $suffix = 1;

    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM utilisateurs WHERE identifiant = :identifiant'
    );

    while (true) {
        $check->execute([':identifiant' => $candidate]);
        if ((int) $check->fetchColumn() === 0) {
            return $candidate;
        }

        $suffixLabel = (string) $suffix;
        $candidate = substr($base, 0, 80 - strlen($suffixLabel) - 1) . '-' . $suffixLabel;
        $suffix++;
    }
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

    if (($body['action'] ?? '') === 'invite') {
        $encadreurId = (int) ($body['id'] ?? 0);

        if ($encadreurId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Encadreur invalide'], 400);
        }

        $selectEncadreur = $pdo->prepare(
            "SELECT id, nom, role, telephone, email, a_compte, invitation_envoyee
               FROM encadreurs
              WHERE id = :id
              LIMIT 1"
        );
        $selectEncadreur->execute([':id' => $encadreurId]);
        $encadreur = $selectEncadreur->fetch();

        if (!$encadreur) {
            jsonResponse(['success' => false, 'message' => 'Encadreur introuvable'], 404);
        }

        if ((int) $encadreur['a_compte'] === 1) {
            jsonResponse([
                'success' => false,
                'message' => 'Cet encadreur dispose deja d\'un compte utilisateur'
            ], 409);
        }

        $existingUser = $pdo->prepare(
            "SELECT id
               FROM utilisateurs
              WHERE encadreur_id = :encadreur_id
                 OR identifiant = :identifiant
              LIMIT 1"
        );

        $temporaryPassword = generateTemporaryPassword();
        $hashedPassword = password_hash($temporaryPassword, PASSWORD_BCRYPT);
        $login = generateLoginFromEmail($pdo, (string) $encadreur['email']);
        $existingUser->execute([
            ':encadreur_id' => $encadreurId,
            ':identifiant' => $login,
        ]);

        if ($existingUser->fetch()) {
            jsonResponse([
                'success' => false,
                'message' => 'Un compte est deja lie a cet encadreur'
            ], 409);
        }

        $pdo->beginTransaction();

        $createUser = $pdo->prepare(
            "INSERT INTO utilisateurs (nom, identifiant, mot_de_passe, role, encadreur_id, actif)
             VALUES (:nom, :identifiant, :mot_de_passe, 'encadreur', :encadreur_id, 1)"
        );
        $createUser->execute([
            ':nom' => $encadreur['nom'],
            ':identifiant' => $login,
            ':mot_de_passe' => $hashedPassword,
            ':encadreur_id' => $encadreurId,
        ]);

        $updateEncadreur = $pdo->prepare(
            "UPDATE encadreurs
                SET a_compte = 1,
                    invitation_envoyee = 1
              WHERE id = :id"
        );
        $updateEncadreur->execute([':id' => $encadreurId]);

        $pdo->commit();

        $selectEncadreur->execute([':id' => $encadreurId]);
        $updated = $selectEncadreur->fetch();

        jsonResponse([
            'success' => true,
            'message' => 'Compte utilisateur cree et invitation envoyee',
            'encadreur' => $updated ? normalizeSupervisor($updated) : null,
            'credentials' => [
                'identifiant' => $login,
                'mot_de_passe_temporaire' => $temporaryPassword,
            ],
        ]);
    }

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
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ((int) $e->getCode() === 23000) {
        jsonResponse(['success' => false, 'message' => 'Cet email existe déjà'], 409);
    }

    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
