<?php

use MediaWiki\Session\SessionManager;

/**
 * Tests for Collection api.php?action=collection&submodule=suggestarticleaction.
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiSuggestArticleAction
 */
class ApiSuggestArticleActionTest extends ApiTestCase {

	public function testApiSuggestArticleAction_Add() {
		$page = $this->getExistingTestPage( 'SuggestAddArticlePage1' );
		$page2 = $this->getExistingTestPage( 'SuggestAddArticlePage2' );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'add',
			'title' => $page->getDBkey()
		] );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'add',
			'title' => $page2->getDBkey()
		] );

		$session = SessionManager::getGlobalSession();

		$this->assertArraySubmapSame(
			[
				[ 'name' => $page->getDBkey() ],
				[ 'name' => $page2->getDBkey() ]
			],
			$session['wsCollectionSuggestProp']
		);

		$this->assertArraySubmapSame(
			[
				[ 'title' => 'SuggestAddArticlePage1' ],
				[ 'title' => 'SuggestAddArticlePage2' ]
			],
			$session['wsCollection']['items']
		);
	}

	public function testApiSuggestArticleAction_Remove() {
		$page = $this->getExistingTestPage();

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'add',
			'title' => $page->getDBkey()
		] );

		$session = SessionManager::getGlobalSession();

		$this->assertArraySubmapSame(
			[ [ 'name' => $page->getDBkey() ] ],
			$session['wsCollectionSuggestProp']
		);

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'remove',
			'title' => $page->getDBkey()
		] );

		$this->assertCount( 0, $session['wsCollectionSuggestProp'] );

		// Removing an article automatically bans it
		$this->assertArrayEquals(
			[ $page->getDBkey() ],
			$session['wsCollectionSuggestBan']
		);
	}

	public function testApiSuggestArticleAction_Ban() {
		$page = $this->getExistingTestPage();

		CollectionSession::startSession();
		$session = SessionManager::getGlobalSession();

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'ban',
			'title' => $page->getDBkey()
		] );

		$this->assertArrayEquals(
			[ $page->getDBkey() ],
			$session['wsCollectionSuggestBan']
		);

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction',
			'suggestaction' => 'add',
			'title' => $page->getDBkey()
		] );

		// Adding an article automatically unbans it
		$this->assertCount( 0, $session['wsCollectionSuggestBan'] );
		$this->assertArraySubmapSame(
			[ [ 'name' => $page->getDBkey() ] ],
			$session['wsCollectionSuggestProp']
		);
	}

	public function testApiSuggestArticleAction_MissingRequired() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'suggestarticleaction'
		] );
	}
}
