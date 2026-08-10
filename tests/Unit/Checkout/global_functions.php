<?php
declare(strict_types=1);

// Global-scope helpers for the DB integration tests. This file has no
// namespace so modification() is available to the framework Loader/Model
// code that the tests exercise.

if (!function_exists('modification')) {
	function modification($file)
	{
		return $file;
	}
}
