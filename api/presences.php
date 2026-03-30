<?php
// api/presences.php — Consultation et enregistrement des présences

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST']);
requireAuthentication();

function normalizePresenceRow(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['nom'],
        'track' => $row['filiere'] . ' - ' . $row['niveau'],
        'school' => $row['etablissement'],
        'status' => $row['presence_statut'] ?? '',
        'reason' => $row['motif'] ?? '',
    ];
}

try {
    $pdo = getDB();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = currentUserFromSession();
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $supervisorId = $isAdmin ? null : getCurrentSupervisorId($pdo);

    if ($method === 'GET') {
        $date = trim($_GET['date'] ?? '');
        if ($date === '') {
            jsonResponse(['success' => false, 'message' => 'Date requise'], 400);
        }

        if ($isAdmin) {
            $stmt = $pdo->prepare(
                "SELECT s.id, s.nom, s.etablissement, s.filiere, s.niveau,
                        p.statut AS presence_statut, p.motif
                   FROM stagiaires s
                   LEFT JOIN presences p
                     ON p.stagiaire_id = s.id
                    AND p.date_presence = :date_presence
                  WHERE s.statut = 'actif'
                  ORDER BY s.nom ASC"
            );
            $stmt->execute([':date_presence' => $date]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT s.id, s.nom, s.etablissement, s.filiere, s.niveau,
                        p.statut AS presence_statut, p.motif
                   FROM stagiaires s
                   INNER JOIN affectations a ON a.stagiaire_id = s.id
                   LEFT JOIN presences p
                     ON p.stagiaire_id = s.id
                    AND p.date_presence = :date_presence
                  WHERE s.statut = 'actif'
                    AND a.encadreur_id = :encadreur_id
                  ORDER BY s.nom ASC"
            );
            $stmt->execute([
                ':date_presence' => $date,
                ':encadreur_id' => $supervisorId,
            ]);
        }

        jsonResponse([
            'success' => true,
            'date' => $date,
            'presences' => array_map('normalizePresenceRow', $stmt->fetchAll()),
        ]);
    }

    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    $body = readJsonBody();
    $stagiaireId = (int) ($body['stagiaireId'] ?? 0);
    $date = trim($body['date'] ?? '');
    $status = trim($body['status'] ?? '');
    $reason = trim($body['reason'] ?? '');

    if ($stagiaireId <= 0 || $date === '' || !in_array($status, ['present', 'absent'], true)) {
        jsonResponse(['success' => false, 'message' => 'Stagiaire, date et statut valides sont requis'], 400);
    }

    if ($status === 'present') {
        $reason = '';
    }

    $exists = $pdo->prepare("SELECT id FROM stagiaires WHERE id = :id LIMIT 1");
    $exists->execute([':id' => $stagiaireId]);
    if (!$exists->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Stagiaire introuvable'], 404);
    }

    if (!$isAdmin && !canAccessSupervisorScope($pdo, $supervisorId, $stagiaireId)) {
        jsonResponse(['success' => false, 'message' => 'Accès refusé à ce stagiaire'], 403);
    }

    $upsert = $pdo->prepare(
        "INSERT INTO presences (stagiaire_id, date_presence, statut, motif)
         VALUES (:stagiaire_id, :date_presence, :statut, :motif)
         ON DUPLICATE KEY UPDATE
           statut = VALUES(statut),
           motif = VALUES(motif)"
    );
    $upsert->execute([
        ':stagiaire_id' => $stagiaireId,
        ':date_presence' => $date,
        ':statut' => $status,
        ':motif' => $reason !== '' ? $reason : null,
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Présence enregistrée avec succès',
    ]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
