<?php
class form_FCKEditor extends form_custom_class {

	var $InstanceName;
	var $Link;
	var $File;
	var $BasePath ;
	var $VALUE;
	var $WIDTH;
	var $HEIGHT;
	var $ToolbarSet;
	var $Accessible = 1;
	var $PlainText = 0;

	var $valid_marks;
	var $format;
	var $connections=array(
		"ONCOMPLETE"=>array()
	);

	var $Config = array();

	/*
	 * No validation so far
	 */
	var $client_validate = 0;
	var $server_validate = 0;

//-----------------------------------------------------------------------------

	Function AddInput(&$form, $arguments)
	{
		$this->InstanceName = $this->GenerateInputID($form,$this->input,"instance");
		//$this->InstanceName = $this->input;
		$this->focus_input = $this->InstanceName;

		if(IsSet($arguments['PlainText'])
		&& $arguments['PlainText'])
			$this->PlainText = 1;

		if(IsSet($arguments['Accessible'])
		&& !$arguments['Accessible'])
			$this->Accessible = 0;

		$events = array("ONCOMPLETE");
		for($e=0; $e<count($events); $e++)
		{
			$n=$events[$e];
			if(IsSet($arguments[$n]))
				$this->events[$n]=$arguments[$n];
		}

		if(!empty($arguments["HEIGHT"])) {
			if( $this->_ValidateHeightWidth($arguments["HEIGHT"]) ){
				$this->HEIGHT = $arguments["HEIGHT"];
			} else {
				$error = "Invalid HEIGHT value";
			}
		} else {
			$this->HEIGHT = 200;
		}

		if(!empty($arguments["WIDTH"])) {
			if( $this->_ValidateHeightWidth($arguments["WIDTH"]) ){
				$this->WIDTH = $arguments["WIDTH"];
			} else {
				$error = "Invalid WIDTH value";
			}
		} else {
			$this->WIDTH = "100%";
		}

		$this->VALUE = empty($arguments["VALUE"]) ? "" : $arguments["VALUE"];

		if(strlen($error) > 0) return $error;

		if($this->_IsCompatible()){

			if(empty($arguments['BasePath'])) return "BasePath was not defined";
			$this->BasePath   = $arguments['BasePath'];
			$this->File       = IsSet($arguments["FCKSource"]) && $arguments["FCKSource"] ? "fckeditor.original.html" : "fckeditor.html";
			$this->ToolbarSet = !IsSet($arguments["ToolbarSet"]) ? 'Default' : $arguments["ToolbarSet"] ;

			if(!empty($arguments["Skin"])) $this->Config['SkinPath'] = $this->BasePath . "editor/skins/" . $arguments["Skin"] . "/";

			if(!empty($arguments["Config"])) {
				if(!is_array($arguments["Config"])) {
					return "Invalid Config hash";
				}
				foreach ( $arguments["Config"] as $k => $v ) {
					$this->Config[$k] = $v;
				}
			}
			$this->Link = "{$this->BasePath}editor/{$this->File}?InstanceName={$this->InstanceName}&amp;Toolbar={$this->ToolbarSet}";


			$this->valid_marks=array(
				"input"=>array(
					"instance"=>$this->InstanceName,
					"config"=>$this->InstanceName . "___Config"
				)
			);

			$this->_CompileFormat(TRUE);

			//Creo los inputs
			$instance = array(
				"TYPE"  => "hidden",
				"ID"    => $this->InstanceName,
				"NAME"  => $this->InstanceName,
				"VALUE" => $this->VALUE,
				"STYLE" =>"display:none;"
			);
			$config = array(
				"TYPE"  => "hidden",
				"ID"    => $this->InstanceName . "___Config",
				"NAME"  => $this->InstanceName . "___Config",
				"VALUE" => $this->_GetConfigFieldString(),
				"STYLE" => "display:none;"
			);


			if(
				strlen($error = $form->AddInput($instance)) ||
				strlen($error = $form->AddInput($config))
			){
				return ($error);
			}
		}
		else
		{

			$WidthCSS = $this->_getCSSValue($this->WIDTH) ;
			$HeightCSS = $this->_getCSSValue($this->HEIGHT) ;

			$this->valid_marks=array(
				"input"=>array(
					"instance"=>$this->InstanceName
				)
			);

			$this->_CompileFormat(FALSE);

			$instance = array(
				"TYPE"  => "textarea",
				"ID"    => $this->InstanceName,
				"NAME"  => $this->InstanceName,
				"ROWS"  => 4,
				"COLS"  => 40,
				"STYLE" => "width: {$WidthCSS}; height: {$HeightCSS}",
				"VALUE" => $this->VALUE
			);
			if( $error = $form->AddInput($instance) ) return ($error);
		}


		return("");
	}

//-----------------------------------------------------------------------------

function _getCSSValue($attrib){
	if ( strpos( $attrib, '%' ) === false )
		$attribCSS = $attrib . 'px' ;
	else
		$attribCSS = $attrib ;

	return $attribCSS;
}

//-----------------------------------------------------------------------------

	Function _CompileFormat($compatible){
		if(!$this->Accessible){
           $WidthCSS = $this->_getCSSValue($this->WIDTH);
           $HeightCSS  = $this->_getCSSValue($this->HEIGHT);
		}
		$format = "<div ".($this->Accessible?"":"style='width:".$WidthCSS.";height:".$HeightCSS.";'").">";
		if($compatible){
			$format .= "{instance}{config}";
			if(!$this->Accessible) {
				$format .= $this->VALUE;
			} else {
				$format .= "<iframe  id=\"{$this->InstanceName}___Frame\" src=\"{$this->Link}\" width=\"{$this->WIDTH}\" height=\"{$this->HEIGHT}\" frameborder=\"0\" scrolling=\"no\"></iframe>" ;
			}
		} else {
			$format .= "{instance}";
		}
		$format .= "</div>";
		$this->format = $format;
	}

//-----------------------------------------------------------------------------

	Function _GetConfigFieldString()
	{
		$sParams = '' ;
		$bFirst = true ;

		foreach ( $this->Config as $sKey => $sValue )
		{
			if ( $bFirst == false )
				$sParams .= '&' ;
			else
				$bFirst = false ;

			if ( $sValue === true )
				$sParams .= $this->_EncodeConfig( $sKey ) . '=true' ;
			else if ( $sValue === false )
				$sParams .= $this->_EncodeConfig( $sKey ) . '=false' ;
			else
				$sParams .= $this->_EncodeConfig( $sKey ) . '=' . $this->_EncodeConfig( $sValue ) ;
		}

		return $sParams ;
	}

//-----------------------------------------------------------------------------

	Function _EncodeConfig( $valueToEncode )
	{
		$chars = array(
			'&' => '%26',
			'=' => '%3D',
			'"' => '%22' ) ;

		return strtr( $valueToEncode,  $chars ) ;
	}

//-----------------------------------------------------------------------------

	Function GetInputValue(&$form){
		return $this->VALUE;
	}

//-----------------------------------------------------------------------------

	Function _IsCompatible()
	{
		global $HTTP_USER_AGENT ;

		if ( isset( $HTTP_USER_AGENT ) )
			$sAgent = $HTTP_USER_AGENT ;
		else
			$sAgent = $_SERVER['HTTP_USER_AGENT'] ;

		if ( strpos($sAgent, 'MSIE') !== false && strpos($sAgent, 'mac') === false && strpos($sAgent, 'Opera') === false )
		{
			$iVersion = (float)substr($sAgent, strpos($sAgent, 'MSIE') + 5, 3) ;
			return ($iVersion >= 5.5) ;
		}
		else if ( strpos($sAgent, 'Gecko/') !== false )
		{
			$iVersion = (int)substr($sAgent, strpos($sAgent, 'Gecko/') + 6, 8) ;
			return ($iVersion >= 20030210) ;
		}
		else
			return false ;
	}

//-----------------------------------------------------------------------------

	Function _ValidateHeightWidth($property){
		return	( is_integer($property)) || ( is_string($property) && ereg("^[0-9]+%$",$property) );
	}

//-----------------------------------------------------------------------------

	Function SetInputProperty(&$form, $property, $value)
	{
		$changeable_properties = array("Accessible","HEIGHT","VALUE","WIDTH","BasePath","FCKSource","ToolbarSet","AutoDetectLanguage","DefaultLanguage","Skin","PlainText");

		if(!in_array($property,$changeable_properties))
			return($this->DefaultSetInputProperty($form, $property, $value));
		else
		{
			switch ($property) {

				case "VALUE":
					if(strlen($error = $form->SetInputProperty($this->InstanceName,"VALUE",$value)))
						return $error;
					$this->VALUE = $value;
					break;

				case "HEIGHT":
				case "WIDTH":
					if(!$this->_ValidateHeightWidth($value)) return "Invalid ".$property." value";
					$this->$property = $value;
					$this->_CompileFormat($this->_IsCompatible());
					break;

				case "Accessible":
					$this->Accessible = $value;
					$this->_CompileFormat($this->_IsCompatible());
					return($this->DefaultSetInputProperty($form,"Accessible",$value));
					break;

				default:
					return($this->DefaultSetInputProperty($form,$property,$value));

			}
			return ("");
		}
	}

//-----------------------------------------------------------------------------

	Function ClassPageHead(&$form)
	{
		$onload_events = array();
		$class = $this->custom_class;
		$inputs = $form->GetInputs();
		$value = null;
		foreach ($inputs as $i)
		{
			if($error=$form->GetInputProperty($i,"TYPE",$value)) continue;
			if($value == "custom")
			{
				if($error=$form->GetInputProperty($i,"CustomClass",$value)) continue;
				if ($value == $class)
				{
					if($error=$form->GetInputProperty($i,"ONCOMPLETE",$value)) continue;
					$onload_events[$i] = $value;
				}
			}

		}

		$eol = $form->end_of_line;
		$javascript = "<script type=\"text/javascript\">".$eol;
		$javascript .= "//<![CDATA[".$eol;
		$javascript .= "function FCKeditor_OnComplete( editorInstance ) {".$eol;

		foreach ($onload_events as $input=>$action){
			if($error=$form->GetInputProperty($input,"InstanceName",$value)) return $error;
			$javascript .= "if(editorInstance.Name == ".$form->EncodeJavascriptString($value)."){".$eol;
			$javascript .= $action.$eol;
			$javascript .= "}".$eol.$eol;
		}

		$javascript .= "}".$eol;
		$javascript .= "//]]>".$eol;
		$javascript .= "</script>".$eol;

		return ($javascript);
	}

//-----------------------------------------------------------------------------

	Function LoadInputValues(&$form, $submitted)
	{
		$this->VALUE = $this->PlainText ? strip_tags($form->GetInputValue($this->InstanceName)) : $form->GetInputValue($this->InstanceName);
	}

//-----------------------------------------------------------------------------

	Function ValidateInput(&$form){
		//NOT IMPLEMENTED
		return ("");
	}

//-----------------------------------------------------------------------------

	Function GetInputProperty(&$form, $property, &$value) {
		switch ($property) {
			case "CustomClass":
				$value = $this->custom_class;
				break;
			case "ONCOMPLETE":
				$value = $this->events["ONCOMPLETE"];
				break;
			case "InstanceName":
				$value = $this->InstanceName;
				break;
			default:
				return($this->DefaultGetInputProperty($form, $property, $value));
		}
	}
//-----------------------------------------------------------------------------

}

?>