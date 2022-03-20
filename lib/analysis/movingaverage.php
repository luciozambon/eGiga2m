<?php
	require_once('./analysis.php');

	function moving_average($data, $parameters) {
		$weight = $parameters['weight']['value'];
		$format = $parameters['format']['value'];
		$dest = $format=='csv'? '': array();
		for ($j=0; $j<count($data['ts']); $j++) {
			$d = $data['ts'][$j];
			$name = pathinfo($d['label']);
			if ($format=='csv') $dest .= 't;'.$name['filename']; else $dest[$j] = array();
			for ($t=0; $t<count($d['data']); $t++) {
				$s = $parameters['samples']['value'];
				// if ($t < $s) $s = $t;
				// if ($t >= (count($d['data'])-$s)) $s = count($d['data']) - $t - 1;
				$y = $w = 0;
				if ($d['data'][$t][1] === null) {
					$y = null;
				}
				else if ($parameters['method']['value'] == 'sma') {
					for ($i=$t-$s; $i<=$t+$s; $i++) {
						if (!isset($d['data'][$i])) continue;
						if ($d['data'][$i][1] !== null) {
							$y += $d['data'][$i][1];
							$w++;
						}
					}
					$y = $w>0? $y / $w: null;
					if (isset($_REQUEST['debug'])) echo sprintf('%01d',$t)."[&Delta;t,y]: [".(($d['data'][$t][0]-$d['data'][0][0])/1000).", $y]<br>\n";
				}
				else {
					for ($i=$t-$s; $i<=$t+$s; $i++) {
						if (!isset($d['data'][$i])) continue;
						if ($d['data'][$i][1] !== null) {
							$wg = $weight / ($weight + abs($d['data'][$t][0] - $d['data'][$i][0]));
							$y += $d['data'][$i][1] * $wg;
							$w += $wg;
						}
					}
					$y = $w>0? $y / $w: null;
					if (isset($_REQUEST['debug'])) echo sprintf('%01d',$t)."[&Delta;t,y]: [".(($d['data'][$t][0]-$d['data'][0][0])/1000).", $y]<br>\n";
				}
				if ($format=='csv') $dest .= ($d['data'][$t][0]/1000).";{$y}\n"; else $dest[$j][] = array($d['data'][$t][0], $y);
			}
		}
		return $dest;
	}

	$parameters = array(
		'title'=>'Moving average',
		'description'=>'Moving average, based only on a number of samples after and before or on time distance weights',
		'samples'=>array('default'=>5,'type'=>'number','description'=>'number of samples before and after actual sample considered for moving average'),
		'method'=>array('default'=>'sma','type'=>array('sma','wma'),'description'=>'simple moving average (SMA) or a time based weighted moving average (WMA)'),
		'weight'=>array('default'=>1,'type'=>'number','factor'=>1000000,'description'=>'wheight (only for exponential moving average)'),
		'appendts'=>array('default'=>true,'type'=>'bool','description'=>'deploy the derived time series and the original one (true) or the derived one only (false)'),
		'format'=>array('default'=>'json','type'=>array('csv','json','short'))
	);
	input_data($data, $t, $parameters);
	$dest = moving_average($data, $parameters);
	output_data($data, $dest, $parameters)
?>
