


function lvShowToast(msg, type) {
    const t = document.getElementById('lv-toast');
    if (!t) return;
    t.textContent = msg;
    t.className   = 'lv-toast' + (type === 'error' ? ' error' : '') + ' show';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.className = 'lv-toast'; }, 3200);
}


function toggleLvDropdown(e) {
    e.stopPropagation();
    const wrap = document.getElementById('lvUserWrap');
    const dd   = document.getElementById('lvDropdown');
    if (!wrap || !dd) return;
    dd.classList.toggle('open');
    wrap.classList.toggle('open');
}
function closeLvDropdown() {
    document.getElementById('lvDropdown')?.classList.remove('open');
    document.getElementById('lvUserWrap')?.classList.remove('open');
}
document.addEventListener('click', e => {
    const wrap = document.getElementById('lvUserWrap');
    if (wrap && !wrap.contains(e.target)) closeLvDropdown();
});


function openLvModal() {
    document.getElementById('lvModalBox')?.classList.add('open');
    document.getElementById('lvModalOverlay')?.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLvModal() {
    document.getElementById('lvModalBox')?.classList.remove('open');
    document.getElementById('lvModalOverlay')?.classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLvModal(); });


function switchLvTab(tab, btn) {
    document.querySelectorAll('.lv-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.lv-profile-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('lv-tab-' + tab)?.classList.add('active');
    btn.classList.add('active');
}


async function submitLvInfo(e) {
    e.preventDefault();
    const form = document.getElementById('lvInfoForm');
    const btn  = document.getElementById('lvInfoSaveBtn');
    const tel  = document.getElementById('lvTelInput')?.value.trim();
    const zone = document.getElementById('lvZoneInput')?.value.trim();

    
    if (!tel || !zone) {
        lvShowToast('✗ Le téléphone et la zone sont obligatoires.', 'error');
        return;
    }
    if (!/^(0[5-7][0-9]{8}|\+212[5-7][0-9]{8})$/.test(tel)) {
        lvShowToast('✗ Numéro invalide. Format : 06XXXXXXXX', 'error');
        return;
    }

    btn.disabled = true;
    const span = btn.querySelector('span');
    if (span) span.textContent = 'Enregistrement…';

    try {
        const data = new FormData();
        data.append('action', 'update_info');
        data.append('telephone', tel);
        data.append('zone_livraison', zone);
        data.append('statut', document.getElementById('lvStatutSelect')?.value || 'disponible');

        const res  = await fetch('update_profil.php', { method: 'POST', body: data });
        const json = await res.json();

        if (json.success) {
            lvShowToast('✓ ' + json.message, 'success');
            setTimeout(() => { closeLvModal(); location.reload(); }, 1400);
        } else {
            lvShowToast('✗ ' + json.message, 'error');
        }
    } catch {
        lvShowToast('✗ Erreur réseau. Veuillez réessayer.', 'error');
    } finally {
        btn.disabled = false;
        if (span) span.textContent = 'Sauvegarder';
    }
}


async function submitLvPassword(e) {
    e.preventDefault();
    const btn     = document.getElementById('lvPwdSaveBtn');
    const pwd     = document.getElementById('lvNewPwd')?.value;
    const confirm = document.getElementById('lvConfirmPwd')?.value;

    if (!pwd)            { lvShowToast('✗ Veuillez saisir un nouveau mot de passe.', 'error'); return; }
    if (pwd.length < 8)  { lvShowToast('✗ Le mot de passe doit contenir au moins 8 caractères.', 'error'); return; }
    if (pwd !== confirm) { lvShowToast('✗ Les mots de passe ne correspondent pas.', 'error'); return; }

    btn.disabled = true;
    const span = btn.querySelector('span');
    if (span) span.textContent = 'Enregistrement…';

    try {
        const data = new FormData();
        data.append('action', 'update_password');
        data.append('mot_de_passe', pwd);
        data.append('confirm_mot_de_passe', confirm);

        const res  = await fetch('update_profil.php', { method: 'POST', body: data });
        const json = await res.json();

        if (json.success) {
            lvShowToast('✓ ' + json.message, 'success');
            document.getElementById('lvNewPwd').value     = '';
            document.getElementById('lvConfirmPwd').value = '';
            document.getElementById('lvStrengthFill').style.width = '0';
            document.getElementById('lvStrengthLabel').textContent = '';
            document.getElementById('lvPwdMatchMsg').textContent   = '';
        } else {
            lvShowToast('✗ ' + json.message, 'error');
        }
    } catch {
        lvShowToast('✗ Erreur réseau. Veuillez réessayer.', 'error');
    } finally {
        btn.disabled = false;
        if (span) span.textContent = 'Modifier';
    }
}


function lvToggleEye(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    if (!f || !i) return;
    f.type     = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}


function checkLvPwdStrength(val) {
    const fill  = document.getElementById('lvStrengthFill');
    const label = document.getElementById('lvStrengthLabel');
    if (!fill || !label) return;
    let score = 0;
    if (val.length >= 8)           score++;
    if (/[a-z]/.test(val))         score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^a-zA-Z0-9]/.test(val)) score++;
    const pct = (score / 5) * 100;
    fill.style.width = pct + '%';
    if (pct <= 40)       { fill.style.background = '#dc3545'; label.style.color = '#dc3545'; label.textContent = 'Faible'; }
    else if (pct <= 80)  { fill.style.background = '#f0a500'; label.style.color = '#f0a500'; label.textContent = 'Moyen'; }
    else                 { fill.style.background = '#28a745'; label.style.color = '#28a745'; label.textContent = 'Fort'; }
}


function checkLvPwdMatch() {
    const pwd     = document.getElementById('lvNewPwd')?.value;
    const confirm = document.getElementById('lvConfirmPwd');
    const msg     = document.getElementById('lvPwdMatchMsg');
    if (!confirm || !msg) return;
    if (confirm.value && pwd) {
        if (confirm.value === pwd) {
            msg.textContent = '✓ Les mots de passe correspondent';
            msg.style.color = '#27ae60';
            confirm.style.borderColor = '#27ae60';
        } else {
            msg.textContent = '✗ Ne correspondent pas';
            msg.style.color = '#c0392b';
            confirm.style.borderColor = '#c0392b';
        }
    } else {
        msg.textContent = '';
        confirm.style.borderColor = '';
    }
}

window.togglePwd = lvToggleEye;
