<?php

declare(strict_types=1);

return [
    // SecurityController
    'voyti.security.invalid_login' => 'Неверный логин или пароль',
    'voyti.security.account_blocked' => 'Ваш аккаунт заблокирован',
    'voyti.security.need_email_confirmation' => 'Вам необходимо подтвердить ваш email-адрес',
    'voyti.security.logged_in' => 'Вы вошли в систему',
    'voyti.security.logged_out' => 'Вы вышли из системы',
    'voyti.security.authenticated' => 'Аутентифицирован',

    // RegistrationController
    'voyti.registration.disabled' => 'Регистрация отключена',
    'voyti.registration.invalid_confirmation_link' => 'Неверная ссылка подтверждения',
    'voyti.registration.complete' => 'Спасибо, регистрация завершена.',
    'voyti.registration.confirmation_link_invalid' => 'Ссылка подтверждения недействительна или истекла.',
    'voyti.registration.email_confirmation_disabled' => 'Подтверждение email отключено',
    'voyti.registration.new_confirmation_sent' => 'Новая ссылка подтверждения отправлена',

    // RecoveryController
    'voyti.recovery.disabled' => 'Восстановление пароля отключено',
    'voyti.recovery.reset_disabled' => 'Сброс пароля отключён',
    'voyti.recovery.link_invalid' => 'Ссылка восстановления недействительна или истекла',
    'voyti.recovery.password_changed' => 'Пароль изменён',

    // SettingsController
    'voyti.settings.not_authenticated' => 'Не аутентифицирован',
    'voyti.settings.user_not_found' => 'Пользователь не найден',
    'voyti.settings.profile_updated' => 'Ваш профиль обновлён',
    'voyti.settings.account_details_updated' => 'Данные вашего аккаунта обновлены',
    'voyti.settings.not_available' => 'Недоступно',
    'voyti.settings.personal_info_removed' => 'Ваша личная информация удалена',
    'voyti.settings.account_deletion_disabled' => 'Удаление аккаунта отключено',
    'voyti.settings.account_deleted' => 'Ваш аккаунт удалён',
    'voyti.settings.gdpr_consent_saved' => 'Согласие GDPR сохранено',
    'voyti.settings.two_factor_enabled' => 'Двухфакторная аутентификация включена',
    'voyti.settings.two_factor_disabled' => 'Двухфакторная аутентификация отключена',
    'voyti.settings.email_changed' => 'Ваш email-адрес изменён',
    'voyti.settings.email_change_failed' => 'Не удалось изменить email-адрес',
    'voyti.settings.network_disconnected' => 'Сеть отключена',
    'voyti.settings.network_not_found' => 'Сеть не найдена',
    'voyti.settings.data_exported' => 'Ваши данные экспортированы',

    // ProfileController
    'voyti.userProfile.forbidden' => 'Доступ запрещён',
    'voyti.userProfile.not_found' => 'Профиль не найден',

    // AdminController
    'voyti.admin.user_created' => 'Пользователь создан',
    'voyti.admin.user_not_found' => 'Пользователь не найден',
    'voyti.admin.account_details_updated' => 'Данные аккаунта обновлены',
    'voyti.admin.profile_details_updated' => 'Данные профиля обновлены',
    'voyti.admin.user_confirmed' => 'Пользователь подтверждён',
    'voyti.admin.unable_to_confirm' => 'Не удалось подтвердить пользователя',
    'voyti.admin.user_deleted' => 'Пользователь удалён',
    'voyti.admin.block_status_updated' => 'Статус блокировки пользователя обновлён',
    'voyti.admin.unable_to_update_block' => 'Не удалось обновить статус блокировки',
    'voyti.admin.password_change_required' => 'Пользователь должен будет сменить пароль при следующем входе',
    'voyti.admin.error_occurred' => 'Произошла ошибка',
    'voyti.admin.cannot_delete_self' => 'Вы не можете удалить свой собственный аккаунт',
    'voyti.admin.sessions_terminated' => 'Сессии завершены',

    // RuleController
    'voyti.rule.added' => 'Правило авторизации добавлено',
    'voyti.rule.updated' => 'Правило авторизации обновлено',
    'voyti.rule.removed' => 'Правило авторизации удалено',
    'voyti.rule.invalid_class' => 'Недопустимый класс правила',

    // AbstractAuthItemController (permissions/roles)
    'voyti.auth_item.permission_created' => 'Разрешение создано',
    'voyti.auth_item.permission_updated' => 'Разрешение обновлено',
    'voyti.auth_item.permission_deleted' => 'Разрешение удалено',
    'voyti.auth_item.role_created' => 'Роль создана',
    'voyti.auth_item.role_updated' => 'Роль обновлена',
    'voyti.auth_item.role_deleted' => 'Роль удалена',
    'voyti.auth_item.not_found' => 'Элемент авторизации не найден',

    // API AdminController
    'voyti.api.not_found' => 'Не найдено',
    'voyti.api.user_created' => 'Пользователь создан',
    'voyti.api.user_updated' => 'Пользователь обновлён',
    'voyti.api.user_deleted' => 'Пользователь удалён',

    // PasswordRecoveryService
    'voyti.recovery.message_sent_if_exists' => 'Если указанный email существует, сообщение для восстановления отправлено',
    'voyti.recovery.message_sent' => 'Сообщение для восстановления отправлено',

    // TwoFactorCodeValidator
    'voyti.validator.two_factor_not_configured' => 'Двухфакторная аутентификация не настроена.',
    'voyti.validator.two_factor_library_missing' => 'Библиотека 2FA (chillerlan/php-authenticator) не установлена.',
    'voyti.validator.invalid_verification_code' => 'Неверный проверочный код.',
    'voyti.validator.two_factor_enabled' => 'Двухфакторная аутентификация включена.',
    'voyti.validator.invalid_code_with_time' => 'Неверный код. Пожалуйста, повторите попытку в течение {timeDuration} секунд.',
    'voyti.validator.invalid_two_factor_code_with_time' => 'Неверный код двухфакторной аутентификации. Пожалуйста, повторите попытку в течение {timeDuration} секунд.',

    // ModuleConfig mail subjects
    'voyti.mail.welcome_subject' => 'Добро пожаловать в {app}',
    'voyti.mail.confirmation_subject' => 'Подтверждение аккаунта на {app}',
    'voyti.mail.reconfirmation_subject' => 'Подтверждение смены email на {app}',
    'voyti.mail.recovery_subject' => 'Завершение сброса пароля на {app}',
    'voyti.mail.two_factor_subject' => 'Код для двухфакторной аутентификации на {app}',

    // Mail view templates
    'voyti.mail.welcome_heading' => 'Добро пожаловать!',
    'voyti.mail.hello_username' => 'Здравствуйте, {username},',
    'voyti.mail.account_created_successfully' => 'Ваш аккаунт успешно создан.',
    'voyti.mail.account_deleted_heading' => 'Аккаунт удалён',
    'voyti.mail.account_deleted_gdpr' => 'Ваш аккаунт удалён в соответствии с GDPR.',
    'voyti.mail.email_change_heading' => 'Подтверждение смены email',
    'voyti.mail.click_to_confirm_email' => 'Нажмите на ссылку ниже, чтобы подтвердить ваш новый email-адрес:',
    'voyti.mail.password_recovery_heading' => 'Восстановление пароля',
    'voyti.mail.click_to_reset_password' => 'Нажмите на ссылку ниже, чтобы сбросить ваш пароль:',
    'voyti.mail.confirm_account_heading' => 'Подтверждение аккаунта',
    'voyti.mail.click_to_confirm_account' => 'Нажмите на ссылку ниже, чтобы подтвердить ваш аккаунт:',
    'voyti.mail.twofactor_heading' => 'Код двухфакторной аутентификации',
    'voyti.mail.your_twofactor_code' => 'Ваш код двухфакторной аутентификации:',

    // Navigation / Menu
    'voyti.menu.userProfile' => 'Профиль',
    'voyti.menu.account' => 'Аккаунт',
    'voyti.menu.networks' => 'Сети',
    'voyti.menu.two_factor' => '2FA',

    // Login view
    'voyti.view.login.title' => 'Войти',
    'voyti.view.login.login_label' => 'Имя пользователя или электронной почте',
    'voyti.view.login.remember_me' => 'Запомнить меня',
    'voyti.view.login.sign_in_button' => 'Войти',
    'voyti.view.login.forgot_password' => 'Забыли пароль?',
    'voyti.view.login.register_link' => 'Регистрация',
    'voyti.view.login.password_label' => 'Пароль',
    'voyti.view.login.remember_me_label' => 'Запомнить меня',

    // Two-factor confirm view
    'voyti.view.two_factor.title' => 'Двухфакторная аутентификация',
    'voyti.view.two_factor.code_label' => 'Код аутентификации',
    'voyti.view.two_factor.verify_button' => 'Проверить',
    'voyti.view.two_factor.enabled' => 'Двухфакторная аутентификация включена',
    'voyti.view.two_factor.disable' => 'Отключить',
    'voyti.view.two_factor.scan_qr' => 'Сканируйте этот QR-код с помощью приложения-аутентификатора',
    'voyti.view.two_factor.manual_entry' => 'Или введите этот ключ вручную:',
    'voyti.view.two_factor.qr_unavailable' => 'QR-код недоступен',
    'voyti.view.two_factor.enter_code' => 'Введите проверочный код',
    'voyti.view.two_factor.enable' => 'Включить',
    'voyti.view.two_factor.verify' => 'Проверить',
    'voyti.view.two_factor_email.title' => 'Двухфакторная аутентификация по email',
    'voyti.view.two_factor_email.enter_code' => 'Введите проверочный код, отправленный на ваш email',
    'voyti.view.two_factor_sms.title' => 'Двухфакторная аутентификация по SMS',
    'voyti.view.two_factor_sms.phone' => 'Номер телефона',
    'voyti.view.two_factor_sms.send' => 'Отправить код',

    // Registration views
    'voyti.view.registration.register_title' => 'Создать аккаунт',
    'voyti.view.registration.gdpr_consent_label' => 'Я согласен на обработку моих персональных данных',
    'voyti.view.registration.register_button' => 'Зарегистрироваться',
    'voyti.view.registration.already_have_account' => 'Уже есть аккаунт?',
    'voyti.view.registration.resend_title' => 'Отправить ссылку подтверждения повторно',
    'voyti.view.registration.connect_title' => 'Привязать аккаунт',
    'voyti.view.registration.connect_provider' => 'Привязать ваш аккаунт {provider}',
    'voyti.view.registration.connect_message' => 'Вы можете привязать свой социальный аккаунт или зарегистрировать новый.',
    'voyti.view.registration.connect_login' => 'Войти',
    'voyti.view.registration.connect_register' => 'Регистрация',

    // Recovery views
    'voyti.view.recovery.request_title' => 'Восстановление пароля',
    'voyti.view.recovery.send_link_button' => 'Отправить ссылку для восстановления',
    'voyti.view.recovery.back_to_login' => 'Назад к входу',
    'voyti.view.recovery.reset_title' => 'Сброс пароля',
    'voyti.view.recovery.reset_button' => 'Сбросить пароль',

    // UserProfile view
    'voyti.view.userProfile.email_label' => 'Email:',
    'voyti.view.userProfile.name_label' => 'Имя:',
    'voyti.view.userProfile.location_label' => 'Местоположение:',
    'voyti.view.userProfile.bio_label' => 'О себе:',

    // Settings views
    'voyti.view.userProfile.title' => 'Настройки профиля',
    'voyti.view.account.title' => 'Настройки аккаунта',
    'voyti.view.networks.title' => 'Сети',
    'voyti.view.privacy.title' => 'Конфиденциальность',
    'voyti.view.privacy.manage_gdpr_consent' => 'Управление согласием GDPR',
    'voyti.view.privacy.delete_data' => 'Удалить мои данные',
    'voyti.view.settings.title' => 'Настройки',
    'voyti.view.settings.userProfile' => 'Профиль',
    'voyti.view.settings.account' => 'Аккаунт',
    'voyti.view.settings.networks' => 'Сети',
    'voyti.view.settings.privacy' => 'Конфиденциальность',

    // GDPR views
    'voyti.view.gdpr.consent_title' => 'Согласие GDPR',
    'voyti.view.gdpr.consent_label' => 'Я даю согласие на обработку моих персональных данных',
    'voyti.view.gdpr.delete_title' => 'Удалить мой аккаунт',
    'voyti.view.gdpr.delete_warning' => 'Внимание: Это действие безвозвратно удалит ваш аккаунт и все связанные данные.',
    'voyti.view.gdpr.delete_confirm_label' => 'Я понимаю, что это действие необратимо',
    'voyti.view.gdpr.delete_button' => 'Удалить мой аккаунт',

    // Account settings (2FA)
    'voyti.view.account.two_factor_title' => 'Двухфакторная аутентификация',
    'voyti.view.account.two_factor_enabled' => '2FA включена',
    'voyti.view.account.disable_two_factor' => 'Отключить 2FA',
    'voyti.view.account.enable_two_factor' => 'Включить 2FA',

    // Admin views
    'voyti.view.admin.title' => 'Пользователи',
    'voyti.view.admin.create_user_title' => 'Создание пользователя',
    'voyti.view.admin.create_user_link' => 'Создать пользователя',
    'voyti.view.admin.update_user_title' => 'Редактирование пользователя: {username}',
    'voyti.view.admin.update_profile_title' => 'Редактирование профиля',
    'voyti.view.admin.info_link' => 'Информация',
    'voyti.view.admin.registered_label' => 'Зарегистрирован',
    'voyti.view.admin.session_history' => 'История сессий',
    'voyti.view.admin.terminate_sessions' => 'Завершить сессии',

    // RBAC views
    'voyti.view.assignments.title' => 'Назначения',
    'voyti.view.rule.title' => 'Правила',
    'voyti.view.rule.create_title' => 'Создание правила',
    'voyti.view.rule.create_link' => 'Создать правило',
    'voyti.view.rule.update_title' => 'Редактирование правила',
    'voyti.view.rule.class_label' => 'Класс правила',
    'voyti.view.permission.title' => 'Разрешения',
    'voyti.view.permission.create_title' => 'Создание разрешения',
    'voyti.view.permission.create_link' => 'Создать разрешение',
    'voyti.view.permission.update_title' => 'Редактирование разрешения: {name}',
    'voyti.view.role.title' => 'Роли',
    'voyti.view.role.create_title' => 'Создание роли',
    'voyti.view.role.create_link' => 'Создать роль',
    'voyti.view.role.update_title' => 'Редактирование роли: {name}',

    // Assignments
    'voyti.view.assignments.assigned' => 'Назначено',
    'voyti.view.assignments.available' => 'Доступно',
    'voyti.view.assignments.update' => 'Обновить назначения',
    'voyti.view.info_link' => 'Info',

    // Session history
    'voyti.view.session_history.title' => 'История сессий',
    'voyti.view.session_history.ip' => 'IP-адрес',
    'voyti.view.session_history.user_agent' => 'User agent',
    'voyti.view.session_history.created' => 'Создано',

    // Pagination
    'voyti.view.filter_button' => 'Фильтр',
    'voyti.view.pagination_navigation' => 'Навигация по страницам',
    'voyti.view.previous' => 'Предыдущая',
    'voyti.view.next' => 'Следующая',

    // Common view labels
    'voyti.view.username_label' => 'Имя пользователя',
    'voyti.view.email_label' => 'Email',
    'voyti.view.password_label' => 'Пароль',
    'voyti.view.new_password_label' => 'Новый пароль',
    'voyti.view.current_password_label' => 'Текущий пароль',
    'voyti.view.name_label' => 'Имя',
    'voyti.view.description_label' => 'Описание',
    'voyti.view.bio_label' => 'О себе',
    'voyti.view.public_email_label' => 'Публичный email',
    'voyti.view.website_label' => 'Веб-сайт',
    'voyti.view.location_label' => 'Местоположение',
    'voyti.view.gravatar_email_label' => 'Gravatar email',
    'voyti.view.timezone_label' => 'Часовой пояс',
    'voyti.view.password_keep_label' => 'Пароль (оставьте пустым, чтобы не менять)',

    // Common table headers
    'voyti.view.id_header' => 'ID',
    'voyti.view.username_header' => 'Имя пользователя',
    'voyti.view.email_header' => 'Email',
    'voyti.view.status_header' => 'Статус',
    'voyti.view.name_header' => 'Имя',
    'voyti.view.description_header' => 'Описание',
    'voyti.view.children_header' => 'Дочерние элементы',
    'voyti.view.actions_header' => 'Действия',

    // User status
    'voyti.view.status_blocked' => 'Заблокирован',
    'voyti.view.status_active' => 'Активен',
    'voyti.view.status_pending' => 'Ожидает подтверждения',

    // Common buttons / links
    'voyti.view.create_button' => 'Создать',
    'voyti.view.save_button' => 'Сохранить',
    'voyti.view.update_button' => 'Обновить',
    'voyti.view.update_link' => 'Обновить',
    'voyti.view.delete_button' => 'Удалить',
    'voyti.view.confirm_button' => 'Подтвердить',
    'voyti.view.unblock_button' => 'Разблокировать',
    'voyti.view.block_button' => 'Заблокировать',
    'voyti.view.force_password_change_button' => 'Принудительная смена пароля',
    'voyti.view.update_profile_link' => 'Редактировать профиль',
    'voyti.view.send_button' => 'Отправить',
    'voyti.view.connect_button' => 'Привязать',
    'voyti.view.disconnect_button' => 'Отключить',

    // Confirmation prompts
    'voyti.view.delete_user_confirm' => 'Удалить этого пользователя?',

    // Widgets
    'voyti.view.sessions.active_sessions' => 'Активные сессии',
    'voyti.view.sessions.ip_label' => 'IP:',
    'voyti.view.sessions.last_activity_label' => 'Последняя активность:',
    'voyti.view.connect.no_accounts' => 'Нет привязанных аккаунтов',
    'voyti.view.networks.no_networks' => 'Нет подключённых сетей',
    'voyti.view.login.link' => 'Войти',
    'voyti.view.logout.link' => 'Выйти',

    // Shared message view
    'voyti.view.go_home' => 'На главную',
];
