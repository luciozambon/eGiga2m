<!DOCTYPE html>
<html lang="en"><head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="">
	<meta name="author" content="LZ">
	<link rel="icon" href="http://www.elettra.eu/favicon.png">

	<title>eGiga2m - logs</title>

	<!-- Bootstrap core CSS -->
	<link href="../lib/bootstrap/bootstrap.css" rel="stylesheet">
	<!-- Bootstrap theme -->
	<link href="../lib/bootstrap/bootstrap-theme.css" rel="stylesheet">

	<!-- Custom styles for this template -->
	<link href="../lib/bootstrap/theme.css" rel="stylesheet">
	</head>
	<body>
		<form method='post' action='?'>
		db <select name='db'>
			<option value='fermi'>FERMI</option>
			<option value='fermi_hdbppold'>FERMI OLD DATA</option>
			<option value='elettra'>ELETTRA</option>
		</select>
		<input type='submit' value='select'></form>
		

<?php
	// ----------------------------------------------------------------
	// parse and detect time periods
	function parse_time($time, $t) {
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
			if (empty($t)) $t = time();
			return date('Y-m-d H:i:s', $t - $n*$time_factor - ($t % $time_factor));
		}
		return $time;
	}

		
	$db = (isset($_REQUEST['db']) && in_array($_REQUEST['db'], array('fermi_hdbppold', 'fermi', 'fermi_tdb', 'padres', 'elettra')))? $_REQUEST['db']: 'fermi';
	$f = file("$db.log");
	$monitor = isset($_REQUEST['monitor'])? "<th>IP</th><th>host</th>": '';
	echo "<h3> ".strtoupper($db)." eGiga2m Log</h3>\n<table class='table table-hover table-striped'>\n<tr><th>Date time</th>$monitor<th>Start</th><th>Stop</th><th>TS</th><th style='text-align: right'>Query [s]</th><th style='text-align: right'>Elab [s]</th><th style='text-align: right'>Samples</th><th style='text-align: right'>Samples/s</th></tr>";
	$keys = array('start', 'stop', 'ts');
	$querySum = $sampleSum = $elabSum = $queryVariance = $elabVariance = 0;
	$host = array();
	for ($i=count($f)-1; $i>=count($f)-50000; $i--) {
		if ($i<0) break;
		list($dateipparam, $time) = explode('query: ', $f[$i]);
		list($dateip, $link) = explode(' conf=', $dateipparam);
		list($date, $hh, $ip) = explode(' ', $dateip);
		$val = array();
		$linkparts = explode('&', $link);
		foreach ($linkparts as $p) {
			if (strpos($p, '=')===false) continue;
			list($k, $v) = explode('=', $p);
			if (!in_array($k, $keys)) continue;
			$val[$k] = $v;
		}
		$ts = strlen($val['ts']) > 43? substr($val['ts'], 0, 40).'...': $val['ts'];
		if (strlen($ts)===0) continue;
		if (strpos($val['start'], 'last')!==false) {
			$start = parse_time($val['start'], strtotime("$date $hh"));
			$link = strtr($link, array('start='.$val['start'] => "start=$start&stop=$date $hh", '&stop='.$val['stop'] => ''));
		}
		$param = isset($_REQUEST['monitor'])? "<td><a href='../egiga2m.html?conf=$link'>{$val['start']}</a></td><td>{$val['stop']}</td><td>$ts</td>": "<td>{$val['start']}</td><td>{$val['stop']}</td><td>$ts</td>";
		$times = explode(': ', $time);
		list($query, $trash) = explode('[', $times[0]);
		$sampleSum += $times[2]-0;
		$elabSum += $times[1]-0;
		$querySum += $query;
		$samplesPerSecond = $query>0? round(($times[2]-0)/$query*100)/100: '';
		if ($query > 3600) {
			$query = "<span style='background-color: red; color: white; font-weight: bolder'>$query</span>";
		}
		else if ($query > 60) {
			$query = "<span style='background-color: yellow; font-weight: bolder'>$query</span>";
		}
		if (empty($host[$ip])) $host[$ip] = gethostbyaddr($ip);
		$monitor = isset($_REQUEST['monitor'])? "<td>$ip</td><td>{$host[$ip]}</td>": '';
		echo "<tr><td>$date $hh</td>$monitor$param<td style='text-align: right'>$query</td><td style='text-align: right'>".($times[1]-0)."</td><td style='text-align: right'>".($times[2]-0)."</td><td style='text-align: right'>$samplesPerSecond</td></tr>\n";
	}
	$qps = $sampleSum/$querySum;
	$monitor = isset($_REQUEST['monitor'])? "<td></td><td></td>": '';
	echo "<tr><td>Total</td>$monitor<td></td><td></td><td></td><td style='text-align: right'>$querySum</td><td style='text-align: right'>$elabSum</td><td style='text-align: right'>$sampleSum</td></tr>\n";
	echo "<tr><td>samples per sec</td>$monitor<td></td><td></td><td></td><td style='text-align: right'>".(round($qps*100)/100)."</td><td style='text-align: right'>".(round($sampleSum/$elabSum*100)/100)."</td><td style='text-align: right'></td></tr>\n";
	for ($i=count($f)-1; $i>=count($f)-50000; $i--) {
		if ($query) {$tmp = (($times[2]-0)/$query)-$qps; $queryVariance += $tmp*$tmp;}
	}
	echo "<tr><td>deviation</td>$monitor<td></td><td style='text-align: right'>".(round(sqrt($queryVariance)*100)/100)."</td><td style='text-align: right'></td><td style='text-align: right'></td></tr>\n";
	echo "</table>\n";
?>

	<!-- jquery -->
	<link rel="stylesheet" href="../lib/jquery/jquery-ui.min.css">
	<script src="../lib/jquery/jquery.min.js" type="text/javascript"></script>
	<script src="../lib/jquery/jquery-ui.min.js" type="text/javascript"></script>

	<!-- Bootstrap core JavaScript -->
	<script src="../lib/bootstrap/bootstrap.js"></script>
	<script src="../lib/bootstrap/docs.js"></script>

