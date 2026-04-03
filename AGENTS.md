# AGENTS.md

## Purpose

This file defines the contribution rules that must keep the codebase consistent over time.

It is normative.

When a rule here conflicts with an older document, this file wins for day-to-day implementation choices.

## Repository context

Toastit currently runs on:

- Symfony 8 for application runtime, security, domain logic, persistence, and HTTP entrypoints
- PHP 8.4+ with Doctrine ORM
- MariaDB
- Vite for front-end bundling
- Vue 3 for the product UI shell
- Tailwind utility classes in the Vue app
- Sass for shared styling entrypoints

## Architecture stance

Toastit is not a pure server-rendered Twig app anymore.
Toastit is also not a front-end-first application where the browser owns business logic.

The current target is:

- Symfony owns business rules, permissions, workflows, persistence, authentication, and payload composition
- Vue owns interactive product screens and client-side composition of already-approved data flows
- The back-end contract is JSON API first
- HTML responses exist only for application bootstrapping
- API endpoints remain thin and explicit
- Controllers do orchestration, not domain decisions
- Payload builders and services shape data for the UI

This means:

- no business rules inside Vue components
- no persistence logic inside controllers
- no undocumented direct database access from the front-end
- no new HTML product responses except the minimal app boot entrypoint
- no ad hoc API surface when an existing workflow/service should be extended instead

## Backend rules

### 0. Global implementation rules

- Always follow Symfony coding-style rules for PHP code.
- Prefer composition over inheritance.
- Do not create an interface unless at least two concrete classes need to implement the contract, or there is an immediate and explicit architectural reason.

### 1. Keep responsibilities explicit

- Controllers under `src/Controller` coordinate request, security, and response only.
- Domain rules belong in dedicated services under `src/`.
- Data exposed to the front-end must be assembled by payload builders or dedicated application services.
- Entities must stay focused on state and invariants, not response formatting.
- Product behavior should be exposed through JSON responses, not server-rendered HTML screens.

### 2. Prefer existing workflows before adding new services

Before creating a new service, inspect existing code in:

- `src/Workspace`
- `src/Security`
- `src/Meeting`
- `src/Api`

If the behavior extends an existing workflow, keep it there unless the class is becoming incoherent.

### 3. Keep API shape stable and predictable

- API controllers must return payloads shaped for the current screen, not raw entity dumps.
- Prefer JSON responses for product flows; HTML is reserved for app bootstrapping only.
- Date fields sent to the front-end must be explicit and consistently formatted.
- Permission-sensitive flags must be computed server-side.
- Do not leak internal-only fields just because they exist on the entity.

### 4. Make security decisions on the server

- Authentication, unlock logic, roles, and permission checks stay in Symfony.
- Vue may hide or disable UI affordances, but the server must remain authoritative.
- Any action that changes state must validate access server-side.

### 5. Keep migrations disciplined

- Every schema change requires a Doctrine migration.
- Never edit an old migration that has already become part of project history unless explicitly required.
- Migration names are generated; business intent belongs in commit/PR description, not hand-renamed filenames.

## Front-end rules

### 1. Respect the current front-end boundary

The Vue app is the interactive shell for product pages.

Use Vue for:

- authenticated product screens
- local interaction state
- optimistic or transient UI state when safe
- composition of server-provided payloads

Do not use Vue for:

- re-implementing business workflows already decided on the server
- inventing a second validation or authorization model
- bypassing explicit API contracts

### 2. Component discipline

- Front-end code must be split between page components and reusable components.
- Target structure:
  - `assets/frontend/pages` for route-level pages
  - `assets/frontend/components` for reusable UI or feature components
- Pages compose focused components.
- Shared presentation logic must move into reusable components before duplication spreads.
- Before creating any new component, always verify that an equivalent or extensible component does not already exist.
- If a close existing component exists, extend or adapt it instead of creating a parallel one.
- Keep route-level components thin; pages must orchestrate and compose, not contain large repeated UI blocks.
- Avoid giant single-file components that mix fetching, mutations, rendering, and formatting without structure.
- New UI work must default to component extraction, not inline local markup inside a page.

### 3. Styling discipline

- Reuse the existing visual language before inventing new patterns.
- Prefer the established app styles in `assets/frontend/styles/app.css` and shared Sass entrypoints.
- If a new visual primitive becomes reusable, promote it instead of copying markup and classes.
- No inline style attributes unless technically unavoidable.
- No one-off color decisions that bypass the existing design language.

### 4. Router discipline

- New product pages must be registered explicitly in [assets/frontend/router.js](/Users/amaury/code/toastit/assets/frontend/router.js).
- Route names must stay stable once used by the app shell or navigation logic.
- A route component should map cleanly to a back-end payload contract.

## API and screen contract rules

- Every screen that fetches data should have a clearly identifiable back-end source.
- The back-end should serve JSON for application behavior; do not introduce new HTML response flows for product features.
- Prefer extending an existing payload builder over scattering formatting across controllers.
- If the front-end needs a new flag, label, or derived field, compute it server-side.
- Keep naming consistent between payload keys and front-end usage.
- Avoid breaking payload shape casually; when unavoidable, update all affected screens and tests in the same change.

## Testing rules

### 1. Test where the behavior lives

- Unit tests for isolated domain logic, value shaping, and pure service behavior
- Integration tests for end-to-end flows, permissions, controller wiring, and persistence effects

### 2. Minimum expectation for feature work

Any non-trivial change should include or update tests when it affects:

- authentication or PIN flows
- permissions
- workspace workflows
- API payload shape
- meeting mode behavior
- item lifecycle behavior

### 3. Do not rely on manual-only validation

If a bug or feature required a debugging session, capture the important behavior in an automated test when practical.

## File and naming conventions

- Follow PSR-4 and the existing namespace layout.
- Backend names must use a noun that describes the domain concept, then the concrete type suffix.
- Prefer names such as `WorkspaceController`, `AgendaEntity`, `ToastService`, `LoginChallengeRepository`, `DashboardPayloadBuilder`.
- Avoid names built around verbs or role-based suffixes such as `Provider`, `Validator`, `Manager`, `Handler`, or similar vague technical labels.
- New controllers should still remain consistent with the existing tree, but future naming should converge toward noun-plus-type naming.
- Services should be named after the domain concept they represent, not vague technical verbs.
- Avoid catch-all utility classes.
- Keep method names explicit about business intent.

## Change management rules

### 1. Prefer extension over parallel systems

When a pattern already exists, extend it.

Do not introduce:

- a second workspace workflow path
- a second auth mechanism
- a second design language
- a second way to assemble the same payload category

### 2. Keep docs synchronized

Update documentation when you intentionally change:

- architecture direction
- design-system rules
- local developer workflow
- security-sensitive behavior
- a major payload contract

At minimum, review:

- [AGENTS.md](/Users/amaury/code/toastit/AGENTS.md)
- [DESIGN-SYSTEM.md](/Users/amaury/code/toastit/DESIGN-SYSTEM.md)
- [doc/technical-foundation.md](/Users/amaury/code/toastit/doc/technical-foundation.md)

### 3. Prefer small coherent slices

- One change should solve one coherent problem.
- Avoid mixing schema changes, major refactors, and visual redesigns unless they are tightly coupled.
- If a refactor is preparatory, keep behavior stable and prove it with tests.

## Explicitly discouraged patterns

- putting business decisions in Vue components
- returning raw entity data directly from API controllers
- duplicating permission logic between front and back
- embedding formatting and orchestration logic directly in entities
- creating new page-local UI blocks without first checking whether an existing component already covers the need
- creating near-duplicate front-end components instead of extending an existing one
- keeping reusable UI inside page files when it should be extracted to `assets/frontend/components`
- adding new UI patterns without aligning them with the existing design system
- changing payload keys without updating the consumers and tests
- introducing new dependencies for problems already solved by the current stack

## Contributor checklist

Before merging a change, verify:

1. The solution follows the existing architectural boundary instead of creating a parallel path.
2. Back-end business rules remain server-owned.
3. Front-end code stays component-oriented and does not absorb domain logic.
4. Any new page-level work respects the `pages/` vs `components/` separation target.
5. An existing reusable component was checked before creating a new one.
6. API payload changes are deliberate, minimal, and fully consumed.
7. Tests cover the changed behavior at the correct level.
8. Documentation was updated if the change altered a standing rule.
