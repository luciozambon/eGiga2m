
function initConf(conf) {
	// hcExportService = "http://ecsproxy.elettra.trieste.it:8080/highcharts-export-web/";
	if (conf=='<first-configuration>') {
		plotService = "./lib/service/hdb_plot_service.php?conf=<first-configuration>";
		treeService = "./lib/service/hdb_tree_service.php?conf=<first-configuration>";
		exportService = "<your_absolute_path>/lib/service/hdb_export_service.php?conf=<first-configuration>"
	}
	else if (conf=='<second-configuration>') {
		plotService = "./lib/service/hdbpp_plot_service.php?conf=<second-configuration>";
		treeService = "./lib/service/hdbpp_tree_service.php?conf=<second-configuration>";
		exportService = "<your_absolute_path>/lib/service/hdbpp_export_service.php?conf=<second-configuration>"
	}
	else {
	// this is the default configuration, comment it if unnecessary
	plotService = "./lib/service/hdbpp_plot_service.php?conf=";
	treeService = "./lib/service/hdbpp_tree_service.php?conf=";
	exportService = "./lib/service/hdb_export_service.php?conf="; // please switch hdb_export_service with hdbpp_export_service if necessary
        }
}

/* 
  // optional "see also" button, used to open other sites, for instance other installations of eGiga2m
  // start and stop parameters can be propagated to the targeted links
  var see_also = 
    "<li><a href='add here link'>add here a label</a></li>" +
    "<li class='divider'></li>" +
    "<li><a href='./egiga2m.html?conf=your_configuration&start=<!--start-->&stop=<!--stop-->'>your_configuration</a></li>";
*/

  // events are displaied only in HighChars version
  // var events = ['error', 'alarm', 'command', 'button'];
  var events = [];
  
  // extract time series name from data dropped on tree widget
  // this extraction is dependent on the environment so it must be custom for your system
  function extractTimeseries(dropData) {
    myproperty = dropData.split('</property>');
    mystring = myproperty[0].split('string>');
    myts = mystring[1].split('<');
    return myts[0];
  }
