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
    'voyti.security.social_auth_cancelled' => 'Die Anmeldung über den sozialen Anbieter wurde abgebrochen.',

    // RegistrationController
    'voyti.registration.disabled' => 'Die Registrierung ist deaktiviert',
    'voyti.registration.invalid_confirmation_link' => 'Ungültiger Bestätigungslink',
    'voyti.registration.complete' => 'Vielen Dank, die Registrierung ist nun abgeschlossen.',
    'voyti.registration.confirmation_link_invalid' => 'Der Bestätigungslink ist ungültig oder abgelaufen.',
    'voyti.registration.email_confirmation_disabled' => 'Die E-Mail-Bestätigung ist deaktiviert',
    'voyti.registration.new_confirmation_sent' => 'Ein neuer Bestätigungslink wurde gesendet',
    'voyti.registration.account_created' => 'Konto erstellt.',
    'voyti.registration.account_created_check_email' => 'Konto erstellt. Bitte prüfen Sie Ihre E-Mail für den Bestätigungslink.',

    // RecoveryController
    'voyti.recovery.disabled' => 'Die Passwortwiederherstellung ist deaktiviert',
    'voyti.recovery.reset_disabled' => 'Das Zurücksetzen des Passworts ist deaktiviert',
    'voyti.recovery.link_invalid' => 'Der Wiederherstellungslink ist ungültig oder abgelaufen',
    'voyti.recovery.password_changed' => 'Das Passwort wurde geändert',
    'voyti.recovery.password_previously_used' => 'Dieses Passwort wurde kürzlich bereits verwendet. Bitte wählen Sie ein anderes.',

    // SettingsController
    'voyti.settings.not_authenticated' => 'Nicht authentifiziert',
    'voyti.settings.profile_updated' => 'Ihr Profil wurde aktualisiert',
    'voyti.settings.account_details_updated' => 'Ihre Kontodaten wurden aktualisiert',
    'voyti.settings.personal_info_removed' => 'Ihre persönlichen Daten wurden entfernt',
    'voyti.settings.account_deleted' => 'Ihr Konto wurde gelöscht',
    'voyti.settings.gdpr_consent_saved' => 'DSGVO-Einwilligung wurde gespeichert',
    'voyti.settings.email_changed' => 'Ihre E-Mail-Adresse wurde geändert',
    'voyti.settings.email_change_failed' => 'E-Mail-Adresse konnte nicht geändert werden',
    'voyti.settings.network_disconnected' => 'Das Netzwerk wurde getrennt',
    'voyti.settings.network_not_found' => 'Netzwerk nicht gefunden',
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
    'voyti.admin.impersonate_identity_success' => 'Sie sind jetzt als dieser Benutzer angemeldet',
    'voyti.admin.impersonate_identity_restored' => 'Sie wurden zu Ihrer ursprünglichen Identität zurückgesetzt',

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

    'voyti.validator.password_complexity' => 'Das Passwort muss mindestens einen Großbuchstaben, einen Kleinbuchstaben, eine Ziffer und ein Sonderzeichen enthalten.',

    // Mail subjects
    'voyti.mail.welcome_subject' => 'Willkommen bei {app}',
    'voyti.mail.confirmation_subject' => 'Konto auf {app} bestätigen',
    'voyti.mail.reconfirmation_subject' => 'E-Mail-Änderung auf {app} bestätigen',
    'voyti.mail.recovery_subject' => 'Passwort-Zurücksetzung auf {app} abschließen',
    'voyti.mail.admin_notification_subject' => 'Neue Benutzerregistrierung auf {app}',

    // Mail view templates
    'voyti.mail.welcome_heading' => 'Willkommen!',
    'voyti.mail.hello_username' => 'Hallo {username},',
    'voyti.mail.account_created_successfully' => 'Ihr Konto wurde erfolgreich erstellt.',
    'voyti.mail.email_change_heading' => 'Bestätigung der E-Mail-Änderung',
    'voyti.mail.click_to_confirm_email' => 'Klicken Sie auf den folgenden Link, um Ihre neue E-Mail-Adresse zu bestätigen:',
    'voyti.mail.password_recovery_heading' => 'Passwortwiederherstellung',
    'voyti.mail.click_to_reset_password' => 'Klicken Sie auf den folgenden Link, um Ihr Passwort zurückzusetzen:',
    'voyti.mail.confirm_account_heading' => 'Bestätigen Sie Ihr Konto',
    'voyti.mail.click_to_confirm_account' => 'Klicken Sie auf den folgenden Link, um Ihr Konto zu bestätigen:',

    // Navigation / Menu
    'voyti.menu.dashboard' => 'Dashboard',
    'voyti.menu.userProfile' => 'Profil',
    'voyti.menu.account' => 'Konto',
    'voyti.menu.networks' => 'Netzwerke',
    'voyti.menu.sessions' => 'Sitzungen',
    'voyti.menu.logout' => 'Abmelden',

    // Login view
    'voyti.view.login.title' => 'Anmelden',
    'voyti.view.login.login_label' => 'Benutzername oder E-Mail',
    'voyti.view.login.sign_in_button' => 'Anmelden',
    'voyti.view.login.forgot_password' => 'Passwort vergessen?',
    'voyti.view.login.register_link' => 'Registrieren',
    'voyti.view.login.password_label' => 'Passwort',
    'voyti.view.login.remember_me_label' => 'Erinnern Sie sich an mich',
    'voyti.view.login.social_divider' => 'Oder anmelden mit',

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

    // Settings views
    'voyti.view.settings.dashboard_title' => 'Dashboard',
    'voyti.view.settings.welcome' => 'Willkommen, {name}!',
    'voyti.view.settings.member_since' => 'Mitglied seit',
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
    'voyti.view.admin.registered_label' => 'Registriert',
    'voyti.view.admin.sessions' => 'Sitzungsverwaltung',
    'voyti.view.admin.sessions_link' => 'Sitzungen',
    'voyti.view.admin.terminate_sessions' => 'Sitzungen beenden',
    'voyti.view.admin.impersonate_button' => 'Imitieren',
    'voyti.view.admin.restore_button' => 'Wiederherstellen',
    'voyti.view.admin.impersonating_banner' => 'Sie sind derzeit als dieser Benutzer angemeldet. Klicken Sie auf Wiederherstellen, um zu {username} zurückzukehren.',

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

    // Dashboard view
    'voyti.view.dashboard.title' => 'Dashboard',
    'voyti.view.dashboard.users_total' => 'Benutzer insgesamt',
    'voyti.view.dashboard.users_blocked' => 'Gesperrte Benutzer',
    'voyti.view.dashboard.users_unconfirmed' => 'Unbestätigte Benutzer',
    'voyti.view.dashboard.roles' => 'Rollen',
    'voyti.view.dashboard.permissions' => 'Berechtigungen',
    'voyti.view.dashboard.rules' => 'Regeln',
    'voyti.view.dashboard.recent_activity' => 'Letzte Aktivität',
    'voyti.view.dashboard.no_recent_activity' => 'Keine kürzliche Aktivität.',
    'voyti.view.dashboard.new_registrations' => 'Neuregistrierungen',
    'voyti.view.dashboard.active_sessions' => 'Aktive Sitzungen',
    'voyti.view.dashboard.last_1d' => 'Letzte 24 Stunden',
    'voyti.view.dashboard.last_7d' => 'Letzte 7 Tage',
    'voyti.view.dashboard.last_lifespan' => 'Letzte {days} Tage',

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

    // Sessions
    'voyti.view.sessions.ip' => 'IP-Adresse',
    'voyti.view.sessions.user_agent' => 'Benutzeragent',
    'voyti.view.sessions.last_seen' => 'Zuletzt gesehen',
    'voyti.view.sessions.title' => 'Aktive Sitzungen',
    'voyti.view.sessions.this_device' => 'Dieses Gerät',
    'voyti.view.sessions.none' => 'Keine aktiven Sitzungen.',
    'voyti.view.sessions.revoke_button' => 'Widerrufen',
    'voyti.view.sessions.revoked' => 'Widerrufen',
    'voyti.view.sessions.active' => 'Aktiv',

    // Pagination
    'voyti.view.filter_button' => 'Filtern',
    'voyti.view.per_page_label' => 'Pro Seite',
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
    'voyti.view.bio_variables_hint' => 'Sie können {age} und {location} Platzhalter verwenden. Sie werden durch Ihr berechnetes Alter und Ihren Standort ersetzt, wenn diese verfügbar sind.',
    'voyti.view.public_email_label' => 'Öffentliche E-Mail',
    'voyti.view.not_set' => 'Nicht festgelegt',
    'voyti.view.website_label' => 'Webseite',
    'voyti.view.location_label' => 'Ort',
    'voyti.view.gravatar_email_label' => 'Gravatar-E-Mail',
    'voyti.view.timezone_label' => 'Zeitzone',
    'voyti.view.birthday_label' => 'Geburtsdatum',

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
    'voyti.view.disconnect_button' => 'Trennen',

    // Widgets
    'voyti.view.networks.no_networks' => 'Keine verbundenen Netzwerke',

    // Shared message view
    'voyti.view.go_home' => 'Zur Startseite',
];
