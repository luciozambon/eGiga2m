<?php
	require_once('./conf.php');
	$htmlhead = "<!DOCTYPE html>
	<html lang='en'><head>
		<meta http-equiv='content-type' content='text/html; charset=UTF-8'>
		<meta charset='utf-8'>
		<meta http-equiv='X-UA-Compatible' content='IE=edge'>
		<meta name='viewport' content='width=device-width, initial-scale=1'>
		<meta name='description' content='Timeseries analysis tools'>
		<meta name='author' content='LZ'>
		<link rel='icon' href='https://www.elettra.eu/favicon.png'>

		<title>eGiga2m - Analysis</title>
		<script src='../json/json2.js'></script>
		<link href='../bootstrap/bootstrap.css' rel='stylesheet'>
		<link href='../bootstrap/bootstrap-theme.css' rel='stylesheet'>
		<link href='../bootstrap/theme.css' rel='stylesheet'>
		<script src='../bootstrap/ie10-viewport-bug-workaround.js'></script>
	</head>
	<body style='margin: 10px; padding: 0;'>";

	// ----------------------------------------------------------------
	// prompt parameters
	function conf_request(&$parameters) {
		if (isset($_REQUEST['configure'])) {
			$param = "{$parameters['description']}<br><br>\n";
			foreach ($parameters as $i=>$p) {
				if ($i == 'format' || !is_array($p)) continue;
				$param .= "$i ";
				if (is_array($p['type'])) {
					$param .= "<select name='$i'>";
					foreach ($p['type'] as $t) {
						$default = $t==$p['default']? ' selected': '';
						$param .= "<option value='$t'$default>$t</option>";
					}
					$param .= "</select>\n";
				}
				else if ($p['type']==='bool') {
					$default = $p['default']? ' checked': '';
					$param .= "<input type='checkbox' name='$i'$default>\n";
				}
				else {
					$param .= "<input type='text' name='$i' value='{$p['default']}'>\n";
				}
				if (isset($p['description'])) { $param .= "<br><span style='font-size: 75%;'>{$p['description']}</span>\n";}
				$param .= "<br><br>\n";
			}
			$operator = pathinfo($_SERVER['SCRIPT_FILENAME']);
			$operator_name = $operator['filename'];
			$title = empty($parameters['title'])? $operator_name: $parameters['title'];
			die("
				<h4>$title <input type='checkbox' onchange=\"switch_analysis('$operator_name')\" id='$operator_name'><a style='margin-left: 1em; text-decoration: none;' href='./lib/analysis/{$operator_name}.php?conf={$_REQUEST['conf']}' target='_blank'>?</a></h4>
				<div id='conf$operator_name' style='display: none'>$param</div>
			");
		}
	}

	if (isset($_REQUEST['list'])) {
		$d = scandir('.');
		$list = array();
		foreach ($d as $f) {
			if (strpos($f,'.php')!==false && strpos($f, 'analysis.php')===false && strpos($f, 'formula.php')===false && strpos($f, 'conf.php')===false && strpos($f, 'test')===false) {
				if (!empty($list)) echo "<hr/>\n";
				readfile('http://localhost'.strtr($_SERVER['PHP_SELF'],array('analysis.php'=>$f)).'?configure&conf='.$_REQUEST['conf']);
				$list[] = strtr($f, array('.php'=>''));
			}
		}
		echo "<analysis/>".implode(',', $list);
	}

	if (isset($_REQUEST['listhtml'])) {
		$d = scandir('.');
		$list = array();
		echo "$htmlhead
		<h2>List of time series analysis tools</h2>";
		foreach ($d as $f) {
			$d = '';
			if (strpos($f,'.php')!==false && strpos($f, 'analysis.php')===false && strpos($f, 'formula.php')===false && strpos($f, 'conf.php')===false && strpos($f, 'test')===false) {
				$c = file('./'.$f);
				foreach ($c as $l) {if (strpos($l, 'description')!==false && strpos($l, 'array(')===false) {$a = explode('=>', $l); $d = trim($a[1], ",'\" \n\r\t\v\x00");}}
				echo "<h3><a href='./$f'>".strtr($f, array('.php'=>''))."</a></h3>$d<br><br>\n";
			}
		}
	}

	// ----------------------------------------------------------------
	// prompt parameters
	function parameters_prompt(&$parameters) {
		global $htmlhead;
		if (empty($_REQUEST) || (empty($_REQUEST['data']) && (empty($_REQUEST['start']) || (empty($_REQUEST['attr']) && empty($_REQUEST['ts']))))) {
			$param = '';
			foreach ($parameters as $i=>$p) {
				if (!is_array($p)) continue;
				$param .= "$i ";
				if (is_array($p['type'])) {
					$param .= "<select name='$i'>";
					foreach ($p['type'] as $t) {
						$default = $t==$p['default']? ' selected': '';
						$param .= "<option value='$t'$default>$t</option>";
					}
					$param .= "</select><br><br>\n";
				}
				else if ($p['type']==='bool') {
					$default = $p['default']? ' checked': '';
					$param .= "<input type='checkbox' name='$i'$default><br><br>\n";
				}
				else {
					$param .= "<input type='text' name='$i' value='{$p['default']}'><br><span style='font-size: 80%'>{$p['description']}</span><br><br>\n";
				}
			}
			$operator = pathinfo($_SERVER['SCRIPT_FILENAME']);
			$operator_name = $operator['filename'];
			$title = empty($parameters['title'])? $operator_name: $parameters['title'];
			die("$htmlhead
				<script type='text/javascript' src='../tigra_calendar/tcal.js'></script> 
				<link rel='stylesheet' type='text/css' href='../tigra_calendar/tcal.css' />
				<h3>$title</h3>
				<div style='font-size: 80%'>{$parameters['description']}</div><br>
				<form method='get' action='./{$operator_name}.php'>
				<table><tr><td>
				<div class='input-group'>
				<span class='input-group-addon' style='width:7em;'>start</span>
				<input type='text' class='tcal' placeholder='YYYY-MM-DD [hh:mm:ss]' name='start' id='startInput' size='18' onKeyPress='editCallback()'>
				</div>
				<div class='input-group' data-toggle='tooltip' title='leave empty to get up to last saved data'>
				<span class='input-group-addon' style='width:7em;'>stop</span>
				<input type='text' class='tcal' placeholder='YYYY-MM-DD [hh:mm:ss]' name='stop' id='stopInput' size='18' onKeyPress='editCallback()'>
				</div>
				<div class='input-group' data-toggle='tooltip'>
				<span class='input-group-addon' style='width:7em;' title='timeseries ids'>timeseries</span>
				<input type='text' placeholder='HDB id' id='ts' name='ts' size='14' title='timeseries ids'>
				<img src='../../img/zoom.png' onClick='pv(0)' title='search timeseries name'> 
				<img src='../../img/tree.png' onClick='pv(1)' title='select in tree'> 
				</div>
				</td></tr></table><br>
				<script LANGUAGE='JavaScript'>
				<!-- HIDE
				var previewWindow = null;
				function pv(tree) {
					var ts = document.getElementById('ts').value.split(';')[0];
					var url = '../../pickts.html?conf={$_REQUEST['conf']}&mode=' + (tree? 'tree': 'list') + (ts == ts-0 && ts>0? '&ts='+document.getElementById('ts').value: '');
					if (!window.previewWindow) {	// has not yet been defined
						previewWindow = window.open(url, 'preview','width=700,height=700,toolbar=1,status=0,location=1,menubar=0,scrollbars=1,resizable=1');
					}
					else {   // has been defined
						if (!previewWindow.closed) {  // still open
							previewWindow.focus();
						}
						else {
							previewWindow = window.open(url, 'preview','width=700,height=700,toolbar=1,status=0,location=1,menubar=0,scrollbars=1,resizable=1');
						}
					}
				}
				//STOP HIDING -->
				</script>
				$param
				<input type='submit' value='submit'>
				</form>
				see also <a href='./index.html'>other methods</a>.
			");
		}
	}

	// ----------------------------------------------------------------
	// parse and detect time periods
	function parse_time($time) {
		if (isset($_REQUEST['debug'])) {echo "<br>parse_time($time)<br>\n";}
		if (strpos($time, 'last ')!== false) {
			$last = explode(' ', $time);
			$i = $n = 1;
			if (count($last) == 3) {
				$i = 2;
				$n = $last[1];
			}
			if (strpos($last[$i], "second")!==false) {
				$time_factor = 1;
			}
			else if (strpos($last[$i], "minute")!==false) {
				$time_factor = 60;
			}
			else if (strpos($last[$i], "hour")!==false) {
				$time_factor = 3600;
			}
			else if (strpos($last[$i], "day")!==false) {
				$time_factor = 86400;
			}
			else if (strpos($last[$i], "week")!==false) {
				$time_factor = 604800;
			}
			else if (strpos($last[$i], "month")!==false) {
				$time_factor = 2592000; // 30days
			}
			else if (strpos($last[$i], "year")!==false) {
				$time_factor = 31536000; // 365days
			}
			$t = time();
			return date('Y-m-d H:i:s', $t - $n*$time_factor - ($t % $time_factor));
		}
		return $time;
	}

	// ----------------------------------------------------------------
	// retrieve and validate values of all parameters
	function get_parameters(&$parameters, &$ts, &$t, &$conf) {
		foreach ($parameters as $i=>$p) {
			if (!is_array($p)) continue;
			if (!isset($_REQUEST[$i]) || $_REQUEST[$i]=='') $parameters[$i]['value'] = $p['default'];
			else if ($p['type']=='bool') $parameters[$i]['value'] = ($_REQUEST[$i]==='true' || $_REQUEST[$i]=='1');
			else if ($p['type']=='number') {$parameters[$i]['value'] = $_REQUEST[$i]-0; if (isset($parameters[$i]['factor'])) $parameters[$i]['value'] *= $parameters[$i]['factor'];}
			else if (is_array($p['type'])) $parameters[$i]['value'] = in_array($_REQUEST[$i], $p['type'])? $_REQUEST[$i]: $p['default'];
			else $parameters[$i]['value'] = strip_tags($_REQUEST[$i]);
		}
		$t = array();
		$ts = '';
		if (!empty($_REQUEST['data'])) return;
		if (empty($_REQUEST['ts']) && !empty($_REQUEST['attr'])) $_REQUEST['ts'] = $_REQUEST['attr'];
		$ts = strip_tags($_REQUEST['ts']);
		if (strpos($_REQUEST['start'], 'last ')!== false) {
			$st = parse_time($_REQUEST['start']);
			$t['startt'] = strtotime($st);
			$t['start'] = strtr($st, array(' '=>'%20'));
		}
		else if (strpos($_REQUEST['start'], '-')!==false) {
			$t['startt'] = strtotime($_REQUEST['start']);
			$t['start'] = strtr($_REQUEST['start'], array(' '=>'%20'));
		}
		else {
			$t['startt'] = $_REQUEST['start']-0;
			$t['start'] = strtr(date('Y-m-d H:i:s', $t['startt']), array(' '=>'%20'));
		}
		if (empty($_REQUEST['stop'])) {
			$t['stopt'] = time() - (isset($parameters['period'])? $parameters['period']['value']: 0);
			if (isset($_REQUEST['debug']) && isset($parameters['period'])) {echo "{$t['stopt']} ".(($t['stopt'] - $t['startt']) % $parameters['period']['value'])."<br>\n";}
			if (isset($parameters['period'])) $t['stopt'] -= (($t['stopt'] - $t['startt']) % $parameters['period']['value']);
		}
		else if (strpos($_REQUEST['stop'], 'last ')!==false) {
			$t['stopt'] = strtotime(parse_time($_REQUEST['stop']));
		}
		else if (strpos($_REQUEST['stop'], '-')!==false) {
			$t['stopt'] = strtotime($_REQUEST['stop']);
		}
		else {
			$t['stopt'] = $_REQUEST['stop']-0;
		}
		$t['stop'] = strtr(date('Y-m-d H:i:s', $t['stopt']+(isset($parameters['period'])? (2*$parameters['period']['value']): 0)), array(' '=>'%20'));
		if (isset($_REQUEST['debug'])) {echo "<br>stop: {$t['stop']} {$t['stopt']} ".time()." , start: {$t['start']} {$t['startt']}<br>\n";}
		$conf = isset($_REQUEST['conf'])? strip_tags($_REQUEST['conf']): 'fermi';
	}

	// ----------------------------------------------------------------
	// retrieve data
	function get_data($ts, $start, $stop, $conf) {
		$context = stream_context_create(array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false)));
		$tsdata = $tsdatac = array();
		if (strpos($ts, '_')!==false) {
			$tsb = $tsc = array();
			$ta = explode(';', $ts);
			foreach ($ta as $t) {
				if (strpos($t, '_')!==false) {$tsc[] = $t;} else {$tsb[] = $t;}
			}
			$url = CSV_SRC."?conf=$conf&start={$start}&stop={$stop}&ts=".implode(';', $tsc);
			$tsdatac = json_decode(file_get_contents($url, false, $context), true);
			$ts = implode(';', $tsb);
		}
		if (!empty($ts)) {
			$url = DATA_SRC."?conf=$conf&start={$start}&stop={$stop}&ts=$ts";
			if (isset($_REQUEST['debug'])) {echo "$url<br>\n";}
			$tsdata = json_decode(file_get_contents($url, false, $context), true);
			for ($i=0; $i<count($tsdata['ts']); $i++) {
				if (isset($tsdata['ts'][$i]['data'][0]['prestart'])) {
					$tsdata['ts'][$i]['data'][0][0] = $tsdata['ts'][$i]['data'][0]['prestart']; 
					$tsdata['ts'][$i]['data'][0][1] = $tsdata['ts'][$i]['data'][0]['y'];
				}
			}
			if (isset($_REQUEST['debug'])) {echo "<br><br>input data<pre>"; print_r($tsdata);echo "</pre>";}
		}
		if (empty($tsdatac)) return $tsdata;
		else if (empty($tsdata)) return $tsdatac;
		else {
			$tsdata['ts'] = array_merge($tsdata['ts'], $tsdatac['ts']);
			return $tsdata;
		}
	}

	// ----------------------------------------------------------------
	// retrieve input
	function input_data(&$data, &$t, &$parameters) {
		conf_request($parameters);
		parameters_prompt($parameters);
		$ts = '';
		get_parameters($parameters, $ts, $t, $conf);
		$data = empty($_REQUEST['data'])? get_data($ts, $t['start'], $t['stop'], $conf): json_decode($_REQUEST['data'], true);
	}

	// ----------------------------------------------------------------
	// send result to output
	function output_data(&$tsdata, &$dest, &$parameters) {
		$operator = pathinfo($_SERVER['SCRIPT_FILENAME']);
		$operator_name = $operator['filename'];
		$name = pathinfo($tsdata['ts'][0]['label']);
		if (isset($_REQUEST['debug'])) {echo "<br><br>_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";}
		if (isset($_REQUEST['debug'])) {echo "<br><br>parameters<pre>"; print_r($parameters); echo "</pre>";}
		if (isset($_REQUEST['debug'])) {echo "<br><br>output data<pre>"; print_r($dest); echo "</pre>";}
		if ($parameters['format']['value']=='csv') {
			$filename = $name['filename'];
			header("Content-Disposition: attachment; filename={$filename}.csv");
			header("Content-Type: application/vnd.ms-excel");
			header("Content-Length: ".strlen($dest));
			die($dest);
		}
		if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
		$ndest = count($dest);
		for ($i=0; $i<$ndest; $i++) {
			if (isset($tsdata['ts'][$i]['data'][0]['prestart'])) {
				if (isset($_REQUEST['debug'])) {echo "<br><br>\$dest[$i][0]<pre>"; print_r($dest[$i][0]); echo "</pre>";}
				$dest[$i][0]['prestart'] = $dest[$i][0][0]; 
				$dest[$i][0]['y'] = $dest[$i][0][1]; 
				$dest[$i][0]['x'] = $tsdata['ts'][$i]['data'][0]['x']; 
				$dest[$i][0]['marker'] = $tsdata['ts'][$i]['data'][0]['marker'];
			}
		}
		if ($parameters['format']['value']=='short') {
			die(json_encode(count($tsdata['ts'])==1? $dest[0]: $dest));
		}
		if ($parameters['format']['value']=='columns') {
			if (count($tsdata['ts'])<2) {
				$out = array('label'=>$tsdata['ts'][0]['label'], 'datetime'=>array(), 'value'=>array());
				for ($i=0; $i<count($dest[0]); $i++) {
					$out['datetime'][$i] = date('Y-m-d H:i:s',$dest[0][$i][0]/1000); //$dest[0][$i][0]/1000; //  
					$out['value'][$i] = $dest[0][$i][1];
				}
			}
			$out = array();
			for ($j=0; $j<count($tsdata['ts']); $j++) {
				$out[$j] = array('label'=>$tsdata['ts'][$j]['label'], 'datetime'=>array(), 'value'=>array());
				for ($i=0; $i<count($dest[$j]); $i++) {
					$out[$j]['datetime'][$i] = date('Y-m-d H:i:s',$dest[$j][$i][0]/1000); // $dest[$j][$i][0]/1000; // 
					$out[$j]['value'][$i] = $dest[$j][$i][1];
				}
			}
			die(trim(json_encode($out),'[]'));
		}
		if ($parameters['format']['value']=='valueOnly') {
			// $d = array();
			for ($j=0; $j<count($tsdata['ts']); $j++) {
				// $d[$j] = array();
				for ($i=0; $i<count($dest[$j]); $i++) {
					$dest[$j][$i] = $dest[$j][$i][1];
				}
			}
			die(json_encode(count($tsdata['ts'])==1? $dest[0]: $dest));
		}
		if (!$parameters['appendts']['value']) { for ($i=$ndest; $i<count($tsdata['ts']); $i++) {unset($tsdata['ts'][$i]);}}
		for ($i=0; $i<$ndest; $i++) {
			if ($parameters['appendts']['value']) {
				$tsdata['ts'][] = array(
					"display_unit"=>$tsdata['ts'][$i]["display_unit"],
					"ts_id"=>"$operator_name({$tsdata['ts'][$i]["ts_id"]})",
					"label"=>"$operator_name({$tsdata['ts'][$i]["label"]})",
					"xaxis"=>$tsdata['ts'][$i]["xaxis"],
					"yaxis"=>$tsdata['ts'][$i]["yaxis"],
					'data'=>$dest[$i],
					'num_rows'=>count($dest[$i])
				);
			}
			else {
				if (isset($_REQUEST['debug'])) {echo "<br><br>appendts<pre>"; print_r($i);echo "</pre>";}
				$tsdata['ts'][$i]["label"] = "$operator_name({$tsdata['ts'][$i]["label"]})";
				$tsdata['ts'][$i]['data'] = $dest[$i];
			}
		}
		die(json_encode($tsdata));
	}

?>
