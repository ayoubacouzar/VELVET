<?php
$pageTitle = "Historique des livraisons";
include __DIR__ . '/header.php';

$mois_actuel   = date('m');
$annee_actuelle = date('Y');
$livraisons    = [];

try {
    $stmt = $conn->prepare("
        SELECT l.*, c.ID_COMMANDE, c.MONTANT_TOTAL,
               cl.NOM_CLIENT, cl.PRENOM_CLIENT, cl.ADRESSE_CLIENT
        FROM livraison l
        INNER JOIN commande c  ON l.ID_COMMANDE = c.ID_COMMANDE
        INNER JOIN client cl   ON c.ID_CLIENT   = cl.ID_CLIENT
        WHERE l.ID_LIVREUR = :id
        AND LOWER(l.STATUT_LIVRAISON) IN ('livree','livré','livrée')
        AND MONTH(l.DATE_LIVRAISON)  = :mois
        AND YEAR(l.DATE_LIVRAISON)   = :annee
        ORDER BY l.DATE_LIVRAISON DESC
    ");
    $stmt->execute([
        'id'    => $_SESSION['livreur_id'],
        'mois'  => $mois_actuel,
        'annee' => $annee_actuelle,
    ]);
    $livraisons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur historique: " . $e->getMessage());
}
?>

<div class="lv-main">

    <div class="lv-section-header" style="margin-bottom:24px;">
        <h2><i class="fas fa-history"></i> Historique des livraisons — <?= date('F Y') ?></h2>
    </div>

    <?php if (empty($livraisons)): ?>
        <div class="lv-page-toast info" style="display:flex;">
            <i class="fas fa-info-circle"></i>
            Aucune livraison trouvée pour ce mois.
        </div>
    <?php else: ?>
        <div class="lv-deliveries-section">
            <div class="lv-section-header">
                <h2><i class="fas fa-history"></i> <?= count($livraisons) ?> livraison<?= count($livraisons)>1?'s':'' ?> ce mois</h2>
            </div>
            <div class="lv-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Adresse</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($livraisons as $l): ?>
                        <tr>
                            <td>
                                <span class="lv-cmd-badge">
                                    <i class="fas fa-hashtag"></i><?= $l['ID_COMMANDE'] ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($l['DATE_LIVRAISON'])) ?></td>
                            <td><?= htmlspecialchars($l['PRENOM_CLIENT'] . ' ' . $l['NOM_CLIENT']) ?></td>
                            <td style="max-width:200px;white-space:normal;"><?= htmlspecialchars($l['ADRESSE_CLIENT']) ?></td>
                            <td><strong><?= number_format($l['MONTANT_TOTAL'], 2) ?> DH</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<script src="../JS/script_livreur.js"></script>
</body>
</html>
