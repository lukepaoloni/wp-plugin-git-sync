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

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

/**
 *
 * @param bool|WP_Error $return Upgrade offer return.
 * @param array         $plugin Plugin package arguments.
 */
function rhgp_handle_plugin_update( $return, $plugin ) {

    if ( ! defined('GIT_REPOSITORY_PATH') ) {
        return $return;
    }

    if ( ! defined('GIT_SC_PLUGINS') ) {
        return $return;
    }

    if ( ! isset($plugin['plugin']) ) {
        return $return;
    }

    $allowedPluginsInSc = array_map('trim', explode(',', GIT_SC_PLUGINS));
    $plugin = preg_replace('/\/.+/', '', $plugin['plugin']);

    if ( ! in_array($plugin, $allowedPluginsInSc) ) {
        return $return;
    }

    // 1. Is plugin allowed to push to source countrol?
        // a. Is plugin flagged to be pushed to a source control (e.g. not a plugin that should be updated via composer)
        // b. Has the current user got write permissions
    // 2. Stage the change of the plugin update
    $git = new CzProject\GitPhp\Git();
    $repo = $git->open(GIT_REPOSITORY_PATH);

    if ( ! $repo->hasChanges() ) {
        return $return;
    }

    $plugin_abs_file = plugin_dir_path(__FILE__ . '../' . $plugin['plugin']);
    $plugin_data = get_plugin_data($plugin_abs_file, false, false);

    $repo->addAllChanges();
    // 3. Create a feature branch to push the changes to
    $branch = 'feature/upgrade/' . $plugin . '-' . md5(time());
    $repo->createBranch($branch, TRUE);
    // 4. Commit with a generated message
    $repo->commit('commit message');
    $repo->push('origin');
    return $return;
}
add_filter( 'upgrader_post_install', 'rhgp_handle_plugin_update', 10, 2 );