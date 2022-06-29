<?php

require_once("./hdbtimescale_conf.php");

	$buffer_filename = 'tree_root.json';
	$buffer_threshold = 3600;

	$fancyConfig = $fc = array();
	$rowArray = array();

	$db = pg_connect("host=".HOST." port=".PORT." user=".USERNAME." password=".PASSWORD." dbname=".DBNAME);

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
			$value = $separator.$value.$separator;
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
		if (isset($_REQUEST['debug'])) {echo __LINE__." expand_children(\$tree_base, $key, $title, $tree_index);<br>\n"; }
		if ($tree_index==8) {
			$icon = "./img/y0axis.png";
			$query = "SELECT * FROM att_conf,att_conf_type WHERE att_name LIKE '%$key' AND att_conf.att_conf_type_id=att_conf_type.att_conf_type_id ORDER BY att_name";
			$res = pg_query($db, $query);
			$rw = pg_fetch_all($res);
			if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br>\n"; debug($rw);}
			$row = $rw[0];
			list($dim, $type, $io) = explode('_', $row['name']);
			if (isset($tree_base[$key])) {
				$icon = is_numeric($tree_base[$key])? "./img/y".($tree_base[$key])."axis.png": "./img/{$tree_base[$key]}.png";
				if (($dim=="array") and ($icon == "./img/y1axis.png")) $icon = "./img/surface.png";
				if (isset($_REQUEST['debug'])) {debug($key, 'key'); debug($tree_base, 'tree_base');}
			}
			$query = "SELECT * FROM att_history WHERE att_conf_id=".$row['att_conf_id']." ORDER BY time DESC LIMIT 20";
			$res2 = pg_query($db, $query);
			$d = pg_fetch_all($res2);
			if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br>\n"; debug($d);}
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
		$query = "SELECT DISTINCT split_part(att_name, '/', $tree_index) AS description FROM att_conf WHERE att_name LIKE '%/$key%' ORDER BY description";
		$res2 = pg_query($db, $query);
		if ($res2 !== false) {
			$rw = pg_fetch_all($res2);
			if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br><pre>"; print_r($rw); echo "</pre>";}
			foreach ($rw as $row) {
				$key2 = $row['description'];
				$title2 = strtr($row['description'], $skipdomain);
				if (isset($_REQUEST['debug'])) {echo __LINE__." key2: $key2, title2: $key/$title2<br>";}
				if (isset($tree_base["$key/$title2"])) {
					$title3 = basename($title2);
					$query = "SELECT type AS data_type, att_conf.att_conf_id, att_name FROM att_conf,att_conf_type WHERE att_name LIKE '%$key/$key2%' AND att_conf.att_conf_type_id=att_conf_type.att_conf_type_id ORDER BY att_name";
					$res = pg_query($db, $query);
					$conf_row = pg_fetch_all($res, PGSQL_ASSOC);
					if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br>\n"; debug($conf_row);}
					list($dim, $type, $io) = explode('_', $conf_row[0]['name']);
					$query = "SELECT * FROM att_history WHERE att_conf_id=".$conf_row[0]['att_conf_id']." ORDER BY time DESC LIMIT 5";
					$res3 = pg_query($db, $query);
					$d = pg_fetch_all($res3);
					if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br>\n"; debug($d);}
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
					$children[$tree_index>5? $title3: $title2] = expand_children($tree_base, "$key/$key2", $title2, $tree_index+1);
				}
				else {
					$title2 = basename($title2);
					if ($tree_index==7) {
						$query = "SELECT type AS data_type, att_conf.att_conf_id FROM att_conf,att_conf_type WHERE att_name LIKE '%$key/$key2' AND att_conf.att_conf_type_id=att_conf_type.att_conf_type_id ORDER BY att_name";
						$res = pg_query($db, $query);
						$conf_row = pg_fetch_all($res, PGSQL_ASSOC);
						if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br>\n"; debug($conf_row);}
						list($dim, $type, $io) = explode('_', $conf_row[0]['name']);
						$query = "SELECT * FROM att_history WHERE att_conf_id=".$conf_row[0]['att_conf_id']." ORDER BY time DESC LIMIT 5";
						$res3 = pg_query($db, $query);
						$d = pg_fetch_all($res3);
						if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br>\n"; debug($d);}
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
						$children[$title2] = array('title' => $title2, 'key'=>$conf_row[0]['att_conf_id'], "folder" => false, "isArray" => $dim=="array", "lazy" => false, "icon" => "./img/y0axis.png");
						if (isset($_REQUEST['debug'])) {echo __LINE__." \$children[$title2];<br>\n"; debug($children[$title2]);}
					}
					else {
						$children[$title2] = array('title' => $title2, 'key'=>"$key/$key2", "folder" => true, "lazy" => true, "expanded" => false);
					}
				}
			}
		}
		ksort($children);
		if (isset($_REQUEST['debug'])) {debug($children, __LINE__.' children sorted');}
		return array('title' => basename($title), 'key'=>$key, "folder" => true, "lazy" => false, "expanded" => true, "children"=>array_values($children));
	}



	if (isset($_REQUEST['query'])) {
		$res = pg_query($db, $_REQUEST['query']);
		if (!$res) 
			echo "{$_REQUEST['query']};<br>\n";
		else while ($row = pg_fetch_array($res)) {
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
		$where = "WHERE split_part(att_name, '/', 4) IN (".implode(',', $family).")";
	}
	if (isset($_REQUEST['reversekey'])) {
		$id = explode('[', $_REQUEST['reversekey']);
		$res = pg_query($db, "SELECT att_name FROM att_conf WHERE att_conf_id=".quote_smart($id[0]));
		$row = pg_fetch_array($res);
		$fancyConfig = strtr($row['att_name'], $skipdomain);
	}
	else if (isset($_REQUEST['search']) and (strlen($_REQUEST['search'])>0)) {
		$s = explode(';', quote_smart($_REQUEST['search'], ''));
		foreach ($s as $search) {
			$res = pg_query($db, "SELECT att_conf_id, att_name FROM att_conf WHERE att_name LIKE '%$search%' ORDER BY att_name");
		 	while ($row = pg_fetch_array($res)) {
				$fancyConfig[$row['att_conf_id']] = strtr($row['att_name'], $skipdomain);
			}
		}
		if (empty($fancyConfig)) $fancyConfig = json_decode ("{}");
	}
	else if (isset($_REQUEST['search_similar']) and (strlen($_REQUEST['search_similar'])>0)) {
		$s = explode(';', quote_smart($_REQUEST['search_similar'], ''));
		$threshold = isset($_REQUEST['threshold'])? $_REQUEST['threshold']: 0.08;
		$res = pg_query($db, "SELECT att_conf_id, att_name FROM att_conf ORDER BY att_name");
		while ($row = pg_fetch_array($res)) {
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
			$res = pg_query($db, "SELECT * FROM att_conf WHERE att_name LIKE '%$search' ORDER BY att_name");
		 	while ($row = pg_fetch_array($res)) {
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
				$query = "SELECT att_name FROM att_conf WHERE att_conf_id={$att_conf_id[0]}";
				$res = pg_query($db, $query);
				$rw = pg_fetch_all($res);
				if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br>\n"; debug($rw);}
				$row = $rw[0];
				$tok_base = '';
				$nameArray = explode('/', strtr($row['att_name'], $skipdomain));
				foreach ($nameArray as $tok) {
					$tok_base .= ($tok_base==''? '':'/').$tok;
					$tree_base[$tok_base] = $y_index;
					if (strpos($tok_base, '] ')!==false) {
						list($trash, $tb) = explode('] ', $tok_base);
						$tree_base[$tb] = $y_index;
					}
				}
			}
			$tree_index = 4;
			$query = "SELECT DISTINCT split_part(att_name, '/', $tree_index) AS description FROM att_conf $where ORDER BY description";
			$res = pg_query($db, $query);
			$rw = pg_fetch_all($res);
			if (isset($_REQUEST['debug'])) {echo __LINE__." $query;<br>\n"; debug($rw);debug($tree_base, 'tree_base');}
			foreach ($rw as $row) {
				$key = $row['description'];
				$title = strtr($row['description'], $skipdomain);
				if (isset($_REQUEST['debug'])) {echo __LINE__." title: $title;<br>\n";}
				if (isset($tree_base[$title])) {
					$fancyConfig[] = expand_children($tree_base, $key, $title, $tree_index+1);
				}
				else {
					$fancyConfig[] = array('title' => basename($title), 'key'=>$key, "lazy" => true, "folder" => true);
				}
			}
		}
		else {
			$query = "SELECT DISTINCT split_part(att_name, '/', 4) AS description FROM att_conf $where ORDER BY description";
			$res = pg_query($db, $query);
			$rw = pg_fetch_all($res);
			if (isset($_REQUEST['debug'])) {echo __LINE__." empty(\$hdb=$hdb)<br>$query;<br>\n"; debug($rw);}
			foreach ($rw as $row) {
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
			$res = pg_query($db, $query);
			if ($row = pg_fetch_array($res)) {
				$fancyConfig[] = array('title' => $key, 'id'=>$row['att_conf_id']);
			}
			else {
				$fancyConfig[] = array('title' => $key, 'id'=>null);
			}
		}
		if (isset($_REQUEST['debug'])) {echo "$query;<br>\n";}
	}
	else if (!isset($_REQUEST['key']) or (strlen($_REQUEST['key'])==0) or ($_REQUEST['key']=="root")) {
		$query = "SELECT DISTINCT concat(split_part(att_name, '/', 1),'/',split_part(att_name, '/', 2),'/',split_part(att_name, '/', 3),'/',split_part(att_name, '/', 4)) AS description FROM att_conf $where ORDER BY description";
		$res = pg_query($db, $query);
		if (isset($_REQUEST['debug'])) echo "q8<br>$query;<br>\n";
		$dataAll = pg_fetch_all($res);
		if (isset($_REQUEST['debug'])) {echo "<pre>"; print_r($dataAll); echo "</pre>";}
		foreach ($dataAll as $row) {
			$key = $row['description'];
			$title = strtr($row['description'], $skipdomain);
			$fancyConfig[] = array('title' => basename($title), 'key'=>$key, "lazy" => true, "folder" => true);
		}
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
		if ($n<4) {
			$q = array();
			for ($i=4; $i<$n+4; $i++) {
				$q[] = "split_part(att_name, '/', $i)";
			}
			$query = "SELECT DISTINCT concat(".implode(",'/',",$q).")AS description FROM att_conf WHERE att_name LIKE ".quote_smart('%'.$_REQUEST['key'].'/%')." ORDER BY description";
			$res = pg_query($db, $query);
			if (isset($_REQUEST['debug'])) echo "q9 $n<br>$query;<br>\n";
			$dataAll = pg_fetch_all($res);
			if (isset($_REQUEST['debug'])) {echo "<pre>"; print_r($dataAll); echo "</pre>";}
			foreach ($dataAll as $row) {
				$key = $row['description'];
				$title = strtr($row['description'], $skipdomain);
				$fancyConfig[] = array('title' => basename($title), 'key'=> $key, "lazy" => true, "folder"=> true);
			}
		}
		else {
			$fc = array();
			$query = "SELECT * FROM att_conf,att_conf_type WHERE att_name LIKE ".quote_smart('%'.$_REQUEST['key'].'/%')." AND att_conf.att_conf_type_id=att_conf_type.att_conf_type_id ORDER BY att_name";
			if (isset($_REQUEST['debug'])) echo "q10 $query;<br>\n";
			$res = pg_query($db, $query);
			$dataAll = pg_fetch_all($res);
			if (isset($_REQUEST['debug'])) {echo "<pre>"; print_r($dataAll); echo "</pre>";}
			foreach ($dataAll as $row) {
				list($dim, $type, $io) = explode('_', $row['data_type']);
				$key = $row['att_conf_id'];
				$title = strtr($row['att_name'], $skipdomain);
				$res2 = pg_query($db, "SELECT * FROM att_history WHERE att_conf_id=".$row['att_conf_id']." ORDER BY time DESC LIMIT 20");
				$d = pg_fetch_all($res2);
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
