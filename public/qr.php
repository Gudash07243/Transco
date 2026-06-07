<?php
// Proxy simple vers une API externe de génération de QR (pas de dépendances vendor)
// Usage: public/qr.php?code=...  -> renvoie image/png

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    http_response_code(400);
    echo 'Missing code parameter';
    exit;
}

// Construire l'URL de l'API (ici api.qrserver.com, sans clé)
// Construire l'URL de l'API
$apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($code);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_USERAGENT, 'Transco-QR-Proxy/1.0');

$img = curl_exec($ch);
$err = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($img === false || $http !== 200) {
    error_log('QR API proxy error: http=' . $http . ' err=' . $err);
    http_response_code(502);
    echo 'QR API error';
    exit;
}

// Préparer le nom de fichier si demandé
$requestedFilename = trim((string)($_GET['filename'] ?? ''));
if ($requestedFilename !== '') {
    // Nettoyage basique : garder lettres, chiffres, espaces, -, _, .
    $clean = preg_replace('/[^\p{L}\p{N} _\-\.]/u', '', $requestedFilename);
    $clean = trim($clean);
    // Déterminer extension à partir du content-type
    $ext = 'png';
    if (stripos($ctype, 'svg') !== false) { $ext = 'svg'; }
    if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $clean)) {
        $clean .= '.' . $ext;
    }
    // Empêcher chemins
    $clean = basename($clean);
    // En-tête d'attachement avec encodage UTF-8
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($clean));
}

header('Content-Type: ' . ($ctype ?: 'image/png'));
// Eviter caching si besoin
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $img;
