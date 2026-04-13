# Installation & first run — AI Blog Automator

## 1. Install

1. Download the zip from CodeCanyon.
2. WordPress admin → **Plugins → Add New → Upload Plugin**.
3. Choose the zip → **Install Now** → **Activate**.

## 2. Permissions

Only users with the **`manage_options`** capability (typically Administrators) can access **AI Automator** screens.

## 3. API keys (required for generation)

1. Go to **AI Automator → Settings → API**.
2. Add at least one of:
   - **Google Gemini API key** (recommended default), and/or  
   - **OpenAI API key**, **Anthropic API key**, or **Custom LLM** base URL + key.
3. Optional: **Pexels** and/or **Unsplash** keys for stock images.
4. Optional: **Google service account JSON** (Indexing API).
5. Click **Save**, then **Test API connections**.

## 4. First article

- **AI Automator → Generate now** — fill topic and primary keyword → submit → edit the draft when ready.
- Or **Queue** — add one job or bulk lines, then rely on cron / “Process queue” from the dashboard.

## 5. SEO plugin

**Settings → SEO**: choose Yoast, Rank Math, AIOSEO, Native, or **Auto** (detects installed SEO plugin).

## 6. Documentation

Open in a browser (from the plugin folder on the server or locally from the zip):

- `wp-content/plugins/ai-blog-automator/docs/USER-GUIDE.html`

Plain text variant: `docs/USER-GUIDE.txt`.

## 7. Premium unlock (optional)

If you purchased premium access:

1. **AI Automator → Settings** — scroll to **Unlock premium**.
2. Paste your **access code** → **Unlock premium**.

Details and purchase link: **AI Automator → Upgrade**.

## 8. Uninstall

To remove all plugin data on uninstall, enable **Settings → Advanced → Delete all plugin data on uninstall**, then deactivate and delete the plugin from **Plugins**.

## 9. Troubleshooting

- **Blank or failed generation** — verify API keys and provider quotas; check **Activity logs**.
- **No images** — add Pexels and/or Unsplash keys; Unsplash “Source” URLs are not used.
- **SEO fields empty** — confirm SEO mode and that the SEO plugin allows meta updates for your role.

For hosting limits (max execution time, `open_basedir`, outbound HTTPS), contact your host.
