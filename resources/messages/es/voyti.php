<?php

declare(strict_types=1);

return [
    // SecurityController
    'voyti.security.invalid_login' => 'Usuario o contraseña no válidos',
    'voyti.security.account_blocked' => 'Su cuenta ha sido bloqueada',
    'voyti.security.need_email_confirmation' => 'Debe confirmar su dirección de correo electrónico',
    'voyti.security.logged_out' => 'Sesión cerrada',
    'voyti.security.password_expired' => 'Su contraseña ha caducado. Por favor, establezca una nueva.',
    'voyti.security.authenticated' => 'Autenticado',

    // RegistrationController
    'voyti.registration.disabled' => 'El registro está deshabilitado',
    'voyti.registration.invalid_confirmation_link' => 'Enlace de confirmación no válido',
    'voyti.registration.complete' => 'Gracias, el registro se ha completado.',
    'voyti.registration.confirmation_link_invalid' => 'El enlace de confirmación no es válido o ha caducado.',
    'voyti.registration.email_confirmation_disabled' => 'La confirmación por correo electrónico está deshabilitada',
    'voyti.registration.new_confirmation_sent' => 'Se ha enviado un nuevo enlace de confirmación',
    'voyti.registration.account_created' => 'Cuenta creada.',
    'voyti.registration.account_created_check_email' => 'Cuenta creada. Revise su correo electrónico para obtener el enlace de confirmación.',

    // RecoveryController
    'voyti.recovery.disabled' => 'La recuperación de contraseña está deshabilitada',
    'voyti.recovery.reset_disabled' => 'El restablecimiento de contraseña está deshabilitado',
    'voyti.recovery.link_invalid' => 'El enlace de recuperación no es válido o ha caducado',
    'voyti.recovery.password_changed' => 'La contraseña ha sido cambiada',
    'voyti.recovery.password_previously_used' => 'Esta contraseña se ha usado recientemente. Por favor, elija una diferente.',

    // SettingsController
    'voyti.settings.not_authenticated' => 'No autenticado',
    'voyti.settings.user_not_found' => 'Usuario no encontrado',
    'voyti.settings.profile_updated' => 'Su perfil ha sido actualizado',
    'voyti.settings.account_details_updated' => 'Los datos de su cuenta han sido actualizados',
    'voyti.settings.personal_info_removed' => 'Su información personal ha sido eliminada',
    'voyti.settings.account_deleted' => 'Su cuenta ha sido eliminada',
    'voyti.settings.gdpr_consent_saved' => 'El consentimiento RGPD ha sido guardado',
    'voyti.settings.email_changed' => 'Su correo electrónico ha sido cambiado',
    'voyti.settings.email_change_failed' => 'Error al cambiar el correo electrónico',
    'voyti.settings.network_disconnected' => 'La red ha sido desconectada',
    'voyti.settings.network_not_found' => 'Red no encontrada',
    'voyti.settings.two_factor_enabled' => 'La autenticación de dos factores ha sido habilitada',
    'voyti.settings.two_factor_disabled' => 'La autenticación de dos factores ha sido deshabilitada',
    'voyti.settings.session_history_disabled' => 'El historial de sesiones está deshabilitado',
    'voyti.settings.session_not_found' => 'Sesión no encontrada',
    'voyti.settings.session_terminated' => 'La sesión ha sido cerrada',
    'voyti.settings.password_previously_used' => 'Esta contraseña se ha usado recientemente. Por favor, elija una diferente.',

    // ProfileController
    'voyti.userProfile.forbidden' => 'Prohibido',
    'voyti.userProfile.not_found' => 'Perfil no encontrado',

    // AdminController
    'voyti.admin.user_not_found' => 'Usuario no encontrado',
    'voyti.admin.profile_details_updated' => 'Los datos del perfil han sido actualizados',
    'voyti.admin.user_confirmed' => 'El usuario ha sido confirmado',
    'voyti.admin.unable_to_confirm' => 'No se pudo confirmar al usuario',
    'voyti.admin.user_deleted' => 'El usuario ha sido eliminado',
    'voyti.admin.password_change_required' => 'El usuario deberá cambiar la contraseña en el próximo inicio de sesión',
    'voyti.admin.error_occurred' => 'Se ha producido un error',
    'voyti.admin.cannot_delete_self' => 'No puede eliminar su propia cuenta',
    'voyti.admin.sessions_terminated' => 'Las sesiones han sido finalizadas',
    'voyti.admin.user_status_changed' => 'El estado del usuario ha sido actualizado',
    'voyti.admin.user_created' => 'El usuario ha sido creado',
    'voyti.admin.account_updated' => 'La cuenta ha sido actualizada',
    'voyti.admin.password_previously_used' => 'Esta contraseña se ha usado recientemente. Por favor, elija una diferente.',
    'voyti.admin.switch_identity_success' => 'Ahora ha iniciado sesión como este usuario',
    'voyti.admin.switch_identity_restored' => 'Se ha restaurado su identidad original',

    // RuleController
    'voyti.rule.added' => 'La regla de autorización ha sido añadida',
    'voyti.rule.updated' => 'La regla de autorización ha sido actualizada',
    'voyti.rule.deleted' => 'La regla de autorización ha sido eliminada',
    'voyti.rule.invalid_class' => 'Clase de regla no válida',

    'voyti.auth_item.not_found' => 'Elemento de autorización no encontrado',
    'voyti.auth_item.created' => 'El elemento de autorización ha sido creado',
    'voyti.auth_item.updated' => 'El elemento de autorización ha sido actualizado',
    'voyti.auth_item.deleted' => 'El elemento de autorización ha sido eliminado',

    // API AdminController
    'voyti.api.not_found' => 'No encontrado',
    'voyti.api.user_created' => 'Usuario creado',
    'voyti.api.user_updated' => 'Usuario actualizado',
    'voyti.api.password_previously_used' => 'Esta contraseña se ha usado recientemente. Por favor, elija una diferente.',
    'voyti.api.user_deleted' => 'Usuario eliminado',

    // PasswordRecoveryService
    'voyti.recovery.message_sent_if_exists' => 'Si el correo electrónico existe, se ha enviado un mensaje de recuperación',
    'voyti.recovery.message_sent' => 'Mensaje de recuperación enviado',

    // TwoFactorCodeValidator
    'voyti.validator.two_factor_not_configured' => 'La autenticación de dos factores no está configurada.',
    'voyti.validator.two_factor_library_missing' => 'La biblioteca 2FA (chillerlan/php-authenticator) no está instalada.',
    'voyti.validator.invalid_verification_code' => 'Código de verificación no válido.',
    'voyti.validator.password_complexity' => 'La contraseña debe contener al menos una letra mayúscula, una minúscula, un dígito y un carácter especial.',
    'voyti.validator.two_factor_enabled' => 'La autenticación de dos factores ha sido habilitada.',
    'voyti.validator.invalid_code_with_time' => 'Código no válido. Inténtelo de nuevo dentro de {timeDuration} segundos.',
    'voyti.validator.invalid_two_factor_code_with_time' => 'Código de autenticación de dos factores no válido. Inténtelo de nuevo dentro de {timeDuration} segundos.',

    // Mail subjects
    'voyti.mail.welcome_subject' => 'Bienvenido a {app}',
    'voyti.mail.confirmation_subject' => 'Confirme su cuenta en {app}',
    'voyti.mail.reconfirmation_subject' => 'Confirme el cambio de correo electrónico en {app}',
    'voyti.mail.recovery_subject' => 'Complete el restablecimiento de contraseña en {app}',
    'voyti.mail.two_factor_subject' => 'Código para la autenticación de dos factores en {app}',
    'voyti.mail.admin_notification_subject' => 'Nuevo registro de usuario en {app}',

    // Mail view templates
    'voyti.mail.welcome_heading' => '¡Bienvenido!',
    'voyti.mail.hello_username' => 'Hola {username},',
    'voyti.mail.account_created_successfully' => 'Su cuenta ha sido creada correctamente.',
    'voyti.mail.account_deleted_heading' => 'Cuenta eliminada',
    'voyti.mail.account_deleted_gdpr' => 'Su cuenta ha sido eliminada de acuerdo con el RGPD.',
    'voyti.mail.email_change_heading' => 'Confirmación de cambio de correo electrónico',
    'voyti.mail.click_to_confirm_email' => 'Haga clic en el siguiente enlace para confirmar su nueva dirección de correo electrónico:',
    'voyti.mail.password_recovery_heading' => 'Recuperación de contraseña',
    'voyti.mail.click_to_reset_password' => 'Haga clic en el siguiente enlace para restablecer su contraseña:',
    'voyti.mail.confirm_account_heading' => 'Confirme su cuenta',
    'voyti.mail.click_to_confirm_account' => 'Haga clic en el siguiente enlace para confirmar su cuenta:',
    'voyti.mail.twofactor_heading' => 'Código de autenticación de dos factores',
    'voyti.mail.your_twofactor_code' => 'Su código de autenticación de dos factores es:',

    // Navigation / Menu
    'voyti.menu.userProfile' => 'Perfil',
    'voyti.menu.account' => 'Cuenta',
    'voyti.menu.networks' => 'Redes',
    'voyti.menu.sessions' => 'Sesiones',
    'voyti.menu.two_factor' => 'Autenticación de dos factores',
    'voyti.menu.logout' => 'Cerrar sesión',

    // Login view
    'voyti.view.login.title' => 'Iniciar sesión',
    'voyti.view.login.login_label' => 'Usuario o correo electrónico',
    'voyti.view.login.remember_me' => 'Recordarme la próxima vez',
    'voyti.view.login.sign_in_button' => 'Iniciar sesión',
    'voyti.view.login.forgot_password' => '¿Olvidó su contraseña?',
    'voyti.view.login.register_link' => 'Registrarse',
    'voyti.view.login.password_label' => 'Contraseña',
    'voyti.view.login.remember_me_label' => 'Recordarme',

    // Two-factor confirm view
    'voyti.view.two_factor.title' => 'Autenticación de dos factores',
    'voyti.view.two_factor_google.button_label' => 'Google Authenticator',
    'voyti.view.two_factor.code_label' => 'Código de autenticación',
    'voyti.view.two_factor.verify_button' => 'Verificar',
    'voyti.view.two_factor.enabled_with_method' => 'La autenticación de dos factores por {method} está habilitada',
    'voyti.view.two_factor.disable' => 'Deshabilitar',
    'voyti.view.two_factor.disable_confirm_intro' => 'Para deshabilitar la autenticación de dos factores, necesitamos verificar que realmente eres tú. Se enviará un código de verificación a tu correo electrónico.',
    'voyti.view.two_factor.disable_send_code' => 'Enviar código para deshabilitar',
    'voyti.view.two_factor.scan_qr' => 'Escanee este código QR con su aplicación de autenticación',
    'voyti.view.two_factor.manual_entry' => 'O introduzca esta clave manualmente:',
    'voyti.view.two_factor.qr_unavailable' => 'El código QR no está disponible',
    'voyti.view.two_factor.enter_code' => 'Introduzca el código de verificación',
    'voyti.view.two_factor.enable' => 'Habilitar',
    'voyti.view.two_factor.verify' => 'Verificar',
    'voyti.view.two_factor.renew' => 'Renovar',
    'voyti.view.two_factor.renew_error' => 'No se pudo generar una nueva clave. Inténtelo de nuevo.',
    'voyti.view.two_factor.loading' => 'Cargando…',
    'voyti.view.two_factor.already_enabled' => 'La autenticación de dos factores ya está habilitada.',
    'voyti.view.two_factor.backup_codes_title' => 'Códigos de respaldo',
    'voyti.view.two_factor.backup_codes_intro' => 'Guarde estos códigos de respaldo de un solo uso en un lugar seguro. Cada uno se puede usar una vez para iniciar sesión si pierde el acceso a su autenticador o correo electrónico.',
    'voyti.view.two_factor.backup_codes_continue' => 'Continuar',
    'voyti.view.two_factor.backup_code_hint' => '¿Perdió el acceso a su dispositivo o correo electrónico? Puede introducir uno de sus códigos de respaldo en su lugar.',
    'voyti.view.two_factor.regenerate_backup_codes' => 'Regenerar códigos de respaldo',
    'voyti.view.two_factor.regenerate_backup_codes_intro' => 'Generar un nuevo conjunto de códigos de respaldo invalida todos los existentes. Introduzca su código de verificación actual o un código de respaldo para confirmar.',
    'voyti.view.two_factor.no_backup_codes_remaining' => 'No le quedan códigos de respaldo. Genere un nuevo conjunto para asegurarse de poder recuperar el acceso si pierde su dispositivo.',
    'voyti.view.two_factor_email.title' => 'Autenticación de dos factores por correo electrónico',
    'voyti.view.two_factor_email.button_label' => 'Correo electrónico',
    'voyti.view.two_factor_email.method_name' => 'correo electrónico',
    'voyti.view.two_factor_email.enter_code' => 'Introduzca el código de verificación enviado a su correo electrónico',
    'voyti.view.two_factor_email.confirm_intro' => 'Se enviará un código de verificación a la dirección de correo electrónico indicada a continuación.',
    'voyti.view.two_factor_email.send_button' => 'Enviar código',
    // Registration views
    'voyti.view.registration.register_title' => 'Crear cuenta',
    'voyti.view.registration.gdpr_consent_label' => 'Acepto el tratamiento de mis datos personales',
    'voyti.view.registration.register_button' => 'Registrarse',
    'voyti.view.registration.already_have_account' => '¿Ya tiene una cuenta?',
    'voyti.view.registration.resend_title' => 'Reenviar enlace de confirmación',
    'voyti.view.registration.connect_title' => 'Conectar cuenta',
    'voyti.view.registration.connect_provider' => 'Conecte su cuenta de {provider}',
    'voyti.view.registration.connect_message' => 'Puede conectar su cuenta social o registrar una nueva.',
    'voyti.view.registration.connect_login' => 'Iniciar sesión',
    'voyti.view.registration.connect_register' => 'Registrarse',

    // Recovery views
    'voyti.view.recovery.request_title' => 'Recuperar contraseña',
    'voyti.view.recovery.send_link_button' => 'Enviar enlace de recuperación',
    'voyti.view.recovery.back_to_login' => 'Volver al inicio de sesión',
    'voyti.view.recovery.reset_title' => 'Restablecer contraseña',
    'voyti.view.recovery.reset_button' => 'Restablecer contraseña',

    // UserProfile view
    'voyti.view.userProfile.email_label' => 'Correo electrónico',
    'voyti.view.userProfile.location_label' => 'Ubicación',
    'voyti.view.userProfile.bio_label' => 'Biografía',

    // Settings views
    'voyti.view.edit_profile.title' => 'Editar perfil',
    'voyti.view.userProfile.title' => 'Vista previa del perfil',
    'voyti.view.account.title' => 'Configuración de la cuenta',
    'voyti.view.networks.title' => 'Redes',
    'voyti.view.privacy.title' => 'Privacidad',
    'voyti.view.privacy.manage_gdpr_consent' => 'Gestionar consentimiento RGPD',
    'voyti.view.privacy.export_data' => 'Exportar mis datos',
    'voyti.view.privacy.anonymize_data' => 'Anonimizar mi cuenta',
    'voyti.view.privacy.delete_account' => 'Eliminar mi cuenta',
    'voyti.view.settings.privacy' => 'Privacidad',

    // GDPR views
    'voyti.view.gdpr.consent_title' => 'Consentimiento RGPD',
    'voyti.view.gdpr.consent_label' => 'Consiento el tratamiento de mis datos personales',
    'voyti.view.gdpr.consent_locked' => 'Ya ha dado su consentimiento el {date}. Esto no se puede deshacer.',
    'voyti.view.anonymize.title' => 'Anonimizar mi cuenta',
    'voyti.view.anonymize.warning' => 'Advertencia: esta acción anonimiza su cuenta (su correo electrónico y nombre de usuario serán sustituidos) y bloquea permanentemente el acceso. Esto no se puede deshacer.',
    'voyti.view.anonymize.confirm_label' => 'Entiendo que esta acción es irreversible',
    'voyti.view.anonymize.button' => 'Anonimizar mi cuenta',

    // Account settings (2FA)
    'voyti.view.account.two_factor_title' => 'Autenticación de dos factores',
    'voyti.view.account.two_factor_enabled' => 'La 2FA está habilitada',
    'voyti.view.account.disable_two_factor' => 'Deshabilitar 2FA',
    'voyti.view.account.enable_two_factor' => 'Habilitar 2FA',

    // Delete account view
    'voyti.view.delete_account.title' => 'Eliminar mi cuenta',
    'voyti.view.delete_account.warning' => 'Advertencia: esta acción elimina permanentemente su cuenta y todos los datos asociados. Esto no se puede deshacer.',
    'voyti.view.delete_account.confirm_label' => 'Entiendo que esta acción es irreversible',
    'voyti.view.delete_account.button' => 'Eliminar mi cuenta',

    // Admin views
    'voyti.view.admin.title' => 'Usuarios',
    'voyti.view.admin.create_user_title' => 'Crear usuario',
    'voyti.view.admin.create_user_link' => 'Crear usuario',
    'voyti.view.admin.update_user_title' => 'Actualizar usuario: {username}',
    'voyti.view.admin.update_profile_title' => 'Actualizar perfil',
    'voyti.view.admin.info_link' => 'Información',
    'voyti.view.admin.registered_label' => 'Registrado',
    'voyti.view.admin.session_history' => 'Historial de sesiones',
    'voyti.view.admin.sessions_link' => 'Sesiones',
    'voyti.view.admin.terminate_sessions' => 'Finalizar sesiones',
    'voyti.view.admin.switch_button' => 'Cambiar',
    'voyti.view.admin.restore_button' => 'Restaurar',
    'voyti.view.admin.switched_banner' => 'Actualmente ha iniciado sesión como este usuario. Haga clic en Restaurar para volver a {username}.',

    // RBAC views
    'voyti.view.assignments.title' => 'Asignaciones',
    'voyti.view.rule.title' => 'Reglas',
    'voyti.view.rule.create_title' => 'Crear regla',
    'voyti.view.rule.create_link' => 'Crear regla',
    'voyti.view.rule.update_title' => 'Actualizar regla',
    'voyti.view.rule.class_label' => 'Clase de regla',
    'voyti.view.permission.title' => 'Permisos',
    'voyti.view.permission.create_title' => 'Crear permiso',
    'voyti.view.permission.create_link' => 'Crear permiso',
    'voyti.view.permission.update_title' => 'Actualizar permiso: {name}',
    'voyti.view.role.title' => 'Roles',
    'voyti.view.role.create_title' => 'Crear rol',
    'voyti.view.role.create_link' => 'Crear rol',
    'voyti.view.role.update_title' => 'Actualizar rol: {name}',
    'voyti.view.audit_log.title' => 'Registro de auditoría',
    'voyti.view.audit_log.created_header' => 'Cuándo',
    'voyti.view.audit_log.actor_header' => 'Actor',
    'voyti.view.audit_log.action_header' => 'Acción',
    'voyti.view.audit_log.target_header' => 'Objetivo',
    'voyti.view.audit_log.context_header' => 'Detalles',

    // Assignments
    'voyti.view.assignments.assigned' => 'Asignado',
    'voyti.view.assignments.available' => 'Disponible',
    'voyti.view.assignments.update' => 'Actualizar asignaciones',
    'voyti.view.info_link' => 'Información',

    // Session history
    'voyti.view.session_history.title' => 'Historial de sesiones',
    'voyti.view.session_history.ip' => 'Dirección IP',
    'voyti.view.session_history.user_agent' => 'Agente de usuario',
    'voyti.view.session_history.last_seen' => 'Última vez visto',
    'voyti.view.sessions.title' => 'Sesiones activas',
    'voyti.view.sessions.this_device' => 'Este dispositivo',
    'voyti.view.sessions.none' => 'No hay sesiones activas.',

    // Pagination
    'voyti.view.filter_button' => 'Filtrar',
    'voyti.view.pagination_navigation' => 'Navegación de páginas',
    'voyti.view.previous' => 'Anterior',
    'voyti.view.next' => 'Siguiente',

    // Common view labels
    'voyti.view.username_label' => 'Usuario',
    'voyti.view.email_label' => 'Correo electrónico',
    'voyti.view.password_label' => 'Contraseña',
    'voyti.view.password_repeat_label' => 'Confirmar contraseña',
    'voyti.view.new_password_label' => 'Nueva contraseña',
    'voyti.view.new_password_repeat_label' => 'Confirmar nueva contraseña',
    'voyti.view.current_password_label' => 'Contraseña actual',
    'voyti.view.name_label' => 'Nombre',
    'voyti.view.description_label' => 'Descripción',
    'voyti.view.bio_label' => 'Biografía',
    'voyti.view.public_email_label' => 'Correo electrónico público',
    'voyti.view.not_set' => 'No establecido',
    'voyti.view.website_label' => 'Sitio web',
    'voyti.view.location_label' => 'Ubicación',
    'voyti.view.gravatar_email_label' => 'Correo electrónico de Gravatar',
    'voyti.view.timezone_label' => 'Zona horaria',
    'voyti.view.birthday_label' => 'Fecha de nacimiento',
    'voyti.view.password_keep_label' => 'Contraseña (déjelo vacío para mantenerla)',

    // Common table headers
    'voyti.view.id_header' => 'ID',
    'voyti.view.username_header' => 'Usuario',
    'voyti.view.email_header' => 'Correo electrónico',
    'voyti.view.status_header' => 'Estado',
    'voyti.view.name_header' => 'Nombre',
    'voyti.view.description_header' => 'Descripción',
    'voyti.view.children_header' => 'Elementos secundarios',
    'voyti.view.actions_header' => 'Acciones',

    // User status
    'voyti.view.status_blocked' => 'Bloqueado',
    'voyti.view.status_active' => 'Activo',
    'voyti.view.status_pending' => 'Pendiente',

    // Common buttons / links
    'voyti.view.create_button' => 'Crear',
    'voyti.view.save_button' => 'Guardar',
    'voyti.view.reset_button' => 'Restablecer',
    'voyti.view.update_button' => 'Actualizar',
    'voyti.view.update_link' => 'Actualizar',
    'voyti.view.delete_button' => 'Eliminar',
    'voyti.view.confirm_button' => 'Confirmar',
    'voyti.view.unblock_button' => 'Desbloquear',
    'voyti.view.block_button' => 'Bloquear',
    'voyti.view.force_password_change_button' => 'Forzar cambio de contraseña',
    'voyti.view.reset_password_button' => 'Enviar enlace de restablecimiento de contraseña',
    'voyti.view.update_profile_link' => 'Actualizar perfil',
    'voyti.view.send_button' => 'Enviar',
    'voyti.view.connect_button' => 'Conectar',
    'voyti.view.disconnect_button' => 'Desconectar',

    // Confirmation prompts
    'voyti.view.delete_user_confirm' => '¿Eliminar este usuario?',

    // Widgets
    'voyti.view.sessions.active_sessions' => 'Sesiones activas',
    'voyti.view.sessions.ip_label' => 'IP:',
    'voyti.view.sessions.last_activity_label' => 'Última actividad:',
    'voyti.view.connect.no_accounts' => 'No hay cuentas conectadas',
    'voyti.view.networks.no_networks' => 'No hay redes conectadas',
    'voyti.view.login.link' => 'Iniciar sesión',
    'voyti.view.logout.link' => 'Cerrar sesión',

    // Shared message view
    'voyti.view.go_home' => 'Ir al inicio',
];
