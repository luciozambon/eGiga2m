<?php
	require_once('./analysis.php');

	// https://en.wikipedia.org/wiki/Spline_(mathematics)#Algorithm_for_computing_natural_cubic_splines
	function splineCompute($x, $y) {
		$n = count($x)-1;
		$a = $y;
		$h = array();
		for ($i=0; $i<$n; $i++)
			$h[] = $x[$i+1]-$x[$i];

		$alpha = array(0);
		for ($i=0; $i<$n; $i++)
			$alpha[] = 3*($a[$i+1]-$a[$i])/$h[$i] - 3*($a[$i]-$a[$i-1])/$h[$i-1];

		$c = array();
		$l = array(1);
		$mu = array(0);
		$z = array(0);

		for ($i=0; $i<$n; $i++) {
			$l[$i] = 2 *($x[$i+1]-$x[$i-1])-$h[$i-1]*$mu[$i-1];
			$mu[$i] = $h[$i]/$l[$i];
			$z[$i] = ($alpha[$i]-$h[$i-1]*$z[$i-1])/$l[$i];
		}

		$l[$n] = 1;
		$z[$n] = 0;
		$c[$n] = 0;

		for ($j=$n-1; $j>=0; $j--) {
			$c[$j] = $z[$j] - $mu[$j] * $c[$j+1];
			$b[$j] = ($a[$j+1]-$a[$j])/$h[$j]-$h[$j]*($c[$j+1]+2*$c[$j])/3;
			$d[$j] = ($c[$j+1]-$c[$j])/3/$h[$j];
		}

		$output_set = array();
		for ($i=0; $i<$n; $i++) {
			$output_set[$i] = array('a'=>$a[$i], 'b'=> $b[$i], 'c'=>$c[$i], 'd'=>$d[$i], 'x'=>$x[$i]);
		}
		return $output_set;
	}
	function splineEval($spline, $x) {
		$dx = $x - $spline['x'];
		$dx2 = $dx * $dx;
		$dx3 = $dx2 * $dx;
		$y = $spline['a'] + $spline['b']*$dx + $spline['c']*$dx2 + $spline['d']*$dx3; 
		return $y;
	}
	function interpolator($data, $startt, $stopt, $parameters) {
		$period = $parameters['period']['value'];
		$method = $parameters['method']['value'];
		$format = $parameters['format']['value'];
		if ($method == 'spline') {
			$spline = array();
			for ($j=0; $j<count($data['ts']); $j++) {
				foreach ($data['ts'][$j]['data'] as $p) {$x[] = $p[0]; $y[] = $p[1];}
				$spline[$j] = splineCompute($x,$y);
			}
			if (isset($_REQUEST['debug'])) {echo "<br><br>spline<pre>"; print_r($spline); echo "</pre>";}
		}
		$dest = $format=='csv'? 't': array();
		$i = array();
		for ($j=0; $j<count($data['ts']); $j++) {
			$i[$j] = 0;
			$name = pathinfo($data['ts'][$j]['label']);
			if ($format=='csv') $dest .= ';'.$name['filename']; else $dest[$j] = array();
		}
		if ($format=='csv') $dest .= "\n";
		for ($t=$startt; $t<=$stopt; $t+=$period) {
			if ($format=='csv') $dest .= "$t";
			for ($j=0; $j<count($data['ts']); $j++) {
				while (isset($data['ts'][$j]['data'][$i[$j]+1]) && $data['ts'][$j]['data'][$i[$j]][0]<$t*1000) $i[$j]++;
				$i[$j]--;
				if ($i[$j]>=0 && $i[$j]<count($data['ts'][$j]['data'])) {
					if ($method == 'step') {
						$y = $data['ts'][$j]['data'][$i[$j]][1];
						if (isset($_REQUEST['debug'])) echo sprintf('%01d',$i[$j])."[t,y]: [".($t*1000-$startt*1000).",$y]<br>\n";
					}
					else if ($method == 'spline') {
						$y = splineEval($spline[$j][$i[$j]], $t*1000);
						$s = $spline[$j][$i[$j]]; $s['x'] -= $startt*1000;
						if (isset($_REQUEST['debug'])) echo sprintf('%01d',$i[$j])."[t,y]: [".($t*1000-$startt*1000).",$y], spline: ".json_encode($s)."<br>\n";
					}
					else {
						$y = $data['ts'][$j]['data'][$i[$j]][1] + ($data['ts'][$j]['data'][$i[$j]+1][1]-$data['ts'][$j]['data'][$i[$j]][1]) * ($t*1000-$data['ts'][$j]['data'][$i[$j]][0]) / ($data['ts'][$j]['data'][$i[$j]+1][0]-$data['ts'][$j]['data'][$i[$j]][0]);
						if (isset($_REQUEST['debug'])) echo sprintf('%01d',$i[$j])."[t,y]: [".($t*1000-$startt*1000).",$y], [t0,y0]: [".($data['ts'][$j]['data'][$i[$j]][0]-$startt*1000).",{$data['ts'][$j]['data'][$i[$j]][1]}], [t1,y1]: [".($data['ts'][$j]['data'][$i[$j]+1][0]-$startt*1000).",{$data['ts'][$j]['data'][$i[$j]+1][1]}]<br>\n";
					}
					if ($format=='csv') $dest .= ";{$y}"; else $dest[$j][] = array($t*1000, $y);
			
				}
			}
			if ($format=='csv') $dest .= "\n";
		}
		if (isset($_REQUEST['debug'])) {echo "<br><br>dest<pre>"; print_r($dest); echo "</pre>"; }
		return $dest;
	}

	$parameters = array(
		'title'=>'Interpolator',
		'description'=>'Transform timeseries from an unequally spaced points in time to equally spaced.<br>The requested period should be congruent with average period and Nyquist frequency',
		'period'=>array('default'=>30,'type'=>'number','description'=>'seconds between two consecutive samples of output'),
		'method'=>array('default'=>'linear','type'=>array('step','linear','spline')),
		'appendts'=>array('default'=>false,'type'=>'bool','description'=>'deploy the derived time series and the original one (true) or the derived one only (false)'),
		'format'=>array('default'=>'json','type'=>array('csv','json','short','columns','valueOnly'))
	);
	input_data($data, $t, $parameters);
	$dest = interpolator($data, $t['startt'], $t['stopt'], $parameters);
	output_data($data, $dest, $parameters);
?>
