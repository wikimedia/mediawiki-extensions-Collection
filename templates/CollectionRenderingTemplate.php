<?php
/**
 * @defgroup Templates Templates
 * @file
 * @ingroup Templates
 */

namespace MediaWiki\Extension\Collection\Templates;

use MediaWiki\Extension\Collection\Session;
use MediaWiki\Title\Title;
use QuickTemplate;

/**
 * HTML template for Special:Book/rendering/ (in progress)
 * @ingroup Templates
 */
class CollectionRenderingTemplate extends QuickTemplate {
	public function execute() {
		?>

		<span style="display:none" id="renderingStatusText"><?php echo wfMessage( 'coll-rendering_status', '%PARAM%' )->parse() ?></span>
		<span style="display:none" id="renderingArticle"><?php echo ' ' . wfMessage( 'coll-rendering_article', '%PARAM%' )->parse() ?></span>
		<span style="display:none" id="renderingPage"><?php echo ' ' . wfMessage( 'coll-rendering_page', '%PARAM%' )->parse() ?></span>

		<?php echo wfMessage( 'coll-rendering_text' )
			->numParams( number_format( $this->data['progress'], 2, '.', '' ) )
			->params( $this->data['status'] )->parse() ?>

		<?php
		if ( Session::isEnabled() ) {
			$title_string = wfMessage( 'coll-rendering_collection_info_text_article' )->inContentLanguage()->text();
		} else {
			$title_string = wfMessage( 'coll-rendering_page_info_text_article' )->inContentLanguage()->text();
		}
		$t = Title::newFromText( $title_string );
		if ( $t && $t->exists() ) {
			echo $GLOBALS['wgOut']->parseAsContent( '{{:' . $t . '}}' );
		}
	}
}
