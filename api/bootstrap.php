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

function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);

    return is_array($decoded) ? $decoded : [];
}
