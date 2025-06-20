<?php

namespace MediaWiki\Extension\Collection\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiModuleManager;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API parent module.
 * Each operation is implemented as a submodule. This module just performs some
 * basic checks and dispatches to execute() call.
 */
class ApiCollection extends ApiBase {
	/** Module name => module class */
	private const SUBMODULES = [
		'addarticle' => ApiAddArticle::class,
		'addcategory' => ApiAddCategory::class,
		'addchapter' => ApiAddChapter::class,
		'clearcollection' => ApiClearCollection::class,
		'getcollection' => ApiGetCollection::class,
		'getbookcreatorboxcontent' => ApiGetBookCreatorBoxContent::class,
		'getpopupdata' => [
			'class' => ApiGetPopupData::class,
			'services' => [
				'TitleFactory',
				'WikiPageFactory',
			]
		],
		'postcollection' => [
			'class' => ApiPostCollection::class,
			'services' => [
				'UrlUtils',
			],
		],
		'removearticle' => ApiRemoveArticle::class,
		'removeitem' => ApiRemoveItem::class,
		'renamechapter' => ApiRenameChapter::class,
		'setsorting' => ApiSetSorting::class,
		'settitles' => ApiSetTitles::class,
		'sortitems' => ApiSortItems::class,
		'suggestarticleaction' => ApiSuggestArticleAction::class,
		'suggestundoarticleaction' => ApiSuggestUndoArticleAction::class,
	];

	private ApiModuleManager $moduleManager;

	public function __construct(
		ApiMain $main,
		string $action
	) {
		parent::__construct( $main, $action );

		$this->moduleManager = new ApiModuleManager(
			$this,
			MediaWikiServices::getInstance()->getObjectFactory()
		);
		$this->moduleManager->addModules( self::SUBMODULES, 'submodule' );
	}

	/**
	 * Entry point for executing the module
	 * @inheritDoc
	 */
	public function execute() {
		$submodule = $this->getParameter( 'submodule' );
		$module = $this->getModuleManager()->getModule( $submodule, 'submodule' );

		if ( $module === null ) {
			$this->dieWithError( 'apihelp-no-such-module' );
		}

		$module->extractRequestParams();
		$module->execute();
	}

	/**
	 * @inheritDoc
	 */
	public function getModuleManager(): ApiModuleManager {
		return $this->moduleManager;
	}

	/** @inheritDoc */
	protected function getAllowedParams(): array {
		return [
			'submodule' => [
				ParamValidator::PARAM_TYPE => 'submodule',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function getExamplesMessages(): array {
		return [
			'action=collection&submodule=getcollection' => 'apihelp-collection-param-submodule',
		];
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

}
