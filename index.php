<?php

// -- relative path of where the cruddy_mysql directory is from where this file is (must be publicly accessed)
define("RELATIVE_CRUD_CLASS_LOCATION","cruddy_mysql/");
if ($_SERVER['SERVER_PORT'] != '80') {
	if ($_SERVER['SERVER_PORT'] == 443) {
		$secure = 's';
	}
	$port = ":".$_SERVER['SERVER_PORT'];
}
$paths = explode('/',$_SERVER['REQUEST_URI']);
unset($paths[sizeof($paths)-1]);
unset($paths[0]);

define("PUBLIC_CRUD_CLASS_LOCATION",'http'.$secure.'://'.$_SERVER['SERVER_NAME'].$port.'/'.implode('/',$paths).'/'.RELATIVE_CRUD_CLASS_LOCATION);

require_once(RELATIVE_CRUD_CLASS_LOCATION."cruddy_mysql.php");

ob_start();
$crudAdmin = new cruddyMysqlAdmin();
if (!$crudAdmin->adminDBExists()) {
	if (!isset($_GET['admin'])) {
		setcookie("redirect", "0", time()-3600);
		setcookie("tempAdmin", "1", time()+3600*24*7);
		header("Location: ".$_SERVER['PHP_SELF']."?admin=true&initialize_server=true");
		exit;
	}
} else {
	 
	// -- auto redirect based on connection status of left off steps
	if (is_array($crudAdmin->currentAdminDB['crud']['mysql_server_names'])) {
		foreach ($crudAdmin->currentAdminDB['crud']['mysql_server_names'] as $mySQLServer=>$server) {
			if (!@mysql_connect($crudAdmin->currentAdminDB['crud']['mysql_server_names'][$mySQLServer].":".$crudAdmin->currentAdminDB['crud']['mysql_ports'][$mySQLServer],$crudAdmin->currentAdminDB['crud']['mysql_user_names'][$mySQLServer],$crudAdmin->currentAdminDB['crud']['mysql_passwords'][$mySQLServer])) {
				if (!isset($_GET['initialize_server'])) {
					header("Location: ".$_SERVER['PHP_SELF']."?admin=true&initialize_server&server=$mySQLServer&msg=There was a problem with your mySQL server credentials.  Please update them.");
					exit;
				}
			}
		}
	}
	if ($crudAdmin->currentAdminDB['crud']['completed_step'] != 'All' && count($_GET) == 0 ) {
		setcookie("tempAdmin", "1", time()+3600*24*7);
		header("Location: ".$_SERVER['PHP_SELF']."?admin=true&".$crudAdmin->steps[$crudAdmin->currentAdminDB['crud']['completed_step']+1]."=true");
		exit;
	}
}


if (isset($_GET['logoff'])) {
	setcookie("current_user", "0", time()-3600);
	setcookie("current_role", "0", time()-3600);
	header('Location: ?');
}

if (isset($crudAdmin->currentAdminDB['crud']['console_name'])) {
	$desc = $crudAdmin->currentAdminDB['crud']['console_name']." Administrator";
} else {
	$extra = (isset($_GET['admin'])) ? " Configuration Setup" : "";
	$desc = "CRUDDY MYSQL " . $extra;
}

if (isset($_GET['admin'])) {
	$div="<div id=\"clear\"></div>";
} else {
	$bodyOnKeyPress = "onkeypress=\"handleEscapeKey(event);\"";
}

echo '
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<title>'.$desc.'</title>
	<link rel="stylesheet" type="text/css" href="'.$crudAdmin->displayGlobalCSS().'" /> 
	<link rel="stylesheet" type="text/css" href="'.$crudAdmin->displayThemeCSS().'" /> 
	</head>
	<script type="text/javascript" src="'.PUBLIC_CRUD_CLASS_LOCATION.'scripts/prototype.js"></script>
	<script type="text/javascript" src="'.PUBLIC_CRUD_CLASS_LOCATION.'scripts/cruddy_mysql.js"></script>
	<body '.$bodyOnKeyPress.'>
	';

if (isset($_GET['msg'])) {
	echo "<h3 style='color:#E63C1E;font-size:1.5em'>".$_GET['msg']."</h3>";
}

echo "<div style=\"float:left;padding-right:16px;\"><h1>";
echo (isset($_GET['admin'])) ? "" : "<a href=\"$_SERVER[PHP_SELF]\">";
echo $desc;
echo (isset($_GET['admin'])) ? "" : "</a>";
echo "</h1></div>$div";


	
if (!isset($_GET['admin']) && isset($_COOKIE['current_user'])) {
	
	if ($crudAdmin->cruddyAdministrator) {
		$serverOptions = "<option value=\"\" selected>Select a Server</option>";
		foreach ($crudAdmin->currentAdminDB['crud']['mysql_server_names'] as $key=>$value) {
			$serverOptions .= "<option value=\"$key\">Edit: $key</option>";
		}
		$serverOptions .= "<option value=\"add\">Add a new server</option>";
		$databaseOptions = "<option value=\"\" selected>Select a Database</option>";
		foreach ($crudAdmin->currentAdminDB['crud']['mysql_databases'] as $values) {
			foreach ($values as $database) {
				$databaseOptions .= "<option value=\"$database\">Edit: $database</option>";
			}
		}
		$fieldsOptions = "<option value=\"\" selected>Select a Table</option>";
		foreach ($crudAdmin->currentAdminDB['crud']['mysql_databases'] as $server=>$values) {
			foreach ($values as $database) {
				$fieldsOptions .= "<optgroup label='$server -> $database'>";
				foreach ($crudAdmin->currentAdminDB[CRUD_FIELD_CONFIG] as $key=>$value) {
					if (stristr($key,$database."_")) {
						$fieldsOptions .= "<option class=\"$database\" value=\"".$value[TABLE_CONFIG][OBJECT_TABLE]."\" title=\"".$server."\">Edit fields: ".$value[TABLE_CONFIG][OBJECT_TABLE]."</option>";
					}
				}
				$fieldsOptions .= "</optgroup>";
			}
		}
		$editThemeLink = (isset($crudAdmin->currentAdminDB['crud']['theme'])) ? "&edit=".$crudAdmin->currentAdminDB['crud']['theme'] : "";
	}
	
	$groupLinks = "
		<div style=\"float:left\" id=\"menu1\" class=\"menu\">
			<div id=\"m-top\">
				<ul id=\"m-top-ul1\">
					<li><a href=\"?\">Home</a></li>\n";
	if (isset($crudAdmin->currentAdminDB['crud']['groups']) && $crudAdmin->currentAdminDB['crud']['group_tables'] == 1) {
		foreach ($crudAdmin->currentAdminDB['crud']['groups'] as $k=>$v) {
			if (!in_array($k,$crudAdmin->currentAdminDB['crud']['roles'][$_COOKIE['current_role']]['groups'])) {
				continue;
			}
			$groupLinks .= "\t\t\t\t\t<li><a href=\"?group=$k\">$k</a></li>\n";
		}
		$groupLinks .= "\t\t\t\t\t<li><a href=\"?logoff\">Logoff</a></li>
				</ul>	
			</div>	
			<div id=\"m-slider\">
				<div id=\"slider1\"></div> 	
			</div>		
		</div>";
	} else {
		$groupLinks = "";
	}
	
	echo $groupLinks;
	
	if ($crudAdmin->cruddyAdministrator) {
		echo "		
		<select id=\"serverList\" style=\"display:none;position:absolute;\" onchange=\"if (this.value != 'new'){document.location = '$_SERVER[PHP_SELF]?admin=true&initialize_server&edit=' + this.value;}else{document.location = '$_SERVER[PHP_SELF]?admin=true&newserver=1';}\">
			$serverOptions
		</select>
		<select id=\"databaseList\" style=\"display:none;position:absolute;\" onchange=\"document.location = '$_SERVER[PHP_SELF]?admin=true&select_tables&edit=' + this.value;\">
			$databaseOptions
		</select>
		<select id=\"FieldList\" style=\"display:none;position:absolute;\" onchange=\"document.location = '$_SERVER[PHP_SELF]?admin=true&select_fields&edit=' + this.value + '&server=' + this.options[this.selectedIndex].title + '&database=' + this.options[this.selectedIndex].className;\">
			$fieldsOptions
		</select>
		<div style=\"float:left\" id=\"menu2\" class=\"menu2\">
			<div id=\"m-top\">
				<ul id=\"m-top-ul2\">
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);$('serverList').style.left = $('slider2').style.marginLeft;$('serverList').style.display = 'inline';\" href=\"#\">Servers</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=true&select_database=true&edit=true';\" href=\"#\">Databases</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);$('databaseList').style.left = $('slider2').style.marginLeft;$('databaseList').style.display = 'inline';\" href=\"#\">Tables</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=true&select_groups=true&edit=true';\" href=\"#\">Groups</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=1&select_roles&edit=true';\" href=\"#\">Roles</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=1&select_users&edit=true';\" href=\"#\">Users</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=true&select_theme=true$editThemeLink';\" href=\"#\">Themes</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);$('FieldList').style.left = $('slider2').style.marginLeft;$('FieldList').style.display = 'inline';\" href=\"#\">Fields</a></li>
				</ul>	
			</div>	
			<div id=\"m-slider\">
				<div id=\"slider2\"></div> 	
			</div>		
		</div>
		";
	}
	echo "<div id=\"clear\"></div>";
	
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
		echo "<br/>";	
		echo "<br/>";	
		echo "<br/>";	
		echo "<br/>";	
		echo "<br/>";	
		echo "<br/>";	
		$crudAdmin->displayLoginForm();
	}
} elseif ((isset($_GET['admin']) && $crudAdmin->cruddyAdministrator) || (isset($_COOKIE['tempAdmin']))) {
	
	echo '<script type="text/javascript" src="'.PUBLIC_CRUD_CLASS_LOCATION.'scripts/crud_admin.js"></script>';
	echo '<span style="font-size:1.2em;">';
	// -- step 1
	if (isset($_GET['initialize_server'])) {	
		if (isset($_GET['store_database'])) {
			$crudAdmin->storeDatabaseConnectionForm();
		} else {
			$crudAdmin->displayDatabaseConnectionForm();
		}
	}
	 
	// -- step 2
	if (isset($_GET['select_database'])) {	
		if (isset($_GET['store_database'])) {
			$crudAdmin->storeDatabaseSelectionForm();
		} else {
			$crudAdmin->displayDatabaseSelectionForm();
		}
	}
	
	// -- step 3
	if (isset($_GET['select_tables'])) {	
		if (isset($_GET['store_database'])) {
			$crudAdmin->storeTableSelectionForm();
		} else {
			$crudAdmin->displayTableSelectionForm();
		}
	}
	
	// -- step 4
	if (isset($_GET['select_groups'])) {	
		if (isset($_GET['store_database'])) {
			$crudAdmin->storeGroupSelectionForm();
		} else {
			$crudAdmin->displayGroupSelectionForm();
		}
	}  
	
	// -- step 5
	if (isset($_GET['select_roles'])) {	
		if (isset($_GET['store_database'])) {
			$crudAdmin->storeRolesSelectionForm();
		} else {
			$crudAdmin->displayRolesSelectionForm();
		}
	}
	 
	// -- step 6
	if (isset($_GET['select_users'])) {	
		if (isset($_GET['store_database'])) {
			$crudAdmin->storeUserSelectionForm();
		} else {
			$crudAdmin->displayUserSelectionForm();
		}
	}
	
	// -- step 7
	if (isset($_GET['select_theme'])) {	
		if (isset($_GET['store_database'])) {
			$crudAdmin->storeThemeSelectionForm();
		} else {
			$crudAdmin->displayThemeSelectionForm();
		}
	}
	
	// -- step 8 (done after everything on a per table basis)
	if (isset($_GET['select_fields'])) {	
		if (isset($_GET['store_database'])) {
			$crudAdmin->storeFieldSelectionForm();
		} else {
			$crudAdmin->displayFieldSelectionForm();
		}
	}
	 
	// -- ajax fields
	if (isset($_GET['find_fields'])) {	
		$crudAdmin->displayFieldsAJAX();
	}
	 
	echo '</span>';
}

echo "
</body>
</html>
";
ob_end_flush();

?>
