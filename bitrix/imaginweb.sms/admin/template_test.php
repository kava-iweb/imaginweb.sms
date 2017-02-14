<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/imaginweb.sms/include.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/imaginweb.sms/prolog.php");

IncludeModuleLangFile(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight("imaginweb.sms");
if($POST_RIGHT=="D")
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$aTabs = array(
	array("DIV" => "edit1", "TAB" => GetMessage("IMAGINWEB_SMS_rub_test_tab"), "ICON"=>"main_user_edit", "TITLE"=>GetMessage("IMAGINWEB_SMS_rub_test_tab_title")),
);
$tabControl = new CAdminTabControl("tabControl", $aTabs);

$ID = intval($ID);		// Id of the rubric to test
$arError = array();
$message = null;
$bVarsFromForm = false;
$rubric = false;
$arRubric = false;

$arFieldDescriptions = array(
	"ACTIVE" => GetMessage("IMAGINWEB_SMS_rub_ACTIVE"),
	"AUTO" => GetMessage("IMAGINWEB_SMS_rub_AUTO"),
	"BODY_TYPE" => GetMessage("IMAGINWEB_SMS_rub_BODY_TYPE"),
	"CHARSET" => GetMessage("IMAGINWEB_SMS_rub_CHARSET"),
	"DAYS_OF_MONTH" => GetMessage("IMAGINWEB_SMS_rub_DAYS_OF_MONTH"),
	"DAYS_OF_WEEK" => GetMessage("IMAGINWEB_SMS_rub_DAYS_OF_WEEK"),
	"DESCRIPTION" => GetMessage("IMAGINWEB_SMS_rub_DESCRIPTION"),
	"DIRECT_SEND" => GetMessage("IMAGINWEB_SMS_rub_DIRECT_SEND"),
	"END_TIME" => GetMessage("IMAGINWEB_SMS_rub_END_TIME"),
	"FROM_FIELD" => GetMessage("IMAGINWEB_SMS_rub_FROM_FIELD"),
	"ID" => GetMessage("IMAGINWEB_SMS_rub_ID"),
	"LAST_EXECUTED" => GetMessage("IMAGINWEB_SMS_rub_LAST_EXECUTED"),
	"LID" => GetMessage("IMAGINWEB_SMS_rub_LID"),
	"NAME" => GetMessage("IMAGINWEB_SMS_rub_NAME"),
	"SITE_ID" => GetMessage("IMAGINWEB_SMS_rub_SITE_ID"),
	"SORT" => GetMessage("IMAGINWEB_SMS_rub_SORT"),
	"START_TIME" => GetMessage("IMAGINWEB_SMS_rub_START_TIME"),
	"SUBJECT" => GetMessage("IMAGINWEB_SMS_rub_SUBJECT"),
	"TEMPLATE" => GetMessage("IMAGINWEB_SMS_rub_TEMPLATE"),
	"TIMES_OF_DAY" => GetMessage("IMAGINWEB_SMS_rub_TIMES_OF_DAY"),
	"VISIBLE" => GetMessage("IMAGINWEB_SMS_rub_VISIBLE"),
);

if($ID>0)
{
	global $DB;
	$rubric = SMSCRubric::GetByID($ID);
	if($rubric)
		$arRubric = $rubric->Fetch();
	if(!$arRubric)
		$arError[] = array("id"=>"", "text"=>GetMessage("IMAGINWEB_SMS_rub_id_not_found"));
	else
	{
		if($START_TIME=="")
			$START_TIME=$arRubric["LAST_EXECUTED"];
		if($END_TIME=="")
			$END_TIME=ConvertTimeStamp(time()+CTimeZone::GetOffset(), "FULL");
	}
}

if(strlen($Test)>0 && $POST_RIGHT=="W" && check_bitrix_sessid())
{
	if($DB->IsDate($START_TIME, false, false, "FULL")!==true)
		$arError[] = array("id"=>"START_TIME", "text"=>GetMessage("IMAGINWEB_SMS_rub_wrong_stime"));
	if($DB->IsDate($END_TIME, false, false, "FULL")!==true)
		$arError[] = array("id"=>"END_TIME", "text"=>GetMessage("IMAGINWEB_SMS_rub_wrong_etime"));
	$bTest = count($arError) == 0;
}
else
	$bTest = false;

$APPLICATION->SetTitle(GetMessage("IMAGINWEB_SMS_rub_title").$ID);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$aMenu = array(
	array(
		"TEXT"=>GetMessage("IMAGINWEB_SMS_POST_LIST"),
		"TITLE"=>GetMessage("IMAGINWEB_SMS_rub_list"),
		"LINK"=>"rubric_admin.php?lang=".LANG,
		"ICON"=>"btn_list",
	)
);
if($ID>0)
{
	$aMenu[] = array("SEPARATOR"=>"Y");
	$aMenu[] = array(
		"TEXT"=>GetMessage("MAIN_ADD"),
		"TITLE"=>GetMessage("IMAGINWEB_SMS_rubric_mnu_add"),
		"LINK"=>"rubric_edit.php?lang=".LANG,
		"ICON"=>"btn_new",
	);
	$aMenu[] = array(
		"TEXT"=>GetMessage("IMAGINWEB_SMS_POST_EDIT"),
		"TITLE"=>GetMessage("IMAGINWEB_SMS_rubric_mnu_edit"),
		"LINK"=>"rubric_edit.php?ID=".$ID."&lang=".LANG
	);
	$aMenu[] = array(
		"TEXT"=>GetMessage("IMAGINWEB_SMS_POST_DELETE"),
		"TITLE"=>GetMessage("IMAGINWEB_SMS_rubric_mnu_del"),
		"LINK"=>"javascript:if(confirm('".GetMessage("IMAGINWEB_SMS_rubric_mnu_del_conf")."'))window.location='rubric_admin.php?ID=".$ID."&cf=delid&lang=".LANG."&".bitrix_sessid_get()."';",
		"ICON"=>"btn_delete",
	);
}
$context = new CAdminContextMenu($aMenu);
$context->Show();
?>

<?
if(count($arError)>0)
{
	$e = new CAdminException($arError);
	$message = new CAdminMessage(GetMessage("IMAGINWEB_SMS_rub_test_error"), $e);
	echo $message->Show();
}
?>

<?if($arRubric):?>
<form method="POST" Action="<?echo $APPLICATION->GetCurPage()?>" ENCTYPE="multipart/form-data" name="post_form">
<?
$tabControl->Begin();
?>
<?
//********************
//Template test tab
//********************
$tabControl->BeginNextTab();
?>
	<tr>
		<td><?echo GetMessage("IMAGINWEB_SMS_rub_name")?></td>
		<td><input type="hidden" name="ID" value="<?echo $ID;?>"><?=htmlspecialchars($arRubric["NAME"])?></td>
	</tr>
	<?
	$arTemplate = SMSCPostingTemplate::GetByID($arRubric["TEMPLATE"]);
	if($arTemplate):
	?>
	<tr>
		<td><?echo GetMessage("IMAGINWEB_SMS_rub_tmpl_name")?></td>
		<td><?=htmlspecialchars($arTemplate["NAME"])?></td>
	</tr>
	<tr>
		<td><?echo GetMessage("IMAGINWEB_SMS_rub_tmpl_desc")?></td>
		<td><?=htmlspecialchars($arTemplate["DESCRIPTION"])?></td>
	</tr>
	<?endif;?>
	<tr class="heading">
		<td colspan="2"><?echo GetMessage("IMAGINWEB_SMS_rub_times")?></td>
	</tr>
	<tr>
		<td><span class="required">*</span><?echo GetMessage("IMAGINWEB_SMS_rub_stime")." (".FORMAT_DATETIME."):"?></td>
		<td><?echo CalendarDate("START_TIME", htmlspecialchars($START_TIME), "post_form", "20")?></td>
	</tr>
	<tr>
		<td><span class="required">*</span><?echo GetMessage("IMAGINWEB_SMS_rub_etime")." (".FORMAT_DATETIME."):"?></td>
		<td><?echo CalendarDate("END_TIME", htmlspecialchars($END_TIME), "post_form", "20")?></td>
	</tr>
<?
$tabControl->Buttons();
?>
<?echo bitrix_sessid_post();?>
<input type="hidden" name="lang" value="<?echo LANG?>">
<input <?if ($POST_RIGHT<"W") echo "disabled" ?> type="submit" name="Test" value="<?echo GetMessage("IMAGINWEB_SMS_rub_action")?>" title="<?echo GetMessage("IMAGINWEB_SMS_rub_action_title")?>">
<?
$tabControl->End();
?>
</form>

<?
$tabControl->ShowWarnings("post_form", $message);
?>

<?endif;?>

<?
if($bTest):
	$rubrics = SMSCRubric::GetList(array(), array("ID"=>$ID));
	if($arRubric=$rubrics->Fetch()):
		$arRubric["START_TIME"] = $START_TIME;
		$arRubric["END_TIME"] = $END_TIME;
		$arRubric["SITE_ID"] = $arRubric["LID"];
		//Include language file for template.php
		$rsSite = CSite::GetByID($arRubric["SITE_ID"]);
		$arSite = $rsSite->Fetch();
		$strFileName= $_SERVER["DOCUMENT_ROOT"]."/".$arRubric["TEMPLATE"]."/lang/".$arSite["LANGUAGE_ID"]."/template.php";
		if(file_exists($strFileName))
			include($strFileName);
		//Execute template
		$strFileName= $_SERVER["DOCUMENT_ROOT"]."/".$arRubric["TEMPLATE"]."/template.php";
		if(file_exists($strFileName))
		{
			ob_start();
			$arFields = include($strFileName);
			$strBody = ob_get_contents();
			ob_end_clean();
		}
		if(!is_array($arFields))
			$arFields=array();
?>
<script language="JavaScript">
<!--
function hide(id)
{
	document.getElementById("div_show_"+id).style.display = "inline";
	document.getElementById("div_hide_"+id).style.display = "none";
}
function show(id)
{
	document.getElementById("div_show_"+id).style.display = "none";
	document.getElementById("div_hide_"+id).style.display = "inline";
}
//-->
</script>
<p>
<div id="div_show_INPUT" style="display:inline;">
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="list-table">
	<tr class="head" align="center" valign="top">
		<td class="left right">
			<a href="javascript:show('INPUT');" ><?=GetMessage("IMAGINWEB_SMS_rub_input_show")?></a>
		</td>
	</tr>
</table>
</div>
<div id="div_hide_INPUT" style="display:none;">
<table width="100%" border="0" cellpadding="3" cellspacing="1" class="list-table">
	<tr class="head" align="center" valign="top">
		<td colspan="3" class="left right">
			<a href="javascript:hide('INPUT');"><?=GetMessage("IMAGINWEB_SMS_rub_input_hide")?></a>
		</td>
	</tr>
	<?foreach($arRubric as $key=>$value):?>
	<tr>
		<td align="left"  width="20%" class="left"><?echo $arFieldDescriptions[$key]?></td>
		<td align="right" width="10%"><?echo htmlspecialchars($key)?></td>
		<td align="left"  width="70%" class="right"><?echo strlen($value)? htmlspecialchars($value): "&nbsp"?></td>
	</tr>
	<?endforeach?>
</table>
</div>
</p>
<script language="JavaScript">
<!--
hide("INPUT");
//-->
</script>

<p align="center"><b><?=GetMessage("IMAGINWEB_SMS_rub_body")?></b></p>

<?if($arFields["BODY_TYPE"]=="html"):?>
	<?=$strBody?>
<?else:?>
	<pre><?=$strBody?></pre>
<?endif?>

<p>
<div id="div_show_OUTPUT" style="display:inline;">
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="list-table">
	<tr class="head" align="center" valign="top">
		<td class="left right">
			<a href="javascript:show('OUTPUT');" ><?=GetMessage("IMAGINWEB_SMS_rub_output_show")?></a>
		</td>
	</tr>
</table>
</div>
<div id="div_hide_OUTPUT" style="display:none;">
<table width="100%" border="0" cellpadding="3" cellspacing="1" class="list-table">
	<tr class="head" align="center" valign="top">
		<td colspan="3" class="left right">
			<a href="javascript:hide('OUTPUT');"><?=GetMessage("IMAGINWEB_SMS_rub_output_hide")?></a>
		</td>
	</tr>
	<?foreach($arFields as $key=>$value):
		if($key == "FILES" && is_array($value))
			$value = "<pre>".htmlspecialchars(print_r($value, true))."</pre>";
		else
			$value = htmlspecialchars(print_r($value, true));
	?>
	<tr>
		<td align="left"  width="20%" class="left"><?echo $arFieldDescriptions[$key]?></td>
		<td align="right" width="10%"><?echo htmlspecialchars($key)?></td>
		<td align="left"  width="70%" class="right"><?echo strlen($value)? $value: "&nbsp"?></td>
	</tr>
	<?endforeach?>
</table>
</div>
</p>
<script language="JavaScript">
<!--
hide("OUTPUT");
//-->
</script>

<form method="post" action="posting_edit.php" ENCTYPE="multipart/form-data" name="add_form">
<?echo bitrix_sessid_post();?>
<input type="hidden" name="lang" value="<?echo LANG?>">
<input type="hidden" name="RUB_ID[]" value="<?=htmlspecialchars($arRubric["ID"])?>">
<?if(array_key_exists("GROUP_ID", $arFields)):
	if(is_array($arFields["GROUP_ID"]))
	{
		foreach($arFields["GROUP_ID"] as $GROUP_ID)
		{
			?><input type="hidden" name="GROUP_ID[]" value="<?=htmlspecialchars($GROUP_ID)?>"><?
		}
	}
	else
	{
		?><input type="hidden" name="GROUP_ID[]" value="<?=htmlspecialchars($arFields["GROUP_ID"])?>"><?
	}
endif;?>
<?if(array_key_exists("FILES", $arFields) && is_array($arFields["FILES"])):
	foreach($arFields["FILES"] as $i => $arFile)
	{
		$i = htmlspecialchars($i);
		if(is_array($arFile))
		{
			foreach($arFile as $key => $value)
			{
				$key = htmlspecialchars($key);
				$value = htmlspecialchars($value);
				?><input type="hidden" name="FILES[<?echo $i?>][<?echo $key?>]" value="<?echo $value?>"><?
			}
		}
	}
endif;?>
<input type="hidden" name="FROM_FIELD" value="<?=htmlspecialchars($arFields["FROM_FIELD"])?>">
<input type="hidden" name="SUBJECT" value="<?=htmlspecialchars($arFields["SUBJECT"])?>">
<input type="hidden" name="BODY_TYPE" value="<?=htmlspecialchars($arFields["BODY_TYPE"])?>">
<input type="hidden" name="CHARSET" value="<?=htmlspecialchars($arFields["CHARSET"])?>">
<input type="hidden" name="DIRECT_SEND" value="<?=htmlspecialchars($arFields["DIRECT_SEND"])?>">
<input type="hidden" name="BODY" value="<?=htmlspecialchars($strBody)?>">
<input <?if ($POST_RIGHT<"W") echo "disabled" ?> type="submit" name="apply" value="<?=GetMessage("IMAGINWEB_SMS_rub_add_issue")?>" title="<?=GetMessage("IMAGINWEB_SMS_rub_add_issue_act")?>">
</form>
	<?endif?>
<?endif?>

<?echo BeginNote();?>
<span class="required">*</span><?echo GetMessage("REQUIRED_FIELDS")?>
<?echo EndNote();?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");?>