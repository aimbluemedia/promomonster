-- The per-trade playbooks, which have never existed.
--
-- The playbooks table has been in the schema since migration 009 and no row
-- has ever been written to it: there is no INSERT INTO playbooks anywhere in
-- the repository. So every account, whatever its trade, saw "we build your
-- playbook during setup" forever, and the Your playbook page has been an empty
-- promise since the day it shipped.
--
-- One row per trade in App\Support\Trades, keyed by the same slugs. Those keys
-- are already in locations.vertical on live rows, so they are data: renaming
-- one here orphans every account on it.
--
-- primary_channel is 'email' on every row. The ENUM also allows 'sms' and
-- 'qr'; neither is built. SMS needs a carrier registration billed per business
-- and was cut from the plans for that reason, and nothing in this product
-- generates a QR code. A playbook telling somebody to send an SMS through
-- software that cannot send one is the same lie this file exists to remove.
--
-- No figures anywhere. "Asking at the door lifts response 40%" is the kind of
-- sentence a playbook invites and this one cannot source, and a business that
-- reorganises its day around an invented number has been misled by us.
--
-- The two compliance notes point at the regulator rather than stating the
-- rule. Dental and legal advertising rules differ by country and by state, and
-- a confident wrong summary here is worse for the member than being told to go
-- and look.
--
-- INSERT IGNORE, not ON DUPLICATE KEY UPDATE: the table comment in 009 says
-- these are data so they can be edited, and a re-paste must not silently
-- overwrite wording somebody has tuned. Safe to run twice; it will not undo
-- your edits.
--
-- Written for phpMyAdmin: every quoted string on ONE line, ASCII only.

INSERT IGNORE INTO playbooks (vertical, display_name, trigger_moment, who_asks, primary_channel, verbal_ask, follow_up_days, offline_method, objection) VALUES
('landscaping', 'Landscaping / lawn care', 'The last visit of the month, while the garden still looks freshly done', 'The crew lead who actually did the work', 'email', 'If the garden looks how you wanted it, would you mind saying so online? I will send you the link before I drive off.', 2, 'A card through the door with the review link printed on it, left on the last visit', 'I would not know how. -- Tell them the link opens the box for them and it takes a minute. Never offer to write it for them.'),
('hvac', 'HVAC / plumbing / electrical', 'Once the system is running again and they have felt it working', 'The engineer who fixed it, before packing the van', 'email', 'Glad that is sorted. If you have two minutes, a review helps the next person find us when their heating packs up. I will send the link now.', 2, 'The review link printed on the invoice, and on the van', 'That was expensive. -- Settle the price conversation before you ask. An unhappy customer asked for a review leaves one.'),
('roofing', 'Roofing / exteriors', 'On the final walk-round, once the skip has gone and the job looks finished', 'The site foreman, standing with the owner in front of the work', 'email', 'Before I go, would you leave us a review? People spend a lot on a roof and they want to hear from somebody who has had one done.', 3, 'A photo of the finished roof, emailed with the link underneath it', 'I will do it later. -- Later is where reviews go to die. Ask while you are still packing up.'),
('dental', 'Dental / medical', 'At the desk, after treatment is finished and the next appointment is booked', 'The receptionist, not the clinician who treated them', 'email', 'If you were happy with how today went, would you leave us a review? It helps people who are nervous about coming in.', 3, 'A card handed over with the appointment reminder', 'I do not want my treatment discussed publicly. -- Tell them to review the practice and the people, not the procedure.'),
('veterinary', 'Veterinary', 'A day or two after the visit, once the animal is clearly on the mend', 'The nurse who handled the discharge', 'email', 'How is she doing? If you are happy with how we looked after her, a review would mean a lot to us.', 3, 'The review link on the discharge notes', 'A poor outcome. -- Do not ask at all. A grieving owner asked for a review writes a complaint, and they are right to.'),
('restaurant', 'Restaurant / cafe', 'With the bill, while they are still at the table and still full', 'The server who looked after them', 'email', 'Hope you enjoyed it. If you have a second, a review helps us more than you would think.', 2, 'A card on the saucer with the bill, link printed on it', 'They are in a hurry. -- Hand the card over and say nothing else. Do not chase somebody who is putting a coat on.'),
('salon', 'Salon / barber / spa', 'At the chair, while they are still looking in the mirror and pleased with it', 'The stylist who did the work', 'email', 'If you love it, would you say so online? I will send you the link while you pay.', 2, 'The link printed on the back of the appointment card', 'I hate how I look in photos. -- Nobody has to post one. It is words.'),
('auto', 'Auto repair', 'At handover, with the keys in their hand and the car ready', 'Whoever hands the keys back', 'email', 'All done. If we did right by you, a review helps a lot -- most people pick a garage from them.', 2, 'The link on the invoice and on the counter', 'Shock at the bill. -- Explain the invoice first. Never ask on top of a bill somebody is unhappy about.'),
('legal', 'Law firm', 'At the end of the matter, when the file closes and the result is known', 'The fee earner who ran the matter, not the office', 'email', 'Now that is behind you, would you be willing to leave a review? You do not need to say anything about the case itself.', 5, 'A line in the closing letter, with the link', 'Confidentiality. -- They can review the service without any of the facts. Say so plainly and they usually will.'),
('realestate', 'Real estate', 'On completion day, at the handover of the keys', 'The agent who ran the sale, by name', 'email', 'Congratulations. Reviews follow me rather than the office, so if today went well it would really help me.', 3, 'The link in the completion email', 'The chain was stressful. -- Acknowledge it first, then ask about how you handled it rather than how it went.'),
('other', 'Something else', 'The day the work is done, while they still remember your name', 'The person who did the work, by name', 'email', 'If we did a good job, would you leave us a review? I will send you the link now.', 3, 'A card with the link on it, handed over at the end', 'I will do it later. -- Ask while you are still in front of them. Nothing else on this page matters as much as that.');

-- The two trades with a regulator looking over their shoulder. Set separately
-- so the long note stays on its own line and the VALUES list above stays
-- readable. Each UPDATE is harmless if the row is not there.
UPDATE playbooks SET compliance_note = 'Never put a patient name, condition, treatment or appointment in a review request, and never confirm in a public reply that somebody is a patient. In the US that is a HIPAA disclosure whether or not the review named them first. Check your own regulator before you start asking.' WHERE vertical = 'dental' AND compliance_note IS NULL;
UPDATE playbooks SET compliance_note = 'Most bars restrict what may be published about a client and what a testimonial may imply about results. Do not ask a client to mention the outcome, and do not answer a review with anything about the matter. Check your own bar admission rules on advertising and testimonials before you start asking.' WHERE vertical = 'legal' AND compliance_note IS NULL;
