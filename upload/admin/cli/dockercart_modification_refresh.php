<?php
/**
 * DockerCart - CLI OCMOD Modification Refresh Script
 *
 * Clears and rebuilds all OCMOD modification cache files.
 * Equivalent to clicking the "Refresh" button in Admin > Modifications.
 * Designed to run inside the container (entrypoint / make update).
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[modification-refresh] ERROR: admin/config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[modification-refresh] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

$registry = new Registry();

// Config
$config = new Config();
$config->load('default');
$config->load('admin');
$registry->set('config', $config);

// Log
$log = new Log('ocmod.log');
$registry->set('log', $log);

// Event
$event = new Event($registry);
$registry->set('event', $event);

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Database
$db = new DB(
	$config->get('db_engine') ?: 'mysqli',
	$config->get('db_hostname') ?: 'mariadb',
	$config->get('db_username') ?: 'dockercart',
	$config->get('db_password') ?: 'dockercart_password',
	$config->get('db_database') ?: 'dockercart',
	$config->get('db_port') ?: '3306'
);
$registry->set('db', $db);

$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'oc_';

echo "[modification-refresh] Clearing modification cache...\n";

// Clear all modification files
$files = [];
$path = [DIR_MODIFICATION . '*'];

while (count($path) != 0) {
	$next = array_shift($path);

	foreach (glob($next) as $file) {
		if (is_dir($file)) {
			$path[] = $file . '/*';
		}

		$files[] = $file;
	}
}

rsort($files);

foreach ($files as $file) {
	if ($file != DIR_MODIFICATION . 'index.html') {
		if (is_file($file)) {
			unlink($file);
		} elseif (is_dir($file)) {
			rmdir($file);
		}
	}
}

echo "[modification-refresh] Collecting XML sources...\n";

// Collect XML modifications
$xml = [];

// Filesystem XML files (developer direct-editing support)
$files = glob(DIR_SYSTEM . '*.ocmod.xml');

if ($files) {
	foreach ($files as $file) {
		$xml[] = file_get_contents($file);
	}
}

// Enabled modifications from database
$results = $db->query("SELECT * FROM `{$prefix}modification` WHERE status = '1'");

foreach ($results->rows as $result) {
	$xml[] = $result['xml'];
}

if (empty($xml)) {
	echo "[modification-refresh] No modifications found. Done.\n";
	exit(0);
}

echo '[modification-refresh] Applying ' . count($xml) . " modification(s)...\n";

$modification = [];
$original = [];
$log_entries = [];

foreach ($xml as $xml_content) {
	if (empty($xml_content)) {
		continue;
	}

	$dom = new DOMDocument('1.0', 'UTF-8');
	$dom->preserveWhiteSpace = false;
	$dom->loadXml($xml_content);

	// Log
	$log_entries[] = 'MOD: ' . $dom->getElementsByTagName('name')->item(0)->textContent;

	$recovery = [];

	if ($modification) {
		$recovery = $modification;
	}

	$files = $dom->getElementsByTagName('modification')->item(0)->getElementsByTagName('file');

	foreach ($files as $file) {
		$operations = $file->getElementsByTagName('operation');

		$files = explode('|', str_replace("\\", '/', $file->getAttribute('path')));

		foreach ($files as $file) {
			$path = '';

			if ((substr($file, 0, 7) == 'catalog')) {
				$path = DIR_CATALOG . substr($file, 8);
			}

			if ((substr($file, 0, 5) == 'admin')) {
				$path = DIR_APPLICATION . substr($file, 6);
			}

			if ((substr($file, 0, 6) == 'system')) {
				$path = DIR_SYSTEM . substr($file, 7);
			}

			if ($path) {
				$files = glob($path, GLOB_BRACE);

				if ($files) {
					foreach ($files as $file) {
						if (substr($file, 0, strlen(DIR_CATALOG)) == DIR_CATALOG) {
							$key = 'catalog/' . substr($file, strlen(DIR_CATALOG));
						}

						if (substr($file, 0, strlen(DIR_APPLICATION)) == DIR_APPLICATION) {
							$key = 'admin/' . substr($file, strlen(DIR_APPLICATION));
						}

						if (substr($file, 0, strlen(DIR_SYSTEM)) == DIR_SYSTEM) {
							$key = 'system/' . substr($file, strlen(DIR_SYSTEM));
						}

						if (!isset($modification[$key])) {
							$content = file_get_contents($file);

							$modification[$key] = preg_replace('~\r?\n~', "\n", $content);
							$original[$key] = preg_replace('~\r?\n~', "\n", $content);

							$log_entries[] = PHP_EOL . 'FILE: ' . $key;
						} else {
							$log_entries[] = PHP_EOL . 'FILE: (sub modification) ' . $key;
						}

						foreach ($operations as $operation) {
							$error = $operation->getAttribute('error');

							// Ignoreif
							$ignoreif = $operation->getElementsByTagName('ignoreif')->item(0);

							if ($ignoreif) {
								if ($ignoreif->getAttribute('regex') != 'true') {
									if (strpos($modification[$key], $ignoreif->textContent) !== false) {
										continue;
									}
								} else {
									if (preg_match($ignoreif->textContent, $modification[$key])) {
										continue;
									}
								}
							}

							$status = false;

							// Search and replace
							if ($operation->getElementsByTagName('search')->item(0)->getAttribute('regex') != 'true') {
								// Search
								$search = $operation->getElementsByTagName('search')->item(0)->textContent;
								$trim = $operation->getElementsByTagName('search')->item(0)->getAttribute('trim');
								$index = $operation->getElementsByTagName('search')->item(0)->getAttribute('index');

								if (!$trim || $trim == 'true') {
									$search = trim($search);
								}

								// Add
								$add = $operation->getElementsByTagName('add')->item(0)->textContent;
								$trim = $operation->getElementsByTagName('add')->item(0)->getAttribute('trim');
								$position = $operation->getElementsByTagName('add')->item(0)->getAttribute('position');
								$offset = $operation->getElementsByTagName('add')->item(0)->getAttribute('offset');

								if ($offset == '') {
									$offset = 0;
								}

								if ($trim == 'true') {
									$add = trim($add);
								}

								$log_entries[] = 'CODE: ' . $search;

								if ($index !== '') {
									$indexes = explode(',', $index);
								} else {
									$indexes = [];
								}

								$i = 0;

								$lines = explode("\n", $modification[$key]);

								for ($line_id = 0; $line_id < count($lines); $line_id++) {
									$line = $lines[$line_id];

									$match = false;

									if (stripos($line, $search) !== false) {
										if (!$indexes) {
											$match = true;
										} elseif (in_array($i, $indexes)) {
											$match = true;
										}

										$i++;
									}

									if ($match) {
										switch ($position) {
											default:
											case 'replace':
												$new_lines = explode("\n", $add);

												if ($offset < 0) {
													array_splice($lines, $line_id + $offset, abs($offset) + 1, [str_replace($search, $add, $line)]);

													$line_id -= $offset;
												} else {
													array_splice($lines, $line_id, $offset + 1, [str_replace($search, $add, $line)]);
												}
												break;
											case 'before':
												$new_lines = explode("\n", $add);

												array_splice($lines, $line_id - $offset, 0, $new_lines);

												$line_id += count($new_lines);
												break;
											case 'after':
												$new_lines = explode("\n", $add);

												array_splice($lines, ($line_id + 1) + $offset, 0, $new_lines);

												$line_id += count($new_lines);
												break;
										}

										$log_entries[] = 'LINE: ' . $line_id;

										$status = true;
									}
								}

								$modification[$key] = implode("\n", $lines);
							} else {
								$search = trim($operation->getElementsByTagName('search')->item(0)->textContent);
								$limit = $operation->getElementsByTagName('search')->item(0)->getAttribute('limit');
								$replace = trim($operation->getElementsByTagName('add')->item(0)->textContent);

								if (!$limit) {
									$limit = -1;
								}

								$match = [];

								preg_match_all($search, $modification[$key], $match, PREG_OFFSET_CAPTURE);

								if ($limit > 0) {
									$match[0] = array_slice($match[0], 0, $limit);
								}

								if ($match[0]) {
									$log_entries[] = 'REGEX: ' . $search;

									for ($i = 0; $i < count($match[0]); $i++) {
										$log_entries[] = 'LINE: ' . (substr_count(substr($modification[$key], 0, $match[0][$i][1]), "\n") + 1);
									}

									$status = true;
								}

								$modification[$key] = preg_replace($search, $replace, $modification[$key], $limit);
							}

							if (!$status) {
								if ($error == 'abort') {
									$modification = $recovery;

									$log_entries[] = 'NOT FOUND - ABORTING!';

									break 5;
								} elseif ($error == 'skip') {
									$log_entries[] = 'NOT FOUND - OPERATION SKIPPED!';

									continue;
								} else {
									$log_entries[] = 'NOT FOUND - OPERATIONS ABORTED!';

									break;
								}
							}
						}
					}
				}
			}
		}
	}

	$log_entries[] = '----------------------------------------------------------------';
}

echo "[modification-refresh] Writing modified files...\n";

foreach ($modification as $key => $value) {
	if ($original[$key] != $value) {
		$path = '';

		$directories = explode('/', dirname($key));

		foreach ($directories as $directory) {
			$path = $path . '/' . $directory;

			if (!is_dir(DIR_MODIFICATION . $path)) {
				@mkdir(DIR_MODIFICATION . $path, 0777);
			}
		}

		file_put_contents(DIR_MODIFICATION . $key, $value);
	}
}

// Write log
$ocmod_log = new Log('ocmod.log');
$ocmod_log->write(implode("\n", $log_entries));

echo '[modification-refresh] Done. ' . count($xml) . " modification(s) applied.\n";
exit(0);
