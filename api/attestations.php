<?php
// api/attestations.php — Liste des stagiaires et génération d'attestation

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET', 'POST']);
requireAuthentication();

function buildMention(string $status): string
{
    return match ($status) {
        'termine' => 'serieux et entiere satisfaction',
        'actif' => 'serieux et engagement',
        default => 'application',
    };
}

try {
    $pdo = getDB();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT id, nom, email, etablissement, filiere, niveau, date_debut, date_fin, statut
               FROM stagiaires
              ORDER BY nom ASC"
        );

        jsonResponse([
            'success' => true,
            'stagiaires' => $stmt->fetchAll(),
        ]);
    }

    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    $body = readJsonBody();
    $stagiaireId = (int) ($body['stagiaireId'] ?? 0);

    if ($stagiaireId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Stagiaire requis'], 400);
    }

    $stmt = $pdo->prepare(
        "SELECT id, nom, email, etablissement, filiere, niveau, date_debut, date_fin, statut
           FROM stagiaires
          WHERE id = :id
          LIMIT 1"
    );
    $stmt->execute([':id' => $stagiaireId]);
    $stagiaire = $stmt->fetch();

    if (!$stagiaire) {
        jsonResponse(['success' => false, 'message' => 'Stagiaire introuvable'], 404);
    }

    $attestationStmt = $pdo->prepare(
        "SELECT reference, mention, date_generation
           FROM attestations
          WHERE stagiaire_id = :stagiaire_id
          ORDER BY id DESC
          LIMIT 1"
    );
    $attestationStmt->execute([':stagiaire_id' => $stagiaireId]);
    $attestation = $attestationStmt->fetch();

    if (!$attestation) {
        $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM attestations");
        $nextNumber = (int) (($countStmt->fetch()['total'] ?? 0) + 1);
        $reference = 'CI/' . date('Y') . '/' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
        $mention = buildMention($stagiaire['statut']);
        $dateGeneration = date('Y-m-d');

        $insert = $pdo->prepare(
            "INSERT INTO attestations (stagiaire_id, reference, mention, date_generation)
             VALUES (:stagiaire_id, :reference, :mention, :date_generation)"
        );
        $insert->execute([
            ':stagiaire_id' => $stagiaireId,
            ':reference' => $reference,
            ':mention' => $mention,
            ':date_generation' => $dateGeneration,
        ]);

        $attestation = [
            'reference' => $reference,
            'mention' => $mention,
            'date_generation' => $dateGeneration,
        ];
    }

    jsonResponse([
        'success' => true,
        'stagiaire' => $stagiaire,
        'attestation' => $attestation,
    ]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
