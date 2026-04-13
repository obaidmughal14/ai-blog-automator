=== AI Blog Automator ===
Contributors: devigontech
Tags: ai, blog, gemini, seo, automation, content, openai
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 2.0.13
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered long-form posts with queue automation, stock images, internal linking, FAQ/schema, and optional Google Indexing API.

== Description ==

**AI Blog Automator** helps WordPress sites produce draft (or published) articles at scale using modern LLMs—**Google Gemini** by default—with optional **OpenAI**, **Anthropic Claude**, and **custom OpenAI-compatible** endpoints. An **Auto** provider mode can chain providers when one rate-limits.

**What you get**

* **Generate** — One-off articles from topic + primary keyword (AJAX), with optional secondary keywords and categories.
* **Queue** — Schedule many articles; **bulk paste** from a spreadsheet (tabs) or pipe-separated lines: title, focus keyphrase, other keywords, category IDs, article format; optional **daily/weekly staggering** from a start date.
* **Content** — Outlines, sections, mandatory **FAQ** block (search-style Q&A), closing paragraphs without stock “conclusion” headings, editorial cleanup (no emojis / typographic dashes per policy), outbound links to reputable sources where prompted.
* **Images** — **Pexels** and/or **Unsplash** (official API; Unsplash Source is retired). Featured image + in-article figures with **credits** when the API provides them.
* **Internal links** — Suggestions against your **published posts and pages**; machine placeholders are always replaced with real `<a>` links.
* **SEO** — Meta title/description and focus keyword for **Yoast**, **Rank Math**, **All in One SEO**, or **native** `_aiba_*` output. **Secondary keywords** map into synonyms / additional keywords / combined keyword fields where supported.
* **Schema** — Optional JSON-LD for Article and FAQ when enabled.
* **Indexing** — Optional **Google Search Console Indexing API** using a service account JSON key.

**Documentation**

Full administrator documentation ships with the plugin:

* `docs/USER-GUIDE.html` — Open in a browser for a **styled guide with SVG figures** (sidebar, API settings, queue bulk, generate flow, pipeline diagram).
* `docs/USER-GUIDE.txt` — Same material as **plain text**, with paths to the same figures for offline reading.
* **Upgrade / Feedback** — Under **AI Automator** in wp-admin: purchase and premium help on **Upgrade**; product feedback on **Feedback** (also linked in the hero bar on every plugin screen).
* **Marketing shortcode** — `[aiba_product_demo]` outputs a feature block for your public product page (see **Upgrade** screen in the plugin for the exact snippet).
* **CodeCanyon pack** — `packaging/envato/` contains listing HTML, installation, credits, privacy, support template, screenshot checklist, and zip build notes for authors.
* **Welcome notice** — After activation, administrators see a dismissible onboarding notice on AI Automator screens.
* `LICENSE` — **GPLv2 or later** (copyright and license notice; full text linked from License URI above).

**Requirements**

WordPress **6.0+**, PHP **8.0+**. At least one LLM API key. For images, configure **Pexels** and/or **Unsplash Access Key** (see Settings → API).

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/` (keep `ai-blog-automator.php` at the root of the folder).
2. Activate **AI Blog Automator** under **Plugins**.
3. Go to **AI Automator → Settings → API**: add your **Gemini** key (and optional Pexels / Unsplash / other providers).
4. Click **Save**, then **Test API connections**.
5. Set **Content**, **Automation**, and **SEO** tabs to match your site.
6. Read **`docs/USER-GUIDE.html`** (or `USER-GUIDE.txt`) for step-by-step workflows, queue column formats, and troubleshooting.

== Frequently Asked Questions ==

= Does this work without Yoast or Rank Math? =

Yes. Choose **native** SEO handling or leave detection on **auto** when no supported SEO plugin is present; the plugin stores `_aiba_*` meta and can output description, canonical, and Open Graph tags when configured.

= Is Google Indexing required? =

No. Disable auto indexing in settings if you do not use the Indexing API.

= Why do I need Pexels or Unsplash keys? =

Unsplash’s old “Source” image URLs are **disabled**. The plugin uses **Pexels** and/or the **official Unsplash API** so featured and in-content images download reliably with proper attribution.

= Where is the full user manual? =

In the plugin directory: **`docs/USER-GUIDE.html`** (recommended) and **`docs/USER-GUIDE.txt`**. Figures live in **`docs/images/`** (SVG diagrams).

= Can I use bulk queue for a whole month? =

Yes. Paste one line per article (tab- or pipe-separated fields). Use **Stagger publishing** (daily or weekly) and a **start date** so `scheduled_at` spreads jobs; time of day follows **Automation → Publish time**.

== Screenshots ==

Illustrations ship as **SVG** files in `docs/images/` (open beside the HTML guide or embed in your intranet):

1. `01-admin-menu.svg` — Where **AI Automator** appears in the admin sidebar.
2. `02-settings-api.svg` — **Settings → API** keys and save/test flow.
3. `03-queue-bulk.svg` — **Bulk queue** line format and stagger options.
4. `04-generate-flow.svg` — **Generate** screen and conceptual pipeline.
5. `05-pipeline.svg` — Queue → processing → LLM → post → SEO/index.

== Upgrade Notice ==

If you previously relied on Unsplash without a Pexels key, add an **Unsplash Access Key** under Settings → API. Review `docs/USER-GUIDE.html` for image and queue behaviour changes in recent releases.

== Changelog ==

= 2.0.13 =
* Envato author pack in `packaging/envato/` (item description HTML, buyer install guide, credits, privacy, support template, screenshot/video scripts, submission + zip build checklists).
* `docs/COMPLIANCE-SELF-AUDIT.md` for pre-release security/i18n checks.
* `languages/ai-blog-automator.pot` starter + `languages/readme.txt` for regenerating translations with WP-CLI.
* Welcome admin notice after activation (per-user dismiss); `Tested up to` raised to 6.8.

= 2.0.12 =
* Admin: new **Upgrade** and **Feedback** submenu pages; hero bar quick links (Upgrade, Feedback, product site) on all AI Automator screens.
* Feedback form stores the last 50 submissions in `aiba_feedback_inbox` and emails the site admin; optional name and reply-to email.
* Marketing: `AIBA_PRODUCT_URL` constant (default https://devigontech.com/ai-blog-automator); front-end shortcode `[aiba_product_demo]` for product/demo pages with scoped CSS.
* Docs: `AIBA_Premium::product_free_highlights()` and premium benefit copy for Upgrade screen and shortcode.

= 2.0.11 =
* Documentation: fixed SVG guide figures that failed to render (invalid XML control characters and corrupted placeholder text in `docs/images/*.svg`); clarified `USER-GUIDE.html` must stay beside `docs/images/`. HTML guide uses `<base href="./">` and `./images/` paths for reliable figure loading.

= 2.0.10 =
* Internal links: every `[INTERNAL_LINK_PLACEHOLDER]` variant (with or without `: anchor`) is replaced with a real `<a>`; invalid LLM rows are skipped; one LLM suggestion pass is reused for placeholders and extra wraps; stray machine tokens are stripped from HTML.

= 2.0.9 =
* Images: Unsplash Source URLs are retired; featured and in-content images now use the official Unsplash API when you add an Access Key (Settings → API), after Pexels. Attachments are parented to the new post.
* SEO: secondary keywords sync to Yoast synonyms (and Yoast Premium related keyphrases when Premium is active), Rank Math `rank_math_additional_keywords`, AIOSEO combined keyword list, and native `_aiba_secondary_keywords`.
* Content: FAQ block is always generated with at least six reader questions (outline padded when needed); closing uses short paragraphs with banned wrap-up wording; headings like “Conclusion” are stripped if present.

= 2.0.8 =
* Queue: bulk paste supports title, focus keyphrase, other keywords, category IDs, and per-line article format (tabs or pipes), optional daily/weekly staggering from a start date; single “Add to queue” saves other keywords and format.
* Generation: prompts enforce keyword coverage, no emojis, no en/em dashes (with HTML cleanup pass), and outbound links to reputable sources; internal link suggestions now include published pages as well as posts.
* Images: Pexels downloads store photographer credit in attachment meta; in-article figures use that caption when present; Unsplash fallback credits the stock search (Google Image scraping is not supported; use Pexels or Unsplash).

= 2.0.7 =
* Fix: New `admin-boot.js` (vanilla JS, no jQuery) powers Settings tabs, Generate post, Queue bulk Apply, select-all checkboxes, dashboard quick actions, and trend picker — works when jQuery is deferred, blocked, or loads late.
* Fix: `aibaAdmin` is localized on `aiba-boot` so AJAX URLs and nonces are always defined before boot runs; `admin.js` now only handles sliders and “Test API connections”.
* Fix: Broader `admin_enqueue_scripts` hook matching for edge-case screen IDs (`_page_aiba-*`).
* UI: Settings card shadow, 16px radius, taller tabs (44px min), form row separators, stronger Save button styling.

= 2.0.6 =
* Generate: fixed activity-log banner missing rows by removing the 14-day SQL cutoff (newest issues always load).
* Generate: one alert at a time with Previous/Next when multiple log rows exist; empty state explains first-time use.
* Generate: primary button uses delegated click + HTML5 validation + `data-gen-nonce` fallback if localized script is stripped; AJAX uses `dataType: "json"` and clearer error extraction; spinner next to the button; progress and result moved inside the form card with refreshed styling.

= 2.0.5 =
* Settings: added a “free API tiers” guidance panel (Auto + multiple keys, word count / posts per day, gpt-4o-mini, Pexels / indexing tips).
* Settings: tabs live in a single card with refreshed styling (accent underline on active tab, softer tab bar); tab JS now resolves the options form via `.aiba-settings-tabs-card`, uses namespaced handlers, syncs on `hashchange`, and supports Arrow Left/Right and Home/End for reliable switching.

= 2.0.4 =
* Generate screen: always show a prominent notice listing recent activity-log errors and warnings for the generation pipeline (LLM, publish, queue, images, etc.) from the last 14 days, with a link to Activity logs.
* Manual Generate AJAX failures are written to the same log and mirrored in a “Last attempt” notice at the top of the page without a full reload.

= 2.0.3 =
* Fix: Admin JavaScript no longer assumes `jQuery` exists as a bare global or that `aibaAdmin` is always defined. Uses `window.jQuery` with an early exit, reads `window.aibaAdmin` with fallbacks for `ajaxurl` / nonces / `admin.php` base URL, wraps settings tab init in try/catch, and matches hash deep-links with `.filter()` so a bad selector cannot abort the whole script (which left Generate, Dashboard, Queue bulk, and other controls dead).

= 2.0.2 =
* Fix: Settings screen tabs — delegate clicks on `.aiba-settings-nav`, toggle the HTML `hidden` property on panels (avoids jQuery `.show()` vs inline `display:none` conflicts), sync `aria-selected`, and support deep-link `#aiba-tab-*` hashes.
* UI: Restyled settings tabs as a flex “pill strip” with clearer active state and a connected form card below.

= 2.0.1 =
* Fix: Generate form used default GET without `page=aiba-generate`, so WordPress loaded a blank `admin.php`. Added hidden `page`, explicit `admin.php` action, inline submit prevention, and a `type="button"` primary control wired to AJAX.
* Prefill Generate fields from the query string when present (after a mistaken GET submit). Always enqueue core `jquery` before the admin script.

= 2.0.0 =
* Rebuilt bootstrap: boot on `plugins_loaded` for normal loads, or immediately when the plugin file is included after `plugins_loaded` (activation sandbox and similar), so hooks always register reliably.
* Added `includes/bootstrap-manifest.php` as the single ordered list of class files loaded by `AIBA_Core::load_full_includes()`.
* Idempotent `AIBA_Core::init()` guard prevents duplicate hook registration if bootstrap runs twice in one request.
* Replaced `match` with `switch` for queue recurrence mapping; relaxed `decode_queue_category_ids()` input handling for bad DB values.
* Version 2.0.0 — recommended full reinstall or upload of this zip if a previous build left the site in recovery mode.

= 1.1.4 =
* Fix: restore a single full bootstrap on `AIBA_Core::init()` so front-end, admin, and cron never run with only a partial class set (which caused critical errors when the queue or trends cron fired).
* Hardening: call `AIBA_Core::load_full_includes()` at the start of queue processing and auto-trends handlers so those hooks are safe even if invoked outside a normal plugin boot.
* Activation remains lean (scheduler + schema + defaults only); the main file still skips `init()` only during `WP_SANDBOX_SCRAPING`.

= 1.1.3 =
* Fix: lean activation path (database, default options, cron schedules only) so activating the plugin no longer loads the full LLM/API stack during WordPress’s activation sandbox.
* Fix: skip `AIBA_Core::init()` while `WP_SANDBOX_SCRAPING` is set to avoid boot hooks during the pre-activation scrape.
* Hardening: `map_queue_frequency_to_recurrence()` accepts non-string option values from the database without throwing a type error.

= 1.1.2 =
* Performance: load a minimal PHP stack on public front-end requests; full stack on admin, cron, CLI, activation sandbox, or when generation APIs run.
* Fix: activation/deactivation hooks accept WordPress multisite arguments; activation no longer fatals on PHP 8+ when the callback receives an extra parameter.

= 1.1.1 =
* Multiple LLM providers: OpenAI, Claude (Anthropic), and custom OpenAI-compatible endpoints; Auto mode chains fallbacks on rate limits.
* Word count 300–5000 with slider; queue schedules every 2h/3h/6h/12h/daily or custom minutes.
* Multiple default categories, queue `category_ids`, bulk keyword queue, AI tag expansion and category suggestions (optional).
* 13+ article format templates and custom prompt prefix/suffix/global append editor.
* Security: empty default Gemini API key on new installs.

= 1.0.0 =
* Initial release.
