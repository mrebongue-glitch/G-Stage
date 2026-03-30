<?php
// api/db.php — Connexion PDO à la base de données
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', '8889');
defined('DB_NAME') || define('DB_NAME', 'gestiondesstagiaires');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', 'root');

function ensureSchema(PDO $pdo): void {
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    $columnCheck = $pdo->prepare(
        'SELECT COUNT(*)
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = :schema
            AND TABLE_NAME = :table
            AND COLUMN_NAME = :column'
    );

    $columnCheck->execute([
        ':schema' => DB_NAME,
        ':table' => 'utilisateurs',
        ':column' => 'encadreur_id',
    ]);

    if ((int) $columnCheck->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE utilisateurs
             ADD COLUMN encadreur_id INT DEFAULT NULL AFTER role"
        );
    }

    $indexCheck = $pdo->prepare(
        'SELECT COUNT(*)
           FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = :schema
            AND TABLE_NAME = :table
            AND INDEX_NAME = :index_name'
    );

    $indexCheck->execute([
        ':schema' => DB_NAME,
        ':table' => 'utilisateurs',
        ':index_name' => 'uq_utilisateurs_encadreur',
    ]);

    if ((int) $indexCheck->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE utilisateurs
             ADD UNIQUE KEY uq_utilisateurs_encadreur (encadreur_id)'
        );
    }

    $foreignKeyCheck = $pdo->prepare(
        'SELECT COUNT(*)
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = :schema
            AND TABLE_NAME = :table
            AND CONSTRAINT_NAME = :constraint_name
            AND REFERENCED_TABLE_NAME IS NOT NULL'
    );

    $foreignKeyCheck->execute([
        ':schema' => DB_NAME,
        ':table' => 'utilisateurs',
        ':constraint_name' => 'fk_utilisateurs_encadreur',
    ]);

    if ((int) $foreignKeyCheck->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE utilisateurs
             ADD CONSTRAINT fk_utilisateurs_encadreur
             FOREIGN KEY (encadreur_id) REFERENCES encadreurs (id)
             ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }
}

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensureSchema($pdo);
        } catch (PDOException $e) {
            throw new RuntimeException('Connexion à la base de données impossible', 0, $e);
        }
    }
    return $pdo;
}
