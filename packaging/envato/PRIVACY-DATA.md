# Privacy & data — AI Blog Automator

## Summary

The plugin **does not** sell end-user data. It **does** send content you choose (topics, keywords, outlines, article text) to **LLM providers** you configure, and may download images from **Pexels/Unsplash** when keys are set. Optional **Google Indexing API** sends URLs you publish.

## Stored on the WordPress site

- **Options** (`aiba_*`): API keys and settings (same as most plugins — protect your database backups).
- **Tables** `wp_aiba_logs`, `wp_aiba_queue`: activity and queue jobs.
- **Post meta** (`_aiba_*`, SEO plugin meta): generated SEO fields when publishing.
- **Feedback** (optional): last submissions in `aiba_feedback_inbox` option when admins use the Feedback screen.

## Sent to third parties

| Data / action              | When                          |
|----------------------------|-------------------------------|
| Prompts & article text     | Each LLM generation call      |
| Image search queries       | Pexels/Unsplash image fetch   |
| Image binary               | Downloaded to Media Library   |
| URL to index               | When Indexing API is enabled  |

## Site owner responsibilities

- Comply with GDPR / local law for AI-generated content, disclosures, and cookies on the **front-end site**.
- Review AI output for accuracy and policy before publishing.
- Rotate API keys if leaked.

## No telemetry

This build does **not** include hidden analytics or “phone home” licensing pings. Premium unlock uses a **local** option after code entry.
