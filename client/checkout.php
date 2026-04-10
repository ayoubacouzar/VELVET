<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']); exit;
}

$clientId = $_SESSION['client_id'];
$panierId = $_SESSION['panier_id'] ?? null;

if (!$panierId) {
    echo json_encode(['success' => false, 'message' => 'Votre panier est vide.']); exit;
}

$stmt = $pdo->prepare("
    SELECT i.ID_PRODUIT, i.TAILLE, i.QUANTITE, p.PRIX, p.EN_PROMO, p.PRIX_PROMO
    FROM inclure i
    JOIN produit p ON i.ID_PRODUIT = p.ID_PRODUIT
    WHERE i.ID_PANIER = ?
");
$stmt->execute([$panierId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Votre panier est vide.']); exit;
}

$sousTotal = 0;
foreach ($items as $it) {
    $prix = ($it['EN_PROMO'] && $it['PRIX_PROMO']) ? $it['PRIX_PROMO'] : $it['PRIX'];
    $sousTotal += $prix * $it['QUANTITE'];
}
$livraison = $sousTotal >= 500 ? 0 : 30;
$total     = $sousTotal + $livraison;

$client = $pdo->prepare("SELECT * FROM client WHERE ID_CLIENT=?");
$client->execute([$clientId]);
$client = $client->fetch(PDO::FETCH_ASSOC);

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO commande (ID_CLIENT, DATE_COMMANDE, STATUT_COMMANDE, MONTANT_TOTAL, ADRESSE_LIVRAISON, TEL_LIVRAISON)
        VALUES (?, CURDATE(), 'en cours', ?, ?, ?)
    ")->execute([$clientId, $total, $client['ADRESSE_CLIENT'] ?? '', $client['TEL_CLIENT'] ?? '']);
    $commandeId = $pdo->lastInsertId();

    foreach ($items as $it) {
        $modele = $pdo->prepare("SELECT ID_MODELE, QUANTITE FROM modele_produit WHERE ID_PRODUIT=? AND TAILLE=? AND QUANTITE>0 LIMIT 1");
        $modele->execute([$it['ID_PRODUIT'], $it['TAILLE']]);
        $modele = $modele->fetch(PDO::FETCH_ASSOC);
        if (!$modele) continue;

        $qteCmd = min($it['QUANTITE'], (int)$modele['QUANTITE']);
        $prix = ($it['EN_PROMO'] && $it['PRIX_PROMO']) ? $it['PRIX_PROMO'] : $it['PRIX'];
        $pdo->prepare("INSERT INTO contient (ID_COMMANDE,ID_MODELE,QUANTITE,PRIX) VALUES(?,?,?,?)")
            ->execute([$commandeId, $modele['ID_MODELE'], $qteCmd, $prix]);

        $pdo->prepare("UPDATE modele_produit SET QUANTITE=QUANTITE-? WHERE ID_MODELE=?")
            ->execute([$qteCmd, $modele['ID_MODELE']]);
    }

    $livreurDisp = $pdo->query("SELECT ID_LIVREUR FROM livreur WHERE STATUT_LIVREUR='disponible' LIMIT 1")->fetch();
    $livreurId   = $livreurDisp ? $livreurDisp['ID_LIVREUR'] : null;
    $pdo->prepare("INSERT INTO livraison (ID_COMMANDE,ID_LIVREUR,STATUT_LIVRAISON) VALUES(?,?,'en attente')")
        ->execute([$commandeId, $livreurId]);

    $pdo->prepare("DELETE FROM inclure WHERE ID_PANIER=?")->execute([$panierId]);
    unset($_SESSION['panier_id']);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Commande passée avec succès !', 'commande_id' => $commandeId]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Checkout error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la validation. Veuillez réessayer.']);
}
