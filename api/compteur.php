<?php
// api/compteur.php
// API REST pour le compteur journalier de passages en zone rouge

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Gestion preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Récupération de l'action
$action = $_GET['action'] ?? null;

if (!$action) {
    http_response_code(400);
    exit(json_encode(['error' => 'Paramètre action requis']));
}

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'get_current':
            getCurrent($pdo);
            break;

        case 'get_history':
            getHistory($pdo);
            break;

        case 'increment':
            incrementCounter($pdo);
            break;

        case 'update_zone_rouge':
            updateZoneRouge($pdo);
            break;

        case 'delete_days':
            deleteDays($pdo);
            break;

        default:
            http_response_code(400);
            exit(json_encode(['error' => 'Action invalide']));
    }
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode([
        'error' => 'Erreur serveur',
        'message' => $e->getMessage()
    ]));
}

/**
 * Récupère le jour actuel et ses informations
 * GET /api/compteur.php?action=get_current
 */
function getCurrent($pdo) {
    // S'assurer que le jour actuel existe
    ensureCurrentDayExists($pdo);

    // Récupérer le jour actuel
    $stmt = $pdo->prepare("
        SELECT numero_jour, date_jour, compteur_passages
        FROM compteur_jours
        WHERE date_jour = CURDATE()
        LIMIT 1
    ");
    $stmt->execute();
    $jourActuel = $stmt->fetch();

    if (!$jourActuel) {
        http_response_code(500);
        exit(json_encode(['error' => 'Impossible de récupérer le jour actuel']));
    }

    // Récupérer les bateaux actuellement en zone rouge
    $stmt = $pdo->prepare("
        SELECT track_id
        FROM bateaux_zone_rouge_actifs
        WHERE numero_jour = ?
    ");
    $stmt->execute([$jourActuel['numero_jour']]);
    $bateauxZoneRouge = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'numero_jour' => (int)$jourActuel['numero_jour'],
        'date_jour' => $jourActuel['date_jour'],
        'compteur_passages' => (int)$jourActuel['compteur_passages'],
        'bateaux_zone_rouge' => $bateauxZoneRouge
    ]);
}

/**
 * Récupère l'historique complet de tous les jours
 * GET /api/compteur.php?action=get_history
 */
function getHistory($pdo) {
    $stmt = $pdo->query("
        SELECT numero_jour, date_jour, compteur_passages
        FROM compteur_jours
        ORDER BY numero_jour ASC
    ");
    $historique = $stmt->fetchAll();

    // Calculer le total cumulé
    $totalCumule = array_sum(array_column($historique, 'compteur_passages'));

    echo json_encode([
        'success' => true,
        'historique' => $historique,
        'total_cumule' => $totalCumule
    ]);
}

/**
 * Incrémente le compteur quand un bateau entre en zone rouge
 * POST /api/compteur.php?action=increment
 * Body: {trackId: "123456", shipName: "Le Bateau"}
 */
function incrementCounter($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['trackId'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'trackId requis']));
    }

    $trackId = $input['trackId'];
    $shipName = $input['shipName'] ?? "Track $trackId";

    // S'assurer que le jour actuel existe
    ensureCurrentDayExists($pdo);

    // Récupérer le jour actuel
    $stmt = $pdo->prepare("
        SELECT numero_jour, compteur_passages
        FROM compteur_jours
        WHERE date_jour = CURDATE()
        LIMIT 1
    ");
    $stmt->execute();
    $jourActuel = $stmt->fetch();

    if (!$jourActuel) {
        http_response_code(500);
        exit(json_encode(['error' => 'Impossible de récupérer le jour actuel']));
    }

    $numeroJour = $jourActuel['numero_jour'];

    // Vérifier si le bateau est déjà en zone rouge ACTUELLEMENT
    $stmt = $pdo->prepare("
        SELECT id FROM bateaux_zone_rouge_actifs
        WHERE numero_jour = ? AND track_id = ?
    ");
    $stmt->execute([$numeroJour, $trackId]);
    $dejaEnZoneRouge = $stmt->fetch();

    if ($dejaEnZoneRouge) {
        // Déjà en zone rouge, ne pas incrémenter
        echo json_encode([
            'success' => true,
            'already_counted' => true,
            'numero_jour' => $numeroJour,
            'compteur' => (int)$jourActuel['compteur_passages'],
            'message' => "Bateau déjà en zone rouge"
        ]);
        return;
    }

    // NOUVELLE ENTRÉE : Incrémenter le compteur
    $pdo->beginTransaction();

    try {
        // 1. Incrémenter le compteur
        $stmt = $pdo->prepare("
            UPDATE compteur_jours
            SET compteur_passages = compteur_passages + 1
            WHERE numero_jour = ?
        ");
        $stmt->execute([$numeroJour]);

        // 2. Ajouter dans la table des bateaux en zone rouge actifs
        $stmt = $pdo->prepare("
            INSERT INTO bateaux_zone_rouge_actifs (numero_jour, track_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$numeroJour, $trackId]);

        // 3. Ajouter dans l'historique des bateaux vus (si pas déjà vu ce jour)
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO bateaux_vus (numero_jour, track_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$numeroJour, $trackId]);

        // 4. Récupérer le nouveau compteur
        $stmt = $pdo->prepare("
            SELECT compteur_passages FROM compteur_jours WHERE numero_jour = ?
        ");
        $stmt->execute([$numeroJour]);
        $nouveauCompteur = $stmt->fetchColumn();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'numero_jour' => $numeroJour,
            'compteur' => (int)$nouveauCompteur,
            'track_id' => $trackId,
            'ship_name' => $shipName,
            'message' => "Compteur incrémenté"
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Met à jour la liste des bateaux actuellement en zone rouge
 * POST /api/compteur.php?action=update_zone_rouge
 * Body: {trackIds: ["123", "456", "789"]}
 */
function updateZoneRouge($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['trackIds']) || !is_array($input['trackIds'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'trackIds (array) requis']));
    }

    $trackIds = $input['trackIds'];

    // S'assurer que le jour actuel existe
    ensureCurrentDayExists($pdo);

    // Récupérer le jour actuel
    $stmt = $pdo->prepare("
        SELECT numero_jour FROM compteur_jours WHERE date_jour = CURDATE() LIMIT 1
    ");
    $stmt->execute();
    $jourActuel = $stmt->fetch();

    if (!$jourActuel) {
        http_response_code(500);
        exit(json_encode(['error' => 'Impossible de récupérer le jour actuel']));
    }

    $numeroJour = $jourActuel['numero_jour'];

    // Récupérer les bateaux actuellement enregistrés en zone rouge
    $stmt = $pdo->prepare("
        SELECT track_id FROM bateaux_zone_rouge_actifs WHERE numero_jour = ?
    ");
    $stmt->execute([$numeroJour]);
    $bateauxActuels = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Déterminer les bateaux à supprimer (sortis de zone)
    $bateauxASupprimer = array_diff($bateauxActuels, $trackIds);

    if (count($bateauxASupprimer) > 0) {
        $placeholders = implode(',', array_fill(0, count($bateauxASupprimer), '?'));
        $stmt = $pdo->prepare("
            DELETE FROM bateaux_zone_rouge_actifs
            WHERE numero_jour = ? AND track_id IN ($placeholders)
        ");
        $stmt->execute(array_merge([$numeroJour], $bateauxASupprimer));
    }

    echo json_encode([
        'success' => true,
        'numero_jour' => $numeroJour,
        'bateaux_actifs' => $trackIds,
        'bateaux_supprimes' => array_values($bateauxASupprimer)
    ]);
}

/**
 * Efface des jours de l'historique
 * POST /api/compteur.php?action=delete_days
 * Body: {type: "range", debut: 1, fin: 30} ou {type: "single", jour: 15} ou {type: "all"}
 */
function deleteDays($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['type'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'type requis (range, single, all)']));
    }

    $type = $input['type'];

    // Récupérer le jour actuel pour éviter de le supprimer
    $stmt = $pdo->prepare("
        SELECT numero_jour FROM compteur_jours WHERE date_jour = CURDATE() LIMIT 1
    ");
    $stmt->execute();
    $jourActuel = $stmt->fetch();
    $numeroJourActuel = $jourActuel ? $jourActuel['numero_jour'] : 9999;

    $pdo->beginTransaction();

    try {
        switch ($type) {
            case 'all':
                // Supprimer TOUS les jours sauf le jour actuel
                $stmt = $pdo->prepare("
                    DELETE FROM compteur_jours WHERE numero_jour < ?
                ");
                $stmt->execute([$numeroJourActuel]);
                $message = "Tout l'historique a été effacé";
                break;

            case 'range':
                if (!isset($input['debut']) || !isset($input['fin'])) {
                    http_response_code(400);
                    exit(json_encode(['error' => 'debut et fin requis pour type=range']));
                }

                $debut = (int)$input['debut'];
                $fin = (int)$input['fin'];

                if ($debut >= $numeroJourActuel || $fin >= $numeroJourActuel) {
                    http_response_code(400);
                    exit(json_encode(['error' => 'Impossible de supprimer le jour actuel ou futur']));
                }

                $stmt = $pdo->prepare("
                    DELETE FROM compteur_jours WHERE numero_jour >= ? AND numero_jour <= ?
                ");
                $stmt->execute([$debut, $fin]);
                $message = "Jours $debut à $fin effacés";
                break;

            case 'single':
                if (!isset($input['jour'])) {
                    http_response_code(400);
                    exit(json_encode(['error' => 'jour requis pour type=single']));
                }

                $jour = (int)$input['jour'];

                if ($jour >= $numeroJourActuel) {
                    http_response_code(400);
                    exit(json_encode(['error' => 'Impossible de supprimer le jour actuel ou futur']));
                }

                $stmt = $pdo->prepare("
                    DELETE FROM compteur_jours WHERE numero_jour = ?
                ");
                $stmt->execute([$jour]);
                $message = "Jour $jour effacé";
                break;

            default:
                http_response_code(400);
                exit(json_encode(['error' => 'Type invalide (range, single, all)']));
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * S'assure que le jour actuel existe dans la base de données
 * Si minuit est passé, crée automatiquement un nouveau jour et nettoie les bateaux actifs
 */
function ensureCurrentDayExists($pdo) {
    // Vérifier si le jour actuel existe
    $stmt = $pdo->prepare("
        SELECT numero_jour FROM compteur_jours WHERE date_jour = CURDATE() LIMIT 1
    ");
    $stmt->execute();
    $jourActuel = $stmt->fetch();

    if (!$jourActuel) {
        // Le jour actuel n'existe pas : créer un nouveau jour
        $pdo->beginTransaction();

        try {
            // Récupérer le dernier numéro de jour
            $stmt = $pdo->query("
                SELECT COALESCE(MAX(numero_jour), 0) as dernier_numero FROM compteur_jours
            ");
            $dernierNumero = $stmt->fetch()['dernier_numero'];
            $nouveauNumero = $dernierNumero + 1;

            // Créer le nouveau jour
            $stmt = $pdo->prepare("
                INSERT INTO compteur_jours (numero_jour, date_jour, compteur_passages)
                VALUES (?, CURDATE(), 0)
            ");
            $stmt->execute([$nouveauNumero]);

            // Nettoyer les bateaux actifs des jours précédents
            $stmt = $pdo->prepare("
                DELETE FROM bateaux_zone_rouge_actifs WHERE numero_jour < ?
            ");
            $stmt->execute([$nouveauNumero]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
?>
