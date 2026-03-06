<?php
/**
 * DockerCart Dockercart Redirects - Startup Pre-Action (shim)
 *
 * Runs the redirect check early in the request lifecycle and preserves
 * language-prefixed URL handling. This is the renamed startup controller
 * to be referenced by events after migration.
 */

// Dockercart Redirects startup shim — removed.
//
// This file replaced the old startup shim but has been deliberately
// cleared to prevent any runtime behavior. The module's event handlers
// now point to `extension/module/dockercart_redirects` controller.

// If you need to fully delete this file from git, run:
//   git rm upload/catalog/controller/startup/dockercart_redirects.php

// Keep a harmless class with a no-op method for absolute safety.
class ControllerStartupDockercartRedirects extends Controller {
    public function index() {
        // intentionally inert
        return;
    }
}
