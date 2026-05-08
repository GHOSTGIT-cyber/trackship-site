<?php
// api/config.php
// Configuration base de données Coolify (service trackship-db)

define('DB_HOST', getenv('DB_HOST') ?: 'vspvmdgly2s24e73tqkpo8a1');
define('DB_NAME', getenv('DB_NAME') ?: 'default');
define('DB_USER', getenv('DB_USER') ?: 'trackship');
define('DB_PASS', getenv('DB_PASS') ?: 'NmCGVOGy9xO');
define('DB_CHARSET', 'utf8mb4');

/**
 * Fonction de connexion PDO avec gestion d'erreurs
 * Utilise une connexion statique pour réutiliser la même instance
 *
 * @return PDO Instance de connexion PDO
 */
function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            exit(json_encode([
                'error' => 'Erreur de connexion à la base de données',
                'message' => $e->getMessage()
            ]));
        }
    }

    return $pdo;
}
?>
