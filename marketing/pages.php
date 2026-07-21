<?php
/**
 * 6ix Developers — service page content.
 *
 * The four service pages share one template (template-service.php); this file
 * supplies each page's copy, keyed by page slug. Copy mirrors the original
 * 6ixdevelopers.com pages verbatim (SEO-preserving); the portal CTA band,
 * testimonials slider and final CTA are shared additions from the homepage.
 *
 * No plugins required — content lives in version-controlled PHP so it deploys
 * through the normal pipeline and renders identically on every environment.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @return array<string,array> slug => content
 */
function six_service_pages() {
    return array(

        // ── Website Design ──────────────────────────────────────────────
        'website-design-agency-toronto' => array(
            'menu'     => 'Website Design',
            'eyebrow'  => 'Website Design',
            'title'    => 'Toronto Website Design Agency',
            'subtitle' => 'Make Your Dream Website a Reality',
            'lead'     => 'Get the website that pays for itself — a 24/7 online salesperson working for you.',
            'intro'    => array(
                'Having a website for your business is necessary now. Having a website is just the beginning; ensuring proper website layout and design encourages customers to stay and take desired actions, such as submitting forms, calling your business, or making online purchases.',
                'With 6ix Developers, you receive website designs tailored to your industry and competitors, aimed at securing the top position in Google search results. Choosing 6ix Developers for your website design means having a 24/7 online salesperson working for you. Our website designs are carefully analyzed and tested before deployment. This ensures we measure and improve returns on your investment, as well as increase user engagement and conversion rates on your website.',
            ),
            'packages_heading' => 'Website Packages',
            'packages_intro'   => 'No matter the size of your business, a website is essential to make an impact with customers. To ensure your website is successful and easy to find, it needs to be fast, easy-to-use, and visually appealing on all devices.',
            'packages' => array(
                array( 'badge' => 'Starter',              'size' => '1 to 5 Pages',   'text' => 'Great for companies offering minimal services, if you are just starting your company, or if you are just beginning to establish your presence online.' ),
                array( 'badge' => 'Standard',             'size' => '6 to 12 Pages',  'text' => 'Great for companies that offer multiple services or locations, if your company is already well-established, or if you want to rebrand your online presence.' ),
                array( 'badge' => 'Advanced / E-Commerce','size' => '13+ Pages',      'text' => 'Perfect for large companies, or if you already have a lot of existing content on your website. Also great for e-commerce companies selling products online.' ),
            ),
            'features_heading' => 'Websites Built to Win You Business',
            'features' => array(
                array( 'icon' => 'target',  'title' => 'Optimized for Lead Generation', 'text' => "Website designs that are optimized for lead generation and lead capturing are essential to run a successful and modern business. We'll tailor yours to your industry, considering how your potential clients interact with websites, to make your website the final destination for their search." ),
                array( 'icon' => 'spark',   'title' => 'Flexible Website Designs', 'text' => 'Our website designs are fully customizable to ensure your vision is realized without limitations. All website designs are developed under the supervision of our marketing team, ensuring the highest level of returns and longer user interaction.' ),
                array( 'icon' => 'website', 'title' => 'Beautiful Across All Devices', 'text' => 'We exclusively offer responsive website designs. Our team adheres to industry standards to ensure the best user experience across all devices, including mobile phones, tablets, computers, and projectors.' ),
                array( 'icon' => 'chart',   'title' => 'Fast Google PageSpeed', 'text' => 'Over half of all visitors will leave your website if it fails to load within 3 seconds. Our website designs are optimized for the fastest Google PageSpeed — and since May 4th, 2020, the top 3 factors for ranking #1 on Google are related to your website\'s PageSpeed.' ),
                array( 'icon' => 'seo',     'title' => 'SEO Friendly Website Designs', 'text' => 'All of our website design packages include expert SEO consultation. We incorporate high-quality, industry-related keywords in titles, descriptions, meta tags and URLs to quickly rank your website on the 1st page of Google.' ),
                array( 'icon' => 'shield',  'title' => 'Easy to Manage & Track', 'text' => 'Our website editor lets you drag and drop items around the page where you want, and adding new content and images couldn\'t be easier. Every site ships with Google Analytics, custom goals and conversion tracking so you can keep improving.' ),
                array( 'icon' => 'ads',     'title' => 'Conversion Tracking on Your Website', 'text' => 'To find out how your potential customers are interacting with your website, all website designs from 6ix Developers come with Google Analytics integration. We set up custom goals and conversion tracking so you can learn and improve your website for the best user experience — ensuring constant, gradual growth for your business.' ),
            ),
            'highlight' => array(
                'heading' => 'Receive FREE Google Ads Setup Valued at $1500',
                'lines' => array(
                    'Start a Website Design service with us and get your Google Ads campaign setup — a $1500 value — completely free, so your new website starts generating leads from day one.',
                ),
            ),
            'steps_heading' => 'How It Works',
            'need' => array(
                'heading' => 'What Do We Need From You?',
                'text'    => 'To begin creating your beautiful website, we\'ll need a few things from you first.',
                'items'   => array(
                    'All of your content as soon as possible — if you do not have content, our premium content creator will create it for your website.',
                    'Your logo as a high-quality graphic in .svg, .jpg, or .png format.',
                    'High quality images (we recommend 100kB–600kB). No images? We\'ll use custom stock images for your website.',
                    'The pages you would like to include on your site.',
                    'Contact form details and the email that should receive submissions.',
                    'If you have an existing domain and hosting, your login information to get started.',
                ),
            ),
            'steps' => array(
                'heading' => 'How Does The Web Design Process Work?',
                'list' => array(
                    array( 'strong' => 'Plan & design',     'text' => 'We develop an initial document that outlines the website and its content, and work with you to come up with a design you like.' ),
                    array( 'strong' => 'Build',             'text' => 'Once the content and design are approved, we send this to the developer to begin building your website.' ),
                    array( 'strong' => 'Revise',            'text' => 'Once the website is completed, we go back in and make revisions.' ),
                    array( 'strong' => 'Launch',            'text' => 'After all the changes are made, we migrate the website to your domain and hosting.' ),
                    array( 'strong' => 'Maintain',          'text' => 'We offer optional monthly management and maintenance — helping with crashes, malware or virus attacks, hackers, and regular updates.' ),
                ),
            ),
            'faq' => array(
                array( 'q' => 'Where does my content come from?', 'a' => 'You! We include the content you send us, or that already exists on your website. We are more than happy to help you write your content as well at our rate of $0.15 per word.' ),
                array( 'q' => 'What is the turnaround time?', 'a' => 'Given you provide us all the necessary information up front, we can have your website up in a matter of weeks.' ),
                array( 'q' => 'Is there a contract?', 'a' => 'No. We do not require any of our clients to sign a contract. Our monthly plans operate on a month-to-month basis.' ),
                array( 'q' => 'Can SEO be done to your sites?', 'a' => 'Yes! All of our sites come with the SEO functionality built-in.' ),
                array( 'q' => 'What is not included?', 'a' => 'Content creation, graphic design, logo design, and other services such as SEO and Google Ads will run at additional costs. Please contact us for more information or to get an estimate.' ),
                array( 'q' => 'If I have a large site, can I still get a small package?', 'a' => 'Yes, but we do not recommend this. Doing so may limit the functionality of the website, and can impact the amount of content we can use.' ),
                array( 'q' => 'How many pages do I get?', 'a' => 'Please refer to the website packages above — Starter (1 to 5 pages), Standard (6 to 12 pages), or Advanced / E-Commerce (13+ pages).' ),
                array( 'q' => 'What do you need from me to get started?', 'a' => 'We will need your existing content, or a link to your website if we are copying your content. We will also need your logo and images (we recommend images be between 100kB and 600kB). Finally, we will need your website outline, contact form information, and your domain/hosting login.' ),
                array( 'q' => 'Do I own the website?', 'a' => 'Yes! We do not operate under contracts, so you will 100% own your website once it is up and running.' ),
                array( 'q' => 'Can I use the domain I already have?', 'a' => 'Yes! And we encourage it. We will need your login information. We also recommend purchasing a hosting plan (our favourite is GoDaddy).' ),
                array( 'q' => 'Are there any monthly maintenance plans?', 'a' => 'Yes. It is important for the security of your website to have someone regularly back it up and provide assistance in case of malware or virus attacks, hackers, or website crashes. Please contact us for more information.' ),
                array( 'q' => 'How do I promote my new website?', 'a' => 'We specialise in Google Ads campaigns for all types of businesses. Contact us to get a quote.' ),
            ),
        ),

        // ── Google Ads / PPC ────────────────────────────────────────────
        'ppc-google-ads-management-toronto' => array(
            'menu'     => 'Google Ads/PPC',
            'eyebrow'  => 'Google Ads / PPC',
            'title'    => '#1 Rated Google Ads Agency in Toronto',
            'subtitle' => 'We Count Leads, Not Clicks',
            'lead'     => 'Certified Google Ads experts managing over $15 Million in monthly ad spend for 2000+ businesses.',

            // Sign-up offer tiers (jumpstart your first campaign)
            'offer_tiers' => array(
                'heading' => 'Choose a sign-up offer to jumpstart your first Google Ads campaign',
                'intro'   => 'Select an offer that fits your monthly Google Ads budget. Terms and conditions apply.',
                'cta'     => 'Check your eligibility for up to $1800 in credit',
                'tiers'   => array(
                    array( 'credit' => 'Up to $600',  'text' => 'Spend $600 with Google Ads in the first 60 days to unlock the credit.' ),
                    array( 'credit' => 'Up to $1800', 'text' => 'Spend $1800 with Google Ads in the first 60 days to unlock the credit.', 'featured' => true ),
                    array( 'credit' => 'Up to $3600', 'text' => 'Spend $3600 with Google Ads in the first 60 days to unlock the credit.' ),
                ),
            ),

            'intro_heading' => 'Top Rated Google Ads Agency Toronto',
            'intro'    => array(
                'Unlock the power of Google Ads with our expert management services tailored specifically for your industry. With a proven track record of maximizing ROI for over 2000 businesses across Canada and the USA, our dedicated team of Google Ads Certified experts oversees more than $15 Million in monthly Google Ads spending.',
                'Our extensive experience spans various industries, equipping us with the insights and strategies to elevate your online presence effectively. As one of the fastest-growing Google Ads marketing agencies in Canada, headquartered in the vibrant city of Toronto, 6ix Developers is your trusted partner for driving unparalleled success in the competitive digital landscape.',
                'Gain the competitive edge you deserve — partner with us for unparalleled Google Ads/PPC management in Toronto!',
            ),
            'show_success'    => true,
            'success_heading' => 'Google Ads Client Success',

            // Audit intro blocks (two alternating panels)
            'audit_heading' => 'Stop Guessing, Understand Your Campaigns Better.',
            'audit_blocks'  => array(
                array(
                    'title' => 'Comprehensive Google Ads Audit',
                    'paras' => array(
                        'Ensure the success of your PPC/Google Ads campaigns with a comprehensive audit tailored to your business. Periodic audits are crucial for optimizing performance, identifying areas for improvement, and maximizing your ROI. At 6ix Developers, we specialize in empowering businesses like yours to understand and optimize their campaigns for optimal results.',
                        "Our Google Ads Certified specialists are ready to conduct a thorough audit of your account. By pinpointing areas of underperformance, we'll help you unlock hidden potential, generate more leads within your existing budget, and save money on your Google Ads.",
                        "Don't leave the success of your campaigns to chance — schedule your Google Ads account audit today by booking a discovery call with one of our experts.",
                    ),
                    'cta' => 'Request Google Ads Account Audit',
                ),
                array(
                    'title' => 'Maximize Your Google Ads Performance with an Account Audit!',
                    'paras' => array(
                        "Whether you're content with your current Google Ads performance or seeking ways to elevate it further, our discreet account audit offers invaluable insights into various performance metrics. From Law Firms to Med Spa Clinics, Construction companies to Restaurants, our Google Ads account audit is tailored to businesses across diverse industries, providing a comprehensive analysis of their campaigns' effectiveness.",
                        'Discover hidden opportunities and uncover potential areas for improvement with our expert audit. Our Google Ads Certified specialists will meticulously evaluate your account, offering actionable recommendations to optimize performance and drive better results.',
                        "Don't settle for mediocre campaign performance — schedule your Google Ads account audit today by booking a discovery call with one of our specialists.",
                    ),
                    'cta' => 'Request Google Ads Account Audit',
                ),
            ),

            // The detailed 8-point audit checklist
            'audit_checklist_heading' => "What's included in our comprehensive Google Ads Account Audit",
            'audit_checklist' => array(
                array( 'icon' => 'shield', 'title' => 'The Wasted Spend: Unlocking Your Google Ads Efficiency', 'paras' => array(
                    'In our Google Ads audit, we delve into the crucial realm of negative keywords — a pivotal factor in minimizing AdWords costs. Failure to implement negative keywords could result in squandering thousands of dollars each month on irrelevant keywords that yield no leads.',
                    'Our audit meticulously reviews your negative keyword usage, identifying areas where potential savings lie dormant. By optimizing your negative keyword strategy, we empower you to cut unnecessary spending and redirect your budget towards high-converting keywords that drive tangible results.',
                ) ),
                array( 'icon' => 'chart',  'title' => 'Unlock Your Ad Relevance with CTR Analysis', 'paras' => array(
                    'CTR (Click-Through Rate) serves as a vital gauge of your ad targeting effectiveness. During our Google Ads audit, our specialists meticulously assess your campaigns, placing a strong emphasis on CTR analysis. This crucial metric offers insight into the relevance and uniqueness of your ad copy.',
                    'A low CTR could signify lost opportunities and potential ground conceded to competitors. By scrutinizing your CTR, we uncover areas for improvement and refine your ad strategy to ensure maximum impact and engagement.',
                ) ),
                array( 'icon' => 'target', 'title' => 'Unlocking Profitability: Harness the Power of Long-Tail Keywords', 'paras' => array(
                    'Long-tail keywords, the cornerstone of a lucrative PPC campaign, are often overlooked but hold immense potential. Failure to incorporate these highly targeted phrases means missed opportunities to capture relevant leads at a lower cost.',
                    'In our assessment of your PPC strategy, we prioritize the integration of long-tail keywords. These specialized terms offer a direct path to highly qualified prospects, maximizing your ROI and minimizing wasted ad spend.',
                ) ),
                array( 'icon' => 'shield', 'title' => 'Our Certified Google Ads Specialist Ensures Adherence to Best Practices', 'paras' => array(
                    'Our dedicated Google Ads specialist ensures that your campaigns adhere to the proven best practices followed by certified Google Ads experts. By implementing these industry-standard techniques, we guarantee optimal performance and maximize the effectiveness of your advertising investment.',
                    'Trust in our expertise to elevate your campaigns to new heights of success and unlock the full potential of your Google Ads strategy.',
                ) ),
                array( 'icon' => 'spark',  'title' => 'Elevate Your ROI with Quality Score Optimization', 'paras' => array(
                    "During our Google Ads audit, our specialist meticulously reviews your campaign's Quality Scores. These scores play a pivotal role in determining your ROI, as they directly impact your ad rankings and cost per click.",
                    'By focusing on achieving high Quality Scores, we not only improve your ad rankings but also lower your cost per click, resulting in more relevant leads at a lower cost.',
                ) ),
                array( 'icon' => 'website','title' => 'Unlock Success with Dedicated Account Management', 'paras' => array(
                    "Our PPC specialist meticulously assesses the time your Google Ads Account manager spends in your account. Through our analysis, we've uncovered a significant correlation between active account management and overall success rates.",
                    'Clients who receive more dedicated attention tend to achieve higher levels of success. By prioritizing regular monitoring, optimization, and strategic adjustments, we ensure your campaigns are consistently optimized for maximum effectiveness and ROI.',
                ) ),
                array( 'icon' => 'ads',    'title' => 'Enhance Your Google Ads Performance with Text Ad Analysis', 'paras' => array(
                    'Our Google Ads campaign audit includes a thorough review of your text ad usage, a crucial element for achieving strong performance metrics such as impressions, clicks, CTR (Click-Through Rate), ad relevance, and ranking.',
                    'By meticulously analyzing your text ads, we identify areas for improvement and optimization. Our goal is to ensure that your ads are not only highly relevant to your target audience but also effectively drive engagement and conversions.',
                ) ),
                array( 'icon' => 'seo',    'title' => 'Elevate Your Business Front with Optimized Landing Pages', 'paras' => array(
                    'Landing pages serve as the forefront of your business, dictating the success of your campaigns. Even with stellar Google Ads performance, a subpar landing page can hinder overall results. Our Google Ads Account audit prioritizes ensuring your landing pages are on par with competitors, optimizing them for maximum effectiveness.',
                    "By conducting a thorough assessment, we identify opportunities to enhance your landing pages' performance. From design and functionality to content and user experience, we ensure your landing pages align with industry standards and surpass competitor benchmarks.",
                ) ),
            ),

            'features_heading' => 'Strategic Google Ads Management',
            'features' => array(
                array( 'icon' => 'chart',  'title' => 'Maximize Your ROI with Strategic Google Ads Management', 'text' => "Running a search marketing campaign on Google can quickly become costly without a solid strategy in place. At 6ix Developers, we craft tailored strategies based on your business type, goals, competition, and other critical factors, ensuring every dollar spent is strategically allocated to keywords that drive tangible leads and maximum ROI." ),
                array( 'icon' => 'spark',  'title' => 'Stay Ahead of Evolving Search Trends', 'text' => "The way people search for services is constantly evolving. Our Google Ads certified team keeps your strategy finely tuned to capture maximum leads — you'll pay only for the keywords directly relevant to your business, ensuring every click counts towards driving high-quality leads." ),
                array( 'icon' => 'target', 'title' => 'Unlock Precision Targeting with GEO Strategy', 'text' => 'We implement a GEO marketing strategy for your campaigns, focusing on specific geographic locations so your ads are shown only to users within your target audience — reaching the right people, in the right place, at the right time, so every impression counts.' ),
                array( 'icon' => 'website','title' => "Don't Underestimate the Impact of Website Speed", 'text' => 'A slow-loading landing page can result in higher costs per click than your competitors, even if they rank below you. Our in-house developers optimize the landing pages used for Google Ads to load quickly and minimize bounce rates, maximizing your ad performance and ROI.' ),
            ),

            'results_heading' => 'Get the Results That Matter to You',
            'results' => array(
                array( 'icon' => 'target', 'title' => 'Get More Qualified Leads', 'text' => 'Generate high intent, qualified leads by ranking #1 on Google at the right time.' ),
                array( 'icon' => 'ads',    'title' => 'More Phone Calls',        'text' => 'Increase qualified call volume using Google Ads high-intent targeting.' ),
                array( 'icon' => 'chart',  'title' => 'Transparency',            'text' => "Understand what's working with quantifiable Google Ads reports." ),
            ),

            'steps_heading' => 'How It Works',
            'need' => array(
                'heading' => 'What Do We Need From You?',
                'text'    => "To begin creating your Google Ads campaign, we'll need a few things from you first.",
                'items'   => array(
                    'Manager Level Access to your Google Ads account — we do not need your login information and you stay in full control of your account.',
                    'An agreed-upon monthly budget. Your specialist will research industry competitors to determine the optimal budget for your business.',
                    'Your website URL to set up conversion tracking.',
                ),
            ),
            'steps' => array(
                'heading' => 'On-boarding Process',
                'list' => array(
                    array( 'strong' => 'Market research', 'text' => 'Our Google Ads specialists conduct comprehensive market research to develop an effective strategy.' ),
                    array( 'strong' => 'Keyword plan',    'text' => 'We curate a list of lead-generating keywords that suits your monthly budget and send it for your approval.' ),
                    array( 'strong' => 'Campaign build',  'text' => 'We build an effective campaign structure with supporting Ad Groups and send it for approval.' ),
                    array( 'strong' => 'Ad creative',     'text' => 'We develop creative ad copy — headlines, descriptions and call extensions — and send it for approval.' ),
                    array( 'strong' => 'Track & report',  'text' => 'We set up conversion tracking, integrate advanced click-fraud protection, and create your reporting dashboard for quantifiable results.' ),
                ),
            ),

            // Pricing ("Grow Your Business With Google Ads")
            'pricing' => array(
                'heading' => 'Grow Your Business With Google Ads',
                'intro'   => array(
                    'No matter the size of your business, a strong Google Ads campaign is essential to capture qualified leads.',
                    'Google Ads is ideal for businesses that are looking to capture high-intent, qualified leads.',
                ),
                'fees' => array(
                    array( 'label' => 'One-Time Setup Fee', 'value' => '$999', 'note' => 'Onboarding fee $1500 — new Google Ads accounts pay only $999.' ),
                    array( 'label' => 'Our Management Fee',  'value' => '$0 for 2 months', 'note' => 'New Google Ads accounts pay $0 for the first 2 months — we build trust by showing results before taking your money. After that, $799/month or 15% of monthly Google Ads budget, whichever is greater. ($0 for 2 months applies to new accounts only.)' ),
                ),
                'included_heading' => "What's Included",
                'included' => array(
                    'No contract — performance-based, month-to-month management.',
                    'Get up to $1800 in Google Ads credit when you start with us.',
                    'Flat-rate monthly fee — you pay the agreed-upon fee, no surprises.',
                    'Full transparency — we work on your account and you pay Google directly for the ad spend.',
                ),
                'cta' => 'Talk to a PPC Expert',
            ),

            'faq_heading' => 'Google Ads FAQ',
            'faq' => array(
                array( 'q' => 'Why is Google Ads right for my business?', 'a' => 'In this digital era, establishing a great online advertising platform is almost essential for the success of any business. PPC advertising strongly positions your company to show in front of the right audience, in the right place, at the right time. It is the perfect tool whether you want to increase sales, increase traffic to your website, or simply establish your presence online. We are also experienced in designing landing pages that run cohesively with Google Ads — highly focused, mobile friendly and fast-loading.' ),
                array( 'q' => 'What are other ways to promote my business?', 'a' => 'We offer website audits and consultations. Having a professionally designed and optimized website that is fast and easy-to-use is extremely important to rank high in Google Search and drive organic traffic. We also offer search engine optimization and social media management services — while an optimized website drives organic traffic, social media allows your business to build an extensive online presence and trust with your customers.' ),
                array( 'q' => 'How does Google Ads work?', 'a' => '1. You set your budget. 2. Google holds an "auction", considering everything from your budget to how relevant your website is. 3. The "winner\'s" ad is shown at the top of the search results page.' ),
                array( 'q' => 'What is a landing page?', 'a' => 'The landing page could be any existing page on your website, or completely separate from your main website. It is the page that customers land on when they click on the link in your ad.' ),
                array( 'q' => 'Why do I want my ad at the top of a search results page?', 'a' => 'Potential customers do not tend to scroll past the first page on a Google search, so an ad that does not show on this page is not going to benefit your business. As customers tend to click on the first result that resonates with them, the higher your ad shows, the more people see it — giving you a higher chance at making conversions.' ),
                array( 'q' => 'How do you use Google Ads effectively?', 'a' => 'By researching keywords and competitors for your industry, we create Google Ads that have a high chance of conversion once someone clicks on them. By targeting specific groups of people, we direct more highly-qualified leads to your website.' ),
                array( 'q' => 'Do I need a landing page or website for my campaign?', 'a' => 'No, but it is recommended. A landing page designed specifically for your campaign provides a more consistent experience and relevant content, which can improve your ad\'s overall Quality Score. The Quality Score helps to determine your ad ranking, which severely affects where your ad shows up on the page of any qualified search.' ),
                array( 'q' => 'Why do I need a Google Ads specialist to manage my campaign?', 'a' => 'An effective Google Ads campaign is an ongoing process — not a one-time thing. You need someone to monitor your progress and conduct regular research on your competitors and keywords, so they can update your ads to keep you ahead of the competition. Our specialists are trained and certified on the Google Ads platform.' ),
            ),
        ),

        // ── SEO ─────────────────────────────────────────────────────────
        'seo-agency-toronto' => array(
            'menu'     => 'SEO Services',
            'eyebrow'  => 'Search Engine Optimization',
            'title'    => 'Toronto SEO Agency That You Can Trust',
            'subtitle' => 'Hire Us & Start Seeing Results in 30 Days',
            'lead'     => 'Increase your organic ranking with proven search engine optimization services.',
            'intro'    => array(
                'SEO (Search Engine Optimization) places your website under the organic results section of search engines like Google, Bing or Yahoo. Being found organically by ranking on the first page of Google is one of the best ways to grow your business consistently. Leads coming from organically ranked websites are always more likely to convert and they do not cost anything.',
                'Our certified team of SEO experts are here to provide affordable SEO packages to small and large businesses. Whether you need local small business SEO or large enterprise SEO services, our team applies proven strategies and best practices across all businesses. You can certainly trust us to help you rank on the 1st page of Google, because we have done it all.',
            ),
            'features_heading' => 'How We Rank Your Website',
            'features' => array(
                array( 'icon' => 'seo',    'title' => 'Website SEO Analysis', 'text' => 'Before starting the actual SEO of your business website, we understand your client base, industry requirements and business goals. Our team gathers all the industry benchmarks for your business and develops a comprehensive SEO strategy based on your initial website analysis, predicting the performance and the timeline.' ),
                array( 'icon' => 'website','title' => 'On-Page SEO', 'text' => 'Our team runs a comprehensive diagnosis on your website to fix all internal errors before starting anything — including slow Google PageSpeed, one of the top 3 ranking factors since May 2020. We create a technical document that is handed over to the development team for implementation.' ),
                array( 'icon' => 'ads',    'title' => 'Off-Page SEO Link Building', 'text' => "Our premium SEO writer works to include you in the news cycle. Our news desk identifies high quality news publishers in your industry and creates news stories that include your business's research and facts in the story." ),
            ),
            'benefits_heading' => 'Why SEO with 6ix Developers',
            'benefits_intro'   => 'Our success is in your success. Our SEO packages follow aggressive optimization strategies in collaboration with search engines like Google, Bing and Yahoo — setting a high standard for both small and large business SEO.',
            'benefits' => array(
                array( 'icon' => 'target', 'title' => 'Prominent Position',        'text' => 'Rank for keywords that actually convert.' ),
                array( 'icon' => 'chart',  'title' => 'Detailed Tracking & Reporting', 'text' => 'We set up tracking codes to track user actions and use that information for better UX and improved ranking. Customized reports are shared bi-weekly to illustrate visible improvement.' ),
                array( 'icon' => 'shield',  'title' => 'No Annual Contracts',        'text' => 'Be comfortable with our no long-term contract SEO plans — our success is in your success.' ),
                array( 'icon' => 'spark',  'title' => 'Dedicated Management Team',    'text' => 'You get a dedicated SEO manager who shares a monthly calendar of all actionable SEO work and goes over progress with you every 2 weeks.' ),
                array( 'icon' => 'social', 'title' => 'Multiple Options',             'text' => 'Our dedicated team of SEO specialists will put a plan together that fits your needs.' ),
                array( 'icon' => 'website','title' => 'SEO Dashboard',                'text' => 'Access our online reporting dashboard anytime, anywhere to see your daily real-time traffic reports.' ),
            ),
            'faq' => array(),
        ),

        // ── Social Media ────────────────────────────────────────────────
        'social-media-marketing-agency-toronto' => array(
            'menu'     => 'Social Media',
            'eyebrow'  => 'Social Media Marketing',
            'title'    => 'Social Media Marketing Agency Toronto',
            'subtitle' => 'Explore the Power of Social Media',
            'lead'     => 'Building brands with passion — get your brand into everybody\'s palms.',
            'intro'    => array(
                'Social Media is the fastest moving industry in the world. Your audience spends hours on social media — big examples like Facebook & Instagram — each day. Being on social media is not just an additional way of reaching out to your potential audience anymore; it is the smartest way of getting your brand name out there and building a community that shares the same interests as your brand.',
                'Social media is changing the face of marketing. How your audience interacts with your brand is how you present yourself on social media these days. Most of your potential audience has Facebook and Instagram in their hands but they don\'t have your brand name — you can use these channels to get your brand in everybody\'s palms.',
            ),
            'features_heading' => 'How We Grow Your Presence',
            'features' => array(
                array( 'icon' => 'social', 'title' => 'Be Seen on Social Media', 'text' => 'The Social Media team at 6ix Developers is actively helping over 150 businesses in Canada. We help you build a community that shares the same ideas as your business, using the latest tools and lots of experience to make your potential audience remember your name when they need the services you offer.' ),
                array( 'icon' => 'chart',  'title' => 'Accelerated Marketing & Countable Results', 'text' => "We take your stagnant business growth and revenue to a consistently and gradually growing pace. We can help your business get all those bounced website visitors back and turn them into solid leads and sales using Social Media Accelerated Marketing strategies." ),
                array( 'icon' => 'spark',  'title' => 'Instagram & Facebook Engagement Campaigns', 'text' => 'If your business is located within Canada, we have proven strategies for many industries on growing engagement on social channels. With constant growth and engagement your business will thrive and provide the best possible returns on your marketing dollars.' ),
                array( 'icon' => 'ads',    'title' => 'Social Media Paid Advertising', 'text' => "Don't stop at organic growth. Our paid marketing strategies are designed to interact with an audience that has engaged with your business — or a similar business — in the past, bringing you active and warm leads that are ready to convert." ),
                array( 'icon' => 'target', 'title' => 'Branding & Media', 'text' => 'Use social media to build your brand and let your potential clients interact with it how you envisioned it. 6ix Developers can help your vision come to life and get your brand under the spotlight.' ),
            ),
            'faq' => array(),
        ),

    );
}

/** Content for one service page by slug, or null. */
function six_service_page( $slug ) {
    $all = six_service_pages();
    return $all[ $slug ] ?? null;
}
