<?php

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
namespace Miraheze\ManageWiki;

/**
 * A class containing constants representing the names of configuration variables,
 * to protect against typos.
 */
class ConfigNames {

	// From MediaWiki core but doesn't exist in MainConfigNames
	public const Conf = 'Conf';

	public const CacheDirectory = 'ManageWikiCacheDirectory';

	public const CacheType = 'ManageWikiCacheType';

	public const Extensions = 'ManageWikiExtensions';

	public const ExtensionsDefault = 'ManageWikiExtensionsDefault';

	public const ForceSidebarLinks = 'ManageWikiForceSidebarLinks';

	public const HandledUnknownContentModels = 'ManageWikiHandledUnknownContentModels';

	public const HelpUrl = 'ManageWikiHelpUrl';

	public const ModulesEnabled = 'ManageWikiModulesEnabled';

	public const NamespacesAdditional = 'ManageWikiNamespacesAdditional';

	public const NamespacesDisallowedNames = 'ManageWikiNamespacesDisallowedNames';

	public const PermissionsAdditionalAddGroups = 'ManageWikiPermissionsAdditionalAddGroups';

	public const PermissionsAdditionalAddGroupsSelf = 'ManageWikiPermissionsAdditionalAddGroupsSelf';

	public const PermissionsAdditionalRemoveGroups = 'ManageWikiPermissionsAdditionalRemoveGroups';

	public const PermissionsAdditionalRemoveGroupsSelf = 'ManageWikiPermissionsAdditionalRemoveGroupsSelf';

	public const PermissionsAdditionalRights = 'ManageWikiPermissionsAdditionalRights';

	public const PermissionsDefaultPrivateGroup = 'ManageWikiPermissionsDefaultPrivateGroup';

	public const PermissionsDisallowedGroups = 'ManageWikiPermissionsDisallowedGroups';

	public const PermissionsDisallowedRights = 'ManageWikiPermissionsDisallowedRights';

	public const PermissionsPermanentGroups = 'ManageWikiPermissionsPermanentGroups';

	public const Servers = 'ManageWikiServers';

	public const Settings = 'ManageWikiSettings';

	public const UseCustomDomains = 'ManageWikiUseCustomDomains';
}
