## 1.5.1 (April 17, 2026)
  - FIX: fix profile payload and normalize grok 4.20 model alias

## 1.5.0 (April 17, 2026)
- NEW: smoother daily toasting and workspace flow
  - Active toasts are now easier to process with a clearer `New + Ready` flow.
  - Ready toasts are more visible in the interface, and finishing a toast returns you to the actionable list.
  - You can create a new toast directly while toasting, with tighter navigation on desktop and cleaner mobile actions.
- NEW: stronger admin control for route and AI operations
  - Route/root admins can now impersonate users with a clear stop-impersonation flow.
  - Added advanced xAI model controls per user, plus visible app indicators where that mode is enabled.
  - Added AI comment-thread summarization and improved session-summary prompting.
- NEW: versioned AI prompt sources in the codebase
  - Current production AI prompts are now stored as Twig files in the repository instead of living only in the database.
  - Prompt loading now prefers versioned files while keeping database history available as fallback/reference.
- FIX: clearer and more reliable AI-generated digests
  - Refined digest and summary prompt rules to produce more actionable, better-structured results.
- TECH: documented the local environment workflow for contributors and agents

## 1.4.2 (April 15, 2026)
  - TECH: env

## 1.4.1 (April 15, 2026)
  - TECH: updated env

## 1.4.0 (April 15, 2026)
- NEW: personal productivity and capture flow upgrades
  - Added a richer My Day experience with focus queue interactions and snooze resurfacing.
  - Added a global quick-add flow with capture timestamp tracking.
  - Added speech-to-text capture via a microphone action.
  - Improved My Actions interactions and responsiveness on dashboard and mobile flows.
- NEW: clearer personal vs team boundaries
  - Made personal versus team modes explicit in the UI.
  - Added explicit private/shared boundaries when moving toasts.
  - Added a personal retention digest with progress KPIs.
- NEW: daily operations and automation support
  - Added a daily collaboration digest command and scheduler backup assets.
  - Tightened digest targeting to active users with at least one login.
- FIX: reliability and performance hardening
  - Fixed async mobile toast creation to return immediately.
  - Hardened workspace summary handling and increased xAI timeout tolerance.
- TECH: deployment and infra documentation improvements
  - Added local HTTPS workflow helpers for development.
  - Added a production hardening compose override.
  - Added/updated VPS runbooks for scheduler tasks, infra cleanup, and DB migration.

## 1.3.0 (April 14, 2026)
- NEW: smoother workspace and toast creation flow
  - Split workspace status filtering into two dedicated views: `New` and `Ready` (#181)
  - Assignee filtering now applies reliably without duplicate page refresh behavior (#183)
  - AI draft improvement is now non-blocking in the toast modal, so editing can continue while suggestions are generated (#182)
- NEW: timezone preference across app and emails
  - Added a user timezone picker in profile preferences (#23)
  - Applied the selected timezone to date rendering in app payloads and session recap emails (#23)
- NEW: better text input ergonomics
  - Text form fields now auto-expand to keep full content visible while typing (#145)
- FIX: safer frontend asset refresh after deployment
  - Updated caching strategy to fetch fresh frontend assets after redeploy, especially on mobile where force-refresh is limited (#180)
- FIX: English-only copy consistency
  - Authentication emails and remaining mixed-language UI labels are now aligned in English (#184)

## 1.2.0 (April 14, 2026)
- UX: major polish release for mobile and desktop consistency
  - Mobile profile is now a full page flow (not modal-like), with clearer section navigation and more consistent spacing.
  - Profile/Admin entries are easier to scan and navigate on mobile, with improved scroll behavior and touch targets.
  - Mobile filters and creation flows are more robust: better readability, fewer action conflicts, and cleaner picker interactions.
- UX: clearer and more stable toast/workspace lists
  - Workspace counters are now more stable and readable (better segment sizing, spacing, and alignment).
  - Toast list readability was improved across breakpoints (title visibility, due-date visibility, sticky headers on mobile).
  - Toasted/ready visual states are clearer and more consistent.
- UX: improved toast detail actions (mobile + desktop)
  - Action bars were reorganized to follow a clearer priority order and better grouping.
  - Desktop toast action layout now matches the cleaner interaction logic introduced on mobile.
  - Better continuity after actions (for example, improved return behavior after solo-workspace toasting).
- AI-assisted drafting and form behavior improvements
  - Reword/refine now reliably uses the current form content during edit flows.
  - The AI refinement state is now clearer to users, with explicit locking feedback and timeout guidance.
  - Draft/title editing behavior was refined (auto-grow, autofocus, and safer interaction during refinement).
- Visual language and design consistency updates
  - CTA palette and primary action styling were standardized for a more consistent interface.
  - Legacy color accents were cleaned up in key app surfaces.
  - Date picker and several form controls were reworked for cleaner, more predictable behavior.
- Product quality and operations
  - Login challenge email now includes a complete support/legal footer.
  - Inbox now has clearer attention counters in web/desktop navigation.
  - Admin user cleanup surfaces are safer and clearer for stale/inactive accounts.

## 1.1.0 (April 14, 2026)
- NEW: redesigned mobile experience to feel like a real app
  - Simplified mobile navigation with a fixed bottom bar (Toasts, Workspaces, Profile)
  - Reworked main screens for smoother reading and navigation on mobile
  - Updated floating action buttons for easier thumb interaction
- NEW: clearer priorities and list readability
  - Toasts now show clearer visual priority states (late, due soon, boosted)
  - Colors and icons are now consistent across the app
  - Removed redundant or unnecessary elements to make screens lighter
- NEW: clearer workspace list
  - More explicit per-workspace counters (late, due soon, boosted)
  - Zero-value counters are hidden
  - Counters are grouped into a single right-aligned capsule
  - Shows the total number of active toasts (new + ready) per workspace
- NEW: improved toast view and editing on mobile
  - Sticky header with more consistent back navigation and better long-title handling
  - Toast actions repositioned to stay accessible while scrolling
  - Delete confirmation is now integrated in-app (instead of browser alerts)
  - Edit form remains visible above the bottom navigation bar
- REMAINING WORK
  - Profile
  - Preferences
  - Admin
  - Edit form
  - AI actions (at least some views/actions still need mobile adaptation)

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
