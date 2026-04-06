=== AI Blog Automator ===
Contributors: yourname
Tags: ai, blog, gemini, seo, automation
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.1.1
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

= 1.1.1 =
* Multiple LLM providers: OpenAI, Claude (Anthropic), and custom OpenAI-compatible endpoints; Auto mode chains fallbacks on rate limits.
* Word count 300–5000 with slider; queue schedules every 2h/3h/6h/12h/daily or custom minutes.
* Multiple default categories, queue `category_ids`, bulk keyword queue, AI tag expansion and category suggestions (optional).
* 13+ article format templates and custom prompt prefix/suffix/global append editor.
* Security: empty default Gemini API key on new installs.

= 1.0.0 =
* Initial release.
