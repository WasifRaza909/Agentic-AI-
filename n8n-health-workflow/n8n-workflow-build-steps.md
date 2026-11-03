# n8n Workflow Build: Complete Step-by-Step Instructions
## "Never Miss a Patient" System (Demo Practice: Brightside Dental)

This document covers **only the n8n build**: what we are creating, and exactly how to build it from zero to a tested demo. Pitching, outreach, pricing and trials are in `demo-product-build-and-pitch-guide.md`. Hosting details are condensed from `n8n-hosting-render-railway.md`.

> n8n node names and screens change between versions. If a name below doesn't match, search the node panel for the closest one. Free-tier limits change, so check them when you sign up.

---

## Part 1. What We Are Creating

One product made of **6 workflows** that make sure no patient inquiry, missed call or review goes unanswered.

| # | Workflow | What it does | Tier |
|---|---|---|---|
| 1 | Instant Lead Responder | Website form inquiry gets an AI-written personal email reply in under 60 seconds, is logged in a Sheet, and the owner is alerted | Free |
| 2 | 24-Hour Follow-Up | Leads still marked "New" after 24h get a friendly follow-up email | Free |
| 3 | Review Reply Assistant | A new Google review gets an AI-drafted reply emailed to the owner for approval | Free |
| 4 | Weekly Owner Report | Monday-morning summary email: leads, bookings, open leads, reviews | Free |
| 5 | Missed-Call Text-Back | A missed call triggers an instant SMS to the caller and is logged | Paid (Twilio) |
| 6 | Error Alerts | Emails you when any workflow fails | Internal |

**Demo practice (fake):** Brightside Dental, Austin, TX. Owner: Dr. Sarah Mitchell (use your own second email as the "owner" inbox).

**Tools used:** n8n, Gmail, Google Sheets, Google Gemini (AI), Twilio (SMS/calls), Cal.com (booking link).

**How the pieces connect:**

```
Form -> [1 Lead Responder] -> Gemini writes reply -> Gmail to patient
                                   |-> Sheet "Leads" -> owner alert
Sheet "Leads" -> [2 Follow-Up] (hourly) -> Gmail follow-up -> mark followup_sent
Review -> [3 Review Assistant] -> Gemini draft -> Sheet "Reviews" -> owner email
Sheet "Leads"/"Reviews" -> [4 Weekly Report] (Mon 8am) -> owner email
Phone call -> Twilio -> [5a Incoming] -> rings office -> [5b Status] -> SMS + Sheet "Calls" + owner email
Any failure -> [6 Error Alerts] -> email to you
```

---

## Part 2. Build Order (7 Days)

| Day | Task |
|---|---|
| 1 | Accounts, run n8n locally (Part 4A), Google + AI credentials (Parts 3, 5) |
| 2 | Google Sheet + Workflow 1 (local) |
| 3 | Workflow 2 + Workflow 4 (local) |
| 4 | Workflow 3 (local), then deploy to Railway and import Workflows 1–4 (Part 4C) |
| 5 | Workflow 5 (Twilio) + Workflow 6 (Error Alerts) built on Railway |
| 6 | End-to-end testing on Railway (Part 12) |
| 7 | Record the demo video, final polish |

**Approach:** build and test Workflows 1–4 locally for free, so the Railway trial credit is only spent once things work. Workflow 5 needs a public URL, so it is built on the hosted instance.

---

## Part 3. Accounts to Create

1. **Railway** (railway.com, sign in with GitHub): hosts n8n once you're ready to demo or run trials. Not needed while building locally. (Alternative: Render, see 4B-alt.)
2. **Google account** for the demo, e.g. `brightside.demo.yourname@gmail.com` (Gmail + Sheets).
3. **Google Cloud Console** (free project): OAuth credentials so n8n can use Gmail and Sheets.
4. **Google AI Studio** (aistudio.google.com): free Gemini API key. (Groq free tier is a backup.)
5. **Twilio** (free trial): for Workflow 5 only.
6. **Cal.com** (free): create an event called "New Patient Visit" and copy its link.
7. **Loom** (free): demo video later.

You do **not** need Docker, DuckDNS, Caddy or SSH on Railway/Render. The platform runs the n8n image and gives you an HTTPS URL. Docker is only optional on your own PC (see 4A).

---

## Part 4. Run and Host n8n

### 4A. Build locally first (free)
Use this to build and test Workflows 1–4 before paying for hosting.

1. Install Node.js (LTS) and run `npx n8n`, or install Docker Desktop and run:
   `docker run -it --rm -p 5678:5678 -v n8n_data:/home/node/.n8n docker.n8n.io/n8nio/n8n`
2. Open `http://localhost:5678` and create your owner account.
3. For Google OAuth (Part 5), add this redirect URI in Google Cloud: `http://localhost:5678/rest/oauth2-credential/callback`.

**Works locally:** Workflows 1, 2, 3, 4 and 6 (they only need outbound internet to Gmail, Sheets and Gemini).

**Does not work locally:**
- Workflow 5 (Twilio): needs a public URL. Build it on Railway, or use a tunnel such as ngrok.
- Form Trigger links: only open on your own machine, so you can't share them.
- Schedule Triggers (hourly follow-up, Monday report): only fire while your PC and n8n are running.

Don't run client trials from your PC. Power or internet cuts will stop everything.

### 4B. Railway (recommended hosting, easiest)
1. railway.com, sign up with GitHub.
2. **New Project → Deploy a Template**, search **n8n**, choose the version **with Postgres**.
3. Deploy. It creates the n8n service and a database and links them.
4. Open the n8n service, **Settings → Networking → Generate Domain**. You get `n8n-production-xxxx.up.railway.app`.
5. Open the n8n service, **Variables**, check or add:

```
WEBHOOK_URL=https://<your-railway-domain>/
N8N_HOST=<your-railway-domain>
N8N_PROTOCOL=https
GENERIC_TIMEZONE=America/Chicago
N8N_ENCRYPTION_KEY=<long random string, saved somewhere safe>
```

6. Redeploy, open the URL, create your owner account.
7. Once everything works, pin the image to a specific version tag (e.g. `n8nio/n8n:1.xx.x`) so an update can't break a client trial.
8. In Railway billing, set a **usage limit**.

Railway has only a one-time trial credit (about $5), then about $5/mo. That covers building the demo.

### 4B-alt. Render (alternative; free tier only for practice)
Render free sleeps after about 15 minutes, which breaks the 60-second reply promise, Twilio webhooks and hourly schedules. Use Starter (about $7/mo) for client trials.

1. **Database:** create a free Postgres project at neon.tech and copy host, database, user, password. (Without a database you lose workflows on every redeploy.)
2. render.com → **New → Web Service → Existing Image** → `docker.n8n.io/n8nio/n8n`.
3. Pick Free (practice) or Starter (trials).
4. Environment variables:

```
N8N_PORT=10000
N8N_PROTOCOL=https
N8N_HOST=<yourapp>.onrender.com
WEBHOOK_URL=https://<yourapp>.onrender.com/
GENERIC_TIMEZONE=America/Chicago
N8N_ENCRYPTION_KEY=<long random string, saved somewhere safe>
DB_TYPE=postgresdb
DB_POSTGRESDB_HOST=<neon host>
DB_POSTGRESDB_PORT=5432
DB_POSTGRESDB_DATABASE=<db name>
DB_POSTGRESDB_USER=<user>
DB_POSTGRESDB_PASSWORD=<password>
DB_POSTGRESDB_SSL_ENABLED=true
```

5. Deploy (first start takes a few minutes), open the URL, create your owner account.
6. Free tier only: add an UptimeRobot monitor on `https://<yourapp>.onrender.com/healthz` every 5 minutes. This reduces sleeping but is no guarantee.

### 4C. Move local workflows to the hosted instance
1. In local n8n, open each workflow → **⋯ menu → Download** (saves JSON).
2. In the hosted n8n, create a workflow → **⋯ menu → Import from file**.
3. Re-create the credentials (Gmail, Sheets, Gemini) on the hosted instance. They do not carry over. Open each node and reselect the credential.
4. Add the hosted redirect URI (`https://<your-domain>/rest/oauth2-credential/callback`) to the Google OAuth client, then sign in again.
5. Re-run tests, then toggle each workflow **Active**.
6. Build Workflow 5 and Workflow 6 here.

### Important for hosted n8n
- **Save `N8N_ENCRYPTION_KEY`.** If lost, you must re-enter every credential.
- Wherever this document says `<YOUR-N8N-URL>`, use your platform domain.

---

## Part 5. Connect Google and AI

### 5.1 Google (Gmail + Sheets)
Do this first for local n8n (using the localhost redirect URI), and again for the hosted instance later.

1. Google Cloud Console → new project.
2. Enable **Gmail API**, **Google Sheets API**, **Google Drive API**.
3. **OAuth consent screen:** External; add your demo Gmail as a **test user**.
4. **Credentials → Create OAuth Client ID → Web application.**
5. In n8n: **Credentials → New → Gmail OAuth2 API**. n8n shows a **redirect URL**. Paste it into the OAuth client's **Authorized redirect URIs** in Google. It must match exactly.
6. Copy Client ID and Secret into n8n, click **Sign in with Google**.
7. Repeat for **Google Sheets OAuth2 API** (same client, same redirect URL).
8. Test both credentials right away.

### 5.2 Gemini
1. Get a free API key at aistudio.google.com.
2. In n8n: **Credentials → New → Google Gemini (PaLM) API**, paste the key.
3. You will use the **Google Gemini Chat Model** sub-node inside a **Basic LLM Chain** node.

---

## Part 6. Create the Google Sheet

Create a Sheet named **"Brightside Dental – Patient Response"** with 4 tabs. Row 1 is headers.

**Tab `Leads`:**
`lead_id | created_at | name | email | phone | message | category | priority | status | reply_sent | followup_sent | source`
- Add a dropdown on `status` (Data → Data validation): New, Booked, Not interested.

**Tab `Reviews`:**
`review_id | received_at | reviewer | stars | review_text | draft_reply | status`

**Tab `Calls`:**
`call_time | caller | call_status | sms_sent`

**Tab `Settings`** (keeps workflows reusable per client):
`practice_name | city | phone | booking_link | owner_email | tone`
Row 2: `Brightside Dental | Austin, TX | (512) 555-0142 | <your Cal.com link> | <owner demo email> | warm, friendly, professional`

Fake practice details: cleanings, whitening, Invisalign, implants, emergency visits; Mon–Fri 8am–5pm.

---

## Part 7. Workflow 1: Instant Lead Responder

**Goal:** every website inquiry gets a personal reply in under 60 seconds.

Create a new workflow named `1 - Instant Lead Responder`. Add nodes in this order:

### Step 1. n8n Form Trigger
- Form title: "Brightside Dental – Request an Appointment".
- Fields: Full Name (required), Email (required), Phone, "How can we help?" (textarea, required).
- Form description: "Please don't include detailed medical information."
- This gives a public form URL that acts as the practice's website form in the demo. (For a real client, connect their existing form via webhook, or a Gmail trigger on the form's notification email.)

### Step 2. Google Sheets → Get Row(s), tab `Settings`
Loads practice info so nothing is hard-coded.

### Step 3. Basic LLM Chain + Google Gemini Chat Model
Attach the Gemini credential to the chat model sub-node. Prompt:

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

### Step 4. Code node, name it `Parse AI`

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

### Step 5. Gmail → Send a message (to the patient)
- To: `{{ $json.email }}`
- Subject: `{{ $json.subject }}`
- Message: `{{ $json.reply }}`, email type Text.

### Step 6. Google Sheets → Append Row, tab `Leads`
Map all fields from `Parse AI`. Set `status` = New, `reply_sent` = yes, `followup_sent` = no, `source` = Website form.

### Step 7. IF node
Condition: `{{ $('Parse AI').item.json.priority }}` equals `high`.

### Step 8a. (true) Gmail → Send to owner
- To: owner email from Settings.
- Subject: `🔥 High-value lead: {{ name }} ({{ category }})`
- Body: name, phone, message, and "Already replied automatically at {{ created_at }}. Call them today."

### Step 8b. (false) Gmail → Send to owner
Simpler "New lead" note. Optional: skip it and rely on the weekly report.

### Step 9. Test and activate
1. Click **Execute workflow**, submit the test form URL, check the patient inbox, Sheet and owner inbox.
2. Toggle the workflow **Active** (the form's production URL only works when active).

**Demo moment:** submit the form on screen, and within seconds the patient gets a personal reply, the Sheet fills in and the owner is alerted.

---

## Part 8. Workflow 2: 24-Hour Follow-Up

Name: `2 - 24 Hour Follow-Up`.

1. **Schedule Trigger:** every hour.
2. **Google Sheets → Get Row(s)**, tab `Leads`.
3. **Code node**, name `Needs Follow-Up`:

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

4. **Gmail → Send** to the lead (`{{ $json.email }}`):

```
Hi {{ $json.name.split(' ')[0] }},

Just checking in. We'd love to help with your request. You can pick a time that suits you here: [booking link], or simply reply to this email.

The Brightside Dental Team
```

5. **Google Sheets → Update Row**, tab `Leads`, match on `lead_id`, set `followup_sent` = yes.
6. Activate.

**Demo moment:** set HOURS to about 2 minutes, show a lead getting followed up, then mark another lead "Booked" in the Sheet to show it's skipped. Set HOURS back to 24 afterwards.

---

## Part 9. Workflow 3: Review Reply Assistant

Name: `3 - Review Reply Assistant`.

**Compliance rule (US/HIPAA):** replies must never confirm the reviewer is a patient or mention any treatment. The prompt enforces this.

### Step 1. Trigger
- **Demo:** n8n Form Trigger "Paste a new review" with fields Reviewer, Stars (1–5), Review text.
- **Real client:** Google emails review notifications to the owner. Have the practice forward them to a dedicated inbox, then use a **Gmail Trigger** (filter subject containing "review"; test with a real notification) plus an LLM step to extract reviewer, stars and text. The Google Business Profile API needs access approval, so start with forwarding.

### Step 2. Google Sheets → Get Row(s), tab `Settings`

### Step 3. Basic LLM Chain + Gemini
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

### Step 4. Google Sheets → Append Row, tab `Reviews`
Set `review_id`, `received_at`, reviewer, stars, review_text, `draft_reply` = AI text, status = Draft.

### Step 5. Gmail → Send to owner
- Subject: `New {{ stars }}★ review – reply ready` (prefix with ⚠️ for 1–2 stars; use an expression or an IF node).
- Body: the review and the draft reply.

### Step 6. Activate

**Demo moment:** paste a 2-star review and the owner instantly gets a careful, safe reply draft.

---

## Part 10. Workflow 4: Weekly Owner Report

Name: `4 - Weekly Owner Report`.

1. **Schedule Trigger:** weekly, Monday 8:00. Check the workflow timezone under Settings (client's timezone).
2. **Google Sheets → Get Row(s)**, tab `Leads`. Rename node to `Get Leads`.
3. **Google Sheets → Get Row(s)**, tab `Reviews`. Rename to `Get Reviews`. In node Settings enable **Execute Once**.
4. **Code node**, name `Build Report`:

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

5. **Gmail → Send** to owner:

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

6. Test by executing manually, then activate.

---

## Part 11. Workflow 5: Missed-Call Text-Back (Paid Tier)

**How it works:** a call comes into a Twilio number → Twilio rings the office line for 20 seconds → if nobody answers, n8n texts the caller and logs the call.

**Twilio trial limits:** only verified numbers can be called or texted, and texts carry a trial notice. Fine for the demo, not for real patients.

### Setup
1. Twilio Console → get a trial phone number. Verify your personal phone as a caller ID.
2. n8n: **Credentials → New → Twilio API** (Account SID + Auth Token).

### Part A: Workflow `5a - Incoming Call`
1. **Webhook node:** method POST, path `incoming-call`, Respond: "Using 'Respond to Webhook' node".
2. **Respond to Webhook:** respond with Text, add header `Content-Type: text/xml`, body:

```xml
<Response>
  <Dial timeout="20" action="https://<YOUR-N8N-URL>/webhook/call-status">
    <Number>+1XXXXXXXXXX</Number>
  </Dial>
</Response>
```
The Number is the "office line". In the demo, use a second phone you won't answer.

3. Activate. In Twilio, set the number's **Voice → A call comes in** to `https://<YOUR-N8N-URL>/webhook/incoming-call`, HTTP POST.

### Part B: Workflow `5b - Call Status`
1. **Webhook node:** POST, path `call-status`, respond via Respond to Webhook.
2. **IF node:** `{{ $json.body.DialCallStatus }}` is `no-answer`, `busy` or `failed` (OR conditions).
3. **(true) Twilio → Send SMS:**
   - From: your Twilio number; To: `{{ $json.body.From }}`
   - Message:
```
Hi, it's Brightside Dental. Sorry we missed your call! Reply here and we'll get right back to you, or book instantly: [booking link]. Reply STOP to opt out.
```
4. **Google Sheets → Append Row**, tab `Calls`: call_time, caller, call_status, sms_sent = yes.
5. **Gmail → Send** to owner: "Missed call from {{ From }}, text sent automatically."
6. **Respond to Webhook** (both IF branches merge here), header `Content-Type: text/xml`:

```xml
<Response>
  <Say>Sorry we missed you. We've just sent you a text so you can reach us quickly. Goodbye.</Say>
  <Hangup/>
</Response>
```
7. Activate.

**Demo moment:** call the Twilio number, let it ring out, and your phone receives the text within seconds.

### Going live for a paying client
- The practice keeps its number and sets **conditional call forwarding** (no answer/busy) to the Twilio number. Test caller ID passthrough per carrier.
- Use the **client's own Twilio account** so they pay usage directly, and complete **A2P 10DLC** registration under their business name before texting real patients (US). UK SMS sender rules differ; check Twilio's UK guidance.

---

## Part 12. Workflow 6: Error Alerts, then Testing

### Error Alerts
1. New workflow `6 - Error Alerts`: **Error Trigger** → **Gmail Send** to you.
2. Message: `Workflow {{ $json.workflow.name }} failed: {{ $json.execution.error.message }}`
3. In **each** other workflow: **Settings → Error Workflow** → select `6 - Error Alerts`.

### Test before every demo
- [ ] Form submission → patient reply received in under 60 seconds
- [ ] Reply sounds natural, no prices, no medical advice
- [ ] Emergency-style message gets the "call us right away" wording and a 🔥 owner alert
- [ ] Sheet row created with all columns
- [ ] Follow-up fires (demo timing) and skips "Booked" leads
- [ ] 2-star review → careful draft, no patient confirmation
- [ ] Weekly report email looks clean
- [ ] Missed call → SMS arrives on your phone
- [ ] Break something on purpose → you get the error email
- [ ] Emails and texts read well on a phone
- [ ] All workflows are **Active**, image version pinned, `N8N_ENCRYPTION_KEY` saved

---

## Part 13. Troubleshooting

| Problem | Likely fix |
|---|---|
| Google sign-in fails with redirect error | Redirect URI in Google must match n8n's exactly; recheck after any domain change |
| `Parse AI` throws a JSON error | Model added extra text; keep "Return ONLY valid JSON" in the prompt, or lower temperature |
| Form works in test but not live | Workflow isn't Active; use the production URL |
| Webhooks time out (Twilio) | Host is sleeping (Render free) or `WEBHOOK_URL` is wrong |
| Hourly follow-up doesn't run | Workflow inactive, or host asleep |
| Follow-up sent twice | Update Row not matching `lead_id`; check the match column |
| Weekly report shows duplicates | Enable **Execute Once** on `Get Reviews` |
| Lost credentials after redeploy | Missing database or changed `N8N_ENCRYPTION_KEY` |

---

## Part 14. Compliance Rules Built Into the Workflows

- Keep data minimal: name, email, phone, short inquiry. No treatment details.
- Form shows "Please don't include detailed medical information."
- Review replies never confirm someone is a patient.
- AI replies never give medical advice or prices.
- SMS includes "Reply STOP to opt out"; US needs A2P 10DLC and TCPA awareness; UK follows GDPR.
- Use a separate n8n instance, or clearly separated credentials and Sheets, per client.

---

## Part 15. Reusing for a Real Client

1. Duplicate the Sheet and fill in the client's `Settings` row.
2. Create credentials under the client's Google account (or a shared mailbox) and, for SMS, their own Twilio account.
3. Point their website form to the Workflow 1 webhook/trigger, or use a Gmail trigger on form notifications.
4. Change the sign-off, tone and booking link in `Settings`; adjust prompts if needed.
5. Re-run the testing checklist, then activate.
