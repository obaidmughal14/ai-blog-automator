# AI Blog Automator

WordPress plugin: AI-assisted long-form articles, queue automation, SEO meta, stock images, internal linking, FAQ/schema, and optional Google Indexing API.

## Requirements

- WordPress **6.0+**
- PHP **8.0+**
- At least one LLM API key (Gemini by default; optional OpenAI, Anthropic, custom OpenAI-compatible endpoint)

## Install

1. Copy the plugin folder into `wp-content/plugins/`.
2. Activate **AI Blog Automator** in **Plugins**.
3. Open **AI Automator → Settings → API**, add keys, save, and use **Test API connections**.

## Public demo shortcode

`[aiba_product_demo]` renders features plus a **walkthrough**, a **browser-saved checklist**, a **sandbox “generate” simulator** (animated timing only; no API or post), and **wp-admin shortcut links** for logged-in administrators. Optional attributes: `show_walkthrough`, `show_checklist`, `show_sandbox`, `show_admin_links`, `show_shortcode_hint`, `title`.

## Documentation (in the zip)

| Path | Description |
|------|-------------|
| `docs/USER-GUIDE.html` | Full styled administrator guide |
| `docs/USER-GUIDE.txt` | Plain-text guide |
| `docs/SECURITY.md` | Security model, rate limits, filters |
| `docs/COMPLIANCE-SELF-AUDIT.md` | Pre-release checklist |
| `packaging/envato/` | CodeCanyon / Envato author pack |

## Development

- Main bootstrap: `ai-blog-automator.php` → `includes/class-core.php`
- Class load order: `includes/bootstrap-manifest.php`

## License

GPL v2 or later — see `LICENSE` and plugin headers.
