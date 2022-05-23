// ------------
// pickts.js
// ------------


	// INIT
	var $_GET = getQueryParams(document.location.search);
	var treeService = './lib/service/hdbpp_tree_service.php?conf='+$_GET['conf'];
	var ftree = null;
	var searchPrompt = "&nbsp;<img src='./img/zoom.png' onClick=\"opener.document.getElementById('ts').value=document.getElementById('search').value;mySearch()\" title='search timeseries name'> "

	if ($_GET['mode']=='list') mySearch(); else initTree($_GET);

	function applySearch() {
		var ts = [];
		for (i=0; i<document.getElementById('ts').length; i++) {
			if (document.getElementById('ts').options[i].selected) {
				ts.push(document.getElementById('ts').options[i].value);
			}
		}
		opener.document.getElementById('ts').value = ts.join(';');
		close();
	}

	function mySearch() {
		$('body').find('#tree').html('search <input id="search" onkeyup="inputKeyUp(event)">'+searchPrompt+'<br><br>');
		if (opener) document.getElementById('search').value = opener.document.getElementById('ts').value;
		var searched = document.getElementById('search').value;
		if (!searched.length) $('body').find('#tree').html('search <input id="search" value="'+document.getElementById('search').value+'" onkeyup="inputKeyUp(event)">'+searchPrompt);
		else $.get(treeService+'&search='+searched, function(data) {
			var emptyList = true; 
			var found = 'search <input id="search" value="'+document.getElementById('search').value+'" onkeyup="inputKeyUp(event)">'+searchPrompt+'<br><br><select id="ts" multiple size="25" onClick="applySearch()">';
			for (var key in data) {
				emptyList = false; 
				found += '<option value="'+ key + '">' + data[key] + '</option>';
			}
			$('body').find('#tree').html(found+'</select>');
			if (emptyList) $('body').find('#tree').html('search <input id="search" value="'+document.getElementById('search').value+'" onkeyup="inputKeyUp(event)">'+searchPrompt+'<br><br>not found');
		});
	}

	function initTree($_GET) {
		// console.log('treeService', treeService+(typeof($_GET['ts']) !== 'undefined'? '&ts=' + $_GET['ts']: ''));
		$.get(treeService+(typeof($_GET['ts']) !== 'undefined'? '&ts=' + $_GET['ts']: ''), function(tdata) {
			if (tdata.length==0) {
				var url = treeService.indexOf('?')>=0? treeService+'&host': treeService+'?host';
				$.get(url, function(d) {
					var t = new Date($.now());
					$("body").html("<div style='margin-left: 10px;margin-top: -80px;'><h1>ERROR</h1>Cannot extract data from<br>"+d+"<br>or<br>"+treeService+'<br>'+t+'<br><a href="'+$(location).attr('href')+'">reload page</a></div>');
				});
			}
			if (localStorage.length && typeof(localStorage.csvrepo) !== 'undefined' && tdata[0].title.indexOf(localStorage.csvrepo)==-1) {
				var ltree = {title: "<span style='color: darkgreen;font-weight: bold;'>"+localStorage.csvrepo+"</span>", key: "csvrepo_"+localStorage.csvrepo, lazy: true, folder: true};
				tdata.unshift(ltree);
				// console.log('tdata', tdata);
			}
			if (!$('#tree').length) return;
			var source_url = treeService;
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
					};
				},
				click: function(event, data) {
					if (data.targetType == 'icon' || data.targetType == 'title') {
						if (opener && (data.node.key == data.node.key-0)) {
							var prev = opener.document.getElementById('ts').value.split(';')[0];
							if (prev == prev-0 && prev>0) opener.document.getElementById('ts').value = opener.document.getElementById('ts').value + ';' + data.node.key;
							else opener.document.getElementById('ts').value = data.node.key;
							close();
						}
					}
					return true;// Allow default processing
				},
				clickFolderMode: 2,
				persist: true
			});
		});
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
