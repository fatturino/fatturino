# F24 manual tracking (OCR assist)

Status: Accepted

## Context

Fatturino tracks electronic invoices and customer payments, not the taxpayer’s full fiscal position. Users still need a clear record of F24 outflows (IRPEF / substitute tax, INPS, VAT) that were already paid or are still due. There is no in-app tax calculation capability.

## Decision

1. F24 entries are **tracking records only**: amounts and codes are entered by the user (or reviewed after import). The product never calculates IRPEF, INPS, or legal VAT settlements.
2. Two intake paths: **manual form** and **PDF/image upload**. Upload stores the file immediately, then runs **local** extraction (`pdftotext` / `pdftoppm` / `tesseract`) and a heuristic parser. Human **review and confirm** is always required before save. OCR failure is a soft path: empty form + attachment.
3. Tribute codes live in a curated JSON catalog (`resources/data/f24-tribute-codes.json`), same pattern as ATECO. Free-text codes remain allowed. No live scrape of Agenzia delle Entrate; no LLM/cloud OCR.
4. Runtime packages for OCR belong in the PHP image (`poppler-utils`, `tesseract-ocr`, Italian traineddata). Missing binaries degrade gracefully to manual entry.

## Consequences

- New tenant tables `f24_payments` / `f24_payment_lines`.
- Sidebar section **Fisco → Versamenti F24** and a dashboard summary widget.
- Dashboard / F24 index may show an **IVA tracking check**: document VAT balance vs F24 IVA lines (paid vs planned). This remains tracking only and is not an official VAT liquidation.
- Future Cassetto Fiscale / A-Cube structured import remains out of scope for this decision.
- Operators must keep the tribute-code JSON updated when AdE tables change for the codes we care about.
