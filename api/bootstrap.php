<?php

function sendCorsHeaders(array $methods = ['GET']): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigin = $origin !== '' ? $origin : 'http://localhost:8888';

    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: ' . implode(', ', $methods) . ', OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

function handlePreflight(array $methods = ['GET']): void
{
    sendCorsHeaders($methods);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function ensureMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }
}

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireAuthentication(): void
{
    startAppSession();

    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
    }
}

function currentUserFromSession(): array
{
    return [
        'id' => $_SESSION['user_id'],
        'nom' => $_SESSION['nom'],
        'identifiant' => $_SESSION['login'],
        'role' => $_SESSION['role'],
    ];
}

function requireAdminAccess(): array
{
    $user = currentUserFromSession();

    if (($user['role'] ?? '') !== 'admin') {
        jsonResponse([
            'success' => false,
            'message' => 'Désolé, cette action nécessite des privilèges d’Administrateur. Je ne peux pas modifier les accès système.'
        ], 403);
    }

    return $user;
}

function getCurrentSupervisorId(PDO $pdo): int
{
    $user = currentUserFromSession();

    if (($user['role'] ?? '') !== 'encadreur') {
        jsonResponse(['success' => false, 'message' => 'Accès réservé aux encadreurs'], 403);
    }

    $stmt = $pdo->prepare(
        "SELECT encadreur_id
           FROM utilisateurs
          WHERE id = :id
            AND role = 'encadreur'
            AND actif = 1
          LIMIT 1"
    );
    $stmt->execute([':id' => $user['id']]);
    $encadreurId = (int) ($stmt->fetchColumn() ?? 0);

    if ($encadreurId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Aucun profil encadreur lié à ce compte'], 403);
    }

    return $encadreurId;
}

function canAccessSupervisorScope(PDO $pdo, int $supervisorId, int $traineeId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
           FROM affectations
          WHERE encadreur_id = :encadreur_id
            AND stagiaire_id = :stagiaire_id
          LIMIT 1"
    );
    $stmt->execute([
        ':encadreur_id' => $supervisorId,
        ':stagiaire_id' => $traineeId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function canAccessAssignment(PDO $pdo, int $supervisorId, int $assignmentId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
           FROM affectations
          WHERE id = :id
            AND encadreur_id = :encadreur_id
          LIMIT 1"
    );
    $stmt->execute([
        ':id' => $assignmentId,
        ':encadreur_id' => $supervisorId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);

    return is_array($decoded) ? $decoded : [];
}
