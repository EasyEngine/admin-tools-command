Feature: admin-tools command line interface
  Help, argument validation and site resolution of `ee admin-tools enable|disable`.

  Scenario: The admin-tools command lists its subcommands
    When I run 'bin/ee help admin-tools'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      Manages admin-tools on a site.
      """
      And STDOUT of the last command should contain:
      """
        disable      Disables admin-tools on given site.
        enable       Enables admin tools on site.
      """

  Scenario Outline: Each subcommand documents its synopsis
    When I run 'bin/ee help admin-tools <subcommand>'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      ee admin-tools <subcommand> [<site-name>] [--force]
      """

    Examples:
      | subcommand |
      | enable     |
      | disable    |

  Scenario: install is an internal step, not a public subcommand
    When I run 'bin/ee admin-tools install'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      'install' is not a registered subcommand of 'admin-tools'
      """

  Scenario Outline: Unknown flags are rejected
    When I run 'bin/ee admin-tools <subcommand> bh-admin-tools-command-1.test --foo'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      unknown --foo parameter
      """

    Examples:
      | subcommand |
      | enable     |
      | disable    |

  Scenario Outline: More than one site name is rejected
    When I run 'bin/ee admin-tools <subcommand> bh-admin-tools-command-1.test bh-admin-tools-command-2.test'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      Too many positional arguments
      """

    Examples:
      | subcommand |
      | enable     |
      | disable    |

  Scenario Outline: A site name is required outside a site directory
    When I run 'bin/ee admin-tools <subcommand>'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      Could not find the site you wish to run admin-tools <subcommand> command on.
      """

    Examples:
      | subcommand |
      | enable     |
      | disable    |

  Scenario Outline: An unknown site is rejected
    When I run 'bin/ee admin-tools <subcommand> bh-admin-tools-command-missing.test'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      Could not find the site you wish to run admin-tools <subcommand> command on.
      """
      And STDOUT of the last command should not contain:
      """
      Success: admin-tools
      """

    Examples:
      | subcommand |
      | enable     |
      | disable    |

  Scenario Outline: A disabled site is rejected
    Given the site 'bh-admin-tools-command-3.test' exists, created with '--type=php'
      And the site 'bh-admin-tools-command-3.test' is disabled
    When I run 'bin/ee admin-tools <subcommand> bh-admin-tools-command-3.test <flags>'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      Site bh-admin-tools-command-3.test does not exist / is not enabled.
      """
      And the site 'bh-admin-tools-command-3.test' should have 'admin_tools' set to '0' in the database
      And the file '/opt/easyengine/sites/bh-admin-tools-command-3.test/docker-compose-admin.yml' should not exist

    Examples:
      | subcommand | flags   |
      | enable     |         |
      | enable     | --force |
      | disable    |         |
      | disable    | --force |

  Scenario: An html site does not support admin-tools
    Given the site 'bh-admin-tools-command-2.test' exists, created with '--type=html'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-2.test'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      Site of type html does not support admin-tools.
      """
      And the site 'bh-admin-tools-command-2.test' should have 'admin_tools' set to '0' in the database
      And the file '/opt/easyengine/sites/bh-admin-tools-command-2.test/docker-compose-admin.yml' should not exist
      And the 'nginx' container of site 'bh-admin-tools-command-2.test' should not have a mount at '/var/www/htdocs/ee-admin'

  Scenario: Disabling on an html site without --force reports it as already disabled
    Given the site 'bh-admin-tools-command-2.test' exists, created with '--type=html'
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-2.test'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      admin-tools already seem to be disabled for bh-admin-tools-command-2.test
      """

  # Fixed in v4.13.2 (EasyEngine/admin-tools-command#54), regression check: disable used to ignore the `docker-compose up -d nginx php` result, so on a site without a php service it prints success.
  Scenario: Force-disabling on an html site fails instead of reporting success
    Given the site 'bh-admin-tools-command-2.test' exists, created with '--type=html'
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-2.test --force'
    Then the last command should fail
      And STDOUT of the last command should not contain:
      """
      admin-tools disabled for bh-admin-tools-command-2.test site.
      """

  Scenario: A trailing slash on the site name is accepted
    Given the site 'bh-admin-tools-command-1.test' exists, created with '--type=php'
      And admin-tools are disabled on 'bh-admin-tools-command-1.test'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-1.test/'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      admin-tools enabled for bh-admin-tools-command-1.test site.
      """
      And the site 'bh-admin-tools-command-1.test' should have 'admin_tools' set to '1' in the database
    When I run 'bin/ee admin-tools disable bh-admin-tools-command-1.test/'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-1.test' should have 'admin_tools' set to '0' in the database

  Scenario: The site is detected from the current directory
    Given the site 'bh-admin-tools-command-1.test' exists, created with '--type=php'
      And admin-tools are disabled on 'bh-admin-tools-command-1.test'
    When I run 'bin/ee admin-tools enable' in the directory '/opt/easyengine/sites/bh-admin-tools-command-1.test/app'
    Then the last command should succeed
      And STDOUT of the last command should contain:
      """
      admin-tools enabled for bh-admin-tools-command-1.test site.
      """
      And the site 'bh-admin-tools-command-1.test' should have 'admin_tools' set to '1' in the database
    When I run 'bin/ee admin-tools disable' in the directory '/opt/easyengine/sites/bh-admin-tools-command-1.test'
    Then the last command should succeed
      And the site 'bh-admin-tools-command-1.test' should have 'admin_tools' set to '0' in the database

  Scenario: A wrong site name inside a site directory suggests the right one
    Given the site 'bh-admin-tools-command-1.test' exists, created with '--type=php'
    When I run 'bin/ee admin-tools enable bh-admin-tools-command-missing.test' in the directory '/opt/easyengine/sites/bh-admin-tools-command-1.test/app'
    Then the last command should fail
      And STDERR of the last command should contain:
      """
      bh-admin-tools-command-missing.test is not a valid site-name. Did you mean `ee admin-tools enable bh-admin-tools-command-1.test`?
      """
