Feature: admin-tools on a WordPress site
  WordPress sites get their status pages from site-type-wp's own nginx config, and the tools from the same admin-tools.conf as php sites.

  Background:
    Given the site 'bh-admin-tools-command-22.test' exists, created with '--type=wp'

  Scenario Outline: Each tool and status page is served on a WordPress site
    Given admin-tools are enabled on 'bh-admin-tools-command-22.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-22.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    When I request 'http://bh-admin-tools-command-22.test<path>'
    Then the response code should be 200
      And the response body should match '<regex>'

    Examples:
      | path                      | regex                |
      | /ee-admin/                | /href="phpinfo.php"/ |
      | /ee-admin/opcache-gui.php | /opcache/i           |
      | /ee-admin/pma/            | /phpMyAdmin/         |
      | /ee-admin/nginx_status    | /Active connections/ |
      | /ee-admin/status          | /pool:/              |
      | /ee-admin/ping            | /pong/               |

  Scenario: Tools are no longer served on a WordPress site once disabled, status pages still are
    Given admin-tools are enabled on 'bh-admin-tools-command-22.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-22.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-22.test'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Success: admin-tools disabled for bh-admin-tools-command-22.test site.
      """
      And the site 'bh-admin-tools-command-22.test' should have 'admin_tools' set to '0' in the database
      And the 'nginx' container of site 'bh-admin-tools-command-22.test' should not have a mount at '/var/www/htdocs/ee-admin'
      And the 'php' container of site 'bh-admin-tools-command-22.test' should not have a mount at '/var/www/htdocs/ee-admin'
    When I request 'http://bh-admin-tools-command-22.test/ee-admin/phpinfo.php' until it returns 404 within 60 seconds
    When I request 'http://bh-admin-tools-command-22.test/ee-admin/opcache-gui.php'
    Then the response code should be 404
    When I request 'http://bh-admin-tools-command-22.test/ee-admin/pma/'
    Then the response code should be 404
    When I request 'http://bh-admin-tools-command-22.test/ee-admin/status'
    Then the response code should be 200
      And the response body should contain 'pool:'
    When I request 'http://bh-admin-tools-command-22.test/ee-admin/ping'
    Then the response code should be 200
      And the response body should contain 'pong'
