<?php
// api/euris-proxy.php
// Remplace /.netlify/functions/euris-proxy pour trackship.bakabi.fr

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

// Récupération du token
$token = null;
$headers = getallheaders();

if (isset($headers['Authorization']) && strpos($headers['Authorization'], 'Bearer ') === 0) {
    $token = substr($headers['Authorization'], 7);
} else {
    http_response_code(401);
    exit(json_encode([
        'error' => 'Authorization header required',
        'message' => 'Le header Authorization avec un token Bearer est requis'
    ]));
}

// Récupération des paramètres
$minLat = $_GET['minLat'] ?? null;
$maxLat = $_GET['maxLat'] ?? null;
$minLon = $_GET['minLon'] ?? null;
$maxLon = $_GET['maxLon'] ?? null;
$pageSize = $_GET['pageSize'] ?? 100;

// Validation des paramètres
if (!$minLat || !$maxLat || !$minLon || !$maxLon) {
    http_response_code(400);
    exit(json_encode([
        'error' => 'Missing required parameters',
        'required' => ['minLat', 'maxLat', 'minLon', 'maxLon']
    ]));
}

// Construction URL EuRIS
$eurisUrl = sprintf(
    'https://www.eurisportal.eu/visuris/api/TracksV2/GetTracksByBBoxV2?minLat=%.6f&maxLat=%.6f&minLon=%.6f&maxLon=%.6f&pageSize=%d',
    floatval($minLat),
    floatval($maxLat),
    floatval($minLon),
    floatval($maxLon),
    intval($pageSize)
);

// Appel à l'API EuRIS
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $eurisUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'User-Agent: TrackShip/1.0 (trackship.bakabi.fr)'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    exit(json_encode([
        'error' => 'Erreur de connexion à l\'API EuRIS',
        'message' => $error
    ]));
}

if ($httpCode !== 200) {
    // Messages d'erreur spécifiques
    $errorMessages = [
        401 => 'Token d\'authentification invalide ou expiré',
        403 => 'Accès interdit - permissions insuffisantes',
        404 => 'Service EuRIS non trouvé',
        429 => 'Trop de requêtes - attendez avant de réessayer',
        500 => 'Service EuRIS temporairement indisponible'
    ];
    
    http_response_code($httpCode);
    exit(json_encode([
        'error' => $errorMessages[$httpCode] ?? 'Erreur API EuRIS',
        'httpStatus' => $httpCode
    ]));
}

// Validation JSON
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    exit(json_encode([
        'error' => 'Réponse invalide de l\'API EuRIS',
        'message' => json_last_error_msg()
    ]));
}

// Normalisation des données (comme dans la fonction Netlify)
$tracks = [];
if (isset($data['tracks']) && is_array($data['tracks'])) {
    $tracks = $data['tracks'];
} elseif (is_array($data)) {
    $tracks = $data;
} elseif (isset($data['data']) && is_array($data['data'])) {
    $tracks = $data['data'];
}

// Normalisation des propriétés
$tracksNormalisees = array_map(function($track) {
    return [
        'latitude' => $track['latitude'] ?? $track['lat'] ?? null,
        'longitude' => $track['longitude'] ?? $track['lon'] ?? null,
        'mmsi' => $track['mmsi'] ?? $track['MMSI'] ?? $track['id'] ?? null,
        'shipName' => $track['shipName'] ?? $track['name'] ?? null,
        'shipType' => $track['shipType'] ?? $track['type'] ?? null,
        'speed' => $track['speed'] ?? $track['sog'] ?? null,
        'course' => $track['course'] ?? $track['cog'] ?? null,
        'length' => $track['length'] ?? null,
        'width' => $track['width'] ?? null,
        'status' => $track['status'] ?? null,
        'timestamp' => $track['timestamp'] ?? null,
        '_original' => $track
    ];
}, $tracks);

// Réponse finale
echo json_encode([
    'tracks' => $tracksNormalisees,
    '_metadata' => [
        'timestamp' => date('c'),
        'source' => 'EuRIS API via trackship.bakabi.fr',
        'trackCount' => count($tracksNormalisees)
    ]
]);
?>