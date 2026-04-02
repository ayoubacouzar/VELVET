<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: ../login.php');
    exit;
}

$clientId           = $_SESSION['client_id'];
$navReturnToAccount = true;

$stmt = $pdo->prepare("
    SELECT p.ID_PRODUIT, p.NOM_PRODUIT, p.IMAGE1, p.PRIX, p.EN_PROMO, p.PRIX_PROMO,
           sc.NOM_SOUS_CATEGORIE, a.DATE_AIME
    FROM aime a
    JOIN produit p ON a.ID_PRODUIT = p.ID_PRODUIT
    LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
    WHERE a.ID_CLIENT = ?
    ORDER BY a.DATE_AIME DESC
");
$stmt->execute([$clientId]);
$favoris = $stmt->fetchAll();
$count   = count($favoris);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Favoris — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
    .prod-card { background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.05);transition:transform .3s,box-shadow .3s; }
    .prod-card:hover { transform:translateY(-5px);box-shadow:0 12px 30px rgba(0,0,0,.10); }
    .prod-img-wrap { position:relative;overflow:hidden;aspect-ratio:3/4; }
    .prod-img-wrap img { width:100%;height:100%;object-fit:cover;transition:transform .55s; }
    .prod-card:hover .prod-img-wrap img { transform:scale(1.06); }
    .prod-no-img { width:100%;height:100%;background:#f0ece8;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2.5rem; }
    .badge-promo   { position:absolute;top:12px;left:12px;background:#e63946;color:#fff;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px;z-index:2; }
    .btn-wish { position:absolute;bottom:12px;right:12px;width:36px;height:36px;background:#fff;border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);cursor:pointer;transition:all .2s;z-index:2; }
    .btn-wish i { font-size:14px;transition:.2s; }
    .btn-wish:hover { background:#e63946; }
    .btn-wish:hover i { color:#fff!important; }
    .prod-body { padding:14px 16px 16px; }
    .prod-sc { font-size:9px;text-transform:uppercase;letter-spacing:2px;color:#bbb;margin-bottom:3px; }
    .prod-name { font-size:13px;font-weight:700;color:#111;margin-bottom:8px;line-height:1.3; }
    .prod-prices { display:flex;align-items:center;gap:8px;margin-bottom:10px; }
    .price-final { font-size:15px;font-weight:800;color:#000; }
    .price-final.sale { color:#e63946; }
    .price-old { font-size:11px;color:#bbb;text-decoration:line-through; }
    .btn-cart { display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;background:#000;color:#fff;border:none;border-radius:50%;font-size:15px;cursor:pointer;transition:all .2s; }
    .btn-cart:hover { background:#333;transform:scale(1.08); }
    .btn-cart.added { background:#27ae60; }
    </style>
</head>
<body>

<?php $base = '../'; include __DIR__ . '/../includes/navbar.php'; ?>
<!-- ── Page Title ── -->
<div class="vp-page-title">
    <div class="container">
        <div class="vp-breadcrumb">
            <a href="index.php">Mon compte</a>
            <i class="fas fa-chevron-right"></i>
            <span>Mes favoris</span>
        </div>
        <h1 class="vp-page-h1">
            Mes Favoris
            <span class="vp-page-count">(<?= $count ?>)</span>
        </h1>
    </div>
</div>

<!-- ── Grid ── -->
<div class="container vp-fav-section">

    <?php if (empty($favoris)): ?>
    <div class="empty-state">
        <i class="far fa-heart"></i>
        <p>Vous n'avez aucun article en favori.</p>
        <a href="index.php" class="btn-black-sm">Retour à mon compte</a>
    </div>

    <?php else: ?>
    <div class="row g-3 g-md-4">
        <?php foreach ($favoris as $fav):
            $promo = $fav['EN_PROMO'] && $fav['PRIX_PROMO'];
            $prix  = $promo ? $fav['PRIX_PROMO'] : $fav['PRIX'];
        ?>
        <div class="col-6 col-md-4 col-lg-3" id="fav-card-<?= $fav['ID_PRODUIT'] ?>">
            <div class="prod-card reveal">

                <!-- Image -->
                <div class="prod-img-wrap">
                    <a href="produit.php?id=<?= $fav['ID_PRODUIT'] ?>">
                    <?php if ($fav['IMAGE1']): ?>
                        <img src="../<?= htmlspecialchars($fav['IMAGE1']) ?>"
                             alt="<?= htmlspecialchars($fav['NOM_PRODUIT']) ?>" loading="lazy"
                             onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-tshirt\'></i></div>'">
                    <?php else: ?>
                        <div class="prod-no-img"><i class="fas fa-tshirt"></i></div>
                    <?php endif; ?>
                    </a>

                    <?php if ($promo): ?>
                        <span class="badge-promo">Promo</span>
                    <?php endif; ?>

                    <!-- Heart: retirer des favoris -->
                    <button class="btn-wish active"
                            onclick="removeFavorite(<?= $fav['ID_PRODUIT'] ?>)"
                            title="Retirer des favoris">
                        <i class="fas fa-heart" style="color:#e63946;"></i>
                    </button>
                </div>

                <!-- Info -->
                <div class="prod-body">
                    <p class="prod-sc"><?= htmlspecialchars($fav['NOM_SOUS_CATEGORIE'] ?? '') ?></p>
                    <a href="produit.php?id=<?= $fav['ID_PRODUIT'] ?>" style="text-decoration:none;">
                        <p class="prod-name"><?= htmlspecialchars($fav['NOM_PRODUIT']) ?></p>
                    </a>
                    <div class="prod-prices">
                        <?php if ($promo): ?>
                            <span class="price-final sale"><?= number_format($fav['PRIX_PROMO'],0) ?> DH</span>
                            <span class="price-old"><?= number_format($fav['PRIX'],0) ?> DH</span>
                        <?php else: ?>
                            <span class="price-final"><?= number_format($fav['PRIX'],0) ?> DH</span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-cart"
                            data-add-cart="<?= $fav['ID_PRODUIT'] ?>"
                            data-cart-id="<?= $fav['ID_PRODUIT'] ?>"
                            title="Ajouter au panier">
                        <i class="fas fa-shopping-bag"></i>
                    </button>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/profile_modal.php'; ?>
<div id="toast" class="toast-msg"></div>
<script src="../JS/script.js"></script>
</body>
</html>
