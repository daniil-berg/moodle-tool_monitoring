@tool @tool_monitoring @javascript
Feature: Tagging metrics

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | One      | manager1@example.com |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |

  Scenario Outline: Tag options <should_or_should_not> be shown when the tag area is <tagarea_enabled_or_disabled> and usetags is set to <usetags>
    Given the following config values are set as admin:
      | usetags | <usetags> |
    And the metrics tag area is "<tagarea_enabled_or_disabled>"
    And I am logged in as "manager1"
    When I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    Then "Manage tags" "link" <should_or_should_not> be visible
    When I click on "Configure" "link" in the "users_online" "table_row"
    Then "Configure Metric" "heading" should exist
    And "Tags" "field" <should_or_should_not> be visible
    And I <should_or_should_not> see "Manage standard tags"
    Examples:
      | usetags | tagarea_enabled_or_disabled| should_or_should_not |
      | 0       | disabled                   | should not           |
      | 0       | enabled                    | should not           |
      | 1       | disabled                   | should not           |
      | 1       | enabled                    | should               |

  Scenario: Tags can be added to metrics and the overview can be filtered by tags
    Given I am logged in as "manager1"
    And I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    # Add tags to one metric.
    And I click on "Configure" "link" in the "user_accounts" "table_row"
    When I set the following fields to these values:
      | Tags | Foo, Bar |
    And I click on "Save changes" "button"
    Then "Overview of Available Metrics" "heading" should be visible
    # Check that they show up next to it in the overview.
    And "Foo" "link" in the "user_accounts" "table_row" should be visible
    And "Bar" "link" in the "user_accounts" "table_row" should be visible
    # Add tags to another metric.
    When I click on "Configure" "link" in the "overdue_tasks" "table_row"
    And I set the following fields to these values:
      | Tags | Bar, Baz |
    And I click on "Save changes" "button"
    Then "Overview of Available Metrics" "heading" should be visible
    # Check that the right tags appear next to the right metrics in the overview.
    And "Bar" "link" in the "overdue_tasks" "table_row" should be visible
    And "Baz" "link" in the "overdue_tasks" "table_row" should be visible
    And "Foo" "link" should not exist in the "overdue_tasks" "table_row"
    And "Baz" "link" should not exist in the "user_accounts" "table_row"
    # Filter by a tag that the two metrics have in common.
    When I click on "Bar" "link"
    Then "Overview of Available Metrics" "heading" should be visible
    And "overdue_tasks" "table_row" should be visible
    And "user_accounts" "table_row" should be visible
    And "courses" "table_row" should not exist
    And "quiz_attempts_in_progress" "table_row" should not exist
    And "users_online" "table_row" should not exist
    # Additionally filter by a tag that only one of the metric carries.
    When I click on "Baz" "link"
    Then "Overview of Available Metrics" "heading" should be visible
    And "overdue_tasks" "table_row" should be visible
    And "user_accounts" "table_row" should not exist
    And "courses" "table_row" should not exist
    And "quiz_attempts_in_progress" "table_row" should not exist
    And "users_online" "table_row" should not exist
    # Remove that tag filter and add another.
    When I click on "× Baz" "link"
    Then "Overview of Available Metrics" "heading" should be visible
    And "overdue_tasks" "table_row" should be visible
    And "user_accounts" "table_row" should be visible
    And "courses" "table_row" should not exist
    And "quiz_attempts_in_progress" "table_row" should not exist
    And "users_online" "table_row" should not exist
    When I click on "Foo" "link"
    Then "Overview of Available Metrics" "heading" should be visible
    And "user_accounts" "table_row" should be visible
    And "overdue_tasks" "table_row" should not exist
    And "courses" "table_row" should not exist
    And "quiz_attempts_in_progress" "table_row" should not exist
    And "users_online" "table_row" should not exist
    # Reset all filters.
    When I click on "Show all metrics" "link"
    Then "Overview of Available Metrics" "heading" should be visible
    And "courses" "table_row" should be visible
    And "overdue_tasks" "table_row" should be visible
    And "quiz_attempts_in_progress" "table_row" should be visible
    And "user_accounts" "table_row" should be visible
    And "users_online" "table_row" should be visible
