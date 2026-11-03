# Hosting n8n on Render or Railway (Easy Alternative to Oracle + DuckDNS)

Replaces Sections 2 (Oracle, DuckDNS) and 3.1–3.4 of the demo build guide. Everything from 3.5 (Google OAuth) onward stays the same, except that your URL is the one the platform gives you instead of `yourname.duckdns.org`.

> Free-tier limits and screen names change often. Check each provider's pricing page before you sign up.

---

## Do I need Docker?

**No, not on your own computer.** Render and Railway run the n8n Docker image for you. You never install Docker, write a `docker-compose.yml`, or set up Caddy.

| Where n8n runs | Docker needed? |
|---|---|
| Render / Railway / n8n Cloud | No. The platform handles it. |
| Your own VPS (Oracle, Hetzner) | Yes, on the server. This is what the original guide does. |
| Your own Windows PC | Optional. Docker Desktop is the easiest way, or `npx n8n` with Node.js. |

You also don't need DuckDNS, Caddy, iptables or SSH keys. The platform gives you a free HTTPS URL.

---

## Which one?

| | Render (free) | Render (Starter) | Railway |
|---|---|---|---|
| Cost | $0 | About $7/mo + disk or external DB | One-time trial credit (about $5), then about $5/mo Hobby plan |
| Sleeps when idle? | **Yes**, after about 15 min | No | No |
| Database | Needs external Postgres (Neon or Supabase) | External Postgres, or Render Postgres | Postgres included in the n8n template |
| Setup effort | Medium | Medium | **Easiest** (one-click template) |
| Good for | Building and testing only | Client trials | Client trials |

**Railway is not free**, but the trial credit is enough to build and record the demo. After that it costs about $5/mo.

**Render free is fine for building but not for client trials.** Sleeping breaks three things:
- the "reply in under 60 seconds" promise
- Twilio webhooks, which time out while the service wakes up
- the hourly Schedule Triggers, which don't fire while it sleeps

**Recommendation:** use Railway for anything a client will see. Use Render free only to practice.

---

## Option A: Railway (easiest)

1. Sign up at railway.com with GitHub.
2. **New Project → Deploy a Template** → search **n8n**. Pick the version that includes **Postgres**.
3. Deploy. It creates the n8n service and the database, and links them.
4. Open the n8n service → **Settings → Networking → Generate Domain**. You get a URL like `n8n-production-xxxx.up.railway.app`.
5. In the n8n service → **Variables**, check or add:

```
WEBHOOK_URL=https://<your-railway-domain>/
N8N_HOST=<your-railway-domain>
N8N_PROTOCOL=https
GENERIC_TIMEZONE=America/Chicago
N8N_ENCRYPTION_KEY=<a long random string you save somewhere safe>
```

6. Redeploy, open the URL, and create your owner account.
7. Pin the image version (for example `n8nio/n8n:1.xx.x`) in the service settings so updates don't break workflows during a trial.

Set a **usage limit** under Railway's billing settings so a surprise bill is impossible.

---

## Option B: Render

### B1. Create a free Postgres database (Neon)
Render's free Postgres expires, and a free web service has no disk. Without a database, you lose your workflows on every redeploy.

1. Sign up at neon.tech and create a project.
2. Copy the connection details: host, database, user and password.

### B2. Create the web service
1. Sign up at render.com.
2. **New → Web Service → Existing Image** → enter `docker.n8n.io/n8nio/n8n` (add a version tag once it works).
3. Choose the **Free** instance type (or Starter for client trials).
4. Add these **environment variables**:

```
N8N_PORT=10000
N8N_PROTOCOL=https
N8N_HOST=<yourapp>.onrender.com
WEBHOOK_URL=https://<yourapp>.onrender.com/
GENERIC_TIMEZONE=America/Chicago
N8N_ENCRYPTION_KEY=<a long random string you save somewhere safe>

DB_TYPE=postgresdb
DB_POSTGRESDB_HOST=<neon host>
DB_POSTGRESDB_PORT=5432
DB_POSTGRESDB_DATABASE=<db name>
DB_POSTGRESDB_USER=<user>
DB_POSTGRESDB_PASSWORD=<password>
DB_POSTGRESDB_SSL_ENABLED=true
```

5. Deploy. The first start takes a few minutes. Open the URL and create your owner account.

### B3. Keep it awake (free tier only)
Create a free UptimeRobot monitor that hits `https://<yourapp>.onrender.com/healthz` every 5 minutes. This reduces sleeping but is not a guarantee. Don't rely on it for a live client trial.

Free Render instances have limited RAM (512 MB), which n8n can strain with large workflows. Starter gives more headroom.

---

## Important for both options

- **Save `N8N_ENCRYPTION_KEY`.** n8n uses it to encrypt your saved credentials. If you lose it, you must re-enter every credential.
- **Update every URL in the guide.** Replace `brightsidedemo.duckdns.org` with your new domain in:
  - the Twilio webhook (Section 9)
  - the `<Dial action="...">` URL in the Respond to Webhook node
  - the Google OAuth redirect URL (Section 3.5)
- **Test the OAuth redirect** right after the first deploy. Google rejects a URL that doesn't match exactly.
- **One instance per client or separated credentials**, as in the compliance section of the original guide.

---

## Quick comparison with the original guide

| Original guide step | With Render / Railway |
|---|---|
| Create Oracle instance, SSH key | Skipped |
| Open ports in the VCN security list and iptables | Skipped |
| Create a DuckDNS subdomain | Skipped (free platform URL) |
| Install Docker | Skipped |
| `docker-compose.yml` + Caddyfile | Skipped (env vars in the dashboard) |
| `docker compose up -d` | The platform deploys automatically |
