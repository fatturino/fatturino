# UI System Spec - Svelte Frontend

## Obiettivo
Sistema UX/UI unificato, sobrio professionale, con zero regressioni funzionali.

## Token System (resources/css/fatturino.css)

### Colori semantici
- Base brand: `--color-brand`, `--color-brand-secondary`, `--color-brand-accent`, `--color-brand-deep`, `--color-brand-bg`
- Surface/border: `--color-surface`, `--color-surface-muted`, `--color-border`, `--color-border-light`
- Stato: `--color-success`, `--color-danger`, `--color-warning`, `--color-info` + relative background

### Motion e forma
- Durate: `--duration-fast`, `--duration-base`
- Easing: `--ease-out`
- Radius: `--radius-sm`, `--radius-md`, `--radius-lg`

## Pattern CSS condivisi
- Layout: `.page-shell`
- Section content: `.section-title`, `.section-caption`
- Status messaging: `.status-alert[data-tone=...]`
- Data patterns: `.filter-grid`, `.data-table`, `.mobile-list`, `.empty-state`
- Focus globale: `:focus-visible` con ring coerente

## API componenti UI condivisi

### Button
Props standard:
- `variant`: `plain|brand|outline|ghost|danger`
- `size`: `sm|md|lg`
- `isLoading: boolean`
- `isDisabled: boolean`
- `ariaLabel: string`
- Compatibilita: continua a supportare `class`, `disabled`, `type`, `href`

### Input
Props standard:
- `state`: `default|error`
- `isDisabled: boolean`
- `ariaLabel: string`
- Compatibilita: mantiene binding `value`/`checked`

### Textarea
Props standard:
- `state`, `isDisabled`, `ariaLabel`

### Select
Props standard:
- `state`, `isDisabled`, `ariaLabel`, `useNative`

### Checkbox / Switch
Props standard:
- `isDisabled`, `ariaLabel`

### FormField
Props standard:
- `forId`
- `ariaLive`
- `label`, `hint`, `error`, `required`

### Dialog
Props standard:
- `variant`
- `isLoading`
- `onConfirm`, `confirmText`, `cancelText`

## Header Actions Contract
Store: `lib/stores/header-actions.js`
- `setHeaderActions(config)`
- `clearHeaderActions()`

Shape standard:
- `indexPath: string|null`
- `onSubmit: function|null`
- `submitLabel: string`
- `processing: boolean`
- `isReadOnly: boolean`
- `isDisabled: boolean`
- `variant: brand|outline|ghost|danger`
- `ariaLabel: string`

## Feedback System Contract
Store API: `showToast(payloadOrMessage, type?, duration?)`
Payload standard:
- `type: success|error|warning|info`
- `title: string`
- `message: string`
- `action?: { label: string, href: string }`
- `duration?: number`

Retrocompatibilita:
- Formato legacy `showToast('msg', 'success')` ancora valido

## Regole Anti-pattern vietate
- No gradient text
- No side-stripe accent cards
- No nested card decorative
- No CTA primaria duplicata nello stesso viewport
- No stati loading senza feedback visivo

## Rollout applicativo
1. Applicare `.page-shell` a tutte le pagine `Pages/*`
2. Allineare index pages a pattern `filter-grid + data-table + mobile-list + empty-state`
3. Migrare form pages a `FormField + Input/Select/Textarea` con `state` e errori locali
4. Usare `setHeaderActions` in pagine create/edit
5. Usare `showToast` payload semantico per success/error

## Verifica obbligatoria
- Keyboard-only pass
- Mobile 360px pass
- Nessun overflow orizzontale
- Stato loading/disabilitato su submit
- Feedback toast e dialog consistenti
