<?

namespace Local\Sync;

use Bitrix\Main\Config\Option
	, Bitrix\Main\Loader;

/*
1.0. Отменён	
1.1. Черновик	
1.2. Подтверждён	
1.3. Готов к отгрузке	
1.4. Отгружен отправителем	
1.5. В обработке	
3.1. Принят на сортировке	
3.2. Заказ передан в компанию доставки	
3.3. Передан на возврат	
4.1. Принят компанией доставки	
4.1.1. В пути	
4.1.2. В городе получателя	
4.2. На ПВЗ	
4.3. Выведен на доставку	
4.4. Вручен	
4.6. Перенос даты доставки	
4.6.1. Отказ до вручения	
4.6.2. Отказ при вручении	
4.7. Частично вручен	
5.1. Передан на возврат	
5.2. Возвращен на сортировку	
6.1. Принят на сортировку для возврата	
7.2. Возвращен	
7.4. Частично возвращен	
8.3. Ошибка	
0.1. Неизвестный статус	
*/


class CustomRandom
{
	public $lsDealStatus = [];
	public $arDealsWithState;
	public $propertyByValueOrder;

	function __construct($lsDealStatus = [], $arDealsWithState = [], $propertyByValueOrder = '')
	{
		$this->lsDealStatus = $lsDealStatus;
		$this->arDealsWithState = $arDealsWithState;
		$this->propertyByValueOrder = $propertyByValueOrder;
	}

	function updatefuncByLoop()
	{
		// print_r($this->arDealsWithState);

		foreach ($this->arDealsWithState as $vID) {
			$this->execfunc($vID['ID'], $vID['STAGE_ID']);
		}


	}

	function execfunc($strID = '', $currentStage)
	{
		if (empty($strID) || empty($currentStage)) {
			return;
		}
		/*
			[parameter] => Array
				(
					[$lsDealStatus] => <optional>
					[$searchval] => <optional>
				)


		(
			[num] => 1
			[stage] => Array
				(
					[0] => empty
					[1] => C7:NEW
					[2] => C7:EXECUTING
					[3] => C7:FINAL_INVOICE
					[4] => C7:2
					[5] => C7:3
					[6] => C7:WON
					[7] => C7:LOSE
					[8] => C7:APOLOGY
				)

			[now] => C7:NEW
		)
		*/
		$arfindByKey = function ($lsDealStatus = [], $searchval = '') {
			$ret = [];
			array_walk($lsDealStatus, function ($v, $k) use ($lsDealStatus, &$ret, $searchval) {
				$arks = array_keys($v);
				$searchkey = array_search($searchval, $arks);
				if ($searchkey) {
					$ret = ['num' => $searchkey, 'stage' => $arks, 'now' => $arks[$searchkey]];
				}
			});
			return $ret;

		};
		$arfindByKey = $arfindByKey($this->lsDealStatus, $currentStage);

		$fChangeState = function ($arIncomingState = [], $nowState = '') {

			$_c = count($arIncomingState);
			$_set = rand(1, $_c - 1);
			if (!empty($arIncomingState[$_set])) {
				return $arIncomingState[$_set];
			}
			return $nowState;
		};

		$arUpdate['STAGE_ID'] = $fChangeState($arfindByKey['stage'], $currentStage);

		$obDeal = new \CCrmDeal(false);
		if (!$obDeal->Update($strID, $arUpdate)) {
			// print_r($obDeal->LAST_ERROR);
			// $this->WriteToTrackingService('Ошибка обновления сделки : '.$obDeal->LAST_ERROR);
		} else {
			// print_r($arUpdate);
			// $this->WriteToTrackingService('Cделка обновлена. Поля: '.print_r($arUpdate, true));
		}

	}

}


class Common
{
	public static $propByOffer = '_';

	public function __construct()
	{

	}

	public static function InvoiceSyncCreate($arFields)
	{
		$ID = $arFields["ID"];

		$arOptionVal = self::getOption();

		$login = urlencode($arOptionVal["login_ie"]);
		$password = urlencode($arOptionVal["password_ie"]);

		//if (!$arFields["PR_INVOICE_9"] || !$arFields["PR_INVOICE_10"])
		//   return false;

		$data = array(
			"Number" => $ID,
			"Date" => date('c'),
			"WithNds" => false,
			"SumsWithNds" => false,
			"Comment" => $arFields["USER_DESCRIPTION"],
			"BankAccount" => array(
				"AccountNumber" => $arOptionVal["AccountNumber"],
				"Bank" => array(
					"Bik" => $arOptionVal["BankBik"],
				)
			),
			"Contractor" => array(
				"Name" => ($arFields["PR_INVOICE_11"]) ? $arFields["PR_INVOICE_11"] : $arFields["PR_INVOICE_1"],
				"Inn" => $arFields["PR_INVOICE_9"],
				"Kpp" => $arFields["PR_INVOICE_10"]
			),
			"Items" => array()
		);

		foreach ($arFields["PRODUCT_ROWS"] as $product) {
			$data["Items"][] = array(
				"ProductName" => $product["PRODUCT_NAME"],
				"UnitName" => $product["MEASURE_NAME"],
				"Quantity" => $product["QUANTITY"],
				"Price" => $product["PRICE"],
				"PriceWithoutNds" => $product["PRICE"],
				"Sum" => $product["PRICE"] * $product["QUANTITY"],
				"NdsRate" => 0
			);
		}
		AddMessage2Log("data: " . print_r($data, true), "SyncCreate");
		$data_string = json_encode($data);

		$ch = curl_init('https://service.localhost.ru/API/CreateBill.ashx');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'X-Login: ' . $login,
			'X-Password: ' . $password,
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data_string)
		));

		$result = curl_exec($ch);
		AddMessage2Log("result: " . print_r($result, true), "SyncCreate");
		global $USER_FIELD_MANAGER;
		$USER_FIELD_MANAGER->Update('CRM_INVOICE', $ID, array($arOptionVal["invoice_prop"] => $result));
		return true;
	}

	public static function getOption()
	{

		$define = self::getDefine();

		$option = [
			'AccountNumber' => Option::get($define['ID_MODULE'], "AccountNumber"),
			'BankBik' => Option::get($define['ID_MODULE'], "BankBik"),
			'login_ie' => Option::get($define['ID_MODULE'], "login_ie"),
			'password_ie' => Option::get($define['ID_MODULE'], "password_ie"),
			'invoice_prop' => Option::get($define['ID_MODULE'], "invoice_prop")
		];

		return $option;

	}

	public static function getDefine()
	{
		$arr = [
			'ID_MODULE' => 'local.sync'
		];

		return $arr;
	}

	public static function syncHoockInvoice($status = 1, $rev = 0)
	{// Status 0 - не оплачен, 1 - оплачен, 2 - частично оплачен, 3 - отклонен

		$settingModule = self::getOption();
		$field_name = $settingModule['invoice_prop'];

		if ($field_name && \CModule::IncludeModule('crm')) {

			$syncurl = 'https://service.localhost.ru/API/GetChanges.ashx?fromRevision=' . $rev;
			$arInvoice = array();

			$login = urlencode($settingModule['login_ie']);
			$password = urlencode($settingModule['password_ie']);
			// Отслеживание изменений статусов счетов
			$ch = curl_init($syncurl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'X-Login: ' . $login,
				'X-Password: ' . $password
			));
			curl_exec($ch);

			$result = json_decode(curl_exec($ch), true);
			foreach ($result as $invoice) {
				if ($invoice["Revision"] > $rev) $rev = $invoice["Revision"];
				if ($invoice["Status"] == $status) {
					$arInvoice[] = $invoice["Id"];
				}
			}
			// Ищем соответствующие счета в CRM
			if (count($arInvoice)) {
				AddMessage2Log("Counts: " . count($arInvoice), "AgentSyncCOUNT");
				$res = \CCrmInvoice::GetList(Array("ID" => "DESC"), Array($field_name => $arInvoice, 'CHECK_PERMISSIONS' => 'N', "!STATUS_ID" => "P"), Array("ID"));
				while ($arInv = $res->Fetch()) {
					// меняем статус счета на оплачен
					\CCrmInvoice::SetStatus($arInv["ID"], "P");
					AddMessage2Log("Status: " . $status . "\n" . print_r($arInv, true), "AgentSync");
				}
				AddMessage2Log("Counts: " . count($arInvoice), "AgentSyncCOUNT_REAL");
			}
		}

		return "\\Local\\Sync\\Common::syncHoockInvoice('" . $status . "', '" . $rev . "');";
	}


	public static function syncDealStateTrack()
	{


		$headers[] = 'Content-Type:application/json';
		$headers[] = "Authorization:Bearer ЙtlUVbdmЙфae03SBCXZUwbфЫва26kjak";
		$headers[] = "shop-id:123456";
		$headers = array_unique($headers);

		$url = "https://api.saferoute.ru/v2/tracking?orderId=10121234";
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		$response = json_decode(curl_exec($curl));
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if ($status === 200) {
			$return = json_encode(
				['status' => $status, 'data' => $response]
			);
		} else {
			/*$return=json_encode([
				'status' => $status,
				'code' => isset($response->code),
				$response->code : null,
			]);*/
		}

		print_r($return);

	}

	public static function syncHoockDeals()
	{
		$lsDealStatus = self::getListStatusDealsCategory();
		self::$propByOffer = \Bitrix\Main\Config\Option::get(self::getDefine()['ID_MODULE'], "property_deals_sfID");
		$arrayIDDeals = self::getListDeals(self::$propByOffer);
		$ret = new CustomRandom($lsDealStatus, $arrayIDDeals, self::$propByOffer);
		$ret = $ret->updatefuncByLoop();


	}

	public static function getListStatusDealsCategory()
	{
		$lsDealStatus = [];
		$sSql = "SELECT
		   ID,
		   -- CREATED_DATE,
		   NAME
		   -- IS_LOCKED,
		   -- SORT
		FROM b_crm_deal_category
		  WHERE IS_LOCKED='N'";
		$connection = \Bitrix\Main\Application::getConnection();
		$dealCatIterator = $connection->query($sSql);
		$ardealCat = array();
		while ($siblingsElement = $dealCatIterator->fetch()) {
			$ardealCat[$siblingsElement['ID']] = $siblingsElement['NAME'];
		}

		foreach (array_keys($ardealCat) as $valdealCat) {
			$numgID = $valdealCat;
			$sgID = $numgID > 0 ? "DEAL_STAGE_$numgID" : "DEAL_STAGE";
			$arDealStatus = \CCrmStatus::GetStatusList($sgID);

			$lsDealStatus[$numgID]['empty'] = '--';
			foreach ($arDealStatus as $dealKey => $arItem) {
				$lsDealStatus[$numgID][$dealKey] = $arItem;
			}
		}
		return $lsDealStatus;

	}

	public static function getListDeals($sPropSF)
	{


		$arrayIDDeals = [];
		$obDealList = \CCrmDeal::GetList(
			array()
			, ["!$sPropSF" => ""]
			, $arSelect = ['ID', 'STAGE_ID', 'TITLE', 'COMPANY_ID', 'CONTACT_FULL_NAME', 'OPPORTUNITY', 'CURRENCY_ID', $sPropSF]
		);
		while ($odeal = $obDealList->Fetch()) {
			if (is_numeric($odeal[$sPropSF])) {
				$arrayIDDeals[] = $odeal;
			}

		}

		return $arrayIDDeals;

	}


}


?>