Feature: admin-tools over HTTP
  Every tool under /ee-admin/ is served by the site's nginx/php and protected by the global admin-tools auth (htpasswd/default_admin_tools) in nginx-proxy. Requests go to the host's public IP so the global whitelist (the frontend network's subnet, usually 10.1.0.0/16) does not apply; 127.0.0.1 arrives from the network's gateway and bypasses auth.

  Scenario: /ee-admin/ is protected even before admin-tools are enabled
    Given the site 'bh-admin-tools-command-9.test' exists, created with '--type=php'
      And admin-tools are disabled on 'bh-admin-tools-command-9.test'
      And I send requests via the public IP
    When I request 'http://bh-admin-tools-command-9.test/ee-admin/phpinfo.php' until it returns 401 within 60 seconds
    Then the response header 'WWW-Authenticate' should contain 'Admin Tools'
    Given I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-9.test/ee-admin/phpinfo.php'
    Then the response code should be 404
    When I request 'http://bh-admin-tools-command-9.test/ee-admin/'
    Then the response code should be 404

  Scenario Outline: Status pages are served by the site config without enabling admin-tools
    Given the site 'bh-admin-tools-command-9.test' exists, created with '--type=php'
      And admin-tools are disabled on 'bh-admin-tools-command-9.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-9.test<path>' until it returns 200 within 60 seconds
    Then the response body should contain '<text>'

    Examples:
      | path                   | text               |
      | /ee-admin/nginx_status | Active connections |
      | /ee-admin/status       | pool:              |
      | /ee-admin/ping         | pong               |

  Scenario Outline: Each tool is served with the admin-tools credentials
    Given the site 'bh-admin-tools-command-9.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-9.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-9.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    When I request 'http://bh-admin-tools-command-9.test<path>' until it returns 200 within 60 seconds
    Then the response body should match '<regex>'

    Examples:
      | path                      | regex               |
      | /ee-admin/                | /href="phpinfo.php"/ |
      | /ee-admin/index.php       | /href="pma"/        |
      | /ee-admin/phpinfo.php     | /PHP Version/       |
      | /ee-admin/opcache-gui.php | /opcache/i          |
      | /ee-admin/pma/            | /phpMyAdmin/        |
      | /ee-admin/nginx_status    | /Active connections/ |
      | /ee-admin/status          | /pool:/             |
      | /ee-admin/ping            | /pong/              |

  Scenario Outline: Each tool requires the admin-tools credentials from outside the whitelist
    Given the site 'bh-admin-tools-command-9.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-9.test'
      And I send requests via the public IP
    When I request 'http://bh-admin-tools-command-9.test<path>' until it returns 401 within 60 seconds
    Then the response header 'WWW-Authenticate' should contain 'Admin Tools'
      And the response body should not contain '<secret>'
    Given I use basic auth with user 'easyengine' and password 'bh-wrong-password'
    When I request 'http://bh-admin-tools-command-9.test<path>'
    Then the response code should be 401

    Examples:
      | path                      | secret             |
      | /ee-admin/                | phpinfo.php        |
      | /ee-admin/phpinfo.php     | PHP Version        |
      | /ee-admin/opcache-gui.php | opcache            |
      | /ee-admin/pma/            | phpMyAdmin         |
      | /ee-admin/pra/            | phpRedisAdmin      |
      | /ee-admin/nginx_status    | Active connections |
      | /ee-admin/status          | pool:              |
      | /ee-admin/ping            | pong               |

  Scenario Outline: Whitelisted clients reach the tools without credentials
    Given the site 'bh-admin-tools-command-9.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-9.test'
      And I send requests via 127.0.0.1
    When I request 'http://bh-admin-tools-command-9.test<path>' until it returns 200 within 60 seconds
    Then the response body should match '<regex>'

    Examples:
      | path                  | regex         |
      | /ee-admin/phpinfo.php | /PHP Version/ |
      | /ee-admin/pma/        | /phpMyAdmin/  |

  Scenario: The index lists the tools but not phpRedisAdmin on a site without cache
    Given the site 'bh-admin-tools-command-9.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-9.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-9.test/ee-admin/' until it returns 200 within 60 seconds
    Then the response body should contain '<a href="nginx_status">nginx_status</a>'
      And the response body should contain '<a href="opcache-gui.php">opcache-gui.php</a>'
      And the response body should contain '<a href="phpinfo.php">phpinfo.php</a>'
      And the response body should contain '<a href="ping">ping</a>'
      And the response body should contain '<a href="pma">pma</a>'
      And the response body should contain '<a href="status">status</a>'
      And the response body should not contain '<a href="pra">'
      And the response body should not contain '<a href="mailhog">'
      And the response body should not contain '<a href="index.php">'
      And the response body should not contain 'Warning'

  Scenario: Tools on a site with cache bypass the redis page cache
    Given the site 'bh-admin-tools-command-10.test' exists, created with '--type=php --cache'
      And admin-tools are enabled on 'bh-admin-tools-command-10.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-10.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Then the response body should contain 'PHP Version'
      And I remember the response body
    When I request 'http://bh-admin-tools-command-10.test/ee-admin/phpinfo.php'
    Then the response code should be 200
      And the response header 'X-SRCache-Fetch-Status' should not contain 'HIT'
      And the response body should differ from the remembered one

  Scenario: Tools are protected and served over HTTPS
    Given the site 'bh-admin-tools-command-11.test' exists, created with '--type=php --ssl=self'
      And admin-tools are enabled on 'bh-admin-tools-command-11.test'
      And I send requests via the public IP
    When I request 'https://bh-admin-tools-command-11.test/ee-admin/phpinfo.php' until it returns 401 within 60 seconds
    Then the response header 'WWW-Authenticate' should contain 'Admin Tools'
    Given I use the global admin-tools credentials
    When I request 'https://bh-admin-tools-command-11.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    Then the response body should contain 'PHP Version'
    When I request 'https://bh-admin-tools-command-11.test/ee-admin/pma/' until it returns 200 within 60 seconds
    Then the response body should contain 'phpMyAdmin'
    When I request 'http://bh-admin-tools-command-11.test/ee-admin/phpinfo.php'
    Then the response code should be 301
      And the response header 'Location' should contain 'https://bh-admin-tools-command-11.test/ee-admin/phpinfo.php'

  # A global `default` auth would also be in the site's htpasswd, so this needs the dedicated admin-tools auth.
  Scenario: Site http auth does not grant access to /ee-admin/
    Given the site 'bh-admin-tools-command-12.test' exists, created with '--type=php'
      And the http auth for 'default_admin_tools' should exist in the database
      And admin-tools are enabled on 'bh-admin-tools-command-12.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-12.test/ee-admin/phpinfo.php' until it returns 200 within 60 seconds
    When I run 'bin/ee auth create bh-admin-tools-command-12.test --user=bh-site-user --pass=bh-site-pass'
    Then the last command should succeed
    # The proxy reload is asynchronous; old workers can still answer for a moment.
    Given I use no basic auth
    When I request 'http://bh-admin-tools-command-12.test/' until it returns 401 within 60 seconds
    Given I use basic auth with user 'bh-site-user' and password 'bh-site-pass'
    When I request 'http://bh-admin-tools-command-12.test/'
    Then the response code should not be 401
    When I request 'http://bh-admin-tools-command-12.test/ee-admin/phpinfo.php'
    Then the response code should be 401
      And the response header 'WWW-Authenticate' should contain 'Admin Tools'
    Given I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-12.test/ee-admin/phpinfo.php'
    Then the response code should be 200
      And the response body should contain 'PHP Version'
    When I request 'http://bh-admin-tools-command-12.test/'
    Then the response code should be 401
    When I run 'bin/ee auth delete bh-admin-tools-command-12.test --user=bh-site-user'
    Then the last command should succeed

  # Fixed in v4.13.2 (EasyEngine/admin-tools-command#54): index.php looked the site up by exact HTTP_HOST, so on an alias domain it found no row and dropped the pra (and mailhog) links.
  Scenario: The index on an alias domain lists the same tools as on the main domain
    Given the site 'bh-admin-tools-command-13.test' exists, created with '--type=php --cache --alias-domains=bh-admin-tools-command-13-alias.test'
      And admin-tools are enabled on 'bh-admin-tools-command-13.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I request 'http://bh-admin-tools-command-13.test/ee-admin/' until it returns 200 within 60 seconds
    Then the response body should contain '<a href="pra">pra</a>'
    When I request 'http://bh-admin-tools-command-13-alias.test/ee-admin/' until it returns 200 within 60 seconds
    Then the response body should contain '<a href="pra">pra</a>'
      And the response body should not contain 'Warning'

  # Fixed in v4.13.2 (EasyEngine/admin-tools-command#54): index.php also concatenated HTTP_HOST into its SQL. A Host under a wildcard alias reaches the site with quotes
  # and parentheses intact and whitelisted clients need no credentials, so it was a boolean oracle over the EE database.
  # No `>` in the payloads: nginx 1.29+ rejects it in Host with a 400 before the request reaches the site.
  Scenario: A crafted Host header under a wildcard alias does not change the index lookup
    Given the site 'bh-admin-tools-command-21.test' exists, created with '--type=php --cache --alias-domains="*.bh-admin-tools-command-21-wild.test"'
      And admin-tools are enabled on 'bh-admin-tools-command-21.test'
      And I send requests via 127.0.0.1
      And I use no basic auth
    When I request 'http://bh-admin-tools-command-21.test/ee-admin/' until it returns 200 within 60 seconds
    Then the response body should contain '<a href="pra">pra</a>'
    When I request 'http://bh-admin-tools-command-21.test/ee-admin/' with the Host header "x'AND(1=2)AND'.bh-admin-tools-command-21-wild.test"
    Then the response code should be 200
      And the response body should contain '<a href="pra">pra</a>'
      And the response body should not contain 'Warning'
    When I request 'http://bh-admin-tools-command-21.test/ee-admin/' with the Host header "x'OR(SELECT(count(*))FROM(auth_users))OR'.bh-admin-tools-command-21-wild.test"
    Then the response code should be 200
      And the response body should contain '<a href="pra">pra</a>'
      And the response body should not contain 'Warning'

  Scenario: The index lists MailHog only while it is enabled on the site
    Given the site 'bh-admin-tools-command-20.test' exists, created with '--type=php'
      And admin-tools are enabled on 'bh-admin-tools-command-20.test'
      And I send requests via the public IP
      And I use the global admin-tools credentials
    When I run 'bin/ee mailhog enable bh-admin-tools-command-20.test --force'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-20.test' should have 'mailhog_enabled' set to '1' in the database
      And the file '/opt/easyengine/sites/bh-admin-tools-command-20.test/config/admin-tools/site.json' should contain '"mailhog":true'
    When I request 'http://bh-admin-tools-command-20.test/ee-admin/' until it returns 200 within 60 seconds
    Then the response body should contain '<a href="mailhog">mailhog</a>'
    When I run 'bin/ee mailhog disable bh-admin-tools-command-20.test'
    Then the last command should succeed
      And the file '/opt/easyengine/sites/bh-admin-tools-command-20.test/config/admin-tools/site.json' should contain '"mailhog":false'
    When I request 'http://bh-admin-tools-command-20.test/ee-admin/'
    Then the response code should be 200
      And the response body should not contain '<a href="mailhog">'
