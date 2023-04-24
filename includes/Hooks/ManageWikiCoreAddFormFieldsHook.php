<?php

namespace Miraheze\ManageWiki\Hooks;

interface ManageWikiCoreAddFormFieldsHook {
	/**
	 * @param array &$formData
	 * @return void
	 */
	public function onManageWikiCoreAddFormFields( &$formDescriptor ): void;
}
