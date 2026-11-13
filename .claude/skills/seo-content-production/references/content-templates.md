# Content templates — the section contract for every page type

Six page types. Each one is a **contract**, not a suggestion: the headings are
given as formulas, in order, and a page that ships with different ones is a
different page type or a mistake.

That is the whole point. A set of seventy pages reads as one body of work
because every service page answers the same questions in the same order, so a
reader who has seen one knows where to look in the next, and a writer never has
to invent a structure. It is also the only way a page set stays reviewable at
volume: the approver checks that each section did its job, rather than
reverse-engineering what the writer was attempting.

**Read `house-standards.md` first.** It holds the twelve tables — voice,
terminology, lengths, linking, meta formats, readability and the claims policy —
that these templates assume. The template says *what goes in each section*; the
profile's standards say *how it sounds and how hard it pushes*.

## Contents

- [The universal blocks](#the-universal-blocks)
- [Template 1: Service page](#template-1-service-page)
- [Template 2: Service hub page](#template-2-service-hub-page)
- [Template 3: Location page](#template-3-location-page)
- [Template 4: Blog post](#template-4-blog-post)
- [Template 5: Audience page](#template-5-audience-page)
- [Template 6: Resource / pillar guide](#template-6-resource--pillar-guide)
- [How local is "local"](#how-local-is-local)

---

## The universal blocks

Three blocks appear on every page type. They are not optional and they are not
the writer's choice.

### The SEO meta block — first thing in the document

Before the H1, before anything. Every page document opens with it, in this
order, one field per line:

```
SEO META BLOCK
Meta Title: Experienced Bail Hearing Lawyer Toronto | NR Lawyers
Meta Description: Facing a bail hearing in Toronto? NR Lawyers provides immediate, experienced bail defence across Ontario. Contact us now for a confidential case review.
Slug: /general-criminal-defence/bail/
Primary Keyword: bail hearing lawyer Toronto
Secondary Keywords: bail lawyer Toronto, bail hearing defence Ontario, criminal bail lawyer GTA
Word Count Target: 2,000 (1,500–2,500)
Author: Joseph A. Neuberger, LSO Certified Specialist in Criminal Law
Reviewed: 13 November 2026
```

Character counts go in parentheses after the title and description while the
page is in review — `(42 chars)` — so the approver can see the limit was met
without counting. They come out when the page is built.

This block is why a developer can build the page without asking a question, and
why `check_draft.py` can check it. A document that starts at the H1 is missing
the half of the deliverable that goes into the CMS.

### Key takeaways — second-to-last section

`## Key Takeaways`, five to seven bullets, each a complete standalone sentence.

Not a summary of the page's structure — the page's *conclusions*. Each bullet
has to make sense lifted out with no other context, because that is exactly
what happens to it: this is the section an AI answer quotes, the section a
skimming reader reads instead of the page, and the section the client's
intake staff screenshot for a prospect.

Write them last, from the finished page. Written first they become an outline,
and the page then repeats them.

### Schema recommendations — last section

`## Schema Recommendations`, naming each schema type this page carries and what
it must assert. Not "add schema" — the specification:

> - **Service schema:** identifies the service as bail hearing legal
>   representation, the provider as NR Lawyers, the area served as Toronto and
>   Ontario, with a description of scope.
> - **FAQPage schema:** marks up each question and answer in the FAQ section.
> - **LegalService schema:** the Service subtype, which classifies this
>   correctly for legal-intent queries.
> - **Organization schema:** name, location, logo, phone, website, with SameAs
>   to the Google Business Profile and verified directory listings.

Which types are permitted is the `risk_schema` switch in the profile's claims
policy. `AggregateRating` carries one extra condition whatever the switch says:
the same rating, count and date must be visible on the page itself.

---

## Template 1: Service page

**2,000 words (1,500–2,500). Minimum 4 internal links. Minimum 6 FAQs.**

For one service the client sells, targeting one commercial or transactional
keyword. The highest-value page type in the set and the one that converts.

| # | Heading formula | Words | Job |
|---|---|---|---|
| — | **[H1]** `{Primary Keyword}` | — | The keyword, as the title, title case |
| — | *Intro, no heading* | 120–180 | The reader's situation, what is at stake, who the client is. Primary keyword in the first 100 words |
| 1 | **[H2]** `Who Needs a {Service} in {City}` | 200–280 | Five to six bullets, each a reader type recognising themselves. This is the qualification section and it is why the page converts |
| 2 | **[H2]** `What {Firm} Provides in {Service} Representation` | 220–300 | Six to eight bullets: the actual scope of work, in order of when it happens. The buyer's "what do I get" |
| 3 | **[H2]** `How the {Service} Process Works in {Region}` | 350–500 | **[H3]** `Step 1: {name}` … `Step 4: {name}`. Numbered, sequential, 80–120 words each. Snippet-eligible and the most-read section on the page |
| 4 | **[H2]** `What Experienced {Service} Delivers` | 180–260 | Five bullets of outcome, not of activity. What is different because the client did the work |
| 5 | **[H2]** `Why {Reader Type} in {City} Choose {Firm}` | 250–350 | Credentials and proof, in the register the claims policy permits. Links to the results page |
| 6 | **[H2]** `Related {Category} Services` | 200–280 | One short paragraph per sibling service, each with a sentence of context and a link. Not a bare list |
| 7 | **[H2]** `{Service} Across {City} and {Region}` | 100–160 | Geography and reach. Links to location pages |
| 8 | **[H2]** `Frequently Asked Questions` | 500–700 | **[H3]** per question, minimum 6. Each answered in 60–120 words, first sentence a direct answer |
| 9 | **[H2]** `Act Now: {urgency line}` | 150–220 | The consequence of delay, stated plainly. Governed by `urgency_language` |
| 10 | **[H2]** `Request a {Consultation Type}` | 80–140 | The call to action, with phone and contact link |
| 11 | **[H2]** `Key Takeaways` | 120–180 | Per the universal block |
| 12 | **[H2]** `Schema Recommendations` | — | Per the universal block |

Sections 1, 2 and 4 are the three our pages have historically lacked, and they
are the reason a competitor's page outsells ours at the same word count: one
tells the reader this page is for them, one tells them what they receive, one
tells them what changes. A page with the process and the credentials but none
of those three reads as a description of the firm rather than an offer.

---

## Template 2: Service hub page

**2,000 words (1,500–2,500). One link per child page, minimum 4. Minimum 6 FAQs.**

The parent of several service pages. It ranks for the category term and passes
readers and link value down. A hub that does not link every child with that
child's own keyword is the commonest structural failure in a site that has the
pages but not the rankings.

| # | Heading formula | Words | Job |
|---|---|---|---|
| — | **[H1]** `{Category Keyword} in {City} and Across {Region}` | — | The category term, front-loaded |
| — | *Intro, no heading* | 120–180 | Do you handle my situation, who leads it, how do I reach you — answered in the first screen |
| 1 | **[H2]** `{Categories} We Handle` | 280–360 | **[H3]** per sub-group. Every child page linked, each with its own primary keyword as the anchor text. The hub's directory |
| 2 | **[H2]** `Your First {Window} After {Trigger}` | 150–220 | Numbered steps for the urgent reader. Carries HowTo markup where `risk_schema` allows |
| 3 | **[H2]** `What Happens After {Trigger} in {Region}` | 220–300 | The process at category level; each child page covers its own specifics |
| 4 | **[H2]** `How We Build a {Category} {Approach}` | 180–260 | The client's method. Where their genuine differentiation lives |
| 5 | **[H2]** `How to Choose a {Category} {Provider}` | 180–260 | Criteria for choosing. This is what answers the "best {provider}" searches — and under a restrictive claims policy it answers them with criteria instead of a claim |
| 6 | **[H2]** `Who Will Handle Your {Matter}` | 120–180 | Named people, checkable credentials, bio links |
| 7 | **[H2]** `{Category} in {City}, {Region 2} and Across {Region}` | 100–160 | Geography; points city searches at their owning pages |
| 8 | **[H2]** `Recent Results` | 40–80 | Proof, linked to the real results page, with the past-results statement |
| 9 | **[H2]** `Frequently Asked Questions` | 380–500 | Minimum 6. Include the primary keyword as a question ("What does a {provider} do?") |
| 10 | **[H2]** `Speak to a {Provider} Today` | 40–80 | Closing call, primary keyword in the closing paragraph |
| 11 | **[H2]** `Key Takeaways` | 120–180 | Per the universal block |
| 12 | **[H2]** `Schema Recommendations` | — | Per the universal block |

**One keyword, one owner.** The hub targets the category group; each child
targets its own term. Where both could plausibly own a search, the one Search
Console already shows impressions for wins — that is measured, and the
alternative is the two pages competing with each other for a year.

---

## Template 3: Location page

**1,500 words (1,200–1,800). Minimum 3 internal links. Minimum 5 FAQs.**

| # | Heading formula | Words | Job |
|---|---|---|---|
| — | **[H1]** `{Service} in {Place}` | — | Service plus place, nothing else |
| — | *Intro, no heading* | 180–260 | Urgency, then a real local detail, then what is at stake, then a soft call with the phone number |
| 1 | **[H2]** `Services Offered in {Place}` | 280–380 | A paragraph per service with a link to its service page |
| 2 | **[H2]** `About {Place}` | 150–220 | The genuinely local content: the courthouse, station, depot or hospital that serves it by name; the arterial road or transit stop; what the area is actually like. **This section is what makes the page not a doorway page** |
| 3 | **[H2]** `Who We Serve in {Place}` | 150–220 | The local client types, specifically |
| 4 | **[H2]** `Why Choose {Firm} for {Category} in {Place}` | 220–300 | Credentials made locally relevant |
| 5 | **[H2]** `What Happens After {Trigger} in {Place}` | 180–260 | The process where it has genuine local variation — which court, which division. Verified, never guessed |
| 6 | **[H2]** `Nearby Areas We Also Serve` | 80–140 | Three to five genuinely adjacent areas, linked where their pages exist |
| 7 | **[H2]** `Frequently Asked Questions` | 350–450 | Minimum 5, each location-specific. Include: do you serve here, how fast can you get here, do I need someone local, how do I start |
| 8 | **[H2]** `{Category} for {Place} Residents` | 120–180 | The closing argument for this place |
| 9 | **[H2]** `Contact {Firm} for {Consultation Type}` | 80–140 | Call to action |
| 10 | **[H2]** `Key Takeaways` | 100–150 | Per the universal block |
| 11 | **[H2]** `Schema Recommendations` | — | Per the universal block, plus `LocalBusiness` with `areaServed` naming this place |
| 12 | **[H2]** `Entity Summary` | 80–140 | See below |

### The entity summary

A flat, extractable statement of the facts a machine needs to place this page:
the business name, the service, the place, the region, the containing city, the
nearest named landmark or venue, the phone, and the page's parent service.

```
Entity: Neuberger & Partners LLP (NR Lawyers)
Service: Criminal defence representation
Location served: Casa Loma, Toronto, Ontario
Within: City of Toronto · Toronto Police 13 Division · Ontario Court of Justice, 1911 Eglinton Ave E
Parent service: /sex-crimes-domestic-offences/
Contact: (416) 364-3111
```

Prose carries nuance; this carries unambiguous facts in a form an answer engine
can lift without inferring. It costs eighty words and it is the cheapest
addition in this file.

---

## Template 4: Blog post

**1,500 words (1,200–2,000). Minimum 3 internal links. Minimum 5 FAQs.**

| # | Heading formula | Words | Job |
|---|---|---|---|
| — | **[H1]** `{Question or Title Carrying the Primary Keyword}` | — | Sentence case. The real headline, publishable as written |
| — | *Intro, no heading* | 150–250 | The reader's situation, not a definition. What is at stake. What this explains. Keyword inside 100 words |
| 1–N | **[H2]** `{Topical question, in the reader's words}` | 250–350 each | Six to ten of them. **Each opens with a 40–60-word direct answer**, then expands. Numbered **[H3]** steps where there is a sequence |
| N+1 | **[H2]** `{Why this matters / the stakes restated}` | 150–220 | The bridge from information to action, linking the relevant service page |
| N+2 | **[H2]** `Frequently Asked Questions` | 350–450 | Minimum 5, 60–120 words each, not repeating the H2s above |
| N+3 | **[H2]** `Key Takeaways` | 120–180 | Per the universal block |
| N+4 | **[H2]** `Schema Recommendations` | — | Per the universal block, plus `Article` with author and `datePublished` |

**Author block** under the H1 or at the foot: name, credential, role, bio link,
and a visible review date. On a YMYL topic this is not optional — an unsigned
page about someone's criminal charge or their health is the single clearest
signal that nobody is accountable for it.

**The 40–60-word opening answer under every H2** is the highest-leverage
mechanical rule in this file. It is what makes a section quotable in a featured
snippet and usable in an AI answer, and it is what lets a frightened reader get
their answer without reading the paragraph.

---

## Template 5: Audience page

**1,500 words (1,200–1,800). Minimum 4 internal links. Minimum 5 FAQs.**

For a reader segment the client serves differently — a professional group, a
specific situation ("falsely accused", "just arrested", "comparing firms").
Build one only where the profile names a genuinely distinct audience with
distinct concerns; otherwise it is a service page with a new title.

| # | Heading formula | Words |
|---|---|---|
| — | **[H1]** `{The audience's situation, in their own words}` | — |
| — | *Intro, no heading* | 180–260 |
| 1 | **[H2]** `What {Audience} Are Facing` | 250–350 |
| 2 | **[H2]** `How {Firm} Works With {Audience}` | 280–380 |
| 3 | **[H2]** `Why {Audience} Choose {Firm}` | 200–280 |
| 4 | **[H2]** `Our Process for {Audience}` | 200–280 |
| 5 | **[H2]** `Frequently Asked Questions` | 350–450 |
| 6 | **[H2]** `Take the First Step` | 100–150 |
| 7 | **[H2]** `Key Takeaways` | 100–150 |
| 8 | **[H2]** `Schema Recommendations` | — |

Section 1 is four to six challenge-and-response pairs specific to this
audience. Generic pairs are the tell that the audience was not real.

---

## Template 6: Resource / pillar guide

**2,500 words (2,000–3,000). Minimum 4 internal links. Minimum 8 FAQs.**

The deepest page on a topic, the one other pages link up to. Same contract as
the blog post, with three additions:

- **A table of contents** after the intro, anchor-linked. At this length a
  reader navigates rather than reads.
- **Every subtopic that has its own page gets a section here plus a link down
  to it** — the pillar summarises, the child covers. Where no child exists yet,
  the section stands alone until one does.
- **A "what to do next" section** before the FAQ, routing by situation rather
  than by topic: if you are *here*, read *this*; if *that* has happened, call.

---

## How local is "local"

A location set usually starts at city level. Below that is a tier most
competitors never build: **neighbourhoods**. A criminal firm in Toronto with
pages for Humewood, Wychwood, Casa Loma, Corso Italia, Forest Hill and
Cedarvale catches searches that "criminal lawyer Toronto" does not, from people
who search the way they think about where they live. Low volume per page, often
no competition at all, and unusually high intent.

Worth doing where customers pick partly on proximity, the city is big enough
that its neighbourhoods have names people use, and you can say something true
and specific about each one.

**The line this crosses if you get it wrong.** A page that is the city page
with the neighbourhood's name swapped in is a doorway page. Google's guidance
names them specifically, they carry a manual-action risk, and at scale they are
the most common way a location strategy fails. Sixteen pages that differ only
in a place name is not sixteen pages.

Template 3's section 2 (`About {Place}`) and section 12 (`Entity Summary`) are
where a neighbourhood page earns its existence. If you cannot write that
section so that it would be **wrong on the neighbouring page**, do not build
the page. Build fewer, better ones and say why.

The profile's service-area answers are the source. Where they do not go deep
enough, ask the client — an invented detail about somebody's own neighbourhood
is the most obviously wrong thing a page can contain, and the client's
prospects live there.
