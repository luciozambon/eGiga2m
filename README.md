# eGiga2m

eGiga2m is a web graphic data viewer.

Data is supposed to be organized as a set of unevenly spaced time series.

Time series are taken from a web service which typically extracts data from a structured database or are stored in a CSV file drag-and-dropped on the plot area.

Each time series is identified by a unique name and by an ID. Time series names are displayed in a hierarchical tree.

Users can configure a wide range of parameters. All settings are interfaced through URI parametrs. This allows to send unique links to graph or exported files and eGiga2m can be included or used by external resources.

Two graphic library are supported: Flot Charts has MIT licence; Highcharts offers more features.

Time series are supposed to be saved on event base, so the value is assured to be within an (hopefully) tiny interval around the last saved value unless a new value is saved. that's why the default line style is 'step'.

## Quick start
Users can select a time interval and one or more time series.

A time interval span from a start point to a stop point. The stop point is optional, if not specified the default is now.

Both start and stop point may be a fix date-time or a relative interval (e.g. last 7 days).

Time series are presented in a tree structure. This tree can be navigated till the leaf level.

Clicking on a leaf a time series will be plotted on the left axis .

Clicking again on the same time series will switch the plot to the right axis .

Clicking the third time on the same time series will remove it from the plot .

The "Show" button will create/update the plot using all the selected configurations.

## Examples

https://luciozambon.altervista.org/egiga2m/?start=last%207%20days&ts=2,1,1;1382,1,1&style=spline

https://luciozambon.altervista.org/egiga2m/xMulti.html

## Documentation
some documentation is available at 
https://luciozambon.altervista.org/egiga2m/doc/index.html
