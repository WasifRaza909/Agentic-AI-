# "Never Miss a Patient" System
## Complete Demo Build + Pitch Guide (n8n, US/UK Dental Practices)

This guide walks you through building one demo product, step by step, and using it to pitch, run free trials, and convert your first paying client.

> Note: n8n node names and screens change slightly between versions. If a node name below doesn't match exactly, search the node panel for the closest match. Free-tier limits of the services below can also change, so check them when you sign up.

---

## 0. What You're Building

One product with two tiers. The free tier costs you $0 to run. The paid tier adds SMS and phone automations.

| # | Workflow | What it does for the practice | Tier |
|---|---|---|---|
| 1 | Instant Lead Responder | Website inquiry gets a personal AI email reply in under 60 seconds, is logged, and the owner is alerted | Free trial |
| 2 | 24-Hour Follow-Up | Leads who haven't booked get a friendly follow-up automatically | Free trial |
| 3 | Review Reply Assistant | Every new Google review gets an AI-drafted reply sent to the owner for approval | Free trial |
| 4 | Weekly Owner Report | Monday email: leads, bookings, open follow-ups, reviews | Free trial |
| 5 | Missed-Call Text-Back | Missed call gets an instant SMS so the patient books instead of calling a competitor | Paid |
| – | Error Alerts | You get an email if any workflow fails | Internal |

**Your one-line pitch:**
"I connect the tools you already use and run automations for you, so every lead, missed call and review gets a response without your team doing extra work."

**Demo practice (fake):** Brightside Dental, Austin, TX. Owner: Dr. Sarah Mitchell. Use your own second email address as the "owner" inbox for the demo.

---

## 1. Build Order (Week 1)

| Day | Task |
|---|---|
| 1 | Create accounts, set up free hosting and n8n (Sections 2–3) |
| 2 | Google Sheet + Workflow 1 (Instant Lead Responder) |
| 3 | Workflow 2 (Follow-Up) + Workflow 4 (Weekly Report) |
| 4 | Workflow 3 (Review Assistant) + Error Alerts |
| 5 | Workflow 5 (Missed-Call Text-Back) on Twilio trial |
| 6 | Test everything end to end (Section 10) |
| 7 | Record the Loom demo (Section 11), set up LinkedIn profile |

---

## 2. Accounts to Create (all free)

1. **Oracle Cloud** (Always Free tier): hosts n8n 24/7. Card may be needed for verification only.
2. **DuckDNS** (duckdns.org): free subdomain like `yourname.duckdns.org` for HTTPS.
3. **Google account** for the demo: Gmail + Google Sheets. Create a new one like `brightside.demo.yourname@gmail.com`.
4. **Google Cloud Console**: free project to create OAuth credentials so n8n can use Gmail and Sheets.
5. **Google AI Studio**: free Gemini API key (or Groq free tier as a backup).
6. **Twilio** (free trial credit): for the missed-call demo only.
7. **Loom** (free): demo videos.
8. **Cal.com** (free): fake booking link for Brightside Dental.

---

## 3. Free Hosting: n8n on Oracle Cloud

### 3.1 Create the server
1. In Oracle Cloud, create a Compute Instance using an **Always Free eligible** shape and an **Ubuntu** image.
2. Download the SSH key when prompted.
3. In the instance's **VCN → Security List**, add ingress rules for TCP ports **80** and **443** from `0.0.0.0/0`.
4. Copy the instance's public IP.

### 3.2 Point DuckDNS to it
On duckdns.org, create a subdomain (e.g. `brightsidedemo`) and set it to your server's public IP.

### 3.3 Install Docker and open the firewall
SSH into the server, then run:

```bash
sudo apt update && sudo apt install -y docker.io docker-compose-v2
sudo usermod -aG docker $USER
# Oracle's Ubuntu images block ports at the OS level too:
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo apt install -y iptables-persistent && sudo netfilter-persistent save
```

Log out and back in so the Docker group applies.

### 3.4 Run n8n with automatic HTTPS (Caddy)

```bash
mkdir ~/n8n && cd ~/n8n
```

Create `docker-compose.yml`:

```yaml
services:
  n8n:
    image: docker.n8n.io/n8nio/n8n
    restart: always
    environment:
      - N8N_HOST=brightsidedemo.duckdns.org
      - N8N_PROTOCOL=https
      - WEBHOOK_URL=https://brightsidedemo.duckdns.org/
      - GENERIC_TIMEZONE=America/Chicago
    volumes:
      - n8n_data:/home/node/.n8n
  caddy:
    image: caddy:2
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
volumes:
  n8n_data:
  caddy_data:
```

Create `Caddyfile`:

```
brightsidedemo.duckdns.org {
  reverse_proxy n8n:5678
}
```

Start it:

```bash
docker compose up -d
```

Open `https://brightsidedemo.duckdns.org`, create your owner account, and you're live.

Tip: once it works, pin the n8n image to a specific version tag so updates don't break workflows during a client trial.

### 3.5 Connect Google (Gmail + Sheets)
1. Google Cloud Console → new project → enable **Gmail API** and **Google Sheets API** (and Google Drive API).
2. Configure the OAuth consent screen (External, add your demo Gmail as a test user).
3. Create **OAuth Client ID** (Web application). Add the redirect URL that n8n shows you in its Google credential screen.
4. Paste the Client ID and Secret into n8n credentials for Gmail and Google Sheets, then sign in.

### 3.6 Connect AI
In n8n, add a **Google Gemini (PaLM) API** credential with your AI Studio key. You'll use the **Google Gemini Chat Model** sub-node inside a **Basic LLM Chain** node.

---

## 4. Demo Assets

### 4.1 Google Sheet: "Brightside Dental – Patient Response"

**Tab `Leads`** (row 1 headers):
`lead_id | created_at | name | email | phone | message | category | priority | status | reply_sent | followup_sent | source`

- `status` values: New, Booked, Not interested (add a dropdown via Data → Data validation)

**Tab `Reviews`:**
`review_id | received_at | reviewer | stars | review_text | draft_reply | status`

**Tab `Calls`** (for paid workflow):
`call_time | caller | call_status | sms_sent`

**Tab `Settings`** (makes it easy to reuse for each client):
`practice_name | city | phone | booking_link | owner_email | tone`
Row 2: `Brightside Dental | Austin, TX | (512) 555-0142 | your Cal.com link | owner demo email | warm, friendly, professional`

### 4.2 Fake practice details
- Services: cleanings, whitening, Invisalign, implants, emergency visits
- Hours: Mon–Fri 8am–5pm
- Booking link: a free Cal.com event named "New Patient Visit"

---

## 5. Workflow 1: Instant Lead Responder (Free Tier)

**Goal:** Every website inquiry gets a personal reply in under 60 seconds.

### Nodes in order

**1. n8n Form Trigger** — "Brightside Dental – Request an Appointment"
Fields: Full Name (required), Email (required), Phone, "How can we help?" (textarea, required).
Add a note on the form: "Please don't include detailed medical information."
This gives you a public form URL that acts as the practice's website form in the demo. For a real client, you'd connect their existing form (via webhook, or a Gmail trigger on the form's notification email).

**2. Google Sheets → Get Rows** (`Settings` tab)
Loads the practice info so nothing is hard-coded.

**3. Basic LLM Chain** (with **Google Gemini Chat Model** attached)
Prompt:

```
You are the front-desk assistant for {{ $json.practice_name }}, a dental practice in {{ $json.city }}.

A potential patient submitted this inquiry:
Name: {{ $('n8n Form Trigger').item.json['Full Name'] }}
Message: {{ $('n8n Form Trigger').item.json['How can we help?'] }}

Write a short email reply. Rules:
- Under 120 words, tone: {{ $json.tone }}.
- Greet them by first name and acknowledge what they asked about.
- Never give medical advice, diagnoses, or prices.
- If they describe severe pain, swelling, bleeding or trauma, tell them to call {{ $json.phone }} right away, and to seek emergency care if symptoms are severe.
- Invite them to book here: {{ $json.booking_link }} or call {{ $json.phone }}.
- Sign off as "The {{ $json.practice_name }} Team".

Also classify the inquiry.
Return ONLY valid JSON, no extra text:
{"category": "emergency|cosmetic|implant|invisalign|routine|other",
 "priority": "high|normal",
 "subject": "email subject line",
 "reply": "the email body"}
Priority is "high" for emergency, implant, invisalign and cosmetic.
```

**4. Code node** — "Parse AI"

```js
const raw = $input.first().json.text;
const clean = raw.replace(/```json|```/g, '').trim();
const ai = JSON.parse(clean);
const form = $('n8n Form Trigger').first().json;

return [{
  json: {
    lead_id: 'L' + Date.now(),
    created_at: new Date().toISOString(),
    name: form['Full Name'],
    email: form['Email'],
    phone: form['Phone'] || '',
    message: form['How can we help?'],
    category: ai.category,
    priority: ai.priority,
    subject: ai.subject,
    reply: ai.reply
  }
}];
```

**5. Gmail → Send** (to the patient)
- To: `{{ $json.email }}`
- Subject: `{{ $json.subject }}`
- Message: `{{ $json.reply }}` (email type: Text)

**6. Google Sheets → Append Row** (`Leads` tab)
Map all fields. Set `status` = New, `reply_sent` = yes, `followup_sent` = no, `source` = Website form.

**7. IF node** — `{{ $('Parse AI').item.json.priority }}` equals `high`

**8a (true). Gmail → Send** to owner — Subject: `🔥 High-value lead: {{ name }} ({{ category }})`, body with name, phone, message, and "Already replied automatically at {{ created_at }}. Call them today."

**8b (false). Gmail → Send** to owner — simpler "New lead" note. (Optional: skip normal alerts and rely on the weekly report.)

**Demo moment:** Submit the form on screen → within seconds, the patient inbox gets a personal reply, the Sheet fills in, and the owner gets an alert.

---

## 6. Workflow 2: 24-Hour Follow-Up (Free Tier)

**1. Schedule Trigger** — every hour.

**2. Google Sheets → Get Rows** (`Leads`)

**3. Code node** — "Needs Follow-Up"

```js
const HOURS = 24; // set to 0.03 (about 2 minutes) for a live demo
const now = Date.now();
return $input.all().filter(item => {
  const l = item.json;
  const age = now - new Date(l.created_at).getTime();
  return l.status === 'New'
    && l.followup_sent !== 'yes'
    && age > HOURS * 60 * 60 * 1000;
});
```

**4. Gmail → Send** — friendly follow-up:

```
Hi {{ $json.name.split(' ')[0] }},

Just checking in. We'd love to help with your request. You can pick a time that suits you here: [booking link], or simply reply to this email.

The Brightside Dental Team
```

**5. Google Sheets → Update Row** — match on `lead_id`, set `followup_sent` = yes.

**Demo moment:** Set HOURS to about 2 minutes, show a lead getting followed up automatically, then mark another one "Booked" in the Sheet to show it's skipped.

---

## 7. Workflow 3: Review Reply Assistant (Free Tier)

**Important (US/HIPAA):** Review replies must never confirm that the reviewer is a patient or mention any treatment. The prompt below enforces this.

### Trigger options
- **Demo:** n8n Form Trigger "Paste a new review" with fields Reviewer, Stars (1–5), Review text.
- **Real client:** Google sends review notification emails to the business owner. Have the practice auto-forward those to a dedicated inbox, then use a **Gmail Trigger** (filter by subject containing "review"; test with a real notification to confirm the format) plus an LLM step to extract reviewer, stars and text. The alternative, Google Business Profile API, requires applying for access, so start with email forwarding.

### Nodes
**1. Trigger** (as above)

**2. Basic LLM Chain + Gemini** — prompt:

```
You write public replies to Google reviews for {{ practice_name }}, a dental practice.
Review: "{{ review_text }}"  Stars: {{ stars }}  Reviewer: {{ reviewer }}

Rules:
- Under 70 words, warm and professional.
- NEVER confirm the person is a patient, and never mention any treatment, visit or health detail.
- 4–5 stars: thank them warmly, invite them to share the practice with friends.
- 1–3 stars: apologize that their experience fell short, don't argue, and invite them to call the office manager at {{ phone }} to talk it through.
- Sign as "The {{ practice_name }} Team".
Return only the reply text.
```

**3. Google Sheets → Append Row** (`Reviews`), status = Draft.

**4. Gmail → Send** to owner — Subject: `New {{ stars }}★ review – reply ready`; body shows the review and draft reply. For 1–2 stars, prefix the subject with ⚠️.

**Demo moment:** Paste a 2-star review → owner instantly gets a careful, safe reply draft.

---

## 8. Workflow 4: Weekly Owner Report (Free Tier)

**1. Schedule Trigger** — weekly, Monday 8:00 (workflow timezone = client's timezone; check under workflow Settings).

**2. Google Sheets → Get Rows** (`Leads`) — name it "Get Leads"

**3. Google Sheets → Get Rows** (`Reviews`) — name it "Get Reviews". In node Settings, turn on **Execute Once** so it doesn't run once per lead.

**4. Code node** — "Build Report"

```js
const weekAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
const leads = $('Get Leads').all().map(i => i.json)
  .filter(l => new Date(l.created_at).getTime() > weekAgo);
const reviews = $('Get Reviews').all().map(i => i.json)
  .filter(r => new Date(r.received_at).getTime() > weekAgo);

const booked = leads.filter(l => l.status === 'Booked').length;
const high = leads.filter(l => l.priority === 'high').length;
const open = leads.filter(l => l.status === 'New');

return [{
  json: {
    total: leads.length,
    booked,
    high,
    reviews: reviews.length,
    openList: open.map(l => `- ${l.name} (${l.category}) ${l.phone}`).join('\n') || 'None, great job!'
  }
}];
```

**5. Gmail → Send** to owner:

```
Good morning Dr. Mitchell,

Here's your Brightside Dental week:

New inquiries: {{ $json.total }}  (high-value: {{ $json.high }})
Booked: {{ $json.booked }}
Every inquiry was answered in under 1 minute.
New reviews: {{ $json.reviews }}

Still waiting to book (worth a call):
{{ $json.openList }}

Have a great week!
```

---

## 9. Workflow 5: Missed-Call Text-Back (Paid Tier)

### How it works
Calls come into a Twilio number → Twilio rings the office line for 20 seconds → if nobody answers, n8n instantly texts the caller and logs the call.

**Twilio trial limits:** trial accounts can only text and call **verified** numbers (add your own phone), and messages include a trial-account notice. Perfect for recording your demo; not for real patients.

### Setup
1. Twilio Console → get a trial phone number. Verify your personal phone as a caller ID.
2. In n8n, add a **Twilio** credential (Account SID + Auth Token).

### Part A: Incoming call
**1. Webhook node** — POST, path `incoming-call`, Respond: "Using 'Respond to Webhook' node".

**2. Respond to Webhook** — Respond with: Text. Add header `Content-Type: text/xml`. Body:

```xml
<Response>
  <Dial timeout="20" action="https://brightsidedemo.duckdns.org/webhook/call-status">
    <Number>+1XXXXXXXXXX</Number>
  </Dial>
</Response>
```
(The Number is the "office line". In your demo, use a second phone you won't answer.)

In Twilio, set the number's **Voice → A call comes in** webhook to `https://brightsidedemo.duckdns.org/webhook/incoming-call` (HTTP POST).

### Part B: Call result
**1. Webhook node** — POST, path `call-status`, respond via Respond to Webhook.

**2. IF node** — `{{ $json.body.DialCallStatus }}` is one of `no-answer`, `busy`, `failed` (use an OR condition).

**3 (true). Twilio → Send SMS**
- From: your Twilio number; To: `{{ $json.body.From }}`
- Message:
```
Hi, it's Brightside Dental. Sorry we missed your call! Reply here and we'll get right back to you, or book instantly: [booking link]. Reply STOP to opt out.
```

**4. Google Sheets → Append Row** (`Calls`): call_time, caller, call_status, sms_sent = yes.

**5. Gmail → Send** to owner: "Missed call from {{ From }}, text sent automatically."

**6. Respond to Webhook** (both branches merge here) — `Content-Type: text/xml`:

```xml
<Response>
  <Say>Sorry we missed you. We've just sent you a text so you can reach us quickly. Goodbye.</Say>
  <Hangup/>
</Response>
```

**Demo moment:** Call the Twilio number from your phone, let it ring out, and your phone receives the text within seconds. Film this for the Loom.

### Going live for a paying client
- Usually the practice keeps its number and sets **conditional call forwarding** (no answer / busy) to the Twilio number. Caller ID passing through forwarding varies by carrier, so test it.
- Set up the client on **their own Twilio account** so they pay usage directly, and complete **A2P 10DLC** registration under their business name before texting real patients (US).
- UK: SMS sender rules differ; check Twilio's UK guidelines.

---

## 10. Testing Checklist + Error Alerts

### Error alerts
Create a workflow: **Error Trigger** → **Gmail Send** to you ("Workflow {{ $json.workflow.name }} failed: {{ $json.execution.error.message }}"). In each workflow's Settings, set **Error Workflow** to this one.

### Test before every demo
- [ ] Form submission → patient reply received in under 60 seconds
- [ ] Reply sounds natural and has no prices or medical advice
- [ ] Emergency-style message gets the "call us right away" wording and a 🔥 owner alert
- [ ] Sheet row created with all columns
- [ ] Follow-up fires (demo timing) and skips "Booked" leads
- [ ] 2-star review → careful draft, no patient confirmation
- [ ] Weekly report email looks clean
- [ ] Missed call → SMS arrives on your phone
- [ ] Break something on purpose → you receive the error email
- [ ] Test on mobile: emails and texts read well on a phone

---

## 11. The Loom Demo Video (2–3 minutes)

Record screen + camera. Have the form, the patient inbox, the owner inbox, the Google Sheet and your phone (via phone mirroring or holding it to camera) ready.

**Script:**

1. **(0:00–0:20) The problem.** "Hi, I'm [Name]. Most dental practices lose new patients in two places: inquiries that wait hours for a reply, and calls that go to voicemail. Those patients usually just contact the next dentist on Google."
2. **(0:20–1:00) Instant reply.** "Watch: I'm a new patient asking about Invisalign… submit. Here's my inbox: a personal reply in seconds, with the booking link. And the owner gets an alert that this is a high-value lead."
3. **(1:00–1:30) Never lost.** "Everything is logged here. If they don't book, they get a friendly follow-up automatically. Every Monday the owner gets this summary."
4. **(1:30–2:00) Reviews.** "When a new review comes in, even a bad one, a professional reply is drafted and sent to you for approval."
5. **(2:00–2:30) Missed calls.** "And for calls: I'll call the practice and let it ring out… and within seconds, the caller gets a text so they book with you instead."
6. **(2:30–2:50) Offer.** "It works with the tools you already use, and I set it up and run it for you. I'm setting up a free 14-day trial for 3 practices this month. If you'd like one of the spots, just reply to my message."

Make a short version (60–90 seconds) for cold outreach and keep the full one for interested prospects.

---

## 12. Lead Leak Audit (Your Best Door-Opener)

Do this for each prospect before you contact them. It takes 10–15 minutes.

1. **Contact form test:** Submit a test inquiry (be transparent later that it was a test). Note how long the reply takes, if any.
2. **After-hours call:** Call after closing time. Voicemail? Any text back?
3. **Google reviews:** How many in the last 3 months? Are reviews replied to?
4. **Ads check:** Search the practice in Meta Ad Library. Running ads means they pay for leads, so lost leads hurt more.

**Audit summary template** (send as a short Loom or in the email):

```
Quick lead check for [Practice] (done [date]):
- Contact form: no reply after [X] hours / replied after [X] hours
- After-hours call: voicemail, no text back
- Google reviews: [X] in the last 90 days, [Y]% have replies
- Ads: currently running on Facebook/Instagram

Each of these is fixable with simple automation, using the tools you already have.
```

---

## 13. Outreach Templates

Personalize the first line of every message using your audit. Aim for 20–30 personalized messages a day.

### Cold email: form hook
**Subject:** quick question about your contact form

```
Hi Dr. [LastName],

I tested [Practice]'s website contact form on Tuesday at 2pm, and I haven't had a reply yet. Most people who fill in a form contact 2–3 practices and book with whoever replies first.

I built a small system that answers every inquiry personally within 60 seconds and follows up automatically, using the tools you already have. Here's a 90-second video of it working: [Loom link]

I'm setting this up free for 3 practices this month (14 days, no contract). Would you like one of the spots?

[Your Name]
```

### Cold email: review hook
**Subject:** your Google reviews

```
Hi [Name],

I noticed [Practice] has some great recent Google reviews, but most haven't been replied to. Replies help you rank higher and show new patients you care.

I've built a simple assistant that drafts a professional reply to every new review and sends it to you for one-click approval, and it also answers new website inquiries instantly. 90-second demo: [Loom link]

I'm offering a free 14-day trial to 3 practices this month. Worth a look?

[Your Name]
```

### Cold email: missed-call hook (for the paid pitch)
**Subject:** called you at 6:40pm

```
Hi Dr. [LastName],

I called [Practice] yesterday at 6:40pm and got voicemail with no text back. Most patients who reach voicemail just call the next dentist on Google.

I set up a system that instantly texts every missed caller so they book with you instead. Here's a 60-second video of it working: [Loom link]

Open to a quick 15-minute call this week?

[Your Name]
```

### Follow-up 1 (3 days later)
```
Hi [Name], just floating this back up. If now isn't the right time, no problem. Should I check back next month instead?
```

### Follow-up 2 (5 days later)
```
Hi [Name], last note from me. If missed inquiries or unanswered reviews ever become a priority, my free 14-day trial offer stands. Here's the demo again: [Loom link]. Wishing you a great week.
```

### LinkedIn connection note (to practice owners or office managers)
```
Hi [Name], I help dental practices make sure every inquiry and missed call gets a reply within a minute. Would love to connect.
```

### LinkedIn message after they accept
```
Thanks for connecting, [Name]! Quick one: when a patient fills in your website form after hours, how quickly do they usually hear back? I recorded a 90-second demo of a system that replies instantly: [Loom link]. Happy to set it up free for 14 days if it's useful.
```

### Dental marketing agency partnership
**Subject:** white-label automation for your dental clients

```
Hi [Name],

You're driving leads for dental practices, but much of that value is lost when the practice replies slowly or misses calls, and the practice blames the ads.

I build automations that reply to every lead in under 60 seconds, follow up automatically, text back missed calls and draft review replies. I can deliver this white-label under your brand.

Here's a 2-minute demo: [Loom link]. Would you be open to trying it with one client for free?

[Your Name]
```

---

## 14. Free Trial Offer + Simple Agreement

**Scope (fixed):** Instant Lead Responder + 24h Follow-Up + Review Reply Assistant + Weekly Report, for 14 days.

**Simple agreement (send by email, ask them to reply "Agreed"):**

```
Free 14-Day Trial – [Practice] x [Your Name]

What I'll set up (free, no contract):
1. Instant personal replies to website inquiries (under 60 seconds)
2. Automatic follow-up for leads who haven't booked
3. AI-drafted replies to new Google reviews, sent to you for approval
4. A weekly summary email every Monday

Trial dates: [start] to [end]

What I ask in return:
- 15-minute results review call at the end
- If you're happy, a short testimonial
- Permission to share anonymized results (no names or patient details)

Data: The system only handles contact details and inquiry messages, never treatment records. Please don't route clinical information through it.

After the trial, you can continue on a paid plan, or I'll switch everything off. No obligation.
```

**Rules for you:** fixed scope only; extra requests are "part of the paid version"; maximum 2–3 trials at a time.

---

## 15. Trial-End Conversion Call (15 minutes)

1. **Show results** (prepare a one-page summary from the Sheet):
```
[Practice] – 14-Day Results
- Inquiries answered: 23 (average reply time: under 1 minute)
- Follow-ups sent: 11 → 4 booked after follow-up
- High-value inquiries flagged: 6
- Reviews with drafted replies: 5
```
2. **Ask:** "What's an average new patient worth to you over a year?" Let them say the number.
3. **ROI math out loud:** "So if even 2 extra patients a month book because they got a fast reply, that's [2 × their number] a month."
4. **Offer:** "To keep this running and add missed-call text-back, it's [setup] plus [monthly]. Want me to keep it switched on?"
5. **If no:** "No problem. Would you be open to giving a short testimonial, and do you know another practice owner who might want this?"

---

## 16. Pricing Packages

Start low to get the first case study, then raise prices.

| Package | Includes | First clients | After case studies |
|---|---|---|---|
| Starter | Lead Responder + Follow-Up + Weekly Report | $300–500 setup + $150–200/mo | $1,000–1,500 setup + $300–500/mo |
| Growth | Starter + Review Assistant + Missed-Call Text-Back | $500–1,000 setup + $200–300/mo | $1,500–3,000 setup + $500–750/mo |

Twilio usage is paid by the client on their own account. Monthly fee covers hosting, monitoring, fixes and small changes.

---

## 17. Objection Handling

**"We already use Weave / Podium / another system."**
"Great, I don't replace it. What isn't it doing for you right now? I usually connect to what practices already have and fill the gaps, like ad leads that never get followed up."

**"Patients hate automated messages."**
"Agreed, which is why every message is written to sound like your front desk, uses their name and what they asked about. And they get it in a minute instead of tomorrow. You can approve the wording before anything goes live."

**"Is this HIPAA compliant?"**
"The system only handles contact details and appointment requests, never treatment records, and review replies never confirm someone is a patient. Anything clinical stays in your practice software. If you need specific compliance paperwork, we can review that together before going live." (Don't claim to be "HIPAA certified"; no such thing exists, and don't promise compliance you haven't verified.)

**"You're overseas."**
"I work on US hours for my clients, and everything runs on reliable cloud systems. The free trial lets you judge the results before paying anything."

**"Just send me some info."**
"Sure. Here's the 2-minute demo [link]. The fastest way to know if it's worth it is the free trial; it takes me about 2 days to set up. Shall I reserve a spot?"

**"Too expensive."**
"What's one new patient worth to you? If this books even one extra patient a month, it more than pays for itself. Let the trial numbers decide."

---

## 18. Compliance Basics (Keep It Safe)

- Keep data minimal: name, email, phone, short inquiry. No treatment details.
- Add "Please don't include detailed medical information" on forms.
- Review replies never confirm someone is a patient.
- AI replies never give medical advice or prices.
- SMS: include "Reply STOP to opt out"; US clients need A2P 10DLC registration; follow TCPA rules (ask the client to confirm with their own advisor if unsure).
- UK: follow GDPR. Tell people how their data is used, keep it minimal, honour deletion requests.
- Use a separate n8n instance or clearly separated credentials and sheets per client.

---

## 19. 30-Day Action Checklist

**Week 1: Build**
- [ ] Hosting + n8n live
- [ ] Workflows 1–5 + error alerts built and tested
- [ ] Loom videos recorded (90-second and full versions)
- [ ] LinkedIn headline: "I help dental practices reply to every lead and missed call in under 60 seconds"

**Week 2: Outreach**
- [ ] List of 150 practices (one city at a time) with emails
- [ ] Audit 10 practices a day, send 20–30 personalized messages a day
- [ ] Email 10 dental marketing agencies
- [ ] Post on LinkedIn 3–4 times this week (demo clips, lessons)

**Week 3: Calls + trials**
- [ ] Book demo calls, offer free trials (max 3)
- [ ] Set up trials within 48 hours of "yes"
- [ ] Keep outreach running daily

**Week 4: Convert**
- [ ] Track trial results daily
- [ ] Results review calls, make paid offer
- [ ] Collect testimonials and referrals
- [ ] Turn your first result into a case study and add it to outreach

**Track weekly:** messages sent, replies, calls booked, trials started, clients won. If replies are under 3%, rewrite your first line and subject. If calls don't convert, improve the demo and ROI story.
