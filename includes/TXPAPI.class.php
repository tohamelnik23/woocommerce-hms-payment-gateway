<?php

define('TXP_WSDL_URL_TEST', 'https://ws.cert.processnow.com/portal/merchantframework/MerchantWebServices-v1?wsdl');
define('TXP_WSDL_URL_LIVE', 'https://ws.processnow.com/portal/merchantframework/MerchantWebServices-v1?wsdl');

class TXPAPI {
	private $mercId;
	private $regKey;
	private $useTestMode;
	private $serviceUrl;
	
	const TRANCODE_AUTH_ONLY       = 0;
	const TRANCODE_AUTH_AND_SETTLE = 1;
	const TRANCODE_SETTLE_ONLY     = 3;
	const TRANCODE_SETTLE_VOID     = 6;

	const TRANFLAGS_NO_REMOVE_AUTH = 0;
	const TRANFLAGS_REMOVE_AUTH    = 1;

	const RESPONSE_SUCCESS = "00";
	const RESPONSE_PARTIAL = "10";

	public function __construct($mercId, $regKey, $useTestMode = true) {
		$this->mercId      = $mercId;
		$this->regKey      = $regKey;
		$this->useTestMode = $useTestMode;
		
		if($this->useTestMode)
			$this->serviceUrl = TXP_WSDL_URL_TEST;
		else
			$this->serviceUrl = TXP_WSDL_URL_LIVE;
	}

	private function _buildMerc($prodType = '5') {
		return array('id' => $this->mercId, 'regKey' => $this->regKey, 'inType' => '1', 'prodType' => $prodType);
	}

	private function _buildCard($cardnumber, $cardexpiry, $cvv) {
		return array('pan' => $cardnumber, 'xprDt' => $cardexpiry, 'sec' => $cvv);
	}

	private function _buildContact($fullname = null, $addrLn1 = null, $addrLn2 = null, $city = null, $state = null, $zipCode = null, $country = null, $email = null, $phone = null, $companyName = null) {
		$params = array();

		if (($fullname != null) && !empty($fullname))
			$params['fullName'] = $fullname;

		if (($addrLn1 != null) && !empty($addrLn1))
			$params['addrLn1'] = $addrLn1;

		if (($addrLn2 != null) && !empty($addrLn2))
			$params['addrLn2'] = $addrLn2;

		if (($city != null) && !empty($city))
			$params['city'] = $city;

		if (($state != null) && !empty($state))
			$params['state'] = ((strlen($state) != 2) ? self::getStateCode($state) : $state);

		if (($zipCode != null) && !empty($zipCode))
			$params['zipCode'] = $zipCode;
		
		if (($country != null) && !empty($country))
			$params['ctry'] = ((strlen($country) != 2) ? self::getCountryCode($country) : $country);

		if (($email != null) && !empty($email))
			$params['email'] = $email;

		if (($phone != null) && !empty($phone))
			$params['phone'] = array('type' => 4, 'nr' => $phone);

        if (($companyName != null) && !empty($companyName))
            $params['coName'] = $companyName;

		return $params;
	}

	public function authorize($fullname, $cardnum, $cardexp, $cvv, $amount, $ordNr, $addrLn1 = null, $addrLn2 = null, $city = null, $state = null, $zipCode = null, $country = null, $email = null, $phone = null, $companyName = null) {
		$params = array(
			'card'    => $this->_buildCard($cardnum, $cardexp, $cvv),
			'authReq' => array('ordNr' => $ordNr),
			'reqAmt'                   => ($this::formatAmount($amount)),
			'contact'                  => $this->_buildContact($fullname, $addrLn1, $addrLn2, $city, $state, $zipCode, $country, $email, $phone, $companyName));
		return $this->_sendRequest($params, self::TRANCODE_AUTH_ONLY);
	}

	public function authAndSettle($fullname, $cardnum, $cardexp, $cvv, $amount, $ordNr, $addrLn1 = null, $addrLn2 = null, $city = null, $state = null, $zipCode = null, $country = null, $email = null, $phone = null, $companyName = null) {
		$params = array(
			'card'    => $this->_buildCard($cardnum, $cardexp, $cvv),
			'authReq' => array('ordNr' => $ordNr),
			'reqAmt'                   => ($this::formatAmount($amount)),
			'contact'                  => $this->_buildContact($fullname, $addrLn1, $addrLn2, $city, $state, $zipCode, $country, $email, $phone, $companyName));

		return $this->_sendRequest($params, self::TRANCODE_AUTH_AND_SETTLE);
	}

    public function settle($tranNr, $amount)
    {
        $amount = $this::formatAmount($amount);

        if ($amount < 0)
            return $this->settleVoid($tranNr, $amount);

        $params = array('origTranData' => array('tranNr' => $tranNr), 'reqAmt' => $amount);
        return $this->_sendRequest($params, self::TRANCODE_SETTLE_ONLY);
    }

	public function settleVoid($tranNr, $removeAuth = true) {
		$params = array(
			'tranFlags' => array('revAuthOnVoid' => ($removeAuth ? self::AUTHFLAG_REMOVE_AUTH : self::AUTHFLAG_NO_REMOVE_AUTH)),
			'origTranData'                       => array('tranNr' => $tranNr));

		return $this->_sendRequest($params, self::TRANCODE_SETTLE_VOID);
	}

	public function addCustomer($fullname, $cardnum, $cardexp, $cvv, $amount, $ordNr, $addrLn1 = null, $addrLn2 = null, $city = null, $state = null, $zipCode = null, $country = null, $email = null, $phone = null, $companyName = null)
	{
		$params = array(
			'card'    => $this->_buildCard($cardnum, $cardexp, $cvv),
			'authReq' => array('ordNr' => $ordNr),
			'reqAmt'                   => ($this::formatAmount($amount)),
			'contact'                  => $this->_buildContact($fullname, $addrLn1, $addrLn2, $city, $state, $zipCode, $country, $email, $phone, $companyName));
		return $this->_sendRequest($params, self::TRANCODE_AUTH_ONLY);
	}

	private function _obj2array($obj) {
		$out = array();
		foreach ($obj as $key => $val) {
			switch (true) {
				case is_object($val):
					$out[$key] = $this->_obj2array($val);
					break;

				case is_array($val):
					$out[$key] = $this->_obj2array($val);
					break;

				default:
					$out[$key] = $val;
			}
		}

		return $out;
	}

	private function _sendRequest($params, $tranCode, $xmlElement = 'SendTran' ) {
		try {
			$wsdl = $this->serviceUrl;
			
			$soap = '';
			
			// allow for invalid SSL certs in test mode
			if($this->useTestMode)
			{			
				$context = stream_context_create([
					'ssl' => [
						// set some SSL/TLS specific options
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					]
				]);
				
				$soap = new SoapClient($wsdl, array('trace' => 1, 'exceptions' => true, 'stream_context' => $context));
			}
			else
			{
				$soap = new SoapClient($wsdl, array('trace' => 1, 'exceptions' => true));
			}

			$params = array_merge($params, array('merc' => $this->_buildMerc(), 'tranCode' => $tranCode));

			$response = $soap->__soapCall($xmlElement, array($params));
			$res      = $this->_obj2array($response);

			if (($res['rspCode'] == '00') || ($res['rspCode'] == '10')) {
				return array(
					'status'  => 'success',
					'transid' => $res['tranData']['tranNr'],
					'partial' => ($res['rspCode'] == '10'),
					'rspcode' => $res['rspCode'],
					'rawdata' => array(
						'rspCode'    => $res['rspCode'],
						'authRspAci' => (array_key_exists('aci', $res['authRsp']) ? $res['authRsp']['aci'] : ''),
						'swchKey'    => $res['tranData']['swchKey'],
						'tranNr'     => $res['tranData']['tranNr'],
						'dtTm'       => $res['tranData']['dtTm'],
						'amt'        => $res['tranData']['amt'],
						'stan'       => $res['tranData']['stan'],
						'auth'       => $res['tranData']['auth'],
						'cardType'   => $res['cardType'],
						'mapCaid'    => $res['mapCaid']));
			} else
				return array('status' => 'declined', 'message' => self::getResponseMessage($res['rspCode']), 'rawdata' => array('rspCode' => $res['rspCode'], 'extRspCode' => $res['extRspCode']));
		} catch (SoapFault $ex) {
			error_log("Error sending request: " . $ex->getMessage());
			return array('status' => 'failed', 'message' => $ex->getMessage(), 'rawdata' => $ex);
		}
	}

    static function formatAmount($amount)
    {
        // remove decimal
        // remove currency symbol
        $amount = filter_var($amount, FILTER_SANITIZE_NUMBER_FLOAT);

        // add leading zero
        if($amount[0] !== "0")
            $amount = '0' . $amount;

        return $amount;
    }

	public function getTransaction($tranNr) {
		try {
			$wsdl = "https://tfreports." . ($this->useTestMode ? "cert." : "") . "transactionexpress.com/eReports/eReportsService.svc?wsdl";

			$soap = new SoapClient($wsdl, array('trace' => 1, 'exceptions' => true));

			$params = array('objTransactionDetailRequest' => array('tranNr' => $tranNr, 'Credential' => array('MerchantInfo' => array('MerchantID' => $this->mercId, 'RegistrationKey' => $this->regKey))));

			$response = $soap->__soapCall('GetTransaction', array($params));
			$res      = $this->_obj2array($response)['GetTransactionResult'];

			return array('status' => 'success',
				'message'            => $res['AuthResponseMessage'],
				'approvedAuthAmount' => $res['ApprovedAuthAmount'],
				'authAmount'         => $res['AuthAmount'],
				'rspCode'            => $res['AuthResponseCode'],
				'authStatus'         => $res['AuthorizationStatus'],
				'authMessage'        => $res['AuthResponseMessage'],
				'authDate'           => $res['AuthDate'],
				'cardType'           => $res['CardType'],
				'refId'              => $res['CustomerRefId'],
				'postedDate'         => $res['PostedDate'],
				'reqAmount'          => $res['RequestedAmount'],
				'aci'                => $res['ReturnedAciIndicator'],
				'totalAuthAmount'    => $res['TotalAuthAmount'],
				'authStatus'         => $res['AuthorizationStatus'],
				'voided'             => $res['Voided'],
				'voidDate'           => $res['VoidDate'],
				'isPartialAuth'      => $res['isPartialAuthorization'],
				'rawdata'            => $res);
		} catch (Exception $ex) {
			error_log("Error sending request: " . $ex->getMessage());
			return array('status' => 'failed', 'message' => $ex->getMessage(), 'rawdata' => $ex);
		}
	}

	public static function getResponseMessage($rspCode) {
		switch ($rspCode) {
			case "00":
				return "Full Authorization";
			case "01":
				return "Refer to card issuer";
			case "02":
				return "Refer to card issuer, special condition";
			case "03":
				return "Invalid merchant";
			case "04":
				return "Pick-up card";
			case "05":
				return "Do not honor";
			case "06":
				return "Error";
			case "07":
				return "Pick-up card, special condition";
			case "08":
				return "Honor with identification (this is a decline response when a card not present transaction) If you receive an approval in a card not present environment, you will need to void the transaction.";
			case "09":
				return "Request in progress";
			case "10":
				return "Approved, partial authorization";
			case "11":
				return "VIP Approval (this is a decline response for a card not present transaction)";
			case "12":
				return "Invalid transaction";
			case "13":
				return "Invalid amount";
			case "14":
				return "Invalid card number";
			case "15":
				return "No such issuer";
			case "16":
				return "Approved, update track 3";
			case "17":
				return "Customer cancellation";
			case "18":
				return "Customer dispute";
			case "19":
				return "Re-enter transaction";
			case "20":
				return "Invalid response";
			case "21":
				return "No action taken";
			case "22":
				return "Suspected malfunction";
			case "23":
				return "Unacceptable transaction fee";
			case "24":
				return "File update not supported";
			case "25":
				return "Unable to locate record";
			case "26":
				return "Duplicate record";
			case "27":
				return "File update field edit error";
			case "28":
				return "File update file locked";
			case "29":
				return "File update failed";
			case "30":
				return "Format error";
			case "31":
				return "Bank not supported";
			case "32":
				return "Completed partially";
			case "33":
				return "Expired card, pick-up";
			case "34":
				return "Suspected fraud, pick-up";
			case "35":
				return "Contact acquirer, pick-up";
			case "36":
				return "Restricted card, pick-up";
			case "37":
				return "Call acquirer security, pick-up";
			case "38":
				return "PIN tries exceeded, pick-up";
			case "39":
				return "No credit account";
			case "40":
				return "Function not supported";
			case "41":
				return "Lost card, pick-up";
			case "42":
				return "No universal account";
			case "43":
				return "Stolen card, pick-up";
			case "44":
				return "No investment account";
			case "45":
				return "Account closed";
			case "46":
				return "Identification required";
			case "47":
				return "Identification cross-check required";
			case "48":
				return "No customer record";
			case "51":
				return "Not sufficient funds";
			case "52":
				return "No checking account";
			case "53":
				return "No savings account";
			case "54":
				return "Expired card";
			case "55":
				return "Incorrect PIN";
			case "56":
				return "No card record";
			case "57":
				return "Transaction not permitted to cardholder";
			case "58":
				return "Transaction not permitted on terminal";
			case "59":
				return "Suspected fraud";
			case "60":
				return "Contact acquirer";
			case "61":
				return "Exceeds withdrawal limit";
			case "62":
				return "Restricted card";
			case "63":
				return "Security violation";
			case "64":
				return "Original amount incorrect";
			case "65":
				return "Exceeds withdrawal frequency";
			case "66":
				return "Call acquirer security";
			case "67":
				return "Hard capture";
			case "68":
				return "Response received too late";
			case "69":
				return "Advice received too late";
			case "75":
				return "PIN tries exceeded";
			case "76":
				return "Reversal: Unable to locate previous message (no match on Retrieval Reference Number)/ Reserved for future Realtime use";
			case "77":
				return "Previous message located for a repeat or reversal, but repeat or reversal data is inconsistent with original message/ Intervene, bank approval required";
			case "78":
				return "Invalid/non-existent account – Decline (MasterCard specific)/ Intervene, bank approval required for partial amount";
			case "79":
				return "Already reversed (by Switch)/ Reserved for client-specific use (declined)";
			case "80":
				return "No financial Impact (Reserved for declined debit)/ Reserved for client-specific use (declined)";
			case "81":
				return "PIN cryptographic error found by the Visa security module during PIN decryption/ Reserved for client-specific use (declined)";
			case "82":
				return "Incorrect CVV/ Reserved for client-specific use (declined)";
			case "83":
				return "Unable to verify PIN/ Reserved for client-specific use (declined)";
			case "84":
				return "Invalid Authorization Life Cycle – Decline (MasterCard) or Duplicate Transaction Detected (Visa)/ Reserved for client-specific use (declined)";
			case "85":
				return "No reason to decline a request for Account Number Verification or Address Verification/ Reserved for client-specific use (declined)";
			case "86":
				return "Cannot verify PIN/ Reserved for client-specific use (declined)";
			case "90":
				return "Cut-off in progress";
			case "91":
				return "Issuer or switch inoperative";
			case "92":
				return "Routing error";
			case "93":
				return "Violation of law";
			case "94":
				return "Duplicate Transmission (Integrated Debit and MasterCard)";
			case "95":
				return "Reconcile error";
			case "96":
				return "System malfunction";
			case "98":
				return "Exceeds cash limit";
		}

		return "Unknown Response [Code: " . $rspCode . "]";
	}

	public static function getCountryCode($country) {
		$countrycodes = array(
		  'AF' => 'Afghanistan',
		  'AX' => 'Åland Islands',
		  'AL' => 'Albania',
		  'DZ' => 'Algeria',
		  'AS' => 'American Samoa',
		  'AD' => 'Andorra',
		  'AO' => 'Angola',
		  'AI' => 'Anguilla',
		  'AQ' => 'Antarctica',
		  'AG' => 'Antigua and Barbuda',
		  'AR' => 'Argentina',
		  'AU' => 'Australia',
		  'AT' => 'Austria',
		  'AZ' => 'Azerbaijan',
		  'BS' => 'Bahamas',
		  'BH' => 'Bahrain',
		  'BD' => 'Bangladesh',
		  'BB' => 'Barbados',
		  'BY' => 'Belarus',
		  'BE' => 'Belgium',
		  'BZ' => 'Belize',
		  'BJ' => 'Benin',
		  'BM' => 'Bermuda',
		  'BT' => 'Bhutan',
		  'BO' => 'Bolivia',
		  'BA' => 'Bosnia and Herzegovina',
		  'BW' => 'Botswana',
		  'BV' => 'Bouvet Island',
		  'BR' => 'Brazil',
		  'IO' => 'British Indian Ocean Territory',
		  'BN' => 'Brunei Darussalam',
		  'BG' => 'Bulgaria',
		  'BF' => 'Burkina Faso',
		  'BI' => 'Burundi',
		  'KH' => 'Cambodia',
		  'CM' => 'Cameroon',
		  'CA' => 'Canada',
		  'CV' => 'Cape Verde',
		  'KY' => 'Cayman Islands',
		  'CF' => 'Central African Republic',
		  'TD' => 'Chad',
		  'CL' => 'Chile',
		  'CN' => 'China',
		  'CX' => 'Christmas Island',
		  'CC' => 'Cocos (Keeling) Islands',
		  'CO' => 'Colombia',
		  'KM' => 'Comoros',
		  'CG' => 'Congo',
		  'CD' => 'Zaire',
		  'CK' => 'Cook Islands',
		  'CR' => 'Costa Rica',
		  'CI' => 'Côte D\'Ivoire',
		  'HR' => 'Croatia',
		  'CU' => 'Cuba',
		  'CY' => 'Cyprus',
		  'CZ' => 'Czech Republic',
		  'DK' => 'Denmark',
		  'DJ' => 'Djibouti',
		  'DM' => 'Dominica',
		  'DO' => 'Dominican Republic',
		  'EC' => 'Ecuador',
		  'EG' => 'Egypt',
		  'SV' => 'El Salvador',
		  'GQ' => 'Equatorial Guinea',
		  'ER' => 'Eritrea',
		  'EE' => 'Estonia',
		  'ET' => 'Ethiopia',
		  'FK' => 'Falkland Islands (Malvinas)',
		  'FO' => 'Faroe Islands',
		  'FJ' => 'Fiji',
		  'FI' => 'Finland',
		  'FR' => 'France',
		  'GF' => 'French Guiana',
		  'PF' => 'French Polynesia',
		  'TF' => 'French Southern Territories',
		  'GA' => 'Gabon',
		  'GM' => 'Gambia',
		  'GE' => 'Georgia',
		  'DE' => 'Germany',
		  'GH' => 'Ghana',
		  'GI' => 'Gibraltar',
		  'GR' => 'Greece',
		  'GL' => 'Greenland',
		  'GD' => 'Grenada',
		  'GP' => 'Guadeloupe',
		  'GU' => 'Guam',
		  'GT' => 'Guatemala',
		  'GG' => 'Guernsey',
		  'GN' => 'Guinea',
		  'GW' => 'Guinea-Bissau',
		  'GY' => 'Guyana',
		  'HT' => 'Haiti',
		  'HM' => 'Heard Island and Mcdonald Islands',
		  'VA' => 'Vatican City State',
		  'HN' => 'Honduras',
		  'HK' => 'Hong Kong',
		  'HU' => 'Hungary',
		  'IS' => 'Iceland',
		  'IN' => 'India',
		  'ID' => 'Indonesia',
		  'IR' => 'Iran, Islamic Republic of',
		  'IQ' => 'Iraq',
		  'IE' => 'Ireland',
		  'IM' => 'Isle of Man',
		  'IL' => 'Israel',
		  'IT' => 'Italy',
		  'JM' => 'Jamaica',
		  'JP' => 'Japan',
		  'JE' => 'Jersey',
		  'JO' => 'Jordan',
		  'KZ' => 'Kazakhstan',
		  'KE' => 'KENYA',
		  'KI' => 'Kiribati',
		  'KP' => 'Korea, Democratic People\'s Republic of',
		  'KR' => 'Korea, Republic of',
		  'KW' => 'Kuwait',
		  'KG' => 'Kyrgyzstan',
		  'LA' => 'Lao People\'s Democratic Republic',
		  'LV' => 'Latvia',
		  'LB' => 'Lebanon',
		  'LS' => 'Lesotho',
		  'LR' => 'Liberia',
		  'LY' => 'Libyan Arab Jamahiriya',
		  'LI' => 'Liechtenstein',
		  'LT' => 'Lithuania',
		  'LU' => 'Luxembourg',
		  'MO' => 'Macao',
		  'MK' => 'Macedonia, the Former Yugoslav Republic of',
		  'MG' => 'Madagascar',
		  'MW' => 'Malawi',
		  'MY' => 'Malaysia',
		  'MV' => 'Maldives',
		  'ML' => 'Mali',
		  'MT' => 'Malta',
		  'MH' => 'Marshall Islands',
		  'MQ' => 'Martinique',
		  'MR' => 'Mauritania',
		  'MU' => 'Mauritius',
		  'YT' => 'Mayotte',
		  'MX' => 'Mexico',
		  'FM' => 'Micronesia, Federated States of',
		  'MD' => 'Moldova, Republic of',
		  'MC' => 'Monaco',
		  'MN' => 'Mongolia',
		  'ME' => 'Montenegro',
		  'MS' => 'Montserrat',
		  'MA' => 'Morocco',
		  'MZ' => 'Mozambique',
		  'MM' => 'Myanmar',
		  'NA' => 'Namibia',
		  'NR' => 'Nauru',
		  'NP' => 'Nepal',
		  'NL' => 'Netherlands',
		  'AN' => 'Netherlands Antilles',
		  'NC' => 'New Caledonia',
		  'NZ' => 'New Zealand',
		  'NI' => 'Nicaragua',
		  'NE' => 'Niger',
		  'NG' => 'Nigeria',
		  'NU' => 'Niue',
		  'NF' => 'Norfolk Island',
		  'MP' => 'Northern Mariana Islands',
		  'NO' => 'Norway',
		  'OM' => 'Oman',
		  'PK' => 'Pakistan',
		  'PW' => 'Palau',
		  'PS' => 'Palestinian Territory, Occupied',
		  'PA' => 'Panama',
		  'PG' => 'Papua New Guinea',
		  'PY' => 'Paraguay',
		  'PE' => 'Peru',
		  'PH' => 'Philippines',
		  'PN' => 'Pitcairn',
		  'PL' => 'Poland',
		  'PT' => 'Portugal',
		  'PR' => 'Puerto Rico',
		  'QA' => 'Qatar',
		  'RE' => 'Réunion',
		  'RO' => 'Romania',
		  'RU' => 'Russian Federation',
		  'RW' => 'Rwanda',
		  'SH' => 'Saint Helena',
		  'KN' => 'Saint Kitts and Nevis',
		  'LC' => 'Saint Lucia',
		  'PM' => 'Saint Pierre and Miquelon',
		  'VC' => 'Saint Vincent and the Grenadines',
		  'WS' => 'Samoa',
		  'SM' => 'San Marino',
		  'ST' => 'Sao Tome and Principe',
		  'SA' => 'Saudi Arabia',
		  'SN' => 'Senegal',
		  'RS' => 'Serbia',
		  'SC' => 'Seychelles',
		  'SL' => 'Sierra Leone',
		  'SG' => 'Singapore',
		  'SK' => 'Slovakia',
		  'SI' => 'Slovenia',
		  'SB' => 'Solomon Islands',
		  'SO' => 'Somalia',
		  'ZA' => 'South Africa',
		  'GS' => 'South Georgia and the South Sandwich Islands',
		  'ES' => 'Spain',
		  'LK' => 'Sri Lanka',
		  'SD' => 'Sudan',
		  'SR' => 'Suriname',
		  'SJ' => 'Svalbard and Jan Mayen',
		  'SZ' => 'Swaziland',
		  'SE' => 'Sweden',
		  'CH' => 'Switzerland',
		  'SY' => 'Syrian Arab Republic',
		  'TW' => 'Taiwan, Province of China',
		  'TJ' => 'Tajikistan',
		  'TZ' => 'Tanzania, United Republic of',
		  'TH' => 'Thailand',
		  'TL' => 'Timor-Leste',
		  'TG' => 'Togo',
		  'TK' => 'Tokelau',
		  'TO' => 'Tonga',
		  'TT' => 'Trinidad and Tobago',
		  'TN' => 'Tunisia',
		  'TR' => 'Turkey',
		  'TM' => 'Turkmenistan',
		  'TC' => 'Turks and Caicos Islands',
		  'TV' => 'Tuvalu',
		  'UG' => 'Uganda',
		  'UA' => 'Ukraine',
		  'AE' => 'United Arab Emirates',
		  'GB' => 'United Kingdom',
		  'US' => 'United States',
		  'UM' => 'United States Minor Outlying Islands',
		  'UY' => 'Uruguay',
		  'UZ' => 'Uzbekistan',
		  'VU' => 'Vanuatu',
		  'VE' => 'Venezuela',
		  'VN' => 'Viet Nam',
		  'VG' => 'Virgin Islands, British',
		  'VI' => 'Virgin Islands, U.S.',
		  'WF' => 'Wallis and Futuna',
		  'EH' => 'Western Sahara',
		  'YE' => 'Yemen',
		  'ZM' => 'Zambia',
		  'ZW' => 'Zimbabwe');

		return array_search($country, $countrycodes);
	}

	public static function getStateCode($state) {
		$statecodes = array(
			'AL'=>'Alabama',
			'AK'=>'Alaska',
			'AZ'=>'Arizona',
			'AR'=>'Arkansas',
			'CA'=>'California',
			'CO'=>'Colorado',
			'CT'=>'Connecticut',
			'DE'=>'Delaware',
			'FL'=>'Florida',
			'GA'=>'Georgia',
			'HI'=>'Hawaii',
			'ID'=>'Idaho',
			'IL'=>'Illinois',
			'IN'=>'Indiana',
			'IA'=>'Iowa',
			'KS'=>'Kansas',
			'KY'=>'Kentucky',
			'LA'=>'Louisiana',
			'ME'=>'Maine',
			'MD'=>'Maryland',
			'MA'=>'Massachusetts',
			'MI'=>'Michigan',
			'MN'=>'Minnesota',
			'MS'=>'Mississippi',
			'MO'=>'Missouri',
			'MT'=>'Montana',
			'NE'=>'Nebraska',
			'NV'=>'Nevada',
			'NH'=>'New Hampshire',
			'NJ'=>'New Jersey',
			'NM'=>'New Mexico',
			'NY'=>'New York',
			'NC'=>'North Carolina',
			'ND'=>'North Dakota',
			'OH'=>'Ohio',
			'OK'=>'Oklahoma',
			'OR'=>'Oregon',
			'PA'=>'Pennsylvania',
			'RI'=>'Rhode Island',
			'SC'=>'South Carolina',
			'SD'=>'South Dakota',
			'TN'=>'Tennessee',
			'TX'=>'Texas',
			'UT'=>'Utah',
			'VT'=>'Vermont',
			'VA'=>'Virginia',
			'WA'=>'Washington',
			'WV'=>'West Virginia',
			'WI'=>'Wisconsin',
			'WY'=>'Wyoming');

			return array_search($state, $statecodes);
	}
}

?>
