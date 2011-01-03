<?php

// -- relative path of where the cruddy_mysql directory is from where this file is (must be publicly accessed)
require_once("cruddy_mysql/cruddy_mysql.php");

ob_start();
$crudAdmin = new cruddyMysqlAdmin();
if (!$crudAdmin->adminDBExists()) {
	if (!isset($_GET['admin'])) {
		setcookie("redirect", "", false);
		setcookie("tempAdmin", "1", time()+3600*24*7);
		$crudAdmin->redirect($_SERVER['PHP_SELF']."?admin=true&initialize_server=true");
		exit;
	}
} else {
	 
	// -- auto redirect based on connection status of left off steps
	if (is_array($crudAdmin->currentAdminDB['crud']['mysql_server_names'])) {
		foreach ($crudAdmin->currentAdminDB['crud']['mysql_server_names'] as $mySQLServer=>$server) {
			if (!@mysql_connect($crudAdmin->currentAdminDB['crud']['mysql_server_names'][$mySQLServer].":".$crudAdmin->currentAdminDB['crud']['mysql_ports'][$mySQLServer],$crudAdmin->currentAdminDB['crud']['mysql_user_names'][$mySQLServer],$crudAdmin->currentAdminDB['crud']['mysql_passwords'][$mySQLServer])) {
				if (!isset($_GET['initialize_server'])) {
					$crudAdmin->redirect($_SERVER['PHP_SELF']."?admin=true&initialize_server&server=$mySQLServer&msg=There was a problem with your mySQL server credentials.  Please update them.");
					exit;
				}
			}
		}
	}
	if ($crudAdmin->currentAdminDB['crud']['completed_step'] != 'All' && count($_GET) == 0 ) {
		setcookie("tempAdmin", "1", time()+3600*24*7);
		$crudAdmin->redirect($_SERVER['PHP_SELF']."?admin=true&".$crudAdmin->steps[$crudAdmin->currentAdminDB['crud']['completed_step']+1]."=true");
		exit;
	}
}


if (isset($_GET['logoff'])) {
	setcookie("current_user", "0", time()-3600);
	setcookie("current_role", "0", time()-3600);
	$crudAdmin->redirect("?");
}

$crudAdmin->paintHead();

if (!isset($_GET['admin']) && isset($_COOKIE['current_user'])) {
	
	if (file_exists($crudAdmin->functionsFile)) {
		 require_once($crudAdmin->functionsFile);
	}
	
	if (file_exists($crudAdmin->functionsDrawFile)) {
		 require_once($crudAdmin->functionsDrawFile);
	}
	
} elseif (!isset($_COOKIE['current_user']) && !isset($_GET['admin'])) {
	if ($_REQUEST['username'] && $_REQUEST['password']) {
		$crudAdmin->LoginToCruddyMysql($_REQUEST['username'],$_REQUEST['password']);
	} else {	
		$crudAdmin->displayLoginForm();
	}
} elseif ((isset($_GET['admin']) && $crudAdmin->cruddyAdministrator) || (isset($_COOKIE['tempAdmin']))) {
	
	$crudAdmin->handleAdminPages();
	
}

echo "
</body>
</html>
";
ob_end_flush();

?>
