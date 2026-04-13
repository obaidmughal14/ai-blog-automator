# Self-audit — security & marketplace hygiene

Run this before each Envato / major release.

## Capabilities

- [ ] All admin menu callbacks check `current_user_can( 'manage_options' )` (or stricter if you change it).
- [ ] AJAX actions use `check_ajax_referer` / nonces and capability checks.

## Input / output

- [ ] `$_POST` / `$_GET` sanitized; SQL uses `$wpdb->prepare` where applicable.
- [ ] Echo in templates uses `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` as appropriate.

## External HTTP

- [ ] Only `wp_remote_get` / `wp_remote_post` (or WP HTTP API), not raw `curl` to user-supplied hosts without validation.
- [ ] Custom LLM URL: ensure only `http`/`https` schemes if you add stricter validation later.
- [ ] `docs/SECURITY.md` matches current behaviour (timeouts, rate limits, filters).

## Assets

- [ ] Script/style versions use `AIBA_VERSION` for cache busting.

## Privacy

- [ ] `PRIVACY-DATA.md` (author pack) still accurate if you add new APIs.

## i18n

- [ ] New user-visible strings wrapped in `__()` / `esc_html__()` with text domain `ai-blog-automator`.
- [ ] Regenerate `.pot` when strings change (see `languages/readme.txt`).

## Envato

- [ ] `ITEM-DESCRIPTION.html` matches actual features.
- [ ] Screenshots updated if UI changed.
