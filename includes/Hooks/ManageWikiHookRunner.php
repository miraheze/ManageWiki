<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use Miraheze\ManageWiki\Helpers\ModuleFactory;

class ManageWikiHookRunner implements
	ManageWikiCoreAddFormFieldsHook,
	ManageWikiCoreFormSubmissionHook
{

	public function __construct(
		private readonly HookContainer $container
	) {
	}

	/**
	 * Executes the ManageWikiCoreAddFormFields hook to allow modification of form fields for a given wiki.
	 *
	 * @param IContextSource $context The context in which the form is being generated.
	 * @param ModuleFactory $moduleFactory Factory for creating modules relevant to the form.
	 * @param string $dbname The database name of the target wiki.
	 * @param bool $ceMW Indicates if the Create/Edit ManageWiki mode is active.
	 * @param array $formDescriptor Reference to the form descriptor array to be modified by hooks.
	 */
	public function onManageWikiCoreAddFormFields(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		bool $ceMW,
		array &$formDescriptor
	): void {
		$this->container->run(
			'ManageWikiCoreAddFormFields',
			[ $context, $moduleFactory, $dbname, $ceMW, &$formDescriptor ],
			[ 'abortable' => false ]
		);
	}

	/**
	 * Executes the ManageWikiCoreFormSubmission hook with the provided context, module factory, database name, and form data.
	 *
	 * This method delegates form submission handling to all registered hook handlers for ManageWikiCoreFormSubmission.
	 *
	 * @param IContextSource $context The context in which the form submission occurs.
	 * @param ModuleFactory $moduleFactory The module factory instance relevant to the submission.
	 * @param string $dbname The name of the target database.
	 * @param array $formData The submitted form data.
	 */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void {
		$this->container->run(
			'ManageWikiCoreFormSubmission',
			[ $context, $moduleFactory, $dbname, $formData ],
			[ 'abortable' => false ]
		);
	}
}
