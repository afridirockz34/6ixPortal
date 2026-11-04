# Assembling the Final Document

Once the requested phases are built, they need to come together as one ordered deliverable — this is what makes the difference between "a folder of separate prompts' outputs" and something that reads like the 101-page agency document this system is modeled on.

## Full document order (all 19 phases, this skill + seo-content-production combined)

1. Brand Summary (this skill — `phase1-foundation.md`)
2. Competitor Analysis (this skill — `phase2-competitors-and-keywords.md`)
3. Brand Voice (**seo-content-production** — the client profile's voice pillars section)
4. Style Guide (**seo-content-production** — `content-templates.md`'s shared rules, adapted per client)
5. SEO Strategy (this skill — `phase5-6-strategy-and-gmb.md`)
6. GMB Strategy (this skill — `phase5-6-strategy-and-gmb.md`)
7. Site Keywords (this skill — `phase2-competitors-and-keywords.md`)
8. Local/GMB Keywords (this skill — `phase2-competitors-and-keywords.md`)
9. Page Architecture (this skill — `phase9-10-architecture-linking.md`)
10. Internal Linking (this skill — `phase9-10-architecture-linking.md`)
11. GEO/AEO Strategy (this skill — `phase11-12-geo-schema.md`)
12. Schema Plan (this skill — `phase11-12-geo-schema.md`)
13. Blog Topics (this skill — `phase13-14-content-calendar.md`)
14. Content Calendar (this skill — `phase13-14-content-calendar.md`)
15. Custom Prompt / grounding system (**seo-content-production** — this is the client profile itself, functioning as the phase)
16. Content Templates (**seo-content-production** — `content-templates.md`)
17. Tech Stack Integration (this skill — `phase17-18-19-tools-tracking-reporting.md`)
18. Local Tracking Setup (this skill — `phase17-18-19-tools-tracking-reporting.md`)
19. Reporting + KPIs (this skill — `phase17-18-19-tools-tracking-reporting.md`)

If the full 19-phase equivalent is being built for a client, produce phases 3, 4, 15, and 16 using **seo-content-production**'s setup workflow (building the client profile and confirming the content templates) at the same point Phase 1 is built here — Phase 1 and the client profile draw from the same questionnaire and should be built together, not sequentially far apart.

## If only some phases are requested

Most requests won't need all 19 — a client might only need a keyword strategy refresh, or a GEO/AEO strategy update, or a new content calendar. Build and check only the phases actually asked for; don't pad a narrow request into a full document. State clearly which phases the delivered document covers and which of the 19 it doesn't, so nobody mistakes a partial deliverable for the complete strategy.

## Assembling into one file

Combine the finished phases in the order above (using only the phases that were actually built) into a single markdown document with a title page (client name, date, prepared by) and a table of contents. Once the content is final, this becomes a normal document-formatting task — build it out as a polished PDF the same way any other client deliverable gets finished, matching the presentation quality of the source NR Lawyers document (clear section breaks, consistent heading hierarchy, tables kept as tables rather than collapsed into prose).

## Before calling it done

Run `python scripts/check_strategy_doc.py <assembled_document>` — it checks for unresolved `[NEEDS CLIENT INPUT]` / `[NEEDS DATA]` placeholders and scans for dead-advice language from `fact-check-registry.md` that might have slipped in from an old template or a generic response. Fix everything it flags before treating the document as client-ready. This does not replace an actual human review for voice, accuracy, and — for a regulated client — compliance sign-off; it catches only what's mechanically catchable.
