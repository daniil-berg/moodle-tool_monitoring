@tool @tool_monitoring @monitoringexporter_prometheus @javascript
Feature: Exporting tagged metrics

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | One      | manager1@example.com |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |

  Scenario: Using the tag query parameter filters out metrics without the specified tags
    Given I am logged in as "manager1"
    # Enable multiple metrics.
    And I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    And I click on "Enable" "link" in the "courses" "table_row"
    And I click on "Enable" "link" in the "overdue_tasks" "table_row"
    And I click on "Enable" "link" in the "quiz_attempts_in_progress" "table_row"
    And I click on "Enable" "link" in the "user_accounts" "table_row"
    And I click on "Enable" "link" in the "users_online" "table_row"
    # Add tags to some of them.
    And I click on "Configure" "link" in the "user_accounts" "table_row"
    When I set the following fields to these values:
      | Tags | Foo, Bar |
    And I click on "Save changes" "button"
    Then "Overview of Available Metrics" "heading" should be visible
    When I click on "Configure" "link" in the "overdue_tasks" "table_row"
    And I set the following fields to these values:
      | Tags | Bar, Baz |
    And I click on "Save changes" "button"
    Then "Overview of Available Metrics" "heading" should be visible
    # Ensure the endpoint delivers all, when no tags are specified.
    When I call the Prometheus endpoint
    Then I should see "# HELP tool_monitoring_courses"
    And I should see "# TYPE tool_monitoring_courses gauge"
    And I should see "# HELP tool_monitoring_overdue_tasks"
    And I should see "# TYPE tool_monitoring_overdue_tasks gauge"
    And I should see "# HELP tool_monitoring_quiz_attempts_in_progress"
    And I should see "# TYPE tool_monitoring_quiz_attempts_in_progress gauge"
    And I should see "# HELP tool_monitoring_user_accounts"
    And I should see "# TYPE tool_monitoring_user_accounts gauge"
    And I should see "# HELP tool_monitoring_users_online"
    And I should see "# TYPE tool_monitoring_users_online gauge"
    # Called with the "Bar" tag, only the two metrics that carry that tag should be exported.
    When I call the Prometheus endpoint with the following query parameters:
      | tag | bar |
    Then I should see "# HELP tool_monitoring_overdue_tasks"
    And I should see "# TYPE tool_monitoring_overdue_tasks gauge"
    And I should see "# HELP tool_monitoring_user_accounts"
    And I should see "# TYPE tool_monitoring_user_accounts gauge"
    And I should not see "tool_monitoring_courses"
    And I should not see "tool_monitoring_quiz_attempts_in_progress"
    And I should not see "tool_monitoring_users_online"
    # Called with the "Bar" and the "Baz" tag, only one metric should be exported.
    When I call the Prometheus endpoint with the following query parameters:
      | tag | bar,baz |
    Then I should see "# HELP tool_monitoring_overdue_tasks"
    And I should see "# TYPE tool_monitoring_overdue_tasks gauge"
    And I should not see "tool_monitoring_courses"
    And I should not see "tool_monitoring_quiz_attempts_in_progress"
    And I should not see "tool_monitoring_user_accounts"
    And I should not see "tool_monitoring_users_online"
    # Called with all three tags, no metrics should be exported.
    When I call the Prometheus endpoint with the following query parameters:
      | tag | foo,bar,baz |
    Then I should not see "tool_monitoring_courses"
    And I should not see "tool_monitoring_overdue_tasks"
    And I should not see "tool_monitoring_quiz_attempts_in_progress"
    And I should not see "tool_monitoring_user_accounts"
    And I should not see "tool_monitoring_users_online"
    # Order should not matter and the parameter should be case-insensitive.
    When I call the Prometheus endpoint with the following query parameters:
      | tag | bAr,Foo |
    Then I should see "# HELP tool_monitoring_user_accounts"
    And I should see "# TYPE tool_monitoring_user_accounts gauge"
    And I should not see "tool_monitoring_courses"
    And I should not see "tool_monitoring_overdue_tasks"
    And I should not see "tool_monitoring_quiz_attempts_in_progress"
    And I should not see "tool_monitoring_users_online"
    # The endpoint should respond with a 422 status code, when an invalid/non-existent tag is passed.
    When I call the Prometheus endpoint with the following query parameters:
      | tag | foo,quux |
    Then I should not see "tool_monitoring_courses"
    And I should not see "tool_monitoring_overdue_tasks"
    And I should not see "tool_monitoring_quiz_attempts_in_progress"
    And I should not see "tool_monitoring_user_accounts"
    And I should not see "tool_monitoring_users_online"
    And I should see "422"
    # Now add a tag that was previously missing.
    Given I am logged in as "manager1"
    And I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    And I click on "Configure" "link" in the "user_accounts" "table_row"
    When I set the following fields to these values:
      | Tags | Foo, Bar, Quux |
    And I click on "Save changes" "button"
    Then "Overview of Available Metrics" "heading" should be visible
    When I call the Prometheus endpoint with the following query parameters:
      | tag | foo,quux |
    Then I should see "# HELP tool_monitoring_user_accounts"
    And I should see "# TYPE tool_monitoring_user_accounts gauge"
    # Remove tag instances.
    Given I am logged in as "manager1"
    And I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    And I click on "Configure" "link" in the "user_accounts" "table_row"
    When I set the following fields to these values:
      | Tags | Bar |
    And I click on "Save changes" "button"
    Then "Overview of Available Metrics" "heading" should be visible
    When I call the Prometheus endpoint with the following query parameters:
      | tag | foo |
    Then I should not see "tool_monitoring_user_accounts"
