<?php

declare(strict_types=1);

return [
    // SecurityController
    'voyti.security.invalid_login' => 'Ungültiger Login oder falsches Passwort',
    'voyti.security.account_blocked' => 'Ihr Konto wurde gesperrt',
    'voyti.security.need_email_confirmation' => 'Sie müssen Ihre E-Mail-Adresse bestätigen',
    'voyti.security.logged_out' => 'Abgemeldet',
    'voyti.security.password_expired' => 'Ihr Passwort ist abgelaufen. Bitte legen Sie ein neues fest.',
    'voyti.security.authenticated' => 'Authentifiziert',

    // RegistrationController
    'voyti.registration.disabled' => 'Die Registrierung ist deaktiviert',
    'voyti.registration.invalid_confirmation_link' => 'Ungültiger Bestätigungslink',
    'voyti.registration.complete' => 'Vielen Dank, die Registrierung ist nun abgeschlossen.',
    'voyti.registration.confirmation_link_invalid' => 'Der Bestätigungslink ist ungültig oder abgelaufen.',
    'voyti.registration.email_confirmation_disabled' => 'Die E-Mail-Bestätigung ist deaktiviert',
    'voyti.registration.new_confirmation_sent' => 'Ein neuer Bestätigungslink wurde gesendet',

    // RecoveryController
    'voyti.recovery.disabled' => 'Die Passwortwiederherstellung ist deaktiviert',
    'voyti.recovery.reset_disabled' => 'Das Zurücksetzen des Passworts ist deaktiviert',
    'voyti.recovery.link_invalid' => 'Der Wiederherstellungslink ist ungültig oder abgelaufen',
    'voyti.recovery.password_changed' => 'Das Passwort wurde geändert',
    'voyti.recovery.password_previously_used' => 'Dieses Passwort wurde kürzlich bereits verwendet. Bitte wählen Sie ein anderes.',

    // SettingsController
    'voyti.settings.not_authenticated' => 'Nicht authentifiziert',
    'voyti.settings.user_not_found' => 'Benutzer nicht gefunden',
    'voyti.settings.profile_updated' => 'Ihr Profil wurde aktualisiert',
    'voyti.settings.account_details_updated' => 'Ihre Kontodaten wurden aktualisiert',
    'voyti.settings.personal_info_removed' => 'Ihre persönlichen Daten wurden entfernt',
    'voyti.settings.account_deleted' => 'Ihr Konto wurde gelöscht',
    'voyti.settings.gdpr_consent_saved' => 'DSGVO-Einwilligung wurde gespeichert',
    'voyti.settings.email_changed' => 'Ihre E-Mail-Adresse wurde geändert',
    'voyti.settings.email_change_failed' => 'E-Mail-Adresse konnte nicht geändert werden',
    'voyti.settings.network_disconnected' => 'Das Netzwerk wurde getrennt',
    'voyti.settings.network_not_found' => 'Netzwerk nicht gefunden',
    'voyti.settings.two_factor_enabled' => 'Zwei-Faktor-Authentifizierung wurde aktiviert',
    'voyti.settings.two_factor_disabled' => 'Zwei-Faktor-Authentifizierung wurde deaktiviert',
    'voyti.settings.session_history_disabled' => 'Sitzungsverlauf ist deaktiviert',
    'voyti.settings.session_not_found' => 'Sitzung nicht gefunden',
    'voyti.settings.session_terminated' => 'Sitzung wurde abgemeldet',
    'voyti.settings.password_previously_used' => 'Dieses Passwort wurde kürzlich bereits verwendet. Bitte wählen Sie ein anderes.',

    // ProfileController
    'voyti.userProfile.forbidden' => 'Verboten',
    'voyti.userProfile.not_found' => 'Profil nicht gefunden',

    // AdminController
    'voyti.admin.user_not_found' => 'Benutzer nicht gefunden',
    'voyti.admin.profile_details_updated' => 'Profildaten wurden aktualisiert',
    'voyti.admin.user_confirmed' => 'Benutzer wurde bestätigt',
    'voyti.admin.unable_to_confirm' => 'Benutzer konnte nicht bestätigt werden',
    'voyti.admin.user_deleted' => 'Benutzer wurde gelöscht',
    'voyti.admin.password_change_required' => 'Benutzer muss beim nächsten Login das Passwort ändern',
    'voyti.admin.error_occurred' => 'Es ist ein Fehler aufgetreten',
    'voyti.admin.cannot_delete_self' => 'Sie können Ihr eigenes Konto nicht löschen',
    'voyti.admin.sessions_terminated' => 'Sitzungen wurden beendet',
    'voyti.admin.user_status_changed' => 'Benutzerstatus wurde aktualisiert',
    'voyti.admin.user_created' => 'Benutzer wurde erstellt',
    'voyti.admin.account_updated' => 'Konto wurde aktualisiert',
    'voyti.admin.password_previously_used' => 'Dieses Passwort wurde kürzlich bereits verwendet. Bitte wählen Sie ein anderes.',

    // RuleController
    'voyti.rule.added' => 'Autorisierungsregel wurde hinzugefügt',
    'voyti.rule.updated' => 'Autorisierungsregel wurde aktualisiert',
    'voyti.rule.deleted' => 'Autorisierungsregel wurde entfernt',
    'voyti.rule.invalid_class' => 'Ungültige Regelklasse',

    'voyti.auth_item.not_found' => 'Autorisierungselement nicht gefunden',
    'voyti.auth_item.created' => 'Autorisierungselement wurde erstellt',
    'voyti.auth_item.updated' => 'Autorisierungselement wurde aktualisiert',
    'voyti.auth_item.deleted' => 'Autorisierungselement wurde entfernt',

    // API AdminController
    'voyti.api.not_found' => 'Nicht gefunden',
    'voyti.api.user_created' => 'Benutzer erstellt',
    'voyti.api.user_updated' => 'Benutzer aktualisiert',
    'voyti.api.password_previously_used' => 'Dieses Passwort wurde kürzlich bereits verwendet. Bitte wählen Sie ein anderes.',
    'voyti.api.user_deleted' => 'Benutzer gelöscht',

    // PasswordRecoveryService
    'voyti.recovery.message_sent_if_exists' => 'Falls die E-Mail existiert, wurde eine Wiederherstellungsnachricht gesendet',
    'voyti.recovery.message_sent' => 'Wiederherstellungsnachricht gesendet',

    // TwoFactorCodeValidator
    'voyti.validator.two_factor_not_configured' => 'Die Zwei-Faktor-Authentifizierung ist nicht konfiguriert.',
    'voyti.validator.two_factor_library_missing' => 'Die 2FA-Bibliothek (chillerlan/php-authenticator) ist nicht installiert.',
    'voyti.validator.invalid_verification_code' => 'Ungültiger Verifizierungscode.',
    'voyti.validator.password_complexity' => 'Das Passwort muss mindestens einen Großbuchstaben, einen Kleinbuchstaben, eine Ziffer und ein Sonderzeichen enthalten.',
    'voyti.validator.two_factor_enabled' => 'Die Zwei-Faktor-Authentifizierung wurde aktiviert.',
    'voyti.validator.invalid_code_with_time' => 'Ungültiger Code. Bitte versuchen Sie es innerhalb von {timeDuration} Sekunden erneut.',
    'voyti.validator.invalid_two_factor_code_with_time' => 'Ungültiger Zwei-Faktor-Authentifizierungscode. Bitte versuchen Sie es innerhalb von {timeDuration} Sekunden erneut.',

    // Mail subjects
    'voyti.mail.welcome_subject' => 'Willkommen bei {app}',
    'voyti.mail.confirmation_subject' => 'Konto auf {app} bestätigen',
    'voyti.mail.reconfirmation_subject' => 'E-Mail-Änderung auf {app} bestätigen',
    'voyti.mail.recovery_subject' => 'Passwort-Zurücksetzung auf {app} abschließen',
    'voyti.mail.two_factor_subject' => 'Code für die Zwei-Faktor-Authentifizierung auf {app}',
    'voyti.mail.admin_notification_subject' => 'Neue Benutzerregistrierung auf {app}',

    // Mail view templates
    'voyti.mail.welcome_heading' => 'Willkommen!',
    'voyti.mail.hello_username' => 'Hallo {username},',
    'voyti.mail.account_created_successfully' => 'Ihr Konto wurde erfolgreich erstellt.',
    'voyti.mail.account_deleted_heading' => 'Konto gelöscht',
    'voyti.mail.account_deleted_gdpr' => 'Ihr Konto wurde gemäß der DSGVO gelöscht.',
    'voyti.mail.email_change_heading' => 'Bestätigung der E-Mail-Änderung',
    'voyti.mail.click_to_confirm_email' => 'Klicken Sie auf den folgenden Link, um Ihre neue E-Mail-Adresse zu bestätigen:',
    'voyti.mail.password_recovery_heading' => 'Passwortwiederherstellung',
    'voyti.mail.click_to_reset_password' => 'Klicken Sie auf den folgenden Link, um Ihr Passwort zurückzusetzen:',
    'voyti.mail.confirm_account_heading' => 'Bestätigen Sie Ihr Konto',
    'voyti.mail.click_to_confirm_account' => 'Klicken Sie auf den folgenden Link, um Ihr Konto zu bestätigen:',
    'voyti.mail.twofactor_heading' => 'Zwei-Faktor-Authentifizierungscode',
    'voyti.mail.your_twofactor_code' => 'Ihr Zwei-Faktor-Authentifizierungscode lautet:',

    // Navigation / Menu
    'voyti.menu.userProfile' => 'Profil',
    'voyti.menu.account' => 'Konto',
    'voyti.menu.networks' => 'Netzwerke',
    'voyti.menu.sessions' => 'Sitzungen',
    'voyti.menu.two_factor' => 'Zwei-Faktor-Auth',
    'voyti.menu.logout' => 'Abmelden',

    // Login view
    'voyti.view.login.title' => 'Anmelden',
    'voyti.view.login.login_label' => 'Benutzername oder E-Mail',
    'voyti.view.login.remember_me' => 'Angemeldet bleiben',
    'voyti.view.login.sign_in_button' => 'Anmelden',
    'voyti.view.login.forgot_password' => 'Passwort vergessen?',
    'voyti.view.login.register_link' => 'Registrieren',
    'voyti.view.login.password_label' => 'Passwort',
    'voyti.view.login.remember_me_label' => 'Erinnern Sie sich an mich',

    // Two-factor confirm view
    'voyti.view.two_factor.title' => 'Zwei-Faktor-Authentifizierung',
    'voyti.view.two_factor_google.button_label' => 'Google Authenticator',
    'voyti.view.two_factor.code_label' => 'Authentifizierungscode',
    'voyti.view.two_factor.verify_button' => 'Überprüfen',
    'voyti.view.two_factor.enabled_with_method' => 'Zwei-Faktor-Authentifizierung per {method} ist aktiviert',
    'voyti.view.two_factor.disable' => 'Deaktivieren',
    'voyti.view.two_factor.disable_confirm_intro' => 'Um die Zwei-Faktor-Authentifizierung zu deaktivieren, müssen wir bestätigen, dass Sie es wirklich sind. Ein Bestätigungscode wird an Ihre E-Mail-Adresse gesendet.',
    'voyti.view.two_factor.disable_send_code' => 'Code zum Deaktivieren senden',
    'voyti.view.two_factor.scan_qr' => 'Scannen Sie diesen QR-Code mit Ihrer Authenticator-App',
    'voyti.view.two_factor.manual_entry' => 'Oder geben Sie diesen Schlüssel manuell ein:',
    'voyti.view.two_factor.qr_unavailable' => 'QR-Code ist nicht verfügbar',
    'voyti.view.two_factor.enter_code' => 'Geben Sie den Verifizierungscode ein',
    'voyti.view.two_factor.enable' => 'Aktivieren',
    'voyti.view.two_factor.verify' => 'Überprüfen',
    'voyti.view.two_factor.renew' => 'Erneuern',
    'voyti.view.two_factor.renew_error' => 'Es konnte kein neuer Schlüssel erzeugt werden. Bitte versuchen Sie es erneut.',
    'voyti.view.two_factor.loading' => 'Wird geladen…',
    'voyti.view.two_factor.already_enabled' => 'Die Zwei-Faktor-Authentifizierung ist bereits aktiviert.',
    'voyti.view.two_factor.backup_codes_title' => 'Backup-Codes',
    'voyti.view.two_factor.backup_codes_intro' => 'Bewahren Sie diese einmaligen Backup-Codes sicher auf. Jeder kann einmal verwendet werden, um sich anzumelden, falls Sie keinen Zugriff mehr auf Ihren Authenticator oder Ihre E-Mail haben.',
    'voyti.view.two_factor.backup_codes_continue' => 'Weiter',
    'voyti.view.two_factor.backup_code_hint' => 'Kein Zugriff mehr auf Ihr Gerät oder Ihre E-Mail? Sie können stattdessen einen Ihrer Backup-Codes eingeben.',
    'voyti.view.two_factor.regenerate_backup_codes' => 'Backup-Codes neu generieren',
    'voyti.view.two_factor.regenerate_backup_codes_intro' => 'Beim Generieren neuer Backup-Codes werden alle bestehenden ungültig. Geben Sie Ihren aktuellen Bestätigungscode oder einen Backup-Code ein, um fortzufahren.',
    'voyti.view.two_factor.no_backup_codes_remaining' => 'Sie haben keine Backup-Codes mehr übrig. Generieren Sie einen neuen Satz, damit Sie den Zugriff auch bei Verlust Ihres Geräts wiederherstellen können.',
    'voyti.view.two_factor_email.title' => 'Zwei-Faktor-Authentifizierung per E-Mail',
    'voyti.view.two_factor_email.button_label' => 'E-Mail',
    'voyti.view.two_factor_email.method_name' => 'E-Mail',
    'voyti.view.two_factor_email.enter_code' => 'Geben Sie den per E-Mail gesendeten Verifizierungscode ein',
    'voyti.view.two_factor_email.confirm_intro' => 'Ein Verifizierungscode wird an die unten stehende E-Mail-Adresse gesendet.',
    'voyti.view.two_factor_email.send_button' => 'Code senden',
    // Registration views
    'voyti.view.registration.register_title' => 'Konto erstellen',
    'voyti.view.registration.gdpr_consent_label' => 'Ich stimme der Verarbeitung meiner personenbezogenen Daten zu',
    'voyti.view.registration.register_button' => 'Registrieren',
    'voyti.view.registration.already_have_account' => 'Bereits ein Konto?',
    'voyti.view.registration.resend_title' => 'Bestätigungslink erneut senden',
    'voyti.view.registration.connect_title' => 'Konto verbinden',
    'voyti.view.registration.connect_provider' => 'Verbinden Sie Ihr {provider}-Konto',
    'voyti.view.registration.connect_message' => 'Sie können Ihr Social-Media-Konto verbinden oder ein neues Konto registrieren.',
    'voyti.view.registration.connect_login' => 'Anmelden',
    'voyti.view.registration.connect_register' => 'Registrieren',

    // Recovery views
    'voyti.view.recovery.request_title' => 'Passwort wiederherstellen',
    'voyti.view.recovery.send_link_button' => 'Wiederherstellungslink senden',
    'voyti.view.recovery.back_to_login' => 'Zurück zur Anmeldung',
    'voyti.view.recovery.reset_title' => 'Passwort zurücksetzen',
    'voyti.view.recovery.reset_button' => 'Passwort zurücksetzen',

    // UserProfile view
    'voyti.view.userProfile.email_label' => 'E-Mail',
    'voyti.view.userProfile.location_label' => 'Ort',
    'voyti.view.userProfile.bio_label' => 'Bio',

    // Settings views
    'voyti.view.edit_profile.title' => 'Profil bearbeiten',
    'voyti.view.userProfile.title' => 'Profilvorschau',
    'voyti.view.account.title' => 'Kontoeinstellungen',
    'voyti.view.networks.title' => 'Netzwerke',
    'voyti.view.privacy.title' => 'Datenschutz',
    'voyti.view.privacy.manage_gdpr_consent' => 'DSGVO-Einwilligung verwalten',
    'voyti.view.privacy.export_data' => 'Meine Daten exportieren',
    'voyti.view.privacy.anonymize_data' => 'Mein Konto anonymisieren',
    'voyti.view.privacy.delete_account' => 'Mein Konto löschen',
    'voyti.view.settings.privacy' => 'Datenschutz',

    // GDPR views
    'voyti.view.gdpr.consent_title' => 'DSGVO-Einwilligung',
    'voyti.view.gdpr.consent_label' => 'Ich stimme der Verarbeitung meiner personenbezogenen Daten zu',
    'voyti.view.gdpr.consent_locked' => 'Sie haben Ihre Zustimmung am {date} gegeben. Dies kann nicht rückgängig gemacht werden.',
    'voyti.view.anonymize.title' => 'Mein Konto anonymisieren',
    'voyti.view.anonymize.warning' => 'Warnung: Diese Aktion anonymisiert Ihr Konto (E-Mail und Benutzername werden ersetzt) und sperrt den Zugriff dauerhaft. Dies kann nicht rückgängig gemacht werden.',
    'voyti.view.anonymize.confirm_label' => 'Ich verstehe, dass diese Aktion nicht rückgängig gemacht werden kann',
    'voyti.view.anonymize.button' => 'Mein Konto anonymisieren',

    // Account settings (2FA)
    'voyti.view.account.two_factor_title' => 'Zwei-Faktor-Authentifizierung',
    'voyti.view.account.two_factor_enabled' => '2FA ist aktiviert',
    'voyti.view.account.disable_two_factor' => '2FA deaktivieren',
    'voyti.view.account.enable_two_factor' => '2FA aktivieren',

    // Delete account view
    'voyti.view.delete_account.title' => 'Mein Konto löschen',
    'voyti.view.delete_account.warning' => 'Warnung: Diese Aktion löscht Ihr Konto und alle zugehörigen Daten endgültig. Dies kann nicht rückgängig gemacht werden.',
    'voyti.view.delete_account.confirm_label' => 'Ich verstehe, dass diese Aktion nicht rückgängig gemacht werden kann',
    'voyti.view.delete_account.button' => 'Mein Konto löschen',

    // Admin views
    'voyti.view.admin.title' => 'Benutzer',
    'voyti.view.admin.create_user_title' => 'Benutzer erstellen',
    'voyti.view.admin.create_user_link' => 'Benutzer erstellen',
    'voyti.view.admin.update_user_title' => 'Benutzer aktualisieren: {username}',
    'voyti.view.admin.update_profile_title' => 'Profil aktualisieren',
    'voyti.view.admin.info_link' => 'Info',
    'voyti.view.admin.registered_label' => 'Registriert',
    'voyti.view.admin.session_history' => 'Sitzungsverlauf',
    'voyti.view.admin.sessions_link' => 'Sitzungen',
    'voyti.view.admin.terminate_sessions' => 'Sitzungen beenden',

    // RBAC views
    'voyti.view.assignments.title' => 'Zuweisungen',
    'voyti.view.rule.title' => 'Regeln',
    'voyti.view.rule.create_title' => 'Regel erstellen',
    'voyti.view.rule.create_link' => 'Regel erstellen',
    'voyti.view.rule.update_title' => 'Regel aktualisieren',
    'voyti.view.rule.class_label' => 'Regelklasse',
    'voyti.view.permission.title' => 'Berechtigungen',
    'voyti.view.permission.create_title' => 'Berechtigung erstellen',
    'voyti.view.permission.create_link' => 'Berechtigung erstellen',
    'voyti.view.permission.update_title' => 'Berechtigung aktualisieren: {name}',
    'voyti.view.role.title' => 'Rollen',
    'voyti.view.role.create_title' => 'Rolle erstellen',
    'voyti.view.role.create_link' => 'Rolle erstellen',
    'voyti.view.role.update_title' => 'Rolle aktualisieren: {name}',
    'voyti.view.audit_log.title' => 'Audit-Log',
    'voyti.view.audit_log.created_header' => 'Wann',
    'voyti.view.audit_log.actor_header' => 'Akteur',
    'voyti.view.audit_log.action_header' => 'Aktion',
    'voyti.view.audit_log.target_header' => 'Ziel',
    'voyti.view.audit_log.context_header' => 'Details',

    // Assignments
    'voyti.view.assignments.assigned' => 'Zugewiesen',
    'voyti.view.assignments.available' => 'Verfügbar',
    'voyti.view.assignments.update' => 'Zuweisungen aktualisieren',
    'voyti.view.info_link' => 'Info',

    // Session history
    'voyti.view.session_history.title' => 'Sitzungsverlauf',
    'voyti.view.session_history.ip' => 'IP-Adresse',
    'voyti.view.session_history.user_agent' => 'Benutzeragent',
    'voyti.view.session_history.created' => 'Erstellt',
    'voyti.view.sessions.title' => 'Aktive Sitzungen',
    'voyti.view.sessions.this_device' => 'Dieses Gerät',
    'voyti.view.sessions.none' => 'Keine aktiven Sitzungen.',

    // Pagination
    'voyti.view.filter_button' => 'Filtern',
    'voyti.view.pagination_navigation' => 'Seitennavigation',
    'voyti.view.previous' => 'Zurück',
    'voyti.view.next' => 'Weiter',

    // Common view labels
    'voyti.view.username_label' => 'Benutzername',
    'voyti.view.email_label' => 'E-Mail',
    'voyti.view.password_label' => 'Passwort',
    'voyti.view.password_repeat_label' => 'Passwort bestätigen',
    'voyti.view.new_password_label' => 'Neues Passwort',
    'voyti.view.new_password_repeat_label' => 'Neues Passwort bestätigen',
    'voyti.view.current_password_label' => 'Aktuelles Passwort',
    'voyti.view.name_label' => 'Name',
    'voyti.view.description_label' => 'Beschreibung',
    'voyti.view.bio_label' => 'Bio',
    'voyti.view.public_email_label' => 'Öffentliche E-Mail',
    'voyti.view.not_set' => 'Nicht festgelegt',
    'voyti.view.website_label' => 'Webseite',
    'voyti.view.location_label' => 'Ort',
    'voyti.view.gravatar_email_label' => 'Gravatar-E-Mail',
    'voyti.view.timezone_label' => 'Zeitzone',
    'voyti.view.birthday_label' => 'Geburtsdatum',
    'voyti.view.password_keep_label' => 'Passwort (leer lassen zum Beibehalten)',

    // Common table headers
    'voyti.view.id_header' => 'ID',
    'voyti.view.username_header' => 'Benutzername',
    'voyti.view.email_header' => 'E-Mail',
    'voyti.view.status_header' => 'Status',
    'voyti.view.name_header' => 'Name',
    'voyti.view.description_header' => 'Beschreibung',
    'voyti.view.children_header' => 'Unterelemente',
    'voyti.view.actions_header' => 'Aktionen',

    // User status
    'voyti.view.status_blocked' => 'Gesperrt',
    'voyti.view.status_active' => 'Aktiv',
    'voyti.view.status_pending' => 'Ausstehend',

    // Common buttons / links
    'voyti.view.create_button' => 'Erstellen',
    'voyti.view.save_button' => 'Speichern',
    'voyti.view.reset_button' => 'Zurücksetzen',
    'voyti.view.update_button' => 'Aktualisieren',
    'voyti.view.update_link' => 'Aktualisieren',
    'voyti.view.delete_button' => 'Löschen',
    'voyti.view.confirm_button' => 'Bestätigen',
    'voyti.view.unblock_button' => 'Entsperren',
    'voyti.view.block_button' => 'Sperren',
    'voyti.view.force_password_change_button' => 'Passwortänderung erzwingen',
    'voyti.view.reset_password_button' => 'Passwort-Reset-Link senden',
    'voyti.view.update_profile_link' => 'Profil aktualisieren',
    'voyti.view.send_button' => 'Senden',
    'voyti.view.connect_button' => 'Verbinden',
    'voyti.view.disconnect_button' => 'Trennen',

    // Confirmation prompts
    'voyti.view.delete_user_confirm' => 'Diesen Benutzer löschen?',

    // Widgets
    'voyti.view.sessions.active_sessions' => 'Aktive Sitzungen',
    'voyti.view.sessions.ip_label' => 'IP:',
    'voyti.view.sessions.last_activity_label' => 'Letzte Aktivität:',
    'voyti.view.connect.no_accounts' => 'Keine verbundenen Konten',
    'voyti.view.networks.no_networks' => 'Keine verbundenen Netzwerke',
    'voyti.view.login.link' => 'Anmelden',
    'voyti.view.logout.link' => 'Abmelden',

    // Shared message view
    'voyti.view.go_home' => 'Zur Startseite',
];
