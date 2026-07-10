<?php

declare(strict_types=1);

return [
    // SecurityController
    'voyti.security.invalid_login' => 'Invalid login or password',
    'voyti.security.account_blocked' => 'Your account has been blocked',
    'voyti.security.need_email_confirmation' => 'You need to confirm your email address',
    'voyti.security.logged_out' => 'Logged out',
    'voyti.security.password_expired' => 'Your password has expired. Please set a new one.',
    'voyti.security.authenticated' => 'Authenticated',

    // RegistrationController
    'voyti.registration.disabled' => 'Registration is disabled',
    'voyti.registration.invalid_confirmation_link' => 'Invalid confirmation link',
    'voyti.registration.complete' => 'Thank you, registration is now complete.',
    'voyti.registration.confirmation_link_invalid' => 'The confirmation link is invalid or expired.',
    'voyti.registration.email_confirmation_disabled' => 'Email confirmation is disabled',
    'voyti.registration.new_confirmation_sent' => 'A new confirmation link has been sent',
    'voyti.registration.account_created' => 'Account created.',
    'voyti.registration.account_created_check_email' => 'Account created. Check your email for the confirmation link.',

    // RecoveryController
    'voyti.recovery.disabled' => 'Password recovery is disabled',
    'voyti.recovery.reset_disabled' => 'Password reset is disabled',
    'voyti.recovery.link_invalid' => 'Recovery link is invalid or expired',
    'voyti.recovery.password_changed' => 'Password has been changed',

    // SettingsController
    'voyti.settings.not_authenticated' => 'Not authenticated',
    'voyti.settings.user_not_found' => 'User not found',
    'voyti.settings.profile_updated' => 'Your profile has been updated',
    'voyti.settings.account_details_updated' => 'Your account details have been updated',
    'voyti.settings.personal_info_removed' => 'Your personal information has been removed',
    'voyti.settings.account_deleted' => 'Your account has been deleted',
    'voyti.settings.gdpr_consent_saved' => 'GDPR consent has been saved',
    'voyti.settings.email_changed' => 'Your email has been changed',
    'voyti.settings.email_change_failed' => 'Failed to change email',
    'voyti.settings.network_disconnected' => 'Network has been disconnected',
    'voyti.settings.network_not_found' => 'Network not found',
    'voyti.settings.two_factor_enabled' => 'Two-factor authentication has been enabled',
    'voyti.settings.two_factor_disabled' => 'Two-factor authentication has been disabled',

    // ProfileController
    'voyti.userProfile.forbidden' => 'Forbidden',
    'voyti.userProfile.not_found' => 'Profile not found',

    // AdminController
    'voyti.admin.user_not_found' => 'User not found',
    'voyti.admin.profile_details_updated' => 'Profile details have been updated',
    'voyti.admin.user_confirmed' => 'User has been confirmed',
    'voyti.admin.unable_to_confirm' => 'Unable to confirm user',
    'voyti.admin.user_deleted' => 'User has been deleted',
    'voyti.admin.password_change_required' => 'User will be required to change password at next login',
    'voyti.admin.error_occurred' => 'There was an error',
    'voyti.admin.cannot_delete_self' => 'You cannot delete your own account',
    'voyti.admin.sessions_terminated' => 'Sessions have been terminated',
    'voyti.admin.user_status_changed' => 'User status has been updated',
    'voyti.admin.user_created' => 'User has been created',
    'voyti.admin.account_updated' => 'Account has been updated',
    'voyti.admin.switch_identity_success' => 'You are now logged in as this user',
    'voyti.admin.switch_identity_restored' => 'You have been restored to your original identity',

    // RuleController
    'voyti.rule.added' => 'Authorization rule has been added',
    'voyti.rule.updated' => 'Authorization rule has been updated',
    'voyti.rule.deleted' => 'Authorization rule has been removed',
    'voyti.rule.invalid_class' => 'Invalid rule class',

    'voyti.auth_item.not_found' => 'Authorization item not found',
    'voyti.auth_item.created' => 'Authorization item has been created',
    'voyti.auth_item.updated' => 'Authorization item has been updated',
    'voyti.auth_item.deleted' => 'Authorization item has been removed',

    // API AdminController
    'voyti.api.not_found' => 'Not found',
    'voyti.api.user_created' => 'User created',
    'voyti.api.user_updated' => 'User updated',
    'voyti.api.user_deleted' => 'User deleted',

    // PasswordRecoveryService
    'voyti.recovery.message_sent_if_exists' => 'If the email exists, a recovery message has been sent',
    'voyti.recovery.message_sent' => 'Recovery message sent',

    // TwoFactorCodeValidator
    'voyti.validator.two_factor_not_configured' => 'Two factor authentication is not configured.',
    'voyti.validator.two_factor_library_missing' => '2FA library (chillerlan/php-authenticator) is not installed.',
    'voyti.validator.invalid_verification_code' => 'Invalid verification code.',
    'voyti.validator.password_complexity' => 'Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.',
    'voyti.validator.two_factor_enabled' => 'Two factor authentication has been enabled.',
    'voyti.validator.invalid_code_with_time' => 'Invalid code. Please try again within {timeDuration} seconds.',
    'voyti.validator.invalid_two_factor_code_with_time' => 'Invalid two factor authentication code. Please try again within {timeDuration} seconds.',

    // Mail subjects
    'voyti.mail.welcome_subject' => 'Welcome to {app}',
    'voyti.mail.confirmation_subject' => 'Confirm account on {app}',
    'voyti.mail.reconfirmation_subject' => 'Confirm email change on {app}',
    'voyti.mail.recovery_subject' => 'Complete password reset on {app}',
    'voyti.mail.two_factor_subject' => 'Code for two factor authentication on {app}',
    'voyti.mail.admin_notification_subject' => 'New user registration on {app}',

    // Mail view templates
    'voyti.mail.welcome_heading' => 'Welcome!',
    'voyti.mail.hello_username' => 'Hello {username},',
    'voyti.mail.account_created_successfully' => 'Your account has been created successfully.',
    'voyti.mail.account_deleted_heading' => 'Account deleted',
    'voyti.mail.account_deleted_gdpr' => 'Your account has been deleted in accordance with GDPR.',
    'voyti.mail.email_change_heading' => 'Email change confirmation',
    'voyti.mail.click_to_confirm_email' => 'Click the link below to confirm your new email address:',
    'voyti.mail.password_recovery_heading' => 'Password recovery',
    'voyti.mail.click_to_reset_password' => 'Click the link below to reset your password:',
    'voyti.mail.confirm_account_heading' => 'Confirm your account',
    'voyti.mail.click_to_confirm_account' => 'Click the link below to confirm your account:',
    'voyti.mail.twofactor_heading' => 'Two-factor authentication code',
    'voyti.mail.your_twofactor_code' => 'Your two-factor authentication code is:',

    // Navigation / Menu
    'voyti.menu.userProfile' => 'Profile',
    'voyti.menu.account' => 'Account',
    'voyti.menu.networks' => 'Networks',
    'voyti.menu.two_factor' => 'Two-Factor Auth',
    'voyti.menu.logout' => 'Logout',

    // Login view
    'voyti.view.login.title' => 'Log In',
    'voyti.view.login.login_label' => 'Username or Email',
    'voyti.view.login.remember_me' => 'Remember me next time',
    'voyti.view.login.sign_in_button' => 'Log In',
    'voyti.view.login.forgot_password' => 'Forgot password?',
    'voyti.view.login.register_link' => 'Register',
    'voyti.view.login.password_label' => 'Password',
    'voyti.view.login.remember_me_label' => 'Remember me',

    // Two-factor confirm view
    'voyti.view.two_factor.title' => 'Two-Factor Authentication',
    'voyti.view.two_factor_google.button_label' => 'Google Authenticator',
    'voyti.view.two_factor.code_label' => 'Authentication Code',
    'voyti.view.two_factor.verify_button' => 'Verify',
    'voyti.view.two_factor.enabled' => 'Two-factor authentication is enabled',
    'voyti.view.two_factor.disable' => 'Disable',
    'voyti.view.two_factor.scan_qr' => 'Scan this QR code with your authenticator app',
    'voyti.view.two_factor.manual_entry' => 'Or enter this key manually:',
    'voyti.view.two_factor.qr_unavailable' => 'QR code is not available',
    'voyti.view.two_factor.enter_code' => 'Enter the verification code',
    'voyti.view.two_factor.enable' => 'Enable',
    'voyti.view.two_factor.verify' => 'Verify',
    'voyti.view.two_factor.renew' => 'Renew',
    'voyti.view.two_factor.renew_error' => 'Could not generate a new key. Please try again.',
    'voyti.view.two_factor.loading' => 'Loading…',
    'voyti.view.two_factor.already_enabled' => 'Two-factor authentication is already enabled.',
    'voyti.view.two_factor_email.title' => 'Two-factor authentication via email',
    'voyti.view.two_factor_email.button_label' => 'Email',
    'voyti.view.two_factor_email.enter_code' => 'Enter the verification code sent to your email',
    'voyti.view.two_factor_email.confirm_intro' => 'A verification code will be sent to the email address below.',
    'voyti.view.two_factor_email.send_button' => 'Send Code',
    // Registration views
    'voyti.view.registration.register_title' => 'Create account',
    'voyti.view.registration.gdpr_consent_label' => 'I agree processing of my personal data',
    'voyti.view.registration.register_button' => 'Register',
    'voyti.view.registration.already_have_account' => 'Already have an account?',
    'voyti.view.registration.resend_title' => 'Resend confirmation link',
    'voyti.view.registration.connect_title' => 'Connect account',
    'voyti.view.registration.connect_provider' => 'Connect your {provider} account',
    'voyti.view.registration.connect_message' => 'You can connect your social account or register a new one.',
    'voyti.view.registration.connect_login' => 'Login',
    'voyti.view.registration.connect_register' => 'Register',

    // Recovery views
    'voyti.view.recovery.request_title' => 'Recover password',
    'voyti.view.recovery.send_link_button' => 'Send recovery link',
    'voyti.view.recovery.back_to_login' => 'Back to login',
    'voyti.view.recovery.reset_title' => 'Reset password',
    'voyti.view.recovery.reset_button' => 'Reset password',

    // UserProfile view
    'voyti.view.userProfile.email_label' => 'Email',
    'voyti.view.userProfile.location_label' => 'Location',
    'voyti.view.userProfile.bio_label' => 'Bio',

    // Settings views
    'voyti.view.edit_profile.title' => 'Edit Profile',
    'voyti.view.userProfile.title' => 'Profile preview',
    'voyti.view.account.title' => 'Account settings',
    'voyti.view.networks.title' => 'Networks',
    'voyti.view.privacy.title' => 'Privacy',
    'voyti.view.privacy.manage_gdpr_consent' => 'Manage GDPR consent',
    'voyti.view.privacy.export_data' => 'Export my data',
    'voyti.view.privacy.anonymize_data' => 'Anonymize my account',
    'voyti.view.privacy.delete_account' => 'Delete my account',
    'voyti.view.settings.privacy' => 'Privacy',

    // GDPR views
    'voyti.view.gdpr.consent_title' => 'GDPR Consent',
    'voyti.view.gdpr.consent_label' => 'I consent to processing of my personal data',
    'voyti.view.gdpr.consent_locked' => 'You have already given consent on {date}. This cannot be undone.',
    'voyti.view.anonymize.title' => 'Anonymize my account',
    'voyti.view.anonymize.warning' => 'Warning: This action anonymizes your account (your email and username are replaced) and permanently blocks access. This cannot be undone.',
    'voyti.view.anonymize.confirm_label' => 'I understand this action is irreversible',
    'voyti.view.anonymize.button' => 'Anonymize my account',

    // Account settings (2FA)
    'voyti.view.account.two_factor_title' => 'Two-Factor Authentication',
    'voyti.view.account.two_factor_enabled' => '2FA is enabled',
    'voyti.view.account.disable_two_factor' => 'Disable 2FA',
    'voyti.view.account.enable_two_factor' => 'Enable 2FA',

    // Delete account view
    'voyti.view.delete_account.title' => 'Delete my account',
    'voyti.view.delete_account.warning' => 'Warning: This action permanently deletes your account and all associated data. This cannot be undone.',
    'voyti.view.delete_account.confirm_label' => 'I understand this action is irreversible',
    'voyti.view.delete_account.button' => 'Delete my account',

    // Admin views
    'voyti.view.admin.title' => 'Users',
    'voyti.view.admin.create_user_title' => 'Create user',
    'voyti.view.admin.create_user_link' => 'Create user',
    'voyti.view.admin.update_user_title' => 'Update user: {username}',
    'voyti.view.admin.update_profile_title' => 'Update profile',
    'voyti.view.admin.info_link' => 'Info',
    'voyti.view.admin.registered_label' => 'Registered',
    'voyti.view.admin.session_history' => 'Session history',
    'voyti.view.admin.sessions_link' => 'Sessions',
    'voyti.view.admin.terminate_sessions' => 'Terminate sessions',
    'voyti.view.admin.switch_button' => 'Switch',
    'voyti.view.admin.restore_button' => 'Restore',
    'voyti.view.admin.switched_banner' => 'You are currently logged in as this user. Click Restore to switch back to {username}.',

    // RBAC views
    'voyti.view.assignments.title' => 'Assignments',
    'voyti.view.rule.title' => 'Rules',
    'voyti.view.rule.create_title' => 'Create rule',
    'voyti.view.rule.create_link' => 'Create rule',
    'voyti.view.rule.update_title' => 'Update rule',
    'voyti.view.rule.class_label' => 'Rule class',
    'voyti.view.permission.title' => 'Permissions',
    'voyti.view.permission.create_title' => 'Create permission',
    'voyti.view.permission.create_link' => 'Create permission',
    'voyti.view.permission.update_title' => 'Update permission: {name}',
    'voyti.view.role.title' => 'Roles',
    'voyti.view.role.create_title' => 'Create role',
    'voyti.view.role.create_link' => 'Create role',
    'voyti.view.role.update_title' => 'Update role: {name}',

    // Assignments
    'voyti.view.assignments.assigned' => 'Assigned',
    'voyti.view.assignments.available' => 'Available',
    'voyti.view.assignments.update' => 'Update assignments',
    'voyti.view.info_link' => 'Info',

    // Session history
    'voyti.view.session_history.title' => 'Session history',
    'voyti.view.session_history.ip' => 'IP address',
    'voyti.view.session_history.user_agent' => 'User agent',
    'voyti.view.session_history.created' => 'Created',

    // Pagination
    'voyti.view.filter_button' => 'Filter',
    'voyti.view.pagination_navigation' => 'Page navigation',
    'voyti.view.previous' => 'Previous',
    'voyti.view.next' => 'Next',

    // Common view labels
    'voyti.view.username_label' => 'Username',
    'voyti.view.email_label' => 'Email',
    'voyti.view.password_label' => 'Password',
    'voyti.view.password_repeat_label' => 'Confirm password',
    'voyti.view.new_password_label' => 'New password',
    'voyti.view.new_password_repeat_label' => 'Confirm new password',
    'voyti.view.current_password_label' => 'Current password',
    'voyti.view.name_label' => 'Name',
    'voyti.view.description_label' => 'Description',
    'voyti.view.bio_label' => 'Bio',
    'voyti.view.public_email_label' => 'Public email',
    'voyti.view.not_set' => 'Not set',
    'voyti.view.website_label' => 'Website',
    'voyti.view.location_label' => 'Location',
    'voyti.view.gravatar_email_label' => 'Gravatar email',
    'voyti.view.timezone_label' => 'Timezone',
    'voyti.view.password_keep_label' => 'Password (leave empty to keep)',

    // Common table headers
    'voyti.view.id_header' => 'ID',
    'voyti.view.username_header' => 'Username',
    'voyti.view.email_header' => 'Email',
    'voyti.view.status_header' => 'Status',
    'voyti.view.name_header' => 'Name',
    'voyti.view.description_header' => 'Description',
    'voyti.view.children_header' => 'Children',
    'voyti.view.actions_header' => 'Actions',

    // User status
    'voyti.view.status_blocked' => 'Blocked',
    'voyti.view.status_active' => 'Active',
    'voyti.view.status_pending' => 'Pending',

    // Common buttons / links
    'voyti.view.create_button' => 'Create',
    'voyti.view.save_button' => 'Save',
    'voyti.view.reset_button' => 'Reset',
    'voyti.view.update_button' => 'Update',
    'voyti.view.update_link' => 'Update',
    'voyti.view.delete_button' => 'Delete',
    'voyti.view.confirm_button' => 'Confirm',
    'voyti.view.unblock_button' => 'Unblock',
    'voyti.view.block_button' => 'Block',
    'voyti.view.force_password_change_button' => 'Force password change',
    'voyti.view.reset_password_button' => 'Send password reset link',
    'voyti.view.update_profile_link' => 'Update profile',
    'voyti.view.send_button' => 'Send',
    'voyti.view.connect_button' => 'Connect',
    'voyti.view.disconnect_button' => 'Disconnect',

    // Confirmation prompts
    'voyti.view.delete_user_confirm' => 'Delete this user?',

    // Widgets
    'voyti.view.sessions.active_sessions' => 'Active sessions',
    'voyti.view.sessions.ip_label' => 'IP:',
    'voyti.view.sessions.last_activity_label' => 'Last activity:',
    'voyti.view.connect.no_accounts' => 'No connected accounts',
    'voyti.view.networks.no_networks' => 'No connected networks',
    'voyti.view.login.link' => 'Login',
    'voyti.view.logout.link' => 'Logout',

    // Shared message view
    'voyti.view.go_home' => 'Go home',
];
