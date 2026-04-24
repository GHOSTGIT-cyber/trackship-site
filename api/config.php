<?php
// api/config.php
// Configuration base de données Hostinger

define('DB_HOST', 'localhost');
define('DB_NAME', 'u411940699_Trackship');
define('DB_USER', 'u411940699_ghost');
define('DB_PASS', 'Trackship6?');
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
