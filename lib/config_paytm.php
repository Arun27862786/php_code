<?php
/*
- Use PAYTM_ENVIRONMENT as 'PROD' if you wanted to do transaction in production environment else 'TEST' for doing transaction in testing environment.
- Change the value of PAYTM_MERCHANT_KEY constant with details received from Paytm.
- Change the value of PAYTM_MERCHANT_MID constant with details received from Paytm.
- Change the value of PAYTM_MERCHANT_WEBSITE constant with details received from Paytm.
- Above details will be different for testing and production environment.
*/

//define('PAYTM_ENVIRONMENT', 'PROD'); // PROD   
define('PAYTM_ENVIRONMENT', 'TEST'); //    TEST
define('CALLBACKURL', 'https://score.fancy11.com/PaytmResponse');
//define('CALLBACKURL', 'http://172.105.49.94/PaytmResponse');
//define('CALLBACKURL_MOBILE_APP', 'https://fancy11.com/fancy11/public/walletcallback');
define('CALLBACKURL_MOBILE_APP', 'http://172.105.49.94/fancy11/public/walletcallback');

//define('PAYTM_MERCHANT_KEY', 'vveK!@jMbiBt8RrW'); 
//define('PAYTM_MERCHANT_MID', 'ZGejgP49536722097116'); 
define('PAYTM_MERCHANT_KEY', 'PjRL9u701NyJgcoh'); 
define('PAYTM_MERCHANT_MID', 'LEKAmc18992160495420');
define('PAYTM_MERCHANT_WEBSITE', 'DEFAULT'); 
define('INDUSTRY_TYPE_ID', 'Retail');
define('CHANNEL_ID', 'WEB');


$PAYTM_STATUS_QUERY_NEW_URL='https://securegw-stage.paytm.in/merchant-status/getTxnStatus';
$PAYTM_TXN_URL='https://securegw-stage.paytm.in/theia/processTransaction';
if (PAYTM_ENVIRONMENT == 'PROD') {
	$PAYTM_STATUS_QUERY_NEW_URL='https://securegw.paytm.in/merchant-status/getTxnStatus';
	$PAYTM_TXN_URL='https://securegw.paytm.in/theia/processTransaction';
}

define('PAYTM_REFUND_URL', '');
define('PAYTM_STATUS_QUERY_URL', $PAYTM_STATUS_QUERY_NEW_URL);
define('PAYTM_STATUS_QUERY_NEW_URL', $PAYTM_STATUS_QUERY_NEW_URL);
define('PAYTM_TXN_URL', $PAYTM_TXN_URL);

?>
