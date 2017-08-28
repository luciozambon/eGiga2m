# eGiga2m Installation
In order to install eGiga2m you must provide a [LAMP](https://en.wikipedia.org/wiki/LAMP_%28software_bundle%29) (or equivalent) server.

You must copy all eGiga2m files in a apache server folder and configure 2 files: ./egiga2m_conf.js and ./lib/service/*_conf.php.

In ./egiga2m_conf.js you can configure one or more databases of different type, the default is a single HDB++ database.

In ./lib/service/hdbpp_conf.php (or ./lib/service/hdb_conf.php) you must provide the parameters necessary to connect to your database.

## HighCharts
If you have a HighCharts licence you must copy HighCharts in folder ./lib/highcharts and/or uncomment appropriate link at the end of file eGiga2m.html

## Events
If you would not support events add this line:
const SKIP_EVENT = true;
