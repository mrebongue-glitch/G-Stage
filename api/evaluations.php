<?php
// api/evaluations.php — Liste et gestion des évaluations

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST', 'PUT', 'DELETE']);
requireAuthentication();

function normalizeEvaluation(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'affectationId' => (int) $row['affectation_id'],
        'trainee' => $row['stagiaire_nom'],
        'module' => $row['module_titre'],
        'date' => $row['date_evaluation'],
        'score' => (float) $row['note'],
        'comment' => $row['commentaire'] ?? '',
    ];
}

function appreciationFromScore(float $score): string
{
    return $score >= 10 ? 'Bien' : 'Insuffisant';
}

function fetchEvaluationOptions(PDO $pdo): array
{
    $user = currentUserFromSession();
    $isAdmin = ($user['role'] ?? '') === 'admin';

    if ($isAdmin) {
        $stmt = $pdo->query(
            "SELECT a.id,
                    s.nom AS stagiaire_nom,
                    m.titre AS module_titre,
                    a.statut
               FROM affectations a
               INNER JOIN stagiaires s ON s.id = a.stagiaire_id
               INNER JOIN modules m ON m.id = a.module_id
              ORDER BY s.nom ASC, m.titre ASC"
        );
    } else {
        $supervisorId = getCurrentSupervisorId($pdo);
        $stmt = $pdo->prepare(
            "SELECT a.id,
                    s.nom AS stagiaire_nom,
                    m.titre AS module_titre,
                    a.statut
               FROM affectations a
               INNER JOIN stagiaires s ON s.id = a.stagiaire_id
               INNER JOIN modules m ON m.id = a.module_id
              WHERE a.encadreur_id = :encadreur_id
              ORDER BY s.nom ASC, m.titre ASC"
        );
        $stmt->execute([':encadreur_id' => $supervisorId]);
    }

    return array_map(
        static fn (array $row): array => [
            'id' => (int) $row['id'],
            'label' => $row['stagiaire_nom'] . ' - ' . $row['module_titre'] . ' (' . $row['statut'] . ')',
        ],
        $stmt->fetchAll()
    );
}

function readEvaluationPayload(): array
{
    $body = readJsonBody();

    $payload = [
        'affectationId' => (int) ($body['affectationId'] ?? 0),
        'date' => trim($body['date'] ?? ''),
        'score' => (float) ($body['score'] ?? -1),
        'comment' => trim($body['comment'] ?? ''),
    ];

    if (
        $payload['affectationId'] <= 0 ||
        $payload['date'] === '' ||
        $payload['comment'] === '' ||
        $payload['score'] < 0 ||
        $payload['score'] > 20
    ) {
        jsonResponse(['success' => false, 'message' => 'Tous les champs de l’évaluation sont requis'], 400);
    }

    return $payload;
}

try {
    $pdo = getDB();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = currentUserFromSession();
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $supervisorId = $isAdmin ? null : getCurrentSupervisorId($pdo);

    if ($method === 'GET') {
        if ($isAdmin) {
            $stmt = $pdo->query(
                "SELECT e.id, e.affectation_id, e.date_evaluation, e.note, e.commentaire,
                        s.nom AS stagiaire_nom,
                        m.titre AS module_titre
                   FROM evaluations e
                   INNER JOIN stagiaires s ON s.id = e.stagiaire_id
                   INNER JOIN modules m ON m.id = e.module_id
                  ORDER BY e.date_evaluation DESC, e.id DESC"
            );
        } else {
            $stmt = $pdo->prepare(
                "SELECT e.id, e.affectation_id, e.date_evaluation, e.note, e.commentaire,
                        s.nom AS stagiaire_nom,
                        m.titre AS module_titre
                   FROM evaluations e
                   INNER JOIN affectations a ON a.id = e.affectation_id
                   INNER JOIN stagiaires s ON s.id = e.stagiaire_id
                   INNER JOIN modules m ON m.id = e.module_id
                  WHERE a.encadreur_id = :encadreur_id
                  ORDER BY e.date_evaluation DESC, e.id DESC"
            );
            $stmt->execute([':encadreur_id' => $supervisorId]);
        }

        jsonResponse([
            'success' => true,
            'evaluations' => array_map('normalizeEvaluation', $stmt->fetchAll()),
            'options' => fetchEvaluationOptions($pdo),
        ]);
    }

    if ($method === 'POST') {
        $payload = readEvaluationPayload();

        $assignment = $pdo->prepare(
            "SELECT a.id, a.stagiaire_id, a.module_id
               FROM affectations a
              WHERE a.id = :id
              LIMIT 1"
        );
        $assignment->execute([':id' => $payload['affectationId']]);
        $assignmentRow = $assignment->fetch();

        if (!$assignmentRow) {
            jsonResponse(['success' => false, 'message' => 'Affectation introuvable'], 404);
        }

        if (!$isAdmin && !canAccessAssignment($pdo, $supervisorId, (int) $assignmentRow['id'])) {
            jsonResponse(['success' => false, 'message' => 'Accès refusé à cette affectation'], 403);
        }

        $insert = $pdo->prepare(
            "INSERT INTO evaluations (affectation_id, stagiaire_id, module_id, date_evaluation, note, appreciation, commentaire)
             VALUES (:affectation_id, :stagiaire_id, :module_id, :date_evaluation, :note, :appreciation, :commentaire)"
        );
        $insert->execute([
            ':affectation_id' => $assignmentRow['id'],
            ':stagiaire_id' => $assignmentRow['stagiaire_id'],
            ':module_id' => $assignmentRow['module_id'],
            ':date_evaluation' => $payload['date'],
            ':note' => $payload['score'],
            ':appreciation' => appreciationFromScore($payload['score']),
            ':commentaire' => $payload['comment'],
        ]);

        $id = (int) $pdo->lastInsertId();
        $select = $pdo->prepare(
            "SELECT e.id, e.affectation_id, e.date_evaluation, e.note, e.commentaire,
                    s.nom AS stagiaire_nom,
                    m.titre AS module_titre
               FROM evaluations e
               INNER JOIN stagiaires s ON s.id = e.stagiaire_id
               INNER JOIN modules m ON m.id = e.module_id
              WHERE e.id = :id
              LIMIT 1"
        );
        $select->execute([':id' => $id]);

        jsonResponse([
            'success' => true,
            'evaluation' => normalizeEvaluation($select->fetch() ?: []),
        ], 201);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Identifiant de l’évaluation requis'], 400);
    }

    if ($method === 'PUT') {
        $payload = readEvaluationPayload();

        $assignment = $pdo->prepare(
            "SELECT a.id, a.stagiaire_id, a.module_id
               FROM affectations a
              WHERE a.id = :id
              LIMIT 1"
        );
        $assignment->execute([':id' => $payload['affectationId']]);
        $assignmentRow = $assignment->fetch();

        if (!$assignmentRow) {
            jsonResponse(['success' => false, 'message' => 'Affectation introuvable'], 404);
        }

        if (!$isAdmin && !canAccessAssignment($pdo, $supervisorId, (int) $assignmentRow['id'])) {
            jsonResponse(['success' => false, 'message' => 'Accès refusé à cette affectation'], 403);
        }

        if (!$isAdmin) {
            $ownedEvaluation = $pdo->prepare(
                "SELECT 1
                   FROM evaluations e
                   INNER JOIN affectations a ON a.id = e.affectation_id
                  WHERE e.id = :id
                    AND a.encadreur_id = :encadreur_id
                  LIMIT 1"
            );
            $ownedEvaluation->execute([
                ':id' => $id,
                ':encadreur_id' => $supervisorId,
            ]);

            if (!$ownedEvaluation->fetchColumn()) {
                jsonResponse(['success' => false, 'message' => 'Accès refusé à cette évaluation'], 403);
            }
        }

        $update = $pdo->prepare(
            "UPDATE evaluations
                SET affectation_id = :affectation_id,
                    stagiaire_id = :stagiaire_id,
                    module_id = :module_id,
                    date_evaluation = :date_evaluation,
                    note = :note,
                    appreciation = :appreciation,
                    commentaire = :commentaire
              WHERE id = :id"
        );
        $update->execute([
            ':id' => $id,
            ':affectation_id' => $assignmentRow['id'],
            ':stagiaire_id' => $assignmentRow['stagiaire_id'],
            ':module_id' => $assignmentRow['module_id'],
            ':date_evaluation' => $payload['date'],
            ':note' => $payload['score'],
            ':appreciation' => appreciationFromScore($payload['score']),
            ':commentaire' => $payload['comment'],
        ]);

        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare("SELECT id FROM evaluations WHERE id = :id LIMIT 1");
            $exists->execute([':id' => $id]);
            if (!$exists->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Évaluation introuvable'], 404);
            }
        }

        jsonResponse([
            'success' => true,
            'message' => 'Évaluation mise à jour',
        ]);
    }

    if ($method === 'DELETE') {
        if (!$isAdmin) {
            $ownedEvaluation = $pdo->prepare(
                "SELECT 1
                   FROM evaluations e
                   INNER JOIN affectations a ON a.id = e.affectation_id
                  WHERE e.id = :id
                    AND a.encadreur_id = :encadreur_id
                  LIMIT 1"
            );
            $ownedEvaluation->execute([
                ':id' => $id,
                ':encadreur_id' => $supervisorId,
            ]);

            if (!$ownedEvaluation->fetchColumn()) {
                jsonResponse(['success' => false, 'message' => 'Accès refusé à cette évaluation'], 403);
            }
        }

        $delete = $pdo->prepare("DELETE FROM evaluations WHERE id = :id");
        $delete->execute([':id' => $id]);

        if ($delete->rowCount() === 0) {
            jsonResponse(['success' => false, 'message' => 'Évaluation introuvable'], 404);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Évaluation supprimée',
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        jsonResponse(['success' => false, 'message' => 'Cette évaluation est déjà enregistrée ou viole une contrainte'], 409);
    }

    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
