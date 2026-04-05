<?php
if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['livreur_id'])) {
    header("Location: ../login.php"); exit();
}
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php?error=missing_id"); exit();
}

require_once __DIR__ . '/../db.php';
$id_livraison = (int)$_GET['id'];

try {
    // Vérifier que la livraison appartient à ce livreur
    $check = $pdo->prepare("SELECT ID_LIVRAISON FROM livraison WHERE ID_LIVRAISON=:id AND ID_LIVREUR=:livreur");
    $check->execute(['id' => $id_livraison, 'livreur' => $_SESSION['livreur_id']]);
    if ($check->rowCount() === 0) {
        header("Location: index.php?error=unauthorized"); exit();
    }

    // Marquer comme livrée
    $pdo->prepare("UPDATE livraison SET STATUT_LIVRAISON='livree', DATE_LIVRAISON=CURDATE() WHERE ID_LIVRAISON=:id")
        ->execute(['id' => $id_livraison]);

    // Mettre à jour la commande
    $pdo->prepare("
        UPDATE commande SET STATUT_COMMANDE='livré'
        WHERE ID_COMMANDE = (SELECT ID_COMMANDE FROM livraison WHERE ID_LIVRAISON=:id)
    ")->execute(['id' => $id_livraison]);

    header("Location: index.php?success=livree"); exit();
} catch (PDOException $e) {
    error_log("livrer_commande error: " . $e->getMessage());
    header("Location: index.php?error=db_error"); exit();
}
