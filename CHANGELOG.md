## 0.5.0 (April 09, 2026)
- NEW: inbound email rewrite quality and routing reliability improvements
  - Introduced a unified inbound rewrite flow for title/description/workspace/owner/due date:
    - added a dedicated DB-backed prompt `inbound_email_rewrite_system`
    - switched inbound ingestion to consume a single structured rewrite payload when available
    - kept fallback compatibility with previous refinement + workspace suggestion path
  - Hardened prompt contracts and runtime behavior:
    - strengthened JSON output constraints and workspace/member routing rules
    - clarified workspace context format in prompt user templates
    - improved temporal resolution guidance for relative/literal due-date signals
    - added explicit logging for invalid AI due-date outputs (`inbound.email_due_date_invalid`)
  - Improved ownership and workspace assignment safety:
    - owner now falls back to requester when AI owner resolution is invalid
    - workspace resolution behavior is deterministic under invalid/ambiguous suggestions
  - Added profile-level language control for inbound rewording:
    - added user preference `inbound_reword_language` (`auto` + top 10 common languages)
    - exposed/validated setting via profile API and profile page preferences UI
    - propagated language instruction into inbound rewrite/refinement prompt contexts
- TECH: local/deployment and documentation updates
  - improved local compose/make environment handling for migrations/workers
  - tightened required production compose environment variables
  - refreshed AGENTS and technical foundation documentation

## 0.4.0 (April 09, 2026)
- NEW: database-backed AI prompt management with versioning, admin editing, and stricter AI contracts
  - Added prompt registry persistence:
    - introduced `ai_prompt` + `ai_prompt_version` entities/repositories with migration-backed schema
    - seeded core AI prompts and appended multiple prompt versions to evolve system/user templates safely
    - added template metadata (`availableVariables`, `availableUserVariables`) for Twig-driven prompt rendering
  - Added ROOT prompt management workflow:
    - new admin page at `/admin/prompts` with prompt selection, system/user template editors, save-as-new-version, and rollback
    - new protected API endpoints: list/detail/update/rollback (`/api/admin/prompts*`)
    - admin navigation now links overview, users, and prompts pages together
  - Switched AI features to resolve prompts from DB templates:
    - `ToastDraftRefinementService`, `WorkspaceSuggestionService`, `TodoDigestService`, `ToastCurationDraftService`,
      `ToastExecutionPlanDraftService`, and `ToastingSessionSummaryService` now use `AiPromptTemplateService`
    - curation/execution/todo/summary pipelines now accept strict JSON envelopes (`result.*`) with backward-safe parsing
    - workspace suggestion now enforces confidence gating (`>= 90`) before auto-selection
  - Adjusted inbound-email auto-routing behavior:
    - when workspace suggestion is disabled or inconclusive, inbox-created toasts are transferred to the actor default workspace

## 0.3.0 (April 09, 2026)
- NEW: inbound email AI automation now supports granular auto-apply preferences
  - Added automatic xAI refinement for inbound toasts:
    - reword title/description
    - suggest and apply assignee
    - suggest and apply due date
    - suggest and apply target workspace
  - Added per-user inbound AI preferences (default `on` for all):
    - `reword`, `assignee`, `dueDate`, `workspace`
    - Preferences are exposed in profile API payloads and persisted in database
  - Updated profile experience:
    - Introduced profile subpages with sidebar navigation: `Infos` (default), `Preferences`, `Trash`, `Account`
    - Preference toggles now save on change (no submit button)
    - Added explicit visual feedback per toggle (`saving`, `saved`, `error`)
  - Improved outbound email command loop:
    - Reply emails now include actionable `Reply-To` addresses for follow-up commands
    - Acknowledgement and action-summary emails include complete task state snapshots

## 0.2.1 (April 08, 2026)
- NEW: simplified homepage and clearer feature positioning
  - Streamlined landing content and reduced visual complexity
  - Surfaced key capabilities: meeting todo workflow, AI-powered summaries, and AI assistant
  - Kept quick access to authentication while improving product update visibility

## 0.2.0 (April 08, 2026)
- NEW: upgraded inbound email workflows
  - Introduced concise command-based replies for toast and `todo` digest emails
  - Added action support for `assign`, `due`, `comment`/`note`, `move`/`transfer`, `update`, and `reword`
  - Added confidence-gated execution:
    - high-confidence commands are applied immediately
    - low-confidence commands are returned as explicit confirmation choices
  - Added authenticated confirmation links for low-confidence actions
    - each option URL applies exactly one explicit action
    - confirmation result is shown in a centered in-app screen
  - Added task ID references (`#<taskId>`) for multi-task command execution
  - Improved outbound email readability:
    - more compact acknowledgement and action-summary messages
    - per-action status reporting (`applied`, `pending_confirmation`, `failed`)
- NEW: improved dashboard home layout
  - Promoted “My Actions” as the primary home section
  - Moved the workspace list to a compact right-side rail
  - Refined workspace counter semantics and visual states (assigned count + late-state signaling)

## 0.1.1 (April 08, 2026)
- TECH: configuration updates

## 0.1.0 (April 08, 2026)
- NEW: first release
