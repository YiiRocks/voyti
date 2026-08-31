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
    'voyti.recovery.password_previously_used' => 'This password has been used recently. Please choose a different one.',

    // SettingsController
    'voyti.settings.not_authenticated' => 'Not authenticated',
    'voyti.settings.profile_updated' => 'Your profile has been updated',
    'voyti.settings.account_details_updated' => 'Your account details have been updated',
    'voyti.settings.personal_info_removed' => 'Your personal information has been removed',
    'voyti.settings.account_deleted' => 'Your account has been deleted',
    'voyti.settings.email_changed' => 'Your email has been changed',
    'voyti.settings.email_change_failed' => 'Failed to change email',
    'voyti.settings.session_not_found' => 'Session not found',
    'voyti.settings.session_terminated' => 'Session has been logged out',
    'voyti.settings.password_previously_used' => 'This password has been used recently. Please choose a different one.',

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
    'voyti.admin.password_previously_used' => 'This password has been used recently. Please choose a different one.',
    'voyti.admin.impersonate_identity_success' => 'You are now logged in as this user',
    'voyti.admin.impersonate_identity_restored' => 'You have been restored to your original identity',

    // RuleController
    'voyti.rule.added' => 'Authorization rule has been added',
    'voyti.rule.updated' => 'Authorization rule has been updated',
    'voyti.rule.deleted' => 'Authorization rule has been removed',
    'voyti.rule.invalid_class' => 'Invalid rule class',

    'voyti.auth_item.not_found' => 'Authorization item not found',
    'voyti.auth_item.created' => 'Authorization item has been created',
    'voyti.auth_item.updated' => 'Authorization item has been updated',
    'voyti.auth_item.deleted' => 'Authorization item has been removed',

    // PasswordRecoveryService
    'voyti.recovery.message_sent_if_exists' => 'If the email exists, a recovery message has been sent',
    'voyti.recovery.message_sent' => 'Recovery message sent',

    // UserCreationHelper
    'voyti.user.email_already_exists' => 'Email already exists',
    'voyti.user.username_already_exists' => 'Username already exists',
    'voyti.user.email_or_username_already_exists' => 'A user with this email or username already exists.',

    'voyti.validator.password_complexity' => 'Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.',

    // Mail subjects
    'voyti.mail.welcome_subject' => 'Welcome to {app}',
    'voyti.mail.confirmation_subject' => 'Confirm account on {app}',
    'voyti.mail.reconfirmation_subject' => 'Confirm email change on {app}',
    'voyti.mail.recovery_subject' => 'Complete password reset on {app}',
    'voyti.mail.admin_notification_subject' => 'New user registration on {app}',

    // Mail view templates
    'voyti.mail.welcome_heading' => 'Welcome!',
    'voyti.mail.hello_username' => 'Hello {username},',
    'voyti.mail.account_created_successfully' => 'Your account has been created successfully.',
    'voyti.mail.email_change_heading' => 'Email change confirmation',
    'voyti.mail.click_to_confirm_email' => 'Click the link below to confirm your new email address:',
    'voyti.mail.password_recovery_heading' => 'Password recovery',
    'voyti.mail.click_to_reset_password' => 'Click the link below to reset your password:',
    'voyti.mail.confirm_account_heading' => 'Confirm your account',
    'voyti.mail.click_to_confirm_account' => 'Click the link below to confirm your account:',

    // Navigation / Menu
    'voyti.menu.dashboard' => 'Dashboard',
    'voyti.menu.userProfile' => 'Profile',
    'voyti.menu.account' => 'Account',
    'voyti.menu.sessions' => 'Sessions',
    'voyti.menu.logout' => 'Log out',

    // Login view
    'voyti.view.login.title' => 'Log In',
    'voyti.view.login.login_label' => 'Username or Email',
    'voyti.view.login.sign_in_button' => 'Log In',
    'voyti.view.login.forgot_password' => 'Forgot password?',
    'voyti.view.login.register_link' => 'Register',
    'voyti.view.login.password_label' => 'Password',
    'voyti.view.login.remember_me_label' => 'Remember me',
    'voyti.view.login.social_divider' => 'Or sign in with',

    // Registration views
    'voyti.view.registration.register_title' => 'Create account',
    'voyti.view.registration.data_processing_consent_label' => 'I agree to processing of my personal data',
    'voyti.view.registration.register_button' => 'Register',
    'voyti.view.registration.already_have_account' => 'Already have an account?',
    'voyti.view.registration.resend_title' => 'Resend confirmation link',

    // Recovery views
    'voyti.view.recovery.request_title' => 'Recover password',
    'voyti.view.recovery.send_link_button' => 'Send recovery link',
    'voyti.view.recovery.back_to_login' => 'Back to login',
    'voyti.view.recovery.reset_title' => 'Reset password',
    'voyti.view.recovery.reset_button' => 'Reset password',

    // Settings views
    'voyti.view.settings.dashboard_title' => 'Dashboard',
    'voyti.view.settings.welcome' => 'Welcome, {name}!',
    'voyti.view.settings.member_since' => 'Member since',
    'voyti.view.edit_profile.title' => 'Edit Profile',
    'voyti.view.userProfile.title' => 'Profile preview',
    'voyti.view.account.title' => 'Account settings',
    'voyti.view.privacy.title' => 'Privacy',
    'voyti.view.privacy.delete_account' => 'Delete my account',
    'voyti.view.settings.privacy' => 'Privacy',

    // GDPR views
    'voyti.view.anonymize.title' => 'Anonymize my account',
    'voyti.view.anonymize.warning' => 'Warning: This action anonymizes your account (your email and username are replaced) and permanently blocks access. This cannot be undone.',
    'voyti.view.anonymize.button' => 'Anonymize my account',

    // Account settings (2FA)

    // Delete account view
    'voyti.view.delete_account.title' => 'Delete my account',
    'voyti.view.delete_account.warning' => 'Warning: This action permanently deletes your account and all associated data. This cannot be undone.',
    'voyti.view.delete_account.confirm_label' => 'I understand this action is irreversible',
    'voyti.view.delete_account.button' => 'Delete my account',
    'voyti.view.delete_account.invalid_password' => 'Incorrect password',

    // Admin views
    'voyti.view.admin.title' => 'Users',
    'voyti.view.admin.create_user_title' => 'Create user',
    'voyti.view.admin.create_user_link' => 'Create user',
    'voyti.view.admin.update_user_title' => 'Update user: {username}',
    'voyti.view.admin.update_profile_title' => 'Update profile',
    'voyti.view.admin.registered_label' => 'Registered',
    'voyti.view.admin.sessions' => 'Session management',
    'voyti.view.admin.sessions_link' => 'Sessions',
    'voyti.view.admin.terminate_sessions' => 'Terminate sessions',
    'voyti.view.admin.impersonate_button' => 'Impersonate',
    'voyti.view.admin.restore_button' => 'Restore',
    'voyti.view.admin.impersonating_banner' => 'You are currently logged in as this user. Click Restore to switch back to {username}.',

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

    // Dashboard view
    'voyti.view.dashboard.title' => 'Dashboard',
    'voyti.view.dashboard.users_total' => 'Total users',
    'voyti.view.dashboard.users_blocked' => 'Blocked users',
    'voyti.view.dashboard.users_unconfirmed' => 'Unconfirmed users',
    'voyti.view.dashboard.roles' => 'Roles',
    'voyti.view.dashboard.permissions' => 'Permissions',
    'voyti.view.dashboard.rules' => 'Rules',
    'voyti.view.dashboard.recent_activity' => 'Recent activity',
    'voyti.view.dashboard.no_recent_activity' => 'No recent activity.',
    'voyti.view.dashboard.new_registrations' => 'New registrations',
    'voyti.view.dashboard.active_sessions' => 'Active sessions',
    'voyti.view.dashboard.last_1d' => 'Last 24 hours',
    'voyti.view.dashboard.last_7d' => 'Last 7 days',
    'voyti.view.dashboard.last_lifespan' => 'Last {days} days',
    'voyti.view.dashboard.recommended_addons' => 'Recommended Addons',
    'voyti.view.dashboard.view_on_packagist' => 'View on Packagist',
    'voyti.view.dashboard.documentation' => 'Documentation',
    'voyti.view.dashboard.package_api_label' => 'REST API',
    'voyti.view.dashboard.package_api_description' => 'Share your user system as a modern REST API with built-in authentication and full OpenAPI documentation.',
    'voyti.view.dashboard.package_gdpr_label' => 'GDPR Compliance',
    'voyti.view.dashboard.package_gdpr_description' => 'Users can download, delete, or anonymize their data. Simplify compliance and build trust.',
    'voyti.view.dashboard.package_social_auth_label' => 'Social Login',
    'voyti.view.dashboard.package_social_auth_description' => 'Skip passwords. Users log in with Google, GitHub, Facebook, and others in one click.',
    'voyti.view.dashboard.package_lockout_label' => 'Brute-Force Protection',
    'voyti.view.dashboard.package_lockout_description' => 'Track failed login and registration attempts per IP address and block further attempts once a threshold is reached.',
    'voyti.view.dashboard.package_recaptcha_label' => 'reCAPTCHA Protection',
    'voyti.view.dashboard.package_recaptcha_description' => 'Protect registration and password recovery from abuse with Google reCAPTCHA v2 and v3.',
    'voyti.view.dashboard.package_2fa_email_label' => 'Two-Factor Auth (Email)',
    'voyti.view.dashboard.package_2fa_email_description' => 'Add an extra security layer with email-based codes. Protects against password breaches instantly.',
    'voyti.view.dashboard.package_2fa_totp_label' => 'Two-Factor Auth (TOTP)',
    'voyti.view.dashboard.package_2fa_totp_description' => 'Military-grade security with authenticator apps. Keeps accounts secure even if passwords are compromised.',
    'voyti.view.dashboard.package_2fa_webauthn_label' => 'Two-Factor Auth (WebAuthn)',
    'voyti.view.dashboard.package_2fa_webauthn_description' => 'The future of security. Biometric or hardware key authentication for maximum protection and ease.',

    // Audit log view
    'voyti.view.audit_log.title' => 'Audit Log',
    'voyti.view.audit_log.created_header' => 'When',
    'voyti.view.audit_log.actor_header' => 'Actor',
    'voyti.view.audit_log.action_header' => 'Action',
    'voyti.view.audit_log.target_header' => 'Target',
    'voyti.view.audit_log.context_header' => 'Details',

    // Assignments
    'voyti.view.assignments.assigned' => 'Assigned',
    'voyti.view.assignments.available' => 'Available',
    'voyti.view.assignments.update' => 'Update assignments',
    'voyti.view.info_link' => 'Info',

    // Sessions
    'voyti.view.sessions.ip' => 'IP address',
    'voyti.view.sessions.user_agent' => 'User agent',
    'voyti.view.sessions.last_seen' => 'Last seen',
    'voyti.view.sessions.title' => 'Active Sessions',
    'voyti.view.sessions.this_device' => 'This device',
    'voyti.view.sessions.none' => 'No active sessions.',
    'voyti.view.sessions.revoke_button' => 'Revoke',
    'voyti.view.sessions.revoked' => 'Revoked',
    'voyti.view.sessions.active' => 'Active',

    // Pagination
    'voyti.view.filter_button' => 'Filter',
    'voyti.view.per_page_label' => 'Per page',
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
    'voyti.view.bio_variables_hint' => 'You can use {age} and {location} placeholders. They will be replaced with your calculated age and location when available.',
    'voyti.view.public_email_label' => 'Public email',
    'voyti.view.not_set' => 'Not set',
    'voyti.view.website_label' => 'Website',
    'voyti.view.location_label' => 'Location',
    'voyti.view.gravatar_email_label' => 'Gravatar email',
    'voyti.view.timezone_label' => 'Timezone',
    'voyti.view.birthday_label' => 'Date of Birth',

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

    // Widgets

    // Shared message view
    'voyti.view.go_home' => 'Go home',
];
