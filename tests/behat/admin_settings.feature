@tool @tool_monitoring @javascript
Feature: Administering metrics via the admin panel.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | One      | manager1@example.com |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |

  Scenario: Viewing the metrics dashboard, enabling and disabling metrics.
    Given I am logged in as "manager1"
    And I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    Then "Overview of Available Metrics" "heading" should be visible
    # Check `courses` metric row.
    And I should see "gauge" in the "courses" "table_row"
    And I should see "Current number of courses" in the "courses" "table_row"
    And "Enable" "icon" in the "courses" "table_row" should be visible
    And "Disable" "icon" in the "courses" "table_row" should not be visible
    # Check `overdue_tasks` metric row.
    And I should see "gauge" in the "overdue_tasks" "table_row"
    And I should see "Number of tasks (excluding disabled ones) for which the next runtime is not in the future" in the "overdue_tasks" "table_row"
    And "Enable" "icon" in the "overdue_tasks" "table_row" should be visible
    And "Disable" "icon" in the "overdue_tasks" "table_row" should not be visible
    # Check `quiz_attempts_in_progress` metric row.
    And I should see "gauge" in the "quiz_attempts_in_progress" "table_row"
    And I should see "Number of ongoing quiz attempts with an approaching deadline" in the "quiz_attempts_in_progress" "table_row"
    And "Enable" "icon" in the "quiz_attempts_in_progress" "table_row" should be visible
    And "Disable" "icon" in the "quiz_attempts_in_progress" "table_row" should not be visible
    # Check `user_accounts` metric row.
    And I should see "gauge" in the "user_accounts" "table_row"
    And I should see "Current number of user accounts" in the "user_accounts" "table_row"
    And "Enable" "icon" in the "user_accounts" "table_row" should be visible
    And "Disable" "icon" in the "user_accounts" "table_row" should not be visible
    # Check `users_online` metric row.
    And I should see "gauge" in the "users_online" "table_row"
    And I should see "Number of users that have recently accessed the site" in the "users_online" "table_row"
    And "Enable" "icon" in the "users_online" "table_row" should be visible
    And "Disable" "icon" in the "users_online" "table_row" should not be visible

    When I call the Prometheus endpoint
    # Since all our metrics are disabled (by default), none of them should be there.
    Then I should not see "tool_monitoring_courses"
    And I should not see "tool_monitoring_overdue_tasks"
    And I should not see "tool_monitoring_quiz_attempts_in_progress"
    And I should not see "tool_monitoring_user_accounts"
    And I should not see "tool_monitoring_users_online"

    Given I am logged in as "manager1"
    # Enable the `courses` and `users_online` metrics.
    When I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    And I click on "Enable" "link" in the "courses" "table_row"
    Then "Enable" "icon" in the "courses" "table_row" should not be visible
    And "Disable" "icon" in the "courses" "table_row" should be visible
    And I click on "Enable" "link" in the "users_online" "table_row"
    Then "Enable" "icon" in the "users_online" "table_row" should not be visible
    And "Disable" "icon" in the "users_online" "table_row" should be visible

    When I call the Prometheus endpoint
    # The `courses` and `users_online` metrics should be there.
    Then I should see "# HELP tool_monitoring_courses"
    And I should see "# TYPE tool_monitoring_courses gauge"
    And I should see "tool_monitoring_courses{visible=\"true\"}"
    And I should see "tool_monitoring_courses{visible=\"false\"}"
    And I should see "# HELP tool_monitoring_users_online"
    And I should see "# TYPE tool_monitoring_users_online gauge"
    And I should see "tool_monitoring_users_online{time_window=\""
    # The others should still not be there.
    And I should not see "tool_monitoring_overdue_tasks"
    And I should not see "tool_monitoring_quiz_attempts_in_progress"
    And I should not see "tool_monitoring_user_accounts"

    Given I am logged in as "manager1"
    # Disable the `users_online` metric.
    When I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    And I click on "Disable" "link" in the "users_online" "table_row"
    Then "Disable" "icon" in the "users_online" "table_row" should not be visible
    And "Enable" "icon" in the "users_online" "table_row" should be visible

    When I call the Prometheus endpoint
    # The `courses` metrics should still be there.
    Then I should see "# HELP tool_monitoring_courses"
    And I should see "# TYPE tool_monitoring_courses gauge"
    And I should see "tool_monitoring_courses{visible=\"true\"}"
    And I should see "tool_monitoring_courses{visible=\"false\"}"
    # The `users_online` metric should no longer be there (and neither should the others).
    And I should not see "tool_monitoring_overdue_tasks"
    And I should not see "tool_monitoring_quiz_attempts_in_progress"
    And I should not see "tool_monitoring_user_accounts"
    And I should not see "tool_monitoring_users_online"

  Scenario: Configuring users_online metric.
    Given I am logged in as "manager1"
    And I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    Then "Overview of Available Metrics" "heading" should be visible
    When I click on "Enable" "link" in the "users_online" "table_row"
    And I call the Prometheus endpoint
    Then I should see "tool_monitoring_users_online{time_window=\"60s\"} "
    And I should see "tool_monitoring_users_online{time_window=\"300s\"} "
    And I should see "tool_monitoring_users_online{time_window=\"900s\"} "
    And I should see "tool_monitoring_users_online{time_window=\"3600s\"} "

    Given I am logged in as "manager1"
    And I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    Then "Configure" "icon" in the "users_online" "table_row" should be visible
    When I click on "Configure" "link" in the "users_online" "table_row"
    Then "Configure Metric" "heading" should exist
    And the field "Time window (seconds)" matches value "60, 300, 900, 3600"
    When I set the field "Time window (seconds)" to "1, 2, 3"
    And I click on "Save changes" "button"
    Then "Overview of Available Metrics" "heading" should be visible
    When I call the Prometheus endpoint
    Then I should see "tool_monitoring_users_online{time_window=\"1s\"} "
    And I should see "tool_monitoring_users_online{time_window=\"2s\"} "
    And I should see "tool_monitoring_users_online{time_window=\"3s\"} "
