function createCookie(name,value,days) {
	if (days) 
	{
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else 
	{
		var expires = "";
	}
	var theCookie = name+"="+value+expires+"; path=/";
	document.cookie = theCookie;
}

function readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function eraseCookie ( cookie_name )
{
  var cookie_date = new Date ( );  // current date & time
  cookie_date.setTime ( cookie_date.getTime() - 1 );
  document.cookie = cookie_name += "=; expires=" + cookie_date.toGMTString();
}

function countLI(elName){
	if ($(elName)) {
		var ul = $(elName);
		var i=0, c =0;
		while(ul.getElementsByTagName("li")[i++]) {
			c++;
		}
		return c;
	}
}
window.onload=function(){
	if ($("menu1")) {
		var width = countLI("m-top-ul1") * 100;
		$("menu1").style.width = width + "px";
		Event.observe("menu1", "mousemove", function(event){
			coordinateX=Event.pointerX(event)-$("menu1").offsetLeft;
			$("slider1").style.marginLeft=coordinateX-20+"px";
		});
	}
	if ($("menu2")) {
		var width2 = countLI("m-top-ul2")  * 65;
		$("menu2").style.width = width2 + "px";
		Event.observe("menu2", "mousemove", function(event){
			coordinateX=Event.pointerX(event)-$("menu2").offsetLeft;
			$("slider2").style.marginLeft=coordinateX-20+"px";
			if ($("serverList")) {
				$("serverList").style.top =Event.pointerY(event)+"px";
				$("databaseList").style.top =Event.pointerY(event)+"px";
				$("FieldList").style.top =Event.pointerY(event)+"px";
				$("serverList").style.left =Event.pointerX(event)+"px";
				$("databaseList").style.left =Event.pointerX(event)+"px";
				$("FieldList").style.left =Event.pointerX(event)+"px";
			}
		});
		Event.observe("menu2", "click", function(event){
			coordinateX=Event.pointerX(event)-$("menu2").offsetLeft;
			$("slider2").style.marginLeft=coordinateX-20+"px";
			if ($("serverList")) {
				$("serverList").style.top =Event.pointerY(event)+"px";
				$("databaseList").style.top =Event.pointerY(event)+"px";
				$("FieldList").style.top =Event.pointerY(event)+"px";
				$("serverList").style.left =Event.pointerX(event)+"px";
				$("databaseList").style.left =Event.pointerX(event)+"px";
				$("FieldList").style.left =Event.pointerX(event)+"px";
			}
		});
	}
}

function handleEscapeKey(e) {
	var kC  = (window.event) ? event.keyCode : e.keyCode;
	var Esc = (window.event) ? 27 : e.DOM_VK_ESCAPE;
	if(kC==Esc) {
		if ($("serverList")) {
			$("serverList").style.display = "none";
			$("databaseList").style.display = "none";
			$("FieldList").style.display = "none";
		}
	}
}