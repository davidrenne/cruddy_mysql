<?php

$pwd = dirname(__FILE__);
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

define("OBJECT_DESC","objdesc"); //high level table description (Keep short)
define("OBJECT_ACTIONS","objactions"); //array of possible CRUD actions used in switch of controller page
define("OBJECT_DEFAULT_ORDER","objdeforder"); //for a generic_read function to handle how the records should be initially sorted
define("OBJECT_READ_FILTER","objwhere"); //initial filter that the main recordset loads as
define("OBJECT_HIDE_NEW_LINK","objnewlink"); //a flag to say whether the table should have a "New" link associated with it
define("OBJECT_HIDE_VIEW_LINK","objviewlink"); //a flag to say whether the table should have a "New" link associated with it
define("OBJECT_DELETE_CHECK_CONSTRAINTS","objdel"); //by default the crud class will loop through all tables and fields and if it finds an identical fieldname in any table in the database and there are records in that table, it will tell the user they cannot delete the only way to bypass this constraint is by setting this to false
define("OBJECT_TABLE","objtable");//table name
define("OBJECT_CONNECTION_STRING","objconnection");//dba connection string
define("OBJECT_PK","objpk");//primary key hard coded
define("OBJECT_FILTER_DESC","objfilterdesc");//used when you want to describe what the data is filtered by inside your controller function
define("OBJECT_PAGING","objpaging");//by default paging is enabled unless you say false here. paging is defaulted to 10 records per page but just need to add new configuration here when needing new functionality
define("OBJECT_PAGING_NUM_ROWS_PER_PAGE","objpagingrows");
define("OBJECT_PAGING_SCROLL","objpagingscroll");
define("OTHER_OBJECTS", "otherobjects" );//otherobjects allows you to build supporting form objects that will be tacked on at the end of the form before the button to post/update

define("REQUIRED_TEXT","requiredText");
define("OTHER_LINKS", "otherlinks" );
define("OBJECT_HIDE_DETAILS_LINK","detailslink");
define("EDIT_TEXT","edittext");
define("DELETE_TEXT","deletetext");
define("EDIT_LINK", "editlink");
define("DELETE_LINK", "deletelink");

// field level keys and configs

define("CAPTION","caption"); // what the user sees as the field name
//these array keys/configurations are for the foreign key lookups definied at the field level
define("ID","id");
define("TEXT", "text");
define("TABLE", "table" );
define("WHERE", "where" );
define("SELECT","select");

define("SHOWCOLUMN","showc");
define("COLUMNPOSTTEXT","posttextc");
define("SORTABLE","sortable");
define("PRETEXTREAD","pretext");
define("POSTTEXTREAD","posttext");
define("REQUIRED","notreq");
define("UPDATE_READ_ONLY","ronlyupdate");
define("HIDE","inserthide");

define("ROW_ID","number_0x45dsa4654das654da64dsa654da");
define("INPUT_DOIT","doitdas4dsa454a6s54da65s4a6s5d4a6s5");
define("INPUT_SUBMIT","submitas2d1as32d1as2d1a3s21d3a2s13");

(include ("$pwd/dbal/dbal.php")) or die("This class require <a href='http://cesars.users.phpclasses.org/dba'>DBA</a> class. Please download it and copy the folder 'dbal' in $pwd");
(include ("$pwd/forms.php")) or die("This class require <a href='http://cesars.users.phpclasses.org/formsgeneration'>Forms Generation Class</a> class. Please download it and copy the file 'forms.php' in $pwd");


class cruddyMysql {
	
	function cruddyMysql($str,$table,$info=array()) {
		$pwd = dirname(__FILE__);
		$this->table = $table;
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

		if (stristr($filter,'=') || stristr($filter,'IN')) {
			$f = $filter == '' ? '' : ' WHERE '.$filter;
		} elseif ($filter != '') {
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
		$pager_url = $_SERVER['PHP_SELF']."?action=".$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['read'].$this->object_key.'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_field'].'='.$_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_field']].'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_direction'].'='.$_GET[$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_direction']].'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['page'].'=';
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
			if ($this->cruddyAdministrator) {
				echo ("ERROR: ".$dba->getLastError());
			}
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
		return$this->buildGenericForm(array(),false,"");
	}
	/**
	 * Generic Form
	 *
	 *  @access private
	 */
	function buildGenericForm($default=array(),$update=false,$update_condition="",$readOnly=false) {
		$methodStartTime = get_microtime_ms();
		$form = new form_class;
		$form->NAME=$this->table."_form";
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
				$input["VALUE"] = stripslashes($default[$k]);
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
						// -- for files, user should be mapping the MIME, MOVE_TO, and SIZE to other fields
						$form->GetFileValues($k,$userfile_values);
						$columns[$k] = $k;
						$values[$k] = $k;
						$_POST[$k] = $userfile_values["name"];
						$columns[$v['MIME']] = $v['MIME'];
						$values[$v['MIME']] = $v['MIME'];
						$_POST[$v['MIME']] = $userfile_values["type"];
						$columns[$v['SIZE']] = $v['SIZE'];
						$values[$v['SIZE']] = $v['SIZE'];
						$_POST[$v['SIZE']] = $userfile_values["size"];
						$_POST["tmp_name"] = $userfile_values["tmp_name"];
						if (isset($v['MOVE_TO'])) {
							if (@is_uploaded_file($userfile_values["tmp_name"])) {
								if (substr($v['MOVE_TO'],-1))
								if (substr($v['MOVE_TO'],-1) != '/' && strtoupper(substr(PHP_OS,0,3)!='WIN')) {
									$v['MOVE_TO'] .= "/";
								} elseif (substr($v['MOVE_TO'],-1) != "\\" && strtoupper(substr(PHP_OS,0,3)=='WIN')) {
									$v['MOVE_TO'] .= "\\";
								}
								if (!@move_uploaded_file($userfile_values["tmp_name"], $v['MOVE_TO'].$userfile_values["name"])) {
									die("File Upload Failed");
								}
							}
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
		$this->autoTemplate($form,$error_message,$verify,$update,$readOnly);
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

	/**
	 *  READ
	 *  @param string $filter SQL filter.
	 */
	function read($filter='') {
		$methodStartTime = get_microtime_ms();

		$definitions = &$this->tableDefinition;
		if (isset($definitions[TABLE_CONFIG][OBJECT_DEFAULT_ORDER]) && !stristr($filter,"order")) {
			$filter .= " ORDER BY ".$definitions[TABLE_CONFIG][OBJECT_DEFAULT_ORDER];
		}
		$this->doQuery($filter);
		$res  = &$this->result;
		$info = &$this->formParams;
		echo "<table>\n";
		if ( is_array($res) ) {
			foreach($definitions as $key => $value) {
				if ( !is_array($value) || $value[SHOWCOLUMN] == 0 || !isset($value[SHOWCOLUMN])) continue;
				
				// -- if the field doesnt say to NOT sort
				if ($definitions[TABLE_CONFIG][SORTABLE] == 1 || !isset($definitions[TABLE_CONFIG][SORTABLE])) {
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
					
					$sortLinkStart = "<a href='?action=".$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['read'].$this->object_key.'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_field'].'='.$key.'&'.$definitions[TABLE_CONFIG][OBJECT_ACTIONS]['order_direction'].'='.$direction."'>$directionAscii";	
					$sortLinkEnd = "</a>";
				}
				echo "      <th>".$sortLinkStart.$value[CAPTION].$sortLinkEnd."</th>\n";
				$sortLinkStart = $sortLinkEnd = '';
			}
			
			foreach($res as $k => $r) {
				$pagedResults = (array)$r;
				echo "   <tr>\n";
				
				$edit_url = $definitions[TABLE_CONFIG][EDIT_LINK];
				$del_url  = $definitions[TABLE_CONFIG][DELETE_LINK];

				foreach($pagedResults as $k2 => $v2) {
					$edit_url = str_replace('%'.$k2.'%', $v2,  rawurldecode($edit_url));
					$del_url  = str_replace('%'.$k2.'%', $v2,  rawurldecode($del_url));
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

					$text .= isset($info[$k]["OPTIONS"][$r[$k]]) ? $info[$k]["OPTIONS"][$r[$k]] : $r[$k];
					$text = htmlentities($text);
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
						$linkStart = "<a href='".str_replace("update_","view_",$edit_url)."'>";
						$linkEnd = "</a>";
					}

					if (strlen($text) > 30 && preg_match("|<[^>]+>(.*)</[^>]+>|U",$text)==0 && !stristr($text,"<img") && !stristr($text,"<input")) {
						$text = substr($text,0,30)."...";
					}
					echo "      <td>".$linkStart.stripslashes($text).$linkEnd."</td>\n";

				}

				if (!empty($definitions[TABLE_CONFIG][EDIT_TEXT])) {
					$edit = '<a title="Edit this '.$definitions[TABLE_CONFIG][OBJECT_DESC].'" href="'.$edit_url.'">'.$definitions[TABLE_CONFIG][EDIT_TEXT].'</a> - ';
				}
				if (!empty($definitions[TABLE_CONFIG][DELETE_TEXT])) {
					$delete = '<a title="Delete this '.$definitions[TABLE_CONFIG][OBJECT_DESC].'" href="javascript:if(window.confirm(\'Are you sure you wish to delete this '.$this->object_name.'?\')){document.location=\''.$del_url.'\';}">'.$definitions[TABLE_CONFIG][DELETE_TEXT].'</a>';
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
				echo "   </tr>\n";
			} 
		} else {
			echo "<tr> \n";
			echo "<td><h2>No ".$definitions[TABLE_CONFIG][OBJECT_DESC]."'s found.</h2></td>";
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
	function autoTemplate($form,$error_message,$verify,$update,$readOnly=false) {
		$methodStartTime = get_microtime_ms();
		$def = &$this->tableDefinition;
		$formParams = &$this->formParams;
		$formParams[INPUT_SUBMIT] =$this->button;
		$form->StartLayoutCapture();
		if (!empty($error_message)) {
			echo '<div class="error">'.$error_message.'</div>';
		}
		echo '<table summary="Input fields table">';
		foreach($this->formParams as $inpName => $i) {
			$continue = true; // -- continue not actually skipping when called
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
				echo $form->AddLabelPart(array("FOR"=>$inpName));
				echo ":  ".($def[$inpName][COLUMNPOSTTEXT]) ? $def[$inpName][COLUMNPOSTTEXT] : ""."</th>\n";
				echo "<td>";
				
				if ( isset($def[$inpName][UPDATE_READ_ONLY]) && $def[$inpName][UPDATE_READ_ONLY] || $readOnly === true) {
					$form->AddInputReadOnlyPart( $inpName );
				} else {
					$form->AddInputPart($inpName);
				}
				echo "</td>\n";
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
					echo$this->formParams[$key]['LABEL'];
					echo ':</th>';
				}
				echo "\n<td>";
				$form->AddInputPart($key);
				echo "</td>\n";
				echo "<td></td>\n";
				echo "</tr>\n";
			}
		}
		if ($readOnly === false) {
			echo '<tr><th align="right">';
			echo$this->formParams[INPUT_SUBMIT]["VALUE"];
			echo ':</th>';
			echo "\n";
			echo '<td>';
			$form->AddInputPart(INPUT_DOIT);
			echo '<input name="'.INPUT_SUBMIT.'" value="'.$this->formParams[INPUT_SUBMIT]["VALUE"].'" onclick="if(this.disabled || typeof(this.disabled)==\'boolean\') this.disabled=true ; form_submitted_test=form_submitted ; form_submitted=true ; form_submitted=(!form_submitted_test || confirm(\''.$form->ResubmitConfirmMessage.'\')) ; if(this.disabled || typeof(this.disabled)==\'boolean\') this.disabled=false ; sub_form=\'\' ; return true" id="'.INPUT_SUBMIT.'" type="submit">';
			echo "</td>\n";
			echo "<td></td>\n";
			echo "</tr>\n";
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
			$autoType =$this->parseColumnInfo($Type,$foo['Default'],$Field);
			$form["NAME"] = trim($Field);
			$form["ID"] = $form["NAME"];
			
			// -- if table is configured as not null then user has to enter something
			/*if (strtoupper($Null) == 'NO') {
				 $form["ValidateAsNotEmpty"] = 1;
			}*/
			
			// -- if developer tells class that the field is non-required then set dont set as required
			if($actInfo[REQUIRED] == 0 && isset($actInfo[REQUIRED])) {
				 $form["ValidateAsNotEmpty"] = 1;
				$form["Optional"] = false;
				 $form["LABEL"] .="<span class='required'>".$info[TABLE_CONFIG][REQUIRED_TEXT]."</span>";
			} else {
				$form["Optional"] = true;
				 unset($form["ValidateAsNotEmpty"]);
			}
			
			$form["LABEL"] = isset($actInfo[CAPTION]) ? $actInfo[CAPTION] : $Field;
			if (isset($actInfo[TABLE]) && isset($actInfo[ID]) && isset($actInfo[TEXT]) && $insert == true) {
				$form["TYPE"] = "select";
				$opt = & $form["OPTIONS"];
				if (isset($actInfo[WHERE])) {
					$where = " where ".$actInfo[WHERE]." order by ".$actInfo[TEXT]." ASC";
				}
				$rec1 = $dba->query("select ".$actInfo[ID].",".$actInfo[TEXT]." from ".$actInfo[TABLE].$where);
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
				$form["BasePath"] = PUBLIC_CRUD_CLASS_LOCATION."fck/";
				$form["HEIGHT"] = 400;
				$form["WIDTH"] = 800;
				$form["Skin"] = "silver";
				$form["UsesAutoFormName"] = "instance";
			}
			if ($form['TYPE'] == 'date' || $form['TYPE'] == 'timestamp') {
				$form["TYPE"] = "custom";
				$form["CustomClass"] = "form_date_class";
				$form["VALUE"] = 'now';
				$form["ChooseControl"] = 1;
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
		/*special field, for helps to know if the form were submited or not */
		$formParams[INPUT_DOIT]   = array("TYPE"=>"hidden","NAME"=>INPUT_DOIT,"VALUE"=>1);
		$formParams[INPUT_SUBMIT] =$this->button;
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
				if ($Field ==$this->tableDefinition[TABLE_CONFIG][OBJECT_PK]) {
					$return["ValidateAsInteger"] = 1;
				}
				break;
			case "float":
				$t=explode(",",$extra);
				$return["TYPE"] = "text";
				$return["MAXLENGTH"] = $t[0]+$t[1]+1;
				$return["SIZE"] = (floor($t[0]+$t[1]+1/1.5) > 50) ? 50 : floor($t[0]+$t[1]+1/1.5);;
				if ($Field ==$this->tableDefinition[TABLE_CONFIG][OBJECT_PK]) {
					$return["ValidateAsFloat"] = 1;
				}
				break;
			case "varchar":
				$return["TYPE"] = "text";
				$return["MAXLENGTH"] = $extra;
				$return["SIZE"] = (floor($extra/1.5) > 50) ? 50 : floor($extra/1.5);
				if ($Field ==$this->tableDefinition[TABLE_CONFIG][OBJECT_PK]) {
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
		 
		$this->adminFile = getcwd().$this->systemDirectorySeparator."crud_".$_SERVER['SERVER_NAME'].".config.php";
		$this->functionsFile = getcwd().$this->systemDirectorySeparator."crud_".$_SERVER['SERVER_NAME'].".custom.functions.php";
		$this->functionsDrawFile = getcwd().$this->systemDirectorySeparator."crud_".$_SERVER['SERVER_NAME'].".draw.functions.php";
		if ($this->adminDBExists()) {
			$this->currentAdminDB =$this->readAdminDB();
		}
		 
		$this->cruddyAdministrator =$this->currentAdminDB['crud']['roles'][$_COOKIE['current_role']]['admin_role'];
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
		$this->tableControlDefaults[OBJECT_DELETE_CHECK_CONSTRAINTS] = 0;
		$this->tableControlDefaults[OBJECT_HIDE_NEW_LINK] = 0;
		$this->tableControlDefaults[OBJECT_HIDE_VIEW_LINK] = 0;
		$this->tableControlDefaults[OBJECT_HIDE_DETAILS_LINK] = 0;
		$this->tableControlDefaults[OBJECT_DELETE_CHECK_CONSTRAINTS] = 0;
		$this->tableControlDefaults[OBJECT_PAGING] = 1;
		$this->tableControlDefaults[OBJECT_ACTIONS] =$this->actionTypes;
		$this->tableControlDefaults[REQUIRED_TEXT] = "*";
		$this->tableControlDefaults[OBJECT_PAGING_NUM_ROWS_PER_PAGE] = 10;
		$this->tableControlDefaults[OBJECT_PAGING_SCROLL] = 5;
		
		$this->tableControlType = array();
		$this->tableControlType[0]['desc'] = "Table Name";
		$this->tableControlType[0]['type'] = "";
		$this->tableControlType[OBJECT_DESC]['desc'] = "Table Desc.";
		$this->tableControlType[OBJECT_DESC]['type'] = "text";
		$this->tableControlType[EDIT_TEXT]['desc'] = "Edit Link Text or Image Src";
		$this->tableControlType[EDIT_TEXT]['type'] = "text";
		$this->tableControlType[DELETE_TEXT]['desc'] = "Delete Link Text or Image Src";
		$this->tableControlType[DELETE_TEXT]['type'] = "text";
		$this->tableControlType[OBJECT_DELETE_CHECK_CONSTRAINTS]['desc'] = "Referential Integrity<br/>On Same Fields?";
		$this->tableControlType[OBJECT_DELETE_CHECK_CONSTRAINTS]['type'] = "checkbox";
		$this->tableControlType[OBJECT_PK]['desc'] = "Primary Key";
		$this->tableControlType[OBJECT_PK]['type'] = "text";
		$this->tableControlType[OBJECT_DEFAULT_ORDER]['desc'] = "Default Order<br/>{FIELDNAME} DESC/ASC";
		$this->tableControlType[OBJECT_DEFAULT_ORDER]['type'] = "text";
		$this->tableControlType[OBJECT_READ_FILTER]['desc'] = "WHERE Clause Filter On Read";
		$this->tableControlType[OBJECT_READ_FILTER]['type'] = "text";
		$this->tableControlType[OBJECT_FILTER_DESC]['desc'] = "Description of Filter";
		$this->tableControlType[OBJECT_FILTER_DESC]['type'] = "text";
		$this->tableControlType[OBJECT_HIDE_NEW_LINK]['desc'] = "Hide \"Create\" Link";
		$this->tableControlType[OBJECT_HIDE_NEW_LINK]['type'] = "checkbox";
		$this->tableControlType[OBJECT_HIDE_VIEW_LINK]['desc'] = "Hide \"View\" Link";
		$this->tableControlType[OBJECT_HIDE_VIEW_LINK]['type'] = "checkbox";
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
		 
		 
		$this->tableControlDefaults[OBJECT_PAGING_NUM_ROWS_PER_PAGE] = 10;
		$this->tableControlDefaults[OBJECT_PAGING_SCROLL] = 5;
		 
		 //uneditable tableControlTypes not avail in the interface for now and are managed in the core class logic
		 // OBJECT_CONNECTION_STRING - automatically set - @todo autoupdate when old server and password change
		 // OBJECT_ACTIONS - based on actions in constructor
		 // OBJECT_TABLE - automatically set
		 // OTHER_OBJECTS -- this could and should be populated manually as an advanced config in your pre_process_load_ function if you want other objects available in the DOM of the crud form.  Dont worry about storing it in the serialized array
		 
		$this->fieldControlDefaults = array();
		$this->fieldControlDefaults[SORTABLE] = 1;
		$this->fieldControlDefaults[REQUIRED] = 0;
		 
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
		$this->fieldMiscTypes['ACCESSKEY']['desc'] = "Access Key";
		$this->fieldMiscTypes['ACCESSKEY']['testdata'] = "t";
		$this->fieldMiscTypes['ACCESSKEY']['type'] = "text";
		$this->fieldMiscTypes['ExtraAttributes']['desc'] = "Extra Attributes";
		$this->fieldMiscTypes['ExtraAttributes']['testdata'] = "";
		$this->fieldMiscTypes['ExtraAttributes']['type'] = "text";
					
	}
	
	function paint($currentTable) {
		
		if (isset($_GET['group'])) {
			if (!in_array($currentTable,$this->currentAdminDB['crud']['groups'][$_GET['group']])) {
				return;
			}
		}
			
		unset($_GET['msg']);
		$crudTableControl =$this->currentAdminDB[CRUD_FIELD_CONFIG];
		
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
				if (isset($crudTableControl[$currentTable])) {
					$crudObject = new cruddyMysql($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_CONNECTION_STRING],$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_TABLE],$crudTableControl[$currentTable]);
				} else {
					die("'$currentTable' is not a valid CRUD table config");
				}
				// -- object_name can be used to describe your table
				$crudObject->object_name = $crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_DESC];
				$crudObject->cruddyAdministrator =$this->cruddyAdministrator;
				$crudObject->object_key = $currentTable;
		
				$viewUrl = (!isset($_GET['action'])) ? 'action='.$crudActions['read'].'' : '';
				$viewText = (!isset($_GET['action'])) ? 'View' : 'Back To All';
				$amp = (stristr($_SERVER['PHP_SELF'],"?")) ? '&' : '?';
				$newLink = '';
				if (isset($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_FILTER_DESC])) {
					$desc = "<h4 style='color:#333'>".$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_FILTER_DESC]."</h4>";
				}
				if ($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_NEW_LINK] == 0 || $crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_NEW_LINK] == 0) {
					
					// -- custom logic for each object on how it draws its links can go here by utilizing case statements of $currentTable
					$newLink = "<a href='?action=".$crudActions['new']."'>Add new $crudObject->object_name</a> | ";
					$break = "<br/>";
				}
				if (!isset($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_VIEW_LINK]) || $crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_HIDE_VIEW_LINK] == 0 ) {
					$viewLink = "<a href='?".$viewUrl."'>$viewText</a>";
				}
				echo "
				<h2 style='color:#E63C1E;'>$crudObject->object_name Administration</h2>
				$desc
				$newLink$viewLink$break
				";
		
		
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
							if ( $crudObject->create() ) {
								eval("
								if (function_exists('new_post_process_".$currentTable."')) {
									\$retPost = new_post_process_".$currentTable."();
								} else {
									\$retPost = true;
								}");
								if ($retPost === true) {
									$msg = "A new ".$crudObject->object_name." was added";
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
						$orderBy = (!empty($_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['order_field']])) ? ' ORDER BY ' . $_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['order_field']] . ' ' . $_GET[$crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_ACTIONS]['order_direction']] : '';
						$crudObject->read($crudTableControl[$currentTable][TABLE_CONFIG][OBJECT_READ_FILTER].$orderBy);
						break;
				}
				
				// -- handle URL redirects
				if ($url) {
					if (!headers_sent()) {
						header ("Location: ".$url);
					} else {
						echo "<script type='text/javascript'>document.location='".$url."';</script>";
					}
				}
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
	
	function paintGroups() {
		if ($this->currentAdminDB['crud']['group_tables'] == 1 && count($_GET) == 0) {
			foreach ($this->currentAdminDB['crud']['groups'] as $k=>$v) {
				// -- show groups listing for user selection
				if (!in_array($k,$this->currentAdminDB['crud']['roles'][$_COOKIE['current_role']]['groups'])) {
					continue;
				}
				echo "<a href=\"?group=$k\"><div class=\"groupBox\">View Records:<br/><strong>$k</strong><img style=\"margin-left:15px;\" src=\"".PUBLIC_CRUD_CLASS_LOCATION."images/database.png\"/></div></a>";
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
		$conn = @mysql_connect($this->currentAdminDB['crud']['mysql_server_names'][$hash].":".$this->currentAdminDB['crud']['mysql_ports'][$hash],$this->currentAdminDB['crud']['mysql_user_names'][$hash],$this->currentAdminDB['crud']['mysql_passwords'][$hash]);
		if (!empty($database)) {
			@mysql_select_db($database);
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
			var url = \"index.php?\";
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
							 <td><input type='text' class='admin' id='password' value=''/></td>
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
				setcookie("current_user", $k, time()+3600*24*7);
				setcookie("current_role", $v['role'], time()+3600*24*7);
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
		echo$this->displayGenericObjects();
		
		if ($_GET['edit']) {
			$defaultPort =$this->currentAdminDB['crud']['mysql_ports'][$_GET['edit']];
			$defaultServer =$this->currentAdminDB['crud']['mysql_server_names'][$_GET['edit']];
			$defaultUserName =$this->currentAdminDB['crud']['mysql_user_names'][$_GET['edit']];      
			$defaultPassword =$this->currentAdminDB['crud']['mysql_passwords'][$_GET['edit']];        
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
				 Step 1: Server Connections
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
							 <td><a class='button' onclick='storeConnectionInfo(1)'><span>Add Another Server</span></a></td>
							 <td><a class='button' onclick='storeConnectionInfo(0)'><span>Store Connection Info And Proceed</span></a></td>
						</tr>
				 </table>
			</div>
		";
	}
			
	function storeDatabaseConnectionForm() {
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
			$this->writeAdminDB();
		}
		exit;
	}
	 
	 
	#2 Step
	function displayDatabaseSelectionForm() {
		foreach ($this->currentAdminDB['crud']['mysql_server_names'] as $mySQLServerHash=>$mySQLServer) {
			$conn =$this->connectDatabase($mySQLServerHash);
			$resultrows =$this->queryDatabase(GET_DATABASES_SQL,$conn);
			foreach ($resultrows as $key=>$value) {
				$selected = "";
				if (in_array($value['Database'],$this->currentAdminDB['crud']['mysql_databases'][$mySQLServer.":".$this->currentAdminDB['crud']['mysql_ports'][$mySQLServerHash]])) {
					 $selected = "selected";
				}
				if ($value['Database'] == 'information_schema' || $value['Database'] == 'mysql') { continue; }
				$options .= "<option value='$mySQLServer".":".$this->currentAdminDB['crud']['mysql_ports'][$mySQLServerHash]."' $selected>".$value['Database']."</option>";
			}
		}
		if ($_GET['edit']) {
			$additionalText = "(All Are Selected CTRL+CLICK to deselect)<br/>";
		}
		echo$this->displayGenericObjects();
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
			</div>
			";
			$this->closeDatabase($conn);
	}
			
	function storeDatabaseSelectionForm() {
		ob_end_clean();
		unset($_GET['admin'],$_GET['select_database'],$_GET['store_database'],$this->currentAdminDB['crud']['mysql_databases']);
		if (is_array($_GET)) {
			$i=0;
			foreach ($_GET as $key=>$value) {
				$this->currentAdminDB['crud']['mysql_databases'][$value][$key] = $key;
				$i++;
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
	function displayTableSelectionForm() {
		foreach ($this->currentAdminDB['crud']['mysql_server_names'] as $mySQLServerHash=>$mySQLServer) {
			$tableControlFlagDisplay = false;
			foreach ($this->currentAdminDB['crud']['mysql_databases'][$mySQLServerHash] as $database) {
				if (isset($_GET['edit']) && $database != $_GET['edit']) {
					continue;
				}
				$failure = false;
				$conn =$this->connectDatabase($mySQLServerHash,$database);
				$resultrows =$this->queryDatabase(GET_TABLES_SQL,$conn);
				if (empty($resultrows)) {
					$resultrows =$this->queryDatabase(GET_TABLES_SQL." from $database",$conn);
					if (empty($resultrows)) {
						$resultrows =$this->queryDatabase("SHOW TABLES FROM $database",$conn);
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
							$options .= "<td>Table Name</td>";
						}
						foreach ($this->tableControlType as $type=>$text) {
							if (!isset($_GET['edit']) && $type == OBJECT_PK) {
								// -- dont let user try and edit PK.  these will be set on next page
								continue;
							}
							$options .= "<td>".$text['desc']."</td>";
						}
						$options .= "</tr>
										 <tr>
											<td style=\"font-size:1.5em;\" colspan=\"20\">Tables in $mySQLServerHash</td>
										 </tr>
										 <tr>
											<td style=\"font-size:1.2em;\" colspan=\"20\">Database:$database</td>
										 </tr>
										 ";
					} else {
						$options .= "</tr>
										 <tr>
											<td style=\"font-size:1.2em;\" colspan=\"20\">Database:$database</td>
										 </tr>";
					}
					
					$options .= "
					<tr>
						<td>Uncheck All</td>
					";
					foreach ($this->tableControlType as $key=>$text) {
						if (empty($text["type"]) ) { continue;}
						if (!isset($_GET['edit']) && $key == OBJECT_PK) {
							continue;
						}	
						$rowOutPut = '';
						$checked2 = '';
						$value =$this->tableControlDefaults[$key];
						if ($text['type'] == 'checkbox' && ($value === true || $value == '1')) {
							$checked2 = 'Off';
						} else if ($text['type'] == 'checkbox' && $value === false)  {
							$checked2 = 'On';
						} else if ($text['type'] == 'checkbox') {
							$checked2 = 'On';
						}
							
						if ($checked2 != '') {
							$rowOutPut .= 'Turn all '.$checked2;
						} else {
							$rowOutPut .= 'Replace All Text';
						}
														
						$options .= "<td>".$rowOutPut."</td>";
					}
					$option .= "   
					</tr>   
					";
					
					
					foreach ($resultrows as $key=>$value) {
						$selected = "";
						$tableName = $value['Tables_in_'.$database];
						$resultPK =$this->queryDatabase(
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
							$resultPK2 =$this->queryDatabase(sprintf(GET_COLUMNS_SQL,$tableName),$conn);
							if (is_array($resultPK2)) {
								foreach ($resultPK2 as $key=>$row) {
									if ($row['Key'] == 'PRI') {
										$resultPK[0]['column_name'] = $row['Field'];
									}
								}
							}
						}
						if (isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$database."_".$tableName])) {
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
						if (empty($resultPK[0]['column_name'])) {
							$errorsCount++;
							$errors  .= "<div class=\"error\">\"$mySQLServer.$database.$tableName\" has no  unique primary key!  Please define and refresh this page.  You cannot use this table in the CRUD system. (ALTER TABLE `$tableName` ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;)</div>";
						} else {
							$additionalHTML = " style='cursor:pointer;' onclick='toggleObj(\"tables[$mySQLServerHash][$database][$tableName][use]\");$extraJS'><input value='$tableName' type='checkbox' name='tables[$mySQLServerHash][$database][$tableName][use]' id='tables[$mySQLServerHash][$database][$tableName][use]' $selected >";
							$options .= "
							<tr>
								<td $additionalHTML $tableName<input type=\"hidden\" value=\"".$resultPK[0]['column_name']."\" name=\"tables[$mySQLServerHash][$database][$tableName][PK]\"/></td>
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
								if (!isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$database."_".$tableName][TABLE_CONFIG][$key])) {
									// -- pull from default
									$value =$this->tableControlDefaults[$key];
									if ($key == OBJECT_DESC) {
										$value = str_replace(array('_','-'),array(' ',' '),ucfirst($tableName));
									}
									$value =$this->createDisplayName($value);
								} else {
									$value =$this->currentAdminDB[CRUD_FIELD_CONFIG][$database."_".$tableName][TABLE_CONFIG][$key];
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
									$extra = "style='cursor:pointer;' onclick='toggleObj(\"tableConfig[$mySQLServerHash][$database][$tableName][$key]\")'";
									$extra2 = "style='display:none;'";
								}
									
								$options .= "<td $extra><input $extra2 $extra type=\"$text[type]\" name=\"tableConfig[$mySQLServerHash][$database][$tableName][$key]\" id=\"tableConfig[$mySQLServerHash][$database][$tableName][$key]\" value=\"$value\" $checked/><span id=\"tableConfig[$mySQLServerHash][$database][$tableName][$key][onoff]\" class=\"".strtolower($checked2)."\">$checked2</span></td>";
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
				<div class=\"error\" style=\"cursor:pointer;\" onclick=\"if ($('allerrors').style.display == 'none') { $('allerrors').style.display = 'inline';} else { $('allerrors').style.display = 'none';}\">There were $errorsCount on tables that could not be used because Primary Keys Dont Exist.  Click For more Info.</div>
						<span id=\"allerrors\" style=\"display:none\">
							$errors
					</span>";
		}

		echo 
			"
			<script>
				var uncheckedTable=false;
			</script>
			<form action='$_SERVER[PHP_SELF]?admin=1&select_tables=1&store_database=1' id='tableForm' method='post'>
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
								$(\"tableForm\").submit();
							}
						} else { 
							$(\"tableForm\").submit();
						}'><span>Select These Tables</span></a>
				 </div>
			</form>
			";
		
	}
	
	function storeTableSelectionForm() {
		ob_end_clean();
		if(is_array($_REQUEST['tables'])) {
			$functions = $drawFunctions = "<?php\n";
				foreach ($_REQUEST['tables'] as $server=>$selectedDatabase) {
					foreach ($selectedDatabase as $database=>$tableValues) {
						foreach ($tableValues as $table=>$primaryKey) {
							$tableHash = $database."_".$table;
							foreach ($this->tableControlDefaults as $systemKey=>$text) {
								$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][$systemKey] = $text;
							}
							foreach ($this->tableControlType as $systemKey=>$text) {
								if (empty($text["type"]) ) { continue;}
								if ($_REQUEST['tableConfig'][$server][$database][$table][$systemKey]) {
									$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][$systemKey] = $_REQUEST['tableConfig'][$server][$database][$table][$systemKey];
								} else {
									unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][$systemKey]);	
								}
							}
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_ACTIONS] =$this->tableControlDefaults[OBJECT_ACTIONS];
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_CONNECTION_STRING] = "mysql://".$this->currentAdminDB['crud']['mysql_user_names'][$server].":".$this->currentAdminDB['crud']['mysql_passwords'][$server]."@".$this->currentAdminDB['crud']['mysql_server_names'][$server].":".$this->currentAdminDB['crud']['mysql_ports'][$server]."/".$database;
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_TABLE] = $table;
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_DESC] = $_REQUEST['tableConfig'][$server][$database][$table][OBJECT_DESC];
							$pk = $primaryKey['PK'];
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OBJECT_PK] = $pk;
							$linkEdit = "?action=".$this->actionTypes['update'].$tableHash."&".$pk."=%".$pk."%";
							$linkDelete = "?action=".$this->actionTypes['delete'].$tableHash."&".$pk."=%".$pk."%";
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][EDIT_LINK] = $linkEdit;
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][DELETE_LINK] = $linkDelete;
							$this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash][TABLE_CONFIG][OTHER_LINKS] = "";
								
							// -- add default field behavior now
							$conn =$this->connectDatabase($this->currentAdminDB['crud']['mysql_server_names'][$server].":".$this->currentAdminDB['crud']['mysql_ports'][$server],$database);
							$fieldResults =$this->queryDatabase(sprintf(GET_COLUMNS_SQL,$table),$conn);

							if (is_array($fieldResults)) {
								foreach ($fieldResults as $key=>$row) {
									$fieldCaption =$this->createDisplayName($row['Field']);
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
							if (!isset($primaryKey['use'])) { 
								unset($this->currentAdminDB[CRUD_FIELD_CONFIG][$tableHash]); 
								continue;
							}
						$this->closeDatabase($conn);
						}
					}
				}
				 
				foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG] as $tableHash=>$obj) {
					$drawFunctions .= "\$crudAdmin->paint('$tableHash');\n\n";
					$functions .= "/*\n* ".strtoupper($tableHash)." PRE PROCESSES BEFORE LOADING A TABLE RECORDSET (Primarily used to overwrite parts of the serialized array with \$_SESSION vars and application specific logic)\n*/\n\nfunction pre_process_load_".$this->cleanTableNames($tableHash)."(\$pointer){\n\t//--add your custom logic here such as changing \$pointer[TABLE_CONFIG][OBJECT_READ_FILTER] with a dynamic where clause or \$pointer['fieldname_config']['VALUE'] for overriding values or any attributes possible in the config array \n\treturn \$pointer;\n}\n\n";
					$functions .= "/*\n* ".strtoupper($tableHash)." PRE PROCESSES BEFORE INSERTING A RECORD\n*/\n\nfunction new_pre_process_".$this->cleanTableNames($tableHash)."(){\n\t//--add your custom logic here before inserting a record in $tableHash -- return false if not wanting to add new\n\treturn true;\n}\n\n";
					$functions .= "/*\n* ".strtoupper($tableHash)." POST PROCESSES AFTER INSERTING A RECORD\n*/\n\nfunction new_post_process_".$this->cleanTableNames($tableHash)."(){\n\t//--add your custom logic here after inserting a record in $tableHash\n\treturn true;\n}\n\n";
					$functions .= "/*\n* ".strtoupper($tableHash)." PRE PROCESSES BEFORE UPDATING A RECORD\n*/\n\nfunction update_pre_process_".$this->cleanTableNames($tableHash)."(){\n\t//--add your custom logic here before updating a record in  $tableHash -- return false if not wanting to update the record because of logical checks\n\treturn true;\n}\n\n";
					$functions .= "/*\n* ".strtoupper($tableHash)." POST PROCESSES AFTER UPDATING A RECORD\n*/\n\nfunction update_post_process_".$this->cleanTableNames($tableHash)."(){\n\t//--add your custom logic here after updating a record in $tableHash\n\treturn true;\n}\n\n";
					$functions .= "/*\n* ".strtoupper($tableHash)." PRE PROCESSES BEFORE DELETING A RECORD\n*/\n\nfunction delete_pre_process_".$this->cleanTableNames($tableHash)."(){\n\t//--add your custom logic here before deleing a record in  $tableHash -- return false if not wanting to delete the record based on logic you add\n\treturn true;\n}\n\n";
					$functions .= "/*\n* ".strtoupper($tableHash)." POST PROCESSES AFTER DELETING A RECORD\n*/\n\nfunction delete_post_process_".$this->cleanTableNames($tableHash)."(){\n\t//--add your custom logic here after deleing a record in $tableHash\n\treturn true;\n}\n\n";
				}
				$drawFunctions .= "\$crudAdmin->paintGroups();\n\n";
				$functions .= "\n\n?>";
				$drawFunctions .= "\n\n?>";
				if (!file_exists($this->functionsFile) ||$this->currentAdminDB['crud']['functionsfile_mtime'] == filemtime($this->functionsFile)) {
					// -- no file modifications have been done or file doesnt exist
					$this->writeFile($this->functionsFile,$functions);
					$this->currentAdminDB['crud']['functionsfile_mtime'] = filemtime($this->functionsFile); 
				} else {
					$this->writeFile($this->functionsFile.".new.php",$functions);
				}
			
				$this->writeFile($this->functionsDrawFile,$drawFunctions);
				$this->currentAdminDB['crud']['drawfile_mtime'] = filemtime($this->functionsFile); 
				$this->writeAdminDB();
				if ($_GET['mode'] != 'edit' ) {
					header("Location: ".$_SERVER['PHP_SELF']."?admin=1&select_groups");
				}
			} else {
				echo "No Tables Selected";
			}
			exit;
	}
	 
	function displayFieldsAJAX() {
		ob_end_clean();
		$conn =$this->connectDatabase($_GET['server'],$_GET['database']);
		$fieldRows =$this->queryDatabase(sprintf(GET_COLUMNS_SQL,$_GET['table']),$conn);
		echo "<select name=\"fields[".$_GET['k1']."][<FIELD_TOKEN>]\">";
		foreach ($fieldRows as $fieldKey=>$fieldValue) {
			echo "<option value=\"".$fieldValue['Field']."\">" . $fieldValue['Field'] . "</option>";
		}
		echo "</select>";
		$this->closeDatabase($conn);
		exit;
	}
	 
	#4 Step
	function displayFieldSelectionForm() {
		$conn =$this->connectDatabase($_GET['server'],$_GET['database']);
		//print_r($this->currentAdminDB);die();
		$database = $_GET['database'];
		$options .= "<tr>";
		$options .= "<td>Field Name</td>";
		foreach ($this->fieldConfigType as $type=>$text) {
			$options .= "<td>".$text['desc']."</td>";
		}
		foreach ($this->fieldControlType as $type=>$text) {
			$options .= "<td>".$text['desc']."</td>";
		}
		$options .= "</tr>";
		$fieldRows =$this->queryDatabase(sprintf(GET_COLUMNS_SQL,$_GET['edit']),$conn);
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
				
				$value =$this->currentAdminDB[CRUD_FIELD_CONFIG][$_GET['database']."_".$_GET['edit']][$fieldValue['Field']."_config"][$key];
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
					$extra2 = "style='display:none;'";
				}
				
				if ($text['type'] != 'link') {
					$options .= "<td $extra><input $extra2 $extra type=\"$text[type]\" name=\"fields_config[".$fieldValue['Field']."][$key]\" id=\"fields_config[".$fieldValue['Field']."][$key]\" value=\"$value\" $checked/><span id=\"fields_config[".$fieldValue['Field']."][$key][onoff]\" class=\"".strtolower($checked2)."\">$checked2</span></td>";
				} else {
					$value =$this->currentAdminDB[CRUD_FIELD_CONFIG][$_GET['database']."_".$_GET['edit']][$fieldValue['Field']."_config"];

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
						} elseif ($autoObject['TYPE'] == $key2) {
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
					
					$existingEntriesTop = '<td valign="top">Edit:</td>';
					$existingEntriesBottom = '<td valign="top"></td>';
					if (is_array($value) && sizeof($value) > 0) {
						foreach ($value as $optionKey=>$optionValue) {
							if ($optionKey == 'TYPE') {
								continue;
							}
							if ($this->fieldMiscTypes[$optionKey]) {
								$desc =$this->fieldMiscTypes[$optionKey]['desc'];
								$type =$this->fieldMiscTypes[$optionKey]['type'];
							} elseif ($this->fieldValidationTypes[$optionKey]) {
								$desc =$this->fieldValidationTypes[$optionKey]['desc'];
								$type = "checkbox";
							} elseif ($this->fieldEventTypes[$optionKey]) {
								$desc =$this->fieldEventTypes[$optionKey]['desc'];
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
						$existingEntriesTop = "<-- Select an option to Add New Behaviors";
					}
					$extra = "style='cursor:pointer;' onclick='toggleObj(\"fields_config[".$fieldValue['Field']."][$optionKey]\")'";
					$extra2 = "style='display:none;'";
					$options .= "
							 <td>
								<a onclick=\"addRow('".$fieldValue['Field']."_tr','".$fieldValue['Field']."_span',this);\" style=\"cursor:pointer;\">Configure</a>
								<span id=\"".$fieldValue['Field']."_span_copy\" style=\"display:none;\">
									<h3>".$fieldValue['Field']." config</h3>
									<table style=\"border:none;\">
										<tr>
											<td>Input Type:</td>
											<td> <select  name=\"fields_config[".$fieldValue['Field']."][TYPE]\" id=\"fields_config[".$fieldValue['Field']."][TYPE]\">
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
								</span>
								<span id=\"".$fieldValue['Field']."_span_copy2\" style=\"display:none;\">
									<table style=\"height:179px;border:none;\">
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
								</span>
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
					if (!isset($this->currentAdminDB[CRUD_FIELD_CONFIG][$_GET['database']."_".$_GET['edit']][$fieldValue['Field']][$key])) {
						// -- pull from default
						$value =$this->fieldControlDefaults[$key];
						if ($key == CAPTION) {
							$value = str_replace(array('_','-'),array(' ',' '),ucfirst($tableName));
						}
						$value =$this->createDisplayName($value);
					} else {
						$value =$this->currentAdminDB[CRUD_FIELD_CONFIG][$_GET['database']."_".$_GET['edit']][$fieldValue['Field']][$key];
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
							$resultrows =$this->queryDatabase(GET_TABLES_SQL,$conn);
							if (empty($resultrows)) {
								$resultrows =$this->queryDatabase(GET_TABLES_SQL." from $database",$conn);
								if (empty($resultrows)) {
									$resultrows =$this->queryDatabase("SHOW TABLES FROM $database",$conn);
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
								$fieldRows2 =$this->queryDatabase(sprintf(GET_COLUMNS_SQL,$tableNameSelected),$conn);
								if (is_array($fieldRows2)) {
									$input .= "<select name=\"fields[$fieldValue[Field]][$key]\">";
									foreach ($fieldRows2 as $fieldKey2=>$fieldValue2) {
										$selected = "";
										if ($value == $fieldValue2['Field']) {
											$selected = "selected";
										}
										$input .=  "<option $selected value=\"".$fieldValue2['Field']."\">" . $fieldValue2['Field'] . "</option>";
									}
									$input .=  "</select></span>";
								}
							}
						}
					} else {
						$input = "<input $extra2 $extra type=\"$text[type]\" name=\"fields[".$fieldValue['Field']."][$key]\" id=\"fields[".$fieldValue['Field']."][$key]\" value=\"$value\" $checked/><span id=\"fields[".$fieldValue['Field']."][$key][onoff]\" class=\"".strtolower($checked2)."\">$checked2</span>";	
					}
					$options .= "<td $extra>$input</td>";
				}
				echo "</tr>";
			}
			echo 
			"
			<script>
				var msgDebug = false;
			function insertAfter( referenceNode, newNode )
			{
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
				var span = document.createElement('span');
				span.id = spanid;
				td1.appendChild(span);
				insertAfter(tbody,td1);
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
			<form action='index.php?admin=1&select_fields=1&store_database=1' id='tableForm' method='post'>
				".$this->displayGenericObjects()."
				 <div id='serverinfo'> 
						Field Configuration
						$errors
					<input type=\"hidden\" name=\"tablePointer\" value=\"".$_GET['database']."_".$_GET['edit']."\"/>
						<table style='width:1500px;'>
									$options
						</table>
						<a class='button' onclick='$(\"tableForm\").submit();'><span>Configure Fields</span></a>  (<span style=\"cursor:pointer;\" onclick=\"toggleObj('recurse');\">Sync To Same Name Fields In Other Tables? </span><input style='display: none;' onclick='toggleObj('recurse')' name='recurse' id='recurse' value='1' checked='checked' type='checkbox'><span id='recurse[onoff]' class='on' onclick=\"toggleObj('recurse');\">On</span>)
				 </div>
			</form>
			";
			$this->closeDatabase($conn);
	}
				 
	function storeFieldSelectionForm() {
		ob_end_clean();
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
			header("Location: ".$_SERVER['PHP_SELF']);
			exit;
	}
	

	#5 Step
	function displayGroupSelectionForm() {
		if ($_GET['edit']) {
			$display = "inline";
			$list =$this->currentAdminDB[CRUD_FIELD_CONFIG];
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
			$list =$this->currentAdminDB[CRUD_FIELD_CONFIG];
			foreach ($this->currentAdminDB[CRUD_FIELD_CONFIG] as $key=>$value) {
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
		$genericObjects =$this->displayGenericObjects();
		echo <<<EOD
			Step 4: Table Groups (Organize Things Logically)
			<form action='index.php?admin=1&select_groups=1&store_database=1' name='tableForm' id='tableForm' method='post'>
			$genericObjects
			<table>
			<tbody>
				<tr id="groupedTable">
					<td>
						Table Configs:<br/>
						<select id="GroupMain" name="groupName[Other][]" multiple="multiple" size="10" style="display:$display">
							$variableOptions
						</select>
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
						document.tableForm.submit();
						"><span>Save Groupings</span></a>
						<span style="cursor:pointer;" onclick="toggleObj('showGroups');">Group Tables By Default (Loads Faster)</span><input style='display: none;' onclick='toggleObj('showGroups')' name='showGroups' id='showGroups' value='$defaultGroup' checked='checked' type='checkbox'><span id='showGroups[onoff]' class='on' onclick="toggleObj('showGroups');">$defaultGroupTxt</span>
		</form>
EOD;
	}
			
	function storeGroupSelectionForm() {
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
		header("Location: ".$_SERVER['PHP_SELF']."?admin=1&select_roles");
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
							 <td><img onclick='removeRow(\"1\",\"allroles\");' src='".PUBLIC_CRUD_CLASS_LOCATION."images/delete.png' style='cursor:pointer;'/></td>
							 <td><input type='text' class='admin' name='role[1][role_name]' value='Super Admin'/></td>
							 <td align='center'><input type='checkbox' name='role[1][admin_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[1][delete_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[1][update_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[1][insert_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[1][search_role]' value='1' checked/></td>
							 <td align='center'>".str_replace(array('TOKEN','TOKN2'),array('1',''),$groupOptions)."</td>
						</tr>
						<tr id='2'>
							 <td><img onclick='removeRow(\"2\",\"allroles\");' src='".PUBLIC_CRUD_CLASS_LOCATION."images/delete.png' style='cursor:pointer;'/></td>
							 <td><input type='text' class='admin' name='role[2][role_name]' value='Admin'/></td>
							 <td align='center'><input type='checkbox' name='role[2][admin_role]' value='0'/></td>
							 <td align='center'><input type='checkbox' name='role[2][delete_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[2][update_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[2][insert_role]' value='1' checked/></td>
							 <td align='center'><input type='checkbox' name='role[2][search_role]' value='1' checked/></td>
							 <td align='center'>".str_replace(array('TOKEN','TOKN2'),array('2',''),$groupOptions)."</td>
						</tr>
						<tr id='cloner'>
							 <td><img onclick='removeRow(\"cloner\",\"allroles\");' src='".PUBLIC_CRUD_CLASS_LOCATION."images/delete.png' style='cursor:pointer;'/></td>
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
					 <td><img onclick='removeRow(\"$id0\",\"allroles\");' src='".PUBLIC_CRUD_CLASS_LOCATION."images/delete.png' style='cursor:pointer;'/></td>
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
			echo 
			"
			Step 5: Setup Roles
			<form action='index.php?admin=1&select_roles=1&store_database=1' name='tableForm' id='tableForm' method='post'>
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
					 <button type=\"button\" onclick=\"cloneRow('cloner');$('cloner_name').value='NewRoleName';changeClonerNames();\"><span style=\"font-size:1.4em;padding-bottom:10px;cursor:pointer;\" >Add Another Role</span> <img src=\"".PUBLIC_CRUD_CLASS_LOCATION."images/db_add.png\"/></button>
					 <br/>
				<br/>
					 <a class='button' onclick='$(\"tableForm\").submit();'><span>Create Roles</span></a>
				 </form>
			";
	}
	
	
	function storeRolesSelectionForm() {
		ob_end_clean();
		$this->currentAdminDB['crud']['roles'] = $_POST['role'];      
		$this->writeAdminDB();
		header("Location: ".$_SERVER['PHP_SELF']."?admin=1&select_users");
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
			if (isset($this->currentAdminDB['crud']['roles'] )) {
				die('<script>document.location = "'.$_SERVER['PHP_SELF'].'?admin=1&select_users=1&edit=true";</script>');
			}
			$form = "
						<tr id='cloner'>
							 <td><img onclick='removeRow(\"cloner\",\"allusers\");' src='".PUBLIC_CRUD_CLASS_LOCATION."images/delete.png' style='cursor:pointer;'/></td>
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
								 <td><img onclick='removeRow(\"$id0\",\"allusers\");' src='".PUBLIC_CRUD_CLASS_LOCATION."images/delete.png' style='cursor:pointer;'/></td>
								 <td>               <input $id2 type='text' class='admin' name='user[$roleID][user_name]' value='$roleObject[user_name]'/></td>
								 <td align='center'><input $id3 type='password' class='admin' name='user[$roleID][password]'  value='$roleObject[password]'/></td>
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
					 <button type=\"button\" onclick=\"cloneRow('cloner');changeClonerUserNames();\"><span style=\"font-size:1.4em;padding-bottom:10px;cursor:pointer;\" >Add Another User</span> <img src=\"".PUBLIC_CRUD_CLASS_LOCATION."images/db_add.png\"/></button>
					 <br/>
				<br/>
					 <a class='button' onclick='if (window.confirm(\"Please ensure you have a user setup with the CRUDDY admin role.  Dont forget your passwords!\")){ $(\"tableForm\").submit();}'><span>Create Users</span></a>
				 </form>
			";
	}
	
	
	function storeUserSelectionForm() {
		ob_end_clean();
		$this->currentAdminDB['crud']['users'] = $_POST['user'];      
		$this->writeAdminDB();
		header("Location: ".$_SERVER['PHP_SELF']."?admin=1&select_theme");
		exit;
	}
	 
	#8 Step
	function displayThemeSelectionForm() {
		echo$this->displayGenericObjects();
		
		if ($_GET['edit']) {
			$currentTheme = $_GET['edit'];
		} elseif (isset($this->currentAdminDB['crud']['theme'])) {
			$currentTheme =$this->currentAdminDB['crud']['theme'];
		} else {
			$currentTheme = 'Default Crud';
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
		ob_end_clean();
		$this->currentAdminDB['crud']['theme'] = $_GET['theme'];
		$this->writeAdminDB();
		exit;
	}  
	
	

	
	
	function cleanTableNames($tableName) {
		return str_replace(" ","_",$tableName);
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
	 
	function readAdminDB() {
		$array = file_get_contents($this->adminFile);
		$newArray = unserialize($array);
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

	 
	function writeAdminDB() {
		if (get_magic_quotes_gpc()) {
			$array =$this->processAssociativeArray($this->currentAdminDB,"\$assocArray[\$n] = stripslashes(\$v);");
		}
		$data = serialize($this->currentAdminDB);
		if (!$handle = fopen($this->adminFile, 'w')) {
			$this->handleErrors("Cannot open file ($this->adminFile)");
		}
		if (fwrite($handle, $data) === FALSE) {
			$this->handleErrors("Could not write to file");
		}
		fclose($handle);
	}
	 
	function writeFile($file,$data) {
		if (!$handle = fopen($file, 'w')) {
			$this->handleErrors("Cannot open file ($this->adminFile)");
		}
		if (fwrite($handle, $data) === FALSE) {
			$this->handleErrors("Could not write to file");
		}
		fclose($handle);   
	}
	 
	function handleErrors($message,$level='fatal') {
		echo "<br/>".$message;
		if ($level=='fatal'){exit;}
	}
	 
	function displayGlobalCSS() {
		return "
		.groupBox {
			border:5px ridge #485254;
			float:left;
			height:150px !important;
			margin-right:3px;
			margin-top:5px;
			width:155px !important;
			padding: 20px;
			margin-left:15px;
		}
		
		.invalid { 
			 border:2px solid #ff0000;
			 background-color: #ffcccc; 
		}
		
		#paging_links a { 
			 text-decoration:none; color:#ff3300; 
			 background:#fff; 
			 border:1px solid #e0e0e0; 
			 padding:1px 4px 1px 4px; 
			 margin:2px; 
		}
		
		#paging_links a:hover { 
			 text-decoration:none; 
			 color:#3399ff; 
			 background:#f2f2f2; 
			 border:1px solid #3399ff; 
			 padding:1px 4px 1px 4px; 
			 margin:2px; 
		}
		
		#current_page { 
			 border:1px solid #333; 
			 padding:1px 4px 1px 4px; 
			 margin:2px; 
			 color:#333; 
		}
		
		img {
			 border:0;
		}
		
		.admin {
			 font-size:35px; 
			 color:#FFFFFF; 
			 background-color:grey;
		}
		
		
		.clear { /* generic container (i.e. div) for floating buttons */
				overflow: hidden;
				width: 100%;
		}
		
		.button {
				background: transparent url('".PUBLIC_CRUD_CLASS_LOCATION."images/bg_button_a.gif') no-repeat scroll top right;
				color: #444;
				display: block;
				float: left;
				font: normal 12px arial, sans-serif;
				height: 24px;
				margin-right: 6px;
				padding-right: 18px; /* sliding doors padding */
				text-decoration: none;
				border-bottom:none;
				cursor:pointer;
		}
		
		.button span {
				background: transparent url('".PUBLIC_CRUD_CLASS_LOCATION."images/bg_button_span.gif') no-repeat;
				display: block;
				line-height: 14px;
				padding: 5px 0 5px 18px;
		}
		
		.button:active {
				background-position: bottom right;
				color: #000;
				outline: none; /* hide dotted outline in Firefox */
		}
		
		.button:active span {
				background-position: bottom left;
				padding: 6px 0 4px 18px; /* push text down 1px */
		} 
		
		a {
			 padding-top: 0px; 
			 padding-bottom: 0px;
		}
		
		#results {
			 color:red;
		}
		
		.on {
			 color:green;
		}
		
		.off {
			 color:grey;
		}
		
		.info, .success, .warning, .error, .validation {
				border: 1px solid;
				margin: 10px 0px;
				padding:15px 10px 15px 50px;
				background-repeat: no-repeat;
				background-position: 10px center;
		}
		.info {
				color: #00529B;
				background-color: #BDE5F8;
		}
		.success {
				color: #4F8A10;
				background-color: #DFF2BF;
		}
		.warning {
				color: #9F6000;
				background-color: #FEEFB3;
		}
		.error {
				color: #D8000C;
				background-color: #FFBABA;
		}
			
		
		/*------ Containters ------*/
		.menu,.menu2{
			height:50px;
			margin:10px auto;
			border:solid 1px #313131;
			background:#ffffff;
			overflow:hidden;
		}
		#m-top{
			height:45px;
			
		}
		
		#m-slider{
			height:5px;
			
		}
		/*------ SLIDER ------*/
		#slider1,#slider2{
			width:50px;
			height:5px;
			float:left;
			line-height:0px;
			margin-top:0px;
			display:inline;
			font-size:1px;
			background:#d1d1d1;
			
		}
		/*------ MENU ------*/
		.menu ul,.menu2 ul{
			padding:0px;
			margin:0px;
			list-style:none;
		}
		
		.menu ul li, .menu2 ul li{
			display: block;
			float: left;
			text-align: center;
			padding: 0;
			margin: 0;
		}
		
		.menu ul li a{
			width:99px;
			height:35px;
			margin:0px;
			padding:5px 0px 0px 0px;
			margin:5px 0px 0px 0px;
			display: block;
			text-decoration:none;
			font-size:14px;
			border-left:solid 1px #e1e1e1;
			border-bottom:0px;	
		}
		
		.menu2 ul li a{
			width:61px;
			height:35px;
			margin:0px;
			padding:5px 0px 0px 0px;
			margin:5px 0px 0px 0px;
			display: block;
			text-decoration:none;
			font-size:14px;
			border-left:solid 1px #e1e1e1;
			border-bottom:0px;
		}
		
		.menu ul li a:hover, .menu2 ul li a:hover{
			color:#E63C1E;
		}
		
		#clear {
			clear:both;
		}
		
		";
	 }
	 
	 function displayThemeCSS($returnCSS=true) {

/* 
 * These are all sample table styles lifted from http://icant.co.uk/csstablegallery/index.php
 */


$crudStyles['templates']['Default Crud'] = <<<EOD
<style type="text/css" >
table {
color: #7F7F7F;
font: 0.8em/1.6em "Trebuchet MS",Verdana,sans-serif;
border-collapse: collapse
}


table,caption {
border-right: 1px solid #CCC;
border-left: 1px solid #CCC
}


caption,th,td {
border-left: 0;
padding: 10px
}


caption,thead th,tfoot th,tfoot td {
background-color: #E63C1E;
color: #FFF;
font-weight: bold;
text-transform: uppercase
}


thead th {
background-color: #C30;
color: #FFB3A6;
text-align: center
}

tbody tr.odd {
background-color: #F7F7F7;
color: #666
}

a {
color: #333;
text-decoration: none;
border-bottom: 1px dotted #E63C1E
}


tbody tr:hover {
background-color: #EEE;
color: #333
}


tbody tr:hover a {
background-color: #FFF
}

tbody td+td+td+td a:active,tbody td+td+td+td a:hover,tbody td+td+td+td a:focus,tbody td+td+td+td a:visited {
color: #E63C1E
}
</style>
EOD;



/* 
	 !!roScripts
	 Table Design by Mihalcea Romeo
	 www.roscripts.com
	 ----------------------------------------------- */


$crudStyles['templates']['Blue Gradient'] = <<<EOD

table {
border-collapse:collapse;
background:#EFF4FB url(http://www.roscripts.com/images/teaser.gif) repeat-x;
border-left:1px solid #686868;
border-right:1px solid #686868;
font:0.8em/145% 'Trebuchet MS',helvetica,arial,verdana;
color: #333;
}


td, th {
padding:5px;
}


caption {
padding: 0 0 .5em 0;
text-align: left;
font-size: 1.4em;
font-weight: bold;
text-transform: uppercase;
color: #333;
background: transparent;
}


 a {
color:#950000;
text-decoration:none;
}


a:link {}


table a:visited {
font-weight:normal;
color:#666;
text-decoration: line-through;
}


 a:hover {
border-bottom: 1px dashed #bbb;
}




thead th, tfoot th, tfoot td {
background:#333 url(http://www.roscripts.com/images/llsh.gif) repeat-x;
color:#fff
}


tfoot td {
text-align:right
}


tbody th, tbody td {
border-bottom: dotted 1px #333;
}


tbody th {
white-space: nowrap;
}


tbody th a {
color:#333;
}


.odd {}


tbody tr:hover {
background:#fafafa
}






table {
width: 650px;
border:1px solid #000000;
border-spacing: 0px; }


table a, table, tbody, tfoot, tr, th, td,a {
font-family: Arial, Helvetica, sans-serif;
}


table caption {
font-size: 1.8em;
text-align: left;
text-indent: 100px;
background: url(images/bg_caption.gif) left top;
height: 40px;
color: #FFFFFF;
border:1px solid #000000; }


thead th {
background: url(images/bg_th.gif) left;
height: 21px;
color: #FFFFFF;
font-size: 0.8em;
font-family: Arial;
font-weight: bold;
padding: 0px 7px;
margin: 20px 0px 0px;
text-align: left; }


tbody tr { background: #ffffff; }


tbody tr.odd { background: #f0f0f0; }


tbody th {
background: url(images/arrow_white.gif) left center no-repeat;
background-position: 5px;
padding-left: 40px !important; }


tbody tr.odd th {
background: url(images/arrow_grey.gif) left center no-repeat;
background-position: 5px;
padding-left: 40px !important; }


tbody th, tbody td {
font-size: 0.8em;
line-height: 1.4em;
font-family: Arial, Helvetica, sans-serif;
color: #000000;
padding: 10px 7px;
border-bottom: 1px solid #800000;
text-align: left; }


tbody a {
color: #000000;
font-weight: bold;
text-decoration: none; }


tbody a:hover {
color: #ffffff;
text-decoration: underline; }


tbody tr:hover th {
background: #800000 url(images/arrow_red.gif) left center no-repeat;
background-position: 5px;
color: #ffffff; }


tbody tr.odd:hover th {
background: #000000 url(images/arrow_black.gif) left center no-repeat;
background-position: 5px;
color: #ffffff; }


tbody tr:hover th a, tr.odd:hover th a {
color: #ffffff; }


tbody tr:hover td, tr:hover td a, tr.odd:hover td, tr.odd:hover td a {
background: #800000;
color: #ffffff; }


tbody tr.odd:hover td, tr.odd:hover td a{
background: #000000;
color: #ffffff; }


tfoot th, tfoot td {
background: #ffffff url(images/bg_footer.gif) repeat-x bottom;
font-size: 0.8em;
color: #ffffff;
height: 21px;
}





table {
width: 650px;
border-collapse:collapse;
border:1px solid #FFCA5E;
}
caption {
font: 1.8em/1.8em Arial, Helvetica, sans-serif;
text-align: left;
text-indent: 10px;
background: url(bg_caption.jpg) right top;
height: 45px;
color: #FFAA00;
}
thead th {
background: url(bg_th.jpg) no-repeat right;
height: 47px;
color: #FFFFFF;
font-size: 0.8em;
font-weight: bold;
padding: 0px 7px;
margin: 20px 0px 0px;
text-align: left;
border-right: 1px solid #FCF1D4;
}
tbody tr {
background: url(bg_td1.jpg) repeat-x top;
}
tbody tr.odd {
background: #FFF8E8 url(bg_td2.jpg) repeat-x;
}


tbody th,td {
font-size: 0.8em;
line-height: 1.4em;
font-family: Arial, Helvetica, sans-serif;
color: #777777;
padding: 10px 7px;
border-top: 1px solid #FFCA5E;
border-right: 1px solid #DDDDDD;
text-align: left;
}
a {
color: #777777;
font-weight: bold;
text-decoration: underline;
}
a:hover {
color: #F8A704;
text-decoration: underline;
}
tfoot th {
background: url(bg_total.jpg) repeat-x bottom;
color: #FFFFFF;
height: 30px;
}
tfoot td {
background: url(bg_total.jpg) repeat-x bottom;
color: #FFFFFF;
height: 30px;
}



EOD;


/*
	 !!Title: Casablanca
	 Rate: 8
*/


$crudStyles['templates']['Casablanca'] = <<<EOD



table { border-collapse: collapse; border: 1px solid #839E99; 
background: #f1f8ee; font: .9em/1.2em Georgia, "Times New Roman", Times, serif; color: #033; }
caption { font-size: 1.3em; font-weight: bold; text-align: left; padding: 1em 4px; }
td, th { padding: 3px 3px .75em 3px; line-height: 1.3em; }
th { background: #839E99; color: #fff; font-weight: bold; text-align: left; padding-right: .5em; vertical-align: top; }
thead th { background: #2C5755; text-align: center; }
.odd td { background: #DBE6DD; }
.odd th { background: #6E8D88; }
td a, td a:link { color: #325C91; }
td a:visited { color: #466C8E; }
td a:hover, td a:focus { color: #1E4C94; }
th a, td a:active { color: #fff; }
tfoot th, tfoot td { background: #2C5755; color: #fff; }
th + td { padding-left: .5em; }



EOD;




/*
	 !!Coffee with milk
	 Table design by Roger Johansson, 456 Berea Street
	 www.456bereastreet.com
	 ================================================*/

$crudStyles['templates']['Coffee with milk'] = <<<EOD

table {
border-collapse: collapse;
font-family: "Trebuchet MS", "Lucida Sans Unicode", verdana, lucida, helvetica, sans-serif;
font-size: 0.8em;
margin: 0;
padding: 0;
}
caption {
font-size: 1.4em;
font-stretch: condensed;
font-weight: bold;
padding-bottom: 5px;
text-align: left;
text-transform: uppercase;
}
th, td {
border-bottom: 1px solid #666;
border-top: 1px solid #666;
padding: 0.6em;
vertical-align: 4px;
}
th {
text-align: left;
text-transform: uppercase;
}
thead th, tfoot th, tfoot td {
background-color: #cc9;
font-size: 1.1em;
}
tbody th {
background: url(http://www.clacksweb.org.uk/images/bullet_vacancy.gif) no-repeat 6px 0.8em;
padding-left: 24px;
}
tbody th, td {
background-color:#eee;
}
tbody tr:hover td, tbody tr:hover th {
background-color: #E5E5CB;
}
tr.odd td, tr.odd th {
background-color: #ddd;
}
tbody a {
color: #333;
}
tbody a:visited {
color: #999999;
}
tbody a:hover {
color: #33c;
}
tbody a:active {
color: #33c;
}
tbody td+td+td+td a {
background: url(http://www.clacksweb.org.uk/images/external.gif) no-repeat right 0.4em;
padding-right: 12px;
}
tfoot th {
text-align: right;
}
tfoot th:after {
content: ":";
}



EOD;






/* 
!!Cusco Sky table styles
written by Braulio Soncco http://www.buayacorp.com
*/


$crudStyles['templates']['Cusco Sky'] = <<<EOD

table, th, td {
border: 1px solid #D4E0EE;
border-collapse: collapse;
font-family: "Trebuchet MS", Arial, sans-serif;
color: #555;
}


caption {
font-size: 150%;
font-weight: bold;
margin: 5px;
}


td, th {
padding: 4px;
}


thead th {
text-align: center;
background: #E6EDF5;
color: #4F76A3;
font-size: 100% !important;
}


tbody th {
font-weight: bold;
}


tbody tr { background: #FCFDFE; }


tbody tr.odd { background: #F7F9FC; }


table a:link {
color: #718ABE;
text-decoration: none;
}


table a:visited {
color: #718ABE;
text-decoration: none;
}


table a:hover {
color: #718ABE;
text-decoration: underline !important;
}


tfoot th, tfoot td {
font-size: 85%;
}

EOD;



/* 
	 !!Greyscale
	 Table Design by Scott Boyle, Two Plus Four
	 www.twoplusfour.co.uk
	 ----------------------------------------------- */


$crudStyles['templates']['Grey Scale'] = <<<EOD

table {border-collapse: collapse;
border: 2px solid #000;
font: normal 80%/140% arial, helvetica, sans-serif;
color: #555;
background: #fff;}


td, th {border: 1px dotted #bbb;
padding: .5em;}


caption {padding: 0 0 .5em 0;
text-align: left;
font-size: 1.4em;
font-weight: bold;
text-transform: uppercase;
color: #333;
background: transparent;}



EOD;





/*
	 Minimalist design in blue
*/
$crudStyles['templates']['Minimalist Blue'] = <<<EOD



table {
 font-size: 95%;
 font-family: 'Lucida Grande', Helvetica, verdana, sans-serif;
 background-color:#fff;
 border-collapse: collapse;
 width: 100%;
 line-height: 1.2em;
}
caption {
 font-size: 30px;
 font-weight: bold;
 color: #002084;
 text-align: left;
 padding: 10px 0px;
 margin-bottom: 2px;
 text-transform: capitalize;
}
thead th {
 border-right: 2px solid #fff;
 color:#fff;
 text-align:center;
 padding:2px;
 height:25px;
 background-color: #004080;
}
tfoot {
 color:#002084;
 padding:2px;
 text-transform:uppercase;
 font-size:1.2em; 
 font-weigth: bold;
 margin-top:6px;
 border-top: 6px solid #004080;
 border-bottom: 6px solid #004080;
}
tbody tr {
 background-color:#fff;
 border-bottom: 2px solid #c0c0c0;
}
tbody td {
 color:#002084;
 padding:5px;
 text-align:left;
}
tbody th {
 text-align:left;
 padding: 2px;
}
tbody td a, tbody th a {
 color:#002084;
 text-decoration:underline;
 font-weight:normal; 
}
tbody td a:hover, tbody th a:hover {
 text-decoration:none;
}



EOD;



/*
 * 2005 Christine Kirchmeier http://www.zeta-software.de
*/
$crudStyles['templates']['Innocent'] = <<<EOD

#itsthetable {
background: #fff url(bg_caption.gif) repeat-x;
padding: 0 2em 2em 2em;
}


table {
margin: 1em auto;
font: 95%/130% Tahoma, Arial, Helvetica, sans-serif;
border-spacing: 0;
}


table caption {
background: url(caption_title.jpg) no-repeat 50% 0;
color: #2442b1;
font-size: 130%;
letter-spacing: .1em;
padding: 3.5em 0.2em 2em 0;
text-align: right;
text-transform: uppercase;
}


thead th, tbody th {
background: #2442b1 url(left_corner.gif) no-repeat;
color: #fff;
font-weight: bold;
padding: .2em .7em .2em .8em;
text-align: left;
border-top: 1px solid #fff;
border-right: 1px solid #c6cdd8;
border-bottom: 1px solid #c6cdd8;
border-left: 1px solid #fff;
}


thead th {
font-size: 1em;
}


tbody th {
background: #dee2e9 url(bg_col1.gif) repeat-x;
}


tbody tr.odd th {
background: #e2e2e2;
border-right: 1px solid #ccc;
border-bottom: 1px solid #ccc;
}


tbody th a, tbody th a:link, tbody th a:visited, tbody th a:hover, tbody th a:active {
color: #2442b1;
font-weight: bold;
text-decoration: none;
font-size: 1.1em;
}


tbody th a:hover {
text-decoration: underline;
}


td a:link {
color: #537fc3;
}


tbody th a:visited, td a:visited {
color: #444;
}


th a:hover, td a:hover {
text-decoration: none;
}


td {
background: #f3f8fd url(bg_col2.gif) repeat-x;
color: #203276;
border-top: 1px solid #fff;
border-right: 1px solid #c6cdd8;
border-bottom: 1px solid #c6cdd8;
border-left: 1px solid #fff;
padding: 1.5em 0.5em 1.5em 0.8em;
}


td:hover {
background: #f3f8fd;
}


tr.odd td {
background: #f8f8f8 url(bg_col3.gif) repeat-x;
color: #444;
border-right: 1px solid #ccc;
border-bottom: 1px solid #ccc;
}


tr.odd td:hover {
background: #f8f8f8;
}


tfoot th, tfoot td {
background: #444 !important;
padding: .5em .5em .5em .5em !important;
color: #fff;
}



EOD;


/*
	 !!--------------------------------------------------------------------------------
	 What: "Oranges in the sky" Styles(Table data design)
	 Who: Krasimir Makaveev(krasi [at] makaveev [dot] com)
	 When: 15.09.2005(created)
	 --------------------------------------------------------------------------------
*/
$crudStyles['templates']['Oranges in the sky'] = <<<EOD

table {
font-family: Verdana, Arial, Helvetica, sans-serif;
border-collapse: collapse;
border-left: 1px solid #ccc;
border-top: 1px solid #ccc; 
color: #333;
}


table caption {
font-size: 1.1em;
font-weight: bold;
letter-spacing: -1px;
margin-bottom: 10px;
padding: 5px;
background: #efefef;
border: 1px solid #ccc;
color: #666;
}


table a {
text-decoration: none;
border-bottom: 1px dotted #f60;
color: #f60;
font-weight: bold;
}


table a:hover {
text-decoration: none;
color: #fff;
background: #f60;
}


table tr th a {
color: #369;
border-bottom: 1px dotted #369;
}


table tr th a:hover {
color: #fff;
background: #369;
}


table thead tr th {
text-transform: uppercase;
background: #e2e2e2;
}


table tfoot tr th, table tfoot tr td {
text-transform: uppercase;
color: #000;
font-weight: bold;
}


table tfoot tr th {
width: 20%;
}


table tfoot tr td {
width: 80%;
}


table td, table th {
border-right: 1px solid #ccc;
border-bottom: 1px solid #ccc;
padding: 5px;
line-height: 1.8em;
font-size: 0.8em;
vertical-align: top;
width: 20%;
}


table tr.odd th, table tr.odd td {
background: #efefef;
}



EOD;




/*


 !!=====================================


 * Data Tables and Cascading Style Sheets Gallery *


 *  http://icant.co.uk/csstablegallery/index.php  *


 * Author: Velizar Garvalov at  http://www.vhg-design.com/ *


 =====================================


*/




$crudStyles['templates']['Shades of Blue'] = <<<EOD



table {


width: 100%;


margin:0; 


	padding:0;


font-family: "Trebuchet MS", Trebuchet, Arial, sans-serif; 


color: #1c5d79;







}


table, tr, th, td {


border-collapse: collapse;


}


caption {


margin:0; 


	padding:0;


background: #f3f3f3;


height: 40px;


line-height: 40px;


text-indent: 28px;


font-family: "Trebuchet MS", Trebuchet, Arial, sans-serif; 


font-size: 14px;


font-weight: bold;


color: #555d6d;


text-align: left;


letter-spacing: 3px;


border-top: dashed 1px #c2c2c2;


border-bottom: dashed 1px #c2c2c2;


}






thead {


background-color: #FFFFFF;


border: none;


}


thead tr th {


height: 32px;


line-height: 32px;


text-align: center;


color: #1c5d79;


background-image: url(col_bg.gif);


background-repeat: repeat-x;


border-left:solid 1px #FF9900;


border-right:solid 1px #FF9900; 


border-collapse: collapse;







}






tbody tr {


background: #dfedf3;


font-size: 13px;


}


tbody tr.odd {


background: #F0FFFF;


}


tbody tr:hover, tbody tr.odd:hover {


background: #ffffff;


}


tbody tr th, tbody tr td {


padding: 6px;


border: solid 1px #326e87;


}


tbody tr th {


background: #1c5d79;


font-family: "Trebuchet MS", Trebuchet, Arial, sans-serif; 


font-size: 12px;


padding: 6px;


text-align: center;


font-weight: bold;


color: #FFFFFF;


border-bottom: solid 1px white;


}


tbody tr th:hover {


background: #ffffff;






}




table a {


color: #FF6600;


text-decoration: none;


font-size: 13px;


border-bottom: solid 1px white;


}


table a:hover {


color: #FF9900;


border-bottom: none;


}




tfoot {


background: #f3f3f3;


height: 24px;


line-height: 24px;


font-family: "Trebuchet MS", Trebuchet, Arial, sans-serif; 


font-size: 14px;


font-weight: bold;


color: #555d6d;


text-align: center;


letter-spacing: 3px;


border-top: solid 2px #326e87;


border-bottom: dashed 1px #c2c2c2;


}




tfoot tr th {


border-top: solid 1px #326e87;


}


tfoot tr td {


text-align: right;







}



EOD;






/*
	 !!Theme: Sky is no heaven
	 Author: Michael Schmieding
	 Web site: http://www.slifer.de/
*/


$crudStyles['templates']['Sky is no heaven']= <<<EOD

table a, table, tbody, tr, th, td, table caption {
font-family: Verdana, arial, helvetica, sans-serif;
color:#000;
font-size:12px;
text-transform:capitalize;
}
table, table caption {
}
tbody {
background:#69c;
}
table a {
font-weight:bold;
}
table a:visited {
color:#333;
}
table a:hover {
text-decoration:none;
color:#69c; 
}
table {
border-bottom:4px outset #9cf;
}
table, table caption {
border-left:4px outset #9cf;
border-right:4px outset #9cf;
}
table caption {
border-top:4px outset #9cf;
font-size:20px;
font-weight:bold;
}
tbody tr:hover, th, tfoot, tfoot th {
background:#9cf;
}
tbody tr:hover td, tbody tr:hover th {
border:1px solid;
border-color:#000 #fff #fff #000;
}
th, td {
border:1px solid;
border-color:#fff #000 #000 #fff;
}
td, th, table caption {
padding:5px;
vertical-align:middle;
}
tfoot td, tfoot th, thead th {
font-weight:bold;
white-space:nowrap;
font-size:14px;
}



EOD;


/* 
 * !!smooth taste table styles
 * written by Thomas Opp http://www.yaway.de
*/


$crudStyles['templates']['Smooth Taste'] = <<<EOD

table {
	border-collapse: collapse;
	border: 1px solid #38160C;
	font: normal 11px verdana, arial, helvetica, sans-serif;
	color: #F6ECF0;
	background: #641B35;
	}
caption {
	text-align: left;
	font: normal 11px verdana, arial, helvetica, sans-serif;
	background: transparent;
	}
td, th {
	border: 1px dashed #B85A7C;
	padding: .8em;
	color: #F6ECF0;
	}
thead th, tfoot th {
	font: bold 11px verdana, arial, helvetica, sans-serif;
	border: 1px solid #A85070;;
	text-align: left;
	background: #38160C;
	color: #F6ECF0;
	padding-top:6px;
	}
tbody td a {
	background: transparent;
	text-decoration: none;
	color: #F6ECF0;
	}
tbody td a:hover {
	background: transparent;
	color: #FFFFFF;
	}
tbody th a {
	font: normal 11px verdana, arial, helvetica, sans-serif;
	background: transparent;
	text-decoration: none;
	font-weight:normal;
	color: #F6ECF0;
	}
tbody th a:hover {
	background: transparent;
	color: #FFFFFF;
	}
tbody th, tbody td {
	vertical-align: top;
	text-align: left;
	}
tfoot td {
	border: 1px solid #38160C;
	background: #38160C;
	padding-top:6px;
	}
.odd {
	background: #7B2342;
	}
tbody tr:hover {
	background: #51152A;
	}
tbody tr:hover th,
tbody tr.odd:hover th {
	background: #51152A;
	}

EOD;

		if ($returnCSS===true) {
			if (isset($crudStyles['templates'][$this->currentAdminDB['crud']['theme']])) {
				return $crudStyles['templates'][$this->currentAdminDB['crud']['theme']];
			} else {
				return $crudStyles['templates']['Default Cruddy MySql'];
			}
		} else {
			$selectBox = "<select class='admin' name='theme' id='theme'>";
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
		if ($this->total_pages <=$this->scroll_page) {
			if ($this->total_records <=$this->per_page) {
				$loop_start = 1;
				$loop_finish =$this->total_pages;
			}else{
				$loop_start = 1;
				$loop_finish =$this->total_pages;
			}
		}else{
			if($this->current_page < intval($this->scroll_page / 2) + 1) {
				$loop_start = 1;
				$loop_finish =$this->scroll_page;
			}else{
				$loop_start =$this->current_page - intval($this->scroll_page / 2);
				$loop_finish =$this->current_page + intval($this->scroll_page / 2);
				if ($loop_finish >$this->total_pages) $loop_finish =$this->total_pages;
			}
		}
		for ($i = $loop_start; $i <= $loop_finish; $i++) {
			if ($i ==$this->current_page) {
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