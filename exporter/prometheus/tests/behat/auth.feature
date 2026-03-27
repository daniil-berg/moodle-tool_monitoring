@tool @tool_monitoring @monitoringexporter_prometheus @javascript
Feature: Securing the exporter endpoint.

  Scenario: Requiring a token query parameter for authentication.
    Given I am logged in as admin
    And I navigate to "Plugins > Admin tools > Monitoring > Overview" in site administration
    Then "Overview of Available Metrics" "heading" should be visible
    And I click on "Enable" "link" in the "courses" "table_row"

    # Verify the endpoint works and returns the metric.
    When I call the Prometheus endpoint
    Then I should see "# HELP tool_monitoring_courses"
    And I should see "# TYPE tool_monitoring_courses gauge"
    And I should see "tool_monitoring_courses{visible=\"true\"}"
    And I should see "tool_monitoring_courses{visible=\"false\"}"

    # Set an access token.
    Given I am logged in as admin
    When I navigate to "Plugins > Admin tools > Monitoring > Prometheus Exporter" in site administration
    Then "Prometheus Exporter" "heading" should be visible
    And I should see "monitoringexporter_prometheus | prometheus_token"
    And the field "Token" matches value ""
    When I click on "Click to enter text" "link" in the "admin-prometheus_token" "region"
    And I set the field "Token" to "abcdef"
    And I click on "Save changes" "button"
    Then "Prometheus Exporter" "heading" should be visible

    # Verify the endpoint no longer returns the metric. Assume the 403 code is displayed somewhere.
    When I call the Prometheus endpoint
    Then I should not see "tool_monitoring"
    And I should see "403"

    # Passing the access token should grant access and the endpoint should return the metric again.
    When I call the Prometheus endpoint with the following query parameters:
      | token | abcdef |
    Then I should see "# HELP tool_monitoring_courses"
    And I should see "# TYPE tool_monitoring_courses gauge"
    And I should see "tool_monitoring_courses{visible=\"true\"}"
    And I should see "tool_monitoring_courses{visible=\"false\"}"
