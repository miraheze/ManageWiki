<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\ContentHandlerFactory;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\Options\UserOptionsLookup;
use Miraheze\ManageWiki\Helpers\TypesBuilder;
use SkinFactory;

class TypesBuilderFactory {

	public function __construct(
		private readonly PermissionsFactory $permissionsFactory,
		private readonly ContentHandlerFactory $contentHandlerFactory,
		private readonly InterwikiLookup $interwikiLookup,
		private readonly PermissionManager $permissionManager,
		private readonly SkinFactory $skinFactory,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly ServiceOptions $options,
	) {
	}

	public function getBuilder( string $dbname ): TypesBuilder {
		return new TypesBuilder(
			$this->contentHandlerFactory,
			$this->interwikiLookup,
			$this->permissionManager,
			$this->permissionsFactory,
			$this->skinFactory,
			$this->userOptionsLookup,
			$this->options,
			$dbname
		);
	}
}
