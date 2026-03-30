<?php
// api/modules.php — Liste, création, modification et suppression des modules

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST', 'PUT', 'DELETE']);
requireAuthentication();
requireAdminAccess();

function normalizeModule(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => $row['titre'],
        'description' => $row['description'],
        'durationDays' => (int) $row['duree_jours'],
        'durationLabel' => ((int) $row['duree_jours']) . ' jours',
    ];
}

function readModulePayload(): array
{
    $body = readJsonBody();

    $title = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    $durationDays = (int) ($body['durationDays'] ?? 0);

    if ($title === '' || $description === '' || $durationDays <= 0) {
        jsonResponse(['success' => false, 'message' => 'Titre, description et durée sont requis'], 400);
    }

    return [
        'title' => $title,
        'description' => $description,
        'durationDays' => $durationDays,
    ];
}

try {
    $pdo = getDB();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT id, titre, description, duree_jours
               FROM modules
              ORDER BY created_at DESC, id DESC"
        );

        jsonResponse([
            'success' => true,
            'modules' => array_map('normalizeModule', $stmt->fetchAll()),
        ]);
    }

    if ($method === 'POST') {
        $payload = readModulePayload();

        $insert = $pdo->prepare(
            "INSERT INTO modules (titre, description, duree_jours)
             VALUES (:titre, :description, :duree_jours)"
        );
        $insert->execute([
            ':titre' => $payload['title'],
            ':description' => $payload['description'],
            ':duree_jours' => $payload['durationDays'],
        ]);

        $id = (int) $pdo->lastInsertId();
        $select = $pdo->prepare(
            "SELECT id, titre, description, duree_jours
               FROM modules
              WHERE id = :id
              LIMIT 1"
        );
        $select->execute([':id' => $id]);

        jsonResponse([
            'success' => true,
            'message' => 'Module enregistré avec succès',
            'module' => normalizeModule($select->fetch() ?: []),
        ], 201);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Identifiant du module requis'], 400);
    }

    if ($method === 'PUT') {
        $payload = readModulePayload();

        $update = $pdo->prepare(
            "UPDATE modules
                SET titre = :titre,
                    description = :description,
                    duree_jours = :duree_jours
              WHERE id = :id"
        );
        $update->execute([
            ':id' => $id,
            ':titre' => $payload['title'],
            ':description' => $payload['description'],
            ':duree_jours' => $payload['durationDays'],
        ]);

        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare("SELECT id FROM modules WHERE id = :id LIMIT 1");
            $exists->execute([':id' => $id]);
            if (!$exists->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Module introuvable'], 404);
            }
        }

        $select = $pdo->prepare(
            "SELECT id, titre, description, duree_jours
               FROM modules
              WHERE id = :id
              LIMIT 1"
        );
        $select->execute([':id' => $id]);

        jsonResponse([
            'success' => true,
            'message' => 'Module mis à jour avec succès',
            'module' => normalizeModule($select->fetch() ?: []),
        ]);
    }

    if ($method === 'DELETE') {
        $delete = $pdo->prepare("DELETE FROM modules WHERE id = :id");
        $delete->execute([':id' => $id]);

        if ($delete->rowCount() === 0) {
            jsonResponse(['success' => false, 'message' => 'Module introuvable'], 404);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Module supprimé avec succès',
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
