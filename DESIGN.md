---
name: Fatturino
description: Cool indigo/navy operational UI for Italian e-invoicing
colors:
  primary: "#5951BA"
  primary-hover: "#484295"
  primary-subtle: "#F5F4FF"
  indigo-300: "#B9B3F0"
  sidebar: "#10213D"
  sidebar-active: "#27345E"
  sidebar-text: "#F8FAFC"
  sidebar-muted: "#AAB5C4"
  canvas: "#F4F6FA"
  surface: "#FFFFFF"
  surface-muted: "#F3F5F8"
  border: "#E1E5EC"
  border-strong: "#C7CED9"
  content: "#172033"
  content-muted: "#526077"
  text-muted: "#6B7280"
  success: "#067647"
  success-bg: "#ECFDF3"
  warning: "#A15C07"
  warning-bg: "#FFF8EB"
  danger: "#B42318"
  danger-bg: "#FEF3F2"
  info: "#175CD3"
  info-bg: "#EFF8FF"
typography:
  title:
    fontFamily: "Outfit, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "Outfit, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Outfit, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "0.08em"
rounded:
  sm: "0.375rem"
  md: "0.5rem"
  lg: "0.5rem"
  xl: "0.875rem"
  full: "9999px"
spacing:
  sm: "0.5rem"
  md: "1rem"
  lg: "1.25rem"
  xl: "1.5rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#FFFFFF"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
    height: "2.75rem"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "#FFFFFF"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.content}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
    height: "2.75rem"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.content-muted}"
    rounded: "{rounded.lg}"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.content}"
    rounded: "{rounded.xl}"
    padding: "1.25rem"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.content}"
    rounded: "{rounded.lg}"
    height: "2.75rem"
    padding: "0 0.75rem"
  badge:
    backgroundColor: "{colors.surface-muted}"
    textColor: "{colors.content}"
    rounded: "{rounded.full}"
    padding: "0.25rem 0.5rem"
  alert:
    backgroundColor: "{colors.info-bg}"
    textColor: "{colors.info}"
    rounded: "{rounded.lg}"
    padding: "1rem"
  sidebar:
    backgroundColor: "{colors.sidebar}"
    textColor: "{colors.sidebar-text}"
---

# Design System: Fatturino

## Overview

**Creative North Star: "The Cool Ledger Desk"**

Fatturino is an Operate-mode product UI: dense enough for daily fiscal work, calm enough to trust with real money and deadlines. Structure lives in cool navy and misty neutrals; indigo appears only when the user can act. Surfaces stay flat and bordered; elevation is reserved for overlays that interrupt the desk.

The system rejects decorative gradients, dark-mode chrome for the main canvas, and purple-on-white marketing tropes. Tailkit may inspire patterns, but the official kit is the Blade components under `resources/views/components` plus tokens in `resources/css/fatturino.css`.

**Key Characteristics:**
- Light canvas with dark navy sidebar (structural, not interactive purple)
- Indigo reserved for CTAs, focus, and active accents
- Flat cards with hairline borders; shadows only on modals/elevated overlays
- Outfit sans throughout; status color is semantic, never decorative
- List shell pattern: page summary + raw table + mobile cards

## Colors

Cool fiscal neutrals with a single interactive indigo and a navy shell.

### Primary
- **Interactive Indigo** (`#5951BA`): Primary buttons, links, focus rings, subtle fills. Rarity is intentional — indigo marks action, not decoration.
- **Indigo Hover** (`#484295`): Pressed/hover deepening of primary.
- **Indigo Mist** (`#F5F4FF`): Soft primary backgrounds (chips, selected hints).

### Secondary
- **Ledger Navy** (`#10213D`): Sidebar and structural “ink” of the shell.
- **Navy Active** (`#27345E`): Active nav row on the sidebar.

### Neutral
- **Cool Mist** (`#F4F6FA`): App canvas (`bg-canvas`).
- **Paper** (`#FFFFFF`): Cards, panels, inputs at rest.
- **Muted Paper** (`#F3F5F8`): Nested surfaces, zebra hints, soft fills.
- **Hairline** (`#E1E5EC`) / **Strong Hairline** (`#C7CED9`): Borders; cards use hairline, not shadow.
- **Ink** (`#172033`): Primary text (`text-content`).
- **Secondary Ink** (`#526077`): Muted copy (`text-content-muted`).
- **Quiet Ink** (`#6B7280`): Meta labels and tertiary text.

### Semantic (status only)
- Success `#067647` / bg `#ECFDF3`
- Warning `#A15C07` / bg `#FFF8EB`
- Danger `#B42318` / bg `#FEF3F2`
- Info `#175CD3` / bg `#EFF8FF`

**The Interaction-Only Indigo Rule.** Indigo is for interaction (CTA, focus, active accent). Do not paint large structural surfaces indigo.

**The Semantic Status Rule.** Green/amber/red/blue communicate state (paid, due, error, info). Never use them as brand decoration.

Prefer semantic Tailwind tokens (`primary`, `content`, `border`, `surface-*`) over legacy aliases (`base-*`, `error` vs `danger`) when writing new UI.

## Typography

**Display Font:** Outfit (sans-serif)
**Body Font:** Outfit (sans-serif)

**Character:** Single-family system — geometric, slightly soft, highly legible at dense table sizes. No serif display layer in the app shell.

### Hierarchy
- **Title** (600, ~1.125rem / `text-lg`): Page and panel headings.
- **Body** (400, 0.875rem / `text-sm`): Default operational copy, table cells, form help.
- **Label** (600, 0.75rem / `text-xs`, wide tracking on KPI labels): Section labels, uppercase metric captions, badges.

**The One-Family Rule.** Stay on Outfit. Do not introduce a second display face for app chrome.

## Layout

Operate density: max-width content columns for forms (~`max-w-5xl` / `max-w-xl` for single-column), full-bleed lists. Page rhythm uses `space-y-5` / `space-y-6` between major blocks. Settings and list panels share `p-5 sm:p-6` padding.

**List shell:** `x-page-summary` (KPI strip) → filters → `x-loading-banner` → desktop table + mobile card stack → `x-empty-state` when empty. Do not force Mary-era `x-table` onto new lists.

Sidebar is fixed navy chrome; main canvas is mist with white bordered panels.

## Elevation & Depth

Depth is tonal and bordered by default. Cards and panels use `border` + white fill with `--shadow-card: none`.

### Shadow Vocabulary
- **Elevated** (`0 10px 24px rgba(23, 32, 51, 0.12)`): Floating panels that lift briefly.
- **Modal** (`0 18px 40px rgba(23, 32, 51, 0.18)`): Confirm dialogs and blocking overlays.
- **Input focus** (`0 0 0 3px` primary mix ~18%): Field focus glow (alongside global `:focus-visible`).

**The Flat-By-Default Rule.** Resting surfaces are flat. Shadows appear only for transient overlays (modals, elevated menus).

## Shapes

Gently rounded operational geometry — not pills for containers, not sharp print grids.

- Controls and inputs: `rounded-lg` (~0.5rem)
- Panels/cards: `rounded-xl` (~0.875rem)
- Badges/status chips: `rounded-full`
- Sidebar active item: `0.5rem` with indigo inset bar

Borders are 1px hairlines; dashed borders appear only on dropzones.

## Components

Official primitives live in Blade under `resources/views/components`. Prefer them over raw Tailwind clones.

### Buttons (`x-button`)
- **Shape:** `rounded-lg`; CTA/list actions use size `cta` / `lg` at **h-11**
- **Primary:** indigo fill, white label
- **Secondary / outline:** white + border, hover muted surface
- **Ghost:** transparent, muted text
- **Danger / warning / success / info:** solid semantic fills for irreversible or status actions
- Supports `link`, `spinner` / `wire:target`, icon-only with `ariaLabel`

### Badges (`x-badge`)
- Soft semantic fills by default; solid/outline available
- Pill radius; compact `text-xs font-semibold`

### Cards / Panels (`x-settings.panel`, bordered articles)
- White surface, hairline border, `rounded-xl`, `p-5 sm:p-6`
- Optional title/subtitle; warning tone uses warning border

### Alerts (`x-alert`)
- Soft semantic background + matching border/text
- Optional title; dismissible via Alpine when needed

### Inputs / Fields (`x-settings.input`, `x-select`, `x-textarea`)
- Height **h-11**, strong border, white fill
- Focus: primary border + ring (`focus:ring-primary/20` / global focus-visible)
- Errors: danger text under the field

### Navigation
- Dark navy sidebar; muted labels; active row on `sidebar-active` with indigo inset accent
- Top bar: white, hairline bottom border

### List shell
- `x-page-summary` + `x-page-summary.stat` for KPI strip
- `x-empty-state` for empty lists
- `x-loading-banner` for Livewire loading

### Confirm (`x-confirm-modal`)
- Mounted in app layout; triggered via `confirm-dialog` window event — never native `confirm()`

## Do's and Don'ts

### Do:
- **Do** use Blade kit components (`x-button`, `x-alert`, `x-settings.panel`, list-shell pieces) before inventing new chrome.
- **Do** keep indigo rare and actionable; structure stays navy/mist/white.
- **Do** keep list pages as summary + table + mobile cards.
- **Do** preserve keyboard focus rings and Italian operational copy density.
- **Do** treat Tailkit as inspiration only — no package, no catalog IDs in the repo.

### Don't:
- **Don't** paint the canvas or large cards indigo/purple.
- **Don't** add resting drop shadows to cards or KPI strips.
- **Don't** reintroduce Mary-era `partials/*-actions` or unused dashboard widgets.
- **Don't** change routes, data, or business logic during UI work (PRODUCT constraint).
- **Don't** invent a second typeface or dark-mode app shell.
