# Toastit Technical Foundation

## Purpose

This document captures the current technical baseline of Toastit.

It is normative and complements `AGENTS.md` with broader system context:

- runtime stack and local environment
- architecture boundaries
- security/authentication model
- mail and inbound processing model
- design-system and front-end constraints
- testing and workflow expectations

## Product Architecture

Toastit is Symfony-first with a Vue product shell.

- Symfony owns business rules, security, workflows, persistence, and payload shaping.
- Vue owns interactive screens and local interaction state.
- Product behavior is JSON API first.
- HTML rendering is reserved for app bootstrapping and auth entry surfaces.

This is not a Twig+Alpine product architecture anymore.

Hard constraints:

- no business-rule reimplementation in Vue
- no permission decisions in the browser
- no persistence logic in controllers
- no parallel workflow when an existing backend service can be extended

## Runtime Stack

### Application runtime

- Symfony 8
- PHP 8.5 on FrankenPHP (`dunglas/frankenphp:1-php8.5-bookworm`)
- Doctrine ORM + Doctrine Migrations
- MariaDB
- Docker Compose for local orchestration

### Front-end runtime

- Vue 3
- Vite
- Tailwind CSS
- shared styles in `assets/frontend/styles/app.css` and `assets/styles/app.scss`

### Local services

- `php`: app runtime (FrankenPHP)
- `database`: MariaDB
- `mailer`: dev mail catcher
- `inbound-smtp`: local inbound email bridge

## Project Structure (Current)

- Backend domain/services: `src/Workspace`, `src/Meeting`, `src/Security`, `src/Admin`, `src/Ai`
- API/controllers: `src/Controller`, `src/Controller/Api`
- Persistence: `src/Entity`, `src/Repository`, `migrations`
- Front-end UI: `assets/frontend/components`
- Front-end API/utilities: `assets/frontend/api`, `assets/frontend/utils`
- Front-end routing: `assets/frontend/router.js`

## Development Workflow

Primary local commands are defined in `ops/make/20-local.mk`:

- `make up`
- `make down`
- `make logs`
- `make bash`
- `make migrate`
- `make build`
- `make dev`
- `make test`

Keep these as the default workflow entrypoints.

## Security and Authentication

Toastit uses two-step access:

1. Email challenge login (magic link + OTP)
2. PIN unlock layer after authentication

### Email challenge invariants

- same flow for sign-in and account creation
- no user-enumeration leak at UI level
- challenge validity is time-bound and one-time-use

### PIN invariants

- numeric 4-digit PIN
- stored hashed
- unlock layer is additive, not a replacement for authentication

### Roles

- `ROLE_USER` baseline
- `ROLE_ROOT` for privileged admin capabilities

Server remains authoritative for all authorization decisions.

## AI Integration Baseline

Toastit AI calls are server-side only.

- AI orchestration must remain in Symfony services.
- Front-end triggers explicit API endpoints and renders returned payloads.
- Prompts are database-backed and versioned (`ai_prompt`, `ai_prompt_version`).
- Prompt rendering uses Twig templates with explicit variables and stable contracts.
- AI responses should use strict structured envelopes when defined (for example `result.*`).

Configuration remains environment-driven (`XAI_API_KEY`, `XAI_BASE_URL`, `XAI_MODEL`, `XAI_TIMEOUT_SECONDS`).

## Mail and Inbound Email

### Outbound mail (dev)

- In development, mail is captured locally through the mailer container.
- Email generation must go through shared templates under `templates/emails/`.

### Inbound mail

- Inbound email is converted into Toastit inbox/workspace flows server-side.
- Inbound processing is protected by shared secret and domain config.
- Heavy processing is queued (Messenger), not done inline on receipt.

Key env configuration:

- `INBOUND_EMAIL_DOMAIN`
- `INBOUND_EMAIL_SECRET`
- `MESSENGER_TRANSPORT_DSN`

## Design System and UI Discipline

Design rules are strict, not optional.

Source of truth:

- `assets/styles/app.scss`
- `assets/frontend/styles/app.css`
- `assets/frontend/components`
- `DESIGN-SYSTEM.md`
- `AGENTS.md`

Rules:

- compose pages from reusable components
- reuse tokens/styles before inventing new primitives
- avoid inline style attributes except unavoidable edge cases
- avoid one-off UI patterns when an existing component can be extended

## Persistence and Migrations

- Doctrine is the persistence layer.
- Schema changes must be introduced via new migrations.
- Existing shipped migrations are append-only unless explicitly instructed otherwise.

## Testing Strategy

Test split:

- unit tests for isolated domain/service behavior
- integration tests for HTTP wiring, auth/permissions, and persistence effects

Execution:

- run `make test`
- test workflow recreates `app_test`, applies migrations, then runs PHPUnit

Behavioral changes in auth, permissions, workflows, or payload contracts should be covered by automated tests.

## Non-Negotiable Constraints

1. Symfony remains authoritative for business logic and security.
2. API contracts are explicit and stable for front-end screens.
3. Front-end stays component-first and does not absorb domain decisions.
4. Mail and inbound processing stay server-driven and auditable.
5. DB schema evolution stays migration-driven.
6. Design-system rules must be respected for new UI work.
7. Tests must remain part of every non-trivial behavioral change.
