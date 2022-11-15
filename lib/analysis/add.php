<?php
	require_once('./analysis.php');

	function tssum($data, $parameters) {
		$format = $parameters['format']['value'];
		$m = explode(',', $parameters['m']['value']);
		$a = $parameters['a']['value']-0;
		$dest = $format=='csv'? '': array();
		$map = $index = $values = array();
		// $minindex: minimum timestamp where all time series are defined (discard all values before this timestamp)
		$minindex = $data['ts'][0]['data'][0][0];
		for ($i=0; $i<count($data['ts']); $i++) {
			if (!isset($m[$i])) $m[$i] = $m[0];
			$values[$i] = $data['ts'][$i]['data'][0][1];
			if ($minindex < $data['ts'][$i]['data'][0][0]) $minindex = $data['ts'][$i]['data'][0][0];
		}
		if (isset($_REQUEST['debug'])) {echo "<br><br>m<pre>"; print_r($m); echo "</pre>"; }
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
			$y = $a;
			if (isset($_REQUEST['debug'])) {echo "<br><br>y<pre>"; print_r($y); echo "</pre>"; }
			for ($i=0; $i<count($data['ts']); $i++) {
				$y += $m[$i] * $values[$i];
			}
			if (isset($_REQUEST['debug'])) {echo "<br><br>y: $y, values<pre>"; print_r($values); echo "</pre>"; }
			if ($format=='csv') $dest .= ($t/1000).";{$y}\n"; else $dest[0][] = array($t, $y);
		}
		if (isset($_REQUEST['debug'])) {echo "<br><br>dest<pre>"; print_r($dest); echo "</pre>"; }
		return $dest;
	}

	$parameters = array(
		'title'=>'Add',
		'description'=>'Add element by element of all timeseries selected (ts[i]); each timeseries is multiplied for a constant (m[i]) plus an additive constant (a)<br>a + m[0]*ts[0] + m[1]*ts[1] + m[2]*ts[2] + ... ',
		'm'=>array('default'=>1,'description'=>'array of mulplicative factors m[i] (comma separated, one foreach timeseries)'),
		'a'=>array('default'=>0,'type'=>'number','description'=>'additive constant a'),
		'appendts'=>array('default'=>false,'type'=>'bool','description'=>'deploy the derived time series and the original one (true) or the derived one only (false)'),
		'format'=>array('default'=>'json','type'=>array('csv','json','short'))
	);
	input_data($data, $t, $parameters);
	$dest = tssum($data, $parameters);
	output_data($data, $dest, $parameters)
?>
