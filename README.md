# Monitoring #

`tool_monitoring` provides a generic API for arbitrary monitoring tools,
where they can define and collect metrics about the Moodle instance.

## Metrics

This plugin already provides a **set of useful metrics** like:
- Number of courses
- Registered or active users
- Spawned and overdue adhoc/scheduled tasks
- Quiz attempts in progress

## Exporter

This plugin also comes with a **predefined exporter for Prometheus** which delivers the metrics data fitting for Prometheus.\
Example:
```
# HELP num_user_count Number of total registered users
# TYPE num_user_count gauge
num_user_count 123
```

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/admin/tool/monitoring

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Usage

Connect your monitoring software with your favored API endpoint of this plugin.

### Prometheus

Go to Site Administration / Plugins / Monitoring / Exporter Prometheus and provide a secure **token** to be used to access the API endpoint.


The Prometheus endpoint can be reached under:

```
https://{your-moodle-site.com}/monitoringexporter_prometheus/{tag}/metrics?token=yoursecuretoken
```

## Exporter Subplugins

To support other monitoring software you can add subplugins in the `exporter/` folder. \
For more information on this see the developer documentation in the `docs/` folder.`

## Hook Implementation in other plugins

Other plugins can also provide their own metrics by implementing the hook callback of this plugin. \
For more information on this see the developer documentation in the `docs/` folder.`

## License ##

2025 MootDACH DevCamp \
Daniel Fainberg <d.fainberg@tu-berlin.de> \
Martin Gauk <martin.gauk@tu-berlin.de> \
Sebastian Rupp <sr@artcodix.com> \
Malte Schmitz <mal.schmitz@uni-luebeck.de> \
Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
