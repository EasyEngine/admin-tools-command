@destructive
Feature: admin-tools with and without the global default_admin_tools auth
  These scenarios change the server-wide auth (auth_users/auth_ips rows for `default` and `default_admin_tools`, htpasswd and acl files). Each snapshots that state first and the context restores it byte for byte after the scenario, then regenerates and reloads nginx-proxy.

  Background:
    Given the site 'bh-admin-tools-command-14.test' exists, created with '--type=php'
      And I snapshot the global http auth state

  Scenario: Enabling creates the admin-tools auth and whitelist when none exist
    Given admin-tools are disabled on 'bh-admin-tools-command-14.test'
      And no global http auth or whitelist exists
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-14.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Success: Global admin-tools auth added. Use `ee auth list global` to view credentials.
      """
      And the http auth for 'default_admin_tools' should have the user 'easyengine' in the database
      And the http auth for 'default' should not exist in the database
      And the whitelist for 'default' should contain '{frontend_subnet}' in the database
      And the file '/opt/easyengine/services/nginx-proxy/htpasswd/default_admin_tools' should contain 'easyengine:'
      And the file '/opt/easyengine/services/nginx-proxy/vhost.d/default_acl' should contain 'allow {frontend_subnet};'
    Given I send requests via the public IP
    When I request 'http://bh-admin-tools-command-14.test/ee-admin/phpinfo.php' until it returns 401 with a 'WWW-Authenticate' header containing 'Admin Tools' within 60 seconds
    Given I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-14.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Then the response body should contain 'PHP Version'
    Given I use no basic auth
      And I send requests via 127.0.0.1
    When I request 'http://bh-admin-tools-command-14.test/ee-admin/phpinfo.php'
    Then the response code should be 200

  Scenario: Enabling keeps an existing admin-tools auth
    Given admin-tools are disabled on 'bh-admin-tools-command-14.test'
      And the http auth for 'default_admin_tools' should exist in the database
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-14.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Global auth exists on admin-tools. Use `ee auth list global` to view credentials.
      """
      And STDOUT of the last command should not contain:
      """
      Global admin-tools auth added.
      """
    When I run 'bin/ee auth list global --format=json'
    Then the last command should succeed
      And STDOUT of the last command should be a JSON list with an entry where 'site_url' is 'Admin Tools Auth'

  Scenario: A global auth protects /ee-admin/ when there is no admin-tools auth
    Given admin-tools are disabled on 'bh-admin-tools-command-14.test'
      And no global http auth or whitelist exists
    When I run 'bin/ee auth create global --user=bh-atc-global --pass=bh-atc-global-pass'
    Then the last command should succeed
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-14.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Global auth exists on admin-tools. Use `ee auth list global` to view credentials.
      """
      And the http auth for 'default_admin_tools' should not exist in the database
      And the file '/opt/easyengine/services/nginx-proxy/htpasswd/default_admin_tools' should not exist
    Given I send requests via the public IP
    When I request 'http://bh-admin-tools-command-14.test/ee-admin/phpinfo.php' until it returns 401 with a 'WWW-Authenticate' header containing 'Admin Tools' within 60 seconds
    Given I use basic auth with user 'bh-atc-global' and password 'bh-atc-global-pass'
    When I request 'http://bh-admin-tools-command-14.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Then the response body should contain 'PHP Version'

  # Fixed in v4.13.2 (EasyEngine/auth-command#60), regression check: `ee auth create global` renames the admin-tools auth row to `default`, so the admin-tools auth disappears.
  Scenario: Creating a global auth keeps the admin-tools auth
    Given admin-tools are enabled on 'bh-admin-tools-command-14.test'
    When I run 'bin/ee auth create global --user=bh-atc-global --pass=bh-atc-global-pass'
    Then the last command should succeed
      And the http auth for 'default_admin_tools' should have the user 'easyengine' in the database
      And the file '/opt/easyengine/services/nginx-proxy/htpasswd/default_admin_tools' should exist

  # Documents that the proxy protects /ee-admin/ only while an htpasswd file exists; ee itself always re-creates it on enable.
  Scenario: Without any global auth file the tools are served to anyone
    Given admin-tools are enabled on 'bh-admin-tools-command-14.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-14.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Given no global http auth or whitelist exists
      And I use no basic auth
    When I request 'http://bh-admin-tools-command-14.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Then the response body should contain 'PHP Version'
      And the response header 'WWW-Authenticate' should not contain 'Admin Tools'

  Scenario: Re-enabling with --force restores the missing admin-tools auth
    Given admin-tools are enabled on 'bh-admin-tools-command-14.test'
      And no global http auth or whitelist exists
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-14.test --force'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Global admin-tools auth added.
      """
      And the http auth for 'default_admin_tools' should have the user 'easyengine' in the database
    Given I send requests via the public IP
    When I request 'http://bh-admin-tools-command-14.test/ee-admin/phpinfo.php' until it returns 401 within 60 seconds
