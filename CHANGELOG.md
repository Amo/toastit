## 1.0.2 (April 13, 2026)
- NEW: legal notice and developer access links
  - Added a public `Legal notice & GDPR` page with privacy, cookie, and operator/hosting information
  - Added direct links to Public API docs and MCP server code from the profile API section and homepage footer
  - Added a proper site title and favicon across app and public API documentation pages

## 1.0.1 (April 13, 2026)
- FIX: improved production magic-link reliability with hardened reCAPTCHA Enterprise configuration

## 1.0.0 (April 13, 2026)
- NEW: Toastit v1 major product release
  - Introduced the new full-width workspace experience with a persistent left navigation and tabular toast views
  - Added a mobile-first navigation model (burger menu + full-screen mobile task surfaces)
  - Refined toast interaction model:
    - row click opens the toast permalink directly
    - list actions are optimized by context (mobile vs desktop, list vs detail)
    - vote updates are now in-place without full workspace refresh
- UX: major homepage refresh
  - Added a dedicated full-width “super footer” with core value pillars and legal block
  - Clarified positioning for both personas:
    - individual users (inbox-to-todo capture)
    - professional users (meeting execution, decisions, follow-ups)
  - Integrated brand logo usage across public and in-app navigation surfaces
- FIX: responsive layout and modal consistency polish
  - Restored desktop modal behavior while keeping mobile full-screen modal flow
  - Removed mobile spacing/overflow regressions across workspace headers and toast lists
  - Improved mobile readability with compact stats, tighter header spacing, and cleaner list density

## 0.10.2 (April 13, 2026)
- FIX: streamlined user account menu navigation
  - Removed redundant `Inbox` and `Workspace` entries from the user dropdown (desktop and mobile)
  - Kept access focused on account-level destinations (`My profile`, `Admin` when available, `Sign out`)

## 0.10.1 (April 13, 2026)
- FIX: clearer inbox email helper and faster copy UX
  - Expanded the inbox helper to list inbound email features: toast creation, `todo`, and `summary`
  - Improved readability of email-title commands by surfacing `todo` and `summary` as highlighted labels
  - Added one-click copy for the inbound email address with auto-select on click/focus
  - Replaced copy toast text feedback with a compact green check state on the button (3s)

## 0.10.0 (April 13, 2026)
- NEW: weekly operational summary by email (7-day window)
  - Added a one-click dashboard action to send a 7-day recap email
  - Added inbound email trigger: send an email with subject `summary` to receive the same recap
  - Built the summary from key task slices:
    - tasks you created and completed in the last 7 calendar days
    - tasks you created in the last 7 calendar days
    - tasks assigned to you and completed in the last 7 calendar days
  - Added language-aware AI prompting based on user language preference (with auto-detect fallback)
  - Enriched AI context with decision notes, follow-up details, and recent comments for more useful operational reporting

## 0.9.3 (April 13, 2026)
- FIX: hardened production ADC wiring for reCAPTCHA Enterprise
  - Corrected service-account credential mounting for both app and worker containers
  - Updated runtime environment template to document ADC credential path usage
- UX: faster magic-link continuation
  - Reduced automatic magic-link submit delay from 2s to 500ms while keeping the fallback click path

## 0.9.2 (April 13, 2026)
- SECURITY: reCAPTCHA Enterprise moved to Google-recommended server validation
  - Migrated magic-link protection to the official reCAPTCHA Enterprise PHP client using ADC/service-account authentication
  - Removed API-key based verification path in favor of enterprise assessment calls
  - Updated frontend execution to use `grecaptcha.enterprise` for token generation consistency

## 0.9.1 (April 13, 2026)
- FIX: production stability after reCAPTCHA rollout
  - Wired reCAPTCHA environment variables into both app and worker runtime containers
  - Prevented production startup failures caused by missing reCAPTCHA runtime configuration

## 0.9.0 (April 13, 2026)
- NEW: clearer homepage and faster login access
  - Simplified the homepage messaging around personal inbox capture and pro meeting execution
  - Added a prominent “login” action in the public header with a dedicated email login modal
  - Added a full-width product flow visual to make the onboarding path easier to understand
- FIX: improved PIN and magic-link reliability
  - PIN unlock now stays valid after long inactivity instead of forcing a full sign-out/sign-in loop
  - Magic-link flow is smoother with automatic continuation and a safe manual fallback
- SECURITY: stronger magic-link protection
  - Added reCAPTCHA Enterprise verification on magic-link consume to reduce automated abuse risk

## 0.8.0 (April 10, 2026)
- NEW: expanded workspace and toast workflow capabilities across API, MCP, and UI flows
  - Added dedicated public API endpoints to create and manage workspaces for external integrations
  - Added matching MCP server capabilities so workspace creation is directly available to agent workflows
  - Added a “set ready” flow for toasts, including assignee-driven progression in non-solo workspaces
  - Improved payload consistency and dashboard/workspace views to reflect the updated lifecycle actions

## 0.7.0 (April 10, 2026)
- NEW: richer Public API editing support for Toastit integrations
  - Added a dedicated endpoint to update toast descriptions from external clients
  - Enabled Markdown-friendly description updates through the MCP Toastit tools
  - Improved integration parity so API and MCP flows now cover creation, assignment, due date, comments, vote/boost, and description edits
- FIX: replace legacy route fallback with proper not-found page
- TECH: simplified toast workflow model and naming consistency
  - Unified toast lifecycle into a single status field (`pending`, `ready`, `treated`, `vetoed`)
  - Removed legacy dual-status storage to reduce ambiguity between discussion and workflow state
  - Aligned database naming with the domain model (`workspace`, `workspace_member`, `toast`)

## 0.6.0 (April 10, 2026)
- NEW: first public API for Toastit integrations
  - Added a dedicated API domain flow (`api.toastit.cc`) with versioning via `Accept` header
  - Added personal access token (PAT) authentication for external API calls
  - Added profile controls to create, list, and revoke personal API tokens
  - Added public endpoints to:
    - list accessible workspaces
    - create toasts
    - update assignee and due date
    - post comments
    - set boost and vote states
  - Added paginated listing endpoints for:
    - workspace toasts (with status filter)
    - toast comments
  - Added a public API documentation page at `/doc`
- TECH: refined changelog wording and formalized `CHANGELOG update` convention

## 0.5.1 (April 09, 2026)
- FIX: improved reliability for AI prompt loading
  - Prevented a case where AI-generated refinements could fail even when prompts existed
  - Added safer fallback behavior so prompt rendering issues do not block inbound toast refinement

## 0.5.0 (April 09, 2026)
- NEW: better inbound email understanding and smarter auto-routing
  - Improved how inbound emails are transformed into actionable toasts:
    - clearer titles and richer structured descriptions
    - more consistent workspace, owner, and due date suggestions
    - stronger fallback behavior when AI output is incomplete
  - Improved assignment and routing safety:
    - more reliable owner selection
    - more predictable workspace selection in ambiguous cases
  - Added language preferences for inbound AI rewording:
    - users can keep automatic language detection or force a preferred language
    - setting is available in profile preferences
- TECH: deployment and maintenance improvements
  - improved environment handling for migrations and workers
  - tightened production environment requirements
  - refreshed internal project documentation

## 0.4.0 (April 09, 2026)
- NEW: AI prompt management in admin, with version history
  - Added a centralized prompt library in the database
  - Added admin tools to view, edit, version, and roll back prompts
  - Improved consistency of AI outputs across summarization, refinement, curation, and execution flows
  - Improved safety for workspace auto-selection with confidence checks

## 0.3.0 (April 09, 2026)
- NEW: inbound email AI automation now supports granular auto-apply preferences
  - Added automatic AI improvements for inbound toasts:
    - reword title/description
    - suggest assignee
    - suggest due date
    - suggest target workspace
  - Added per-user controls for which suggestions are auto-applied
  - Improved profile experience with clearer sections and instant preference saving
  - Improved outbound email follow-up loop with actionable reply addresses and clearer summaries

## 0.2.1 (April 08, 2026)
- NEW: simplified homepage and clearer feature positioning
  - Streamlined landing content and reduced visual complexity
  - Surfaced key capabilities: meeting todo workflow, AI-powered summaries, and AI assistant
  - Kept quick access to authentication while improving product update visibility

## 0.2.0 (April 08, 2026)
- NEW: upgraded inbound email workflows
  - Added concise command-based replies for toast and `todo` digest emails
  - Added support for `assign`, `due`, `comment`/`note`, `move`/`transfer`, `update`, and `reword`
  - Added confidence-aware execution:
    - clear commands are applied immediately
    - ambiguous commands return explicit confirmation options
  - Added secure confirmation links for low-confidence actions
  - Added task ID references (`#<taskId>`) for multi-task commands
  - Improved outbound email readability with compact summaries and clearer action statuses
- NEW: improved dashboard home layout
  - Promoted “My Actions” as the primary home section
  - Moved the workspace list to a compact right-side rail
  - Refined workspace counter semantics and visual states (assigned count + late-state signaling)

## 0.1.1 (April 08, 2026)
- TECH: configuration updates

## 0.1.0 (April 08, 2026)
- NEW: first release
