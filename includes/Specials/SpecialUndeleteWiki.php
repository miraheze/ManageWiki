<?php

namespace Miraheze\ManageWiki\Specials;

use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\RawMessage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\UserGroupManagerFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use function array_intersect;
use function implode;

class SpecialUndeleteWiki extends SpecialPage {

	public function __construct(
		private readonly ActorStoreFactory $actorStoreFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly ModuleFactory $moduleFactory,
		private readonly UserGroupManagerFactory $userGroupManagerFactory,
	) {
		parent::__construct( 'UndeleteWiki' );
	}

	/**
	 * @param ?string $par @phan-unused-param
	 * @throws ErrorPageError
	 */
	public function execute( $par ): void {
		$this->setHeaders();

		if ( !$this->extensionRegistry->isLoaded( 'CreateWiki' ) ) {
			throw new ErrorPageError( 'nosuchspecialpage', 'nospecialpagetext' );
		}

		if ( !$this->databaseUtils->isCurrentWikiCentral() ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-unavailable-notcentralwiki' );
		}

		if ( !$this->getConfig()->get( ConfigNames::UndeleteGroups ) ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-disabled', [ 'undelete' ] );
		}

		$this->requireNamedUser();

		$formDescriptor = [
			'info' => [
				'type' => 'info',
				'default' => $this->msg( 'managewiki-undelete-info' )->text(),
			],
			'dbname' => [
				'type' => 'text',
				'label-message' => 'managewiki-label-dbname',
				'required' => true,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setSubmitCallback( [ $this, 'onSubmit' ] )
			->setWrapperLegendMsg( 'managewiki-undelete-header' )
			->setSubmitTextMsg( 'managewiki-undelete-submit' )
			->prepareForm()
			->show();
	}

	public function onSubmit( array $formData ): Status|false {
		$dbname = $formData['dbname'];

		try {
			$mwCore = $this->moduleFactory->core( $dbname );
		} catch ( MissingWikiError ) {
			return Status::newFatal( 'managewiki-error-missingwiki', $dbname );
		}

		if ( $mwCore->isLocked() ) {
			return Status::newFatal( 'managewiki-mwlocked' );
		}

		if ( !$mwCore->isEnabled( 'action-undelete' ) || !$mwCore->isDeleted() ) {
			return Status::newFatal( 'managewiki-undelete-notdeleted', $dbname );
		}

		if ( !$this->isAllowedToUndelete( $dbname ) ) {
			return Status::newFatal( 'managewiki-undelete-notallowed', $dbname );
		}

		$mwCore->undelete();
		if ( !$mwCore->hasChanges() ) {
			return Status::newFatal( 'managewiki-changes-none' );
		}

		$mwCore->commit();

		$errors = $mwCore->getErrors();
		if ( $errors ) {
			$errorOut = [];
			foreach ( $errors as $error ) {
				foreach ( $error as $msg => $params ) {
					$errorOut[] = $this->msg( $msg, $params )->escaped();
				}
			}

			return Status::newFatal(
				new RawMessage( implode( Html::element( 'br' ), $errorOut ) )
			);
		}

		$mwCore->addLogParam( '4::wiki', $dbname );

		$logEntry = new ManualLogEntry( 'managewiki', $mwCore->getLogAction() );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'ManageWiki', "core/$dbname" ) );
		$logEntry->setParameters( $mwCore->getLogParams() );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$this->getOutput()->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->text()
				),
				'mw-notify-success'
			)
		);
		return false;
	}

	private function isAllowedToUndelete( string $dbname ): bool {
		$remoteUser = $this->actorStoreFactory->getUserIdentityLookup( $dbname )
			->getUserIdentityByName( $this->getUser()->getName() );

		if ( !$remoteUser ) {
			return false;
		}

		$allowedGroups = $this->getConfig()->get( ConfigNames::UndeleteGroups );
		$userGroupManager = $this->userGroupManagerFactory->getUserGroupManager( $dbname );
		return (bool)array_intersect( $allowedGroups, $userGroupManager->getUserGroups( $remoteUser ) );
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'managewiki-undelete' );
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
			$this->databaseUtils->isCurrentWikiCentral() &&
			(bool)$this->getConfig()->get( ConfigNames::UndeleteGroups );
	}
}
