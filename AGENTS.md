# AGENTS.md

## Design System Rules

This repository uses a strict component-first UI system on top of Bulma.

Pages must assemble approved Twig components and theme tokens.
Pages must not invent their own UI patterns.

## Source of Truth

- Theme tokens: `assets/styles/app.scss`
- UI components: `templates/components/ui`
- Form components: `templates/components/forms`
- Visual reference: `templates/design_system/index.html.twig`
- Design system guide: `DESIGN-SYSTEM.md`

## Mandatory Rules

1. All colors, radii, shadows, and spacing values must come from existing tokens.
2. New pages must use existing Twig components whenever a matching pattern already exists.
3. No inline `<style>` blocks in page templates.
4. No hard-coded hex or rgba values in templates.
5. All icons must use `templates/components/ui/_icon.html.twig`.
6. Buttons must use `templates/components/ui/_button.html.twig` unless there is a documented exception.
7. Alerts must use `templates/components/ui/_alert.html.twig`.
8. Panels must use `templates/components/ui/_panel.html.twig`.
9. Section intros must use `templates/components/ui/_section_heading.html.twig`.

## Allowed Exceptions

- Favicon markup in the base layout.
- Third-party snippets explicitly required by a dependency.
- Prototype-only code inside the design system page, as long as it still uses the official components where possible.

## Workflow For New UI

1. Check the design system page for an existing pattern.
2. If the pattern exists, reuse its component.
3. If the pattern does not exist, add the component first.
4. Update the design system page with the new pattern.
5. Only then use the component in product pages.

## Review Checklist

- Is every icon rendered through the icon component?
- Is every button rendered through the button component?
- Are colors referenced through tokens instead of raw values?
- Is the page composed of reusable blocks instead of local one-off markup?
- Was the design system page updated if a new pattern was introduced?
