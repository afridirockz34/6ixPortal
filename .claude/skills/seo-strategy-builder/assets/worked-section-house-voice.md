*A worked section in the house voice. Read this before writing any part of a
strategy document, and read `../references/plain-language.md` for the rules it
follows.*

*The same findings are shown twice: as they shipped in November 2026, and as
they should have been written. Nothing in the rewrite is softer, shorter or
less specific. Every number is identical. Only the shape of the sentences and
the shape of the page changed.*

*Run `python ../scripts/check_strategy_doc.py` against each half. The figures
at the bottom of this file are what it reports.*

*One honest note on that. A single section is too short for the sentence-shape
check to fire — it needs three instances of a construction before it calls
something a pattern, so that a document is not failed over one sentence. Across
the full 10,000-word document the shipped voice fails it outright: 8 clefts, 28
"rather than", 10 "is what", 10 fronted clauses. At section length what shows up
instead is the scannability gap, and that gap is the one a reader feels first.*

---

# As it shipped — do not write like this

## Who is ahead, and on what

A Dhillon Law takes an estimated $16,432 a month in traffic that would
otherwise have to be paid for. That figure is the scale of what organic search
is worth in this market.

The useful part is not that they are ahead. It is how. We pulled every
non-branded search both firms rank in the top ten for, and two patterns do
nearly all the work.

A page for each offence in each city. A Dhillon Law ranks first for "impaired
driving lawyer brampton" and first for "dui lawyer mississauga" — both rated 0
or 1 out of 100 for difficulty. They hold the same shape in Vaughan, Hamilton,
Kitchener, Burlington, Oakville, Etobicoke, North York, Markham and Orangeville.
These pages are not difficult to rank. They are simply built, and you have not
built them.

One thing we checked that is worth your knowing: neither competitor got there on
the strength of their links. Both carry the same automated junk links your own
domain does, and A Dhillon Law's better links are mostly paid placements on
unrelated blogs. Their rankings come from how their sites are built, not from
authority you cannot match.

**What is wrong with it.** Nothing is false and nothing is hedged. The median
sentence is 15 words. It scores Grade 8 on Flesch-Kincaid. A client still has to
read it twice, because four sentences withhold their subject, three use "rather
than" or "not X but Y", and no paragraph says what it is about before it starts
saying it.

---

# The same findings, in the house voice

## Two things your competitors built, and you have not

**What the market is worth.** A Dhillon Law draws an estimated $16,432 a month
in traffic they would otherwise pay for. Ahrefs estimate, pulled 17 November
2026. That is the size of the organic prize in criminal defence in the GTA.

**How they got there.** We pulled every non-branded search both firms rank in
the top ten for. Two patterns account for nearly all of it.

**Pattern 1 — a page for each offence in each city.** A Dhillon Law ranks first
for "impaired driving lawyer brampton" and first for "dui lawyer mississauga".
Ahrefs rates both 0 or 1 out of 100 for difficulty. They repeat the pattern in
Vaughan, Hamilton, Kitchener, Burlington, Oakville, Etobicoke, North York,
Markham and Orangeville. Karapancev Law does the same in Mississauga, Richmond
Hill, Barrie, Burlington and Oakville. These pages are easy to rank. You do not
have them.

**Pattern 2 — plain-English explanations of charges.** A Dhillon Law ranks first
for "criminal harassment" (an estimated 1,000 searches a month), first for "what
is aggravated assault" (600) and first for "assault charges" (500). Ahrefs rates
most of these at 0 for difficulty.

**What we checked, and what it rules out.** Neither firm got there on links.
Both carry the same automated junk links your domain does. A Dhillon Law's
better links are mostly paid placements on unrelated blogs. Their rankings come
from how the sites are built. You can match that. You do not need their
authority to do it.

**What we build.** Build 17 city-and-offence pages across months 2–9. Start
with Brampton and Oshawa, the two largest markets on your list. Write 4
plain-English charge explainers in months 2–4. Target "criminal harassment",
"what is aggravated assault", "assault charges" and "peace bond" first.

**What you do.** Confirm the two cities before 1 December. Send us the
courthouse details for each one. Check the first two drafts within five working
days so months 3 and 4 do not slip.

---

## What changed, line by line

| Shipped | Rewritten | Why |
|---|---|---|
| The useful part is not that they are ahead. It is how. | **How they got there.** We pulled every non-branded search… | A cleft replaced by a label and a subject |
| These pages are not difficult to rank. They are simply built, and you have not built them. | These pages are easy to rank. You do not have them. | Two negatives and a "not X but Y" become two statements |
| One thing we checked that is worth your knowing: … | **What we checked, and what it rules out.** … | The label does the work the clause was doing |
| Their rankings come from how their sites are built, not from authority you cannot match. | Their rankings come from how the sites are built. You can match that. | The contrast becomes its own sentence, in the positive |
| *(no paragraph said what it was about)* | Six labels across six paragraphs | 32% label density is the agency standard; this section runs at 100% |
| *(no instruction anywhere)* | **What we build.** 17 city-and-offence pages… | Every section ends with the countable thing we do |

## The numbers both halves score

Measured by `check_strategy_doc.py` on each half of this file:

| | Shipped | Rewritten | Target |
|---|---|---|---|
| Paragraphs opening with a label | 0% (0 of 4) | 100% (6 of 6) | 25%+ |
| Imperative verbs per 1,000 words | 0.0 | 21.6 | 3.0+ |
| Banned constructions | 1 | 0 | 0 |
| Sentences over 25 words | 7% | 3% | under 10% |
| Flesch-Kincaid grade | 6.3 | 5.0 | 9–10 |

And across the two full documents this was calibrated against:

| | Ours, November 2026 | The agency's, May 2026 |
|---|---|---|
| Paragraphs opening with a label | 6% | 32% |
| Imperative verbs per 1,000 words | 0.1 | 5.8 |
| Banned constructions | 48 | 4 |
| Sentences over 25 words | 14% | 8% |
| Flesch-Kincaid grade | **7.6** | **10.9** |

The last row is the point, and it is why "write in simpler English" never fixed
anything. **Ours scores three grades easier and is the one the client could not
read.** Flesch-Kincaid counts syllables and sentence length. It cannot see a
cleft, it cannot see a missing label, and it cannot see that nothing on the page
told the reader what to do.
