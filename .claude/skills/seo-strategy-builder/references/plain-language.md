# How a strategy document has to read

A strategy nobody can follow is not a strategy. It is a document.

The failure this file prevents is specific. It is not bad writing in the usual
sense. No typos, no grammar errors, every sentence defensible — and a client who
reads it three times still cannot say what we are going to do.

The test is one question, asked of every section: **after reading this, could
the client say out loud what we are doing, and could someone on our team start
it on Monday?** If the answer is no, the section is not finished, however
accurate it is.

---

## Who this file governs

**Every document a client reads.** The audit, the strategy, the monthly report,
the content brief, the onboarding pages and the portal. One voice across all of
them. The other skills point here rather than keeping their own copy.

---

## Rule 1 — The document is for the client, not for the reviewer

This is the rule that was missing, and it caused more damage than anything
else in this file.

A strategy gets reviewed internally before it ships. The reviewer pushes back,
the next draft answers the pushback, and the answer stays in the document. Do
that a dozen times and the client receives a document arguing with somebody who
is not in the room.

Measured across the two documents this spec was built from: the agency we are
measured against does this **zero** times in 5,893 words. Ours did it **eighteen**
times in 7,420.

**What it looks like.** All of these shipped:

| Shipped | What is wrong | Write instead |
|---|---|---|
| One thing we checked that is worth your knowing: neither competitor got there on links. | Announcing that a check happened. The client assumes we checked. | Neither competitor got there on links. |
| The 551 figure needs explaining, because it looks like a strength and is not. | Defending a number against an objection nobody raised. | Of the 551 sites linking to the firm, 547 are automated junk. |
| Read the last two columns together, because they are the finding. | Instructing the reader how to read our table. | Citadel Law has 216 reviews and ranks 25.8. Mira Law has 12 and does not appear. |
| We would rather give you 96 rows you can build than pad four more. | Justifying our own methodology. | 96 searches, each with a page that owns it. |
| That is the honest answer, not a gap in it. | Pre-empting an accusation. | *(delete — say the thing and stop)* |
| This appendix is here so it is clear the question was asked, not overlooked. | Defending the document's completeness. | No previous SEO work has been done on this site. |

**The test.** Read any sentence and ask: *is this for Mira Bhandari, or is it for
the person who might criticise the draft?* If it is the second, cut it. The
client did not attend the review and does not need the transcript.

**The same fault in another form.** A question asked in a working session must
never become a paragraph in the deliverable. Somebody asks "why are there 'near
me' keywords in here?" and the next draft grows a section explaining why "near
me" keywords are in there. That explanation belongs in the reply to the
question, not in the client's document. The client never asked.

---

## Rule 2 — Pronouns: we do the work, the firm owns the business

**The ratios, measured.** The agency we are measured against says "you" or
"your" 5.3 times per 1,000 words and names the client's firm 7.6 times. Ours
said "you" 23.3 times and named the firm 2.4. We jab the reader four times as
often and name them three times less. That is the whole "it is talking at me"
feeling, in two numbers.

| Use | For | Example |
|---|---|---|
| **we, our, us** | Everything we do, and everything we are doing together | "We start with the pages and the profile you already have." "Our ranking goals around the office." |
| **the firm's name** | Facts, measurements, anything checkable | "Mira Law's website got 173 clicks in November." |
| **you, your** | Only things that are genuinely and only theirs | "your name", "your office", "your Google Business Profile" |

**Never:** "your company", "your business", "your firm" as a way of saying "you".
Use the name. It is shorter, warmer and it is what a person would say.

**Targets:** "you / your" under 8 per 1,000 words. The firm's name at least as
often as "you".

**Why this reads as warmer.** "We" says two parties are doing this. "You" with no
"we" beside it says one party is being assessed by another. A client paying for
twelve months of work should read the first.

---

## Rule 3 — A strategy is not an audit

**The audit says what is true now. The strategy says what we are going to do.**
A client can forward the strategy to their partners; the audit is the document
that explains why they hired us, and they only need to read it once.

**Banned from a strategy document:** any present-tense statement of a
deficiency.

| Never in a strategy | Why | Say instead |
|---|---|---|
| No page title contains the word "lawyer". | A finding. The audit already said it. | We rewrite the titles on all 23 pages in month one. |
| The services list is empty. | A finding. | We add nine services to the profile in month one. |
| 426 words today. | A finding. | We take the sexual assault page to 2,000 words in month two. |
| Your citation score is 6 out of 100. | A third-party grade on the current state. | We get 20 listings live and correct by month three. |
| The thirteen city pages are 53–61% the same text. | A finding. | We rewrite four city pages around their own courthouses. |

**A number is allowed when it is half of a projection.** That is the whole
test. A baseline exists to show the size of the jump, so it appears beside the
target it is being measured against and nowhere else.

> **Allowed.** We start from 173 clicks a month and aim for 400 by month six.

> **Allowed.**
>
> | What we measure | Today | Month 6 | Month 12 |
> |---|---|---|---|
> | Clicks from searches that are not the firm's name | 1 | 40 | 120 |

> **Never.** Of the 139 attributable clicks, 138 were somebody typing the firm's
> name. One was not.

The second version states a baseline with no target attached, so it is a
finding, and findings belong in the audit.

**What the strategy opens with.** Not what is wrong. What we are going to do,
in what order, and what it is expected to produce. `document-assembly.md` has
the running order.

### No findings section, whatever it is called

The rule above is about sentences. This one is about chapters, and it is the
half that actually went wrong.

A strategy came back with a section headed **"Where the firm stands today"** —
323 words of measured present, every figure sourced and dated, not one target
anywhere. Every sentence in it was true. The checker passed it, because the
checker was reading sentences.

**A heading that announces the present is a findings chapter.** "Where the firm
stands today", "Current performance", "Our findings", "Today's numbers". The
plan's version of that content is a table with a Target column. If a section
carries measured figures and commits to no month and no target, it belongs in
the audit.

**"Finding" is the audit's word for the audit's content.** A plan has
priorities, targets and months. `scripts/check_strategy_doc.py` fails on it.

**What is still allowed in a section about the present**, because this is a
real distinction and the checker was taught it:

- *Market demand* — "an estimated 2,900 people a month search dui lawyer" — is
  the raw material of a plan, not a measurement of the client. A keyword
  opportunity table is the most forward-looking thing in the document.
- *A baseline beside its target*, in any shape: a Today / Month 6 / Month 12
  table, or "173 clicks a month today, 400 by month six".

---

## Read this part first: the shape of a sentence, not the size of its words

We got this wrong for a year, so it is worth stating plainly.

A 10,000-word strategy we shipped in November scored **Grade 8.5** on
Flesch-Kincaid. The competitor document it was measured against scored **Grade
11.4**. Ours used shorter words and shorter sentences by every standard metric.
The client needed four hours to read it and said he could not present it to his
team.

So the problem was never vocabulary and it was never sentence length. **It was
sentence shape and page shape.** Those are what this file now specifies, and
what `scripts/check_strategy_doc.py` now measures.

| Measured | Theirs | Ours | What we target |
|---|---|---|---|
| Paragraphs opening with a `Label:` | 32% | 6% | **25–55%** |
| Sentences opening with an imperative verb | 5.8 per 1k words | 0.6 per 1k | **4+ per 1k** |
| Cleft sentences ("The useful part is not that they are ahead. It is how.") | 0.00 | 1.03 per 1k | **0** |
| "rather than" / "not X but Y" | 0.00 | 4.27 per 1k | **under 1 per 1k** |
| "is what" / "is why" / "is the one" | 0.00 | 1.77 per 1k | **0** |
| Sentences over 25 words | 8% | 16% | **under 10%** |

The right column is not a style preference. It is the difference between a
document a partner can read on a phone and a document he has to study.

---

## The five constructions that are banned

Each one is grammatical. Each one makes the reader hold the first half of the
sentence in their head until the second half arrives. Each one is in the
November document and in none of the competitor's 117 pages.

### 1. The cleft — the subject arrives late, or not at all

> **Don't:** The useful part is not that they are ahead. It is how.
> **Do:** They are ahead because they built a page for each offence in each city. We have not built those pages.

> **Don't:** A hub that links to its children using each child's own search term is what tells Google which page owns which search.
> **Do:** Link each hub to its children using the child's own search term. That tells Google which page owns which search.

### 2. The fronted subordinate clause

> **Don't:** Where a larger search sits in work you did not name, it waits behind a smaller search in work you did.
> **Do:** You named sexual assault and impaired driving as your most profitable work. We build those pages first, even where another area has more searches.

Start the sentence with its subject. If a condition has to come first, make it
its own sentence.

### 3. "Rather than" and "not X but Y"

These were the single most common marker in the November document — 29 of them
in 6,794 words, against zero in the competitor's. They turn one fact into a
comparison the reader has to resolve.

> **Don't:** Each one is a stream of work rather than a single job.
> **Do:** Each one runs for several months. It is not a single job.

> **Don't:** The sequence is commercial rather than geographic.
> **Do:** We ordered the cities by what their searches are worth.

### 4. "is what", "is why", "is the one"

> **Don't:** Naming the actual courthouse is what makes a location page a real page.
> **Do:** Name the actual courthouse on every location page. Without it the page is a template with the city swapped.

### 5. The verb that never arrives

> **Don't:** Alongside these run the work that has no single milestone.
> **Do:** Four things run all year: 100 articles, a weekly Google Business post, monthly reporting, and the quarterly review.

---

## The shape of a paragraph

**Lead with a label wherever the paragraph carries instructions.** A client
scanning for the answer to one question finds it from the label. A client
reading straight through loses nothing. This is 32% of the competitor's
paragraphs and the largest single difference between the two documents.

> **Title Tags:** Format: `[Primary Keyword] [Location] | NR Lawyers`. Under 60
> characters. Unique on every page. Include the location modifier on all service
> and location pages.

> **Review Strategy:** You have 12 Google reviews. The ten firms on your map
> hold 72 to 216. We set up a request that goes out after every closed matter,
> and we draft the response template. Ask every client. Never offer anything in
> exchange — that breaks Google's policy and can remove the whole review set.

**Label between a quarter and half of them. Not all of them.** This is a range
with a ceiling, and the ceiling is the half that was missing. A strategy
written against a bare "25%+" target came back at **91% labelled paragraphs
with zero connectives**, and the client who received it called it unreadable.
He was right: a document where every paragraph is a bolded stub is a filing
system, not a document. A label earns its place on a paragraph carrying
instructions somebody will come back to look up, and gets in the way on a
paragraph making an argument.

Three rules for the body after the label:

- **Complete sentences.** Subject, main verb. "A separate set of 40, in
  keywords-gmb.csv." is a stub; "A separate set of 40 keywords sits in
  keywords-gmb.csv." is a sentence. Fragments belong in a specification table —
  "Under 60 characters." in a meta standards row — and nowhere else. An earlier
  version of this file said fragments were "often better", and that one line
  produced a document made of them.
- **Say the noun.** "A separate set of 40" leaves the reader working out 40 of
  what. "40 keywords" costs one word and saves a re-read.
- **Connect the facts.** *because*, *so*, *which means*, *so that*. Two facts
  side by side with nothing between them make the reader build the argument
  themselves. Roughly one connective per paragraph is the difference between a
  list and an argument.
- **Imperative verbs for anything we or they will do.** "Build", "Add",
  "Rewrite", "Claim", "Publish". Not "we will be building".
- **Say what we will do with it.** A paragraph about a deliverable that only
  describes the deliverable is half-written. "The profile answers them, not a
  page" describes. "We optimize the profile for these searches because
  'criminal lawyer near me' alone gets 1,000 a month" commits.
- **Two to four sentences, then stop.** One idea per paragraph.

---

## Sentence discipline

- **12–18 words on average. Nothing over 25.** Twenty-five is a ceiling, not a
  target: a section of nothing but nine-word sentences reads as a telegram.
  Vary the length, and follow a long sentence with a short one.
- **Subject, verb, object.** In that order, in almost every sentence.
- **One em-dash per section, at most.** The November document used 49 in 6,794
  words. Each one is a clause the reader has to hold open.
- **Target Grade 9–10 on Flesch-Kincaid**, which is the standard for a
  frightened non-specialist reading on a phone. Note that this is a floor on
  *clarity*, not a ceiling — scoring 8.5 did not make the November document
  readable, because the metric cannot see sentence shape.
- **Tables for anything with more than three parallel items.** 41% of the
  competitor's document is tabular. A paragraph listing eight keywords is
  unreadable; the same eight in a table are scannable and can go straight to a
  developer.

---

## The three questions every section answers, in this order

1. **What is true right now.** The measurement, with its number, its period and
   its source.
2. **What we are going to do about it.** A named, countable action, in the
   imperative.
3. **What changes if we do it.** The mechanism — *why* the action moves the
   number. Not a promised figure.

A section missing (1) is an opinion. Missing (2) is an observation. Missing (3)
is a task list the client cannot evaluate or argue with.

---

## The six ways our prose goes wrong

These are older than the shape rules above and still apply. Each is invisible to
a spell-check.

### 1. Abstraction stacking

> **Don't:** Implementation of a topical authority framework will establish
> semantic relevance signals across the practice-area taxonomy.

> **Do:** You have one page about bail hearings. Your three main competitors
> have between four and nine each. We write six more, one per stage of a bail
> hearing.

### 2. Nouns doing a verb's job

| Don't | Do |
|---|---|
| Implementation of schema markup | Add LocalBusiness schema to all 14 pages |
| Optimization of the GMB listing | Rewrite the 750-character description. Add all 9 services |
| Content development across priority clusters | Publish 12 pages, 3 a month, starting with bail hearings |
| Utilization of internal linking opportunities | Link every new page to the service page above it and two siblings |

If a sentence's main verb is "is", "will be", or "involves", the real verb is
trapped in a noun. Let it out.

### 3. No subject — nobody is doing it

> **Don't:** Pages will be optimized and schema will be deployed in month two.
> **Do:** We optimize the pages in month two. You need to give us WordPress
> editor access first. That is the one thing we need from you.

**Say "we" for what 6ix Developers does and "you" for what the client does.**

### 4. Jargon with no gloss

Some terms have to appear. The rule is not to avoid them. **Each one is defined
once, in plain words, in the sentence where it first appears.**

> **Domain Rating** — Ahrefs' 0–100 estimate of how much other sites link to
> you — is 4. The three firms you named are at 22, 31 and 38.

A glossary at the back does not count. Nobody flips to the back.

### 5. A number with no period, no unit, and no source

Every figure carries three things or it does not go in: what it measures, over
what period, and where it came from. `seo-audit-report/references/measured-vs-estimated.md`
is the long version. A figure from the client's own GA4 or Search Console is a
measurement. An Ahrefs figure about the client's own site is an estimate and
must say so.

### 6. Recommendations that are not instructions

> **Don't:** Consider improving page speed where possible.
> **Do:** Your homepage takes 4.8 seconds to become usable on mobile, measured
> by PageSpeed Insights on 4 November. Three uncompressed hero images cause most
> of it. We compress them and move to WebP in week one. Target: under 2.5
> seconds.

---

## What confidence sounds like

The client is paying for a decision, not a survey of possibilities.

| Overclaiming — never | Hedging — reads as weak | Confident and true |
|---|---|---|
| This will generate 40 new leads a month | This may help improve visibility over time | These 6 pages target 2,900 monthly searches that send you nothing today |
| You will rank #1 within 90 days | Rankings could potentially improve | 31 of your target keywords sit on page 2. Those move first |
| Guaranteed 3× return | Results vary by market | The three firms ahead of you have 4–9 pages per practice area. You have 1 |

**State the gap, in numbers, and name what closes it.**

---

## Section titles that say something

A title is read by everybody; the body is read by some.

| Don't | Do |
|---|---|
| Competitor Analysis | Three firms own the searches you are not on |
| Technical SEO | Your site blocks Google from a third of its own pages |
| Keyword Research | 2,900 monthly searches, none of them reaching you |
| Content Strategy | 12 pages, in the order they should be built |
| Local SEO | You appear in 4 of 25 map searches across the GTA |

Keep them accurate. A title is a claim. If the body does not support it, cut the
title rather than the evidence.

---

## Before it goes out

Read a page out loud. Not metaphorically. Every sentence you stumble on is a
sentence the client will read twice and then skip.

Then the mechanical pass:

```bash
python scripts/check_strategy_doc.py <document.md>
```

It measures sentence shape, label density, imperative density, sentence length
and reading grade against the targets at the top of this file. It cannot tell
you the document is clear. It can tell you where it is definitely not.

The thing it cannot check, and the one that matters most: **hand the document to
somebody who was not in the research and ask them what we are going to do.** If
they cannot answer, no amount of accuracy in it has done its job.
