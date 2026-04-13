This directory holds translations for AI Blog Automator.

Regenerate the template (.pot) from the project root with WP-CLI:

  wp i18n make-pot . languages/ai-blog-automator.pot --domain=ai-blog-automator --exclude=node_modules,vendor,.git

Or use the official @wordpress/create-block / @wordpress/i18n tooling if you prefer npm.

The shipped ai-blog-automator.pot is a starter; run the command above for a complete string catalog before claiming “100% translation ready” on a marketplace.
