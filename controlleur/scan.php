<?php
// ============================================================
//  controlleur/scan.php — Interface de validation des billets
// ============================================================

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auth_check.php';

requireRole('CONTROLEUR');

$pdo    = getPDO();
$result = null;

// ── Traitement de la validation ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_qr = trim($_POST['code_qr'] ?? '');

    if (empty($code_qr)) {
        $result = ['type' => 'error', 'msg' => 'Veuillez saisir ou scanner un code QR.'];
    } else {
        // Rechercher le billet
        $stmt = $pdo->prepare("SELECT bi.id_billet, bi.numero_siege, bi.statut_billet,
                   v.date_depart, v.heure_date,
                   l.ville_depart, l.ville_destination,
                   cl.nom, cl.postnom
            FROM BILLET bi
            JOIN VOYAGE   v  ON v.id_voyage   = bi.id_voyage
            JOIN LIGNE    l  ON l.id_ligne    = v.id_ligne
            JOIN COMMANDE c  ON c.id_commande = bi.id_commande
            JOIN CLIENT   cl ON cl.id_client  = c.id_client
            WHERE bi.code_qr = ?");
        $stmt->execute([$code_qr]);
        $billet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$billet) {
            $result = ['type' => 'error', 'msg' => '❌ Code QR invalide. Billet introuvable.'];
        } elseif ($billet['statut_billet'] === 'UTILISE') {
            $result = ['type' => 'warning', 'msg' => '⚠ Ce billet a déjà été utilisé.', 'billet' => $billet];
        } elseif ($billet['statut_billet'] === 'ANNULE') {
            $result = ['type' => 'error', 'msg' => '❌ Ce billet est annulé.', 'billet' => $billet];
        } else {
            // Marquer comme UTILISÉ
            $upd = $pdo->prepare("UPDATE BILLET SET statut_billet = 'UTILISE' WHERE id_billet = ?");
            $upd->execute([$billet['id_billet']]);
            $billet['statut_billet'] = 'UTILISE';
            $result = ['type' => 'success', 'msg' => '✅ Billet validé avec succès !', 'billet' => $billet];
        }
    }
}

// ── Statistiques de la journée ───────────────────────────
$stats_jour = $pdo->query("SELECT
        SUM(statut_billet = 'UTILISE') as utilises,
        SUM(statut_billet = 'VALIDE')  as valides,
        SUM(statut_billet = 'ANNULE')  as annules
        FROM BILLET
        WHERE DATE(NOW()) = CURDATE()")->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Scanner — Contrôleur</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Camera reader square */
        #reader { width:180px; height:180px; max-width:100%; margin:0 auto 1rem; border-radius:6px; overflow:hidden; }
        #reader video, #reader canvas { width:100% !important; height:100% !important; object-fit:cover !important; display:block !important; }
    </style>
</head>
<body>

<div class="wrapper">

    <aside class="sidebar">
        <?php
            $logo_new = __DIR__ . '/../assets/css/img/Capture d’écran 2026-06-07 175009.png';
            $logo_old = __DIR__ . '/../assets/img/transco_logo.png';
            if (file_exists($logo_new)):
        ?>
            <div class="sidebar-logo"><img src="../assets/css/img/Capture d’écran 2026-06-07 175009.png" alt="Transco" class="logo-img"></div>
        <?php elseif (file_exists($logo_old)): ?>
            <div class="sidebar-logo"><img src="../assets/img/transco_logo.png" alt="Transco" class="logo-img"></div>
        <?php else: ?>
            <div class="sidebar-logo">🚌 Tran<span class="brand-sco">SCO</span></div>
        <?php endif; ?>
        <div class="sidebar-user">
            <strong><?= htmlspecialchars($_SESSION['email'] ?? '') ?></strong>
            <span class="badge badge-controleur">Contrôleur</span>
        </div>
        <nav>
            <a href="scan.php" class="active"><span class="nav-icon">📷</span> Scanner</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../public/logout.php">⬅ Déconnexion</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Scanner</h1>
            <span class="badge badge-controleur">Contrôleur</span>
        </div>

        <div class="card">
            <div class="card-body">
                <div style="max-width:480px;margin:0 auto">
                    <p style="color:var(--c-muted);margin-bottom:.75rem">Scannez le QR du billet ou saisissez le code manuellement</p>
                    <div style="text-align:center; margin-bottom:.5rem">
                        <span class="scan-icon" style="font-size:3rem;display:inline-block">📷</span>
                    </div>
                    <div id="reader" style="width:180px;max-width:100%;height:180px;margin:0 auto 1rem;border:1px solid var(--c-border);border-radius:6px"></div>

                    <div style="text-align:center; margin:0.5rem 0">
                        <button id="btnStartCamera" type="button" class="btn btn-secondary btn-sm" style="margin-right:.5rem">Démarrer la caméra</button>
                        <button id="btnStopCamera" type="button" class="btn btn-secondary btn-sm" disabled>Arrêter la caméra</button>
                    </div>

                    <form method="POST" action="">
                        <input type="text" name="code_qr" class="scan-input" placeholder="CODE QR DU BILLET" autocomplete="off" value="<?= htmlspecialchars($_POST['code_qr'] ?? '') ?>">
                        <div style="margin-top:.6rem; text-align:center"><button class="btn btn-primary" type="submit">Valider le billet ✓</button></div>
                    </form>

                    <div id="scanStatus" style="font-size:.85rem;color:var(--c-muted);margin-top:.5rem">Statut: prêt</div>

                    <?php if (!empty($result)): ?>
                        <div class="result-box result-<?= $result['type'] ?>">
                            <div style="font-weight:700;margin-bottom:.5rem"><?= $result['msg'] ?></div>
                            <?php if (!empty($result['billet'])): $b = $result['billet']; ?>
                                <div style="font-size:.9rem">
                                    <div><strong>Passager:</strong> <?= htmlspecialchars($b['nom'] . ' ' . $b['postnom']) ?></div>
                                    <div><strong>Trajet:</strong> <?= htmlspecialchars($b['ville_depart'] . ' → ' . $b['ville_destination']) ?></div>
                                    <div><strong>Date / Heure:</strong> <?= date('d/m/Y', strtotime($b['date_depart'])) ?> à <?= substr($b['heure_date'],0,5) ?></div>
                                    <div><strong>Siège:</strong> #<?= $b['numero_siege'] ?></div>
                                    <div><strong>Statut:</strong> <?= $b['statut_billet'] ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </main>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script src="../assets/js/sidebar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const readerId = 'reader';
    const input = document.querySelector('input[name="code_qr"]');
    const form = input ? input.closest('form') : null;
    const statusEl = document.getElementById('scanStatus');
    const btnStart = document.getElementById('btnStartCamera');
    const btnStop  = document.getElementById('btnStopCamera');

    if (typeof Html5Qrcode === 'undefined') {
        if (statusEl) statusEl.textContent = 'Erreur: bibliothèque html5-qrcode non disponible.';
        if (btnStart) btnStart.disabled = true;
        return;
    }

    const html5QrCode = new Html5Qrcode(readerId);
    let isScanning = false;
    let selectedCameraId = null;

    function onScanSuccess(decodedText) {
        if (statusEl) statusEl.textContent = 'QR scanné';
        if (input) input.value = decodedText;
        stopCamera().finally(()=>{
            if (form) form.submit();
        });
    }

    function onScanFailure(error) {
        // ignore frequent scan failures
    }

    function choosePreferredCamera(cameras) {
        if (!cameras || cameras.length === 0) return null;
        let camera = cameras[0];
        for (const c of cameras) {
            const label = (c.label || '').toLowerCase();
            if (label.includes('back') || label.includes('rear') || label.includes('environment')) {
                camera = c; break;
            }
        }
        return camera;
    }

    function startCamera() {
        if (isScanning) return Promise.resolve();
        if (!Html5Qrcode.getCameras) {
            if (statusEl) statusEl.textContent = 'API caméras non supportée par ce navigateur.';
            return Promise.reject(new Error('getCameras not supported'));
        }

        if (btnStart) btnStart.disabled = true;
        if (statusEl) statusEl.textContent = 'Recherche de caméras...';

        return Html5Qrcode.getCameras().then(cameras => {
            if (!cameras || cameras.length === 0) {
                if (statusEl) statusEl.textContent = 'Aucune caméra détectée.';
                if (btnStart) btnStart.disabled = false;
                return Promise.reject(new Error('no cameras'));
            }

            const cam = choosePreferredCamera(cameras);
            selectedCameraId = cam.id;

            return html5QrCode.start({ deviceId: { exact: selectedCameraId } }, { fps: 10, qrbox: 180 }, onScanSuccess, onScanFailure)
                .then(() => {
                    isScanning = true;
                    if (statusEl) statusEl.textContent = 'Prêt: montrez le QR devant la caméra.';
                    if (btnStart) btnStart.disabled = true;
                    if (btnStop) btnStop.disabled = false;
                })
                .catch(err => {
                    console.error('Impossible de démarrer la caméra:', err);
                    if (err && err.name === 'NotAllowedError') {
                        if (statusEl) statusEl.textContent = 'Accès caméra refusé. Autorisez la caméra dans votre navigateur.';
                    } else {
                        if (statusEl) statusEl.textContent = 'Erreur lors de l\'accès à la caméra: ' + (err.message || err);
                    }
                    if (btnStart) btnStart.disabled = false;
                    return Promise.reject(err);
                });
        }).catch(err => {
            console.error('getCameras error', err);
            if (statusEl) statusEl.textContent = 'Impossible de lister les caméras. Vérifiez les permissions du site.';
            if (btnStart) btnStart.disabled = false;
            return Promise.reject(err);
        });
    }

    function stopCamera() {
        if (!isScanning) return Promise.resolve();
        return html5QrCode.stop().then(() => {
            isScanning = false;
            if (statusEl) statusEl.textContent = 'Caméra arrêtée.';
            if (btnStart) btnStart.disabled = false;
            if (btnStop) btnStop.disabled = true;
        }).catch(err => {
            console.error('Erreur arrêt caméra', err);
            if (statusEl) statusEl.textContent = 'Erreur lors de l\'arrêt de la caméra.';
            return Promise.reject(err);
        });
    }

    // Wire buttons
    if (btnStart) btnStart.addEventListener('click', function(){ startCamera().catch(()=>{}); });
    if (btnStop)  btnStop.addEventListener('click', function(){ stopCamera().catch(()=>{}); });

    // Auto-start on page load (try, but allow manual control)
    startCamera().catch(()=>{});

});
</script>
</body>
</html>
