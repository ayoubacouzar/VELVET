<?php
$pageTitle = "Tableau de Bord";
include __DIR__ . '/header.php';

$message = '';
$message_type = '';

if (isset($_GET['success']) && $_GET['success'] === 'livree') {
    $message = "Livraison confirmée avec succès !";
    $message_type = 'success';
}
if (isset($_GET['error'])) {
    $msgs = [
        'missing_id'   => "Erreur : identifiant de livraison manquant.",
        'unauthorized' => "Vous n'êtes pas autorisé à modifier cette livraison.",
        'db_error'     => "Erreur de base de données. Veuillez réessayer.",
    ];
    $message = $msgs[$_GET['error']] ?? "Une erreur est survenue.";
    $message_type = 'error';
}

$a_livrer     = 0;
$livrees_mois = 0;
$commandes    = [];

try {
    $s1 = $conn->prepare("
        SELECT COUNT(*) AS total FROM livraison
        WHERE STATUT_LIVRAISON IN ('en_attente','en attente') AND ID_LIVREUR = :id
    ");
    $s1->execute(['id' => $_SESSION['livreur_id']]);
    $a_livrer = (int)$s1->fetch(PDO::FETCH_ASSOC)['total'];

    $s2 = $conn->prepare("
        SELECT COUNT(*) AS total FROM livraison
        WHERE MONTH(DATE_LIVRAISON)=MONTH(CURDATE()) AND YEAR(DATE_LIVRAISON)=YEAR(CURDATE())
        AND STATUT_LIVRAISON IN ('livree','livré','livrée') AND ID_LIVREUR = :id
    ");
    $s2->execute(['id' => $_SESSION['livreur_id']]);
    $livrees_mois = (int)$s2->fetch(PDO::FETCH_ASSOC)['total'];

    $s3 = $conn->prepare("
        SELECT l.ID_LIVRAISON, l.STATUT_LIVRAISON,
               c.ID_COMMANDE AS NUM_COMMANDE,
               c.MONTANT_TOTAL, c.ADRESSE_LIVRAISON, c.TEL_LIVRAISON,
               cl.NOM_CLIENT, cl.PRENOM_CLIENT, cl.TEL_CLIENT, cl.ADRESSE_CLIENT
        FROM livraison l
        INNER JOIN commande c  ON l.ID_COMMANDE = c.ID_COMMANDE
        INNER JOIN client   cl ON c.ID_CLIENT   = cl.ID_CLIENT
        WHERE l.ID_LIVREUR = :id
          AND (
              l.STATUT_LIVRAISON IN ('en_attente','en attente')
              OR (l.STATUT_LIVRAISON IN ('livree','livré','livrée') AND DATE(l.DATE_LIVRAISON)=CURDATE())
          )
        ORDER BY CASE WHEN l.STATUT_LIVRAISON IN ('en_attente','en attente') THEN 1 ELSE 2 END, l.ID_LIVRAISON DESC
    ");
    $s3->execute(['id' => $_SESSION['livreur_id']]);
    $commandes = $s3->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SQL livreur index: " . $e->getMessage());
}
?>

<div class="lv-main">

    <!-- Message de bienvenue -->
    <div class="lv-welcome">
        Bienvenue, <span class="lv-welcome-name"><?= htmlspecialchars($livreur['prenom']) ?></span>
        !
    </div>

    <!-- Alertes -->
    <?php if ($message): ?>
    <div class="lv-page-alert lv-alert-<?= $message_type ?>">
        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Stats cards -->
    <div class="lv-stats-grid">
        <div class="lv-stat-card">
            <div class="lv-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="lv-stat-number"><?= $a_livrer ?></div>
            <div class="lv-stat-label">À livrer aujourd'hui</div>
        </div>
        <a href="historique.php" class="lv-stat-card-link">
    <div class="lv-stat-card">
        <div class="lv-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="lv-stat-number"><?php echo $livrees_mois; ?></div>
        <div class="lv-stat-label">Livrées ce mois</div>
    </div>
</a>
    </div>

    <!-- Livraisons du jour -->
    <div class="lv-deliveries-card">
        <div class="lv-deliveries-header">
            <h2><i class="fas fa-calendar-day"></i> Livraisons du jour — <?= date('d/m/Y') ?></h2>
        </div>
        <div class="lv-table-container">
            <table style="width:100%;border-collapse:collapse;">
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
                        <td colspan="6" class="lv-empty-cell">
                            <i class="fas fa-box-open"></i>
                            <span>Aucune commande pour aujourd'hui</span>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($commandes as $cmd):
                        $st = $cmd['STATUT_LIVRAISON'] ?? '';
                        $en_attente = in_array(mb_strtolower(trim($st)), ['en_attente','en attente']);
                    ?>
                    <tr>
                        <td>
                            <span class="lv-cmd-badge"># <?= $cmd['NUM_COMMANDE'] ?? $cmd['ID_LIVRAISON'] ?? '—' ?></span>
                        </td>
                        <td><?= htmlspecialchars(($cmd['PRENOM_CLIENT']??'').' '.($cmd['NOM_CLIENT']??'')) ?></td>
                        <td><?= htmlspecialchars($cmd['ADRESSE_LIVRAISON'] ?? $cmd['ADRESSE_CLIENT'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($cmd['TEL_LIVRAISON'] ?? $cmd['TEL_CLIENT'] ?? '—') ?></td>
                        <td><?= number_format($cmd['MONTANT_TOTAL'] ?? 0, 2) ?> DH</td>
                        <td>
                            <span class="lv-status-badge <?= $en_attente ? 'pending' : 'done' ?>">
                                <?= $en_attente ? 'À livrer' : 'Livrée' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($en_attente): ?>
                            <button class="lv-action-btn"
                                    onclick="confirmerLivraisonHist(<?= $cmd['ID_LIVRAISON'] ?>, this)">
                                <i class="fas fa-check"></i> Livrer
                            </button>
                            <?php else: ?>
                            <span class="lv-done-label">
                                <i class="fas fa-check-double"></i> Terminée
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /lv-main -->

<script>
function confirmerLivraisonHist(id, btn) {
    const td = btn.closest('td');
    td.innerHTML = `
      <div style="display:inline-flex;align-items:center;gap:8px;background:#fff3cd;border:1.5px solid #ffc107;border-radius:10px;padding:6px 12px;font-size:12px;color:#856404;">
          <span>Confirmer ?</span>
          <button onclick="window.location.href='livrer_commande.php?id=${id}'"
                  style="background:#27ae60;color:#fff;border:none;border-radius:6px;padding:4px 12px;cursor:pointer;font-size:12px;font-weight:600;">
              <i class="fas fa-check"></i> Oui
          </button>
          <button onclick="annulerLivraisonHist(this, ${id})"
                  style="background:#eee;color:#555;border:none;border-radius:6px;padding:4px 12px;cursor:pointer;font-size:12px;">
              Non
          </button>
      </div>`;
}
function annulerLivraisonHist(btn, id) {
    const td = btn.closest('td');
    td.innerHTML = `<button class="lv-action-btn" onclick="confirmerLivraisonHist(${id}, this)"><i class="fas fa-check"></i> Livrer</button>`;
}
</script>
<script src="../JS/script_livreur.js"></script>
</div><!-- /lv-page-wrapper -->
</body>
</html>
