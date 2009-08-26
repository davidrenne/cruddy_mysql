	function toggleObj(id) {
		if ($(id)) {
		   if($(id).checked==true) {
		      $(id).checked = false;
		      $(id).value = 0;
				if ($(id + "[onoff]")) {
					$(id + "[onoff]").innerHTML = "Off"
					$(id + "[onoff]").className = "off"
				}
		   } else {
		      $(id).checked = true;
		      $(id).value = 1;
				if ($(id + "[onoff]")) {
					$(id + "[onoff]").innerHTML = "On"
					$(id + "[onoff]").className = "on"
				}
		   }
		}
	}
	
	function removeRow(rowID,tableID) { 
	  var row = $(rowID);
	  $(tableID).deleteRow(row.rowIndex);
	}
	
   function storeConnectionInfo(option) {
		var url = location.pathname;
		var params = "?admin=1&initialize_server=1&store_database=1&server=" + $("server").value + "&adminname=" + $("adminname").value + "&username=" + $("username").value + "&password=" + $("password").value + "&port=" + $("port").value;
      new Ajax.Request( url + params,
      {
         method: 'get',
         onSuccess: function(transport) {
          var response = transport.responseText || false;
          if (response != false) {
            $("results").innerHTML = response;
          } else {
          	if (option == 0) {
          		var redir = readCookie('redirect');
          		if (redir == null) {
            		document.location = location.pathname + '?admin=1&select_database';
          		} else {
          			document.location = redir;
          		}
          	} else {
         		document.location = location.pathname + '?admin=true&initialize_server=1&newserver=1';
          	}
          }
         },
         onFailure: function() { alert('An unexpected error occurred.'); }
      });
	}	
	
	function storeDatabaseInfo() {
		var url = location.pathname;
		var params = "?admin=1&select_database=1&store_database=1";
		var selectobject=document.getElementById("database")
		for (var i=0; i<selectobject.length; i++){
			if (selectobject.options[i].selected) {
				params += "&" + selectobject.options[i].text + "=" + selectobject.options[i].value;
			}
		}
      new Ajax.Request( url + params,
      {
         method: 'get',
         onSuccess: function(transport) {
          var response = transport.responseText || false;
          if (response != false) {
            $("results").innerHTML = response;
          } else {
       		var redir = readCookie('redirect');
       		if (redir == null) {
            	document.location = location.pathname + '?admin=1&select_tables';
       		} else {
       			document.location = redir;
       		}
          }
         },
         onFailure: function() { alert('An unexpected error occurred.'); }
      });
	}
	function storeThemeInfo() {
		var url = location.pathname;
		var params = "?admin=1&select_theme=1&store_database=1&theme=" + document.getElementById("theme").value;
      new Ajax.Request( url + params,
      {
         method: 'get',
         onSuccess: function(transport) {
          var response = transport.responseText || false;
          if (response != false) {
            $("results").innerHTML = response;
          } else {
            document.location = location.pathname;
          }
         },
         onFailure: function() { alert('An unexpected error occurred.'); }
      });
	}
	
	function lookupFieldsFromTable(val,p1,p2,p3,p4) {
		var url = location.pathname;
		var params = "?admin=1&find_fields=1&table=" + val + "&server=" + p1 + "&database=" + p2 + "&k1=" + p3 + "&k2=" + p4;
      new Ajax.Request( url + params,
      {
         method: 'get',
         onSuccess: function(transport) {
          if ($('fields[' + p3 + '][id]')) {
			  var d = $('fields[' + p3 + '][id][span]');
			  var olddiv = $('fields[' + p3 + '][id]');
			  d.removeChild(olddiv);
          }
          if ($('fields[' + p3 + '][text]')) {
			  var d = $('fields[' + p3 + '][text][span]');
			  var olddiv = $('fields[' + p3 + '][text]');
			  d.removeChild(olddiv);
          }
          $('fields[' + p3 + '][id][span]').innerHTML = transport.responseText.replace("<FIELD_TOKEN>","id");
          $('fields[' + p3 + '][text][span]').innerHTML = transport.responseText.replace("<FIELD_TOKEN>","text");
         },
         onFailure: function() { alert('An unexpected error occurred.'); }
      });
	}
	
	function getElements(tagname, node)  {
       if(!node) node = document.getElementsByTagName("body")[0];
       var a = [];
       var els = node.getElementsByTagName(tagname);
       for(var i=0,j=els.length; i<j; i++) {
          a.push(els[i]);
       }
       return a;
   }
   
   
	function hasOptions(obj) {
		if (obj!=null && obj.options!=null) { 
			return true; 
		}
		return false;
	}
	
	function cloneRow(cloneID) {
		var root=$(cloneID);
		var clone=$(cloneID).cloneNode(true);
		root.parentNode.insertBefore(clone,root.nextSibling);
	}
	
	function changeClonerNames() {
		var nextRoleID = parseInt($('totalRoles').value) + 1;
		$('totalRoles').value = nextRoleID;
		$('cloner_name').focus();
		$('cloner_name').name = 'role[' + nextRoleID + '][role_name]';
		$('cloner_admin_role').name = 'role[' + nextRoleID + '][admin_role]';
		$('cloner_delete_role').name = 'role[' + nextRoleID + '][delete_role]';
		$('cloner_update_role').name = 'role[' + nextRoleID + '][update_role]';
		$('cloner_insert_role').name = 'role[' + nextRoleID + '][insert_role]';
		$('cloner_search_role').name = 'role[' + nextRoleID + '][search_role]';
		$('cloner_group_roles').name = 'role[' + nextRoleID + '][groups][]';
	}
	

	function changeClonerUserNames() {
		var nextRoleID = parseInt($('totalUsers').value) + 1;
		$('totalUsers').value = nextRoleID;
		$('user_name').focus();
		$('user_name').value = '';
		$('password').value = '';
		$('user_name').name = 'user[' + nextRoleID + '][user_name]';
		$('password').name = 'user[' + nextRoleID + '][password]';
		$('group_roles').name = 'user[' + nextRoleID + '][group_roles]';
	}
	
	function addNewVariableGroup() {
		var response = window.prompt('What is the name of the new table group?','NewGroupName');
		var returnId;
		if ($('groupName[' +response + ']')) {
			alert('You cannot add two groups with the same name');
			return;
		}
		if (response){ 
	        // -- create first TD
			mycurrent_row = $('groupedTable');
	        currentCell = document.createElement("td");
	        currentCell.setAttribute("align","center");
	        currentCell.setAttribute("valign","middle");
	        currenttext = document.createElement("a");
	        currenttext.href = 'javascript:moveSelectedOptions($(\'GroupMain\'),$(\'groupName[' + response + ']\'));';
	        currenttext.appendChild(document.createTextNode(">>"))
	        currentCell.appendChild(currenttext);
	        mycurrent_row.appendChild(currentCell);
	        
	        // -- second TD needed
	        currentCell = document.createElement("td");
	        currentCell.setAttribute("align","center");
	        currentCell.setAttribute("valign","middle");
	        currentSelect = document.createElement("select");
	        currentSelect.setAttribute("id","groupName[" + response + "]");
	        currentSelect.setAttribute("name","groupName[" + response + "][]");
	        currentSelect.setAttribute("multiple","multiple");
	        currentSelect.setAttribute("size","10");
	        currentSelect.setAttribute("title","Double Click to Remove");
	        currentSelect.setAttribute("ondblclick",'moveSelectedOptions($(\'groupName[' + response + ']\'),$(\'GroupMain\'));');
	        currentBR = document.createElement("br");
	        currentCell.appendChild(document.createTextNode(response));
	        currentCell.appendChild(currentBR);
	        currentCell.appendChild(currentSelect);
	        mycurrent_row.appendChild(currentCell);
	        
	        moveSelectedOptions($('GroupMain'),$("groupName[" + response + "]"),1);
		}
	}
	
	function moveSelectedOptions(from,to,warn) {
		// Move them over
		if (!hasOptions(from)) { 
			return;
		}
		var hasParentSelected = false;
		
		for (var i=0; i<from.options.length; i++) {
			var o = from.options[i];
			if (o.selected) {
				hasParentSelected = true;
				if (!hasOptions(to)) { 
					var index = 0; 
				} else { 
					var index=to.options.length; 
				}
				to.options[index] = new Option( o.text, o.value, false, false);
			}
		}
		if (hasParentSelected === false) {
			if (warn === undefined) {
				alert("You need to first select a table from the main list before adding to the group");
			}
		}
		for (var i=(from.options.length-1); i>=0; i--) {
			var o = from.options[i];
			if (o.selected) {
				from.options[i] = null;
			}
		}
		from.selectedIndex = -1;
		to.selectedIndex = -1;
	}