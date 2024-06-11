<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;

class FeatureContext implements Context, SnippetAcceptingContext
{
    private $command_output;
    private static $site_created = false;

    /**
     * @Given I have created a WordPress site at :site
     */
    public function iHaveCreatedAWordpressSiteAtExampleCom()
    {
        if (!self::$site_created) {
            $site = "example.com";
            $site_directory = "/opt/easyengine/sites/$site";

            if (!file_exists($site_directory)) {
                $command = "ee site create $site --type=php";
                $this->command_output = shell_exec($command);

                $site_url = "http://$site";
                sleep(5);
                $site_accessible = $this->isSiteAccessible($site_url);

                if (!$site_accessible) {
                    throw new Exception("Failed to create WordPress site at $site. The site is not accessible.");
                }
            } else {
                echo "WordPress site at $site already exists. Skipping site creation.";
            }

            self::$site_created = true;
        }
    }

    /**
     * Check if a site is accessible
     *
     * @param string $site_url The URL of the site to check
     * @return bool True if the site is accessible, false otherwise
     */
    private function isSiteAccessible($site_url)
    {
        $headers = get_headers($site_url);
        return $headers && strpos($headers[0], '200') !== false;
    }

    /**
     * @When I run "ee admin-tools enable :site"
     */
    public function iRunEnableAdminTools($site)
    {
        $this->command_output = shell_exec("ee admin-tools enable $site");
    }

    /**
     * @When I run "ee admin-tools disable :site"
     */
    public function iRunDisableAdminTools($site)
    {
        $this->command_output = shell_exec("ee admin-tools disable $site");
    }

    /**
     * @Then I should be able to access :url
     */
    public function iShouldBeAbleToAccess($url)
    {
        sleep(5);
        $headers = @get_headers($url);
        if (!$headers || strpos($headers[0], '200') === false) {
            throw new Exception("Failed to access $url. Expected 200 status code, but got: " . (is_array($headers) ? implode(', ', $headers) : 'No headers received'));
        }
    }

    /**
     * @Then I should not be able to access :url
     */
    public function iShouldNotBeAbleToAccess($url)
    {
        sleep(5);
        $headers = @get_headers($url);
        if (!$headers || strpos($headers[0], '403') === false) {
            throw new Exception("Failed to access $url. Expected 403 status code, but got: " . (is_array($headers) ? implode(', ', $headers) : 'No headers received'));
        }
    }
}