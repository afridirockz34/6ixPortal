# Questionnaire fields

**Generated file — do not edit by hand.** Regenerate with
`php tools/generate-questionnaire-reference.php` in the 6ixPortal repo after
changing `portal/data-seo-questionnaire.php`.

This is the canonical list of questions the 6ix Developers portal stores, and
the key each answer is saved under. Use it when a client returns the
questionnaire as a document (email, PDF, Word) and you want to map their
answers onto the portal with `update_client_profile` — match on the question
text, write using the key.

A question marked **(from signup)** is already captured during onboarding, so
it will usually be filled already; writing it overwrites what they told us at
signup, so only do that if their document is genuinely more current.

Questions marked **(optional)** don't count toward questionnaire completion.

## Your details  
`contact`

_Most of this we already have — just filling the gaps._

| Key | Question | Type | Notes |
|---|---|---|---|
| `first_name` | Full name | text | from signup |
| `contact_title` | Your title | text |  |
| `business_name` | Business name | text | from signup |
| `brand_tagline` | Tagline | text | optional |
| `website` | Website | url | from signup |
| `industry` | Industry / what you do | text | from signup |
| `phone` | Phone | tel | from signup |
| `business_address` | Physical address | text | from signup |
| `mailing_address` | Mailing address | text | optional |

## Your brand  
`brand`

_If you're unsure about a question or want to talk it through first, leave it blank — we'll cover it on the call._

| Key | Question | Type | Notes |
|---|---|---|---|
| `brand_business_explain` | Explain your business. What do you do? | textarea |  |
| `brand_story` | Why did you start it? How did you get here? | textarea |  |
| `brand_mission` | What is your purpose and mission? | textarea |  |
| `brand_ideal_client` | Describe your ideal client | textarea |  |
| `brand_pain_points` | Their top three pain points or frustrations | textarea |  |
| `brand_solution` | What do you offer that solves those? | textarea |  |
| `seo_usp` | How are you different from everyone else who does this? | textarea | from signup |
| `brand_reputation` | The one thing you want to be known for | textarea |  |
| `brand_values` | Your top five core values | textarea |  |
| `brand_experience` | What should people say about you to a friend? | textarea |  |
| `competitors` | Main competitors (names and URLs) | textarea | from signup |
| `brand_sensitivities` | Anything to avoid? | textarea | optional |
| `brand_special_reqs` | Any special requirements? | textarea | optional |

## SEO specifics  
`onpage`

| Key | Question | Type | Notes |
|---|---|---|---|
| `seo_keywords` | Top phrases people would search to find you | textarea | from signup |
| `seo_scope` | Are you national or local? | select | one of: Local / National / Both |
| `seo_locations` | Cities or areas that matter most | textarea | from signup |
| `seo_prev_work` | Any previous SEO work on the site? | textarea |  |
| `seo_content_workflow` | How does blog/content production work today? | textarea | from signup |
| `seo_approver` | Who approves new content, and what is their email? | text |  |
| `seo_priority` | Prioritize maps or organic? | select | one of: No preference — do both / Maps (local / Google Business Profile) / Organic (content, meta, performance) |
| `seo_topics` | Topics to cover — and any to stay away from | textarea |  |
| `seo_paid_links` | Any previous paid backlinking? | select | one of: No / Yes / Not sure |
| `seo_engagement` | How well does the site engage visitors today? | textarea |  |
| `seo_conversion_type` | How do people usually get in touch? | select | one of: Mostly form submissions / Mostly phone calls / Both about equally / Online purchases |
| `seo_crm_tools` | How do you track those enquiries today? | text | from signup |
| `seo_self_check_terms` | Search terms you type in yourself to check your rankings | textarea | optional |
| `seo_profitable_services` | Your most profitable services | textarea |  |
| `seo_lifetime_value` | Lifetime value of a typical customer | text | optional |
| `seo_repeat_business` | Mostly one-time or returning customers? | select | one of: Mostly one-time / Mostly returning / An even mix |
| `seo_sales_process` | What does your sales process look like, first contact to close? | textarea |  |

## Team and credentials  
`team`

_This builds the expertise and trust signals Google weighs (E-E-A-T). List anyone whose name or credentials should appear publicly._

| Key | Question | Type | Notes |
|---|---|---|---|
| `eeat_team` | Team members to feature (name and role) | textarea |  |
| `eeat_credentials` | Licences, certifications or designations each person holds | textarea |  |
| `eeat_experience` | Years of experience, per person | textarea |  |
| `eeat_media` | Media, press, publications or speaking appearances | textarea | optional |
| `reviews_awards` | Associations, memberships or awards | textarea | from signup; optional |

## Regulated industry and compliance  
`compliance`

_Skip this if it doesn't apply. If your field is regulated, these answers decide what we can and can't say in your content._

| Key | Question | Type | Notes |
|---|---|---|---|
| `comp_regulator` | Is your profession regulated? By whom? | text |  |
| `comp_prohibited_claims` | Claims you are not permitted to make | textarea |  |
| `comp_review_rules` | Rules about testimonials, reviews, or asking for them | textarea |  |
| `comp_disclaimers` | Required disclaimers or disclosures | textarea |  |
| `comp_approver` | Compliance officer or approval process before publishing | text |  |

## Goals and success  
`goals`

| Key | Question | Type | Notes |
|---|---|---|---|
| `goal` | Primary goal for this engagement | text | from signup |
| `goal_6_12_months` | What does success look like in 6 months? In 12? | textarea |  |
| `goal_target_number` | A target number you have in mind | text | optional |
| `goal_ads_budget` | Separate budget for paid ads, aside from the SEO fee? | text | optional; not asked if they bought google-ads |
| `goal_seasonality` | Is the business seasonal? | textarea | optional |

## Online presence, tools and access  
`tools`

_Please don't type passwords here — just tell us what exists, and we'll set up access with you properly._

| Key | Question | Type | Notes |
|---|---|---|---|
| `web_platform` | What is the website built on? | text | from signup |
| `tools_seo_plugin` | SEO plugin installed, if any | text | optional |
| `seo_gsc` | Google Analytics and Search Console — set up, and can you grant access? | textarea | from signup |
| `tools_gbp_status` | Is your Google Business Profile claimed and verified? Who manages it? | textarea | not asked if they bought google-business |
| `tools_social` | Active social media channels | textarea | optional |
| `tools_other` | Any other SEO, marketing or CRM tools in use | textarea | optional |

## Content production  
`content`

| Key | Question | Type | Notes |
|---|---|---|---|
| `content_writer` | Who writes your content today? | select | one of: We need you to write it / I write it personally / We have an in-house writer / We use a freelancer or agency |
| `content_assets` | Do you have photos, video or brand assets we can use? | textarea |  |
| `content_turnaround` | How quickly can you review and approve new content? | select | one of: Within 2 days / Within a week / 1-2 weeks / It varies |
| `content_frequency` | How often should new content go live? | select | one of: Weekly / Every two weeks / Monthly / You recommend |

## Languages and accessibility  
`access`

| Key | Question | Type | Notes |
|---|---|---|---|
| `lang_served` | What languages do you serve clients in? | text |  |
| `lang_multilingual` | Does the website need to support multiple languages? | select | one of: No / Yes / Maybe later |
| `access_requirements` | Any accessibility requirements to meet? | textarea | optional |

---

67 questions in total; 51 have their own storage and the rest reuse a field
onboarding already captured.
