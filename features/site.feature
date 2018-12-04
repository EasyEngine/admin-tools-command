Feature: Admin Tools Command

  Scenario: ee executable is command working correctly
    Given 'bin/ee' is installed
    When I run 'bin/ee'
    Then STDOUT should return something like
    """
    NAME

      ee
    """

  Scenario: Check admin-tools command is present
    When I run 'bin/ee admin-tools'
    Then STDOUT should return exactly
    """
    usage: ee admin-tools disable [<site-name>] [--force]
       or: ee admin-tools enable [<site-name>] [--force]

    See 'ee help admin-tools <command>' for more information on a specific command.
    """

  Scenario: Create php site
    When I run 'bin/ee site create php.test --type=php'
    Then After delay of 2 seconds
      And The site 'php.test' should have index file
      And Request on 'php.test' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |

  Scenario: Check admin-tools enable sub command is present
    When I run 'bin/ee admin-tools enable'
    Then STDERR should return something like
    """
    Error: Could not find the site you wish to run admin-tools enable command on.
    Either pass it as an argument: `ee admin-tools enable <site-name>`
    or run `ee admin-tools enable` from inside the site folder.
    """

  Scenario: Enable admin-tools for PHP site
    When I run 'bin/ee admin-tools enable php.test'
    Then STDOUT should return exactly
    """
    Global auth exists on admin-tools. Use `ee auth list global` to view credentials.
    Installing admin-tools. This may take some time.
    Installing index
    Success: Installed index successfully.
    Installing phpinfo
    Success: Installed phpinfo successfully.
    Installing pma
    Success: Installed pma successfully.
    Installing pra
    Success: Installed pra successfully.
    Installing opcache
    Success: Installed opcache successfully.
    Success: admin-tools enabled for php.test site.
    """
      And After delay of 5 seconds
      And The ee should have admin-tools directory in root
      And The admin-tools should have index file
      And Request on 'php.test/ee-admin/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/nginx_status/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/opcache-gui.php' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/phpinfo.php' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/ping/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/pma/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/status/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |

  Scenario: Disable PHP site
    When I run 'bin/ee site disable php.test'
    Then STDOUT should return exactly
    """
    Disabling site php.test.
    Success: Site php.test disabled.
    """
      And Request on 'php.test' should contain following headers:
        | header                                       |
        | HTTP/1.1 503 Service Temporarily Unavailable |

  Scenario: Enable PHP site and check ee-admin should Work as expected
    When I run 'bin/ee site enable php.test'
    Then STDOUT should return exactly
    """
    Enabling site php.test.
    Success: Site php.test enabled.
    Running post enable configurations.
    Starting site's services.
    Global auth exists on admin-tools. Use `ee auth list global` to view credentials.
    Success: admin-tools enabled for php.test site.
    Success: Post enable configurations complete.
    """
      And After delay of 5 seconds
      And Request on 'php.test/ee-admin/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/nginx_status/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/opcache-gui.php' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/phpinfo.php' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/ping/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/pma/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'php.test/ee-admin/status/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |

  Scenario: Disable admin-tools for PHP site
    When I run 'bin/ee admin-tools disable php.test'
    Then STDOUT should return exactly
    """
    Success: admin-tools disabled for php.test site.
    """
      And After delay of 5 seconds
      And Request on 'php.test/ee-admin/' should contain following headers:
        | header                 |
        | HTTP/1.1 403 Forbidden |
      And Request on 'php.test/ee-admin/opcache-gui.php' should contain following headers:
        | header                 |
        | HTTP/1.1 404 Not Found |
      And Request on 'php.test/ee-admin/phpinfo.php' should contain following headers:
        | header                 |
        | HTTP/1.1 404 Not Found |
      And Request on 'php.test/ee-admin/pma/' should contain following headers:
        | header                 |
        | HTTP/1.1 403 Forbidden |

  Scenario: Create WordPress site
    When I run 'bin/ee site create wp.test --type=wp'
    Then After delay of 2 seconds
      And The site 'wp.test' should have WordPress
      And Request on 'wp.test' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |

  Scenario: Enable admin-tools for WordPress site
    When I run 'bin/ee admin-tools enable wp.test'
    Then STDOUT should return exactly
    """
    Global auth exists on admin-tools. Use `ee auth list global` to view credentials.
    Success: admin-tools enabled for wp.test site.
    """
      And After delay of 5 seconds
      And Request on 'wp.test/ee-admin/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/nginx_status/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/opcache-gui.php' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/phpinfo.php' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/pra/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/ping/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/pma/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/status/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |

  Scenario: Disable WordPress site
    When I run 'bin/ee site disable wp.test'
    Then STDOUT should return exactly
    """
    Disabling site wp.test.
    Success: Site wp.test disabled.
    """
      And Request on 'wp.test' should contain following headers:
        | header                                       |
        | HTTP/1.1 503 Service Temporarily Unavailable |

  Scenario: Enable WordPress site and check ee-admin should Work as expected
    When I run 'bin/ee site enable wp.test'
    Then STDOUT should return exactly
    """
    Enabling site wp.test.
    Success: Site wp.test enabled.
    Running post enable configurations.
    Starting site's services.
    Global auth exists on admin-tools. Use `ee auth list global` to view credentials.
    Success: admin-tools enabled for wp.test site.
    Success: Post enable configurations complete.
    """
      And After delay of 5 seconds
      And Request on 'wp.test/ee-admin/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/nginx_status/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/opcache-gui.php' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/phpinfo.php' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/ping/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/pma/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |
      And Request on 'wp.test/ee-admin/status/' should contain following headers:
        | header           |
        | HTTP/1.1 200 OK  |

  Scenario: Disable admin-tools for WordPress site
    When I run 'bin/ee admin-tools disable wp.test'
    Then STDOUT should return exactly
    """
    Success: admin-tools disabled for wp.test site.
    """
      And After delay of 5 seconds
      And Request on 'wp.test/ee-admin/' should contain following headers:
        | header                 |
        | HTTP/1.1 403 Forbidden |
      And Request on 'wp.test/ee-admin/opcache-gui.php' should contain following headers:
        | header                 |
        | HTTP/1.1 404 Not Found |
      And Request on 'wp.test/ee-admin/phpinfo.php' should contain following headers:
        | header                 |
        | HTTP/1.1 404 Not Found |
      And Request on 'wp.test/ee-admin/pma/' should contain following headers:
        | header                 |
        | HTTP/1.1 403 Forbidden |
