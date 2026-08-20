# Voyti — Yii3 User Management Extension

> **войти**  
> ***/vɐjˈtʲi/***  
> *verb*
>
> "to enter" or "to log in"

Highly customizable and extensible user management, authentication, and authorization extension for [Yii Framework 3](https://www.yiiframework.com/).

Originally ported from [Usuario](https://github.com/2amigos/yii2-usuario), Voyti has since been rebuilt around modern PSR standards and Yiisoft components. It has been extensively redesigned to provide a flexible, modular foundation that adapts to a wide range of authentication and authorization requirements.

[![Packagist Version](https://img.shields.io/packagist/v/yiirocks/voyti.svg)](https://packagist.org/packages/yiirocks/voyti)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/yiirocks/voyti.svg)](https://php.net/)
[![Packagist](https://img.shields.io/packagist/dt/yiirocks/voyti.svg)](https://packagist.org/packages/yiirocks/voyti)
[![GitHub](https://img.shields.io/github/license/yiirocks/voyti.svg)](https://github.com/yiirocks/voyti/blob/main/LICENSE.md)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/yiirocks/voyti/build.yml?branch=main)](https://github.com/yiirocks/voyti/actions)

Stats for Nerds

[![Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti%2Fbadges%2Fcoverage.json)](https://github.com/yiirocks/voyti/tree/badges)
[![MSI](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti%2Fbadges%2Fmsi.json)](https://github.com/yiirocks/voyti/tree/badges)
[![Tests](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti%2Fbadges%2Ftests.json)](https://github.com/yiirocks/voyti/tree/badges)
[![Assertions](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti%2Fbadges%2Fassertions.json)](https://github.com/yiirocks/voyti/tree/badges)

---

## Features

- **User Management** — Registration, email confirmation, login/logout with remember-me, password recovery, password expiration
- **Profile Management** — User profiles with gravatar, timezone, bio, and a personal website link
- **Social Authentication** — OAuth2 login via Google, GitHub, Facebook, and more
- **Two-Factor Authentication** — TOTP (authenticator app) with QR code provisioning, email 2FA, WebAuthn, per-permission enforcement and recovery codes
- **RBAC Management** — Full admin UI for roles, permissions, and rules with parent-child hierarchy, assignment management, and filtering
- **Identity Switching** — Admins can temporarily switch into another user's identity for support or debugging, then restore their own session with one click
- **Session Management** — Session tracking and termination
- **GDPR Compliance** — Consent management, data export, anonymized deletion with admin notification
- **Password Policies** — Minimum complexity requirements, max age enforcement via middleware
- **Email Change Confirmation** — Three modes: immediate, confirm new address, confirm both old and new
- **REST API** — JSON user CRUD
- **Bot Protection** — Google reCAPTCHA v2/v3 for registration and login forms
- **i18n** — Built-in translations for multiple languages
- **Themed Views** — Bootstrap 5 views shipped by default; mail templates separate and independently overridable
- **Toast Notifications** — Native Bootstrap toast support with automatic fallback to flash messages

## Requirements

- PHP 8.3+
- ext-intl
- Various yiisoft packages (automatic installation via composer)

## Installation

```bash
composer require yiirocks/voyti yiirocks/voyti-views-bootstrap5
```

## Documentation

The complete reference guide is available at [Yii.Rocks](https://www.yii.rocks/voyti/).
