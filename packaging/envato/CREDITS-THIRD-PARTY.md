# Third-party credits — AI Blog Automator

## WordPress

- Built for **WordPress** (GPLv2+). Uses core APIs: REST/AJAX patterns, Options API, `wp_remote_*`, media sideload, cron, block editor serialization.

## PHP / WordPress libraries

- No Composer vendor bundle shipped. Uses PHP extensions when available (e.g. `mbstring`, `DOMDocument` for HTML parsing).

## External services (user-configured)

Buyers supply their own keys. Traffic goes to:

| Service            | Purpose                          | Privacy note                                      |
|--------------------|----------------------------------|---------------------------------------------------|
| Google Gemini      | LLM text generation              | Prompts/content sent per Google terms           |
| OpenAI             | Optional LLM                     | Per OpenAI policy                                 |
| Anthropic          | Optional LLM                     | Per Anthropic policy                              |
| User “custom” URL  | OpenAI-compatible API            | Endpoints chosen by buyer                         |
| Pexels             | Stock images                     | Search + download; attribution where required     |
| Unsplash           | Stock images                     | Official API per Unsplash terms                   |
| Google Indexing API| URL notification (optional)      | Service account; Google’s terms                   |

## Admin UI

- **Dashicons** (WordPress bundled icon font).
- Admin CSS/JS authored for this plugin (no bundled third-party UI framework).

## Fonts (front-end shortcode)

- System font stack: `system-ui`, `-apple-system`, `Segoe UI`, `Roboto`, sans-serif (no embedded webfont files).

## Legal

- Plugin license: **GPLv2 or later** (see `LICENSE` in the plugin root).
- Envato split licensing applies to the **distribution** on CodeCanyon per Envato’s terms; PHP remains GPL-compatible.

## Trademarks

“WordPress”, “Yoast”, “Rank Math”, “All in One SEO”, “Google”, “OpenAI”, “Anthropic”, “Pexels”, “Unsplash” are trademarks of their respective owners. This plugin is not endorsed by them.
