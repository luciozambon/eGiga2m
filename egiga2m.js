// ------------
// egiga2m.js
// ------------

// todo: 
// add plugin
// add more documentation
// add other decimation method (mean, average, random...)
// add smart periods on update
// add regression https://github.com/Tom-Alexander/regression-js

	var version = '1.15.6';
	var visited = new Array();
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
	var myPlotClass = new Array();
	var myHistory = new Array();
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
	var flotOptions = {
		series: { lines: { show: true } },
		grid: { hoverable: true, clickable: true},
		xaxis: { tickLength: 5 },
		yaxis: { position: "left" },
		legend: { position: 'sw' },
		canvas: true
	};
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
	if (typeof($_GET['decimation']) !== 'undefined') {
		decimation = $_GET['decimation'];
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
	// console.log('plotService: '+plotService);

	if (typeof(isHighChartsInstalled) !== 'boolean' && document.getElementById('show_flot')) {
		document.getElementById('show_flot').checked = true;
		document.getElementById('show_hc').checked = false;
		document.getElementById('show_error').checked = false;
		document.getElementById('style_output').style.display = 'none';
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

	function updateLink(myTs){
		var ts = '';
		if (document.getElementById('ts').value.length) {
			ts = '&ts=' + document.getElementById('ts').value;
		}
		else {
			var axis = [];
			var tree = $("#tree").fancytree("getTree");
			tree.visit(function(node){
				if (!node.folder) {
					if (node.data.icon == './img/y1axis.png') axis.push(node.key+',1,1');
					if (node.data.icon == './img/y2axis.png') axis.push(node.key+',1,2');
					if (node.data.icon == './img/surface.png') axis.push(node.key+',surface'); 
					if (node.data.icon == './img/animation.png') axis.push(node.key+',animation'); 
					if (node.data.icon == './img/multi.png') axis.push(node.key+',multi');
				}
			});
			if (axis.length) ts = '&ts=' + axis.join(';');
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
		// console.log('downtimeCheck: '+downtimeCheck);
		var necessaryParam = conf+start+stop+ts;
		var optionalParam = yconf+style+height+decimationStr+decimationSamplesStr+downtimeCheckStr;
		jsonURL = window.location.protocol + "//" + window.location.host + path.join('/') + plotService.substr(1)+start+stop+ts+optionalParam;
		// console.log('jsonURL', jsonURL);
		var jsonTreeURL = window.location.protocol + "//" + window.location.host + path.join('/') + treeService.substr(1)+start+stop+ts+optionalParam;
		// console.log('jsonTreeURL', jsonTreeURL);
		if (typeof(myTs) !== 'undefined') {
			if (myTs == 'history') return necessaryParam+optionalParam+hc+flot+table;
			window.location = homeURL+'?'+conf+start+stop+'&ts='+myTs+optionalParam+hc+flot+table;
		}
		else {
			document.getElementById("plotOnly").setAttribute("onClick","javascript: location='"+plotURL+'?'+necessaryParam+optionalParam+hc+flot+table+event+"'");
			document.getElementById("plotOnly").innerHTML = plotURL+'?'+necessaryParam+optionalParam+hc+flot+table+event;
			document.getElementById("plotAndControls").setAttribute("onClick","javascript: location='"+homeURL+'?'+necessaryParam+optionalParam+hc+flot+table+event+"'");
			document.getElementById("plotAndControls").innerHTML = homeURL+'?'+necessaryParam+optionalParam+hc+flot+table+event;
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
			document.getElementById("multiParam").value = plotURL+'?'+necessaryParam+optionalParam+hc+flot+event;
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
		var exportTsLabel = document.getElementById('tsLabel').value.length? '&tsLabel='+document.getElementById('tsLabel').value: '';
		exportTsLabel = exportTsLabel + (document.getElementById('nullValue').value.length? '&nullValue='+document.getElementById('nullValue').value: '');
		if (exportFormat==='json'){
			document.getElementById('exportLink').innerHTML = jsonURL;
			document.getElementById('exportLink').setAttribute("onClick","javascript: location='"+jsonURL+"'");
		}
		else {
			document.getElementById('exportLink').innerHTML = exportURL+'&format='+exportFormat+exportBlanks+exportDecimation+exportTsLabel;
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
		var tree = $("#tree").fancytree("getTree");
		tree.visit(function(node){
			if (!node.folder) {
				if (node.data.icon == './img/y1axis.png') axis.push(node.key+',1,1');
				if (node.data.icon == './img/y2axis.png') axis.push(node.key+',1,2');
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
					if (document.getElementById('show_flot').checked) {flotAnimationTs(node.key, start, stop); axis.push(node.key+',animation'); surface = true;}
					if (document.getElementById('show_hc').checked) {hcAnimationTs(node.key, start, stop); axis.push(node.key+',animation'); surface = true;}
				}
			}
		});
		var ts = axis.join(';');
		document.getElementById('ts').value = ts;
		if (!surface) document.getElementById('animationControls').style.display = 'none';
		if (updateId!==false) {clearInterval(updateId); updateId = false;} 
		document.getElementById('hidetree').style.display = 'inline';
		if (!surface) plotTs(ts, start, stop);
		add_history(0);
	}

	function initPlot($_GET) {
		$("#tree").width($("#configContainer").width());
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
			else {
				hcAnimationTs(tsArray[0], start, stop);
			}
			return;
		}
		if (document.getElementById('hidetree')) {
			document.getElementById('hidetree').style.display = 'inline';
		}
		if (typeof(document.getElementById('ts')) !== 'undefined') {
			document.getElementById('ts').value = ts;
		}
		if (typeof($_GET['show_flot']) !== 'undefined') {
			if (document.getElementById('show_flot')) {
				document.getElementById('show_flot').checked = true;
				document.getElementById('show_hc').checked = false;
			}
		}
		if (typeof($_GET['show_table']) !== 'undefined') {
			if (document.getElementById('show_table')) {
				document.getElementById('show_table').checked = true;
			}
		}
		plotTs(ts, start, stop);
		add_history(0);
	}

	function initTree($_GET) {
		// console.log(typeof(document.getElementById('tree')));
		if (!$('#tree').length) return;
		var source_url = treeService;
		if (typeof(formula_edit) === 'undefined') $(tree).width(250).height($(window).height()-320);
		if (typeof($_GET['ts']) !== 'undefined') source_url = source_url + '&ts=' + $_GET['ts'];
		$("#tree").fancytree({
			autoScroll: true,
			source: {
				url: source_url
			},
			lazyLoad: function(event, data) {
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
	}

	function switchtree() {
		if (document.getElementById('hidetree').InnerHTML == "<img src='./img/right.png'>") {
			document.getElementById('hidetree').InnerHTML = "<img src='./img/left.png'>";
			$('body').find('#hidetree').html("<img src='./img/left.png'>")
			document.getElementById('treeid').style.display = 'inline';
			$("#mybackground").width($(window).width()-280).height(((height-0)+10)+'px');
			$("#placeholder").width($(window).width()-290).height(height+'px');
			rePlotTs();
		}
		else {
			document.getElementById('hidetree').InnerHTML = "<img src='./img/right.png'>";
			$('body').find('#hidetree').html("<img src='./img/right.png'>")
			document.getElementById('treeid').style.display = 'none';
			$("#mybackground").width($(window).width()-30).height(((height-0)+10)+'px');
			$("#placeholder").width($(window).width()-40).height(height+'px');
			rePlotTs();
		}
	}

	function rePlotTs(){
		// adjust plot width
		var height = document.getElementById('height').value.length? document.getElementById('height').value: $(window).height()-200;
		if (document.getElementById('show_flot') && document.getElementById('show_flot').checked) {
			myPlot.resize();
			myPlot.draw();
		}
		else {
			myPlot.reflow();
		}
	}

	function applyConf() {
		// apply Y1 conf
		document.getElementById('minY').value = document.getElementById('minY1set').value+';'+document.getElementById('minY2set').value;
		document.getElementById('maxY').value = document.getElementById('maxY1set').value+';'+document.getElementById('maxY2set').value;
		document.getElementById('logY').value = (document.getElementById('logY1set').checked? '1': '0')+';'+(document.getElementById('logY2set').checked? '1': '0');
		// apply style
		document.getElementById('style').value = document.getElementById('styleSet').value;
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

	function change_flot() {
		if (document.getElementById('show_flot').checked) document.getElementById('show_hc').checked = false;
		if (document.getElementById('style_item')) document.getElementById('style_item').style.display = document.getElementById('show_hc').checked? 'inline': 'none';
	}

	function change_hc() {
		if (document.getElementById('show_hc').checked) document.getElementById('show_flot').checked = false;
		document.getElementById('style_item').style.display = document.getElementById('show_hc').checked? 'inline': 'none';
	}

	function csvCallback(){
		ts = document.getElementById('ts').value;
		start = document.getElementById('start').value;
		stop = document.getElementById('stop').value;
		csvTs(ts, start, stop);
	}

	function pngCallback(){
		Canvas2Image.saveAsPNG(myPlot.getCanvas());
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
		$("#mybackground").width($("#plotContainer").width()).height(((height-0)+10)+'px');
		$("#placeholder").width($("#plotContainer").width()-10).height(height+'px');
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
				lines = e.target.result.split("\n");
				val = lines[0].split(',');
				col_number = val.length-1;
				var yaxis_max_index = 1;
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
				// title = escape(theFile.name);
				var row_index = -1;
				var showTable = document.getElementById('show_table') && document.getElementById('show_table').checked;
				var tableContent = '<table class="table table-striped table-bordered table-condensed" width="100%"><thead><tr><th>timestamp</th>';
				for (var i=0; i<col_number; i++) {
					tableContent += '<th>'+myPlotClass[i].label+'</th>';
				}
				tableContent += '</tr></thead><tbody>';
				var start = 0
				for (var k=0; k<lines.length; k++) {
					val = lines[k].split(',');
					if (val.length < 2) continue;
					if ($.isNumeric(val[0])) {
						timestamp = val[0];
						var myDate = new Date(val[0]*1000);
					}
					else if (val[0].indexOf('-')>=0) {
						var v = val[0].split(' ');
						var myDate = new Date(v[0]+"T"+v[1]); 
						timestamp = myDate.getTime()/1000.0;
					}
					else continue;
					if ($.isNumeric(timestamp) && timestamp>0) {
						row_index++;
						if (start==0) start = timestamp*1000;
						tableContent += '<tr><td>'+myDate.toLocaleString()+'</td>'
						for (var j=0; j<col_number; j++) {
							tableContent += '<td>'+val[j+1]+'</td>';
							if (val[j+1]==='') continue;
							myPlotClass[j].data[row_index] = [timestamp*1000,parseFloat(val[j+1])];
						}
						tableContent += '</tr>';
					}
				}
				stop = timestamp*1000;
				if (document.getElementById('show_hc') && document.getElementById('show_hc').checked) {
					var style = 'step';
					if (document.getElementById('style') && document.getElementById('style').value.length) {
						style = document.getElementById('style').value;
					}
					var chartConfig = {
						chart: {
							renderTo: document.getElementById('placeholder'),
							type: 'line',
							height: 800
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
				else {
					$("#mybackground").height('1px');
					$("#placeholder").height('1px');
					document.getElementById('placeholder').innerHTML = '';
				}
				tableContent += '</tbody></table>';
				document.getElementById('tableContainer').innerHTML = showTable? tableContent: '';
				document.getElementById('pngCallback').style.display = 'inline';
			};
		})(f);
		reader.readAsText(f);
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
				console.log(document.getElementById('ts').value);
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
			plotTs(param['ts'], param['start'], (typeof(param['stop']) === 'undefined')? '': param['stop']);
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
		// 
		return data; // skip evalFormulae(data) unless read/write data is preserved
		var formulaDebug = true;
		var formulaSamples = new Array();
		var newData = new Array();
		var formulaReplace;
		var dataIndex=0;
		// for each curve
		for (var curveIndex=0; curveIndex<curves.length; curveIndex++) {
			// skip if not a formula
			if (curves[curveIndex].response>=0) {
				while (data[dataIndex] && data[dataIndex]['ts_id']==curves[curveIndex]['request']) {
					newData.push(data[dataIndex]); 
					dataIndex++;
				}
				continue;
			}
			var formulaData = new Array();
			if (formulaDebug) alert('curveIndex: '+curveIndex+', curves[curveIndex].response: '+curves[curveIndex].response);
			formulaSamples = [];
			formulaReplace = {};
			labelReplace = {};
			// for each ts in formula append data in a unique array called formulaSamples
			for (var j=0; j<curves[curveIndex].response.length; j++) {
				for (var k=0; k<data.length; k++) {
					if(formulaDebug) alert('data[k].ts_id: '+data[k].ts_id+'== curves[curveIndex].response[j]: '+curves[curveIndex].response[j]);
					if (data[k].ts_id==curves[curveIndex].response[j] && data[k].xaxis==curves[curveIndex].x) {
						for (var l=0; l<data[k].data.length; l++) {
							formulaSamples.push({'ts':data[k].ts_id,'label':data[k].label,'x':data[k].data[l][0],'y':data[k].data[l][1]});
						}
					}
				}
			}
			if (formulaDebug) alert('formulaSamples.sort(SortByTime)');
			formulaSamples.sort(SortByTime);
			if (formulaDebug) print_r(formulaSamples[0]);
			// for each timestamp eval formula with last value of each ts 
			for (var j=0; j<formulaSamples.length; j++) {
				formulaReplace['$'+formulaSamples[j].ts+'$'] = formulaSamples[j].y;
				labelReplace['$'+formulaSamples[j].ts+'$'] = formulaSamples[j].label;
				if (j<formulaSamples.length-1 && formulaSamples[j].x==formulaSamples[j+1].x) continue;
				// if (formulaDebug) alert('j: '+j+', formulaSamples[j].ts: '+formulaSamples[j].ts);
				f = strtr(curves[curveIndex].request, formulaReplace);
				if (formulaDebug) alert('x: '+formulaSamples[j].x+', f: '+f);
				if (f.indexOf('$')>=0) continue;
				y = mathEval(f);
				if (typeof(y) === 'undefined' || isNaN(y)) continue;
				if (formulaDebug) alert('eval(f): '+y+', type: '+typeof(y));
				formulaData.push([formulaSamples[j].x,y]);
			}
			curves[curveIndex].response = newData.push({'label': strtr(curves[curveIndex].request, labelReplace),'xaxis':curves[curveIndex].x,'yaxis':curves[curveIndex].y,'data': formulaData});
			if (formulaDebug) print_r(newData);
		}
		return newData;
	}

	function plotTs(tsRequest, start, stop){
		curves = new Array();
		myRequest = tsRequest;
		var event = '';
		for (j=0; j<events.length; j++) event += document.getElementById('show_'+events[j]).checked? '&show_'+events[j]+'='+document.getElementById('filter_'+events[j]).value: '';
		event = `${event}&decimation=${decimation}&decimation_samples=${decimationSamples}`;
		// adjust plot dimensions
		var height = document.getElementById('height').value.length? document.getElementById('height').value: $(window).height()-200;
		$("#mybackground").width($(window).width()-(($('#tree').length > 0)? 280: 0)).height(((height-0)+10)+'px');
		$("#placeholder").width($(window).width()-(($('#tree').length > 0)? 290: 10)).height(height+'px');
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
		var ts = decodeTs(tsRequest);
		const stopTime = stop.length? new Date(stop): new Date();
		if (downtimeCheck) {
			$.get(plotService+'&Seconds_Behind_Master', function(behind) {
				if (behind>60) alert('WARNING\nThe data has not been updated for '+behind+' seconds');
			})
		}
		// console.log('stopTime: '+stopTime.valueOf());
		$.get(plotService+'&'+start_param+stop_param+'&ts='+ts+prestart+event, function(data) {
			const downtimeCheck = document.getElementById('downtimeCheck')? document.getElementById('downtimeCheck').checked: false;
			if (downtimeCheck) {
				const startTimestamp = data.ts[0].data[0].x? data.ts[0].data[0].x: data.ts[0].data[0][0];
				// console.log('startTimestamp: '+startTimestamp);
				const missingTS = new Array();
				for (var dataIndex=0; dataIndex<data.ts.length; dataIndex++) {
					const lastTimestamp = data.ts[dataIndex].data[data.ts[dataIndex].data.length-1][0];
					// console.log('lastTimestamp: '+lastTimestamp);
					// console.log('formula: '+((stopTime.valueOf()-lastTimestamp) / (stopTime.valueOf()-startTimestamp))+ ', 10 / data.ts[dataIndex].data.length: '+(10 / data.ts[dataIndex].data.length));
					if ((stopTime.valueOf()-lastTimestamp) / (stopTime.valueOf()-startTimestamp) > 10 / data.ts[dataIndex].data.length) missingTS.push(data.ts[dataIndex].label);
				}
				if (missingTS.length) alert("WARNIG\nsome data may be missing (may be server or replication downtime) for Time Series:\n"+missingTS.join(','));
			}
			plotData(evalFormulae(data.ts), data.event, start, stop);
		})
	}

	function plotData(dataTs, dataEvent, start, stop){
		var startArray = start.split(';');
		var stopArray = stop.split(';');
		// console.log(JSON.stringify(dataTs, null, '\t'));
		if (document.getElementById('show_hc').checked) {
			hcPlot(dataTs, dataEvent, startArray, stopArray);
			document.getElementById('pngCallback').style.display = 'none';
		}
		else if (document.getElementById('show_flot').checked) {
			flotPlot(dataTs, dataEvent, startArray, stopArray);
			document.getElementById('pngCallback').style.display = 'inline';
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
// Flot plot
// ------------
	function flotPlot(data, dataEvent, start, stop){
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
			if (minYArray[i-1]) options.yaxis[i-1].min = minYArray[i-1];
			if (maxYArray[i-1]) options.yaxis[i-1].max = maxYArray[i-1];
			if (logYArray[i-1] && logYArray[i-1]=='1') {
				options.yaxes[i-1].transform = function (v) { return v>0? Math.log(v): -23; };
				options.yaxes[i-1].inverseTransform = function (v) { return Math.exp(v); };
			}
		}
		var localPlot = $.plot($("#placeholder"), myPlotClass, options);
		myPlot = localPlot;
		// add labels
		$("#placeholder").append('<div style="position:absolute;left:120px;top:10px;color:#676;font-size:smaller">eGiga2m - '+start+' - '+stop+'</div>');
		if (document.getElementById('pngCallback')) document.getElementById('pngCallback').style.display = 'inline';
		if (document.getElementById('hidetree')) document.getElementById('hidetree').style.display = 'inline';
		// flotUpdate();
	}
/*
	function getRandomData() {
		y = Math.random() * 100 - 50;
		myPlot.data.push([(new Date()).getTime(), y]);
	}

	function flotUpdate() {
		myPlot.setData([getRandomData()]);
		// Since the axes don't change, we don't need to call plot.setupGrid()
		myPlot.draw();
		setTimeout(flotUpdate, updatePlot);
	}
*/

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
			plotTs(myRequest, startStop[0], startStop[1]);
			add_history(0);
		}
		else {
			document.location = './index.html'+document.location.search;
		}
	});

// ------------
// HighCharts plot
// ------------
	var printing = false;
	function hcPlot(data, eventData, startArray, stopArray){
		// console.log('data: ',data);
		var emptyMessage = "No data available in selected period";
		var height = document.getElementById('height').value.length? document.getElementById('height').value: $(window).height()-200;
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
		for (var j in curves) {
			if (yaxis_max_index < curves[j].y) yaxis_max_index = curves[j].y;
		}
		k=0;
		for (j=0; j<events.length; j++) document.getElementById('event_'+events[j]).style.display = 'none';
		// console.log('curves: ',curves);
		for (var j in curves) {
			if (j=='clone') continue;
			if (data) while (data[k] && data[k]['ts_id']==curves[j]['request']) {
				myPlotClass[k] = new Array();
				const samplesPerSecond = data[k]['query_time']>0? ', Samples per second: '+(data[k]['num_rows']/data[k]['query_time']).toFixed(0): '';
				const title = 'Samples: '+data[k]['data'].length+(data[k]['num_rows']>data[k]['data'].length? '/'+data[k]['num_rows']: '')+', query time: '+data[k]['query_time'].toFixed(2)+' [s]'+samplesPerSecond;
				myPlotClass[k].name = '<span title="'+title+'">'+((typeof(tsLabel) !== 'undefined' && typeof(tsLabel[k]) !== 'undefined')? tsLabel[k]: (yaxis_max_index>1? 'Y'+curves[j]['y']+' ':'')+data[k]['label'].replace(/&deg;/g, "°"))+'</span>';
				if (typeof($_GET['num_rows']) !== 'undefined') myPlotClass[k].name = myPlotClass[k].name+' num_rows: '+data[k]['num_rows'];
				myPlotClass[k].xAxis = data[k]['xaxis']-1;
				myPlotClass[k].yAxis = $.isNumeric(curves[j].y)? curves[j].y-1: 0;
				myPlotClass[k].data = data[k]['data'];
				myPlotClass[k].step = style=='step'? 'left': false;
				if (xaxis_max_index < data[k]['xaxis']) xaxis_max_index = data[k]['xaxis'];
				if (typeof(data[k]['categories']) !== 'undefined') categories[data[k]['yaxis']-1] = data[k]['categories'];
				k++;
			}
			else emptyMessage = "No variable selected"
		}
		// console.log('myPlotClass: ',myPlotClass);
		for (var j in eventData) {
			if (j=='clone') continue;
			if (typeof(fade_level[j]) === 'undefined') continue;
			myPlotClass[k] = new Array();
			if (typeof(eventData[j]['label']) === 'undefined') {
				myPlotClass[k].name = j;
				myPlotClass[k].showInLegend = false;
				myPlotClass[k].xAxis = 0;
				myPlotClass[k].yAxis = 0;
				myPlotClass[k].data = eventData[j]['data'];
				myPlotClass[k].type = 'scatter';
				myPlotClass[k].step = false;
				document.getElementById('event_'+j).style.display = 'inline';
				k++;
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
								plotTs(myRequest, startStop[0], startStop[1]);
								add_history(0);
							}
							else {
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
							var name = myPlotClass[j].name;
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
									myPlot.series[j].addPoint(data[j], true, false); 
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
			plotOptions: { series: { animation: !updatePlot } },
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
			chartConfig.xAxis[i-1] = {type: 'datetime', gridLineWidth: 0,lineColor: '#000',title: {text: startArray[i-1] + ' - ' + stopArray[i-1]},opposite: xaxis_max_index==2 && i==2};
		}
		if (typeof(window.$_GET['hideMenu']) !== 'undefined') {
			chartConfig.navigation={buttonOptions:{enabled: false}};
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
			chartConfig.yAxis[i-1] = {title: {text: 'Y'+i},opposite: yaxis_max_index==2 && i==2};
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
				chartConfig.yAxis[i-1].title={text: (typeof(yLabel[i-1]) !== 'undefined')? yLabel[i-1]: yLabel[0]};
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
				var mySeries = (typeof(mychart.hoverSeries) === 'undefined')? 0: mychart.hoverSeries.index;
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
