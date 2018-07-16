<?php

namespace Liquipedia\ResourceLoaderArticles;

use ContentHandler;
use DatabaseUpdater;
use OutputPage;
use Revision;
use Skin;
use Title;

class ResourceLoaderArticlesModuleHooks {

	public static function onResourceLoaderRegisterModules( $resourceLoader ) {
		global $wgRequest;
		/* @var $request WebRequest */
		if ( $wgRequest->getText( 'mode' ) !== 'articles' ) {
			return true;
		}

		$articles = $wgRequest->getText( 'articles' );
		$articles = explode( '|', $articles );
		if ( empty( $articles ) ) {
			return true;
		}

		$text = '';
		foreach ( $articles as $article ) {
			$title = Title::newFromText( $article );
			if ( !$title ) {
				continue;
			}

			$handler = ContentHandler::getForTitle( $title );
			if ( $handler->isSupportedFormat( CONTENT_FORMAT_CSS ) ) {
				$format = CONTENT_FORMAT_CSS;
			} elseif ( $handler->isSupportedFormat( CONTENT_FORMAT_JAVASCRIPT ) ) {
				$format = CONTENT_FORMAT_JAVASCRIPT;
			} else {
				continue;
			}

			$revision = Revision::newFromTitle( $title, false, Revision::READ_NORMAL );
			if ( !$revision ) {
				continue;
			}

			$content = $revision->getContent( Revision::RAW );

			$text .= $content->getNativeData() . "\n";
		}

		// prepare fake ResourceLoader module metadata
		$moduleName = md5( serialize( [ $articles ] ) . $text );
		$moduleFullName = 'liquipedia.module.articles.' . $moduleName;
		$moduleInfo = [
			'class' => 'Liquipedia\\ResourceLoaderArticles\\ResourceLoaderArticlesModule',
		];

		// register new fake module
		$resourceLoader->register( $moduleFullName, $moduleInfo );

		// reinitialize ResourceLoader context
		$wgRequest->setVal( 'modules', $moduleFullName );
		return true;
	}

	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$dbr = wfGetDB( DB_REPLICA );
		$config = $out->getConfig();
		$scriptPath = substr( $config->get( 'ScriptPath' ), 1 );
		$wikiUrl = $config->get( 'ResourceLoaderArticlesWiki' );
		$scripts = [];
		$addScript = false;
		$styles = [];
		$addStyle = false;
		$res = $dbr->select( 'resourceloaderarticles', '*', [ '`rla_wiki` IN(\'' . $scriptPath . '\', \'all\')' ] );
		while ( $row = $res->fetchObject() ) {
			if ( $row->rla_type === 'script' ) {
				$scripts[] = 'MediaWiki:Common.js/' . $row->rla_page;
				$addScript = true;
			} elseif ( $row->rla_type === 'style' ) {
				$styles[] = 'MediaWiki:Common.css/' . $row->rla_page;
				$addStyle = true;
			}
		}
		if ( !$out->msg( 'liquiflow-css-urls' )->exists() || strpos( $out->msg( 'liquiflow-css-urls' )->plain(), 'CACHEBUST' ) === false ) { // TODO: Remove
			if ( $addScript ) {
				$out->addScriptFile( $wikiUrl . '?articles=' . implode( '|', $scripts ) . '&only=scripts&mode=articles&cacheversion=' . $out->msg( 'resourceloaderarticles-cacheversion' )->text() . '&*' );
			}
			if ( $addStyle ) {
				$out->addStyle( $wikiUrl . '?articles=' . implode( '|', $styles ) . '&only=styles&mode=articles&cacheversion=' . $out->msg( 'resourceloaderarticles-cacheversion' )->text() . '&*' );
			}
		} // TODO: Remove
	}

	/**
	 * Handle database updates
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$db = $updater->getDB();

		if ( !$db->tableExists( 'resourceloaderarticles', __METHOD__ ) ) {
			$updater->output( "Creating resourceloaderarticles table resourceloaderarticles ...\n" );
			$db->sourceFile( __DIR__ . '/sql/resourceloaderarticles.sql' );
			$updater->output( "done.\n" );
		} else {
			$updater->output( "...resourceloaderarticles table already exists (LPDB).\n" );
		}
	}

}
