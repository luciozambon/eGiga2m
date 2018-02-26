<?php

	include './hdb_conf.php';

	$buffer_filename = 'tree_root.json';
	$buffer_threshold = 3600;

	$fancyConfig = array();
	$rowArray = array();

	$db = mysqli_connect(HOST, USERNAME, PASSWORD);
	mysqli_select_db($db, DB);


	if (isset($_REQUEST['query'])) {
		echo "{$_REQUEST['query']};<br>\n";
		$query_result = mysqli_query($db, $_REQUEST['query']);
		if (mysqli_errno($db)) {
			echo "Error ".mysqli_errno($db).": ".mysqli_error($db)."<p>\n";
		}
		else while($row = mysqli_fetch_array($query_result)) {
			debug($row);
		}
		exit(0);
	}


	// ----------------------------------------------------------------
	// Quote variable to make safe
	function quote_smart($value, $separator="'")
	{
		global $db;
		// Stripslashes
		if (get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}
		strtr($value, '&#147;&#148;`', '""'."'");
		// Quote if not integer
		if (!is_numeric($value)) {
			$value = $separator.mysqli_real_escape_string($db, $value).$separator;
		}
		return $value;
	}

	// ----------------------------------------------------------------
	// debug a variable
	function debug($var, $name='') {
		if ($name !== '') {
			echo "\$$name: ";
		}
		if (is_array($var)) {
			echo "<pre>"; print_r($var); echo "</pre><p>\n";
		}
		else {
			echo ($var===0? "0": $var)."<p>\n";
		}
	}

	// ----------------------------------------------------------------
	// eval relative differece in parameters
	function eval_diff($s1, $s2) {
		$len = strlen($s1) + strlen($s2);
		$diff = levenshtein(strtoupper(trim($s1)), strtoupper(trim($s2)));
		return array($diff, $len);
	}

	// ----------------------------------------------------------------
	// expand tree children
	function expand_children($tree_base, $key, $title, $tree_index) {
		global $skipdomain, $db, $dim;
		$tooltip = 'click to switch display mode';
		if ($tree_index==5) {
			$icon = "./img/y0axis.png";
			$res = mysqli_query($db, "SELECT * FROM adt WHERE full_name = '$key' ORDER BY full_name");
			// echo "SELECT * FROM adt WHERE full_name = '$key' ORDER BY full_name<br>\n";
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			if (isset($tree_base[$title])) {
				$icon = is_numeric($tree_base[$title])? "./img/y".($tree_base[$title]/*+1*/)."axis.png": "./img/{$tree_base[$title]}.png";
				$tooltip = is_numeric($tree_base[$title])? "show on Y".($tree_base[$title]/*+1*/)." axis": strtr("{$tree_base[$title]} display", array('multi'=>'multi line'));
				// $icon = is_numeric($tree_base[$title])? "./img/y".($tree_base[$title]+1)."axis.png": "./img/{$tree_base[$title]}.png";
				if (($dim==1) and ($icon == "./img/y1axis.png")) {
					$icon = $tree_base[$title];//"./img/surface.png";
					$tooltip = "{$tree_base[$title]} display";
				}
			}
			$tooltip = 'click to switch display mode';
			return array('title' => basename($title), 'key'=> $row['ID'], "folder"=> false, "isArray" => $row['data_format']=="1", "lazy" => false, "icon"=> $icon, "tooltip"=> $tooltip);
		}
		$res2 = mysqli_query($db, "SELECT DISTINCT SUBSTRING_INDEX(full_name,'/',$tree_index) AS description FROM adt WHERE full_name LIKE '$key%' ORDER BY description");
		if ($res2 !== false) while ($row = mysqli_fetch_array($res2, MYSQLI_ASSOC)) {
			$key2 = $row['description'];
			$title2 = strtr($row['description'], $skipdomain);
			if (isset($_REQUEST['debug'])) {debug($key2, 'key2'); debug($title2, 'title2'); debug($tree_base, 'tree_base');}
			if (isset($tree_base[strtolower($title2)])) {
				$children[] = expand_children($tree_base, $key2, $title2, $tree_index+1);
			}
			else {
				if ($tree_index==4) {
					$res = mysqli_query($db, "SELECT data_format, ID FROM adt WHERE full_name = '$key2' ORDER BY full_name");
					// echo "SELECT data_format, ID FROM adt WHERE full_name = '$key2' ORDER BY full_name<br>\n";
					$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
					$children[] = array('title' => basename($title2), 'key'=>$row['ID'], "folder" => false, "isArray" => $row['data_format']=="1", "lazy" => false, "icon" => "./img/y0axis.png", "tooltip"=> $tooltip);
				}
				else {
					$children[] = array('title' => basename($title2), 'key'=>$key2, "folder" => true, "lazy" => true, "expanded" => false);
				}
			}
		}
		return array('title' => basename($title), 'key'=>$key, "folder" => true, "lazy" => false, "expanded" => true, "children"=>$children);
	}



	$querytime = $fetchtime = 0.0;
	if (isset($_REQUEST['reversekey'])) {
		$res = mysqli_query($db, "SELECT full_name FROM adt WHERE ID=".quote_smart($_REQUEST['reversekey']));
		$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
		$fancyConfig = empty($row['full_name'])? '': $row['full_name'];
	}
	else if (isset($_REQUEST['search']) and (strlen($_REQUEST['search'])>0)) {
		$s = explode(';', quote_smart($_REQUEST['search'], ''));
		foreach ($s as $search) {
			$res = mysqli_query($db, "SELECT * FROM adt WHERE full_name LIKE '%$search%' ORDER BY full_name");
		 	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$fancyConfig[$row['ID']] = $row['full_name'] ;
			}
		}
		if (empty($fancyConfig)) $fancyConfig = json_decode ("{}");
	}
	else if (isset($_REQUEST['search_similar']) and (strlen($_REQUEST['search_similar'])>0)) {
		$s = explode(';', quote_smart($_REQUEST['search_similar'], ''));
		$threshold = isset($_REQUEST['threshold'])? $_REQUEST['threshold']: 0.08;
		$res = mysqli_query($db, "SELECT ID, full_name FROM adt ORDER BY full_name");
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			$s1 = strtr($row['full_name'], $skipdomain);
			foreach ($s as $search) {
				list ($diff, $len) = eval_diff($s1, $search); 
				if (isset($_REQUEST['debug'])) echo "diff: $diff, len: $len, s1: $s1, search: $search<br>\n";
				if ($diff < round($threshold * $len)) $fancyConfig[$row['ID']] = $s1;
			}
		}
		if (empty($fancyConfig)) $fancyConfig = json_decode ("{}");
	}
	else if (isset($_REQUEST['ts']) and (strlen($_REQUEST['ts'])>0)) {
		$ts = explode(';', quote_smart($_REQUEST['ts'], ''));
		$ids = $tsArray = array();
		$tree_base = array();
		foreach ($ts as $s) {
			$id = explode(',', $s);
			$y_index = isset($id[2])? $id[2]: (isset($id[1])? $id[1]: 1);
			$res = mysqli_query($db, "SELECT full_name,data_format FROM adt WHERE ID=".quote_smart($id[0]));
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			$dim = $row['data_format'];
			$tok_base = '';
			$nameArray = explode('/', strtr($row['full_name'], $skipdomain));
			foreach ($nameArray as $tok) {
				$tok_base .= ($tok_base==''? '':'/').$tok;
				$tree_base[$tok_base] = $y_index;
			}
		}
		if (isset($_REQUEST['debug'])) {debug($tree_base);}
		$tree_index = 1;
		$res = mysqli_query($db, "SELECT DISTINCT SUBSTRING_INDEX(full_name,'/',$tree_index) AS description FROM adt ORDER BY description");
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			if (isset($_REQUEST['debug'])) {debug($row);}
			$key = $row['description'];
			$title = strtr($row['description'], $skipdomain);
			if (isset($tree_base[$title])) {
				$fancyConfig[] = expand_children($tree_base, $key, $title, $tree_index+1);
			}
			else {
				$fancyConfig[] = array('title' => basename($title), 'key'=>$key, "lazy" => true, "folder" => true);
			}
		}
	}
	else if (isset($_REQUEST['keys'])) {
		$keys = explode(';', quote_smart($_REQUEST['keys'], ''));
		foreach ($keys as $key) {
			$query = "SELECT * FROM adt WHERE full_name LIKE '$key' ORDER BY full_name";
			$res = mysqli_query($db, $query);
			if ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$fancyConfig[] = array('title' => $key, 'id'=>$row['att_conf_id']);
			}
			else {
				$fancyConfig[] = array('title' => $key, 'id'=>null);
			}
		}
		if (isset($_REQUEST['debug'])) {echo "$query;<br>\n";}
	}
	else if (!isset($_REQUEST['key']) or (strlen($_REQUEST['key'])==0) or ($_REQUEST['key']=="root")) {
		/* skip caching
		if (file_exists($buffer_filename) and (time()-filectime($buffer_filename)<$buffer_threshold)) {
			header("Content-Type: application/json");
			readfile($buffer_filename);
			exit(0);
		}
		*/
		$querytime -= microtime(true);
		if (isset($_REQUEST['domain'])) {
			$res = mysqli_query($db, "SELECT DISTINCT domain AS description FROM adt WHERE LENGTH(domain)>0 ORDER BY description");
			if (isset($_REQUEST['debug'])) $fancyConfig[] = "SELECT DISTINCT domain AS description FROM adt WHERE LENGTH(domain)>0 ORDER BY description";
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$key = $row['description'];
				$title = strtr($row['description'], $skipdomain);
				$fancyConfig[] = array('title' => basename($title), 'key'=>$key, "lazy" => true, "folder" => true);
			}
		}
		else {
			$oldDomain = '';
			$res = mysqli_query($db, "SELECT full_name FROM adt ORDER BY full_name");
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				list($domain, $trash) = explode('/', $row['full_name'], 2);
				if ($oldDomain != $domain) {
					$key = $domain;
					$title = strtr($domain, $skipdomain);
					$fancyConfig[] = array('title' => basename($title), 'key'=>$key, "lazy" => true, "folder" => true);
					$oldDomain = $domain;
				}
			}
		}
		$querytime += microtime(true);
		if (isset($_REQUEST['debug'])) $fancyConfig[] = $querytime;
		// skip caching
		// file_put_contents($buffer_filename, json_encode($fancyConfig));
	}
	else {
		$n = substr_count($_REQUEST['key'], '/') + 2;
		if ($n<4) {
			$res = mysqli_query($db, "SELECT DISTINCT SUBSTRING_INDEX(full_name,'/',$n) AS description FROM adt WHERE full_name LIKE ".quote_smart($_REQUEST['key'].'/%')." ORDER BY description");
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$key = $row['description'];
				$title = strtr($row['description'], $skipdomain);
				$fancyConfig[] = array('title' => basename($title), 'key'=> $key, "lazy" => true, "folder"=> true);
			}
		}
		else {
			$res = mysqli_query($db, "SELECT * FROM adt WHERE full_name LIKE ".quote_smart($_REQUEST['key'].'/%')." ORDER BY full_name");
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$key = $row['ID'];
				$title = strtr($row['full_name'], $skipdomain);
				$fancyConfig[] = array('title' => basename($title), 'key'=>$key, "lazy"=>false, "folder"=>false, "isArray"=>$row['data_format']=="1", "icon"=>"./img/y0axis.png", "tooltip"=>'click to switch display mode');
			}
		}
	}
	// file_put_contents('tree_log.txt', date("Y-m-d H:i:s").' - '.json_encode($tsArray).' - '.json_encode($fancyConfig)."\n", FILE_APPEND);

	header("Content-Type: application/json");
	echo json_encode($fancyConfig);
?>

