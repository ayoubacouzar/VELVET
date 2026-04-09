<?php
session_start();
require_once 'db.php';


$form_success = false;
$form_error   = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && $_POST['action'] === 'contact') {
    $nom     = trim(htmlspecialchars($_POST['nom']     ?? ''));
    $prenom  = trim(htmlspecialchars($_POST['prenom']  ?? ''));
    $problem = trim(htmlspecialchars($_POST['problem'] ?? ''));
    if (empty($nom) || empty($prenom) || empty($problem)) {
        $form_error = "Veuillez remplir tous les champs.";
    } else {
        $form_success = true;
    }
}


try {
    $stmt = $pdo->query("
        SELECT p.*, sc.NOM_SOUS_CATEGORIE
        FROM produit p
        LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
        ORDER BY p.ID_PRODUIT DESC
        LIMIT 6
    ");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}


try {
    $total_products = $pdo->query("SELECT COUNT(*) FROM produit")->fetchColumn();
} catch (PDOException $e) {
    $total_products = count($products);
}


$restants = max(0, $total_products - count($products));


$tailles_par_produit = [];
if (!empty($products)) {
    $ids          = array_column($products, 'ID_PRODUIT');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT ID_PRODUIT, TAILLE, COULEUR, QUANTITE
            FROM modele_produit
            WHERE ID_PRODUIT IN ($placeholders)
            ORDER BY ID_PRODUIT,
                FIELD(TAILLE,'XS','S','M','L','XL','XXL') ,
                TAILLE
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $tailles_par_produit[$row['ID_PRODUIT']][] = $row;
        }
    } catch (PDOException $e) {}
}

function getImage(array $p): string {
    return $p['IMAGE1'] ?: ($p['IMAGE2'] ?: ($p['IMAGE3'] ?: 'images/placeholder.jpg'));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/VELVET_LOGO_blanc.png">
    <title>Velvet Fashion — Mode Premium au Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/Main.css">

    <style>
        
        .btn-voir-plus {
            display: inline-flex; align-items: center; gap: 10px;
            background: transparent; color: #000;
            border: 2px solid #000; padding: 14px 40px;
            font-family: 'Anton', sans-serif; font-size: 14px;
            letter-spacing: 3px; text-transform: uppercase;
            text-decoration: none; border-radius: 4px;
            transition: all 0.4s ease; position: relative; overflow: hidden;
        }
        .btn-voir-plus::before {
            content: ''; position: absolute; inset: 0;
            background: #000; transform: translateY(100%);
            transition: transform 0.4s ease; z-index: 0;
        }
        .btn-voir-plus:hover::before { transform: translateY(0); }
        .btn-voir-plus:hover { color: #fff; }
        .btn-voir-plus span, .btn-voir-plus i { position: relative; z-index: 1; }
        .btn-voir-plus i { transition: transform 0.4s ease; }
        .btn-voir-plus:hover i { transform: translateX(4px); }

        
        .sizes-row { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px; }
        .sz {
            border: 1.5px solid #e0e0e0; border-radius: 5px;
            padding: 3px 9px; font-size: 10px; font-weight: 700;
            cursor: pointer; transition: all 0.2s; color: #444; user-select: none;
        }
        .sz:hover:not(.sz-out) { border-color: #000; background: #000; color: #fff; }
        .sz.sz-active { border-color: #000; background: #000; color: #fff; }
        .sz.sz-out { color: #ccc; border-color: #eee; cursor: not-allowed; text-decoration: line-through; }
        .sz-stock { font-size: 9px; color: #bbb; margin-bottom: 10px; }
        .sz-stock.low { color: #e63946; font-weight: 700; }

        
        .prod-col { content-visibility: auto; contain-intrinsic-size: 0 500px; }
    </style>
</head>
<body>

<?php $base = ''; include 'includes/navbar.php'; ?>


<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <h1 class="hero-title">VELVET<br>FASHION</h1>
                <p class="my-4 text-muted w-75 lead">
                    Découvrez une expérience mode qui reflète votre personnalité unique
                    et vous permet de vous démarquer avec élégance dans chaque situation.
                </p>
                <div class="mt-5">
                    <button class="btn btn-black me-3" onclick="goToProducts()">Acheter Produit</button>
                    <button class="btn btn-outline-dark px-4 py-2 fw-bold" onclick="goToCollection()">Explorer la Collection</button>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="Images/hero.png" class="hero-img shadow-sm" alt="Fashion Model">
            </div>
        </div>
    </div>
</section>


<div class="marquee">
    <div class="marquee-track">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <span>✦ VELVET FASHION ✦</span>
            <span>✦ NEW COLLECTION ✦</span>
        <?php endfor; ?>
    </div>
</div>


<section class="collection-section" id="collection-section">
    <a href="client/collection-femme.php" class="collection-card women">
        <img class="col-img" src="https://i0.wp.com/malwinapersonalshopper.com/wp-content/uploads/2025/07/f99c532cf189d724b0552483f1f276f7.jpg?resize=736%2C917&ssl=1" alt="Femmes">
        <div class="collection-overlay"><h2>Femmes</h2><span>Découvrir la collection</span></div>
    </a>
    <a href="client/collection-homme.php" class="collection-card men">
        <img class="col-img" src="https://media.paperblog.fr/i/1000/10001422/lesthetique-old-money-principale-tendance-mas-L-PDnaKT.jpeg" alt="Hommes">
        <div class="collection-overlay"><h2>Hommes</h2><span>Explorer les Styles</span></div>
    </a>
</section>


<section class="products-section container my-5 py-5" id="products-section">
    <div class="text-center mb-5">
        <h2 class="fw-bold display-5" style="font-family:'Anton';">NOUVELLES ARRIVÉES</h2>
        <p class="text-muted">
            Les <?= count($products) ?> dernières pièces
            <?php if ($total_products > 6): ?>
                sur <strong><?= $total_products ?></strong> au total
            <?php endif; ?>
        </p>
    </div>

    <?php if (empty($products)): ?>
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-3x text-muted mb-3 d-block"></i>
            <p class="text-muted" style="letter-spacing:2px;text-transform:uppercase;font-size:12px;">
                Aucun produit disponible pour le moment.
            </p>
        </div>
    <?php else: ?>
    <div class="row g-3 g-md-4">
        <?php foreach ($products as $i => $product):
            $img     = getImage($product);
            $tailles = $tailles_par_produit[$product['ID_PRODUIT']] ?? [];
            $promo   = !empty($product['EN_PROMO']) && !empty($product['PRIX_PROMO']);
            $stock_total = array_sum(array_column($tailles, 'QUANTITE'));
            $firstAvail  = null;
            foreach ($tailles as $t) {
                if ($t['QUANTITE'] > 0) { $firstAvail = $t; break; }
            }
        ?>
        <div class="col-6 col-md-4 prod-col">
            <div class="prod-card">
                <div class="prod-img-wrap">
                    <a href="client/produit.php?id=<?= $product['ID_PRODUIT'] ?>">
                        <img src="<?= htmlspecialchars($img) ?>"
                             alt="<?= htmlspecialchars($product['NOM_PRODUIT']) ?>"
                             loading="lazy"
                             onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-tshirt\'></i></div>'">
                    </a>
                    <?php if ($promo): ?>
                        <span class="badge-promo">Promo</span>
                    <?php else: ?>
                        <span class="badge-nouveau">Nouveau</span>
                    <?php endif; ?>
                    <?php if ($stock_total <= 0): ?>
                        <span class="badge-epuise" style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.5);color:#fff;font-size:9px;letter-spacing:1px;padding:4px 10px;border-radius:20px;z-index:2;">Épuisé</span>
                    <?php endif; ?>
                    <button class="btn-wish" title="Favoris" data-toggle-fav="<?= $product['ID_PRODUIT'] ?>">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
                <div class="prod-body">
                    <?php if (!empty($product['NOM_SOUS_CATEGORIE'])): ?>
                        <p class="prod-sc"><?= htmlspecialchars($product['NOM_SOUS_CATEGORIE']) ?></p>
                    <?php endif; ?>
                    <a href="client/produit.php?id=<?= $product['ID_PRODUIT'] ?>" style="text-decoration:none;">
                        <p class="prod-name"><?= htmlspecialchars($product['NOM_PRODUIT']) ?></p>
                    </a>
                    <div class="prod-prices">
                        <?php if ($promo): ?>
                            <span class="price-final sale"><?= number_format($product['PRIX_PROMO'], 0) ?> DH</span>
                            <span class="price-old"><?= number_format($product['PRIX'], 0) ?> DH</span>
                        <?php else: ?>
                            <span class="price-final"><?= number_format($product['PRIX'], 0) ?> DH</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($tailles)): ?>
                    <div class="sizes-row" id="sizes-<?= $product['ID_PRODUIT'] ?>">
                        <?php foreach ($tailles as $t):
                            $dispo  = $t['QUANTITE'] > 0;
                            $active = ($firstAvail && $t['TAILLE'] === $firstAvail['TAILLE']);
                            $cls    = 'sz';
                            if (!$dispo)       $cls .= ' sz-out';
                            elseif ($active)   $cls .= ' sz-active';
                        ?>
                        <span class="<?= $cls ?>"
                              data-qty="<?= (int)$t['QUANTITE'] ?>"
                              onclick="selectSizeHome(this, <?= $product['ID_PRODUIT'] ?>)">
                            <?= htmlspecialchars($t['TAILLE']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="sz-stock <?= ($firstAvail && $firstAvail['QUANTITE'] <= 3) ? 'low' : '' ?>"
                       id="stock-<?= $product['ID_PRODUIT'] ?>">
                        <?php if ($firstAvail): ?>
                            <?php if ($firstAvail['QUANTITE'] <= 3): ?>
                                ⚠ Plus que <?= $firstAvail['QUANTITE'] ?> en stock
                            <?php else: ?>
                                En stock (<?= $firstAvail['QUANTITE'] ?> dispo.)
                            <?php endif; ?>
                        <?php elseif ($stock_total <= 0): ?>
                            <span style="color:#ccc;">Rupture de stock</span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>

                    <button class="btn-cart"
                            data-add-cart="<?= $product['ID_PRODUIT'] ?>"
                            data-cart-id="<?= $product['ID_PRODUIT'] ?>"
                            <?= $stock_total <= 0 ? 'disabled' : '' ?>
                            title="Ajouter au panier">
                        <i class="fas fa-shopping-bag"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    
    <?php if ($total_products > 6): ?>
    <div class="text-center mt-5">
        <a href="client/nouvelles-arrivees.php" class="btn-voir-plus">
            <span>Voir Plus</span>
            <i class="fas fa-arrow-right"></i>
        </a>
        <p class="mt-3 text-muted" style="font-size:11px;letter-spacing:2px;text-transform:uppercase;">
            <?= $restants ?> autres articles disponibles
        </p>
    </div>
    <?php endif; ?>

</section>


<section class="py-5" style="background-color:#f8f9fa;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold display-5" style="font-family:'Anton';">POURQUOI NOUS CHOISIR ?</h2>
            <p class="text-muted">Plus que de la mode — c'est une expérience.</p>
        </div>
        <div class="row text-center g-4">
            <div class="col-md-4"><div class="p-4">
                <i class="fas fa-tags fa-2x mb-3 text-dark"></i>
                <h5 class="fw-bold">Promotions Exclusives</h5>
                <p class="text-muted small">Chez Velvet, nous proposons des promotions régulières et des remises spéciales pour que vous puissiez profiter d'une mode premium aux meilleurs prix.</p>
            </div></div>
            <div class="col-md-4"><div class="p-4">
                <i class="fas fa-gem fa-2x mb-3 text-dark"></i>
                <h5 class="fw-bold">Qualité Premium</h5>
                <p class="text-muted small">Nous sélectionnons soigneusement des matériaux de haute qualité pour garantir confort, durabilité et un look luxueux dans chaque pièce.</p>
            </div></div>
            <div class="col-md-4"><div class="p-4">
                <i class="fas fa-shipping-fast fa-2x mb-3 text-dark"></i>
                <h5 class="fw-bold">Livraison Rapide &amp; Fiable</h5>
                <p class="text-muted small">Profitez d'une livraison rapide et sécurisée. Votre satisfaction est notre priorité, de la commande jusqu'à la réception.</p>
            </div></div>
        </div>
        <div class="text-center mt-5">
            <p style="font-family:'Anton',sans-serif;font-size:1.6rem;letter-spacing:1.5px;color:#111;line-height:1.5;max-width:680px;margin:0 auto;text-transform:uppercase;">
                VELVET. VOTE PROPRE —<br>DESTIONATION.
                <span style="font-family:'Inter',sans-serif;font-size:0.95rem;letter-spacing:3px;color:#888;font-weight:500;text-transform:uppercase;display:block;margin-top:10px;">
Plus que de la mode — c'est une expérience.                </span>
            </p>
            <button class="btn btn-black mt-4" onclick="goToCategories()">Découvrir les catégories</button>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<div id="toast" class="toast-msg"></div>
<script src="JS/Main.js"></script>
<script src="JS/script.js"></script>
<script>

function selectSizeHome(el, prodId) {
    if (el.classList.contains('sz-out')) return;
    const wrap = document.getElementById('sizes-' + prodId);
    if (wrap) wrap.querySelectorAll('.sz').forEach(s => s.classList.remove('sz-active'));
    el.classList.add('sz-active');
    const qty = parseInt(el.dataset.qty || '0');
    const stockEl = document.getElementById('stock-' + prodId);
    if (stockEl) {
        if (qty <= 0) {
            stockEl.innerHTML = '<span style="color:#ccc;">Rupture de stock</span>';
            stockEl.className = 'sz-stock';
        } else if (qty <= 3) {
            stockEl.innerHTML = '⚠ Plus que ' + qty + ' en stock';
            stockEl.className = 'sz-stock low';
        } else {
            stockEl.innerHTML = 'En stock (' + qty + ' dispo.)';
            stockEl.className = 'sz-stock';
        }
    }
}
</script>
</body>
</html>