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
 * HTML template for Special:Book/rendering/ (failed)
 * @ingroup Templates
 */
class CollectionFailedTemplate extends QuickTemplate {
	public function execute() {
		$skin = $this->getSkin();
		echo $skin->msg( 'coll-rendering_failed_text', $this->data['status'] )->parseAsBlock();

		$t = Title::newFromText( $this->data['return_to'] );
		if ( $t && $t->isKnown() ) {
			echo $skin->msg( 'coll-return_to', $t->getPrefixedText() )->parseAsBlock();
		}

		if ( Session::isEnabled() ) {
			$title_string = $skin->msg( 'coll-failed_collection_info_text_article' )->inContentLanguage()->text();
		} else {
			$title_string = $skin->msg( 'coll-failed_page_info_text_article' )->inContentLanguage()->text();
		}
		$t = Title::newFromText( $title_string );
		if ( $t && $t->exists() ) {
			echo $skin->getOutput()->parseAsContent( '{{:' . $t->getPrefixedText() . '}}' );
		}
		?>

		<?php
	}
}
