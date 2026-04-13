# Build the CodeCanyon zip

## What buyers must receive

A zip that expands to a single folder:

```
ai-blog-automator/
  ai-blog-automator.php
  readme.txt
  includes/
  assets/
  docs/
  templates/
  packaging/   ← optional: reviewers see author docs; buyers get extra help. Remove if you want a slimmer zip.
  languages/
  uninstall.php
  LICENSE
```

Some authors **delete** `packaging/` from the buyer zip and keep it only for their own records. **Your choice.**

## Windows (PowerShell)

From the folder **containing** the plugin folder:

```powershell
Compress-Archive -Path ".\ai-blog-automator" -DestinationPath ".\ai-blog-automator.zip" -Force
```

Ensure you compress the **folder**, not a loose pile of files.

## macOS / Linux

```bash
cd /path/to/parent
zip -r ai-blog-automator.zip ai-blog-automator -x "*.git*" -x "*node_modules*"
```

## Verify before upload

1. Unzip to a temp folder.
2. Upload to a **staging** WordPress.
3. Activate and run **Generate** once.
