<?php
/**
 * Convert generated HTML into Gutenberg block markup for easier editing.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block serializer for the block editor.
 */
class AIBA_Block_Content {

	/**
	 * Wrap classic HTML fragments in core block comments so the editor shows separate blocks.
	 *
	 * @param string $html Raw HTML from the LLM pipeline.
	 * @return string Block-serialized content or original HTML on failure.
	 */
	public static function html_to_blocks( string $html ): string {
		$html = trim( $html );
		if ( '' === $html || ! class_exists( 'DOMDocument' ) ) {
			return $html;
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$wrap = '<?xml encoding="UTF-8"?><html><body><div id="aiba-root">' . $html . '</div></body></html>';
		$ok   = @$dom->loadHTML( $wrap, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		if ( ! $ok ) {
			return $html;
		}
		$xpath = new DOMXPath( $dom );
		$roots = $xpath->query( '//div[@id="aiba-root"]' );
		if ( ! $roots || ! $roots->length ) {
			return $html;
		}
		/** @var DOMElement $root */
		$root = $roots->item( 0 );
		$out  = array();
		foreach ( $root->childNodes as $child ) {
			$piece = self::node_to_block( $child, $dom );
			if ( $piece !== '' ) {
				$out[] = $piece;
			}
		}
		$joined = implode( "\n\n", $out );
		return $joined !== '' ? $joined : $html;
	}

	private static function node_to_block( DOMNode $node, DOMDocument $dom ): string {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			$t = trim( $node->textContent ?? '' );
			return '' === $t ? '' : self::paragraph_block( '<p>' . esc_html( $t ) . '</p>' );
		}
		if ( ! $node instanceof DOMElement ) {
			return '';
		}
		$name = strtolower( $node->nodeName );
		if ( preg_match( '/^h([1-6])$/', $name, $m ) ) {
			$level = (int) $m[1];
			$level = max( 1, min( 6, $level ) );
			$inner = self::inner_html( $node, $dom );
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				return '';
			}
			$inner = wp_kses_post( $inner );
			return sprintf(
				"<!-- wp:heading {\"level\":%d} -->\n<h%d class=\"wp-block-heading\">%s</h%d>\n<!-- /wp:heading -->",
				$level,
				$level,
				$inner,
				$level
			);
		}
		if ( 'p' === $name ) {
			return self::paragraph_block( $dom->saveHTML( $node ) );
		}
		if ( in_array( $name, array( 'ul', 'ol', 'figure', 'div', 'blockquote', 'table' ), true ) ) {
			return self::html_block( $dom->saveHTML( $node ) );
		}
		return self::html_block( $dom->saveHTML( $node ) );
	}

	private static function paragraph_block( string $p_html ): string {
		$p_html = trim( $p_html );
		if ( '' === $p_html ) {
			return '';
		}
		return "<!-- wp:paragraph -->\n" . wp_kses_post( $p_html ) . "\n<!-- /wp:paragraph -->";
	}

	private static function html_block( string $inner ): string {
		$inner = trim( $inner );
		if ( '' === $inner ) {
			return '';
		}
		return "<!-- wp:html -->\n" . wp_kses_post( $inner ) . "\n<!-- /wp:html -->";
	}

	private static function inner_html( DOMElement $el, DOMDocument $dom ): string {
		$html = '';
		foreach ( $el->childNodes as $c ) {
			$html .= $dom->saveHTML( $c );
		}
		return $html;
	}
}
