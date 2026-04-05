<?php
// ─── includes/footer.php ─────────────────────────────────────────────────
$base = $base ?? '';

// Catégories pour les liens "Collections"
$_footer_cats = $pdo->query("
    SELECT ID_CATEGORIE, NOM_CATEGORIE FROM categorie ORDER BY ID_CATEGORIE
")->fetchAll(PDO::FETCH_ASSOC);
?>
<footer id="footer">
    <div class="container-xl px-4">
        <div class="row g-5">

            <!-- Logo + tagline + réseaux sociaux -->
            <div class="col-12 col-lg-4">
                <div class="footer-logo">
                    <img src="<?= $base ?>images/VELVET_LOGO_blanc.png" alt="VELVET">
                </div>
                <p class="footer-tagline">
                    "L'art de s'habiller avec élégance,<br>au cœur du Maroc."
                </p>
                <p style="font-size:11px;color:rgba(249,247,242,0.65);margin-bottom:20px;letter-spacing:0.5px;text-align:left;">
                    VELVET — votre destination mode premium au Maroc depuis 2018.
                </p>
                <div class="footer-social">
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <!-- Collections (dynamique depuis la BDD) -->
            <div class="col-6 col-sm-3 col-lg-2">
                <p class="footer-heading">Collections</p>
                <ul class="footer-links">
                    <?php foreach ($_footer_cats as $fc):
                        $catName = strtolower($fc['NOM_CATEGORIE']);
                        $catLink = '#';
                        if (strpos($catName, 'femme') !== false) $catLink = $base . 'client/collection-femme.php';
                        elseif (strpos($catName, 'homme') !== false) $catLink = $base . 'client/collection-homme.php';
                        else $catLink = $base . 'client/nouvelles-arrivees.php';
                    ?>
                    <li>
                        <a href="<?= $catLink ?>">
                            Mode <?= htmlspecialchars(ucfirst(strtolower($fc['NOM_CATEGORIE']))) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <li>
                        <a href="<?= $base ?>client/nouvelles-arrivees.php">Tous les produits</a>
                    </li>
                    <li>
                        <a href="<?= $base ?>client/nouvelles-arrivees.php">Promotions</a>
                    </li>
                </ul>
            </div>

            <!-- Service client -->
            <div class="col-6 col-sm-3 col-lg-2">
                <p class="footer-heading">Service Client</p>
                <ul class="footer-links">
                    <li><a href="<?= $base ?>client/index.php">Mon Compte</a></li>
                    <li><a href="<?= $base ?>client/index.php">Mes Commandes</a></li>
                    <li><a href="<?= $base ?>client/panier.php">Mon Panier</a></li>
                    <li><a href="<?= $base ?>client/favoris.php">Mes Favoris</a></li>
                    <li><a href="<?= $base ?>login.php">Connexion</a></li>
                    <li><a href="<?= $base ?>client/inscription.php">Créer un compte</a></li>
                </ul>
            </div>

            <!-- Formulaire de contact -->
            <div class="col-12 col-lg-4">
                <p class="footer-heading">Nous Contacter</p>
                <div id="footer-form-msg" style="display:none;margin-bottom:10px;"></div>
                <form class="footer-form" id="footerContactForm" onsubmit="submitContact(event)">
                    <input type="text"     name="nom"     placeholder="Nom"      required>
                    <input type="text"     name="prenom"  placeholder="Prénom"   required>
                    <input type="email"    name="email"   placeholder="Email (optionnel)">
                    <textarea name="problem" placeholder="Décrivez votre problème..." required></textarea>
                    <div class="footer-form-btns">
                        <button type="submit" class="btn-footer-send">
                            <i class="fas fa-paper-plane me-1"></i> Envoyer
                        </button>
                        <button type="reset" class="btn-footer-cancel">Annuler</button>
                    </div>
                </form>
            </div>

        </div>

        <div class="footer-bottom">
            <p style="text-align:center;width:100%;">
                © <?= date('Y') ?> VELVET Morocco. Tous droits réservés.
            </p>
        </div>
    </div>
</footer>

<script>
async function submitContact(e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    data.append('action', 'contact');

    const res  = await fetch('<?= $base ?>contact.php', { method: 'POST', body: data });
    const json = await res.json().catch(() => null);

    const msg = document.getElementById('footer-form-msg');
    msg.style.display = 'block';
    if (json && json.success) {
        msg.style.cssText = 'display:block;color:#4CAF50;font-size:13px;margin-bottom:10px;';
        msg.innerHTML = '<i class="fas fa-check-circle me-1"></i> Message envoyé avec succès !';
        form.reset();
    } else {
        msg.style.cssText = 'display:block;color:#e74c3c;font-size:13px;margin-bottom:10px;';
        msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (json?.message || 'Erreur, veuillez réessayer.');
    }
    setTimeout(() => { msg.style.display = 'none'; }, 4000);
}
</script>