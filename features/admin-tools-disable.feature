Feature: Disable admin-tools on a site
  `ee admin-tools disable` recreates nginx and php from docker-compose.yml alone (dropping the admin mounts) and clears sites.admin_tools.

  Background:
    Given the site 'bh-admin-tools-command-8.test' exists, created with '--type=php'

  Scenario: Disable admin-tools after enabling them
    Given admin-tools are enabled on 'bh-admin-tools-command-8.test'
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-8.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Success: admin-tools disabled for bh-admin-tools-command-8.test site.
      """
      And the site 'bh-admin-tools-command-8.test' should have 'admin_tools' set to '0' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-8.test' should be running
      And the 'php' container of site 'bh-admin-tools-command-8.test' should be running
      And the 'nginx' container of site 'bh-admin-tools-command-8.test' should not have a mount at '/var/www/htdocs/ee-admin'
      And the 'php' container of site 'bh-admin-tools-command-8.test' should not have a mount at '/var/www/htdocs/ee-admin'
      And the 'nginx' container of site 'bh-admin-tools-command-8.test' should not have a mount at '/opt/easyengine/db/ee.sqlite'
      And the 'php' container of site 'bh-admin-tools-command-8.test' should not have a mount at '/opt/easyengine/db/ee.sqlite'
      And the directory '/opt/easyengine/admin-tools' should exist

  Scenario: Tools are no longer served once disabled, status pages still are
    Given admin-tools are enabled on 'bh-admin-tools-command-8.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-8.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-8.test'
    Then the last command should succeed
    When I request 'http://bh-admin-tools-command-8.test/ee-admin/phpinfo.php' until it returns 404 within 60 seconds
    When I request 'http://bh-admin-tools-command-8.test/ee-admin/pma/'
    Then the response code should be 404
    When I request 'http://bh-admin-tools-command-8.test/ee-admin/ping'
    Then the response code should be 200
      And the response body should contain 'pong'

  Scenario: Disabling twice without --force is rejected
    Given admin-tools are disabled on 'bh-admin-tools-command-8.test'
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-8.test'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      admin-tools already seem to be disabled for bh-admin-tools-command-8.test
      """
      And the site 'bh-admin-tools-command-8.test' should have 'admin_tools' set to '0' in the database

  Scenario: Force-disabling a site that never had admin-tools succeeds
    # A site of its own: the shared one was enabled by earlier scenarios and keeps docker-compose-admin.yml.
    Given the site 'bh-admin-tools-command-23.test' exists, created with '--type=php'
    Then the site 'bh-admin-tools-command-23.test' should have 'admin_tools' set to '0' in the database
      And the file '/opt/easyengine/sites/bh-admin-tools-command-23.test/docker-compose-admin.yml' should not exist
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-23.test --force'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Success: admin-tools disabled for bh-admin-tools-command-23.test site.
      """
      And the site 'bh-admin-tools-command-23.test' should have 'admin_tools' set to '0' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-23.test' should be running
      And the 'php' container of site 'bh-admin-tools-command-23.test' should be running
      And the 'nginx' container of site 'bh-admin-tools-command-23.test' should not have a mount at '/var/www/htdocs/ee-admin'

  Scenario: Enable, disable and enable again
    Given admin-tools are disabled on 'bh-admin-tools-command-8.test'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-8.test'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-8.test' should have 'admin_tools' set to '1' in the database
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-8.test'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-8.test' should have 'admin_tools' set to '0' in the database
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-8.test'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-8.test' should have 'admin_tools' set to '1' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-8.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
      And the 'php' container of site 'bh-admin-tools-command-8.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
