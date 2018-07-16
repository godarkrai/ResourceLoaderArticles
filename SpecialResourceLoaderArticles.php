<?php

namespace Liquipedia\ResourceLoaderArticles;

use HTMLForm;
use Title;

class SpecialResourceLoaderArticles extends \SpecialPage {

	function __construct() {
		parent::__construct( 'ResourceLoaderArticles', 'adminresourceloaderarticles' );
	}

	function getGroupName() {
		return 'liquipedia';
	}

	function isListed() {
		return false;
	}

	function execute( $par ) {
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}
		$this->setHeaders();
		$output = $this->getOutput();
		$params = explode( '/', $par );

		$output->addWikiText( '[[Special:ResourceLoaderArticles|' . $this->msg( 'resourceloaderarticles-show-list' )->text() . ']]' );
		$output->addWikiText( '[[Special:ResourceLoaderArticles/add|' . $this->msg( 'resourceloaderarticles-add-page' )->text() . ']]' );

		if ( $params[ 0 ] === 'add' ) {
			$this->addPage();
		} elseif ( $params[ 0 ] === 'edit' && count( $params ) === 2 ) {
			$this->editPage( intval( $params[ 1 ] ) );
		} elseif ( $params[ 0 ] === 'delete' && count( $params ) === 2 ) {
			$this->deletePage( intval( $params[ 1 ] ) );
		} else {
			$this->listPages();
		}
	}

	private function listPages() {
		$output = $this->getOutput();
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'resourceloaderarticles', '*', [], __METHOD__, [ 'ORDER BY' => 'rla_type ASC, rla_page ASC, rla_wiki ASC' ] );
		$output->addHTML( '<div class="table-responsive"><table class="wikitable">' );
		$output->addHTML( '<tr><th>#</th><th>' . $this->msg( 'resourceloaderarticles-page' )->text() . '</th><th>' . $this->msg( 'resourceloaderarticles-wiki' )->text() . '</th><th>' . $this->msg( 'resourceloaderarticles-type' )->text() . '</th><th></th></tr>' );
		while ( $row = $res->fetchObject() ) {
			$deleteTitle = Title::newFromText( 'ResourceLoaderArticles/delete/' . $row->rla_id, NS_SPECIAL );
			$editTitle = Title::newFromText( 'ResourceLoaderArticles/edit/' . $row->rla_id, NS_SPECIAL );
			$output->addHTML( '<tr><td>' . $row->rla_id . '</td><td>' . $row->rla_page . '</td><td>' . $row->rla_wiki . '</td><td>' . $row->rla_type . '</td><td><a class="btn btn-primary" href="' . $editTitle->getLocalURL() . '">' . $this->msg( 'resourceloaderarticles-edit' )->text() . '</a><a class="btn btn-primary" href="' . $deleteTitle->getLocalURL() . '">' . $this->msg( 'resourceloaderarticles-delete' )->text() . '</a></td></tr>' );
		}
		$output->addHTML( '</table></div>' );
	}

	private function addPage() {
		$formDescriptor = [
			'Page' => [
				'label-message' => 'resourceloaderarticles-page',
				'type' => 'text',
				'required' => true,
			],
			'Wiki' => [
				'label-message' => 'resourceloaderarticles-wiki',
				'type' => 'text',
				'required' => true,
			],
			'Type' => [
				'class' => 'HTMLSelectField',
				'label' => 'Select an option',
				'options' => [
					'JavaScript' => 'script',
					'CSS' => 'style',
				],
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( $this->msg( 'resourceloaderarticles-add-page' )->text() );
		$htmlForm->setFormIdentifier( 'addPageCB' );
		$htmlForm->setSubmitCallback( [ $this, 'addPageCB' ] );

		$htmlForm->show();
	}

	public function addPageCB( $formData ) {
		$output = $this->getOutput();
		$store = true;
		if ( empty( $formData[ 'Page' ] ) ) {
			$output->addWikiText( '<div class="error">' . $this->msg( 'resourceloaderarticles-error-page-empty' )->text() . '</div>' );
			$store = false;
		} elseif ( ( substr( $formData[ 'Page' ], -4 ) !== '.css' && $formData[ 'Type' ] === 'style' ) || ( substr( $formData[ 'Page' ], -3 ) !== '.js' && $formData[ 'Type' ] === 'script' ) ) {
			$output->addWikiText( '<div class="error">' . $this->msg( 'resourceloaderarticles-error-page-invalid' )->text() . '</div>' );
			$store = false;
		}
		if ( empty( $formData[ 'Wiki' ] ) ) {
			$output->addWikiText( '<div class="error">' . $this->msg( 'resourceloaderarticles-error-wiki-empty' )->text() . '</div>' );
			$store = false;
		}
		if ( $store ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->insert( 'resourceloaderarticles', [ 'rla_page' => $formData[ 'Page' ], 'rla_wiki' => $formData[ 'Wiki' ], 'rla_type' => $formData[ 'Type' ] ] );
			$output->addWikiText( '<div class="success">' . $this->msg( 'resourceloaderarticles-success-add' )->text() . '</div>' );
		}
	}

	private function editPage( $id ) {
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select( 'resourceloaderarticles', '*', [ 'rla_id' => $id ] );
		$row = $res->fetchObject();
		$formDescriptor = [
			'Id' => [
				'type' => 'hidden',
				'required' => true,
				'default' => $row->rla_id,
			],
			'Page' => [
				'label-message' => 'resourceloaderarticles-page',
				'type' => 'text',
				'required' => true,
				'default' => $row->rla_page,
			],
			'Wiki' => [
				'label-message' => 'resourceloaderarticles-wiki',
				'type' => 'text',
				'required' => true,
				'default' => $row->rla_wiki,
			],
			'Type' => [
				'class' => 'HTMLSelectField',
				'label' => 'Select an option',
				'options' => [
					'JavaScript' => 'script',
					'CSS' => 'style',
				],
				'default' => $row->rla_type,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( $this->msg( 'resourceloaderarticles-add-page' )->text() );
		$htmlForm->setFormIdentifier( 'editPageCB' );
		$htmlForm->setSubmitCallback( [ $this, 'editPageCB' ] );

		$htmlForm->show();
	}

	public function editPageCB( $formData ) {
		$output = $this->getOutput();
		$store = true;
		if ( empty( $formData[ 'Page' ] ) ) {
			$output->addWikiText( '<div class="error">' . $this->msg( 'resourceloaderarticles-error-page-empty' )->text() . '</div>' );
			$store = false;
		} elseif ( ( substr( $formData[ 'Page' ], -4 ) !== '.css' && $formData[ 'Type' ] === 'style' ) || ( substr( $formData[ 'Page' ], -3 ) !== '.js' && $formData[ 'Type' ] === 'script' ) ) {
			$output->addWikiText( '<div class="error">' . $this->msg( 'resourceloaderarticles-error-page-invalid' )->text() . '</div>' );
			$store = false;
		}
		if ( empty( $formData[ 'Wiki' ] ) ) {
			$output->addWikiText( '<div class="error">' . $this->msg( 'resourceloaderarticles-error-wiki-empty' )->text() . '</div>' );
			$store = false;
		}
		if ( $store ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update( 'resourceloaderarticles', [ 'rla_page' => $formData[ 'Page' ], 'rla_wiki' => $formData[ 'Wiki' ], 'rla_type' => $formData[ 'Type' ] ], [ 'rla_id' => $formData[ 'Id' ] ] );
			$output->addWikiText( '<div class="success">' . $this->msg( 'resourceloaderarticles-success-edit' )->text() . '</div>' );
		}
	}

	private function deletePage( $id ) {
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select( 'resourceloaderarticles', '*', [ 'rla_id' => $id ] );
		$row = $res->fetchObject();
		$formDescriptor = [
			'Id' => [
				'type' => 'hidden',
				'required' => true,
				'default' => $row->rla_id,
			],
			'Page' => [
				'label-message' => 'resourceloaderarticles-page',
				'type' => 'text',
				'required' => true,
				'default' => $row->rla_page,
			],
			'Wiki' => [
				'label-message' => 'resourceloaderarticles-wiki',
				'type' => 'text',
				'required' => true,
				'default' => $row->rla_wiki,
			],
			'Type' => [
				'class' => 'HTMLSelectField',
				'label' => 'Select an option',
				'options' => [
					'JavaScript' => 'script',
					'CSS' => 'style',
				],
				'default' => $row->rla_type,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( $this->msg( 'resourceloaderarticles-delete-page' )->text() );
		$htmlForm->setFormIdentifier( 'detelePageCB' );
		$htmlForm->setSubmitCallback( [ $this, 'detelePageCB' ] );

		$htmlForm->show();
	}

	public function detelePageCB( $formData ) {
		$output = $this->getOutput();
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'resourceloaderarticles', [ 'rla_id' => $formData[ 'Id' ] ] );
		$output->addWikiText( '<div class="success">' . $this->msg( 'resourceloaderarticles-success-delete' )->text() . '</div>' );
	}

}
