# UI/UX Audit Baseline - Svelte Pages

## Scope
Audit completo su tutte le pagine Svelte in `resources/js/Pages`, layout condivisi e componenti UI in `resources/js/lib/components`.

## Metodo
- Framework Impeccable: Accessibility, Performance, Responsive, Theming, Anti-patterns
- Checklist UI/UX Pro Max: priorita P1-P10
- Severita issue: P0 blocking, P1 major, P2 minor, P3 polish

## Audit Health Score (baseline)
| Dimensione | Score (0-4) | Evidenza principale |
|---|---:|---|
| Accessibility | 2 | Focus states presenti ma non uniformi, alcuni controlli senza `aria-label` standardizzato |
| Performance | 3 | Nessun pattern critico di layout thrashing osservato, ma markup ripetuto e tabelle dense |
| Responsive | 2 | Pattern desktop/mobile presenti ma non sempre uniformi tra domini |
| Theming | 2 | Token esistenti, ma uso misto con colori hardcoded e varianti non semantiche |
| Anti-patterns | 2 | Rischio di incoerenza visiva dovuto a duplicazione di layout e pattern |
| **Totale** | **11/20** | **Acceptable - significant work needed** |

## Risultato anti-pattern
Pass parziale. Non emergono pattern proibiti gravi in modo sistemico, ma si rileva forte duplicazione di sezioni card/filter/table/list e stati non centralizzati.

## Findings principali (priorita)

### P1
- **API componenti UI non standardizzate**
  - Location: `lib/components/ui/*.svelte`
  - Impatto: inconsistenza su stati loading/disabled/error e accessibilita
- **Header actions contract implicito**
  - Location: `lib/stores/header-actions.js`, `Layouts/Authenticated.svelte`
  - Impatto: CTA incoerenti tra pagine con rischio regressione UX
- **Toast payload minimale**
  - Location: `lib/toast.js`, `lib/components/Toast.svelte`
  - Impatto: feedback limitato, assenza semantica titolo/azione

### P2
- **Wrapper pagina e spacing non uniformi**
  - Location: domini `Contacts`, `Invoices`, `Settings`, `Imports`, `Sequences`, `Dashboard`
  - Impatto: ritmo visivo discontinuo cross-page
- **Pattern tabella/lista ripetuti per dominio**
  - Location: `*Index.svelte` fatture
  - Impatto: manutenzione lenta, rischio divergenza responsive

### P3
- **Microcopy e stati empty non sempre allineati**
  - Location: varie pagine index/settings
  - Impatto: polish e chiarezza

## Matrice per dominio pagina
| Dominio | A11y | Perf | Resp | Theme | Anti-pattern |
|---|---:|---:|---:|---:|---:|
| Guest (Login, Setup) | 2 | 3 | 3 | 2 | 3 |
| Dashboard | 2 | 3 | 2 | 2 | 2 |
| Contacts | 2 | 3 | 2 | 2 | 2 |
| SalesInvoices | 2 | 3 | 2 | 2 | 2 |
| PurchaseInvoices | 2 | 3 | 2 | 2 | 2 |
| SelfInvoices | 2 | 3 | 2 | 2 | 2 |
| CreditNotes | 2 | 3 | 2 | 2 | 2 |
| Proforma | 2 | 3 | 2 | 2 | 2 |
| Settings (all) | 2 | 3 | 2 | 2 | 2 |
| ElectronicInvoice + Cloud | 2 | 3 | 2 | 2 | 2 |
| Imports | 2 | 3 | 2 | 2 | 2 |
| Sequences | 2 | 3 | 2 | 2 | 2 |

## Azioni applicate in questo rollout
1. Standardizzazione API componenti shared (`variant`, `size`, `state`, `isLoading`, `isDisabled`, `ariaLabel`)
2. Contratto header actions esplicito con helper store
3. Feedback system toast esteso a payload semantico
4. Estensione token CSS semantici e pattern layout condivisi
5. Uniformazione wrapper pagina (`page-shell`) su domini principali

## Criteri di uscita audit finale
- Zero issue P0/P1 aperti
- Score minimo 16/20 con target 18/20
- Allineamento completo su accessibilita, token, responsive pattern
