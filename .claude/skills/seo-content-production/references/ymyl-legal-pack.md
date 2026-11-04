# Domain pack: YMYL and regulated-profession clients (legal focus)

Read this before drafting a profile or any content for a client whose services fall under Google's YMYL (Your Money or Your Life) category — legal, medical, financial, and safety-related services. A wrong claim in this category carries more real-world consequence than in most other content work, both for the client's regulatory standing and for how search engines evaluate the page.

This pack is written with a Canadian legal practice as the primary example, since that's the most common case for this skill, but the general structure (heightened E-E-A-T requirements, a "say this not that" table, a hard compliance-review gate) applies to any regulated profession — swap in the relevant regulator's rules.

**This file is a snapshot and will go stale.** Schema behavior and platform policy change faster than almost anything else here. Treat every claim below as something to re-verify against a current, primary source before it goes into a live client deliverable — not as settled fact indefinitely.

## Contents

- [Why YMYL content gets held to a higher bar](#why-ymyl-content-gets-held-to-a-higher-bar)
- [E-E-A-T signals that actually matter for a legal practice](#e-e-a-t-signals-that-actually-matter-for-a-legal-practice)
- [Terminology — say this, not that](#terminology--say-this-not-that)
- [Claims that need a compliance review before publishing](#claims-that-need-a-compliance-review-before-publishing)
- [Schema guidance — what still does anything](#schema-guidance--what-still-does-anything)
- [Reviews and reputation](#reviews-and-reputation)

---

## Why YMYL content gets held to a higher bar

Google's quality rater guidelines put legal, medical, financial, and safety topics in the category that gets rated most harshly for low quality and most generously for genuine expertise and trust signals. This isn't a ranking factor you can check directly — it's a description of what the ranking systems are built to approximate. The practical consequence: generic, unattributed, or unverifiable content underperforms here more than in almost any other vertical, and the fix is the same in every case — real named experts, real verifiable outcomes, and real institutional trust signals, stated plainly rather than implied.

## E-E-A-T signals that actually matter for a legal practice

**Experience:** first-person case narratives with real (anonymized where needed) specifics — jurisdiction, timeline, what actually happened. Named courts the lawyer actually appears before. Real photos, not stock.

**Expertise:** a real named author on every substantive page, mapped to a real lawyer with a real bio — never "Admin" or the firm name as the byline. Bio pages should carry: year called to the bar, license/registration number if the jurisdiction publishes one, education, practice areas, reported decisions, publications, speaking engagements.

**Authoritativeness:** citations from outside the firm's own site — law society or bar directories, court/tribunal records, legal databases, press coverage quoting the lawyer, bar association listings.

**Trust:** a real physical address and phone number, explicit disclaimers ("this is not legal advice; no lawyer-client relationship is formed by reading this page"), a visible privacy policy, plain explanation of fee structure, content review dates with a named reviewer where the practice supports it.

**Avoid:** ghost-written volume content bylined to a partner who didn't write it, AI-generated practice-area pages published at scale without review, unlinked "as seen on" logos.

## Terminology — say this, not that

This table is a starting point — every practice has its own required terminology, and the client's actual profile should extend or override it.

| Say this | Not that | Why |
|---|---|---|
| Charges withdrawn | Charges dropped | Legal precision |
| Acquittal | Win | Professional register — courts don't "win" or "lose," they acquit or convict |
| [Regulator]-certified specialist, stated in full | "[Regulator] certified" / "specialist lawyer" as a shorthand | Most jurisdictions require the full formal designation, not an abbreviated claim |
| The accused / the complainant | The defendant / the victim | Match the actual terminology of the jurisdiction's criminal code, and stay neutral — "victim" and "defendant" both presume an outcome that hasn't been decided |
| Retain a lawyer | Hire a lawyer | Professional register |
| Available 24/7 (only if actually true) | "Always available" as a vague claim | Specific and verifiable beats vague — and don't claim availability the practice doesn't actually provide |

## Claims that need a compliance review before publishing

Route these to the client's own compliance process — a lawyer or compliance officer at the firm, not this skill and not general SEO advice — before anything goes live. This list is illustrative for a Canadian legal practice; the specific rules vary by jurisdiction and profession, so confirm against the actual regulator's current published rules.

- **"Specialist" or "certified specialist" claims.** In Ontario, for example, this can only be claimed by lawyers certified through the Law Society of Ontario's Certified Specialist Program — claiming it without that certification is a real regulatory problem, not just an SEO nitpick. Note the real distinction some regulators draw between claiming to be a certified "specialist" and describing a practice as "specializing in" an area — the second is often permitted where the first, without certification, is not. Confirm the current rule before writing either phrasing.
- **Result-based testimonials or case outcome claims.** "We got him $500,000" or "we always win" implies a predictable, guaranteed outcome. Most legal advertising rules require claims to be demonstrably true and not misleading — phrase outcomes as documented history ("recent case outcomes include...") with a disclaimer that past results don't guarantee future ones, not as a promise.
- **Awards, rankings, and "top lawyer" badges.** Verify the award is based on genuinely objective, verifiable criteria before featuring it. Pay-to-play directory badges are a real exposure in several jurisdictions.
- **Any language implying certainty of outcome:** "guaranteed," "we will win your case," "rest assured" — flag these regardless of context; they tend to fail both the regulator's truthfulness standard and search engines' trust signals simultaneously.
- **Contingency fee or "no fee unless we win" language** — often separately regulated; confirm current wording requirements before publishing.

## Schema guidance — what still does anything

Checked as of mid-2026 — **re-verify before implementing**, this category moves fast:

**Worth implementing:** `LegalService` or `Attorney` (schema.org has both; check current guidance on which is preferred) with full address, phone, geo, hours, and areas served; `Organization` with logo and social profile links; `Person` for each lawyer with credentials and links to their law society listing; `BreadcrumbList`; `Article`/`BlogPosting` with a real author reference.

**Not worth implementing, despite being commonly recommended:** `FAQPage` (rich result withdrawn from Google Search as of mid-2026 — keep the FAQ content, drop the expectation of a rich result), `HowTo` (fully deprecated since 2023), `Speakable` (perpetual beta, scoped to news publishers, never applicable to a law firm). `AggregateRating` built from the firm's own collected testimonials is not just ineffective — it's a structured-data policy violation (self-serving reviews are explicitly prohibited) that risks a manual action. Star ratings for a local business come from the Google Business Profile, not from markup on the firm's own site.

## Reviews and reputation

- Never incentivize a review (a discount, credit, or gift in exchange for one) — this violates search platform policy and, for regulated professions, likely also violates rules on client inducements.
- Always ask permission before sending a review request; never send more than one follow-up if a client declines.
- Never reference specific case details in a review request or in a response to a review — this risks a confidentiality breach on top of any advertising-rule issue, and it's one of the most common sources of professional discipline complaints tied to marketing.
- In a response to a negative review, never confirm or deny that the reviewer was a client — take the conversation offline instead.
