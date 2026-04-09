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
