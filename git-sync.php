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
} else {
    exit;
}

/**
 *
 * @param bool|WP_Error $return Upgrade offer return.
 * @param array         $plugin Plugin package arguments.
 */
function rhgp_handle_plugin_update( $return, $plugin ) {

    if ( ! defined('GIT_REPOSITORY_PATH') || ( defined('GIT_REPOSITORY_PATH') && !GIT_REPOSITORY_PATH ) ) {
        return $return;
    }

    if ( ! defined('GIT_SC_PLUGINS') || ( defined('GIT_SC_PLUGINS') && !GIT_SC_PLUGINS ) ) {
        return $return;
    }

    if ( ! isset($plugin['plugin']) ) {
        return $return;
    }

    $allowedPluginsInSc = array_map('trim', explode(',', GIT_SC_PLUGINS));
    $pluginName = preg_replace('/\/.+/', '', $plugin['plugin']);

    if ( ! in_array($pluginName, $allowedPluginsInSc) ) {
        return $return;
    }

    try {
        $git = new \CzProject\GitPhp\Git();
        $repo = $git->open(GIT_REPOSITORY_PATH);
        $currentBranch = $repo->getCurrentBranchName();

        if ( ! $repo->hasChanges() ) {
            return $return;
        }

        $plugins_abs_folder = WP_CONTENT_DIR . '/plugins/';
        $plugin_data = get_plugin_data($plugins_abs_folder . $plugin['plugin'], false, false);
        $repo->addFile($plugins_abs_folder . $pluginName);
        $tag = $pluginName . '-' . $plugin_data['Version'];
        try {
            $repo->createTag($tag, TRUE);
            $commitMessage = 'GitPush: Upgrade ' . $pluginName . ' to version ' . $plugin_data['Version'] . '.';
            if ($user = wp_get_current_user()) {
                $commitMessage = '['.$user->user_login.'] ' . $commitMessage;
            }
            $repo->commit($commitMessage);
            $repo->push('origin', ['--tags']);
        } catch (\Exception $ex2) {
            if ($repo->getCurrentBranchName() !== $currentBranch) {
                $brokenBranch = $repo->getCurrentBranchName();
                if ($repo->hasChanges()) {
                    $repo->execute('checkout .');
                }
                $repo->checkout($currentBranch);
                $repo->removeBranch($brokenBranch);
            }
        }
    } catch (\Exception $ex) {
        throw $ex;
    }
    return $return;
}
add_filter( 'upgrader_post_install', 'rhgp_handle_plugin_update', 10, 2 );