<?php
/**
 *  LICENSE: This file is subject to the terms and conditions defined in
 *  file 'LICENSE', which is part of this source code package.
 *
 * @copyright 2025 Copyright(c) - All rights reserved.
 * @author    Austin Patkos / APex / mwire Development Team
 * @package   mwire-crypto-payments
 * @version   0.0.2
 */

/**
 * Class Updater
 */
class eGiftCertificate_Updater
{
    private $slug; // plugin slug
    private $pluginData; // plugin data
    private $username = 'APexBlockchainDevelopments'; // GitHub username
    private $repo = 'mwire-wordpress-plugin'; // GitHub repo name
    private $pluginFile;
    private $githubAPIResult;

    // 🔐 Hardcoded token for internal use
    private $token = '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';

    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Get information regarding our plugin from WordPress
     */
    private function initPluginData()
    {
        $this->slug = plugin_basename($this->pluginFile);
        $this->pluginData = get_plugin_data($this->pluginFile);
    }

    /**
     * Get information regarding our plugin from GitHub
     */
    private function getRepoReleaseInfo() {
        if (!empty($this->githubAPIResult)) {
            return;
        }

        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";

        $response = wp_remote_get($url, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'token ' . $this->token,
                'User-Agent'    => 'WordPress/' . get_bloginfo('version'),
            ],
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $this->githubAPIResult = json_decode(wp_remote_retrieve_body($response));
    }

    /**
     * Push in plugin version information to get the update notification
     *
     * @param $transient
     *
     * @return mixed
     */
    public function setTransient($transient) {
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        if (!isset($this->githubAPIResult->tag_name)) return $transient;

        $version = $this->pluginData['Version'];
        $releaseVersion = ltrim($this->githubAPIResult->tag_name, 'v');
        $doUpdate = version_compare($releaseVersion, $version);

        if ($doUpdate === 1) {
            $package = $this->githubAPIResult->zipball_url . '?access_token=' . $this->token;

            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $releaseVersion;
            $obj->url = $this->pluginData["PluginURI"];
            $obj->package = $package;
            $transient->response[$this->slug] = $obj;
        } else {
            unset($transient->response[$this->slug]);
        }

        return $transient;
    }

    /**
     * Push in plugin version information to display in the details lightbox
     *
     * @param $false
     * @param $action
     * @param $response
     *
     * @return bool
     */
    public function setPluginInfo($false, $action, $response)
    {
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        if (empty($response->slug) || $response->slug !== $this->slug) {
            return false;
        }

        $response->last_updated = $this->githubAPIResult->published_at;
        $response->slug         = $this->slug;
        $response->plugin_name  = $this->pluginData['Name'];
        $response->version      = $this->pluginData['Version'];
        $response->author       = $this->pluginData['AuthorName'];
        $response->homepage     = $this->pluginData['PluginURI'];
        $response->requires_php = $this->pluginData['RequiresPHP'];
        $response->download_link = $this->githubAPIResult->zipball_url . '?access_token=' . $this->token;

        return $response;
    }

    /**
     * Perform additional actions to successfully install our plugin
     *
     * @param $true
     * @param $hook_extra
     * @param $result
     *
     * @return mixed
     */

    public function postInstall($true, $hook_extra, $result) {
        $this->initPluginData();

        $wasActivated = is_plugin_active($this->slug);

        if ($wasActivated) {
            activate_plugin($this->slug);
        }

        return $result;
    }
}
