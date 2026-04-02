<?php
// checkout.php — Crée une commande depuis le panier
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

// Récupérer les articles du panier
$stmt = $pdo->prepare("
    SELECT i.ID_PRODUIT, i.QUANTITE, p.PRIX, p.EN_PROMO, p.PRIX_PROMO
    FROM inclure i
    JOIN produit p ON i.ID_PRODUIT = p.ID_PRODUIT
    WHERE i.ID_PANIER = ?
");
$stmt->execute([$panierId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Votre panier est vide.']); exit;
}

// Calculer le total
$sousTotal = 0;
foreach ($items as $it) {
    $prix = ($it['EN_PROMO'] && $it['PRIX_PROMO']) ? $it['PRIX_PROMO'] : $it['PRIX'];
    $sousTotal += $prix * $it['QUANTITE'];
}
$livraison = $sousTotal >= 500 ? 0 : 30;
$total     = $sousTotal + $livraison;

// Récupérer l'adresse du client
$client = $pdo->prepare("SELECT * FROM client WHERE ID_CLIENT=?");
$client->execute([$clientId]);
$client = $client->fetch(PDO::FETCH_ASSOC);

try {
    $pdo->beginTransaction();

    // Créer la commande
    $pdo->prepare("
        INSERT INTO commande (ID_CLIENT, DATE_COMMANDE, STATUT_COMMANDE, MONTANT_TOTAL, ADRESSE_LIVRAISON, TEL_LIVRAISON)
        VALUES (?, CURDATE(), 'en cours', ?, ?, ?)
    ")->execute([$clientId, $total, $client['ADRESSE_CLIENT'] ?? '', $client['TEL_CLIENT'] ?? '']);
    $commandeId = $pdo->lastInsertId();

    // Ajouter chaque produit dans contient — on prend le premier modèle disponible
    foreach ($items as $it) {
        $modele = $pdo->prepare("SELECT ID_MODELE FROM modele_produit WHERE ID_PRODUIT=? AND QUANTITE>0 ORDER BY ID_MODELE LIMIT 1");
        $modele->execute([$it['ID_PRODUIT']]);
        $modele = $modele->fetch(PDO::FETCH_ASSOC);
        if (!$modele) continue;

        $prix = ($it['EN_PROMO'] && $it['PRIX_PROMO']) ? $it['PRIX_PROMO'] : $it['PRIX'];
        $pdo->prepare("INSERT INTO contient (ID_COMMANDE,ID_MODELE,QUANTITE,PRIX) VALUES(?,?,?,?)")
            ->execute([$commandeId, $modele['ID_MODELE'], $it['QUANTITE'], $prix]);

        // Décrémenter le stock
        $pdo->prepare("UPDATE modele_produit SET QUANTITE=QUANTITE-? WHERE ID_MODELE=?")
            ->execute([$it['QUANTITE'], $modele['ID_MODELE']]);
    }

    // Créer une livraison (livreur auto = premier disponible)
    $livreurDisp = $pdo->query("SELECT ID_LIVREUR FROM livreur WHERE STATUT_LIVREUR='disponible' LIMIT 1")->fetch();
    $livreurId   = $livreurDisp ? $livreurDisp['ID_LIVREUR'] : null;
    $pdo->prepare("INSERT INTO livraison (ID_COMMANDE,ID_LIVREUR,STATUT_LIVRAISON) VALUES(?,?,'en attente')")
        ->execute([$commandeId, $livreurId]);

    // Vider le panier
    $pdo->prepare("DELETE FROM inclure WHERE ID_PANIER=?")->execute([$panierId]);
    unset($_SESSION['panier_id']);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Commande passée avec succès !', 'commande_id' => $commandeId]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Checkout error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la validation. Veuillez réessayer.']);
}
