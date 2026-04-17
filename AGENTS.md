# AGENTS.md

## Purpose

This file defines the contribution rules that keep Toastit coherent over time.

It is normative.  
If this file conflicts with older docs, this file wins for implementation choices.

## Current stack (authoritative)

- Symfony 8 (`symfony/*` 8.0)
- PHP `>=8.4`
- Doctrine ORM + Doctrine Migrations
- MariaDB
- Vite + Vue 3
- Tailwind utilities in Vue templates
- Shared styling through `assets/frontend/styles/app.css` and `assets/styles/app.scss`

## Architecture boundary

Toastit is a Symfony-first product with a Vue app shell.

- Symfony owns business rules, permissions, workflows, persistence, auth, and payload shaping.
- Vue owns interactive product screens and local UI state only.
- Product behavior is JSON API first.
- HTML responses are for app bootstrapping and auth surfaces, not new product flows.

Hard rules:

- no business decision logic in Vue components
- no persistence logic in controllers
- no direct database usage from front-end code
- no parallel workflow path when an existing service can be extended

## Backend rules

### Responsibility split

- Controllers in `src/Controller` orchestrate request/security/response only.
- Domain logic lives in dedicated services (`src/Workspace`, `src/Meeting`, `src/Security`, `src/Admin`, `src/Ai`, etc.).
- Entities hold state/invariants; they do not shape API responses.
- API payloads must be deliberate screen contracts, never raw entity dumps.

### Service extension first

Before adding a new service/class, check whether behavior belongs in existing modules:

- `src/Workspace`
- `src/Meeting`
- `src/Security`
- `src/Admin`
- `src/Ai`
- `src/Api`

Prefer extending coherent existing workflows over creating near-duplicates.

### API contract discipline

- Keep payload keys stable and explicit.
- Compute permission-sensitive flags server-side.
- Keep date/datetime formatting consistent and explicit.
- Do not leak internal fields just because they exist on entities.
- If payload shape changes, update all consumers and tests in the same slice.

### Security rules

- Server is authoritative for auth, unlock, roles, and authorization checks.
- Any state-changing endpoint must enforce access checks server-side.
- UI affordances can hide actions; they cannot grant permissions.

### Migration rules

- Every schema change requires a new Doctrine migration.
- Never rewrite old shipped migrations unless explicitly requested.
- Keep migration intent in commit/PR text; filenames remain generated.

## Front-end rules

### Current structure (authoritative)

- Reusable/front-end feature components: `assets/frontend/components`
- API client/helpers: `assets/frontend/api`, `assets/frontend/utils`
- Router: `assets/frontend/router.js`

There is no required `assets/frontend/pages` tree today; do not enforce it as a rule.

### Component discipline

- Reuse/extend existing components before creating new ones.
- Keep large route-level shells thin and composition-oriented.
- Extract repeated UI blocks into reusable components early.
- Avoid giant single-file components that mix unrelated concerns.

### Styling discipline

- Reuse existing tokens and shared styles first.
- Prefer established styles in `assets/frontend/styles/app.css` and `assets/styles/app.scss`.
- No inline style attributes unless technically unavoidable.
- No one-off color/spacing primitives that bypass shared design language.

### Router discipline

- New product pages must be explicitly registered in `assets/frontend/router.js`.
- Route names should remain stable once consumed by navigation logic.
- Each route should map to a clear backend payload source.

## Testing rules

### Test where behavior lives

- Unit tests for isolated service/domain/value-shaping logic.
- Integration tests for controller wiring, permissions, persistence effects, and flow behavior.

### Minimum expectation

Non-trivial changes should add/update tests when affecting:

- authentication or PIN flows
- permissions/roles/access checks
- workspace workflows
- meeting mode behavior
- API payload contracts
- inbound/outbound email behavior

### Manual validation is not enough

If a bug or feature required debugging, capture the key behavior in automated tests when practical.

## Naming and code conventions

- Follow PSR-4 and existing namespace layout.
- Prefer clear domain-oriented names.
- Avoid vague catch-all utility classes.
- Keep method names explicit about business intent.
- Do not introduce interfaces without a concrete need.

Note: existing classes may still use legacy suffixes (`*Manager`, `*Handler`, etc.).  
Do not rename broadly without a focused refactor task.

## Change management

### Prefer extension over parallel systems

Do not introduce a second:

- auth mechanism
- workspace workflow for same behavior
- payload assembly path for same screen intent
- design language

### Keep changes coherent

- One change-set should solve one coherent problem.
- Avoid mixing major refactor, schema redesign, and UI overhaul unless tightly coupled.
- Preparatory refactors should preserve behavior and prove it with tests.

### Keep docs in sync when rules change

When architecture or conventions intentionally change, update:

- `AGENTS.md`
- `DESIGN-SYSTEM.md`
- `doc/technical-foundation.md`

## Explicitly discouraged patterns

- business logic inside Vue components
- raw entity serialization as API contract
- duplicated permission logic in front and back
- ad hoc API shape that bypasses established services/workflows
- near-duplicate UI components instead of extending existing ones
- payload key changes without synchronized consumer/test updates
- adding dependencies for problems already solved by current stack

## Changelog convention

- When a request says `CHANGELOG update`, interpret it as: reword the latest changes in `CHANGELOG.md` for end-user sharing, while keeping the existing release/version structure.

## Local environment notes

These notes are operational shortcuts for future sessions on this repository.

### Docker-first local workflow

- The effective local app environment is Docker Compose, not bare host PHP.
- Main services:
  - `php`: Symfony app container
  - `database`: MariaDB
  - `mailer`: Mailpit SMTP/UI for local email inspection
  - `worker`: async worker container
  - `inbound-smtp`: local inbound email bridge
- If a console command needs database, mailer, or container hostnames such as `database` or `mailer`, prefer running it in the `php` container:
  - `docker compose exec -T php php bin/console ...`

### Console and cache behavior

- Host-level `php bin/console ...` may fail locally for commands that need `DATABASE_URL=mysql://...@database:3306/...`, because `database` resolves only inside Docker Compose.
- If a newly added Symfony command is missing in `prod`, clear the Symfony cache before debugging further:
  - `php bin/console cache:clear`
- `APP_ENV=dev php bin/console list` is useful to confirm whether a command exists before assuming registration is broken.

### Local email testing

- Local outbound email uses Mailpit.
- The Compose stack exposes Mailpit SMTP and UI from the `mailer` service.
- For local verification of emails, trigger the mail from the `php` container, then inspect it in Mailpit rather than relying on external delivery.

### Useful local commands

- Run a Symfony command inside the app container:
  - `docker compose exec -T php php bin/console <command>`
- Run targeted PHPUnit from the host when no container-only hostname is needed:
  - `php bin/phpunit --filter <TestName>`
- Check running local services:
  - `docker compose ps`

## Contributor checklist

Before merging, verify:

1. The solution extends the current architecture instead of creating a parallel path.
2. Server-side remains authoritative for business and security decisions.
3. Front-end remains component-driven and thin on domain logic.
4. Existing reusable components were checked before adding new UI blocks.
5. API payload changes are deliberate, stable, and fully consumed.
6. Tests cover changed behavior at the right level.
7. Docs were updated when standing rules changed.
