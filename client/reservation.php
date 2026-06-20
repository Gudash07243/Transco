<?php
// ============================================================
//  client/reservation.php — Achat de billets (famille)
// ============================================================

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auth_check.php';

requireRole('CLIENT');

$pdo = getPDO();

// Récupérer le client
$stmt = $pdo->prepare('SELECT * FROM CLIENT WHERE id_user = ?');
$stmt->execute([$_SESSION['id_user']]);
$client = $stmt->fetch();

if (!$client) {
    die('Erreur : profil client introuvable.');
}

$error   = '';
$success = '';

// ── Traitement de la réservation ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserver') {
    $id_voyage  = (int)($_POST['id_voyage'] ?? 0);
    $nb_billets = (int)($_POST['nb_billets'] ?? 1);
    $nb_billets = max(1, min(10, $nb_billets)); // 1 à 10 billets max

    // Récupérer le voyage
    $stmt = $pdo->prepare("
        SELECT v.*, l.ville_depart, l.ville_destination
        FROM VOYAGE v JOIN LIGNE l ON l.id_ligne = v.id_ligne
        WHERE v.id_voyage = ? AND v.places_disponibles >= ? AND v.date_depart >= CURDATE()
    ");
    $stmt->execute([$id_voyage, $nb_billets]);
    $voyage = $stmt->fetch();

    if (!$voyage) {
        $error = 'Voyage indisponible ou places insuffisantes.';
    } else {
        try {
            $pdo->beginTransaction();

            $montant_total = $voyage['prix_billet'] * $nb_billets;

            // Créer la commande
            $stmt = $pdo->prepare('INSERT INTO COMMANDE (montant_total, id_client) VALUES (?, ?)');
            $stmt->execute([$montant_total, $client['id_client']]);
            $id_commande = $pdo->lastInsertId();

            // Calculer le numéro de siège de départ
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(numero_siege), 0) FROM BILLET WHERE id_voyage = ?");
            $stmt->execute([$id_voyage]);
            $dernier_siege = (int)$stmt->fetchColumn();

            // Créer les billets
            for ($i = 1; $i <= $nb_billets; $i++) {
                $siege  = $dernier_siege + $i;
                $code_qr = strtoupper(bin2hex(random_bytes(12))); // Code QR unique

                $stmt = $pdo->prepare('INSERT INTO BILLET (numero_siege, code_qr, id_commande, id_voyage) VALUES (?, ?, ?, ?)');
                $stmt->execute([$siege, $code_qr, $id_commande, $id_voyage]);
            }

            // Décrémenter les places disponibles
            $stmt = $pdo->prepare('UPDATE VOYAGE SET places_disponibles = places_disponibles - ? WHERE id_voyage = ?');
            $stmt->execute([$nb_billets, $id_voyage]);

            $pdo->commit();
            $success = "Réservation confirmée ! Commande #$id_commande — $nb_billets billet(s) pour "
                     . htmlspecialchars($voyage['ville_depart'] . ' → ' . $voyage['ville_destination']) . ".";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erreur lors de la réservation. Veuillez réessayer.';
            error_log($e->getMessage());
        }
    }
}

// ── Voyage sélectionné (depuis dashboard) ───────────────
$voyage_select = null;
$id_voyage_get = (int)($_GET['id_voyage'] ?? 0);
if ($id_voyage_get > 0) {
    $stmt = $pdo->prepare("
        SELECT v.*, l.ville_depart, l.ville_destination, b.plaque_bus
        FROM VOYAGE v
        JOIN LIGNE l ON l.id_ligne = v.id_ligne
        JOIN BUS   b ON b.id_bus   = v.id_bus
        WHERE v.id_voyage = ? AND v.places_disponibles > 0 AND v.date_depart >= CURDATE()
    ");
    $stmt->execute([$id_voyage_get]);
    $voyage_select = $stmt->fetch();
}

// ── Mes billets ──────────────────────────────────────────
$billets = $pdo->prepare("
    SELECT bi.id_billet, bi.numero_siege, bi.code_qr, bi.statut_billet,
           bi.id_commande,
           v.date_depart, v.heure_date,
           l.ville_depart, l.ville_destination,
           c.date_commande, c.montant_total
    FROM BILLET bi
    JOIN VOYAGE   v  ON v.id_voyage   = bi.id_voyage
    JOIN LIGNE    l  ON l.id_ligne    = v.id_ligne
    JOIN COMMANDE c  ON c.id_commande = bi.id_commande
    WHERE c.id_client = ?
    ORDER BY c.date_commande DESC, bi.id_billet
    LIMIT 30
");
$billets->execute([$client['id_client']]);
$billets = $billets->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Billets — Transco</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .billet-card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: var(--radius);
            padding: 1.1rem 1.3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .75rem;
            transition: transform .2s;
        }
        .billet-card:hover { transform: translateX(3px); }
        .billet-qr {
            font-family: monospace;
            font-size: .7rem;
            color: var(--c-muted);
            background: rgba(255,255,255,.04);
            padding: .25rem .5rem;
            border-radius: 4px;
            letter-spacing: .1em;
        }
        .billet-trajet { font-weight: 700; font-size: 1rem; }
        .billet-meta   { font-size: .8rem; color: var(--c-muted); }
        .siege-badge {
            background: var(--c-accent);
            color: #fff;
            font-family: var(--font-head);
            font-size: 1.1rem;
            font-weight: 800;
            width: 48px; height: 48px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(1,121,236,.18);
        }
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
            <strong><?= htmlspecialchars($client['nom'] . ' ' . $client['postnom']) ?></strong>
            <span class="badge badge-client">Client</span>
        </div>
        <nav>
            <a href="dashboard.php"><span class="nav-icon">🔍</span> Rechercher</a>
            <a href="reservation.php" class="active"><span class="nav-icon">🎫</span> Mes billets</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../public/logout.php">⬅ Déconnexion</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Réservation & Billets</h1>
            <span class="badge badge-client">Client</span>
        </div>

        <?php if ($error):   ?><div class="alert alert-error fade-in">⚠ <?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success fade-in">✓ <?= $success ?></div><?php endif; ?>

        <!-- Formulaire de réservation -->
        <?php if ($voyage_select): ?>
        <div class="card fade-in" style="max-width:540px; margin-bottom:2rem">
            <div class="card-header">
                <h3>🎫 Réserver ce voyage</h3>
            </div>
            <div class="card-body">
                <!-- Résumé du voyage -->
                <div style="background:rgba(240,165,0,.08); border:1px solid rgba(240,165,0,.2);
                            border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.25rem">
                    <div style="font-family:var(--font-head); font-size:1.2rem; font-weight:700">
                        <?= htmlspecialchars($voyage_select['ville_depart']) ?>
                        <span style="color:var(--c-accent)">→</span>
                        <?= htmlspecialchars($voyage_select['ville_destination']) ?>
                    </div>
                    <div style="font-size:.85rem; color:var(--c-muted); margin-top:.4rem">
                        📅 <?= date('d/m/Y', strtotime($voyage_select['date_depart'])) ?>
                        &nbsp;|&nbsp;
                        🕐 <?= substr($voyage_select['heure_date'], 0, 5) ?>
                        &nbsp;|&nbsp;
                        🚌 <?= htmlspecialchars($voyage_select['plaque_bus']) ?>
                        &nbsp;|&nbsp;
                        💺 <?= $voyage_select['places_disponibles'] ?> places restantes
                    </div>
                    <div style="font-size:1.1rem; font-weight:700; color:var(--c-accent); margin-top:.5rem">
                        <?= number_format($voyage_select['prix_billet'], 2) ?> FC / billet
                    </div>
                </div>

                <form id="reservationForm" method="POST" action="">
                    <input type="hidden" name="action"    value="reserver">
                    <input type="hidden" name="id_voyage" value="<?= $voyage_select['id_voyage'] ?>">

                    <div class="form-group">
                        <label for="nb_billets">Nombre de billets (famille) — max 10</label>
                        <input type="number" id="nb_billets" name="nb_billets"
                               min="1" max="<?= min(10, $voyage_select['places_disponibles']) ?>"
                               value="1" id="nbInput"
                               oninput="updateTotal(this.value)">
                    </div>

                    <div style="background:rgba(255,255,255,.04); border-radius:8px; padding:.85rem 1rem;
                                margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center">
                        <span style="color:var(--c-muted)">Montant total estimé :</span>
                        <span id="totalDisplay" style="font-family:var(--font-head); font-size:1.3rem;
                              font-weight:800; color:var(--c-accent)">
                            <?= number_format($voyage_select['prix_billet'], 2) ?> FC
                        </span>
                    </div>

                    <button type="submit" id="reserveBtn" class="btn btn-primary btn-full">
                        Confirmer la réservation ✓
                    </button>
                </form>
            </div>
        </div>

        <!-- Simulated payment modal -->
        <div id="simPayModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); align-items:center; justify-content:center; z-index:9999">
            <div style="background:#fff; border-radius:8px; width:420px; max-width:92%; padding:1.1rem; box-shadow:0 8px 24px rgba(0,0,0,.24)">
                <h3 style="margin:0 0 .5rem">Simulation de paiement</h3>
                <p style="color:#555; margin:.25rem 0 1rem">Ceci est une simulation. Aucune carte n'est débitée. Confirmez pour continuer la réservation.</p>

                <div style="display:flex; gap:.5rem; margin-bottom:1rem">
                    <div style="flex:1">
                        <label style="font-size:.85rem; color:#444">Moyen de paiement</label>
                        <select id="simMethod" style="width:100%; padding:.5rem; border-radius:6px; border:1px solid #ddd">
                            <option>Carte (simulation)</option>
                            <option>Mobile Money (simulation)</option>
                            <option>Paiement en agence (simulation)</option>
                        </select>
                    </div>
                    <div style="width:120px; text-align:right">
                        <label style="font-size:.85rem; color:#444">Montant</label>
                        <div id="simAmount" style="font-weight:800; margin-top:.25rem;"><?= number_format($voyage_select['prix_billet'], 2) ?> FC</div>
                    </div>
                </div>

                <div style="display:flex; gap:.5rem; justify-content:flex-end">
                    <button id="simCancel" class="btn btn-ghost">Annuler</button>
                    <button id="simConfirm" class="btn btn-primary">Payer (simulation)</button>
                </div>
            </div>
        </div>

        <script>
        const prix = <?= (float)$voyage_select['prix_billet'] ?>;
        function updateTotal(n) {
            n = Math.max(1, parseInt(n) || 1);
            document.getElementById('totalDisplay').textContent =
                (prix * n).toLocaleString('fr-FR', {minimumFractionDigits:2}) + ' FC';
        }
        </script>
        <script>
        // Intercept form submit to simulate payment first
        (function(){
            const form = document.getElementById('reservationForm');
            const modal = document.getElementById('simPayModal');
            const cancel = document.getElementById('simCancel');
            const confirm = document.getElementById('simConfirm');
            const amountEl = document.getElementById('simAmount');

            if (!form) return;
            form.addEventListener('submit', function(e){
                e.preventDefault();
                // update amount display based on input
                const n = parseInt(document.getElementById('nb_billets').value) || 1;
                amountEl.textContent = (prix * n).toLocaleString('fr-FR', {minimumFractionDigits:2}) + ' FC';
                modal.style.display = 'flex';
            });

            cancel.addEventListener('click', function(){ modal.style.display = 'none'; });

            confirm.addEventListener('click', function(){
                // small simulated delay
                confirm.disabled = true;
                confirm.textContent = 'Traitement…';
                setTimeout(function(){
                    // Submit the original form to perform reservation (server-side)
                    modal.style.display = 'none';
                    form.submit();
                }, 800);
            });
        })();
        </script>
        <?php else: ?>
        <div class="alert alert-info fade-in">
            ℹ <a href="dashboard.php">Recherchez un voyage</a> pour effectuer une réservation.
        </div>
        <?php endif; ?>

        <!-- Liste des billets -->
        <h2 style="margin-bottom:1rem; font-size:1.25rem">Mes billets (<?= count($billets) ?>)</h2>

        <?php if (empty($billets)): ?>
            <p style="color:var(--c-muted)">Aucun billet pour le moment.</p>
        <?php else: ?>
            <?php foreach ($billets as $b): ?>
            <div class="billet-card fade-in">
                <div class="siege-badge"><?= $b['numero_siege'] ?></div>
                <div style="flex:1">
                    <div class="billet-trajet">
                        <?= htmlspecialchars($b['ville_depart']) ?>
                        → <?= htmlspecialchars($b['ville_destination']) ?>
                    </div>
                    <div class="billet-meta">
                        📅 <?= date('d/m/Y', strtotime($b['date_depart'])) ?>
                        &nbsp;|&nbsp; 🕐 <?= substr($b['heure_date'],0,5) ?>
                        &nbsp;|&nbsp; Commande #<?= $b['id_commande'] ?>
                    </div>
                    <div class="billet-qr">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=88x88&data=<?= urlencode($b['code_qr']) ?>" 
                             alt="QR code" style="width:88px;height:88px;display:block;border-radius:6px">
                        <div style="display:flex; gap:.5rem; align-items:center; margin-top:.35rem;">
                            <div style="font-size:.72rem; color:var(--c-muted)">Code: <?= htmlspecialchars($b['code_qr']) ?></div>
                            <?php
                                $clientName = trim(($client['nom'] ?? '') . ' ' . ($client['postnom'] ?? '')) ?: 'client';
                                $downloadName = $clientName . '_billet_' . $b['code_qr'] . '.svg';
                                $downloadUrl = '../public/ticket.php?code=' . urlencode($b['code_qr']) . '&filename=' . urlencode($downloadName);
                            ?>
                            <a class="btn btn-secondary btn-sm" href="<?= $downloadUrl ?>">Télécharger</a>
                        </div>
                    </div>
                </div>
                <div>
                    <span class="statut statut-<?= strtolower($b['statut_billet']) ?>">
                        <?= $b['statut_billet'] ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div>
<script src="../assets/js/sidebar.js"></script>
</body>
</html>
