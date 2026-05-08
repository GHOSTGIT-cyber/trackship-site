<?php
// api/test-db.php
// Test diag DB - à supprimer après usage
header('Content-Type: application/json');

$secret = $_GET['secret'] ?? '';
if ($secret !== 'trackship-init-2026') {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$host = 'vspvmdgly2s24e73tqkpo8a1';
$db = 'default';
$results = [];

// Test 1 : user trackship sur 'default'
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", 'trackship', 'NmCGVOGy9xO', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $results['trackship_on_default'] = 'OK';
} catch (Exception $e) {
    $results['trackship_on_default'] = 'FAIL: ' . $e->getMessage();
}

// Test 2 : root sans dbname
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", 'root', 'UbDphwqW06ENyubhJ5fKIEc2yW6nAa4HLmQWue7DKNP9XeJiaD4inX4uGodz392J', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $results['root_no_db'] = 'OK';

    // Lister les users
    $stmt = $pdo->query("SELECT user, host FROM mysql.user");
    $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lister les bases
    $stmt = $pdo->query("SHOW DATABASES");
    $results['databases'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Voir grants pour trackship
    try {
        $stmt = $pdo->query("SHOW GRANTS FOR 'trackship'@'%'");
        $results['grants_trackship_pct'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $results['grants_trackship_pct'] = 'no grants for %';
    }
} catch (Exception $e) {
    $results['root_no_db'] = 'FAIL: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
