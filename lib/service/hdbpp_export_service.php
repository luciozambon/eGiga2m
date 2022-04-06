<?php

	include './hdbpp_conf.php';
	$timezone = date_default_timezone_get();
	
	$host = 'http://'.$_SERVER["HTTP_HOST"];
	$uri = explode('?', $_SERVER["REQUEST_URI"]);
	$host .= strtr($uri[0], array('/lib/service/hdbpp_plot_service.php'=>''));

	// if (isset($_REQUEST['debug'])) file_put_contents('debug.txt', json_encode($_REQUEST));

	$state = array('ON','OFF','CLOSE','OPEN','INSERT','EXTRACT','MOVING','STANDBY','FAULT','INIT','RUNNING','ALARM','DISABLE','UNKNOWN');

	$pretimer = !isset($_REQUEST['no_pretimer']);
	$posttimer = !isset($_REQUEST['no_posttimer']);
	$nullValue = isset($_REQUEST['nullValue'])? strip_tags($_REQUEST['nullValue']): '';

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

	$timeformat = 'data_time AS';
	if (isset($_REQUEST['timeformat'])) {
		if ($_REQUEST['timeformat']=='noseconds') {
			$timeformat = "DATE_FORMAT(UNIX_TIMESTAMP(data_time),'%Y-%m-%d %H:%i') AS ";
		}
		else {
			$timeformat = "DATE_FORMAT(data_time,".quote_smart($_REQUEST['timeformat']).") AS ";
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
	// log request
	function log_request() {
		global $querytime;
		$requests = $sep = '';
		foreach ($_REQUEST as $key => $value ) {
			$requests .= $sep . $key . '=' . $value;
			$sep = '&';
		}
		$remote = $_SERVER['REMOTE_ADDR'];
		$forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR'])? $_SERVER['HTTP_X_FORWARDED_FOR']: 0;
		$fd = fopen(LOG_REQUEST, 'a');
		$date = date("Y-m-d H:i:s");
		fwrite($fd, "$date $remote $forwarded $requests query: ".round($querytime,2)."[s]\n");
		fclose($fd);
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

	// ----------------------------------------------------------------
	// emit matlab
	function emit_matlab_data($ts) {
		global $db, $start, $stop, $skipdomain;
		$data_type_result = array(
			"ro"=>"value_r AS val",
			"rw"=>"value_r AS val, value_w AS val_w",
			"wo"=>"value_w AS val"
		);
		include_once("../php2mat.php");
		$php2mat = new php2mat();
		if (!isset($_REQUEST['debug'])) $php2mat->php2mat5_head('eGiga2m.mat', "Created on: ".date("d-F-Y H:i:s"));
		$label_num = 0;
		$tsLabel = array();
		if (isset($_REQUEST['tsLabel'])) {$tsLabel = explode(';', strip_tags($_REQUEST['tsLabel']));}
		foreach ($ts as $xaxis=>$ts_array) {
			foreach ($ts_array as $ts_num=>$ts_id_num) {
				if (isset($_REQUEST['debug'])) debug($ts_id_num,'ts_id_num');
				$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id={$ts_id_num[0]} AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
				$res = mysqli_query($db, $query);
				$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
				if (isset($_REQUEST['debug'])) debug($row);
				list($dim, $type, $io) = explode('_', $row['data_type']);
				$table = sprintf("att_{$dim}_{$type}_{$io}");
				$col_name = $data_type_result[$io];
				$skipdomain['/'] = '_';
				$full_name = strtr(isset($tsLabel[$label_num])? $tsLabel[$label_num]: $row['att_name'], $skipdomain);
				$label_num++;
				$orderby = $dim=='array'? "data_time,idx": "data_time";
				$tm = "UNIX_TIMESTAMP(data_time) / 86400 + 719529 AS time"; // UNIX_TIMESTAMP(time) / 86400 + MATLAB:datenum('01/01/1970');";
				$query = "SELECT $tm FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";
				if (isset($_REQUEST['debug'])) debug($query);
				$res = mysqli_query($db, $query);
				$php2mat->php2mat5_var_init($full_name, $io=='rw'? 3: 2, mysqli_num_rows($res));
				while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
					$php2mat->php2mat5_var_addrow($row['time']);
				}
				$query = "SELECT $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";
				$res = mysqli_query($db, $query);
				while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
					$php2mat->php2mat5_var_addrow($row['val']);
				}
				if ($io=='rw') {
					mysqli_data_seek($res, 0);
					while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
						$php2mat->php2mat5_var_addrow($row['val_w']);
					}
				}
			}
		}
		exit(0);
	}

	$output_buffer = '';
	// ----------------------------------------------------------------
	// send message to output either buffered or not
	function emit_output($str) {
		global $output_buffer;
		if (isset($_REQUEST['buffered_output'])) $output_buffer .= $str; else echo $str;
		if (isset($_REQUEST['debug'])) {echo "\nemit_output($str)\n";}
	}

	// ----------------------------------------------------------------
	// eval linear interpolation
	// see https://en.wikipedia.org/wiki/Linear_interpolation
	function linear_interpolation($x0, $y0, $x1, $y1, $x) {
		return $y0 + ($x - $x0) * ($y1 - $y0) / ($x1 - $x0);
	}

	// ----------------------------------------------------------------
	// calculate the median value
	function calculate_median($arr) {
		sort($arr);
		$count = count($arr); //total numbers in array
		$middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
		if ($count % 2) { // odd number, middle is the median
			return $arr[$middleval];
		} 
		else { // even number, calculate avg of 2 medians
			return (($arr[$middleval] + $arr[$middleval+1])/2);
		}
	}

	// ----------------------------------------------------------------
	// emit a row
	function emit_row($data_type, $t, $v, $nl, $separator, $format) {
		global $y;
		$empty_val = $data_type=='itx'? "NAN": NULL;
		if ($data_type=='xls') {
			$objPHPExcel = $nl;
			xls_date($objPHPExcel, $t, 'A'.$y);
			$objPHPExcel->getActiveSheet()->fromArray($v, NULL, 'B'.$y++);
		}
		else {
			emit_output("{$nl}".($data_type=='itx'? $t+2082844800: date("Y-m-d H:i:s", $t)));
			foreach ($v as $val) {
				$vf = sprintf($format, $val);
				if ($_REQUEST['decimal']==',') $vf = strtr($vf, array('.'=>','));
				emit_output($separator.(is_null($val)? $empty_val: $vf));
			}
		}
	}

	// ----------------------------------------------------------------
	// emit data with linear filling of missing values
	function emit_data_foh($res, $data_type, $memory_threshold, $max_id, $nl, $separator, $format, $old_data, $old_time) {
		global $db, $pretimer, $start, $stop, $output_buffer, $start_timestamp, $y;
		if ($data_type=='xls') $objPHPExcel = $nl;
		$empty_val = $data_type=='itx'? "NAN": NULL;
		$time_buffer = array();
		$data_buffer = array(array_fill(0, $max_id+1, null));
		$y = 2;
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			if (empty($data_buffer[0][$row['ts_index']])) {
				$data_buffer[0][$row['ts_index']] = linear_interpolation($old_time[$row['ts_index']], $old_data[$row['ts_index']], $row['timestamp'], $row['val']-0, $start_timestamp[0]);
				$time_buffer[0] = $start_timestamp[0];
				if (isset($_REQUEST['debug'])) echo "<br>\nlinear_interpolation__({$old_time[$row['ts_index']]}, {$old_data[$row['ts_index']]}, {$row['timestamp']}, {$row['val']}-0, {$start_timestamp[0]});<br>\n";
				$data_index = 0;
			}
			else {
				$data_index = count($data_buffer)-1;
				while (is_null($data_buffer[$data_index][$row['ts_index']])) $data_index--;
			}
			for ($i=$data_index+1; $i<count($data_buffer); $i++) {
				$data_buffer[$i][$row['ts_index']] = linear_interpolation($time_buffer[$data_index], $data_buffer[$data_index][$row['ts_index']], $row['timestamp'], $row['val']-0, $time_buffer[$i]);
				if (isset($_REQUEST['debug'])) echo "<br>\nlinear_interpolation({$time_buffer[$data_index]}, {$data_buffer[$data_index][$row['ts_index']]}, {$row['timestamp']}, {$row['val']}-0, {$time_buffer[$i]});<br>\n"; 
			}
			if ($time_buffer[count($time_buffer)-1] == $row['timestamp']) {
				$new_data = array_pop($data_buffer);
			}
			else {
				$new_data = array_fill(0, $max_id+1, null);
				$time_buffer[] = $row['timestamp'];
			}
			$new_data[$row['ts_index']] = $row['val']-0;
			$data_buffer[] = $new_data;
			// if (isset($_REQUEST['debug'])) {debug($row, 'row'); debug($data_buffer, 'data_buffer__');debug($time_buffer, 'time_buffer__'); continue;}
			while ((count($data_buffer)>1) and (!in_array(null, $data_buffer[1], true))) {
				if (isset($_REQUEST['debug'])) debug($data_buffer, 'data_buffer');
				$t = array_shift($time_buffer);
				$v = array_shift($data_buffer);
				emit_row($data_type, $t, $v, $nl, $separator, $format);
			}
			if (isset($_REQUEST['debug'])) {debug($data_buffer, 'data_buffer__');debug($time_buffer, 'time_buffer__');}
		}
		while (count($data_buffer)>1) {
			if (isset($_REQUEST['debug'])) debug($data_buffer, 'data_buffer');
			$t = array_shift($time_buffer);
			$v = array_shift($data_buffer);
			emit_row($data_type, $t, $v, $nl, $separator, $format);
		}
		$t = array_shift($time_buffer);
		$v = array_shift($data_buffer);
		emit_row($data_type, $t, $v, $nl, $separator, $format);
		// is there a tail to append?
		if ($data_type=='itx') emit_output("{$nl}END{$nl}"); else if ($data_type=='csv') emit_output($nl); else if ($data_type=='xls') emit_tail_xls($objPHPExcel);
		if (isset($_REQUEST['debug'])) {echo "extraction time: ".(microtime(TRUE)-$t0)."<br>\n"; echo "memory_usage: ".memory_get_usage().", memory_limit: $memory_limit<br>\n";}
		if (isset($_REQUEST['buffered_output'])) {if (!isset($_REQUEST['debug'])) header("Content-Length: ".strlen($output_buffer)); echo $output_buffer;}
		if (defined('LOG_REQUEST')) {
			log_request();
		}
		exit(0);
	}

	// ----------------------------------------------------------------
	// emit decimated data in text formats
	function emit_data_decimated($res, $data_type, $memory_threshold, $max_id, $nl, $separator, $format) {
		global $db, $pretimer, $start, $stop, $output_buffer, $start_timestamp, $stop_timestamp, $y;
		if ($data_type=='xls') $objPHPExcel = $nl;
		$start_t = $start_timestamp[0];
		$stop_t = $stop_timestamp[0];
		$empty_val = $data_type=='itx'? "NAN": NULL;
		$decimation_samples = $_REQUEST['decimation']-0>1? $_REQUEST['decimation']: 1000;
		$decimation_maxmin = strpos($_REQUEST['decimation'], 'maxmin')!==false;
		$decimation_avg = strpos($_REQUEST['decimation'], 'avg')!==false;
		$decimation_mean = strpos($_REQUEST['decimation'], 'mean')!==false;
		$decimation_samples_per_period = ($decimation_maxmin? 2: 0) + ($decimation_avg? 1: 0) + ($decimation_mean? 1: 0);
		// if no decimation method is requested use maxmin as default
		if ($decimation_samples_per_period==0) {$decimation_maxmin = true; $decimation_samples_per_period = 2;}
		$decimation_period = ($stop_timestamp[0]-$start_timestamp[0]) / floor($decimation_samples / $decimation_samples_per_period);
		$decimation_max_buffer = $decimation_min_buffer = array_fill(0, $max_id+1, null);
		$decimation_avg_buffer = $decimation_count_buffer = array_fill(0, $max_id+1, 0);
		$decimation_mean_buffer = array_fill(0, $max_id+1, array());
		if (isset($_REQUEST['debug'])) {debug($decimation_max_buffer); debug($decimation_avg_buffer);}
		$decimation_period_index = 1;
		$parts = $decimation_period/($decimation_samples_per_period+1);
		$y = 2;
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			if (isset($_REQUEST['debug'])) {debug($row, 'row');}
			if (memory_get_usage()>$memory_threshold) die("Fatal ERROR, too much memory used, num_rows: ".mysqli_num_rows($res).", memory_usage: ".memory_get_usage()."<br>\n");
			if ($start_t+$decimation_period_index*$decimation_period < $row['timestamp']) {
				$i = $decimation_samples_per_period;
				if ($decimation_maxmin) {
					$t = $start_t + $decimation_period_index*$decimation_period - $i*$parts; $i--;
					emit_row($data_type, $t, $decimation_min_buffer, $nl, $separator, $format);
					if (isset($_REQUEST['debug'])) {debug($decimation_max_buffer);}
				}
				if ($decimation_mean) {
					$t = $start_t + $decimation_period_index*$decimation_period - $i*$parts; $i--;
					$v = array(); foreach ($decimation_mean_buffer as $val) {$v[] = calculate_median($val);}
					emit_row($data_type, $t, $v, $nl, $separator, $format);
					$decimation_mean_buffer = array_fill(0, $max_id+1, array());
				}
				if ($decimation_avg) {
					$t = $start_t + $decimation_period_index*$decimation_period - $i*$parts; $i--;
					$v = array(); foreach ($decimation_avg_buffer as $i=>$val) {$v[] = $decimation_count_buffer[$i]>0? $val/$decimation_count_buffer[$i]: null;}
					emit_row($data_type, $t, $v, $nl, $separator, $format);
					$decimation_avg_buffer = $decimation_count_buffer = array_fill(0, $max_id+1, 0);
				}
				if ($decimation_maxmin) {
					$t = $start_t + $decimation_period_index*$decimation_period - $i*$parts;
					emit_row($data_type, $t, $decimation_max_buffer, $nl, $separator, $format);
					$decimation_max_buffer = $decimation_min_buffer = array_fill(0, $max_id+1, null);
				}
				$decimation_period_index++;
			}
			if ($decimation_maxmin) {
				$decimation_max_buffer[$row['ts_index']] = max($row['val']-0, $decimation_max_buffer[$row['ts_index']]);
				$decimation_min_buffer[$row['ts_index']] = is_null($decimation_min_buffer[$row['ts_index']])? $row['val']-0: min($row['val']-0, $decimation_min_buffer[$row['ts_index']]);
				if (isset($_REQUEST['debug'])) {debug($row,'row');debug($decimation_min_buffer);}
			}
			if ($decimation_avg) {
				if (empty($row['val'])) continue;
				$decimation_avg_buffer[$row['ts_index']] += $row['val']-0;
				$decimation_count_buffer[$row['ts_index']]++;
			}
			if ($decimation_mean) {
				if (empty($row['val'])) continue;
				$decimation_mean_buffer[$row['ts_index']][] = $row['val']-0;
			}
		}
		$i = $decimation_samples_per_period;
		if ($decimation_maxmin) {
			$t = $start_t + $decimation_period_index*$decimation_period - $i*$parts; $i--;
			emit_row($data_type, $t, $decimation_min_buffer, $nl, $separator, $format);
		}
		if ($decimation_mean) {
			$t = $start_t + $decimation_period_index*$decimation_period - $i*$parts; $i--;
			$v = array(); foreach ($decimation_mean_buffer as $val) {$v[] = calculate_median($val);}
			emit_row($data_type, $t, $v, $nl, $separator, $format);
		}
		if ($decimation_avg) {
			$t = $start_t + $decimation_period_index*$decimation_period - $i*$parts; $i--;
			$v = array(); foreach ($decimation_avg_buffer as $i=>$val) {$v[] = $decimation_count_buffer[$i]>0? $val/$decimation_count_buffer[$i]: null;}
			emit_row($data_type, $t, $v, $nl, $separator, $format);
		}
		if ($decimation_maxmin) {
			$t = $start_t + $decimation_period_index*$decimation_period - $i*$parts;
			emit_row($data_type, $t, $decimation_max_buffer, $nl, $separator, $format);
		}
		if ($data_type=='itx') emit_output("{$nl}END{$nl}"); else if ($data_type=='csv') emit_output($nl); else if ($data_type=='xls') emit_tail_xls($objPHPExcel);
		if (isset($_REQUEST['debug'])) {echo "extraction time: ".(microtime(TRUE)-$t0)."<br>\n"; echo "memory_usage: ".memory_get_usage().", memory_limit: $memory_limit<br>\n";}
		if (isset($_REQUEST['buffered_output'])) {if (!isset($_REQUEST['debug'])) header("Content-Length: ".strlen($output_buffer)); echo $output_buffer;}
		if (defined('LOG_REQUEST')) {
			log_request();
		}
		exit(0);
	}

	// ----------------------------------------------------------------
	// format a date for PHPExcel
	function xls_date($objPHPExcel, $timestamp, $cell) {
		global $output_buffer;
		$objPHPExcel->getActiveSheet()->setCellValue($cell, PHPExcel_Shared_Date::PHPToExcel($timestamp));
		$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DATETIME);
	}

	// ----------------------------------------------------------------
	// emit tail of xls data
	function emit_tail_xls($objPHPExcel) {
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="eGiga2m.xls"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
		header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
		header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
		header ('Pragma: public'); // HTTP/1.0
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save('php://output');
	}

	// ----------------------------------------------------------------
	// emit igor statistics
	function emit_data_xls() {
		global $ts, $db, $pretimer, $start, $stop, $output_buffer, $skipdomain, $data_type_result;
		if (isset($_REQUEST['debug'])) $t0 = microtime(TRUE);
		$memory_limit = ini_get('memory_limit');
		if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
			if ($matches[2] == 'M') {
				$memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
			} else if ($matches[2] == 'K') {
				$memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
			}
		}
		if (isset($_REQUEST['debug'])) debug($memory_limit);
		$format = isset($_REQUEST['numberformat']) && strlen($_REQUEST['numberformat']<10)? $_REQUEST['numberformat']: '%8.5e';
		$time = 'UNIX_TIMESTAMP(data_time) AS time';
		require_once strtr(dirname(__FILE__),array('lib/service'=>'lib/PHPExcel')).'/PHPExcel.php';
		$objPHPExcel = new PHPExcel();
		$objPHPExcel->getProperties()->setCreator("eGiga2m")->setLastModifiedBy("eGiga2m")->setTitle("eGiga2m")->setSubject("eGiga2m")->setDescription("Document for Office 2007 XLSX, generated using PHPExcel classes.")->setKeywords("office 2007 openxml php eGiga2m")->setCategory("eGiga2m");
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', 'time');
		$xaxis = 1;
		$ts_array  = $ts[$xaxis];
		if (empty($ts_array)) return;
		$ts_empty = array();
		foreach ($ts_array as $ts_num=>$t) {
			$ts_empty[$ts_num] = ' ';
		}
		$query_array = $old_data = $old_time = array();
		$label_num = 0;
		$tsLabel = array();
		if (isset($_REQUEST['tsLabel'])) {$tsLabel = explode(';', strip_tags($_REQUEST['tsLabel']));}
		foreach ($ts_array as $ts_num=>$ts_id_num) {
			if (isset($_REQUEST['debug'])) debug($ts_id_num, 'ts_id_num');
			// if (isset($_REQUEST['debug'])) die("memory_usage: ".memory_get_usage());
			$big_data_w = array();
			$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id={$ts_id_num[0]} AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
			if (isset($_REQUEST['debug'])) {debug($query, 'query');}
			$res = mysqli_query($db, $query);
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			list($dim, $type, $io) = explode('_', $row['data_type']);
			$table = sprintf("att_{$dim}_{$type}_{$io}");
			$col_name = $data_type_result[$io];
			$full_name = strtr(isset($tsLabel[$label_num])? $tsLabel[$label_num]: $row['att_name'], $skipdomain);
			$label_num++;
			$dataxls[] = $full_name;
			$orderby = $dim=='array'? "time, ts_index,idx": "data_time, ts_index";
			$query_array[$ts_num] = "SELECT $time, UNIX_TIMESTAMP(data_time) AS t, data_time, $col_name, $ts_num AS ts_index FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]}";
			$old_data[$ts_num] = NULL;
			if (isset($_REQUEST['zoh']) or isset($_REQUEST['foh'])) {
				$query = "SELECT $time, UNIX_TIMESTAMP(data_time) AS t, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time <= '{$start[$xaxis-1]}' ORDER BY data_time DESC LIMIT 1";
				$res = mysqli_query($db, $query);
				if (isset($_REQUEST['debug'])) debug($query, 'zoh_query');
				$zrow = mysqli_fetch_array($res, MYSQLI_ASSOC);
				$old_data[$ts_num] = sprintf($format, $zrow['val']-0);
				$old_time[$ts_num] = $zrow['t']-0;
				if (isset($_REQUEST['debug'])) {debug($zrow, 'row');debug($ts_num, 'ts_num');}
			}
			$last_id = $ts_num;
		}
		$objPHPExcel->getActiveSheet()->fromArray($dataxls,NULL, 'B1');		
		if (isset($_REQUEST['debug'])) echo "pre-execution time: ".(microtime(TRUE)-$t0)."<br>\n";
		$max_id = $last_id;
		for ($i=0; $i<=$max_id; $i++) {$dataxls[$i]=$old_data[$i];}
		// UNION will remove duplicates. UNION ALL does not.
		$query = implode(' UNION ', $query_array)." ORDER BY $orderby";
		if (isset($_REQUEST['debug'])) {debug($query, 'big query'); $t0 = microtime(TRUE);}
		$res = mysqli_query($db, $query); if (!$res) die($query.'; '.mysqli_error($db));
		if (isset($_REQUEST['debug'])) {echo "execution time: ".(microtime(TRUE)-$t0)."<br>\n"; echo "num_rows: ".mysqli_num_rows($res)."<br>\n"; $t0 = microtime(TRUE);}
		$memory_threshold = $memory_limit*(isset($_REQUEST['buffered_output'])? 0.499: 0.999);
		$y = 2;
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			if (memory_get_usage() > $memory_threshold) die("Fatal ERROR, too much memory used, num_rows: ".mysqli_num_rows($res).", memory_usage: ".memory_get_usage()."<br>\n");
			if ($old_time[$row['ts_index']]==$row['t']) {
				if ($last_id==$row['ts_index']) continue; 
				for ($i=$last_id+1; $i<$row['ts_index']; $i++) {$dataxls[$i]=$old_data[$i];} 
				$dataxls[$row['ts_index']]=sprintf($format, $row['val']-0);
			}
			else {
				if (isset($_REQUEST['debug'])) {debug($row, 'row');debug($old_time, 'old_time');debug($old_data, 'old_data');debug($dataxls, 'dataxls');}
				for ($i=$last_id+1; $i<=$max_id; $i++) {$dataxls[$i]=$old_data[$i];} 
				xls_date($objPHPExcel, $row['time'], 'A'.$y); 
				$dataxls[$row['ts_index']]=sprintf($format, $row['val']-0); 
				$objPHPExcel->getActiveSheet()->fromArray($dataxls, NULL, 'B'.$y++);
				for ($i=0; $i<=$max_id; $i++) {$dataxls[$i]=$old_data[$i];}
				if (isset($_REQUEST['debug'])) {debug($old_data, 'old_data');debug($dataxls, 'dataxls');}
				for ($i=0; $i<$row['ts_index']; $i++) {$dataxls[$i]=$old_data[$i];} 
				if (isset($_REQUEST['zoh'])) $dataxls[$row['ts_index']]=sprintf($format, $row['val']-0);
			}
			$old_time[$row['ts_index']] = $row['t'];
			$old_data[$row['ts_index']] = isset($_REQUEST['zoh'])? sprintf($format, $row['val']-0): NULL;
			$last_id = $row['ts_index'];
		}
		for ($i=$last_id+1; $i<=$max_id; $i++) {$dataxls[$i]=$old_data[$i];} 
		if (isset($_REQUEST['debug'])) exit;
		emit_tail_xls($objPHPExcel);
		exit;
	}

	// ----------------------------------------------------------------
	// emit igor,csv,matlab statistics
	function emit_data($data_type) {
		global $ts, $db, $pretimer, $start, $stop, $output_buffer, $skipdomain, $data_type_result, $nullValue, $timeformat;
		if (isset($_REQUEST['debug'])) $t0 = microtime(TRUE);
		$memory_limit = ini_get('memory_limit');
		$filename = isset($_REQUEST['filename'])? $_REQUEST['filename']: 'egiga2m.'.$_REQUEST['format'];
		if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
			if ($matches[2] == 'M') {
				$memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
			} else if ($matches[2] == 'K') {
				$memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
			}
		}
		if (isset($_REQUEST['debug'])) debug($memory_limit);
		$format = isset($_REQUEST['numberformat']) && strlen($_REQUEST['numberformat']<10)? $_REQUEST['numberformat']: '%8.5e';
		$time = "UNIX_TIMESTAMP(data_time) AS timestamp, {$timeformat} time, data_time";
		if ($data_type=='itx') {
			$nl = "\r\n";
			$separator = "\t";
			if (!isset($_REQUEST['debug'])){
				header("Content-Disposition: attachment; filename=eGiga2m.itx");
				header("Content-Type: application/x-itx");
			}
			emit_output("IGOR{$nl}WAVES/D timestamp");
		}
		if ($data_type=='csv') {
			$nl = "\n";
			$separator = isset($_REQUEST['separator'])? $_REQUEST['separator']: ";";
			if (!isset($_REQUEST['debug'])){
				header("Content-Disposition: attachment; filename=$filename");
				header("Content-Type: application/csv");
			}
			emit_output("timestamp");
		}
		if ($data_type=='mat') {
			emit_matlab_data($ts);
		}
		$xaxis = 1;
		$ts_array  = $ts[$xaxis];
		if (empty($ts_array)) return;
		$ts_empty = array();
		foreach ($ts_array as $ts_num=>$t) {
			$ts_empty[$ts_num] = ' ';
		}
		$query_array = $old_data = $old_time = array();
		$label_num = 0;
		$tsLabel = array();
		if (isset($_REQUEST['tsLabel'])) {$tsLabel = explode(';', strip_tags($_REQUEST['tsLabel']));}
		foreach ($ts_array as $ts_num=>$ts_id_num) {
			if (isset($_REQUEST['debug'])) debug($ts_id_num, 'ts_id_num');
			$big_data_w = array();
			$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id={$ts_id_num[0]} AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
			if (isset($_REQUEST['debug'])) {debug($query, 'query');}
			$res = mysqli_query($db, $query);
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			list($dim, $type, $io) = explode('_', $row['data_type']);
			$table = sprintf("att_{$dim}_{$type}_{$io}");
			$col_name = $data_type_result[$io];
			$full_name = strtr(isset($tsLabel[$label_num])? $tsLabel[$label_num]: $row['att_name'], $skipdomain);
			$label_num++;
			if ($data_type=='itx') {
				// extract last part of naming in order to avoid "name or string too long" error
				$name_array = explode('/', $full_name);
				$name = array_pop($name_array);
				$n = array_pop($name_array);
				while (strlen($n) and (strlen($name)+strlen($n)<31)) {
					$name = $n.'_'.$name;
					$n = array_pop($name_array);
				}
				if (isset($_REQUEST['debug'])) debug($name);
				emit_output($separator.strtr($name, array("/"=>"_", "."=>"_", " "=>"_", "["=>"_", "]"=>"_"))); 
			}
			else if ($data_type=='csv') {
				emit_output($separator.$full_name);
			}
			$orderby = $dim=='array'? "time, ts_index,idx": "data_time, ts_index";
			$query_array[$ts_num] = "SELECT $time, $col_name, $ts_num AS ts_index FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]}";
			$old_data[$ts_num] = ($data_type=='itx')? "NAN": NULL;
			if (isset($_REQUEST['zoh']) or isset($_REQUEST['foh'])) {
				$query = "SELECT $time, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time <= '{$start[$xaxis-1]}' ORDER BY data_time DESC LIMIT 1";
				$res = mysqli_query($db, $query);
				if (isset($_REQUEST['debug'])) debug($query, 'zoh_query');
				$zrow = mysqli_fetch_array($res, MYSQLI_ASSOC);
				$old_data[$ts_num] = sprintf($format, $zrow['val']-0);
				$old_time[$ts_num] = $zrow['t']-0;
				if (isset($_REQUEST['debug'])) {debug($zrow, 'row');debug($ts_num, 'ts_num');}
			}
			$last_id = $ts_num;
		}
		if ($data_type=='itx') {
			emit_output("{$nl}BEGIN"); 
		}
		if (isset($_REQUEST['debug'])) echo "pre-execution time: ".(microtime(TRUE)-$t0)."<br>\n";
		$max_id = $last_id;
		for ($i=0; $i<=$max_id; $i++) {$dataxls[$i]=$old_data[$i];}
		// UNION will remove duplicates. UNION ALL does not.
		$querybase = implode(' UNION ', $query_array)." ORDER BY $orderby";
		$recordsPerIteration = 50000;
		$start = 0;
		for (;;) {
			$query = "$querybase LIMIT $start , $recordsPerIteration";
			if (isset($_REQUEST['debug'])) {debug($query, 'big query'); $t0 = microtime(TRUE);}
			$res = mysqli_query($db, $query); if (!$res) die($query.'; '.mysqli_error($db));
			if (isset($_REQUEST['debug'])) {echo "execution time: ".(microtime(TRUE)-$t0)."<br>\n"; echo "num_rows: ".mysqli_num_rows($res)."<br>\n"; $t0 = microtime(TRUE);}
			$memory_threshold = $memory_limit*(isset($_REQUEST['buffered_output'])? 0.499: 0.999);
			$y = 2;
			if (isset($_REQUEST['decimation'])) {
				emit_data_decimated($res, $data_type, $memory_threshold, $max_id, $nl, $separator, $format);
			}
			if (isset($_REQUEST['foh'])) {
				emit_data_foh($res, $data_type, $memory_threshold, $max_id, $nl, $separator, $format, $old_data, $old_time);
			}
			$r = mysqli_fetch_all($res, MYSQLI_ASSOC);
			foreach($r as $row) {
				if (memory_get_usage() > $memory_threshold) {
					if (isset($_REQUEST['ignore_error'])) {unset($row); break;}
					else die("Fatal ERROR, too much memory used, num_rows: ".mysqli_num_rows($res).", memory_usage: ".memory_get_usage()."<br>\n");
				}
				$vf = sprintf($format, $row['val']-0);
				if ($_REQUEST['decimal']==',') $vf = strtr($vf, array('.'=>','));
				if ($old_time[$row['ts_index']]==$row['timestamp']) {
					if ($last_id==$row['ts_index']) continue; 
					for ($i=$last_id+1; $i<$row['ts_index']; $i++) {emit_output($separator.$old_data[$i]);}
					if ($last_id<$max_id) emit_output($separator.(is_numeric($row['val'])? $vf: $nullValue));
				}
				else {
					for ($i=$last_id+1; $i<=$max_id; $i++) {emit_output($separator.$old_data[$i]);}
					$timef = isset($_REQUEST['timestamp'])? strtotime($row['time']): $row['time'];
					emit_output("{$nl}".($data_type=='itx'? $row['timestamp']+2082844800: $timef));
					for ($i=0; $i<$row['ts_index']; $i++) {emit_output($separator.$old_data[$i]);} 
					// if (isset($_REQUEST['debug'])) {debug($row, 'row'); debug(is_null($row['val'])); debug(is_numeric($row['val']));}
					emit_output($separator.(is_numeric($row['val'])? $vf: $nullValue));
				}
				$old_time[$row['ts_index']] = $row['timestamp'];
				$old_data[$row['ts_index']] = isset($_REQUEST['zoh'])? $vf: ($data_type=='itx'? "NAN": NULL);
				$last_id = $row['ts_index'];
			}
			for ($i=$last_id+1; $i<=$max_id; $i++) {emit_output($separator.$old_data[$i]);} 
			if (mysqli_num_rows($res)<$recordsPerIteration) break;
			$GLOBALS['res'] = null;
			$GLOBALS['r'] = null;
			$start += $recordsPerIteration;
		}
		for ($i=$last_id+1; $i<=$max_id; $i++) {emit_output($separator.$old_data[$i]);} 
		if ($data_type=='itx') emit_output("{$nl}END{$nl}"); else if ($data_type=='csv') emit_output($nl);
		if (isset($_REQUEST['debug'])) {echo "extraction time: ".(microtime(TRUE)-$t0)."<br>\n"; echo "memory_usage: ".memory_get_usage().", memory_limit: $memory_limit<br>\n";}
		if (isset($_REQUEST['buffered_output'])) {if (!isset($_REQUEST['debug'])) header("Content-Length: ".strlen($output_buffer)); echo $output_buffer;}
		exit(0);
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
	
	$mat = $xls = $xls_new = $csv = $igor = false;
	$merged_var = true;
	if (isset($_REQUEST['format'])) {
		if ($_REQUEST['format']=='matlab') {
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
			$separator = isset($_REQUEST['separator'])? $_REQUEST['separator']: ';';
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
		$stop[$k] = '';
		$stop_timestamp[$k] = time();
	}
	if (isset($_REQUEST["stop"]) and strlen($_REQUEST["stop"])) {
		$stop = explode(';', $_REQUEST['stop']);
		foreach ($stop as $k=>$val) {
			$time = parse_time($val);
			$stop[$k] = strlen($val)? " AND data_time < '$time'": '';
			$stop_timestamp[$k] = strlen($val)? strtotime($time): time();
		}
	}
	$start_timestamp[0] = strtotime($start[0]);

	$ts_array = explode(';', $_REQUEST["ts"]);
	$ts = array(1=>array(),2=>array(),3=>array(),4=>array(),5=>array(),6=>array(),7=>array(),8=>array(),9=>array(),10=>array());
	foreach ($ts_array as $ts_element) {
		$t = explode(',', $ts_element);
		$x = isset($t[1])? $t[1]: 1;
		$y = isset($t[2])? $t[2]: 1;
		$ts[$x][] = array($t[0], $y);
	}

	$data_type_result = array(
		"ro"=>"value_r AS val",
		// "rw"=>"value_r AS val, value_w AS val_w",
		"rw"=>"value_r AS val",
		"wo"=>"value_w AS val"
	);
	$timezone_offset = 0;
	$big_data = array();
	$ts_counter = 0;

	if ($_REQUEST['format']=='igor') {
		emit_data('itx');
	}
	if ($_REQUEST['format']=='csv') {
		emit_data('csv');
	}
	if ($_REQUEST['format']=='mat') {
		emit_data('mat');
	}
	if ($_REQUEST['format']=='xlsx') {
		emit_data_xls();
	}
	if (isset($_REQUEST['debug'])) debug($merged_var, 'merged_var');

	$type = 'num';
	$csv_header = $data = array();
	foreach ($ts as $xaxis=>$ts_array) {
	  if (!$merged_var) {
		foreach ($ts_array as $ts_num=>$ts_id_num) {
			$big_data_w = array();
			$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id={$ts_id_num[0]} AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
			$res = mysqli_query($db, $query);
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			list($dim, $type, $io) = explode('_', $row['data_type']);
			$table = sprintf("att_{$dim}_{$type}_{$io}");
			$col_name = $data_type_result[$io];
			$full_name = strtr($row['att_name'], $skipdomain);
			$orderby = $dim=='array'? "time,idx": "data_time";
			$tm = "UNIX_TIMESTAMP(data_time) AS time";
			if ($mat) $tm = "UNIX_TIMESTAMP(data_time) / 86400 + 719519 AS time"; // UNIX_TIMESTAMP(time) / 86400 + datenum('01/01/1970');";
			$query = "SELECT $tm, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";
			// $query = "SELECT data_time AS time, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";

			if (isset($_REQUEST['debug'])) debug($query); // exit(0);
			$res = mysqli_query($db, $query);
			$time = $val = array();
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$time[] = $row['time'];
				$val[] = is_null($row['val'])? $row['val']: $row['val']-0;
			}
			$big_data[strtr($full_name,array('/'=>'_','-'=>'_'))] = array($time, $val);
			$ts_counter++;
		}
		if (isset($_REQUEST['debug'])) {
			debug($big_data);
			exit();
		}
	  }
	  else {
		if (empty($ts_array)) continue;
		foreach($ts_array as $k=>$t) {
			$ts_empty[$k] = ' ';
		}
		$old_data = $old_time = $ts_empty;
		foreach ($ts_array as $ts_num=>$ts_id_num) {
			$big_data_w = array();
			$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_conf_id={$ts_id_num[0]} AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id";
			$res = mysqli_query($db, $query);
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			if (isset($_REQUEST['debug'])) debug($row);
			list($dim, $type, $io) = explode('_', $row['data_type']);
			$table = sprintf("att_{$dim}_{$type}_{$io}");
			$col_name = $data_type_result[$io];
			if (isset($_REQUEST['debug'])) debug($query);
			if (isset($_REQUEST['zoh']) or isset($_REQUEST['foh'])) {
				$query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time <= '{$start[$xaxis-1]}' ORDER BY data_time DESC LIMIT 1";
				$res = mysqli_query($db, $query);
				if (isset($_REQUEST['debug'])) debug($query);
				$zrow = mysqli_fetch_array($res, MYSQLI_ASSOC);
				$old_data[$ts_num] = $zrow['val']-0;
				$old_time[$ts_num] = $zrow['time']-0;
				if (isset($_REQUEST['debug'])) {debug($zrow, 'row');debug($ts_num, 'ts_num');}
			}
			if (isset($_REQUEST['foh']) and (!empty($stop[$xaxis-1]))) {
				$query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} {$stop[$xaxis-1]} ORDER BY data_time LIMIT 1";
				$res = mysqli_query($db, $query);
				if (isset($_REQUEST['debug'])) debug($query);
				$zrow = mysqli_fetch_array($res, MYSQLI_ASSOC);
				$last_data[$ts_num] = $zrow['val']-0;
				$last_time[$ts_num] = $zrow['time']-0;
				if (isset($_REQUEST['debug'])) {debug($zrow, 'row');debug($ts_num, 'ts_num');}
			}
			$big_data[$ts_counter]['ts_id'] = $ts_id_num[0];
			$big_data[$ts_counter]['label'] = strtr($row['att_name'], $skipdomain);
			$big_data[$ts_counter]['xaxis'] = $xaxis;
			$big_data[$ts_counter]['yaxis'] = $ts_id_num[1];
			if ($xls) $myxls->Data[0][$ts_counter+1] = strtr($row['att_name'], $skipdomain);
			if ($xls_new) $myxls->setActiveSheetIndex(0)->setCellValue($cellColumn[$ts_counter+1].'1', strtr($row['att_name'], $skipdomain));
			if ($csv or $igor) $csv_header[$ts_counter+1] = strtr($row['att_name'], $skipdomain);
			$ts_map[$ts_id_num[0]] = $ts_counter;
			if ($row['data_type']==19) $big_data[$ts_counter]['categories'] = $state;
			if ($io=="rw") {
				$big_data[$ts_counter+1]['ts_id'] = $ts_id_num[0];
				$big_data[$ts_counter+1]['label'] = strtr($row['att_name'], $skipdomain).'_w';
				$big_data[$ts_counter]['label'] = strtr($row['att_name'], $skipdomain).'_r';
				$big_data[$ts_counter+1]['xaxis'] = $xaxis;
				$big_data[$ts_counter+1]['yaxis'] = $ts_id_num[1];
			}
			$orderby = "time";
			$dim = $row['data_format'];
			// $query = "SELECT UNIX_TIMESTAMP(data_time) AS time, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";
			$query = "SELECT data_time AS time, $col_name FROM $table WHERE att_conf_id={$ts_id_num[0]} AND data_time > '{$start[$xaxis-1]}'{$stop[$xaxis-1]} ORDER BY $orderby";
			$res = mysqli_query($db, $query);
			if (isset($_REQUEST['debug'])) debug($query);
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				// if (!isset($data[$row['time']])) $data[$row['time']] = array_merge($ts_array, array_fill(0,count($ts_array), ''));
				if (!isset($data[$row['time']])) $data[$row['time']] = $ts_empty;
				// if (isset($_REQUEST['debug'])) {debug($data[$row['time']]);debug($ts_id_num);}
				$data[$row['time']][$ts_map[$ts_id_num[0]]] = is_null($row['val'])? $row['val']: $row['val']-0;
			}
			$ts_counter++;
		}
	  }
	}

	ksort($data);
	// if (isset($_REQUEST['debug'])) debug($data);
	if ($mat) {
		$php2mat->SendFile("eGiga2m.mat", $big_data, "eGiga2m, Platform: $platform, Created on: ".date("d-F-Y H:i:s"));
		exit();
	}
	if ($xls) {
		foreach ($data as $time=>$v) {
			if (isset($_REQUEST['zoh'])) {foreach ($v as $i=>$val) {if (empty($val)) $v[$i] = $old_data[$i]; else $old_data[$i] = $val;}}
			array_unshift($v, $time);
			$myxls->Data[] = $v;
		}
		// debug($myxls->Data);
		$myxls->FileName = $filename;
		$myxls->SendFile();
		exit();
	}
	if ($xls_new) {
		$row = 2;
		foreach ($data as $time=>$v) {
			if (isset($_REQUEST['zoh'])) {foreach ($v as $i=>$val) {}}
			$myxls->setActiveSheetIndex(0)->setCellValue($cellColumn[0].$row, $time);
			foreach ($v as $i=>$val) {
				if (isset($_REQUEST['zoh'])) if (empty($val)) $val = $old_data[$i]; else $old_data[$i] = $val;
				$myxls->setActiveSheetIndex(0)->setCellValue($cellColumn[$i+1].$row, $val);
			}
			$row++;
		}
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="eGiga2m.xls"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($myxls, 'Excel5');
		$objWriter->save('php://output');
		exit();
	}
	if ($csv) {
		header("Content-Type: application/csv");
		header("Content-Disposition: attachment; filename=\"$filename\"");
		echo "time";
		foreach ($csv_header as $h) {
			echo $separator.$h;
		}
		echo "\n";
		$times = array_keys($data);
		$t_index = -1;
		foreach ($data as $time=>$v) {
			$t_index++;
			echo (isset($_REQUEST['timestamp'])? strtotime($time): $time);
			if (isset($_REQUEST['debug'])) exit();
			foreach ($v as $i=>$val) {
				$val = trim($val);
				if (isset($_REQUEST['zoh'])) {if (empty($val)) $val = $old_data[$i]; else $old_data[$i] = $val;}
				if (isset($_REQUEST['foh'])) { // TODO: correct $next_time evaluation
					if (empty($val)) {
						for ($t=$t_index+1; strlen(trim($data[$times[$t]][$i])); $t++) {
							$next_time = $times[$t]; 
							$next_data = $data[$next_time][$i];
							if ($t==count($times)-1) {
								$next_time = $last_time[$i]; 
								$next_data = $last_data[$i];
								break;
							}
						}
						$val = (strtotime($time)-$old_time[$i])/(strtotime($next_time)-$old_time[$i])*($next_data-$old_data[$i])+$old_data[$i]; 
					} 
					else {
						$old_data[$i] = $val;
						$old_time[$i] = strtotime($time);
					}
				}
				echo $separator.$val;
			}
			echo "\n";
		}
		exit();
	}
	if ($_REQUEST['format']=='igor' || $_REQUEST['format']=='itx') {
		emit_igor($data, $csv_header);
	}
?>
