<?php

use Bitrix\Main\Localization\Loc
	, Bitrix\Main\Loader
	, Bitrix\Main\Config\Option;

$module_id = 'local.sync';

use Local\Sync\Common;

Loader::includeModule($module_id);
Loader::includeModule("crm");


Loc::loadMessages($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/options.php");
Loc::loadMessages(__FILE__);

if ($APPLICATION->GetGroupRight($module_id) < "S") {
	$APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}

		$ardealCat = array();
		$dealCatIterator = \Bitrix\Crm\Category\Entity\DealCategoryTable::query()
                // ->addSelect('CATEGORY_ID')	b_crm_deal_category
				// ->setSelect(["*","UF_*"])
				->setSelect(["ID","NAME"])
				->setFilter([
					// 'ID'=>116371,
					// "!$sPropSF" => ""
					// "!$sPropSF"=>''
					])
                ->where("IS_LOCKED", "N")
                // ->setLimit(1)
                ->exec()
                ->fetchAll();
		foreach($dealCatIterator as $siblingsElement){
				$ardealCat[$siblingsElement['ID']] = $siblingsElement['NAME'];
			}
		$enumIDCat = array_keys($ardealCat);

$arDealStatuses['KEY'] = array_keys($arDealStatus);
$arDealStatuses['VALUE'] = array_values($arDealStatus);

$dealDefaultKey = '0';

$lsDealStatus = [];
foreach ($enumIDCat as $valdealCat) {
	$numgID = $valdealCat;
	$sgID = $numgID > 0 ? "DEAL_STAGE_$numgID" : "DEAL_STAGE";
	$arDealStatus = \CCrmStatus::GetStatusList($sgID);

	$lsDealStatus[$numgID]['empty'] = '--';
	foreach ($arDealStatus as $dealKey => $arItem) {
		$lsDealStatus[$numgID][$dealKey] = $arItem;
	}
}

function _cs($str)
{
	return mb_convert_encoding($str, 'utf8', mb_detect_encoding($str));
}

/*
https://saferoute.atlassian.net/wiki/spaces/API/pages/328130    
*/
$arSaferouteStatus = [
	44 => 'Вручен',
	43 => 'Выведен на доставку',
	412 => 'В городе получателя',
	411 => 'В пути',
	32 => 'Отгружен в компанию доставки',
	31 => 'Принят на сортировке',
	13 => 'Готов к отгрузке',
	15 => 'В обработке',
	12 => 'Подтвержден',
	11 => 'Черновик',
];

$listDealsItem = function ($IBLOCK_TYPE = '') {
	$arDealsItem = Bitrix\Crm\UserField\UserFieldManager::getUserFieldEntity(\CCrmOwnerType::Deal)->GetFields();
	$DealsItem = [];
	foreach ($arDealsItem as $arItem) {

		$DealsItem[$arItem["FIELD_NAME"]] = $arItem["EDIT_FORM_LABEL"];
	}
	return $DealsItem;
};


$aTabs[] = Array(
	'DIV' => 'OSNOVNOE',
	'TAB' => Loc::getMessage('LOCAL_SYNC_TAB_SETTINGS'),
	'OPTIONS' => Array(
		array(
			'login_id',
			Loc::getMessage('LOCAL_SYNC_OPTIONS_LOGIN_ID'),
			Loc::getMessage('LOCAL_SYNC_OPTIONS_LOGIN_ID_DEFAULT_VALUE'),
			array(
				'text',
				0
			)
		),
		array(
			'password_token',
			Loc::getMessage('LOCAL_SYNC_OPTIONS_PASSWORD_TOKEN'),
			Loc::getMessage('LOCAL_SYNC_OPTIONS_PASSWORD_TOKEN_DEFAULT_VALUE'),
			array(
				'password',
				0
			)
		),
		array(
			'property_deals_sfID',
			Loc::getMessage('LOCAL_SYNC_DEALS_PROP'),
			Loc::getMessage('LOCAL_SYNC_DEALS_PROP_DEFAULT_VALUE'),
			array(
				'selectbox',
				$listDealsItem()
			)
		)
	),
);

$aTabs[] = array(
	"DIV" => "logscron",
	"TAB" => Loc::getMessage("LOCAL_SYNC_LOG_TAB"),
	"TITLE" => Loc::getMessage("LOCAL_SYNC_LOG_TAB_TITLE"),
	"OPTIONS" => Array(array(
		'cron_every_time',
		Loc::getMessage('LOCAL_SYNC_CRON_RUNTIME'),
		'',//Loc::getMessage('LOCAL_SYNC_OPTIONS_LOGIN_ID_DEFAULT_VALUE'),
		array(
			'text',
			0
		)
	))
);

foreach ($enumIDCat as $numIDCat) {
	$arCategory = Array(
		'DIV' => "CATEGORY$numIDCat",
		'TAB' => $ardealCat[$numIDCat],//Loc::getMessage('LOCAL_SYNC_TAB_SETTINGS'),
		'OPTIONS' => [],
	);
	foreach ($arSaferouteStatus as $keySf => $valSf) {
		$arCategory['OPTIONS'][] = array(
			"property_sf_" . $numIDCat . "_" . $keySf,
			$valSf,
			'',
			array(
				'selectbox',
				$lsDealStatus[$numIDCat]
			)
		);
	}

	$aTabs[] = $arCategory;
}


$aTabs[] = array(
	"DIV" => "rights",
	"TAB" => Loc::getMessage("MAIN_TAB_RIGHTS"),
	"TITLE" => Loc::getMessage("MAIN_TAB_TITLE_RIGHTS"),
	"OPTIONS" => Array()
);


$arFilter = array(
	"NAME" => "\\Local\\Sync\\Common::syncHoockDeals();"
);
$arResult["AGENT_RUN"] = CAgent::GetList(array("SORT" => "ASC"), $arFilter)->Fetch();


#Сохранение

if ($request->isPost() && $request['Apply'] && check_bitrix_sessid()) {

	foreach ($aTabs as $aTab) {
		foreach ($aTab['OPTIONS'] as $arOption) {
			if (!is_array($arOption))
				continue;

			if ($arOption['note'])
				continue;

			$optionName = $arOption[0];

			$optionValue = $request->getPost($optionName);

			Option::set($module_id, $optionName, is_array($optionValue) ? implode(",", $optionValue) : $optionValue);
		}
	}
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);

?>
<? $tabControl->Begin(); ?>
<form method='post'
      action='<? echo $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($request['mid']) ?>&lang=<?= $request['lang'] ?>'
      name='lsettings_comments_settings'>
	<? foreach ($aTabs as $aTab):
		if ($aTab['OPTIONS']):?>
			<? $tabControl->BeginNextTab(); ?>
			<? if ($aTab['DIV'] == 'logscron') { ?>
                <tr>
                    <td width="100%" style="" colspan="2">
                        <a href="/bitrix/admin/agent_list.php?PAGEN_1=1&SIZEN_1=20&lang=ru&set_filter=Y&adm_filter_applied=0&find_type=id&find_module_id=<?= $module_id ?>"
                           target="_blank"
                        >
							<?= GetMessage("LOCAL_SYNC_AGENT_VIEWINLIST"); ?></a>.
						<?= GetMessage("LOCAL_SYNC_AGENT_LOST_TIME"); ?> <?= $arResult["AGENT_RUN"]["LAST_EXEC"]; ?>
						<? echo "<pre>";
						print_r($arResult["AGENT_RUN"]);
						echo "</pre>"; ?>
                        </br> <?= GetMessage("LOCAL_SYNC_AGENT_TIMELASTUPDATE"); ?>

                    </td>
                </tr>

			<? } ?>
			<? __AdmSettingsDrawList($module_id, $aTab['OPTIONS']); ?>
		<? endif;
	endforeach; ?>
	<?
	$tabControl->BeginNextTab();

	require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/admin/group_rights.php");

	$tabControl->Buttons(); ?>
    <input type="submit"
           name="Apply"
           value="<? echo GetMessage('MAIN_SAVE') ?>">
    <input type="reset"
           name="reset"
           value="<? echo GetMessage('MAIN_RESET') ?>">
	<?= bitrix_sessid_post(); ?>
</form>
<? $tabControl->End(); ?>

