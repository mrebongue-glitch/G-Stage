<?php
// api/encadreurs.php — Liste, création, modification et suppression des encadreurs

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST', 'PUT', 'DELETE']);
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

function findUserBySupervisorId(PDO $pdo, int $encadreurId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, identifiant
           FROM utilisateurs
          WHERE encadreur_id = :encadreur_id
          LIMIT 1"
    );
    $stmt->execute([':encadreur_id' => $encadreurId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function createUserForSupervisor(PDO $pdo, array $encadreur): array
{
    $temporaryPassword = generateTemporaryPassword();
    $login = generateLoginFromEmail($pdo, (string) $encadreur['email']);
    $hashedPassword = password_hash($temporaryPassword, PASSWORD_BCRYPT);

    $createUser = $pdo->prepare(
        "INSERT INTO utilisateurs (nom, identifiant, mot_de_passe, role, encadreur_id, actif)
         VALUES (:nom, :identifiant, :mot_de_passe, 'encadreur', :encadreur_id, 1)"
    );
    $createUser->execute([
        ':nom' => $encadreur['nom'],
        ':identifiant' => $login,
        ':mot_de_passe' => $hashedPassword,
        ':encadreur_id' => (int) $encadreur['id'],
    ]);

    return [
        'identifiant' => $login,
        'mot_de_passe_temporaire' => $temporaryPassword,
    ];
}

function resetSupervisorUserPassword(PDO $pdo, int $userId): array
{
    $temporaryPassword = generateTemporaryPassword();
    $hashedPassword = password_hash($temporaryPassword, PASSWORD_BCRYPT);

    $update = $pdo->prepare(
        "UPDATE utilisateurs
            SET mot_de_passe = :mot_de_passe,
                actif = 1
          WHERE id = :id"
    );
    $update->execute([
        ':id' => $userId,
        ':mot_de_passe' => $hashedPassword,
    ]);

    $select = $pdo->prepare(
        "SELECT identifiant
           FROM utilisateurs
          WHERE id = :id
          LIMIT 1"
    );
    $select->execute([':id' => $userId]);

    return [
        'identifiant' => (string) ($select->fetchColumn() ?? ''),
        'mot_de_passe_temporaire' => $temporaryPassword,
    ];
}

function deleteSupervisorUser(PDO $pdo, int $encadreurId): void
{
    $delete = $pdo->prepare(
        "DELETE FROM utilisateurs
          WHERE encadreur_id = :encadreur_id"
    );
    $delete->execute([':encadreur_id' => $encadreurId]);
}

function readSupervisorPayload(): array
{
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

    return [
        'name' => $nom,
        'role' => $role,
        'phone' => ($telephone !== '') ? $telephone : null,
        'email' => $email,
        'account' => $account,
        'invited' => $invited,
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

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST') {
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

            $pdo->beginTransaction();
            $user = findUserBySupervisorId($pdo, $encadreurId);
            $credentials = $user
                ? resetSupervisorUserPassword($pdo, (int) $user['id'])
                : createUserForSupervisor($pdo, $encadreur);

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
                'message' => 'Compte utilisateur pret et invitation envoyee',
                'encadreur' => $updated ? normalizeSupervisor($updated) : null,
                'credentials' => $credentials,
            ]);
        }

        $payload = readSupervisorPayload();
        $effectiveInvited = ($payload['account'] === 'avec') ? 1 : 0;

        $pdo->beginTransaction();

        $insert = $pdo->prepare(
            "INSERT INTO encadreurs (nom, role, telephone, email, a_compte, invitation_envoyee)
             VALUES (:nom, :role, :telephone, :email, :a_compte, :invitation_envoyee)"
        );

        $insert->execute([
            ':nom' => $payload['name'],
            ':role' => $payload['role'],
            ':telephone' => $payload['phone'],
            ':email' => $payload['email'],
            ':a_compte' => ($payload['account'] === 'avec') ? 1 : 0,
            ':invitation_envoyee' => $effectiveInvited,
        ]);

        $createdId = (int) $pdo->lastInsertId();
        $credentials = null;

        if ($payload['account'] === 'avec') {
            $credentials = createUserForSupervisor($pdo, [
                'id' => $createdId,
                'nom' => $payload['name'],
                'email' => $payload['email'],
            ]);
        }

        $select = $pdo->prepare(
            "SELECT id, nom, role, telephone, email, a_compte, invitation_envoyee
               FROM encadreurs
              WHERE id = :id
              LIMIT 1"
        );
        $select->execute([':id' => $createdId]);
        $created = $select->fetch();

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Encadreur enregistré avec succès',
            'encadreur' => $created ? normalizeSupervisor($created) : null,
            'credentials' => $credentials,
        ], 201);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Identifiant de l\'encadreur requis'], 400);
    }

    if ($method === 'PUT') {
        $existing = $pdo->prepare(
            "SELECT id, nom, email, invitation_envoyee
               FROM encadreurs
              WHERE id = :id
              LIMIT 1"
        );
        $existing->execute([':id' => $id]);
        $row = $existing->fetch();

        if (!$row) {
            jsonResponse(['success' => false, 'message' => 'Encadreur introuvable'], 404);
        }

        $payload = readSupervisorPayload();
        $hadUser = findUserBySupervisorId($pdo, $id);
        $credentials = null;

        $pdo->beginTransaction();

        $update = $pdo->prepare(
            "UPDATE encadreurs
                SET nom = :nom,
                    role = :role,
                    telephone = :telephone,
                    email = :email,
                    a_compte = :a_compte,
                    invitation_envoyee = :invitation_envoyee
              WHERE id = :id"
        );
        $nextInvited = ($payload['account'] === 'avec')
            ? (($payload['invited'] || $hadUser) ? 1 : 0)
            : 0;
        $update->execute([
            ':id' => $id,
            ':nom' => $payload['name'],
            ':role' => $payload['role'],
            ':telephone' => $payload['phone'],
            ':email' => $payload['email'],
            ':a_compte' => ($payload['account'] === 'avec') ? 1 : 0,
            ':invitation_envoyee' => $nextInvited,
        ]);

        if ($payload['account'] === 'avec' && !$hadUser) {
            $credentials = createUserForSupervisor($pdo, [
                'id' => $id,
                'nom' => $payload['name'],
                'email' => $payload['email'],
            ]);

            $markInvited = $pdo->prepare(
                "UPDATE encadreurs
                    SET invitation_envoyee = 1
                  WHERE id = :id"
            );
            $markInvited->execute([':id' => $id]);
        }

        if ($payload['account'] === 'sans' && $hadUser) {
            deleteSupervisorUser($pdo, $id);
        }

        if ($payload['account'] === 'avec' && $hadUser) {
            $syncUser = $pdo->prepare(
                "UPDATE utilisateurs
                    SET nom = :nom,
                        actif = 1
                  WHERE encadreur_id = :encadreur_id"
            );
            $syncUser->execute([
                ':nom' => $payload['name'],
                ':encadreur_id' => $id,
            ]);
        }

        $select = $pdo->prepare(
            "SELECT id, nom, role, telephone, email, a_compte, invitation_envoyee
               FROM encadreurs
              WHERE id = :id
              LIMIT 1"
        );
        $select->execute([':id' => $id]);
        $updated = $select->fetch();

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Encadreur mis à jour avec succès',
            'encadreur' => $updated ? normalizeSupervisor($updated) : null,
            'credentials' => $credentials,
        ]);
    }

    if ($method === 'DELETE') {
        $pdo->beginTransaction();
        deleteSupervisorUser($pdo, $id);

        $delete = $pdo->prepare("DELETE FROM encadreurs WHERE id = :id");
        $delete->execute([':id' => $id]);

        if ($delete->rowCount() === 0) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'Encadreur introuvable'], 404);
        }

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Encadreur supprimé avec succès',
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
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
