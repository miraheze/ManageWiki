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
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserIdentity;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use function array_intersect;
use function implode;
use function mb_strtolower;
use function trim;

class SpecialUndeleteWiki extends SpecialPage {

	// Keyed into $wgRateLimits, same as any other MediaWiki rate limit.
	private const string RATE_LIMIT_ACTION = 'managewiki-undeletewiki';

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

		// Wiki deletion state only lives on the central wiki.
		if ( !$this->databaseUtils->isCurrentWikiCentral() ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-unavailable-notcentralwiki' );
		}

		if ( !$this->moduleFactory->isEnabled( 'undeletewiki' ) ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-disabled', [ 'undeletewiki' ] );
		}

		$this->requireNamedUser();

		$session = $this->getRequest()->getSession();
		if ( $session->get( 'manageWikiSaveSuccess' ) ) {
			// Remove session data for the success message
			$session->remove( 'manageWikiSaveSuccess' );
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
		}

		$formDescriptor = [
			'info' => [
				'type' => 'info',
				'default' => $this->msg( 'managewiki-undeletewiki-info' )->text(),
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
			->setWrapperLegendMsg( 'managewiki-undeletewiki-header' )
			->setSubmitTextMsg( 'managewiki-undeletewiki-submit' )
			->prepareForm()
			->show();
	}

	public function onSubmit( array $formData ): Status|false {
		$dbname = mb_strtolower( trim( $formData['dbname'] ) );

		try {
			$mwCore = $this->moduleFactory->core( $dbname );
		} catch ( MissingWikiError ) {
			return Status::newFatal( 'managewiki-error-missingwiki', $dbname );
		}

		$remoteUser = $this->actorStoreFactory->getUserIdentityLookup( $dbname )
			->getUserIdentityByName( $this->getUser()->getName() );

		$hasCentralGroup = $this->holdsGroup(
			$this->userGroupManagerFactory->getUserGroupManager(),
			$this->getUser(),
			ConfigNames::UndeleteWikiCentralGroups
		);

		$hasLocalGroup = $remoteUser && $this->holdsGroup(
			$this->userGroupManagerFactory->getUserGroupManager( $dbname ),
			$remoteUser,
			ConfigNames::UndeleteWikiLocalGroups
		);

		if ( !$hasCentralGroup && !$hasLocalGroup ) {
			return Status::newFatal( 'managewiki-undeletewiki-notallowed', $dbname );
		}

		if ( $mwCore->isLocked() ) {
			return Status::newFatal( 'managewiki-mwlocked' );
		}

		if ( !$mwCore->isEnabled( 'action-undelete' ) || !$mwCore->isDeleted() ) {
			return Status::newFatal( 'managewiki-undeletewiki-notdeleted', $dbname );
		}

		if ( $mwCore->isPrivate() && !$this->hasReadAccess( $dbname, $remoteUser ) ) {
			return Status::newFatal( 'managewiki-undeletewiki-noreadaccess', $dbname );
		}

		// Only the central groups are rate limited, local groups are not.
		if ( !$hasLocalGroup && $this->getUser()->pingLimiter( self::RATE_LIMIT_ACTION ) ) {
			return Status::newFatal( 'actionthrottledtext' );
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

		$this->getRequest()->getSession()->set( 'manageWikiSaveSuccess', 1 );
		$this->getOutput()->redirect( $this->getPageTitle()->getFullURL() );

		// Even though it's successful we still return false so
		// that the form does not dissappear when submitted.
		return false;
	}

	/**
	 * Checks whether the current user's effective local groups on the given
	 * wiki (per ManageWiki's own permissions module) include 'read', since
	 * private wikis don't grant that implicitly.
	 */
	private function hasReadAccess( string $dbname, ?UserIdentity $remoteUser ): bool {
		if ( !$remoteUser ) {
			return false;
		}

		$readGroups = $this->moduleFactory->permissions( $dbname )->getGroupsWithPermission( 'read' );
		$userGroupManager = $this->userGroupManagerFactory->getUserGroupManager( $dbname );
		return (bool)array_intersect( $readGroups, $userGroupManager->getUserEffectiveGroups( $remoteUser ) );
	}

	/**
	 * Checks whether the user holds any of the groups in the given group config.
	 */
	private function holdsGroup(
		UserGroupManager $userGroupManager,
		UserIdentity $user,
		string $configName
	): bool {
		$allowedGroups = $this->getConfig()->get( $configName );
		return (bool)array_intersect( $allowedGroups, $userGroupManager->getUserGroups( $user ) );
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
			$this->databaseUtils->isCurrentWikiCentral() &&
			$this->moduleFactory->isEnabled( 'undeletewiki' );
	}
}
