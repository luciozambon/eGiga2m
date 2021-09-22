<?php
	$skipdomain = array(); // part of naming can be hidden inserting it here

	// if you don't use conf parameters remove it and keep only one series of defines
	if (isset($_REQUEST['conf'])) {
		if ($_REQUEST['conf']=='config_1') {
			define("HOST", "host");
			define("USERNAME", "username");
			define("PASSWORD", "password");
			define("DB", "db");
			define("DBTYPE", "hdb");
			// define("LOG_REQUEST", "../../log/config_1.log");
		}
		if ($_REQUEST['conf']=='config_2') {
			define("HOST", "host");
			define("USERNAME", "username");
			define("PASSWORD", "password");
			define("DB", "db");
			define("DBTYPE", "hdb");
			// define("LOG_REQUEST", "../../log/config_2.log");
		}
	}
	else {
		  define("HOST", "hostname");
		  define("USERNAME", "username");
		  define("PASSWORD", "password");
		  define("DB", "hdb");
		  define("DBTYPE", "hdb");
		  // define("LOG_REQUEST", "../../log/hdbpp.log");
	}
?>
