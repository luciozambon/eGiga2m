<?php
	$url = './svgexport.php'; // this can be hosted on another server
	// file_put_contents('./export.svg', $_REQUEST['svg']);
	$svg = $_REQUEST['svg'];
	$data = array('svg' => $svg);
	if ($_REQUEST['format']=='pdf') $data['pdf'] = 1;
	// use key 'http' even if you send the request to https://...
	$options = array(
		'http' => array(
			'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
			'method'  => 'POST',
			'content' => http_build_query($data)
		)
	);
	$context  = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
	file_put_contents('./export.'.$_REQUEST['format'], $result);
?>
