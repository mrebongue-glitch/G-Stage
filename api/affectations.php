<?php
// api/affectations.php — Liste et gestion des affectations

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST', 'PUT', 'DELETE']);
requireAuthentication();
requireAdminAccess();

function normalizeAssignment(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'traineeId' => (int) $row['stagiaire_id'],
        'trainee' => $row['stagiaire_nom'],
        'moduleId' => (int) $row['module_id'],
        'module' => $row['module_titre'],
        'supervisorId' => (int) $row['encadreur_id'],
        'supervisor' => $row['encadreur_nom'],
        'date' => $row['date_affectation'],
        'status' => $row['statut'],
    ];
}

function fetchAssignmentOptions(PDO $pdo): array
{
    $trainees = $pdo->query(
        "SELECT id, nom
           FROM stagiaires
          WHERE statut = 'actif'
          ORDER BY nom ASC"
    )->fetchAll();

    $modules = $pdo->query(
        "SELECT id, titre
           FROM modules
          ORDER BY titre ASC"
    )->fetchAll();

    $supervisors = $pdo->query(
        "SELECT id, nom
           FROM encadreurs
          ORDER BY nom ASC"
    )->fetchAll();

    return [
        'trainees' => $trainees,
        'modules' => $modules,
        'supervisors' => $supervisors,
    ];
}

function readAssignmentPayload(): array
{
    $body = readJsonBody();

    $payload = [
        'traineeId' => (int) ($body['traineeId'] ?? 0),
        'moduleId' => (int) ($body['moduleId'] ?? 0),
        'supervisorId' => (int) ($body['supervisorId'] ?? 0),
        'date' => trim($body['date'] ?? ''),
        'status' => trim($body['status'] ?? 'en-cours'),
    ];

    if (
        $payload['traineeId'] <= 0 ||
        $payload['moduleId'] <= 0 ||
        $payload['supervisorId'] <= 0 ||
        $payload['date'] === '' ||
        !in_array($payload['status'], ['en-cours', 'terminee'], true)
    ) {
        jsonResponse(['success' => false, 'message' => 'Tous les champs de l’affectation sont requis'], 400);
    }

    return $payload;
}

function fetchAssignmentById(PDO $pdo, int $id): ?array
{
    $select = $pdo->prepare(
        "SELECT a.id, a.stagiaire_id, a.module_id, a.encadreur_id, a.date_affectation, a.statut,
                s.nom AS stagiaire_nom,
                m.titre AS module_titre,
                e.nom AS encadreur_nom
           FROM affectations a
           INNER JOIN stagiaires s ON s.id = a.stagiaire_id
           INNER JOIN modules m ON m.id = a.module_id
           INNER JOIN encadreurs e ON e.id = a.encadreur_id
          WHERE a.id = :id
          LIMIT 1"
    );
    $select->execute([':id' => $id]);

    $row = $select->fetch();

    return $row ?: null;
}

try {
    $pdo = getDB();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT a.id, a.stagiaire_id, a.module_id, a.encadreur_id, a.date_affectation, a.statut,
                    s.nom AS stagiaire_nom,
                    m.titre AS module_titre,
                    e.nom AS encadreur_nom
               FROM affectations a
               INNER JOIN stagiaires s ON s.id = a.stagiaire_id
               INNER JOIN modules m ON m.id = a.module_id
               INNER JOIN encadreurs e ON e.id = a.encadreur_id
              ORDER BY a.date_affectation DESC, a.id DESC"
        );

        jsonResponse([
            'success' => true,
            'assignments' => array_map('normalizeAssignment', $stmt->fetchAll()),
            'options' => fetchAssignmentOptions($pdo),
        ]);
    }

    if ($method === 'POST') {
        $payload = readAssignmentPayload();

        $insert = $pdo->prepare(
            "INSERT INTO affectations (stagiaire_id, module_id, encadreur_id, date_affectation, statut)
             VALUES (:stagiaire_id, :module_id, :encadreur_id, :date_affectation, :statut)"
        );
        $insert->execute([
            ':stagiaire_id' => $payload['traineeId'],
            ':module_id' => $payload['moduleId'],
            ':encadreur_id' => $payload['supervisorId'],
            ':date_affectation' => $payload['date'],
            ':statut' => $payload['status'],
        ]);

        $id = (int) $pdo->lastInsertId();
        $created = fetchAssignmentById($pdo, $id);

        jsonResponse([
            'success' => true,
            'assignment' => $created ? normalizeAssignment($created) : null,
        ], 201);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Identifiant de l’affectation requis'], 400);
    }

    if ($method === 'PUT') {
        $body = readJsonBody();
        $hasFullPayload = isset(
            $body['traineeId'],
            $body['moduleId'],
            $body['supervisorId'],
            $body['date']
        );

        if ($hasFullPayload) {
            $payload = readAssignmentPayload();

            $update = $pdo->prepare(
                "UPDATE affectations
                    SET stagiaire_id = :stagiaire_id,
                        module_id = :module_id,
                        encadreur_id = :encadreur_id,
                        date_affectation = :date_affectation,
                        statut = :statut
                  WHERE id = :id"
            );
            $update->execute([
                ':id' => $id,
                ':stagiaire_id' => $payload['traineeId'],
                ':module_id' => $payload['moduleId'],
                ':encadreur_id' => $payload['supervisorId'],
                ':date_affectation' => $payload['date'],
                ':statut' => $payload['status'],
            ]);
        } else {
            $status = trim($body['status'] ?? '');

            if (!in_array($status, ['en-cours', 'terminee'], true)) {
                jsonResponse(['success' => false, 'message' => 'Statut invalide'], 400);
            }

            $update = $pdo->prepare(
                "UPDATE affectations
                    SET statut = :statut
                  WHERE id = :id"
            );
            $update->execute([
                ':id' => $id,
                ':statut' => $status,
            ]);
        }

        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare("SELECT id FROM affectations WHERE id = :id LIMIT 1");
            $exists->execute([':id' => $id]);
            if (!$exists->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Affectation introuvable'], 404);
            }
        }

        $updated = fetchAssignmentById($pdo, $id);

        jsonResponse([
            'success' => true,
            'message' => 'Affectation mise à jour',
            'assignment' => $updated ? normalizeAssignment($updated) : null,
        ]);
    }

    if ($method === 'DELETE') {
        $delete = $pdo->prepare("DELETE FROM affectations WHERE id = :id");
        $delete->execute([':id' => $id]);

        if ($delete->rowCount() === 0) {
            jsonResponse(['success' => false, 'message' => 'Affectation introuvable'], 404);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Affectation supprimée',
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
