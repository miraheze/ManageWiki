<?php

namespace Miraheze\ManageWiki\Specials;

use MediaWiki\Exception\ErrorPageError;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\Helpers\WikiUndeleter;

class SpecialUndeleteWiki extends FormSpecialPage {

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly WikiUndeleter $wikiUndeleter,
	) {
		parent::__construct( 'UndeleteWiki' );
	}

	/** @inheritDoc */
	public function execute( $par ): void {
		if ( !$this->extensionRegistry->isLoaded( 'CreateWiki' ) ||
			!$this->getConfig()->get( ConfigNames::EnableUndeleteWiki )
		) {
			throw new ErrorPageError( 'nosuchspecialpage', 'nospecialpagetext' );
		}
		if ( !$this->databaseUtils->isCurrentWikiCentral() ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-unavailable-notcentralwiki' );
		}

		$this->requireNamedUser();
		parent::execute( $par );
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		return [
			'dbname' => [
				'type' => 'text',
				'label-message' => 'managewiki-label-dbname',
				'required' => true,
			],
		];
	}

	/** @inheritDoc */
	protected function alterForm( HTMLForm $form ): void {
		$form->setSubmitTextMsg( 'managewiki-undeletewiki-submit' );
	}

	/** @inheritDoc */
	protected function getDisplayFormat(): string {
		return 'ooui';
	}

	/** @inheritDoc */
	public function onSubmit( array $data ): Status {
		return $this->wikiUndeleter->undelete( $data['dbname'], $this->getUser() );
	}

	/** @inheritDoc */
	public function onSuccess(): void {
		$this->getOutput()->addWikiMsg( 'managewiki-undeletewiki-success' );
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'managewiki-undeletewiki' );
	}

	/** @inheritDoc */
	public function doesWrites(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/** @inheritDoc */
	public function isListed(): bool {
		return $this->extensionRegistry->isLoaded( 'CreateWiki' ) &&
			$this->getConfig()->get( ConfigNames::EnableUndeleteWiki ) &&
			$this->databaseUtils->isCurrentWikiCentral();
	}
}
