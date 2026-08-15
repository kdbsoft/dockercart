<?php
// Heading
$_['heading_title'] = 'System Update';

// Text
$_['text_current_version']   = 'Current version';
$_['text_remote_version']    = 'Available version';
$_['text_up_to_date']        = 'Up to date';
$_['text_update_available']   = 'Update available';
$_['text_checking']          = 'Checking…';
$_['text_running']           = 'Update in progress…';
$_['text_progress']          = 'Progress';
$_['text_changelog']          = 'Changelog';
$_['text_no_changelog']       = 'Changelog is not available for this source.';
$_['text_system_update']       = 'System Update';
$_['text_maintenance_on']    = 'Maintenance mode is enabled during the update. The storefront is temporarily unavailable to visitors.';
$_['text_success']           = 'Settings saved.';
$_['text_trust_warning']     = 'The update downloads and executes code from the repository configured below. Only use repositories you control and trust.';
$_['text_limitations']       = 'The GUI updater synchronizes application files (upload/), applies SQL migrations and refreshes OCMOD modifications. Infrastructure changes (Dockerfile, docker-compose, entrypoint, composer.lock) are NOT applied by the GUI — use `make update` on the host for those.';
$_['text_stale']             = 'A previous update run was interrupted. Maintenance mode may still be enabled. Use the button below to restore maintenance mode and clear the update state, then try again.';
$_['text_request_failed']    = 'Request failed';
$_['text_update_failed']     = 'Update failed';

// Entry
$_['entry_remote']           = 'Repository URL';
$_['entry_branch']           = 'Branch';

// Help
$_['help_remote']            = 'HTTPS URL of the same DockerCart source repository (e.g. https://github.com/kdbsoft/dockercart). The updater downloads the branch archive from this repository and executes its code — only use repositories you control and trust.';
$_['help_branch']            = 'Branch to pull updates from (e.g. main). Must match the branch your deployment tracks.';

// Button
$_['button_save']            = 'Save';
$_['button_check']           = 'Check for updates';
$_['button_update']          = 'Update now';
$_['button_reset']           = 'Reset update state';

// Error
$_['error_permission']       = 'Warning: You do not have permission to modify the system update settings!';
$_['error_required']         = 'Repository URL and branch are required.';
$_['error_remote']           = 'Repository URL must start with https://';
$_['error_branch']           = 'Branch must contain only letters, numbers, dots, dashes and slashes.';
$_['error_method']           = 'Invalid request method.';
$_['error_fetch']            = 'Could not fetch the remote version. Check the repository URL, branch and network access.';
$_['error_running']          = 'An update is already running. Please wait for it to finish.';
