<?php
	// https://stackoverflow.com/questions/48800128/using-php-mysql-how-do-i-free-memory
	include './hdbpp_conf.php';
	$timezone = date_default_timezone_get();

	$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')? 'https': 'http';
	$host = $protocol.'://'.$_SERVER["HTTP_HOST"];
	$uri = explode('?', $_SERVER["REQUEST_URI"]);
	$host .= strtr($uri[0], array('/lib/service/hdbpp_plot_service.php'=>''));

	$state = array('ON','OFF','CLOSE','OPEN','INSERT','EXTRACT','MOVING','STANDBY','FAULT','INIT','RUNNING','ALARM','DISABLE','UNKNOWN');
	$bool = array('FALSE','TRUE');

	$pretimer = !isset($_REQUEST['no_pretimer']);
	$posttimer = !isset($_REQUEST['no_posttimer']);
	$no_time = isset($_REQUEST['no_time']);


	$db = mysqli_connect(HOST, USERNAME, PASSWORD, DB, PORT);
	//$db = mysqli_connect(HOST, USERNAME, PASSWORD);
	mysqli_select_db($db, DB);

	$now = time();

	if (isset($_REQUEST['Seconds_Behind_Master'])) {
		header("Content-Type: application/json");
		$query = "SHOW SLAVE STATUS";
		$res = mysqli_query($db, $query);
		if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			die(json_encode($row['Seconds_Behind_Master']-0));
		}
		else {
			die(json_encode(mysqli_error($db)));
		}
	}
	
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
	function return_bytes($val) {
		$val = trim($val);
		if (($len = strlen($val)) < 1) {
			return 0;
		}	
		$last = strtolower($val{strlen($val)-1});
		$v = (int) substr($val, 0, -1);
		switch($last) {
			case 'g':
					 $v *= 1024;
			case 'm':
					 $v *= 1024;
			case 'k':
					 $v *= 1024;
		}
		return $v;
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
	$event = array('error', 'alarm', 'command', 'button');
	$show = array();
	foreach ($event as $e) {
		$show[$e] = (!SKIP_EVENT) || (isset($_REQUEST["show_$e"]));
	}
	$yerr = 0;

	// ----------------------------------------------------------------
	// based on https://stackoverflow.com/questions/48800128/using-php-mysql-how-do-i-free-memory
	function optimized_query() {
		global $db, $memory_limit, $start, $stop, $ts, $ts_array, $data_type_result, $decimationSamples, $decimation, $stop_timestamp, $querytime, 
		$skipdomain, $fetchtime, $pretimer, $no_time, $show, $yerr;
		$samples = 0;
		if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
		echo '{"ts":[';
		$tsbreak = $envseparator = '';
		foreach ($ts as $xaxis=>$ts_array) {
			if (empty($ts_array)) continue;
			$start_timestamp = strtotime($start[$xaxis-1]);
			$interval = $stop_timestamp[$xaxis-1] - $start_timestamp;
			$slot_maxmin = $decimationSamples>0? $interval*2/$decimationSamples: 0;
			foreach ($ts_array as $ts_num=>$ts_id_num) {
				$recordsPerIteration = isset($_REQUEST['recordsPerIteration'])? $_REQUEST['recordsPerIteration']-0: 500000;
				if (isset($_REQUEST['debug'])) debug($ts_num, 'ts_num '.__LINE__);
				if (isset($_REQUEST['debug'])) debug($ts_id_num, 'ts_id_num');
				$big_data_w = array();
				list($att_conf_id,$element_index,$trash) = explode('[',trim($ts_id_num[0], ']').'[[',3);
				if (isset($_REQUEST['debug'])) debug($att_conf_id, 'att_conf_id '.__LINE__);
				if (isset($_REQUEST['debug'])) debug($element_index, 'element_index');
				$query = "SELECT * FROM att_parameter WHERE att_conf_id=$att_conf_id ".strtr($stop[$xaxis-1],array('data_time'=>'recv_time'))." ORDER BY recv_time DESC LIMIT 1";
				// $query = "SELECT COUNT(*), display_unit FROM (SELECT DISTINCT att_conf_id, display_unit FROM att_parameter ORDER BY display_unit) AS t GROUP BY display_unit";
				$querytime -= microtime(true);
				$res = mysqli_query($db, $query);
				$querytime += microtime(true);
				if (isset($_REQUEST['debug'])) debug($query, 'query');
				while ($unit_row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
					if (isset($_REQUEST['debug'])) {debug($unit_row, 'unit_row'); debug(json_decode($unit_row['enum_labels']));}
					$data_buffer['display_unit'] = $unit_row['display_unit']=='No display unit'? '': $unit_row['display_unit'];
					if ($unit_row['enum_labels']!='[]') $data_buffer['categories'] = json_decode($unit_row['enum_labels']);
				}
				$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id=$att_conf_id AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
				$querytime -= microtime(true);
				$res = mysqli_query($db, $query);
				$querytime += microtime(true);
				if (isset($_REQUEST['debug'])) debug($query, 'query '.__LINE__);
				$conf_row = mysqli_fetch_array($res, MYSQLI_ASSOC);
				list($dim, $type, $io) = explode('_', $conf_row['data_type']);
				$table = sprintf("att_{$dim}_{$type}_{$io}");
				// do not process read/write for array
				if (($io=="rw") and (($element_index=='0') or (!empty($_REQUEST['readonly'])))) $io = "ro";
				if (($io=="rw") and ($element_index=='1')) $io = "wo";
				$col_name = $data_type_result[$io];
				if ($type=='devstate') $data_buffer['categories'] = $state;
				if ($type=='devboolean') $data_buffer['categories'] = $bool;
				$data_buffer['ts_id'] = $att_conf_id;
				$data_buffer['label'] = strtr($conf_row['att_name'], $skipdomain);
				$data_buffer['xaxis'] = $xaxis;
				$data_buffer['yaxis'] = $ts_id_num[1];
				$orderby = "data_time";
				$last = isset($_REQUEST['last'])? ' DESC LIMIT 1': '';
				$thresh = isset($_REQUEST['thresh_lt'])? " AND ".strtr($data_type_result[$io], array(' AS val'=>''))." < ".($_REQUEST['thresh_lt']-0): '';
				$thresh = isset($_REQUEST['thresh_gt'])? " AND ".strtr($data_type_result[$io], array(' AS val'=>''))." > ".($_REQUEST['thresh_gt']-0): $thresh;
				$query = "SELECT COUNT(*) AS n FROM $table WHERE att_conf_id=$att_conf_id AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]}$thresh";
				// echo "querytime: $querytime<br>\n";
				$querytime -= microtime(true);
				$res = mysqli_query($db, $query);
				$querytime += microtime(true);
				$data = mysqli_fetch_array($res, MYSQLI_ASSOC);
				$num_rows = $data['n'];
				$samples += $num_rows;
				// echo "querytime: $querytime<br>\n";
				$data_buffer['num_rows'] = $num_rows;
				$data_buffer['data'] = array('eGiga2m_separator');
				$data_buffer['query_time'] = 'eGiga2m_querytime';
				$querybase = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE att_conf_id=$att_conf_id AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]}$thresh ORDER BY $orderby$last";
				$start_limit = 0;
				if (isset($_REQUEST['debug'])) debug($data_buffer,'data_buffer '.__LINE__);
				$env = explode('"eGiga2m_separator"', json_encode($data_buffer, JSON_INVALID_UTF8_IGNORE));
				echo $envseparator.$env[0];
				$envseparator = ',';
				$data_separator = '';
				$sample = -1;
				$oversampled = false;
				if ($decimationSamples > $num_rows) $decimation = 'none';
				// limit to less than 1000 samples http://api.highcharts.com/highcharts#plotOptions.series.turboThreshold
				if (($decimationSamples > 0) && ($decimation !== 'none')) {
					$sampling_every = ceil($num_rows/$decimationSamples);
					if (isset($_REQUEST['debug'])) debug($sampling_every, "sampling_every $num_rows/$decimationSamples ".__LINE__);
					$oversampled = $sampling_every>2;
					// echo "recordsPerIteration: $recordsPerIteration, sampling_every: $sampling_every<br>\n";
					$recordsPerIteration -= $recordsPerIteration % $sampling_every;
					// echo "recordsPerIteration: $recordsPerIteration<br>\n"; exit();
				}
				$maxv = $minv = array();
				unset($max);
				for (;;) {
					$query = "$querybase LIMIT $start_limit , $recordsPerIteration";
					if (isset($_REQUEST['debug'])) debug($query, 'query '.__LINE__);
					$querytime -= microtime(true);
					$res = mysqli_query($db, $query);
					$querytime += microtime(true);
					$fetchtime -= microtime(true);
					if ($pretimer and ($num_rows<500)) {
						$query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE att_conf_id=$att_conf_id AND data_time <= '{$start[$xaxis-1]}' ORDER BY data_time DESC LIMIT 1";
						$fetchtime += microtime(true);
						$querytime -= microtime(true);
						$res2 = mysqli_query($db, $query);
						$querytime += microtime(true);
						$fetchtime -= microtime(true);
						if (isset($_REQUEST['debug'])) debug($query, 'query '.__LINE__. ', mysqli_num_rows:'.mysqli_num_rows($res2));
						if (mysqli_num_rows($res2)>0) {
							$conf_row = mysqli_fetch_array($res2, MYSQLI_ASSOC);
							if (round($conf_row['time'])<strtotime($start[$xaxis-1]." $timezone"))
							if ($no_time) $output = $data_separator.json_encode($conf_row['val']-0, JSON_INVALID_UTF8_IGNORE);
							else $output = $data_separator.json_encode(array('x'=>strtotime($start[$xaxis-1]." $timezone")*1000,'y'=>$conf_row['val']-0, 'marker'=>array('symbol'=>"url($host/img/prestart.png)"), 'prestart'=>$conf_row['time']*1000));
							if ($conf_row['val'] !== NULL) $yerr = $row['val']-0;
							if (!empty($output)) {
								echo $output;
								$data_separator = ',';
							}
							if (isset($_REQUEST['debug'])) debug($data_separator, "data_separator, line ".__LINE__. ", output: $output, output2: $output2");
						}
					}
					$avgbuf = $avgcount = 0;
					if (isset($_REQUEST['dump'])) {echo "<br>\nINSERT INTO $table (data_time, att_conf_id, value_r) VALUES "; $dumprow=0;}
					$querytime -= microtime(true);
					$rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
					$querytime += microtime(true);
					if ($oversampled) {
						if (isset($_REQUEST['debug'])) debug($row, "oversampled, decimation: $decimation, row".__LINE__);
						if ($decimation=='downsample') {
							for ($i=0; $i<$recordsPerIteration; $i+=$sampling_every) {
								if (!isset($rows[$i])) break;
								$row = $rows[$i];
								if ($row['val'] !== NULL) $yerr = $row['val']-0;
								// echo $data_separator.$i.json_encode($rows[$i])."\n";
								if ($no_time) echo $data_separator.json_encode((($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0, JSON_INVALID_UTF8_IGNORE);
								else echo $data_separator.json_encode(array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0), JSON_INVALID_UTF8_IGNORE);
								$data_separator = ',';
							}
						}
						else if ($decimation=='avg') {
							foreach ($rows as $row) {
								if ($row['val'] !== NULL) $yerr = $row['val']-0;
								$sample++;
								if (($sample+1) % $sampling_every) {
									if ($row['val'] !== NULL) {
										$avgbuf += $row['val']-0;
										$avgcount++;
									}
								}
								else {
									if ($no_time) echo $data_separator.json_encode($avgcount>0? $avgbuf/$avgcount: NULL, JSON_INVALID_UTF8_IGNORE);
									else echo $data_separator.json_encode(array($row['time']*1000, $avgcount>0? $avgbuf/$avgcount: NULL), JSON_INVALID_UTF8_IGNORE);
									$data_separator = ',';
									$avgbuf = $avgcount = 0;
								}
							}
						}
						else if ($decimation=='maxmin') {
							if (isset($_REQUEST['debug'])) {echo "decimation==maxmin max: $max, min: $min<br>".json_encode($rows[0]).' '.__LINE__.'<br>'.json_encode($rows[1]).'<br>';}
							$imax = $imin = 0;
							$prevtime = $rows[0]['time'];
							foreach ($rows as $imm=>$row) {
								// if (isset($_REQUEST['debug_maxmin'])) if ($row['val'] === NULL) continue;
								if ($row['val'] !== NULL) $yerr = $row['val']-0;
								$deltatime = ($row['time']-$prevtime)/2;
								$sample++;
								$v = is_null($row['val'])? NULL: $row['val']-0;
								if (isset($max) && !is_null($max) && !is_null($v)) {
									if ($v>$max) {$max = $v; $imax = $imm;}
									if ($v<$min) {$min = $v; $imin = $imm;}
								}
								else $max = $min = $v;
								// if (is_null($row['val'])) $max = $min = NULL;
								if (($sample+1) % $sampling_every) {
									if ($row['val'] !== NULL) {
										$avgbuf += $v;
										$avgcount++;
									}
								}
								else {
									if ($no_time) echo $data_separator.json_encode($min, JSON_INVALID_UTF8_IGNORE).','.json_encode($max, JSON_INVALID_UTF8_IGNORE);
									else {
										if ($imin < $imax) {
											echo $data_separator.json_encode(array(($row['time']-$deltatime)*1000, $min), JSON_INVALID_UTF8_IGNORE).','.json_encode(array($row['time']*1000, $max), JSON_INVALID_UTF8_IGNORE);
										}
										else {
											echo $data_separator.json_encode(array(($row['time']-$deltatime)*1000, $max), JSON_INVALID_UTF8_IGNORE).','.json_encode(array($row['time']*1000, $min), JSON_INVALID_UTF8_IGNORE);
										}
									}
									$prevtime = $row['time'];
									$data_separator = ',';
									unset($max);
								}
							}
						}
					}
					else {
						if (isset($_REQUEST['debugjson'])) {$data_buffer['data'] = array('eGiga2m_separator'); $buf = explode('"eGiga2m_separator"', json_encode($data_buffer, JSON_INVALID_UTF8_IGNORE)); echo '<pre>';print_r($buf);die('</pre>');}
						if (isset($_REQUEST['debug'])) {debug($rows[0], $data_separator.' rows[0] '.__LINE__);}
						foreach ($rows as $row) {
							// if (isset($_REQUEST['debug'])) {debug($row, 'row '.__LINE__);debug($type, 'type');}
							// else {
								if ($no_time) echo $data_separator.json_encode((($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0, JSON_INVALID_UTF8_IGNORE);
								else echo $data_separator.json_encode(array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0), JSON_INVALID_UTF8_IGNORE);
								$data_separator = ',';
							//}
							if ($row['val'] !== NULL) $yerr = $row['val']-0;
							if (function_exists('memory_get_usage')) {
								if ($memory_limit-memory_get_usage() < 8200) {
									if (defined('LOG_REQUEST')) {
										$requests = $sep = '';
										foreach ($_REQUEST as $key => $value ) {
											$requests .= $sep . $key . '=' . $value;
											$sep = '&';
										}
										$remote = $_SERVER['REMOTE_ADDR'];
										$forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR'])? $_SERVER['HTTP_X_FORWARDED_FOR']: 0;
										if ($fd = @fopen(LOG_REQUEST, 'a')) {
											$date = date("Y-m-d H:i:s");
											fwrite($fd, "$date $remote $forwarded $requests Insufficient Storage query: ".round($querytime,2)."[s] fetch: ".round($fetchtime,2)."[s] #samples: $samples\n");
											fclose($fd);
										}
									}
									header("HTTP/1.1 507 Insufficient Storage");
									exit();
								}
							}
						}
					}
					// debug(count($big_data["ts{$xaxis}_".$ts_id_num]['data']), 'ts'.$ts_id_num);
					$fetchtime += microtime(true);
					if (mysqli_num_rows($res)<$recordsPerIteration) break;
					unset($res);
					unset($rows);
					$start_limit += $recordsPerIteration;
				}
				echo strtr($env[1],array('"eGiga2m_querytime"'=>$querytime));
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
			if ($fd = @fopen(LOG_REQUEST, 'a')) {
				$date = date("Y-m-d H:i:s");
				fwrite($fd, "$date $remote $forwarded $requests query: ".round($querytime,2)."[s] fetch: ".round($fetchtime,2)."[s] #samples: $samples\n");
				fclose($fd);
			}
		}
		if (isset($_REQUEST['errdebug2'])) die('$show '.print_r($show, true));
		if ($show['error']) {
			$err = get_error();
			die('],"event":{"error":'.json_encode($err).'}}');// ."\n\n".
		}
		die('],"event":[]}');
	}

	$is_json_array = defined("ARRAY_TYPE") && ARRAY_TYPE=="json";

	if (isset($_REQUEST['s'])) {
		$s = explode(';', quote_smart($_REQUEST['s'], ''));
		$ts = array();
		foreach ($s as $search) {
			$res = mysqli_query($db, "SELECT att_conf_id FROM att_conf WHERE att_name LIKE '%".trim($search, "'")."%' ORDER BY att_name");
			// echo "SELECT att_conf_id FROM att_conf WHERE att_name LIKE '%{$search}%' ORDER BY att_name; ".mysqli_error($db);
		 	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$ts[$row['att_conf_id']] = true;
			}
		}
		if (count($ts)) $_REQUEST['ts'] = implode(';', array_keys($ts));
	}

	$memory_limit = return_bytes(ini_get('memory_limit'));

	if (!isset($_REQUEST['start'])) die('no start (date/time) selected');
	if (!isset($_REQUEST['ts'])) die('no ts (time series) selected');
	$start = explode(';', $_REQUEST['start']);
	foreach ($start as $k=>$val) {
		$start[$k] = parse_time($val);
		$stop[$k] = ' AND data_time <= NOW() + INTERVAL 2 HOUR';
		$stop_timestamp[$k] = $now;
	}
	if (isset($_REQUEST["stop"]) and strlen($_REQUEST["stop"])) {
		$stop = explode(';', $_REQUEST['stop']);
		foreach ($stop as $k=>$val) {
			$time = parse_time($val);
			$stop[$k] = strlen($val)? " AND data_time < '$time'": ' AND data_time <= NOW() + INTERVAL 2 HOUR';
			$stop_timestamp[$k] = strlen($val)? strtotime($time): $now;
		}
	}

	$ts_array = explode(';', $_REQUEST["ts"]);
	$ts = array(1=>array(),2=>array(),3=>array(),4=>array(),5=>array(),6=>array(),7=>array(),8=>array(),9=>array(),10=>array());
	foreach ($ts_array as $ts_element) {
		$t = explode(',', $ts_element);
		$x = (isset($t[1]) and is_numeric($t[1]))? $t[1]: 1;
		$y = isset($t[2])? $t[2]: ($t[1]=='multi'? 'multi': 1);
		$ts[$x][] = array($t[0], $y);
	}

	$data_type_result = array(
		"ro"=>"value_r AS val",
		"rw"=>"value_r AS val, value_w AS val_w",
		"wo"=>"value_w AS val_w"
	);
	$timezone_offset = 0;
	$big_data = array();
	$ts_counter = 0;
	$querytime = $oldquerytime = $fetchtime = 0.0;
	$samples = 0;
	$decimationSamples = isset($_REQUEST['decimation_samples'])? $_REQUEST['decimation_samples']-0: 1000;
	$decimation = isset($_REQUEST['decimation'])? $_REQUEST['decimation']: 'maxmin'; // maxmin, downsample, avg, avgmaxmin, none
			
	// SELECT GROUP_CONCAT(att_conf_data_type_id) AS array FROM att_conf_data_type WHERE data_type LIKE 'array%'
	// SELECT GROUP_CONCAT(att_conf_data_type_id) AS array FROM att_conf_data_type WHERE data_type LIKE '%w'
	$att_conf_ids = array();
	foreach ($ts_array as $tsr) {$a=explode(',', $tsr); $att_conf_ids[]=$a[0];}
	$query = "SELECT GROUP_CONCAT(data_type) AS dtype FROM att_conf,att_conf_data_type WHERE att_conf_id IN (".implode(',',$att_conf_ids).") AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
	$res = mysqli_query($db, $query);
	$data = mysqli_fetch_array($res, MYSQLI_ASSOC);
	$notarray = strpos($data['dtype'], 'array_')===false;
	$notrw = strpos($data['dtype'], '_rw')===false;

	if (($decimation!='avgmaxmin') && $notarray && ($notrw || isset($_REQUEST['readonly']))) {
		optimized_query();
	}
	foreach ($ts as $xaxis=>$ts_array) {
		if (empty($ts_array)) continue;
		$start_timestamp = strtotime($start[$xaxis-1]);
		$interval = $stop_timestamp[$xaxis-1] - $start_timestamp;
		$slot_maxmin = $decimationSamples>0? $interval*2/$decimationSamples: 0;
		foreach ($ts_array as $ts_num=>$ts_id_num) {
			if (isset($_REQUEST['debug'])) debug($ts_num, 'ts_num '.__LINE__);
			if (isset($_REQUEST['debug'])) debug($ts_id_num, 'ts_id_num');
			$big_data_w = array();
			list($att_conf_id,$element_index,$trash) = explode('[',trim($ts_id_num[0], ']').'[[',3);
			if (isset($_REQUEST['debug'])) debug($att_conf_id, 'att_conf_id');
			if (isset($_REQUEST['debug'])) debug($element_index, 'element_index '.__LINE__);
			$query = "SELECT * FROM att_parameter WHERE att_conf_id=$att_conf_id ".strtr($stop[$xaxis-1],array('data_time'=>'recv_time'))." ORDER BY recv_time DESC LIMIT 1";
			// $query = "SELECT COUNT(*), display_unit FROM (SELECT DISTINCT att_conf_id, display_unit FROM att_parameter ORDER BY display_unit) AS t GROUP BY display_unit";
			$querytime -= microtime(true);
			$res = mysqli_query($db, $query);
			$querytime += microtime(true);
			if (isset($_REQUEST['debug'])) debug($query, 'query');
			while ($unit_row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				if (isset($_REQUEST['debug'])) {debug($unit_row, 'unit_row'); debug(json_decode($unit_row['enum_labels']));}
				$big_data[$ts_counter]['display_unit'] = $unit_row['display_unit']=='No display unit'? '': $unit_row['display_unit'];
				if ($unit_row['enum_labels']!='[]') $big_data[$ts_counter]['categories'] = json_decode($unit_row['enum_labels']);
			}
			$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id=$att_conf_id AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
			$querytime -= microtime(true);
			$res = mysqli_query($db, $query);
			$querytime += microtime(true);
			if (isset($_REQUEST['debug'])) debug($query, 'query '.__LINE__);
			$conf_row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			list($dim, $type, $io) = explode('_', $conf_row['data_type']);
			$table = sprintf("att_{$dim}_{$type}_{$io}");
			// do not process read/write for array
			if ($dim=='array') $_REQUEST['readonly'] = true;
			if (($io=="rw") and (($element_index=='0') or (!empty($_REQUEST['readonly'])))) $io = "ro";
			if (($io=="rw") and ($element_index=='1')) $io = "wo";
			$col_name = $dim=='array'? "value_r AS val".($is_json_array? ',-1 AS idx': ',idx'): $data_type_result[$io];
			if ($type=='devstate') $big_data[$ts_counter]['categories'] = $state;
			if ($type=='devboolean') $big_data[$ts_counter]['categories'] = $bool;
			$big_data[$ts_counter]['ts_id'] = $att_conf_id;
			$big_data[$ts_counter]['label'] = strtr($conf_row['att_name'], $skipdomain);
			$big_data[$ts_counter]['xaxis'] = $xaxis;
			$big_data[$ts_counter]['yaxis'] = $ts_id_num[1];
			if ($io=="rw") {
				$big_data[$ts_counter+1]['ts_id'] = $att_conf_id;
				$big_data[$ts_counter+1]['label'] = strtr($conf_row['att_name'], $skipdomain).'_w';
				$big_data[$ts_counter]['label'] = strtr($conf_row['att_name'], $skipdomain).'_r';
				$big_data[$ts_counter+1]['xaxis'] = $xaxis;
				$big_data[$ts_counter+1]['yaxis'] = $ts_id_num[1];
			}
			$orderby = $dim=='array'? "time".($is_json_array? '': ',idx'): "data_time";
			$last = isset($_REQUEST['last'])? ' DESC LIMIT 1': '';
			$thresh = isset($_REQUEST['thresh_lt'])? " AND ".strtr($data_type_result[$io], array(' AS val'=>''))." < ".($_REQUEST['thresh_lt']-0): '';
			$thresh = isset($_REQUEST['thresh_gt'])? " AND ".strtr($data_type_result[$io], array(' AS val'=>''))." > ".($_REQUEST['thresh_gt']-0): $thresh;
			$query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE att_conf_id=$att_conf_id AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]}$thresh ORDER BY $orderby$last";
			if (isset($_REQUEST['debug'])) debug($query);
			// debug($query); exit(0);
			$querytime -= microtime(true);
			$res = mysqli_query($db, $query);
			$querytime += microtime(true);
			$fetchtime -= microtime(true);
			$samples += mysqli_num_rows($res);
			$sample = -1;
			$oversampled = false;
			// limit to less than 1000 samples http://api.highcharts.com/highcharts#plotOptions.series.turboThreshold
			if (($decimationSamples > 0) && ($decimation !== 'none')) {
				$sampling_every = ceil(mysqli_num_rows($res)/$decimationSamples);
				if (isset($_REQUEST['debug'])) debug($sampling_every, 'sampling_every '.__LINE__);
				$oversampled = $sampling_every>1;
			}
			$maxv = $minv = array();
			// if (($dim=='array') && ($big_data[$ts_counter]['yaxis']=='multi')) {
			if ($dim=='array') {
				$pretimer = false;
				$posttimer = false;
			}
			if ($pretimer and (mysqli_num_rows($res)<500)) {
				$query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE att_conf_id=$att_conf_id AND data_time <= '{$start[$xaxis-1]}' ORDER BY data_time DESC LIMIT 1";
				$fetchtime += microtime(true);
				$querytime -= microtime(true);
				$res2 = mysqli_query($db, $query);
				$querytime += microtime(true);
				$fetchtime -= microtime(true);
				if (mysqli_num_rows($res2)>0) {
					$conf_row = mysqli_fetch_array($res2, MYSQLI_ASSOC);
					if (round($conf_row['time'])<strtotime($start[$xaxis-1]." $timezone"))
					if ($no_time) $big_data[$ts_counter]['data'][] = $conf_row['val']-0;
					else $big_data[$ts_counter]['data'][] = array('x'=>strtotime($start[$xaxis-1]." $timezone")*1000,'y'=>$conf_row['val']-0, 
					'marker'=>array('symbol'=>"url($host/img/prestart.png)"), 
					'prestart'=>$conf_row['time']*1000); 
				}
			}
			if (($dim=='array') && ($big_data[$ts_counter]['yaxis']!='multi')) {
				$buf = array(); $oldtime = false;
				while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
					if (isset($_REQUEST['debug']) && $is_json_array) echo "{$big_data[$ts_counter]['yaxis']} {$row['val']}\n";
					if ($oldtime != $row['time']) {
						if (count($buf)) {
							if ($no_time) $big_data[$ts_counter]['data'][] = $buf;
							else $big_data[$ts_counter]['data'][] = array($oldtime*1000, $buf);
						}
						$buf = array();
						$oldtime = $row['time'];
					}
					if ($is_json_array) $buf = array_merge($buf, json_decode($row['val']));
					else $buf[] = (($type=='devstring') || ($row['val'] === NULL))? $row['val']: $row['val']-0;
				}
			}
			else if (($dim=='array') && $is_json_array) {
				$buf = array(); $oldtime = false;
				while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
					if (isset($_REQUEST['debug'])) echo ($row['time'] % 864000)." {$row['val']}\n";
					$val = empty($row['val'])? $row['val']: json_decode($row['val']);
					if (!empty($val)) foreach ($val as $k=>$v) {
						if ($oldtime == false) {
							$big_data[$ts_counter+$k]['ts_id'] = $ts_id_num[0];
							$big_data[$ts_counter+$k]['label'] = strtr($conf_row['att_name'], $skipdomain)."[$k]";
							$big_data[$ts_counter+$k]['xaxis'] = $xaxis;
							$big_data[$ts_counter+$k]['yaxis'] = $ts_id_num[1];
							if ($no_time) $big_data[$ts_counter+$k]['data'][] = (($type=='devstring') or ($row['val'] === NULL))? $v: $v-0;
							else $big_data[$ts_counter+$k]['data'][] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $v: $v-0);
						}
						else {
							if ($no_time) $big_data[$ts_counter+$k]['data'][] = (($type=='devstring') or ($row['val'] === NULL))? $v: $v-0;
							else $big_data[$ts_counter+$k]['data'][] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $v: $v-0);
						}
					}
					$oldtime = true;
				}
			}
			else {
				$big_data[$ts_counter]['num_rows'] = mysqli_num_rows($res);
				$big_data[$ts_counter]['query_time'] = $querytime - $oldquerytime;
				if (isset($_REQUEST['debugjson'])) {$big_data[$ts_counter]['data'] = array('eGiga2m_separator'); $buf = explode('"eGiga2m_separator"', json_encode($big_data[$ts_counter])); echo '<pre>';print_r($buf);die('</pre>');}
				$oldquerytime = $querytime;
				$avgbuf = $avgcount = 0;
				if (isset($_REQUEST['dump'])) {echo "<br>\nINSERT INTO $table (data_time, att_conf_id, value_r) VALUES "; $dumprow=0;}
				while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
					if (isset($_REQUEST['dump'])) {
						if ($dumprow==1000) {echo ";<br>\nINSERT INTO $table (data_time, att_conf_id, value_r) VALUES "; $dumprow=0;} 
						if ($dumprow>0) echo ',';
						$dumprow++;
						echo '('.date("'Y-m-d H:i:s'", $row['time']).",$att_conf_id,".(strlen($row['val'])? $row['val']-0: 'NULL').')'; 
						continue; 
					}
					if ($oversampled) {
						if (isset($_REQUEST['debug'])) debug($row, "oversampled, decimation: $decimation, row ".__LINE__);
						if ($decimation=='downsample') {
							if ($sampling_every>1) {
								$sample++;
								if ($sample % $sampling_every) continue;
							}
							if ($dim=='array') {
								if ($big_data[$ts_counter]['yaxis']=='multi') {
									$k = $row['idx'];
									$big_data[$ts_counter+$k]['ts_id'] = $ts_id_num[0];
									$big_data[$ts_counter+$k]['label'] = strtr($conf_row['att_name'], $skipdomain)."[$k]";
									$big_data[$ts_counter+$k]['xaxis'] = $xaxis;
									$big_data[$ts_counter+$k]['yaxis'] = $ts_id_num[1]; 
									if ($no_time) $big_data[$ts_counter+$k]['data'][] = (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0;
									else $big_data[$ts_counter+$k]['data'][] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0);
								}
								else {
									foreach ($v as $k=>$i) $v[$k] = $i-0; 
									if ($no_time) $big_data[$ts_counter]['data'][] = $v;
									else $big_data[$ts_counter]['data'][] = array($row['time']*1000, $v);
								}
							}
							else {
								if ($no_time) $big_data[$ts_counter]['data'][] = (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0;
								else $big_data[$ts_counter]['data'][] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0);
								if ($io=="rw") {
									if ($no_time) $big_data_w[] = (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0;
									else $big_data_w[] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0);
								}
								if ($row['val'] !== NULL) $yerr = $row['val']-0;
							}
						}
						else if ($decimation=='avg') {
							$sample++;
							if ($dim=='array') {
								// not implemented jet
							}
							else {
								if (($sample+1) % $sampling_every) {
									if ($row['val'] !== NULL) {
										$avgbuf += $row['val']-0;
										$avgcount++;
									}
								}
								else {
									if ($no_time) $big_data[$ts_counter]['data'][] = $avgcount>0? $avgbuf/$avgcount: NULL;
									else $big_data[$ts_counter]['data'][] = array($row['time']*1000, $avgcount>0? $avgbuf/$avgcount: NULL);
									if ($io=="rw") {
										if ($no_time) $big_data_w[$ts_counter]['data'][] = $avgcount>0? $avgbuf/$avgcount: NULL;
										else $big_data_w[$ts_counter]['data'][] = array($row['time']*1000, $avgcount>0? $avgbuf/$avgcount: NULL);
									}
									$avgbuf = $avgcount = 0;
								}
							}
						}
						else if ($decimation=='avgmaxmin') {
							$sample++;
							if ($dim=='array') {
								// not implemented jet
								$k = $row['idx'];
								$big_data[$ts_counter+$k]['ts_id'] = $ts_id_num[0];
								$big_data[$ts_counter+$k]['label'] = strtr($conf_row['att_name'], $skipdomain)."[$k]";
								$big_data[$ts_counter+$k]['xaxis'] = $xaxis;
								$big_data[$ts_counter+$k]['yaxis'] = $ts_id_num[1];
								if ($no_time) $big_data[$ts_counter+$k]['data'][] = (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0;
								else $big_data[$ts_counter+$k]['data'][] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0);
							}
							else {
								$v = $row['val']-0;
								if (isset($max) && !is_null($max)) {
									if ($v>$max) $max = $v;
									if ($v<$min) $min = $v;
								}
								else $max = $min = $v;
								// if (is_null($row['val'])) $max = $min = NULL;
								if (($sample+1) % $sampling_every) {
									if ($row['val'] !== NULL) {
										$avgbuf += $v;
										$avgcount++;
									}
								}
								else {
									if ($no_time) $big_data[$ts_counter]['ranges'][] = array($min, $max);
									else $big_data[$ts_counter]['ranges'][] = array($row['time']*1000, $min, $max);
									if ($no_time) $big_data[$ts_counter]['data'][] = $avgcount>0? $avgbuf/$avgcount: NULL;
									else $big_data[$ts_counter]['data'][] = array($row['time']*1000, $avgcount>0? $avgbuf/$avgcount: NULL);
									if ($io=="rw") {
										if ($no_time) $big_data_w[$ts_counter]['data'][] = $avgcount>0? $avgbuf/$avgcount: NULL;
										else $big_data_w[$ts_counter]['data'][] = array($row['time']*1000, $avgcount>0? $avgbuf/$avgcount: NULL);
									}
									unset($max);
									$avgbuf = $avgcount = 0;
								}
							}
						}
						else if ($decimation=='maxmin') {
							if (isset($_REQUEST['debug_maxmin'])) {debug($decimation, 'decimation');}
							if ($dim=='array') {
								$k = $row['idx'];
								$big_data[$ts_counter+$k]['ts_id'] = $ts_id_num[0];
								$big_data[$ts_counter+$k]['label'] = strtr($conf_row['att_name'], $skipdomain)."[$k]";
								$big_data[$ts_counter+$k]['xaxis'] = $xaxis;
								$big_data[$ts_counter+$k]['yaxis'] = $ts_id_num[1];
								if ($no_time) $big_data[$ts_counter+$k]['data'][] = (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0;
								else $big_data[$ts_counter+$k]['data'][] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0);
							}
							else {
								$slot = floor(($row['time']-$start_timestamp)/$slot_maxmin);
								$v = is_null($row['val'])? NULL: $row['val']-0;
								if (isset($maxv[$slot]) && !is_null($maxv[$slot][1])) {
									if ($no_time) if ($v>$maxv[$slot][1]) $maxv[$slot] = $v;
									else if ($v>$maxv[$slot][1]) $maxv[$slot] = array($row['time']*1000, $v);
									if ($no_time) if ($v<$minv[$slot][1]) $minv[$slot] = $v;
									else if ($v<$minv[$slot][1]) $minv[$slot] = array($row['time']*1000, $v);
								}
								else {
									if ($no_time) $maxv[$slot] = $minv[$slot] = $v;
									else $maxv[$slot] = $minv[$slot] = array($row['time']*1000, $v);
								}
								if (is_null($row['val'])) {
									if ($no_time) $maxv[$slot] = $minv[$slot] = NULL;
									else $maxv[$slot] = $minv[$slot] = array($row['time']*1000, NULL);
								}
							}
						}
					}
					else {
						if (isset($_REQUEST['debug'])) {debug($row, 'row');debug($type, 'type');}
						if ($dim=='array') {
							if ($big_data[$ts_counter]['yaxis']=='multi') {
								$k = $row['idx'];
								$big_data[$ts_counter+$k]['ts_id'] = $ts_id_num[0];
								$big_data[$ts_counter+$k]['label'] = strtr($conf_row['att_name'], $skipdomain)."[$k]";
								$big_data[$ts_counter+$k]['xaxis'] = $xaxis;
								$big_data[$ts_counter+$k]['yaxis'] = $ts_id_num[1];
								if ($no_time) $big_data[$ts_counter+$k]['data'][] = (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0;
								else $big_data[$ts_counter+$k]['data'][] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0);
							}
							else {
								foreach ($v as $k=>$i) $v[$k] = $i-0; 
								if ($no_time) $big_data[$ts_counter]['data'][] = $v;
								else $big_data[$ts_counter]['data'][] = array($row['time']*1000, $v);
							}
						}
						else {
							if ($no_time) $big_data[$ts_counter]['data'][] = (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0;
							else $big_data[$ts_counter]['data'][] = array($row['time']*1000, (($type=='devstring') or ($row['val'] === NULL))? $row['val']: $row['val']-0);
							if ($io=="rw") {
								if ($no_time) $big_data_w[] = (($type=='devstring') or ($row['val_w'] === NULL))? $row['val_w']: $row['val_w']-0;
								else $big_data_w[] = array($row['time']*1000, (($type=='devstring') or ($row['val_w'] === NULL))? $row['val_w']: $row['val_w']-0);
							}
						}
					}
					if (function_exists('memory_get_usage')) {
						if ($memory_limit-memory_get_usage() < 16400) {
							if (defined('LOG_REQUEST')) {
								$requests = $sep = '';
								foreach ($_REQUEST as $key => $value ) {
									$requests .= $sep . $key . '=' . $value;
									$sep = '&';
								}
								$remote = $_SERVER['REMOTE_ADDR'];
								$forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR'])? $_SERVER['HTTP_X_FORWARDED_FOR']: 0;
								if ($fd = @fopen(LOG_REQUEST, 'a')) {
									$date = date("Y-m-d H:i:s");
									fwrite($fd, "$date $remote $forwarded $requests Insufficient Storage query: ".round($querytime,2)."[s] fetch: ".round($fetchtime,2)."[s] #samples: $samples\n");
									fclose($fd);
								}
							}
							header("HTTP/1.1 507 Insufficient Storage");
							exit();
						}
					}
				}
				if (isset($_REQUEST['dump'])) {echo ";";}
			}
			if ($decimation=='maxmin') {
				if (isset($_REQUEST['debug'])) debug($maxv, 'max '.__LINE__);
				foreach ($maxv as $slot=>$point) {
					if (is_null($point[1])) {
						$big_data[$ts_counter]['data'][] = $point;
					}
					else if ($point[0]<$minv[$slot][0]) {
						$big_data[$ts_counter]['data'][] = $point;
						$big_data[$ts_counter]['data'][] = $minv[$slot];
					}
					else {
						$big_data[$ts_counter]['data'][] = $minv[$slot];
						$big_data[$ts_counter]['data'][] = $point;
					}
				}
			}
			if ($io=="rw") {
				$ts_counter++;
				$big_data[$ts_counter]['data'] = $big_data_w;
			}
			// debug(count($big_data["ts{$xaxis}_".$ts_id_num]['data']), 'ts'.$ts_id_num);
			$ts_counter++;
			$fetchtime += microtime(true);
		}
	}

	//
	// EVENTS
	//
	$event = array('error', 'alarm', 'command', 'button');
	$show = array();
	foreach ($event as $e) {
		$show[$e] = (!SKIP_EVENT) || (isset($_REQUEST["show_$e"]));
	}
	if (isset($_REQUEST['debug'])) debug($show, 'show '.__LINE__);
	if (count($show) and (!empty($big_data[0]['data']))) {
		foreach ($big_data[0]['data'] as $d) {
			if (isset($d[1]) && ($d[1] !== NULL)) {
				$y = $d[1];
				break;
			}
		}
	}

	$big_data = array('ts'=>$big_data);

	//
	// extract ERRORs
	//
	if ($show['error']) {
		$messages = array();
		$ts_counter = 0;
		$data = array();
		foreach ($ts as $xaxis=>$ts_array) {
			if (empty($ts_array)) continue;
			$interval = $stop_timestamp[$xaxis-1] - strtotime($start[$xaxis-1]);
			$slot_maxmin = $interval*2/1000;
			foreach ($ts_array as $ts_num=>$ts_id_num) {
				list($att_conf_id,$element_index,$trash) = explode('[',trim($ts_id_num[0], ']').'[[',3);
				$res = mysqli_query($db, "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id=$att_conf_id AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id");
				$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
				list($dim, $type, $io) = explode('_', $row['data_type']);
				$table = sprintf("att_{$dim}_{$type}_{$io}");
				$col_name = 'error_desc';
				$orderby = $dim=='array'? "time,idx": "data_time";
				$filter = (strlen($_REQUEST["show_error"]) and ($_REQUEST["show_error"]!=='1'))? "AND $col_name LIKE ".quote_smart(strtr($_REQUEST["show_error"], array('*'=>'%'))): ""; 
				$query = "SELECT UNIX_TIMESTAMP(data_time) AS time, att_error_desc.$col_name FROM $table, att_error_desc WHERE {$table}.att_error_desc_id=att_error_desc.att_error_desc_id $filter AND att_conf_id=$att_conf_id AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";
				if (isset($_REQUEST['errdebug'])) echo "\n\n".__LINE__."\n$query;<br>\n";
				// $query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE $filter AND att_conf_id=$att_conf_id AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";
				if (isset($_REQUEST['errdebug'])) echo "\n\n".__LINE__."\n$query;<br>\n";
				if (isset($_REQUEST['debug'])) debug($query);
				$querytime -= microtime(true);
				$res = mysqli_query($db, $query);
				$querytime += microtime(true);
				$fetchtime -= microtime(true);
				if ($res) $samples += mysqli_num_rows($res);
				$sample = -1;
				if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
					if (($msg = array_search($row[$col_name], $messages)) === false) {
						$messages[] = $row[$col_name];
						$msg = count($messages) - 1;
					}
					$data[$row['time']*100000+$ts_counter] = array('x'=>$row['time']*1000, 'y'=>$y, 'message'=>$msg, 'ts'=>$ts_counter,'marker'=>array('symbol'=>"url($host/img/event_error.png)"));
				}
				$ts_counter++;
				$fetchtime += microtime(true);
			}
		}
		if (!empty($data)) {
			ksort($data);
			$big_data['event']['error']['message'] = $messages;
			$big_data['event']['error']['data'] = array_values($data);
		}
	}
	function get_error() {
		global $ts,$stop_timestamp,$stop,$start,$interval,$db,$y,$yerr,$host;
		$max_event_num = empty($_REQUEST['max_event_num'])? 1000: $_REQUEST['max_event_num']-0;
		$messages = $warnings = array();
		$ts_counter = 0;
		$data = array();
		foreach ($ts as $xaxis=>$ts_array) {
			if (empty($ts_array)) continue;
			$interval = $stop_timestamp[$xaxis-1] - strtotime($start[$xaxis-1]);
			$slot_maxmin = $interval*2/1000;
			foreach ($ts_array as $ts_num=>$ts_id_num) {
				list($att_conf_id,$element_index,$trash) = explode('[',trim($ts_id_num[0], ']').'[[',3);
				$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id=$att_conf_id AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
				$res = mysqli_query($db, $query);
				if (isset($_REQUEST['errdebug'])) echo "\n\n".__LINE__."\n$query;<br>\n";
				$r = mysqli_fetch_all($res, MYSQLI_ASSOC);
				$row = $r[0];
				if (isset($_REQUEST['errdebug'])) echo "\n\n".__LINE__."\nrow ".print_r($row,true)."<br>\n";
				list($dim, $type, $io) = explode('_', $row['data_type']);
				$table = sprintf("att_{$dim}_{$type}_{$io}");
				$col_name = 'error_desc';
				$orderby = $dim=='array'? "time,idx": "data_time";
				$filter = (strlen($_REQUEST["show_error"]) and ($_REQUEST["show_error"]!=='1'))? "AND $col_name LIKE ".quote_smart(strtr($_REQUEST["show_error"], array('*'=>'%'))): ""; 
				$query = "SELECT UNIX_TIMESTAMP(data_time) AS time, att_error_desc.$col_name FROM $table, att_error_desc WHERE {$table}.att_error_desc_id=att_error_desc.att_error_desc_id $filter AND att_conf_id=$att_conf_id AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby LIMIT $max_event_num";
				// $query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE $filter AND att_conf_id=$att_conf_id AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";
				if (isset($_REQUEST['errdebug'])) echo "\n\n".__LINE__."\n$query;<br>\n";
				if (isset($_REQUEST['debug'])) debug($query);
				$querytime -= microtime(true);
				$res = mysqli_query($db, $query);
				$querytime += microtime(true);
				$fetchtime -= microtime(true);
				$sample = -1;
				if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
					if (($msg = array_search($row[$col_name], $messages)) === false) {
						$messages[] = $row[$col_name];
						$msg = count($messages) - 1;
					}
					$maxevent = $row['time']*1000;
					$data[$row['time']*100000+$ts_counter] = array('x'=>$row['time']*1000, 'y'=>$yerr*0.99, 'message'=>$msg, 'ts'=>$ts_counter,'marker'=>array('symbol'=>"url($host/img/event_error.png)"));
				}
				if ($res && $max_event_num==mysqli_num_rows($res)) {
					$warnings[$ts_counter] = $maxevent;
				}
				$ts_counter++;
				$fetchtime += microtime(true);
			}
		}
		if (isset($_REQUEST['errdebug'])) echo "\n\n".__LINE__."\nrow ".print_r($data,true)."<br>\n";
		if (!empty($data)) {
			ksort($data);
			return array('message'=>$messages,'data'=>array_values($data), 'warning'=>$warnings);
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
		if ($fd = @fopen(LOG_REQUEST, 'a')) {
			$date = date("Y-m-d H:i:s");
			fwrite($fd, "$date $remote $forwarded $requests query: ".round($querytime,2)."[s] fetch: ".round($fetchtime,2)."[s] #samples: $samples\n");
			fclose($fd);
		}
	}


	//
	// extract ALARMs
	//
	if ($show['alarm']) {
		$data = array();
		$messages = array();
		$db = mysqli_connect(ALARM_HOST, ALARM_USER, ALARM_PASSWORD);
		mysqli_select_db($db, ALARM_DB);
		//$db = mysqli_connect(($_REQUEST['conf']=='fermi'? 'srv-db-srf': 'ecsproxy'), 'alarm-client', '');
		//mysqli_select_db($db, 'alarm');
		$stop_cond = strlen($stop_timestamp[0]) ? " AND alarms.time_sec < {$stop_timestamp[0]}": '';
		$col_name = 'description.name';
		$condition = quote_smart(strtr($_REQUEST["show_alarm"], array('*'=>'%')));
		$filter = (strlen($_REQUEST["show_alarm"]) and ($_REQUEST["show_alarm"]!=='1'))? "((description.name LIKE $condition) OR (description.msg LIKE $condition))": "NOT ISNULL($col_name)"; 
		$query = "SELECT alarms.time_sec*1000+ROUND(alarms.time_usec/1000) AS t,description.name,description.msg FROM alarms,description WHERE $filter AND alarms.id_description=description.id_description AND alarms.time_sec >= UNIX_TIMESTAMP('{$start[0]}')$stop_cond AND status='ALARM' AND ack='NACK' ORDER BY t";
		if (isset($_REQUEST['debug'])) debug($query);
		// $res = mysqli_query($db, $query);
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			$text = "{$row['name']}<br>{$row['msg']}";
			if (($msg = array_search($text, $messages)) === false) {
				$messages[] = $text;
				$msg = count($messages) - 1;
			}
			$data[$row['t']] = array('x'=>$row['t']-0, 'y'=>$y, 'message'=>$msg, 'marker'=>array('symbol'=>"url($host/img/event_alarm.png)"));
		}
		if (!empty($data)) {
			ksort($data);
			$big_data['event']['alarm']['message'] = $messages;
			$big_data['event']['alarm']['data'] = array_values($data);
		}
		// debug($query);debug($row);exit();
		/*
		$big_data['alarm']['message'][0] = 'Fault Conditioning Mod. 7';
		$big_data['alarm']['message'][1] = "Situazione anomala sul modulatore 3: trigger thyratron assente e klystron filament 100%. Se permane l'anomalia fra 15 minuti verra' spenta l'alta tensione e i filamenti klystron saranno posti al 80%";
		$big_data['alarm']['message'][2] = 'BC01: radiation alarm ';
		$big_data['alarm']['data'][0] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 4.1) * 1000, 'y'=>$y, 'message'=>0,'marker'=>array('symbol'=>"url($host/img/event_alarm.png)"));
		$big_data['alarm']['data'][1] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 2.8) * 1000, 'y'=>$y, 'message'=>1,'marker'=>array('symbol'=>"url($host/img/event_alarm.png)"));
		$big_data['alarm']['data'][2] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 2.1) * 1000, 'y'=>$y, 'message'=>2,'marker'=>array('symbol'=>"url($host/img/event_alarm.png)"));
		$big_data['alarm']['data'][3] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 1.2) * 1000, 'y'=>$y, 'message'=>1,'marker'=>array('symbol'=>"url($host/img/event_alarm.png)"));
		*/
	}
	/*
	if ($show['command']) {
		$big_data['command']['message'][0] = 'f/modulators/modulators=>ControlledAccess';
		$big_data['command']['message'][1] = "kg02/mod/hv=>Off";
		$big_data['command']['message'][2] = 'kg07/mod/modcond-kg07-01=>On';
		$big_data['command']['data'][0] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 7.1) * 1000, 'y'=>$y, 'message'=>0,'marker'=>array('symbol'=>"url($host/img/event_command.png)"));
		$big_data['command']['data'][1] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 3.8) * 1000, 'y'=>$y, 'message'=>1,'marker'=>array('symbol'=>"url($host/img/event_command.png)"));
		$big_data['command']['data'][2] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 2.5) * 1000, 'y'=>$y, 'message'=>2,'marker'=>array('symbol'=>"url($host/img/event_command.png)"));
		$big_data['command']['data'][3] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 1.05) * 1000, 'y'=>$y, 'message'=>1,'marker'=>array('symbol'=>"url($host/img/event_command.png)"));
	}
	if ($show['button']) {
		$big_data['button']['message'][0] = 'modmulti - SwitchToControlledAccess';
		$big_data['button']['message'][1] = "kg02 conditioning - Off";
		$big_data['button']['message'][2] = 'kg07 control - On';
		$big_data['button']['data'][0] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 7.1) * 1000, 'y'=>$y, 'message'=>0,'marker'=>array('symbol'=>"url($host/img/event_button.png)"));
		$big_data['button']['data'][1] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 3.8) * 1000, 'y'=>$y, 'message'=>1,'marker'=>array('symbol'=>"url($host/img/event_button.png)"));
		$big_data['button']['data'][2] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 2.5) * 1000, 'y'=>$y, 'message'=>2,'marker'=>array('symbol'=>"url($host/img/event_button.png)"));
		$big_data['button']['data'][3] = array('x'=>(strtotime($start[0]) + ($stop_timestamp[0] - strtotime($start[0])) / 1.05) * 1000, 'y'=>$y, 'message'=>1,'marker'=>array('symbol'=>"url($host/img/event_button.png)"));
	}
	*/

	if (isset($_REQUEST['dump'])) {exit(0);}
	if (isset($_REQUEST['debug'])) {debug($big_data, 'big_data');}
	if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
	echo json_encode($big_data, JSON_INVALID_UTF8_IGNORE);
?>
