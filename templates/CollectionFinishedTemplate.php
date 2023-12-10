<?php
/**
 * @defgroup Templates Templates
 * @file
 * @ingroup Templates
 */

namespace MediaWiki\Extension\Collection\Templates;

use MediaWiki\Extension\Collection\Session;
use MediaWiki\Html\Html;
use MediaWiki\Title\Title;
use QuickTemplate;
use SkinTemplate;

/**
 * HTML template for Special:Book/rendering/ (finished)
 * @ingroup Templates
 */
class CollectionFinishedTemplate extends QuickTemplate {
	public function execute() {
		global $wgCollectionShowRenderNotes;

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
				$noteMessage = wfMessage( 'coll-rendering_finished_note_article_rdf2latex', $tt );
			} else {
				$noteMessage = wfMessage( $noteKey );
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

		echo wfMessage( 'coll-rendering_finished_text', $this->data['download_url'] )->parseAsBlock();

		if ( $notes !== '' || $this->data['is_cached'] ) {
			echo wfMessage( 'coll-rendering_finished_notes_heading' )->parseAsBlock();
		}

		if ( $notes !== '' ) {
			echo Html::rawElement( 'ul', [], $notes );
		}

		if ( $this->data['is_cached'] ) {
			$forceRenderURL = SkinTemplate::makeSpecialUrl(
				'Book',
				'bookcmd=forcerender&' . $this->data['query'],
				PROTO_RELATIVE
			);
			echo wfMessage( 'coll-is_cached', $forceRenderURL )->parseAsBlock();
		}
		if ( $t && $t->isKnown() ) {
			echo wfMessage( 'coll-return_to', $t )->parseAsBlock();
		}

		if ( Session::isEnabled() ) {
			$title_string = wfMessage( 'coll-finished_collection_info_text_article' )->inContentLanguage()->text();
		} else {
			$title_string = wfMessage( 'coll-finished_page_info_text_article' )->inContentLanguage()->text();
		}
		$t = Title::newFromText( $title_string );
		if ( $t && $t->exists() ) {
			echo $GLOBALS['wgOut']->parseAsContent( '{{:' . $t . '}}' );
		}
		?>

		<?php
	}
}
