<?php
	include './hdb_conf.php';

	$timezone = date_default_timezone_get();
	// if (isset($_REQUEST['debug'])) echo "date_default_timezone_get(): ".date_default_timezone_get()."<br>\n";
	// if (isset($_REQUEST['debug'])) file_put_contents('debug.txt', json_encode($_REQUEST));
	if (isset($_REQUEST['debug'])) echo json_encode($_REQUEST);

	$state = array('ON','OFF','CLOSE','OPEN','INSERT','EXTRACT','MOVING','STANDBY','FAULT','INIT','RUNNING','ALARM','DISABLE','UNKNOWN');
	$bool = array('FALSE','TRUE');

	$pretimer = !isset($_REQUEST['no_pretimer']);
	$posttimer = !isset($_REQUEST['no_posttimer']);

	$db = mysqli_connect(HOST, USERNAME, PASSWORD);
	mysqli_select_db($db, DB);
	
	$xls = false;
	$now = time();
	

	// ----------------------------------------------------------------
	// Quote variable to make safe
	function quote_smart($value, $quote="'")
	{
		global $db;
		// Stripslashes
		if (get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}
		strtr($value, '&#147;&#148;`', '""'."'");
		// Quote if not integer
		if (!is_numeric($value)) {
			$value = $quote.mysqli_real_escape_string($db, $value).$quote;
		}
		return $value;
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
	
	if (isset($_REQUEST['query'])) {
		echo "query<br>\n"; debug($_REQUEST['query']);
		$res = mysqli_query($db, $_REQUEST['query']);
		echo "err: ".mysqli_error($db)."<br>\n";
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			debug($row);
		}
		exit();
	}

	// ----------------------------------------------------------------
	// parse and detect time periods
	function parse_time($time) {
		global $now;
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
			$t = $now;
			return date('Y-m-d H:i:s', $t - $n*$time_factor - ($t % $time_factor));
		}
		return $time;
	}
	if (isset($_REQUEST['s'])) {
		$s = explode(';', quote_smart($_REQUEST['s'], ''));
		$ts = array();
		foreach ($s as $search) {
			$res = mysqli_query($db, "SELECT ID FROM adt WHERE full_name LIKE '%$search%' ORDER BY full_name");
		 	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$ts[$row['ID']] = true;
			}
		}
		if (count($ts)) $_REQUEST['ts'] = implode(';', array_keys($ts));
	}

	if (!isset($_REQUEST['start'])) die('no start (date/time) selected');
	if (!isset($_REQUEST['ts'])) die('no ts (time series) selected');


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

	$ts_array = explode(';', $_REQUEST["ts"]);
	$ts = array(1=>array(),2=>array(),3=>array(),4=>array(),5=>array(),6=>array(),7=>array(),8=>array(),9=>array(),10=>array());
	foreach ($ts_array as $ts_element) {
		$t = explode(',', $ts_element);
		// if (isset($_REQUEST['debug'])) echo json_encode($t);
		$x = (isset($t[1]) and is_numeric($t[1]))? $t[1]: 1;
		// $x = isset($t[1])? $t[1]: 1;
		$y = isset($t[2])? $t[2]: ($t[1]=='multi'? 'multi': 1);
		// $y = isset($t[2])? $t[2]: $t[1];
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
	$querytime = $oldquerytime = $fetchtime = 0.0;
	$samples = 0;
	$decimationSamples = isset($_REQUEST['decimation_samples'])? $_REQUEST['decimation_samples']-0: 1000;
	$decimation = isset($_REQUEST['decimation'])? $_REQUEST['decimation']: 'maxmin'; // maxmin, downsample, none
	foreach ($ts as $xaxis=>$ts_array) {
		if (empty($ts_array)) continue;
		$start_timestamp = strtotime($start[$xaxis-1]);
		$interval = $stop_timestamp[$xaxis-1] - $start_timestamp;
		$slot_maxmin = $decimationSamples>0? $interval*2/$decimationSamples: 0;
		foreach ($ts_array as $ts_num=>$ts_id_num) {
			$big_data_w = array();
			$res = mysqli_query($db, "SELECT * FROM adt WHERE ID={$ts_id_num[0]}");
			if (isset($_REQUEST['debug'])) echo "SELECT * FROM adt WHERE ID={$ts_id_num[0]};<br>\n";
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			$table = sprintf("att_{$ts_id_num[0]}");
			$col_name = !$row['writable']? "value AS val": "read_value AS val";
			$big_data[$ts_counter]['ts_id'] = $ts_id_num[0];
			$big_data[$ts_counter]['label'] = strtr($row['full_name'], $skipdomain);
			$big_data[$ts_counter]['xaxis'] = $xaxis;
			$big_data[$ts_counter]['yaxis'] = $ts_id_num[1];
			if ($xls) $myxls->Data[0][$ts_counter+1] = $row['full_name'];
			// derived from enum CmdArgType
			if ($row['data_type']==19) $big_data[$ts_counter]['categories'] = $state;
			if ($row['data_type']==1) $big_data[$ts_counter]['categories'] = $bool;
			if ($io=="rw") {
				$big_data[$ts_counter+1]['ts_id'] = $ts_id_num[0];
				$big_data[$ts_counter+1]['label'] = strtr($row['full_name'], $skipdomain).'_w';
				$big_data[$ts_counter]['label'] = strtr($row['full_name'], $skipdomain).'_r';
				$big_data[$ts_counter+1]['xaxis'] = $xaxis;
				$big_data[$ts_counter+1]['yaxis'] = $ts_id_num[1];
			}
			$orderby = "time";
			$dim = $row['data_format'];
			if ($dim==1) $decimation = 'downsample';
			if (isset($_REQUEST['debug'])) {debug($row, 'row'); }
			$period = $stop_timestamp[$xaxis-1]-strtotime($start[$xaxis-1]);
			$pretimer_limit = date('Y-m-d H:i:s', strtotime($start[$xaxis-1])-$period*log($period)/2);
			$union = ''; //$pretimer? " UNION (SELECT time, $col_name FROM $table WHERE time < '{$start[$xaxis-1]}' AND time > '$pretimer_limit' ORDER BY time DESC LIMIT 1)": '';
			// $query = "SELECT UNIX_TIMESTAMP(time) AS time, $col_name FROM $table WHERE time >= '{$start[$xaxis-1]}'{$stop[$xaxis-1]}$union ORDER BY $orderby";
			$query = "SELECT UNIX_TIMESTAMP(time) AS time, $col_name FROM $table WHERE time >= '{$start[$xaxis-1]}'{$stop[$xaxis-1]}$union ORDER BY $orderby";
			if (isset($_REQUEST['debug'])) {debug($query); } // exit(0);}
			$querytime -= microtime(true);
			$res = mysqli_query($db, $query);
			$querytime += microtime(true);
			$fetchtime -= microtime(true);
			$samples += mysqli_num_rows($res);
			if (isset($_REQUEST['debug'])) file_put_contents('debug.txt', $query);
			$sample = -1;
			$oversampled = false;
			// limit to less than 1000 samples http://api.highcharts.com/highcharts#plotOptions.series.turboThreshold
			if (($decimationSamples > 0) && ($decimation!=='none')) {
				$sampling_every = ceil(mysqli_num_rows($res)/$decimationSamples);
				$oversampled = $sampling_every>1;
			}
			$max = $min = array();
			if (($dim==1) && ($big_data[$ts_counter]['yaxis']=='multi')) {
				$pretimer = false;
				$posttimer = false;
			}
			if ($pretimer and (mysqli_num_rows($res)<500)) {
				$query = "SELECT UNIX_TIMESTAMP(time) AS time, $col_name FROM $table WHERE time <= '{$start[$xaxis-1]}' AND time > '$pretimer_limit' ORDER BY time DESC LIMIT 1";
				$querytime -= microtime(true);
				$res2 = mysqli_query($db, $query);
				$querytime += microtime(true);
				if (mysqli_num_rows($res2)>0) {
					$row = mysqli_fetch_array($res2, MYSQLI_ASSOC);
					if ($row['time']<strtotime($start[$xaxis-1]))
					$big_data[$ts_counter]['data'][] = array('x'=>strtotime($start[$xaxis-1]." $timezone")*1000,'y'=>$row['val']-0, 
					'marker'=>array('symbol'=>'url(http://fcsproxy.elettra.trieste.it/docs/egiga2m/img/prestart.png)'), 
					'prestart'=>$row['time']*1000); 
				}
			}
			$big_data[$ts_counter+$k]['num_rows'] = mysqli_num_rows($res);
			$big_data[$ts_counter]['query_time'] = $querytime - $oldquerytime;
			$oldquerytime = $querytime;
			if (isset($_REQUEST['dump'])) {list($column, $trash) = explode(' AS ', $col_name); echo "<br>\nINSERT INTO $table (time, $column) VALUES "; $dumprow=0;} 
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				if (isset($_REQUEST['debug'])) {print_r($row);} 
				if ($row['time']==0) continue;
				if (isset($_REQUEST['dump'])) {
					if ($dumprow==1000) {echo ";<br>\nINSERT INTO $table (time, $column) VALUES "; $dumprow=0;} 
					if ($dumprow>0) echo ',';
					$dumprow++;
					echo '('.date("'Y-m-d H:i:s'", $row['time']).','.(strlen($row['val'])? $row['val']-0: 'NULL').')'; 
					continue; 
				}
				// if ($xls) {$myxls->Data[] = array($row['time'],$row['val']); continue;}
				if ($oversampled) {
					if ($decimation=='downsample') {
						if ($sampling_every>1) {
							$sample++;
							if ($sample % $sampling_every) continue;
						}
						if ($dim==1) {
							$v = explode(',', $row['val']);
							if ($big_data[$ts_counter]['yaxis']=='multi') {
								foreach ($v as $k=>$i) {
									$big_data[$ts_counter+$k]['ts_id'] = $ts_id_num[0]."[$k]";
									$big_data[$ts_counter+$k]['label'] = strtr($row['full_name']."[$k]", $skipdomain);
									if (isset($remapLabel[$ts_id_num[0]])) {$big_data[$ts_counter+$k]['label'] = $remapLabel[$ts_id_num[0]][$k];}
									$big_data[$ts_counter+$k]['xaxis'] = $xaxis;
									$big_data[$ts_counter+$k]['yaxis'] = $ts_id_num[1];
									$big_data[$ts_counter+$k]['data'][] = array($row['time']*1000, $i-0);
								}
							}
							else {
								foreach ($v as $k=>$i) $v[$k] = $i-0; 
								$big_data[$ts_counter]['data'][] = array($row['time']*1000, $v);
							}
						}
						else {
							$v = $type=='string'? $row['val']: (strlen($row['val'])? $row['val']-0: $row['val']);
							if (isset($_REQUEST['hideOverMaxmin']) && isset($_REQUEST['maxY']) && ($v > $_REQUEST['maxY'])) continue;
							if (isset($_REQUEST['hideOverMaxmin']) && isset($_REQUEST['minY']) && ($v < $_REQUEST['minY'])) continue;
							$big_data[$ts_counter]['data'][] = array($row['time']*1000, $v);
						}
					}
					else if ($decimation=='maxmin') {
						$slot = floor(($row['time']-$start_timestamp)/$slot_maxmin);
						$v = $row['val']-0;
						if (isset($_REQUEST['hideOverMaxmin']) && isset($_REQUEST['maxY']) && ($v > $_REQUEST['maxY'])) continue;
						if (isset($_REQUEST['hideOverMaxmin']) && isset($_REQUEST['minY']) && ($v < $_REQUEST['minY'])) continue;
						if (isset($max[$slot])) {
							if ($v>$max[$slot][1]) $max[$slot] = array($row['time']*1000, $v);
							if ($v<$min[$slot][1]) $min[$slot] = array($row['time']*1000, $v);
						}
						else $max[$slot] = $min[$slot] = array($row['time']*1000, $v);
					}
				}
				else {
					// todo: find an efficient method to convert string to numeric
					if ($dim==1) {
						$v = explode(',', $row['val']);
						if ($big_data[$ts_counter]['yaxis']=='multi') {
							foreach ($v as $k=>$i) {
								$big_data[$ts_counter+$k]['ts_id'] = $ts_id_num[0]."[$k]";
								$big_data[$ts_counter+$k]['label'] = strtr($row['full_name']."[$k]", $skipdomain);
								if (isset($remapLabel[$ts_id_num[0]])) {$big_data[$ts_counter+$k]['label'] = $remapLabel[$ts_id_num[0]][$k];}
								$big_data[$ts_counter+$k]['xaxis'] = $xaxis;
								$big_data[$ts_counter+$k]['yaxis'] = $ts_id_num[1];
								$big_data[$ts_counter+$k]['data'][] = array($row['time']*1000, $i-0);
							}
						}
						else {
							foreach ($v as $k=>$i) $v[$k] = $i-0; 
							$big_data[$ts_counter]['data'][] = array($row['time']*1000, $v);
						}
					}
					else {
						$v = $type=='string'? $row['val']: (strlen($row['val'])? $row['val']-0: $row['val']);
						if (isset($_REQUEST['hideOverMaxmin']) && isset($_REQUEST['maxY']) && ($v > $_REQUEST['maxY'])) continue;
						if (isset($_REQUEST['hideOverMaxmin']) && isset($_REQUEST['minY']) && ($v < $_REQUEST['minY'])) continue;
						$big_data[$ts_counter]['data'][] = array($row['time']*1000, $v);
					}
				}
				$last_val = $v;
				/*
				if ($io=="rw") {
					$big_data_w[] = array($row['time']*1000, $type=='string'? $row['val_w']: $row['val_w']-0);
				}
				*/
			}
			if (isset($_REQUEST['dump'])) {echo ";";}
			if ($decimation=='maxmin') {
				foreach ($max as $slot=>$point) {
					if ($point[0]<$min[$slot][0]) {
						$big_data[$ts_counter]['data'][] = $point;
						$big_data[$ts_counter]['data'][] = $min[$slot];
					}
					else {
						$big_data[$ts_counter]['data'][] = $min[$slot];
						$big_data[$ts_counter]['data'][] = $point;
					}
				}
			}
			// debug(count($big_data["ts{$xaxis}_".$ts_id_num]['data']), 'ts'.$ts_id_num);
			
			if ($posttimer and ($stop_timestamp[$xaxis-1]<=$now) and ($ts_counter<1000)) {
				$big_data[$ts_counter]['data'][] = array('x'=>$stop_timestamp[$xaxis-1]*1000,'y'=>$last_val,'marker'=>array('radius'=>1),'poststart'=>true); 
			}
			$ts_counter++;
			$fetchtime += microtime(true);
		}
	}

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
		fwrite($fd, "$date $remote $forwarded $requests query: ".round($querytime,2)."[s] fetch: ".round($fetchtime,2)."[s] #samples: $samples\n");
		fclose($fd);
	}

	if ($xls) {$myxls->SendFile();exit();}

	// if (isset($_REQUEST['categorized'])) 
	$big_data = array('ts'=>$big_data);

	if (isset($_REQUEST['dump'])) {exit(0);}
	header("Content-Type: application/json");
	echo json_encode($big_data);
?>
