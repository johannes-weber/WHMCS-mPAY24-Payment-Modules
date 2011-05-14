<?php
/**
 * WHMCS Paymentmodule for mPay24 Bank Transfer Payment Gateway
 * 
 * @author Johannes Weber <jw@internex.at>
 * @version $Id:$
 */

/**
 * Configuration options for this Payment Gateway
 *
 * @return array
 */
function mPAY24_config()
{
    $configarray = array(
	     "FriendlyName" => array("Type" => "System", "Value"=>"mPAY24 Bank Transfer"),
	     "partnerID" => array("FriendlyName" => "Partner ID", "Type" => "text", "Size" => "10", ),
	     "instructions" => array("FriendlyName" => "Payment Instructions", "Type" => "textarea", "Rows" => "5", "Description" => "The instructions you want displaying to customers who choose this payment method.", ),
	     "logoUrl" => array("FriendlyName" => "Logo Url", "Type" => "text",),
	     "linkText" => array("FriendlyName" => "Link-Text", "Type" => "text",),
	     "wbmiTitle" => array("FriendlyName" => "WBMI Title", "Type" => "text",),
	     "topLimit" => array("FriendlyName" => "Top Limit", "Type" => "text", "Description" => "Top Limit for Creditcard Payments. Format: ###.##. An empty value means no limit."),
		 "topLimitText" => array("FriendlyName" => "Top Limit Text", "Type" => "textarea", "Rows" => "5", "Description" => "Text which will be displayed if invoice amount is higher than top limit.",),
	     "hashKeyExtension" => array("FriendlyName" => "Hashkey Extension", "Type" => "text", "Rows" => "5", "Description" => "Some signs which are attached to confirmaiton hash. (<b>As more different signs, as better for your Security</b>)",),
	     "emailNotification" => array("FriendlyName" => "E-Mail notification?", "Type" => "yesno", "Description" => "Tick this to receive an E-Mail (with Payment & Customer Details) when Payment was successful. "),
		 "notificationRecipients" => array("FriendlyName" => "Notification recipients", "Type" => "textarea", "Description" => 'Add more recipients by comma-seperating E-Mail adresses'),
		 "notificationSubject" => array("FriendlyName" => "Notification Subject", "Type" => "text"),
		 "notificationSenderName" => array("FriendlyName" => "Notification sender Name", "Type" => "text"),
		 "notificationSenderEmail" => array("FriendlyName" => "Notification sender E-Mail", "Type" => "text"),
	     "testmode" => array("FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Tick this to test", ),
    );
    
	return $configarray;
}

/**
 * Create payment link for customers digital bill
 *
 * @return string
 */
function mPAY24_link($params)
{	
	// Customer
	$firstname = utf8_decode($params['clientdetails']['firstname']);
	$lastname = utf8_decode($params['clientdetails']['lastname']);
	$email = utf8_decode($params['clientdetails']['email']);
	$address1 = utf8_decode($params['clientdetails']['address1']);
	$address2 = utf8_decode($params['clientdetails']['address2']);
	$city = utf8_decode($params['clientdetails']['city']);
	$state = utf8_decode($params['clientdetails']['state']);
	$postcode = utf8_decode(trim($params['clientdetails']['postcode']));
	$country = utf8_decode($params['clientdetails']['country']);
	$phone = utf8_decode($params['clientdetails']['phonenumber']);
	
   	// Invoice
	$idInvoice = $params['invoiceid'];
	$description = $params["description"];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $topLimit = floatval($params['topLimit']);
	$hashKeyExtension = (!empty($params['hashKeyExtension'])) ? $params['hashKeyExtension'] : '';

	// check orders top limit if it exists
	$topLimit = floatval($params['topLimit']);
	if($topLimit > 0)
	{
		if(floatval($amount) > $topLimit)
		{
			return $params['topLimitText'];
		}
	}
    
	// is module in testing mode
    $mode = ('on' == $params['testmode']) ? 'test' : 'www';
	
   	// generate the request link
    $request = new mPAY24_SelectPayment($mode);
   	$request->setMerchantId($params['partnerID']);
   	$request->setTid($idInvoice);
   	$request->setDescription($description);

	// add users address-data to this order
   	$billing_addr = new mPAY24_Address();
	$billing_addr->setFirstName($firstname);
	$billing_addr->setLastName($lastname);
	$billing_addr->setStreet($address1 . ' ' . $address2);
	$billing_addr->setZip($postcode);
	$billing_addr->setCity($city);
	$billing_addr->setCountry($country);
	$billing_addr->setEmail($email);
	$request->setBillingAddress($billing_addr);
	
	// create the order-item for mpay24 external window
	$item = new mPAY24_Item();
	$item->setDescription($description);
	$item->setPrice($amount);
	$request->addItem($item);
	
	$request->setLanguage("DE");
   	$request->setTotalPrice($amount);
	$request->setCurrency($currency);
	
	// external mpay24 window styling
	$style = new mPAY24_Style();
	$style->setLogoStyle("border: none;padding-bottom: 10px;margin: 10px auto;");
	$style->setPageHeaderStyle("text-align: left;");
	$style->setPageCaptionStyle("color:#FFFFFF;font-size:9pt;font-weight:bold;padding:4px 10px 1px;margin-right:5px;");
	$style->setSCHeaderStyle("text-align:left;");
	$style->setSCCaptionStyle("color:#FFFFFF;font-size:14px;font-weight:bold;padding:3px;background-position:left top;margin-right:13px;padding: 2px 10px 1px;");
	$style->setSCPriceStyle("padding:3px;");     
	$style->setSCItemEvenStyle("padding:3px;");
	$style->setSCItemOddStyle("padding:3px;");
	$style->setShippingCostsHStyle("padding:3px;");
	$style->setShippingCostsStyle("padding:3px;");
	$style->setTaxHStyle("padding:3px;");
	$style->setTaxStyle("padding:3px;");
	$style->setPRHeader("Gesamtpreis Ihrer Bestellung");
	$style->setPRHeaderStyle("padding:3px;");
	$style->setPRStyle("padding:3px;");
	$style->setWholePageStyle("margin-left: auto; margin-right: auto; width:600px;");
	$style->setFooterStyle("margin-top: 2px;");
	$style->setPageStyle("margin-top:10px;");
	$style->setSCHeader($params['wbmiTitle']);
	
	$request->setStyle($style);
	
	// genrate url which is linked to mpay24 external payment window
	$urlParams = array(
		'confirm=true',
		'paymentmethod=' . $params['paymentmethod'],
		'id_user=' . $params['clientdetails']['userid'],
		'amount=' . $params['amount'],
		'description=' . urlencode($params["description"]),
	);
	
	// generate the secret token
	$token = sha1($hashKeyExtension.md5($_SERVER['HTTP_HOST'] . sha1($params['clientdetails']['userid']*100/23.5) . md5($params['amount']) . $params["description"] . $_SERVER['SERVER_ADDR'] . 'BT'));
	$urlParams[] = 'token=' . $token;
	// ... and the confirmation url
	$confirmUrl = $params['systemurl'] . '/modules/gateways/callback/mPAY24.php?' . implode('&', $urlParams);
	
	$request->setSuccessURL($params['returnurl'] . '&success=true');
	$request->setErrorURL($params['returnurl'] . '&success=false');
	$request->setConfirmURL($confirmUrl);	
	$request->createXmlFile();
	$request->send();
	
	$request->parseResponse();
	
	$STATUS = $request->getSTATUS();
	$RETURNCODE = $request->getRETURNCODE();
	$LOCATION = $request->getLOCATION();
	
	if($STATUS == "OK"){
      if($RETURNCODE == "REDIRECT"){
         if(!empty($LOCATION)){
         	$returnString = '';
			if(!empty($params['logoUrl']))
			{
				$returnString .= '<table>
					  	<tr>
					  		<td style="padding-right: 3px;" align="center"><a href="'.$LOCATION.'"><img src="'.$params['logoUrl'].'" alt="" border="0" /></a></td>
					  	</tr>
					  	<tr>
					  		<td align="center">'.$params['instructions'].'</td>
					  	</tr>
					  </table>';
			}
			else 
			{
				$returnString .= $params['instructions'];
			}
            $returnString.= '<br /><form method="post" action="' . $LOCATION . '"><input type="submit" value="' . $params['linkText'] . '" />';
         } 
         else {
         	$returnString = "";
         }
      }
   }
   else 
   {
   	   $returnString = 'Error during init payment-process!';
   }
	return $returnString;
}



// ALL REQUIRED CLASSES
/*
$Id: Style.php,v 1.4 2008/03/12 11:26:49 thomas Exp $

Created on 20070524
Code by Thomas Langer, Thomas.Langer@mPAY24.com
Company mPAY24 GmbH
https://www.mPAY24.com
Released under the GNU General Public License

This file contains the class for setting the design attributes in the xml file created by the mPAY24 SelectPayment Plugin,
written for osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com
*/
class mPAY24_Style {
  // set-functions
  function setLogoStyle($logo) {
     $this->logostyle = $logo;
  }
  
  function setPageHeaderStyle($pageheader) {
     $this->pageheaderstyle = $pageheader;
  }
  
  function setPageCaptionStyle($pagecaption) {
     $this->pagecaptionstyle = $pagecaption;
  }
  
  function setPageStyle($pagest) {
     $this->pagestyle = $pagest;
  }
  
  function setFooterStyle($footer) {
     $this->footerstyle = $footer;
  }
  
  function setSCStyle($sc) {
     $this->scstyle = $sc;
  }

  function setSCHeader($sc_header){
     $this->scheader = $sc_header;
  }
  
  function setSCHeaderStyle($scheader) {
     $this->scheaderstyle = $scheader;
  }
  
  function setSCCaptionStyle($sccaption) {
     $this->sccaptionstyle = $sccaption;
  }
  
  function setSCNumberStyle($scnumberstyle) {
     $this->scnumberstyle = $scnumberstyle;
  }
  
  function setSCProductNrStyle($scprodnrstyle) {
     $this->scprodnrstyle = $scprodnrstyle;
  }
  
  function setSCDescriptionStyle($scdescstyle) {
     $this->scdescriptionstyle = $scdescstyle;
  }
  
  function setSCPackageStyle($packagestyle) {
     $this->scpackagestyle = $packagestyle;
  }
  
  function setSCQuantityStyle($scquantitystyle) {
     $this->scquantitystyle = $scquantitystyle;
  }
  
  function setSCItemPriceStyle($scitempricest) {
     $this->scitempricestyle = $scitempricest;
  }
  
  function setSCPriceStyle($scpricestyle) {
     $this->scpricestyle = $scpricestyle;
  }
  
  function setSCItemEvenStyle($even) {
     $this->scitemevenstyle = $even;
  }
  
  function setSCItemOddStyle($odd) {
     $this->scitemoddstyle = $odd;
  }
  
  function setShippingCostsHStyle($shippingcostsh) {
     $this->shippingcostshstyle = $shippingcostsh;
  }
  
  function setShippingCostsStyle($shippingcostssyle) {
     $this->shippingcostsstyle = $shippingcostssyle;
  }
  
  function setTaxHStyle($taxhstyle) {
     $this->taxhstyle = $taxhstyle;
  }
  
  function setTaxStyle($taxstyle) {
     $this->taxstyle = $taxstyle;
  }
  
  function setPRHeader($prheader) {
     $this->prheader = $prheader;
  }
  
  function setPRHeaderStyle($prheaders) {
     $this->prheaderstyle = $prheaders;
  }
  
  function setPRStyle($prs) {
     $this->prstyle = $prs;
  }
  
  function setWholePageStyle($wps){
  	$this->wholepagestyle = $wps;
  }
  
  function setInputFieldsStyle($ifs){
  	$this->inputfieldsstyle = $ifs;
  }
  
  // get-functions
  function getLogoStyle() {
     return $this->logostyle;
  }
  
  function getPageHeaderStyle() {
     return $this->pageheaderstyle;
  }
  
  function getPageCaptionStyle() {
     return $this->pagecaptionstyle;
  }
  
  function getPageStyle() {
     return $this->pagestyle;
  }
  
  function getFooterStyle() {
     return $this->footerstyle;
  }
  
  function getSCStyle() {
     return $this->scstyle;
  }

  function getSCHeader() {
     return $this->scheader;
  }
  
  function getSCHeaderStyle() {
     return $this->scheaderstyle;
  }
  
  function getSCCaptionStyle() {
     return $this->sccaptionstyle;
  }
  
  function getSCNumberStyle() {
     return $this->scnumberstyle;
  }
  
  function getSCProductNrStyle() {
     return $this->scprodnrstyle;
  }
  
  function getSCDescriptionStyle() {
     return $this->scdescriptionstyle;
  }
  
  function getSCPackageStyle() {
     return $this->scpackagestyle;
  }
  
  function getSCQuantityStyle() {
     return $this->scquantitystyle;
  }
  
  function getSCItemPriceStyle() {
     return $this->scitempricestyle;
  }
  
  function getSCPriceStyle() {
     return $this->scpricestyle;
  }
  
  function getSCItemEvenStyle() {
     return $this->scitemevenstyle;
  }
  
  function getSCItemOddStyle() {
     return $this->scitemoddstyle;
  }
  
  function getShippingCostsHStyle() {
     return $this->shippingcostshstyle;
  }
  
  function getShippingCostsStyle() {
     return $this->shippingcostsstyle;
  }
  
  function getTaxHStyle() {
     return $this->taxhstyle;
  }
  
  function getTaxStyle() {
     return $this->taxstyle;
  }
  
  function getPRHeader() {
     return $this->prheader;
  }
  
  function getPRHeaderStyle() {
     return $this->prheaderstyle;
  }
  
  function getPRStyle() {
     return $this->prstyle;
  }
  
	  function getWholePageStyle(){
  	return $this->wholepagestyle;
  }
  
  function getInputFieldsStyle(){
  	return $this->inputfieldsstyle;
  }
  
  // member-variables
  var $logostyle;
  var $pageheaderstyle;
  var $pagecaptionstyle;
  var $pagestyle;
  var $footerstyle;
  var $scheader;
  var $scstyle;
  var $scheaderstyle;
  var $sccaptionstyle;
  var $scnumberstyle;
  var $scprodnrstyle;
  var $scdescriptionstyle;
  var $scpackagestyle;
  var $scquantitystyle;
  var $scitempricestyle;
  var $scpricestyle;
  var $scitemevenstyle;
  var $scitemoddstyle;
  var $shippingcostshstyle;
  var $shippingcostsstyle;
  var $taxhstyle;
  var $taxstyle;
  var $prheaderstyle;
  var $prstyle;
  var $wholepagestyle;
  var $inputfieldsstyle;
}



/*******************************************************************
* File    : Address.php
* Version : $Id: Address.php,v 1.7 2008/08/08 13:07:41 wolfi Exp $
* Author  : wolfgang.schaefer@mPAY24.com
*******************************************************************/

class mPAY24_Address {
  // set-methods
  function setFirstName($name) {
     $this->firstname = substr($name, 0, 49);
  }

  function setLastName($name) {
     $this->lastname = substr($name, 0, 49);
  }

  function setStreet($street) {
     $this->street = substr($street, 0, 49);
  }

  function setZip($zip) {
  	$this->zip = substr($zip, 0, 49);
  }

  function setCity($city) {
     $this->city = substr($city, 0, 49);
  }

  function setCountry($country) {
     $this->country = substr($country, 0, 49);
  }

  function setEmail($email) {
  	$len = strlen($email);

  	if($len < 64) {
  		$this->email = $email;
} else {
  		$this->email = substr($email, 0, 63);
    }
  }

  // get-methods
  function getFirstName() {
     return $this->firstname;
  }

  function getLastName() {
     return $this->lastname;
  }

  function getStreet() {
     return $this->street;
  }

  function getCity() {
     return $this->city;
  }

  function getCountry() {
     return $this->country;
  }

  function getEmail() {
     return $this->email;
  }

  function getZip() {
     return $this->zip;
  }

  // member-variables
  var $firstname;        
  var $lastname;
  var $street;
  var $zip;
  var $city;
  var $country;
  var $email; 
}



/******************************************************************
* File    : Item.php
* Version : $Id: Item.php,v 1.4 2007/06/05 07:33:51 thomas Exp $
* Author  : wolfgang.schaefer@mPAY24.com
******************************************************************/

class mPAY24_Item {
  // set-methods
  function setNumber($num) {
     if(is_numeric($num)) {
        $this->number = $num;
     }
  }

  function setProductNr($num) {
     $this->productnr = $num;
  }

  function setDescription($desc) {
     $this->description = $desc;
  }

  function setPackage($pkg) {
     $this->package = $pkg;
  }

  function setQuantity($quantity) {
     if(is_numeric($quantity)) {
        $this->quantity = $quantity;
     }
  }

  function setItemPrice($price) {
     $this->itemprice = $price;
  }

  function setPrice($price) {
     $this->price = $price;
  }

  // get-methods
  function getNumber() {
     return $this->number;
  }

  function getProductNr() {
     return $this->productnr;
  }

  function getDescription() {
     return $this->description;
  }

  function getPackage() {
     return $this->package;
  }

  function getQuantity() {
     return $this->quantity;
  }

  function getItemPrice() {
     return $this->itemprice;
  }

  function getPrice() {
     return $this->price;
  }

  // member-variables
  var $number;
  var $productnr;
  var $description;
  var $package;
  var $quantity;
  var $itemprice;
  var $price;
}



/***************************************************************************
* File    : Selectpayment.php
* Version : $Id: SelectPayment.php,v 1.22 2009/08/18 11:41:28 thomas Exp $
* Author  : wolfgang.schaefer@mPAY24.com, thomas.langer@mPAY24.com
***************************************************************************/

class mPAY24_SelectPayment {

	// constructor
	function mPAY24_SelectPayment($url) {
		$etp_url = "https://";
	   if($url != "www")
		   $etp_url .= "test";
		else
		   $etp_url .= "www";
		$etp_url .= ".mPAY24.com/app/bin/etpv5";
		$this->etp_url = $etp_url;
	}
	
	// set-functions for POST to mPAY24
	function setMerchantID($id) {
		if(is_numeric($id)) {
		   $this->merchantid = urlencode($id);
		}
	}
	
	function setTID($tid) {
		if($tid == "") {
			$ip= (int) str_replace(".", "", $_SERVER['REMOTE_ADDR']);
			$ts= microtime();
			$rand = mt_rand(0, 100000);
			$seed = (string) $ip * $ts * $rand;
			$tid  = md5($seed);
		}
		if (strlen($tid) <= 32) {
			$this->tid = urlencode($tid);
		}
	}
	
	// public
	function setTemplateSet($set) {
		$this->templateset = "WEB";
	}
	
	// public
	function setLanguage($lang) {
		if(strlen($lang) == 2){
			$this->language = $lang;
		}
	}
	
	// public
	function setCssName($name) {
		$this->cssname = $name;
	}
	
	// public
	function setUserField($field) {
		if(strlen($field) <= 1000) {
			$this->userfield = $field;
		}
	}
	
	// public
	function setDescription($description) {
		if(strlen($description) <= 1000) {
			$this->description = $description;
		} else {
			$this->description = substr($description, 0, 1000);
		}
	}
	
	// public
	function addShippingCosts($header_value) {
		array_push($this->shippingcosts, $header_value);
	}
	
	function addTax($tax_array) {
		array_push($this->tax, $tax_array);
	}
	
	// public
	function setTotalPrice($price) {
			$this->total_price = $price;
	}
	
	// public
	function setCurrency($cur) {
		$len = strlen($cur);
		if($len == 3){
			$this->currency = $cur;
		}else{
			print "ERROR: wrong currency format specified!";
			exit;
		}
	}
	
	function addCustomer($details) {
		$this->customer_details = $details;
	}
	
	// public
	function addItem($item) {
		array_push($this->itemlist, $item);
	}
	
	function setPaymentEnab($show) {
		if($show) {
			$this->ptenable = "true";
		}
		elseif(!($show)) {
			$this->ptenable = "false";
		}
	}
	
	// public
	function addPaymentType($type_brand) {
		array_push($this->paymenttypes, $type_brand);
	}
	
	function setStyle($style) {
		$this->style = $style;
	}
	
	// public
	function setBillingAddress($addr) {
		$this->billing_addr = $addr;
	}
	
	// public
	function setXmlDir($path) {
		$this->xmldir = $path;
	}
	
	function setGetdataURL($url){
		$this->getdata_url = $url;
	}
	
	// public
	function setErrorURL($url){
		$this->error_url = $this->xmlentities($url);
	}
	
	function setSuccessURL($url){
		$this->success_url = $this->xmlentities($url);
	}
	
	function setConfirmURL($url){
		$this->confirm_url = $this->xmlentities($url);
	}
	
	// private
	function xmlentities($string, $quote_style=ENT_QUOTES) {
		static $trans;
		if (!isset($trans)) {
			$trans = get_html_translation_table(HTML_ENTITIES, $quote_style);
			foreach ($trans as $key => $value)
			$trans[$key] = '&#'.ord($key).';';
			// dont translate the '&' in case it is part of &xxx;
			$trans[chr(38)] = '&';
		}
		// after the initial translation, _do_ map standalone '&' into '&#38;'
		return preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,3};)/","&#38;" , strtr($string, $trans));
	}
	
	// public
	function createXmlFile() {
		$type = undef;
		$brand= undef;
		if(phpversion() >= 5) {
		#if(false) {
			$doc = new DOMDocument("1.0", "UTF-8");
			$doc->formatOutput = true;
			$xmlOrder = $doc->createElement('Order');
			$xmlOrder = $doc->appendChild($xmlOrder);
			if ($this->style->getLogoStyle())
				$xmlOrder->setAttribute('LogoStyle', $this->style->getLogoStyle());
			if ($this->style->getPageHeaderStyle())
				$xmlOrder->setAttribute('PageHeaderStyle', $this->style->getPageHeaderStyle());
			if ($this->style->getPageCaptionStyle())
				$xmlOrder->setAttribute('PageCaptionStyle', $this->style->getPageCaptionStyle());
			if ($this->style->getPageStyle())
				$xmlOrder->setAttribute('PageStyle', $this->style->getPageStyle());
			if ($this->style->getFooterStyle())
				$xmlOrder->setAttribute('FooterStyle', $this->style->getFooterStyle());
			if ($this->style->getWholePageStyle())
				$xmlOrder->setAttribute("Style",$this->style->getWholePageStyle());
			if ($this->style->getInputFieldsStyle())
				$xmlOrder->setAttribute("InputFieldsStyle",$this->style->getInputFieldsStyle());
			
			$xmlUserField = $doc->createElement('UserField', $this->userfield);
			$xmlUserField = $xmlOrder->appendChild($xmlUserField);
			
			$xmlTid = $doc->createElement('Tid', $this->tid);
			$xmlTid = $xmlOrder->appendChild($xmlTid);
			
			$xmlTemplateSet = $doc->createElement('TemplateSet', $this->templateset);
			$xmlTemplateSet = $xmlOrder->appendChild($xmlTemplateSet);
			$xmlTemplateSet->setAttribute('Language', $this->language);
			
			$xmlPaymentTypes = $doc->createElement('PaymentTypes');
			if(!empty($this->paymenttypes)) {
				$xmlPaymentTypes = $xmlOrder->appendChild($xmlPaymentTypes);
				if($this->ptenable)
					$xmlPaymentTypes->setAttribute('Enable', $this->ptenable);
				for($i = 0; $i < count($this->paymenttypes); $i++) {
					$xmlPayment = $doc->createElement('Payment');
					$xmlPayment = $xmlPaymentTypes->appendChild($xmlPayment);
					if(strpos($this->paymenttypes[$i], ",") == false) {
						$xmlPayment->setAttribute('Type', $this->paymenttypes[$i]);
					} else {
						list($type, $brand) = split(",", $this->paymenttypes[$i]);
						$xmlPayment->setAttribute('Type', $type);
						$xmlPayment->setAttribute('Brand', $brand);
					}
				}
			}
			
			$xmlShoppingCart = $doc->createElement('ShoppingCart');
			$xmlShoppingCart = $xmlOrder->appendChild($xmlShoppingCart);
			if ($this->style->getSCStyle())
				$xmlShoppingCart->setAttribute('Style', $this->style->getSCStyle());
			if ($this->style->getSCHeader())
				$xmlShoppingCart->setAttribute('Header', $this->style->getSCHeader());
			if ($this->style->getSCHeaderStyle())
				$xmlShoppingCart->setAttribute('HeaderStyle', $this->style->getSCHeaderStyle());
			if ($this->style->getSCCaptionStyle())
				$xmlShoppingCart->setAttribute('CaptionStyle', $this->style->getSCCaptionStyle());
			if ($this->style->getSCNumberStyle())
				$xmlShoppingCart->setAttribute('NumberStyle', $this->style->getSCNumberStyle());
			if ($this->style->getSCProductNrStyle())
				$xmlShoppingCart->setAttribute('ProductNrStyle', $this->style->getSCProductNrStyle());
			if ($this->style->getSCDescriptionStyle())
				$xmlShoppingCart->setAttribute('DescriptionStyle', $this->style->getSCDescriptionStyle());
			if ($this->style->getSCPackageStyle())
				$xmlShoppingCart->setAttribute('PackageStyle', $this->style->getSCPackageStyle());
			if ($this->style->getSCQuantityStyle())
				$xmlShoppingCart->setAttribute('QuantityStyle', $this->style->getSCQuantityStyle());
			if ($this->style->getSCItemPriceStyle())
				$xmlShoppingCart->setAttribute('ItemPriceStyle', $this->style->getSCItemPriceStyle());
			if ($this->style->getSCPriceStyle())
				$xmlShoppingCart->setAttribute('PriceStyle', $this->style->getSCPriceStyle());
			$xmlDescription  = $doc->createElement('Description', $this->description);
			$xmlDescription  = $xmlShoppingCart->appendChild($xmlDescription);
			
			for($i = 0; $i < count($this->itemlist); $i++) {
				$xmlItem = $doc->createElement('Item');
				$xmlItem = $xmlShoppingCart->appendChild($xmlItem);
				if($this->itemlist[$i]->getNumber()){
					$xmlNumber  = $doc->createElement('Number', $this->xmlentities($this->itemlist[$i]->getNumber()));
					$xmlNumber  = $xmlItem->appendChild($xmlNumber);
				}
				if($this->itemlist[$i]->getProductNr()){
					$xmlProductNumber = $doc->createElement('ProductNr', $this->xmlentities($this->itemlist[$i]->getProductNr()));
					$xmlProductNumber = $xmlItem->appendChild($xmlProductNumber);
				}
				if($this->itemlist[$i]->getDescription()){
					$xmlDescription= $doc->createElement('Description', $this->xmlentities($this->itemlist[$i]->getDescription()));
					$xmlDescription= $xmlItem->appendChild($xmlDescription);
				}
				if($this->itemlist[$i]->getPackage()){
					$xmlPackage = $doc->createElement('Package', $this->xmlentities($this->itemlist[$i]->getPackage()));
					$xmlPackage = $xmlItem->appendChild($xmlPackage);
				}
				if($this->itemlist[$i]->getQuantity()){
					$xmlQuantity= $doc->createElement('Quantity', $this->xmlentities($this->itemlist[$i]->getQuantity()));
					$xmlQuantity= $xmlItem->appendChild($xmlQuantity);
				}
				if($this->itemlist[$i]->getItemPrice()){
					$xmlItemPrice  = $doc->createElement('ItemPrice', $this->xmlentities(number_format($this->itemlist[$i]->getItemPrice(),2,'.','')));
					$xmlItemPrice  = $xmlItem->appendChild($xmlItemPrice);
				}
				if($this->itemlist[$i]->getPrice()){
					$xmlPrice= $doc->createElement('Price', $this->xmlentities(number_format($this->itemlist[$i]->getPrice(),2,'.','')));
					$xmlPrice= $xmlItem->appendChild($xmlPrice);
				}
				if(($i % 2) && $this->style->getSCItemEvenStyle()) {
					if($xmlNumber)
						$xmlNumber->setAttribute('Style', $this->style->getSCItemEvenStyle());
					if($xmlProductNumber)
						$xmlProductNumber->setAttribute('Style', $this->style->getSCItemEvenStyle());
					if($xmlDescription)
						$xmlDescription->setAttribute('Style', $this->style->getSCItemEvenStyle());
					if($xmlPackage)
						$xmlPackage->setAttribute('Style', $this->style->getSCItemEvenStyle());
					if($xmlQuantity)
						$xmlQuantity->setAttribute('Style', $this->style->getSCItemEvenStyle());
					if($xmlItemPrice)
						$xmlItemPrice->setAttribute('Style', $this->style->getSCItemEvenStyle());
					if($xmlPrice)
						$xmlPrice->setAttribute('Style', $this->style->getSCItemEvenStyle());
				} elseif(!($i % 2) && $this->style->getSCItemOddStyle()) {
					if($xmlNumber)
						$xmlNumber->setAttribute('Style', $this->style->getSCItemOddStyle());
					if($xmlProductNumber)
						$xmlProductNumber->setAttribute('Style', $this->style->getSCItemOddStyle());
					if($xmlDescription)
						$xmlDescription->setAttribute('Style', $this->style->getSCItemOddStyle());
					if($xmlPackage)
						$xmlPackage->setAttribute('Style', $this->style->getSCItemOddStyle());
					if($xmlQuantity)
						$xmlQuantity->setAttribute('Style', $this->style->getSCItemOddStyle());
					if($xmlItemPrice)
						$xmlItemPrice->setAttribute('Style', $this->style->getSCItemOddStyle());
					if($xmlPrice)
						$xmlPrice->setAttribute('Style', $this->style->getSCItemOddStyle());
				}
			}
			
			for($i = 0; $i < count($this->shippingcosts); $i++) {
				if(strpos($this->shippingcosts[$i], ",") == false) {
					$xmlShippingCosts = $doc->createElement('ShippingCosts', number_format($this->shippingcosts[$i],2,'.',''));
					$xmlShippingCosts = $xmlShoppingCart->appendChild($xmlShippingCosts);
				} else {
					list($header, $value) = split(",", $this->shippingcosts[$i]);
					$xmlShippingCosts = $doc->createElement('ShippingCosts', number_format($value,2,'.',''));
					$xmlShippingCosts = $xmlShoppingCart->appendChild($xmlShippingCosts);
					$xmlShippingCosts->setAttribute('Header', $this->xmlentities($header));
				}
				if($this->style->getShippingCostsHStyle())
					$xmlShippingCosts->setAttribute('HeaderStyle', $this->style->getShippingCostsHStyle());
				if($this->style->getShippingCostsStyle())
					$xmlShippingCosts->setAttribute('Style', $this->style->getShippingCostsStyle());
			}
			
			for($i = 0; $i < count($this->tax); $i++) {
				if(strpos($this->tax[$i], ",") == false) {
					$xmlTax = $doc->createElement('Tax', number_format($this->tax[$i],2,'.',''));
					$xmlTax = $xmlShoppingCart->appendChild($xmlTax);
				} else {
					list($value1, $value2, $value3) = split(",", $this->tax[$i]);
					if($value3) {
						$xmlTax = $doc->createElement('Tax', number_format($value3,2,'.',''));
						$xmlTax = $xmlShoppingCart->appendChild($xmlTax);
						$xmlTax->setAttribute('Percent', $value1);
						$xmlTax->setAttribute('Header', $value2);
					}
					elseif(is_numeric($value1) && $value3 == false) {
						$xmlTax = $doc->createElement('Tax', number_format($value2,2,'.',''));
						$xmlTax = $xmlShoppingCart->appendChild($xmlTax);
						$xmlTax->setAttribute('Percent', $value1);
					} else {
						$xmlTax = $doc->createElement('Tax', number_format($value2,2,'.',''));
						$xmlTax = $xmlShoppingCart->appendChild($xmlTax);
						$xmlTax->setAttribute('Header', $value1);
					}
				}
				if($this->style->getTaxHStyle())
					$xmlTax->setAttribute('HeaderStyle', $this->style->getTaxHStyle());
				if($this->style->getTaxStyle())
					$xmlTax->setAttribute('Style', $this->style->getTaxStyle());
			}
			$xmlPrice = $doc->createElement('Price', $this->total_price);
			$xmlPrice = $xmlOrder->appendChild($xmlPrice);
			if ($this->style->getPRHeader())
				$xmlPrice->setAttribute('Header', $this->style->getPRHeader());
			if ($this->style->getPRHeaderStyle())
				$xmlPrice->setAttribute('HeaderStyle', $this->style->getPRHeaderStyle());
			if ($this->style->getPRStyle())
				$xmlPrice->setAttribute('Style',  $this->style->getPRStyle());
			
			if($this->currency){
				$xmlCurrency= $doc->createElement('Currency', $this->currency);
				$xmlCurrency= $xmlOrder->appendChild($xmlCurrency);
				$xmlCurrency->setAttribute('Display', $this->currency);
			}
			
			if($this->customer_details) {
				list($id, $set)= split(",", $this->customer_details);
				$xmlCustomer= $doc->createElement('Customer');
				$xmlCustomer= $xmlOrder->appendChild($xmlCustomer);
				$xmlCustomer->setAttribute('Id', $id);
				$xmlCustomer->setAttribute('UseProfile', $set);
			}
			
			$xmlBillingAddr= $doc->createElement('BillingAddr');
			$xmlBillingAddr= $xmlOrder->appendChild($xmlBillingAddr);
			$xmlBillingAddr->setAttribute('Mode', 'ReadWrite');
			$xmlFirstName  = $doc->createElement('FirstName', utf8_encode($this->billing_addr->getFirstName()));
			$xmlFirstName  = $xmlBillingAddr->appendChild($xmlFirstName);
			$xmlLastName= $doc->createElement('LastName', utf8_encode($this->billing_addr->getLastName()));
			$xmlLastName= $xmlBillingAddr->appendChild($xmlLastName);
			$xmlStreet  = $doc->createElement('Street', utf8_encode($this->billing_addr->getStreet()));
			$xmlStreet  = $xmlBillingAddr->appendChild($xmlStreet);
			$xmlZip  = $doc->createElement('Zip', utf8_encode($this->billing_addr->getZip()));
			$xmlZip  = $xmlBillingAddr->appendChild($xmlZip);
			$xmlCity = $doc->createElement('City', utf8_encode($this->billing_addr->getCity()));
			$xmlCity = $xmlBillingAddr->appendChild($xmlCity);
			$xmlCountry = $doc->createElement('Country', utf8_encode($this->billing_addr->getCountry()));
			$xmlCountry = $xmlBillingAddr->appendChild($xmlCountry);
			$xmlEmail= $doc->createElement('Email', utf8_encode($this->billing_addr->getEmail()));
			$xmlEmail= $xmlBillingAddr->appendChild($xmlEmail);
			
			$xmlURL = $doc->createElement('URL');
			$xmlURL = $xmlOrder->appendChild($xmlURL);
			$xmlSuc = $doc->createElement("Success",$this->xmlentities($this->success_url));
			$xmlSuc = $xmlURL->appendChild($xmlSuc);
			$xmlErr = $doc->createElement("Error",$this->xmlentities($this->error_url));
			$xmlErr = $xmlURL->appendChild($xmlErr);
			$xmlCon = $doc->createElement("Confirmation",$this->xmlentities($this->confirm_url));
			$xmlCon = $xmlURL->appendChild($xmlCon);
			
			$this->xmlfile  = $doc->saveXML();
		} else {
			$this->xmlfile = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n" . 
			"<Order";
			if ($this->style->getLogoStyle())
				$this->xmlfile .= " LogoStyle=\"" . $this->style->getLogoStyle() . "\"";
			if ($this->style->getPageHeaderStyle())
				$this->xmlfile .= " PageHeaderStyle=\"" . $this->style->getPageHeaderStyle() . "\"";
			if ($this->style->getPageCaptionStyle())
				$this->xmlfile .= " PageCaptionStyle=\"" . $this->style->getPageCaptionStyle() . "\"";
			if ($this->style->getPageStyle())
				$this->xmlfile .= " PageStyle=\"" . $this->style->getPageStyle() . "\"";
			if ($this->style->getFooterStyle())
				$this->xmlfile .= " FooterStyle=\"" . $this->style->getFooterStyle() . "\"";
			if ($this->style->getWholePageStyle())
				$this->xmlfile .= " Style=\"" . $this->style->getWholePageStyle() . "\"";
			if ($this->style->getInputFieldsStyle())
				$this->xmlfile .= " InputFieldsStyle=\"" . $this->style->getInputFieldsStyle() . "\"";
			$this->xmlfile .= ">\n";
			
			if (!empty($this->userfield))
				$this->xmlfile .= " <UserField>" . $this->userfield . "</UserField>\n";
			$this->xmlfile .= " <Tid>" . $this->tid . "</Tid>\n";
			
			if (!empty($this->TemplateSet) || !empty($this->language)) {
				$this->xmlfile .= " <TemplateSet";
				if(!empty($this->language))
					$this->xmlfile .= " Language=\"" . $this->language . "\"";
				$this->xmlfile .= ">" . $this->TemplateSet . "</TemplateSet>\n";
			}
			
			if(!empty($this->paymenttypes)) {
				$this->xmlfile .= " <PaymentTypes";
				if ($this->ptenable)
					$this->xmlfile .= " Enable=\"" . $this->ptenable . "\"";
				$this->xmlfile .= ">\n";
				for ($i = 0; $i < count($this->paymenttypes); $i++) {
					$this->xmlfile .= "  <Payment";
					if(strpos($this->paymenttypes[$i], ",") == false) {
						$this->xmlfile .= " Type=\"" . $this->paymenttypes[$i] . "\"";
					} else {
						list($type, $brand) = split(",", $this->paymenttypes[$i]);
						$this->xmlfile .= " Type=\"" . $type . "\"" .
						" Brand=\"" . $brand . "\"";
					}
					$this->xmlfile .= "/>\n";
				}
				$this->xmlfile .= " </PaymentTypes>\n";
			}
			
			if ($this->style->getSCStyle()||
				$this->style->getSCHeader()||
				$this->style->getSCHeaderStyle() ||
				$this->style->getSCCaptionStyle()||
				$this->style->getSCNumberStyle() ||
				$this->style->getSCProductNrStyle() ||
				$this->style->getSCDescriptionStyle()  ||
				$this->style->getSCPackageStyle()||
				$this->style->getSCQuantityStyle()  ||
				$this->style->getSCItemPriceStyle() ||
				$this->style->getSCPriceStyle()  ||
				!empty($this->description) ||
				!empty($this->itemlist))
			{
				$this->xmlfile .= " <ShoppingCart";
				if ($this->style->getSCStyle())
					$this->xmlfile .= " Style=\"" . $this->style->getSCStyle() . "\"";
				if ($this->style->getSCHeader())
					$this->xmlfile .= " Header=\"" . $this->style->getSCHeader() . "\"";
				if ($this->style->getSCHeaderStyle())
					$this->xmlfile .= " HeaderStyle=\"" . $this->style->getSCHeaderStyle() . "\"";
				if ($this->style->getSCCaptionStyle())
					$this->xmlfile .= " CaptionStyle=\"" . $this->style->getSCCaptionStyle() . "\"";
				if ($this->style->getSCNumberStyle())
					$this->xmlfile .= " NumberStyle=\"" . $this->style->getSCNumberStyle() . "\"";
				if ($this->style->getSCProductNrStyle())
					$this->xmlfile .= " ProductNrStyle=\"" . $this->style->getSCProductNrStyle() . "\"";
				if ($this->style->getSCDescriptionStyle())
					$this->xmlfile .= " DescriptionStyle=\"" . $this->style->getSCDescriptionStyle() . "\"";
				if ($this->style->getSCPackageStyle())
					$this->xmlfile .= " PackageStyle=\"" . $this->style->getSCPackageStyle() . "\"";
				if ($this->style->getSCQuantityStyle())
					$this->xmlfile .= " QuantityStyle=\"" . $this->style->getSCQuantityStyle() . "\"";
				if ($this->style->getSCItemPriceStyle())
					$this->xmlfile .= " ItemPriceStyle=\"" . $this->style->getSCItemPriceStyle() . "\"";
				if ($this->style->getSCPriceStyle())
					$this->xmlfile .= " PriceStyle=\"" . $this->style->getSCPriceStyle() . "\"";
				$this->xmlfile .= ">\n";
				if (!empty($this->description))
					$this->xmlfile .= "  <Description>" . $this->description . "</Description>\n";
				for($i = 0; $i < count($this->itemlist); $i++) {
					$this->xmlfile .= "  <Item>\n";
					if ($this->itemlist[$i]->getNumber()) {
						$this->xmlfile .= "<Number";
						if (($i % 2) && $this->style->getSCItemEvenStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemEvenStyle() . "\"";
						elseif (!($i % 2) && $this->style->getSCItemOddStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemOddStyle() . "\"";
						$this->xmlfile .= ">" . $this->itemlist[$i]->getNumber() . "</Number>\n";
					}
					if ($this->itemlist[$i]->getProductNr()) {
						$this->xmlfile .= "<ProductNr";
						if (($i % 2) && $this->style->getSCItemEvenStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemEvenStyle() . "\"";
						elseif (!($i % 2) && $this->style->getSCItemOddStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemOddStyle() . "\"";
						$this->xmlfile .= ">" . $this->itemlist[$i]->getProductNr() . "</ProductNr>\n";
					}
					if ($this->itemlist[$i]->getDescription()) {
						$this->xmlfile .= "<Description";
						if (($i % 2) && $this->style->getSCItemEvenStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemEvenStyle() . "\"";
						elseif (!($i % 2) && $this->style->getSCItemOddStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemOddStyle() . "\"";
						$this->xmlfile .= ">" . $this->itemlist[$i]->getDescription() . "</Description>\n";
					}
					if ($this->itemlist[$i]->getPackage()) {
						$this->xmlfile .= "<Package";
						if (($i % 2) && $this->style->getSCItemEvenStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemEvenStyle() . "\"";
						elseif (!($i % 2) && $this->style->getSCItemOddStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemOddStyle() . "\"";
						$this->xmlfile .= ">" . $this->itemlist[$i]->getPackage() . "</Package>\n";
					}
					if ($this->itemlist[$i]->getQuantity()) {
						$this->xmlfile .= "<Quantity";
						if (($i % 2) && $this->style->getSCItemEvenStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemEvenStyle() . "\"";
						elseif (!($i % 2) && $this->style->getSCItemOddStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemOddStyle() . "\"";
						$this->xmlfile .= ">" . $this->itemlist[$i]->getQuantity() . "</Quantity>\n";
					}
					if ($this->itemlist[$i]->getItemPrice()) {
						$this->xmlfile .= "<ItemPrice";
						if (($i % 2) && $this->style->getSCItemEvenStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemEvenStyle() . "\"";
						elseif (!($i % 2) && $this->style->getSCItemOddStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemOddStyle() . "\"";
						$this->xmlfile .= ">" . number_format($this->itemlist[$i]->getItemPrice(),2,'.','') . "</ItemPrice>\n";
					}
					if ($this->itemlist[$i]->getPrice()) {
						$this->xmlfile .= "<Price";
						if (($i % 2) && $this->style->getSCItemEvenStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemEvenStyle() . "\"";
						elseif (!($i % 2) && $this->style->getSCItemOddStyle())
							$this->xmlfile .= " Style=\"" . $this->style->getSCItemOddStyle() . "\"";
						$this->xmlfile .= ">" . number_format($this->itemlist[$i]->getPrice(),2,'.','') . "</Price>\n";
					}
					$this->xmlfile .= "  </Item>\n";
				}
				for($i = 0; $i < count($this->shippingcosts); $i++) {
					$this->xmlfile .= "  <ShippingCosts";
					if($this->style->getShippingCostsHStyle())
						$this->xmlfile .= " HeaderStyle=\"" . $this->style->getShippingCostsHStyle() . "\"";
					if($this->style->getShippingCostsStyle())
						$this->xmlfile .= " Style=\"" . $this->style->getShippingCostsStyle() . "\"";
					if(strpos($this->shippingcosts[$i], ",") == false)
						$this->xmlfile .= ">" . number_format($this->shippingcosts[$i],2,'.','') . "</ShippingCosts>\n";
					else {
						list($header, $value) = split(",", $this->shippingcosts[$i]);
						$this->xmlfile .= " Header=\"" . $header . "\">" . number_format($value,2,'.','') . "</ShippingCosts>\n";
					}
				}
				for($i = 0; $i < count($this->tax); $i++) {
					$this->xmlfile .= "  <Tax";
					if($this->style->getTaxHStyle())
						$this->xmlfile .= " HeaderStyle=\"" . $this->style->getTaxHStyle() . "\"";
					if($this->style->getTaxStyle())
						$this->xmlfile .= " Style=\"" . $this->style->getTaxStyle() . "\"";
					if(strpos($this->tax[$i], ",") == false)
						$this->xmlfile .= ">" . number_format($this->tax[$i],2,'.','') . "</Tax>\n";
					else {
						list($value1, $value2, $value3) = split(",", $this->tax[$i]);
						if($value3)
							$this->xmlfile .= " Percent=\"" . $value1 . "\" Header=\"" . $value2 . "\">" . number_format($value3,2,'.','') . "</Tax>\n";
						elseif(is_numeric($value1) && $value3 == false)
							$this->xmlfile .= " Percent=\"" . $value1 . "\">" . number_format($value2,2,'.','') . "</Tax>\n";
						else
							$this->xmlfile .= " Header=\"" . $value1 . "\">" . number_format($value2,2,'.','') . "</Tax>\n";
					}
				}
				$this->xmlfile .= " </ShoppingCart>\n";
			}
			$this->xmlfile .= " <Price";
			if ($this->style->getPRHeader())
				$this->xmlfile .= " Header=\"" . $this->style->getPRHeader() . "\"";
			if ($this->style->getPRHeaderStyle())
				$this->xmlfile .= " HeaderStyle=\"" . $this->style->getPRHeaderStyle() . "\"";
			if ($this->style->getPRStyle())
				$this->xmlfile .= " Style=\"" . $this->style->getPRStyle() . "\"";
			$this->xmlfile .= ">" . number_format($this->total_price,2,'.','') . "</Price>\n";
			if($this->currency)
				$this->xmlfile .= " <Currency>" . $this->currency . "</Currency>\n";
			if($this->customer_details) {
				list($id, $set)= split(",", $this->customer_details);
				$this->xmlfile .= " <Customer Id=\"" . $id . "\" UseProfile=\"" . $set . "\"/>\n";
			}
			if($this->billing_addr) {
				$this->xmlfile .= " <BillingAddr Mode=\"ReadWrite\">\n";
				$this->xmlfile .= "  <FirstName>" . $this->xmlentities($this->billing_addr->getFirstName()) . "</FirstName>\n";
				$this->xmlfile .= "  <LastName>"  . $this->xmlentities($this->billing_addr->getLastName())  . "</LastName>\n";
				$this->xmlfile .= "  <Street>" . $this->xmlentities($this->billing_addr->getStreet()) . "</Street>\n";
				$this->xmlfile .= "  <Zip>" . $this->xmlentities($this->billing_addr->getZip()) . "</Zip>\n";
				$this->xmlfile .= "  <City>". $this->xmlentities($this->billing_addr->getCity()). "</City>\n";
				$this->xmlfile .= "  <Country>". $this->xmlentities($this->billing_addr->getCountry()). "</Country>\n";
				  if($this->billing_addr->getEmail())
				  $this->xmlfile .= "  <Email>" . $this->xmlentities($this->billing_addr->getEmail()) . "</Email>\n";
				$this->xmlfile .= " </BillingAddr>\n";
			}
			
			if($this->success_url || $this->error_url || $this->confirm_url){
				$this->xmlfile .= " <URL>\n";
				if($this->success_url)
					$this->xmlfile .= "  <Success>" . $this->xmlentities($this->success_url) . "</Success>\n";
				if($this->error_url)
					$this->xmlfile .= "  <Error>" . $this->xmlentities($this->error_url) . "</Error>\n";
				if($this->confirm_url)
					$this->xmlfile .= "  <Confirmation>" . $this->xmlentities($this->confirm_url) . "</Confirmation>\n";
				$this->xmlfile .= " </URL>\n";
			}
			
			$this->xmlfile .= "</Order>";
		}
	}
	// end function createXmlFile()
	
	// public
	function getXmlFile() {
		return $this->xmlfile;
	}
	
	// public
	function saveXmlFile() {
		$filename = $this->xmldir . "/" . $this->tid . ".xml";
		if(! $fh = fopen($filename, 'w')) {
			echo "Cannot open file ($filename)";
			exit(1);
		}
		if(fwrite($fh, $this->xmlfile) == FALSE) {
			echo "Cannot write to file ($filename)";
			exit(1);
		}
		fclose($fh);
	}
	
	// private
	function isValid() {
		$status = true;
		// check for mandatory parameters
		if($this->merchantid == "") {
			print("Error : No/Wrong MERCHANTID specified !<br>\n");
			$status = false;
		}
		if($this->tid == "") {
			print("Error : No/Wrong TID specified !<br>\n");
			$status = false;
		}
		
		return $status;
	}

     // private
     function buildRequest() {
        $this->request = "OPERATION="   . urlencode($this->operation)  . "&" .
           "MERCHANTID="  . urlencode($this->merchantid) . "&" .
           "TID="         . urlencode($this->tid)        . "&" .
           "MDXI="        . urlencode($this->xmlfile)    . "&" .
           "GETDATA_URL=" . urlencode($this->getdata_url);
     }
	
     // public
     function send() {
        if($this->isValid()) {
           
           /*
            * if SOAP is installed, try to perform a SOAP request
            * else, try to use cURL
            * else, throw an error message that neither of those are installed
            */
           
           if(class_exists("SoapClienttt")){
           #if(false){
              $client = new SoapClient('https://www.mPAY24.com/soap/etp/1.4/ETP.wsdl',array(   // TODO: Move authentication information to a more secure area,
                                                                                               // e.g. an authentication file outside the web servers
                                                                                               // document root.
                                                                                               'login'        => 'u' . $this->merchantid,
                                                                                               'password'     => 'password',
                                                                                               
                                                                                               // TODO: Move proxy information to a more secure area,
                                                                                               // e.g. a configuration file outside the web servers
                                                                                               // document root.
                                                                                               #'proxy_host'  => 'aaa.bbb.ccc.ddd',
                                                                                               #'proxy_port'  => 'port',
                                                                                               #...
                                                                                               
                                                                                               // exceptions => FALSE to cleanly catch errors
                                                                                               'exceptions'   => FALSE));
              
              // if we are on test system, override the Location set within the WSDL
              if($this->etp_url == "https://test.mPAY24.com/app/bin/etpv5")
                 $client->__setLocation('https://test.mPAY24.com/app/bin/etpproxy_v14');
                 
              // perform the request
              $this->response = $client->SelectPayment(array( 'mdxi'         => $this->xmlfile,
                                                              'merchantID'   => $this->merchantid,
                                                              'getDataURL'   => '',
                                                              'tid'          => ''));
           }elseif(function_exists("curl_init")){
           #}if(false){
              
              // build Request
              $this->buildRequest();
              
              // send request;
              $ch = curl_init($this->etp_url);
              curl_setopt($ch, CURLOPT_HEADER, 0);
              curl_setopt($ch, CURLOPT_POST, 1);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
              
              // TODO: Move proxy information to a more secure area,
              // e.g. a configuration file outside the web servers
              // document root.
              #curl_setopt($ch, CURLOPT_PROXY, 'aaa.bbb.ccc.ddd:port');
              #...
              
              // NOT FOR PRODUCTION USE
              #curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
              
              // uncomment the following line in case you experience trouble regarding cURL SSL certificate errors
              // Plese download cacert.pem from http://curl.haxx.se/docs/caextract.html
              #curl_setopt($ch, CURLOPT_CAINFO, "./cacert.pem");
              
              // get response
              $this->response = curl_exec($ch);
              
              // catch errors
              $ch_error = curl_error($ch);
              if(empty($this->response) || !empty($ch_error)){
                 $this->response = "STATUS=ERROR&RETURNCODE=cURL+-+" . urlencode(curl_error($ch));
              }
              curl_close($ch);
           }else{
              $this->response = "STATUS=ERROR&RETURNCODE=Your+PHP+installation+does+not+meet+either+of+the+following+requirements%3A%3Cul%3E%3Cli%3E%3Ca+href%3D%27http%3A%2F%2Fwww.php.net%2FcURL%27%3EcURL%3C%2Fa%3E+or%3C%2Fli%3E%3Cli%3E%3Ca+href%3D%27http%3A%2F%2Fwww.php.net%2FSOAP%27%3ESOAP%3C%2Fa%3E%3C%2Fli%3E%3C%2Ful%3E";
           }
        }
     }
	
     // public
     function parseResponse() {
        
        // if SOAP was used, response is returned as an object
        if(is_object($this->response)){
           if(is_soap_fault($this->response)){
              $this->STATUS = "ERROR";
              $this->RETURNCODE = 'SOAP - ' . $this->response->faultstring . '&nbsp;(' . $this->response->faultcode . ')';
           }else{
              $this->STATUS     = strtoupper($this->response->out->status);
              $this->RETURNCODE = strtoupper($this->response->out->returnCode);
              $this->LOCATION   = $this->response->out->location;
           }
           
        // cURL returns a urlencoded text/plain response
        }else{
           $parameters = split("&", $this->response);
           
           for($i=0; $i<count($parameters);$i++) {
              list($html_resp[$i]['name'],$html_resp[$i]['value'])  = split("=",$parameters[$i]);
              if(strtoupper($html_resp[$i]['name']) == 'STATUS')     $this->STATUS     = urldecode(strtoupper($html_resp[$i]['value']));
              if(strtoupper($html_resp[$i]['name']) == 'RETURNCODE') $this->RETURNCODE = urldecode(strtoupper($html_resp[$i]['value']));
              if(strtoupper($html_resp[$i]['name']) == 'LOCATION')   $this->LOCATION   = urldecode($html_resp[$i]['value']);
           }
        }
     }
	
	function getResponse() {
		return $this->response;
	}
	
  function getSTATUS(){
     return $this->STATUS;
  }
	
  function getRETURNCODE(){
     return $this->RETURNCODE;
  }
	
	function getLOCATION(){
	   return $this->LOCATION;
	}		
	
	// member-variables
	var $etp_url;
	var $merchantid;
	var $tid;
	var $templateset;
	var $language = "DE";
	var $cssname;
	var $description;
	var $shippingcosts	= array();
	var $tax			= array();
	var $total_price;
	var $currency;
	var $customer_details;
	var $ptenable;
	var $itemlist		= array();
	var $paymenttypes	= array();
	var $style;					// Object
	var $billing_addr;			// Object
	var $decimal_point	= ".";	// set your preferred decimal point here
	var $xmldir;
	var $xmlfile;
	var $STATUS;
	var $operation		= "SELECTPAYMENT";
	var $request;
	var $RETURNCODE;
	var $LOCATION;
	var $mpaytid;
	var $error_url;
	var $response;
	var $success_url;
	var $confirm_url;
	var $getdata_url;
}