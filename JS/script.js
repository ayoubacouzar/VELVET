/* ═══════════════════════════════════════════════════════════
   script.js — VELVET · JavaScript partagé (toutes pages)
   ══════════════════════════════════════════════════════════ */

/* ── Mini-bar slider ───────────────────────────────────── */
(function () {
    const slides = document.querySelectorAll('.mini-bar-slide');
    if (!slides.length) return;
    let current = 0;
    setInterval(() => {
        slides[current].classList.remove('active');
        current = (current + 1) % slides.length;
        slides[current].classList.add('active');
    }, 4500);
})();

/* ── Scroll reveal ─────────────────────────────────────── */
(function () {
    const els = document.querySelectorAll('.reveal');
    if (!els.length) return;
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (!e.isIntersecting) return;
            const siblings = Array.from(e.target.parentNode.children).filter(el => el.classList.contains('reveal'));
            const idx = siblings.indexOf(e.target);
            setTimeout(() => e.target.classList.add('visible'), idx * 80);
            obs.unobserve(e.target);
        });
    }, { threshold: 0.08 });
    els.forEach(el => obs.observe(el));
})();

/* ── Toast notification — 7 secondes ──────────────────── */
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'toast-msg ' + type + ' show';
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.className = 'toast-msg'; }, 7000);
}

/* ── Footer contact form ───────────────────────────────── */
function sendFooterForm() {
    const nom     = document.getElementById('f-nom')?.value.trim();
    const prenom  = document.getElementById('f-prenom')?.value.trim();
    const problem = document.getElementById('f-problem')?.value.trim();
    if (!nom || !prenom || !problem) { showToast('Veuillez remplir tous les champs.', 'warning'); return; }
    document.getElementById('f-nom').value     = '';
    document.getElementById('f-prenom').value  = '';
    document.getElementById('f-problem').value = '';
    showToast('Votre message a bien été envoyé !', 'success');
    const s = document.getElementById('footerFormSuccess');
    if (s) { s.style.display = 'block'; setTimeout(() => { s.style.display = 'none'; }, 4000); }
}
function cancelFooterForm() {
    ['f-nom','f-prenom','f-problem'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    const s = document.getElementById('footerFormSuccess');
    if (s) s.style.display = 'none';
}

/* ── Auth pages: toggle password ──────────────────────── */
function togglePwd(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    if (!f || !i) return;
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

/* ── Inscription: ville "Autre" ────────────────────────── */
(function () {
    const villeSelect = document.getElementById('ville');
    if (!villeSelect) return;
    villeSelect.addEventListener('change', function () {
        const c = document.getElementById('ville_autre_container');
        const a = document.getElementById('ville_autre');
        if (!c || !a) return;
        if (this.value === 'Autre') { c.style.display = 'block'; a.required = true; }
        else                        { c.style.display = 'none';  a.required = false; }
    });
})();

/* ── Inscription: password strength ───────────────────── */
(function () {
    const pwdField = document.getElementById('passwordField');
    if (!pwdField) return;
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    pwdField.addEventListener('input', function () {
        const v = this.value;
        let score = 0;
        if (v.length >= 8) score++; if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++; if (/[^A-Za-z0-9]/.test(v)) score++;
        const cfg = [
            { w: '0%', bg: '#e8e8e8', txt: '' },
            { w: '25%', bg: '#e74c3c', txt: 'FAIBLE' },
            { w: '50%', bg: '#f39c12', txt: 'MOYEN' },
            { w: '75%', bg: '#3498db', txt: 'BON' },
            { w: '100%', bg: '#27ae60', txt: 'FORT' },
        ];
        const c = v.length === 0 ? cfg[0] : cfg[score] || cfg[4];
        if (fill)  { fill.style.width = c.w; fill.style.background = c.bg; }
        if (label) label.textContent = c.txt;
    });
})();

/* ── Inscription: confirm password match ──────────────── */
(function () {
    const confirmField = document.getElementById('confirmField');
    if (!confirmField) return;
    confirmField.addEventListener('input', function () {
        const pwd = document.getElementById('passwordField')?.value;
        this.style.borderColor = (this.value && this.value !== pwd) ? '#e74c3c' : '';
    });
})();


/* ═══════════════════════════════════════════════════════════
   PANIER & FAVORIS — AJAX buttons (toutes pages)
   ══════════════════════════════════════════════════════════ */

/* Déterminer le bon chemin vers actions.php */
function getActionsPath() {
    // window.VELVET_BASE est défini dans navbar.php
    return (window.VELVET_BASE || '') + 'client/actions.php';
}

/* ── Ajouter au panier ─────────────────────────────────── */
function addToCart(produitId, qte) {
    qte = qte || 1;
    const btn = document.querySelector(`[data-cart-id="${produitId}"]`);
    if (btn) { btn.classList.add('loading'); btn.disabled = true; }

    const data = new FormData();
    data.append('action', 'add_to_cart');
    data.append('id_produit', produitId);
    data.append('qte', qte);

    fetch(getActionsPath(), { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('✓ ' + res.message, 'success');
                // Mettre à jour le badge panier
                const badges = document.querySelectorAll('.panier-nav-badge');
                if (res.panier_count > 0) {
                    badges.forEach(b => { b.textContent = res.panier_count; b.style.display = 'flex'; });
                    if (!badges.length) {
                        const wrap = document.querySelector('.nav-panier-wrap');
                        if (wrap) {
                            const b = document.createElement('span');
                            b.className = 'panier-nav-badge';
                            b.textContent = res.panier_count;
                            wrap.appendChild(b);
                        }
                    }
                }
                // Animation bouton
                if (btn) {
                    btn.classList.remove('loading');
                    btn.classList.add('added');
                    setTimeout(() => { btn.classList.remove('added'); btn.disabled = false; }, 2000);
                }
            } else {
                showToast('✗ ' + (res.message || 'Erreur'), 'error');
                if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
            }
        })
        .catch(() => {
            showToast('✗ Erreur réseau', 'error');
            if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
        });
}

/* ── Toggle favori ─────────────────────────────────────── */
function toggleFav(produitId, btn) {
    const data = new FormData();
    data.append('action', 'toggle_fav');
    data.append('id_produit', produitId);

    if (btn) { btn.classList.add('loading'); }

    fetch(getActionsPath(), { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.classList.remove('loading');
            if (res.requireLogin) {
                showToast('Connectez-vous pour ajouter aux favoris.', 'warning');
                return;
            }
            if (res.success) {
                if (res.added) {
                    showToast('♥ ' + res.message, 'success');
                    if (btn) { btn.classList.add('active'); btn.title = 'Retirer des favoris'; }
                } else {
                    showToast(res.message, 'info');
                    if (btn) { btn.classList.remove('active'); btn.title = 'Ajouter aux favoris'; }
                }
            } else {
                showToast('✗ ' + (res.message || 'Erreur'), 'error');
            }
        })
        .catch(() => {
            if (btn) btn.classList.remove('loading');
            showToast('✗ Erreur réseau', 'error');
        });
}

/* ── Retirer des favoris (page client) ────────────────── */
function removeFavorite(produitId) {
    const card = document.getElementById('fav-card-' + produitId);
    if (card) { card.style.opacity = '0.4'; card.style.transform = 'scale(0.95)'; }

    const data = new FormData();
    data.append('action', 'remove_favorite');
    data.append('produit_id', produitId);

    fetch(getActionsPath(), { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (card) { card.style.opacity = '0'; setTimeout(() => card.remove(), 400); }
                showToast('Retiré des favoris.', 'info');
            } else {
                if (card) { card.style.opacity = '1'; card.style.transform = 'scale(1)'; }
                showToast('✗ Erreur, réessayez.', 'error');
            }
        })
        .catch(() => {
            if (card) { card.style.opacity = '1'; card.style.transform = 'scale(1)'; }
            showToast('✗ Erreur réseau', 'error');
        });
}

/* ── Quick-add depuis les pages catalogue ──────────────── */
document.addEventListener('click', function(e) {
    // Bouton panier (data-add-cart)
    const cartBtn = e.target.closest('[data-add-cart]');
    if (cartBtn) {
        e.preventDefault();
        const id  = cartBtn.dataset.addCart;
        const qte = cartBtn.dataset.qte || 1;
        addToCart(id, qte);
        return;
    }
    // Bouton favori (data-toggle-fav)
    const favBtn = e.target.closest('[data-toggle-fav]');
    if (favBtn) {
        e.preventDefault();
        toggleFav(favBtn.dataset.toggleFav, favBtn);
        return;
    }
});


/* ═══════════════════════════════════════════════════════════
   CLIENT PAGE (index.php) — modals & AJAX
   ══════════════════════════════════════════════════════════ */

function openProfileModal() {
    document.getElementById('profileModal')?.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeProfileModal(event) {
    if (event && event.target !== document.getElementById('profileModal')) return;
    document.getElementById('profileModal')?.classList.remove('open');
    document.body.style.overflow = '';
}
function closeOrderModal(event) {
    if (event && event.target !== document.getElementById('orderModal')) return;
    document.getElementById('orderModal')?.classList.remove('open');
    document.body.style.overflow = '';
}
function submitProfile(e) {
    e.preventDefault();
    const form = document.getElementById('profileForm');
    const btn  = document.getElementById('saveBtn');
    const data = new FormData(form);
    data.append('action', 'update_profile');
    const requiredFields = [
        { key: 'prenom', label: 'Prénom' }, { key: 'nom', label: 'Nom' },
        { key: 'email', label: 'E-mail' }, { key: 'tel', label: 'Téléphone' },
        { key: 'adresse', label: 'Adresse' }, { key: 'ville', label: 'Ville' },
    ];
    for (const f of requiredFields) {
        if (!data.get(f.key) || !data.get(f.key).trim()) {
            showToast('✗ Le champ "' + f.label + '" est obligatoire.', 'error'); return;
        }
    }
    btn.disabled = true;
    btn.querySelector('span').textContent = 'Enregistrement…';
    fetch('actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('✓ ' + res.message, 'success');
                closeProfileModal();
                const heroName = document.getElementById('heroFullName');
                if (heroName) heroName.textContent = data.get('prenom') + ' ' + data.get('nom');
            } else {
                showToast('✗ ' + (res.message || 'Erreur'), 'error');
            }
        })
        .catch(() => showToast('✗ Erreur réseau', 'error'))
        .finally(() => { btn.disabled = false; btn.querySelector('span').textContent = 'Enregistrer'; });
}

/* ── Modal commande ────────────────────────────────────── */
function openOrderModal(commandeId) {
    const modal = document.getElementById('orderModal');
    const body  = document.getElementById('orderModalBody');
    const title = document.getElementById('orderModalTitle');
    if (!modal) return;
    body.innerHTML  = '<div class="modal-loading"><i class="fas fa-spinner fa-spin"></i></div>';
    title.textContent = 'Commande #' + commandeId;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    fetch('actions.php?action=get_order_details&commande_id=' + commandeId)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { body.innerHTML = '<p style="text-align:center;color:#999;padding:40px;">Commande introuvable.</p>'; return; }
            const c = res.commande;
            const statutMap = {
                'livré': ['badge-delivered','fa-check','Livré'],
                'expédié': ['badge-shipped','fa-truck','Expédié'],
                'en cours': ['badge-processing','fa-spinner','En cours'],
                'annulé': ['badge-cancelled','fa-times','Annulé'],
            };
            const k = (c.STATUT_COMMANDE||'').toLowerCase().trim();
            const [cls,ico,lbl] = statutMap[k] || ['badge-processing','fa-circle',c.STATUT_COMMANDE||'—'];
            let html = `
            <div class="order-detail-meta">
                <div class="odm-row">
                    <span class="odm-label">Statut</span>
                    <span class="badge-status ${cls}"><i class="fas ${ico}"></i> ${lbl}</span>
                </div>
                <div class="odm-row">
                    <span class="odm-label">Date</span>
                    <span>${fmtDate(c.DATE_COMMANDE)}</span>
                </div>
                <div class="odm-row">
                    <span class="odm-label">Adresse</span>
                    <span>${esc(c.ADRESSE_LIVRAISON||'—')}</span>
                </div>
            </div>
            <div class="order-detail-items">`;
            (res.articles||[]).forEach(a => {
                const img = a.IMAGE1 ? `<img src="../${esc(a.IMAGE1)}" alt="">` : `<div class="odi-noimg"><i class="fas fa-shirt"></i></div>`;
                html += `<div class="odi-item">
                    <div class="odi-img">${img}</div>
                    <div class="odi-info">
                        <div class="odi-name">${esc(a.NOM_PRODUIT)}</div>
                        <div class="odi-meta">${esc(a.TAILLE||'')} · ${esc(a.COULEUR||'')} · ×${a.QUANTITE}</div>
                    </div>
                    <div class="odi-price">${fmtNum(a.PRIX)} DH</div>
                </div>`;
            });
            html += `</div>
            <div class="order-detail-total">
                <span>Total</span>
                <strong>${fmtNum(c.MONTANT_TOTAL)} DH</strong>
            </div>`;
            body.innerHTML = html;
        })
        .catch(() => { body.innerHTML = '<p style="text-align:center;color:#e74c3c;padding:40px;">Erreur de chargement.</p>'; });
}

/* ── Escape key closes modals ──────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    document.getElementById('profileModal')?.classList.remove('open');
    document.getElementById('orderModal')?.classList.remove('open');
    closeUserDropdown();
    closeSearch();
    document.body.style.overflow = '';
});

/* ── Nav user dropdown ─────────────────────────────────── */
function toggleUserDropdown(e) {
    e.preventDefault();
    document.getElementById('userNavDropdown')?.classList.toggle('open');
}
function closeUserDropdown() {
    document.getElementById('userNavDropdown')?.classList.remove('open');
}
document.addEventListener('click', function(e) {
    const item = document.querySelector('.nav-user-item');
    if (item && !item.contains(e.target)) closeUserDropdown();
});

/* ── Search overlay ────────────────────────────────────── */
function openSearch(e) {
    e && e.preventDefault();
    document.getElementById('searchOverlay')?.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('searchInput')?.focus(), 350);
}
function closeSearch() {
    document.getElementById('searchOverlay')?.classList.remove('open');
    document.body.style.overflow = '';
    const si = document.getElementById('searchInput');
    if (si) si.value = '';
}
function closeSearchOnBg(e) {
    if (e.target === document.getElementById('searchOverlay')) closeSearch();
}
function fillSearch(t) {
    const si = document.getElementById('searchInput');
    if (si) { si.value = t; si.focus(); }
}
function doSearch() {
    const q = document.getElementById('searchInput')?.value.trim();
    if (!q) return;
    // Rediriger vers recherche.php avec le bon chemin de base
    const base = window.VELVET_BASE || '';
    window.location.href = base + 'recherche.php?q=' + encodeURIComponent(q);
}
function handleSearchKey(e) {
    if (e.key === 'Enter')  doSearch();
    if (e.key === 'Escape') closeSearch();
}

/* ── Utilities ─────────────────────────────────────────── */
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtNum(n) { return Math.round(parseFloat(n)).toLocaleString('fr-MA'); }
function fmtDate(d) {
    if (!d) return '—';
    const parts = String(d).split('-');
    if (parts.length === 3) {
        const months = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        return parseInt(parts[2],10)+' '+months[parseInt(parts[1],10)-1]+' '+parts[0];
    }
    const dt = new Date(d + 'T00:00:00');
    const m = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
    return dt.getDate()+' '+m[dt.getMonth()]+' '+dt.getFullYear();
}

/* ═══════════════════════════════════════════════════════════
   PROFIL MODAL — Tabs + Password change
   ══════════════════════════════════════════════════════════ */

function switchProfileTab(tab, btn) {
    document.querySelectorAll('.profile-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.profile-tab').forEach(b => b.classList.remove('active'));
    const content = document.getElementById('tab-' + tab);
    if (content) content.classList.add('active');
    if (btn) btn.classList.add('active');
    if (tab === 'info') {
        const pf = document.getElementById('passwordForm');
        if (pf) pf.reset();
        const fill = document.getElementById('modalStrengthFill');
        const lbl  = document.getElementById('modalStrengthLabel');
        const msg  = document.getElementById('pwdMatchMsg');
        if (fill) { fill.style.width = '0%'; fill.style.background = '#e8e8e8'; }
        if (lbl)  lbl.textContent = '';
        if (msg)  { msg.textContent = ''; msg.className = 'pwd-match-msg'; }
    }
}

function checkPwdStrengthModal(v) {
    const fill  = document.getElementById('modalStrengthFill');
    const label = document.getElementById('modalStrengthLabel');
    if (!fill || !label) return;
    let score = 0;
    if (v.length >= 8) score++; if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++; if (/[^A-Za-z0-9]/.test(v)) score++;
    const cfg = [
        { w:'0%',bg:'#e8e8e8',txt:'' }, { w:'25%',bg:'#e74c3c',txt:'FAIBLE' },
        { w:'50%',bg:'#f39c12',txt:'MOYEN' }, { w:'75%',bg:'#3498db',txt:'BON' },
        { w:'100%',bg:'#27ae60',txt:'FORT' },
    ];
    const c = v.length === 0 ? cfg[0] : (cfg[score] || cfg[4]);
    fill.style.width = c.w; fill.style.background = c.bg; label.textContent = c.txt;
}

function checkPwdMatchModal() {
    const newVal  = document.getElementById('newPwdField')?.value     || '';
    const confVal = document.getElementById('confirmPwdField')?.value || '';
    const msg     = document.getElementById('pwdMatchMsg');
    if (!msg || !confVal) return;
    if (confVal === newVal) {
        msg.textContent = '✓ Les mots de passe correspondent'; msg.className = 'pwd-match-msg match-ok';
    } else {
        msg.textContent = '✗ Les mots de passe ne correspondent pas'; msg.className = 'pwd-match-msg match-err';
    }
}

function submitPasswordChange(e) {
    e.preventDefault();
    const form = document.getElementById('passwordForm');
    const btn  = document.getElementById('savePwdBtn');
    const data = new FormData(form);
    data.append('action', 'change_password');
    const current = data.get('current_password')?.trim();
    const newPwd  = data.get('new_password')?.trim();
    const confirm = data.get('confirm_password')?.trim();
    if (!current || !newPwd || !confirm) { showToast('✗ Veuillez remplir tous les champs.', 'error'); return; }
    if (newPwd.length < 8) { showToast('✗ Minimum 8 caractères.', 'error'); return; }
    if (newPwd !== confirm) { showToast('✗ Les mots de passe ne correspondent pas.', 'error'); return; }
    btn.disabled = true;
    btn.querySelector('span').textContent = 'Modification…';
    fetch('actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('✓ ' + res.message, 'success');
                form.reset();
                const fill = document.getElementById('modalStrengthFill');
                const lbl  = document.getElementById('modalStrengthLabel');
                const msg  = document.getElementById('pwdMatchMsg');
                if (fill) { fill.style.width = '0%'; fill.style.background = '#e8e8e8'; }
                if (lbl) lbl.textContent = '';
                if (msg) { msg.textContent = ''; msg.className = 'pwd-match-msg'; }
                closeProfileModal();
            } else {
                showToast('✗ ' + (res.message || 'Erreur'), 'error');
            }
        })
        .catch(() => showToast('✗ Erreur réseau', 'error'))
        .finally(() => { btn.disabled = false; btn.querySelector('span').textContent = 'Modifier'; });
}
