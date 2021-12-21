<?php
/**
 * Plugin Name: Git Push
 * Plugin URI: https://wordpress.org/plugins/git-push
 * Description: Push updates such as plugin updates to a version control system.
 * Author: Right Hook Studio
 * Version: 0.0.1
 * Text Domain: rhgp
 * Author URI: https://righthookstudio.co.uk
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package git-push
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 *
 * @param object $wp_upgrader Plugin_Upgrader instance.
 * @param array  $hook_extra  {
 *     Array of bulk item update data.
 *
 *     @type string $action  Type of action. Default 'update'.
 *     @type string $type    Type of update process. Accepts 'plugin', 'theme', 'translation', or 'core'.
 *     @type bool   $bulk    Whether the update process is a bulk update. Default true.
 *     @type array  $plugins Array of the basename paths of the plugins' main files.
 * }
 */
function rhgp_handle_other_plugins_update( $wp_upgrader, $hook_extra ) {
    if ( ! isset( $hook_extra['action'], $hook_extra['type'], $hook_extra['plugins'] ) ) {
		return;
	}

    if ( 'update' !== $hook_extra['action'] || 'plugin' !== $hook_extra['type'] || ! is_array( $hook_extra['plugins'] ) ) {
		return;
	}

    if ( ! defined('GIT_REPOSITORY_URL') ) {
        return;
    }

    $plugins = $hook_extra['plugins'];

    // 1. Is plugin allowed to push to source countrol?
        // a. Is plugin flagged to be pushed to a source control (e.g. not a plugin that should be updated via composer)
        // b. Has the current user got write permissions
    // 2. Stage the change of the plugin update
    $git = new CzProject\GitPhp\Git;
    $repo = $git->open(GIT_REPOSITORY_URL);
    $repo->addAllChanges();
    // 3. Create a feature branch to push the changes to
    $repo->createBranch(md5(time()), TRUE);
    // 4. Commit with a generated message
    $repo->commit('commit message');
    $repo->push('origin');
}
add_action( 'upgrader_process_complete', 'rhgp_handle_other_plugins_update', 25, 2 );