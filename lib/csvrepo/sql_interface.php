<?php

// ----------------------------------------------------------------
//
// sql_interface.php
//
// interface to various SQL libraries
//
// 25/10/2002 - LZ - First release
//
// ----------------------------------------------------------------

// $dbms = DataBase Management Server
// $dbms = 'pg'     (PostgreSQL)
// $dbms = 'fbsql'  (FrontBase)
// $dbms = 'msql'   (mSQL)
// $dbms = 'mssql'  (Microsoft SQL Server)
// $dbms = 'mysql'  (mySQL)
// $dbms = 'mysqli' (mySQL) [default]

	function return_bytes($val) {
		$val = trim($val);
		if (($len = strlen($val)) < 1) {
			return 0;
		}	
		$last = strtolower($val{strlen($val)-1});
		switch($last) {
			case 'g':
					 $val *= 1024;
			case 'm':
					 $val *= 1024;
			case 'k':
					 $val *= 1024;
		}
		return $val;
	}


// ----------------------------------------------------------------
// ----------------------------------------------------------------
class SqlInterface {

	var $dbms;
	var $db;
	var $stmtname;

	// ----------------------------------------------------------------
	// init SQL interface (select DBMS name, mySQL as default)
	function __construct($db_ms = 'mysqli') {
		$this->dbms = $db_ms;
	}

	// ----------------------------------------------------------------
	// Connection to DBMS
	function sql_connect($host, $user, $pwd, $dbname)
	{
		$sql_command = $this->dbms.'_connect';
		if ($this->dbms == "pg") {
			$str = "host='$host' port=5432 user='$user' password='$pwd' dbname='$dbname'";
			// $this->db = @pg_connect($str);
			$this->db = pg_connect($str);
			return $this->db;
		}
		$this->db = @$sql_command($host, $user, $pwd, $db);
		return $this->db;
	}

	// ----------------------------------------------------------------
	// Connection to DBMS
	function sql_pconnect($cmd)
	{
		$sql_command = $this->dbms.'_connect';
		$this->db = @$sql_command($cmd);
		return $this->db;
	}

	// ----------------------------------------------------------------
	// select_db
	function sql_select_db($database, $db)
	{
		$sql_command = $this->dbms.'_select_db';
		if ($this->dbms == 'mysqli') return @$sql_command($db, $database);
		return @$sql_command($database, $db);
	}

	// ----------------------------------------------------------------
	// select_db
	function real_escape_string($string)
	{
		$sql_command = $this->dbms.'_real_escape_string';
		if ($this->dbms == 'mysqli') return @$sql_command($this->db, $string);
		if ($this->dbms == 'pg') return @pg_escape_literal($this->db, $string);
		return @$sql_command($string);
	}

	
// mysqli_connect("host", "user", "password", "db");

	// ----------------------------------------------------------------
	// connect to DB server and open $database
	function sql_open($user, $pwd, $database)
	{
		if ($this->sql_connect("localhost", $user, $pwd) == FALSE) return FALSE;
		return $this->sql_select_db($database, $this->db);
	}	

	// ----------------------------------------------------------------
	// open_db
	function open($dbname, $host="127.0.0.1", $user="root", $pwd="") {
		if ($this->dbms == 'mysqli') {
      // echo "mysqli_connect($host, $user, $pwd, $dbname)<br>\n";
			$this->db = mysqli_connect($host, $user, $pwd, $dbname);
			// echo "opened<br>\n";
			if (mysqli_connect_errno()) {
        printf("Connect failed: %s\n", mysqli_connect_error());
        exit();
			}
			// echo "opened2<br>\n";
			// echo "\$this->db: {$this->db}<br>\n";
			return $this->db;
		}
		if ($this->dbms == "pg") {
			$this->db = pg_connect("dbname=$dbname host=$host user=$user password=$pwd");
			return $this->db;
		}
		$this->db = $this->sql_connect($host, $user, $pwd);
		if ($this->db === FALSE) {
			return $this->db;
		}
		// select db
		$this->sql_select_db($dbname, $this->db);
		return $this->db;
	}

	// ----------------------------------------------------------------
	// Send an SQL query
	function sql_query($query)
	{
		if ($this->dbms == "mysqli") {return @mysqli_query($this->db, $query);}
		if ($this->dbms == "pg") return @pg_query($this->db, $query);
		$sql_command = $this->dbms.'_query';
		return @$sql_command($query);
	}

	// ----------------------------------------------------------------
	// Fetch a result row as an associative array, a numeric array, or both
	function sql_fetch_array($result)
	{
		// also MSSQL_ASSOC exists even if undocumented
		$sql_assoc = strtoupper($this->dbms.'_ASSOC');
		$sql_command = $this->dbms.'_fetch_array';
		// echo "$sql_command, $sql_assoc<br>\n";
		if (defined($sql_assoc))
			return @$sql_command($result, constant($sql_assoc));
		else
			return @$sql_command($result);
	}

	// ----------------------------------------------------------------
	// last_insert_id
	function last_insert_id()
	{
		$sql_command = $this->dbms.'_insert_id';
		if ($this->dbms == "mysqli") {return @$sql_command($this->db);}
		if ($this->dbms == "pg") {
			$result = @pg_query($this->db, "SELECT LASTVAL()");
			// $result = @pg_query($this->db, "SELECT CURRVAL()");
			$val = @pg_fetch_all($result);
			return $val[0]['lastval'];
    	}
		return @$sql_command();
	}

	// ----------------------------------------------------------------
	// free_result
	function sql_free_result($result)
	{
		$sql_command = $this->dbms.'_free_result';
		return @$sql_command($result);
	}

	// ----------------------------------------------------------------
	// Closing connection to DB
	function sql_close()
	{
		$sql_command = $this->dbms.'_close';
		return @$sql_command($this->db);
	}

	// ----------------------------------------------------------------
	//	Returns the text of the error message from previous SQL operation
	function sql_error()
	{
		$sql_error = $this->dbms.'_error';
		if ($this->dbms == "mysqli") return $sql_error($this->db);
		if ($this->dbms == "pg") return pg_last_error();
		return $sql_error();
	}

	// ----------------------------------------------------------------
	//	Returns the text of the error number from previous SQL operation
	function sql_errno()
	{
		$sql_errno = $this->dbms.'_errno';
		if ($this->dbms == "mysqli") return $sql_errno($this->db);
		return $sql_errno();
	}

	// ----------------------------------------------------------------
	// test connection
	function sql_try($user, $pwd) {
		if (($res = $this->sql_connect("localhost", $user, $pwd)) != FALSE) {
			$this->sql_close();
		}
		return $res;
	}
	// ----------------------------------------------------------------
	// debug a variable
	function sql_var_debug($var, $name='') {
		echo "<p>";
		if ($name !== '') {
			echo "\$$name: ";
		}
		if (is_array($var)) {
			echo "<pre>"; print_r($var); echo "</pre></p>\n";
		}
		else {
			echo ($var===0? "0": $var)."</p>\n";
		}
	}

	// ----------------------------------------------------------------
	// SQL SELECT interface
	function sql_select_single_value($select, $from, $where='', $debug=false)
	{
		$query = "SELECT $select FROM $from";
		if (strlen($where) > 2) {
			$query = $query . " WHERE $where";
		}
		// echo "query: $query;<br>\n";
		// if ($this->dbms == "mysqli") $result = mysqli_query($this->db, $query);
		$result = $this->sql_query($query);
		if ($debug) {
			if ($debug=='hidden') echo "\n<!--\n";
			echo "$query;<br>\n";
			$err = $this->sql_error();
			if (strlen($err)) echo "Error: $err<br>\n";
			$this->sql_var_debug(array_shift($this->sql_fetch_array($result)));
			if ($debug=='hidden') echo "\n-->\n";
		}
		if ($result == FALSE) {
			return $result;
		}
		return array_shift($this->sql_fetch_array($result));
  }

	// ----------------------------------------------------------------
	// SQL SELECT interface
	function sql_select_single_row($select, $from, $where='', $debug=false)
	{
		$query = "SELECT $select FROM $from";
		if (strlen($where) > 2) {
			$query = $query . " WHERE $where";
		}
		// echo "query: $query;<br>\n";
		// if ($this->dbms == "mysqli") $result = mysqli_query($this->db, $query);
		$result = $this->sql_query($query);
		if ($debug) {
			if ($debug=='hidden') echo "\n<!--\n";
			echo "$query;<br>\n";
			$err = $this->sql_error();
			if (strlen($err)) echo "Error: $err<br>\n";
			$this->sql_var_debug($this->sql_fetch_array($result));
			if ($debug=='hidden') echo "\n-->\n";
		}
		if ($result == FALSE) {
			return $result;
		}
		return $this->sql_fetch_array($result);
  }

	// ----------------------------------------------------------------
	// SQL SELECT interface
	function sql_select($select, $from, $where='', $debug=false)
	{
		$query = "SELECT $select FROM $from";
		if (strlen($where) > 2) {
			$query = $query . " WHERE $where";
		}
		// echo "query: $query;<br>\n";
		if ($this->dbms == "pg") $result = pg_query($this->db, $query);
		$result = $this->sql_query($query);
		if ($debug) {
			if ($debug=='hidden') echo "\n<!--\n";
			echo "$query;<br>\n";
			$err = $this->sql_error();
			if (strlen($err)) echo "Error: $err<br>\n";
		}
		if ($result == FALSE) {
			return $result;
		}
		if ($this->dbms == "pg") {
			$data = pg_fetch_all($result);
		}
		else {
			$memory_limit = return_bytes(ini_get('memory_limit'));
			// echo "$memory_limit = return_bytes(ini_get('memory_limit'));<br>\n";
			$memory_usage = function_exists('memory_get_usage')? memory_get_usage(): 0;
			// echo "$memory_usage = function_exists('memory_get_usage')? memory_get_usage(): 0;<br>\n";
			$sql_command = $this->dbms.'_num_rows';
			$num_rows = @$sql_command($result);
			$i = 0;
			while ($d = $this->sql_fetch_array($result)) {
				// echo "<pre>"; print_r($d); echo "</pre>\n";
				// check if there is enough memory to be allocated for $data[]
				// use arbitrary value: count($d)*500 instead of sizeof($d)
				if (function_exists('memory_get_usage')) {
					if (return_bytes(ini_get('memory_limit'))-memory_get_usage() < count($d)*500) {
						echo "<b>Fatal error:</b> out of memeory<br />memory_limit: ".ini_get('memory_limit')."\n";
						if ($debug=='hidden') echo "\n-->\n";
						exit();
					}
				}
				$data[] = $d;
				$i++;
				if ($i==$num_rows) break;
			}
		}
		// echo "sql_free_result();<br>\n";
		$free = $this->sql_free_result($result);
		if (($this->dbms != "mysqli") and ($free == FALSE)) {
			if ($debug=='hidden') echo "\n-->\n";
			return FALSE;
		}
		// echo "sql_free_result(); done<br>\n";
		if (isset($data)) {
			if ($debug) {
				$this->sql_var_debug($data);
				if ($debug=='hidden') echo "\n-->\n";
			}
			return $data;
		}
		else {
			if ($debug) {
				echo "empty result<br>\n";
				if ($debug=='hidden') echo "\n-->\n";
			}
			return FALSE;
		}
	}

	// ----------------------------------------------------------------
	// SQL UPDATE interface
	function sql_update($table, $set, $where, $debug=false)
	{
		$query = "UPDATE $table SET $set";
		if (strlen($where) > 2) {
			$query = $query . " WHERE $where";
		}	
		if ($debug) {
			if ($debug=='hidden') echo "\n<!--\n";
			echo "query: $query <br>";
			if ($debug=='hidden') echo "\n-->\n";
		}
		return $this->sql_query($query);
	}

	// ----------------------------------------------------------------
	// SQL INSERT interface
	function sql_insert($table, $params, $values, $debug=false)
	{
		$query = "INSERT INTO $table ($params) VALUES ($values)";
		if ($debug) {
			if ($debug=='hidden') echo "\n<!--\n";
			echo "query: $query <br>";
			if ($debug=='hidden') echo "\n-->\n";
		}
		return $this->sql_query($query);
	}

	// ----------------------------------------------------------------
	// SQL DELETE interface
	function sql_delete($table, $where, $debug=false)
	{
		$query = "DELETE FROM $table";
		if (strlen($where) > 2) {
			$query = $query . " WHERE $where";
		}	
		if ($debug) {
			if ($debug=='hidden') echo "\n<!--\n";
			echo "query: $query <br>";
			if ($debug=='hidden') echo "\n-->\n";
		}
		return $this->sql_query($query);
	}

	// ----------------------------------------------------------------
	// SQL CREATE interface
	function sql_create($table, $columns)
	{
		$query = "CREATE TABLE `$table` ($columns)";
		return $this->sql_query($query);
	}

	// ----------------------------------------------------------------
	// SQL CREATE interface
	function sql_drop($table)
	{
		$query = "DROP TABLE `$table`";
		return $this->sql_query($query);
	}



function fetch($result) {
	$array = array();
	if($result instanceof mysqli_stmt) {
		$result->store_result();
		$variables = array();
		$data = array();
		$meta = $result->result_metadata();
		while($field = $meta->fetch_field())
			$variables[] = &$data[$field->name]; // pass by reference
		call_user_func_array(array($result, 'bind_result'), $variables);
		$i=0;
		while($result->fetch()) {
			$array[$i] = array();
			foreach($data as $k=>$v)
				$array[$i][$k] = $v;
			$i++;
			// don't know why, but when I tried $array[] = $data, I got the same one result in all rows
		}
	}
	else if($result instanceof mysqli_result) {
		while($row = $result->fetch_assoc())
			$array[] = $row;
	}
	return $array;
}



	// ----------------------------------------------------------------
	// SQL PREPARE interface
	function sql_prepare($query, $stmtname=false) {
		if ($this->dbms == "pg") {return @pg_prepare($this->db, $stmtname, $query);}
		$sql_command = $this->dbms.'_prepare';
		return @$sql_command($this->db, $query);
	}

	// ----------------------------------------------------------------
	// SQL EXECUTE interface
	function sql_execute($params, $stmtname=false) {
		if ($this->dbms == "pg") {
			$result = @pg_execute($this->db, $stmtname, $params);
			if (!$result) return $result;
			return pg_fetch_all($result);
		}
		if ($this->dbms == "mysqli") {
			//now we need to add references
			$tmp = array();
			$stmt = $params[0];
			foreach($params as $key => $value) $tmp[$key] = &$params[$key];
			// now use the tmp array
			call_user_func_array('mysqli_stmt_bind_param', $tmp);
			mysqli_stmt_execute($stmt);
			if (function_exists('mysqli_stmt_get_result')) {
				$res = $stmt->get_result();
				$data = array();
				while ($row = $res->fetch_array(MYSQLI_ASSOC)) {
					$data[] = $row;
				}
				return $data;
			}
			else {
				return fetch($stmt);
			}
		}
		// mysqli_stmt_bind_param($stmt, 'sssd', $code, $language, $official, $percent);
		$sql_command = $this->dbms.'_execute';
		return @$sql_command($this->db, $params);
	}

	// ----------------------------------------------------------------
	// SQL secure query: use prepare and execute
	function sql_secure($query, $params) {
		if (empty($this->stmtname)) $this->stmtname = 1; else $this->stmtname++;
		$this->sql_prepare($query, $this->stmtname);
		if (!empty($this->sql_error())) return false;
		return $this->sql_execute($params, $this->stmtname);
	}

	// ----------------------------------------------------------------
	//  Returns the text of the error message from previous SQL operation
	function sql_exec($query)
	{
		$sql_command = $this->dbms.'_exec';
		echo "$query<br>";
		if (function_exists($sql_command)) {
			return $sql_command($query, $this->db);
		}
		else {
			return $this->sql_query($query);
		}
	}

	// ----------------------------------------------------------------
	//  Fetch a result in an array
	function sql_fetch_all($result)
	{     
		// also MSSQL_ASSOC exists even if undocumented
		$sql_assoc = strtoupper($this->dbms.'_ASSOC');
		$sql_command = $this->dbms.'_fetch_all';
		if (function_exists($sql_command)) {
			if (defined($sql_assoc))
				return @$sql_command($result, constant($sql_assoc));
			else  
				return @$sql_command($result);
		}
		else {         
			$sql_command = $this->dbms.'_fetch_array';
			$data = array();
			$memory_limit = return_bytes(ini_get('memory_limit'));
			$memory_usage = function_exists('memory_get_usage')? memory_get_usage(): 0;
			while ($d = $sql_command($result, constant($sql_assoc))) {
				// check if there is enough memory to be allocated for $data[]
				// use arbitrary value: count($d)*500 instead of sizeof($d)
				if (function_exists('memory_get_usage')) {
					if (return_bytes(ini_get('memory_limit'))-memory_get_usage() < count($d)*500) {
						echo "<b>Fatal error:</b> out of memeory<br />memory_limit: ".ini_get('memory_limit')."\n";
						exit();
					}
				}
				$data[] = $d;
			}
			return $data;
		}
	}

	// ----------------------------------------------------------------
	// return list of columns in table
	function sql_list_columns($table) {
		if ($this->dbms == "sqlite") {
			$query = "PRAGMA table_info($table)";
		}
		else if ($this->dbms == "pg") {
/*
    SELECT attnum,attname,typname,atttypmod-4,attnotnull,atthasdef ,adsrc AS def
    FROM pg_attribute, pg_class, pg_type, pg_attrdef
    WHERE pg_class.oid=attrelid AND pg_type.oid=atttypid AND attnum>0 AND pg_class.oid=adrelid AND adnum=attnum
    AND atthasdef='t' AND lower(relname)='$table'
    UNION
*/
			$query = "
    SELECT attnum,attname,typname,atttypmod-4,attnotnull,atthasdef ,'' AS def
    FROM pg_attribute, pg_class, pg_type WHERE pg_class.oid=attrelid
    AND pg_type.oid=atttypid AND attnum>0 AND atthasdef='f' AND lower(relname)='$table' ";                                             
		}
		else {
			$query = "SHOW COLUMNS FROM $table";
		}
		$result = $this->sql_query($query);
		return $this->sql_fetch_all($result);
	}

	// ----------------------------------------------------------------
	// return list of tables in db
	function sql_list_tables() {
		$tables = array();
		if ($this->dbms == "sqlite") {
			$query = "SELECT name FROM sqlite_master WHERE (type = 'table')";
			if ($res = $this->sql_query($query)) {
				while ($row = $this->sql_fetch_array($res)) {
					$tables[] = $row;
				}
			}
		}
		else if ($this->dbms == "pg") {
			if ($res = $this->sql_query(" SELECT relname AS tablename
                    FROM pg_class WHERE relkind IN ('r')
                    AND relname not like 'pg_%' AND relname NOT LIKE 
                    'sql_%' ORDER BY tablename")) {
				// while ($row = $this->sql_fetch_array($res, constant($sql_num))) {
				while ($row = $this->sql_fetch_array($res)) {
					$r = array_values($row);
					// echo "tab: {$r[0]}<br>";
					$tables[] = array("name"=>$r[0]);
					if (count($tables)>=100) break;
				}
			}
			else {
				echo $this->sql_error();
			}
			// echo "res: "; print_r($res);
		}
		else {
			$sql_num = strtoupper($this->dbms.'_NUM');
			$query = "SHOW TABLES";
			// echo "query: $query<p>";
			if ($res = $this->sql_query($query)) {
				// while ($row = $this->sql_fetch_array($res, constant($sql_num))) {
				while ($row = $this->sql_fetch_array($res)) {
					$r = array_values($row);
					// echo "tab: {$r[0]}<br>";
					$tables[] = array("name"=>$r[0]);
					if (count($tables)>=100) break;
				}
			}
			else {
				echo $this->sql_error();
			}
			// echo "res: "; print_r($res);
		}
		return $tables;
	}

}



	//
	// ----------------------------------------------------------------
	// the following lines are only for test pourpouse, to be removed
	// ----------------------------------------------------------------
	//
/*
	$HTML_header = "
		<html>
		<head>
		<title>sql-test</title>
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">
		</head>
		<body bgcolor=\"#e5dba0\">
	";
	echo $HTML_header;

	$sql = new SqlInterface('mysqli');

	// open db
	$sql->open("database", "host", "user", "password") or die("sql_open error <br>".$sql->sql_error());
	echo "sql->open: done <br>\n";

	// create, select, drop... something, use $_GET as parameters
	if (isset($_GET['create'])) {
		$data = $sql->sql_create($create, $columns) or die("Query failed <br>".$sql->sql_error());
	}
	if (isset($_GET['insert'])) {
		$data = $sql->sql_insert($insert, $params, $values) or die("Query failed <br>".$sql->sql_error());
	}
	if (isset($_GET['delete'])) {
		$data = $sql->sql_delete($delete, $where) or die("Query failed <br>".$sql->sql_error());
	}
	if (isset($_GET['update'])) {
		$data = $sql->sql_update($update, $set, $where) or die("Query failed <br>".$sql->sql_error());
	}
	if (isset($_GET['select'])) {
		echo "\$sql->sql_select({$_GET['select']}, {$_GET['from']}, {$_GET['where']})<br>\n"; 
		$data = $sql->sql_select($_GET['select'], $_GET['from'], $_GET['where']); // or die("Query failed <br>".$sql->sql_error());
		echo "<pre>"; print_r($data); echo "</pre><br/>";
	}
	if (isset($_GET['drop'])) {
		$data = $sql->sql_drop($drop) or die("Query failed <br>".$sql->sql_error());
	}
	

	// Closing connection to DB
	$sql->sql_close() or die("SQL close failed <br>".$sql->sql_error());


 */

?>
