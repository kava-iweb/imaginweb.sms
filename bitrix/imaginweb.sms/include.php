<?php

IncludeModuleLangFile(__FILE__);

use Bitrix\Main\Loader;
if(class_exists('Bitrix\Main\Loader')) {
	Loader::includeModule("sale");
} else {
	CModule::IncludeModule('sale');
}

global $DB;
$db_type = strtolower($DB->type);
CModule::AddAutoloadClasses(
	"imaginweb.sms",
	array(
		"SMSCRubric" => "classes/general/rubric.php",
		"SMSCSubscription" => "classes/".$db_type."/subscription.php",
		"SMSCPosting" => "classes/".$db_type."/posting.php",
		"SMSCPostingTemplate" => "classes/general/template.php",
		"SMSCMailTools" => "classes/general/posting.php"
	)
);


require_once dirname(__FILE__).'/classes/iweb/Sender.php';

Class CImaginwebSms 
{
	function OnBeforeUserRegisterHandler(&$arFields)
	{
		$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
		if(strlen($userField)>0 && $userField !='OFF') $arFields[$userField] = $arFields['PASSWORD'];
	}
	function OnBeforeUserSimpleRegisterHandler(&$arFields)
	{
		$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
		if(strlen($userField)>0 && $userField !='OFF') $arFields[$userField] = $arFields['PASSWORD'];
	}
	function OnBeforeUserUpdateHandler(&$arFields)
	{
		$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
		if(strlen($userField)>0 && $userField !='OFF' && isset($arFields['PASSWORD']) && strlen($arFields['PASSWORD']) >0 ) $arFields[$userField] = $arFields['PASSWORD'];
	}
	function OnBeforeUserAddHandler(&$arFields)
	{
		$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
		if(strlen($userField)>0 && $userField !='OFF' && isset($arFields['PASSWORD']) && strlen($arFields['PASSWORD']) >0 ) $arFields[$userField] = $arFields['PASSWORD'];
	}
	function GetTrackingNumber($my_order_id,$fieldShow = "TRACKING_NUMBER") {
		$my_order_id = intval($my_order_id);
		if($my_order_id <= 0) return '';
		
		if($info = CModule::CreateModuleObject('sale')){
			$testVersion = '16.0.0';
			if(CheckVersion($testVersion, $info->MODULE_VERSION)){
				return '';
			}
			else{
				
			}
		}
		
		$order = \Bitrix\Sale\Order::load($my_order_id);
		
		$shipmentCollection = $order->getShipmentCollection();
		$tmp = '';
		
		$arFields = array();
		foreach ($shipmentCollection as $shipment)
		{
			$tmp= $shipment->getField($fieldShow);
			$id = $shipment->getField("ID");
			$arFields[$id] = $tmp;
			
		}
		ksort($arFields);
		$field = array_pop($arFields);
		
		return $field;
	}
	//после добавления заказа
	function OnSaleComponentOrderOneStepCompleteHandler($id,$arFields)
	{
		$code = COption::GetOptionString('imaginweb.sms', 'property_phone');
		
		$switch ='';// COption::GetOptionString('imaginweb.sms', 'gate');
		if(strlen(trim($code))>0 && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $arFields['ID']);
			
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			

			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;

			$add_phone = COption::GetOptionString('imaginweb.sms', 'add_phone_new'.SITE_ID);
			$message = COption::GetOptionString('imaginweb.sms', 'new_order'.SITE_ID);
			$message2 = COption::GetOptionString('imaginweb.sms', 'new_order_2'.SITE_ID);
		
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}

			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			if(isset($arFields['LID']) && strlen($arFields['LID']) > 0) {
				$sms->Send($phone,$message,array('SITE_ID' => $arFields['LID']));
				$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2,array('SITE_ID' => $arFields['LID'])); };
			} else {
				$sms->Send($phone,$message);
				$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2); };
			}
			
		}
		//call
		$code = COption::GetOptionString('imaginweb.sms', 'call_property_phone');
		
		$switch = COption::GetOptionString('imaginweb.sms', 'call_gate');
		if(strlen(trim($code))>0 && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $arFields['ID']);
			
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;

			$add_phone = COption::GetOptionString('imaginweb.sms', 'call_add_phone_new'.SITE_ID);
			$message = COption::GetOptionString('imaginweb.sms', 'call_new_order'.SITE_ID);
			$message2 = COption::GetOptionString('imaginweb.sms', 'call_new_order_2'.SITE_ID);
		
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field2');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}

			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			$sms->SendCall($phone,$message);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->SendCall(trim($addPhone),$message2); };
		}
		
	}
	function OnSaleComponentOrderCompleteHandler($id,$arFields)
	{
		$code = COption::GetOptionString('imaginweb.sms', 'property_phone');
		$switch ='';// COption::GetOptionString('imaginweb.sms', 'gate');
		
		if(strlen(trim($code))>0 && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $arFields['ID']);
			
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'add_phone_new'.SITE_ID);
			$message = COption::GetOptionString('imaginweb.sms', 'new_order'.SITE_ID);
			$message2 = COption::GetOptionString('imaginweb.sms', 'new_order_2'.SITE_ID);
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			if(isset($arFields['LID']) && strlen($arFields['LID']) > 0) {
				$sms->Send($phone,$message,array('SITE_ID' => $arFields['LID']));
				$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2,array('SITE_ID' => $arFields['LID'])); };
			} else {
				$sms->Send($phone,$message);
				$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2); };
			}
			//$sms->Send($phone,$message);
			//$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2); };
		}
		
		
		$code = COption::GetOptionString('imaginweb.sms', 'call_property_phone');
		$switch = COption::GetOptionString('imaginweb.sms', 'call_gate');
		
		if(strlen(trim($code))>0 && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $arFields['ID']);
			
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'call_add_phone_new'.SITE_ID);
			$message = COption::GetOptionString('imaginweb.sms', 'call_new_order'.SITE_ID);
			$message2 = COption::GetOptionString('imaginweb.sms', 'call_new_order_2'.SITE_ID);
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field2');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			$sms->SendCall($phone,$message);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->SendCall(trim($addPhone),$message2); };
		}
	}
	//после оплаты заказа
	function OnSalePayOrderHandler($id,$val)
	{
		if(isset($GLOBALS['CImaginwebSms']['OnSalePayOrderHandler'][$id]))
			return;
		$GLOBALS['CImaginwebSms']['OnSalePayOrderHandler'][$id] = '';
		
		$code = COption::GetOptionString('imaginweb.sms', 'property_phone');
		$switch ='';// COption::GetOptionString('imaginweb.sms', 'gate');

		if(strlen(trim($code))>0 && $val == 'Y' && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $id);
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}

			$arFields = CSaleOrder::GetByID($id);
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'add_phone_pay'.$arFields['LID']);
			$message = COption::GetOptionString('imaginweb.sms', 'on_pay_order'.$arFields['LID']);
			$message2 = COption::GetOptionString('imaginweb.sms', 'on_pay_order_2'.$arFields['LID']);
			
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);

			$sms = new CIWebSMS;
			$arParams['SITE_ID'] = $arFields['LID'];
			$sms->Send($phone,$message,$arParams);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2); };
		}
		
		//call
		
		$code = COption::GetOptionString('imaginweb.sms', 'call_property_phone');
		$switch = COption::GetOptionString('imaginweb.sms', 'call_gate');

		if(strlen(trim($code))>0 && $val == 'Y' && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $id);
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}

			$arFields = CSaleOrder::GetByID($id);
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'call_add_phone_pay'.$arFields['LID']);
			$message = COption::GetOptionString('imaginweb.sms', 'call_on_pay_order'.$arFields['LID']);
			$message2 = COption::GetOptionString('imaginweb.sms', 'call_on_pay_order_2'.$arFields['LID']);
			
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field2');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);

			$sms = new CIWebSMS;
			$arParams['SITE_ID'] = $arFields['LID'];
			$sms->SendCall($phone,$message,$arParams);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->SendCall(trim($addPhone),$message2); };
		}
		
	}
	//после разрешения на доставку
	function OnSaleDeliveryOrderHandler($id,$val)
	{
		if(isset($GLOBALS['CImaginwebSms']['OnSaleDeliveryOrderHandler'][$id]))
			return;
		$GLOBALS['CImaginwebSms']['OnSaleDeliveryOrderHandler'][$id] = '';
		
		$code = COption::GetOptionString('imaginweb.sms', 'property_phone');
		$switch ='';// COption::GetOptionString('imaginweb.sms', 'gate');
		
		if(strlen(trim($code))>0 && $val == 'Y' && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $id);
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}

			$arFields = CSaleOrder::GetByID($id);
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'add_phone_delivery'.$arFields['LID']);
			$message2 = COption::GetOptionString('imaginweb.sms', 'order_delivery_2'.$arFields['LID']);
			$message = COption::GetOptionString('imaginweb.sms', 'order_delivery'.$arFields['LID']);
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
			
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			$arParams['SITE_ID'] = $arFields['LID'];
			$sms->Send($phone,$message,$arParams);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2); };
		}
		
		#call
		
		$code = COption::GetOptionString('imaginweb.sms', 'call_property_phone');
		$switch = COption::GetOptionString('imaginweb.sms', 'call_gate');
		
		if(strlen(trim($code))>0 && $val == 'Y' && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $id);
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}

			$arFields = CSaleOrder::GetByID($id);
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'call_add_phone_delivery'.$arFields['LID']);
			$message2 = COption::GetOptionString('imaginweb.sms', 'call_order_delivery_2'.$arFields['LID']);
			$message = COption::GetOptionString('imaginweb.sms', 'call_order_delivery'.$arFields['LID']);
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field2');
			
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			$arParams['SITE_ID'] = $arFields['LID'];
			$sms->SendCall($phone,$message,$arParams);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->SendCall(trim($addPhone),$message2); };
		}
	}
	//после отмены заказа
	function OnSaleCancelOrderHandler($id,$val)
	{
		if(isset($GLOBALS['CImaginwebSms']['OnSaleCancelOrderHandler'][$id]))
			return;
		$GLOBALS['CImaginwebSms']['OnSaleCancelOrderHandler'][$id] = '';
		
		$code = COption::GetOptionString('imaginweb.sms', 'property_phone');
		$switch ='';// COption::GetOptionString('imaginweb.sms', 'gate');

		if(strlen(trim($code))>0 && $val == 'Y' && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $id);
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}

			$arFields = CSaleOrder::GetByID($id);
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'add_phone_cancel'.$arFields['LID']);
			$message = COption::GetOptionString('imaginweb.sms', 'order_cancel'.$arFields['LID']);
			$message2 = COption::GetOptionString('imaginweb.sms', 'order_cancel_2'.$arFields['LID']);
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			$arParams['SITE_ID'] = $arFields['LID'];
			$sms->Send($phone,$message,$arParams);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2); };
		}
		#call
		$code = COption::GetOptionString('imaginweb.sms', 'call_property_phone');
		$switch = COption::GetOptionString('imaginweb.sms', 'call_gate');

		if(strlen(trim($code))>0 && $val == 'Y' && $switch != 'OFF') {
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $id);
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}

			$arFields = CSaleOrder::GetByID($id);
			
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$arFields['STATUS_ID'],'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'call_add_phone_cancel'.$arFields['LID']);
			$message = COption::GetOptionString('imaginweb.sms', 'call_order_cancel'.$arFields['LID']);
			$message2 = COption::GetOptionString('imaginweb.sms', 'call_order_cancel_2'.$arFields['LID']);
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field2');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			$arParams['SITE_ID'] = $arFields['LID'];
			$sms->SendCall($phone,$message,$arParams);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->SendCall(trim($addPhone),$message2); };
		}
		
	}
	
	//после смены статуса заказа
	function OnSaleStatusOrderHandler($id,$val)
	{
		if(isset($GLOBALS['CImaginwebSms']['OnSaleStatusOrderHandler'][$id]))
			return;
		$GLOBALS['CImaginwebSms']['OnSaleStatusOrderHandler'][$id] = '';
		
		$code = COption::GetOptionString('imaginweb.sms', 'property_phone');
		$switch ='';// COption::GetOptionString('imaginweb.sms', 'gate');
		if(strlen(trim($code))>0 && $switch != 'OFF') {
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$val,'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $id);
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}

			$arFields = CSaleOrder::GetByID($id);
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'add_phone_status_'.$val.$arFields['LID']);
			$message = COption::GetOptionString('imaginweb.sms', 'status_'.$val.$arFields['LID']);
			$message2 = COption::GetOptionString('imaginweb.sms', 'status_'.$val.'_2'.$arFields['LID']);
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			$arParams['SITE_ID'] = $arFields['LID'];
			$sms->Send($phone,$message,$arParams);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->Send(trim($addPhone),$message2); };
		}
		
		#call
		
		$code = COption::GetOptionString('imaginweb.sms', 'call_property_phone');
		$switch = COption::GetOptionString('imaginweb.sms', 'call_gate');
		if(strlen(trim($code))>0 && $switch != 'OFF') {
			$obStatus = CSaleStatus::GetList(array(),array('ID'=>$val,'LID'=>LANGUAGE_ID));
			$status = '';
			if($arStat = $obStatus->Fetch()) $status = $arStat['NAME'];
			
			$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("TYPE" => array('TEXT','TEXTAREA','STRING','NUMBER')));
			$arReplaces = array();
			while($arProps = $db_props->Fetch()) {if(!in_array($arProps['CODE'],$arReplaces) && strlen($arProps['CODE'])>0) $arReplaces['#PROP_'.$arProps['CODE'].'#'] = $arProps['CODE'];}
			$arFilter = array("ORDER_ID" 	=> $id);
			$db_order = CSaleOrderPropsValue::GetList(array(),$arFilter);
			$phone = '';
			while($arOrder = $db_order->Fetch()) {
				if($arOrder['CODE'] == $code) $phone = $arOrder['VALUE'];
				if(in_array($arOrder['CODE'],$arReplaces)) $arReplaces['#PROP_'.$arOrder['CODE'].'#'] = $arOrder['VALUE'];
			}

			$arFields = CSaleOrder::GetByID($id);
			$delivery = CSaleDelivery::GetByID($arFields['DELIVERY_ID']);
			
			
			
			$arReplacesTemlate = array(
				'#ACCOUNT_NUMBER#'	=> ($arFields['ACCOUNT_NUMBER'])?$arFields['ACCOUNT_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'ACCOUNT_NUMBER'),
				'#ORDER_NUMBER#' 	=> $arFields['ID'],
				'#ORDER_SUMM#'		=> ($arFields['PRICE']-$arFields['PRICE_DELIVERY']),
				'#PRICE_DELIVERY#'	=> $arFields['PRICE_DELIVERY'],
				'#PRICE#'		=> $arFields['PRICE'],
				'#DELIVERY_DOC_NUM#'	=> ($arFields['DELIVERY_DOC_NUM'])?$arFields['DELIVERY_DOC_NUM']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_NUM'),
				'#DELIVERY_DOC_DATE#'	=> ($arFields['DELIVERY_DOC_DATE'])?$arFields['DELIVERY_DOC_DATE']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_DOC_DATE'),
				'#STATUS_NAME#'		=> $status,
				'#DELIVERY_NAME#'	=> !empty($delivery['NAME'])?$delivery['NAME']:CImaginwebSms::GetTrackingNumber($arFields['ID'],'DELIVERY_NAME'),
				'#TRACKING_NUMBER#'	=> ($arFields['TRACKING_NUMBER'])?$arFields['TRACKING_NUMBER']:CImaginwebSms::GetTrackingNumber($arFields['ID']),
                '#ORDER_LINK#' => \Bitrix\Sale\Helpers\Order::getPublicLink(\Bitrix\Sale\Order::load($arFields['ID'])),
			);
			//foreach($arReplacesTemlate as $codeTMP => $valTMP) {
			//	
			//}
			
			foreach($arFields as $k => $v)
				if(!isset($arReplacesTemlate['#'.$k.'#'])) $arReplacesTemlate['#'.$k.'#'] = $v;
			
			$add_phone = COption::GetOptionString('imaginweb.sms', 'call_add_phone_status_'.$val.$arFields['LID']);
			$message = COption::GetOptionString('imaginweb.sms', 'call_status_'.$val.$arFields['LID']);
			$message2 = COption::GetOptionString('imaginweb.sms', 'call_status_'.$val.'_2'.$arFields['LID']);
			
			$userField = COption::GetOptionString('imaginweb.sms', 'user_password_field2');
			if(strlen($userField)>0 && $userField !='OFF') {
				$rsUser = CUser::GetByID($arFields['USER_ID']);
				$arUser = $rsUser->Fetch();
				$arUser['PASSWORD'] = (strlen(trim($arUser[$userField]))>0)?$arUser[$userField]:'';
				$arUserF = array();
				foreach($arUser as $id => $value) {
					$arUserF['#'.$id.'#'] = $value;
				}
				
				$message = str_replace(array_keys($arUserF),$arUserF,$message);
				$message2 = str_replace(array_keys($arUserF),$arUserF,$message2);
			}
			
			$message = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message);
			$message2 = str_replace(array_keys($arReplacesTemlate),$arReplacesTemlate,$message2);
			$message = str_replace(array_keys($arReplaces),$arReplaces,$message);
			$message2 = str_replace(array_keys($arReplaces),$arReplaces,$message2);
			
			$sms = new CIWebSMS;
			$arParams['SITE_ID'] = $arFields['LID'];
			$sms->SendCall($phone,$message,$arParams);
			$arAddPhone = explode(',',$add_phone); foreach($arAddPhone as $addPhone) { $sms->SendCall(trim($addPhone),$message2); };
		}
	}
	function OnBuildGlobalMenu() {
		
	}
	
	
	// обработчики ядра D7
	
	// если оплаченность заказа была изменена
	function OnSaleOrderPaidHandler($ENTITY)
	{
		$parameters = $ENTITY->getParameters();
		
		/** @var Sale\Order $order */
		$order = $parameters['ENTITY'];
		$ID = $order->getField('ID');
		$value = $order->getField('PAYED');
		
		self::OnSalePayOrderHandler($ID, $value);
	}
	
	// если был изменен флаг разрешения отгрузки
	function OnShipmentAllowDeliveryHandler($ENTITY)
	{
		$parameters = $ENTITY->getParameters();

		/** @var Sale\Shipment $shipment */
		$shipment = $parameters['ENTITY'];
		$oldValues = $parameters['VALUES'];
		$allow_delivery = $shipment->getField('ALLOW_DELIVERY');
		$ORDER_ID = $shipment->getField('ORDER_ID');
		$ID = $shipment->getField('ID');
		
		self::OnSaleDeliveryOrderHandler($ORDER_ID, $allow_delivery);
	}
	
	// если сохраняемый заказ был отменен
	function OnSaleOrderCanceledHandler($ENTITY)
	{
		$order = $ENTITY->getParameter('ENTITY');
		$ID = $order->getField('ID');
		$canceled = $order->getField('CANCELED');
		
		self::OnSaleCancelOrderHandler($ID, $canceled);
	}
	
	// если статус заказа был изменен
	function OnSaleStatusOrderChangeHandler($ENTITY)
	{
		$value = $ENTITY->getParameter('VALUE');
		$order = $ENTITY->getParameter('ENTITY');
		$ID = $order->getField('ID');
		
		self::OnSaleStatusOrderHandler($ID, $value);
	}
}
?>