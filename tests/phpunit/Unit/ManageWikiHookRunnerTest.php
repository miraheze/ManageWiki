<?php

namespace Miraheze\ManageWiki\Tests\Unit;

use Generator;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;
use Miraheze\ManageWiki\Hooks\ManageWikiHookRunner;

/**
 * @covers \Miraheze\ManageWiki\Hooks\ManageWikiHookRunner
 */
class ManageWikiHookRunnerTest extends HookRunnerTestBase {

	/** @inheritDoc */
	public static function provideHookRunners(): Generator {
		yield ManageWikiHookRunner::class => [ ManageWikiHookRunner::class ];
	}
}
