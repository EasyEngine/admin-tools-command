Feature: EasyEngine Admin Tools

  Scenario: Enable and disable admin tools with EasyEngine
    Given I have created a WordPress site at "example.com"
    When I run "ee admin-tools enable example.com"
    Then I should be able to access "http://example.com/ee-admin/"
    When I run "ee admin-tools disable example.com"
    Then I should not be able to access "http://example.com/ee-admin/"