<?php

declare(strict_types=1);

namespace App\Support;

/**
 * How to get reviews, and where a business should be asking for them.
 *
 * Reference content, held as data rather than written into a template, so the
 * page stays a list and the list stays editable without touching markup. It is
 * the same for every account -- there is nothing here to store per business --
 * so it is a constant, not a table, and needs no migration.
 *
 * Deliberately no web addresses. Sixty platforms is sixty chances to send a
 * business to a domain that has changed hands, and a confidently wrong link in
 * a product is worse than no link: somebody follows it. The names are exact
 * enough to search for, which is the job.
 *
 * Nothing here quotes a figure. "Reviews lift conversion by 31%" is the kind of
 * sentence this product could easily carry and could not stand behind, and a
 * business that acts on an invented number has been misled by us rather than by
 * the internet.
 */
final class ReviewPlaces
{
    /**
     * The practices that decide whether asking works.
     *
     * Ordered by how much they change the outcome, not by how easy they are.
     * The first three are about timing and reach and cost nothing; the ones
     * after are about not undoing that.
     *
     * @var list<array{do:string, why:string}>
     */
    public const PRACTICES = [
        [
            'do'  => 'Ask on the day the work is done',
            'why' => 'While they can still remember your name and what you fixed. A week '
                   . 'later they have to reconstruct it, and most people do not bother. '
                   . 'Nothing else on this page moves the number as much as this does.',
        ],
        [
            'do'  => 'Ask out loud first, then send the link',
            'why' => 'A request that arrives after somebody has already said "yes, I will" '
                   . 'is a reminder of their own decision. One that arrives cold is a '
                   . 'favour being asked by an email. Say it at the door, send it from '
                   . 'the van.',
        ],
        [
            'do'  => 'Ask everybody',
            'why' => 'Not the ones you expect to be pleased. Picking who gets asked is '
                   . 'review gating, it is against Google policy, and since 2024 the FTC '
                   . 'has banned it outright. It is also the version that works: a page of '
                   . 'nothing but fives reads as bought, and a four with a specific '
                   . 'complaint answered well sells more than either.',
        ],
        [
            'do'  => 'Offer nothing in return',
            'why' => 'No discount, no draw, no free coffee. Google removes reviews '
                   . 'collected that way and can penalise the listing, and in the US it '
                   . 'breaks the law. If the work was good, the ask is enough.',
        ],
        [
            'do'  => 'Make it one tap',
            'why' => '"Find us on Google and leave a review" loses most people at "find". '
                   . 'A link that opens the review box is the whole difference, which is '
                   . 'what the link in your requests does.',
        ],
        [
            'do'  => 'Use the name of the person who did the work',
            'why' => '"How did Dave do?" gets answered. "How did we do?" gets deleted. It '
                   . 'also tells you which of your people customers remember, which is '
                   . 'worth knowing for its own sake.',
        ],
        [
            'do'  => 'Send one reminder, then stop',
            'why' => 'The second message catches the people who meant to and forgot, which '
                   . 'is most of them. The third catches nobody and loses you the next '
                   . 'job. Chasing harder is the most common way to turn a customer who '
                   . 'liked you into one who does not.',
        ],
        [
            'do'  => 'Reply to every review, and to the bad ones first',
            'why' => 'The reply is not for the person who wrote it. It is for the next '
                   . 'customer reading, who is deciding what you are like when something '
                   . 'goes wrong. Answer the complaint, say what you changed, do not '
                   . 'argue about the facts in public.',
        ],
        [
            'do'  => 'Keep it a trickle, not a burst',
            'why' => 'Forty reviews in a week after two years of none looks exactly like '
                   . 'what it would be if it were bought, and the platforms treat it that '
                   . 'way. A few a week, every week, builds something that holds.',
        ],
        [
            'do'  => 'Act on what they tell you',
            'why' => 'The same complaint twice is a job list, not bad luck. Fixing it is '
                   . 'the only thing on this page that improves the reviews rather than '
                   . 'the number of them.',
        ],
    ];

    /**
     * Where to ask, grouped by the kind of business.
     *
     * Grouped because a flat list of sixty is a wall nobody reads, and because
     * the useful question is not "what exists" but "which of these is mine".
     * Each platform appears once, in the place it earns its keep; the universal
     * ones are in the first group rather than repeated in every other.
     *
     * The advice that matters is in the first group's note: a business needs
     * Google and then one or two others that its own customers actually read.
     * Being thinly present on fifteen is worth less than being properly present
     * on three.
     *
     * @var list<array{name:string, note:string, places:list<array{0:string,1:string}>}>
     */
    public const GROUPS = [
        [
            'name'  => 'Start here, whatever you do',
            'note'  => 'Google is the one that decides whether a stranger calls you. Get '
                     . 'that right before you spend an hour anywhere else.',
            'places' => [
                ['Google Business Profile', 'Everyone. The one that shows up when somebody searches your trade and your town'],
                ['Facebook Recommendations', 'Almost any consumer-facing business, and the one your existing customers already use'],
                ['Yelp', 'Restaurants, home services, beauty, local retail, contractors, auto, professional services'],
                ['Better Business Bureau', 'General businesses, contractors, financial services, home services, ecommerce'],
                ['Trustpilot', 'Online businesses, ecommerce, SaaS, financial services, insurance, agencies, subscriptions'],
            ],
        ],
        [
            'name'  => 'Home services and trades',
            'note'  => 'These carry quoting as well as reputation, so a thin profile costs '
                     . 'you work directly rather than only in search.',
            'places' => [
                ['Angi', 'Contractors, landscaping, plumbing, HVAC, roofing, remodeling, electricians, cleaning'],
                ['HomeAdvisor', 'Contractors and home-service professionals'],
                ['Thumbtack', 'Local service professionals, photographers, cleaners, movers, tutors, events'],
                ['Houzz', 'Remodelers, kitchen and bath, interior designers, architects, builders, landscapers'],
                ['Nextdoor', 'Neighbourhood businesses, contractors, landscapers, pool, plumbing, HVAC, real estate'],
            ],
        ],
        [
            'name'  => 'Restaurants and food',
            'note'  => 'The delivery platforms rate you whether you ask or not, so the only '
                     . 'choice is whether anybody good is reviewing.',
            'places' => [
                ['OpenTable', 'Restaurants'],
                ['Resy', 'Restaurants'],
                ['Grubhub', 'Restaurants and food delivery'],
                ['DoorDash', 'Restaurants and food delivery'],
                ['Uber Eats', 'Restaurants and food delivery'],
            ],
        ],
        [
            'name'  => 'Travel and hospitality',
            'note'  => 'Where the booking happens is where the review has to be. A great '
                     . 'Google rating does not fill rooms if the booking site is empty.',
            'places' => [
                ['Tripadvisor', 'Hotels, resorts, restaurants, attractions, tours, activities'],
                ['Booking.com', 'Hotels, vacation rentals, resorts and lodging'],
                ['Expedia', 'Hotels, travel, resorts, vacation properties'],
                ['Airbnb', 'Vacation rentals, short-term rentals and experiences'],
            ],
        ],
        [
            'name'  => 'Healthcare',
            'note'  => 'Patients search the specialist directories before they search '
                     . 'Google. Check what each one already says about you.',
            'places' => [
                ['Healthgrades', 'Doctors, physicians, medical practices'],
                ['Vitals', 'Doctors and healthcare professionals'],
                ['Zocdoc', 'Doctors, dentists, healthcare providers'],
                ['WebMD Doctor Reviews', 'Physicians and healthcare providers'],
                ['RateMDs', 'Doctors and medical professionals'],
            ],
        ],
        [
            'name'  => 'Legal',
            'note'  => 'Somebody choosing a lawyer reads more before calling than almost '
                     . 'any other customer. These are where they read.',
            'places' => [
                ['Avvo', 'Attorneys and law firms'],
                ['Martindale-Hubbell', 'Attorneys and law firms'],
                ['Lawyers.com', 'Lawyers and law firms'],
                ['Justia', 'Attorneys and law firms'],
            ],
        ],
        [
            'name'  => 'Real estate',
            'note'  => 'Agent reviews follow the person, not the brokerage, so they are '
                     . 'worth building whoever you work for.',
            'places' => [
                ['Zillow', 'Realtors, agents, mortgage professionals'],
                ['Realtor.com', 'Realtors and real estate professionals'],
                ['Redfin', 'Agents and housing transactions'],
            ],
        ],
        [
            'name'  => 'Auto',
            'note'  => 'These rate the salesperson as well as the dealership, which is the '
                     . 'part worth asking for by name.',
            'places' => [
                ['Cars.com', 'Auto dealers'],
                ['DealerRater', 'Dealerships and salespeople'],
                ['CarGurus', 'Auto dealers'],
                ['Edmunds', 'Auto dealers and vehicles'],
            ],
        ],
        [
            'name'  => 'Ecommerce and products',
            'note'  => 'Two different things here: the marketplace that sells for you, and '
                     . 'the review service that puts stars on your own site.',
            'places' => [
                ['Amazon Reviews', 'Physical products and ecommerce'],
                ['Walmart Reviews', 'Consumer products and ecommerce'],
                ['eBay Feedback', 'Ecommerce sellers and products'],
                ['Etsy Reviews', 'Handmade goods, crafts, collectibles, custom products'],
                ['Shopify Shop Reviews', 'Ecommerce stores and products'],
                ['Sitejabber', 'Online businesses and ecommerce'],
                ['ConsumerAffairs', 'Consumer services, finance, home products, warranties, insurance, major brands'],
                ['Reviews.io', 'Ecommerce, online businesses and brands'],
                ['Feefo', 'Ecommerce, travel, finance and online services'],
                ['Yotpo', 'Ecommerce and consumer products'],
                ['Judge.me', 'Shopify and ecommerce product reviews'],
                ['PowerReviews', 'Large ecommerce brands and retailers'],
                ['Bazaarvoice', 'Retailers, brands and ecommerce product reviews'],
            ],
        ],
        [
            'name'  => 'Software and B2B',
            'note'  => 'Buyers shortlist from these directories before they ever reach your '
                     . 'site, so an empty profile is a shortlist you are not on.',
            'places' => [
                ['G2', 'SaaS, software, B2B technology'],
                ['Capterra', 'Business software and SaaS'],
                ['GetApp', 'SaaS and business software'],
                ['Software Advice', 'SaaS and business software'],
            ],
        ],
        [
            'name'  => 'Agencies and professional services',
            'note'  => 'These verify the client before publishing, so they take longer and '
                     . 'count for more.',
            'places' => [
                ['Clutch', 'Marketing agencies, web developers, SEO, software developers, IT firms'],
                ['DesignRush', 'Marketing agencies, web design, branding, software development'],
                ['UpCity', 'Digital marketing, SEO, advertising, web design, B2B agencies'],
            ],
        ],
        [
            'name'  => 'As an employer',
            'note'  => 'Customers read these too, more often than most owners expect, and '
                     . 'they are the hardest to influence by asking.',
            'places' => [
                ['Glassdoor', 'Employer reputation and employee reviews'],
                ['Indeed', 'Employer reviews and recruiting reputation'],
            ],
        ],
        [
            'name'  => 'Multi-location',
            'note'  => 'Built for chains and groups managing many profiles at once.',
            'places' => [
                ['Birdeye', 'Multi-location businesses; healthcare, dental, automotive, home services'],
            ],
        ],
    ];

    /** How many places the directory lists, counted rather than typed. */
    public static function count(): int
    {
        $n = 0;
        foreach (self::GROUPS as $group) {
            $n += count($group['places']);
        }

        return $n;
    }
}
