<?php
// api/init.php
// Script one-shot d'initialisation de la base de données.
// Crée les tables si elles n'existent pas (idempotent).
// Protégé par un secret simple via paramètre GET.

header('Content-Type: application/json');

require_once 'config.php';

// Protection : exiger un secret pour éviter exécution publique
$secret = $_GET['secret'] ?? '';
$expected = getenv('INIT_SECRET') ?: 'trackship-init-2026';

if ($secret !== $expected) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden : paramètre secret manquant ou invalide']));
}

try {
    // Connexion en root pour créer le user d'application si absent
    // (Coolify n'a pas créé le user trackship malgré la config)
    $rootPwd = 'UbDphwqW06ENyubhJ5fKIEc2yW6nAa4HLmQWue7DKNP9XeJiaD4inX4uGodz392J';
    $rootPdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        'root',
        $rootPwd,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $rootPdo->exec("CREATE USER IF NOT EXISTS '" . DB_USER . "'@'%' IDENTIFIED BY '" . DB_PASS . "'");
    $rootPdo->exec("GRANT ALL PRIVILEGES ON `" . DB_NAME . "`.* TO '" . DB_USER . "'@'%'");
    $rootPdo->exec("FLUSH PRIVILEGES");

    // Maintenant connexion en tant que user d'app pour créer les tables
    $pdo = getDbConnection();

    // Lecture du fichier SQL
    $sqlPath = __DIR__ . '/init-database.sql';
    if (!file_exists($sqlPath)) {
        throw new Exception("Fichier SQL introuvable : $sqlPath");
    }

    $sql = file_get_contents($sqlPath);

    // Découpage en requêtes individuelles (séparateur ;)
    // On retire les commentaires SQL avant
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $resultats = [];
    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;

        try {
            $result = $pdo->query($stmt);
            $resultats[] = [
                'statement' => substr($stmt, 0, 80) . (strlen($stmt) > 80 ? '...' : ''),
                'status' => 'OK'
            ];
        } catch (PDOException $e) {
            $resultats[] = [
                'statement' => substr($stmt, 0, 80) . (strlen($stmt) > 80 ? '...' : ''),
                'status' => 'ERREUR',
                'message' => $e->getMessage()
            ];
        }
    }

    // Vérification finale : tables présentes ?
    $stmt = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name IN ('compteur_jours', 'bateaux_vus', 'bateaux_zone_rouge_actifs')
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => count($tables) === 3,
        'tables_creees' => $tables,
        'execution' => $resultats
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Échec initialisation',
        'message' => $e->getMessage()
    ]);
}
?>
