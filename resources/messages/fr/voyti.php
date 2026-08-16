<?php

declare(strict_types=1);

return [
    // SecurityController
    'voyti.security.invalid_login' => 'Identifiant ou mot de passe invalide',
    'voyti.security.account_blocked' => 'Votre compte a été bloqué',
    'voyti.security.need_email_confirmation' => 'Vous devez confirmer votre adresse e-mail',
    'voyti.security.logged_out' => 'Déconnecté',
    'voyti.security.password_expired' => 'Votre mot de passe a expiré. Veuillez en définir un nouveau.',
    'voyti.security.authenticated' => 'Authentifié',

    // RegistrationController
    'voyti.registration.disabled' => "L'inscription est désactivée",
    'voyti.registration.invalid_confirmation_link' => 'Lien de confirmation invalide',
    'voyti.registration.complete' => "Merci, l'inscription est maintenant terminée.",
    'voyti.registration.confirmation_link_invalid' => 'Le lien de confirmation est invalide ou a expiré.',
    'voyti.registration.email_confirmation_disabled' => "La confirmation par e-mail est désactivée",
    'voyti.registration.new_confirmation_sent' => 'Un nouveau lien de confirmation a été envoyé',
    'voyti.registration.account_created' => 'Compte créé.',
    'voyti.registration.account_created_check_email' => 'Compte créé. Consultez votre e-mail pour le lien de confirmation.',

    // RecoveryController
    'voyti.recovery.disabled' => 'La récupération de mot de passe est désactivée',
    'voyti.recovery.reset_disabled' => 'La réinitialisation du mot de passe est désactivée',
    'voyti.recovery.link_invalid' => 'Le lien de récupération est invalide ou a expiré',
    'voyti.recovery.password_changed' => 'Le mot de passe a été modifié',
    'voyti.recovery.password_previously_used' => 'Ce mot de passe a été utilisé récemment. Veuillez en choisir un autre.',

    // SettingsController
    'voyti.settings.not_authenticated' => 'Non authentifié',
    'voyti.settings.profile_updated' => 'Votre profil a été mis à jour',
    'voyti.settings.account_details_updated' => 'Les détails de votre compte ont été mis à jour',
    'voyti.settings.personal_info_removed' => 'Vos informations personnelles ont été supprimées',
    'voyti.settings.account_deleted' => 'Votre compte a été supprimé',
    'voyti.settings.email_changed' => 'Votre e-mail a été modifié',
    'voyti.settings.email_change_failed' => "Échec de la modification de l'e-mail",
    'voyti.settings.session_not_found' => 'Session introuvable',
    'voyti.settings.session_terminated' => 'La session a été déconnectée',
    'voyti.settings.password_previously_used' => 'Ce mot de passe a été utilisé récemment. Veuillez en choisir un autre.',

    // ProfileController
    'voyti.userProfile.forbidden' => 'Interdit',
    'voyti.userProfile.not_found' => 'Profil introuvable',

    // AdminController
    'voyti.admin.user_not_found' => 'Utilisateur introuvable',
    'voyti.admin.profile_details_updated' => 'Les détails du profil ont été mis à jour',
    'voyti.admin.user_confirmed' => "L'utilisateur a été confirmé",
    'voyti.admin.unable_to_confirm' => "Impossible de confirmer l'utilisateur",
    'voyti.admin.user_deleted' => "L'utilisateur a été supprimé",
    'voyti.admin.password_change_required' => "L'utilisateur devra changer son mot de passe à la prochaine connexion",
    'voyti.admin.error_occurred' => "Une erreur s'est produite",
    'voyti.admin.cannot_delete_self' => 'Vous ne pouvez pas supprimer votre propre compte',
    'voyti.admin.sessions_terminated' => 'Les sessions ont été terminées',
    'voyti.admin.user_status_changed' => "Le statut de l'utilisateur a été mis à jour",
    'voyti.admin.user_created' => "L'utilisateur a été créé",
    'voyti.admin.account_updated' => 'Le compte a été mis à jour',
    'voyti.admin.password_previously_used' => 'Ce mot de passe a été utilisé récemment. Veuillez en choisir un autre.',
    'voyti.admin.impersonate_identity_success' => 'Vous êtes maintenant connecté en tant que cet utilisateur',
    'voyti.admin.impersonate_identity_restored' => 'Vous avez été restauré à votre identité d\'origine',

    // RuleController
    'voyti.rule.added' => "La règle d'autorisation a été ajoutée",
    'voyti.rule.updated' => "La règle d'autorisation a été mise à jour",
    'voyti.rule.deleted' => "La règle d'autorisation a été supprimée",
    'voyti.rule.invalid_class' => 'Classe de règle invalide',

    'voyti.auth_item.not_found' => "Élément d'autorisation introuvable",
    'voyti.auth_item.created' => "L'élément d'autorisation a été créé",
    'voyti.auth_item.updated' => "L'élément d'autorisation a été mis à jour",
    'voyti.auth_item.deleted' => "L'élément d'autorisation a été supprimé",

    // PasswordRecoveryService
    'voyti.recovery.message_sent_if_exists' => "Si l'e-mail existe, un message de récupération a été envoyé",
    'voyti.recovery.message_sent' => 'Message de récupération envoyé',

    'voyti.validator.password_complexity' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',

    // Mail subjects
    'voyti.mail.welcome_subject' => 'Bienvenue sur {app}',
    'voyti.mail.confirmation_subject' => 'Confirmez votre compte sur {app}',
    'voyti.mail.reconfirmation_subject' => "Confirmez le changement d'e-mail sur {app}",
    'voyti.mail.recovery_subject' => 'Terminez la réinitialisation du mot de passe sur {app}',
    'voyti.mail.admin_notification_subject' => 'Nouvelle inscription sur {app}',

    // Mail view templates
    'voyti.mail.welcome_heading' => 'Bienvenue !',
    'voyti.mail.hello_username' => 'Bonjour {username},',
    'voyti.mail.account_created_successfully' => 'Votre compte a été créé avec succès.',
    'voyti.mail.email_change_heading' => "Confirmation du changement d'e-mail",
    'voyti.mail.click_to_confirm_email' => 'Cliquez sur le lien ci-dessous pour confirmer votre nouvelle adresse e-mail :',
    'voyti.mail.password_recovery_heading' => 'Récupération de mot de passe',
    'voyti.mail.click_to_reset_password' => 'Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :',
    'voyti.mail.confirm_account_heading' => 'Confirmez votre compte',
    'voyti.mail.click_to_confirm_account' => 'Cliquez sur le lien ci-dessous pour confirmer votre compte :',

    // Navigation / Menu
    'voyti.menu.dashboard' => 'Tableau de bord',
    'voyti.menu.userProfile' => 'Profil',
    'voyti.menu.account' => 'Compte',
    'voyti.menu.sessions' => 'Sessions',
    'voyti.menu.logout' => 'Se déconnecter',

    // Login view
    'voyti.view.login.title' => 'Connexion',
    'voyti.view.login.login_label' => "Nom d'utilisateur ou e-mail",
    'voyti.view.login.sign_in_button' => 'Connexion',
    'voyti.view.login.forgot_password' => 'Mot de passe oublié ?',
    'voyti.view.login.register_link' => "S'inscrire",
    'voyti.view.login.password_label' => 'Mot de passe',
    'voyti.view.login.remember_me_label' => 'Se souvenir de moi',
    'voyti.view.login.social_divider' => 'Ou se connecter avec',

    // Registration views
    'voyti.view.registration.register_title' => 'Créer un compte',
    'voyti.view.registration.data_processing_consent_label' => "J'accepte le traitement de mes données personnelles",
    'voyti.view.registration.register_button' => "S'inscrire",
    'voyti.view.registration.already_have_account' => 'Vous avez déjà un compte ?',
    'voyti.view.registration.resend_title' => 'Renvoyer le lien de confirmation',

    // Recovery views
    'voyti.view.recovery.request_title' => 'Récupérer le mot de passe',
    'voyti.view.recovery.send_link_button' => 'Envoyer le lien de récupération',
    'voyti.view.recovery.back_to_login' => 'Retour à la connexion',
    'voyti.view.recovery.reset_title' => 'Réinitialiser le mot de passe',
    'voyti.view.recovery.reset_button' => 'Réinitialiser le mot de passe',

    // Settings views
    'voyti.view.settings.dashboard_title' => 'Tableau de bord',
    'voyti.view.settings.welcome' => 'Bienvenue, {name} !',
    'voyti.view.settings.member_since' => 'Membre depuis',
    'voyti.view.edit_profile.title' => 'Modifier le profil',
    'voyti.view.userProfile.title' => 'Aperçu du profil',
    'voyti.view.account.title' => 'Paramètres du compte',
    'voyti.view.privacy.title' => 'Confidentialité',
    'voyti.view.privacy.delete_account' => 'Supprimer mon compte',
    'voyti.view.settings.privacy' => 'Confidentialité',

    // GDPR views
    'voyti.view.anonymize.title' => 'Anonymiser mon compte',
    'voyti.view.anonymize.warning' => 'Attention : cette action anonymise votre compte (votre e-mail et votre nom d\'utilisateur sont remplacés) et bloque définitivement l\'accès. Cela ne peut pas être annulé.',
    'voyti.view.anonymize.button' => 'Anonymiser mon compte',

    // Account settings (2FA)

    // Delete account view
    'voyti.view.delete_account.title' => 'Supprimer mon compte',
    'voyti.view.delete_account.warning' => 'Attention : cette action supprime définitivement votre compte et toutes les données associées. Cela ne peut pas être annulé.',
    'voyti.view.delete_account.confirm_label' => 'Je comprends que cette action est irréversible',
    'voyti.view.delete_account.button' => 'Supprimer mon compte',

    // Admin views
    'voyti.view.admin.title' => 'Utilisateurs',
    'voyti.view.admin.create_user_title' => 'Créer un utilisateur',
    'voyti.view.admin.create_user_link' => 'Créer un utilisateur',
    'voyti.view.admin.update_user_title' => "Modifier l'utilisateur : {username}",
    'voyti.view.admin.update_profile_title' => 'Modifier le profil',
    'voyti.view.admin.registered_label' => 'Inscrit',
    'voyti.view.admin.sessions' => 'Gestion des sessions',
    'voyti.view.admin.sessions_link' => 'Sessions',
    'voyti.view.admin.terminate_sessions' => 'Terminer les sessions',
    'voyti.view.admin.impersonate_button' => 'Usurper',
    'voyti.view.admin.restore_button' => 'Restaurer',
    'voyti.view.admin.impersonating_banner' => 'Vous êtes actuellement connecté en tant que cet utilisateur. Cliquez sur Restaurer pour revenir à {username}.',

    // RBAC views
    'voyti.view.assignments.title' => 'Attributions',
    'voyti.view.rule.title' => 'Règles',
    'voyti.view.rule.create_title' => 'Créer une règle',
    'voyti.view.rule.create_link' => 'Créer une règle',
    'voyti.view.rule.update_title' => 'Modifier la règle',
    'voyti.view.rule.class_label' => 'Classe de règle',
    'voyti.view.permission.title' => 'Permissions',
    'voyti.view.permission.create_title' => 'Créer une permission',
    'voyti.view.permission.create_link' => 'Créer une permission',
    'voyti.view.permission.update_title' => 'Modifier la permission : {name}',
    'voyti.view.role.title' => 'Rôles',
    'voyti.view.role.create_title' => 'Créer un rôle',
    'voyti.view.role.create_link' => 'Créer un rôle',
    'voyti.view.role.update_title' => 'Modifier le rôle : {name}',

    // Dashboard view
    'voyti.view.dashboard.title' => 'Tableau de bord',
    'voyti.view.dashboard.users_total' => 'Utilisateurs au total',
    'voyti.view.dashboard.users_blocked' => 'Utilisateurs bloqués',
    'voyti.view.dashboard.users_unconfirmed' => 'Utilisateurs non confirmés',
    'voyti.view.dashboard.roles' => 'Rôles',
    'voyti.view.dashboard.permissions' => 'Permissions',
    'voyti.view.dashboard.rules' => 'Règles',
    'voyti.view.dashboard.recent_activity' => 'Activité récente',
    'voyti.view.dashboard.no_recent_activity' => 'Aucune activité récente.',
    'voyti.view.dashboard.new_registrations' => 'Nouvelles inscriptions',
    'voyti.view.dashboard.active_sessions' => 'Sessions actives',
    'voyti.view.dashboard.last_1d' => 'Dernières 24 heures',
    'voyti.view.dashboard.last_7d' => '7 derniers jours',
    'voyti.view.dashboard.last_lifespan' => '{days} derniers jours',
    'voyti.view.dashboard.recommended_addons' => 'Extensions recommandées',
    'voyti.view.dashboard.view_on_packagist' => 'Voir sur Packagist',
    'voyti.view.dashboard.documentation' => 'Documentation',
    'voyti.view.dashboard.package_api_label' => 'API REST',
    'voyti.view.dashboard.package_api_description' => 'Partagez votre système utilisateur comme une API REST moderne avec authentification intégrée.',
    'voyti.view.dashboard.package_gdpr_label' => 'Conformité RGPD',
    'voyti.view.dashboard.package_gdpr_description' => 'Les utilisateurs téléchargent, suppriment ou anonymisent leurs données. conformité simplifiée.',
    'voyti.view.dashboard.package_social_auth_label' => 'Connexion sociale',
    'voyti.view.dashboard.package_social_auth_description' => 'Sans mots de passe. les utilisateurs se connectent avec Google, GitHub, Facebook et autres en un clic.',
    'voyti.view.dashboard.package_recaptcha_label' => 'Protection reCAPTCHA',
    'voyti.view.dashboard.package_recaptcha_description' => 'Protégez l\'inscription et la récupération de mot de passe contre les abus avec Google reCAPTCHA v2 et v3.',
    'voyti.view.dashboard.package_2fa_email_label' => 'Authentification à deux facteurs (e-mail)',
    'voyti.view.dashboard.package_2fa_email_description' => 'Sécurité supplémentaire avec codes e-mail. protège instantanément contre le vol de mots de passe.',
    'voyti.view.dashboard.package_2fa_totp_label' => 'Authentification à deux facteurs (TOTP)',
    'voyti.view.dashboard.package_2fa_totp_description' => 'Sécurité de niveau militaire avec applications d\'authentification. sécurisé même si le mot de passe est compromis.',
    'voyti.view.dashboard.package_2fa_webauthn_label' => 'Authentification à deux facteurs (WebAuthn)',
    'voyti.view.dashboard.package_2fa_webauthn_description' => 'L\'avenir de la sécurité. authentification biométrique ou clé de sécurité pour une protection maximale.',

    // Audit log view
    'voyti.view.audit_log.title' => "Journal d'audit",
    'voyti.view.audit_log.created_header' => 'Quand',
    'voyti.view.audit_log.actor_header' => 'Acteur',
    'voyti.view.audit_log.action_header' => 'Action',
    'voyti.view.audit_log.target_header' => 'Cible',
    'voyti.view.audit_log.context_header' => 'Détails',

    // Assignments
    'voyti.view.assignments.assigned' => 'Attribué',
    'voyti.view.assignments.available' => 'Disponible',
    'voyti.view.assignments.update' => 'Mettre à jour les attributions',
    'voyti.view.info_link' => 'Infos',

    // Sessions
    'voyti.view.sessions.ip' => 'Adresse IP',
    'voyti.view.sessions.user_agent' => 'Agent utilisateur',
    'voyti.view.sessions.last_seen' => 'Dernière activité',
    'voyti.view.sessions.title' => 'Sessions actives',
    'voyti.view.sessions.this_device' => 'Cet appareil',
    'voyti.view.sessions.none' => 'Aucune session active.',
    'voyti.view.sessions.revoke_button' => 'Révoquer',
    'voyti.view.sessions.revoked' => 'Révoquée',
    'voyti.view.sessions.active' => 'Active',

    // Pagination
    'voyti.view.filter_button' => 'Filtrer',
    'voyti.view.per_page_label' => 'Par page',
    'voyti.view.pagination_navigation' => 'Navigation des pages',
    'voyti.view.previous' => 'Précédent',
    'voyti.view.next' => 'Suivant',

    // Common view labels
    'voyti.view.username_label' => "Nom d'utilisateur",
    'voyti.view.email_label' => 'E-mail',
    'voyti.view.password_label' => 'Mot de passe',
    'voyti.view.password_repeat_label' => 'Confirmer le mot de passe',
    'voyti.view.new_password_label' => 'Nouveau mot de passe',
    'voyti.view.new_password_repeat_label' => 'Confirmer le nouveau mot de passe',
    'voyti.view.current_password_label' => 'Mot de passe actuel',
    'voyti.view.name_label' => 'Nom',
    'voyti.view.description_label' => 'Description',
    'voyti.view.bio_label' => 'Bio',
    'voyti.view.bio_variables_hint' => 'Vous pouvez utiliser les espaces réservés {age} et {location}. Ils seront remplacés par votre âge calculé et votre localisation lorsqu\'ils sont disponibles.',
    'voyti.view.public_email_label' => 'E-mail public',
    'voyti.view.not_set' => 'Non défini',
    'voyti.view.website_label' => 'Site web',
    'voyti.view.location_label' => 'Localisation',
    'voyti.view.gravatar_email_label' => 'E-mail Gravatar',
    'voyti.view.timezone_label' => 'Fuseau horaire',
    'voyti.view.birthday_label' => 'Date de naissance',

    // Common table headers
    'voyti.view.id_header' => 'ID',
    'voyti.view.username_header' => "Nom d'utilisateur",
    'voyti.view.email_header' => 'E-mail',
    'voyti.view.status_header' => 'Statut',
    'voyti.view.name_header' => 'Nom',
    'voyti.view.description_header' => 'Description',
    'voyti.view.children_header' => 'Enfants',
    'voyti.view.actions_header' => 'Actions',

    // User status
    'voyti.view.status_blocked' => 'Bloqué',
    'voyti.view.status_active' => 'Actif',
    'voyti.view.status_pending' => 'En attente',

    // Common buttons / links
    'voyti.view.create_button' => 'Créer',
    'voyti.view.save_button' => 'Enregistrer',
    'voyti.view.reset_button' => 'Réinitialiser',
    'voyti.view.update_button' => 'Mettre à jour',
    'voyti.view.update_link' => 'Mettre à jour',
    'voyti.view.delete_button' => 'Supprimer',
    'voyti.view.confirm_button' => 'Confirmer',
    'voyti.view.unblock_button' => 'Débloquer',
    'voyti.view.block_button' => 'Bloquer',
    'voyti.view.force_password_change_button' => 'Forcer le changement de mot de passe',
    'voyti.view.reset_password_button' => 'Envoyer le lien de réinitialisation du mot de passe',
    'voyti.view.update_profile_link' => 'Mettre à jour le profil',
    'voyti.view.send_button' => 'Envoyer',

    // Widgets

    // Shared message view
    'voyti.view.go_home' => "Retour à l'accueil",
];
