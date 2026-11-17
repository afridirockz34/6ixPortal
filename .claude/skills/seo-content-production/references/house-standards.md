# House standards: the tables every client profile carries

A content programme reads as one voice across seventy pages because the
decisions were made once, written down as tables, and then not re-made per
page. This file is the specification for those tables — what each one must
contain, what the default is, and what goes wrong without it.

Twelve tables. They live in the client's profile (`client-profile-template.md`
has the blank shapes) and they are also a **section of the client-facing
strategy document**, because the client's own writers and their web developer
need them as much as we do.

The test for every table below: *a competent writer who has never met this
client could produce a publishable page from the profile alone.* If a table
needs a conversation to interpret, it is not finished.

---

## 1. Brand voice pillars

Four to six pillars. Four columns, and the fourth is the one that does the
work — a pillar without an `Avoid` column is a compliment, not an instruction.

| Pillar | What it means | Example | Avoid |
|---|---|---|---|
| Authoritative | Every word earns its place. Speaks from documented, credentialed expertise. | "Joseph Neuberger holds the LSO Certified Specialist designation — held by fewer than 2% of Ontario criminal lawyers." | Vague superlatives with nothing behind them, passive constructions. |
| Direct | Clients in crisis have no time for preamble. Get to the point in the first sentence. | "If you've been charged with sexual assault in Ontario, the decisions you make in the next 24 hours matter." | Long wind-ups, buried calls to action, sentences that could be cut without losing meaning. |

The `Example` must be a real sentence that could ship, not a description of a
sentence. One invented placeholder example and every page built on the profile
inherits the vagueness.

## 2. Core messages

Five numbered messages. Each is a complete positioning statement with its
proof attached, written so it can be dropped into a page nearly as-is.

> **Message 1 — Specialization is the standard.** A criminal lawyer who has
> spent 30 years on sexual assault and domestic assault is not interchangeable
> with a generalist. LSO Certified Specialist (fewer than 2%), three decades of
> focused practice, deep familiarity with Crown prosecution patterns in these
> charge types.

Five, not three and not fifteen. Fewer and the pages repeat themselves; more
and nobody remembers which message a page is making.

## 3. Terminology — say this, not that

Three columns. The `Why` column is what stops a writer "fixing" the rule back.

| Say this | Not that | Why |
|---|---|---|
| Criminal defence lawyer | Criminal defense lawyer | Canadian spelling |
| Charges withdrawn | Charges dropped | Legal precision |
| Acquittal | Win | Professional register |
| Complainant | Victim, accuser | Neutrality — no prejudgment |
| The accused | The defendant | Ontario Criminal Code terminology |
| Crown attorney | Prosecutor | Ontario court language |
| Not guilty | Innocent | Legal verdict, not a moral claim |

Twelve to twenty rows for a regulated client. This table is also what makes a
page sound like it was written by somebody inside the profession, which is the
single cheapest credibility signal available.

## 4. Voice examples — one good, one bad, both explained

Exactly two, each followed by the reason. This does more to align a writer than
the five tables above it combined, because it shows the pillars operating
together at paragraph length.

**GOOD.** Then: *why it works* — leads with the client's situation; credentials
stated as facts; proof points specific and checkable; call to action clear; no
guarantees; nothing that could be any firm in any city.

**BAD.** Then: *why it fails* — name the specific failures. "Top-notch" and
"best possible outcomes" say nothing. No credentials, no proof, no specificity.

Write the bad example yourself. A real one from the client's current site is
better still, and it is usually the fastest way to get agreement on the voice.

## 5. Words and phrases to avoid entirely

A literal blocklist, ten to twenty entries, enforced by `check_draft.py`.

> guaranteed results · we will win your case · rest assured · don't hesitate to
> · click here · very experienced · passionate about justice · top-notch ·
> best-in-class · reach out to us · leverage · utilize · in today's fast-paced

Two kinds of entry belong here: phrases that are legally or professionally
dangerous for this client, and phrases that are simply empty. Keep both in one
list — a writer does not need to know which is which to obey it.

## 6. Content length standards

| Content type | Target | Acceptable range |
|---|---|---|
| Standard blog post | 1,500 | 1,200–2,000 |
| Resource / pillar guide | 2,500 | 2,000–3,000 |
| Service page | 2,000 | 1,500–2,500 |
| Service hub page | 2,000 | 1,500–2,500 |
| Location page | 1,500 | 1,200–1,800 |
| Audience page | 1,500 | 1,200–1,800 |
| Case result entry | 500 | 400–600 |
| Team bio page | 1,000 | 800–1,200 |
| GMB post | 150 | 100–200 |
| FAQ / process page | 1,200 | 1,000–1,500 |

**Never pad to hit a count.** A short page with specific information
outperforms a padded one. If a topic does not support its range, say so and
ship it short rather than inflating it — and if that happens repeatedly, the
topic list is the problem, not the word count.

## 7. Critical placements

Where the primary keyword has to appear, as a checklist rather than a
percentage:

- H1, near the front
- First 100 words of the body
- At least two H2 subheadings
- Final paragraph
- Meta title, within the first 50 characters
- Meta description
- URL slug

**Density target:** set per client in the claims policy below. The default is
1–1.5% of body words for the primary keyword and 0.5–0.75% per secondary
keyword, counting natural variations — for a 1,500-word page, 15–22 uses of the
primary. Where it is set, `check_draft.py` reports the achieved figure.

Use natural variations freely; "criminal defence lawyer" and "criminal lawyer
Toronto" are the same term to a reader and to a search engine, and writing both
is what keeps the count from reading as stuffing.

## 8. Meta element standards

Four columns. The good and bad examples are not decoration — they are what a
writer actually copies the pattern from.

| Element | Format | Good example | Bad example |
|---|---|---|---|
| Meta title | Under 60 characters. Primary keyword first, brand last. | `Sexual Assault Lawyer Toronto \| NR Lawyers` (42) | `NR Lawyers - Top Criminal Defence Lawyers in Toronto Ontario Canada GTA` (71, stuffed) |
| Meta description | Under 160 characters. Keyword + credential + call to action. | `Charged with sexual assault in Toronto? Joseph Neuberger — LSO Certified Specialist, 30 years of acquittals. Free confidential consultation.` (143) | `NR Lawyers is a Toronto criminal defence law firm offering legal services to clients across Ontario and the GTA. Contact us today.` (keyword buried, no specificity) |
| URL slug | Lowercase, hyphens, keyword-rich, 3–5 words. | `/sexual-assault-lawyer-toronto/` | `/toronto-criminal-defence-legal-services-gta-ontario/` |

## 9. Grammar and mechanics

Short and absolute. Every entry settles an argument that otherwise recurs.

- **Capitalization.** Title case for H1 on service and location pages. Sentence
  case for blog H1s and all H2/H3. Formal designations always capitalized in
  full.
- **Numbers.** Spell out one through nine in prose. Numerals for 10+, all
  percentages, dollar amounts, and statutory section numbers. Years of
  experience always numerals.
- **Punctuation.** Oxford comma always. Em dashes for strong parenthetical
  emphasis. No ellipses. Quotation marks only for direct quotes from a
  documented source.
- **Abbreviations.** Spell out on first use, then abbreviate. Name any
  institution the reader may not know in full the first time.
- **Spelling.** Canadian English for Canadian clients: `-ize` and `-our`
  together, "inquiry" never "enquiry", "defence" not "defense".

## 10. Internal linking rules

| Page type | Minimum links | Destination mix |
|---|---|---|
| Blog post | 3 | 1–2 service pages, 1 related post, 1 contact or results page (rotate) |
| Service page | 4 | Related services, location pages, the lead lawyer's bio, results page |
| Service hub page | 1 per child page, minimum 4 | Every child page, each linked with that page's own primary keyword |
| Location page | 3 | Primary service hub, 1–2 nearby location pages, contact |
| Resource guide | 4 | Relevant service pages, related posts, results, contact |
| Bio page | 4 | Relevant service pages, results, team page, contact |

**Anchor text carries the destination's keyword, not "click here" or "this
page".** On a hub, the link to each child uses that child's primary keyword —
this is what tells Google which page owns which search, and it is the single
most common thing missing from a site that has the pages but not the rankings.

## 11. Readability standards

- **Reading level:** grade 9–10. The reader is frightened, non-expert, and on a
  phone.
- **Sentences:** 12–18 words average. Break anything over 25 into two. Follow a
  long sentence with a short one.
- **Paragraphs:** two to four sentences. One idea. White space between every
  one. Nothing over five lines on a phone.
- **Scannability:** an H2 or H3 every 300–400 words. Bullets for any list of
  three or more. Bold the single most important phrase per section, once.

## 12. Claims policy

This is the table that decides how hard the copy pushes, and it is set **per
client** by the person who will answer for the pages. Defaults below are the
house defaults; a regulated client's own regulator may be stricter, and the
client's answer overrides ours either way.

| Switch | Default | What it allows when on |
|---|---|---|
| `superlatives` | **on** | "best", "top", "leading", "premier", "most experienced" about the client |
| `comparative_claims` | **on** | Claims positioning the client above named or unnamed competitors |
| `aggregate_outcomes` | **on** | "hundreds of acquittals", "a documented success rate", volume-of-results phrasing |
| `urgency_language` | **on** | "act immediately", "the clock is running", "every hour matters" |
| `free_consultation` | **on** | Promoting a free or no-obligation first consultation |
| `keyword_density` | **on** | The 1–1.5% primary / 0.5–0.75% secondary targets in section 7 |
| `risk_schema` | **on** | `AggregateRating`, `HowTo`, `Speakable`, `FAQPage` regardless of current rich-result status |
| `outcome_guarantees` | **off** | Promising or implying a specific result. Off in all jurisdictions we work in; turning it on is a legal question for the client's counsel, not a marketing one |

Three rules apply whatever the switches say, because they are about truth
rather than tone:

1. **A specific number or credential needs a source.** "Hundreds of acquittals"
   ships when the client's own materials support it. Where nothing does, it is
   written `[VERIFY: volume-of-results claim]` for the approver to confirm or
   cut — not generated and not softened. `check_draft.py` fails a draft with an
   unresolved marker, so nothing reaches a page by being forgotten.
2. **Past-results statement travels with outcome claims.** Wherever outcomes
   appear, so does the client's required disclaimer, in their words. For most
   Canadian law firms: *"Past results are not necessarily indicative of future
   results; outcomes depend on the facts of each case."*
3. **`aggregate_outcomes` and `AggregateRating` are different things.** The
   schema type needs the same rating visible on the page with its source and
   count. Marking up a rating the page does not show is the one item here that
   can cost the client the rich result and the trust at once.

**Where a profession's rules are stricter, they win and the profile says so.**
Ontario lawyers have LSO rule 4.2-1; medical and financial professions have
their own. Record the constraint in the profile with its rule number, so a
writer sees why a switch is off rather than assuming it was an oversight.

---

## Filling these in for a new client

Most of it is already answered. The portal's `questionnaire.strategy` block
carries `brand` (story, ideal client, pain points, differentiation → pillars
and messages), `team` (credentials → the proof attached to each message) and
`compliance` (→ the claims policy and any terminology constraint). Pull it with
`get_client_profile` and write the tables from their answers, not from this
file's examples.

What is never inferred: the claims policy switches, the past-results wording,
and any regulator constraint. Those are confirmed with the client, because they
are the ones that land on them rather than on us.

Where an answer is missing, mark the row `[NEEDS CLIENT INPUT]` and keep going.
A profile with four honest gaps is usable. A profile with four invented voice
decisions corrupts every page built on it, and nobody downstream can tell which
rows were real.
