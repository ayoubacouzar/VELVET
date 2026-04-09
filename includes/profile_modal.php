<?php

$stmtPM = $pdo->prepare("SELECT * FROM client WHERE ID_CLIENT = ?");
$stmtPM->execute([$clientId]);
$pmClient = $stmtPM->fetch();
?>
<div class="modal-overlay" id="profileModal" onclick="closeProfileModal(event)">
    <div class="modal-box modal-box-profile">

        <div class="profile-tabs">
            <button class="profile-tab active" onclick="switchProfileTab('info', this)">
                <i class="fas fa-user"></i> Mes informations
            </button>
            <button class="profile-tab" onclick="switchProfileTab('password', this)">
                <i class="fas fa-lock"></i> Mot de passe
            </button>
            <button class="modal-close-tab" onclick="closeProfileModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        
        <div id="tab-info" class="profile-tab-content active">
            <form id="profileForm" onsubmit="submitProfile(event)" novalidate>
                <div class="modal-body">
                    <div class="modal-row">
                        <div class="modal-field">
                            <label>Prénom <span class="req">*</span></label>
                            <input type="text" name="prenom"
                                   value="<?= htmlspecialchars($pmClient['PRENOM_CLIENT']) ?>" required>
                        </div>
                        <div class="modal-field">
                            <label>Nom <span class="req">*</span></label>
                            <input type="text" name="nom"
                                   value="<?= htmlspecialchars($pmClient['NOM_CLIENT']) ?>" required>
                        </div>
                    </div>
                    <div class="modal-field">
                        <label>E-mail <span class="req">*</span></label>
                        <input type="email" name="email"
                               value="<?= htmlspecialchars($pmClient['EMAIL_CLIENT']) ?>" required>
                    </div>
                    <div class="modal-field">
                        <label>Téléphone <span class="req">*</span></label>
                        <input type="tel" name="tel"
                               value="<?= htmlspecialchars($pmClient['TEL_CLIENT'] ?? '') ?>"
                               placeholder="06XXXXXXXX" required>
                    </div>
                    <div class="modal-field">
                        <label>Adresse de livraison <span class="req">*</span></label>
                        <input type="text" name="adresse"
                               value="<?= htmlspecialchars($pmClient['ADRESSE_CLIENT'] ?? '') ?>" required>
                    </div>
                    <div class="modal-field">
                        <label>Ville</label>
                        <input type="text" name="ville" value="Oujda" readonly
                               style="background:rgba(0,0,0,0.04);cursor:not-allowed;color:#666;">
                        <small style="color:#999;font-size:11px;margin-top:4px;display:block;">
                            <i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>
                            Livraison disponible uniquement à Oujda.
                        </small>
                    </div>
                    <p class="modal-required-note"><span class="req">*</span> Champs obligatoires</p>
                </div>

                
                <div class="modal-save-bar">
                    <div class="modal-save-bar-info">
                        <i class="fas fa-info-circle"></i>
                        Vos modifications seront enregistrées immédiatement.
                    </div>
                    <div class="modal-save-bar-actions">
                        <button type="button" class="btn-modal-cancel"
                                onclick="closeProfileModal()">Annuler</button>
                        <button type="submit" class="btn-modal-sauvegarder" id="saveBtn">
                            <i class="fas fa-floppy-disk"></i>
                            <span>Sauvegarder</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        
        <div id="tab-password" class="profile-tab-content">
            <form id="passwordForm" onsubmit="submitPasswordChange(event)" novalidate>
                <div class="modal-body">
                    <div class="modal-field">
                        <label>Mot de passe actuel <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input class="modal-pwd-input" type="password"
                                   name="current_password" id="currentPwdField"
                                   placeholder="••••••••" required>
                            <button type="button" class="toggle-pwd-modal"
                                    onclick="togglePwd('currentPwdField','eyeCurrent')">
                                <i class="fas fa-eye" id="eyeCurrent"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-field">
                        <label>Nouveau mot de passe <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input class="modal-pwd-input" type="password"
                                   name="new_password" id="newPwdField"
                                   placeholder="Min. 8 caractères"
                                   oninput="checkPwdStrengthModal(this.value)" required>
                            <button type="button" class="toggle-pwd-modal"
                                    onclick="togglePwd('newPwdField','eyeNew')">
                                <i class="fas fa-eye" id="eyeNew"></i>
                            </button>
                        </div>
                        <div class="strength-bar" style="margin-top:8px;">
                            <div class="strength-fill" id="modalStrengthFill"></div>
                        </div>
                        <div class="strength-label" id="modalStrengthLabel"></div>
                    </div>
                    <div class="modal-field">
                        <label>Confirmer le nouveau mot de passe <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input class="modal-pwd-input" type="password"
                                   name="confirm_password" id="confirmPwdField"
                                   placeholder="Répétez le mot de passe"
                                   oninput="checkPwdMatchModal()" required>
                            <button type="button" class="toggle-pwd-modal"
                                    onclick="togglePwd('confirmPwdField','eyeConfirm')">
                                <i class="fas fa-eye" id="eyeConfirm"></i>
                            </button>
                        </div>
                        <div id="pwdMatchMsg" class="pwd-match-msg"></div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel"
                            onclick="closeProfileModal()">Annuler</button>
                    <button type="submit" class="btn-modal-save" id="savePwdBtn">
                        <span>Modifier</span> <i class="fas fa-lock"></i>
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
