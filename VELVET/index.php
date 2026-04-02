<?php
require_once 'db.php';

// ── Traitement formulaire contact ────────────────────────────────
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

// ── 6 derniers produits avec image ──────────────────────────────
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

// ── Total produits ───────────────────────────────────────────────
try {
    $total_products = $pdo->query("SELECT COUNT(*) FROM produit")->fetchColumn();
} catch (PDOException $e) {
    $total_products = count($products);
}

// ── Calcul correct des articles restants ─────────────────────────
$restants = max(0, $total_products - count($products));

// ── Tailles + stock depuis modele_produit ────────────────────────
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
    <title>Velvet Fashion — Mode Premium au Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/Main.css">

    <style>
        /* ── Cards ── */
        .product-card {
            background: #fff; border-radius: 16px; overflow: hidden;
            transition: 0.3s ease; box-shadow: 0 2px 15px rgba(0,0,0,0.06);
        }
        .product-card:hover { transform: translateY(-6px); box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
        .product-img-wrap { position: relative; overflow: hidden; }
        .product-img-wrap img { width: 100%; height: 320px; object-fit: cover; transition: transform 0.5s ease; }
        .product-card:hover .product-img-wrap img { transform: scale(1.05); }

        /* ── Badges ── */
        .badge-new {
            position: absolute; top: 14px; left: 14px;
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.70rem; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; background: #000; color: #fff;
            z-index: 2;
        }
        .badge-promo {
            position: absolute; top: 14px; left: 14px;
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.70rem; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; background: #e63946; color: #fff;
            z-index: 2;
        }

        /* ── Icône favoris ── */
        .btn-wishlist {
            position: absolute; top: 14px; right: 14px;
            background: white; border: none; border-radius: 50%;
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            z-index: 2;
        }
        .btn-wishlist i { color: #555; font-size: 15px; transition: 0.3s; }
        .btn-wishlist:hover { background: #e63946; }
        .btn-wishlist:hover i { color: #fff; }
        .btn-wishlist.active i { color: #e63946; font-weight: 900; }

        /* ── Infos produit ── */
        .product-info { padding: 16px 18px 18px; }
        .product-type { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 2px; color: #aaa; margin-bottom: 2px; }
        .product-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 0; color: #111; line-height: 1.3; }
        .product-price { font-weight: 800; font-size: 1rem; color: #000; white-space: nowrap; }

        /* ── Tailles ── */
        .sizes { display: flex; gap: 5px; margin-top: 12px; flex-wrap: wrap; }
        .size {
            border: 1px solid #e0e0e0; border-radius: 6px;
            padding: 4px 10px; font-size: 0.72rem; font-weight: 600;
            cursor: pointer; transition: 0.2s; user-select: none;
            background: #fff; color: #333; line-height: 1.4;
        }
        .size:hover:not(.out) { border-color: #000; background: #000; color: #fff; }
        .size.active-size { background: #000; color: #fff; border-color: #000; }
        .size.out {
            color: #ccc; border-color: #eee; cursor: not-allowed;
            text-decoration: line-through; background: #fafafa;
        }
        .stock-info {
            font-size: 10px; color: #aaa; margin-top: 6px;
            letter-spacing: 0.5px;
        }
        .stock-info.low { color: #e63946; font-weight: 600; }

        /* ── Bouton panier ── */
        .btn-panier {
            display: inline-flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; margin-top: 10px;
            background: #000; color: #fff; border: none;
            border-radius: 50%; font-size: 16px;
            cursor: pointer; transition: all 0.2s ease;
        }
        .btn-panier:hover { background: #333; transform: scale(1.08); }
        .btn-panier:disabled { background: #ccc; cursor: not-allowed; }

        /* ── Prix promo ── */
        .prix-promo { color: #e63946 !important; }
        .prix-barre { font-size: 11px; color: #aaa; text-decoration: line-through; }

        /* ── Bouton Voir Plus ── */
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
    </style>
</head>
<body>

<?php $base = ''; include 'includes/navbar.php'; ?>

<!-- ════ HERO ════ -->
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

<!-- ════ MARQUEE ════ -->
<div class="marquee">
    <div class="marquee-track">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <span>✦ VELVET FASHION ✦</span>
            <span>✦ VELVET FASHION ✦</span>
            <span>✦ NEW COLLECTION ✦</span>
        <?php endfor; ?>
    </div>
</div>

<!-- ════ COLLECTIONS ════ -->
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

<!-- ════ NOUVELLES ARRIVÉES ════ -->
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
    <div class="row g-4">
        <?php foreach ($products as $product):
            $img     = getImage($product);
            $tailles = $tailles_par_produit[$product['ID_PRODUIT']] ?? [];
            // Taille sélectionnée par défaut = première disponible
            $first_available = null;
            foreach ($tailles as $t) {
                if ($t['QUANTITE'] > 0) { $first_available = $t['TAILLE']; break; }
            }
        ?>
        <div class="col-md-4">
            <div class="product-card">
                <a href="produitdetails.php?id=<?= $product['ID_PRODUIT'] ?>" style="text-decoration:none;color:inherit;">
                <div class="product-img-wrap">
                    <img src="<?= htmlspecialchars($img) ?>"
                         alt="<?= htmlspecialchars($product['NOM_PRODUIT']) ?>"
                         onerror="this.src='images/placeholder.jpg'">

                    <!-- Badge NEW ou PROMO -->
                    <?php if (!empty($product['EN_PROMO'])): ?>
                        <span class="badge-promo">PROMO</span>
                    <?php else: ?>
                        <span class="badge-new">NOUVEAU</span>
                    <?php endif; ?>

                    <!-- Icône favoris -->
                    <button class="btn-wishlist" title="Ajouter aux favoris"
                            onclick="toggleWishlist(this)">
                        <i class="far fa-heart"></i>
                    </button>
                </div>

                <div class="product-info">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div style="flex:1;min-width:0;">
                            <?php if (!empty($product['NOM_SOUS_CATEGORIE'])): ?>
                                <p class="product-type"><?= htmlspecialchars($product['NOM_SOUS_CATEGORIE']) ?></p>
                            <?php endif; ?>
                            <h6 class="product-name"><?= htmlspecialchars($product['NOM_PRODUIT']) ?></h6>
                        </div>
                        <div class="text-end" style="white-space:nowrap;">
                            <?php if (!empty($product['EN_PROMO']) && !empty($product['PRIX_PROMO'])): ?>
                                <div class="prix-promo product-price"><?= number_format($product['PRIX_PROMO'], 0, ',', '') ?> DH</div>
                                <div class="prix-barre"><?= number_format($product['PRIX'], 0, ',', '') ?> DH</div>
                            <?php else: ?>
                                <div class="product-price"><?= number_format($product['PRIX'], 0, ',', '') ?> DH</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tailles depuis modele_produit -->
                    <?php if (!empty($tailles)): ?>
                    <div class="sizes">
                        <?php foreach ($tailles as $t):
                            $dispo   = $t['QUANTITE'] > 0;
                            $isFirst = ($t['TAILLE'] === $first_available);
                            $cls     = 'size';
                            if (!$dispo)   $cls .= ' out';
                            elseif ($isFirst) $cls .= ' active-size';
                        ?>
                            <span class="<?= $cls ?>"
                                  data-quantite="<?= (int)$t['QUANTITE'] ?>"
                                  title="<?= $dispo ? 'Stock : '.(int)$t['QUANTITE'] : 'Épuisé' ?>">
                                <?= htmlspecialchars($t['TAILLE']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <!-- Info stock taille sélectionnée -->
                    <div class="stock-info" id="stock-info-<?= $product['ID_PRODUIT'] ?>">
                        <?php if ($first_available): ?>
                            <?php
                            $stock_first = 0;
                            foreach ($tailles as $t) {
                                if ($t['TAILLE'] === $first_available) { $stock_first = $t['QUANTITE']; break; }
                            }
                            ?>
                            <?php if ($stock_first <= 3): ?>
                                <span class="low">⚠ Plus que <?= $stock_first ?> en stock</span>
                            <?php else: ?>
                                En stock (<?= $stock_first ?> disponibles)
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Bouton Ajouter au Panier -->
                    <button class="btn-panier" data-add-cart="<?= (int)$product['ID_PRODUIT'] ?>" data-cart-id="<?= (int)$product['ID_PRODUIT'] ?>"
                        <i class="fas fa-shopping-bag"></i>
                        Ajouter au Panier
                    </button>
                </div>
            </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Bouton Voir Plus -->
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

<!-- ════ POURQUOI NOUS CHOISIR ════ -->
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
// ── Sélection taille + mise à jour stock ──
document.querySelectorAll('.sizes').forEach(group => {
    group.querySelectorAll('.size:not(.out)').forEach(size => {
        size.addEventListener('click', () => {
            group.querySelectorAll('.size').forEach(s => s.classList.remove('active-size'));
            size.classList.add('active-size');
            const card     = group.closest('.product-card');
            const stockEl  = card ? card.querySelector('[id^="stock-info-"]') : null;
            const quantite = parseInt(size.dataset.quantite || '0');
            if (stockEl) {
                if (quantite <= 0)      stockEl.innerHTML = '<span style="color:#aaa;">Épuisé</span>';
                else if (quantite <= 3) stockEl.innerHTML = `<span class="low">⚠ Plus que ${quantite} en stock</span>`;
                else                    stockEl.innerHTML = `En stock (${quantite} disponibles)`;
            }
        });
    });
});

// ── Toggle favoris ──
function toggleWishlist(btn) {
    const prodId = btn.dataset.prodId || btn.closest('[data-prod-id]')?.dataset.prodId;
    if (prodId) { toggleFav(prodId, btn); return; }
    btn.classList.toggle('active');
    const icon = btn.querySelector('i');
    if (btn.classList.contains('active')) {
        icon?.classList.replace('far', 'fas');
        icon && (icon.style.color = '#e63946');
    } else {
        icon?.classList.replace('fas', 'far');
        icon && (icon.style.color = '');
    }
}
</script>
</body>
</html>