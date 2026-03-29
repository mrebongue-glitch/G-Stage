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
        $recentStmt->fetchAll()
    );

    $assignmentStmt = $pdo->query(
        "SELECT s.nom AS stagiaire_nom, m.titre AS module_titre, a.statut
           FROM affectations a
           INNER JOIN stagiaires s ON s.id = a.stagiaire_id
           INNER JOIN modules m ON m.id = a.module_id
          ORDER BY a.date_affectation DESC, a.id DESC
          LIMIT 5"
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
        $assignmentStmt->fetchAll()
    );

    jsonResponse([
        'success' => true,
        'stats' => [
            ['label' => 'Stagiaires actifs', 'value' => (int) ($stats['stagiaires_actifs'] ?? 0)],
            ['label' => 'Encadreurs', 'value' => (int) ($stats['total_encadreurs'] ?? 0)],
            ['label' => 'Modules', 'value' => (int) ($stats['total_modules'] ?? 0)],
            ['label' => 'Affectations en cours', 'value' => (int) ($stats['affectations_en_cours'] ?? 0)],
        ],
        'recentTrainees' => $recentTrainees,
        'assignments' => $assignments,
        'user' => currentUserFromSession(),
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
