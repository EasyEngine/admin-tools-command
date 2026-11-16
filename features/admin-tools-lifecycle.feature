Feature: admin-tools across site lifecycle operations
  Site commands that recreate containers must keep the admin-tools mounts of a site that has sites.admin_tools set.

  Scenario: Disabling and enabling the site re-enables admin-tools
    Given the site 'bh-admin-tools-command-16.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-16.test'
    When I run 'bin/ee site disable bh-admin-tools-command-16.test'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-16.test' should have 'admin_tools' set to '1' in the database
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-16.test'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      Site bh-admin-tools-command-16.test does not exist / is not enabled.
      """
    When I run 'bin/ee site enable bh-admin-tools-command-16.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      admin-tools enabled for bh-admin-tools-command-16.test site.
      """
      And the site 'bh-admin-tools-command-16.test' should have 'admin_tools' set to '1' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-16.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
      And the 'php' container of site 'bh-admin-tools-command-16.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
    Given I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-16.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Then the response body should contain 'PHP Version'

  Scenario: Enabling a site without admin-tools does not enable them
    Given the site 'bh-admin-tools-command-16.test' exists, created with '--type=php'
      And admin-tools are disabled on 'bh-admin-tools-command-16.test'
    When I run 'bin/ee site disable bh-admin-tools-command-16.test'
    Then the last command should succeed
    When I run 'bin/ee site enable bh-admin-tools-command-16.test'
    Then the last command should succeed
      And STDOUT of the last command should not contain:
      """
      admin-tools enabled for
      """
      And the site 'bh-admin-tools-command-16.test' should have 'admin_tools' set to '0' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-16.test' should not have a mount at '/var/www/htdocs/ee-admin'

  Scenario: Refreshing the site keeps admin-tools
    Given the site 'bh-admin-tools-command-16.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-16.test'
    When I run 'bin/ee site refresh bh-admin-tools-command-16.test'
    Then the last command should succeed
      And the 'nginx' container of site 'bh-admin-tools-command-16.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
      And the 'php' container of site 'bh-admin-tools-command-16.test' should have a read-only mount at '/var/www/htdocs/ee-admin'

  Scenario: Restarting the site keeps admin-tools
    Given the site 'bh-admin-tools-command-16.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-16.test'
    When I run 'bin/ee site restart bh-admin-tools-command-16.test'
    Then the last command should succeed
      And the 'nginx' container of site 'bh-admin-tools-command-16.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
      And the 'php' container of site 'bh-admin-tools-command-16.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
    Given I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-16.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds

  Scenario: Deleting a site with admin-tools keeps the shared tools for other sites
    Given the site 'bh-admin-tools-command-19.test' exists, created with '--type=php'
      And the site 'bh-admin-tools-command-16.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-19.test'
      And admin-tools are enabled on 'bh-admin-tools-command-16.test'
    When I run 'bin/ee site delete bh-admin-tools-command-19.test --yes'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-19.test' should not exist
      And the file '/opt/easyengine/sites/bh-admin-tools-command-19.test/docker-compose-admin.yml' should not exist
      And the file '/opt/easyengine/admin-tools/index.php' should exist
      And the global admin-tools auth should exist
    Given I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-16.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
