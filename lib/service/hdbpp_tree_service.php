<?php
	include './hdbpp_conf.php';
	if (isset($_REQUEST['host'])) {die(HOST);}

	$buffer_filename = 'tree_root.json';
	$buffer_threshold = 3600;

	$fancyConfig = $fc = array();
	$rowArray = array();

	$db = mysqli_connect(HOST, USERNAME, PASSWORD, DB);
	if (isset($_REQUEST['debug'])) {mysqli_connect_error();}
	// mysqli_select_db($db, DB);
	// if (isset($_REQUEST['debug'])) {mysqli_connect_error();}

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
		global $skipdomain, $db;
		if ($tree_index==8) {
			$icon = "./img/y0axis.png";
			$res = mysqli_query($db, "SELECT * FROM att_conf,att_conf_data_type WHERE att_name = '$key' AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id ORDER BY att_name");
			$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
			list($dim, $type, $io) = explode('_', $row['data_type']);
			if (isset($tree_base[$title])) {
				$icon = is_numeric($tree_base[$title])? "./img/y".($tree_base[$title])."axis.png": "./img/{$tree_base[$title]}.png";
				if (($dim=="array") and ($icon == "./img/y1axis.png")) $icon = "./img/surface.png";
				if (isset($_REQUEST['debug'])) {debug($key, 'key'); debug($tree_base, 'tree_base');}
			}
			$res2 = mysqli_query($db, "SELECT * FROM att_history WHERE att_conf_id=".$row['att_conf_id']." ORDER BY time DESC LIMIT 20");
			$d = mysqli_fetch_all($res2, MYSQLI_ASSOC);
			$logging = !empty($d) && $d[0]['att_history_event_id']=='3';
			$title = basename($title);
			if (!$logging) {
				if (empty($d)) $alt = 'archiving has never been active'; else {
					$alt = 'archiving not active now';
					foreach ($d as $data) {
						if ($data['att_history_event_id']=='3') {$alt = 'archiving not active since '.$t; break;}
						$t = $data['time'];
					}
				}
				$title = "<span style='text-decoration: line-through;' title='$alt'>$title</span>";
			}
			else $title = "<span data-title='$title' title='last start: {$d[0]['time']}'>$title</span>";
			return array('title' => $title, 'key'=> $row['att_conf_id'], "folder"=> false, "isArray" => $dim=="array", "lazy" => false, "icon"=> $icon);
		}
		$res2 = mysqli_query($db, "SELECT DISTINCT SUBSTRING_INDEX(att_name,'/',$tree_index) AS description FROM att_conf WHERE att_name LIKE '$key%' ORDER BY description");
		if ($res2 !== false) while ($row = mysqli_fetch_array($res2, MYSQLI_ASSOC)) {
			$key2 = $row['description'];
			$title2 = strtr($row['description'], $skipdomain);
			if (isset($tree_base[$title2])) {
				$title3 = basename($title2);
				$res = mysqli_query($db, "SELECT data_type, att_conf.att_conf_id FROM att_conf,att_conf_data_type WHERE att_name = '$key2' AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id ORDER BY att_name");
				$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
				list($dim, $type, $io) = explode('_', $row['data_type']);
				$query = "SELECT * FROM att_history WHERE att_conf_id=".$row['att_conf_id']." ORDER BY time DESC LIMIT 5";
				$res3 = mysqli_query($db, $query);
				if (isset($_REQUEST['debug'])) echo("$query;<br>");
				$d = mysqli_fetch_all($res3, MYSQLI_ASSOC);
				if (isset($_REQUEST['debug'])) debug($d, 'd');
				$logging = !empty($d) && $d[0]['att_history_event_id']=='3';
				if (!$logging) {
					if (empty($d)) $alt = 'archiving has never been active'; else {
						$alt = 'archiving not active now';
						foreach ($d as $data) {
							if ($data['att_history_event_id']=='3') {$alt = 'archiving not active since '.$t; break;}
							$t = $data['time'];
						}
					}
					$title3 = "<span style='text-decoration: line-through;' title='$alt'>$title3</span>";
				}
				else $title3 = "<span data-laststart='{$d[0]['time']}'>$title3</span>";
				$children[$tree_index>5? $title3: $title2] = expand_children($tree_base, $key2, $title2, $tree_index+1);
			}
			else {
				$title2 = basename($title2);
				if ($tree_index==7) {
					$res = mysqli_query($db, "SELECT data_type, att_conf.att_conf_id FROM att_conf,att_conf_data_type WHERE att_name = '$key2' AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id ORDER BY att_name");
					$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
					list($dim, $type, $io) = explode('_', $row['data_type']);
					$query = "SELECT * FROM att_history WHERE att_conf_id=".$row['att_conf_id']." ORDER BY time DESC LIMIT 5";
					$res3 = mysqli_query($db, $query);
					if (isset($_REQUEST['debug'])) echo("$query;<br>");
					$d = mysqli_fetch_all($res3, MYSQLI_ASSOC);
					if (isset($_REQUEST['debug'])) debug($d, 'd');
					$logging = !empty($d) && $d[0]['att_history_event_id']=='3';
					if (!$logging) {
						if (empty($d)) $alt = 'archiving has never been active'; else {
							$alt = 'archiving not active now';
							foreach ($d as $data) {
								if ($data['att_history_event_id']=='3') {$alt = 'archiving not active since '.$t; break;}
								$t = $data['time'];
							}
						}
						$title2 = "<span style='text-decoration: line-through;' title='$alt'>$title2</span>";
					}
					else $title2 = "<span data-laststart='{$d[0]['time']}'>$title2</span>";
					$children[$title2] = array('title' => $title2, 'key'=>$row['att_conf_id'], "folder" => false, "isArray" => $dim=="array", "lazy" => false, "icon" => "./img/y0axis.png");
				}
				else {
					$children[$title2] = array('title' => $title2, 'key'=>$key2, "folder" => true, "lazy" => true, "expanded" => false);
				}
			}
		}
		ksort($children);
		if (isset($_REQUEST['debug'])) {debug($children, 'children sorted');}
		return array('title' => basename($title), 'key'=>$key, "folder" => true, "lazy" => false, "expanded" => true, "children"=>array_values($children));
	}



	if (isset($_REQUEST['query'])) {
		$res = mysqli_query($db, $_REQUEST['query']);
		if (!$res) 
			echo "{$_REQUEST['query']};<br>\n".mysqli_error($db);
		else while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			debug($row);
		}
		exit();
	}
	$where = '';
	if (isset($_REQUEST['family'])) {
		$keys = array_keys($skipdomain);
		$family = array();
		foreach (explode(',',$_REQUEST['family']) as $f) {
			$family[] = quote_smart($keys[0].$f);
		}
		$where = "WHERE SUBSTRING_INDEX(att_name,'/',4) IN (".implode(',', $family).")";
	}
	if (isset($_REQUEST['reversekey'])) {
		$id = explode('[', $_REQUEST['reversekey']);
		$res = mysqli_query($db, "SELECT att_name FROM att_conf WHERE att_conf_id=".quote_smart($id[0]));
		$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
		$fancyConfig = strtr($row['att_name'], $skipdomain);
	}
	else if (isset($_REQUEST['search']) and (strlen($_REQUEST['search'])>0)) {
		$s = explode(';', quote_smart($_REQUEST['search'], ''));
		foreach ($s as $search) {
			$res = mysqli_query($db, "SELECT att_conf_id, att_name FROM att_conf WHERE att_name LIKE '%$search%' ORDER BY att_name");
		 	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$fancyConfig[$row['att_conf_id']] = strtr($row['att_name'], $skipdomain);
			}
		}
		if (empty($fancyConfig)) $fancyConfig = json_decode ("{}");
	}
	else if (isset($_REQUEST['search_similar']) and (strlen($_REQUEST['search_similar'])>0)) {
		$s = explode(';', quote_smart($_REQUEST['search_similar'], ''));
		$threshold = isset($_REQUEST['threshold'])? $_REQUEST['threshold']: 0.08;
		$res = mysqli_query($db, "SELECT att_conf_id, att_name FROM att_conf ORDER BY att_name");
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			$s1 = strtr($row['att_name'], $skipdomain);
			foreach ($s as $search) {
				list ($diff, $len) = eval_diff($s1, $search); 
				if (isset($_REQUEST['debug'])) echo "diff: $diff, len: $len, s1: $s1, search: $search<br>\n";
				if ($diff < round($threshold * $len)) $fancyConfig[$row['att_conf_id']] = $s1;
			}
		}
		if (empty($fancyConfig)) $fancyConfig = json_decode ("{}");
	}
	else if (isset($_REQUEST['searchkey']) and (strlen($_REQUEST['searchkey'])>0)) {
		$s = explode(';', quote_smart($_REQUEST['searchkey'], ''));
		foreach ($s as $search) {
			$res = mysqli_query($db, "SELECT * FROM att_conf WHERE att_name LIKE '%$search' ORDER BY att_name");
		 	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$fancyConfig[] = $row['att_conf_id'];
			}
		}
		if (empty($fancyConfig)) $fancyConfig = json_decode ("{}");
	}
	else if (isset($_REQUEST['ts']) and (strlen($_REQUEST['ts'])>0)) {
		$ts = explode(';', quote_smart($_REQUEST['ts'], ''));
		$ids = $tsArray = $tree_base = $csv = $hdb = array();

		foreach ($ts as $s) {
			if (strpos($s, '_')===false) $hdb[] = $s; else $csv[] = $s;
		}
		if (count($csv)) {
			$context = stream_context_create(array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false)));
			$table = $tb = array();
			foreach ($csv as $k) {
				$a = explode('_', $k);
				if (empty($table[$a[1]])) $table[$a[1]] = array($k);
				else $table[$a[1]][] = $k;
				$dbname = $a[0];
			}
			if (isset($_REQUEST['debug'])) echo CSVREPO."?tree&key=csvrepo_{$dbname}<br>\n";
			$data = file_get_contents(CSVREPO."?tree&key=csvrepo_$dbname", false, $context);
			foreach (explode(',', $data) as $d) {
				if (!isset($table[$d])) {
					$tb[] = array('title' => $d, 'key'=>"csvrepo_{$dbname}_$d", "lazy" => true, "folder" => true);
				}
				else {
					$leaf = $children = array();
					foreach ($table[$d] as $key) {
						$a = explode('_', $key);
						$l = explode(',', array_pop($a));
						$leaf[$l[0]] = isset($l[2])? $l[2]-0: 1;
					}
					if (isset($_REQUEST['debug'])) echo CSVREPO."?tree&key=csvrepo_{$dbname}_$d<br>\n";
					$data = file_get_contents(CSVREPO."?tree&key=csvrepo_{$dbname}_$d", false, $context);
					foreach (explode(',', $data) as $l) {
						if (!isset($leaf[$l])) {
							$children[] = array('title' => $l, 'key'=>"csvrepo_{$dbname}_{$d}_{$l}", "lazy" => false, "folder" => false, "icon"=>"./img/y0axis.png");
						}
						else {
							$children[] = array('title' => $l, 'key'=>"csvrepo_{$dbname}_{$d}_{$l}", "lazy" => false, "folder" => false, "icon"=>"./img/y{$leaf[$l]}axis.png");
						}
					}
					$tb[] = array('title' => $d, 'key'=>"csvrepo_{$dbname}_$d", "lazy" => false, "folder" => true, "expanded" => true, "children"=>$children);
				}
			}
			$fancyConfig[] = array('title' => "<span style='color: darkgreen;font-weight: bold;'>$dbname</span>", 'key'=>"csvrepo_{$dbname}", "lazy" => false, "folder" => true, "expanded" => true, "children"=>$tb);
		}
		/* else {
			$fancyConfig[] = array('title' => "<span style='color: darkgreen;font-weight: bold;'>$dbname</span>", 'key'=>"csvrepo_{$dbname}", "lazy" => true, "folder" => true);
		} */
		if (!empty($hdb)) {
			foreach ($hdb as $s) {
				// skip formulae (which contain character '$')
				if (strpos($s, '$')!==false) continue;
				$id = explode(',', $s);
				$y_index = isset($id[2])? $id[2]: (isset($id[1])? $id[1]: 1);
				$att_conf_id = explode('[', $id[0]); 
				// $res = mysqli_query($db, "SELECT att_name FROM att_conf WHERE att_conf_id={$att_conf_id[0]]");
				$res = mysqli_query($db, "SELECT att_name FROM att_conf WHERE att_conf_id={$att_conf_id[0]}");
				$row = mysqli_fetch_array($res, MYSQLI_ASSOC);
				$tok_base = '';
				$nameArray = explode('/', strtr($row['att_name'], $skipdomain));
				foreach ($nameArray as $tok) {
					$tok_base .= ($tok_base==''? '':'/').$tok;
					$tree_base[$tok_base] = $y_index;
				}
			}
			// debug($tree_base); exit(0);
			$tree_index = 4;
			$res = mysqli_query($db, "SELECT DISTINCT SUBSTRING_INDEX(att_name,'/',$tree_index) AS description FROM att_conf $where ORDER BY description");
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
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
		else {
			$query = "SELECT DISTINCT SUBSTRING_INDEX(att_name,'/',4) AS description FROM att_conf $where ORDER BY description";
			$res = mysqli_query($db, $query);
			if (isset($_REQUEST['debug'])) echo "$query;<br>\n";
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$key = $row['description'];
				$title = strtr($row['description'], $skipdomain);
				$fancyConfig[] = array('title' => basename($title), 'key'=>$key, "lazy" => true, "folder" => true);
			}
		}
	}
	else if (isset($_REQUEST['keys'])) {
		$keys = explode(';', quote_smart($_REQUEST['keys'], ''));
		foreach ($keys as $key) {
			$query = "SELECT * FROM att_conf WHERE att_name LIKE '%$key' ORDER BY att_name";
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
		$query = "SELECT DISTINCT SUBSTRING_INDEX(att_name,'/',4) AS description FROM att_conf $where ORDER BY description";
		$res = mysqli_query($db, $query);
		if (isset($_REQUEST['debug'])) echo "$query;<br>\n";
		while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			$key = $row['description'];
			$title = strtr($row['description'], $skipdomain);
			$fancyConfig[] = array('title' => basename($title), 'key'=>$key, "lazy" => true, "folder" => true);
		}
		// skip caching
		// file_put_contents($buffer_filename, json_encode($fancyConfig));
	}
	else if (strpos($_REQUEST['key'], 'csvrepo_')!==false) {
		$context = stream_context_create(array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false)));
		$n = substr_count($_REQUEST['key'], '_');
		if ($n>2) {
			$keys = explode(';', $_REQUEST['key']);
			$table = array();
			foreach ($keys as $k) {
				$a = explode('_', $k);
				if (empty($table[$a[2]])) $table[$a[2]] = array($k);
				else $table[$a[2]][] = $k;
				$db = $a[1];
			}
			if (isset($_REQUEST['debug'])) echo CSVREPO."?tree&key=csvrepo_{$db}<br>\n";
			$data = file_get_contents(CSVREPO."?tree&key=csvrepo_$db", false, $context);
			foreach (explode(',', $data) as $d) {
				if (!isset($table[$d])) {
					$fancyConfig[] = array('title' => $d, 'key'=>"csvrepo_{$db}_$d", "lazy" => true, "folder" => true);
				}
				else {
					$leaf = $children = array();
					foreach ($table[$d] as $key) {
						$a = explode('_', $key);
						$l = explode(',', array_pop($a));
						$leaf[$l[0]] = isset($l[2])? $l[2]-0: 1;
					}
					if (isset($_REQUEST['debug'])) echo CSVREPO."?tree&key=csvrepo_{$db}_$d<br>\n";
					$data = file_get_contents(CSVREPO."?tree&key=csvrepo_{$db}_$d", false, $context);
					foreach (explode(',', $data) as $l) {
						if (!isset($leaf[$l])) {
							$children[] = array('title' => $l, 'key'=>"csvrepo_{$d}_{$l}", "lazy" => false, "folder" => false, "icon"=>"./img/y0axis.png");
						}
						else {
							$children[] = array('title' => $l, 'key'=>"csvrepo_{$d}_{$l}", "lazy" => false, "folder" => false, "icon"=>"./img/y{$leaf[$l]}axis.png");
						}
					}
					$fancyConfig[] = array('title' => $d, 'key'=>"csvrepo_{$db}_$d", "lazy" => false, "folder" => true, "children"=>$children);
				}
			}
		}
		else {
			if (isset($_REQUEST['debug'])) echo CSVREPO."?tree&key={$_REQUEST['key']}<br>\n";
			$data = file_get_contents(CSVREPO."?tree&key={$_REQUEST['key']}", false, $context);
			foreach (explode(',', $data) as $d) {
				if ($n<2) {
					$fancyConfig[] = array('title' => $d, 'key'=>"{$_REQUEST['key']}_$d", "lazy" => true, "folder" => true);
				}
				else {
					$fancyConfig[] = array('title' => $d, 'key'=>"{$_REQUEST['key']}_$d", "lazy" => false, "folder" => false, "icon"=>"./img/y0axis.png");
				}
			}
		}
	}
	else {
		$n = substr_count($_REQUEST['key'], '/') + 2;
		if ($n<7) {
			$query = "SELECT DISTINCT SUBSTRING_INDEX(att_name,'/',$n) AS description FROM att_conf WHERE att_name LIKE ".quote_smart($_REQUEST['key'].'/%')." ORDER BY description";
			$res = mysqli_query($db, $query);
			if (isset($_REQUEST['debug'])) echo "$query;<br>\n";
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				$key = $row['description'];
				$title = strtr($row['description'], $skipdomain);
				$fancyConfig[] = array('title' => basename($title), 'key'=> $key, "lazy" => true, "folder"=> true);
			}
		}
		else {
			$fc = array();
			$query = "SELECT * FROM att_conf,att_conf_data_type WHERE att_name LIKE ".quote_smart($_REQUEST['key'].'/%')." AND att_conf.att_conf_data_type_id=att_conf_data_type.att_conf_data_type_id ORDER BY att_name";
			$res = mysqli_query($db, $query);
			if (isset($_REQUEST['debug'])) echo "$query;<br>\n";
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				list($dim, $type, $io) = explode('_', $row['data_type']);
				$key = $row['att_conf_id'];
				$title = strtr($row['att_name'], $skipdomain);
				$res2 = mysqli_query($db, "SELECT * FROM att_history WHERE att_conf_id=".$row['att_conf_id']." ORDER BY time DESC LIMIT 20");
				$d = mysqli_fetch_all($res2, MYSQLI_ASSOC);
				$title = basename($title);
				$logging = !empty($d) && $d[0]['att_history_event_id']=='3';	
				if (!$logging) {
					if (empty($d)) $alt = 'archiving has never been active'; else {
						$alt = 'archiving not active now';
						foreach ($d as $data) {
							if ($data['att_history_event_id']=='3') {$alt = 'archiving not active since '.$t; break;}
							$t = $data['time'];
						}
					}
					$title = "<span style='text-decoration: line-through;' title='$alt'>$title</span>";
				}
				else $title = "<span data-laststart='{$d['time']}'>$title</span>";
				$fc[$title] = array('title' => $title, 'key'=> $key, "lazy" => false, "folder"=> false, "isArray" => $dim=="array", "icon"=>"./img/y0axis.png");
			}
			ksort($fc);
			$fancyConfig = array_values($fc);
		}
	}
	// file_put_contents('tree_log.txt', date("Y-m-d H:i:s").' - '.json_encode($tsArray).' - '.json_encode($fancyConfig)."\n", FILE_APPEND);

	if (!isset($_REQUEST['debug'])) header("Content-Type: application/json");
	echo json_encode($fancyConfig);
?>

