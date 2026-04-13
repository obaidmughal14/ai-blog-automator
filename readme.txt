=== AI Blog Automator ===
Contributors: yourname
Tags: ai, blog, gemini, seo, automation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.0.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automates blog writing, SEO meta, images, internal linking, and optional Google Indexing API notifications using Google Gemini.

== Description ==

AI Blog Automator generates long-form posts with outlines, FAQ blocks, featured and in-content images (Unsplash / Pexels), internal links suggested via Gemini, and JSON-LD Article/FAQ schema. Optional Google Search Console Indexing API submission uses a service account JSON key.

== Installation ==

1. Upload the `ai-blog-automator` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Open **AI Automator → Settings**, add your Gemini API key, and configure niche, author, and automation options.

== Frequently Asked Questions ==

= Does this work without Yoast or Rank Math? =

Yes. The plugin stores `_aiba_*` meta and outputs description/canonical/OG tags when no supported SEO plugin is active.

= Is Google Indexing required? =

No. Disable **Auto Google indexing** in settings if you do not use the Indexing API.

== Changelog ==

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
* Fix: restore a single full bootstrap on `AIBA_Core::init()` so front-end, admin, and cron never run with only a partial class set (this caused critical errors when the queue or trends cron fired).
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
