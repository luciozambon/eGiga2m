<?php
	require_once('./analysis.php');

	function tsdiff($data, $parameters) {
		$format = $parameters['format']['value'];
		$dest = $format=='csv'? '': array();
		for ($j=0; $j<count($data['ts']); $j++) {
			$d = $data['ts'][$j];
			$name = pathinfo($d['label']);
			if ($format=='csv') $dest .= 't;'.$name['filename']; else $dest[$j] = array();
			for ($t=0; $t<count($d['data'])-1; $t++) {
				$s = $parameters['samples']['value'];
				$y = $w = 0;
				if ($d['data'][$t][1] === null) {
					$y = null;
				}
				else if ($parameters['dt']['value']) {
					$diff = ($d['data'][$t+1][1] - $d['data'][$t][1]) / ($d['data'][$t+1][0]-$d['data'][$t][0]) * 1000;
					$y = $diff;
					if (isset($_REQUEST['debug'])) echo sprintf('%01d',$t)."[&Delta;t,y]: [".(($d['data'][$t][0]-$d['data'][0][0])/1000).", $y]<br>\n";
				}
				else {
					$diff = $d['data'][$t+1][1] - $d['data'][$t][1];
					$y = $diff;
					if (isset($_REQUEST['debug'])) echo sprintf('%01d',$t)."[&Delta;t,y]: [".(($d['data'][$t][0]-$d['data'][0][0])/1000).", $y]<br>\n";
				}
				if ($format=='csv') $dest .= ($d['data'][$t][0]/1000).";{$y}\n"; else $dest[$j][] = array($d['data'][$t][0], $y);
			}
		}
		return $dest;
	}

	$parameters = array(
		'title'=>'Diff',
		'description'=>'Difference from the previous value considering or not the time diff',
		'dt'=>array('default'=>true,'type'=>'bool','description'=>'consider or not the time diff'),
		'appendts'=>array('default'=>true,'type'=>'bool','description'=>'deploy the derived time series and the original one (true) or the derived one only (false)'),
		'format'=>array('default'=>'json','type'=>array('csv','json','short'))
	);
	input_data($data, $t, $parameters);
	$dest = tsdiff($data, $parameters);
	output_data($data, $dest, $parameters)
?>
