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

function slack_message($plugin_name, $plugin_version, $previous_version, $user_email, $user_name, $change_url)
{
    $previous_version_block = [];

    if ($previous_version) {
        $previous_version_block = array (
            'type' => 'mrkdwn',
            'text' => '*Previous version:* '.$previous_version,
        );
    }

    $author_block = [];

    if ($user_email && $user_name) {
        $author_block = array (
            'type' => 'mrkdwn',
            'text' => '*Author:* <'.$user_email.'|'.$user_name.'>',
        );
    }

    return array (
        'blocks' =>
        array (
          0 =>
          array (
            'type' => 'header',
            'text' =>
            array (
              'type' => 'plain_text',
              'text' => $plugin_name . ' has been updated to version ' . $plugin_version . ' 🚀',
              'emoji' => true,
            ),
          ),
          1 =>
          array (
            'type' => 'section',
            'fields' =>
            array (
              0 => $previous_version_block,
              1 => $author_block,
            ),
          ),
          2 =>
          array (
            'type' => 'actions',
            'elements' =>
            array (
              0 =>
              array (
                'type' => 'button',
                'text' =>
                array (
                  'type' => 'plain_text',
                  'emoji' => true,
                  'text' => 'View change :technologist::skin-tone-2:',
                ),
                'value' => 'click_me_123',
                'url' => $change_url,
                'action_id' => 'button-action',
              ),
            ),
          ),
        ),
    );
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

    if ( ! defined('GIT_REPOSITORY_HTTPS') ) {
        return $return;
    }

    if ( ! defined('GP_SLACK_WEBHOOK') ) {
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
        $branch = 'gp/upgrade/' . $pluginName . '-' . $plugin_data['Version'];
        try {
            $repo->createBranch($branch, TRUE);
            $commitMessage = 'GitPush: Upgrade ' . $pluginName . ' to version ' . $plugin_data['Version'] . '.';
            $user_name = null;
            $user_email = null;
            if ($user = wp_get_current_user()) {
                $commitMessage = '['.$user->user_login.'] ' . $commitMessage;
                $user_name = [$user->user_firstname, $user->user_lastname];
                $user_name = implode(' ', array_filter(array_map('trim', $user_name)));
                $user_email = $user->user_email;
            }
            $repo->commit($commitMessage);
            $repo->push('origin', [$branch, '-u']);
            $change_url = GIT_REPOSITORY_HTTPS . '/-/commit/'.$repo->getLastCommitId();
            $body = wp_json_encode(slack_message($plugin_data['Name'], $plugin_data['Version'], null, $user_email, $user_name, $change_url));
            $response = wp_remote_post(
                GP_SLACK_WEBHOOK,
                [
                    'body'        => $body,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                    ]
                ]
            );
        } catch (\Exception $ex2) {
            if ($repo->hasChanges()) {
                $repo->execute('checkout', $plugins_abs_folder . $pluginName);
            }
            if ($repo->getCurrentBranchName() !== $currentBranch) {
                $brokenBranch = $repo->getCurrentBranchName();
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