# How a strategy document has to read

A strategy nobody can follow is not a strategy. It is a document.

The failure this file exists to prevent is specific, and it is not bad writing in
the usual sense — no typos, no grammar errors, every sentence defensible. It is a
document a client reads three times and still cannot say what we are going to do.
That document is worse than a short one, because it took their money and their
attention and left them unable to act or to approve.

The test is one question, asked of every section: **after reading this, could the
client say out loud what we are doing, and could someone on our team start it on
Monday?** If the answer is no, the section is not finished, however accurate it is.

---

## The three questions every section answers, in this order

1. **What is true right now.** The measurement, with its number, its period and
   its source.
2. **What we are going to do about it.** A named, countable action.
3. **What changes if we do it.** The mechanism — *why* the action moves the
   number. Not a promised figure.

A section missing (1) is an opinion. Missing (2) is an observation. Missing (3)
is a task list the client cannot evaluate or argue with — and the one they are
most likely to cut, because nothing told them what it was for.

Write them in that order even when it feels repetitive across twenty sections.
The repetition is the structure; it is what lets somebody open the document at
page 40 and understand page 40.

---

## The six ways our documents actually go wrong

Each of these is real, each has shipped, and each is invisible to a spell-check.

### 1. Abstraction stacking

Three abstract nouns in a row, none of which names a thing anybody does.

> **Don't:** Implementation of a topical authority framework will establish
> semantic relevance signals across the practice-area taxonomy.

> **Do:** Right now you have one page about bail hearings. Your three main
> competitors have between four and nine each. We are writing six more — one per
> stage of a bail hearing — so that when somebody searches any part of that
> process, the page that answers it is yours.

The second is longer. Length is not the problem; **density of abstraction** is.
A client will read four sentences they understand before one they don't.

### 2. Nouns doing a verb's job

"Implementation", "optimization", "utilization", "the creation of", "the
development of". Each one hides who does what, and by when.

| Don't | Do |
|---|---|
| Implementation of schema markup | We add LocalBusiness schema to all 14 pages |
| Optimization of the GMB listing | We rewrite the 750-character description and add all 9 services |
| Content development across priority clusters | We publish 12 pages, 3 per month, starting with bail hearings |
| Utilization of internal linking opportunities | Every new page links to the service page above it and two siblings |

If a sentence's main verb is "is", "will be", or "involves", look for the real
verb trapped in a noun and let it out.

### 3. No subject — nobody is doing it

> **Don't:** Pages will be optimized and schema will be deployed in month two.

Who? Us, them, their web developer? This is the sentence that produces a
three-week stall at kickoff, and it is produced by the passive voice.

> **Do:** We optimize the pages in month two. Your developer needs to give us
> WordPress editor access before we can start — that is the one thing we need
> from you.

**Say "we" for what 6ix Developers does and "you" for what the client does.**
Every task in the document belongs to one or the other, by name.

### 4. Jargon with no gloss

Some terms have to appear — they are the real names of real things, and the
client will meet them again in their analytics. The rule is not to avoid them. It
is that **each one gets defined once, in plain words, in the sentence where it
first appears**, and is then used freely.

> Your **Domain Rating** — Ahrefs' 0–100 estimate of how much other sites link
> to you — is 4. The three firms you named are at 22, 31 and 38.

> **Impressions** — the number of times you appeared in Google results, whether
> or not anybody clicked — were 31,400 over the last 28 days.

A glossary at the back does not count. Nobody flips to the back; they skim past
the word and lose the sentence.

### 5. A number with no period, no unit, and no source

Every figure in a strategy document carries three things or it does not go in:

- **what it measures** (organic sessions, not "traffic")
- **over what period** ("the 28 days to 4 November", not "recently")
- **where it came from** (Search Console, GA4, Ahrefs *estimate*)

The source matters most, and `seo-audit-report/references/measured-vs-estimated.md` is
the long version: a figure from the client's own GA4 or Search Console is a
measurement and may be stated as fact. An Ahrefs figure about the client's own
site is an estimate and must say so, because they can check it against a tab
they already have open. Getting caught once costs the engagement.

### 6. Recommendations that are not instructions

> **Don't:** Consider improving page speed where possible.
> **Don't:** It is recommended that review velocity be increased.

"Consider", "where possible", "it is recommended", "should be explored",
"look at opportunities to" — these are how a document avoids committing to
anything. They read as padding because they are padding.

> **Do:** Your homepage takes 4.8 seconds to become usable on mobile, measured
> by PageSpeed Insights on 4 November. Most of that is three uncompressed hero
> images. We compress them and move to WebP in week one. Target is under 2.5
> seconds.

> **Do:** You have 11 Google reviews; the three firms you named have 47, 112 and
> 139. We set up a review request that goes out after every closed matter. Ask
> every client. Do not offer anything in exchange for one — that breaks Google's
> policy and can get the whole review set removed.

---

## Sentence and paragraph discipline

- **One idea per sentence.** If it has two clauses joined by "and" or a
  semicolon, it is probably two sentences.
- **Around 20 words.** Not a rule to count against — a signal. A 45-word
  sentence is almost always two or three ideas that have not been separated yet.
- **Three to five sentences per paragraph.** A nine-sentence paragraph is where
  the one actionable sentence goes to hide.
- **The point goes first.** Not after the context, the caveat and the method.
  The client stops reading at the end of the first sentence of each paragraph
  far more often than at the end of the paragraph.
- **Tables for anything with more than three parallel items.** A paragraph
  listing eight keywords is unreadable; the same eight in a table with intent
  and target page is scannable, and they can take it to their developer.

---

## What confidence sounds like

The client is paying for a decision, not a survey of possibilities. There is a
real difference between **confident** and **overclaiming**, and the honest
constraints in this skill set (no ROI figures, no invented measurements, no
Ahrefs-as-fact) do not require hedged prose — they require being specific about
the *mechanism* instead of the *outcome*.

| Overclaiming — never | Hedging — reads as weak | Confident and true |
|---|---|---|
| This will generate 40 new leads a month | This may help improve visibility over time | These 6 pages target 2,900 monthly searches that currently send you nothing |
| You will rank #1 within 90 days | Rankings could potentially improve | 31 of your target keywords sit on page 2; those are the ones that move first |
| Guaranteed 3× return | Results vary by market | The three firms ahead of you have 4–9 pages per practice area. You have 1. That gap is the whole plan |

The pattern in the right column: **state the gap, in numbers, and name what
closes it.** That is a strong claim about something we can actually see. "You
will make $X" is a weak claim about something nobody can see, and we do not make
it — not softened, not qualified, not at all.

---

## Section titles that say something

A title is read by everybody; the body is read by some. So the title has to
carry the finding, not label the topic.

| Don't | Do |
|---|---|
| Competitor Analysis | Three firms own the searches you are not on |
| Technical SEO | Your site blocks Google from a third of its own pages |
| Keyword Research | 2,900 monthly searches, none of them reaching you |
| Content Strategy | 12 pages, in the order they should be built |
| Local SEO | You appear in 4 of 25 map searches across the GTA |

Keep them accurate. A title is a claim, and if the body does not support it, cut
the title rather than the evidence. Where a client-facing contents page or a
standing section name is needed, pair them — `Competitor Analysis — three firms
own the searches you are not on` — rather than falling back to the bare label.

---

## Before it goes out

Read it out loud. Not metaphorically — actually read a page aloud. Every
sentence you stumble on is a sentence the client will read twice and then skip.

Then the mechanical pass, which catches the patterns above that can be caught:

```bash
python scripts/check_strategy_doc.py <document.md>
```

It flags hedge words, nominalizations, undefined jargon on first use, very long
sentences, and the numbers-without-a-period pattern. It cannot tell you the
document is clear. It can tell you where it is definitely not.

The thing it cannot check, and the one that matters most: **hand the document to
somebody who was not in the research and ask them what we are going to do.** If
they cannot answer, no amount of accuracy in it has done its job.
