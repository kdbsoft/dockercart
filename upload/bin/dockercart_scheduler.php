#!/usr/bin/env php
<?php
/**
 * DockerCart Scheduler Daemon — long-running cron dispatcher.
 *
 * Polls oc_dockercart_scheduler_task for due tasks and spawns worker
 * processes via proc_open(). Modules register their tasks via the
 * DockercartScheduler library at install() / uninstall() time.
 * There is no hardcoded handler list — the daemon is fully generic.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_scheduler.php
 *
 * Environment:
 *   SCHEDULER_ENABLED=true      (required — exits 0 otherwise)
 *   SCHEDULER_POLL_INTERVAL=60  (seconds, default 60)
 *   DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT, DB_PREFIX
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

// ── Environment ───────────────────────────────────────────────────────

$schedulerEnabled = getenv('SCHEDULER_ENABLED') ?: 'false';
if ($schedulerEnabled !== 'true') {
	echo '[' . date('Y-m-d H:i:s') . '] SCHEDULER_DISABLED scheduler not enabled, exiting' . "\n";
	exit(0);
}

$pollInterval = (int)(getenv('SCHEDULER_POLL_INTERVAL') ?: 60);
if ($pollInterval < 1) {
	$pollInterval = 60;
}

$workerTimeout = (int)(getenv('SCHEDULER_WORKER_TIMEOUT') ?: 3600);
if ($workerTimeout < 1) {
	$workerTimeout = 3600;
}

$dbHost   = getenv('DB_HOSTNAME') ?: 'mariadb';
$dbUser   = getenv('DB_USERNAME') ?: 'dockercart';
$dbPass   = getenv('DB_PASSWORD') ?: 'dockercart_password';
$dbName   = getenv('DB_DATABASE') ?: 'dockercart';
$dbPort   = getenv('DB_PORT') ?: '3306';
$dbPrefix = getenv('DB_PREFIX') ?: 'oc_';

// ── PDO Connection ─────────────────────────────────────────────────────

function connectPdo(string $dbHost, string $dbPort, string $dbName, string $dbUser, string $dbPass): PDO {
	return new PDO(
		sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
		$dbUser,
		$dbPass,
		[
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]
	);
}

$maxRetries = 5;
$retryDelay = 3;
$pdo = null;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
	try {
		$pdo = connectPdo($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
		break;
	} catch (\PDOException $e) {
		if ($attempt === $maxRetries) {
			fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] FATAL DB connection failed after ' . $maxRetries . ' attempts: ' . $e->getMessage() . "\n");
			exit(1);
		}
		fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] WARNING DB connection attempt ' . $attempt . '/' . $maxRetries . ' failed: ' . $e->getMessage() . " — retrying in {$retryDelay}s\n");
		sleep($retryDelay);
	}
}

// ── Logging ────────────────────────────────────────────────────────────

function scheduler_log(string $status, string $detail = ''): void {
	$line = '[' . date('Y-m-d H:i:s') . '] ' . $status;
	if ($detail !== '') {
		$line .= ' ' . $detail;
	}
	echo $line . "\n";
}

// ── Cron Schedule Parser ───────────────────────────────────────────────

/**
 * Human-readable schedule presets mapped to intervals in minutes.
 */
const PRESET_INTERVALS = [
	'every_15m' => 15,
	'every_30m' => 30,
	'hourly'    => 60,
	'every_6h'  => 360,
	'every_12h' => 720,
];

/**
 * Determine whether a task is due to run.
 *
 * @param string      $cronSchedule  cron expression or preset name
 * @param string|null $lastRun       last_run timestamp from DB (nullable)
 * @return bool
 */
function isDue(string $cronSchedule, ?string $lastRun): bool {
	$now = new \DateTime();
	$lastRunDt = ($lastRun !== null) ? new \DateTime($lastRun) : null;

	// First run — always due
	if ($lastRunDt === null) {
		return true;
	}

	// ── Presets: interval-based ──
	if (isset(PRESET_INTERVALS[$cronSchedule])) {
		$intervalMinutes = PRESET_INTERVALS[$cronSchedule];
		$nextRun = (clone $lastRunDt)->modify("+{$intervalMinutes} minutes");
		return $now >= $nextRun;
	}

	// ── Preset: daily (once per calendar day) ──
	if ($cronSchedule === 'daily') {
		$todayMidnight = new \DateTime('today midnight');
		return $lastRunDt < $todayMidnight;
	}

	// ── 5-field cron expression ──
	if (preg_match('/^\s*[\d*,-\/]+\s+[\d*,-\/]+\s+[\d*,-\/]+\s+[\d*,-\/]+\s+[\d*,-\/]+\s*$/', $cronSchedule)) {
		$parts = preg_split('/\s+/', trim($cronSchedule));
		if (count($parts) === 5) {
			return isCronDue($parts, $lastRunDt, $now);
		}
	}

	// Unknown schedule format → never due
	return false;
}

/**
 * Check if a time matches a cron field value.
 * Supports: *, N, N-M, N-M/step, every-N/step, N,M,...
 */
function cronFieldMatches(string $fieldValue, int $actual): bool {
	// Split by comma
	$alternatives = explode(',', $fieldValue);

	foreach ($alternatives as $alt) {
		$alt = trim($alt);

		// Wildcard
		if ($alt === '*') {
			return true;
		}

		// Step: */N or N-M/N
		if (strpos($alt, '/') !== false) {
			[$range, $step] = explode('/', $alt, 2);
			$step = (int)$step;
			if ($step <= 0) {
				continue;
			}

			if ($range === '*') {
				if ($actual % $step === 0) {
					return true;
				}
			} else {
				$rangeBounds = explode('-', $range, 2);
				$min = (int)$rangeBounds[0];
				$max = isset($rangeBounds[1]) ? (int)$rangeBounds[1] : $min;
				if ($actual >= $min && $actual <= $max && ($actual - $min) % $step === 0) {
					return true;
				}
			}
			continue;
		}

		// Range: N-M
		if (strpos($alt, '-') !== false) {
			[$minStr, $maxStr] = explode('-', $alt, 2);
			$min = (int)$minStr;
			$max = (int)$maxStr;
			if ($actual >= $min && $actual <= $max) {
				return true;
			}
			continue;
		}

		// Single value
		if ((int)$alt === $actual) {
			return true;
		}
	}

	return false;
}

/**
 * Check if a DateTime matches a 5-field cron expression.
 */
function matchesCron(array $cronParts, \DateTime $dt): bool {
	[$minField, $hourField, $domField, $monthField, $dowField] = $cronParts;

	return cronFieldMatches($minField, (int)$dt->format('i'))
		&& cronFieldMatches($hourField, (int)$dt->format('G'))
		&& cronFieldMatches($domField, (int)$dt->format('j'))
		&& cronFieldMatches($monthField, (int)$dt->format('n'))
		&& cronFieldMatches($dowField, (int)$dt->format('w'));
}

/**
 * Determine if a cron-scheduled task is due by finding the most recent
 * matching time after $lastRunDt and checking if we are past it.
 */
function isCronDue(array $cronParts, \DateTime $lastRunDt, \DateTime $now): bool {
	// Search minute-by-minute from lastRun+1 to now+buffer
	$current = clone $lastRunDt;
	$current->modify('+1 minute');
	$maxCheck = (clone $now)->modify('+2 minutes');

	while ($current <= $maxCheck) {
		if (matchesCron($cronParts, $current)) {
			return $now >= $current;
		}
		$current->modify('+1 minute');
	}

	return false;
}

// ── Worker Process Management ──────────────────────────────────────────

/**
 * Calculate the earliest next-due Unix timestamp for a task with a
 * preset schedule.  Returns null for cron expressions (too complex).
 */
function calculateNextDueTimestamp(string $cronSchedule, ?string $lastRun): ?int {
	if (isset(PRESET_INTERVALS[$cronSchedule])) {
		$interval = PRESET_INTERVALS[$cronSchedule] * 60;
		if ($lastRun !== null) {
			return strtotime($lastRun) + $interval;
		}
		return time();
	}

	if ($cronSchedule === 'daily') {
		if ($lastRun !== null) {
			return strtotime('today midnight') + 86400;
		}
		return time();
	}

	return null;
}

/**
 * Spawn a background worker via proc_open().
 * Returns [pid, process] where process is the proc_open resource.
 * Returns null on failure.
 *
 * @return array{pid: int, process: resource}|null
 */
function spawnWorker(string $command, string $handlerName, int $taskId): ?array {
	$logDir = '/var/www/storage/logs/scheduler';

	if (!is_dir($logDir)) {
		@mkdir($logDir, 0755, true);
	}

	$logFile = $logDir . '/worker_' . $handlerName . '_' . $taskId . '.log';

	$descriptorspec = [
		0 => ['file', '/dev/null', 'r'],
		1 => ['file', '/dev/null', 'a'],
		2 => ['file', $logFile, 'w'],
	];

	$process = @proc_open($command, $descriptorspec, $pipes);

	if (!is_resource($process)) {
		return null;
	}

	$status = proc_get_status($process);
	$pid = $status['pid'] ?? 0;

	return ['pid' => $pid, 'process' => $process];
}

/**
 * Reap finished workers and log completion.
 * Updates $activeTasks in place.
 *
 * @param array<string, array> $activeTasks  keyed by task identifier, value = ['pid' => int, 'process' => resource, ...]
 */
function reapWorkers(array &$activeTasks, PDO $pdo, string $dbPrefix, int $workerTimeout): void {
	foreach ($activeTasks as $taskKey => $info) {
		$process = $info['process'];

		if (!is_resource($process)) {
			unset($activeTasks[$taskKey]);
			continue;
		}

		$runningDuration = time() - ($info['started_at'] ?? 0);

		if ($runningDuration > $workerTimeout) {
			$pid = $info['pid'] ?? 0;

			if ($pid > 0) {
				@posix_kill($pid, SIGTERM);

				for ($i = 0; $i < 5; $i++) {
					sleep(1);
					$ts = proc_get_status($process);

					if (!$ts['running']) {
						break;
					}
				}

				$ts = proc_get_status($process);

				if ($ts['running']) {
					@proc_terminate($process, 9);
				}
			}

			@proc_close($process);

			scheduler_log('TIMEOUT',
				'handler=' . ($info['handler'] ?? 'unknown')
				. ' task=' . ($info['task_id'] ?? '?')
				. ' pid=' . $pid
				. ' duration=' . $runningDuration . 's'
			);

			unset($activeTasks[$taskKey]);
			continue;
		}

		$status = proc_get_status($process);

		if (!$status['running']) {
			$exitCode = $status['exitcode'];
			proc_close($process);

			scheduler_log('COMPLETED',
				'handler=' . ($info['handler'] ?? 'unknown')
				. ' task=' . ($info['task_id'] ?? '?')
				. ' pid=' . ($info['pid'] ?? 0)
				. ' exitcode=' . $exitCode
			);

			unset($activeTasks[$taskKey]);
		}
	}
}

// ── Task‑Running Check ─────────────────────────────────────────────────

/**
 * Determine whether a task is already being processed by checking the
 * scheduler_task.last_result JSON for an in_progress flag.
 *
 * @param string               $taskKey      e.g. "import_yml:1" or "novapost_sync:2"
 * @param array<string, array> $activeTasks  PID tracking map
 * @param PDO                  $pdo
 * @param string               $dbPrefix
 * @return bool
 */
function isTaskAlreadyRunning(string $taskKey, array $activeTasks, PDO $pdo, string $dbPrefix): bool {
	// PID tracking
	if (isset($activeTasks[$taskKey])) {
		return true;
	}

	// Extract task_id for DB check (last identifier after colon)
	$colonPos = strrpos($taskKey, ':');
	if ($colonPos === false) {
		return false;
	}

	$dbId = (int)substr($taskKey, $colonPos + 1);

	try {
		$stmt = $pdo->prepare(
			"SELECT `last_result` FROM `{$dbPrefix}dockercart_scheduler_task` WHERE `task_id` = ?"
		);
		$stmt->execute([$dbId]);
		$row = $stmt->fetch();
	} catch (\PDOException $e) {
		return false;
	}

	if ($row && !empty($row['last_result'])) {
		$result = json_decode($row['last_result'], true);

		if (is_array($result) && !empty($result['in_progress'])) {
			return true;
		}
	}

	return false;
}

// ── Signal Handling ────────────────────────────────────────────────────

$running = true;
$reload  = false;

pcntl_async_signals(true);

pcntl_signal(SIGTERM, function () use (&$running): void {
	scheduler_log('SHUTDOWN', 'reason=SIGTERM draining_active=' . ($GLOBALS['activeTaskCount'] ?? 0));
	$running = false;
});

pcntl_signal(SIGINT, function () use (&$running): void {
	scheduler_log('SHUTDOWN', 'reason=SIGINT');
	$running = false;
});

pcntl_signal(SIGHUP, function () use (&$running, &$reload): void {
	scheduler_log('RELOAD', 'reason=SIGHUP draining_active=' . ($GLOBALS['activeTaskCount'] ?? 0));
	$running = false;
	$reload  = true;
});

// ── Main Loop ──────────────────────────────────────────────────────────

/**
 * Active tasks keyed by task_key (e.g. "import_yml:3", "novapost_sync:1").
 * Each value: ['pid' => int, 'process' => resource, 'handler' => string, 'task_id' => int, 'started_at' => int]
 * @var array<string, array>
 */
$activeTasks = [];

$GLOBALS['activeTaskCount'] = 0;

scheduler_log('STARTED',
	'poll_interval=' . $pollInterval
	. ' db_host=' . $dbHost
);

$GLOBALS['schedulerNeedReconnect'] = false;

$schedulerTable = $dbPrefix . 'dockercart_scheduler_task';
$lastResultStmt = null;

while ($running) {
	// Reconnect if a previous loop detected a DB failure
	if ($GLOBALS['schedulerNeedReconnect']) {
		try {
			$pdo = connectPdo($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
			$GLOBALS['schedulerNeedReconnect'] = false;
			$lastResultStmt = null;
			scheduler_log('RECONNECT', 'db_connection_recovered=1');
		} catch (\PDOException $e) {
			scheduler_log('ERROR', 'reconnect_failed="' . $e->getMessage() . '"');

			if ($running) {
				sleep(5);
			}

			continue;
		}
	}

	// Reap finished workers
	reapWorkers($activeTasks, $pdo, $dbPrefix, $workerTimeout);
	$GLOBALS['activeTaskCount'] = count($activeTasks);

	// Query all enabled tasks from the universal registry
	try {
		$dueRows = $pdo->query(
			"SELECT `task_id`, `task_type`, `task_name`, `source_id`, `worker_command`,
			        `cron_schedule`, `last_run`
			 FROM `{$schedulerTable}`
			 WHERE `cron_enabled` = 1 AND `status` = 1"
		);
	} catch (\PDOException $e) {
		scheduler_log('ERROR', 'scheduler_task_query_error="' . $e->getMessage() . '"');
		$GLOBALS['schedulerNeedReconnect'] = true;
		continue;
	}

	foreach ($dueRows as $row) {
		$taskType    = $row['task_type'];
		$taskId      = (int)$row['task_id'];
		$sourceId    = ((int)$row['source_id'] > 0) ? (int)$row['source_id'] : null;
		$cronSchedule = $row['cron_schedule'];
		$lastRun     = $row['last_run'];

		if (!isDue($cronSchedule, $lastRun)) {
			continue;
		}

		// Build task_key for dedup: "import_yml:5" or "novapost_sync:2"
		$taskKey = $taskType . ':' . ($sourceId ?? $taskId);

		if (isTaskAlreadyRunning($taskKey, $activeTasks, $pdo, $dbPrefix)) {
			continue;
		}

		// Build the actual command — substitute source_id into %d placeholder
		$command = $row['worker_command'];
		if (strpos($command, '%d') !== false) {
			$command = sprintf(str_replace('%d', '%1$d', $command), $sourceId ?? 0);
		}

		$result = spawnWorker($command, $taskType, $taskId);

		if ($result === null || $result['pid'] === 0) {
			scheduler_log('ERROR',
				'handler=' . $taskType
				. ' task=' . $taskId
				. ' error="failed to spawn worker"'
			);
			continue;
		}

		$activeTasks[$taskKey] = [
			'pid'        => $result['pid'],
			'process'    => $result['process'],
			'handler'    => $taskType,
			'task_id'    => $taskId,
			'started_at' => time(),
		];

		// Update last_run immediately on spawn to prevent re-spawning
		try {
			$pdo->exec(
				"UPDATE `{$schedulerTable}`"
				. " SET `last_run` = NOW(), `date_modified` = NOW()"
				. " WHERE `task_id` = " . (int)$taskId
			);
		} catch (\PDOException $e) {
			scheduler_log('WARNING',
				'handler=' . $taskType
				. ' task=' . $taskId
				. ' error="failed to update last_run: ' . $e->getMessage() . '"'
			);
		}

		$GLOBALS['activeTaskCount'] = count($activeTasks);

		scheduler_log('STARTED',
			'handler=' . $taskType
			. ' task=' . $taskId
			. ' pid=' . $result['pid']
		);
	}

	// Smart sleep: calculate time until next due task
	$sleepSeconds = $pollInterval;

	if (empty($activeTasks)) {
		$earliestDue = null;

		try {
			$sleepRows = $pdo->query(
				"SELECT `cron_schedule`, `last_run`
				 FROM `{$schedulerTable}`
				 WHERE `cron_enabled` = 1 AND `status` = 1"
			);

			foreach ($sleepRows as $r) {
				$ts = calculateNextDueTimestamp($r['cron_schedule'], $r['last_run']);

				if ($ts !== null && ($earliestDue === null || $ts < $earliestDue)) {
					$earliestDue = $ts;
				}
			}
		} catch (\PDOException $e) {
			// continue with default
		}

		if ($earliestDue !== null) {
			$sleepSeconds = max(1, min($pollInterval, $earliestDue - time()));
		}
	}

	if ($running) {
		sleep($sleepSeconds);
	}
}

// ── Graceful Shutdown / Reload ─────────────────────────────────────────

$drainLabel = $reload ? 'RELOAD' : 'SHUTDOWN';

scheduler_log($drainLabel, 'draining_active=' . count($activeTasks));

$drainTimeout = time() + 30;

while (!empty($activeTasks) && time() < $drainTimeout) {
	reapWorkers($activeTasks, $pdo, $dbPrefix, $workerTimeout);

	if (!empty($activeTasks)) {
		sleep(1);
	}
}

// Force-kill any remaining workers
if (!empty($activeTasks)) {
	scheduler_log($drainLabel, 'force_kill_remaining=' . count($activeTasks));

	foreach ($activeTasks as $taskKey => $info) {
		$pid = $info['pid'] ?? 0;

		if ($pid > 0) {
			@posix_kill($pid, SIGTERM);
			scheduler_log('KILLED',
				'handler=' . ($info['handler'] ?? 'unknown')
				. ' task=' . ($info['task_id'] ?? '?')
				. ' pid=' . $pid
			);
		}

		if (is_resource($info['process'])) {
			@proc_close($info['process']);
		}
	}
}

// SIGHUP: re-execute with fresh code, same PID, no container restart
if ($reload) {
	scheduler_log('RELOAD', 're-executing pid=' . getmypid());
	pcntl_exec(PHP_BINARY, $argv);
	scheduler_log('ERROR', 'reload_exec_failed');
	exit(1);
}

scheduler_log('EXIT', 'all_workers_drained');
exit(0);
