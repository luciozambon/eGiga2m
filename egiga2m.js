// ------------
// egiga2m.js
// ------------

// todo: 
// add configuration of global: {useUTC: false}
// add plugin
// add more documentation
// add other decimation method (mean, average, random...)
// add smart periods on update
// add regression https://github.com/Tom-Alexander/regression-js
// use mysqlnd https://secure.php.net/manual/en/book.mysqlnd.php http://www.php.net/manual/en/features.connection-handling.php https://stackoverflow.com/questions/7582485/kill-mysql-query-on-user-abort email GS 9/1/2018

	var version = '1.18.6';
	console.log('eGiga2m, version', version);
	var visited = [];
	var activePoint = -1; // used by tooltip keyboard navigation
	var mychart = -1;
	var myPlot = -1;
	var myRequest;
	var myStart;
	var myStop;
	var exportURL = 'undefined';
	var plotURL = 'undefined';
	var jsonURL = 'undefined';
	var exportFormat = 'csv';
	var exportBlanks = '';
	var exportDecimation = '';
	var curves;
	var myPlotClass = [];
	var myHistory = [];
	var myHistoryCounter = -1;
	var plotService = './lib/service/plot_service.php?conf=';
	var treeService = './lib/service/tree_service.php?conf=';
	var updateService = '';
	var exportService = './lib/service/export_service.php?conf=';
	var hcExportService = 'http://export.highcharts.com';
	var zoom_speed = document.getElementById('zoomFactor')? document.getElementById('zoomFactor').value : 10;
	var globalVal;
	var globalLabel;
	var minVal;
	var maxVal;
	var tsLabel;
	var animationDelay;
	var frameNum;
	var updatePlot = 0;
	var decimation = 'maxmin';
	var decimationSamples = 1000;
	var updateBackground = 300;
	var updateRequest = '';
	var updateDecimation = 0;
	var updateCounter = 0;
	var updateId = false;
	var downtimeCheck = false;
	var visible = [];
	var parameters = {'correlation': null};
	var ftree = null;
	var flotOptions = {
		series: { lines: { show: true } },
		grid: { hoverable: true, clickable: true},
		xaxis: { tickLength: 5 },
		yaxis: { position: "left" },
		legend: { position: 'sw' },
		canvas: true
	};
	var csvImport = {name: '', lines: []};
	var fade_level = new Array();
	for (j=0; j<events.length; j++) fade_level[events[j]] = 1; 

	// INIT
	var $_GET = getQueryParams(document.location.search);
	if (typeof($_GET['conf']) !== 'undefined') {
		document.getElementById('conf').value = $_GET['conf'];
		initConf($_GET['conf']);
	}
	else {
		initConf(false);
	}
	if (typeof($_GET['debug']) !== 'undefined') console.log('init, $_GET', $_GET);
	if (localStorage.length && typeof(localStorage.csvrepo) !== 'undefined') {
		document.getElementById('csvrepo').value = localStorage.csvrepo;
		document.getElementById('retain').value = localStorage.csvrepo;
	}
	if (typeof($_GET['plotservice']) !== 'undefined') {
		// & = %26
		plotService = $_GET['plotservice'];
	}
	for (var i in parameters) {
		if (typeof($_GET[i]) !== 'undefined') {
			parameters[i] = $_GET[i];
			document.getElementById(i).checked = true;
		}
	}
	if (typeof($_GET['visible']) !== 'undefined') {
		visible = $_GET['visible'].split(';');
	}
	if (typeof($_GET['decimation']) !== 'undefined') {
		decimation = $_GET['decimation'];
	}
	if (typeof($_GET['hideOverMaxmin']) !== 'undefined') {
		if (document.getElementById('hideOverMaxmin')) document.getElementById('hideOverMaxmin').checked = true;
	}
	if (typeof($_GET['downtimeCheck']) !== 'undefined') {
		downtimeCheck = true;
	}
	if (document.getElementById('downtimeCheck')) document.getElementById('downtimeCheck').checked = downtimeCheck;
	if (document.getElementById('decimation_'+decimation)) document.getElementById('decimation_'+decimation).selected = true;
	if (typeof($_GET['decimation_samples']) !== 'undefined') {
		decimationSamples = $_GET['decimation_samples'];
	}
	if (document.getElementById('decimationSamples')) document.getElementById('decimationSamples').value = decimationSamples;

	if (typeof(isHighChartsInstalled) !== 'boolean') {
		document.getElementById('show_hc').checked = false;
		document.getElementById('show_error').checked = false;
		document.getElementById('hc_label').style.display = 'none';
		document.getElementById('style_output').style.display = 'none';
	}
	if (!isHighChartsInstalled) {
		if (document.getElementById('show_chartjs')) {
			document.getElementById('show_chartjs').checked = true;
		}
		else if (document.getElementById('show_flot')) {
			document.getElementById('show_flot').checked = true;
		}
	}
	if (typeof(formula_edit) === 'undefined') initPlot($_GET);
	if (typeof(window.$_GET['tsLabel']) !== 'undefined') {
		tsLabel = window.$_GET['tsLabel'].split(';');
	}
	initTree($_GET);
	if (typeof(see_also) === 'string' && document.getElementById('see_also')) {
		// document.getElementById('see_also').innerHTML = see_also;
		document.getElementById('see_also_button').style.display = 'inline';
	}

	// update "see also" menu 
	function update_see_also() {
		var replace = {
			'<!--start-->': document.getElementById('startInput').value,
			'<!--stop-->': document.getElementById('stopInput').value,
			'<!--decimation-->': decimation
		};
		document.getElementById('see_also').innerHTML = strtr(see_also, replace);
	}

	function popup(location, title, params) {
		this.popupWindow = null;
		this.open = function () {
			if (!this.popupWindow ) {	// has not yet been defined
				this.popupWindow = window.open(location, title, params);
			}
			else {   // has been defined
				if (!this.popupWindow .closed) {  // still open
					this.popupWindow .focus();
				}
				else {
					this.popupWindow = window.open(location, title, params);
				}
			}
		};
	}

	if (typeof($_GET['formula']) !== 'undefined') {
		var myPopup = new popup('./formula_editor.html?conf='+$_GET['conf'], 'formula', 'width=670,height=800');
		myPopup.open();
	}

	function print_r(theObj) {
		var myPopup = new popup('', 'mywindow', 'width=600,height=400');
		myPopup.open();
		if (theObj.constructor == Array || theObj.constructor == Object) {
			myPopup.popupWindow.document.write("<ul>")
			for (var p in theObj) {
				if (theObj[p].constructor == Array || theObj[p].constructor == Object) {
					myPopup.popupWindow.document.write("<li>["+p+"] => "+typeof(theObj)+"</li>");
					myPopup.popupWindow.document.write("<ul>")
					print_r(theObj[p]);
					myPopup.popupWindow.document.write("</ul>")
				} 
				else {
					myPopup.popupWindow.document.write("<li>["+p+"] => "+theObj[p]+"</li>");
				}
			}
			myPopup.popupWindow.document.write("</ul>")
		}
	}

	function openFormula() {
		var myFormula = new popup('./formula_editor.html?conf='+document.getElementById('conf').value, 'formula', 'width=670,height=800'); 
		myFormula.open();
	}
	function returnFormula(formula) {
		document.getElementById('ts').value = formula+';'+document.getElementById('ts').value;
	}

	// Detect document visibility and slow down the refresh process if in background
	// Set the name of the hidden property and the change event for visibility
	var hidden, visibilityChange; 
	if (typeof document.hidden !== "undefined") { // Opera 12.10 and Firefox 18 and later support 
		hidden = "hidden";
		visibilityChange = "visibilitychange";
	} else if (typeof document.mozHidden !== "undefined") {
		hidden = "mozHidden";
		visibilityChange = "mozvisibilitychange";
	} else if (typeof document.msHidden !== "undefined") {
		hidden = "msHidden";
		visibilityChange = "msvisibilitychange";
	} else if (typeof document.webkitHidden !== "undefined") {
		hidden = "webkitHidden";
		visibilityChange = "webkitvisibilitychange";
	}
	var videoElement = document.getElementById("videoElement");
	function handleVisibilityChange() {
		updateDecimation = document[hidden]? Math.ceil(updateBackground / updatePlot): updateDecimation = 0;
	}
	// Warn if the browser doesn't support addEventListener or the Page Visibility API
	if (typeof document.addEventListener === "undefined" || typeof document[hidden] === "undefined") {
		alert("This feature requires a browser, such as Google Chrome or Firefox, that supports the Page Visibility API.");
	} else {
		// Handle page visibility change   
		document.addEventListener(visibilityChange, handleVisibilityChange, false);
	}
	/*
	function onBlur() {
		updateDecimation = 20;
		console.debug(updateDecimation);
	};
	function onFocus(){
		updateDecimation = 0;
		console.debug(updateDecimation);
	};
	*/

	function exportImage(format) {
		if (document.getElementById('show_chartjs').checked) {
			var tmp_canvas = document.getElementById('canvas');
			var dt = tmp_canvas.toDataURL("image/png");
			dt = dt.replace(/^data:image\/[^;]*/, 'data:application/octet-stream');
			dt = dt.replace(/^data:application\/octet-stream/, 'data:application/octet-stream;headers=Content-Disposition%3A%20attachment%3B%20filename=eGiga2m.png');
			document.getElementById('pngExport').href = dt;
			document.getElementById('pngExport').download = "eGiga2m."+format;
			console.log(dt);
		}
		else if (document.getElementById('show_flot').checked) {
			Canvas2Image.saveAsPNG(myPlot.getCanvas());
		}
		else {
			var legendLeft = $('div.highcharts-legend')[0].style.left.replace('px','')-0;
			var legendTop = $('div.highcharts-legend')[0].style.top.replace('px','')-0;
			var legendItems = $('div.highcharts-legend-item');
			var legend = '';
			for (var i=0; i<legendItems.length; i++) {
				var left = legendItems[i].style.left.replace('px','') - 0;
				var top = legendItems[i].style.top.replace('px','') - 0;
				legend = legend + '<text x="'+(legendLeft+left+25)+'" y="'+(legendTop+top+15)+'">'+legendItems[i].children[0].children[0].innerText+'</text>';
			}
			var svgdata = $('.highcharts-container')[0].innerHTML.split('</svg>')[0]+legend+'</svg>';
			$.post('./lib/img/export.php',{svg: svgdata, format: format}, function( data ) {
				// https://stackoverflow.com/questions/17311645/download-image-with-javascript
				var a = document.createElement('a');
				var t = Date.now();
				a.href = "./lib/img/export."+format+"?"+t;
				a.download = "eGiga2m."+format;
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
			});
		}
	}

	function updateLink(myTs){
		var ts = '';
		if (ftree==null && document.getElementById('ts').value.length) {
			ts = '&ts=' + document.getElementById('ts').value;
		}
		else {
			var axis = [];
			var tree = $("#tree").fancytree("getTree");
			if (tree) tree.visit(function(node){
				if (!node.folder) {
					// if (node.data.icon != './img/y0axis.png') console.log('node', node.data.icon, node.key);
					if (node.data.icon == './img/y1axis.png') axis.push(node.key+',1,1');
					if (node.data.icon == './img/y2axis.png') axis.push(node.key+',1,2');
					if (node.data.icon == './img/xaxis.png') axis.push(node.key+',1,2');
					if (node.data.icon == './img/surface.png') axis.push(node.key+',surface'); 
					if (node.data.icon == './img/animation.png') axis.push(node.key+',animation'); 
					if (node.data.icon == './img/multi.png') axis.push(node.key+',multi');
				}
			});
			if (axis.length) ts = '&ts=' + axis.join(';').split('csvrepo_').join('');
		}
		var start = '';
		if (document.getElementById('start').value.length) {
			start = '&start=' + document.getElementById('start').value;
		}
		else if (document.getElementById('startInput').value.length) {
			start = '&start=' + document.getElementById('startInput').value;
		}
		var stop = '';
		if (document.getElementById('stop').value.length) {
			stop = '&stop=' + document.getElementById('stop').value;
		}
		else if (document.getElementById('startInput') && document.getElementById('startInput').value.length) {
			stop = '&stop=' + document.getElementById('stopInput').value;
		}
		var conf = document.getElementById('conf').value.length? 'conf='+document.getElementById('conf').value: 'conf=';
		var yconf = '';
		var minY = document.getElementById('minY').value;
		if (minY.length>1) yconf = '&minY=' + minY;
		var maxY = document.getElementById('maxY').value;
		if (maxY.length>1) yconf += '&maxY=' + maxY;
		var logY = document.getElementById('logY').value;
		if (logY != '' && logY != '0;0') yconf += '&logY=' + logY;
		var style = document.getElementById('style').value;
		if (style.length) style = '&style='+style;
		var height = document.getElementById('height').value;
		if (height.length) height = '&height='+height;
		var hc = ''; // document.getElementById('show_hc').checked? '&show_hc=true': '';
		var flot = document.getElementById('show_flot').checked? '&show_flot=true': '';
		var chartjs = document.getElementById('show_chartjs').checked? '&show_chartjs=true': '';
		var table = document.getElementById('show_table').checked? '&show_table=true': '';
		var pathname = (typeof(location.pathname) !== 'undefined')? location.pathname: ((typeof(window.location.pathname) !== 'undefined')? window.location.pathname: ((typeof(document.location.pathname) !== 'undefined')? document.location.pathname: './egiga2m.html'));
		path = pathname.split('/');
		// path[path.length-1] = pathname.match(/[^\/]+$/)[0];
		var event = '';
		for (j=0; j<events.length; j++) event += document.getElementById('show_'+events[j]).checked? '&show_'+events[j]+'='+document.getElementById('filter_'+events[j]).value: '';
		var homeURL = window.location.protocol + "//" + window.location.host + path.join('/');
		path[path.length-1] = (pathname.indexOf('.html')==-1)? path[path.length-1]+'index_plot.html': path[path.length-1].replace('.html', '_plot.html');
		plotURL = window.location.protocol + "//" + window.location.host + path.join('/');
		path.pop();
		if (exportService.indexOf("./") == 0) {
			exportService = window.location.protocol + "//" + window.location.host + path.join('/') + exportService.substr(1);
		}
		exportURL = exportService+start+stop+ts;
		const downtimeCheckStr = document.getElementById('downtimeCheck').checked? '&downtimeCheck=true': '';
		const decimationStr = decimation!=='maxmin'? '&decimation='+decimation: '';
		const decimationSamplesStr = decimationSamples!=1000? '&decimationSamples='+decimationSamples: '';
		const hideOverMaxmin = (document.getElementById('hideOverMaxmin') && document.getElementById('hideOverMaxmin').checked)? '&hideOverMaxmin=true': '';
		// console.log('downtimeCheck: '+downtimeCheck);
		var necessaryParam = conf+start+stop+ts;
		var optionalParam = yconf+style+height+decimationStr+decimationSamplesStr+downtimeCheckStr+hideOverMaxmin;
		for (var i in parameters) {
			if (document.getElementById(i) && document.getElementById(i).checked) {
				optionalParam = optionalParam + '&'+i+'=true';
			}
		}
		jsonURL = window.location.protocol + "//" + window.location.host + path.join('/') + plotService.substr(1)+start+stop+ts+optionalParam;
		// console.log('jsonURL', jsonURL);
		var jsonTreeURL = window.location.protocol + "//" + window.location.host + path.join('/') + treeService.substr(1)+start+stop+ts+optionalParam;
		// console.log('jsonTreeURL', jsonTreeURL);
		if (typeof(myTs) !== 'undefined') {
			if (myTs == 'history') return necessaryParam+optionalParam+hc+flot+chartjs+table;
			window.location = homeURL+'?'+conf+start+stop+'&ts='+myTs+optionalParam+hc+flot+chartjs+table;
		}
		/*else if (document.getElementById("exportPng").checked) {
			exportImage('png');
		}*/
		else {
			var myParams = necessaryParam+optionalParam+hc+flot+chartjs+table+event;
			myParams = myParams.replace('&show_error=','');
			if (plotService.indexOf('analysis')>-1) myParams = myParams + '&plotservice=' + plotService.replaceAll('&','%26');
			document.getElementById("plotOnly").setAttribute("onClick","javascript: location='"+plotURL+'?'+myParams+"'");
			document.getElementById("plotOnly").innerHTML = plotURL+'?'+myParams;
			document.getElementById("plotAndControls").setAttribute("onClick","javascript: location='"+homeURL+'?'+myParams+"'");
			document.getElementById("plotAndControls").innerHTML = homeURL+'?'+myParams;
			if (document.getElementById("scratch")) {
				document.getElementById("scratch").setAttribute("onClick","javascript: location='"+homeURL+'?'+conf+"'");
				document.getElementById("scratch").innerHTML = homeURL+'?'+conf;
			}
			exportBlanks = document.getElementById('exportZoh').checked? '&zoh': (document.getElementById('exportFoh').checked? '&foh': '');
			exportFormat = document.getElementById("exportCsv").checked? 'csv': '';
			exportFormat = document.getElementById("exportXls").checked? 'xlsx': exportFormat;
			exportFormat = document.getElementById("exportMat").checked? 'mat': exportFormat;
			exportFormat = document.getElementById("exportIgor").checked? 'itx': exportFormat;
			exportFormat = document.getElementById("exportJson") && document.getElementById("exportJson").checked? 'json': exportFormat;
			exportDecimation = evalExportDecimation();
			refreshExportLink();
			document.getElementById("exportCsv").setAttribute("onClick","javascript: exportFormat = 'csv';document.getElementById('matDisabled').style.display='inline'; refreshExportLink();");
			document.getElementById("exportXls").setAttribute("onClick","javascript: exportFormat = 'xlsx';document.getElementById('matDisabled').style.display='inline'; refreshExportLink();");
			if (document.getElementById("exportMat")) {
				document.getElementById("exportMat").setAttribute("onClick","javascript: exportFormat = 'mat';document.getElementById('matDisabled').style.display='none'; refreshExportLink();");
			}
			if (document.getElementById("exportIgor")) {
				document.getElementById("exportIgor").setAttribute("onClick","javascript: exportFormat = 'itx';document.getElementById('matDisabled').style.display='inline'; refreshExportLink();");
			}
			if (document.getElementById("exportJson")) {
				document.getElementById("exportJson").setAttribute("onClick","javascript: exportFormat = 'json';document.getElementById('matDisabled').style.display='none'; refreshExportLink();");
				document.getElementById("exportJson").innerHTML = jsonURL;
			}
			if (document.getElementById("exportTreeJson")) {
				document.getElementById("exportTreeJson").setAttribute("onClick","javascript: location='"+jsonTreeURL+"'");
				document.getElementById("exportTreeJson").innerHTML = jsonTreeURL;
			}
			document.getElementById('exportBlanks').setAttribute("onClick","javascript: exportBlanks = '';refreshExportLink();");
			document.getElementById('exportZoh').setAttribute("onClick","javascript: exportBlanks = '&zoh';refreshExportLink();");
			document.getElementById('exportFoh').setAttribute("onClick","javascript: exportBlanks = '&foh';refreshExportLink();");
			document.getElementById('exportSamplesNumber').setAttribute("onChange","javascript: exportDecimation = evalExportDecimation();refreshExportLink();");
			document.getElementById('exportMaxmin').setAttribute("onClick","javascript: exportDecimation = evalExportDecimation();refreshExportLink();");
			document.getElementById('exportMean').setAttribute("onClick","javascript: exportDecimation = evalExportDecimation();refreshExportLink();");
			document.getElementById('exportAvg').setAttribute("onClick","javascript: exportDecimation = evalExportDecimation();refreshExportLink();");
			document.getElementById('tsLabel').setAttribute("onChange","javascript: refreshExportLink();");
			document.getElementById('nullValue').setAttribute("onChange","javascript: refreshExportLink();");
			document.getElementById('timestamp').setAttribute("onChange","javascript: refreshExportLink();");
			document.getElementById("multiParam").value = plotURL+'?'+necessaryParam+optionalParam+hc+flot+event;
		}
	}

	function getFormattedDate(timestamp) {
		var date = new Date(timestamp);

		var month = date.getMonth() + 1;
		var day = date.getDate();
		var hour = date.getHours();
		var min = date.getMinutes();
		var sec = date.getSeconds();

		month = (month < 10 ? "0" : "") + month;
		day = (day < 10 ? "0" : "") + day;
		hour = (hour < 10 ? "0" : "") + hour;
		min = (min < 10 ? "0" : "") + min;
		sec = (sec < 10 ? "0" : "") + sec;

		return date.getFullYear() + "-" + month + "-" + day + " " +  hour + ":" + min + ":" + sec;
	}

	function previous_period(){
		const startT = document.getElementById('startInput').value;
		const stopT = document.getElementById('stopInput').value;
		if ((startT.length == 19) && (startT.indexOf('last') == -1)) {
			const startTime = startT.substring(0,10)+'T'+startT.substring(11);
			if ((stopT.length == 19) && (stopT.indexOf('last') == -1)) {
				const stopTime = stopT.substring(0,10)+'T'+stopT.substring(11);
				const period = (new Date(stopTime)).getTime() - (new Date(startTime)).getTime();
				document.getElementById('stopInput').value = startT;
				document.getElementById('startInput').value = getFormattedDate((new Date(startTime)).getTime() - period);
			}
			if (stopT.length < 2) {
				document.getElementById('stopInput').value = startT;
				document.getElementById('startInput').value = getFormattedDate((new Date(startTime)).getTime() - 86400000);
			}
		}
		if (startT.indexOf('last') !== -1) {
			const startArray = startT.split(' ');
			startArray[1] = startArray[1]*2;
			document.getElementById('startInput').value = startArray.join(' ');
		}
	}

	function following_period(){
		const startT = document.getElementById('startInput').value;
		const stopT = document.getElementById('stopInput').value;
		if ((startT.length == 19) && (startT.indexOf('last') == -1)) {
			const startTime = startT.substring(0,10)+'T'+startT.substring(11);
			if ((stopT.length == 19) && (stopT.indexOf('last') == -1)) {
				const stopTime = stopT.substring(0,10)+'T'+stopT.substring(11);
				const period = (new Date(stopTime)).getTime() - (new Date(startTime)).getTime();
				document.getElementById('startInput').value = stopT;
				document.getElementById('stopInput').value = getFormattedDate((new Date(stopTime)).getTime() + period);
			}
			if (stopT.length < 2) {
				document.getElementById('stopInput').value = getFormattedDate((new Date(startTime)).getTime() + 86400000);
			}
		}
		if (startT.indexOf('last') !== -1) {
			const startArray = startT.split(' ');
			startArray[1] = Math.round(startArray[1]/2);
			document.getElementById('startInput').value = startArray.join(' ');
		}
	}

	function evalExportDecimation(){
		var exportDecimation = '';
		if (document.getElementById('exportMaxmin').checked || document.getElementById('exportMean').checked || document.getElementById('exportAvg').checked) {
			exportDecimation = '&decimation=';
			if (document.getElementById("exportSamplesNumber").value>0) exportDecimation += document.getElementById("exportSamplesNumber").value;
			if (document.getElementById('exportMaxmin').checked) exportDecimation += ',maxmin';
			if (document.getElementById('exportMean').checked) exportDecimation += ',mean';
			if (document.getElementById('exportAvg').checked) exportDecimation += ',avg';
		}
		return exportDecimation;
	}

	function refreshExportLink(){
		document.getElementById("timestampBlock").style.display = 'inline';
		// console.log('itx',document.getElementById("exportIgor").checked);
		if (document.getElementById("exportIgor").checked) document.getElementById("timestampBlock").style.display = 'none';
		var exportTsLabel = document.getElementById('tsLabel').value.length? '&tsLabel='+document.getElementById('tsLabel').value: '';
		exportTsLabel = exportTsLabel + (document.getElementById('nullValue').value.length? '&nullValue='+document.getElementById('nullValue').value: '');
		exportTsLabel = exportTsLabel + (document.getElementById('timestamp').checked? '&timestamp': '');
		if (exportFormat==='json'){
			document.getElementById('exportLink').innerHTML = jsonURL;
			document.getElementById('exportLink').setAttribute("onClick","javascript: location='"+jsonURL+"'");
		}
		else {
			expu = exportURL+'&format='+exportFormat+exportBlanks+exportDecimation+exportTsLabel;
			// console.log(expu, expu.split('&').join('&amp;'));
			document.getElementById('exportLink').innerHTML = expu.split('&').join('&amp;');
			document.getElementById('exportLink').setAttribute("onClick","javascript: location='"+exportURL+"&format="+exportFormat+exportBlanks+exportDecimation+exportTsLabel+"'");
		}
	}

	function plotCallback(){
		activePoint = -1; // used by tooltip keyboard navigation
		// stop Animation (if running)
		animationDelay = 0;
		document.getElementById('placeholder').style.border = "0px";
		decimationSamples = document.getElementById('decimationSamples')? document.getElementById('decimationSamples').value: 'maxmin';
		decimation = document.getElementById('decimation')? document.getElementById('decimation').value: 1000;
		// console.log('decimation: '+decimation);
		myPlotClass = []; // todo: re-use myPlotClass as cache, request data before calling plot function
		if (document.getElementById('startInput').value.length) {
			document.getElementById('start').value = document.getElementById('startInput').value;
		}
		start = document.getElementById('start').value;
		document.getElementById('stop').value = document.getElementById('stopInput').value;
		stop = document.getElementById('stop').value;
		var surface = false;
		var axis = [];
		var axis2 = [];
		var tree = $("#tree").fancytree("getTree");
		tree.visit(function(node){
			if (!node.folder) {
				// console.log('visit(), node: '+node.key, node);
				if (node.key.indexOf('_')>-1) {
					var naming = node.key.split('_');
					naming.shift();
					var db = naming.shift();
					// if (typeof axis2[db] == 'undefined') axis2[db] = [];
					if (node.data.icon == './img/y1axis.png') axis2.push(db+'_'+naming.join('_')+',1,1');
					if (node.data.icon == './img/y2axis.png') axis2.push(db+'_'+naming.join('_')+',1,2');
					if (node.data.icon == './img/xaxis.png') axis2.push(db+'_'+naming.join('_')+',1,2');
				}
				else {
					if (node.data.icon == './img/y1axis.png') axis.push(node.key+',1,1');
					if (node.data.icon == './img/y2axis.png') axis.push(node.key+',1,2');
					if (node.data.icon == './img/xaxis.png') axis.push(node.key+',1,2');
					if (node.data.icon == './img/multi.png') {
						axis.push(node.key+',multi');
					}
					if (node.data.icon == './img/surface.png') {
						plotSurfaceTs(node.key, start, stop); 
						axis.push(node.key+',surface'); 
						surface = true;
						document.getElementById('animationControls').style.display = 'none';
					}
					if (node.data.icon == './img/animation.png') {
						document.getElementById('animationControls').style.display = 'inline';
						frameNum = 0;
						if (document.getElementById('show_chartjs').checked) {chartjsAnimationTs(node.key, start, stop); axis.push(node.key+',animation'); surface = true;}
						if (document.getElementById('show_flot').checked) {flotAnimationTs(node.key, start, stop); axis.push(node.key+',animation'); surface = true;}
						if (document.getElementById('show_hc').checked) {hcAnimationTs(node.key, start, stop); axis.push(node.key+',animation'); surface = true;}
					}
				}
			}
		});
		var ts = axis.join(';');
		document.getElementById('ts').value = ts;
		if (!surface) document.getElementById('animationControls').style.display = 'none';
		if (updateId!==false) {clearInterval(updateId); updateId = false;} 
		// document.getElementById('hidetree').style.display = 'inline';
		if (!surface) plotTs(ts, axis2, start, stop);
		add_history(0);
	}

	function initPlot($_GET) {
		if ($_GET.debug) console.log('initPlot(), $_GET', $_GET);
		console.log('tw', $("#tree").width(), $("#configContainer").width())
		if ($("#tree")) $("#tree").width($("#configContainer").width());
		// var $_GET = getQueryParams(document.location.search);
		// alert(JSON.stringify($_GET, null, '\t'));
		for (j=0; j<events.length; j++) if (typeof($_GET['show_'+events[j]]) !== 'undefined') {
			document.getElementById('show_'+events[j]).checked = true;
			if ($_GET['show_'+events[j]].length && $_GET['show_'+events[j]]!=='1') document.getElementById('filter_'+events[j]).value = $_GET['show_'+events[j]];
		}
		if (typeof($_GET['minY']) !== 'undefined') document.getElementById('minY').value = $_GET['minY'];
		if (typeof($_GET['maxY']) !== 'undefined') document.getElementById('maxY').value = $_GET['maxY'];
		if (typeof($_GET['logY']) !== 'undefined') document.getElementById('logY').value = $_GET['logY'];
		if (typeof($_GET['height']) !== 'undefined') document.getElementById('height').value = $_GET['height'];
		if (typeof($_GET['style']) !== 'undefined') document.getElementById('style').value = $_GET['style'];
		if (typeof($_GET['start']) === 'undefined') return;
		if (typeof($_GET['ts']) === 'undefined') return;
		document.getElementById('placeholder').style.border = "0px";
		var start = $_GET['start'];
		document.getElementById('start').value = start;
		if (document.getElementById('startInput')) document.getElementById('startInput').value = start;
		var stop = '';
		if (typeof($_GET['stop']) !== 'undefined') {
			stop = $_GET['stop'];
			if (document.getElementById('stopInput')) document.getElementById('stopInput').value = stop;
			document.getElementById('stop').value = stop;
		}
		else {
			if (document.getElementById('stopInput')) document.getElementById('stopInput').value = '';
			document.getElementById('stop').value = '';
		}
		var ts = $_GET['ts'];
		var tsc = [];
		if (ts.indexOf('surface')>=0) {
			var tsArray = ts.split(',');
			plotSurfaceTs(tsArray[0], start, stop);
			return;
		}
		if (ts.indexOf('animation')>=0) {
			var tsArray = ts.split(',');
			document.getElementById('animationControls').style.display = 'inline';
			if (typeof($_GET['show_flot']) !== 'undefined') {
				flotAnimationTs(tsArray[0], start, stop);
			}
			else if (typeof($_GET['show_chartjs']) !== 'undefined') {
				chartjsAnimationTs(tsArray[0], start, stop);
			}
			else {
				hcAnimationTs(tsArray[0], start, stop);
			}
			return;
		}
		if (ts.indexOf('_')>=0 && ts.indexOf('/')<0) {
			var tsa = ts.split(';');
			var tsb = [];
			for (var i=0; i<tsa.length; i++) {
				if (tsa[i].indexOf('_')>=0) {tsc.push(tsa[i]);} else {tsb.push(tsa[i]);}
			}
			ts = tsb.join(';');
		}
		if (document.getElementById('hidetree')) {
			document.getElementById('hidetree').style.display = 'inline';
		}
		if (typeof(document.getElementById('ts')) !== 'undefined') {
			document.getElementById('ts').value = ts;
		}
		if (typeof($_GET['show_chartjs']) !== 'undefined') {
			if (document.getElementById('show_chartjs')) {
				document.getElementById('show_chartjs').checked = true;
				document.getElementById('show_flot').checked = false;
				document.getElementById('show_hc').checked = false;
			}
		}
		else if (typeof($_GET['show_flot']) !== 'undefined') {
			if (document.getElementById('show_flot')) {
				document.getElementById('show_flot').checked = true;
				document.getElementById('show_chartjs').checked = false;
				document.getElementById('show_hc').checked = false;
			}
		}
		if (typeof($_GET['show_table']) !== 'undefined') {
			if (document.getElementById('show_table')) {
				document.getElementById('show_table').checked = true;
			}
		}
		if ($_GET.debug) console.log('initPlot(), plotTs', ts, tsc, start, stop);
		plotTs(ts, tsc, start, stop);
		if (ts!='') add_history(0);
	}

	function initTree($_GET) {
		// console.log('treeService', treeService+(typeof($_GET['ts']) !== 'undefined'? '&ts=' + $_GET['ts']: ''));
		$.get(treeService+(typeof($_GET['ts']) !== 'undefined'? '&ts=' + $_GET['ts']: '')+(parameters.correlation!=null? '&correlation=1': ''), function(tdata) {
			if (tdata.length==0) {
				var url = treeService.indexOf('?')>=0? treeService+'&host': treeService+'?host';
				$.get(url, function(d) {
					var t = new Date($.now());
					$("body").html("<div style='margin-left: 10px;margin-top: -80px;'><h1>ERROR</h1>Cannot extract data from<br>"+d+"<br>or<br>"+treeService+'<br>'+t+'<br><a href="'+$(location).attr('href')+'">reload page</a></div>');
				});
			}
			if (localStorage.length && typeof(localStorage.csvrepo) !== 'undefined' && tdata[0].title.indexOf(localStorage.csvrepo)==-1) {
				var ltree = {title: "<span style='color: darkgreen;font-weight: bold;'>"+localStorage.csvrepo+"</span>", key: "csvrepo_"+localStorage.csvrepo, lazy: true, folder: true}
				tdata.unshift(ltree);
				// console.log('tdata', tdata);
			}
			if (!$('#tree').length) return;
			var source_url = treeService;
			var treeHeight = $("#treeconfig").height(); // $(window).height()-320;
			if (treeHeight < 150) treeHeight = 150;
			var configWidth = document.location.search.indexOf('config_size=')==-1? 280: document.location.search.split('config_size=')[1].split('&')[0]-10;
			if (typeof(formula_edit) === 'undefined') $(tree).width(configWidth).height(treeHeight);
			if (typeof($_GET['ts']) !== 'undefined') source_url = source_url + '&ts=' + $_GET['ts'];
			ftree = $("#tree").fancytree({
				autoScroll: true,
				source: tdata,
				lazyLoad: function(event, data) {
					// console.log('lazyLoad()', treeService, data.node.key);
					var node = data.node;
					// Issue an ajax request to load child nodes
					data.result = {
						url: treeService,
						data: {key: node.key}
					}
				},
				click: function(event, data) {
					if (data.targetType == 'icon' || data.targetType == 'title') {
						if (typeof(formula_edit) === 'undefined') {
							if (data.node.data.isArray) {
								if (data.node.data.icon == './img/y0axis.png') {
									data.node.data.icon = './img/surface.png';
									data.node.data.tooltip = 'surface display';
								}
								else if (data.node.data.icon == './img/surface.png') {
									data.node.data.icon = './img/animation.png';
									data.node.data.tooltip = 'animation display';
								}
								else if (data.node.data.icon == './img/animation.png') {
									data.node.data.icon = './img/multi.png';
									data.node.data.tooltip = 'multi line display';
								}
								else if (data.node.data.icon == './img/multi.png') {
									data.node.data.icon = './img/y0axis.png';
									data.node.data.tooltip = 'not selected';
								}
							}
							else if (data.node.data.isString) {
								if (data.node.data.icon == './img/y0axis.png') {
									data.node.data.icon = './img/table.png';
									data.node.data.tooltip = 'show table';
								}
								else if (data.node.data.icon == './img/table.png') {
									data.node.data.icon = './img/y0axis.png';
									data.node.data.tooltip = 'not selected';
								}
							}
							else if (parameters.correlation != null) {
								if (data.node.data.icon == './img/y0axis.png') {
									data.node.data.icon = './img/y1axis.png';
									data.node.data.tooltip = 'show on Y1 axis';
								}
								else if (data.node.data.icon == './img/y1axis.png') {
									data.node.data.icon = './img/xaxis.png';
									data.node.data.tooltip = 'show on X axis';
								}
								else if (data.node.data.icon == './img/xaxis.png' || data.node.data.icon == './img/y2axis.png') {
									data.node.data.icon = './img/y0axis.png';
									data.node.data.tooltip = 'not selected';
								}
							}
							else {
								if (data.node.data.icon == './img/y0axis.png') {
									data.node.data.icon = './img/y1axis.png';
									data.node.data.tooltip = 'show on Y1 axis';
								}
								else if (data.node.data.icon == './img/y1axis.png') {
									data.node.data.icon = './img/y2axis.png';
									data.node.data.tooltip = 'show on Y2 axis';
								}
								else if (data.node.data.icon == './img/y2axis.png') {
									data.node.data.icon = './img/y0axis.png';
									data.node.data.tooltip = 'not selected';
								}
							}
							// tooltip is not updated by render() method so it's the same in all cases
							data.node.data.tooltip = 'click to switch display mode';
							data.node.render(true);
							// workaround to avoid sorting by image
							data.node.parent.sortChildren();
							// return false;// Prevent default processing
						}
						else {
							$.get(treeService+'&reversekey='+data.node.key, function(data) {
								if (data.length) append2formula('"'+data+'"');
							});
						}
					}
					return true;// Allow default processing
				},
				clickFolderMode: 2,
				persist: true
			});
			updateLink();
		});
	}

	/*function switchtree() {
		if (document.getElementById('hidetree').InnerHTML == "<img src='./img/right.png'>") {
			document.getElementById('hidetree').InnerHTML = "<img src='./img/y0axis.png'>";
			$('body').find('#hidetree').html("<img src='./img/y0axis.png'>")
			document.getElementById('treeid').style.display = 'inline';
			var plotWidth = $(window).width()-280;
			if (plotWidth < 300) plotWidth = 300;
			$("#mybackground").width(plotWidth-14).height(((height-0)-14)+'px');
			$("#placeholder").width(plotWidth-14).height(height+'px');
			rePlotTs();
		}
		else {
			document.getElementById('hidetree').InnerHTML = "<img src='./img/right.png'>";
			$('body').find('#hidetree').html("<img src='./img/right.png'>")
			document.getElementById('treeid').style.display = 'none';
			var plotWidth = $(window).width()-30;
			if (plotWidth < 300) plotWidth = 300;
			$("#mybackground").width(plotWidth-14).height(((height-0)-14)+'px');
			$("#placeholder").width(plotWidth-14).height(height+'px');
			rePlotTs();
		}
	}*/

	function rePlotTs(){
		// adjust plot width
		var height = document.getElementById('height').value.length? document.getElementById('height').value: $(window).height()-200;
		if (document.getElementById('show_flot') && document.getElementById('show_flot').checked) {
			myPlot.resize();
			myPlot.draw();
		}
		if (document.getElementById('show_chartjs') && document.getElementById('show_chartjs').checked) {
			myPlot.resize();
			myPlot.draw();
		}
		else {
			myPlot.reflow();
		}
	}

	function applyAnalysis() {
		// reset plotService
		if (typeof($_GET['conf']) !== 'undefined') {
			document.getElementById('conf').value = $_GET['conf'];
			initConf($_GET['conf']);
		}
		else {
			initConf(false);
		}
		for (var i=0; i<analysis.length; i++) {
			if (document.getElementById(analysis[i]).checked) {
				var an = [];
				for (var j=0; j<document.getElementById('conf'+analysis[i]).children.length; j++) {
					// console.log('applyAnalysis()', document.getElementById('conf'+analysis[i]).children[j].name, document.getElementById('conf'+analysis[i]).children[j].type);
					if (document.getElementById('conf'+analysis[i]).children[j].type=='text') {
						an.push(document.getElementById('conf'+analysis[i]).children[j].name+'=' + document.getElementById('conf'+analysis[i]).children[j].value);
					}
					else if (document.getElementById('conf'+analysis[i]).children[j].type=='checkbox') {
						an.push(document.getElementById('conf'+analysis[i]).children[j].name + (document.getElementById('conf'+analysis[i]).children[j].checked? '=1': '=0'));
					}
					else if (document.getElementById('conf'+analysis[i]).children[j].type=='select-one') {
						var k = document.getElementById('conf'+analysis[i]).children[j].options.selectedIndex;
						an.push(document.getElementById('conf'+analysis[i]).children[j].name+'=' + document.getElementById('conf'+analysis[i]).children[j].options[k].value);
					}
				}
				// console.table(an);
				var conf = '';
				if (document.location.search.indexOf('conf=')>-1) conf = 'conf='+document.location.search.split('conf=')[1].split('&')[0]+'&';
				plotService = './lib/analysis/'+analysis[i]+'.php?'+conf+an.join('&');
			}
		}
		// console.log('plotService', plotService);
	}
	function switch_analysis(operator) {
		for (var i=0; i<analysis.length; i++) {
			document.getElementById('conf'+analysis[i]).style.display = (operator == analysis[i] && document.getElementById(analysis[i]).checked) ? 'block': 'none';
			if (operator != analysis[i]) document.getElementById(analysis[i]).checked = false;
		}
	}

	function initAnalysis() {
		$.get('./lib/analysis/analysis.php?list&conf='+$_GET['conf'], function(data) {
			var d = data.split('<analysis/>')
			document.getElementById('analysis').innerHTML = d[0];
			analysis = d[1].split(',');
			if (plotService.indexOf('analysis/')>-1) {
				var an = plotService.split('analysis/')[1].split('.php?');
				document.getElementById(an[0]).checked = true;
				document.getElementById('conf'+an[0]).style.display = 'block';
				var c = [];
				var cnf = {}
				if (an[1].indexOf('&')>-1) c = an[1].split('&');
				if (an[1].indexOf('%26')>-1) c = an[1].split('%26');
				for (var i=0; i<c.length; i++) { cnf[c[i].split('=')[0]] = c[i].split('=')[1];}
				for (var j=0; j<document.getElementById('conf'+an[0]).children.length; j++) {
					if (document.getElementById('conf'+an[0]).children[j].type=='text' || document.getElementById('conf'+an[0]).children[j].type=='select-one') {
						document.getElementById('conf'+an[0]).children[j].value = cnf[document.getElementById('conf'+an[0]).children[j].name];
					}
					else if (document.getElementById('conf'+an[0]).children[j].type=='checkbox') {
						document.getElementById('conf'+an[0]).children[j].checked = cnf[document.getElementById('conf'+an[0]).children[j].name]=='1';
					}
				}
			}
		});
	}

	function applyConf() {
		// apply Y1 conf
		document.getElementById('minY').value = document.getElementById('minY1set').value+';'+document.getElementById('minY2set').value;
		document.getElementById('maxY').value = document.getElementById('maxY1set').value+';'+document.getElementById('maxY2set').value;
		document.getElementById('logY').value = (document.getElementById('logY1set').checked? '1': '0')+';'+(document.getElementById('logY2set').checked? '1': '0');
		// apply style
		document.getElementById('style').value = document.getElementById('styleSet').value;
		
		var csvrepo = document.getElementById('csvrepo').value;
		if (csvrepo.length>0) localStorage.setItem('csvrepo', csvrepo); else localStorage.removeItem('csvrepo');
		for (var i in parameters) {
			if (document.getElementById(i) && document.getElementById(i).checked) optionalParam = parameters[i] = 1;
		}
	}

	function updateConf() {
		minY = document.getElementById('minY').value.split(';');
		maxY = document.getElementById('maxY').value.split(';');
		logY = document.getElementById('logY').value.split(';');
		document.getElementById('minY1set').value = minY[0];
		document.getElementById('maxY1set').value = maxY[0];
		document.getElementById('logY1set').checked = logY[0]=='1';
		document.getElementById('minY2set').value = (typeof(minY[1]) === 'undefined')? '': minY[1];
		document.getElementById('maxY2set').value = (typeof(maxY[1]) === 'undefined')? '': maxY[1];
		document.getElementById('logY2set').checked = logY[1]=='1';
		// update style
		document.getElementById('style_scatter').selected = document.getElementById('style').value=='scatter';
		document.getElementById('style_step').selected = document.getElementById('style').value=='step';
		document.getElementById('style_line').selected = document.getElementById('style').value=='line';
		document.getElementById('style_spline').selected = document.getElementById('style').value=='spline';
	}

	function updateAbout() {
		$('body').find('#aboutVersion').html(version);
	}

	function applySearch() {
		var ts = [];
		for (i=0; i<document.getElementById('Y1search').length; i++) {
			if (document.getElementById('Y1search').options[i].selected) {
				ts.push(document.getElementById('Y1search').options[i].value);
			}
		}
		for (j=0; j<document.getElementById('Y2search').length; j++) {
			if (document.getElementById('Y2search').options[j].selected) {
				ts.push(document.getElementById('Y2search').options[j].value+',1,2');
			}
		}
		updateLink(ts.join(';'));
	}

	function mysearch() {
		searched = document.getElementById('search').value;
		$.get(treeService+'&search='+searched, function(data) {
			var found = '';
			// for (var key=0; key<data.length; key++) {
			for (var key in data) {
				found += '<option value="'+ key + '">' + data[key] + '</option>';
			}
			$('body').find('#Y1search').html(found);
			$('body').find('#Y2search').html(found);
		});
	}

	function change_chartjs() {
		if (document.getElementById('show_chartjs').checked) {
			document.getElementById('show_hc').checked = false;
			document.getElementById('show_flot').checked = false;
		}
		if (document.getElementById('style_item')) document.getElementById('style_item').style.display = document.getElementById('show_hc').checked? 'inline': 'none';
	}

	function change_flot() {
		if (document.getElementById('show_flot').checked) {
			document.getElementById('show_hc').checked = false;
			document.getElementById('show_chartjs').checked = false;
		}
		if (document.getElementById('style_item')) document.getElementById('style_item').style.display = document.getElementById('show_hc').checked? 'inline': 'none';
	}

	function change_hc() {
		if (document.getElementById('show_hc').checked) {
			document.getElementById('show_flot').checked = false;
			document.getElementById('show_chartjs').checked = false;
		}
		document.getElementById('style_item').style.display = document.getElementById('show_hc').checked? 'inline': 'none';
	}

	function csvCallback(){
		ts = document.getElementById('ts').value;
		start = document.getElementById('start').value;
		stop = document.getElementById('stop').value;
		csvTs(ts, start, stop);
	}

	function getQueryParams(qs) {
		qs = qs.split("+").join(" ");
		var params = {},
				tokens,
				re = /[?&]?([^=]+)=([^&]*)/g;
		while (tokens = re.exec(qs)) {
			params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
		}
		return params;
	}

	function handleFileSelect(evt) {
		var height = document.getElementById('height').value.length? document.getElementById('height').value: $(window).height()-200;
		// console.log('handleFileSelect()',evt, height);
		$("#mybackground").width($("#plotContainer").width()-14).height(((height-0)-14)+'px');
		$("#placeholder").width($("#plotContainer").width()-14).height(height+'px');
		document.getElementById('placeholder').style.border = "0px";
		evt.stopPropagation();
		evt.preventDefault();
		var files = evt.dataTransfer.files; // FileList object.
		// files is a FileList of File objects. List some properties.
		var f = files[0];
		var reader = new FileReader();
		// Closure to capture the file information.
		reader.onload = (function(theFile) {
			return function(e) {
				console.log('reader.onload', e, theFile);
				csvImport.name = theFile.name;
				csvImport.lines = e.target.result.split("\n");
				var separator = '';
				if (csvImport.lines[0].indexOf(',')>-1 && csvImport.lines[0].indexOf(';')==-1 && csvImport.lines[0].indexOf("\t")==-1) {separator = ","; document.getElementById('separatorComma').checked = true;}
				if (csvImport.lines[0].indexOf(',')==-1 && csvImport.lines[0].indexOf(';')>-1 && csvImport.lines[0].indexOf("\t")==-1) {separator = ";"; document.getElementById('separatorSemicolumn').checked = true;}
				if (csvImport.lines[0].indexOf(',')==-1 && csvImport.lines[0].indexOf(';')==-1 && csvImport.lines[0].indexOf("\t")>-1) {separator = "\t"; document.getElementById('separatorTab').checked = true;}
				// console.log('separator', separator);
				if (separator != '') {
					var datetime = csvImport.lines[1].split(separator)[0];
					// console.log('datetime', datetime);
					if (datetime - 0 > 0) document.getElementById('datetime').value = 'X';
					if (datetime[4] == '-' && datetime[7] == '-' && datetime[13] == ':' && datetime[16] == ':') document.getElementById('datetime').value = 'YYYY-MM-DD HH:mm:ss';
					if (datetime[4] == '.' && datetime[7] == '.' && datetime[10] == '_' && datetime[13] == '.' && datetime[16] == '.') document.getElementById('datetime').value = 'YYYY.MM.DD_HH.mm.ss';
				}
				$('#csvModal').modal('show');
			}
		})(f);
		reader.readAsText(f);
	}
	function processCsv() {
		var separator = '';
		if (document.getElementById('separatorTab').checked) separator = "\t";
		else if (document.getElementById('separatorComma').checked) separator = ",";
		else if (document.getElementById('separatorSemicolumn').checked) separator = ";";
		else separator = document.getElementById('separator').value;
		const datetimeFormat = document.getElementById('datetime').value;
		var val = csvImport.lines[0].split(separator);
		col_number = val.length-1;
		if (col_number < 1) {alert("Error: cannot detect columns using separator "+separator); return;}
		var yaxis_max_index = 1;
		myPlotClass = new Array();
		for (var j=0; j<col_number; j++) {
			myPlotClass[j] = new Array();
			myPlotClass[j].data = new Array();
			if (document.getElementById('show_hc') && document.getElementById('show_hc').checked) {
				myPlotClass[j].name = $.isNumeric(val[j+1])? 'ts1'+j: val[j+1].replace(/&deg;/g, "°");
			}
			else {
				myPlotClass[j].lines = { show: true, fill: false };
				myPlotClass[j].label = $.isNumeric(val[j+1])? 'ts1'+j: val[j+1];
			}
			if (val[j+1].substring(0, 1)=='Y' && $.isNumeric(val[j+1].substring(1, 2))) {
				myPlotClass[j].yAxis = val[j+1].substring(1, 2)-1;
				myPlotClass[j].yaxis = val[j+1].substring(1, 2)-0;
				if (yaxis_max_index < val[j+1].substring(1, 2)) yaxis_max_index = val[j+1].substring(1, 2);
			}
		}
		// console.log(myPlotClass);
		var row_index = -1;
		var showTable = document.getElementById('show_table') && document.getElementById('show_table').checked;
		var tableContent = '<table class="table table-striped table-bordered table-condensed" width="100%"><thead><tr><th>timestamp</th>';
		for (var i=0; i<col_number; i++) {
			tableContent += '<th>'+myPlotClass[i].label+'</th>';
		}
		tableContent += '</tr></thead><tbody>';
		var start = 0;
		for (var k=0; k<csvImport.lines.length; k++) {
			val = csvImport.lines[k].split(separator);
			if (val.length < 2) continue;
			var momentDate = moment(val[0], datetimeFormat);
			var timestamp = momentDate.valueOf();
			if ($.isNumeric(timestamp) && timestamp>0) {
				row_index++;
				if (start==0) start = timestamp;
				tableContent += '<tr><td>'+momentDate.toString()+'</td>'
				for (var j=0; j<col_number; j++) {
					tableContent += '<td>'+val[j+1]+'</td>';
					if (val[j+1]==='') continue;
					myPlotClass[j].data[row_index] = [timestamp,parseFloat(val[j+1])];
				}
				tableContent += '</tr>';
			}
		}
		stop = timestamp;
		if (document.getElementById('show_hc') && document.getElementById('show_hc').checked) {
			var style = 'step';
			if (document.getElementById('style') && document.getElementById('style').value.length) {
				style = document.getElementById('style').value;
			}
			var chartConfig = {
				chart: {
					renderTo: document.getElementById('placeholder'),
					type: 'line',
					height: document.getElementById('height').value.length? document.getElementById('height').value: $(window).height()-200
				},
				title: {text: 'eGiga2m'},
				xAxis: {type: 'datetime'},
				yAxis: [],
				tooltip: {
					headerFormat: '<b>{series.name}</b><br>',
					pointFormat: '{point.x:%d/%m/%Y %H\:%M\:%S}: {point.y:.9f} '
				},
				type: style=='step'? 'line': style,
				series: myPlotClass
			}
			Highcharts.setOptions({
				global: {
					useUTC: false // true by default
				}
			});
			for (var i=1; i<=yaxis_max_index; i++) {
				chartConfig.yAxis[i-1] = {title: {text: 'Y'+i},opposite: yaxis_max_index==2 && i==2};
			}
			var localPlot = new Highcharts.Chart(chartConfig);
			myPlot = localPlot;
		}
		else if (document.getElementById('show_flot') && document.getElementById('show_flot').checked) {
			var options = {
				series: { lines: { show: true } },
				grid: { hoverable: true, clickable: true},
				xaxis: { zoomRange: [0.01, 120000000], panRange: [start, stop], mode: "time", tickLength: 5 },
				yaxes: [ { }, { position: "right" } ],
				legend: { position: 'nw' },
				canvas: true
			};
			var localPlot = $.plot($("#placeholder"), myPlotClass, options);
			myPlot = localPlot;
		}
		else if (document.getElementById('show_chartjs') && document.getElementById('show_chartjs').checked) {
			var options = {
				scales: {
					xAxes: [{
						type: "time",
						time: {
							format: timeFormat,
							// round: 'month',
							tooltipFormat: timeFormat,// 'll HH:mm'
						},
						scaleLabel: {
							display: true,
							labelString: 'Date'
						},
						ticks: {
							callback: function(value, index, values) {
								return index==values.length-1? '': moment(values[index]? values[index]['_i']: 0).format('DD/MM/Y');
							}
						}
					}, ],
					yAxes: [{
						scaleLabel: {
							display: true,
							labelString: 'value'
						}
					}]
				},
			};				
			var localPlot = $.plot($("#placeholder"), myPlotClass, options);
			myPlot = localPlot;
		}
		else {
			$("#mybackground").height('1px');
			$("#placeholder").height('1px');
			document.getElementById('placeholder').innerHTML = '';
		}
		tableContent += '</tbody></table>';
		document.getElementById('tableContainer').innerHTML = showTable? tableContent: '';
		if (document.getElementById('pdfExport')) document.getElementById('pdfExport').style.display = 'none';
		if (document.getElementById('retain').value.length>0) {
			$.post("./lib/service/csv_service.php",{ name: csvImport.name, content: csvImport.lines.join("\n"), separator: separator, timeformat: datetimeFormat, db: document.getElementById('retain').value }, dataImported);
		}
	}

	function dataImported(data) {
		if (data.indexOf('ok')>-1) localStorage.setItem('csvrepo', document.getElementById('retain').value);
		console.log('CSV repo', data);
	}

	function handleTreeSelect(evt) {
		evt.stopPropagation();
		evt.preventDefault();
		attr = extractTimeseries(evt.dataTransfer.getData("text"));
		// attr = evt.dataTransfer.getData('application/taurus-device'); does this work with taurus?
		addTs = evt.dataTransfer.dropEffect=='copy';
		// console.log(evt.dataTransfer.dropEffect);
		$.get(treeService+'&searchkey='+attr, function(data) {
			if (typeof(data[0]) !== 'undefined') {
				// console.log(document.getElementById('ts').value);
				var ts = (addTs && document.getElementById('ts').value.length)? document.getElementById('ts').value+';'+data[0]: data[0];
				updateLink(ts);
				document.getElementById('ts').value = ts;
			}
		});
	}

	function handleDragOver(evt) {
		evt.stopPropagation();
		evt.preventDefault();
		evt.dataTransfer.dropEffect = 'plot'; // Explicitly show this is a copy.
	}

	// Setup the dnd listeners.
	var dropZone = document.getElementById('plotContainer');
	dropZone.addEventListener('dragover', handleDragOver, false);
	dropZone.addEventListener('drop', handleFileSelect, false);
	if ($('#tree').length) {
		var dropTree = document.getElementById('tree');
		dropTree.addEventListener('dragover', handleDragOver, false);
		dropTree.addEventListener('drop', handleTreeSelect, false);
	}

	function fillTable(data) {
		var tableContent = '';
		var tableBuffer = new Array();
		var keys = new Array();
		tableContent = '<br><table class="table table-striped table-bordered table-condensed" width="100%"><thead><tr><th>time</th>';
		var col_number = 0;
		for (var j=0; j<data.length; j++) {
			if (j=='clone') continue;
			if (typeof(data[j]['label']) === 'undefined') continue;
			col_number = j-0+1;
		}
		for (var j=0; j<data.length; j++) {
			if (j=='clone') continue;
			if (typeof(data[j]['label']) === 'undefined') continue;
			tableContent += '<th>'+data[j]['label']+'</th>';
			for (var k=0; k<data[j].data.length; k++) {
			if (typeof(data[j].data[k][0]) === 'undefined') continue;
				if (typeof(tableBuffer[data[j].data[k][0]]) === 'undefined') {
					tableBuffer[data[j].data[k][0]-0] = new Array(col_number);
					keys.push(data[j].data[k][0]-0);
				}
				tableBuffer[data[j].data[k][0]][j] = data[j].data[k][1];
			}
		}
		tableContent += '</tr></thead>\n<tbody>\n';
		keys.sort(function(a, b){return a-b});
		for (var k=0; k<keys.length; k++) {
			if (keys[k]-0 != keys[k]) continue;
			if ((typeof(keys[k]) === 'undefined') || (keys[k]=='clone')) continue;
			tableContent += '<tr>';
			var myDate = new Date(keys[k]);
			tableContent += '<td>'+myDate.format('Y-m-d H:i:s')+'</td>';
			for (var j=0; j<col_number; j++) {
				tableContent += '<td>'+((typeof(tableBuffer[keys[k]]) !== 'undefined' && typeof(tableBuffer[keys[k]][j]) !== 'undefined')? tableBuffer[keys[k]][j]: '&nbsp;')+'</td>';
			}
			tableContent += '</tr>\n';
		}
		tableContent += '</tbody></table>';
		if (document.getElementById('tableContainer')) document.getElementById('tableContainer').innerHTML = tableContent;
	}

	function timeZoom(start, clicked, stop) {
		var delta_time = (stop - start) / zoom_speed;
		var round_time = 1;
		if (delta_time > 864000000) round_time = 86400000;
		else if (delta_time > 345600000) round_time = 21600000;
		else if (delta_time > 10800000) round_time = 3600000;
		else if (delta_time > 900000) round_time = 60000;
		delta_time = Math.round(delta_time/round_time) * round_time;
		start = Math.round((clicked - delta_time/2)/round_time) * round_time;
		// alert(round_time + ' ' +delta_time + ' ' +start);
		var startDate = new Date(start);
		var stopDate = new Date(start + delta_time);
		return [startDate.format('Y-m-d H:i:s'), stopDate.format('Y-m-d H:i:s')];
	}

	function add_history(dir) {
		if (myHistoryCounter+dir<-1 && myHistoryCounter+dir>=myHistory.length) return;
		myHistoryCounter += dir;
		if (dir==0) {
			myHistoryCounter += 1;
			myHistory.length = myHistoryCounter;
			myHistory[myHistoryCounter] = updateLink('history');
		}
		else {
			var param = getQueryParams(myHistory[myHistoryCounter]);
			plotTs(param['ts'], [], param['start'], (typeof(param['stop']) === 'undefined')? '': param['stop']);
			var conf = ['minY','maxY','logY','height','style'];
			for (c=0; c<conf.length; c++) {
				document.getElementById(conf[c]).value = (typeof(param[conf[c]]) === 'undefined')? '': param[conf[c]];
			}
		}
		document.getElementById('myBack').style.display = (myHistoryCounter<1)? 'none': 'inline';
		document.getElementById('myFwd').style.display = (myHistoryCounter==myHistory.length-1)? 'none': 'inline';
		// alert(JSON.stringify(myHistory, null, '\t'));
	}

	function decodeTs(tsRequest){
		const ts = tsRequest.split(';');
		for (var i=0; i<ts.length; i++) {
			const s = ts[i].split(',');
			curves[i] = {'request':s[0],'x':s[1],'y':s[2],'response': s[0]};
			if (document.location.search.indexOf('replacets=false')>-1) curves[i+ts.length] = {'request':s[0],'x':s[1],'y':s[2],'response': s[0]};
		}
		return tsRequest;
	}
	function newdecodeTs(tsRequest){
		var ts = tsRequest.split(';');
		var uniqueTs = new Array();
		var fullTs = new Array();
		var formulaTs = new Array();
		var response = new Array();
		var responseIndex;
		for (var i=0; i<ts.length; i++) {
			responseIndex = -1;
			var s = ts[i].split(',');
			if (typeof(s[1]) === 'undefined') s[1]=1;
			if (typeof(s[2]) === 'undefined') s[2]=1;
			// a formula must contain the character '$'
			if (s[0].indexOf('$')>-1) {
				var f = s[0].split('$');
				for (var j=0; j<f.length; j++) {
					if (j % 2) {
						if (formulaTs.indexOf(f[j]+','+s[1])==-1) {
							formulaTs.push(f[j]+','+s[1]);
							response.push(f[j]);
						}
					}
				}
			}
			else {
				responseIndex = (uniqueTs.push(s[0]+','+s[1])) - 1;
			}
			curves[i] = {'request':s[0],'x':s[1],'y':s[2],'response': (responseIndex>=0? responseIndex: response)};
		}
		// print_r(formulaTs);
		// return time series included only in formulae plus time series not inclded in formulae
		// return $(formulaTs).not(uniqueTs).get().concat(uniqueTs).join(';');
		return uniqueTs.concat($(formulaTs).not(uniqueTs).get()).join(';');
	}

	// alert(decodeTs("1,1,1;2;3;$4$+$2$,2;5,1,2;$6$-$1$"));

	function SortByTime(a, b){
		return a.x - b.x;
	}

	function strtr(myString, replaceArray){
		var oldString ;
		for (var i in replaceArray) {
			oldString = '';
			while (myString !== oldString) {
				oldString = myString;
				myString = oldString.replace(i,replaceArray[i]);
			}
		}
		return myString;
	}

	function mathEval(exp) {
		// var invalidExpression = "Invalid arithmetic expression"; 
		var invalidExpression = NaN; 
		var reg = /(?:[a-z$_][a-z0-9$_]*)|(?:[;={}\[\]"'!&<>^\\?:])/ig,
		valid = true;
		// Detect valid JS identifier names and replace them
		exp = exp.replace(reg, function ($0) {
			// If the name is a direct member of Math, allow
			if (Math.hasOwnProperty($0))
				return "Math."+$0;
			// Otherwise the expression is invalid
			else
				valid = false;
		});
		// Don't eval if our replace function flagged as invalid
		if (!valid) return invalidExpression;
		try { var a = eval(exp);  return a} catch (e) { return invalidExpression; };
	}

	function evalFormulae(data){
		// console.log('evalFormulae()',data);
		if ((parameters.correlation != null) && (data.length>1)) {
			var d0;
			for (d0=0; d0<data.length; d0++) {
				if (data[d0].yaxis=="2") {break;}
			}
			var cdata;
			for (var i=0; i<data.length; i++) {
				if (i == d0) continue;
				var k;
				cdata = [];
				// console.log('i', i, data[i].data.length, d0, data[d0].data.length);
				for (var j=0; j<data[i].data.length; j++) {
					k = 0;
					// console.log(k, data[d0].data[k][0], data[i].data[j][0]);
					while (typeof data[d0].data[k] != 'undefined' && (typeof data[d0].data[k][0] == 'undefined' || data[d0].data[k][0] < data[i].data[j][0])) {k++; /*console.log(k, data[d0].data[k][0], data[i].data[j][0]);*/}
					if (k>0 && typeof data[d0].data[k-1] != 'undefined' && typeof data[d0].data[k-1][1] != 'undefined') cdata.push([data[d0].data[k-1][1], data[i].data[j][1], data[d0].data[k-1][0]]);
				}
				data[i].data = cdata;
				data[i].display_unit = '<b>'+data[d0].label+'</b>';
				// console.log('cdata ',cdata);
			}
			data.splice(d0, 1);
			curves.splice(d0, 1);
			// console.log('data ',data);
		} 
		return data;
	}

	function plotTs(tsRequest, ts2, start, stop){
		if ($_GET.debug) console.log('plotTs(), tsRequest',tsRequest,'ts2',ts2);
		curves = new Array();
		myRequest = tsRequest;
		var event = '';
		for (j=0; j<events.length; j++) event += document.getElementById('show_'+events[j]).checked? '&show_'+events[j]+'='+document.getElementById('filter_'+events[j]).value: '';
		event = `${event}&decimation=${decimation}&decimation_samples=${decimationSamples}`;
		// console.log(document.getElementById('hideOverMaxmin').checked);
		// document.getElementById('hideOverMaxmin').value = true;
		var hideOverMaxmin = document.getElementById('hideOverMaxmin')? document.getElementById('hideOverMaxmin').checked: false;
		if (hideOverMaxmin) {
			event += '&hideOverMaxmin=';
			var maxY = document.getElementById('maxY').value;
			if (maxY.length>1) event += '&maxY=' + maxY;
			var minY = document.getElementById('minY').value;
			if (minY.length>1) event += '&minY=' + minY;
		}
		if ($_GET.normalize) event += '&normalize';
		// adjust plot dimensions
		var height = document.getElementById('height').value.length? document.getElementById('height').value: $(window).height()-200;
		if (height < 300) height = 300;
		// var plotWidth = $(window).width()-(($('#tree').length > 0)? 280: 0);
		var plotWidth = $("#plotContainer").width()-14
		if (plotWidth < 300) plotWidth = 300;
		$("#mybackground").width(plotWidth-14).height(((height-0)-14)+'px');
		$("#placeholder").width(plotWidth-14).height(height+'px');
		$("#placeholder").html("&nbsp;&nbsp;&nbsp;&nbsp;<img src='./img/timer.gif'>");
		var start_param = 'start=' + start;
		var stop_param = '';
		updatePlot = 0;
		if (stop.indexOf('update')>=0) {
			var u = stop.split(',');
			updatePlot = (typeof(u[1]) === 'undefined')? 1: (u[1]>0.1? u[1]: 0.1);
			updateBackground = (typeof(u[2]) === 'undefined')? 300: (u[2]>60? u[2]: 60);
			stop_param = '&no_posttimer=';
		}
		else if (stop.length) stop_param = '&stop=' + stop;
		var prestart = document.getElementById('show_hc').checked? '&prestart=hc': '';
		var ts = tsRequest;
		const stopTime = stop.length? new Date(stop): new Date();
		// if (downtimeCheck) {
			$.get(plotService+'&Seconds_Behind_Master', function(behind) {
				if (behind>60) alert('WARNING\nThe data has not been updated for '+behind+' seconds');
			})
		//}
		if ($_GET.debug) console.log('ts',ts,'ts2', ts2);
		if (ts.length+ts2.length < 1) {
			$("#placeholder").html("&nbsp;&nbsp;&nbsp;&nbsp;<h4>WARNING: No Time Series has been requested</h4>Please select e Time Series and a time period");
		}
		else 
			if ($_GET.debug) console.log('plotService',plotService);
			if (ts.length>0 && ts2.length>0) {
				curves = [];
				if (plotService.indexOf('analysis/')>-1) {
					// console.log(plotService+'&'+start_param+stop_param+'&ts='+ts+';'+ts2.join(';')+prestart+event);
					$.getJSON(plotService+'&'+start_param+stop_param+'&ts='+ts+';'+ts2.join(';')+prestart+event, function(data) {
						for (var i=0; i<data.ts.length; i++) {
							curves.push({request: data.ts[i].ts_id, x: ''+data.ts[i].xaxis, y: data.ts[i].yaxis, response: i});
						}
						plotData(evalFormulae(data.ts), data.event, data.forecast, start, stop);
					})
				}
				else {
					var urls = ['./lib/service/csv_service.php?'+start_param+stop_param+'&ts='+ts2.join(';')+event,plotService+'&'+start_param+stop_param+'&ts='+ts+prestart+event];
					Promise.all(urls.map(url =>
						fetch(url).then(resp => resp.json())
					)).then(mdata => {
						// console.log('mdatats', mdata[0].ts.concat(mdata[1].ts), start, stop);
						for (var i=0; i<mdata[0].ts.length; i++) {
							curves.push({request: mdata[0].ts[i].ts_id, x: ''+mdata[0].ts[i].xaxis, y: mdata[0].ts[i].yaxis, response: i});
						}
						for (var j=0; j<mdata[1].ts.length; j++) {
							curves.push({request: mdata[1].ts[j].ts_id, x: ''+mdata[1].ts[j].xaxis, y: mdata[1].ts[j].yaxis, response: mdata[1].ts[j].ts_id});
						}
						plotData(evalFormulae(mdata[0].ts.concat(mdata[1].ts)), mdata[1].event, mdata[0].forecast? mdata[0].forecast.concat(mdata[1].forecast): mdata[1].forecast, start, stop);
					})
				}
			}
			else if (ts2.length>0) {
				$.getJSON((plotService.indexOf('analysis/')>-1? plotService+'&': './lib/service/csv_service.php?')+start_param+stop_param+'&ts='+ts2.join(';')+event, function(data) {
					// console.log('csv data', data);
					for (var i=0; i<data.ts.length; i++) {
						curves.push({request: data.ts[i].ts_id, x: ''+data.ts[i].xaxis, y: data.ts[i].yaxis, response: i});
					}
					// console.log('curves', curves, evalFormulae(data.ts));
					plotData(evalFormulae(data.ts), data.event, data.forecast, start, stop);
				})
				.fail(function(jqxhr, textStatus, error) {
					console.log('fail',jqxhr, textStatus, error);
					alert(textStatus);
					alert(error);
					document.getElementById('placeholder').innerHTML = "&nbsp;&nbsp;&nbsp; ERROR: no data found, please double check start, stop and timeseries selection, "+textStatus+ ' - '+error;
				})
				.always(function() {
					if (typeof document.getElementById('placeholder').children[0] != 'undefined') document.getElementById('placeholder').children[0].src = '';
				});
			}
			else if (ts.length>0) $.get(plotService+'&'+start_param+stop_param+'&ts='+ts+prestart+event, function(data) {
				if ($_GET.debug) console.log('$.get data',data, '.get()', plotService+'&'+start_param+stop_param+'&ts='+ts+prestart+event);
				for (var i=0; i<data.ts.length; i++) {
					curves.push({request: data.ts[i].ts_id, x: ''+data.ts[i].xaxis, y: data.ts[i].yaxis, response: data.ts[i].ts_id});
				}
				var num_rows = 0;
				for (var dIndex=0; dIndex<data.ts.length; dIndex++) {
					num_rows += data.ts[dIndex].num_rows;
				}
				if (num_rows == 0) {
					$("#placeholder").html("&nbsp;&nbsp;&nbsp;&nbsp;<h4>WARNING: No data available</h4>Please select e Time Series and a time period containing some data");
				}
				// console.log('num_rows: '+num_rows);
				const downtimeCheck = document.getElementById('downtimeCheck')? document.getElementById('downtimeCheck').checked: false;
				if (downtimeCheck) {
					const startTimestamp = data.ts[0].data[0].x? data.ts[0].data[0].x: data.ts[0].data[0][0];
					const missingTS = new Array();
					for (var dataIndex=0; dataIndex<data.ts.length; dataIndex++) {
						num_rows += data.ts[dataIndex].num_rows
						const lastTimestamp = data.ts[dataIndex].data[data.ts[dataIndex].data.length-1][0];
						if ((stopTime.valueOf()-lastTimestamp) / (stopTime.valueOf()-startTimestamp) > 10 / data.ts[dataIndex].data.length) missingTS.push(data.ts[dataIndex].label);
					}
					if (missingTS.length) alert("WARNIG\nsome data may be missing (may be server or replication downtime) for Time Series:\n"+missingTS.join(','));
				}
				plotData(evalFormulae(data.ts), data.event, data.forecast, start, stop);
			})
			.fail(function(jqxhr, textStatus, error) {
				console.log('fail',jqxhr.status, textStatus, error);
				document.getElementById('placeholder').innerHTML = "&nbsp;&nbsp;&nbsp; ERROR: "+jqxhr.status+' '+error;
			})
			.always(function() {
				if (typeof document.getElementById('placeholder').children[0] != 'undefined') document.getElementById('placeholder').children[0].src = '';
			});
	}

	function plotData(dataTs, dataEvent, forecastData, start, stop){
		var startArray = start.split(';');
		var stopArray = stop.split(';');
		if ($_GET.debug) console.log('plotData, dataTs:', dataTs);
		if (document.getElementById('show_hc').checked) {
			hcPlot(dataTs, dataEvent, forecastData, startArray, stopArray);
			if (document.getElementById('pdfExport')) document.getElementById('pdfExport').style.display = 'inline';
		}
		else if (document.getElementById('show_chartjs').checked) {
			chartjsPlot(dataTs, dataEvent, forecastData, startArray, stopArray);
			if (document.getElementById('pdfExport')) document.getElementById('pdfExport').style.display = 'none'; 
		}
		else if (document.getElementById('show_flot').checked) {
			flotPlot(dataTs, dataEvent, startArray, stopArray);
			if (document.getElementById('pdfExport')) document.getElementById('pdfExport').style.display = 'none'; 
		}
		else {
			$("#mybackground").height('1px');
			$("#placeholder").height('1px');
			document.getElementById('placeholder').innerHTML = '';
		}
		if (document.getElementById('show_table').checked) {
			fillTable(dataTs);
		}
		else {
			// show table if not numeric dataTs
			var k = 0;
			tableData = new Array();
			for (var j=0; j<dataTs.length; j++) {
				if (dataTs[j]['isString']) {
					tableData[k++] = dataTs[j];
				}
			}
			if (k) fillTable(tableData);
		}
	}

// ------------
// Chart.js plot
// ------------
	function chartjsPlot(data, dataEvent, forecastData, start, stop) {
		if (window.myLine) window.myLine.destroy();
		var style = 'step';
		if (document.getElementById('style') && document.getElementById('style').value.length) {
			style = document.getElementById('style').value;
		}
		const colors = [window.chartColors.blue, window.chartColors.red, window.chartColors.green, window.chartColors.orange, window.chartColors.magenta, window.chartColors.brown];
		var minYArray = (document.getElementById('minY') && document.getElementById('minY').value.length)? document.getElementById('minY').value.split(';'): [''];
		var maxYArray = (document.getElementById('maxY') && document.getElementById('maxY').value.length)? document.getElementById('maxY').value.split(';'): [''];
		var logYArray = (document.getElementById('logY') && document.getElementById('logY').value.length)? document.getElementById('logY').value.split(';'): ['0'];
		var timeFormat = 'DD/MM/Y HH:mm:ss';
		var color = Chart.helpers.color;
		var options = {
			legend: {
				position: 'bottom'
			},
			radius: 20,
			scales: {
				xAxes: [{
					type: "time",
					time: {
						format: timeFormat,
						tooltipFormat: timeFormat,
					},
					scaleLabel: {
						display: true,
						labelString: 'Date'
					},
					ticks: {
						callback: function(value, index, values) {
							return index==values.length-1? '': moment(values[index]? values[index]['_i']: 0).format('DD/MM/Y');
						}
					}
				}],
				yAxes: [{
					id: 'y1',
					type: 'linear',
					position: 'left',
					ticks: {}
				}, {
					id: 'y2',
					type: 'linear',
					position: 'right',
					grid: false,
					ticks: {}
				}],
			},
		};
		if (minYArray[0]!="") options.scales.yAxes[0].ticks.min = minYArray[0]-0;
		if (maxYArray[0]!="") options.scales.yAxes[0].ticks.max = maxYArray[0]-0;
		if (logYArray[0]!="0") options.scales.yAxes[0].type = 'logarithmic';
		if (minYArray[1]!="") options.scales.yAxes[1].ticks.min = minYArray[1]-0;
		if (maxYArray[1]!="") options.scales.yAxes[1].ticks.max = maxYArray[1]-0;
		if (logYArray[1]!="0") options.scales.yAxes[1].type = 'logarithmic';
		var datasets = [];
		var y2 = false;
		// console.log('style',style);
		for (var j=0; j<data.length; j++) {
			var d = []
			for (var i=0; i<data[j].data.length; i++) d.push({x: data[j].data[i][0],y: data[j].data[i][1]});
			if (data[j].yaxis=='2') y2 = true;
			datasets.push({
				label: data[j].label,
				backgroundColor: color(colors[j % colors.length]).alpha(0.5).rgbString(),
				borderColor: colors[j % colors.length],
				fill: style.indexOf('area')>-1,
				pointStyle: style=='scatter'? 'circle': 'line',
				showLine: style!='scatter',
				lineTension: (style=='line' || style=='area')? 0: 0.8,
				cubicInterpolationMode: 'monotone',
				steppedLine: style=='step',
				data: d,
				yAxisID: 'y'+data[j].yaxis,
			});
		}
		if (typeof forecastData != 'undefined') {
			options.legend.labels = {
				filter: function(item, chart) {
					// Logic to remove a particular legend item goes here
					return !item.text.includes('forecast ');
				}
			};
			var d = [];
			for (var i=0; i<forecastData['fv'].length; i++) d.push({x: forecastData['fv'][i][0],y: forecastData['fv'][i][1]});
			datasets.push({
				label: 'Forecast',
				data: d,
				backgroundColor: color(colors[1]).alpha(0.5).rgbString(),
				borderColor: colors[1],
				fill: false,
				pointStyle: 'line',
				showLine: true,
				lineTension: 0.8,
				cubicInterpolationMode: 'monotone',
				steppedLine: false,
			});
			var d1 = []; var d2 = [];
			for (var i=0; i<forecastData['f80'].length; i++) {
				d1.push({x: forecastData['f80'][i][0],y: forecastData['f80'][i][1]});
				d2.push({x: forecastData['f80'][i][0],y: forecastData['f80'][i][2]});
			}
			datasets.push({
				label: 'forecast 80%',
				data: d1,
				color: 'pink',
				borderColor: 'pink',
				fill: false,
				pointStyle: 'line',
				showLine: true,
				lineTension: 0,
				cubicInterpolationMode: 'monotone',
				steppedLine: false,
			});
			datasets.push({
				label: 'forecast 80%',
				data: d2,
				color: 'pink',
				borderColor: 'pink',
				backgroundColor: 'pink',
				fill: '-1',
				pointStyle: 'line',
				showLine: true,
				lineTension: 0,
				cubicInterpolationMode: 'monotone',
				steppedLine: false,
			});
			d1 = []; var d2 = [];
			for (var i=0; i<forecastData['f95'].length; i++) {
				d1.push({x: forecastData['f95'][i][0],y: forecastData['f95'][i][1]});
				d2.push({x: forecastData['f95'][i][0],y: forecastData['f95'][i][2]});
			}
			datasets.push({
				label: 'forecast 95%',
				data: d1,
				borderColor: 'mistyrose',
				color: 'mistyrose',
				fill: false,
				pointStyle: 'line',
				showLine: true,
				lineTension: 0,
				cubicInterpolationMode: 'monotone',
				steppedLine: false,
			});
			datasets.push({
				label: 'forecast 95%',
				data: d2,
				color: 'mistyrose',
				borderColor: 'mistyrose',
				backgroundColor: 'mistyrose',
				fill: '-1',
				pointStyle: 'line',
				showLine: true,
				lineTension: 0,
				cubicInterpolationMode: 'monotone',
				steppedLine: false,
			});
		}
		if (!y2) options.scales.yAxes.pop();
		var config = {
			type: 'line',
			data: {
				datasets: datasets,
			},
			options: options,
		};
		$("#canvas").show();
		$("#placeholder").hide();

		var ctx = document.getElementById("canvas").getContext("2d");
		// console.log('data', data, 'config', config, minYArray, maxYArray, logYArray);
		window.myLine = new Chart(ctx, config);
	}


// ------------
// Flot plot
// ------------
	function flotPlot(data, dataEvent, start, stop){
		$("#canvas").hide();
		$("#placeholder").show();
		var options = {
			series: { lines: { show: true } },
			grid: { hoverable: true, clickable: true},
			xaxes: [{ zoomRange: [0.01, 120000000], panRange: [1370037600000, 1401573600000], mode: "time", timezone: "browser", tickLength: 5 }],
			yaxes: [ { }, { position: "right" } ],
			legend: { position: 'sw' },
			canvas: true
		};
		myPlotClass = [];
		var minYArray = (document.getElementById('minY') && document.getElementById('minY').value.length)? document.getElementById('minY').value.split(';'): [];
		var maxYArray = (document.getElementById('maxY') && document.getElementById('maxY').value.length)? document.getElementById('maxY').value.split(';'): [];
		var logYArray = (document.getElementById('logY') && document.getElementById('logY').value.length)? document.getElementById('logY').value.split(';'): [];
		var xaxis_max_index = 1;
		var yaxis_max_index = 1;
		for (var j=0; j<data.length; j++) {
			if (typeof(data[j]['label']) === 'undefined') continue;
			myPlotClass[j] = new Array();
			myPlotClass[j].label = data[j]['label'];
			myPlotClass[j].xaxis = data[j]['xaxis']-0;
			myPlotClass[j].yaxis = data[j]['yaxis']-0;
			myPlotClass[j].data = data[j]['data'];
			myPlotClass[j].lines = { show: true, fill: false };
			if (xaxis_max_index < data[j]['xaxis']) xaxis_max_index = data[j]['xaxis'];
			if (yaxis_max_index < data[j]['yaxis']) yaxis_max_index = data[j]['yaxis'];
		}
		for (var i=1; i<=yaxis_max_index; i++) {
			if (logYArray[i-1] && logYArray[i-1]=='1') {
				options.yaxes[i-1].transform = function (v) { return v>0? Math.log(v): -23; };
				options.yaxes[i-1].inverseTransform = function (v) { return Math.exp(v); };
			}
			else {
				if (!options.yaxis) options.yaxis = []; options.yaxis[i-1] = {};
				if (minYArray[i-1]) options.yaxis[i-1].min = minYArray[i-1];
				if (maxYArray[i-1]) options.yaxis[i-1].max = maxYArray[i-1];
			}
		}
		var localPlot = $.plot($("#placeholder"), myPlotClass, options);
		myPlot = localPlot;
		// add labels
		$("#placeholder").append('<div style="position:absolute;left:120px;top:10px;color:#676;font-size:smaller">eGiga2m - '+start+' - '+stop+'</div>');
		if (document.getElementById('pdfExport')) document.getElementById('pdfExport').style.display = 'none'; 
		if (document.getElementById('hidetree')) document.getElementById('hidetree').style.display = 'inline';
		// flotUpdate();
	}
	// show value and time in tooltip
	function showTooltip(x, y, contents) {
		$('<div id="tooltip">' + contents + '</div>').css( {
			position: 'absolute',
			display: 'none',
			top: y + 5,
			left: x + 5,
			border: '1px solid #fdd',
			padding: '2px',
			'background-color': '#fee',
			opacity: 0.80
		}).appendTo("body").fadeIn(200);
	}
	var previousPoint = null;
	$("#placeholder").bind("plothover", function (event, pos, item) {
		if (typeof(pos.x)!=='undefined') $("#x").text(pos.x.toFixed(2));
		if (typeof(pos.y)!=='undefined') $("#y").text(pos.y.toFixed(2));
		if (item) {
			if (previousPoint != item.dataIndex) {
				previousPoint = item.dataIndex;
				$("#tooltip").remove();
				var myDate = new Date(item.datapoint[0]-0);
				showTooltip(item.pageX, item.pageY, '&nbsp;&nbsp;'+item.datapoint[1]+'<br>&nbsp;&nbsp;'+myDate.toLocaleString());
			}
		}
		else {
			$("#tooltip").remove();
			previousPoint = null;
		}
	});

	$("#placeholder").bind("plotclick", function (event, pos, item) {
		if (document.getElementById('startInput')) {
			var i=0; while (typeof(myPlotClass[0].data[i][0]) === 'undefined') {i++}
			startStop = timeZoom(myPlotClass[0].data[i][0], pos.x, myPlotClass[0].data[myPlotClass[0].data.length-1][0]);
			document.getElementById('startInput').value = document.getElementById('start').value = startStop[0];
			document.getElementById('stopInput').value = document.getElementById('stop').value = startStop[1];
			// flotPlot(myRequest, startStop[0], startStop[1]);
			plotTs(myRequest, [], startStop[0], startStop[1]);
			add_history(0);
		}
		else if (document.location.search.indexOf('noclick')==-1) {
			document.location = './index.html'+document.location.search;
		}
	});

// ------------
// HighCharts plot
// ------------
	var printing = false;
	function hcPlot(data, eventData, forecastData, startArray, stopArray){
		$("#canvas").hide();
		$("#placeholder").show();
		if ($_GET.debug) console.log('hcPlot(), data', data, 'curves', curves)
		var emptyMessage = "No data available in selected period";
		var height = document.getElementById('height').value.length? document.getElementById('height').value: $(window).height()-200;
		if (height < 300) height = 300;
		var style = 'step';
		var categories = new Array(false,false,false,false,false,false,false,false,false,false);
		var minYArray = (document.getElementById('minY') && document.getElementById('minY').value.length)? document.getElementById('minY').value.split(';'): [];
		var maxYArray = (document.getElementById('maxY') && document.getElementById('maxY').value.length)? document.getElementById('maxY').value.split(';'): [];
		var logYArray = (document.getElementById('logY') && document.getElementById('logY').value.length)? document.getElementById('logY').value.split(';'): [];
		if (document.getElementById('style') && document.getElementById('style').value.length) {
			style = document.getElementById('style').value;
		}
		var xaxis_max_index = 1;
		var yaxis_max_index = 1;
		for (var j=0; j<data.length; j++) {
			curves[j].request = data[j].ts_id;
			if (yaxis_max_index < curves[j].y) yaxis_max_index = curves[j].y;
		}
		var kk=0;
		for (j=0; j<events.length; j++) document.getElementById('event_'+events[j]).style.display = 'none';
		myPlotClass = [];
		// console.log('curves', curves);
		for (var j in curves) {
			if (j=='clone') continue;
			if ($_GET.debug) console.log('kk', kk, 'j', j,'data[kk]', data[kk]);
			if (data) while (data[kk] && data[kk]['ts_id'].split('[')[0]==curves[j]['request']) {
				const query_time = (data[kk]['query_time'])? data[kk]['query_time']: 0;
				const samplesPerSecond = query_time>0? ', Samples per second: '+(data[kk]['num_rows']/query_time).toFixed(0): '';
				const title = 'Samples: '+data[kk]['data'].length+(data[kk]['num_rows']>data[kk]['data'].length? '/'+data[kk]['num_rows']: '')+((data[kk]['query_time'])?', query time: '+query_time.toFixed(2)+' [s]'+samplesPerSecond:'');
				var name = '<span title="'+title+'">'+((typeof(tsLabel) !== 'undefined' && typeof(tsLabel[kk]) !== 'undefined')? tsLabel[kk]: (yaxis_max_index>1? 'Y'+(curves[j]['y']? curves[j]['y']: 1)+' ':'')+data[kk]['label'].replace(/&deg;/g, "°"))+'</span>';
				if (typeof($_GET['num_rows']) !== 'undefined') name = name+' num_rows: '+data[kk]['num_rows'];
				myPlotClass.push({
					shortName: ((typeof(tsLabel) !== 'undefined' && typeof(tsLabel[kk]) !== 'undefined')? tsLabel[kk]: (yaxis_max_index>1? 'Y'+(curves[j]['y']? curves[j]['y']: 1)+' ':'')+data[kk]['label'].replace(/&deg;/g, "°")),
					name: '<span title="'+title+'">'+((typeof(tsLabel) !== 'undefined' && typeof(tsLabel[kk]) !== 'undefined')? tsLabel[kk]: (yaxis_max_index>1? 'Y'+(curves[j]['y']? curves[j]['y']: 1)+' ':'')+data[kk]['label'].replace(/&deg;/g, "°"))+'</span>',
					xAxis: data[kk]['xaxis']-1,
					yAxis: $.isNumeric(curves[j].y)? curves[j].y-1: 0,
					data: data[kk]['data'],
					visible: typeof(visible[kk]) !== 'undefined'? visible[kk]=='true': true,
					step: style=='step'? 'left': false
				});
				if (xaxis_max_index < data[kk]['xaxis']) xaxis_max_index = data[kk]['xaxis'];
				if (typeof(data[kk]['categories']) !== 'undefined') categories[data[kk]['yaxis']-1] = data[kk]['categories'];
				if (data[kk]['ranges']) {
					myPlotClass.push({
						name: 'Range',
						data: data[kk]['ranges'],
						type: 'arearange',
						lineWidth: 0,
						linkedTo: ':previous',
						color: Highcharts.getOptions().colors[kk],
						fillOpacity: 0.5,
						zIndex: 0,
						marker: {
							enabled: false
						}
					});
				}
				kk++;
			}
			else emptyMessage = "No variable selected"
		}
		// console.log('myPlotClass', myPlotClass);
		if (typeof forecastData != 'undefined') {
			myPlotClass.push({
				name: 'Forecast',
				data: forecastData['fv'],
				color: 'red',
				zIndex: 1
			});
			myPlotClass.push({
				name: 'Forecast 80%',
				data: forecastData['f80'],
				type: 'arearange',
				lineWidth: 0,
				linkedTo: ':previous',
				color: 'pink',
				opacity: 0.4,
				fillOpacity: 0.4,
				zIndex: 0,
				marker: {
					enabled: false
				}
			});
			myPlotClass.push({
				name: 'Forecast 95%',
				data: forecastData['f95'],
				type: 'arearange',
				lineWidth: 0,
				linkedTo: ':previous',
				color: 'lightpink',
				opacity: 0.3,
				fillOpacity: 0.3,
				zIndex: -1,
				marker: {
					enabled: false
				}
			});
		}
		// console.log('eventData: ',eventData);
		if (eventData) for (var j in eventData) {
			if (j=='clone') continue;
			if (typeof(fade_level[j]) === 'undefined') continue;
			// console.log('eventData[j][data]: ',eventData[j]['data'], 'j',j);
			if (typeof(eventData[j]['label']) === 'undefined') {
				myPlotClass.push({
					name: j,
					showInLegend: false,
					xAxis: 0,
					yAxis: 0,
					data: eventData[j]['data'],
					type: 'scatter',
					step: false
				});
				document.getElementById('event_'+j).style.display = 'inline';
			}
		}
		if (typeof(window.$_GET['tsLabel']) !== 'undefined') {
			var tsLabel = window.$_GET['tsLabel'].split(';');
			for (var j in data) {
				myPlotClass[j].name=(typeof(tsLabel[j]) !== 'undefined')? tsLabel[j]: tsLabel[0];
			}
		}
		var chartConfig = {
			credits: {
				enabled: false
			},
			exporting: {
				url: hcExportService
			},
			legend: {
			  useHTML: true
			},
			chart: {
				events: {
					beforePrint: function () {
						window.printing = true;
					},
					afterPrint: function () {
						window.printing = true;
					},
					click: function (event) {
						if (!window.printing) {
							var width = $('#plotContainer').width();
							if (document.getElementById('startInput')) {
								var i=0; while (typeof(myPlotClass[0].data[i][0]) === 'undefined') {i++}
								startStop = timeZoom(myPlotClass[0].data[i][0], event.xAxis[0].value, myPlotClass[0].data[myPlotClass[0].data.length-1][0]);
								document.getElementById('startInput').value = document.getElementById('start').value = startStop[0];
								document.getElementById('stopInput').value = document.getElementById('stop').value = startStop[1];
								plotTs(myRequest, [], startStop[0], startStop[1]);
								add_history(0);
							}
							else if (document.location.search.indexOf('noclick')==-1) {
								document.location = document.location.pathname.match(/[^\/]+$/)[0].replace('_plot.html', '.html')+document.location.search;
							}
						}
						window.printing = false;
					},
					load: function () {
						if (!updatePlot || updateService.length==0) return;
						var request = new Array();
						for (var j=0; j<myPlotClass.length; j++) {
							if (j=='clone') continue;
							var name = myPlotClass[j].shortName;
							if (name.substring(0, 1)=='Y' && $.isNumeric(name.substring(1, 2))) name = name.substring(3);
							request[j] = name;
						}
						updateRequest = updateService+request.join(',');
						// todo: move to external function updateHc()
						updateId = setInterval(function () {
							if (updateDecimation) {
								if (updateCounter>0) { updateCounter--; return; }
								updateCounter = updateDecimation;
							}
							$.get(updateRequest, function( data ) {
								// console.debug(updateCounter);
								for (var j in data) {
									if (j=='clone') continue;
									// validate timestamp
									if (data[j][0] > 1500000000000) myPlot.series[j].addPoint(data[j], true, false); 
								}
							});
						}, updatePlot*1000);
					}
				},
				renderTo: placeholder,
				type: style=='step'? 'line': style,
				height: height
			},
			title: {text: 'eGiga2m'},
			plotOptions: { series: { animation: !updatePlot, turboThreshold: 4000 } },
			xAxis: [],
			yAxis: [],
			tooltip: {
				headerFormat: '<b>{series.name}</b><br>',
				pointFormat: '{point.x:%d/%m/%Y %H\:%M\:%S}: {point.y:.3f} ',
				formatter: function () {
					if (activePoint == -1) {activePoint = 0; return} // used by tooltip keyboard navigation
					activePoint = this.series.data.indexOf( this.point); 
					var myDate = new Date(this.x);
					var prestart = '';
					if (parameters.correlation != null) {
						for (var k=0; k<myPlotClass[0].data.length; k++) {
							if (myPlotClass[0].data[k][0] == this.x) {
								myDate = new Date(myPlotClass[0].data[k][2]);
								return myDate.format('Y-m-d H:i:s') + ' x: ' + myPlotClass[0].data[k][0] + ' y: ' + myPlotClass[0].data[k][1];
							}
						}
					}
					for (var j=0; j<myPlotClass.length; j++) {
					// for (var j in myPlotClass) {
						if (myPlotClass[j].name == this.series.name && 
						    this.x == myPlotClass[j].data[0].x &&
						    typeof(myPlotClass[j].data[0].prestart) !== 'undefined') {
							myDate = new Date(myPlotClass[j].data[0].prestart);
							prestart = ' pre-start';
						}
						if (myPlotClass[j].name == this.series.name && this.series.name == 'error') {
							for (var k in myPlotClass[j].data) {
								if (myPlotClass[j].data[k].x != this.x) continue;
								// alert('j: '+j+', val: '+myPlotClass[j].data[k].message); // print_r(myPlotClass);
								return '<b>error</b><br>' + '<br>' + data[myPlotClass[j].data[k].message].label + '<br>' + 
								myDate.format('Y-m-d H:i:s') + prestart + ': <b><br>' + eventData.error.message[myPlotClass[j].data[k].message] + '</b>';
							}
						}
						if (myPlotClass[j].name == this.series.name && (typeof(fade_level[this.series.name]) !== 'undefined')) {
							for (var k in myPlotClass[j].data) {
								if (myPlotClass[j].data[k].x != this.x) continue;
								return '<b>' + this.series.name + '</b><br>' + 
								myDate.format('Y-m-d H:i:s') + ': <b><br>' + eventData[this.series.name].message[myPlotClass[j].data[k].message] + '</b>';
							}
						}
						if (myPlotClass[j].name == this.series.name && 
						    this.x == myPlotClass[j].data[myPlotClass[j].data.length - 1].x &&
						    typeof(myPlotClass[j].data[myPlotClass[j].data.length - 1].poststart) !== 'undefined') {
							this.tooltip.hide(); // prestart = ' stop-time supposed value'; // return '';
						}
					}
					return '<b>'+this.series.name+'</b><br>' + 
					myDate.format('Y-m-d H:i:s') + prestart + ': <b>' + this.y + '</b>';
				}
			},
			lang: {
				noData: emptyMessage
			},
			noData: {
				style: {
					fontWeight: 'bold',
					fontSize: '15px',
					color: '#303030'
				}
			},
			series: myPlotClass
		}
		for (var i=1; i<=xaxis_max_index; i++) {
			chartConfig.xAxis[i-1] = {type: (parameters.correlation == null && curves[0].request.indexOf('FFT')==-1? 'datetime': 'linear'), gridLineWidth: 0,lineColor: '#000',title: {text: startArray[i-1] + ' - ' + stopArray[i-1]},opposite: xaxis_max_index==2 && i==2};
		}
		if (typeof(window.$_GET['hideMenu']) !== 'undefined') {
			chartConfig.navigation={buttonOptions:{enabled: false}};
		}
		if (typeof(window.$_GET['plotLines']) !== 'undefined') {
			var pl = window.$_GET['plotLines'].split(';');
			var plotLines = [];
			for (var i=0; i<pl.length; i++) {
				var v = pl[i].split(',');
				plotLines.push({color: (v.length>2? v[2]:'#FF0000'), width: (v.length>1? v[1]:1), value: v[0]});
			}
			chartConfig.xAxis[0].plotLines = plotLines;
			// https://jsfiddle.net/gh/get/library/pure/highcharts/highcharts/tree/master/samples/highcharts/demo/annotations/
		}
		if (typeof(window.$_GET['xShow']) !== 'undefined') {
			var xShow = window.$_GET['xShow'].split(';');
			for (var i=1; i<=xaxis_max_index; i++) {
				if (((typeof(xShow[i-1]) !== 'undefined')? xShow[i-1]: xShow[0])=='0') {
					chartConfig.xAxis[i-1].lineWidth=0; 
					chartConfig.xAxis[i-1].labels={enabled: false};
				}
			}
		}
		if (typeof(window.$_GET['xLabel']) !== 'undefined' || typeof(window.$_GET['xlabel']) !== 'undefined') {
			var xl = (typeof(window.$_GET['xLabel']) !== 'undefined')? window.$_GET['xLabel']: window.$_GET['xlabel'];
			var xLabel = xl.split(';');
			for (var i=1; i<=xaxis_max_index; i++) {
				chartConfig.xAxis[i-1].title={text: (typeof(xLabel[i-1]) !== 'undefined')? xLabel[i-1]: xLabel[0]};
			}
		}
		if (typeof(window.$_GET['title']) !== 'undefined') {
			chartConfig.title={text: window.$_GET['title']};
		}
		chartConfig.xAxis[0]['gridLineWidth'] = 1;
		for (var i=1; i<=yaxis_max_index; i++) {
			chartConfig.yAxis[i-1] = {title: {text: 'Y'+(i? i: 1)},opposite: yaxis_max_index==2 && i==2};
			if (minYArray[i-1]) chartConfig.yAxis[i-1].min = minYArray[i-1];
			if (maxYArray[i-1]) chartConfig.yAxis[i-1].max = maxYArray[i-1];
			/* if (i>1 && (minYArray[i-1] || maxYArray[i-1])) {
				chartConfig.yAxis[i-1].startOnTick = false;
				chartConfig.chart.alignTicks = false;
			} */
			if (logYArray[i-1] && logYArray[i-1]=='1') chartConfig.yAxis[i-1].type = 'logarithmic';
			chartConfig.yAxis[i-1].labels = {formatter: function () {return this.value;}};
			if (categories[i-1]) chartConfig.yAxis[i-1].categories = categories[i-1];
		}
		if (typeof(window.$_GET['yLabel']) !== 'undefined') {
			var yLabel = window.$_GET['yLabel'].split(';');
			for (var i=1; i<=yaxis_max_index; i++) {
				chartConfig.yAxis[i-1].title={text: (typeof(yLabel[i-1]) !== 'undefined')? yLabel[i-1]: (yLabel[0]? yLabel[0]: '')};
			}
		}
		else {
			// detect display_unit (if any) and attach as Y label
			ylab = [];
			for (var j in curves) {
				if (j=='clone') continue;
				if (ylab[data[j]['yaxis']]) ylab[data[j]['yaxis']].push(data[j]['display_unit']); else ylab[data[j]['yaxis']] = [data[j]['display_unit']];
				// console.log('ylab', j, data[j]['yaxis'], data[j]['display_unit']);
			}
			for (var k=1; k<ylab.length; k++) {
				if (!ylab[k]) {
					$("#placeholder").html("&nbsp;&nbsp;&nbsp;&nbsp;<h4>ERROR: missing axis Y"+k+"</h4>At least one time series must be displayed on Y"+k+" axes.<br>Please change axis selection clicking on axis icons: <img src='./img/y0axis.png'> <img src='./img/y1axis.png'> <img src='./img/y2axis.png'>");
				}
				lab = ylab[k][0];
				for (var m=1; m<ylab[k].length; m++) {
					if (lab != ylab[k][m]) {lab = ylab[k].join(' - '); break;}
				}
				chartConfig.yAxis[k-1].title = {text: 'Y'+k+' - '+lab};
			}				
		}
		Highcharts.setOptions({
			global: {
				useUTC: false // true by default
			}
		});
		var localPlot = new Highcharts.Chart(chartConfig, function(chart){ // used by tooltip keyboard navigation
			if (mychart != -1) {mychart = this; return;} // avoid multiple keydown function
			mychart = this;
			$(document).keydown(function(e){
				var mySeries = ((typeof(mychart.hoverSeries) == 'undefined') || (mychart.hoverSeries == null))? 0: mychart.hoverSeries.index;
				// print_r(mychart);
				switch(e.which) {
					case 37:
						if(activePoint>0)
							activePoint--;
						break;
					case 39:
						if(activePoint+1 < mychart.series[mySeries].data.length)
							activePoint++;
						break;
				}
				if (typeof(mychart.series[mySeries]) !== 'undefined') mychart.tooltip.refresh(mychart.series[mySeries].data[activePoint]);
			})
		});
		myPlot = localPlot;
		for (var i in myPlotClass) {
			if (myPlotClass[i].showInLegend===false) {
				// don't install en event handler twice, if you cannot tell if it has already been installed, just uninstall it anyway
				$('#event_'+myPlotClass[i].name).off("click");
				$('#event_'+myPlotClass[i].name).click({name: i, label: myPlotClass[i].name},function (event) {
					// alert(myPlotClass[event.data.name].name+' - '+event.data.label);
					if (myPlotClass[event.data.name].name===event.data.label && !myPlotClass[event.data.name].done) {
						myPlot.series[event.data.name].setVisible();
						fade_level[event.data.label] = 1.5 - fade_level[event.data.label];
						$("#event_"+event.data.label).fadeTo(400, fade_level[event.data.label]);
					}
				});
			}
		}
	}


// ------------
// animation
// ------------
	function flotFrameTs() {
		if (frameNum>=globalVal.length) {animationDelay=0; return;} 
		if (animationDelay==0) return;
		var myDate = new Date(globalVal[frameNum][0]-0);
		$('body').find('#animationTime').html(myDate.format('Y-m-d H:i:s'));
		myPlotClass = new Array();
		myPlotClass[0] = new Array();
		myPlotClass[0].label = globalLabel;
		myPlotClass[0].data = globalVal[frameNum][1];
		myPlotClass[0].lines = { show: true, fill: false };
		flotOptions.yaxis = { position: "left", min: minVal, max: maxVal };
		var localPlot = $.plot($("#placeholder"), myPlotClass, flotOptions);
		$( "#animationSlider" ).slider( "value", frameNum);
		frameNum++;
		if (animationDelay) setTimeout(function(){flotFrameTs();},animationDelay);
	}

	function flotAnimationTs(ts_id, start, stop) {
		$("#animationSlider").width($("#plotContainer").width()-20);
		globalVal = new Array();
		var start_param = 'start=' + start;
		start_param = `${start_param}&decimation=${decimation}&decimation_samples=${decimationSamples}`;
		var stop_param = '';
		if (stop.length) stop_param = '&stop=' + stop;
		$.get(plotService+'&'+start_param+stop_param+'&ts='+ts_id+'&no_pretimer&no_posttimer', function(jsonval) {
			var j = 0; // ts_id
			minVal = maxVal = jsonval['ts'][j].data[0][1][0];
			globalLabel = jsonval['ts'][j].label;
			var v;
			for (var fn=0; fn<jsonval['ts'][j].data.length; fn++) {
				globalVal[fn] = [jsonval['ts'][j].data[fn][0],[]];
				for (var d=0; d<jsonval['ts'][j].data[fn][1].length; d++) {
					v = jsonval['ts'][j].data[fn][1][d];
					globalVal[fn][1][d] = [d, v];
					if (minVal>v) minVal=v;
					if (maxVal<v) maxVal=v;
				}
			}
			var margin = (maxVal - minVal) / 20;
			maxVal = maxVal + margin;
			minVal = minVal - margin;
			$( "#animationSlider" ).slider({
				range: false,
				min: 0,
				max: globalVal.length-1
			});
			$( "#animationSlider" ).slider({stop: function( event, ui ) { frameNum=ui.value; flotFrameTs();}});
			animationDelay = 100;
			frameNum = 0;
			setTimeout(function(){flotFrameTs();},10);
		})
	}

	function hcFrameTs() {
		if (frameNum>=globalVal.length) {animationDelay=0; return;} 
		if (animationDelay==0) return;
		var myDate = new Date(globalVal[frameNum][0]-0);
		$('body').find('#animationTime').html(myDate.format('Y-m-d H:i:s'));
		var container = document.getElementById('placeholder');
		var style = 'step';
		if (document.getElementById('style') && document.getElementById('style').value.length) {
			style = document.getElementById('style').value;
		}
		var chartConfig = {
			chart: {
				renderTo: container,
				type: style=='step'? 'line': style,
				height: 800
			},
			title: {text: 'eGiga2m'},
			plotOptions: { series: { animation: false } },
			yAxis: [ {
				title: {text: 'Y'},
				labels: {
					formatter: function() {
						return (this.value === 0.00001)? 0: this.value;
					}
				}
			}],
			series: [{
				name: globalLabel,
				step: style=='step'? 'left': false,
				data: globalVal[frameNum][1]
			}]
		}
		chartConfig.yAxis[0].min = minVal;
		chartConfig.yAxis[0].max = maxVal;
		Highcharts.setOptions({
			global: {
				useUTC: false // true by default
			}
		});
		var localPlot = new Highcharts.Chart(chartConfig);
		$( "#animationSlider" ).slider( "value", frameNum);
		frameNum++;
		if (animationDelay) setTimeout(function(){hcFrameTs();},animationDelay);
	}

	function hcAnimationTs(ts_id, start, stop) {
		$("#animationSlider").width($("#plotContainer").width()-20);
		globalVal = new Array();
		var start_param = 'start=' + start;
		start_param = `${start_param}&decimation=${decimation}&decimation_samples=${decimationSamples}`;
		var stop_param = '';
		if (stop.length) stop_param = '&stop=' + stop;
		$.get(plotService+'&'+start_param+stop_param+'&ts='+ts_id+'&no_pretimer&no_posttimer', function(jsonval) {
			var j = 0; // 'ts'+ts_id
			minVal = maxVal = jsonval['ts'][j].data[0][1][0];
			globalLabel = jsonval['ts'][j].label;
			var v;
			for (var fn=0; fn<jsonval['ts'][j].data.length; fn++) {
				globalVal[fn] = [jsonval['ts'][j].data[fn][0],[]];
				for (var d=0; d<jsonval['ts'][j].data[fn][1].length; d++) {
					v = jsonval['ts'][j].data[fn][1][d];
					globalVal[fn][1][d] = [d, v];
					if (minVal>v) minVal=v;
					if (maxVal<v) maxVal=v;
				}
			}
			var margin = (maxVal - minVal) / 20;
			maxVal = maxVal + margin;
			minVal = minVal - margin;
			$( "#animationSlider" ).slider({
				range: false,
				min: 0,
				max: globalVal.length-1
			});
			animationDelay = 100;
			frameNum = 0;
			$( "#animationSlider" ).slider({stop: function( event, ui ) { frameNum=ui.value; hcFrameTs()}});
			setTimeout(function(){hcFrameTs();},10);
		})
	}

	function stopAnimation() {
		animationDelay = 0;
		frameNum = 0;
		$( "#animationSlider" ).slider( "value", frameNum);
	}

	function pauseAnimation() {
		animationDelay = 0;
	}

	function playAnimation() {
		if (animationDelay==0) {
			if (document.getElementById('show_flot').checked) setTimeout(function(){flotFrameTs();},10);
			if (document.getElementById('show_hc').checked) setTimeout(function(){hcFrameTs();},10);
		}
		animationDelay = 100;
	}

	function ffAnimation() {
		if (animationDelay==0) {
			animationDelay = 50;
			if (document.getElementById('show_flot').checked) setTimeout(function(){flotFrameTs();},10);
			if (document.getElementById('show_hc').checked) setTimeout(function(){hcFrameTs();},10);
		}
		else {
			animationDelay = animationDelay / 2;
		}
	}


// ------------
// surface plot
// ------------
	var surfacePlot,data, options, basicPlotOptions, glOptions, animated, plot1;
	var values = new Array();
	var times = new Array();

	function plotSurfaceTs(ts_id, start, stop) {
		var start_param = 'start=' + start;
		var stop_param = '';
		if (stop.length) stop_param = '&stop=' + stop;
		$.get(plotService+'&'+start_param+stop_param+'&ts='+ts_id+'&no_pretimer&no_posttimer', function(jsonval) {
			var tooltipStrings = new Array();
			var j = 0; // 'ts'+ts_id
			var numRows = jsonval['ts'][j].data.length;
			var time_decimation = Math.round(numRows / 5);
			for (var i = 0; i < numRows; i++) {
				if (i % time_decimation == 0) {
					var myDate = new Date(jsonval['ts'][j].data[i][0]-0);
					times[i/time_decimation] = myDate.format('Y-m-d H:i:s');
				}
				values[i] = jsonval['ts'][j].data[i][1];
			}
			var numCols = values[0].length;
			data = {nRows: numRows, nCols: numCols, formattedValues: values};
			$('#placeholder').empty();
			surfacePlot = new SurfacePlot(document.getElementById("placeholder"));
			// Define a colour gradient.
			var colour1 = {red:0, green:0, blue:255};
			var colour2 = {red:0, green:255, blue:255};
			var colour3 = {red:0, green:255, blue:0};
			var colour4 = {red:255, green:255, blue:0};
			var colour5 = {red:255, green:0, blue:0};
			var colours = [colour1, colour2, colour3, colour4, colour5];
			// Options for the basic canvas plot.
			// Don't fill polygons in IE < v9. It's too slow.
			basicPlotOptions = {fillPolygons: true, tooltips: tooltipStrings, renderPoints: true }
			// Options for the webGL plot.
			var yLabels = new Array();
			var sample_decimation = 1;
			if (numCols>20) sample_decimation = Math.round(numCols / 10);
			for(var i =0; i <= numCols; i+=sample_decimation) {
				yLabels[i/sample_decimation] = i;
			}
			glOptions = {xLabels: times, yLabels: yLabels, chkControlId: "allowWebGL", autoCalcZScale: true, animate: false};
			// Options common to both types of plot.
			options = {xPos: 0, yPos: 0, width: 1200, height: 800, colourGradient: colours,
			xTitle: "", yTitle: "samples", zTitle: "values",
			backColour: '#f8f8f8', axisTextColour: '#000000', hideFlatMinPolygons: false, origin: {x: 600, y:400}};
			surfacePlot.draw(data, options, basicPlotOptions, glOptions);
		})
	}

// http://closure-compiler.appspot.com
