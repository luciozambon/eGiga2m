<?php
	require_once('./analysis.php');

	// ----------------------------------------------------------------
	// retrieve fft from a specific server
	function get_fft(&$tsdata, &$parameters) {
		conf_request($parameters);
		parameters_prompt($parameters);
		get_parameters($parameters, $ts, $t, $conf);
		$src = 'http://'.$_SERVER["SERVER_NAME"].strtr($_SERVER["PHP_SELF"],array('fft.php'=>'interpolator.php'));
		$src .= strtr("?conf=$conf%26start={$t['start']}%26stop={$t['stop']}%26ts={$ts}", array('%20'=>'+', ':'=>'%3A'));
		$src .= "%26period={$parameters['period']['value']}%26method=linear%26format=columns";
		$url = R_SRC."/fft.php?outformat={$parameters['outformat']['value']}&src=".urlencode($src);
		$context = stream_context_create(array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false)));
		if (isset($_REQUEST['debug'])) {echo "$url<br>\n";}
		if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
		readfile($url, false, $context);
	}

	$parameters = array(
		'title'=>'FFT',
		'description'=>'FFT from <a href="https://www.rdocumentation.org/packages/stats/versions/3.6.2/topics/fft">R package</a>; apply to no more than one time series at a time<br>not supported by Flot<br><span style="color: red;"><b>WARNING</b> this tool is experimental</span><br>',
		'period'=>array('default'=>3600,'type'=>'number','description'=>'seconds between two consecutive samples extracted by interpolator'),
		'outformat'=>array('default'=>'Mod','type'=>array('Mod','Phase','Mod,Phase','Re','Im','Re,Im'))
		// 'appendts'=>array('default'=>true,'type'=>'bool','description'=>'deploy the derived time series and the original one (true) or the derived one only (false)'),
		// 'format'=>array('default'=>'json','type'=>array('csv','json','short'))
	);
	get_fft($data, $parameters);
?>
