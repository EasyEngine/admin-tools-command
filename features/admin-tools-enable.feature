Feature: Enable admin-tools on a site
  `ee admin-tools enable` installs the shared tools under /opt/easyengine/admin-tools, writes docker-compose-admin.yml, mounts the tools read-only into the site's nginx and php containers and sets sites.admin_tools.

  Scenario: Enable admin-tools on a php site
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are disabled on 'bh-admin-tools-command-4.test'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-4.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Global auth exists on admin-tools. Use `ee auth list global` to view credentials.
      """
      And STDOUT of the last command should contain:
      """
      Success: admin-tools enabled for bh-admin-tools-command-4.test site.
      """
      And the site 'bh-admin-tools-command-4.test' should have 'admin_tools' set to '1' in the database
      And the global admin-tools auth should exist

  Scenario: Enabling writes docker-compose-admin.yml with read-only mounts and site env
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-4.test'
    Then the file '/opt/easyengine/sites/bh-admin-tools-command-4.test/docker-compose-admin.yml' should contain '"/opt/easyengine/admin-tools:/var/www/htdocs/ee-admin:ro"'
      And the file '/opt/easyengine/sites/bh-admin-tools-command-4.test/docker-compose-admin.yml' should contain '"/opt/easyengine/sites/bh-admin-tools-command-4.test/config/admin-tools:/etc/easyengine/admin-tools:ro"'
      And the file '/opt/easyengine/sites/bh-admin-tools-command-4.test/docker-compose-admin.yml' should not contain 'ee.sqlite'
      And the file '/opt/easyengine/sites/bh-admin-tools-command-4.test/docker-compose-admin.yml' should contain 'REDIS_HOST='
      And the file '/opt/easyengine/sites/bh-admin-tools-command-4.test/docker-compose-admin.yml' should contain 'DB_HOST='

  Scenario Outline: Enabling mounts the tools read-only into nginx and php, and never the EE database
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-4.test'
    Then the '<service>' container of site 'bh-admin-tools-command-4.test' should be running
      And the '<service>' container of site 'bh-admin-tools-command-4.test' should have a mount from '/opt/easyengine/admin-tools' at '/var/www/htdocs/ee-admin'
      And the '<service>' container of site 'bh-admin-tools-command-4.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
      And the '<service>' container of site 'bh-admin-tools-command-4.test' should not have a mount at '/opt/easyengine/db/ee.sqlite'
      And the '<service>' container of site 'bh-admin-tools-command-4.test' should have the label 'com.docker.compose.project.config_files' containing 'docker-compose-admin.yml'

    Examples:
      | service |
      | nginx   |
      | php     |

  # Fixed in v4.13.2 (EasyEngine/admin-tools-command#54): the whole EE database used to be mounted into the site's php container.
  Scenario: The site's php container has no access to the EasyEngine DB, only to its own admin-tools flags
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-4.test'
    Then the 'php' container of site 'bh-admin-tools-command-4.test' should have a mount from '/opt/easyengine/sites/bh-admin-tools-command-4.test/config/admin-tools' at '/etc/easyengine/admin-tools'
      And the 'php' container of site 'bh-admin-tools-command-4.test' should have a read-only mount at '/etc/easyengine/admin-tools'
      And the 'nginx' container of site 'bh-admin-tools-command-4.test' should not have a mount at '/etc/easyengine/admin-tools'
      And the file '/opt/easyengine/sites/bh-admin-tools-command-4.test/config/admin-tools/site.json' should contain '{"mailhog":false,"page_cache":false}'
    When I run 'docker-compose exec -T --user www-data php sh -c "test ! -e /opt/easyengine && ls /etc/easyengine/admin-tools"' in the directory '/opt/easyengine/sites/bh-admin-tools-command-4.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      site.json
      """
    When I run 'docker-compose exec -T --user www-data php sh -c "touch /etc/easyengine/admin-tools/site.json"' in the directory '/opt/easyengine/sites/bh-admin-tools-command-4.test'
    Then the last command should fail

  Scenario: The flags file follows the site's page cache
    Given the site 'bh-admin-tools-command-5.test' exists, created with '--type=php --cache --with-db'
      And admin-tools are enabled on 'bh-admin-tools-command-5.test'
    Then the file '/opt/easyengine/sites/bh-admin-tools-command-5.test/config/admin-tools/site.json' should contain '"page_cache":true'
      And the 'php' container of site 'bh-admin-tools-command-5.test' should not have a mount at '/opt/easyengine/db/ee.sqlite'

  Scenario: The php container gets the site's redis and db hosts
    Given the site 'bh-admin-tools-command-5.test' exists, created with '--type=php --cache --with-db'
      And admin-tools are enabled on 'bh-admin-tools-command-5.test'
    Then the 'php' container of site 'bh-admin-tools-command-5.test' should have the environment variable 'REDIS_HOST' set to 'global-redis'
      And the 'php' container of site 'bh-admin-tools-command-5.test' should have the environment variable 'DB_HOST' set to 'global-db'
      And the file '/opt/easyengine/sites/bh-admin-tools-command-5.test/docker-compose-admin.yml' should contain 'REDIS_HOST=global-redis'
      And the file '/opt/easyengine/sites/bh-admin-tools-command-5.test/docker-compose-admin.yml' should contain 'DB_HOST=global-db'

  Scenario: The shared tools are installed under /opt/easyengine/admin-tools
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-4.test'
    Then the file '/opt/easyengine/admin-tools/index.php' should exist
      And the file '/opt/easyengine/admin-tools/index.php' should contain "$site_data_file = '/etc/easyengine/admin-tools/site.json';"
      And the file '/opt/easyengine/admin-tools/index.php' should not contain 'SQLite3'
      And the file '/opt/easyengine/admin-tools/index.php' should contain "$scan_dir = '/var/www/htdocs/ee-admin';"
      And the file '/opt/easyengine/admin-tools/phpinfo.php' should contain 'phpinfo()'
      And the file '/opt/easyengine/admin-tools/opcache-gui.php' should exist
      And the directory '/opt/easyengine/admin-tools/pma' should exist
      And the file '/opt/easyengine/admin-tools/pma/config.inc.php' should not contain '{{blowfish_secret}}'
      And the file '/opt/easyengine/admin-tools/pma/config.inc.php' should match "/\$cfg\['blowfish_secret'\] = '[A-Za-z0-9]{32}';/"
      And the file '/opt/easyengine/admin-tools/pma/config.inc.php' should contain "$cfg['Servers'][$i]['host'] = getenv('DB_HOST');"
      And the directory '/opt/easyengine/admin-tools/pra' should exist
      And the directory '/opt/easyengine/admin-tools/pra/vendor' should exist
      And the file '/opt/easyengine/admin-tools/pra/includes/config.inc.php' should contain "$server_host = getenv($prefix . 'HOST');"

  Scenario: Enabling twice without --force is rejected and changes nothing
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-4.test'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-4.test'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      admin-tools already seem to be enabled for bh-admin-tools-command-4.test
      """
      And the site 'bh-admin-tools-command-4.test' should have 'admin_tools' set to '1' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-4.test' should have a read-only mount at '/var/www/htdocs/ee-admin'

  Scenario: Enabling again with --force re-applies the configuration
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-4.test'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-4.test --force'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Success: admin-tools enabled for bh-admin-tools-command-4.test site.
      """
      And the site 'bh-admin-tools-command-4.test' should have 'admin_tools' set to '1' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-4.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
      And the 'php' container of site 'bh-admin-tools-command-4.test' should have a read-only mount at '/var/www/htdocs/ee-admin'

  Scenario: --force restores a site whose docker-compose-admin.yml was removed
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-4.test'
      And the path '/opt/easyengine/sites/bh-admin-tools-command-4.test/docker-compose-admin.yml' is moved aside
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-4.test --force'
    Then the last command should succeed
      And the file '/opt/easyengine/sites/bh-admin-tools-command-4.test/docker-compose-admin.yml' should contain '/var/www/htdocs/ee-admin:ro'

  Scenario: Site info shows the admin-tools URL once enabled
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-4.test'
    When I run 'bin/ee site info bh-admin-tools-command-4.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Access admin-tools
      """
      And STDOUT of the last command should contain:
      """
      http://bh-admin-tools-command-4.test/ee-admin/
      """

  Scenario: Site info does not show admin-tools when disabled
    Given the site 'bh-admin-tools-command-4.test' exists, created with '--type=php'
      And admin-tools are disabled on 'bh-admin-tools-command-4.test'
    When I run 'bin/ee site info bh-admin-tools-command-4.test'
    Then the last command should succeed
      And STDOUT of the last command should not contain:
      """
      Access admin-tools
      """

  Scenario: Enable admin-tools on a WordPress site
    Given the site 'bh-admin-tools-command-6.test' exists, created with '--type=wp'
      And admin-tools are disabled on 'bh-admin-tools-command-6.test'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-6.test'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-6.test' should have 'admin_tools' set to '1' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-6.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
      And the 'php' container of site 'bh-admin-tools-command-6.test' should have a read-only mount at '/var/www/htdocs/ee-admin'
    Given I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-6.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Then the response body should contain 'PHP Version'
    When I request 'http://bh-admin-tools-command-6.test/ee-admin/pma/' until it returns 200 within 60 seconds
      And the response body should contain 'phpMyAdmin'

  Scenario: Enable admin-tools on a php site with a public dir
    Given the site 'bh-admin-tools-command-7.test' exists, created with '--type=php --public-dir=public'
      And admin-tools are disabled on 'bh-admin-tools-command-7.test'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-7.test'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-7.test' should have 'admin_tools' set to '1' in the database
    Given I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-7.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Then the response body should contain 'PHP Version'
    When I request 'http://bh-admin-tools-command-7.test/ee-admin/'
    Then the response code should be 200
      And the response body should contain '<a href="phpinfo.php">phpinfo.php</a>'
