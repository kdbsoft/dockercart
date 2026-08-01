<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

class OrderFlowTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		require_once __DIR__ . '/../../../upload/system/library/order_flow.php';
	}

	private function makeFlow(): \OrderFlow
	{
		return new \OrderFlow([
			'steps'       => [1, 131, 132, 133, 128, 129],
			'transitions' => [
				1   => [130],
				131 => [130],
				132 => [130, 134],
				133 => [130, 134],
				128 => [130, 134],
				129 => [134],
			],
		]);
	}

	public function testForwardTransitionAllowed(): void
	{
		$flow = $this->makeFlow();

		$this->assertTrue($flow->validateTransition(1, 131));
		$this->assertTrue($flow->validateTransition(131, 132));
		$this->assertTrue($flow->validateTransition(132, 133));
		$this->assertTrue($flow->validateTransition(133, 128));
		$this->assertTrue($flow->validateTransition(128, 129));
	}

	public function testExtraTransitionsAllowed(): void
	{
		$flow = $this->makeFlow();

		$this->assertTrue($flow->validateTransition(1, 130));
		$this->assertTrue($flow->validateTransition(132, 134));
		$this->assertTrue($flow->validateTransition(129, 134));
	}

	public function testInvalidTransitionBlocked(): void
	{
		$flow = $this->makeFlow();

		$this->assertFalse($flow->validateTransition(1, 129));
		$this->assertFalse($flow->validateTransition(131, 133));
		$this->assertFalse($flow->validateTransition(133, 1));
		$this->assertFalse($flow->validateTransition(130, 132));
	}

	public function testSameStatusIsNoopAllowed(): void
	{
		$flow = $this->makeFlow();

		$this->assertTrue($flow->validateTransition(128, 128));
	}

	public function testTerminalStatuses(): void
	{
		$flow = $this->makeFlow();

		$this->assertTrue($flow->isTerminal(130));
		$this->assertTrue($flow->isTerminal(134));
		$this->assertFalse($flow->isTerminal(129));
		$this->assertFalse($flow->isTerminal(1));
	}

	public function testUnknownStatusesNotInFlow(): void
	{
		$flow = $this->makeFlow();

		$this->assertSame([], $flow->getAllowedTransitions(127));
		$this->assertFalse($flow->validateTransition(127, 132));
	}

	public function testEmptyConfigAllowsEverything(): void
	{
		$flow = new \OrderFlow();

		$this->assertFalse($flow->isEnabled());
		$this->assertTrue($flow->validateTransition(1, 999));
	}

	public function testStepsOrderAndIndex(): void
	{
		$flow = $this->makeFlow();

		$this->assertSame([1, 131, 132, 133, 128, 129], $flow->getSteps());
		$this->assertSame(3, $flow->getStepIndex(133));
		$this->assertSame(-1, $flow->getStepIndex(999));
		$this->assertSame(132, $flow->getNextStatus(131));
		$this->assertNull($flow->getNextStatus(129));
		$this->assertTrue($flow->isStep(133));
		$this->assertFalse($flow->isStep(130));
	}

	public function testAllowedTransitionsOrderedByChain(): void
	{
		$flow = $this->makeFlow();

		$this->assertSame([133, 130, 134], $flow->getAllowedTransitions(132));
		$this->assertSame([129, 130, 134], $flow->getAllowedTransitions(128));
	}

	public function testGetAllowedTransitionsFiltersSelfLoop(): void
	{
		$flow = new \OrderFlow([
			'steps'       => [1, 2],
			'transitions' => [1 => [1, 2]],
		]);

		$this->assertSame([2], $flow->getAllowedTransitions(1));
	}

	public function testConfigStringStatusesNormalized(): void
	{
		$flow = new \OrderFlow([
			'steps'       => ['1', '132', '132', 133],
			'transitions' => ['1' => ['130', 130, '134']],
		]);

		$this->assertSame([1, 132, 133], $flow->getSteps());
		$this->assertSame([132, 130, 134], $flow->getAllowedTransitions(1));
	}
}
