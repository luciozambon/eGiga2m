<?php
require_once("./csvrepo_conf.php");
$old_error_reporting = error_reporting(E_ALL);
require_once("./sql_interface.php");
error_reporting($old_error_reporting);

$querytime = 0.0;

// parse and detect time periods
function parse_time($time) {
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
	if (strlen($time)>19 || $time[4]!='-' || $time[7]!='-' || $time[10]!=' ' || $time[13]!=':' || $time[16]!=':') die("ERROR: invalid date");
	return $time;
}

function detect_time($timestring) {
	if ($_REQUEST['timeformat']=='YYYY.MM.DD_HH.mm.ss') {
		$t = explode('_', $timestring);
		return "'".strtr($t[0],array('.'=>'-')).' '.strtr($t[1],array('.'=>':'))."'";
	}
	if ($_REQUEST['timeformat']=='YYYY-MM-DD HH:mm:ss') {
		return "'$timestring'";
	}
	if ($_REQUEST['timeformat']=='X') {
		return "'".date('Y-m-d H:i:s', $timestring)."'";
	}
	// todo parse other cases
	file_put_contents('import.log', date("Y-m-d H:i:s")." ERROR: timeformat not supported yet {$_REQUEST['timeformat']}\n", FILE_APPEND);
	die('ERROR: timeformat not supported yet');
}

function myquery($query, $param=array()) {
	global $sql;
	$data = $sql->sql_secure($query, $param);
	$err = $sql->sql_error(); 
	if (!empty($err)) {
		if (strpos($err, 'duplicate key value')===false) file_put_contents('import.log', date("Y-m-d H:i:s")." $err - $query\n", FILE_APPEND);
		echo " $err - $query\n";
	}
	return $data;
}


if (isset($_REQUEST['tree'])) {
	$a = explode('_', $_REQUEST['key']);
	if (!in_array($a[1], $databases)) die("ERROR: storage not supported");
	$sql = new SqlInterface('pg');
	$sql->sql_connect(HOST, USERNAME, PASSWORD, $a[1]);
	if (count($a)==2) {
		$query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type='BASE TABLE'";
		$data = $sql->sql_secure($query, array());
		$res = array(); foreach ($data as $d) {$res[] = $d['table_name'];}
		die(implode(',', $res));
	}
	if (count($a)==3) {
		$query = "SELECT column_name FROM information_schema.columns WHERE table_name = $1 ORDER BY column_name";
		$data = $sql->sql_secure($query, array($a[2]));
		foreach ($data as $d) {
			if ($d['column_name']=='time') continue;
			$col[] = $d['column_name'];
		}
		die(implode(',', $col));
	}
	die("ERROR: key not found or invalid");
}

if (isset($_REQUEST['ts'])) {
	$c = pg_connect("host=".HOST." user=".USERNAME." password=".PASSWORD);
	$start = explode(';', $_REQUEST['start']);
	foreach ($start as $k=>$val) {
		$start[$k] = parse_time($val);
		$stop[$k] = ' AND time <= NOW() + INTERVAL 2 HOUR';
		$stop_timestamp[$k] = $now;
	}
	if (isset($_REQUEST["stop"]) and strlen($_REQUEST["stop"])) {
		$stop = explode(';', $_REQUEST['stop']);
		foreach ($stop as $k=>$val) {
			$time = parse_time($val);
			$stop[$k] = strlen($val)? " AND time < '$time'": ' AND time <= NOW() + INTERVAL 2 HOUR';
			$stop_timestamp[$k] = strlen($val)? strtotime($time): $now;
		}
	}
	$r = explode(';', $_REQUEST['ts']);
	$ds = array();
	$skipdecimation = isset($_REQUEST['all']) || 
		(isset($_REQUEST['decimationSamples']) && ($_REQUEST['decimationSamples']=='0')) || 
		(isset($_REQUEST['decimation']) && ($_REQUEST['decimation']=='none'));
	foreach ($r as $k=>$req) {
		$q = explode(',', $req);
		$parts = explode('_', $q[0]);
		if (!in_array($parts[0], $databases)) die("ERROR: storage not supported");
		$pg_table = strtolower(trim(pg_escape_identifier($parts[1]), '"')); // $parts[1];
		if (!isset($ds[$parts[0]])) $ds[$parts[0]] = array();
		if (!isset($ds[$parts[0]][$pg_table])) {
			$startk = isset($start[$k])? $start[$k]: $start[0];
			$stopk = isset($stop[$k])? $stop[$k]: $stop[0];
			$ds[$parts[0]][$pg_table] = array(
				'col'=>array(),
				'start'=>$startk,
				'stop'=>$stopk,
				'x'=>array(),
				'y'=>array(),
				'dt'=>$stop_timestamp[$k] - strtotime($startk)
			);
		}
		$p2 = strtolower(trim(pg_escape_identifier($parts[2]), '"'));
		$ds[$parts[0]][$pg_table]['col'][] = ($ds[$parts[0]][$pg_table]['dt'] <= THRESHOLD_5M || $skipdecimation)? $p2: "avg_{$p2} AS $p2";
		$ds[$parts[0]][$pg_table]['x'][] = $q[1]-0;
		$ds[$parts[0]][$pg_table]['y'][] = $q[2]-0;
	}
	pg_close($c);
	$data = array('ts'=>array());
	foreach ($ds as $db => $tb) {
		$conn = pg_connect("host=".HOST." dbname=$db user=".USERNAME." password=".PASSWORD);
		foreach ($tb as $table => $column) {
			if ($column['dt'] <= THRESHOLD_5M || $skipdecimation) {
				$query = "SELECT EXTRACT(EPOCH FROM time)*1000 AS time,".implode(',', $column['col'])." FROM $table WHERE '{$column['start']}'<=time {$column['stop']} ORDER BY time";
			}
			else if ($column['dt'] <= THRESHOLD_1H) {
				$query = "SELECT EXTRACT(EPOCH FROM bucket)*1000 AS time,".implode(',', $column['col'])." FROM {$table}_5m WHERE '{$column['start']}'<=bucket ".strtr($column['stop'],array('time'=>'bucket'))." ORDER BY bucket";
			}
			else if ($column['dt'] <= THRESHOLD_1D) {
				$query = "SELECT EXTRACT(EPOCH FROM bucket)*1000 AS time,".implode(',', $column['col'])." FROM {$table}_1h WHERE '{$column['start']}'<=bucket ".strtr($column['stop'],array('time'=>'bucket'))." ORDER BY bucket";
			}
			else {
				$query = "SELECT EXTRACT(EPOCH FROM bucket)*1000 AS time,".implode(',', $column['col'])." FROM {$table}_1d WHERE '{$column['start']}'<=bucket ".strtr($column['stop'],array('time'=>'bucket'))." ORDER BY bucket";
			}
			if (isset($_REQUEST['debug'])) echo "start: {$column['start']}<br>\nstop: {$column['stop']}<br>\ndt: {$column['dt']}<br>\n$query<br>\n";
			$querytime -= microtime(true);
			$result = pg_query($conn, $query);
			$time = pg_fetch_all_columns($result,0);
			$querytime += microtime(true);
			foreach ($column['col'] as $i=>$col) {
				$querytime -= microtime(true);
				$c = pg_fetch_all_columns($result,$i+1);
				$querytime += microtime(true);
				$d = array();
				foreach ($c as $j=>$y) {
					$d[] = array($time[$j]-0,$y-0);
				}
				$ca = explode(' AS', strtr($col,array('avg_'=>'')));
				$data['ts'][] = array("display_unit"=>"","ts_id"=>"$i","label"=>"{$db}_{$table}_{$ca[0]}","xaxis"=>$column['x'][$i],"yaxis"=>"{$column['y'][$i]}","data"=>$d,"query_time"=>$querytime);
			}
		}
		pg_close($conn);
	}
	if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
	die(json_encode($data));
}

if (isset($_REQUEST['import'])) {
	$querytime -= microtime(true);
	if (!in_array($_REQUEST["db"], $databases)) die("ERROR: storage non supported");
	$sql = new SqlInterface('pg');
	$sql->sql_connect(HOST, USERNAME, PASSWORD, $_REQUEST["db"]);
	$content = explode("\n",$_REQUEST["content"]);
	$n = explode('_', $_REQUEST['name']);
	$pg_name = strtolower(trim(pg_escape_identifier($n[0]), '"'));
	// echo "$pg_name<br>\nSELECT relname AS tablename FROM pg_class WHERE relkind IN ('r') AND relname LIKE $pg_name<br>\n";
	$query = "SELECT relname AS tablename FROM pg_class WHERE relkind IN ('r') AND relname LIKE $1";
	$data = $sql->sql_secure($query, array($pg_name));
	$separator = isset($_REQUEST['separator'])? substr($_REQUEST['separator'], 0, 1): ';';
	$col = explode($separator, strtolower(trim($content[0])));
	// echo "data<pre>"; print_r($data); echo "</pre>";
	if (empty($data)) {
		$query = "CREATE TABLE IF NOT EXISTS $pg_name (time TIMESTAMP WITH TIME ZONE PRIMARY KEY";
		$caquery = '';
		foreach ($col as $i=>$c) {
			if ($i==0) continue;
			$pg_col = trim(pg_escape_identifier($c), '"');
			$query .= ",$pg_col NUMERIC";
			$caquery .= ",AVG($pg_col) AS avg_$pg_col";
		}
		$query .= ")";
		myquery($query);
		myquery("SELECT create_hypertable('$pg_name', 'time')");
		myquery("CREATE MATERIALIZED VIEW {$pg_name}_1d WITH (timescaledb.continuous) AS SELECT time_bucket('1 day', time) as bucket $caquery FROM $pg_name GROUP BY bucket WITH NO DATA");
		myquery("CREATE MATERIALIZED VIEW {$pg_name}_1h WITH (timescaledb.continuous) AS SELECT time_bucket('1 hour', time) as bucket $caquery FROM $pg_name GROUP BY bucket WITH NO DATA");
		myquery("CREATE MATERIALIZED VIEW {$pg_name}_5m WITH (timescaledb.continuous) AS SELECT time_bucket('5 minute', time) as bucket $caquery FROM $pg_name GROUP BY bucket WITH NO DATA");
	}
	else {
		$query = "SELECT column_name FROM information_schema.columns WHERE table_name = $1";
		$data = $sql->sql_secure($query, array($pg_name));
		foreach ($data as $d) {
			$oldcol[] = $d['column_name'];
		}
		$diff = array_diff($col, $oldcol);
		if (isset($diff[1]) || (isset($diff[0]) && ($diff[0]!='timestamp'))) {
			file_put_contents('import.log', date("Y-m-d H:i:s")." ERROR: column mistmatch - $pg_name, oldcol: ".json_encode($oldcol).", newcol: ".json_encode($col)."\n", FILE_APPEND);
			die("ERROR: column mistmatch");
		}
	}
	$query = '';
	foreach ($content as $i=>$line) {
		if ($i==0) continue;
		$l = explode($separator, trim($line));
		$time = detect_time(array_shift($l));
		if ($i==1) $time0 = $time;
		if ($time=="' '") continue;
		$timef = $time;
		$qrow = "($time,".implode(',', $l).")";
		$query .= (strlen($query)? ', ': "INSERT INTO $pg_name VALUES ").$qrow;
		if (strlen($query) > 3000) {
			myquery($query);
			$query = '';
		}
	}
	if (strlen($query) > 0) {
		myquery($query);
	}
	myquery("CALL refresh_continuous_aggregate('{$pg_name}_1d', $time0, $timef)");
	myquery("CALL refresh_continuous_aggregate('{$pg_name}_1h', $time0, $timef)");
	myquery("CALL refresh_continuous_aggregate('{$pg_name}_5m', $time0, $timef)");
	$querytime += microtime(true);
	echo "ok ".round($querytime,2)."[s]";
}
?>
