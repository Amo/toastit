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
