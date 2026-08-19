# WC AI Assistant

A floating AI chat bubble for WooCommerce. Answers customer questions about your
products, prices, stock and policies, and captures leads when it can't help.

This is a PHP port of the Python digital twin in [`../twin/`](../twin/), rebuilt to run
inside WordPress so it works on shared hosting (Hostinger Premium and similar) with no
second server and no monthly bill.

## Why a plugin instead of a hosted Python service

Shared hosting runs PHP, not long-lived Python processes. Running inside WordPress also
removes most of the moving parts the split architecture needed:

| Concern | Hosted Python service | This plugin |
| --- | --- | --- |
| Product data | WooCommerce REST API + keys + sync job | `wc_get_products()` reads the DB directly |
| Cross-origin requests | CORS allowlist | Same origin — no CORS at all |
| API key safety | Kept server-side on a second host | Kept in the WordPress DB, never sent to the browser |
| Cost | ~$5–7/mo for an always-on host | $0 |
| Cold starts | 30–60s on free tiers | None |

## Requirements

- WordPress with WooCommerce active
- PHP 8.1+
- Outbound HTTPS from PHP (check **Tools → Site Health**: *"HTTP requests seem to be
  working as expected"* must pass)
- A Google Gemini API key

## Install

1. Zip the `wc-ai-assistant` folder.
2. **Plugins → Add New → Upload Plugin**, choose the zip, install, activate.
3. Go to **WooCommerce → AI Assistant**.
4. Paste your Gemini API key and save.
5. Click **Test connection**. A green tick means the server reached Gemini and the key works.
6. Fill in **Shipping & returns**, then load the storefront — the bubble appears bottom-right.

Alternatively upload the folder to `wp-content/plugins/` over SFTP and activate from the
Plugins screen.

## Settings

| Setting | Notes |
| --- | --- |
| Gemini API key | Stored in the DB. Leave blank when saving to keep the existing key. |
| Models | One per line, in preference order. On a quota error the next is tried — the fallback chain from `app.py`. |
| Shipping & returns | Free text added to the prompt. **Products and prices come from WooCommerce automatically — don't repeat them here.** |
| Notify email | Where leads and unanswered questions are emailed. |
| Pushover | Optional push notifications, matching `tools.py`. |
| Messages per hour | Per-IP cap protecting your API quota. |
| Launcher button | Icon only, icon + text, or text only; five built-in glyphs or your own image; left/right and a bottom offset. Previewed live on the settings screen. |

### Telling the launcher apart from a WhatsApp button

Stores that already float a WhatsApp button need the assistant to read as something
else at a glance. The default `AI bubble` glyph is a squared-off bubble holding two
sparkles — a different silhouette from WhatsApp's round tailed bubble, and the sparkle
is the shorthand shoppers already read as "AI". If both buttons sit on the same side,
add a label (**Icon and text**) and raise this one with **Lift off the bottom**; 96px
clears a typical floating button.

## How it works

```
Shopper → widget.js → POST /wp-json/wc-ai/v1/chat
                          ↓
                  WCAI_Context   builds the system prompt from wc_get_products()
                  WCAI_Gemini_Client   calls Gemini, falling back on quota errors
                  WCAI_Tools     runs tool calls, loops results back
                          ↓
                  reply → widget.js
```

Your whole catalogue is small enough to fit in the system prompt, so there is no
retrieval step — the model sees every product, variant, price and stock state on every
turn. The prompt is cached for 15 minutes and flushed automatically whenever a product
is created, updated or deleted, so it never quotes a stale price.

If the catalogue grows past roughly 100 products the prompt gets expensive, and
`WCAI_Context::catalogue()` should be replaced with a `search_products` tool backed by
WooCommerce's product search.

## Files

| File | Role |
| --- | --- |
| `wc-ai-assistant.php` | Bootstrap, activation, cache invalidation hooks |
| `includes/class-wcai-settings.php` | Settings screen, launcher builder, connection tester |
| `includes/class-wcai-icons.php` | The launcher icon set, shared by the admin picker and the storefront |
| `includes/class-wcai-gemini-client.php` | API client, model fallback (port of `_create_completion`) |
| `includes/class-wcai-context.php` | System prompt from live WooCommerce data (replaces `context.py`) |
| `includes/class-wcai-tools.php` | Tool schemas and dispatch (port of `tools.py`) |
| `includes/class-wcai-leads.php` | Lead storage, admin screen, notifications |
| `includes/class-wcai-rest.php` | `/wp-json/wc-ai/v1/chat`, tool loop, rate limiting |
| `includes/class-wcai-widget.php` | Enqueues the storefront assets |
| `assets/widget.js` / `assets/widget.css` | The bubble, in a shadow root |

## Security notes

- The API key never reaches the browser; all model calls happen server-side.
- The chat endpoint is public by necessity (shoppers aren't logged in). It's protected by
  a same-origin check and a per-IP hourly rate limit. **If you expect real abuse, put
  Cloudflare or a CAPTCHA in front of it** — a rate limit alone is friction, not a wall.
- Client-supplied history is filtered to `user`/`assistant` turns only, so a visitor can't
  inject a fake `system` message to rewrite the assistant's instructions.
- Model output is HTML-escaped before a small Markdown subset is applied, so a prompt
  injection can't inject markup into your storefront.

## Captured leads

**WooCommerce → AI Chat Leads** lists contact details and unanswered questions, newest
first, with the page each came from.
