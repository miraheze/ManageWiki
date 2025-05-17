<?php

namespace Miraheze\ManageWiki\Tests\Unit;

use Generator;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;
use Miraheze\ManageWiki\Hooks\HookRunner;

/**
 * @covers \Miraheze\ManageWiki\Hooks\HookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {

	/** @inheritDoc */
	public static function provideHookRunners(): Generator {
		yield HookRunner::class => [ HookRunner::class ];
	}
}
