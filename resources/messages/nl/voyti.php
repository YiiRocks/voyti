<?php

declare(strict_types=1);

return [
    // SecurityController
    'voyti.security.invalid_login' => 'Ongeldige gebruikersnaam of wachtwoord',
    'voyti.security.account_blocked' => 'Uw account is geblokkeerd',
    'voyti.security.need_email_confirmation' => 'U moet uw e-mailadres bevestigen',
    'voyti.security.logged_out' => 'Uitgelogd',
    'voyti.security.password_expired' => 'Uw wachtwoord is verlopen. Stel een nieuw wachtwoord in.',
    'voyti.security.authenticated' => 'Geverifieerd',

    // RegistrationController
    'voyti.registration.disabled' => 'Registratie is uitgeschakeld',
    'voyti.registration.invalid_confirmation_link' => 'Ongeldige bevestigingslink',
    'voyti.registration.complete' => 'Dank u, de registratie is nu voltooid.',
    'voyti.registration.confirmation_link_invalid' => 'De bevestigingslink is ongeldig of verlopen.',
    'voyti.registration.email_confirmation_disabled' => 'E-mailbevestiging is uitgeschakeld',
    'voyti.registration.new_confirmation_sent' => 'Er is een nieuwe bevestigingslink verzonden',

    // RecoveryController
    'voyti.recovery.disabled' => 'Wachtwoordherstel is uitgeschakeld',
    'voyti.recovery.reset_disabled' => 'Wachtwoord opnieuw instellen is uitgeschakeld',
    'voyti.recovery.link_invalid' => 'Herstellink is ongeldig of verlopen',
    'voyti.recovery.password_changed' => 'Wachtwoord is gewijzigd',

    // SettingsController
    'voyti.settings.not_authenticated' => 'Niet geverifieerd',
    'voyti.settings.user_not_found' => 'Gebruiker niet gevonden',
    'voyti.settings.profile_updated' => 'Uw profiel is bijgewerkt',
    'voyti.settings.account_details_updated' => 'Uw accountgegevens zijn bijgewerkt',
    'voyti.settings.personal_info_removed' => 'Uw persoonlijke gegevens zijn verwijderd',
    'voyti.settings.account_deleted' => 'Uw account is verwijderd',
    'voyti.settings.gdpr_consent_saved' => 'AVG-toestemming is opgeslagen',
    'voyti.settings.email_changed' => 'Uw e-mailadres is gewijzigd',
    'voyti.settings.email_change_failed' => 'E-mailadres kon niet worden gewijzigd',
    'voyti.settings.network_disconnected' => 'Netwerk is ontkoppeld',
    'voyti.settings.network_not_found' => 'Netwerk niet gevonden',
    'voyti.settings.two_factor_enabled' => 'Tweefactorauthenticatie is ingeschakeld',
    'voyti.settings.two_factor_disabled' => 'Tweefactorauthenticatie is uitgeschakeld',

    // ProfileController
    'voyti.userProfile.forbidden' => 'Verboden',
    'voyti.userProfile.not_found' => 'Profiel niet gevonden',

    // AdminController
    'voyti.admin.user_not_found' => 'Gebruiker niet gevonden',
    'voyti.admin.profile_details_updated' => 'Profielgegevens zijn bijgewerkt',
    'voyti.admin.user_confirmed' => 'Gebruiker is bevestigd',
    'voyti.admin.unable_to_confirm' => 'Kan gebruiker niet bevestigen',
    'voyti.admin.user_deleted' => 'Gebruiker is verwijderd',
    'voyti.admin.password_change_required' => 'Gebruiker moet bij de volgende aanmelding het wachtwoord wijzigen',
    'voyti.admin.error_occurred' => 'Er is een fout opgetreden',
    'voyti.admin.cannot_delete_self' => 'U kunt uw eigen account niet verwijderen',
    'voyti.admin.sessions_terminated' => 'Sessies zijn beëindigd',
    'voyti.admin.user_status_changed' => 'Gebruikersstatus is bijgewerkt',
    'voyti.admin.user_created' => 'Gebruiker is aangemaakt',
    'voyti.admin.account_updated' => 'Account is bijgewerkt',

    // RuleController
    'voyti.rule.added' => 'Autorisatieregel is toegevoegd',
    'voyti.rule.updated' => 'Autorisatieregel is bijgewerkt',
    'voyti.rule.deleted' => 'Autorisatieregel is verwijderd',
    'voyti.rule.invalid_class' => 'Ongeldige regelklasse',

    'voyti.auth_item.not_found' => 'Autorisatie-item niet gevonden',
    'voyti.auth_item.created' => 'Autorisatie-item is aangemaakt',
    'voyti.auth_item.updated' => 'Autorisatie-item is bijgewerkt',
    'voyti.auth_item.deleted' => 'Autorisatie-item is verwijderd',

    // API AdminController
    'voyti.api.not_found' => 'Niet gevonden',
    'voyti.api.user_created' => 'Gebruiker aangemaakt',
    'voyti.api.user_updated' => 'Gebruiker bijgewerkt',
    'voyti.api.user_deleted' => 'Gebruiker verwijderd',

    // PasswordRecoveryService
    'voyti.recovery.message_sent_if_exists' => 'Als het e-mailadres bestaat, is er een herstelbericht verzonden',
    'voyti.recovery.message_sent' => 'Herstelbericht verzonden',

    // TwoFactorCodeValidator
    'voyti.validator.two_factor_not_configured' => 'Tweefactorauthenticatie is niet geconfigureerd.',
    'voyti.validator.two_factor_library_missing' => '2FA-bibliotheek (chillerlan/php-authenticator) is niet geïnstalleerd.',
    'voyti.validator.invalid_verification_code' => 'Ongeldige verificatiecode.',
    'voyti.validator.password_complexity' => 'Het wachtwoord moet minstens één hoofdletter, één kleine letter, één cijfer en één speciaal teken bevatten.',
    'voyti.validator.two_factor_enabled' => 'Tweefactorauthenticatie is ingeschakeld.',
    'voyti.validator.invalid_code_with_time' => 'Ongeldige code. Probeer het opnieuw binnen {timeDuration} seconden.',
    'voyti.validator.invalid_two_factor_code_with_time' => 'Ongeldige tweefactorauthenticatiecode. Probeer het opnieuw binnen {timeDuration} seconden.',

    // Mail subjects
    'voyti.mail.welcome_subject' => 'Welkom bij {app}',
    'voyti.mail.confirmation_subject' => 'Account bevestigen op {app}',
    'voyti.mail.reconfirmation_subject' => 'E-mailwijziging bevestigen op {app}',
    'voyti.mail.recovery_subject' => 'Voltooi wachtwoordherstel op {app}',
    'voyti.mail.two_factor_subject' => 'Code voor tweefactorauthenticatie op {app}',
    'voyti.mail.admin_notification_subject' => 'Nieuwe gebruiker geregistreerd op {app}',

    // Mail view templates
    'voyti.mail.welcome_heading' => 'Welkom!',
    'voyti.mail.hello_username' => 'Hallo {username},',
    'voyti.mail.account_created_successfully' => 'Uw account is succesvol aangemaakt.',
    'voyti.mail.account_deleted_heading' => 'Account verwijderd',
    'voyti.mail.account_deleted_gdpr' => 'Uw account is verwijderd in overeenstemming met de AVG.',
    'voyti.mail.email_change_heading' => 'Bevestiging e-mailwijziging',
    'voyti.mail.click_to_confirm_email' => 'Klik op onderstaande link om uw nieuwe e-mailadres te bevestigen:',
    'voyti.mail.password_recovery_heading' => 'Wachtwoordherstel',
    'voyti.mail.click_to_reset_password' => 'Klik op onderstaande link om uw wachtwoord te resetten:',
    'voyti.mail.confirm_account_heading' => 'Bevestig uw account',
    'voyti.mail.click_to_confirm_account' => 'Klik op onderstaande link om uw account te bevestigen:',
    'voyti.mail.twofactor_heading' => 'Tweefactorauthenticatiecode',
    'voyti.mail.your_twofactor_code' => 'Uw tweefactorauthenticatiecode is:',

    // Navigation / Menu
    'voyti.menu.userProfile' => 'Profiel',
    'voyti.menu.account' => 'Account',
    'voyti.menu.networks' => 'Netwerken',
    'voyti.menu.two_factor' => 'Tweefactorauthenticatie',
    'voyti.menu.logout' => 'Uitloggen',

    // Login view
    'voyti.view.login.title' => 'Inloggen',
    'voyti.view.login.login_label' => 'Gebruikersnaam of e-mailadres',
    'voyti.view.login.remember_me' => 'Herinner mij de volgende keer',
    'voyti.view.login.sign_in_button' => 'Inloggen',
    'voyti.view.login.forgot_password' => 'Wachtwoord vergeten?',
    'voyti.view.login.register_link' => 'Registreren',
    'voyti.view.login.password_label' => 'Wachtwoord',
    'voyti.view.login.remember_me_label' => 'Onthoud mij',

    // Two-factor confirm view
    'voyti.view.two_factor.title' => 'Tweefactorauthenticatie',
    'voyti.view.two_factor.code_label' => 'Authenticatiecode',
    'voyti.view.two_factor.verify_button' => 'Verifiëren',
    'voyti.view.two_factor.enabled' => 'Tweefactorauthenticatie is ingeschakeld',
    'voyti.view.two_factor.disable' => 'Uitschakelen',
    'voyti.view.two_factor.scan_qr' => 'Scan deze QR-code met uw authenticator-app',
    'voyti.view.two_factor.manual_entry' => 'Of voer deze sleutel handmatig in:',
    'voyti.view.two_factor.qr_unavailable' => 'QR-code is niet beschikbaar',
    'voyti.view.two_factor.enter_code' => 'Voer de verificatiecode in',
    'voyti.view.two_factor.enable' => 'Inschakelen',
    'voyti.view.two_factor.verify' => 'Verifiëren',
    'voyti.view.two_factor_email.title' => 'Tweefactorauthenticatie via e-mail',
    'voyti.view.two_factor_email.enter_code' => 'Voer de per e-mail verzonden verificatiecode in',
    // Registration views
    'voyti.view.registration.register_title' => 'Account aanmaken',
    'voyti.view.registration.gdpr_consent_label' => 'Ik ga akkoord met de verwerking van mijn persoonsgegevens',
    'voyti.view.registration.register_button' => 'Registreren',
    'voyti.view.registration.already_have_account' => 'Heeft u al een account?',
    'voyti.view.registration.resend_title' => 'Bevestigingslink opnieuw verzenden',
    'voyti.view.registration.connect_title' => 'Account koppelen',
    'voyti.view.registration.connect_provider' => 'Koppel uw {provider}-account',
    'voyti.view.registration.connect_message' => 'U kunt uw sociale account koppelen of een nieuw account registreren.',
    'voyti.view.registration.connect_login' => 'Inloggen',
    'voyti.view.registration.connect_register' => 'Registreren',

    // Recovery views
    'voyti.view.recovery.request_title' => 'Wachtwoord herstellen',
    'voyti.view.recovery.send_link_button' => 'Herstellink verzenden',
    'voyti.view.recovery.back_to_login' => 'Terug naar inloggen',
    'voyti.view.recovery.reset_title' => 'Wachtwoord resetten',
    'voyti.view.recovery.reset_button' => 'Wachtwoord resetten',

    // UserProfile view
    'voyti.view.userProfile.email_label' => 'E-mail',
    'voyti.view.userProfile.location_label' => 'Locatie',
    'voyti.view.userProfile.bio_label' => 'Bio',

    // Settings views
    'voyti.view.edit_profile.title' => 'Profiel bewerken',
    'voyti.view.userProfile.title' => 'Profielvoorbeeld',
    'voyti.view.account.title' => 'Accountinstellingen',
    'voyti.view.networks.title' => 'Netwerken',
    'voyti.view.privacy.title' => 'Privacy',
    'voyti.view.privacy.manage_gdpr_consent' => 'AVG-toestemming beheren',
    'voyti.view.privacy.export_data' => 'Mijn gegevens exporteren',
    'voyti.view.privacy.anonymize_data' => 'Mijn account anonimiseren',
    'voyti.view.privacy.delete_account' => 'Mijn account verwijderen',
    'voyti.view.settings.privacy' => 'Privacy',

    // GDPR views
    'voyti.view.gdpr.consent_title' => 'AVG-toestemming',
    'voyti.view.gdpr.consent_label' => 'Ik geef toestemming voor de verwerking van mijn persoonsgegevens',
    'voyti.view.gdpr.consent_locked' => 'U heeft al toestemming gegeven op {date}. Dit kan niet ongedaan worden gemaakt.',
    'voyti.view.anonymize.title' => 'Mijn account anonimiseren',
    'voyti.view.anonymize.warning' => 'Waarschuwing: Deze actie anonimiseert uw account (e-mail en gebruikersnaam worden vervangen) en blokkeert de toegang permanent. Dit kan niet ongedaan worden gemaakt.',
    'voyti.view.anonymize.confirm_label' => 'Ik begrijp dat deze actie onomkeerbaar is',
    'voyti.view.anonymize.button' => 'Mijn account anonimiseren',

    // Account settings (2FA)
    'voyti.view.account.two_factor_title' => 'Tweefactorauthenticatie',
    'voyti.view.account.two_factor_enabled' => '2FA is ingeschakeld',
    'voyti.view.account.disable_two_factor' => '2FA uitschakelen',
    'voyti.view.account.enable_two_factor' => '2FA inschakelen',

    // Delete account view
    'voyti.view.delete_account.title' => 'Mijn account verwijderen',
    'voyti.view.delete_account.warning' => 'Waarschuwing: Deze actie verwijdert uw account en alle bijbehorende gegevens permanent. Dit kan niet ongedaan worden gemaakt.',
    'voyti.view.delete_account.confirm_label' => 'Ik begrijp dat deze actie onomkeerbaar is',
    'voyti.view.delete_account.button' => 'Mijn account verwijderen',

    // Admin views
    'voyti.view.admin.title' => 'Gebruikers',
    'voyti.view.admin.create_user_title' => 'Gebruiker aanmaken',
    'voyti.view.admin.create_user_link' => 'Gebruiker aanmaken',
    'voyti.view.admin.update_user_title' => 'Gebruiker bijwerken: {username}',
    'voyti.view.admin.update_profile_title' => 'Profiel bijwerken',
    'voyti.view.admin.info_link' => 'Info',
    'voyti.view.admin.registered_label' => 'Geregistreerd',
    'voyti.view.admin.session_history' => 'Sessiegeschiedenis',
    'voyti.view.admin.sessions_link' => 'Sessies',
    'voyti.view.admin.terminate_sessions' => 'Sessies beëindigen',

    // RBAC views
    'voyti.view.assignments.title' => 'Toewijzingen',
    'voyti.view.rule.title' => 'Regels',
    'voyti.view.rule.create_title' => 'Regel aanmaken',
    'voyti.view.rule.create_link' => 'Regel aanmaken',
    'voyti.view.rule.update_title' => 'Regel bijwerken',
    'voyti.view.rule.class_label' => 'Regelklasse',
    'voyti.view.permission.title' => 'Machtigingen',
    'voyti.view.permission.create_title' => 'Machtiging aanmaken',
    'voyti.view.permission.create_link' => 'Machtiging aanmaken',
    'voyti.view.permission.update_title' => 'Machtiging bijwerken: {name}',
    'voyti.view.role.title' => 'Rollen',
    'voyti.view.role.create_title' => 'Rol aanmaken',
    'voyti.view.role.create_link' => 'Rol aanmaken',
    'voyti.view.role.update_title' => 'Rol bijwerken: {name}',

    // Assignments
    'voyti.view.assignments.assigned' => 'Toegewezen',
    'voyti.view.assignments.available' => 'Beschikbaar',
    'voyti.view.assignments.update' => 'Toewijzingen bijwerken',
    'voyti.view.info_link' => 'Info',

    // Session history
    'voyti.view.session_history.title' => 'Sessiegeschiedenis',
    'voyti.view.session_history.ip' => 'IP-adres',
    'voyti.view.session_history.user_agent' => 'User agent',
    'voyti.view.session_history.created' => 'Aangemaakt',

    // Pagination
    'voyti.view.filter_button' => 'Filteren',
    'voyti.view.pagination_navigation' => 'Pagina navigatie',
    'voyti.view.previous' => 'Vorige',
    'voyti.view.next' => 'Volgende',

    // Common view labels
    'voyti.view.username_label' => 'Gebruikersnaam',
    'voyti.view.email_label' => 'E-mail',
    'voyti.view.password_label' => 'Wachtwoord',
    'voyti.view.password_repeat_label' => 'Wachtwoord bevestigen',
    'voyti.view.new_password_label' => 'Nieuw wachtwoord',
    'voyti.view.new_password_repeat_label' => 'Nieuw wachtwoord bevestigen',
    'voyti.view.current_password_label' => 'Huidig wachtwoord',
    'voyti.view.name_label' => 'Naam',
    'voyti.view.description_label' => 'Omschrijving',
    'voyti.view.bio_label' => 'Bio',
    'voyti.view.public_email_label' => 'Openbaar e-mailadres',
    'voyti.view.not_set' => 'Niet ingesteld',
    'voyti.view.website_label' => 'Website',
    'voyti.view.location_label' => 'Locatie',
    'voyti.view.gravatar_email_label' => 'Gravatar-e-mail',
    'voyti.view.timezone_label' => 'Tijdzone',
    'voyti.view.password_keep_label' => 'Wachtwoord (leeg laten om te behouden)',

    // Common table headers
    'voyti.view.id_header' => 'ID',
    'voyti.view.username_header' => 'Gebruikersnaam',
    'voyti.view.email_header' => 'E-mail',
    'voyti.view.status_header' => 'Status',
    'voyti.view.name_header' => 'Naam',
    'voyti.view.description_header' => 'Omschrijving',
    'voyti.view.children_header' => 'Onderliggende',
    'voyti.view.actions_header' => 'Acties',

    // User status
    'voyti.view.status_blocked' => 'Geblokkeerd',
    'voyti.view.status_active' => 'Actief',
    'voyti.view.status_pending' => 'In afwachting',

    // Common buttons / links
    'voyti.view.create_button' => 'Aanmaken',
    'voyti.view.save_button' => 'Opslaan',
    'voyti.view.reset_button' => 'Resetten',
    'voyti.view.update_button' => 'Bijwerken',
    'voyti.view.update_link' => 'Bijwerken',
    'voyti.view.delete_button' => 'Verwijderen',
    'voyti.view.confirm_button' => 'Bevestigen',
    'voyti.view.unblock_button' => 'Deblokkeren',
    'voyti.view.block_button' => 'Blokkeren',
    'voyti.view.force_password_change_button' => 'Wachtwoordwijziging afdwingen',
    'voyti.view.reset_password_button' => 'Wachtwoordherstellink verzenden',
    'voyti.view.update_profile_link' => 'Profiel bijwerken',
    'voyti.view.send_button' => 'Verzenden',
    'voyti.view.connect_button' => 'Koppelen',
    'voyti.view.disconnect_button' => 'Ontkoppelen',

    // Confirmation prompts
    'voyti.view.delete_user_confirm' => 'Deze gebruiker verwijderen?',

    // Widgets
    'voyti.view.sessions.active_sessions' => 'Actieve sessies',
    'voyti.view.sessions.ip_label' => 'IP:',
    'voyti.view.sessions.last_activity_label' => 'Laatste activiteit:',
    'voyti.view.connect.no_accounts' => 'Geen gekoppelde accounts',
    'voyti.view.networks.no_networks' => 'Geen gekoppelde netwerken',
    'voyti.view.login.link' => 'Inloggen',
    'voyti.view.logout.link' => 'Uitloggen',

    // Shared message view
    'voyti.view.go_home' => 'Naar startpagina',
];
