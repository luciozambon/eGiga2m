<?php
	require_once('./analysis.php');

	function tsmul($data, $parameters) {
		$format = $parameters['format']['value'];
		$e = explode(',', $parameters['e']['value']);
		$m = $parameters['m']['value']-0;
		$dest = $format=='csv'? '': array();
		$map = $index = $values = array();
		// $minindex: minimum timestamp where all time series are defined (discard all values before this timestamp)
		$minindex = $data['ts'][0]['data'][0][0];
		for ($i=0; $i<count($data['ts']); $i++) {
			if (!isset($e[$i])) $e[$i] = $e[0];
			$values[$i] = $data['ts'][$i]['data'][0][1];
			if ($minindex < $data['ts'][$i]['data'][0][0]) $minindex = $data['ts'][$i]['data'][0][0];
		}
		if (isset($_REQUEST['debug'])) {echo "<br><br>e<pre>"; print_r($e); echo "</pre>"; }
		for ($i=0; $i<count($data['ts']); $i++) {		
			$index[$i] = 0;
			for ($j=0; $j<count($data['ts'][$i]['data']); $j++) {
				if ($data['ts'][$i]['data'][$j][0] < $minindex) {$values[$i] = $data['ts'][$i]['data'][$j][1]; $index[$i] = $j; continue;}
				if (isset($map[$data['ts'][$i]['data'][$j][0]])) $map[$data['ts'][$i]['data'][$j][0]][] = $i;
				else $map[$data['ts'][$i]['data'][$j][0]] = array($i);
				
			}
		}
		if (isset($_REQUEST['debug'])) {echo "<br><br>map<pre>"; print_r($map); echo "</pre>"; }
		ksort($map);
		if (isset($_REQUEST['debug'])) {echo "<br><br>map<pre>"; print_r($map); echo "</pre>"; }
		foreach ($map as $t => $in) {
			if (isset($_REQUEST['debug'])) {echo "<br><br>index<pre>"; print_r($index); echo "</pre>"; }
			for ($k=0; $k<count($in); $k++) {
				$i = $in[$k];
				while ($data['ts'][$i]['data'][$index[$i]][0] < $t) $index[$i]++;
				$values[$i] = $data['ts'][$i]['data'][$index[$i]][1];
			}
			$y = $m;
			if (isset($_REQUEST['debug'])) {echo "<br><br>y<pre>"; print_r($y); echo "</pre>"; }
			for ($i=0; $i<count($data['ts']); $i++) {
				$y *= $values[$i] ** $e[$i];
			}
			if (isset($_REQUEST['debug'])) {echo "<br><br>y: $y, values<pre>"; print_r($values); echo "</pre>"; }
			if ($format=='csv') $dest .= ($t/1000).";{$y}\n"; else $dest[0][] = array($t, $y);
		}
		if (isset($_REQUEST['debug'])) {echo "<br><br>dest<pre>"; print_r($dest); echo "</pre>"; }
		return $dest;
	}

	$parameters = array(
		'title'=>'Mul',
		'description'=>'Multiply element by element of all timeseries selected (ts[i]); each timeseries is rised to a constant (e[i])<br>m * ts[0]^e[0] * ts[1]^e[1] * ts[2]^e[2] * ... ',
		'e'=>array('default'=>1,'description'=>'array of exponents e[i] (comma separated, one foreach timeseries)'),
		'm'=>array('default'=>1,'description'=>'mulplicative factor'),
		'appendts'=>array('default'=>true,'type'=>'bool','description'=>'deploy the derived time series and the original one (true) or the derived one only (false)'),
		'format'=>array('default'=>'json','type'=>array('csv','json','short'))
	);
	input_data($data, $t, $parameters);
	$dest = tsmul($data, $parameters);
	output_data($data, $dest, $parameters)
?>
