<?php
// ============================================================
//  admin/gestion_voyages.php — Planification des voyages
// ============================================================

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/auth_check.php';

requireRole('ADMIN');

$pdo     = getPDO();
$error   = '';
$success = '';
$tab     = $_GET['tab'] ?? 'voyages'; // voyages | bus | lignes

// ── Traitement des formulaires ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- Ajouter un bus
    if ($action === 'ajouter_bus') {
        $plaque   = trim($_POST['plaque_bus'] ?? '');
        $capacite = (int)($_POST['capacite'] ?? 0);
        if (empty($plaque) || $capacite <= 0) {
            $error = 'Plaque et capacité valide obligatoires.';
        } else {
            try {
                $pdo->prepare('INSERT INTO BUS (plaque_bus, capacite) VALUES (?, ?)')->execute([$plaque, $capacite]);
                $success = 'Bus ajouté.';
            } catch (PDOException $e) {
                $error = 'Erreur : plaque déjà existante ?';
            }
        }
        $tab = 'bus';
    }

    // -- Supprimer un bus
    if ($action === 'supprimer_bus') {
        $pdo->prepare('DELETE FROM BUS WHERE id_bus = ?')->execute([(int)$_POST['id_bus']]);
        $success = 'Bus supprimé.';
        $tab = 'bus';
    }

    // -- Ajouter une ligne
    if ($action === 'ajouter_ligne') {
        $dep  = trim($_POST['ville_depart'] ?? '');
        $dest = trim($_POST['ville_destination'] ?? '');
        if (empty($dep) || empty($dest)) {
            $error = 'Villes obligatoires.';
        } else {
            $pdo->prepare('INSERT INTO LIGNE (ville_depart, ville_destination) VALUES (?, ?)')->execute([$dep, $dest]);
            $success = 'Ligne ajoutée.';
        }
        $tab = 'lignes';
    }

    // -- Supprimer une ligne
    if ($action === 'supprimer_ligne') {
        $pdo->prepare('DELETE FROM LIGNE WHERE id_ligne = ?')->execute([(int)$_POST['id_ligne']]);
        $success = 'Ligne supprimée.';
        $tab = 'lignes';
    }

    // -- Planifier un voyage
    if ($action === 'ajouter_voyage') {
        $date   = $_POST['date_depart']  ?? '';
        $heure  = $_POST['heure_date']   ?? '';
        $prix   = (float)($_POST['prix_billet']   ?? 0);
        $places = (int)($_POST['places'] ?? 0);
        $id_bus = (int)($_POST['id_bus'] ?? 0);
        $id_ligne = (int)($_POST['id_ligne'] ?? 0);

        if (empty($date) || empty($heure) || $prix <= 0 || $places <= 0 || !$id_bus || !$id_ligne) {
            $error = 'Tous les champs du voyage sont obligatoires.';
        } else {
            $pdo->prepare('INSERT INTO VOYAGE (date_depart, heure_date, prix_billet, places_disponibles, id_bus, id_ligne)
                           VALUES (?, ?, ?, ?, ?, ?)')->execute([$date, $heure, $prix, $places, $id_bus, $id_ligne]);
            $success = 'Voyage planifié.';
        }
        $tab = 'voyages';
    }

    // -- Supprimer un voyage
    if ($action === 'supprimer_voyage') {
        $pdo->prepare('DELETE FROM VOYAGE WHERE id_voyage = ?')->execute([(int)$_POST['id_voyage']]);
        $success = 'Voyage supprimé.';
        $tab = 'voyages';
    }
}

// ── Récupération des données ─────────────────────────────
$bus = $pdo->query('SELECT * FROM BUS ORDER BY id_bus DESC')->fetchAll();

$lignes = $pdo->query('SELECT * FROM LIGNE ORDER BY id_ligne DESC')->fetchAll();

$voyages = $pdo->query("
    SELECT v.*, l.ville_depart, l.ville_destination, b.plaque_bus
    FROM VOYAGE v
    JOIN LIGNE l ON l.id_ligne = v.id_ligne
    JOIN BUS   b ON b.id_bus   = v.id_bus
    ORDER BY v.date_depart DESC, v.heure_date DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Voyages — Tranco</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tabs { display:flex; gap:.5rem; margin-bottom:1.5rem; }
        .tab-btn {
            padding:.55rem 1.2rem; border-radius:8px; border:1px solid var(--c-border);
            background:var(--c-surface); color:var(--c-muted); cursor:pointer;
            font-family:var(--font-body); font-size:.875rem; font-weight:600;
            transition:all .2s;
        }
        .tab-btn.active, .tab-btn:hover {
            background:linear-gradient(135deg,var(--c-accent),var(--c-accent2));
            color:#fff; border-color:transparent;
        }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
    </style>
</head>
<body>
<div class="wrapper">

    <aside class="sidebar">
        <div class="sidebar-logo">🚌 Tran<span>co</span></div>
        <div class="sidebar-user">
            <strong><?= htmlspecialchars($_SESSION['email']) ?></strong>
            <span class="badge badge-admin">Admin</span>
        </div>
        <nav>
            <a href="admin_dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="gestion_personnel.php"><span class="nav-icon">👥</span> Personnel</a>
            <a href="gestion_voyages.php" class="active"><span class="nav-icon">🗺️</span> Voyages</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../public/logout.php">⬅ Déconnexion</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Planification des Voyages</h1>
            <span class="badge badge-admin">Administrateur</span>
        </div>

        <?php if ($error):   ?><div class="alert alert-error fade-in">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success fade-in">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

        <!-- Onglets -->
        <div class="tabs">
            <button class="tab-btn <?= $tab==='voyages'?'active':'' ?>" onclick="showTab('voyages')">🗺️ Voyages</button>
            <button class="tab-btn <?= $tab==='bus'?'active':'' ?>"     onclick="showTab('bus')">🚌 Bus</button>
            <button class="tab-btn <?= $tab==='lignes'?'active':'' ?>"  onclick="showTab('lignes')">📍 Lignes</button>
        </div>

        <!-- ── Onglet Voyages ── -->
        <div id="tab-voyages" class="tab-content <?= $tab==='voyages'?'active':'' ?>">

            <div class="card fade-in" style="max-width:620px; margin-bottom:2rem">
                <div class="card-header"><h3>➕ Planifier un voyage</h3></div>
                <div class="card-body">
                    <form method="POST" action="?tab=voyages">
                        <input type="hidden" name="action" value="ajouter_voyage">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Ligne</label>
                                <select name="id_ligne" required>
                                    <option value="">-- Choisir --</option>
                                    <?php foreach ($lignes as $l): ?>
                                        <option value="<?= $l['id_ligne'] ?>">
                                            <?= htmlspecialchars($l['ville_depart'].' → '.$l['ville_destination']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Bus</label>
                                <select name="id_bus" required>
                                    <option value="">-- Choisir --</option>
                                    <?php foreach ($bus as $b): ?>
                                        <option value="<?= $b['id_bus'] ?>">
                                            <?= htmlspecialchars($b['plaque_bus']) ?> (<?= $b['capacite'] ?> places)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date de départ</label>
                                <input type="date" name="date_depart" required>
                            </div>
                            <div class="form-group">
                                <label>Heure</label>
                                <input type="time" name="heure_date" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Prix billet (FC)</label>
                                <input type="number" name="prix_billet" min="1" step="0.01" placeholder="150.00" required>
                            </div>
                            <div class="form-group">
                                <label>Places disponibles</label>
                                <input type="number" name="places" min="1" placeholder="50" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Planifier le voyage</button>
                    </form>
                </div>
            </div>

            <div class="card fade-in">
                <div class="card-header"><h3>📋 Liste des voyages (<?= count($voyages) ?>)</h3></div>
                <?php if (empty($voyages)): ?>
                    <div class="card-body"><p style="color:var(--c-muted)">Aucun voyage planifié.</p></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>#</th><th>Trajet</th><th>Date</th><th>Heure</th><th>Bus</th><th>Places</th><th>Prix</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($voyages as $v): ?>
                        <tr>
                            <td><?= $v['id_voyage'] ?></td>
                            <td><?= htmlspecialchars($v['ville_depart'].' → '.$v['ville_destination']) ?></td>
                            <td><?= date('d/m/Y', strtotime($v['date_depart'])) ?></td>
                            <td><?= substr($v['heure_date'],0,5) ?></td>
                            <td><?= htmlspecialchars($v['plaque_bus']) ?></td>
                            <td><?= $v['places_disponibles'] ?></td>
                            <td><?= number_format($v['prix_billet'],2) ?> FC</td>
                            <td>
                                <form method="POST" action="?tab=voyages" onsubmit="return confirm('Supprimer ?')">
                                    <input type="hidden" name="action"    value="supprimer_voyage">
                                    <input type="hidden" name="id_voyage" value="<?= $v['id_voyage'] ?>">
                                    <button class="btn btn-danger btn-sm">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Onglet Bus ── -->
        <div id="tab-bus" class="tab-content <?= $tab==='bus'?'active':'' ?>">
            <div class="card fade-in" style="max-width:420px; margin-bottom:2rem">
                <div class="card-header"><h3>➕ Ajouter un bus</h3></div>
                <div class="card-body">
                    <form method="POST" action="?tab=bus">
                        <input type="hidden" name="action" value="ajouter_bus">
                        <div class="form-group">
                            <label>Plaque d'immatriculation</label>
                            <input type="text" name="plaque_bus" placeholder="KIN-001-A" required>
                        </div>
                        <div class="form-group">
                            <label>Capacité (places)</label>
                            <input type="number" name="capacite" min="1" placeholder="50" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Ajouter le bus</button>
                    </form>
                </div>
            </div>
            <div class="card fade-in">
                <div class="card-header"><h3>🚌 Flotte (<?= count($bus) ?> bus)</h3></div>
                <table>
                    <thead><tr><th>#</th><th>Plaque</th><th>Capacité</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($bus as $b): ?>
                        <tr>
                            <td><?= $b['id_bus'] ?></td>
                            <td><?= htmlspecialchars($b['plaque_bus']) ?></td>
                            <td><?= $b['capacite'] ?> places</td>
                            <td>
                                <form method="POST" action="?tab=bus" onsubmit="return confirm('Supprimer ?')">
                                    <input type="hidden" name="action" value="supprimer_bus">
                                    <input type="hidden" name="id_bus" value="<?= $b['id_bus'] ?>">
                                    <button class="btn btn-danger btn-sm">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Onglet Lignes ── -->
        <div id="tab-lignes" class="tab-content <?= $tab==='lignes'?'active':'' ?>">
            <div class="card fade-in" style="max-width:420px; margin-bottom:2rem">
                <div class="card-header"><h3>➕ Ajouter une ligne</h3></div>
                <div class="card-body">
                    <form method="POST" action="?tab=lignes">
                        <input type="hidden" name="action" value="ajouter_ligne">
                        <div class="form-group">
                            <label>Ville de départ</label>
                            <input type="text" name="ville_depart" placeholder="Kinshasa" required>
                        </div>
                        <div class="form-group">
                            <label>Ville de destination</label>
                            <input type="text" name="ville_destination" placeholder="Lubumbashi" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Ajouter la ligne</button>
                    </form>
                </div>
            </div>
            <div class="card fade-in">
                <div class="card-header"><h3>📍 Lignes (<?= count($lignes) ?>)</h3></div>
                <table>
                    <thead><tr><th>#</th><th>Départ</th><th>Destination</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($lignes as $l): ?>
                        <tr>
                            <td><?= $l['id_ligne'] ?></td>
                            <td><?= htmlspecialchars($l['ville_depart']) ?></td>
                            <td><?= htmlspecialchars($l['ville_destination']) ?></td>
                            <td>
                                <form method="POST" action="?tab=lignes" onsubmit="return confirm('Supprimer ?')">
                                    <input type="hidden" name="action"   value="supprimer_ligne">
                                    <input type="hidden" name="id_ligne" value="<?= $l['id_ligne'] ?>">
                                    <button class="btn btn-danger btn-sm">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>
</body>
</html>
