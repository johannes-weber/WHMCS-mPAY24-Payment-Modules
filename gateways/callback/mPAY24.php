<?
/**
 * WHMCS Paymentmodule - Callback fole for mPay24 (all) Payment Gateway
 * 
 * @author Johannes Weber <jw@internex.at>
 * @version $Id:$
 */
$gatewayModule = "mPAY24";

if($_GET['confirm'] == 'true' && $_GET['OPERATION'] == 'CONFIRMATION' && $_GET['STATUS'] == 'BILLED')
{
	include("../../../dbconnect.php");
	include("../../../includes/functions.php");
	include("../../../includes/gatewayfunctions.php");
	include("../../../includes/invoicefunctions.php");
	
	$gateway = getGatewayVariables($gatewayModule);
	
	$id_invoice = $_GET['TID'];
	$token = $_GET['token'];
	$id_user = $_GET['id_user'];
	$amount = number_format($_GET['PRICE'] / 100, 2, '.', '');
	$paymentmethod = $_GET['paymentmethod'];
	$id_transaction_mpay24 = $_GET['MPAYTID'];

	$status = $_GET['STATUS'];
	$description = urldecode($_GET['description']);

	$hashKeyExtension = (!empty($gateway['hashKeyExtension'])) ? $gateway['hashKeyExtension'] : '';
	$tokenGenerated = sha1($hashKeyExtension.md5($_SERVER['HTTP_HOST'] . sha1($id_user*100/23.5) . md5($amount) . $description . $_SERVER['SERVER_ADDR'] . 'BT'));
		
	try
	{
		if($token == $tokenGenerated)
		{
			if (!$gateway["type"]) throw new Exception("Module Not Activated");

			$invoiceid = checkCbInvoiceID($id_invoice, $gateway["name"]);			
			checkCbTransID($id_transaction_mpay24);
			addInvoicePayment($id_invoice, $id_transaction_mpay24, $amount, 0, $gatewayModule);
			logTransaction($gatewayModule, $_GET, "Successful");
			sendMessage("mPAY24 (all) Payment Confirmation", $id_invoice);
			echo "OK: STATUS received; Invoice marked as paid";
			if(isset($gateway['emailNotification']) && 'on' == $gateway['emailNotification'] && !empty($gateway['notificationRecipients']))
			{
				$subject = empty($gateway['notificationSubject']) ? 'WHMCS order notification' : $gateway['notificationSubject'];
				$senderName = empty($gateway['notificationSenderName']) ? 'WHMCS ordernotification' : $gateway['notificationSenderName'];
				$senderEmail = empty($gateway['notificationSenderEmail']) ? 'noreply@noreply.com' : $gateway['notificationSenderEmail'];
				$tempRecipients = explode(',', $gateway['notificationRecipients']);
				$header = 'From: '.$senderName.'' . "\r\n" .
				    'Reply-To: '.$senderEmail.'' . "\r\n" .
				    'X-Mailer: PHP/' . phpversion();
				
				$content = 'Order notification from Module '. $gateway['paymentmethod'] . '
					
Invoice ID: ' . $id_invoice . '

Amount: ' . $amount. '

Order Details:
User ID: ' . $id_user . '					
mPAY24 TID: ' . $id_transaction_mpay24 . '					
Description: ' . $description;
				
				foreach ($tempRecipients as $email)
				{
					$recipient = trim($email);
					mail($recipient, $subject, $content, $header);
				}
			}
		}
		else
		{
			throw new InvalidArgumentException('Requestet token != generated token!');
		}
	}
	catch(Exception $e)
	{
		$_GET['EXCEPTION'] = $e->getMessage();
		$_GET['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
		logTransaction($gatewayModule, $_GET, "Error");
		echo "ERROR: " . $e->getMessage();
	}
}