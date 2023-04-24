<?php

namespace Miraheze\ManageWiki\Hooks;

interface ManageWikiCoreAddFormFieldsHook {
	/**
	 * @param array &$formDescriptor
	 * @return void
	 */
	public function onManageWikiCoreAddFormFields( &$formDescriptor ): void;
}
