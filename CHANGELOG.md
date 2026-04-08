## 0.2.0 (April 08, 2026)
  - NEW: upgraded inbound email interactions
    - Added concise command-based email workflow for toast replies and `todo` digest replies
    - Added action support for `assign`, `due`, `comment`/`note`, `move`/`transfer`, `update`, and `reword`
    - Added confidence-gated execution:
      - high confidence commands are applied immediately
      - low confidence commands are returned as confirmation options
    - Added authenticated confirmation links for low-confidence actions
      - each option URL applies one explicit action
      - confirmation result is displayed in a centered in-app screen
    - Added task id references (`#<taskId>`) for multi-task email command execution
    - Improved outbound email clarity:
      - more compact acknowledgement and action-summary emails
      - per-action status reporting (`applied`, `pending_confirmation`, `failed`)
  - NEW: improved dashboard home layout
    - Promoted "My Actions" as the primary home section
    - Moved workspace list to a compact right-side rail
    - Refined workspace counter semantics and visual states (assigned count + late-state signaling)

## 0.1.1 (April 08, 2026)
  - TECH: updated configuration

## 0.1.0 (April 08, 2026)
  - NEW: First release
