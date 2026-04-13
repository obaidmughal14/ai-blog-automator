# Changelog (buyer-facing)

## 2.0.15

- Longer **HTTP timeouts** for known LLM and image API hosts (fewer cURL 28 timeouts on slow providers).
- **Admin notices** if recommended PHP extensions (cURL, DOM, mbstring) are missing; PHP 8.0+ is enforced in code paths that load the full stack.
- **Rate limits** on manual Generate and on Feedback (per administrator) to reduce accidental overload and abuse.
- **Generate** validates required fields and wraps the pipeline in a safety net so rare PHP errors surface as a logged message instead of breaking the AJAX response.

## 2.0.14

- Clearer **Generate** errors when the server returns HTML or times out; longer default PHP time budget for generation.

## 2.0.13

- **Author pack** in `packaging/envato/`: listing HTML, installation, credits, privacy, support template, screenshot + video scripts, submission + zip checklists.
- **Welcome notice** after activation (dismissible per admin user).
- **Compliance self-audit** doc in `docs/COMPLIANCE-SELF-AUDIT.md`.
- **Translation starter** `languages/ai-blog-automator.pot` + instructions in `languages/readme.txt`.
- **Plugin icon** `assets/images/icon-256.png` for listings / wp.org style branding.
- Declared **Tested up to WordPress 6.8** in plugin header and readme.

## 2.0.12

- Admin **Upgrade** and **Feedback** pages; hero quick links; `[aiba_product_demo]` shortcode; `AIBA_PRODUCT_URL` constant.

## 2.0.11

- Documentation SVG fixes; Gutenberg block serialization; SEO polish; image caption links.

_(Older versions: see `readme.txt` in the plugin root.)_
