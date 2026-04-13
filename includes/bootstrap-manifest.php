<?php
/**
 * Ordered list of class files for AI Blog Automator (single load path).
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

return array(
	'class-gemini-api.php',
	'class-openai-api.php',
	'class-anthropic-api.php',
	'class-custom-llm-api.php',
	'class-llm-templates.php',
	'class-llm-client.php',
	'class-premium.php',
	'class-trend-fetcher.php',
	'class-content-generator.php',
	'class-seo-handler.php',
	'class-image-handler.php',
	'class-internal-linker.php',
	'class-post-publisher.php',
	'class-google-indexing.php',
	'class-scheduler.php',
	'class-admin-ui.php',
);
