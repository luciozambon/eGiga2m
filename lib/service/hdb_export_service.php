<?php

	include './hdb_conf.php';

	if (isset($_REQUEST['debug'])) $t0 = microtime(TRUE);
	if (isset($_REQUEST['debug'])) file_put_contents('debug.txt', json_encode($_REQUEST));
	$timezone = date_default_timezone_get();

	$state = array('ON','OFF','CLOSE','OPEN','INSERT','EXTRACT','MOVING','STANDBY','FAULT','INIT','RUNNING','ALARM','DISABLE','UNKNOWN');

	$pretimer = (isset($_REQUEST['pretimer']) and ($_REQUEST['pretimer']!=='false'))? true: false;

	$db = mysqli_connect(HOST, USERNAME, PASSWORD);
	mysqli_select_db($db, DB);

	// ----------------------------------------------------------------
	// Quote variable to make safe
	function quote_smart($value)
	{
		global $db;
		// Stripslashes
		if (get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}
		strtr($value, '&#147;&#148;`', '""'."'");
		// Quote if not integer
		if (!is_numeric($value)) {
			$value = "'".mysqli_real_escape_string($db, $value)."'";
		}
		return $value;
	}

	$timeformat = '';
	if (isset($_REQUEST['timeformat'])) {
		if ($_REQUEST['timeformat']=='noseconds') {
			$timeformat = "DATE_FORMAT(time,'%Y-%m-%d %H:%i') AS ";
		}
		else {
			$timeformat = "DATE_FORMAT(time,".quote_smart($_REQUEST['timeformat']).") AS ";
		}
	}

	// ----------------------------------------------------------------
	// debug a variable
	function debug($var, $name='')
	{
		if ($name !== '') {
			echo "\$$name: ";
		}
		if (is_array($var)) {
			echo "<pre>"; print_r($var); echo "</pre><p>\n";
		}
		else {
			echo ($var===0? "0": $var)."<p>\n";
		}
	}

	// ----------------------------------------------------------------
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
		return $time;
	}

/*
IGOR
WAVES/D unit1, unit2
BEGIN
19.7 23.9
19.8 23.7
20.1 22.9
END
X SetScale x 0,1, "V", unit1; SetScale y 0,0, "A", unit1
X SetScale x 0,1, "V", unit2; SetScale y 0,0, "A", unit2
*/
	// ----------------------------------------------------------------
	// emit igor statistics
	function emit_igor($data, $name) {
		$nl = "\r\n";
		$buff = "IGOR{$nl}WAVES/D timestamp";
		foreach ($name as $label) {
			if (defined("SLASH_SEPARATED_INDEX") and strpos($label, '/') !== false) {
				$labelArray = explode("/", $label);
				$label = isset($labelArray[SLASH_SEPARATED_INDEX])? $labelArray[SLASH_SEPARATED_INDEX]: $label;
			}
			$buff .= "\t".strtr($label, array("/"=>"_", "."=>"_", " "=>"_", "["=>"_", "]"=>"_"));
		}
		$buff .= "{$nl}BEGIN{$nl}";
		foreach ($data as $t=>$v) {
			$buff .= strtotime($t)+2082844800; // date("d-m-Y H:i:s", $t);
			foreach ($v as $val) {
				$buff .= "\t".($val==="-"? "NAN": sprintf("%e", $val));
			}
			$buff .= "{$nl}";
		}
		$buff .= "END{$nl}";
		// debug($buff);exit();
		header("Content-Disposition: attachment; filename=eGiga2m.itx");
		header("Content-Type: application/x-itx");
		header("Content-Length: ".strlen($buff));
		echo $buff;
		exit();
	}


	// ----------------------------------------------------------------
	// MAIN
	if (!isset($_REQUEST['start'])) die('no start (date/time) selected');
	if (!isset($_REQUEST['ts'])) die('no ts (time series) selected');

    if (defined('LOG_REQUEST')) {
      $requests = $sep = '';
      foreach ($_REQUEST as $key => $value ) {
        $requests .= $sep . $key . '=' . $value;
	$sep = '&';
      }
      $remote = $_SERVER['REMOTE_ADDR'];
      $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR'])? $_SERVER['HTTP_X_FORWARDED_FOR']: 0;
      $fd = fopen(LOG_REQUEST, 'a');
      $date = date("Y-m-d H:i:s");
      fwrite($fd, "$date $remote $forwarded $requests\n");
      fclose($fd);
    }
	
	$mat = $xls = $csv = $igor = false;
	$merged_var = true;
	if (isset($_REQUEST['format'])) {
		if ($_REQUEST['format']=='mat') {
			$mat = true;
			$old_error_reporting = error_reporting(0);
			include("../php2mat.php");
			error_reporting($old_error_reporting);
			$php2mat = new php2mat();
			// eval platform
			list($h, $platform, $g) = explode(";", strtr($_SERVER["HTTP_USER_AGENT"], array("(" => ";")), 3);
			$merged_var = false;
		}
		if ($_REQUEST['format']=='xls') {
			$xls = true;
			$old_error_reporting = error_reporting(0);
			include("../xls/xls.php");
			error_reporting($old_error_reporting);
			$myxls = new php_xls();
			$myxls->FileName = "eGiga2m.xls";
			$myxls->Data[0][0] = "time";
		}
		if ($_REQUEST['format']=='csv') {
			$csv = true;
			$separator = isset($_REQUEST['separator'])? $_REQUEST['separator']: ',';
			$string_continer = isset($_REQUEST['string_continer'])? $_REQUEST['string_continer']: '';
		}
		if ($_REQUEST['format']=='igor') {
			$igor = true;
		}
		$filename = isset($_REQUEST['filename'])? $_REQUEST['filename']: 'egiga2m.'.$_REQUEST['format'];
	}


	$start = explode(';', $_REQUEST['start']);
	foreach ($start as $k=>$val) {
		$start[$k] = parse_time($val);
		$stop[$k] = ' AND time <= NOW() + INTERVAL 2 HOUR';
		$stop_timestamp[$k] = time();
	}
	if (isset($_REQUEST["stop"]) and strlen($_REQUEST["stop"])) {
		$stop = explode(';', $_REQUEST['stop']);
		foreach ($stop as $k=>$val) {
			$time = parse_time($val);
			$stop[$k] = strlen($val)? " AND time < '$time'": ' AND time <= NOW() + INTERVAL 2 HOUR';
			$stop_timestamp[$k] = strlen($val)? strtotime($time): time();
		}
	}

	$ts_array = explode(';', $_REQUEST["ts"]);
	$ts = array(1=>array(),2=>array(),3=>array(),4=>array(),5=>array(),6=>array(),7=>array(),8=>array(),9=>array(),10=>array());
	foreach ($ts_array as $ts_element) {
		$t = explode(',', $ts_element);
		$x = (isset($t[1]) and is_numeric($t[1]))? $t[1]: 1;
		$y = isset($t[2])? $t[2]: 1;
		$ts[$x][] = array($t[0], $y);
	}

	$data_type_result = array(
		"ro"=>"value AS val",
		"rw"=>"read_value AS val",
		"wo"=>"value_w AS val_w"
	);
	$timezone_offset = 0;
	$big_data = array();
	$ts_counter = 0;
	$type = 'num';
	$csv_header = $data = array();
	foreach ($ts as $xaxis=>$ts_array) {
	  if (!$merged_var) {
		foreach ($ts_array as $ts_num=>$ts_id_num) {
			$big_data_w = array();
			$res = mysqli_query($db, "SELECT * FROM adt WHERE ID={$ts_id_num[0]}");
			// echo "SELECT * FROM adt WHERE ID={$ts_id_num[0]};<br>\n";
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			$full_name = strtr($row['full_name'],array('/'=>'_'));
			$table = sprintf("att_{$ts_id_num[0]}");
			$col_name = !$row['writable']? "value AS val": "read_value AS val";
			$orderby = "time";
			$dim = $row['data_format'];
			$tm = "UNIX_TIMESTAMP(time)";
			if ($mat) $tm = "UNIX_TIMESTAMP(time) / 86400 + 719519"; // UNIX_TIMESTAMP(time) / 86400 + datenum('01/01/1970');";
			$union = $pretimer? " UNION (SELECT time, $tm AS t, $col_name FROM $table WHERE time <= '{$start[$xaxis-1]}' ORDER BY time DESC LIMIT 1)": '';
			$query = "SELECT time, $tm AS t, $col_name FROM $table WHERE time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]}$union ORDER BY $orderby";
			// debug($query); exit(0);
			$res = mysqli_query($db, $query);
			$time = $val = array();
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$time[] = $row['t'];
				$val[] = $row['val']-0;
			}
			$big_data[$full_name] = array($time, $val);
		}
	  }
	  else {
		if (empty($ts_array)) continue;
		foreach($ts_array as $k=>$t) {
			$ts_empty[$k] = ' ';
		}
		foreach ($ts_array as $ts_num=>$ts_id_num) {
			$big_data_w = array();
			$query = "SELECT * FROM adt WHERE ID={$ts_id_num[0]}";
			$res = mysqli_query($db, $query);
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			if (isset($_REQUEST['debug'])) debug($query);
			$table = sprintf("att_{$ts_id_num[0]}");
			$col_name = !$row['writable']? "value AS val": "read_value AS val";
			if (isset($_REQUEST['zoh']) or isset($_REQUEST['foh'])) {
				$query = "SELECT {$timeformat}time, $col_name FROM $table WHERE time <= '{$start[$xaxis-1]}' ORDER BY time DESC LIMIT 1";
				$res = mysqli_query($db, $query);
				if (isset($_REQUEST['debug'])) debug($query);
				$zrow = mysqli_fetch_array($res, MYSQLI_ASSOC);
				$old_data[$ts_num] = $zrow['val']-0;
				$old_time[$ts_num] = $zrow['time']-0;
				if (isset($_REQUEST['debug'])) {debug($zrow, 'row');debug($ts_num, 'ts_num');}
			}
			/*
			if (isset($_REQUEST['foh']) and (!empty($stop[$xaxis-1]))) {
				$query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} {$stop[$xaxis-1]} ORDER BY data_time LIMIT 1";
				$res = mysqli_query($db, $query);
				if (isset($_REQUEST['debug'])) debug($query);
				$zrow = mysqli_fetch_array($res, MYSQLI_ASSOC);
				$last_data[$ts_num] = $zrow['val']-0;
				$last_time[$ts_num] = $zrow['time']-0;
				if (isset($_REQUEST['debug'])) {debug($zrow, 'row');debug($ts_num, 'ts_num');}
			}
			*/
			$big_data[$ts_counter]['ts_id'] = $ts_id_num[0];
			$big_data[$ts_counter]['label'] = strtr($row['full_name'], $skipdomain);
			$big_data[$ts_counter]['xaxis'] = $xaxis;
			$big_data[$ts_counter]['yaxis'] = $ts_id_num[1];
			if ($xls) $myxls->Data[0][$ts_counter+1] = $row['full_name'];
			if ($csv or $igor) $csv_header[$ts_counter+1] = $row['full_name'];
			$ts_map[$ts_id_num[0]] = $ts_counter;
			if ($row['data_type']==19) $big_data[$ts_counter]['categories'] = $state;
			if ($io=="rw") {
				$big_data[$ts_counter+1]['ts_id'] = $ts_id_num[0];
				$big_data[$ts_counter+1]['label'] = strtr($row['full_name'], $skipdomain).'_w';
				$big_data[$ts_counter]['label'] = strtr($row['full_name'], $skipdomain).'_r';
				$big_data[$ts_counter+1]['xaxis'] = $xaxis;
				$big_data[$ts_counter+1]['yaxis'] = $ts_id_num[1];
			}
			$orderby = "time";
			$dim = $row['data_format'];
			$union = $pretimer? " UNION (SELECT time, $col_name FROM $table WHERE time <= '{$start[$xaxis-1]}' ORDER BY time DESC LIMIT 1)": '';
			$query = "SELECT {$timeformat}time, $col_name FROM $table WHERE time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]}$union ORDER BY $orderby";
			// debug($query); exit(0);
			// if (isset($_REQUEST['timeformat'])) die($query);
			$res = mysqli_query($db, $query);
			// if (isset($_REQUEST['debug'])) file_put_contents('debug.txt', $query);
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				// if (!isset($data[$row['time']])) $data[$row['time']] = array_merge($ts_array, array_fill(0,count($ts_array), ''));
				if (!isset($data[$row['time']])) $data[$row['time']] = $ts_empty;
				// debug($data[$row['time']]);
				// debug($ts_id_num);
				$data[$row['time']][$ts_map[$ts_id_num[0]]] = $row['val']-0;
				/*
				if ($dim==1) {
					$v = explode(',', $row['val']);
					foreach ($v as $k=>$i) $v[$k] = $i-0; 
					$big_data[$ts_counter]['data'][] = array(strtotime($row['time']." $timezone")*1000, $v);
				}
				else {
					if (strtotime($row['time'])<strtotime($start[$xaxis-1])) {
						$big_data[$ts_counter]['data'][] = array('x'=>strtotime($start[$xaxis-1]." $timezone")*1000,'y'=>$row['val']-0, 
						'marker'=>array('symbol'=>'url(http://fcsproxy.elettra.trieste.it/docs/egiga2m/img/prestart.png)'), 
						'prestart'=>strtotime($row['time']." $timezone")*1000); 
					}
					else $big_data[$ts_counter]['data'][] = array(strtotime($row['time']." $timezone")*1000, $type=='string'? $row['val']: $row['val']-0);
				}
				*/
			}
			$ts_counter++;
		}
	  }
	}
	
	ksort($data);
	// if (isset($_REQUEST['debug'])) {debug($data); exit();}
//http://fcsproxy.elettra.trieste.it/docs/egiga2m/lib/service/hdb_export_service.php?start=2015-06-18%2003:40:00&stop=2015-06-18%2003:45:00&ts=00196,1,1;00214,1,1&conf=fermi&export=csv&separator=%09&filename=2015-06-18.csv
	if ($mat) {
		$php2mat->SendFile("eGiga2m.mat", $big_data, "eGiga2m, Platform: $platform, Created on: ".date("d-F-Y H:i:s"));
		exit();
	}
	if ($xls) {
		foreach ($data as $time=>$v) {
			if (isset($_REQUEST['zoh'])) {foreach ($v as $i=>$val) {if ($val==$ts_empty[$i]) $v[$i] = $old_data[$i]; else $old_data[$i] = $val;}}
			array_unshift($v, $time);
			$myxls->Data[] = $v;
		}
		// debug($myxls->Data);
		$myxls->FileName = $filename;
		$myxls->SendFile();
		exit();
	}
	if ($csv) {
		if (!isset($_REQUEST['debug']))  {
			header("Content-Type: application/csv");
			header("Content-Disposition: attachment; filename=\"$filename\"");
		}
		else {
			debug($_REQUEST);
		}
		echo "time";
		foreach ($csv_header as $h) {
			echo $separator.$h;
		}
		echo "\n";
		foreach ($data as $time=>$v) {
			echo $time;
			foreach ($v as $i=>$val) {
				if (isset($_REQUEST['zoh'])) {if ($val==$ts_empty[$i]) $val = $old_data[$i]; else $old_data[$i] = $val;}
				if (isset($_REQUEST['debug'])) echo "_{$val}_{$nv}_{$i}_{$old_data[$i]}<br>";
				echo $separator.$val;
			}
			echo "\n";
		}
		// debug($myxls->Data);
		exit();
	}
	if ($igor) {
		emit_igor($data, $csv_header);
	}
	
?>
