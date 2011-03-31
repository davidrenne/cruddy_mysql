<?php

$pwd = dirname(__FILE__);
define("ABS_PATH_TO_CRUDDY_MYSQL_FOLDER",dirname($_SERVER['PHP_SELF']).'/cruddy_mysql/');
define("ABS_PATH_HASH",substr(md5(dirname($_SERVER['PHP_SELF']).'/cruddy_mysql/'),0,8));
ini_set("memory_limit","256M");
set_time_limit(0);
set_magic_quotes_runtime(false); // -- dude just dont use magic quotes...
function get_microtime_ms() {
	list($usec, $sec) = explode(" ",microtime());
	return ((float)$usec + (float)$sec);
}
/* constants */

define("GET_COLUMNS_SQL", "show full columns from %s");
define("GET_TABLES_SQL", "show full tables");
define("GET_DATABASES_SQL", "show databases");
define("UPDATE_SQL","update %s set %s where %s");
define("INSERT_SQL","insert into %s(%s) values(%s)");
define("TABLE_CONFIG","tableDef");
define("CRUD_FIELD_CONFIG","crudConfig");

// table level keys and configs
define("OBJECT_DESC","description"); //high level table description (Keep short)
define("OBJECT_ACTIONS","actions"); //array of possible CRUD actions used in switch of controller page
define("OBJECT_DEFAULT_ORDER","defaultorder"); //for a generic_read function to handle how the records should be initially sorted
define("OBJECT_READ_FILTER","filterrecords"); //initial filter that the main recordset loads as
define("OBJECT_HIDE_NEW_LINK","hidenewlink"); //a flag to say whether the table should have a "New" link associated with it
define("OBJECT_HIDE_VIEW_LINK","hideviewlink"); //a flag to say whether the table should have a "New" link associated with it
define("OBJECT_HIDE_SEARCH_LINK","hidesearchlink");
define("OBJECT_HIDE_DETAILS_LINK","hidedetailslink");
define("OBJECT_HIDE_EDIT_LINK","hideeditlink");
define("OBJECT_HIDE_DELETE_LINK","hidedeletelink");
define("OBJECT_DELETE_CHECK_CONSTRAINTS","objdeleteconstraints"); //by default the crud class will loop through all tables and fields and if it finds an identical fieldname in any table in the database and there are records in that table, it will tell the user they cannot delete the only way to bypass this constraint is by setting this to false
define("OBJECT_TABLE","table");//table name
define("OBJECT_IS_AGGREGATE","aggregateview");//table name
define("OBJECT_CONNECTION_STRING","connection");//dba connection string
define("OBJECT_PK","primarykey");//primary key hard coded
define("OBJECT_FILTER_DESC","filterrecordsdescription");//used when you want to describe what the data is filtered by inside your controller function
define("OBJECT_PAGING","pagingenabled");//by default paging is enabled unless you say false here. paging is defaulted to 10 records per page but just need to add new configuration here when needing new functionality
define("OBJECT_PAGING_NUM_ROWS_PER_PAGE","pagingrows");
define("OBJECT_PAGING_SCROLL","pagingscroll");
define("OTHER_OBJECTS", "otherobjects" );//otherobjects allows you to build supporting form objects that will be tacked on at the end of the form before the button to post/update

define("REQUIRED_TEXT","requiredtext");
define("OTHER_LINKS", "otherlinks" );
define("EDIT_TEXT","edittext");
define("DELETE_TEXT","deletetext");
define("TABLE_TEXT","tabletext");
define("ADD_TEXT","addtext");
define("VIEW_TEXT","viewtext");
define("SEARCH_TEXT","searchtext");
define("EDIT_LINK", "editlink");
define("DELETE_LINK", "deletelink");

// field level keys and configs

define("CAPTION","caption"); // what the user sees as the field name

//these array keys/configurations are for the foreign key lookups definied at the field level
define("ID","lookupid");
define("TEXT", "lookuptext");
define("TABLE", "lookuptable" );
define("WHERE", "lookupwhere" );
define("SELECT","select");

define("SHOWCOLUMN","showcolumn");
define("COLUMNPOSTTEXT","posttextc");
define("SORTABLE","sortable");
define("PRETEXTREAD","pretext");
define("POSTTEXTREAD","posttext");
define("REQUIRED","required");
define("UPDATE_READ_ONLY","ronlyupdate");
define("HIDE","inserthide");

define("ROW_ID","number_0x45dsa4654das654da64dsa654da");
define("INPUT_DOIT","submit_cruddy_mysql");
define("INPUT_SUBMIT","submit_button");

(include ("$pwd/dbal/dbal.php")) or die("This class require <a href='http://cesars.users.phpclasses.org/dba'>DBA</a> class. Please download it and copy the folder 'dbal' in $pwd");
(include ("$pwd/forms.php")) or die("This class require <a href='http://cesars.users.phpclasses.org/formsgeneration'>Forms Generation Class</a> class. Please download it and copy the file 'forms.php' in $pwd");


class cruddyMysql {

	function cruddyMysql($str,$table,$info=array()) {
		$pwd = dirname(__FILE__);
		$this->table = $info[TABLE_CONFIG][OBJECT_TABLE];
		$this->conn = $str;
		$this->dba = new dbal($str);
		$this->dba->setCacheDir( "${pwd}/cache/" );
		$this->tableDefinition = $info;
		$this->getTableInformation();
	}

	function doQuery($filter) {
		$methodStartTime = get_microtime_ms();
		$res  =  &$this->result;
		$dba  =  &$this->dba;
		$info =  &$this->formParams;
		$definitions = &$this->tableDefinition;

		if (!empty($filter)) {
			if ( ( stristr($filter,'=') || stristr($filter,'IN (') ||  stristr($filter,'IN(') ) && !stristr($filter,'where') ) {
				$f = $filter == '' ? '' : ' WHERE '.$filter;
			} else {
				$f = $filter;
			}
		} else {
			$f = $filter;
		}
		$query = "select count(*) as count from ".$this->table." $f";
		$result = @mysql_query($query,$dba->dbm->dbh);
		if ($result) {
			$row = mysql_fetch_array($result);
			$total_records = $row['count'];
		} else {
			$total_records = 0;
		}
		$scroll_page = ($definitions[TABLE_CONFIG][OBJECT_PAGING_NUM_ROWS_PER_PAGE]) ? $definitions[TABLE_CONFIG][OBJECT_PAGING_SCROLL] : 5 ;
		$per_page = ($definitions[TABLE_CONFIG][OBJECT_PAGING_NUM_ROWS_PER_PAGE]) ? $definitions[TABLE_CONFIG][OBJECT_PAGING_NUM_ROWS_PER_PAGE] : 10 ;
		$current_page = $_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['page']];
		$pager_url = $_SERVER['PHP_SELF']."?action=".strtolower($definitions[TABLE_CONFIG][OBJECT_ACTIONS]['read'].$this->object_key).'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_field'].'='.$_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_field']].'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_direction'].'='.$_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_direction']].'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['page'].'=';
		$inactive_page_tag = 'id="current_page"';
		$previous_page_text = '&lt; Previous';
		$next_page_text = 'Next &gt;';
		$first_page_text = '&lt;&lt; First';
		$last_page_text = 'Last &gt;&gt;';
		$crudPage = new cruddyMysqlPager();
		$crudPage->pager_set($pager_url, $total_records, $scroll_page, $per_page, $current_page, $inactive_page_tag, $previous_page_text, $next_page_text, $first_page_text, $last_page_text,'');
		$result = mysql_query(str_replace("count(*) as count","*",$query)." LIMIT ".$crudPage->start.", ".$crudPage->per_page."",$dba->dbm->dbh);
		$definitions[TABLE_CONFIG][OBJECT_PAGING] =  $crudPage;

		if ($result) {
			while ($row = mysql_fetch_assoc($result)) {
				$res[] = $row;
			}
		} else {
			//if ($this->cruddyAdministrator) {
				echo ("ERROR: ".$dba->getLastError());
			//}
		}
		$total = (get_microtime_ms() - $methodStartTime);
		$this->performance['doQuery'][] = $total ." sql:".$query;
	}

	/**
	 *  Creates a new row.
	 *
	 *  Show the form for create a new row.
	 */
	function create() {
		$this->getTableInformation(true);
		return $this->buildGenericForm(array(),false,"");
	}

	/**
	 *  search
	 */
	function search() {
		$this->getTableInformation("search");
		return $this->buildGenericForm(array(),false,"",false,true);
	}
	/**
	 * Generic Form
	 *
	 *  @access private
	 */
	function buildGenericForm($default=array(),$update=false,$update_condition="",$readOnly=false,$search=false) {
		$methodStartTime = get_microtime_ms();
		$form = new form_class;
		$form->NAME= $this->table."_form";
		$form->METHOD="POST";
		$form->ACTION="";
		$form->ENCTYPE="multipart/form-data";
		$form->InvalidCLASS="invalid";
		$form->ResubmitConfirmMessage="Are you sure you want to submit this form again?";
		$form->OptionsSeparator="<br />\n";
		$form->ErrorMessagePrefix="- ";
		$form->ErrorMessageSuffix="";
		foreach($this->formParams as $k => $input)   {
			if ( is_array($default) && count($default) > 0) {
				$input["VALUE"] = $default[$k];
			}
			if ($input["NAME"]) {
				echo $form->AddInput( $input );
			}
		}
		$form->LoadInputValues($form->WasSubmitted(INPUT_DOIT));


		$verify=array();
		$doit=false;
		$error_message="";
		if($form->WasSubmitted(INPUT_DOIT))  {
			 if(($error_message=$form->Validate($verify))!="") {
				$doit=false;
			 }  else {
				$doit=true;
			 }
		}

		if($doit) {
			$dba  = &$this->dba;

			// -- get a list of fields that the table can take skip anything else in the post
			$sql  = sprintf(GET_COLUMNS_SQL,$this->table);
			$record = $dba->query($sql);
			if ( !$record )
				return false;
			$Field     = & $record->bindColumn('Field');
			while ( $foo=$record->getNext() ) {
				$tableFields[$Field] = $Field;
			}
			$sql = "";
			$columns=array();
			foreach($this->formParams as $k=>$v) {
				if ( $k == ROW_ID || $k == INPUT_DOIT || $k == INPUT_SUBMIT) continue;
				if (!in_array($k,$tableFields)) {
					// -- found another form element see if there is something to do with it
					continue;
				} else {
					if (strtoupper($v['TYPE']) == 'FILE') {
						$form->GetFileValues($k,$userfile_values);
						if ($userfile_values["name"]) {
							// -- for files, user should be mapping the MIME, MOVE_TO, and SIZE to other fields
							$columns[$k] = $k;
							$values[$k] = $k;
							$_POST[$k] = $userfile_values["name"];

							// -- users can store the MIME and FILE_SIZE attributes into a custom field mapping
							// -- FYI there is no edit facility for MIME/SIZE you must convert your config to an array and manually add them to the $field_name_"config" section of the array
							// -- MIME is meant to update another field with the MIME type of the fileupload and expects a field name as the value of the key
							if ($v['MIME']) {
								$columns[$v['MIME']] = $v['MIME'];
								$values[$v['MIME']] = $v['MIME'];
								$_POST[$v['MIME']] = $userfile_values["type"];
							}

							if ($v['FILE_SIZE']) {
								$columns[$v['FILE_SIZE']] = $v['FILE_SIZE'];
								$values[$v['FILE_SIZE']] = $v['FILE_SIZE'];
								$_POST[$v['FILE_SIZE']] = $userfile_values["size"];
							}

							if (isset($v['MOVE_TO'])) {
								if (@is_uploaded_file($userfile_values["tmp_name"])) {
									if (substr($v['MOVE_TO'],-1))
									if (substr($v['MOVE_TO'],-1) != '/' && strtoupper(substr(PHP_OS,0,3)!='WIN')) {
										$v['MOVE_TO'] .= "/";
									} elseif (substr($v['MOVE_TO'],-1) != "\\" && strtoupper(substr(PHP_OS,0,3)=='WIN')) {
										$v['MOVE_TO'] .= "\\";
									}
									if (!@move_uploaded_file($userfile_values["tmp_name"], $v['MOVE_TO'].$userfile_values["name"])) {
										die("File Upload Failed.  Ensure that {$v['MOVE_TO']} is chmod 777 for new files to overwrite.");
									}
								}
							} else {
								die("Missing MOVE_TO value to move the file");
							}

						} else {

						}
					} elseif (strtoupper($v['CustomClass']) == 'FORM_DATE_CLASS') {
						$dateValue = $_POST["p_".$k."_year"]."-".$_POST["p_".$k."_month"]."-".$_POST["p_".$k."_day"];
						if (empty($_POST["p_".$k."_year"]) || empty($_POST["p_".$k."_month"])) {
							$dateValue = "";
						}
						$_POST[$k] = $dateValue;
						$values[$k] = $k;
						$columns[$k] = $k;
					} else {
						if ($v["UsesAutoFormName"] ==! false) {
							// -- custom flag for use when widget calls $forms->GenerateInputID()
							$columns[$k] = $k;
							$values[$k] = "p_".$k."_".$v["UsesAutoFormName"];
						} else {
							$columns[$k] = $k;
							$values[$k] = $k;
						}
					}
				}
			}

			if ( $update ) {
				$updatx  = array();
				foreach($columns as $k=>$v) {
					if (isset($_POST[$k])) {
						$updatx[] = " $v = :$values[$k]";
					}
				}
				$sql = sprintf(UPDATE_SQL, $this->table,implode(" , ",$updatx),$update_condition);

			} else {
				foreach($columns as $k=>$v) {
					if (intval(substr($k,0,1)) > 0) {
						// -- column starts with a number - unsupported
						unset($columns[$k],$values[$k]);
					}
					if (!isset($_POST[$k])) {
						unset($columns[$k],$values[$k]);
					}
				}
				$sql = sprintf(INSERT_SQL, $this->table,implode(", ",$columns),":".implode(", :",$values));
			}

			$dba->compile($sql);

			// -- support multi-value inserts/updates
			$multi=false;
			foreach ($_POST as $postKey=>$postValue) {
				if (is_array($postValue)) {
					$cnt++;
					$multi=true;
					$multiArray = $postValue;
					$multiArrayKey = $postKey;
				}
			}

			if ($cnt != 1 && $multi === true) {
				$error_message="You can only have 1 multi select for each row.";
				return false;
			}

			if ($multi === false ) {
				$f = $dba->execute($_POST);
			} else {
				foreach ($multiArray as $insertValue) {
					$_POST[$multiArrayKey] = $insertValue;
					$f = $dba->execute($_POST);
				}
			}

			if ( $f ) {
				if ($update) {
					return true;
				} else {
					$lastInsert = mysql_insert_id($this->dba->dbm->dbh);
					$_POST[$this->tableDefinition[TABLE_CONFIG][OBJECT_PK]] = $lastInsert;
					return $lastInsert;
				}
			} else {
				 $str = $dba->getLastError();
				 if ( substr(strtolower($str),0,9) == "duplicate") {
					$error_message="Duplicated data";
					$s = strpos($str,"'")+1;
					$e = strpos($str,"'",$s);
					$err = trim( substr($str,$s,$e-$s) );
					foreach($columns as $k => $v) {
						if ( $err == $_POST[$v])  {
							$verify[$v] = $v;
						}
					}
				 } else {
					$error_message="There was a database error that occurred in saving this record.";
					if ($this->cruddyAdministrator) {
						$error_message = $str;
						echo $dba->__sql;
					}

				}

			}
		}
		$total = (get_microtime_ms() - $methodStartTime);
		$this->performance['buildGenericForm'][] = $total;
		$this->autoTemplate($form,$error_message,$verify,$update,$readOnly,$search);
		return false;
	}

	function update($arr) {
		if ( !is_array($arr) ) return false;
		$filter=Array();
		foreach($arr as $k=>$v) {
			$filter[] ="$k = \"".addslashes($v)."\"";
		}
		$this->doQuery(implode(" && ",$filter));
		return$this->buildGenericForm($this->result[0], true, implode(" && ",$filter) );
	}

	function view($arr) {
		if ( !is_array($arr) ) return false;
		$filter=Array();
		foreach($arr as $k=>$v) {
			$filter[] ="$k = \"".addslashes($v)."\"";
		}
		$this->doQuery(implode(" && ",$filter));
		return$this->buildGenericForm($this->result[0], true, implode(" && ",$filter),true);
	}

	function delete($arr) {
		if ( !is_array($arr) ) return false;
		$filter=Array();
		foreach($arr as $k=>$v) {
			$filter[] ="$k = \"".addslashes($v)."\"";
		}
		$filter = implode(" && ",$filter);
		$dba  =  &$this->dba;
		$definitions = &$this->tableDefinition;
		$f = $filter == '' ? 'XXXXXXXXX Unsupported XXXXXXXXX' : ' where '.$filter;
		$r = $dba->query(GET_TABLES_SQL);
			if (empty($r)) {
			$parts = explode("/",$this->conn);
			$database = $parts[sizeof($parts)-1];
				$r = $dba->query(GET_TABLES_SQL." from $database");
				if (empty($r)) {
					$r = $dba->query("SHOW TABLES FROM $database");
					if (empty($r)) {
						die("<div class=\"error\">Could not get table listing from $database</div>");
					}
			}
			}
		if ( $r ) {
			$Table     = & $r->bindColumn('Tables_in_'.$dba->info['db']);
			$Type      = & $r->bindColumn('Table_type');
			$dependentRecords = false;
			while ( $foo=$r->getNext() ) {
				if (strtolower($Table) == strtolower($definitions[TABLE_CONFIG][OBJECT_TABLE])) {
					// -- dont check current table
					continue;
				}
				$record2 = $dba->query(sprintf(GET_COLUMNS_SQL,$Table));
				if ( $record2 ) {
					$Field2     = & $record2->bindColumn('Field');
					while ( $foo2=$record2->getNext() ) {
						if ($definitions[TABLE_CONFIG][OBJECT_PK] == $Field2) {
							// -- rules are if you have a table with the same field name and you didnt specify to OBJECT__CHECK_CONSTRAINTS => false
							if ($definitions[TABLE_CONFIG][OBJECT_DELETE_CHECK_CONSTRAINTS] == 1) {
								if ($Type == 'BASE TABLE') {
									foreach($arr as $k=>$v) {
										if ($k == $Field2) {
											$valueWhere = $v;
											break;
										}
									}
									$record3 = $dba->query("SELECT * FROM ".$Table." WHERE ".$Field2." = '".$valueWhere."'");
									if ( $record3->_result != null ) {
										if ($_GET['confirm']==1 && $_GET['table']==$Table) {
											$dba->query("DELETE FROM ".$Table." WHERE ".$Field2." = '".$valueWhere."'");
											header("Location: ".rawurldecode($_GET['redir']));
										} else {
											$dependentRecords = "There are dependent records in \"".$Table."\" and you cannot delete this ".$Field2.".  Would you like to delete these dependent records too?  <a href='".$_SERVER['REQUEST_URI']."&table=$Table&confirm=1&redir=".rawurlencode($_SERVER['REQUEST_URI'])."'>Yes</a>";
										}
									}
								}
							}
						}
					}
				}
			}
			if ($dependentRecords==false) {
				$r = $dba->execute("delete from ".$this->table." $f");
			} else {
				$r = false;
				echo $dependentRecords;
			}
		}
		return $r != false;
	}

	function buildSearchWhere($currentTable='') {
		$definitions = &$this->tableDefinition;
		if ($currentTable!='') {
			$definitions = $this->currentAdminDB[CRUD_FIELD_CONFIG][$currentTable];
		}
		foreach($_COOKIE as $k=>$v) {
			if (stristr($k,$definitions[TABLE_CONFIG]['alias']."~")) {
				$column = str_replace($definitions[TABLE_CONFIG]['alias']."~","",$k);
				if (!empty($v) && $v != "null") {
					if (isset($definitions[$column])) {
						// -- valid column config with a search cookie value
						$where .= " AND `$column` like '%".mysql_real_escape_string($v)."%' ";
//						if ($definitions[$column][TABLE]) {
//							$res = mysql_query("select ".$definitions[$column][TEXT]." from ".$definitions[$column][TABLE]." WHERE `$column` = '".mysql_real_escape_string($v)."'");
//							var_dump(mysql_fetch_assoc($res));
//						}
						$desc .=  "<div style='-moz-border-radius:8px 8px 8px 8px;border: 3px ridge #485254; float: left;cursor:pointer;' onclick='if (window.confirm(\"Do you want to remove the `".$definitions[$column][CAPTION]."` filter?\")) { eraseCookie(\"$k\"); document.location = document.location; } '><span style='font-size: 19px;color:#7F7F7F;'>".$definitions[$column][CAPTION]."</span>&rarr;<span style='font-size: 19px;color:#7F7F7F;'>\"".$v."\"</span></div><div style='float:left;margin-top:7px;'> + </div>";
					}
					if (!isset($definitions[$column]) && $currentTable!='') {
						$desc = '';
						$where = '';
					}
				}
			}
		}
		$desc = substr($desc,0,-49);
		return array($where,$desc);
	}
	/**
	 *  READ
	 *  @param string $filter SQL filter.
	 */
	function read($filter='') {
		$methodStartTime = get_microtime_ms();
		$definitions = &$this->tableDefinition;
		list($wh,$desc) = $this->buildSearchWhere();
		if (!stristr($filter,"order")) {
			$filter .= $wh;
		} elseif ($wh) {
			$filter = str_replace("1=1","1=1 $wh", $filter);
		}
		if (!empty($definitions[TABLE_CONFIG][OBJECT_DEFAULT_ORDER]) && !stristr($filter,"order")) {
			$filter .= " ORDER BY `".$definitions[TABLE_CONFIG][OBJECT_DEFAULT_ORDER]."`";
		}
		
		$this->doQuery($filter);
		$res  = &$this->result;
		$info = &$this->formParams;
		echo "<table>\n";
		if ( is_array($res) ) {
			
			echo "<thead>
						<tr>";
			
			if ($definitions[TABLE_CONFIG][OBJECT_IS_AGGREGATE]) {
				echo "<th>Database</th>";
			}
			foreach($definitions as $key => $value) {
				if ( !is_array($value) || $value[SHOWCOLUMN] == 0 || !isset($value[SHOWCOLUMN])) continue;

				// -- if the field doesnt say to NOT sort
				if ( ($definitions[TABLE_CONFIG][SORTABLE] == 1 || !isset($definitions[TABLE_CONFIG][SORTABLE])) && !$definitions[TABLE_CONFIG][OBJECT_IS_AGGREGATE]) {
					if ($_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_direction']] == 'ASC') {
						$direction = 'DESC';
						$directionAscii = '&darr;';
					} else {
						$direction = 'ASC';
						$directionAscii = '&uarr;';
					}

					// -- only set direction arrow if on current field
					if (strtoupper($_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_field']]) == strtoupper($key)) {
						if ($_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_direction']] == 'ASC') {
							$directionAscii = '&uarr;';
						} else {
							$directionAscii = '&darr;';
						}
					} else {
						$directionAscii = '';
					}

					if (!empty($_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['page']])) {
						$direction .= '&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['page'].'='.$_GET[$definitions[OBJECT_ACTIONS]['page']];
					}

					$sortLinkStart = "<a href='?action=".strtolower($definitions[TABLE_CONFIG][OBJECT_ACTIONS]['read'].$this->object_key).'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_field'].'='.$key.'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_direction'].'='.$direction;
					if ($this->isPageInclude) {
						$sortLinkStart .= "&conf=$this->current_config";
					}
					$sortLinkStart .= "'>$directionAscii";
					$sortLinkEnd = "</a>";
				}
				echo "      <th>".$sortLinkStart.$value[CAPTION].$sortLinkEnd."</th>\n";
				$sortLinkStart = $sortLinkEnd = '';
			}

			echo "</tr>
						</thead>";

		
			//
			$databases = array();
			if ($definitions[TABLE_CONFIG][OBJECT_IS_AGGREGATE]) {
				foreach ($definitions[TABLE_CONFIG]['all_databases'] as $server=>$values) {
					foreach ($values as $database) {
						$databases[$database]['db_name'] = $database;
						//$databases[$database]['db_port'] = $definitions[TABLE_CONFIG]['all_ports'][$server];
						$databases[$database]['db_password'] = $definitions[TABLE_CONFIG]['all_passwords'][$server];
						$databases[$database]['db_server'] = $definitions[TABLE_CONFIG]['all_servers'][$server];
						$databases[$database]['db_user'] = $definitions[TABLE_CONFIG]['all_users'][$server];
					}
				}
			} else {
				$database = $this->dba->info['db'];
				$databases[$database]['db_name'] = $database;
				$databases[$database]['db_port'] = $this->dba->info['user'];
				$databases[$database]['db_password'] = $this->dba->info['pass'];
				$databases[$database]['db_server'] = $this->dba->info['host'];
				$databases[$database]['db_user'] = $this->dba->info['user'];
			}
			
			$aggregateTotals = array();
			foreach ($databases as $dbId=>$dbAttribs) {
				$this->dba->setHost($dbAttribs['db_server']);
				$this->dba->setPass($dbAttribs['db_password']);
				$this->dba->setUser($dbAttribs['db_user']);
				$this->dba->connectToNewDB($dbAttribs['db_name']);
				$res  = array();
				$this->doQuery($filter);
				$res  = &$this->result;
				
				foreach($res as $k => $r) {
					$pagedResults = (array)$r;
					echo "   <tr>\n";

					if ($definitions[TABLE_CONFIG][OBJECT_IS_AGGREGATE]) {
						echo "<td>{$dbAttribs['db_name']}</td>";
					}
					$edit_url = $definitions[TABLE_CONFIG][EDIT_LINK];
					$del_url  = $definitions[TABLE_CONFIG][DELETE_LINK];

					if ($definitions[TABLE_CONFIG][OBJECT_HIDE_EDIT_LINK] == 1) {
						$edit_url = "";
					}
					if ($definitions[TABLE_CONFIG][OBJECT_HIDE_DELETE_LINK] == 1) {
						$del_url = "";
					}

					foreach($pagedResults as $k2 => $v2) {
						$edit_url = str_replace('%'.$k2.'%', $v2,  $edit_url);
						$del_url  = str_replace('%'.$k2.'%', $v2,  $del_url);
					}
					$count=0;
					foreach($definitions as $k => $v) {
						if (!is_array($v)) {continue;}
						if ( ! isset($v[SHOWCOLUMN]) || $v[SHOWCOLUMN] == 0) continue;
						$count++;
						$text = "";
						if (isset($v[PRETEXTREAD])) {
							$processedText = $v[PRETEXTREAD];
							foreach($pagedResults as $k2 => $v2) {
								$processedText = str_replace('%'.$k2.'%', $v2, rawurldecode($processedText));
							}
							$text .= $processedText;
						}
						$dataElementValue = (isset($info[$k]["OPTIONS"][$r[$k]]) && !empty($r[$k])) ? $info[$k]["OPTIONS"][$r[$k]] : $r[$k];
						if (is_numeric($dataElementValue)) {
							$aggregateTotals[$k] += $dataElementValue;
						} /*else {
							$aggregateTotals[$k] = 'N/A';
						}*/
						$text .= htmlentities($dataElementValue);
						if (isset($v[POSTTEXTREAD])) {
							$processedText = $v[POSTTEXTREAD];
							foreach($pagedResults as $k2 => $v2) {
								$processedText = str_replace('%'.$k2.'%', $v2,  rawurldecode($processedText));
							}
							$text .= $processedText;
						}
						if (empty($text) && $text !=='0') {
							 $text .= "<span style='color:#EBEBEB'>(No ".$v[CAPTION].")</span>";
						}

						$linkStart = $linkEnd = "";
						if ($definitions[TABLE_CONFIG][OBJECT_HIDE_DETAILS_LINK] == 0 && $count == 1) {
							$linkStart  = "<a href='".str_replace("update_","view_",$edit_url);
							if ($this->isPageInclude) {
								$linkStart .= "&conf=$this->current_config";
							}
							$linkStart .= "'>";
							$linkEnd = "</a>";
						}

						if (strlen($text) > 30 && preg_match("|<[^>]+>(.*)</[^>]+>|U",$text)==0 && !stristr($text,"<img") && !stristr($text,"<input")) {
							$text = substr($text,0,30)."...";
						}
						if ($info[$k]["TYPE"] == 'select') {
							$parts = parse_url($definitions[TABLE_CONFIG]['connection']);
							if (!$this->isPageInclude) {
								$text .= " <strong style=\"color:black;\">(<a href=\"?action=view_".str_replace("/","",$parts['path'])."_".$v[TABLE]."&". $v[ID] . "=". $r[$k] ."\">{$r[$k]}</a>)</strong>";
							}
						}

						echo "<td>".$linkStart.stripslashes($text).$linkEnd."</td>\n";
						// -- debug the row
						//echo "<td>".var_export($r,true)."</td>";

					}

					if (!empty($edit_url)) {
						$edTxt = ($definitions[TABLE_CONFIG][EDIT_TEXT]) ? $definitions[TABLE_CONFIG][EDIT_TEXT] : 'Edit';
						$edit = '<a title="Edit this '.$definitions[TABLE_CONFIG][OBJECT_DESC].'" href="'.$edit_url;
						if ($this->isPageInclude) {
							$linkStart .= "&conf=$this->current_config";
						}
						$edit .= '">'.$edTxt.'</a> - ';
					}
					if (!empty($del_url)) {
						$delTxt = ($definitions[TABLE_CONFIG][DELETE_TEXT]) ? $definitions[TABLE_CONFIG][DELETE_TEXT] : "Delete";
						$delete = '<a title="Delete this '.$definitions[TABLE_CONFIG][OBJECT_DESC].'" href="javascript:if(window.confirm(\'Are you sure you wish to delete this '.$this->object_name.'?\')){document.location=\''.$del_url.'\';}">'.$delTxt.'</a>';
					}
					if (is_array($definitions[TABLE_CONFIG][OTHER_LINKS])) {
						$other = '';
						foreach ($definitions[TABLE_CONFIG][OTHER_LINKS] as $key=>$value) {
							$other_url = $value;
							foreach($r as $k2 => $v2) {
								$other_url  = str_replace('%'.$k2.'%', $v2,  rawurldecode($other_url));
							}
							$other .= ' - <a href="'.$other_url.'">'.$key.'</a>';
						}
					}
					echo '<td><nobr>'.$edit.$delete.$other.'</nobr></td>'."\n";
					echo "</tr>\n";
				}
			}
			
			if ($definitions[TABLE_CONFIG][OBJECT_IS_AGGREGATE]) {
				echo "<tr>";
				echo "<td>Totals</td>";
				foreach ($aggregateTotals as $kAgg=>$vAgg) {
					echo "<td>$vAgg</td>";
				}
				echo "</tr>\n\n";
			}
			
		} else {
			echo "<tr> \n";
			if ($_COOKIE['current_db']) {
				list($void,$db) = explode('-',$_COOKIE['current_db']);
				$db .= " ";
			}
			echo "<td><h2>No ".$db.$definitions[TABLE_CONFIG][OBJECT_DESC]."'s found.</h2></td>";
			echo "</tr> \n";
		}
		echo '</table>';
		echo '<p id="paging_links">';
		if ($definitions[TABLE_CONFIG][OBJECT_PAGING] -> next_page != "" || !empty($_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['page']])) {
			echo $definitions[TABLE_CONFIG][OBJECT_PAGING] -> first_page;
			echo $definitions[TABLE_CONFIG][OBJECT_PAGING] -> previous_page;
			echo $definitions[TABLE_CONFIG][OBJECT_PAGING] -> page_links;
			echo $definitions[TABLE_CONFIG][OBJECT_PAGING] -> next_page;
			echo $definitions[TABLE_CONFIG][OBJECT_PAGING] -> last_page;
		}
		$this->performance['readGeneric'][] = (get_microtime_ms() - $methodStartTime);

		echo '</p>';

	}

	/**
	 *  Generate a basic template for the form.
	 *
	 *  @param object $form Form object
	 *  @access private
	 */
	function autoTemplate($form,$error_message,$verify,$update,$readOnly=false,$search=false) {
		$methodStartTime = get_microtime_ms();
		$def = &$this->tableDefinition;
		$formParams = &$this->formParams;
		$formParams[INPUT_SUBMIT] = $this->button;
		$form->StartLayoutCapture();
		if (!empty($error_message)) {
			echo '<div class="error">'.$error_message.'</div>';
		}
		// -- logic to hide/show based on cookies (also show a post text to unset the search cookie)

		if ($search == true) {
			$disp = "style=\"display:none;\" id=\"{$def[TABLE_CONFIG]['alias']}_search\"";
		}

		echo '<table '.$disp.' summary="Input fields table">';


		if ($search == true) {
			$jsSearch = array();
			foreach($this->formParams as $inpName => $i) {
				$form->inputs[$inpName]['VALUE'] = '';
				$p = '';
				if (substr($inpName,2) == 'p_') {
					$p = 'p_';
				}
				$newSearchId = $p.$inpName."_search";
				$form->inputs[$inpName]['NAME'] = $newSearchId;
				$form->inputs[$inpName]['ID'] = $newSearchId;
				$form->inputs[$newSearchId] = $form->inputs[$inpName];
				unset($form->inputs[$inpName]);
				$possibleSearchKey = $def[TABLE_CONFIG]['alias']."~".$inpName;
				$possibleSearchVal = $_COOKIE[$possibleSearchKey];
				if ($possibleSearchVal) {
					$form->inputs[$newSearchId]['VALUE'] = $possibleSearchVal;
				}
				$jsAll .= "if ($('$newSearchId')) { createCookie('$possibleSearchKey',$('$newSearchId').value,500);} ";
				$jsSearch[$inpName.'_search'] = "$('$newSearchId').value='';eraseCookie('$possibleSearchKey');";
			}
		}
		
		foreach($this->formParams as $inpName => $i) {
			$continue = true;
			
			if ($search == true) {
				$originalInputName = $inpName;
				$inpName = $inpName . "_search";
			}

			if (is_array($def[TABLE_CONFIG][OTHER_OBJECTS])) {
				foreach ($def[TABLE_CONFIG][OTHER_OBJECTS] as $key=>$value) {
					if ($key == $inpName) {
						$continue = false;
					}
				}
			}
			if ( $inpName == INPUT_DOIT || $inpName == INPUT_SUBMIT) {
				$continue = false;
			}

			if (!isset($i['NAME'])) {
				$continue = false;
			}

			if ($continue === true) {
				if ( isset($def[$inpName][HIDE]) && $def[$inpName][HIDE] ) {
					echo "<tr style=\"display:none;\">\n";
				} else {
					echo "<tr>\n";
				}
				echo "<th align=\"right\">";
				if ($search) {
					echo "<label for=\"$inpName\">".$def[$originalInputName][CAPTION]."</label>";
					echo " (<a style=\"cursor:pointer;\" onclick=\"{$jsSearch[$inpName]}\">X</a>)";
				} else {
					echo $form->AddLabelPart(array("FOR"=>$inpName));
				}
				echo "</th>\n";
				echo "<td>";
				if ( isset($def[$inpName][UPDATE_READ_ONLY]) && $def[$inpName][UPDATE_READ_ONLY] || $readOnly === true) {
					$form->AddInputReadOnlyPart( $inpName );
				} else {
					$form->AddInputPart($inpName);
				}
				if ($search) {
					echo " <a style=\"cursor:pointer;\" onclick=\"$('{$def[TABLE_CONFIG]['alias']}_bttn').onclick();\">&rArr;</a>";
				}
				echo $def[$inpName][COLUMNPOSTTEXT]."</td>\n";
				echo "<td>". (IsSet($verify[$inpName]) ? "[Verify]" : "")."</td>\n";
				echo "</tr>\n";
			}
		}

		if ( isset($def[TABLE_CONFIG][OTHER_OBJECTS]) && is_array($def[TABLE_CONFIG][OTHER_OBJECTS])) {
			// -- for now additional elements draw right before the input box
			foreach ($def[TABLE_CONFIG][OTHER_OBJECTS] as $key=>$value) {
				echo "<tr>";
				if (strtoupper($value['TYPE']) != 'HIDDEN') {
					echo '<th align="right">';
					echo $this->formParams[$key]['LABEL'];
					echo ':</th>';
				}
				echo "\n<td>";
				$form->AddInputPart($key);
				echo "</td>\n";
				echo "<td></td>\n";
				echo "</tr>\n";
			}
		}

		if ($readOnly === false && $search == false) {
			echo '<tr><th align="right"></th>';
			echo "\n";
			echo '<td>';
			echo '<input name="'.INPUT_DOIT.'" value="1" TYPE="hidden"/><input name="'.INPUT_SUBMIT.'" value="'.$this->formParams[INPUT_SUBMIT]["VALUE"].'" onclick="if(this.disabled || typeof(this.disabled)==\'boolean\') this.disabled=true ; form_submitted_test=form_submitted ; form_submitted=true ; form_submitted=(!form_submitted_test || confirm(\''.$form->ResubmitConfirmMessage.'\')) ; if(this.disabled || typeof(this.disabled)==\'boolean\') this.disabled=false ; sub_form=\'\' ; return true" id="'.INPUT_SUBMIT.'" type="submit">';
			echo "</td>\n";
			echo "<td></td>\n";
			echo "</tr>\n";
		} elseif ($search == true) {
			foreach ($jsSearch as $k=>$v) {
				$tmp .= $v;
			}
			echo '<tr><th><input value="Clear All" onclick="'.$tmp.'" type="button"></th>';
			echo '<td>';
			echo '<input value="Search" id="'.$def[TABLE_CONFIG]['alias'].'_bttn" onclick="'.$jsAll.'document.location = location.pathname + \'?action=show_'.$def[TABLE_CONFIG]['alias'].'\';" type="button">';
			echo "</td>";
			echo "<td></td>";
			echo "</tr>";
		}
		
		echo '</table>';
		$form->EndLayoutCapture();
		$form->DisplayOutput();
		$total = (get_microtime_ms() - $methodStartTime);
		$this->performance['autoTemplate'][] = $total;
	}

	/**
	 *  Get information about the table
	 *
	 *  @access private.
	 */
	function getTableInformation($insert=false) {
		$methodStartTime = get_microtime_ms();
		$dba  = &$this->dba;

		$info = &$this->tableDefinition;
		unset($this->formParams);
		$formParams = &$this->formParams;
		$sql  = sprintf(GET_COLUMNS_SQL,$this->table);
		$record = $dba->query($sql);

		if ( !$record )
			return false;

		$Field     = & $record->bindColumn('Field');
		$Type      = & $record->bindColumn('Type');
		$Null      = & $record->bindColumn('Null');
		$Key       = & $record->bindColumn('Key');
		$Extra     = & $record->bindColumn('Extra');
		$Default     = & $record->bindColumn('Default');
		$Comment     = & $record->bindColumn('Comment');
		while ( $foo=$record->getNext() ) {
			$actInfo = & $info[$Field];
			if (stristr($Comment,"lookup")) {
				list($type,$table,$field,$value) = explode(",",$Comment);
				$actInfo[TABLE] = trim($table);
				$actInfo[ID] = trim($field);
				$actInfo[TEXT] = trim($value);
			}
			$actInfoFormOverRides = & $info[$Field."_config"];

			/* reseting form information */
			$form = array();

			if ($Extra == 'auto_increment') {
				continue;
			}
			/**
			 *  If the field is autoincrement, we
			 *  do not need to show it on the form.
			 */
			$display = "";
			if ( isset($actInfo[HIDE]) && $actInfo[HIDE] ) {
				$form["READONLY"] = "true";
			}
			$this->comments[$Field] = $Comment;
			$this->datatypes[$Field] = $Type;
			$autoType = $this->parseColumnInfo($Type,$foo['Default'],$Field);
			$form["NAME"] = trim($Field);
			$form["ID"] = $form["NAME"];

			// -- if table is configured as not null then user has to enter something
			/*if (strtoupper($Null) == 'NO') {
				 $form["ValidateAsNotEmpty"] = 1;
			}*/

			// -- if developer tells class that the field is non-required then set dont set as required
			if($actInfo[REQUIRED] == 1 && isset($actInfo[REQUIRED])) {
				$form["ValidateAsNotEmpty"] = 1;
				$form["Optional"] = false;
				$form["LABEL"] .="<span class='required'>".$info[TABLE_CONFIG][REQUIRED_TEXT]."</span>";
			} else {
				$form["Optional"] = true;
				unset($form["ValidateAsNotEmpty"]);
			}

			$form["LABEL"] = isset($actInfo[CAPTION]) ? $actInfo[CAPTION] : $Field;
			if (isset($actInfo[TABLE]) && isset($actInfo[ID]) && isset($actInfo[TEXT])) {
				$form["TYPE"] = "select";
				$opt = & $form["OPTIONS"];
				if (isset($actInfo[WHERE])) {
					$where = " where ".$actInfo[WHERE]." order by `".$actInfo[TEXT]."` ASC";
				}
				if (substr($actInfo[ID],0,23) == '___distinct___lookup___' || substr($actInfo[TEXT],0,23) == '___distinct___lookup___') {
					$distinct = "distinct";
					$actInfo[ID] = substr($actInfo[ID],23);
					$actInfo[TEXT] = substr($actInfo[TEXT],23);
				}
				$rec1 = $dba->query("select ".$distinct." ".$actInfo[ID].",".$actInfo[TEXT]." from ".$actInfo[TABLE].$where);
				if ( !$rec1 ) {
					continue;
				}
				//@ToDo - say couldnt join if admin
				$opt[""] = "Select a : ".$form["LABEL"];

				while ( $f = $rec1->getNext() ) {
					if ( !isset($form["VALUE"]) ) $form["VALUE"]= "";
					if (strlen($f[ $actInfo[TEXT] ]) > 300 ) {
						$val = substr($f[ $actInfo[TEXT] ],0,300)."...";
					} else {
						$val = $f[ $actInfo[TEXT] ];
					}
					$this->cachedLookup[$hash]["ID"] = $f[$actInfo[ID] ];
					$this->cachedLookup[$hash]["VALUE"] = $val;
					$opt[ $f[$actInfo[ID] ] ] = $val;
				}
				if ($actInfoFormOverRides['TYPE'] != 'select_multi') {
					unset($actInfoFormOverRides['TYPE']);
				}
			} else if ( isset($actInfo[SELECT]) ){
				$form["TYPE"] = "select";
				$form["OPTIONS"] = array_merge(array(""=>"Select: ".$form["LABEL"]),$actInfo[SELECT]);
				$form["VALUE"] = array_shift( array_keys($actInfo[SELECT]) );
			} else {
				$form["TYPE"] = $autoType["TYPE"];
			}
			$form["ValidationErrorMessage"] = "'".$form["LABEL"]."' is required.";

			if (is_array($autoType)) {
				foreach ($autoType as $autoTypeKey=>$autoTypeVal) {
					if (!isset($form[$autoTypeKey])) {
						$form[$autoTypeKey] = $autoType[$autoTypeKey];
					}
				}
			}
			if ( $type["TYPE"]=="select" ) {
				$form["VALUE"] = strlen($Default)>0? $Default : current($form["OPTIONS"]);
			}
			/**
			 *  Override Field Configuration based on field_config array
			 */
			if (!empty($actInfoFormOverRides)) {
				 foreach ($actInfoFormOverRides as $option=>$optionValue) {
					$form[$option] = $optionValue;
				 }
			}

			if (isset($form['ValidateAsURL'])) {
				unset($form['ValidateAsURL']);
				$form["ReplacePatterns"] =  array(
											"^[ \t\r\n]+"=>"",

											"[ \t\r\n]+\$"=>"",

											"^([wW]{3}\\.)"=>"http://\\1",

											"^([^:]+)\$"=>"http://\\1",

											"^(http|https)://(([-!#\$%&'*+.0-9=?A-Z^_`a-z{|}~]+\.)+[A-Za-z]{2,6}(:[0-9]+)?)\$"=>"\\1://\\2/"
											);

				$form["ValidateRegularExpression"] = '^(http|https)\://(([-!#\$%&\'*+.0-9=?A-Z^_`a-z{|}~]+\.)+[A-Za-z]{2,6})(\:[0-9]+)?(/)?/';
				$form["ValidationErrorMessage"]    = (!isset($form["ValidateAsURLErrorMessage"])) ? "This is not a valid URL" : $form["ValidateAsURLErrorMessage"];;
			}

			if ($actInfoFormOverRides['TYPE'] == 'select_multi') {
				$form["TYPE"] = "select";
				$form["SIZE"] = "8";
				$form["NAME"] = $Field."[]";
				$form["ValidateOnlyOnClientSide"] = true;
				$form["ExtraAttributes"] = array("multiple"=>"multiple");
			}

			if ($form['TYPE'] == 'wysiwyg' || $actInfoFormOverRides['TYPE'] == 'wysiwyg') {
				unset($form['TYPE']);
				require_once("form_FCKEditor.php");
				$form["TYPE"] = "custom";
				$form["CustomClass"] = "form_FCKEditor";
				$form["BasePath"] = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."fck/";
				$form["HEIGHT"] = 400;
				$form["WIDTH"] = 800;
				$form["Skin"] = "silver";
				$form["UsesAutoFormName"] = "instance";
			}
			if ($form['TYPE'] == 'date' || $form['TYPE'] == 'timestamp') {
				$form["TYPE"] = "custom";
				$form["CustomClass"] = "form_date_class";
				if ($insert=='search') {
					$form["VALUE"] = '';
					$form["ChooseControl"] = 0;
				} else {
					$form["VALUE"] = 'now';
					$form["ChooseControl"] = 1;
				}
				$form["Format"] = "{day}/{month}/{year}";
				$form["Months"] = array(
													""=>"Select A Month",
													"01"=>"January",
													"02"=>"February",
													"03"=>"March",
													"04"=>"April",
													"05"=>"May",
													"06"=>"June",
													"07"=>"July",
													"08"=>"August",
													"09"=>"September",
													"10"=>"October",
													"11"=>"November",
													"12"=>"December"
												);
			}

			if (!isset($form["STYLE"]) && $form['TYPE'] == 'textarea') {
				$form["STYLE"] = "WIDTH:500px;HEIGHT:250px;";
			}

			if ($form['TYPE'] == 'select' && $actInfoFormOverRides['TYPE'] != 'select_multi' && isset($form['SIZE'])) {
				unset($form['SIZE']);
			}

			$formParams[$Field] = $form;
		}
		if ( isset($info[TABLE_CONFIG][OTHER_OBJECTS]) && is_array($info[TABLE_CONFIG][OTHER_OBJECTS]) ) {
			// -- for now additional elements draw right before the input box
			foreach ($info[TABLE_CONFIG][OTHER_OBJECTS] as $key=>$value) {
				$formParams[$key] = $value;
			}
		}
		$this->performance['getTableInfo'][] = (get_microtime_ms() - $methodStartTime);
	}

	/**
	 *  Analyze the column type, parse it, and return
	 *  to the class for prepare the form.
	 *
	 *  @access private
	 *  @param string $type MySQL column description
	 *  @return array Parsed information
	 */
	function parseColumnInfo($type,$Default,$Field) {
		$type = trim($type);
		$pos = strpos($type,'(');
		if ( $pos !== false) {
			$extra = substr($type,$pos+1);
			$extra[strlen($extra)-1] = ' ';
			$type = substr($type,0,$pos);
		}

		$return = array();
		if (!empty($Default)) {
			$return["VALUE"] = $Default;
		}
		switch( strtolower($type) ) {
			case "int":
				$return["TYPE"] = "text";
				$return["MAXLENGTH"] = $extra;
				$return["SIZE"] = (floor($extra/1.5) > 50) ? 50 : floor($extra/1.5);
				if ($Field == $this->tableDefinition[TABLE_CONFIG][OBJECT_PK]) {
					$return["ValidateAsInteger"] = 1;
				}
				break;
			case "float":
				$t=explode(",",$extra);
				$return["TYPE"] = "text";
				$return["MAXLENGTH"] = $t[0]+$t[1]+1;
				$return["SIZE"] = (floor($t[0]+$t[1]+1/1.5) > 50) ? 50 : floor($t[0]+$t[1]+1/1.5);;
				if ($Field == $this->tableDefinition[TABLE_CONFIG][OBJECT_PK]) {
					$return["ValidateAsFloat"] = 1;
				}
				break;
			case "varchar":
				$return["TYPE"] = "text";
				$return["MAXLENGTH"] = $extra;
				$return["SIZE"] = (floor($extra/1.5) > 50) ? 50 : floor($extra/1.5);
				if ($Field == $this->tableDefinition[TABLE_CONFIG][OBJECT_PK]) {
					$return["ValidateAsNotEmpty"] = 1;
				}
				break;
			case "mediumtext":
			case "longtext":
				$return["TYPE"] = "textarea";
				$return["STYLE"] = "WIDTH:500px;HEIGHT:250px;";
				$return["MAXLENGTH"] = ($type == 'mediumtext') ? 16777215 : 4294967296;
				break;
			case "date":
				require_once("form_date.php");
				$return["TYPE"] = "custom";
				$return["CustomClass"] = "form_date_class";
				$return["VALUE"] = 'now';
				$return["ChooseControl"] = 1;
				$return["Format"] = "{day}/{month}/{year}";
				$return["Months"] = array(
													""=>"Select A Month",
													"01"=>"January",
													"02"=>"February",
													"03"=>"March",
													"04"=>"April",
													"05"=>"May",
													"06"=>"June",
													"07"=>"July",
													"08"=>"August",
													"09"=>"September",
													"10"=>"October",
													"11"=>"November",
													"12"=>"December"
												);
				break;
			case "timestamp":
			case "datetime":
				require_once("form_date.php");
				$return["TYPE"] = "custom";
				$return["CustomClass"] = "form_date_class";
				$return["VALUE"] = 'now';
				$return["ChooseControl"] = 1;
				$return["Format"] = "{day}/{month}/{year}";
				$return["Months"] = array(
													""=>"Select A Month",
													"01"=>"January",
													"02"=>"February",
													"03"=>"March",
													"04"=>"April",
													"05"=>"May",
													"06"=>"June",
													"07"=>"July",
													"08"=>"August",
													"09"=>"September",
													"10"=>"October",
													"11"=>"November",
													"12"=>"December"
												);
				break;
			case "enum":
				$return["TYPE"] = "select";
				$options = & $return["OPTIONS"];
				$return["OPTIONS"][""] = "Select One";
				$max = strlen($extra);
				$buf = "";
				for($i=0; $i < $max; $i++)
					switch ( $extra[$i] ) {
						case "'":
						case '"':
							$end = $extra[$i++];

							for(;$i < $max && $extra[$i] != $end; $i++) {
								if ( $extra[$i] == "\\") {
									$buf .= $extra[$i+1];
									$i++;
									continue;
								}
								$buf .= $extra[$i];
							}
							break;
						case ",":
							$options[$buf] = $buf;
							$buf = "";
							break;
					}
					if ( $buf!='') {
						$return["OPTIONS"][$buf] = $buf;
					}
				break;
			default:
				$return["TYPE"] = "text";
				break;
		}

		return $return;
	}
}

class cruddyMysqlAdmin extends cruddyMysql {

	function cruddyMysqlAdmin() {
		if (strtoupper(substr(PHP_OS,0,3)=='WIN')) {
			$this->isWindows = true;
			$this->systemDirectorySeparator = '\\';
		} else {
			$this->isWindows = false;
			$this->systemDirectorySeparator = '/';
		}

		$this->paintedHead = false;
		$this->adminFile = getcwd().$this->systemDirectorySeparator."configurations".$this->systemDirectorySeparator."crud_".$_SERVER['SERVER_NAME']."_".ABS_PATH_HASH.".config.php";
		$this->functionsFile = getcwd().$this->systemDirectorySeparator."configurations".$this->systemDirectorySeparator."crud_".$_SERVER['SERVER_NAME']."_".ABS_PATH_HASH.".custom.functions.php";
		$this->functionsDrawFile = getcwd().$this->systemDirectorySeparator."configurations".$this->systemDirectorySeparator."crud_".$_SERVER['SERVER_NAME']."_".ABS_PATH_HASH.".draw.functions.php";
		$this->databaseConnectionFile = getcwd().$this->systemDirectorySeparator."configurations".$this->systemDirectorySeparator."crud_".$_SERVER['SERVER_NAME']."_".ABS_PATH_HASH.".connections.php";
		if ($this->adminDBExists()) {
			$this->currentAdminDB = $this->readAdminDB();
		}

		$this->steps[1] = 'initialize_server';
		$this->steps[2] = 'select_database';
		$this->steps[3] = 'select_tables';
		$this->steps[4] = 'select_groups';
		$this->steps[5] = 'select_roles';
		$this->steps[6] = 'select_users';
		$this->steps[7] = 'select_theme';

		$this->cruddyAdministrator = (isset($_COOKIE['current_role'])) ? $this->currentAdminDB['crud']['roles'][$_COOKIE['current_role']]['admin_role'] : false;
		$this->dateTime = date("Y-m-j H:i:s");

		// -- update these to whayou want your get string to look like  with concatenated TABLE by the time the user clicks
		$this->actionTypes = array();
		$this->actionTypes['new'] = "new_"; // + {TABLENAME} will be concatenated to match the action
		$this->actionTypes['delete'] = "delete_"; // + {TABLENAME}
		$this->actionTypes['update'] = "update_"; // + {TABLENAME}
		$this->actionTypes['read'] = "show_"; // + {TABLENAME}
		$this->actionTypes['view'] = "view_"; // + {TABLENAME}
		$this->actionTypes['order_field'] = "sort_by"; // no additional
		$this->actionTypes['order_direction'] = "direction"; // no additional
		$this->actionTypes['page'] = "page"; // no additional

		$this->tableControlDefaults = array();
		$this->tableControlDefaults[EDIT_TEXT] = "Edit";
		$this->tableControlDefaults[DELETE_TEXT] = "Delete";
		$this->tableControlDefaults[ADD_TEXT] = "Add New {table_desc}";
		$this->tableControlDefaults[TABLE_TEXT] = "{table_desc} Administration";
		$this->tableControlDefaults[VIEW_TEXT] = "View";
		$this->tableControlDefaults[SEARCH_TEXT] = "Search";
		$this->tableControlDefaults[OBJECT_DELETE_CHECK_CONSTRAINTS] = 0;
		$this->tableControlDefaults[OBJECT_HIDE_DELETE_LINK] = 0;
		$this->tableControlDefaults[OBJECT_HIDE_EDIT_LINK] = 0;
		$this->tableControlDefaults[OBJECT_HIDE_NEW_LINK] = 0;
		$this->tableControlDefaults[OBJECT_HIDE_VIEW_LINK] = 0;
		$this->tableControlDefaults[OBJECT_HIDE_SEARCH_LINK] = 0;

		$this->tableControlDefaults[OBJECT_HIDE_DETAILS_LINK] = 0;
		$this->tableControlDefaults[OBJECT_DELETE_CHECK_CONSTRAINTS] = 0;
		$this->tableControlDefaults[OBJECT_PAGING] = 1;
		$this->tableControlDefaults[OBJECT_ACTIONS] = $this->actionTypes;
		$this->tableControlDefaults[REQUIRED_TEXT] = "*";
		$this->tableControlDefaults[OBJECT_PAGING_NUM_ROWS_PER_PAGE] = 10;
		$this->tableControlDefaults[OBJECT_PAGING_SCROLL] = 5;

		$this->tableControlType = array();
		$this->tableControlType[0]['desc'] = "Table Name";
		$this->tableControlType[0]['type'] = "";
		$this->tableControlType[OBJECT_DESC]['desc'] = "Table Desc.";
		$this->tableControlType[OBJECT_DESC]['type'] = "text";
		$this->tableControlType[TABLE_TEXT]['desc'] = "Table Name Text";
		$this->tableControlType[TABLE_TEXT]['type'] = "text";
		$this->tableControlType[EDIT_TEXT]['desc'] = "Edit Link Text or Image Src";
		$this->tableControlType[EDIT_TEXT]['type'] = "text";
		$this->tableControlType[ADD_TEXT]['desc'] = "Add Link Text or Image Src";
		$this->tableControlType[ADD_TEXT]['type'] = "text";
		$this->tableControlType[VIEW_TEXT]['desc'] = "View Link Text or Image Src";
		$this->tableControlType[VIEW_TEXT]['type'] = "text";
		$this->tableControlType[SEARCH_TEXT]['desc'] = "Search Link Text or Image Src";
		$this->tableControlType[SEARCH_TEXT]['type'] = "text";
		$this->tableControlType[DELETE_TEXT]['desc'] = "Delete Link Text or Image Src";
		$this->tableControlType[DELETE_TEXT]['type'] = "text";
		$this->tableControlType[OBJECT_DELETE_CHECK_CONSTRAINTS]['desc'] = "Referential Integrity<br/>On Same Fields?";
		$this->tableControlType[OBJECT_DELETE_CHECK_CONSTRAINTS]['type'] = "checkbox";
		/*$this->tableControlType[OBJECT_PK]['desc'] = "Primary Key";
		$this->tableControlType[OBJECT_PK]['type'] = "text";*/
		$this->tableControlType[OBJECT_DEFAULT_ORDER]['desc'] = "Default Order<br/>{FIELDNAME} DESC/ASC";
		$this->tableControlType[OBJECT_DEFAULT_ORDER]['type'] = "text";
		$this->tableControlType[OBJECT_READ_FILTER]['desc'] = "WHERE Clause Filter On Read";
		$this->tableControlType[OBJECT_READ_FILTER]['type'] = "text";
		$this->tableControlType[OBJECT_FILTER_DESC]['desc'] = "Description of Filter";
		$this->tableControlType[OBJECT_FILTER_DESC]['type'] = "text";
		$this->tableControlType[OBJECT_HIDE_NEW_LINK]['desc'] = "Hide \"Create\" Link";
		$this->tableControlType[OBJECT_HIDE_NEW_LINK]['type'] = "checkbox";
		$this->tableControlType[OBJECT_HIDE_DELETE_LINK]['desc'] = "Hide \"Delete\" Link";
		$this->tableControlType[OBJECT_HIDE_DELETE_LINK]['type'] = "checkbox";
		$this->tableControlType[OBJECT_HIDE_EDIT_LINK]['desc'] = "Hide \"Edit\" Link";
		$this->tableControlType[OBJECT_HIDE_EDIT_LINK]['type'] = "checkbox";
		$this->tableControlType[OBJECT_HIDE_VIEW_LINK]['desc'] = "Hide \"View\" Link";
		$this->tableControlType[OBJECT_HIDE_VIEW_LINK]['type'] = "checkbox";
		$this->tableControlType[OBJECT_HIDE_SEARCH_LINK]['desc'] = "Hide \"Search\" Link";
		$this->tableControlType[OBJECT_HIDE_SEARCH_LINK]['type'] = "checkbox";
		$this->tableControlType[OBJECT_HIDE_DETAILS_LINK]['desc'] = "Hide \"Details\" Link";
		$this->tableControlType[OBJECT_HIDE_DETAILS_LINK]['type'] = "checkbox";
		$this->tableControlType[OBJECT_PAGING]['desc'] = "Show Paging<br/>(Default 10 Records/Page)";
		$this->tableControlType[OBJECT_PAGING]['type'] = "checkbox";
		$this->tableControlType[OBJECT_PAGING_NUM_ROWS_PER_PAGE]['desc'] = "# of Rows<br/>Per Page";
		$this->tableControlType[OBJECT_PAGING_NUM_ROWS_PER_PAGE]['type'] = "text";
		$this->tableControlType[OBJECT_PAGING_SCROLL]['desc'] = "Number of Pages<br/>Linked Ahead";
		$this->tableControlType[OBJECT_PAGING_SCROLL]['type'] = "text";
		$this->tableControlType[REQUIRED_TEXT]['desc'] = "Required<br/>Post Text";
		$this->tableControlType[REQUIRED_TEXT]['type'] = "text";

		 //uneditable tableControlTypes not avail in the interface for now and are managed in the core class logic
		 // OBJECT_CONNECTION_STRING - automatically set - @todo autoupdate when old server and password change
		 // OBJECT_ACTIONS - based on actions in constructor
		 // OBJECT_TABLE - automatically set
		 // OTHER_OBJECTS -- this could and should be populated manually as an advanced config in your pre_process_load_ function if you want other objects available in the DOM of the crud form.  Dont worry about storing it in the serialized array

		$this->fieldControlDefaults = array();
		$this->fieldControlDefaults[SORTABLE] = 1;
		$this->fieldControlDefaults[REQUIRED] = 0;
		$this->fieldControlDefaults[SHOWCOLUMN] = 1;

		$this->fieldControlType = array();
		$this->fieldControlType[CAPTION]['desc'] = "Field Caption";
		$this->fieldControlType[CAPTION]['type'] = "text";
		$this->fieldControlType[SHOWCOLUMN]['desc'] = "Show Column On Read";
		$this->fieldControlType[SHOWCOLUMN]['type'] = "checkbox";
		$this->fieldControlType[UPDATE_READ_ONLY]['desc'] = "Read Only";
		$this->fieldControlType[UPDATE_READ_ONLY]['type'] = "checkbox";
		$this->fieldControlType[HIDE]['desc'] = "Hide On Insert";
		$this->fieldControlType[HIDE]['type'] = "checkbox";
		$this->fieldControlType[REQUIRED]['desc'] = "Required Field";
		$this->fieldControlType[REQUIRED]['type'] = "checkbox";
		$this->fieldControlType[TABLE]['desc'] = "Lookup Table";
		$this->fieldControlType[TABLE]['type'] = "text";
		$this->fieldControlType[ID]['desc'] = "Lookup Field (Key/ID)";
		$this->fieldControlType[ID]['type'] = "text";
		$this->fieldControlType[TEXT]['desc'] = "Lookup Field <br/>(Description)";
		$this->fieldControlType[TEXT]['type'] = "text";
		$this->fieldControlType[COLUMNPOSTTEXT]['desc'] = "Post Text<br/>(Add/Update)";
		$this->fieldControlType[COLUMNPOSTTEXT]['type'] = "text";
		$this->fieldControlType[PRETEXTREAD]['desc'] = "Pre-Text<br/>(On Read)";
		$this->fieldControlType[PRETEXTREAD]['type'] = "text";
		$this->fieldControlType[POSTTEXTREAD]['desc'] = "Post-Text<br/>(On Read)";
		$this->fieldControlType[POSTTEXTREAD]['type'] = "text";
		$this->fieldControlType[SORTABLE]['desc'] = "Sortable";
		$this->fieldControlType[SORTABLE]['type'] = "checkbox";

		$this->fieldConfigType = array();
		$this->fieldConfigType["TYPE"]['desc'] = "Input Type";
		$this->fieldConfigType["TYPE"]['type'] = "link";
		$this->fieldConfigType["VALUE"]['desc'] = "Default Value";
		$this->fieldConfigType["VALUE"]['type'] = "text";

		$this->fieldObjectTypes = array();
		$this->fieldObjectTypes['file']['desc'] = "File Upload";
		$this->fieldObjectTypes['text']['desc'] = "Text";
		$this->fieldObjectTypes['password']['desc'] = "Password";
		$this->fieldObjectTypes['checkbox']['desc'] = "Checkbox";
		 //$this->fieldObjectTypes['radio']['desc'] = "Radio";
		$this->fieldObjectTypes['hidden']['desc'] = "Hidden";
		$this->fieldObjectTypes['textarea']['desc'] = "Text Area";
		$this->fieldObjectTypes['select']['desc'] = "Select Box";
		$this->fieldObjectTypes['select_multi']['desc'] = "Select Box (Multi)";
		$this->fieldObjectTypes['wysiwyg']['desc'] = "HTML Editor";
		$this->fieldObjectTypes['date']['desc'] = "Date";
		$this->fieldObjectTypes['timestamp']['desc'] = "Time Stamp";


		$this->fieldValidationTypes = array();
		$this->fieldValidationTypes['ValidateAsEmail']['desc'] = "Validate As Email";
		$this->fieldValidationTypes['ValidateRegularExpression']['desc'] = "Validate Regular Expression (Match Found)";
		$this->fieldValidationTypes['ValidateAsURL']['desc'] = "Validate As URL";
		$this->fieldValidationTypes['ValidateAsNotRegularExpression']['desc'] = "Validate Regular Expression (Match Not Found)";
		$this->fieldValidationTypes['ValidateAsNotEmpty']['desc'] = "Validate as Not Empty";
		$this->fieldValidationTypes['ValidateMinimumLength']['desc'] = "Validate Minimum Length";
		$this->fieldValidationTypes['ValidateAsEqualTo']['desc'] = "Validate As Equal To";
		$this->fieldValidationTypes['ValidateAsDifferentFrom']['desc'] = "Validate As Different From";
		$this->fieldValidationTypes['ValidateAsInteger']['desc'] = "Validate As Integer";
		$this->fieldValidationTypes['ValidateAsFloat']['desc'] = "Validate As Float";
		$this->fieldValidationTypes['DiscardInvalidValues']['desc'] = "Discard Invalid Values";
		$this->fieldValidationTypes['ReplacePatterns']['desc'] = "Replace Patterns";
		$this->fieldValidationTypes['Capitalization']['desc'] = "Capitalization";


		$this->fieldEventTypes = array();
		$this->fieldEventTypes['ONBLUR']['desc'] = "On Blur";
		$this->fieldEventTypes['ONCLICK']['desc'] = "On Click";
		$this->fieldEventTypes['ONCHANGE']['desc'] = "On Change";
		$this->fieldEventTypes['ONDBLCLICK']['desc'] = "On Double Click";
		$this->fieldEventTypes['ONFOCUS']['desc'] = "On Focus";
		$this->fieldEventTypes['ONKEYDOWN']['desc'] = "On Key Down";
		$this->fieldEventTypes['ONKEYUP']['desc'] = "On Key Up";
		$this->fieldEventTypes['ONMOUSEDOWN']['desc'] = "On Mouse Down";
		$this->fieldEventTypes['ONMOUSEMOVE']['desc'] = "On Mouse Move";
		$this->fieldEventTypes['ONMOUSEOUT']['desc'] = "On Mouse Out";
		$this->fieldEventTypes['ONMOUSEOVER']['desc'] = "On Mouse Over";
		$this->fieldEventTypes['ONMOUSEUP']['desc'] = "On Mouse Up";


		$this->fieldMiscTypes = array();
		$this->fieldMiscTypes['TITLE']['desc'] = "Title";
		$this->fieldMiscTypes['TITLE']['type'] = "text";
		$this->fieldMiscTypes['TABINDEX']['desc'] = "Table Index";
		$this->fieldMiscTypes['TABINDEX']['testdata'] = "5";
		$this->fieldMiscTypes['TABINDEX']['type'] = "text";
		$this->fieldMiscTypes['STYLE']['desc'] = "Inline Style";
		$this->fieldMiscTypes['STYLE']['testdata'] = "background-color:black;color:white;";
		$this->fieldMiscTypes['STYLE']['type'] = "textarea";
		$this->fieldMiscTypes['CLASS']['desc'] = "Class Name";
		$this->fieldMiscTypes['CLASS']['testdata'] = "none";
		$this->fieldMiscTypes['CLASS']['type'] = "text";
		$this->fieldMiscTypes['LABEL']['desc'] = "Label Name";
		$this->fieldMiscTypes['LABEL']['type'] = "text";
		$this->fieldMiscTypes['MOVE_TO']['desc'] = "Location To Move:";
		$this->fieldMiscTypes['MOVE_TO']['type'] = "text";
		$this->fieldMiscTypes['MIME']['desc'] = "Mime Type Field Storage:";
		$this->fieldMiscTypes['MIME']['type'] = "text";
		$this->fieldMiscTypes['FILE_SIZE']['desc'] = "File Size Field Storage:";
		$this->fieldMiscTypes['FILE_SIZE']['type'] = "text";
		$this->fieldMiscTypes['ACCESSKEY']['desc'] = "Access Key";
		$this->fieldMiscTypes['ACCESSKEY']['testdata'] = "t";
		$this->fieldMiscTypes['ACCESSKEY']['type'] = "text";
		$this->fieldMiscTypes['ExtraAttributes']['desc'] = "Extra Attributes";
		$this->fieldMiscTypes['ExtraAttributes']['testdata'] = "";
		$this->fieldMiscTypes['ExtraAttributes']['type'] = "text";
	}

	function paintHead() {
		if ($this->paintedHead !== true) {
			$this->paintedHead = true;
			if (isset($crudAdmin->currentAdminDB['crud']['console_name'])) {
				$desc = $crudAdmin->currentAdminDB['crud']['console_name']." Administrator";
			}

			/*else {
				$extra = (isset($_GET['admin'])) ? " Configuration Setup" : "";
				$desc = "CRUDDY MYSQL " . $extra;
			}*/

			if (!isset($_GET['admin'])) {
				$bodyOnKeyPress = "onkeypress=\"handleEscapeKey(event);\"";
			} else {
				$bodyOnKeyPress = "";
			}

			if ($this->currentAdminDB['crud']['theme'] != 'None') {
				$themeCSS = '<link rel="stylesheet" type="text/css" href="'.$this->displayThemeCSS().'" />';
			}

			$scriptsAndCss = '
			<link rel="stylesheet" type="text/css" href="'.$this->displayGlobalCSS().'" />
			'.$themeCSS.'
			<script type="text/javascript" src="'.ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'scripts/crud_admin.js"></script>
			<link type="text/css" href="'.ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'scripts/css/ui-lightness/jquery-ui-1.8.11.custom.css" rel="stylesheet" />   
			<script type="text/javascript" src="'.ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'scripts/js/jquery-1.5.1.min.js"></script>
			<script type="text/javascript" src="'.ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'scripts/js/jquery-ui-1.8.11.custom.min.js"></script>

			<script type="text/javascript">
				var cruddy = jQuery.noConflict();
			</script>
			<script type="text/javascript" src="'.ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'scripts/prototype.js"></script>
			<script type="text/javascript" src="'.ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'scripts/cruddy_mysql.js"></script>';

			if (!$this->isPageInclude) {
				echo '
					<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
					<html xmlns="http://www.w3.org/1999/xhtml">
					<head>
					<title>'.$desc.'</title>
					'.$scriptsAndCss.'
					</head>
					<body '.$bodyOnKeyPress.'>
				';
			} else {
				echo $scriptsAndCss;
			}

			//if (isset($_GET['msg'])) {
				echo "<h3 style='color:#E63C1E;font-size:1.5em' id='response'>".$_GET['msg']."</h3>";
			//}

			if (isset($crudAdmin->currentAdminDB['crud']['console_name'])) {
				echo "<div style=\"float:left;padding-right:16px;\"><h1>";
				echo (isset($_GET['admin'])) ? "" : "<a href=\"$_SERVER[PHP_SELF]\">";
				echo $desc;
				echo (isset($_GET['admin'])) ? "" : "</a>";
				echo "</h1></div><div id=\"clear\"></div>";
			}
		}
		$this->paintAdminAndGroupLinks();
	}

	function paintAdminAndGroupLinks() {
		//if ($this->paintedAdminAndGroups !== true || $this->isPageInclude) {
		//	$this->paintedAdminAndGroups = true;
			if ($this->cruddyAdministrator) {
				if (is_array($this->currentAdminDB['crud']['mysql_server_names'])) {
					$serverOptions = "<option value=\"\" selected>Select a Server</option>";
					foreach ($this->currentAdminDB['crud']['mysql_server_names'] as $key=>$value) {
						$serverOptions .= "<option value=\"$key\">Edit: $key</option>";
					}
					$serverOptions .= "<option value=\"add\">Add a new server</option>";
				}

				if (is_array($this->currentAdminDB['crud']['mysql_databases'])) {
					$databaseOptions = "<option value=\"\" selected>Select a Database</option>";
					foreach ($this->currentAdminDB['crud']['mysql_databases'] as $values) {
						foreach ($values as $database) {
							if (strlen($this->currentAdminDB['crud']['mysql_master_database_configuration']) > 0 && $database != $this->currentAdminDB['crud']['mysql_master_database_configuration']) {
								continue;
							}
							$databaseOptions .= "<option value=\"$database\">Edit: $database</option>";
						}
					}
				}
				$fieldsOptions = "<option value=\"\" selected>Select a Table</option>";
				if (is_array($this->currentAdminDB['crud']['mysql_databases'])) {
					foreach ($this->currentAdminDB['crud']['mysql_databases'] as $server=>$values) {
						foreach ($values as $database) {
							if (strlen($this->currentAdminDB['crud']['mysql_master_database_configuration']) > 0 && $database != $this->currentAdminDB['crud']['mysql_master_database_configuration']) {
								continue;
							}
							$fieldsOptions .= "<optgroup label='$server -> $database'>";
							foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG] as $key=>$value) {
								//if (stristr($key,$database."_")) {
									$fieldsOptions .= "<option class=\"$database\" value=\"".$key."\" title=\"".$server."\">Edit fields: ".$key."</option>";
								//}
							}
							$fieldsOptions .= "</optgroup>";
						}
					}
				}
				if (!$this->isPageInclude) {
					$editThemeLink = (isset($this->currentAdminDB['crud']['theme'])) ? "&edit=".$this->currentAdminDB['crud']['theme'] : "";
				}
			}
			
			if ($_COOKIE['current_user']) {
				
				if (strlen($this->currentAdminDB['crud']['mysql_master_database_configuration']) > 0) {
					$databaseList = "<li><a>Select a DB:</a><select style='width:77px;position:absolute;margin-left: -35px; margin-top: -17px;' onchange=\"createCookie('current_db',this.value,1);document.location=document.location;\">";
					
					foreach ($this->currentAdminDB['crud']['mysql_databases'] as $mySQLServerHash=>$allDBs) {
						foreach ($allDBs as $db) {
							$selected = '';
							if ( ($_COOKIE['current_db'] == "$mySQLServerHash-$db") || (empty($_COOKIE['current_db']) && $db == $this->currentAdminDB['crud']['mysql_master_database_configuration']) ) {
								if (empty($_COOKIE['current_db'])) {
									$redirect=true;
									setcookie("current_db", "$mySQLServerHash-$db", time()+3600*24*7,"/");
								}
								$selected = 'selected';
							}
							$databaseList .= "<option $selected value=\"$mySQLServerHash-$db\">$db</option>";
						}
					}
					$databaseList .= "</select></li>";
				}
				
				$groupLinks = "
					<div style=\"float:left\" id=\"menu1\" class=\"menu\">
						<div id=\"m-top\">
							<ul id=\"m-top-ul1\">
								<li><a href=\"?\">Home</a></li>\n";
				if (isset($this->currentAdminDB['crud']['groups']) && $this->currentAdminDB['crud']['group_tables'] == 1) {
					foreach ($this->currentAdminDB['crud']['groups'] as $k=>$v) {
						if (!in_array($k,$this->currentAdminDB['crud']['roles'][$_COOKIE['current_role']]['groups'])) {
							continue;
						}
						$groupLinks .= "\t\t\t\t\t<li><a href=\"?group=$k\">$k</a></li>\n";
					}
				}
				
				$groupLinks .= "
						$databaseList
						<li><a onclick=\"javascript:eraseCookie ('current_user');eraseCookie ('current_role');document.location= '$_SERVER[PHP_SELF]';\" href=\"#\">Log Out</a></li>
						</ul>
					</div>
					<div id=\"m-slider\">
						<div id=\"slider1\"></div>
					</div>
				</div>";
				echo $groupLinks;
				if ($redirect) {
					echo "<script>document.location=document.location;</script>";
				}
			}
			if (!$this->isPageInclude) {
				$logOutLink = "";
				if ($this->cruddyAdministrator) {
					$logOutLink = "
					<li><a onclick=\"document.location= 'pages';\" href=\"#\">Drop-In<br/>Includes</a></li>
					";
				}
				$logOutLink .= "
				<li><a onclick=\"javascript:eraseCookie ('current_user');eraseCookie ('current_role');document.location= '$_SERVER[PHP_SELF]';\" href=\"#\">Log Out</a></li>
				";
			}
			if ($this->cruddyAdministrator) {

				$themes = "<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=true&select_theme=true$editThemeLink';\" href=\"#\">Themes</a></li>";
				if (!$this->isPageInclude) {
					$serversAndDatabases = "
							<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);$('serverList').style.left = $('slider2').style.marginLeft;$('serverList').style.display = 'inline';\" href=\"#\">Servers</a></li>
							<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=true&select_database=true&edit=true';\" href=\"#\">Databases</a></li>
";
					$groupsRolesUsers = "
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=true&select_groups=true&edit=true';\" href=\"#\">Groups</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=1&select_roles&edit=true';\" href=\"#\">Roles</a></li>
					<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]?admin=1&select_users&edit=true';\" href=\"#\">Users</a></li>";
					$fieldsOnClick = "$('FieldList').style.left = $('slider2').style.marginLeft;$('FieldList').style.display = 'inline';";
					$tablesOnClick = "$('databaseList').style.left = $('slider2').style.marginLeft;$('databaseList').style.display = 'inline';";
				} else {
					$fieldsOnClick = "document.location = '$_SERVER[PHP_SELF]?admin=true&select_fields&edit={$_REQUEST['tablePointer']}&conf={$this->current_config}';";
					$tablesOnClick = "document.location = '$_SERVER[PHP_SELF]?admin=true&select_tables&edit={$_REQUEST['tablePointer']}&conf={$this->current_config}';";
				}

				if ($this->isProductionized===false) {
					// -- if array is still a security risk, make sure user converts into PHP array file for inclusion
					$productionizeLink = "<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location = '$_SERVER[PHP_SELF]?admin=true&productionize&conf={$this->current_config}'\" href=\"#\">Production<br/>Finalize</a></li>";
				}
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
				<div style=\"clear:both;\"></div>
				<div style=\"float:left\" id=\"menu2\" class=\"menu2\">
					<div id=\"m-top\">
						<ul id=\"m-top-ul2\">

							<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]';\" href=\"#\">Home</a></li>
							$serversAndDatabases
							<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);$tablesOnClick\" href=\"#\">Tables</a></li>
							$groupsRolesUsers
							$themes
							<!--<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location = '$_SERVER[PHP_SELF]?admin=true&export_phpGrids&edit={$_REQUEST['tablePointer']}';\" href=\"#\">phpGrids</a></li>-->
							<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);$fieldsOnClick\" href=\"#\">Fields</a></li>
							$productionizeLink
							$logOutLink
						</ul>
					</div>
					<div id=\"m-slider\">
						<div id=\"slider2\"></div>
					</div>
				</div>
				";

				if ($this->isPageInclude) {
					echo "<div style=\"float:right\"><a onclick=\"javascript:alert('The links to the left are to configure $this->current_config configuration.')\" href=\"#\">($this->current_config)</a></div>";
				}
			} elseif ( (isset($this->currentAdminDB['crud']['groups']) && $this->currentAdminDB['crud']['group_tables'] == 1) == false && $_COOKIE['current_user'] && !isset($_COOKIE['tempAdmin'])) {
				echo
				"<div style=\"clear:both;\"></div>
				<div style=\"float:left\" id=\"menu2\" class=\"menu2\">
					<div id=\"m-top\">
						<ul id=\"m-top-ul2\">
							<li><a onclick=\"javascript:createCookie('redirect','$_SERVER[REQUEST_URI]',1);document.location= '$_SERVER[PHP_SELF]';\" href=\"#\">Home</a></li>
							$logOutLink
						</ul>
					</div>
					<div id=\"m-slider\">
						<div id=\"slider2\"></div>
					</div>
				</div>";
			}
			echo "<div id=\"clear\"></div>";
		//}
	}

	function replaceTokens($search,$config) {
		return str_replace(array("{table_desc}","{table_name}"),array($config[OBJECT_DESC],$config[OBJECT_TABLE]),$search);
	}

	function paint($currentTable,$mysqlServer='',$mysqlUsername='',$mysqlPassword='',$mysqlDatabase='',$configurationFile='') {

		if (isset($_GET['group'])) {
			if (!in_array($currentTable,$this->currentAdminDB['crud']['groups'][$_GET['group']])) {
				return;
			}
		}

		if ($mysqlServer && !$configurationFile) {
			$configurationFile = $currentTable;
		}

		if ($configurationFile) {
			/*
			 * you can manage a table completely without storing mySQL credentials in the configuration and can simply call paint directly pointing to your servername and telling it the name of your configuration like so
			 *
			 * include("cruddy_mysql/cruddy_mysql.php");
			 * $crudAdmin = new cruddyMysqlAdmin();
			 * $crudAdmin->paint('table','localhost','root','root','database','file_configuration_name');
			 */
			$this->isPageInclude = true;
			$this->adminFile  = getcwd().$this->systemDirectorySeparator."configurations".$this->systemDirectorySeparator.$configurationFile.".config.php";
			$this->functionsFile  = getcwd().$this->systemDirectorySeparator."configurations".$this->systemDirectorySeparator.$configurationFile.".custom.functions.php";
			if ($this->adminDBExists()) {
				$this->currentAdminDB = $this->readAdminDB();
			} else {
				$this->currentAdminDB = array();
				$this->override['select_fields'] = true;
			}
			if (isset($this->configs[$configurationFile])) {
				die("It is recommended to pass different configuration files for each table you are showing on a page.  \"$configurationFile\" is already used.  Pass a different filename.");
			}
			$this->configs[$configurationFile] = $configurationFile;
			$this->current_config = $configurationFile;
		} else {
			$this->isPageInclude = false;
		}
		$crudTableControl = $this->currentAdminDB[CRUD_FIELD_CONFIG];
		if ($this->currentAdminDB['crud']['group_tables'] == 0 || count($_GET) != 0) {
			$crudActions = array(
									'new'=>strtolower($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['new'].$currentTable),
									'delete'=>strtolower($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['delete'].$currentTable),
									'update'=>strtolower($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['update'].$currentTable),
									'view'=>strtolower($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['view'].$currentTable),
									'read'=>strtolower($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['read'].$currentTable),
									);
			if (in_array($_GET['action'],$crudActions) || !isset($_GET['action']) ) {
				eval("
						if (function_exists('pre_process_load_".$currentTable."')) {
							\$crudTableControl[\$currentTable] = pre_process_load_".$currentTable."(\$crudTableControl[\$currentTable]);
						}");

				if (isset($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_CONNECTION_STRING])) {
					unset($_GET['msg']);
					
					if (strlen($this->currentAdminDB['crud']['mysql_master_database_configuration']) > 0) {
						list($server,$database) = explode('-',$_COOKIE['current_db']);
						$port = $this->currentAdminDB['crud']['mysql_ports'][$server];
						$serverName = $this->currentAdminDB['crud']['mysql_server_names'][$server];
						$user = $this->currentAdminDB['crud']['mysql_user_names'][$server];
						$pass = $this->currentAdminDB['crud']['mysql_passwords'][$server];
						$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_CONNECTION_STRING] = "mysql://$user:$pass@$serverName:$port/$database";
						$crudTableControl[$currentTable][TABLE_CONFIG]['all_databases'] = $this->currentAdminDB['crud']['mysql_databases'];
						$crudTableControl[$currentTable][TABLE_CONFIG]['all_ports'] = $this->currentAdminDB['crud']['mysql_ports'];
						$crudTableControl[$currentTable][TABLE_CONFIG]['all_servers'] = $this->currentAdminDB['crud']['mysql_server_names'];
						$crudTableControl[$currentTable][TABLE_CONFIG]['all_users'] = $this->currentAdminDB['crud']['mysql_user_names'];
						$crudTableControl[$currentTable][TABLE_CONFIG]['all_passwords'] = $this->currentAdminDB['crud']['mysql_passwords'];
					}
					$crudObject = new cruddyMysql($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_CONNECTION_STRING],$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_TABLE],$crudTableControl[$currentTable]);
				} else {
					if (!$mysqlServer) {
						die("'$currentTable' is not a valid CRUD table config and does not exist in the crud configuration '".basename($this->currentAdminDB)."'");
					} else {
						if ($_GET['edit'] != $currentTable && isset($_GET['edit']) || ($_POST['tablePointer'] != $currentTable) && isset($_POST['tablePointer'])) {
							return;
						}
						$_GET['server']       = $mysqlServer;
						$_GET['database']     = $mysqlDatabase;
						$_GET['username']     = $mysqlUsername;
						$_GET['password']     = $mysqlPassword;
						if (!isset($_GET['edit'])) {
							$_GET['edit']         = $currentTable;
							$_REQUEST['tablePointer'] = $currentTable;
						} else {
							$_REQUEST['tablePointer'] = $_GET['edit'];
						}
						$this->paintHead();
						if (!file_exists($this->adminFile) || isset($_GET['admin'])) {
							$this->handleAdminPages();
							unset($_GET['server'],$_GET['database'],$_GET['username'],$_GET['password'],$_REQUEST['tablePointer'],$_GET['edit']);
							return;
						} else {
							$crudObject = new cruddyMysql("mysql://$mysqlUsername:$mysqlPassword@$mysqlServer:3306/$mysqlDatabase",$currentTable,$crudTableControl[$currentTable]);
							if ($configurationFile) {
								$crudObject->isPageInclude = true;
								$crudObject->current_config = $configurationFile;
							}
							unset($_GET['server'],$_GET['database'],$_GET['username'],$_GET['password'],$_REQUEST['tablePointer'],$_GET['edit']);
						}
					}
				}
				// -- object_name can be used to describe your table
				if ($_COOKIE['current_db']) {
					list($void,$db) = explode('-',$_COOKIE['current_db']);
					$name = $db." ";
				}
				$crudObject->object_name = $name.$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_DESC];
				$crudObject->cruddyAdministrator = $this->cruddyAdministrator;
				$crudObject->object_key = $currentTable;
				$viewUrl = (!isset($_GET['action'])) ? 'action='.$crudActions['read'].'' : '';
				if ($this->isPageInclude) {
					$viewUrl .= "&conf=$this->current_config";
				}
				$viewText = (!isset($_GET['action'])) ? $this->replaceTokens($crudTableControl[$currentTable][TABLE_CONFIG][VIEW_TEXT],$crudTableControl[$currentTable][TABLE_CONFIG]) : '&larr; Back';
				$amp = (stristr($_SERVER['PHP_SELF'],"?")) ? '&' : '?';
				$newLink = '';

				list($wh,$filterDesc) = $this->buildSearchWhere($currentTable);
				if (isset($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_FILTER_DESC]) || !empty($filterDesc)) {
					if ($filterDesc) {
						$filterTxt = "<div style='float: left; margin-top: 7px;'>Filtered By:</div>".$filterDesc."<div style='clear:both;'></div>";
					} else {
						$filterTxt = $crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_FILTER_DESC];
					}
					$desc = "<h4 style='color:#333'>".$filterTxt."</h4>";
				}
				if ($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_NEW_LINK] == 0 || $crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_NEW_LINK] == 0) {

					// -- custom logic for each object on how it draws its links can go here by utilizing case statements of $currentTable
					
					$theLink = "<a href='?action=".strtolower($crudActions['new']);
					if ($this->isPageInclude) {
						$theLink .= "&conf=$this->current_config";
					}

					$theLink .= "'>".$this->replaceTokens($crudTableControl[$currentTable][TABLE_CONFIG][ADD_TEXT],$crudTableControl[$currentTable][TABLE_CONFIG])."</a> | ";

					if ($definitions[TABLE_CONFIG][OBJECT_HIDE_EDIT_LINK] != 1 && substr($_GET['action'],0,4) == "view") {
						$theLink = "<a href='".str_replace("action=view_","action=update_",$_SERVER['REQUEST_URI'])."'>Update This $crudObject->object_name</a> | ";
					} elseif (substr($_GET['action'],0,4) == "view") {
						// -- user cannot see the update link
						$theLink = "";
					}
					$newLink .= $theLink;
					$break = "<br/>";
				}
				
				if (!isset($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_VIEW_LINK]) || $crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_VIEW_LINK] == 0 ) {
					$viewLink = "<a href='?".$viewUrl."'>$viewText</a>";
				}

				if (!isset($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_SEARCH_LINK]) || $crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_SEARCH_LINK] == 0 ) {
					if ($_GET['action'] != $crudActions['new'] && $_GET['action'] != $crudActions['update']) {
						$searchTxt = $this->replaceTokens($crudTableControl[$currentTable][TABLE_CONFIG][SEARCH_TEXT],$crudTableControl[$currentTable][TABLE_CONFIG]);
						$searchLink = "<a style='cursor:pointer' onclick='if ($(\"{$currentTable}_search\").style.display==\"none\") { $(\"{$currentTable}_search\").style.display=\"block\"; this.innerHTML = \"Hide $searchTxt\"; } else { $(\"{$currentTable}_search\").style.display=\"none\"; this.innerHTML = \"".$searchTxt."\"; } '>".$searchTxt."</a> | ";
					}
				}

				if ($configurationFile == '') {
					$tableTxt = $this->replaceTokens($crudTableControl[$currentTable][TABLE_CONFIG][TABLE_TEXT],$crudTableControl[$currentTable][TABLE_CONFIG]);
					echo "<h2 style='color:#E63C1E;'>$tableTxt</h2>";
				}
				
				echo "
				$desc
				$newLink$searchLink$viewLink$break
				";
				if ($this->isPageInclude && $_GET['action'] != $crudActions['read']) {
					if (isset($_GET['conf']) && $_GET['conf'] != $this->current_config) {
						return;
					}
				}

				if ( $_GET['action'] != $crudActions['new'] && $_GET['action'] != $crudActions['update']) {
					$crudObject->search();
				}
				switch ( $_GET['action'] ) {
					case $crudActions['new']:
						$crudObject->button = array("TYPE"=>"submit","LABEL"=>"Add New ".$crudObject->object_name,"VALUE"=>"Add New ".$crudObject->object_name,"ID"=>INPUT_SUBMIT ,"NAME"=>INPUT_SUBMIT);
						eval("
						if (function_exists('new_pre_process_".$currentTable."')) {
							\$retPre = new_pre_process_".$currentTable."();
						} else {
							\$retPre = true;
						}");
						if ($retPre === true) {
							if ( $id = $crudObject->create() ) {
								eval("
								if (function_exists('new_post_process_".$currentTable."')) {
									\$retPost = new_post_process_".$currentTable."();
								} else {
									\$retPost = true;
								}");
								if ($retPost === true) {
									$msg = "A new ".$crudObject->object_name." was added (%23$id)";
								} else {
									$msg = $retPost;
								}
								$url = $_SERVER['PHP_SELF'].$amp."msg=".rawurldecode($msg);
							}
						} else {
							$url = $_SERVER['PHP_SELF'].$amp."msg=".rawurldecode($retPre);
						}
						break;
					case $crudActions['delete'];
						eval("
						if (function_exists('delete_pre_process_".$currentTable."')) {
							\$retPre = delete_pre_process_".$currentTable."();
						} else {
							\$retPre = true;
						}
						");
						if ($retPre === true) {
							if ( $crudObject->delete(array($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_PK] => $_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_PK]])) == true) {
								eval("
									if (function_exists('delete_post_process_".$currentTable."')) {
										\$retPost = delete_post_process_".$currentTable."();
									} else {
										\$retPost = true;
									}
									");
								if ($retPost === true) {
									if (intval($_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_PK]]) != 0) {
										$pound = "#";
									}
									$msg = "Your ".$crudObject->object_name." (".$pound.$_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_PK]].") has been deleted";
								} else {
									$msg = $retPost;
								}
								$url = $_SERVER['PHP_SELF'].$amp."msg=".rawurlencode($msg);
							}
						} else {
							$url = $_SERVER['PHP_SELF'].$amp."msg=".rawurldecode($retPre);
						}
						break;
					case $crudActions['update']:
						$crudObject->button = array("TYPE"=>"submit","LABEL"=>"Update ".$crudObject->object_name,"VALUE"=>"Update ".$crudObject->object_name,"ID"=>INPUT_SUBMIT ,"NAME"=>INPUT_SUBMIT);

						eval("
							if (function_exists('update_pre_process_".$currentTable."')) {
								\$retPre = update_pre_process_".$currentTable."();
							} else {
								\$retPre = true;
							}
							");
						if ($retPre === true) {
							if ( $crudObject->update(array($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_PK] => $_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_PK]])) == true) {
								eval("
									 if (function_exists('update_post_process_".$currentTable."')) {
										\$retPost = update_post_process_".$currentTable."();
									 } else {
										\$retPost = true;
									 }");
								if ($retPost === true) {
									$msg = $crudObject->object_name." has been updated";
								} else {
									$msg = $retPost;
								}
								$url = $_SERVER['PHP_SELF'].$amp."msg=".rawurldecode($msg);
							}
						} else {
							$url = $_SERVER['PHP_SELF'].$amp."msg=".rawurldecode($retPre);
						}
						break;
					case $crudActions['view']:
						// -- to do view
						$crudObject->view(array($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_PK] => $_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_PK]]));
						break;

					case $crudActions['read']:
					default:
						$orderBy = (!empty($_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['order_field']])) ? ' ORDER BY `' . $_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['order_field']] . '` ' . $_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['order_direction']] : '';
						if ($crudObject->isPageInclude) {
							if (isset($_GET['conf']) && $_GET['conf'] != $crudObject->current_config) {
								$orderBy = '';
							}
						}
						if (empty($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_READ_FILTER])) {
							$where = " WHERE 1=1 ";
						} else {
							$where = $crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_READ_FILTER];
						}
						$crudObject->read($where.$orderBy);
						break;
				}

				$this->redirect($url);
				echo "<hr />";
				if ($this->cruddyAdministrator) {
					foreach ($crudObject->performance as $k=>$v) {
						// -- if slow queries/methods greater than 10 seconds show warning
						if ($v[0] > 10) {
							echo "<div class=\"error\"><strong>Performance Issues Encountered</strong><br/><pre>";
							print_r($crudObject->performance);
							echo "</pre></div>";
						}
					}
				}
			}
		}
	}

	function redirect($url) {
		// -- handle URL redirects
		if ($url) {
			if (!headers_sent()) {
				header ("Location: ".$url);
			} else {
				echo "<script type='text/javascript'>document.location='".$url."';</script>";
			}
		}
	}

	function paintGroups() {
		if ($this->currentAdminDB['crud']['group_tables'] == 1 && count($_GET) == 0) {
			foreach ($this->currentAdminDB['crud']['groups'] as $k=>$v) {
				// -- show groups listing for user selection
				
				if (!is_array($this->currentAdminDB['crud']['roles'][$_COOKIE['current_role']]['groups'])) {
					continue;
				} elseif (!in_array($k,$this->currentAdminDB['crud']['roles'][$_COOKIE['current_role']]['groups'])) {
					continue;
				}
				echo "<a href=\"?group=$k\"><div class=\"groupBox\">View Records:<br/><strong>$k</strong><img style=\"margin-left:15px;\" src=\"".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/database.png\"/></div></a>";
			}
		}
	}

	function handleDatabaseListResultSet($result) {
		$data = mysql_fetch_array($result, 2);
		return $data;
	}

	function queryDatabase($query, $link = null) {
		$resultrows = array();
		if (is_string($query)) {
			$result = @mysql_query($query, $link);
		}
		// return empty array if result is empty or false
		if (! $result) {
			return $resultrows;
		}
		while ($row = @mysql_fetch_assoc($result)) {
			 $resultrows[] = $row;
		}
		mysql_free_result($result);
		return $resultrows;
	}
	function connectDatabase($hash,$database='') {
		if (isset($this->currentAdminDB['crud']['mysql_server_names'][$hash])) {
			$conn = @mysql_connect($this->currentAdminDB['crud']['mysql_server_names'][$hash].":".$this->currentAdminDB['crud']['mysql_ports'][$hash],$this->currentAdminDB['crud']['mysql_user_names'][$hash],$this->currentAdminDB['crud']['mysql_passwords'][$hash]);
		} else {
			$conn = @mysql_connect($_GET['server'].":3306",$_GET['username'],$_GET['password']);
		}
		if (!empty($database)) {
			if (mysql_select_db($database) == false) {
				die('<script>$("response").innerHTML="Invalid Database ('.$database.')";</script>');
			}
		}
		if (!$conn) {
			 die('<script>$("response").innerHTML="Could not connect to database";</script>');
		}
		return $conn;
	}

	function closeDatabase($conn) {
		@mysql_close(closeDatabase);
	}

	function displayLoginForm() {
		echo $this->displayGenericObjects();
		echo
		"
			<script>
				function login(username,password) {
					var url = \"".$_SERVER['PHP_SELF']."?\";
					var params = \"username=\" + username + \"&password=\" + password;
					new Ajax.Request( url + params,
							{
						method: 'post',
						onSuccess: function(transport) {
						var response = transport.responseText || false;
						if (response != false) {
							$(\"results\").innerHTML = response;
						} else {
							document.location = 'index.php';
						}
					},
					onFailure: function() { alert('An unexpected error occurred.'); }
				});
		}
			</script>
			<div id='serverinfo'>
				 Login To <strong>".$this->currentAdminDB['crud']['console_name']."</strong>
				 <table>
						<tr>
							 <td>Username: </td>
							 <td><input type='text' class='admin' id='username' value=''/></td>
						</tr>
						<tr>
							 <td>Password: </td>
							 <td><input class='admin' type='password' id='password' value=''/></td>
						</tr>
						<tr>
							 <td></td>
							 <td><a class='button' onclick='login($(\"username\").value,$(\"password\").value);'><span>Login</span></a></td>
						</tr>
				 </table>
			</div>
			";
	 }

	function LoginToCruddyMysql($username,$password) {
		ob_end_clean();
		$loggedIn = false;
		foreach ($this->currentAdminDB['crud']['users'] as $k=>$v) {
			if (strtoupper($v['user_name']) == strtoupper($username) && strtoupper($v['password']) == strtoupper($password)) {
				$loggedIn = true;
				setcookie("current_user", $k, time()+3600*24*7,"/");
				setcookie("current_role", $v['role'], time()+3600*24*7,"/");
				break;
			}
		}

		if ($loggedIn === false) {
			echo "Invalid username or password.";
		}

		exit;
	}


	#1 Step
	function displayDatabaseConnectionForm() {
		echo $this->displayGenericObjects();

		if ($_GET['edit']) {
			$defaultPort = $this->currentAdminDB['crud']['mysql_ports'][$_GET['edit']];
			$defaultServer = $this->currentAdminDB['crud']['mysql_server_names'][$_GET['edit']];
			$defaultUserName = $this->currentAdminDB['crud']['mysql_user_names'][$_GET['edit']];
			$defaultPassword = $this->currentAdminDB['crud']['mysql_passwords'][$_GET['edit']];
		} else {
			$defaultPort = '3306';
			$defaultServer = 'localhost';
			$defaultUserName = 'root';
		}

		if ($_GET['mode']=='edit' || !isset($_GET['newserver'])) {
			$adminHTML = "<tr>
					<td>Name of Administration: </td>
					<td><input type='text' class='admin' id='adminname' value='".$this->currentAdminDB['crud']['console_name']."'/></td>
			</tr>";
		} else {
			$adminHTML = "<tr><td>Name of Administration: </td><input type='hidden' id='adminname' value='".$this->currentAdminDB['crud']['console_name']."'/><td>".$this->currentAdminDB['crud']['console_name']."</td>
				 </tr>";
		}

		echo
			"
			<div id='serverinfo'>
				 Step 1: CruddyMySQL Server Connections
				 <table>
					$adminHTML
						<tr>
							 <td>MySQL Server: </td>
							 <td><input type='text' class='admin' id='server' value='$defaultServer'/></td>
						</tr>
						<tr>
							 <td>MySQL Port: </td>
							 <td><input type='text' class='admin' id='port' value='$defaultPort'/></td>
						</tr>
						<tr>
							 <td>MySQL Username:</td>
							 <td><input type='text' class='admin' id='username' value='$defaultUserName'/></td>
						</tr>
						<tr>
							 <td>MySQL Password:</td>
							 <td><input type='password' class='admin' id='password' value='$defaultPassword'/></td>
						</tr>
						<tr>
							 <td>Cruddy mySQL Instance Name:</td>
							 <td>\"".$_SERVER["SERVER_NAME"]."_".ABS_PATH_HASH."\"<br/>(dont change your path or you'll have to rename /configuration files)</td>
						</tr>
						<tr>
							 <td><a class='button' onclick='storeConnectionInfo(1)'><span>Add Another Server</span></a></td>
							 <td><a class='button' onclick='storeConnectionInfo(0)'><span>Store Connection Info And Proceed</span></a></td>
						</tr>
				 </table>
			</div>
		";
	}

	function storeDatabaseConnectionForm() {
		if ($this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->currentAdminDB['crud']['completed_step'] = 1;
		}
		ob_end_clean();
		if (!@mysql_connect($_GET['server'].":".$_GET['port'],$_GET['username'],$_GET['password'])) {
			echo "Error: Connection settings incorrect.  Please try again.";
		} else {
			$serverHash = $_GET['server'].':'.$_GET['port'];
			$this->currentAdminDB['crud']['console_name'] = $_GET['adminname'];
			$this->currentAdminDB['crud']['mysql_server_names'][$serverHash] = $_GET['server'];
			$this->currentAdminDB['crud']['mysql_user_names'][$serverHash] = $_GET['username'];
			$this->currentAdminDB['crud']['mysql_passwords'][$serverHash] = $_GET['password'];
			$this->currentAdminDB['crud']['mysql_ports'][$serverHash] = $_GET['port'];
			if (isset($this->currentAdminDB[CRUD_FIELD_CONFIG])) {
				// -- update connections for each matching DB
				foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG] as $k=>$v) {
					$parts = explode('/',$this->currentAdminDB[CRUD_FIELD_CONFIG][$k][TABLE_CONFIG][OBJECT_CONNECTION_STRING]);
					if (stristr($this->currentAdminDB[CRUD_FIELD_CONFIG][$k][TABLE_CONFIG][OBJECT_CONNECTION_STRING] ,$_GET['server'])) {
						$this->currentAdminDB[CRUD_FIELD_CONFIG][$k][TABLE_CONFIG][OBJECT_CONNECTION_STRING] = "mysql://".$_GET['username'].":".$_GET['password']."@".$_GET['server'].":".$_GET['port']."/".$parts[sizeof($parts)-1];
					}
				}
			}
			$this->writeAdminDB();

			foreach ($this->currentAdminDB['crud']['mysql_server_names'] as $mySQLServerHash=>$mySQLServer) {
				$phpCode .= "\$connection['$mySQLServerHash']['server']   = '{$this->currentAdminDB['crud']['mysql_server_names'][$mySQLServerHash]}';\n";
				$phpCode .= "\$connection['$mySQLServerHash']['username'] = '{$this->currentAdminDB['crud']['mysql_user_names'][$mySQLServerHash]}';\n";
				$phpCode .= "\$connection['$mySQLServerHash']['password'] = '{$this->currentAdminDB['crud']['mysql_passwords'][$mySQLServerHash]}';\n";
				$phpCode .= "\$connection['$mySQLServerHash']['port']     = '{$this->currentAdminDB['crud']['mysql_ports'][$mySQLServerHash]}';\n";
			}

			$this->writeFile($this->databaseConnectionFile,
			"<?php\n// -- all of your server connections\n$phpCode\n?>"
			);
		}
		exit;
	}


	#2 Step
	function displayDatabaseSelectionForm() {
		foreach ($this->currentAdminDB['crud']['mysql_server_names'] as $mySQLServerHash=>$mySQLServer) {
			$conn = $this->connectDatabase($mySQLServerHash);
			$resultrows = $this->queryDatabase(GET_DATABASES_SQL,$conn);
			foreach ($resultrows as $key=>$value) {
				$selected = "";
				$db = $this->currentAdminDB['crud']['mysql_databases'][$this->currentAdminDB['crud']['mysql_server_names'][$mySQLServerHash].":".$this->currentAdminDB['crud']['mysql_ports'][$mySQLServerHash]];
				if (!empty($db)) {
					$keys = array_keys($db);
					if ($db == $value['Database'] || in_array($value['Database'],$keys) || !isset($_GET['edit'])) {
						$selected = "selected";
					}
				} else {
					$selected = "selected";
				}
				if ($value['Database'] == 'information_schema' || $value['Database'] == 'mysql') { continue; }
				$options .= "<option value='$mySQLServer".":".$this->currentAdminDB['crud']['mysql_ports'][$mySQLServerHash]."' $selected title='$mySQLServer -> {$value['Database']}'>".$value['Database']."</option>";
			}
		}
		if (!isset($_GET['edit'])) {
			$additionalText = "(All Are Selected CTRL+CLICK to deselect)<br/>";
		}
		echo $this->displayGenericObjects();
		
		$valMaster = ($this->currentAdminDB['crud']['mysql_master_database_configuration']) ? $this->currentAdminDB['crud']['mysql_master_database_configuration'] : "0";
		$valMaster2 = ($this->currentAdminDB['crud']['mysql_master_database_configuration']) ? $this->currentAdminDB['crud']['mysql_master_database_configuration'] : "Off";
		
		echo
			"
			<div id='serverinfo'>
				 Step 2: Database Selection
				 <table>
						<tr>
							 <td>Please select a database:</td>
							 <td>
							 $additionalText
							 <select style='background-color:white;color:black;' class='admin' multiple='multiple' id='database' name='database[]'>
									$options
							 </select>
							 </td>
						</tr>
				 </table>
				 <a class='button' onclick='storeDatabaseInfo()'><span>Select A Database</span></a>
				 <span style=\"cursor:pointer;\" title=\"If you have multiple databases with the same schema,use this mode to point one configuration against all DBs\">Master Config Mode</span><input style='display: none;' onclick=\"toggleObj('masterMode');\" name='masterMode' id='masterMode' value='$valMaster' type='checkbox'><span id='masterMode[onoff]' class='off' onclick=\"toggleObj('masterMode');if ($('masterMode').value == 1) { var val = window.prompt('Enter the master database name'); $('masterMode').value = val;} \">$valMaster2</span>
			</div>
			";
			$this->closeDatabase($conn);
	}

	function storeDatabaseSelectionForm() {
		if ($this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->currentAdminDB['crud']['completed_step'] = 2;
		}
		ob_end_clean();
		$masterMode = $_GET['masterMode'];
		unset($_GET['masterMode'],$_GET['admin'],$_GET['select_database'],$_GET['store_database'],$this->currentAdminDB['crud']['mysql_databases']);
		$this->currentAdminDB['crud']['mysql_master_database_configuration'] = false;
		if (is_array($_GET)) {
			$i=0;
			$databasesLeft = $this->currentAdminDB['crud']['mysql_tables_to_config'];
			foreach ($_GET as $key=>$value) {
				if (strtolower($masterMode) == strtolower($key)) {
					$this->currentAdminDB['crud']['mysql_master_database_configuration'] = $key;
				}
				$this->currentAdminDB['crud']['mysql_databases'][$value][$key] = $key;
				$i++;
				unset($databasesLeft[$key]);
			}
			if (!empty($databasesLeft)) {
				foreach ($databasesLeft as $database) {
					foreach ($database as $configuration) {
						unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$configuration]);
					}
				}
			}
			$this->writeAdminDB();
		} else {
			echo "Please select 1 or more databases";
		}
		exit;
	}

	function createDisplayName($name) {
		$fieldCaption = str_replace(array("_","-",".","[","]","<",">"),array(" "," "," "," "," "," "," "),$name);
		$parts = explode(" ",$fieldCaption);
		if (sizeof($parts) > 1) {
			foreach ($parts as $word) {
				$newFieldCaption .= ucwords($word)." ";
			}
			$fieldCaption = substr($newFieldCaption,0,-1);
		} else {
			$fieldCaption = ucwords($fieldCaption);
		}
		return $fieldCaption;
	}

	#3 Step
	function displayTableSelectionForm($mysqlServers,$mysqlDatabases) {
		if ($this->isPageInclude) {
			if (isset($_GET['conf']) && $_GET['conf'] != $this->current_config) {
				return;
			}
		}

		foreach ($mysqlServers as $mySQLServerHash=>$mySQLServer) {
			$tableControlFlagDisplay = false;
			foreach ($mysqlDatabases[$mySQLServerHash] as $database) {
				if ($this->currentAdminDB['crud']['mysql_master_database_configuration'] !== false && $this->currentAdminDB['crud']['mysql_master_database_configuration'] != $database) {
					continue;
				}
				$failure = false;
				$conn = $this->connectDatabase($mySQLServerHash,$database);
				$resultrows = $this->queryDatabase(GET_TABLES_SQL,$conn);
				if (empty($resultrows)) {
					$resultrows = $this->queryDatabase(GET_TABLES_SQL." from $database",$conn);
					if (empty($resultrows)) {
						$resultrows = $this->queryDatabase("SHOW TABLES FROM $database",$conn);
						if (empty($resultrows)) {
							$failure = true;
							$errors  .= "<div class=\"error\">Could not get table listing from $mySQLServer.$database</div>";
						}
					}
				}

				if ($failure === false) {
					if ($tableControlFlagDisplay === false) {
						$tableControlFlagDisplay = true;
						$options .= "<tr>";
						if (!isset($_GET['edit'])) {
							$options .= "<td></td><td>Table Name</td>";
						} else {
							$options .= "<td></td>";
						}
							$options .= "<td></td>";
							$options .= "<td></td>";
						foreach ($this->tableControlType as $type=>$text) {
							if (!isset($_GET['edit']) && $type == OBJECT_PK) {
								// -- dont let user try and edit PK.  these will be set on next page
								continue;
							}
							$options .= "<td>".$text['desc']."</td>";
						}
						$master = '';
						if ($this->currentAdminDB['crud']['mysql_master_database_configuration'] == $database) {
							$master = ' (MASTER CONFIG)';
						}
						
						$options .= "</tr>
										 <tr>
											<td style=\"font-size:1.5em;\" colspan=\"20\">Tables in $mySQLServerHash</td>
										 </tr>
										 <tr>
											<td style=\"font-size:1.2em;\" colspan=\"20\">Database:$database $master</td>
										 </tr>
										 ";
					} else {
						$options .= "</tr>
										 <tr>
											<td style=\"font-size:1.2em;\" colspan=\"20\">Database:$database $master</td>
										 </tr>";
					}

					$options .= "
					<tr>
						<td></td>
						<td><a onclick='if (this.innerHTML==\"Uncheck All\") { cruddy(\".tableNames\").attr(\"checked\",false); this.innerHTML = \"Check All\";} else { cruddy(\".tableNames\").attr(\"checked\",true); this.innerHTML = \"Uncheck All\"; }'>Uncheck All</a></td>
						<td></td>
						<td></td>
						<td></td>
					";
					foreach ($this->tableControlType as $key=>$text) {
						if (empty($text["type"]) ) { continue;}
						if ( (!isset($_GET['edit']) && $key == OBJECT_PK) ) {
							continue;
						}
						$rowOutPut = '';
						$checked2 = '';
						$value = $this->tableControlDefaults[$key];
						if ($text['type'] == 'checkbox' && ($value === true || $value == '1')) {
							$checked2 = 'Off';
						} else if ($text['type'] == 'checkbox' && $value === false)  {
							$checked2 = 'On';
						} else if ($text['type'] == 'checkbox') {
							$checked2 = 'On';
						}

						if ($checked2 != '') {
							$rowOutPut .= 'Turn all '.$checked2;
							$click = "if (this.innerHTML==\"Turn all Off\") { cruddy(\"input[$key]\").attr(\"checked\",false);cruddy(\"span[{$key}_onoff]\").html(\"Off\"); cruddy(\"input[$key]\").val(0); this.innerHTML = \"Turn all On\";} else { cruddy(\"input[$key]\").attr(\"checked\",true); cruddy(\"input[$key]\").val(1); cruddy(\"span[{$key}_onoff]\").html(\"On\");this.innerHTML = \"Turn all Off\"; }";
						} else {
							$rowOutPut .= 'Replace All Text';
							$click   =  "var val = window.prompt(\"Enter the replacement value\",\"\");cruddy(\"input[$key]\").val(val);";
						}
						
								
						$options .= "<td><a onclick='$click'>".$rowOutPut."</a></td>";
					}
					$option .= "
					</tr>
					";
					if ($this->isPageInclude) {
						$tableHash = $_GET['edit'];
					} else {
						$tableHash = $database."_".$table;
					}

					if (is_array($this->currentAdminDB[CRUD_FIELD_CONFIG])) {
						foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG] as $confName=>$objectCrud) {
							if (isset($objectCrud[TABLE_CONFIG]['is_clone']) && $objectCrud[TABLE_CONFIG]['is_clone'] == true) {
								array_unshift($resultrows, array('Tables_in_'.$database=>$confName));
							}
						}
					}

					foreach ($resultrows as $key=>$value) {

						$selected = "";
						$tableName = $value['Tables_in_'.$database];
						$tableType = $value['Table_type'];
						if ($tableType == 'BASE TABLE') {
							$tableType = 'Table';
						} elseif ($tableType == 'VIEW') {
							$tableType = 'View';
						} else {
							$tableType = 'Unsupported';
						}
						if ($this->currentAdminDB[CRUD_FIELD_CONFIG][$value['Tables_in_'.$database]][TABLE_CONFIG]['is_clone'] == true) {
							$tableType = 'Clone';
							$isClone = true;
							$tableName = $this->currentAdminDB[CRUD_FIELD_CONFIG][$value['Tables_in_'.$database]][TABLE_CONFIG][OBJECT_TABLE];
							$tableHash = $value['Tables_in_'.$database];
						} else {
							$isClone = false;
							if (!$this->isPageInclude) {
								$tableHash = $database."_".$tableName;
							}
						}

						if ($this->isPageInclude && $_GET['edit'] != $tableName) {
							continue;
						}

						$resultPK = $this->getPrimaryKey($database,$tableName,$conn);
						if (isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash])) {
							$selected = "checked";
							if ($_GET['edit']) {
								// -- warn user if editing
								$extraJS = "uncheckedTable=true;";
							} else {
								$extraJS = "uncheckedTable=false;";
							}
						} else {
							$selected = "";
							$extraJS = "uncheckedTable=false;";
						}

						if (!$_GET['edit']) {
							$selected = "checked";
							$extraJS = "uncheckedTable=false;";
						}
						if (empty($resultPK[0]['column_name']) && $tableType == 'Table') {
							$errorsCount++;
							$errors  .= "<div class=\"error\">\"$mySQLServer.$database.$tableName\" has no  unique primary key!  Please define and refresh this page.  You cannot use this table in the CRUD system. (ALTER TABLE `$tableName` ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;)</div>";
						} elseif ($tableType == 'Unsupported') {
							$errorsCount++;
							$errors  .= "<div class=\"error\">\"$mySQLServer.$database.$tableName\" is an unsupported object.  (Only VIEWS and TABLE objects)</div>";
						} else {
							
							$viewIsAggregateHTML = '';
							if ($tableType == 'View' && strlen($this->currentAdminDB['crud']['mysql_master_database_configuration']) > 0) {
								$disp = 'none';
								$aggregateClick = '$("'.$tableHash.'_aggregate").style.display = "block";';
								if ($selected != '')
								{
									$disp = 'block';
								}
								
								$aggTxt = 'Aggregate<br>All DBs';
								$aggVal = '0';
								if ( $this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_IS_AGGREGATE] == '1'){
									$aggTxt = 'Remove Aggregate';
									$aggVal = '1';
								}
								$viewIsAggregateHTML = '<td><a id="'.$tableHash.'_aggregate" style="display:'.$disp.'" 
								onclick="
								if (this.innerHTML == \'Aggregate<br>All DBs\') 
								{ 
									$(\''.$tableHash.'_aggregate_hidden\').value=1;
									this.innerHTML=\'Remove Aggregate\'; 
								}
								else
								{
									$(\''.$tableHash.'_aggregate_hidden\').value=0;
									this.innerHTML=\'Aggregate<br>All DBs\'; 
								}
								">'.$aggTxt.'</a></td>';
								$viewIsAggregateHTML .= "<input type=\"hidden\" value=\"$aggVal\" name=\"tables[$mySQLServerHash][$database][$tableHash][Aggregate]\" id=\"{$tableHash}_aggregate_hidden\"/>";
							} else {
								$viewIsAggregateHTML = '<td></td>';
							}
							$additionalHTML = " style='cursor:pointer;' onclick='{$aggregateClick}toggleObj(\"tables[$mySQLServerHash][$database][$tableHash][use]\");$(\"tables[$mySQLServerHash][$database][$tableHash][use]\").value=\"$tableName\";$extraJS'><input value='$tableName' type='checkbox' name='tables[$mySQLServerHash][$database][$tableHash][use]' class='tableNames' id='tables[$mySQLServerHash][$database][$tableHash][use]' $selected >";

							if (isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash]) && $isClone === false) {
								$cloneLink = "<td><a style='cursor:pointer;' onclick='cloneTable(\"$tableHash\");'>Clone<br/>$tableType<br/>Config</a></td>";
							} elseif ($isClone === true) {
								$cloneLink = "<td>Cloned..</td>";
							} else {
								$cloneLink = "<td></td>";
							}
							
							$options .= "
							<tr id='$tableHash'>
								$cloneLink<td $additionalHTML $tableHash $viewIsAggregateHTML<input type=\"hidden\" value=\"".$resultPK[0]['column_name']."\" name=\"tables[$mySQLServerHash][$database][$tableHash][PK]\"/></td>
							";

							foreach ($this->tableControlType as $key=>$text) {
								if (empty($text["type"]) ) { continue;}
								if (!isset($_GET['edit']) && $key == OBJECT_PK) {
									 // -- dont let user try and edit PK.  these will be set on next page
									 continue;
								}
								$checked = '';
								$value = '';
								$extra = '';
								$checked2 = '';
								$extra2 = '';
								if ($this->currentAdminDB['crud']['completed_step'] != 'All' && !$this->isPageInclude) {
									// -- pull from default to pre-populate values
									$value = $this->tableControlDefaults[$key];
									if ($key == OBJECT_DESC) {
										$value = str_replace(array('_','-'),array(' ',' '),ucfirst($tableName));
										$value = $this->createDisplayName($value);
									}
								} else {
									$value = $this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][$key];
								}
								
								if (empty($value)) {
									if ($key == OBJECT_DESC) {
										$value = str_replace(array('_','-'),array(' ',' '),ucfirst($tableName));
										$value = $this->createDisplayName($value);
									} else {
										$value = $this->tableControlDefaults[$key];
									}
								}

								//var_dump($this->currentAdminDB,$tableHash,$table);
								if ($text['type'] == 'checkbox' && ($value === true || $value == '1')) {
									$checked = 'checked';
									$checked2 = 'On';
								} else if ($text['type'] == 'checkbox' && $value === false)  {
									$checked2 = "Off";
								} else if ($text['type'] == 'checkbox') {
									$checked2 = "Off";
								}

								if ($text['type'] == 'checkbox') {
									$extra = "style='cursor:pointer;' onclick='toggleObj(\"tableConfig[$mySQLServerHash][$database][$tableHash][$key]\")'";
									$extra2 = "style='display:none;'";
								}


								$disabled = "";
								if ($tableType == 'View' && $this->currentAdminDB['crud']['completed_step'] != 'All') {
									switch ($key) {
										case EDIT_TEXT:
											$value = "N/A";
											$disabled = " disabled ";
											break;
										case DELETE_TEXT:
											$value = "N/A";
											$disabled = " disabled ";
											break;
										case ADD_TEXT:
											$value = "N/A";
											$disabled = " disabled ";
											break;
										case VIEW_TEXT:
											$value = "N/A";
											$disabled = " disabled ";
											break;
										case OBJECT_HIDE_DELETE_LINK:
											$extra = "onclick='alert(\"This is a view, you cannot enable this\")'";
											$value = 1;
											break;
										case OBJECT_HIDE_NEW_LINK:
											$value = 1;
											$extra = "onclick='alert(\"This is a view, you cannot enable this\")'";
											break;
										case OBJECT_HIDE_EDIT_LINK:
											$value = 1;
											$extra = "onclick='alert(\"This is a view, you cannot enable this\")'";
											break;
										case OBJECT_PAGING_NUM_ROWS_PER_PAGE:
											$value = 100;
											break;
										case TABLE_TEXT:
											$value = "{table_desc} Listing";
											break;
									}
								}
								$options .= "<td $extra><input $key $disabled $extra2 $extra type=\"$text[type]\" name=\"tableConfig[$mySQLServerHash][$database][$tableHash][$key]\" id=\"tableConfig[$mySQLServerHash][$database][$tableHash][$key]\" value=\"$value\" $checked/><span id=\"tableConfig[$mySQLServerHash][$database][$tableHash][$key][onoff]\" {$key}_onoff class=\"".strtolower($checked2)."\">$checked2</span></td>";
							}
							$option .= "
							</tr>
							";
						}
					}
				}

				$this->closeDatabase($conn);
			}
		}

		if ($errors) {
			$allErrors = "
				<div class=\"error\" style=\"cursor:pointer;\" onclick=\"if ($('allerrors').style.display == 'none') { $('allerrors').style.display = 'inline';} else { $('allerrors').style.display = 'none';}\">There were tables that could not be used because Primary Keys Dont Exist.  Click For more Info.</div>
						<span id=\"allerrors\" style=\"display:none\">
							$errors
					</span>";
		}

		echo
			"
			<script>
				var uncheckedTable=false;
			</script>
			<form action='$_SERVER[PHP_SELF]?admin=1&select_tables=1&store_database=1' id='tableForm{$_GET['edit']}' method='post'>
				".$this->displayGenericObjects()."
				<div id='serverinfo'>
						Step 3: Table Selection
						$allErrors
						<table style='width:1500px;'>
									$options
						</table>
						<a class='button' onclick='
						if (uncheckedTable==true) {
							if (window.confirm(\"Are you sure you want to remove a table from the listing?  Doing so will delete all field configuration for this table.\")) {
								$(\"tableForm{$_GET['edit']}\").action= $(\"tableForm{$_GET['edit']}\").action + \"&conf=$this->current_config\";
								$(\"tableForm{$_GET['edit']}\").submit();
							}
						} else {
							$(\"tableForm{$_GET['edit']}\").action= $(\"tableForm{$_GET['edit']}\").action + \"&conf=$this->current_config\";
							$(\"tableForm{$_GET['edit']}\").submit();
						}'><span>Select These Tables</span></a>
				 </div>
			</form>
			";

	}

	function handleAdminPages() {
		echo '<span style="font-size:1.2em;">';
		// -- step 1
		if (isset($_GET['initialize_server']) || $this->override['initialize_server']) {
			if (isset($_GET['store_database'])) {
				$this->storeDatabaseConnectionForm();
			} else {
				$this->displayDatabaseConnectionForm();
			}
		}

		// -- step 2
		if (isset($_GET['select_database']) || $this->override['select_database']) {
			if (isset($_GET['store_database'])) {
				$this->storeDatabaseSelectionForm();
			} else {
				$this->displayDatabaseSelectionForm();
			}
		}

		// -- step 3
		if (isset($_GET['select_tables']) || $this->override['select_tables']) {
			if (isset($_GET['store_database'])) {
				$this->storeTableSelectionForm();
			} else {
				if ($this->isPageInclude) {
					$mySqlArray   = array($_GET['server']=>$_GET['server']);
					$mysqlDbArray = array($_GET['server']=>array($_GET['database']=>$_GET['database']));
				} else {
					$mySqlArray   = $this->currentAdminDB['crud']['mysql_server_names'];
					$mysqlDbArray = $this->currentAdminDB['crud']['mysql_databases'];
				}
				$this->displayTableSelectionForm($mySqlArray,$mysqlDbArray);
			}
		}

		// -- step 4
		if (isset($_GET['select_groups']) || $this->override['select_groups']) {
			if (isset($_GET['store_database'])) {
				$this->storeGroupSelectionForm();
			} else {
				$this->displayGroupSelectionForm();
			}
		}

		// -- step 5
		if (isset($_GET['select_roles']) || $this->override['select_roles']) {
			if (isset($_GET['store_database'])) {
				$this->storeRolesSelectionForm();
			} else {
				$this->displayRolesSelectionForm();
			}
		}

		// -- step 6
		if (isset($_GET['select_users']) || $this->override['select_users']) {
			if (isset($_GET['store_database'])) {
				$this->storeUserSelectionForm();
			} else {
				$this->displayUserSelectionForm();
			}
		}

		// -- step 7
		if (isset($_GET['select_theme']) || $this->override['select_themes']) {
			if (isset($_GET['store_database'])) {
				$this->storeThemeSelectionForm();
			} else {
				$this->displayThemeSelectionForm();
			}
		}

		// -- step 8 (done after everything on a per table basis)
		if (isset($_GET['select_fields']) || $this->override['select_fields']) {
			if (isset($_GET['store_database'])) {
				$this->storeFieldSelectionForm();
			} else {
				$this->displayFieldSelectionForm();
			}
		}

		// -- step 9 finalize your local serialized array for production use
		if (isset($_GET['productionize'])) {
			$this->productionizeAdminDB();
		}

		// -- ajax fields
		if (isset($_GET['find_fields'])) {
			$this->displayFieldsAJAX();
		}

		// -- ajax clone
		if (isset($_GET['clone_table'])) {
			$this->cloneObject($_GET['original_pointer'],$_GET['new_name']);
		}

		echo '</span>';
	}

	function getPrimaryKey($database,$tableName,$conn) {
		$resultPK = $this->queryDatabase(
		"SELECT k.column_name
		FROM information_schema.table_constraints t
		JOIN information_schema.key_column_usage k
		USING(constraint_name,table_schema,table_name)
		WHERE t.constraint_type='PRIMARY KEY'
		AND t.table_schema='$database'
		AND t.table_name='$tableName'
		UNION
		",$conn);
		if (empty($resultPK)) {
			// -- developers might not have access to information_schema
			$resultPK2 = $this->queryDatabase(sprintf(GET_COLUMNS_SQL,$tableName),$conn);
			if (is_array($resultPK2)) {
				foreach ($resultPK2 as $key=>$row) {
					if ($row['Key'] == 'PRI') {
						$resultPK[0]['column_name'] = $row['Field'];
					}
				}
			}
		}
		return $resultPK;
	}

	function storeTableSelectionForm() {
		if ($this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->currentAdminDB['crud']['completed_step'] = 3;
		}
		if ($this->isPageInclude) {
			if (isset($_GET['conf']) && $_GET['conf'] != $this->current_config) {
				return;
			}
		}
		ob_end_clean();
		if(is_array($_REQUEST['tables'])) {
			$drawFunctions = "<?php\n";
				foreach ($_REQUEST['tables'] as $server=>$selectedDatabase) {
					foreach ($selectedDatabase as $database=>$tableValues) {
						foreach ($tableValues as $table=>$primaryKey) {
							$tableOriginal = $primaryKey['use'];
							if ($this->isPageInclude) {
								$tableHash = $tableOriginal;
							} else {
								$tableHash = $table;
							}
							$addNew = false;
							if (!isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG])) {
								$addNew = true;
							}
							if (!is_dir(getcwd().$this->systemDirectorySeparator."pages")) {
								mkdir(getcwd().$this->systemDirectorySeparator."pages",0777);
							}
							$pages[] = "pages_".$_SERVER['SERVER_NAME'].".$table.php";
							$this->writeFile( getcwd().$this->systemDirectorySeparator."pages".$this->systemDirectorySeparator."pages_".$_SERVER['SERVER_NAME'].".$table.php","<?php
ob_start();
echo
'	<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
	<html xmlns=\"http://www.w3.org/1999/xhtml\">
	<head>
	<title></title>
	<meta http-equiv=\"Content-Language\" content=\"English\" />
	<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
	<body>
<h4>Copy/paste or modify this template in any application to manage your data for \"{$primaryKey['use']}\" configuration</h4>
<h4>Once you are done configuring {$primaryKey['use']}.config.php, click Productionize to ensure security on your database connections and then set \$crudAdmin->cruddyAdministrator = false; to disble configuration changes any further</h4>
';
// -- all requests for cruddy mysql must start one path below the \"cruddy_mysql\" libs driectory to pick up the config files and connections
chdir(\"".getcwd()."\");
require_once(\"configurations/crud_".$_SERVER['SERVER_NAME'].".connections.php\");
require_once(\"cruddy_mysql/cruddy_mysql.php\");
\$crudAdmin = new cruddyMysqlAdmin();
\$serverHash    = \"$server\";
\$tableName     = \"{$primaryKey['use']}\";
\$databaseName  = \"$database\";
// -- set to true or false when you wish to make changes to the crud configuration for the table
\$crudAdmin->cruddyAdministrator = true;
\$crudAdmin->paint(\$tableName, \$connection[\$serverHash]['server'], \$connection[\$serverHash]['username'], \$connection[\$serverHash]['password'], \$databaseName, \$tableName);

echo
'
</body>
</html>
';

ob_end_flush();
?>"
							);
							$this->currentAdminDB['crud']['mysql_tables_to_config'][$database][$tableHash] = $tableHash;
							if (!isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG])) {
								foreach ($this->tableControlDefaults as $systemKey=>$text) {
									$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][$systemKey] = $text;
								}
								foreach ($this->tableControlType as $systemKey=>$text) {
									if (empty($text["type"]) ) { continue;}
									if ($_REQUEST['tableConfig'][$server][$database][$tableHash][$systemKey]) {
										$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][$systemKey] = $_REQUEST['tableConfig'][$server][$database][$tableHash][$systemKey];
									} else {
										unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][$systemKey]);
									}
								}
							}
							if (!$this->isPageInclude) {
								$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_CONNECTION_STRING] = "mysql://".$this->currentAdminDB['crud']['mysql_user_names'][$server].":".$this->currentAdminDB['crud']['mysql_passwords'][$server]."@".$this->currentAdminDB['crud']['mysql_server_names'][$server].":".$this->currentAdminDB['crud']['mysql_ports'][$server]."/".$database;
								// -- add default field behavior now
								$conn = $this->connectDatabase($this->currentAdminDB['crud']['mysql_server_names'][$server].":".$this->currentAdminDB['crud']['mysql_ports'][$server],$database);
								if ($addNew == true) {
									$this->addDefaultFieldData($tableOriginal,$conn,$tableHash);
								}
								$this->closeDatabase($conn);
							}
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_ACTIONS] = $this->tableControlDefaults[OBJECT_ACTIONS];

							if (!$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG]['is_clone']) {
								$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_TABLE] = $tableOriginal;
							}
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_DESC] = $_REQUEST['tableConfig'][$server][$database][$tableHash][OBJECT_DESC];
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_IS_AGGREGATE] = $_REQUEST['tables'][$server][$database][$tableHash]['Aggregate'];
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_DEFAULT_ORDER] = $_REQUEST['tableConfig'][$server][$database][$tableHash]['defaultorder'];
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_READ_FILTER] = $_REQUEST['tableConfig'][$server][$database][$tableHash]['filterrecords'];
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_FILTER_DESC] = $_REQUEST['tableConfig'][$server][$database][$tableHash]['filterrecordsdescription'];
							$pk = $primaryKey['PK'];
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_PK] = $pk;
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG]['alias'] = $tableHash;
							$linkEdit = "?action=".strtolower($this->actionTypes['update'].$tableHash)."&".$pk."=%".$pk."%";
							$linkDelete = "?action=".strtolower($this->actionTypes['delete'].$tableHash)."&".$pk."=%".$pk."%";
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][EDIT_LINK] = $linkEdit;
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][DELETE_LINK] = $linkDelete;
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OTHER_LINKS] = "";

							if (!isset($primaryKey['use'])) {
								unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash]);
								continue;
							}
						}
					}
				}

				foreach ($pages as $page) {
					$pageIndexHTML .= "<h5><a href=\"$page\">$page</a></h5>";
				}

				$this->writeFile( getcwd().$this->systemDirectorySeparator."pages".$this->systemDirectorySeparator."index.php","
<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
	<html xmlns=\"http://www.w3.org/1999/xhtml\">
		<head>
		<title></title>
		<meta http-equiv=\"Content-Language\" content=\"English\" />
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		<body>
			<h4>Here are all of your cruddy_mysql configuration pages that you can drop in ANYWHERE with ANY APPLICATION!</h4>
			<h4>Just step through the fields configuration as to how you want it to behave and productionize.</h4>
			$pageIndexHTML
		</body>
	</html>
"
				);
				// -- turning off comments in the files to conserve size
				$showComments = false;
				foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG] as $tableHash=>$obj) {
					$drawFunctions .= "\$crudAdmin->paint('$tableHash');\n\n";
					if ($showComments) { $functions .= "/*\n* ".strtoupper($tableHash)." PRE PROCESSES BEFORE LOADING A TABLE RECORDSET (Primarily used to overwrite parts of the serialized array with \$_SESSION vars and application specific logic)\n*/";}
					$functionsIncludes .= "if (file_exists(\$_SERVER['DOCUMENT_ROOT'].'".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."custom_processors/".$this->cleanTableNames($tableHash).".php'))\n\t require_once(\$_SERVER['DOCUMENT_ROOT'].'".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."custom_processors/".$this->cleanTableNames($tableHash).".php');\n";
					$functions = "<?php\n";
					$functions .= "\n\nif(!function_exists('pre_process_load_".$this->cleanTableNames($tableHash)."')){\n\tfunction pre_process_load_".$this->cleanTableNames($tableHash)."(\$pointer){\n\t";

					if ($showComments) { $functions .= "//--add your custom logic here such as changing \$pointer[TABLE_CONFIG][OBJECT_READ_FILTER] with a dynamic where clause or \$pointer['fieldname_config']['VALUE'] for overriding values or any attributes possible in the config array ";	}

					$functions .= "\n\t\treturn \$pointer;\n\t}\n}\n\n";

					if ($showComments) { $functions .= "/*\n* ".strtoupper($tableHash)." PRE PROCESSES BEFORE INSERTING A RECORD\n*/ ";	}

					$functions .= "\n\nif(!function_exists('pre_process_load_".$this->cleanTableNames($tableHash)."')){\n\tfunction new_pre_process_".$this->cleanTableNames($tableHash)."(){\n\t";

					if ($showComments) { $functions .= "//--add your custom logic here before inserting a record in $tableHash -- return false if not wanting to add new ";	}

					$functions .= "\n\t\treturn true;\n\t}\n}\n\n";

					if ($showComments) { $functions .= "/*\n* ".strtoupper($tableHash)." POST PROCESSES AFTER INSERTING A RECORD\n*/ ";	}

					$functions .= "\n\nif(!function_exists('new_post_process_".$this->cleanTableNames($tableHash)."')){\n\tfunction new_post_process_".$this->cleanTableNames($tableHash)."(){\n\t";

					if ($showComments) { $functions .= "//--add your custom logic here after inserting a record in $tableHash ";	}

					$functions .= "\n\t\treturn true;\n\t}\n}\n\n";

					if ($showComments) { $functions .= "/*\n* ".strtoupper($tableHash)." PRE PROCESSES BEFORE UPDATING A RECORD\n*/ ";	}

					$functions .= "\n\nif(!function_exists('update_pre_process_".$this->cleanTableNames($tableHash)."')){\n\tfunction update_pre_process_".$this->cleanTableNames($tableHash)."(){";

					if ($showComments) { $functions .= "\n\t//--add your custom logic here before updating a record in  $tableHash -- return false if not wanting to update the record because of logical checks ";	}

					$functions .= "\n\t\treturn true;\n\t}\n}\n\n";

					if ($showComments) { $functions .= "/*\n* ".strtoupper($tableHash)." POST PROCESSES AFTER UPDATING A RECORD\n*/ ";	}

					$functions .= "\n\nif(!function_exists('update_post_process_".$this->cleanTableNames($tableHash)."')){\n\tfunction update_post_process_".$this->cleanTableNames($tableHash)."(){";

					if ($showComments) { $functions .= "\n\t//--add your custom logic here after updating a record in $tableHash ";	}

					$functions .= "\n\t\treturn true;\n\t}\n}\n\n";

					if ($showComments) { $functions .= "/*\n* ".strtoupper($tableHash)." PRE PROCESSES BEFORE DELETING A RECORD\n*/ ";	}

					$functions .= "\n\nif(!function_exists('delete_pre_process_".$this->cleanTableNames($tableHash)."')){\nfunction delete_pre_process_".$this->cleanTableNames($tableHash)."(){";

					if ($showComments) { $functions .= "\n\t//--add your custom logic here before deleing a record in  $tableHash -- return false if not wanting to delete the record based on logic you add ";	}

					$functions .= "\n\t\treturn true;\n\t}\n}\n\n";

					if ($showComments) { $functions .= "/*\n* ".strtoupper($tableHash)." POST PROCESSES AFTER DELETING A RECORD\n*/ ";	}

					$functions .= "\n\nif(!function_exists('delete_post_process_".$this->cleanTableNames($tableHash)."')){\n\tfunction delete_post_process_".$this->cleanTableNames($tableHash)."(){";

					if ($showComments) { $functions .= "\n\t//--add your custom logic here after deleing a record in $tableHash ";	}
					$functions .= "\n\t\treturn true;\n\t}\n}\n\n";
					$functions .= "\n\n?>";
					if (!is_dir(getcwd().$this->systemDirectorySeparator."custom_processors")) {
						mkdir(getcwd().$this->systemDirectorySeparator."custom_processors",0777);
					}
					$funcFile = getcwd().$this->systemDirectorySeparator."custom_processors".$this->systemDirectorySeparator.$this->cleanTableNames($tableHash).".php";
					if (!file_exists($funcFile)) {
						// -- write once to unique table configuration pre/post processor functions so people can confidently know that cruddy will not kill any custom changes.
						$this->writeFile($funcFile,$functions);
					}
				}
				$drawFunctions .= "\$crudAdmin->paintGroups();\n\n";
				
				$drawFunctions .= "\n\n?>";
				if (!file_exists($this->functionsFile)) {
					// -- no file modifications have been done or file doesnt exist
					$this->writeFile($this->functionsFile,"<?php\n\n".$functionsIncludes."\n\n?>");
					$this->currentAdminDB['crud']['functionsfile_mtime'] = filemtime($this->functionsFile);
				}
				if (!$this->isPageInclude) {
					$this->writeFile($this->functionsDrawFile,$drawFunctions);
				}

				$this->currentAdminDB['crud']['drawfile_mtime'] = filemtime($this->functionsFile);
				$this->writeAdminDB();
				if ($_GET['mode'] != 'edit' ) {
					if (!isset($_COOKIE['redirect']) || $this->currentAdminDB['crud']['completed_step'] != 'All') {
						if ($this->isPageInclude) {
							$this->redirect($_SERVER['PHP_SELF']);
						} else {
							$this->redirect($_SERVER['PHP_SELF']."?admin=1&select_groups");
						}
					} else {
						$this->redirect($_COOKIE['redirect']);
					}
				}
			} else {
				echo "No Tables Selected";
			}
			exit;
	}

	function addDefaultFieldData($table,$conn,$tableHash) {
		$fieldResults = $this->queryDatabase(sprintf(GET_COLUMNS_SQL,$table),$conn);
		if (is_array($fieldResults)) {
			foreach ($fieldResults as $key=>$row) {
				$fieldCaption = $this->createDisplayName($row['Field']);
				$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][CAPTION] = $fieldCaption;
				$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][SORTABLE] = true;
				$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']."_config"] = array();
				$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][SHOWCOLUMN] = true;
				if (stristr($row['Comment'],"lookup")) {
					// -- if you initially put lookup,tableName,fieldThatStoresKey,fieldThatStoresTextLookup in your comments they will be automatically initialized
					list($type,$table,$field,$value) = explode(",",$row['Comment']);
					$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][TABLE] = trim($table);
					$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][ID] = trim($field);
					$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][TEXT] = trim($value);
				}
				if ($row['Field'] == $pk) {
					$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][SHOWCOLUMN] = false;
					$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][READONLY] = true;
					$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][UPDATE_READ_ONLY] = true;
					$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][$row['Field']][HIDE] = true;
					continue;
				}
			}
		}
	}

	function displayFieldsAJAX() {
		ob_end_clean();
		$conn = $this->connectDatabase($_GET['server'],$_GET['database']);
		$fieldRows = $this->queryDatabase(sprintf(GET_COLUMNS_SQL,$_GET['table']),$conn);
		echo "<select name=\"fields[".$_GET['k1']."][<FIELD_TOKEN>]\">";
		foreach ($fieldRows as $fieldKey=>$fieldValue) {
			echo "<option value=\"___distinct___lookup___".$fieldValue['Field']."\">" . $fieldValue['Field'] . " (Unique Values)</option>";
			echo "<option value=\"".$fieldValue['Field']."\">" . $fieldValue['Field'] . "</option>";
		}
		echo "</select>";
		$this->closeDatabase($conn);
		exit;
	}

	function cloneObject($pointer,$name) {
		ob_end_clean();
		$name = $this->cleanTableNames($name);
		$this->currentAdminDB[CRUD_FIELD_CONFIG][$name] = $this->currentAdminDB[CRUD_FIELD_CONFIG][$pointer];
		$this->currentAdminDB[CRUD_FIELD_CONFIG][$name][TABLE_CONFIG]['is_clone'] = true;
		$this->currentAdminDB[CRUD_FIELD_CONFIG][$name][TABLE_CONFIG]['clone_of'] = $pointer;
		$this->writeAdminDB();
		echo "SUCCESS";
		exit;
	}

	#4 Step
	function displayFieldSelectionForm() {
		$conn = $this->connectDatabase($_GET['server'],$_GET['database']);
		$database = $_GET['database'];

		if (!$this->isPageInclude) {
			$configPointer = $_GET['edit'];
		} else {
			if (isset($_GET['conf']) && $_GET['conf'] != $this->current_config) {
				return;
			}
			$configPointer = $_REQUEST['tablePointer'];
		}
		$options .= "<tr><td></td><td></td><td></td>";
		foreach ($this->fieldControlType as $key=>$text) {
			if ($key == TABLE || $key == ID || $key == TEXT ) {
				$options .= "<td></td>";
				continue;
			}
			$rowOutPut = '';
			$checked2 = '';
			$value = $this->tableControlDefaults[$key];
			if ($text['type'] == 'checkbox' && ($value === true || $value == '1')) {
				$checked2 = 'Off';
			} else if ($text['type'] == 'checkbox' && $value === false)  {
				$checked2 = 'On';
			} else if ($text['type'] == 'checkbox') {
				$checked2 = 'On';
			}

			if ($checked2 != '') {
				$rowOutPut .= 'Turn all ';
				if ($checked2 == 'On') {
					$rowOutPut .= 'Off';
				} else {
					$rowOutPut .= 'On';
				}
				$click = "if (this.innerHTML==\"Turn all Off\") { cruddy(\"input[$key]\").attr(\"checked\",false);cruddy(\"span[{$key}_onoff]\").html(\"Off\"); cruddy(\"input[$key]\").val(0); this.innerHTML = \"Turn all On\";} else { cruddy(\"input[$key]\").attr(\"checked\",true); cruddy(\"input[$key]\").val(1); cruddy(\"span[{$key}_onoff]\").html(\"On\");this.innerHTML = \"Turn all Off\"; }";
			} else {
				$rowOutPut .= 'Replace All Text';
				$click   =  "var val = window.prompt(\"Enter the replacement value\",\"\");cruddy(\"input[$key]\").val(val);";
			}
			$options .= "<td><a onclick='$click'>".$rowOutPut."</a></td>";
		}
		
		$options .= "</tr>";
		$options .= "<tr>";
		$options .= "<td>Field Name</td>";
		foreach ($this->fieldConfigType as $type=>$text) {
			$options .= "<td>".$text['desc']."</td>";
		}
		foreach ($this->fieldControlType as $type=>$text) {
			$options .= "<td>".$text['desc']."</td>";
		}
		$options .= "</tr>";
		$table = $this->currentAdminDB[CRUD_FIELD_CONFIG][$_GET['edit']][TABLE_CONFIG][OBJECT_TABLE];
		if (!$table) {
			$table = $_GET['edit'];
		}
		$fieldRows = $this->queryDatabase(sprintf(GET_COLUMNS_SQL,$table),$conn);

		foreach ($fieldRows as $fieldKey=>$fieldValue) {
			$options .=  "
				<tr id=\"".$fieldValue['Field']."_tr\">
					 <td>$fieldValue[Field]<input type=\"hidden\" value=\"".$fieldValue['Field']."\" name=\"fields[".$fieldValue['Field']."][name]\"/></td>
				";
			foreach ($this->fieldConfigType as $key=>$text) {
				$checked = '';
				$value = '';
				$extra = '';
				$checked2 = '';
				$extra2 = '';

				if ($key == "VALUE") {
					$value;
				}

				$value = $this->currentAdminDB[CRUD_FIELD_CONFIG][$configPointer][$fieldValue['Field']."_config"][$key];
				if ($text['type'] == 'checkbox' && ($value === true || $value == '1')) {
					$checked = 'checked';
					$checked2 = 'On';
				} else if ($text['type'] == 'checkbox' && $value === false)  {
					$checked2 = "Off";
				} else if ($text['type'] == 'checkbox') {
					$checked2 = "Off";
				}

				if ($text['type'] == 'checkbox') {
					$extra = "style='cursor:pointer;' onclick='toggleObj(\"fields_config[".$fieldValue['Field']."][$key]\")'";
					//$extra2 = "style='display:none;'";
				}
				
				
				if ($text['type'] != 'link') {
					$options .= "<td $extra><input $extra2 $extra type=\"$text[type]\" name=\"fields_config[".$fieldValue['Field']."][$key]\" $key id=\"fields_config[".$fieldValue['Field']."][$key]\" value=\"$value\" $checked/><span {$key}_onoff id=\"fields_config[".$fieldValue['Field']."][$key][onoff]\" class=\"".strtolower($checked2)."\">$checked2</span></td>";
				} else {
					$value = $this->currentAdminDB[CRUD_FIELD_CONFIG][$configPointer][$fieldValue['Field']."_config"];

					$autoObject = parent::parseColumnInfo($fieldValue['Type'],$fieldValue['Default'],$fieldValue['Field']);
					$optionsObjectTypes = '<option>Select a Field Type</option>';
					foreach ($this->fieldObjectTypes as $key2=>$text2) {
						$selected = "";
						if ($fieldValue['Type'] == "date") {
							$value['TYPE'] = "date";
						}
						if ($fieldValue['Type'] == "datetime" || $fieldValue['Type'] == "timestamp") {
							$value['TYPE'] = "timestamp";
						}
						if ($value['TYPE'] == $key2) {
							$selected = "selected";
						}
						if ($autoObject['TYPE'] == $key2 && empty($value['TYPE'])) {
							$selected = "selected";
						}
						$optionsObjectTypes .= "<option value=\"".$key2."\" $selected>".$text2['desc']."</option>";
				}

				$noneAvailable = true;
				$optionsMiscTypes = '<option>Add A New Misc. Config</option>';
				foreach ($this->fieldMiscTypes as $key2=>$text2) {
					if (isset($value[$key2])) { continue;}
						$noneAvailable = false;
						$optionsMiscTypes .= "<option value=\"".$key2."\">".$text2['desc']."</option>";
					}
					if ($noneAvailable===true) {
						$optionsMiscTypes .= "<option>There are no Validation Rules Available</option>";
					}

					$noneAvailable = true;
					$optionsValidationTypes = '<option>Add A New Validation Type</option>';
					foreach ($this->fieldValidationTypes as $key2=>$text2) {
						if (isset($value[$key2])) { continue;}
						$noneAvailable = false;
						$optionsValidationTypes .= "<option value=\"".$key2."\">".$text2['desc']."</option>";
					}
					if ($noneAvailable===true) {
						$optionsValidationTypes .= "<option>There are no Validation Rules Available</option>";
					}

					$noneAvailable = true;
					$optionsEventTypes = '<option>Add A New Javascript Event</option>';
					foreach ($this->fieldEventTypes as $key2=>$text2) {
						if (isset($value[$key2])) { continue; }
						$noneAvailable = false;
						$optionsEventTypes .= "<option value=\"".$key2."\">".$text2['desc']."</option>";
					}
					if ($noneAvailable===true) {
						$optionsEventTypes .= "<option>There are no Events Left</option>";
					}
					$attributes = $value;
					unset($attributes['TYPE']);
					if (is_array($attributes) && sizeof($attributes) > 0) {
						$existingEntriesTop = '<td valign="top">Edit:</td>';
						$existingEntriesBottom = '<td valign="top"></td>';
						foreach ($attributes as $optionKey=>$optionValue) {
							if ($optionKey == 'TYPE') {
								continue;
							}
							if ($this->fieldMiscTypes[$optionKey]) {
								$desc = $this->fieldMiscTypes[$optionKey]['desc'];
								$type = $this->fieldMiscTypes[$optionKey]['type'];
							} elseif ($this->fieldValidationTypes[$optionKey]) {
								$desc = $this->fieldValidationTypes[$optionKey]['desc'];
								$type = "checkbox";
							} elseif ($this->fieldEventTypes[$optionKey]) {
								$desc = $this->fieldEventTypes[$optionKey]['desc'];
								$type = "textarea";
							}

							$checked = '';
							$value = '';
							$extra = '';
							$checked2 = '';
							$extra2 = '';
							if ($type == 'checkbox' && ($optionValue === true || $optionValue == '1')) {
								$checked = 'checked';
								$checked2 = 'On';
							} else if ($type == 'checkbox' && $optionValue === false)  {
								$checked2 = "Off";
							} else if ($type == 'checkbox') {
								$checked2 = "Off";
							}

							if ($type == 'checkbox') {
								$extra = "style='cursor:pointer;' onclick='toggleObj(\"fields_config[".$fieldValue['Field']."][$optionKey]\")'";
								$extra2 = "style='display:none;'";
							}

							$existingEntriesTop .= "<td valign=\"top\"><span id=\"".$fieldValue['Field'].$optionKey."_top\">$desc:</td>";
							$deleteButton = "<a style='cursor:pointer;' class='button' onclick='$(\"fields_config[".$fieldValue['Field']."][$optionKey]\").style.display = \"none\";$(\"fields_config[".$fieldValue['Field']."][$optionKey]\").value = \"!!DELETE_TOKEN!!\";this.style.display=\"none\";$(\"".$fieldValue['Field'].$optionKey."_top\").style.textDecoration = \"line-through\";$(\"".$fieldValue['Field'].$optionKey."_top\").style.color = \"red\";'><span>Delete</span></a>";
							if ($type != 'textarea') {
								if (!is_array($optionValue)) {
									$existingEntriesBottom .= "<td valign=\"top\"><input $extra2 $extra type=\"$text[type]\" name=\"fields_config[".$fieldValue['Field']."][$optionKey]\" id=\"fields_config[".$fieldValue['Field']."][$optionKey]\" value=\"$optionValue\" $checked/><span id=\"fields_config[".$fieldValue['Field']."][$optionKey][onoff]\" class=\"".strtolower($checked2)."\">$checked2</span><br/>$deleteButton</td>";
								} else {
									$existingEntriesBottom .= "<td valign=\"top\">";
									foreach ($optionValue as $k=>$v) {
										$existingEntriesBottom .= "<input $extra2 $extra type=\"$text[type]\" name=\"fields_config[".$fieldValue['Field']."][$optionKey][key][]\" id=\"fields_config[".$fieldValue['Field']."][$optionKey][key][]\" value=\"$k\" $checked/>=><input $extra2 $extra type=\"$text[type]\" name=\"fields_config[".$fieldValue['Field']."][$optionKey][value][]\" id=\"fields_config[".$fieldValue['Field']."][$optionKey][value][]\" value=\"$v\" $checked/><br/><span id='".$fieldValue['Field']."additional'></span><span id='".$fieldValue['Field']."additional'></span>";
									}
									$existingEntriesBottom .= "<a style='cursor:pointer;' class='button' onclick='if (msgDebug === false) {alert(\"Known issue, please click add however many times before entering the attribute name and value (otherwise it clears out)\");}msgDebug=true;$(\"".$fieldValue['Field']."additional\").innerHTML += \"<input type=text name=fields_config[".$fieldValue['Field']."][$optionKey][key][]/>=><input type=text name=fields_config[".$fieldValue['Field']."][$optionKey][value][] /><br/>\";'><span>Add</span></a></td>";
								}
							} else {
								$existingEntriesBottom .= "<td valign=\"top\"><textarea style=\"height:100px;\" name=\"fields_config[".$fieldValue['Field']."][$optionKey]\" id=\"fields_config[".$fieldValue['Field']."][$optionKey]\">$optionValue</textarea>$deleteButton</td>";
							}
						}
					} else {
						$existingEntriesTop = "";
					}
					$extra = "style='cursor:pointer;' onclick='toggleObj(\"fields_config[".$fieldValue['Field']."][$optionKey]\")'";
					$extra2 = "style='display:none;'";
					$options .= "
							 <td>
								<!--<a onclick=\"addRow('".$fieldValue['Field']."_tr','".$fieldValue['Field']."_span',this);\" style=\"cursor:pointer;\">Configure</a>-->
								<div style=\"float:left\" id=\"".$fieldValue['Field']."_span_copy\" >
									<h3 style=\"margin:0px\">".$fieldValue['Field']." control</h3>
									<table style=\"border:none;\">
										<tr>
											<td>Input Type:</td>
											<td>
												<span id=\"additionalParameters\"></span>
												<select name=\"fields_config[".$fieldValue['Field']."][TYPE]\" id=\"fields_config[".$fieldValue['Field']."][TYPE]\" onchange=\"handleWidgetChange(this,'{$fieldValue['Field']}')\">
													$optionsObjectTypes
												</select>
											</td>
										</tr>
										<tr>
											<td> Validations: </td>
											<td> <select onchange=\"onChangeValidations('".$fieldValue['Field']."',this);\" id=\"fields_config[".$fieldValue['Field']."][events]\" id=\"fields_config[".$fieldValue['Field']."][validations]\">
														$optionsValidationTypes
													</select>
										</td>
										</tr>
										<tr>
											<td> Events:</td>
											<td> <select onchange=\"addTD('".$fieldValue['Field']."_new','".$fieldValue['Field']."_' + this.value);$('".$fieldValue['Field']."_' + this.value).innerHTML = this.value + ' Event:<br/><textarea name=fields_config[".$fieldValue['Field']."][' + this.value + ']></textarea>';this.removeChild(this[this.selectedIndex]);\" id=\"fields_config[".$fieldValue['Field']."][events]\">
													$optionsEventTypes
												</select>
											</td>
										</tr>
										<tr>
											<td> Misc:</td>
											<td> <select onchange=\"addTD('".$fieldValue['Field']."_new','".$fieldValue['Field']."_' + this.value);var HTML = this.options[this.selectedIndex].text + '<br/><input type=text name=fields_config[".$fieldValue['Field']."][' + this.value + ']';if(this.value=='ExtraAttributes'){ $('".$fieldValue['Field']."_' + this.value).innerHTML += HTML + '[key][]/>=><input name=fields_config[".$fieldValue['Field']."][' + this.value + '][value][]>';} else { $('".$fieldValue['Field']."_' + this.value).innerHTML += HTML + '/>';}this.removeChild(this[this.selectedIndex]);\" id=\"fields_config[".$fieldValue['Field']."][misc]\">
													$optionsMiscTypes
												</select>
											</td>
										</tr>
									</table>
								</div>
								<div style=\"float:left\" id=\"".$fieldValue['Field']."_span_copy2\">
									<table style=\"border:none;\">
										<tr>
											<td valign=\"top\" style=\"display:none;\" id=\"".$fieldValue['Field']."_new\"></td>
											<td>
												<table style=\"border:none;\">
													<tr>
														$existingEntriesTop
													</tr>
													<tr>
														$existingEntriesBottom
													</tr>
												</table>
										</td>
										</tr>
																								</table>
								</div>
							 </td>";
					}
				}
				foreach ($this->fieldControlType as $key=>$text) {
					if (empty($text["type"]) ) { continue;}
					$checked = '';
					$value = '';
					$extra = '';
					$checked2 = '';
					$extra2 = '';
					if (!isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$configPointer][$fieldValue['Field']][$key]) && !isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$configPointer][TABLE_CONFIG]['configuredfields'])) {
						// -- pull from default
						$value = $this->fieldControlDefaults[$key];
						if ($key == CAPTION) {
							$value = str_replace(array('_','-'),array(' ',' '),ucfirst($fieldValue['Field']));
						}

						$value = $this->createDisplayName($value);
					} elseif (isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$configPointer][$fieldValue['Field']][$key])) {
						$value = $this->currentAdminDB[CRUD_FIELD_CONFIG][$configPointer][$fieldValue['Field']][$key];
					}
					if ($text['type'] == 'checkbox' && ($value === true || $value == '1')) {
						$checked = 'checked';
						$checked2 = 'On';
					} else if ($text['type'] == 'checkbox' && $value === false)  {
						$checked2 = "Off";
					} else if ($text['type'] == 'checkbox') {
						$checked2 = "Off";
					}

					if ($text['type'] == 'checkbox') {
						$extra = "style='cursor:pointer;' onclick='toggleObj(\"fields[".$fieldValue['Field']."][$key]\")'";
						$extra2 = "style='display:none;'";
					}
					if (TEXT == $key || ID == $key || TABLE == $key) {
						$optionsFieldsTables = "";
						if (TABLE == $key) {
							$failure = false;
							$resultrows = $this->queryDatabase(GET_TABLES_SQL,$conn);
							if (empty($resultrows)) {
								$resultrows = $this->queryDatabase(GET_TABLES_SQL." from $database",$conn);
								if (empty($resultrows)) {
									$resultrows = $this->queryDatabase("SHOW TABLES FROM $database",$conn);
									if (empty($resultrows)) {
										$failure = true;
									}
								}
							}
							$optionsFieldsTables .= "<option value=\"\">No Lookup Table Defined</option>";
							if ($failure === false) {
								foreach ($resultrows as $kk=>$vv) {
									 $selected = "";
									 $tableName = $vv['Tables_in_'.$database];
									 if ($value == $tableName) {
										$tableNameSelected = $tableName;
										$selected = "selected";
									 }
									 $optionsFieldsTables .= "<option $selected value=\"$tableName\">$tableName</option>";
								}
							}
							$input = "<select onchange=\"lookupFieldsFromTable(this.value,'$_GET[server]','$_GET[database]','$fieldValue[Field]','$key');\" name=\"fields[".$fieldValue['Field']."][$key]\" id=\"fields[".$fieldValue['Field']."][$key]\">$optionsFieldsTables</select>";
						} else {
							if (empty($value)) {
								$input = "<span id=\"fields[".$fieldValue['Field']."][$key][span]\">Select A Table</span>";
							} else {
								$input  = "<span id=\"fields[".$fieldValue['Field']."][$key][span]\">";
								$fieldRows2 = $this->queryDatabase(sprintf(GET_COLUMNS_SQL,$tableNameSelected),$conn);
								if (is_array($fieldRows2)) {
									$input .= "<select name=\"fields[$fieldValue[Field]][$key]\">";
									foreach ($fieldRows2 as $fieldKey2=>$fieldValue2) {
										$selected = "";
										$selected2 = "";
										if ($value == $fieldValue2['Field']) {
											$selected = "selected";
										}
										if ($value == "___distinct___lookup___".$fieldValue2['Field']) {
											$selected2 = "selected";
										}
										$input .=  "<option $selected2 value=\"___distinct___lookup___".$fieldValue2['Field']."\">".$fieldValue2['Field']." (Distinct Values)</option>";
										$input .=  "<option $selected value=\"".$fieldValue2['Field']."\">" . $fieldValue2['Field'] . "</option>";
									}
									$input .=  "</select></span>";
								}
							}
						}
					} else {
						$input = "<input $extra2 $extra {$key} type=\"$text[type]\" name=\"fields[".$fieldValue['Field']."][$key]\" id=\"fields[".$fieldValue['Field']."][$key]\" value=\"$value\" $checked/><span {$key}_onoff id=\"fields[".$fieldValue['Field']."][$key][onoff]\" class=\"".strtolower($checked2)."\">$checked2</span>";
					}
					$options .= "<td $extra>$input</td>";
				}
				echo "</tr>";
			}

			$i=0;
			if (is_array($this->currentAdminDB[CRUD_FIELD_CONFIG])) {
				foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG] as $values) {
					$sameFields[$i++] = array_keys($values);
				}
			}

			$break=false;
			if (is_array($sameFields)) {
				foreach ($sameFields as $key=>$array) {
					foreach ($sameFields as $key2=>$array2) {
						if ($key!=$key2) {
							if ($array == $array2) {
								$recurseOnOff =
								"<input style='display: none;' onclick='toggleObj('recurse')' name='recurse' id='recurse' value='0' checked='checked' type='checkbox'><span id='recurse[onoff]' class='off' onclick=\"toggleObj('recurse');\">Off</span> (Sync off because exact table mapping would overwrite another config)";
								$break=true;
								break;
							}
						}
					}
					if ($break===true) {
						break;
					}
				}
			}
			if ($break===false) {
				$recurseOnOff =
				"<input style='display: none;' onclick='toggleObj('recurse')' name='recurse' id='recurse' value='1' checked='checked' type='checkbox'><span id='recurse[onoff]' class='on' onclick=\"toggleObj('recurse');\">On</span>";
			}

			if (!$this->isPageInclude) {
				$recurse = "<br/>";
				//$recurse = "(<span style=\"cursor:pointer;\" onclick=\"toggleObj('recurse');\">Sync To Same Name Fields In Other Tables? </span>$recurseOnOff)";
			} else {
				$recurse = "<br/>";
			}
			echo
			"
			<script>
			var msgDebug = false;
			function insertAfter( referenceNode, newNode ) {
				referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
			}

			function addRow(id,spanid,obj){
				obj.onclick='';
				var tbody = document.getElementById(id);
				var row = document.createElement('TR');
				var td1 = document.createElement('TD');
				var td2 = document.createElement('TD');
				td2.colSpan = '50';
				var span = document.createElement('span');
				span.id = spanid;
				td1.appendChild(span);
				var span = document.createElement('span');
				span.id = spanid + '2';
				td2.appendChild(span);
				row.appendChild(td1);
				row.appendChild(td2);
				insertAfter(tbody,row);
				$(spanid).innerHTML = $(spanid + \"_copy\").innerHTML;
				$(spanid + '2').innerHTML = $(spanid + \"_copy2\").innerHTML;
				$(spanid + \"_copy\").innerHTML = \"\";
				$(spanid + \"_copy2\").innerHTML = \"\";
			}

			function addTD(id,spanid){
				var tbody = document.getElementById(id);
				var td1 = document.createElement('TD');
				td1.vAlign = 'top';
				td1.style.borderRight = '2px solid black';
				var span = document.createElement('span');
				span.id = spanid;
				td1.appendChild(span);
				insertAfter(tbody,td1);
			}

			function handleWidgetChange(obj,name) {
				if (obj.value == 'file') {
					var response = window.prompt(\"Please enter the path for the files to upload\",\"../uploads/\");
					$('additionalParameters').innerHTML += \"<input name='fields_config[\" + name + \"][MOVE_TO]' id='fields_config[\" + name + \"][MOVE_TO]' value='\" + response + \"'/>\";
				}
			}

			function onChangeValidations(id,obj) {
				addTD(id + '_new',id + '_' + obj.value);
				$(id + '_' + obj.value).innerHTML = obj.options[obj.selectedIndex].text + ':<br/><input type=hidden name=fields_config[' + id + '][' + obj.value + '] value=true><span class=on>On</span>';

				if (obj.value == 'ValidateMinimumLength') {
					$(id + '_' + obj.value).innerHTML = obj.options[obj.selectedIndex].text + ':<br/><input type=text name=fields_config[' + id + '][' + obj.value + ']';
				}
				$(id + '_' + obj.value).innerHTML += '<br/><br/>Error Message:<br/><input type=text name=fields_config[' + id + '][' + obj.value + 'ErrorMessage]';
				obj.removeChild(obj[obj.selectedIndex]);
			}

			</script>
			<form action='".$_SERVER['PHP_SELF']."?admin=1&select_fields=1&store_database=1' id='tableForm{$_GET['edit']}' method='post'>
				".$this->displayGenericObjects()."
				 <div id='serverinfo'>
						<strong>{$_GET['edit']}</strong> Field Configuration
						$errors
						<input type=\"hidden\" name=\"tablePointer\" value=\"".$configPointer."\"/>
						<table style='width:1500px;'>
									$options
						</table>
						<a class='button' onclick='$(\"tableForm{$_GET['edit']}\").action= $(\"tableForm{$_GET['edit']}\").action + \"&conf=$this->current_config\";$(\"tableForm{$_GET['edit']}\").submit();'><span>Configure Fields</span></a>  $recurse
				 </div>
			</form>
			";
			$this->closeDatabase($conn);
	}

	function storeFieldSelectionForm() {
		ob_end_clean();

		if ($this->isPageInclude) {
			if ($_GET['conf'] != $this->current_config) {
				return;
			}
			$this->currentAdminDB[CRUD_FIELD_CONFIG][TABLE_CONFIG]['alias'] = $configurationFile;
			foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']] as $k=>$v) {
				if ($k!=TABLE_CONFIG) {
					unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$k]);
				}
			}
		}
		$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG]['configuredfields'] = '1';
		if (!isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][OBJECT_ACTIONS])) {
			//default data in step 2 is not set
			$conn = $this->connectDatabase($_GET['server'],$_GET['database']);
			foreach ($this->tableControlDefaults as $systemKey=>$text) {
				$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][$systemKey] = $text;
			}
			$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][OBJECT_ACTIONS] = $this->tableControlDefaults[OBJECT_ACTIONS];
			$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][OBJECT_TABLE] = $_REQUEST['tablePointer'];
			$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][OBJECT_DESC] = $this->createDisplayName($_REQUEST['tablePointer']);
			$resultPK = $this->getPrimaryKey($_GET['database'],$_REQUEST['tablePointer'],$conn);
			$pk = $resultPK[0]['column_name'];
			$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][OBJECT_PK] = $pk;
			$linkEdit = "?action=".strtolower($this->actionTypes['update'].$_REQUEST['tablePointer'])."&".$pk."=%".$pk."%";
			$linkDelete = "?action=".strtolower($this->actionTypes['delete'].$_REQUEST['tablePointer'])."&".$pk."=%".$pk."%";
			$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][EDIT_LINK] = $linkEdit;
			$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][DELETE_LINK] = $linkDelete;
			$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][TABLE_CONFIG][OTHER_LINKS] = "";
			$this->addDefaultFieldData($_REQUEST['tablePointer'],$conn,$_REQUEST['tablePointer']);
			$this->closeDatabase($conn);
		}

		$deleteToken = '!!DELETE_TOKEN!!';
		foreach ($_REQUEST['fields'] as $key=>$selected) {
			foreach ($this->fieldControlType as $systemKey=>$text) {
				if ($_REQUEST['fields'][$key][$systemKey]) {
					if ($_REQUEST['fields'][$key][$systemKey] != $deleteToken) {
						$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key][$systemKey] = $_REQUEST['fields'][$key][$systemKey];
					} else {
						unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key][$systemKey]);
					}
				} else {
					unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key][$systemKey]);
				}
			}
		}
		foreach ($_REQUEST['fields_config'] as $key=>$selected) {
			if (!isset($_REQUEST['fields_config'][$key][TABLE])) {
				$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"]["TYPE"] = $_REQUEST['fields_config'][$key]["TYPE"];
			} else {
				unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"]["TYPE"]);
			}
			foreach ($this->fieldMiscTypes as $key2=>$text2) {
				if ($_REQUEST['fields_config'][$key][$key2]) {
					if ($key2 == 'ExtraAttributes') {
						unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2]);
						foreach ($_REQUEST['fields_config'][$key][$key2]['key'] as $kk=>$vv) {
							if (!empty($vv) && !empty($kk)) {
								$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2][$vv] = $_REQUEST['fields_config'][$key][$key2]['value'][$kk];
							}
						}
					} else {
						if ($_REQUEST['fields_config'][$key][$key2] != $deleteToken) {
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2] = $_REQUEST['fields_config'][$key][$key2];
						} else {
							unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2]);
						}
					}
				}
			}
			foreach ($this->fieldValidationTypes as $key2=>$text2) {
				if ($_REQUEST['fields_config'][$key][$key2] || $_REQUEST['fields_config'][$key][$key2.'ErrorMessage']) {
					if ($_REQUEST['fields_config'][$key][$key2] != $deleteToken) {
						if ($_REQUEST['fields_config'][$key][$key2] != $deleteToken) {
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2] = $_REQUEST['fields_config'][$key][$key2];
						} else {
							unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2]);
						}
						if (isset($_REQUEST['fields_config'][$key][$key2.'ErrorMessage'])) {
							if ($_REQUEST['fields_config'][$key][$key2.'ErrorMessage'] != $deleteToken) {
								$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2.'ErrorMessage'] = $_REQUEST['fields_config'][$key][$key2.'ErrorMessage'];
							} else {
								unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2.'ErrorMessage']);
							}
						}
					} else {
						unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2]);
					}
				}
			}
			foreach ($this->fieldEventTypes as $key2=>$text2) {
				if ($_REQUEST['fields_config'][$key][$key2]) {
					if ($_REQUEST['fields_config'][$key][$key2] != $deleteToken) {
						$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2] = $_REQUEST['fields_config'][$key][$key2];
					} else {
						unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2]);
					}
				}
			}

			foreach ($this->fieldConfigType as $key2=>$text2) {
				if ($_REQUEST['fields_config'][$key][$key2]) {
					if ($_REQUEST['fields_config'][$key][$key2] != $deleteToken) {
						$this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2] = $_REQUEST['fields_config'][$key][$key2];
					} else {
						unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2]);
					}
				} else {
					unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']][$key."_config"][$key2]);
				}
			}
		}

		if ($_REQUEST['recurse'] == 1) {
			foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG][$_REQUEST['tablePointer']] as $key=>$fieldConfigs) {
				if ($key == 'tableDef') {continue;}
				foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG] as $section=>$configs) {
					foreach ($configs as $fieldOption=>$options) {
						if ($fieldOption == 'tableDef') {continue;}
						if ($fieldOption == $key) {
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$section][$fieldOption] = $fieldConfigs;
						}
					}
				}
			}
		}
		$this->writeAdminDB();
		if (!isset($_COOKIE['redirect']) || $this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->redirect($_SERVER['PHP_SELF']);
		} else {
			$this->redirect($_COOKIE['redirect']);
		}
		exit;
	}


	#5 Step
	function displayGroupSelectionForm() {
		if ($_GET['edit']) {
			$display = "inline";
			$list = $this->currentAdminDB[CRUD_FIELD_CONFIG];
			foreach ($this->currentAdminDB['crud']['groups'] as $key=>$value) {
				if ($key == 'Other' || $key == 'group_id') {continue;}
				$database = $key;
				$groupHash[$database] = $key;
				foreach ($value as $table) {
					$i++;
					$groupedVariables[$database][$table]['group_name'] = $database;
					$groupedVariables[$database][$table]['option_value'] = $table;
					$groupedVariables[$database][$table]['option_desc'] = $table;
					unset($list[$table]);
				}
			}
		} else {
			$display = "none";
			$list = $this->currentAdminDB[CRUD_FIELD_CONFIG];
			foreach ($list as $key=>$value) {
				$i++;
				$parts = explode("/",$value[TABLE_CONFIG][OBJECT_CONNECTION_STRING]);
				$database = $parts[sizeof($parts)-1];
				$groupHash[$database] = $key;
				$groupedVariables[$database][$key]['group_name'] = $database;
				$groupedVariables[$database][$key]['option_value'] = $key;
				$groupedVariables[$database][$key]['option_desc'] = $key;
				unset($list[$key]);
			}
		}
		if (($i > 30 && !isset($_GET['edit'])) || $this->currentAdminDB['crud']['group_tables'] == 1 && $this->currentAdminDB['crud']['group_tables'] != 0) {
			$defaultGroup = "1";
			$defaultGroupTxt = "On";
		} else {
			$defaultGroup = "0";
			$defaultGroupTxt = "Off";
		}
		if (is_array($list)) {
			foreach ($list as $key=>$value) {
				$variableOptions .= "<option value='$key'>".$key."</option>";
			}
		}

		if (is_array($groupHash)) {
			foreach ($groupHash as $hash=>$void) {
				$cnt++;
				if (!isset($_GET['edit'])) {
					$hashText = str_replace(array("-","_","."),array(" "," "," "),$hash);
				} else {
					$hashText = $hash;
				}
				$additionalTDsToLoad .= '
				<td align="center" valign="middle">
					<a href="javascript:moveSelectedOptions($(\'GroupMain\'),$(\'groupName['.$hash.']\'));">&gt;&gt;</a>
				</td>
				<td>
					Name: <input type="text" name="groupName['.$hash.'][name]" value="'.$hashText.'" style="width:115px"/><br/><br/>
					<select name="groupName['.$hash.'][]" id="groupName['.$hash.']" multiple="multiple" size="10" ondblclick="moveSelectedOptions($(\'groupName['.$hash.']\'),$(\'GroupMain\'),false);$(\'GroupMain\').style.display = \'block\';" title="Double Click to Remove" style="width:175px">';
				$turnOnGroups .= 'moveSelectedOptions($(\'groupName['.$hash.']\'),$(\'GroupMain\'),false);';
				foreach ($groupedVariables as $hashKey => $valuesArray) {
					if ($hash == $hashKey) {
						foreach ($valuesArray as $k=>$v) {
							$additionalTDsToLoad .= '<option value="'.$v['option_value'].'">'.$v['option_desc'].'</option>';
						}
					}
				}
				$additionalTDsToLoad .= '</select></td>';
				if ($cnt == 4  || ($cnt==3 && !isset($secondRow))) {
					$cnt=0;
					$secondRow=true;
					$additionalTDsToLoad .= "</tr><tr>";
				}
			}
		}
		$genericObjects = $this->displayGenericObjects();
		echo <<<EOD
			Step 4: Table Groups (Organize Things Logically)
			<form action='{$_SERVER['PHP_SELF']}?admin=1&select_groups=1&store_database=1' name='tableForm' id='tableForm' method='post'>
			$genericObjects
			<table>
			<tbody>
				<tr id="groupedTable">
					<td>
						Table Configs:<br/>
						<select id="GroupMain" name="groupName[Other][]" multiple="multiple" size="10" style="display:$display">
							$variableOptions
						</select>
						<input type="hidden" name="hasAddedNewGroup" id="hasAddedNewGroup"/>
						<input type="hidden" name="edit" id="edit" value="{$_GET['edit']}"/>
					</td>
					<td>
						<button type="button" style="background-color:#D8FFD5;cursor:pointer;font-size:14px;font-weight:bold;" onclick="addNewVariableGroup()">Add New Group</button>
					</td>
					$additionalTDsToLoad
				</tr>
			</tbody>
			</table>
			<br/>
			<a class='button' onclick="
						var elms = document.body.getElementsByTagName('select');
						for(var p = 0, maxI = elms.length; p < maxI; ++p) {
							for(var i=0; i<elms[p].length; i++) {
								if (elms[p].title == 'Double Click to Remove' || elms[p].multiple == true) {
									// -- if typical attribs are found, these are the ones we need selected
									elms[p].options[i].selected = true;
								}
							}
						}
						$('tableForm').action= $('tableForm').action + '&conf=$this->current_config';
						if ($('edit').value == 'true' && $('hasAddedNewGroup').value == 1)
						{
							alert('Since you added a new group, you need to edit the user roles and who gets to see this new grouping');
						}
						document.tableForm.submit();
						"><span>Save Groupings</span></a>
						<span style="cursor:pointer;" onclick="toggleGroupings();$turnOnGroups">Group Tables</span><input style='display: none;' onclick=\"toggleGroupings();$turnOnGroups\" name='showGroups' id='showGroups' value='$defaultGroup' checked='checked' type='checkbox'><span id='showGroups[onoff]' class='on' onclick="toggleGroupings();$turnOnGroups">$defaultGroupTxt</span>
		</form>
EOD;
	}

	function storeGroupSelectionForm() {
		if ($this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->currentAdminDB['crud']['completed_step'] = 4;
		}
		ob_end_clean();
		foreach ($_POST['groupName'] as $section=>$groups) {
			if ($groups['name'] != $section && !empty($groups['name'])) {
				// -- user changed the name of the group unset and re-index
				$_POST['groupName'][$groups['name']] = $_POST['groupName'][$section];
				unset($_POST['groupName'][$section]);
				unset($_POST['groupName'][$groups['name']]['name']);
			}
			unset($_POST['groupName'][$section]['name']);
			if (sizeof($_POST['groupName'][$section]) == 0) {
				unset($_POST['groupName'][$section]);
			}
		}
		$this->currentAdminDB['crud']['groups'] =  $_POST['groupName'];
		$this->currentAdminDB['crud']['group_tables'] =  $_POST['showGroups'];
		$this->writeAdminDB();
		if ( ($_POST['edit'] == 'true' && $_POST['hasAddedNewGroup'] == 1) || !isset($_COOKIE['redirect']) || $this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->redirect($_SERVER['PHP_SELF']."?admin=1&select_roles");
		} else {
			$this->redirect($_COOKIE['redirect']);
		}
		exit;
	}

	#6 Step
	function displayRolesSelectionForm() {
		$groupOptions = "<select TOKN2 name=\"role[TOKEN][groups][]\" multiple=\"multiple\" size=\"5\">";
		if (isset($this->currentAdminDB['crud']['groups'])) {
			foreach ($this->currentAdminDB['crud']['groups'] as $k=>$v) {
				$groupOptions .= "<option value=\"$k\" selected>$k</option>";
			}
			$groupOptions .= "</select>";
		}

		if (!isset($_GET['edit'])) {
			if (isset($this->currentAdminDB['crud']['roles'] )) {
				die('<script>document.location = "'.$_SERVER['PHP_SELF'].'?admin=1&select_roles=1&edit=true";</script>');
			}
			$form = "
			<tr id='1'>
							 <td><img onclick='removeRow(\"1\",\"allroles\");' src='".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/delete.png' style='cursor:pointer;'/></td>
							 <td><input type='text' class='admin' name='role[1][role_name]' value='Super Admin'/></td>
							 <td align='center'><input type='checkbox' name='role[1][admin_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[1][delete_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[1][update_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[1][insert_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[1][search_role]' value='1' checked/></td>
							 <td align='center'>".str_replace(array('TOKEN','TOKN2'),array('1',''),$groupOptions)."</td>
						</tr>
						<tr id='2'>
							 <td><img onclick='removeRow(\"2\",\"allroles\");' src='".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/delete.png' style='cursor:pointer;'/></td>
							 <td><input type='text' class='admin' name='role[2][role_name]' value='Admin'/></td>
							 <td align='center'><input type='checkbox' name='role[2][admin_role]' value='0'/></td>
							 <td align='center'><input type='checkbox' name='role[2][delete_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[2][update_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[2][insert_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[2][search_role]' value='1' checked/></td>
							 <td align='center'>".str_replace(array('TOKEN','TOKN2'),array('2',''),$groupOptions)."</td>
						</tr>
						<tr id='cloner'>
							 <td><img onclick='removeRow(\"cloner\",\"allroles\");' src='".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/delete.png' style='cursor:pointer;'/></td>
							 <td>               <input id='cloner_name' type='text' class='admin' name='role[3][role_name]' value='Publisher'/></td>
							 <td align='center'><input id='cloner_admin_role' type='checkbox' name='role[3][admin_role]'  value='0'/></td>
							 <td align='center'><input id='cloner_delete_role' type='checkbox' name='role[3][delete_role]' value='0'/></td>
							 <td align='center'><input id='cloner_update_role' type='checkbox' name='role[3][update_role]' value='1' checked/></td>
							 <td align='center'><input id='cloner_insert_role' type='checkbox' name='role[3][insert_role]' value='1' checked/></td>
							 <td align='center'><input id='cloner_search_role' type='checkbox' name='role[3][search_role]' value='1' checked/></td>
							 <td align='center'>".str_replace(array('TOKEN','TOKN2'),array('3','id="cloner_group_roles"'),$groupOptions)."</td>
						</tr>";
			$i=3;
		} else {
			$i=0;
			foreach ($this->currentAdminDB['crud']['roles'] as $roleID=>$roleObject) {

				if (!isset($roleObject['admin_role'])) {
					$adminRoleValue = 0;
					$adminRoleChecked = '';
				} else {
					$adminRoleValue = 1;
					$adminRoleChecked = 'checked';
				}
				if (!isset($roleObject['delete_role'])) {
					$deleteRoleValue = 0;
					$deleteRoleChecked = '';
				} else {
					$deleteRoleValue = 1;
					$deleteRoleChecked = 'checked';
				}
				if (!isset($roleObject['update_role'])) {
					$updateRoleValue = 0;
					$updateRoleChecked = '';
				} else {
					$updateRoleValue = 1;
					$updateRoleChecked = 'checked';
				}
				if (!isset($roleObject['insert_role'])) {
					$insertRoleValue = 0;
					$insertRoleChecked = '';
				} else {
					$insertRoleValue = 1;
					$insertRoleChecked = 'checked';
				}
				if (!isset($roleObject['search_role'])) {
					$searchRoleValue = 0;
					$searchRoleChecked = '';
				} else {
					$searchRoleValue = 1;
					$searchRoleChecked = 'checked';
				}
				if ($i == sizeof($this->currentAdminDB['crud']['roles'])-1) {
					$id0="cloner";
					$id1="id='cloner'";
					$id2="id='cloner_name'";
					$id3="id='cloner_admin_role'";
					$id4="id='cloner_delete_role'";
					$id5="id='cloner_update_role'";
					$id6="id='cloner_insert_role'";
					$id7="id='cloner_search_role'";
					$id8="id='cloner_group_roles'";
				} else {
					$id1="id='$i'";
					$id0=$i;
				}
				$groupOptions = "<select $id8 name=\"role[$roleID][groups][]\" multiple=\"multiple\" size=\"5\">";
				if (isset($this->currentAdminDB['crud']['groups'])) {
					foreach ($this->currentAdminDB['crud']['groups'] as $k=>$v) {
						$sel = '';
						if (in_array($k,$roleObject['groups'])) {
							$sel = 'selected';
						}
						$groupOptions .= "<option value=\"$k\" $sel>$k</option>";
					}
					$groupOptions .= "</select>";
				}
				$form .= "
				<tr $id1>
					 <td><img onclick='removeRow(\"$id0\",\"allroles\");' src='".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/delete.png' style='cursor:pointer;'/></td>
								 <td>               <input $id2 type='text' class='admin' name='role[$roleID][role_name]' value='$roleObject[role_name]'/></td>
								 <td align='center'><input $id3 type='checkbox' name='role[$roleID][admin_role]' value='$adminRoleValue' $adminRoleChecked/></td>
								 <td align='center'><input $id4 type='checkbox' name='role[$roleID][delete_role]' value='$deleteRoleValue' $deleteRoleChecked/></td>
								 <td align='center'><input $id5 type='checkbox' name='role[$roleID][update_role]' value='$updateRoleValue' $updateRoleChecked/></td>
								 <td align='center'><input $id6 type='checkbox' name='role[$roleID][insert_role]' value='$insertRoleValue' $insertRoleChecked/></td>
								 <td align='center'><input $id7 type='checkbox' name='role[$roleID][search_role]' value='$searchRoleValue' $searchRoleChecked/></td>
								 <td align='center'>$groupOptions</td>
							</tr>";
							$i++;
			}
		}

			if (!isset($_GET['edit'])) {
				$selectAll = "
				var elms = document.body.getElementsByTagName(\"select\");
				for(var p = 0, maxI = elms.length; p < maxI; ++p) {
					for(var i=0; i<elms[p].length; i++) {
						elms[p].options[i].selected = true;
					}
				}";
			}
			echo
			"
			Step 5: Setup Roles
			<form action='".$_SERVER['PHP_SELF']."?admin=1&select_roles=1&store_database=1' name='tableForm' id='tableForm' method='post'>
				<input id=\"totalRoles\" type=\"hidden\" value=\"$i\"/>
				".$this->displayGenericObjects()."
					 <table id='allroles'>
							<tr>
								 <td>Del:</td>
								 <td>Role Name: </td>
								 <td>CRUDDY Admin: </td>
								 <td>Delete Link: </td>
								 <td>Update Link: </td>
								 <td>Insert Link: </td>
								 <td>Search Link: </td>
								 <td>Group Access: </td>
							</tr>
							$form
					 </table>
					 <button type=\"button\" onclick=\"cloneRow('cloner');$('cloner_name').value='NewRoleName';changeClonerNames();\"><span style=\"font-size:1.4em;padding-bottom:10px;cursor:pointer;\" >Add Another Role</span> <img src=\"".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/db_add.png\"/></button>
					 <br/>
					 <br/>
					 <a class='button' onclick='{$selectAll}$(\"tableForm\").action= $(\"tableForm\").action + \"&conf=$this->current_config\";$(\"tableForm\").submit();'><span>Create Roles</span></a>
				 </form>
			";
	}


	function storeRolesSelectionForm() {
		if ($this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->currentAdminDB['crud']['completed_step'] = 5;
		}
		ob_end_clean();
		$this->currentAdminDB['crud']['roles'] = $_POST['role'];
		$this->writeAdminDB();
		if (!isset($_COOKIE['redirect']) || $this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->redirect($_SERVER['PHP_SELF']."?admin=1&select_users");
		} else {
			$this->redirect($_COOKIE['redirect']);
		}
		exit;
	}

	#6 Step
	function displayUserSelectionForm() {
		$groupOptions = "<select class='admin' TOKN2 name=\"user[TOKEN][role]\">";
		if (isset($this->currentAdminDB['crud']['roles'])) {
			foreach ($this->currentAdminDB['crud']['roles'] as $k=>$v) {
				$groupOptions .= "<option value=\"$k\">$v[role_name]</option>";
			}
			$groupOptions .= "</select>";
		}

		if (!isset($_GET['edit'])) {
			if (isset($this->currentAdminDB['crud']['users'])) {
				die('<script>document.location = "'.$_SERVER['PHP_SELF'].'?admin=1&select_users=1&edit=true";</script>');
			}
			$form = "
						<tr id='cloner'>
							 <td><img onclick='removeRow(\"cloner\",\"allusers\");' src='".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/delete.png' style='cursor:pointer;'/></td>
							 <td>               <input id='user_name' type='text' class='admin' name='user[1][user_name]' value=''/></td>
							 <td align='center'><input id='password' type='password' class='admin' name='user[1][password]'  value=''/></td>
							 <td align='center'>".str_replace(array('TOKEN','TOKN2'),array('1','id="group_roles"'),$groupOptions)."</td>
						</tr>";
			$i=1;
		} else {
			$i=0;
			foreach ($this->currentAdminDB['crud']['users'] as $roleID=>$roleObject) {
				if ($i == sizeof($this->currentAdminDB['crud']['users'])-1) {
					$id0="cloner";
					$id1="id='cloner'";
					$id2="id='user_name'";
					$id3="id='password'";
				} else {
					$id1="id='$i'";
					$id0=$i;
				}

				$groupOptions = "<select $id8 class='admin' TOKN2 name=\"user[TOKEN][role]\">";
				if (isset($this->currentAdminDB['crud']['roles'])) {
					foreach ($this->currentAdminDB['crud']['roles'] as $k=>$v) {
						$sel = '';
						if ($k == $roleObject['role']) {
							$sel = 'selected';
						}
						$groupOptions .= "<option $sel value=\"$k\">$v[role_name]</option>";
					}
					$groupOptions .= "</select>";
				}
				$form .= "
				<tr $id1>
								 <td><img onclick='removeRow(\"$id0\",\"allusers\");' src='".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/delete.png' style='cursor:pointer;'/></td>
								 <td>               <input $id2 type='text' class='admin' name='user[$roleID][user_name]' value='$roleObject[user_name]'/></td>
								 <td align='center'><input $id3 type='password' type='password' class='admin' name='user[$roleID][password]'  value='$roleObject[password]'/></td>
								 <td align='center'>".str_replace(array('TOKEN','TOKN2'),array($roleID,'id="group_roles"'),$groupOptions)."</td>
							</tr>";
							$i++;
			}
		}
			echo
			"
			Step 6: Setup Users
			<form action='$_SERVER[PHP_SELF]?admin=1&select_users=1&store_database=1' name='tableForm' id='tableForm' method='post'>
				<input id=\"totalUsers\" type=\"hidden\" value=\"$i\"/>
				".$this->displayGenericObjects()."
					 <table id='allusers'>
							<tr>
								 <td>Del:</td>
								 <td>User Name: </td>
								 <td>Password: </td>
								 <td>Role: </td>
							</tr>
							$form
					 </table>
					 <button type=\"button\" onclick=\"cloneRow('cloner');changeClonerUserNames();\"><span style=\"font-size:1.4em;padding-bottom:10px;cursor:pointer;\" >Add Another User</span> <img src=\"".ABS_PATH_TO_CRUDDY_MYSQL_FOLDER."images/db_add.png\"/></button>
					 <br/>
				<br/>
					 <a class='button' onclick='if (finishUser()){ $(\"tableForm\").action= $(\"tableForm\").action + \"&conf=$this->current_config\"; $(\"tableForm\").submit();}'><span>Create Users</span></a>
				 </form>
			";
	}


	function storeUserSelectionForm() {
		if ($this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->currentAdminDB['crud']['completed_step'] = 6;
		}
		ob_end_clean();
		$this->currentAdminDB['crud']['users'] = $_POST['user'];
		$this->writeAdminDB();
		if (!isset($_COOKIE['redirect']) || $this->currentAdminDB['crud']['completed_step'] != 'All') {
			$this->redirect($_SERVER['PHP_SELF']."?admin=1&select_theme");
		} else {
			$this->redirect($_COOKIE['redirect']);
		}
		exit;
	}

	#8 Step
	function displayThemeSelectionForm() {
		echo $this->displayGenericObjects();

		if ($_GET['edit']) {
			$currentTheme = $_GET['edit'];
		} elseif (isset($this->currentAdminDB['crud']['theme'])) {
			$currentTheme = $this->currentAdminDB['crud']['theme'];
		} else {
			$currentTheme = 'Default Cruddy MySql';
		}

		echo
			"
			<div id='serverinfo'>
				 Final Step 7: Themes... Yum
				 <table>
						<tr>
							 <td>Select A Theme: </td>
							 <td>".$this->displayThemeCSS($currentTheme)."</td>
						</tr>
						<tr>
							 <td><a class='button' onclick='storeThemeInfo()'><span>Finish Setup</span></a></td>
						</tr>
				 </table>
			</div>
			";
	}

	function storeThemeSelectionForm() {
		/*if ($this->isPageInclude) {
			if ($_GET['conf'] != $this->current_config) {
				return;
			}
		}*/
		$this->currentAdminDB['crud']['completed_step'] = 'All';
		ob_end_clean();
		$this->currentAdminDB['crud']['theme'] = $_GET['theme'];
		$this->writeAdminDB();
		exit;
	}

	function cleanTableNames($tableName) {
		return preg_replace('/[^a-z0-9]/i', '',str_replace(" ","_",$tableName));
	}

	function displayGenericObjects() {
		$ret = "<span id='results'></span>";
		if (isset($_GET['edit'])) {
			$ret .= "<input type=\"hidden\" id=\"editing\" value=\"true\"/>";
		}
		return $ret;
	}

	function adminDBExists() {
		if (file_exists($this->adminFile)) {
			return true;
		} else {
			return false;
		}
	}

	function productionizeAdminDB() {
		if (isset($_GET['conf']) && $_GET['conf'] != $this->current_config) {
			return;
		}
		echo "<div class='success'>$this->adminFile has been productionized into a secure php array.<br/><a href=\"javascript:history.go(-1);\">(Click Here To Go Back)</a></div>";
		$this->writeAdminDB("<?php\n\n\$cruddyMysqlConfiguration  = ".var_export($this->currentAdminDB,true).";\n\n?>");
	}

	function readAdminDB() {
		$array = file_get_contents($this->adminFile);
		$newArray = unserialize($array);
		if ($newArray === false) {
			// -- assuming you have productionized your config array into a secure array configuration
			include($this->adminFile);
			$newArray = $cruddyMysqlConfiguration;
			$this->isProductionized = true;
		} else {
			$this->isProductionized = false;
		}
		$newArray['crud']['console_name'] = stripslashes($newArray['crud']['console_name']);
		//$this->processAssociativeArray($newArray[CRUD_FIELD_CONFIG],"\$assocArray[\$n] = stripslashes(\$v);");
		return $newArray;
	}

	function processAssociativeArray(&$assocArray,$phpCode) {
		if (is_array($assocArray)) {
			foreach ($assocArray as $n => $v) {
				if (is_array($v)) {
						$this->processAssociativeArray($v,$phpCode);
				} else {
						eval($phpCode);
				}
			}
		} else {
			return $assocArray;
		}
	}


	function writeAdminDB($stream='') {
		if (get_magic_quotes_gpc()) {
			$array = $this->processAssociativeArray($this->currentAdminDB,"\$assocArray[\$n] = stripslashes(\$v);");
		}
		if ($stream=='') {
			$data = serialize($this->currentAdminDB);
		} else {
			$data = $stream;
		}
		if (!$handle = @fopen($this->adminFile, 'w')) {
			@chmod(getcwd(),'755');
			if (!$handle = @fopen($this->adminFile, 'w')) {
				$this->handleErrors("Cannot open file ($this->adminFile)","fatal");
			}
		}
		if (fwrite($handle, $data) === FALSE) {
			$this->handleErrors("Could not write to file","fatal");
		}
		fclose($handle);
	}

	function writeFile($file,$data) {
		if (!$handle = @fopen($file, 'w')) {
			$this->handleErrors("Cannot open file ($this->adminFile)","fatal");
		}
		if (fwrite($handle, $data) === FALSE) {
			$this->handleErrors("Could not write to file","fatal");
		}
		fclose($handle);
	}

	function handleErrors($message,$level='fatal') {
		echo "<br/>".$message;
		if ($level=='fatal'){exit;}
	}

	function displayGlobalCSS() {
		return ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/cruddy_mysql.css';
	}

	function displayThemeCSS($returnCSS=true) {
		$crudStyles['templates']['Default Cruddy MySql'] = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/default.css';
		$crudStyles['templates']['Blue Gradient']        = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/blue_gradient.css';
		$crudStyles['templates']['Casablanca']           = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/casablanca.css';

		$crudStyles['templates']['Coffee with milk']     = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/coffee.css';
		$crudStyles['templates']['Cusco Sky']            = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/cusco.css';
		$crudStyles['templates']['Grey Scale']           = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/grey_scale.css';
		$crudStyles['templates']['Minimalist Blue']      = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/grey_scale.css';
		$crudStyles['templates']['Innocent']             = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/innocent.css';
		$crudStyles['templates']['Oranges in the sky']   = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/oranges.css';
		$crudStyles['templates']['Shades of Blue']       = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/shades_of_blue.css';
		$crudStyles['templates']['Sky is no heaven']     = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/sky_is_no_heaven.css';
		$crudStyles['templates']['Smooth Taste']         = ABS_PATH_TO_CRUDDY_MYSQL_FOLDER.'styles/smooth_taste.css';
		if ($returnCSS===true) {
			if (isset($crudStyles['templates'][$this->currentAdminDB['crud']['theme']])) {
				return $crudStyles['templates'][$this->currentAdminDB['crud']['theme']];
			} else {
				return $crudStyles['templates']['Default Cruddy MySql'];
			}
		} else {
			$selectBox = "
			<select class='admin' name='theme' id='theme'>
			<option value='None'>None</option>
			";
			foreach ($crudStyles['templates'] as $key=>$nothing) {
				$selected=""; if ($key == $returnCSS) {$selected="selected";}
				$selectBox .="<option $selected value=\"$key\">$key</option>";
			}
			$selectBox .= "</select>";
			return $selectBox;
		}
		unset($crudStyles);
	}
}

// pager class
class cruddyMysqlPager {
	var $total_records = NULL;
	var $start = NULL;
	var $scroll_page = NULL;
	var $per_page = NULL;
	var $total_pages = NULL;
	var $current_page = NULL;
	var $page_links = NULL;

	// total pages and essential variables
	function total_pages ($pager_url, $total_records, $scroll_page, $per_page, $current_page) {
		$this->url = $pager_url;
		$this->total_records = $total_records;
		$this->scroll_page = $scroll_page;
		$this->per_page = $per_page;
		if (!is_numeric($current_page)) {
			$this->current_page = 1;
		}else{
			$this->current_page = $current_page;
		}
		if ($this->current_page == 1)$this->start = 0; else$this->start = ($this->current_page - 1) *$this->per_page;
		$this->total_pages = ceil($this->total_records /$this->per_page);
	}

	// page links
	function page_links ($inactive_page_tag, $pager_url_last) {
		if ($this->total_pages <= $this->scroll_page) {
			if ($this->total_records <= $this->per_page) {
				$loop_start = 1;
				$loop_finish = $this->total_pages;
			}else{
				$loop_start = 1;
				$loop_finish = $this->total_pages;
			}
		}else{
			if($this->current_page < intval($this->scroll_page / 2) + 1) {
				$loop_start = 1;
				$loop_finish = $this->scroll_page;
			}else{
				$loop_start = $this->current_page - intval($this->scroll_page / 2);
				$loop_finish = $this->current_page + intval($this->scroll_page / 2);
				if ($loop_finish >$this->total_pages) $loop_finish = $this->total_pages;
			}
		}
		for ($i = $loop_start; $i <= $loop_finish; $i++) {
			if ($i == $this->current_page) {
				$this->page_links .= '<span '.$inactive_page_tag.'>'.$i.'</span>';
			}else{
				$this->page_links .= '<span><a href="'.$this->url.$i.$pager_url_last.'">'.$i.'</a></span>';
			}
		}
	}

	// previous page
	function previous_page ($previous_page_text, $pager_url_last) {
		if ($this->current_page > 1) {
			$this->previous_page = '<span><a href="'.$this->url.($this->current_page - 1).$pager_url_last.'">'.$previous_page_text.'</a></span>';
		}
	}

	// next page
	function next_page ($next_page_text, $pager_url_last) {
		if ($this->current_page <$this->total_pages) {
			$this->next_page = '<span><a href="'.$this->url.($this->current_page + 1).$pager_url_last.'">'.$next_page_text.'</a></span>';
		}
	}

	// first page
	function first_page ($first_page_text, $pager_url_last) {
		if ($this->current_page > 1) {
			$this->first_page = '<span><a href="'.$this->url.'1'.$pager_url_last.'">'.$first_page_text.'</a></span>'; // :)
		}
	}

	// last page
	function last_page ($last_page_text, $pager_url_last) {
		if ($this->current_page < $this->total_pages) {
			$this->last_page = '<span><a href="'.$this->url.$this->total_pages.$pager_url_last.'">'.$last_page_text.'</a></span>';
		}
	}

	// pages functions set
	function pager_set ($pager_url, $total_records, $scroll_page, $per_page, $current_page, $inactive_page_tag, $previous_page_text, $next_page_text, $first_page_text, $last_page_text, $pager_url_last) {
		$this->total_pages($pager_url, $total_records, $scroll_page, $per_page, $current_page);
		$this->page_links($inactive_page_tag, $pager_url_last);
		$this->previous_page($previous_page_text, $pager_url_last);
		$this->next_page($next_page_text, $pager_url_last);
		$this->first_page($first_page_text, $pager_url_last);
		$this->last_page($last_page_text, $pager_url_last);
	}
}

?>
