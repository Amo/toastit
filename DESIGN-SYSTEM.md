# Toastit Design System

## Goal

This repository uses a strict component-first UI system in the Vue front-end.

Pages must assemble approved front-end components and theme tokens.
Pages must not invent their own UI patterns.

## Source of truth

- Theme tokens: [assets/styles/app.scss](/Users/amaury/code/toastit/assets/styles/app.scss)
- Front-end components: [assets/frontend/components](/Users/amaury/code/toastit/assets/frontend/components)
- App styling entrypoint: [assets/frontend/styles/app.css](/Users/amaury/code/toastit/assets/frontend/styles/app.css)

## Mandatory rules

1. All colors, radii, shadows, and spacing values must come from existing tokens or shared front-end component styles.
2. New pages must use existing Vue components whenever a matching pattern already exists.
3. No inline `<style>` blocks inside Vue templates.
4. No hard-coded hex or rgba values inside page markup when a shared token or component style already exists.

## Allowed exceptions

- Favicon markup in the base layout.
- Third-party snippets explicitly required by a dependency.
- Prototype-only code inside dedicated front-end playgrounds, as long as it still uses the official components where possible.

## Workflow for new UI

1. Check the front-end component library for an existing pattern.
2. If the pattern exists, reuse its component.
3. If the pattern does not exist, add the component first.
4. Update the relevant front-end reference or story point for the new pattern.
5. Only then use the component in product pages.

## Review checklist

- Is every repeated UI pattern extracted into a reusable front-end component?
- Did we reuse an existing component before creating a new one?
- Are colors referenced through tokens instead of raw values?
- Is the page composed of reusable blocks instead of local one-off markup?
- Was the front-end reference updated if a new pattern was introduced?
