<?php
include './hdbpp_conf.php';

if (isset($_REQUEST['ts'])) {
	$q = explode('?',$_SERVER["REQUEST_URI"]);
	$context = stream_context_create(array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false)));
	// echo(CSVREPO.'?'.$q[1]);
	readfile(CSVREPO.'?'.$q[1], false, $context);
	exit();
}


file_put_contents('import.log', date("Y-m-d H:i:s").' - '.json_encode($_REQUEST)."\n", FILE_APPEND);
function redirect_post($url, array $data, array $headers = null) {
  $params = [
    'http' => [
      'method' => 'POST',
      'content' => http_build_query($data)
    ]
  ];

  if (!is_null($headers)) {
    $params['http']['header'] = '';
    foreach ($headers as $k => $v) {
      $params['http']['header'] .= "$k: $v\n";
    }
  }

  $ctx = stream_context_create($params);
  $fp = @fopen($url, 'rb', false, $ctx);

  if ($fp) {
    echo @stream_get_contents($fp);
    die();
  } else {
    // Error
    throw new Exception("Error loading '$url', $php_errormsg");
  }
}
redirect_post(CSVREPO.'?import', $_REQUEST);
?>
