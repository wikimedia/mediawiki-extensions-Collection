<?php
/**
 * @defgroup Templates Templates
 * @file
 * @ingroup Templates
 */

namespace MediaWiki\Extension\Collection\Templates;

use MediaWiki\Extension\Collection\Session;
use MediaWiki\Html\Html;
use MediaWiki\Skin\Components\SkinComponentUtils;
use MediaWiki\Skin\QuickTemplate;
use MediaWiki\Title\Title;

/**
 * HTML template for Special:Book/rendering/ (finished)
 * @ingroup Templates
 */
class CollectionFinishedTemplate extends QuickTemplate {
	public function execute() {
		global $wgCollectionShowRenderNotes;

		$skin = $this->getSkin();
		$t = Title::newFromText( $this->data['return_to'] );

		$notes = '';
		foreach ( $wgCollectionShowRenderNotes as $noteKey ) {
			if ( $noteKey === 'coll-rendering_finished_note_article_rdf2latex' ) {
				// Show a note specific to the rdf2latex when rendering an article
				if ( $this->data['writer'] !== 'rdf2latex' || ( $t && $t->isSpecialPage() ) ) {
					continue;
				}
				$tt = '{{int:printableversion}}';
				if ( $t && $t->isKnown() ) {
					# Direct link to printable version; only valid for single articles.
					$tt = '[' . $t->getFullURL( [ 'printable' => 'yes' ] ) . " $tt]";
				}
				$noteMessage = $skin->msg( 'coll-rendering_finished_note_article_rdf2latex', $tt );
			} else {
				$noteMessage = $skin->msg( $noteKey );
			}

			if ( $noteMessage->exists() ) {
				$notes .= Html::rawElement(
					'li',
					[],
					$noteMessage->parseAsBlock()
				);
			} else {
				wfDebugLog( 'collection', 'Note message key not found: ' . $noteKey );
			}
		}

		echo $skin->msg( 'coll-rendering_finished_text', $this->data['download_url'] )->parseAsBlock();

		if ( $notes !== '' || $this->data['is_cached'] ) {
			echo $skin->msg( 'coll-rendering_finished_notes_heading' )->parseAsBlock();
		}

		if ( $notes !== '' ) {
			echo Html::rawElement( 'ul', [], $notes );
		}

		if ( $this->data['is_cached'] ) {
			$forceRenderURL = SkinComponentUtils::makeSpecialUrl(
				'Book',
				'bookcmd=forcerender&' . $this->data['query'],
				PROTO_RELATIVE
			);
			echo $skin->msg( 'coll-is_cached', $forceRenderURL )->parseAsBlock();
		}
		if ( $t && $t->isKnown() ) {
			echo $skin->msg( 'coll-return_to', $t->getPrefixedText() )->parseAsBlock();
		}

		if ( Session::isEnabled() ) {
			$title_string = $skin->msg( 'coll-finished_collection_info_text_article' )->inContentLanguage()->text();
		} else {
			$title_string = $skin->msg( 'coll-finished_page_info_text_article' )->inContentLanguage()->text();
		}
		$t = Title::newFromText( $title_string );
		if ( $t && $t->exists() ) {
			echo $skin->getOutput()->parseAsContent( '{{:' . $t->getPrefixedText() . '}}' );
		}
		?>

		<?php
	}
}
