<?php
declare(strict_types=1);

/**
 * OrderFlow
 *
 * Configurable order status workflow built on top of the existing
 * oc_order_status catalogue. The flow is defined by two settings stored
 * in oc_setting (store 0):
 *
 *   config_order_flow_steps       - ordered list of status IDs forming the
 *                                   main chain (Pending -> ... -> Delivered)
 *   config_order_flow_transitions - JSON map of extra transitions that are
 *                                   allowed in addition to "forward to the
 *                                   next step", e.g. {"1":["130"], ...}
 *
 * Transition rules:
 *   - from step N the order may always move forward to step N+1;
 *   - additional transitions come from the extra-transitions map;
 *   - a status with no next step and no extra transitions is terminal;
 *   - when the flow is not configured (no steps), every transition is
 *     allowed — this keeps upgrades and custom setups working unchanged.
 *
 * The class takes the config array in the constructor so it can be unit
 * tested without a registry.
 */
class OrderFlow {
	/** @var array<int> Ordered chain of status IDs. */
	private $steps = [];

	/** @var array<int, array<int>> Map: from status ID => allowed extra status IDs. */
	private $transitions = [];

	/**
	 * @param array|null $config ['steps' => array<int>, 'transitions' => array]
	 */
	public function __construct($config = null) {
		if (is_array($config)) {
			$this->steps = array_values(array_unique(array_map('intval', (array)($config['steps'] ?? []))));
			$this->transitions = $this->normalizeTransitions($config['transitions'] ?? []);
		}
	}

	/**
	 * Flow is active only when at least one step is configured.
	 */
	public function isEnabled(): bool {
		return (bool)$this->steps;
	}

	/**
	 * Ordered chain of status IDs.
	 *
	 * @return array<int>
	 */
	public function getSteps(): array {
		return $this->steps;
	}

	/**
	 * Index of a status inside the chain, or -1 when it is not a step.
	 */
	public function getStepIndex($status_id): int {
		$index = array_search((int)$status_id, $this->steps, true);

		return $index === false ? -1 : (int)$index;
	}

	/**
	 * Status ID of the next step in the chain, or null for the last step.
	 */
	public function getNextStatus($status_id) {
		$index = $this->getStepIndex($status_id);

		if ($index === -1 || !isset($this->steps[$index + 1])) {
			return null;
		}

		return $this->steps[$index + 1];
	}

	/**
	 * Extra transitions map: from status ID => list of target status IDs.
	 *
	 * @return array<int, array<int>>
	 */
	public function getTransitions(): array {
		return $this->transitions;
	}

	/**
	 * All status IDs reachable from the given one: the next step (when the
	 * status is part of the chain) plus every extra transition. Targets equal
	 * to the source status are filtered out; results are unique and ordered
	 * by chain position (chain members first).
	 *
	 * @return array<int>
	 */
	public function getAllowedTransitions($status_id): array {
		$status_id = (int)$status_id;
		$allowed = [];

		$next = $this->getNextStatus($status_id);

		if ($next !== null) {
			$allowed[$next] = $next;
		}

		foreach ($this->transitions[$status_id] ?? [] as $target) {
			$target = (int)$target;

			if ($target !== $status_id) {
				$allowed[$target] = $target;
			}
		}

		usort($allowed, function ($a, $b) {
			$ia = $this->getStepIndex($a);
			$ib = $this->getStepIndex($b);

			if ($ia === -1 && $ib === -1) {
				return $a <=> $b;
			}

			if ($ia === -1) {
				return 1;
			}

			if ($ib === -1) {
				return -1;
			}

			return $ia <=> $ib;
		});

		return array_values($allowed);
	}

	/**
	 * A status is terminal when no transition leads out of it (neither a
	 * next step nor an extra transition). Typically used for statuses like
	 * Cancelled/Refunded that end the flow.
	 */
	public function isTerminal($status_id): bool {
		return $this->getAllowedTransitions((int)$status_id) === [];
	}

	/**
	 * Whether the status belongs to the configured chain.
	 */
	public function isStep($status_id): bool {
		return $this->getStepIndex($status_id) !== -1;
	}

	/**
	 * Validates a transition. Returns true when:
	 *   - the flow is disabled (no steps configured), or
	 *   - the target equals the source (no-op), or
	 *   - the target is the next step or listed in the extra transitions.
	 */
	public function validateTransition($from, $to): bool {
		$from = (int)$from;
		$to = (int)$to;

		if (!$this->isEnabled() || $from === $to) {
			return true;
		}

		return in_array($to, $this->getAllowedTransitions($from), true);
	}

	/**
	 * @param mixed $transitions
	 *
	 * @return array<int, array<int>>
	 */
	private function normalizeTransitions($transitions): array {
		if (!is_array($transitions)) {
			return [];
		}

		$normalized = [];

		foreach ($transitions as $from => $targets) {
			$from = (int)$from;

			if (!$from || !is_array($targets)) {
				continue;
			}

			$normalized[$from] = array_values(array_unique(array_map('intval', $targets)));
		}

		return $normalized;
	}
}
