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
