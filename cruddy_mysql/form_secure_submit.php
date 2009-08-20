<?php
/*
 *
 * @(#) $Id: form_secure_submit.php,v 1.2 2007/05/09 01:50:37 mlemos Exp $
 *
 */

class form_secure_submit_class extends form_custom_class
{
	var $server_validate=0;
	var $key='';
	var $validation='';
	var $expiry_time=300;
	var $expired=0;
	var $requirements=array(
		"mcrypt_cfb"=>"the mcrypt extension is not available"
	);

	Function CheckRequirements()
	{
		Reset($this->requirements);
		$end=(GetType($function=Key($this->requirements))!="string");
		for(;!$end;)
		{
			if(!function_exists($function))
				return($this->requirements[$function]);
			Next($this->requirements);
			$end=(GetType($function=Key($this->requirements))!="string");
		}
		return("");
	}

	Function EncryptValidation()
	{
		$encrypt_time=time();
		$iv_size=mcrypt_get_iv_size(MCRYPT_3DES,MCRYPT_MODE_CFB);
		$iv=str_repeat(chr(0),$iv_size);
		$key_size=mcrypt_get_key_size(MCRYPT_3DES,MCRYPT_MODE_CFB);
		$salt=substr(md5(rand()),0,2);
		$key=$salt.$this->key;
		if(strlen($key)>$key_size)
			$key=substr($key,0,$key_size);
 		return(base64_encode(mcrypt_cfb(MCRYPT_3DES,$key,$encrypt_time,MCRYPT_ENCRYPT,$iv)).':'.$salt.$encrypt_time);
	}

	Function DecryptValidation($encoded)
	{
		if(GetType($colon=strpos($encoded,':'))!='integer'
		|| strlen($encoded)<=$colon+3
		|| ($encrypt_time=intval(substr($encoded,$colon+3)))==0
		|| $encrypt_time>time()
		|| !($encrypted=base64_decode(substr($encoded,0,$colon))))
			return('');
		$iv_size=mcrypt_get_iv_size(MCRYPT_3DES,MCRYPT_MODE_CFB);
		$iv=str_repeat(chr(0),$iv_size);
		$key_size=mcrypt_get_key_size(MCRYPT_3DES,MCRYPT_MODE_CFB);
		$salt=substr($encoded,$colon+1,2);
		$key=$salt.$this->key;
		if(strlen($key)>$key_size)
			$key=substr($key,0,$key_size);
		return(mcrypt_cfb(MCRYPT_3DES,$key,$encrypted,MCRYPT_DECRYPT,$iv));
	}

	Function AddInput(&$form, $arguments)
	{
		if(!IsSet($arguments['Key'])
		|| strlen($arguments['Key'])==0)
			return('it was not specified a valid key');
		$this->key=$arguments['Key'];
		if(IsSet($arguments['ExpiryTime']))
		{
			if(($this->expiry_time=intval($arguments['ExpiryTime']))<=0)
				return('it was not specified a valid expiry time value');
		}
		if(strlen($error=$this->CheckRequirements()))
			return($error);
		$submit_arguments=$arguments;
		$submit_arguments['TYPE']=(IsSet($arguments['SRC']) ? 'image' : 'submit');
		$this->focus_input=$submit_arguments['ID']=$this->GenerateInputID($form, $this->input, 'submit');
		$submit_arguments['NAME']=(IsSet($arguments['NAME']) ? $arguments['NAME'] : $this->focus_input);
		$submit_arguments['IgnoreAnonymousSubmitCheck']=1;
		if(strlen($error=$form->AddInput($submit_arguments)))
			return($error);
		$this->validation=$this->GenerateInputID($form, $this->input, 'validation');
		$arguments=array(
			'NAME'=>$this->validation,
			'ID'=>$this->validation,
			'TYPE'=>'hidden',
			'VALUE'=>''
		);
		return($form->AddInput($arguments));
	}

	Function AddInputPart(&$form)
	{
		if(strlen($error=$form->SetInputValue($this->validation, $this->EncryptValidation()))
		|| strlen($error=$form->AddInputPart($this->validation)))
			return($error);
		return($form->AddInputPart($this->focus_input));
	}

	Function WasSubmitted(&$form, $input)
	{
		$name=$form->WasSubmitted($this->focus_input);
		if(strcmp($name, $this->focus_input)
		|| strcmp(strtoupper($form->METHOD), Getenv('REQUEST_METHOD')))
			return('');
		$encoded=$form->GetSubmittedValue($this->validation);
		$decrypted=$this->DecryptValidation($encoded);
		if(strlen($decrypted)==0)
			return('');
		$remaining_time=intval($decrypted)+$this->expiry_time-time();
		if($remaining_time<0)
		{
			$this->expired=1;
			return('');
		}
		return($this->input);
	}

	Function GetInputProperty(&$form, $property, &$value)
	{
		switch($property)
		{
			case 'Expired':
				$value = $this->expired;
				return('');
		}
		return($this->DefaultGetInputProperty($form, $property, $value));
	}
};

?>