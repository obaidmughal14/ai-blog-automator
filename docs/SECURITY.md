# Security model — AI Blog Automator

This document is for **site owners**, **reviewers** (Envato, enterprise), and **developers** extending the plugin.

## Threat model (summary)

The plugin is **admin-only** for configuration and generation. It stores **API keys and service account material** in the WordPress options table (same trust boundary as the rest of wp-admin). It sends **article prompts and metadata** to third-party LLM APIs and may fetch **stock images** from Pexels/Unsplash. It does **not** expose generation to unauthenticated visitors.

## Capabilities and authentication

- **Menus, settings, queue, logs, generate UI, upgrade, feedback:** require `manage_options` (default WordPress administrator).
- **AJAX actions** (`wp_ajax_*`): `check_ajax_referer` with action-specific nonces plus `current_user_can( 'manage_options' )` where applicable.
- **Admin-post handlers:** `check_admin_referer` and capability checks.

## Secrets and options

- API keys (Gemini, OpenAI, Anthropic, Pexels, Unsplash, custom LLM) and optional **Google Indexing** service account JSON are stored via the WordPress Options API (`update_option`). Treat backups and database exports as **sensitive**.
- **Recommendation:** use a dedicated API key per site; rotate keys if a site is compromised.

## Outbound HTTP

- All outbound calls use the **WordPress HTTP API** (`wp_remote_get` / `wp_remote_post`), not ad-hoc sockets.
- **Timeouts:** known LLM and image hosts receive a higher minimum timeout via `http_request_args` to reduce spurious failures. Filter: `aiba_outbound_api_timeout_seconds` (clamped 30–300 seconds after filtering).
- **Custom LLM URL:** only the host configured in settings is matched for extended timeouts; the URL itself must be a valid endpoint the administrator configures.

## Rate limiting (abuse and overload)

- **Generate (AJAX):** sliding-window limit per user ID. Defaults: 6 requests per 120 seconds. Filter: `aiba_generate_rate_limit` → array `max` (1–30), `window` (30–3600 seconds).
- **Feedback:** 8 submissions per hour per administrator (stored locally and optionally emailed to `admin_email`).

## Input sanitization

- User and form input use `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_key`, `sanitize_email`, `intval`, `wp_unslash` as appropriate before use or storage.
- Database writes for queue and logs use `$wpdb->prepare` for dynamic SQL.

## Output escaping

- Admin templates use `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` consistent with WordPress admin patterns.

## Generated content

- Generated HTML is intended for trusted editors; publishing uses WordPress post APIs. Follow your site’s **content security** policy for what you allow editors to publish.

## Public marketing shortcode (`[aiba_product_demo]`)

- The **sandbox “Run demo”** block is **entirely client-side**: topic and keyword stay in the visitor’s browser; **no** request is sent to WordPress or to LLM providers for that simulation. Disable it with `show_sandbox="no"` if you do not want that UI.

## Logging

- Activity entries may contain error messages from providers (no deliberate logging of full API key values). Restrict log access to administrators.

## Filters (extension points)

| Filter | Purpose |
|--------|---------|
| `aiba_outbound_api_timeout_seconds` | Minimum timeout for matched outbound API hosts. |
| `aiba_generate_rate_limit` | `{ max, window }` for Generate AJAX. |
| `aiba_environment_requirement_issues` | Add or adjust requirement notices (PHP extensions, version). |
| `aiba_generate_max_execution_seconds` | PHP `set_time_limit` budget for Generate. |

## Reporting issues

Use the in-plugin **Feedback** page (administrators) or your vendor support channel. For **security vulnerabilities**, contact the author privately and avoid posting exploit details in public forums until patched.

## Compliance checklist

See `docs/COMPLIANCE-SELF-AUDIT.md` before each release.
