<?php
/**
 * Code based on the implementation using CentralAuth's Special:GlobalGroupPermissions
 * A ManageWiki implementation to MediaWiki of the prototype code to core.
 */

class SpecialManageWikiDefaultPermissions extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ManageWikiDefaultPermissions' );
	}

	function execute( $subpage ) {
		global $wgDBname, $wgCreateWikiGlobalWiki;

		$this->setHeaders();
		$out = $this->getOutput();

		$out->addModuleStyles( 'ext.managewiki.permissions' );
		$out->setRobotPolicy( "noindex,nofollow" );
		$out->setArticleRelated( false );
		$out->enableClientCache( false );

		if ( !ManageWiki::checkSetup( 'permissions', true, $out ) ) {
			return false;
		}

		if ( $wgDBname != $wgCreateWikiGlobalWiki ) {
			$this->getOutput()->addWikiMsg( 'managewiki-perm-not-default' );
			return false;
		}

		if ( $subpage == '' ) {
			$subpage = $this->getRequest()->getVal( 'wpGroup' );
		}

		if (
			$subpage != ''
			&& $this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) )
			&& $this->getRequest()->wasPosted()
		) {
			$this->doSubmit( $subpage );

		} elseif ( $subpage != '' ) {
			$this->buildGroupView( $subpage );
		} else {
			$this->buildMainView();
		}
	}

	function buildMainView() {
		global $wgUser;
		$out = $this->getOutput();
		$groups = ManageWiki::defaultGroups();
		$craftedGroups = [];

		foreach( $groups as $group ) {
			$craftedGroups[UserGroupMembership::getGroupName( $group )] = $group;
		}

		$out->addWikiMsg( 'managewiki-perm-header' );

		$groupSelector['groups'] = [
			'label-message' => 'managewiki-perm-select',
			'type' => 'select',
			'options' => $craftedGroups,
		];

		$selectForm = HTMLForm::factory( 'ooui', $groupSelector, $this->getContext(), 'groupSelector' );
		$selectForm->setMethod('post' )->setFormIdentifier( 'groupSelector' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )->prepareForm()->show();

		if ( $wgUser->isAllowed( 'managewiki-editdefault' ) ) {
			$createDescriptor['groups'] = [
				'type' => 'text',
				'label-message' => 'managewiki-perm-creategroup',
			];

			$createForm = HTMLForm::factory( 'ooui', $createDescriptor, $this->getContext() );
			$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] ) ->prepareForm()->show();
		}
	}

	function onSubmitRedirectToPermissionsPage( array $params ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions' )->getFullUrl() . '/' . $params['groups'] );

		return true;
	}

	function buildGroupView( $group ) {
		global $wgUser;
		$editable = $wgUser->isAllowed( 'managewiki-editdefault' );

		$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );

		$fieldsetClass = $editable
			? 'mw-managewiki-perm-edit'
			: 'mw-managewiki-perm-readonly';
		$html = Xml::fieldset(
			$this->msg( 'managewiki-perm-fieldset', $group )->text(),
			false,
			[ 'class' => $fieldsetClass ]
		);

		if ( $editable ) {
			$html .= Xml::openElement( 'form', [
				'method' => 'post',
				'action' =>
					SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions', $group )->getLocalUrl(),
				'name' => 'managewiki-perm-newgroup'
			] );
			$html .= Html::hidden( 'wpGroup', $group );
			$html .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		}

		$fields = [];

		if ( $editable ) {
			$fields['managewiki-name'] = Xml::input( 'wpGroupName', 50, $group );
		} else {
			$fields['managewiki-name'] = htmlspecialchars( $group );
		}

		$fields['managewiki-perm-display'] =
			htmlspecialchars( UserGroupMembership::getGroupName( $group ) );
		$fields['managewiki-perm-member'] =
			htmlspecialchars( UserGroupMembership::getGroupMemberName( $group, '#' ) );
		$fields['managewiki-perm-perms'] = $this->buildPermCheckboxes( $group );
		$fields['managewiki-perm-add'] = $this->buildAddCheckboxes( $group );
		$fields['managewiki-perm-remove'] = $this->buildRemoveCheckboxes( $group );

		if ( $editable ) {
			$fields['managewiki-perm-reason'] = Xml::input( 'wpReason', 60 );
		}

		$html .= Xml::buildForm( $fields,  $editable ? 'managewiki-perm-submit' : null );

		if ( $editable ) {
			$html .= Xml::closeElement( 'form' );
		}

		$html .= Xml::closeElement( 'fieldset' );

		$this->getOutput()->addHTML( $html );

		$this->showLogFragment( $group, $this->getOutput() );

	}

	function buildPermCheckboxes( $group ) {
		global $wgUser;
		$editable = $wgUser->isAllowed( 'managewiki-editdefault' );

		$assignedRights = $this->getAssignedRights( $group );

		$checkboxes = [];
		$attribs = [];

		if ( !$editable ) {
			$attribs['disabled'] = 'disabled';
			if ( !$assignedRights ) {
				$this->getOutput()->wrapWikiMsg( '<div class="error">$1</div>',
					[ 'managewiki-perm-nonexistent', $group ] );
			}
		}

		$rights = User::getAllRights();
		sort( $rights );

		foreach ( $rights as $right ) {
			$checked = in_array( $right, $assignedRights );

			$desc = $this->formatRight( $right );

			$checkbox = Xml::check( "wpRightAssigned-$right", $checked,
				array_merge( $attribs, [ 'id' => "wpRightAssigned-$right" ] ) );
			$label = Xml::tags( 'label', [ 'for' => "wpRightAssigned-$right" ],
					$desc );

			$liClass = $checked
				? 'mw-managewiki-perm-checked'
				: 'mw-managewiki-perm-unchecked';
			$checkboxes[] = Html::rawElement(
				'li', [ 'class' => $liClass ], "$checkbox&#160;$label" );
		}

		$count = count( $checkboxes );

		$html = Html::openElement( 'div', [ 'class' => 'mw-managewiki-boxes' ] )
			. '<ul>';

		foreach ( $checkboxes as $cb ) {
			$html .= $cb;
		}

		$html .= '</ul>'
			. Html::closeElement( 'div' );

		return $html;
	}

	function buildAddCheckboxes( $group ) {
		global $wgUser;
		$editable = $wgUser->isAllowed( 'managewiki-editdefault' );

		$checkboxes = [];
		$attribs = [];

		$addedGroups = $this->getAddGroups( $group );
		$addgroups = array_diff( ManageWiki::defaultGroups(), User::getImplicitGroups() );
		sort( $addgroups );

		foreach ( $addgroups as $addgroup ) {
			$checked = in_array( $addgroup, $addedGroups );

			$desc = $this->formatGroup( $addgroup );

			$checkbox = Xml::check( "wpGroupAdd-$addgroup", $checked,
				array_merge( $attribs, [ 'id' => "wpGroupAdd-$addgroup" ] ) );
			$label = Xml::tags( 'label', [ 'for' => "wpGroupAdd-$addgroup" ],
					$desc );

			$liClass = $checked
				? 'mw-managewiki-perm-checked'
				: 'mw-managewiki-perm-unchecked';
			$checkboxes[] = Html::rawElement(
				'li', [ 'class' => $liClass ], "$checkbox&#160;$label" );
		}

		$count = count( $checkboxes );

		$html = Html::openElement( 'div', [ 'class' => 'mw-managewiki-boxes' ] )
			. '<ul>';

		foreach ( $checkboxes as $cb ) {
			$html .= $cb;
		}

		$html .= '</ul>'
			. Html::closeElement( 'div' );

		return $html;
	}

	function buildRemoveCheckboxes( $group ) {
		global $wgUser;
		$editable = $wgUser->isAllowed( 'managewiki-editdefault' );

		$checkboxes = [];
		$attribs = [];
		$removedGroups = $this->getRemoveGroups( $group );
		$removegroups = array_diff( ManageWiki::defaultGroups(), User::getImplicitGroups() );
		sort( $removegroups );

		foreach ( $removegroups as $removegroup ) {
			$checked = in_array( $removegroup, $removedGroups );

			$desc = $this->formatGroup( $removegroup );
			$checkbox = Xml::check( "wpGroupRemove-$removegroup", $checked,
				array_merge( $attribs, [ 'id' => "wpGroupRemove-$removegroup" ] ) );
			$label = Xml::tags( 'label', [ 'for' => "wpGroupRemove-$removegroup" ],
					$desc );

			$liClass = $checked
				? 'mw-managewiki-perm-checked'
				: 'mw-managewiki-perm-unchecked';
			$checkboxes[] = Html::rawElement(
				'li', [ 'class' => $liClass ], "$checkbox&#160;$label" );
		}

		$count = count( $checkboxes );

		$html = Html::openElement( 'div', [ 'class' => 'mw-managewiki-boxes' ] )
			. '<ul>';

		foreach ( $checkboxes as $cb ) {
			$html .= $cb;
		}

		$html .= '</ul>'
			. Html::closeElement( 'div' );

		return $html;
	}


	protected function formatRight( $right ) {
		$rightDesc = $this->msg(
			'listgrouprights-right-display',
			User::getRightDescription( $right ),
			Html::element(
				'span',
				[ 'class' => 'mw-listgrouprights-right-name' ],
				$right
			)
		)->parse();

		return $rightDesc;
	}

	protected function formatGroup( $group ) {
		$groupName = $this->msg(
			'listgrouprights-right-display',
			UserGroupMembership::getGroupName( $group ),
			Html::element(
				'span',
				[ 'class' => 'mw-listgrouprights-right-name' ],
				$group
			)
		)->parse();

		return $groupName;
	}

	function getAssignedRights( $group ) {
		$grouparray = ManageWiki::defaultGroupPermissions( $group );

		return $grouparray['permissions'];
	}

	function getAddGroups( $group ) {
		$grouparray = ManageWiki::defaultGroupPermissions( $group );

		return $grouparray['addgroups'];
	}

	function getRemoveGroups( $group ) {
		$grouparray = ManageWiki::defaultGroupPermissions( $group );

		return $grouparray['removegroups'];
	}

	function doSubmit( $group ) {
		global $wgUser, $wgCreateWikiDatabase, $wgManageWikiPermissionsBlacklistRenames;

		if ( !$wgUser->isAllowed( 'managewiki-editdefault' ) ) {
			return;
		}

		$reason = $this->getRequest()->getVal( 'wpReason', '' );

		$group = Title::newFromText( $group )->getUserCaseDBKey();

		$newname = $this->getRequest()->getVal( 'wpGroupName', $group );

		$newname = Title::newFromText( $newname )->getUserCaseDBKey();

		if ( $group != $newname ) {
			if ( in_array( $newname, ManageWiki::defaultGroups() ) ) {
				$this->getOutput()->addWikiMsg( 'managewiki-perm-rename-taken', $newname );
				return;
			}

			if ( in_array( $oldname, $wgManageWikiPermissionsBlacklistRenames ) ) {
				$this->getOutput()->addWikiMsg( 'managewiki-perm-rename-blacklisted' );
				return;
			}

			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );
			$dbw->update(
				'mw_permissions',
				[
					'perm_group' => $newname
				],
				[
					'perm_group' => $group,
					'perm_dbname' => 'default'
				],
				__METHOD__
			);

			$this->addRenameLog( $group, $newname, $reason );

			$group = $newname;
		}

		$addRights = [];
		$removeRights = [];
		$oldRights = ( !is_null( $this->getAssignedRights( $group ) ) ) ? $this->getAssignedRights( $group ): [];
		$allRights = User::getAllRights();

		foreach ( $allRights as $right ) {
			$alreadyAssigned = in_array( $right, $oldRights );

			if ( !$alreadyAssigned && $this->getRequest()->getCheck( "wpRightAssigned-$right" ) ) {
				$addRights[] = $right;
			} elseif ( $alreadyAssigned &&
				!$this->getRequest()->getCheck( "wpRightAssigned-$right" )
			) {
				$removeRights[] = $right;
			}
		}
		$newRights = array_merge( $addRights, array_diff( $oldRights, $removeRights ) );

		$newAddGroups = [];
		$newRemoveGroups = [];
		$removedAddGroups = [];
		$removedRemoveGroups = [];
		$oldAddGroups = ( !is_null( $this->getAddGroups( $group ) ) ) ? $this->getAddGroups( $group ) : [];
		$oldRemoveGroups = ( !is_null( $this->getRemoveGroups( $group ) ) ) ? $this->getRemoveGroups( $group ) : [];
		$allGroups = ManageWiki::availableGroups();

		foreach ( $allGroups as $allgroup ) {
			$assignedOldAdd = in_array( $allgroup, $oldAddGroups );
			$assignedOldRemove = in_array( $allgroup, $oldRemoveGroups );

			if ( !$assignedOldAdd && $this->getRequest()->getCheck( "wpGroupAdd-$allgroup" ) ) {
				$newAddGroups[] = $allgroup;
			} elseif ( $assignedOldAdd &&
				!$this->getRequest()->getCheck( "wpGroupAdd-$allgroup" )
			) {
				$removedAddGroups[] = $allgroup;
			}

			if ( !$assignedOldRemove && $this->getRequest()->getCheck( "wpGroupRemove-$allgroup" ) ) {
				$newRemoveGroups[] = $allgroup;
			} elseif ( $assignedOldRemove &&
				!$this->getRequest()->getCheck( "wpGroupRemove-$allgroup" )
			) {
				$removedRemoveGroups[] = $allgroup;
			}
		}

		$addGroups = array_merge( array_diff( $oldAddGroups, $removedAddGroups ), $newAddGroups );
		$removeGroups = array_merge( array_diff( $oldRemoveGroups, $removedRemoveGroups ), $newRemoveGroups );

		if ( count( $addRights ) != 0 || count( $removeRights ) != 0 ||
		    count( $newAddGroups ) != 0 || count( $removedAddGroups ) != 0 ||
		    count( $newRemoveGroups ) != 0 || count( $removedRemoveGroups ) != 0
		) {
			$this->updatePermissions( $group, $newRights, $addGroups, $removeGroups );
			$this->addPermissionLog( $group, $addRights, $removeRights, $newAddGroups, $removedAddGroups, $newRemoveGroups, $removedRemoveGroups, $reason );
		}

		$this->getOutput()->setSubTitle( $this->msg( 'managewiki-perm-success' ) );
		$this->getOutput()->addWikiMsg( 'managewiki-perm-success-text', $group );
	}

	function updatePermissions( $group, $rights, $addgroups, $removegroups ) {
		global $wgCreateWikiDatabase;
		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$existing = in_array( $group, ManageWiki::defaultGroups() );

		$rows = [
			'perm_dbname' => 'default',
			'perm_group' => $group,
			'perm_permissions' => json_encode( $rights ),
			'perm_addgroups' => json_encode( $addgroups ),
			'perm_removegroups' => json_encode( $removegroups )
		];

		if ( $existing ) {
			$dbw->update(
				'mw_permissions',
				$rows,
				[ 'perm_dbname' => 'default', 'perm_group' => $group ],
				__METHOD__
			);
		} else {
			$dbw->insert(
				'mw_permissions',
				$rows,
				__METHOD__
			);
		}
	}

	protected function showLogFragment( $group, $output ) {
		$logPage = new LogPage( 'managewiki' );
		$output->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract( $output, 'managewiki', "default-$group" );
	}

	function addPermissionLog( $group, $addRights, $removeRights, $newAddGroups, $removedAddGroups, $newRemoveGroups, $removedRemoveGroups, $reason ) {
		$log = new LogPage( 'managewiki' );

		$log->addEntry(
			'rights',
			SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions', "default-$group" ),
			$reason,
			[
				$this->makeLogList( $addRights ),
				$this->makeLogList( $removeRights ),
				$this->makeLogList( $newAddGroups ),
				$this->makeLogList( $removedAddGroups ),
				$this->makeLogList( $newRemoveGroups ),
				$this->makeLogList( $removedRemoveGroups )
			]
		);
	}

	function addRenameLog( $oldName, $newName, $reason ) {
		$log = new LogPage( 'managewiki' );

		$log->addEntry(
			'rename',
			SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions', "default-$newName" ),
			$reason,
			[
				SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions', $newName ),
				SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions', $oldName )
			]
		);
	}

	function makeLogList( $ids ) {
		return count( $ids )
			? implode( ', ', $ids )
			: $this->msg( 'rightsnone' )->inContentLanguage()->text();
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
