<?php
$pageTitle = "Accueil Livreur";
include __DIR__ . '/header.php';

$message      = '';
$message_type = '';

if (isset($_GET['success']) && $_GET['success'] == 'livree') {
    $message      = "Livraison marquée comme terminée avec succès !";
    $message_type = 'success';
}
if (isset($_GET['error'])) {
    $msgs = [
        'missing_id'   => "Erreur : ID de livraison manquant.",
        'unauthorized' => "Erreur : Vous n'êtes pas autorisé à modifier cette livraison.",
        'db_error'     => "Erreur de base de données. Veuillez réessayer.",
    ];
    $message      = $msgs[$_GET['error']] ?? "Une erreur est survenue.";
    $message_type = 'error';
}

$a_livrer    = 0;
$livrees_mois = 0;
$commandes   = [];

try {
    if (isset($conn)) {
        $stmt1 = $conn->prepare("
            SELECT COUNT(*) as total FROM livraison
            WHERE LOWER(STATUT_LIVRAISON) IN ('en_attente','en attente')
            AND ID_LIVREUR = :id
        ");
        $stmt1->execute(['id' => $_SESSION['livreur_id']]);
        $a_livrer = $stmt1->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt2 = $conn->prepare("
            SELECT COUNT(*) as total FROM livraison
            WHERE MONTH(DATE_LIVRAISON) = MONTH(CURDATE())
            AND YEAR(DATE_LIVRAISON) = YEAR(CURDATE())
            AND LOWER(STATUT_LIVRAISON) IN ('livree','livré','livrée')
            AND ID_LIVREUR = :id
        ");
        $stmt2->execute(['id' => $_SESSION['livreur_id']]);
        $livrees_mois = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt3 = $conn->prepare("
            SELECT l.ID_LIVRAISON, l.STATUT_LIVRAISON,
                   c.ID_COMMANDE AS NUM_COMMANDE,
                   c.MONTANT_TOTAL, c.ADRESSE_LIVRAISON, c.TEL_LIVRAISON,
                   cl.NOM_CLIENT, cl.PRENOM_CLIENT, cl.TEL_CLIENT, cl.ADRESSE_CLIENT
            FROM livraison l
            INNER JOIN commande c  ON l.ID_COMMANDE = c.ID_COMMANDE
            INNER JOIN client cl   ON c.ID_CLIENT   = cl.ID_CLIENT
            WHERE l.ID_LIVREUR = :id
            AND (
                LOWER(l.STATUT_LIVRAISON) IN ('en_attente','en attente')
                OR (
                    LOWER(l.STATUT_LIVRAISON) IN ('livree','livré','livrée')
                    AND DATE(l.DATE_LIVRAISON) = CURDATE()
                )
            )
            ORDER BY
                CASE WHEN LOWER(l.STATUT_LIVRAISON) IN ('en_attente','en attente') THEN 1 ELSE 2 END,
                l.ID_LIVRAISON DESC
        ");
        $stmt3->execute(['id' => $_SESSION['livreur_id']]);
        $commandes = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Erreur SQL accueil: " . $e->getMessage());
}
?>

<!-- Toast inline pour messages page -->
<?php if ($message): ?>
<div id="lv-page-toast" class="lv-page-toast <?= $message_type ?>" style="display:block;">
    <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
    <div class="lv-page-toast-bar"></div>
</div>
<script>
(function(){
    const t = document.getElementById('lv-page-toast');
    if (!t) return;
    const bar = t.querySelector('.lv-page-toast-bar');
    if (bar) { bar.style.transition='width 10s linear'; bar.style.width='0%'; }
    setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(),400); }, 10000);
})();
</script>
<?php endif; ?>

<div class="lv-main">

    <!-- MESSAGE DE BIENVENUE — police Anton (cohérente avec le reste du site) -->
    <div class="lv-welcome">
        Bonjour,&nbsp;<span class="lv-welcome-name"><?= htmlspecialchars($livreur['prenom']) ?></span>
        <span class="lv-welcome-sub">— Espace Livreur</span>
    </div>

    <!-- Statistiques -->
    <div class="lv-stats-grid">
        <div class="lv-stat-card">
            <div class="lv-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="lv-stat-number"><?= $a_livrer ?></div>
            <div class="lv-stat-label">À livrer aujourd'hui</div>
        </div>
        <div class="lv-stat-card">
            <div class="lv-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="lv-stat-number"><?= $livrees_mois ?></div>
            <div class="lv-stat-label">Livrées ce mois</div>
        </div>
    </div>

    <!-- Livraisons du jour -->
    <div class="lv-deliveries-section">
        <div class="lv-section-header">
            <h2>
                <i class="fas fa-calendar-day"></i>
                Livraisons du jour — <?= date('d/m/Y') ?>
            </h2>
        </div>

        <div class="lv-table-container">
            <table>
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Adresse</th>
                        <th>Téléphone</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($commandes)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;">
                                <i class="fas fa-box-open" style="font-size:40px;color:#ccc;display:block;margin-bottom:10px;"></i>
                                Aucune livraison pour aujourd'hui
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($commandes as $cmd):
                            $st         = mb_strtolower(trim($cmd['STATUT_LIVRAISON'] ?? ''));
                            $en_attente = in_array($st, ['en_attente','en attente']);
                        ?>
                        <tr id="row-<?= $cmd['ID_LIVRAISON'] ?>">
                            <td>
                                <span class="lv-cmd-badge">
                                    # <?= $cmd['NUM_COMMANDE'] ?? $cmd['ID_LIVRAISON'] ?? '—' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(($cmd['PRENOM_CLIENT'] ?? '') . ' ' . ($cmd['NOM_CLIENT'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($cmd['ADRESSE_LIVRAISON'] ?? $cmd['ADRESSE_CLIENT'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($cmd['TEL_LIVRAISON'] ?? $cmd['TEL_CLIENT'] ?? '—') ?></td>
                            <td><?= number_format($cmd['MONTANT_TOTAL'] ?? 0, 2) ?> DH</td>
                            <td>
                                <span class="lv-status-badge <?= $en_attente ? 'a-livrer' : 'livree' ?>">
                                    <?= $en_attente ? 'À livrer' : 'Livrée' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($en_attente): ?>
                                    <button class="lv-action-btn livrer"
                                            onclick="confirmerLivraison(<?= $cmd['ID_LIVRAISON'] ?>, this)">
                                        <i class="fas fa-check"></i> Livrer
                                    </button>
                                <?php else: ?>
                                    <span class="lv-status-badge livree">Terminée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* ── Welcome fix: même police Anton ── */
.lv-welcome {
    font-family: 'Anton', sans-serif;
    font-size: clamp(1.6rem, 3vw, 2.2rem);
    color: #111;
    margin-bottom: 28px;
    padding: 0 4px;
    letter-spacing: 0.5px;
}
.lv-welcome-name {
    color: #000;
    text-decoration: underline;
    text-decoration-thickness: 2px;
    text-underline-offset: 4px;
}
.lv-welcome-sub {
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    font-weight: 400;
    color: #999;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* ── Page toast ── */
.lv-page-toast {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 22px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
    opacity: 1;
    transition: opacity .4s;
}
.lv-page-toast.success { background:#e8f5e9; color:#2e7d32; border-left: 4px solid #27ae60; }
.lv-page-toast.error   { background:#fce4ec; color:#c62828; border-left: 4px solid #e74c3c; }
.lv-page-toast i { font-size: 18px; }
.lv-page-toast-bar {
    position: absolute; bottom: 0; left: 0;
    height: 3px;
    background: currentColor;
    opacity: 0.3;
    width: 100%;
}

/* ── Action button inline ── */
.lv-action-btn.livrer {
    background: #000;
    color: #fff;
    border: none;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: background .2s, transform .15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.lv-action-btn.livrer:hover { background: #333; transform: translateY(-1px); }
.lv-action-btn.livrer:disabled { opacity: .5; cursor: not-allowed; transform: none; }

/* ── Inline confirmation ── */
.lv-confirm-wrap {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff3cd;
    border: 1.5px solid #ffc107;
    border-radius: 10px;
    padding: 6px 12px;
    font-size: 12px;
    color: #856404;
}
.lv-confirm-yes {
    background: #27ae60; color: #fff;
    border: none; border-radius: 6px;
    padding: 4px 12px; cursor: pointer; font-size: 12px; font-weight: 600;
}
.lv-confirm-no {
    background: #eee; color: #555;
    border: none; border-radius: 6px;
    padding: 4px 12px; cursor: pointer; font-size: 12px;
}

/* ── Status badges ── */
.lv-status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:0.5px; }
.lv-status-badge.a-livrer { background:#fff3cd; color:#856404; }
.lv-status-badge.livree   { background:#d4edda; color:#155724; }
</style>

<script>
/* Livrer sans confirm() natif — inline UI */
function confirmerLivraison(id, btn) {
    const td = btn.closest('td');
    td.innerHTML = `
      <div class="lv-confirm-wrap">
          <span>Confirmer ?</span>
          <button class="lv-confirm-yes" onclick="executerLivraison(${id}, this)">
              <i class="fas fa-check"></i> Oui
          </button>
          <button class="lv-confirm-no" onclick="annulerConfirm(this, ${id})">Non</button>
      </div>`;
}

function annulerConfirm(btn, id) {
    const td = btn.closest('td');
    td.innerHTML = `
      <button class="lv-action-btn livrer" onclick="confirmerLivraison(${id}, this)">
          <i class="fas fa-check"></i> Livrer
      </button>`;
}

function executerLivraison(id, btn) {
    btn.disabled = true;
    btn.textContent = '…';
    window.location.href = 'livrer_commande.php?id=' + id;
}
</script>

<script src="../JS/script_livreur.js"></script>
</body>
</html>
