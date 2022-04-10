<?php
	$src = $_REQUEST['src'];
	$tsa = explode('ts=', $src);
	$tsa = explode(',', $tsa[1]);

	$outformat = empty($_REQUEST['outformat'])? '': $_REQUEST['outformat'];
	$period = 3600;
	if (strpos('period=', $src)!==false) {$p = explode('period=', $src); $q = explode('%', $p[1]); $period = $q[0];}
	if (isset($_REQUEST['debug'])) echo "/usr/bin/Rscript /var/www/html/r/fft.R $src $outformat 2>&1<br><br>\n";
	exec("/usr/bin/Rscript /var/www/html/r/fft.R $src $outformat 2>&1", $out, $retval);
	$d = $data = array();
	$k = 0;
	foreach ($out as $i=>$l) {
		if (isset($_REQUEST['debug'])) echo "$i - $l<br>";
		if (strpos($l, 'label')!==false) {
			$a = explode('"', $out[$i+1]);
			$tslabel = $a[1];
			continue;
		}
		if (strpos($l, 'datetime')!==false) $datetime = $i;
		if (strpos($l, 'value')!==false) $value = $i;
		if (empty($value)) continue;
		if (strpos($l, '[1] "')!==false) {
			if (!empty($label)) {
				$d[$label] = $data;
				$data = array();
				$k = 0;
			}
			$t = explode('"',$l); 
			$label = $t[1];
		}
		else {
			if (empty($label)) continue;
			$buf = explode("]", $l);
			$buf = explode(" ", $buf[1]);
			foreach ($buf as $b) {
				if (strlen($b)) {
					$data[$k] = array($k, $b-0);
					$k++;
				}
			}
		}
	}
	if (!empty($label)) {
		$d[$label] = $data;
		$data = array();
	}
	$data = array('ts'=>array());
	$y = 0;
	foreach ($d as $label=>$dt) {
		$y++;
		$data['ts'][] = array('display_unit'=>'','ts_id'=>$tsa[0]." FFT $label",'label'=>"$label(FFT($tslabel))",'xaxis'=>'1','yaxis'=>"$y",'data'=>$dt);
	}
	if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
	echo json_encode($data); // , JSON_INVALID_UTF8_IGNORE
?>
