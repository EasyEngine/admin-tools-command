Feature: Installation of the shared admin-tools
  The private install() step runs on every enable: it validates ee-tools.json and installs each missing tool into /opt/easyengine/admin-tools (index, phpinfo, pma, pra, opcache).

  Background:
    Given the site 'bh-admin-tools-command-15.test' exists, created with '--type=php'
      And admin-tools are disabled on 'bh-admin-tools-command-15.test'

  Scenario: Enabling with everything installed installs nothing
    Given admin-tools are enabled on 'bh-admin-tools-command-15.test'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-15.test --force'
    Then the last command should succeed
      And STDOUT of the last command should not contain:
      """
      Installing
      """

  # Moves the live tools directory away (running containers keep their bind mount) and puts it back afterwards.
  @destructive @network
  Scenario: A fresh install downloads and configures every tool
    Given the path '/opt/easyengine/admin-tools' is moved aside
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-15.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Installing admin-tools. This may take some time.
      """
      And STDOUT of the last command should contain:
      """
      Success: Installed index successfully.
      """
      And STDOUT of the last command should contain:
      """
      Success: Installed phpinfo successfully.
      """
      And STDOUT of the last command should contain:
      """
      Success: Installed pma successfully.
      """
      And STDOUT of the last command should contain:
      """
      Success: Installed pra successfully.
      """
      And STDOUT of the last command should contain:
      """
      Success: Installed opcache successfully.
      """
      And STDOUT of the last command should contain:
      """
      Success: admin-tools enabled for bh-admin-tools-command-15.test site.
      """
      And the file '/opt/easyengine/admin-tools/index.php' should contain "$site_data_file = '/etc/easyengine/admin-tools/site.json';"
      And the file '/opt/easyengine/admin-tools/index.php' should not contain 'ee.sqlite'
      And the file '/opt/easyengine/admin-tools/phpinfo.php' should contain 'phpinfo()'
      And the file '/opt/easyengine/admin-tools/opcache-gui.php' should exist
      And the file '/opt/easyengine/admin-tools/pma/config.inc.php' should match "/\$cfg\['blowfish_secret'\] = '[A-Za-z0-9]{32}';/"
      And the file '/opt/easyengine/admin-tools/pma/index.php' should exist
      And the file '/opt/easyengine/admin-tools/pra/index.php' should exist
      And the file '/opt/easyengine/admin-tools/pra/includes/config.sample.inc.php' should exist
      And the file '/opt/easyengine/admin-tools/pra/includes/config.inc.php' should contain "include 'config.sample.inc.php';"
      And the directory '/opt/easyengine/admin-tools/pra/vendor/src' should exist
    Given I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-15.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    When I request 'http://bh-admin-tools-command-15.test/ee-admin/pma/' until it returns 200 within 60 seconds
      And the response body should contain 'phpMyAdmin'
    When I request 'http://bh-admin-tools-command-15.test/ee-admin/opcache-gui.php' until it returns 200 within 60 seconds

  @destructive
  Scenario: Only a missing tool is reinstalled
    Given the path '/opt/easyengine/admin-tools/phpinfo.php' is moved aside
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-15.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Installing phpinfo
      """
      And STDOUT of the last command should contain:
      """
      Success: Installed phpinfo successfully.
      """
      And STDOUT of the last command should not contain:
      """
      Installing admin-tools. This may take some time.
      """
      And STDOUT of the last command should not contain:
      """
      Installing pma
      """
      And the file '/opt/easyengine/admin-tools/phpinfo.php' should contain 'phpinfo()'

  @destructive
  Scenario: A missing index is regenerated from the template
    Given the path '/opt/easyengine/admin-tools/index.php' is moved aside
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-15.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Success: Installed index successfully.
      """
      And the file '/opt/easyengine/admin-tools/index.php' should contain "$site_data_file = '/etc/easyengine/admin-tools/site.json';"
      And the file '/opt/easyengine/admin-tools/index.php' should not contain 'ee.sqlite'

  # Edits ee-tools.json in the ee checkout under test (moved aside and put back afterwards).
  @destructive
  Scenario Outline: An invalid ee-tools.json aborts before the site is changed
    Given the file 'vendor/easyengine/admin-tools-command/ee-tools.json' in the ee checkout contains '<content>'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-15.test'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      <error>
      """
      And the site 'bh-admin-tools-command-15.test' should have 'admin_tools' set to '0' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-15.test' should not have a mount at '/var/www/htdocs/ee-admin'

    Examples:
      | content                             | error                                                     |
      |                                     | admin-tools file is empty. Can't proceed further.         |
      | {                                   | Error decoding admin-tools file.                          |
      | {}                                  | No data found in admin-tools file. Can't proceed further. |
      | {"foo": {"url": "", "version": ""}} | No method found to install foo. Aborting.                 |
