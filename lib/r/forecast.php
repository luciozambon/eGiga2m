<?php
	$src = $_REQUEST['src'];
	$samples = $_REQUEST['samples'];
	$frequency = $_REQUEST['frequency'];
	$period = 3600;
	if (strpos('period=', $src)!==false) {$p = explode('period=', $src); $q = explode('%', $p[1]); $period = $q[0];}
	exec("/usr/bin/Rscript /var/www/html/r/forecast.R $src $samples $frequency 2>&1", $out, $retval);

	$format = empty($_REQUEST['format'])? 'json': $_REQUEST['format'];
	if ($format=='raw') die(implode("\n",$out));
	$datetime = $value = $forecast = 100000000;
	$t = $v = $ft = $fv = $f80 = $f95 = array();
	foreach ($out as $i=>$l) {
		if (isset($_REQUEST['debug'])) echo "$i - $l<br>";
		if (strpos($l, 'label')!==false) {
			$a = explode('"', $out[$i+1]);
			$label = $a[1];
		}
		if (strpos($l, 'datetime')!==false) $datetime = $i;
		if ($i>$datetime) {
			if (empty($l)) {
				$datetime = 100000000;
				while ($t[count($t)-1] == null) unset($t[count($t)-1]);
				continue;
			}
			$a = explode('"', $l);
			if ($i==$datetime+1) {
				$start = strtotime($a[1]);
			}
			$t[] = strtotime($a[1])*1000;
			$t[] = strtotime($a[3])*1000;
			$t[] = strtotime($a[5])*1000;
		}
		if (strpos($l, 'value')!==false) $value = $i;
		if ($i>$value) {
			if (empty($l)) {
				$value = 100000000;
				while ($v[count($v)-1] == null) unset($v[count($v)-1]);
				continue;
			}
			$a = explode('] ', $l);
			$b = explode(' ', $a[1]);
			foreach ($b as $c) $v[] = $c - 0;
		}
		if (strpos($l, 'Forecast')!==false) $forecast = $i;
		if ($i>$forecast) {
			$a = explode(' ', $l);
			$n = count($a);
			$ft = $frequency==0? $a[0]+$start: ($a[0]-1)*$frequency*$period+$start;
			$f95[] = array($ft*1000, $a[$n-2]-0, $a[$n-1]-0);
			$f80[] = array($ft*1000, $a[$n-4]-0, $a[$n-3]-0);
			$fv[] = array($ft*1000, $a[$n-5]-0);
		}
	}
	$d = array();
	foreach ($t as $i=>$tt) $d[] = array($tt, $v[$i]);
	$data = array('ts'=>array(array('display_unit'=>'','ts_id'=>'','label'=>$label,'xaxis'=>'1','yaxis'=>'1','data'=>$d)),'forecast'=>array('fv'=>$fv,'f80'=>$f80,'f95'=>$f95));
	if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
	echo json_encode($data); // , JSON_INVALID_UTF8_IGNORE

?>
