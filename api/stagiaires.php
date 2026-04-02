<?php
// api/stagiaires.php — Liste, création, modification et suppression des stagiaires

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST', 'PUT', 'DELETE']);
requireAuthentication();

function normalizeRow(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['nom'],
        'email' => $row['email'],
        'school' => $row['etablissement'],
        'field' => $row['filiere'],
        'level' => $row['niveau'],
        'startDate' => $row['date_debut'],
        'endDate' => $row['date_fin'],
        'status' => $row['statut'],
    ];
}

function readTraineePayload(): array
{
    $body = readJsonBody();

    $nom = trim($body['name'] ?? '');
    $email = trim($body['email'] ?? '');
    $etablissement = trim($body['school'] ?? '');
    $filiere = trim($body['field'] ?? '');
    $niveau = trim($body['level'] ?? '');
    $dateDebut = trim($body['startDate'] ?? '');
    $dateFin = trim($body['endDate'] ?? '');
    $statut = trim($body['status'] ?? 'actif');

    if (
        $nom === '' ||
        $email === '' ||
        $etablissement === '' ||
        $filiere === '' ||
        $niveau === '' ||
        $dateDebut === '' ||
        $dateFin === ''
    ) {
        jsonResponse(['success' => false, 'message' => 'Tous les champs sont requis'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Adresse email invalide'], 400);
    }

    $allowedStatus = ['actif', 'termine', 'abandonne'];
    if (!in_array($statut, $allowedStatus, true)) {
        jsonResponse(['success' => false, 'message' => 'Statut invalide'], 400);
    }

    if ($dateFin < $dateDebut) {
        jsonResponse(['success' => false, 'message' => 'La date de fin doit être après la date de début'], 400);
    }

    return [
        'name' => $nom,
        'email' => $email,
        'school' => $etablissement,
        'field' => $filiere,
        'level' => $niveau,
        'startDate' => $dateDebut,
        'endDate' => $dateFin,
        'status' => $statut,
    ];
}

try {
    $pdo = getDB();
    $user = currentUserFromSession();
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $supervisorId = $isAdmin ? null : getCurrentSupervisorId($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($isAdmin) {
            $stmt = $pdo->query(
                "SELECT id, nom, email, etablissement, filiere, niveau, date_debut, date_fin, statut
                   FROM stagiaires
                  ORDER BY created_at DESC, id DESC"
            );
        } else {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT s.id, s.nom, s.email, s.etablissement, s.filiere, s.niveau, s.date_debut, s.date_fin, s.statut, s.created_at
                   FROM stagiaires s
                   INNER JOIN affectations a ON a.stagiaire_id = s.id
                  WHERE a.encadreur_id = :encadreur_id
                  ORDER BY s.created_at DESC, s.id DESC"
            );
            $stmt->execute([':encadreur_id' => $supervisorId]);
        }

        jsonResponse([
            'success' => true,
            'stagiaires' => array_map('normalizeRow', $stmt->fetchAll()),
        ]);
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method !== 'GET' && !$isAdmin) {
        jsonResponse([
            'success' => false,
            'message' => 'Désolé, cette action nécessite des privilèges d’Administrateur. Je ne peux pas modifier les accès système.'
        ], 403);
    }

    if ($method === 'POST') {
        $payload = readTraineePayload();

        $insert = $pdo->prepare(
            "INSERT INTO stagiaires (nom, email, etablissement, filiere, niveau, date_debut, date_fin, statut)
             VALUES (:nom, :email, :etablissement, :filiere, :niveau, :date_debut, :date_fin, :statut)"
        );

        $insert->execute([
            ':nom' => $payload['name'],
            ':email' => $payload['email'],
            ':etablissement' => $payload['school'],
            ':filiere' => $payload['field'],
            ':niveau' => $payload['level'],
            ':date_debut' => $payload['startDate'],
            ':date_fin' => $payload['endDate'],
            ':statut' => $payload['status'],
        ]);

        $select = $pdo->prepare(
            "SELECT id, nom, email, etablissement, filiere, niveau, date_debut, date_fin, statut
               FROM stagiaires
              WHERE id = :id
              LIMIT 1"
        );
        $select->execute([':id' => (int) $pdo->lastInsertId()]);
        $created = $select->fetch();

        jsonResponse([
            'success' => true,
            'message' => 'Stagiaire enregistré avec succès',
            'stagiaire' => $created ? normalizeRow($created) : null,
        ], 201);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Identifiant du stagiaire requis'], 400);
    }

    if ($method === 'PUT') {
        $payload = readTraineePayload();

        $update = $pdo->prepare(
            "UPDATE stagiaires
                SET nom = :nom,
                    email = :email,
                    etablissement = :etablissement,
                    filiere = :filiere,
                    niveau = :niveau,
                    date_debut = :date_debut,
                    date_fin = :date_fin,
                    statut = :statut
              WHERE id = :id"
        );
        $update->execute([
            ':id' => $id,
            ':nom' => $payload['name'],
            ':email' => $payload['email'],
            ':etablissement' => $payload['school'],
            ':filiere' => $payload['field'],
            ':niveau' => $payload['level'],
            ':date_debut' => $payload['startDate'],
            ':date_fin' => $payload['endDate'],
            ':statut' => $payload['status'],
        ]);

        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare("SELECT id FROM stagiaires WHERE id = :id LIMIT 1");
            $exists->execute([':id' => $id]);
            if (!$exists->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Stagiaire introuvable'], 404);
            }
        }

        $select = $pdo->prepare(
            "SELECT id, nom, email, etablissement, filiere, niveau, date_debut, date_fin, statut
               FROM stagiaires
              WHERE id = :id
              LIMIT 1"
        );
        $select->execute([':id' => $id]);
        $updated = $select->fetch();

        jsonResponse([
            'success' => true,
            'message' => 'Stagiaire mis à jour avec succès',
            'stagiaire' => $updated ? normalizeRow($updated) : null,
        ]);
    }

    if ($method === 'DELETE') {
        $delete = $pdo->prepare("DELETE FROM stagiaires WHERE id = :id");
        $delete->execute([':id' => $id]);

        if ($delete->rowCount() === 0) {
            jsonResponse(['success' => false, 'message' => 'Stagiaire introuvable'], 404);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Stagiaire supprimé avec succès',
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        jsonResponse(['success' => false, 'message' => 'Cet email existe déjà'], 409);
    }

    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
