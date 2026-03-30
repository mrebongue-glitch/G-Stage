<?php
// api/dashboard.php — Données dynamiques du tableau de bord

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

handlePreflight(['GET']);
ensureMethod('GET');
requireAuthentication();

function initialsFromName(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn ($part) => $part !== ''));

    if ($parts === []) {
        return '--';
    }

    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
    }

    return strtoupper($initials);
}

function traineeStatusLabel(string $status): string
{
    return match ($status) {
        'actif' => 'Actif',
        'termine' => 'Terminé',
        'abandonne' => 'Abandonné',
        default => ucfirst($status),
    };
}

function assignmentStatusLabel(string $status): string
{
    return match ($status) {
        'en-cours' => 'En cours',
        'terminee' => 'Terminée',
        default => ucfirst($status),
    };
}

try {
    $pdo = getDB();
    $user = currentUserFromSession();
    $isAdmin = ($user['role'] ?? '') === 'admin';

    if ($isAdmin) {
        $stats = $pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM stagiaires WHERE statut = 'actif') AS stagiaires_actifs,
                (SELECT COUNT(*) FROM encadreurs) AS total_encadreurs,
                (SELECT COUNT(*) FROM modules) AS total_modules,
                (SELECT COUNT(*) FROM affectations WHERE statut = 'en-cours') AS affectations_en_cours"
        )->fetch() ?: [];

        $recentStmt = $pdo->query(
            "SELECT nom, etablissement, filiere, statut
               FROM stagiaires
              ORDER BY created_at DESC, id DESC
              LIMIT 5"
        );
        $recentRows = $recentStmt->fetchAll();

        $assignmentStmt = $pdo->query(
            "SELECT s.nom AS stagiaire_nom, m.titre AS module_titre, a.statut
               FROM affectations a
               INNER JOIN stagiaires s ON s.id = a.stagiaire_id
               INNER JOIN modules m ON m.id = a.module_id
              ORDER BY a.date_affectation DESC, a.id DESC
              LIMIT 5"
        );
        $assignmentRows = $assignmentStmt->fetchAll();
    } else {
        $supervisorId = getCurrentSupervisorId($pdo);
        $statsStmt = $pdo->prepare(
            "SELECT
                (SELECT COUNT(DISTINCT a.stagiaire_id)
                   FROM affectations a
                   INNER JOIN stagiaires s ON s.id = a.stagiaire_id
                  WHERE a.encadreur_id = :encadreur_id
                    AND s.statut = 'actif') AS stagiaires_actifs,
                (SELECT COUNT(*)
                   FROM evaluations e
                   INNER JOIN affectations a ON a.id = e.affectation_id
                  WHERE a.encadreur_id = :encadreur_id) AS total_evaluations,
                (SELECT COUNT(*)
                   FROM attestations t
                   INNER JOIN affectations a ON a.stagiaire_id = t.stagiaire_id
                  WHERE a.encadreur_id = :encadreur_id) AS total_attestations,
                (SELECT COUNT(*)
                   FROM affectations
                  WHERE encadreur_id = :encadreur_id
                    AND statut = 'en-cours') AS affectations_en_cours"
        );
        $statsStmt->execute([':encadreur_id' => $supervisorId]);
        $stats = $statsStmt->fetch() ?: [];

        $recentStmt = $pdo->prepare(
            "SELECT DISTINCT s.nom, s.etablissement, s.filiere, s.statut, s.created_at, s.id
               FROM stagiaires s
               INNER JOIN affectations a ON a.stagiaire_id = s.id
              WHERE a.encadreur_id = :encadreur_id
              ORDER BY s.created_at DESC, s.id DESC
              LIMIT 5"
        );
        $recentStmt->execute([':encadreur_id' => $supervisorId]);
        $recentRows = $recentStmt->fetchAll();

        $assignmentStmt = $pdo->prepare(
            "SELECT s.nom AS stagiaire_nom, m.titre AS module_titre, a.statut
               FROM affectations a
               INNER JOIN stagiaires s ON s.id = a.stagiaire_id
               INNER JOIN modules m ON m.id = a.module_id
              WHERE a.encadreur_id = :encadreur_id
              ORDER BY a.date_affectation DESC, a.id DESC
              LIMIT 5"
        );
        $assignmentStmt->execute([':encadreur_id' => $supervisorId]);
        $assignmentRows = $assignmentStmt->fetchAll();
    }

    $recentTrainees = array_map(
        static function (array $row): array {
            return [
                'initials' => initialsFromName($row['nom']),
                'name' => $row['nom'],
                'sub' => $row['etablissement'] . ' - ' . $row['filiere'],
                'status' => $row['statut'],
                'statusLabel' => traineeStatusLabel($row['statut']),
            ];
        },
        $recentRows
    );

    $assignments = array_map(
        static function (array $row): array {
            return [
                'name' => $row['stagiaire_nom'],
                'module' => $row['module_titre'],
                'status' => $row['statut'],
                'statusLabel' => assignmentStatusLabel($row['statut']),
            ];
        },
        $assignmentRows
    );

    jsonResponse([
        'success' => true,
        'stats' => [
            ['label' => 'Stagiaires actifs', 'value' => (int) ($stats['stagiaires_actifs'] ?? 0)],
            ['label' => $isAdmin ? 'Encadreurs' : 'Evaluations', 'value' => (int) ($isAdmin ? ($stats['total_encadreurs'] ?? 0) : ($stats['total_evaluations'] ?? 0))],
            ['label' => $isAdmin ? 'Modules' : 'Attestations', 'value' => (int) ($isAdmin ? ($stats['total_modules'] ?? 0) : ($stats['total_attestations'] ?? 0))],
            ['label' => 'Affectations en cours', 'value' => (int) ($stats['affectations_en_cours'] ?? 0)],
        ],
        'recentTrainees' => $recentTrainees,
        'assignments' => $assignments,
        'user' => currentUserFromSession(),
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
