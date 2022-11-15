<?php
	require_once('./analysis.php');

	function tsmin($data, $parameters) {
		$format = $parameters['format']['value'];
		$dest = $format=='csv'? '': array();
		$min = null;
		for ($j=0; $j<count($data['ts']); $j++) {
			$d = $data['ts'][$j];
			$name = pathinfo($d['label']);
			if ($format=='csv') $dest .= 't;'.$name['filename']; else $dest[$j] = array();
			for ($t=0; $t<count($d['data']); $t++) {
				$s = $parameters['samples']['value'];
				$y = $w = 0;
				if ($d['data'][$t][1] === null) {
					$y = null;
				}
				else if ($s == 0) {
					if ($min == null || $min > $d['data'][$t][1]) $min = $d['data'][$t][1];
					$y = $min;
					if (isset($_REQUEST['debug'])) echo sprintf('%01d',$t)."[&Delta;t,y]: [".(($d['data'][$t][0]-$d['data'][0][0])/1000).", $y]<br>\n";
				}
				else {
					$min = null;
					for ($i=$t-$s; $i<=$t+$s; $i++) {
						if (!isset($d['data'][$i])) continue;
						if ($min == null || $min > $d['data'][$i][1]) $min = $d['data'][$i][1];
					}
					$y = $min;
					if (isset($_REQUEST['debug'])) echo sprintf('%01d',$t)."[&Delta;t,y]: [".(($d['data'][$t][0]-$d['data'][0][0])/1000).", $y]<br>\n";
				}
				if ($format=='csv') $dest .= ($d['data'][$t][0]/1000).";{$y}\n"; else $dest[$j][] = array($d['data'][$t][0], $y);
			}
		}
		return $dest;
	}

	$parameters = array(
		'title'=>'Min',
		'description'=>'Minimum value from the beginning of the timeseries or in a moving window (discrete lower envelope)',
		'samples'=>array('default'=>0,'type'=>'number','description'=>'number of samples before and after actual sample, if 0 consider all samples before actual sample and none after'),
		'appendts'=>array('default'=>false,'type'=>'bool','description'=>'deploy the derived time series and the original one (true) or the derived one only (false)'),
		'format'=>array('default'=>'json','type'=>array('csv','json','short'))
	);
	input_data($data, $t, $parameters);
	$dest = tsmin($data, $parameters);
	output_data($data, $dest, $parameters)
?>
