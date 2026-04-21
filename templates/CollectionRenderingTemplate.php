<?php
/**
 * @defgroup Templates Templates
 * @file
 * @ingroup Templates
 */

namespace MediaWiki\Extension\Collection\Templates;

use MediaWiki\Extension\Collection\Session;
use MediaWiki\Skin\QuickTemplate;
use MediaWiki\Title\Title;

/**
 * HTML template for Special:Book/rendering/ (in progress)
 * @ingroup Templates
 */
class CollectionRenderingTemplate extends QuickTemplate {
	public function execute() {
		$skin = $this->getSkin();
		?>

		<span style="display:none" id="renderingStatusText"><?php echo $skin->msg( 'coll-rendering_status', '%PARAM%' )->parse() ?></span>
		<span style="display:none" id="renderingArticle"><?php echo ' ' . $skin->msg( 'coll-rendering_article', '%PARAM%' )->parse() ?></span>
		<span style="display:none" id="renderingPage"><?php echo ' ' . $skin->msg( 'coll-rendering_page', '%PARAM%' )->parse() ?></span>

		<?php echo $skin->msg( 'coll-rendering_text' )
			->numParams( number_format( $this->data['progress'], 2, '.', '' ) )
			->params( $this->data['status'] )->parse() ?>

		<?php
		if ( Session::isEnabled() ) {
			$title_string = $skin->msg( 'coll-rendering_collection_info_text_article' )->inContentLanguage()->text();
		} else {
			$title_string = $skin->msg( 'coll-rendering_page_info_text_article' )->inContentLanguage()->text();
		}
		$t = Title::newFromText( $title_string );
		if ( $t && $t->exists() ) {
			echo $skin->getOutput()->parseAsContent( '{{:' . $t . '}}' );
		}
	}
}
