<?php

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Session\SessionManager;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * Tests for Collection api.php?action=collection&submodule=suggestundoarticleaction.
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiSuggestUndoArticleAction
 */
class ApiSuggestUndoArticleActionTest extends ApiTestCase {

	public function testApiSuggestUndoArticleAction_UndoAdd() {
		$page = $this->getExistingTestPage();

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'add',
			'title' => $page->getDBkey()
		] );

		$session = SessionManager::getGlobalSession();
		$this->assertArrayContains(
			[ [ 'name' => $page->getTitle()->getPrefixedText() ] ],
			$session['wsCollectionSuggestProp']
		);

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestundoarticleaction',
			'lastaction' => 'add',
			'title' => $page->getDBkey()
		] );

		$session = SessionManager::getGlobalSession();
		$this->assertCount( 0, $session['wsCollectionSuggestProp'] );

		// Undoing an add doesn't ban it ( different from calling suggestremovearticle )
		$this->assertCount( 0, $session['wsCollectionSuggestBan'] );
	}

	public function testApiSuggestUndoArticleAction_UndoRemove() {
		$page = $this->getExistingTestPage();

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'add',
			'title' => $page->getDBkey()
		] );

		$session = SessionManager::getGlobalSession();
		$this->assertArrayContains(
			[ [ 'name' => $page->getTitle()->getPrefixedText() ] ],
			$session['wsCollectionSuggestProp']
		);

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'remove',
			'title' => $page->getDBkey()
		] );
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestundoarticleaction',
			'lastaction' => 'remove',
			'title' => $page->getDBkey()
		] );

		$session = SessionManager::getGlobalSession();
		$this->assertArrayContains(
			[ [ 'name' => $page->getTitle()->getPrefixedText() ] ],
			$session['wsCollectionSuggestProp']
		);

		// Undoing a remove should re-add it
		$this->assertArrayContains(
			[ [ 'title' => $page->getTitle()->getPrefixedText() ] ],
			$session['wsCollection']['items']
		);

		// Undoing a remove should unban it
		$this->assertCount( 0, $session['wsCollectionSuggestBan'] );
	}

	public function testApiSuggestUndoArticleAction_UndoBan() {
		$page = $this->getExistingTestPage();

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'ban',
			'title' => $page->getDBkey()
		] );
		$session = SessionManager::getGlobalSession();
		$this->assertArrayEquals(
			[ $page->getDBkey() ],
			$session['wsCollectionSuggestBan']
		);

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestundoarticleaction',
			'lastaction' => 'ban',
			'title' => $page->getDBkey()
		] );
		$session = SessionManager::getGlobalSession();
		$this->assertCount( 0, $session['wsCollectionSuggestBan'] );
	}

	public function testApiSuggestUndoArticleAction_MissingRequired() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestundoarticleaction'
		] );
	}
}
