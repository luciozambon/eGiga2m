<?php
	require_once('./analysis.php');

	// ----------------------------------------------------------------
	// retrieve forecast from a specific server
	function get_forecast(&$tsdata, &$parameters) {
		conf_request($parameters);
		parameters_prompt($parameters);
		get_parameters($parameters, $ts, $t, $conf);
		$src = 'http://'.$_SERVER["SERVER_NAME"].strtr($_SERVER["PHP_SELF"],array('forecast.php'=>'interpolator.php'));
		$src .= strtr("?conf=$conf%26start={$t['start']}%26stop={$t['stop']}%26ts={$ts}", array('%20'=>'+', ':'=>'%3A'));
		$src .= "%26period={$parameters['period']['value']}%26method=linear%26format=columns";
		$url = R_SRC."/forecast.php?samples={$parameters['samples']['value']}&src=".urlencode($src);
		$context = stream_context_create(array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false)));
		if (isset($_REQUEST['debug'])) {echo "$url<br>\n";}
		if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
		readfile($url, false, $context);
	}

	$parameters = array(
		'title'=>'Forecast',
		'description'=>'Forecast from <a href="https://www.rdocumentation.org/packages/forecast/versions/8.16/topics/forecast">R ARIMA package</a>; apply to no more than one time series at a time<br>user can provide seasonal frequency<br>only a few parameters are implemented, not supported by Flot<br><span style="color: red;"><b>WARNING</b> this tool is experimental and forecasts can be inaccurate or even misleading</span><br>',
		'period'=>array('default'=>3600,'type'=>'number','description'=>'seconds between two consecutive samples extracted by interpolator.<br>This parameter can have a huge impact on the quality of the forecast.'),
		'samples'=>array('default'=>100,'type'=>'number','description'=>'number of forecasted samples<br>If about 1000 or more samples are available it is suggested to request no more than 100 forecasted samples<br>having less available samples it is suggested to request no more than 10% samples'),
		'frequency'=>array('default'=>0,'type'=>'number','description'=>'seasonal frequency as number of samples, 0 = no seasonality'),
		// 'appendts'=>array('default'=>true,'type'=>'bool','description'=>'deploy the derived time series and the original one (true) or the derived one only (false)'),
		// 'format'=>array('default'=>'json','type'=>array('csv','json','short'))
	);
	get_forecast($data, $parameters);
?>
