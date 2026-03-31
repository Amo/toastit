# Toastit Technical Foundation

## Purpose

This document captures the current technical baseline of Toastit.

It is intentionally technical and normative. It describes:

- the execution stack
- the security model
- the UI and design-system discipline
- the local development environment
- the testing strategy
- the conventions that must be preserved while the product grows

This file is meant to serve as the first stable reference for future development.

## Product and UI Direction

Toastit is built as a mobile-first Symfony application.

The target architecture is:

- Symfony for all business logic and security
- Twig for server rendering
- Alpine.js only for small interaction logic
- Bulma as the primitive CSS framework
- a strict component-first design system layered on top of Bulma

The application must not drift toward a SPA architecture.

That means:

- no React-style client-side state architecture
- no front-end API orchestration as the main application model
- no page-specific UI inventions when an existing component already exists

The browser is responsible for:

- rendering server-provided pages
- small interactive behavior
- segmented code input UX
- lightweight overlays and local interactions

Symfony remains responsible for:

- authentication
- authorization
- state transitions
- email flows
- persistence
- security decisions

## Runtime Stack

### Application runtime

- Symfony 8
- PHP 8.5 on FrankenPHP
- MariaDB 11.4
- Docker Compose for local orchestration

### Local services

- `php`: FrankenPHP application container
- `database`: MariaDB container

### Key project files

- `compose.yaml`
- `Dockerfile`
- `docker/frankenphp/Caddyfile`
- `docker/frankenphp/docker-entrypoint.sh`
- `.env`

### Make targets

The project currently standardizes local actions through:

- `make up`
- `make down`
- `make logs`
- `make bash`
- `make migrate`
- `make test`

These are intended to remain the primary developer entrypoints for common tasks.

## Security and Authentication Model

Toastit uses a two-step authentication and unlock model.

### Step 1: Email-based authentication

There is a single email entrypoint for both:

- sign in
- account creation

Behavior:

1. user submits an email
2. email is normalized
3. user is looked up
4. user is created if absent
5. an OTP challenge is created
6. an email is sent with:
   - a magic login link
   - a 6-character alphanumeric OTP code

Important invariants:

- login and account creation share the same flow
- the UI must not reveal whether the email previously existed
- OTP challenges are time-limited and one-time-use

### Step 2: PIN unlock

After Symfony authentication succeeds, the user must pass a second unlock layer:

- first successful login: define a 4-digit PIN
- subsequent sessions: enter the PIN
- active authenticated session: reverrouillage after 1 hour since last PIN verification

Important invariants:

- the PIN is numeric and exactly 4 digits
- the PIN is hashed
- the PIN is not a substitute for Symfony authentication; it is an app-level unlock layer

### Current authentication building blocks

Domain and security code currently lives in:

- `src/Entity/User.php`
- `src/Entity/LoginChallenge.php`
- `src/Security/LoginChallengeManager.php`
- `src/Security/ChallengeFactory.php`
- `src/Security/PinManager.php`
- `src/Security/PinSessionManager.php`
- `src/Security/OtpLoginAuthenticator.php`
- `src/EventSubscriber/PinLockSubscriber.php`
- `src/Controller/AuthController.php`
- `src/Controller/PinController.php`

### Roles

User roles are persisted in the `roles` JSON column on `User`.

Current role model:

- every user gets `ROLE_USER`
- privileged accounts can receive `ROLE_ROOT`

The command:

- `toastit:user:root`

promotes an existing user to `ROLE_ROOT`.

`ROLE_ROOT` is reserved for future application administration capabilities and must be treated as a privileged role from now on.

## Mail Strategy

Toastit uses Symfony Mailer, but development and production are intentionally different.

### Development

In `dev`, email is not sent to an external provider.

Instead, a local custom transport writes each message to:

- `var/storage/mails`

This is required behavior and must be preserved.

It gives the team:

- inspectable mail payloads
- deterministic local debugging
- no accidental external delivery during development

### Email templating

All emails must go through a shared HTML email layout.

Current structure:

- `templates/emails/base.html.twig`
- `templates/emails/auth/login_challenge.html.twig`
- `templates/emails/auth/login_challenge.txt.twig`

Important invariant:

- every email must use the shared HTML template system, even if the visual design is still temporary

No email should be assembled as ad hoc raw HTML inside controllers or services.

## Design System Discipline

The design system is a hard constraint, not a loose guideline.

The current source of truth is:

- `assets/styles/app.scss`
- `templates/components/ui`
- `templates/components/forms`
- `templates/design_system/index.html.twig`
- `DESIGN-SYSTEM.md`
- `AGENTS.md`

### Core UI principle

Pages must compose approved components.

Pages must not invent their own primitives.

### Current base components

Examples already introduced:

- icon
- button
- alert
- panel
- section heading
- inline email form
- segmented code input

### Icon policy

All icons must go through the icon component:

- `templates/components/ui/_icon.html.twig`

This must remain true across:

- page actions
- form buttons
- overlays
- icon showcases
- future navigation and admin tooling

### Color policy

Color usage must flow through tokens.

No page or component should hard-code its own palette unless it is:

- a documented new system token
- added to the design system reference page

### Design system page

The design system page is not decorative. It is a living reference.

It currently showcases:

- color tokens
- typography
- spacing and surfaces
- button states
- OTP/PIN interaction patterns
- tabular UI
- overlays
- breadcrumb and pagination
- Font Awesome usage

Any new reusable UI pattern should be added there before being duplicated across product pages.

## Front-End Interaction Model

Alpine.js is allowed only for lightweight interaction logic.

That includes:

- segmented code inputs
- focus transitions
- drawers and sheets
- local interaction demos
- small stateful display logic

It must not become:

- a business-state store
- a client-side application shell
- a replacement for Symfony controllers and security

## Persistence and Migrations

Doctrine ORM is the persistence layer.

Current migrations of note:

- initial auth tables
- role support on `User`

Migrations must continue to be additive and explicit.

Already-applied migrations should not be rewritten casually.

## Test Strategy

The repository now has:

- unit tests
- integration tests
- a dedicated test database flow

### Test execution

Use:

- `make test`

This currently:

1. recreates `app_test`
2. grants access to the app database user
3. runs doctrine migrations in test
4. clears local mail storage artifacts
5. runs PHPUnit

### Test database

Tests run against a dedicated database:

- `app_test`

This isolation must remain in place.

### Current coverage baseline

The current suite covers:

- email normalization
- OTP challenge generation
- login request flow
- mail storage side effects
- OTP and magic-link integration
- PIN setup and unlock flow

This is only a baseline; future business logic should continue the same split:

- unit tests for pure domain/services
- integration tests for HTTP/security/persistence flows

## Development Tooling

### Web Debug Toolbar

The Symfony Web Debug Toolbar and profiler are enabled in development.

This is required for now and should remain available while the application is still being shaped.

Routes include:

- `/_wdt`
- `/_profiler`

### Local shell access

The expected shell entrypoint is:

- `make bash`

This should stay stable for the development workflow.

## Rules To Preserve

The following are not optional and should be considered architectural constraints:

1. Symfony owns security and business logic.
2. Authentication stays email-first, with OTP + magic link.
3. PIN remains a second unlock layer, not a replacement for authentication.
4. Development mail must be stored locally in `var/storage/mails`.
5. Every email must use the shared HTML templating system.
6. The UI must remain component-first and server-rendered.
7. All icons must pass through the icon component.
8. All reusable patterns must be reflected in the design system page.
9. Tests must continue to run through `make test` against a dedicated test DB.

## Recommended Next Documentation

This foundation file should be followed later by:

- a business domain document
- an authentication flow document with sequence diagrams
- an admin/ROOT capability document
- a component catalog document if the design system grows substantially
