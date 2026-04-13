=== AI Blog Automator ===
Contributors: yourname
Tags: ai, blog, gemini, seo, automation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.0.4
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
