<?php
set_time_limit(0);
use Slim\Http\Request;
use Slim\Http\Response;
use \Firebase\JWT\JWT;

require_once("lib/config_paytm.php");
require_once("lib/encdec_paytm.php");

require_once("rconfig.php");

define('ADMIN_NOTIFY', 'admin');
define('WITHDRAW_NOTIFY', 'wm');
define('ADD_NOTIFY', 'am');
define('WINNER_NOTIFY', 'cong');
define('CONTEST_JOIN_NOTIFY', 'joincnst');
define('KYC_NOTIFY', 'kyc');
define('APP_HEXZ_PASS', 'Brm9@doortinpanch');
define('WIN_CONTEST_NOTIFY', 'wincnst');
define('CANCEL_POOL_NOTIFY', 'clpool');
define('CANCEL_MATCH_NOTIFY', 'clmatch');
define('WINCL', 'wincl');

define('PLAYWEBSETTING', 'playwebsetting');

define('CRICKETID', 1);
define('FOOTBALLID', 2);
define('KABADDIID', 3);

define('CNTSSIZEMIN', 2);
define('CNTSSIZEMAX', 100);
//Rummy transfer
define('RUMMY_MONEY', 'trnsrummy');
//Login Admin
$app->post('/login', 'AdminuserController:funcLogin');

//User Login
$app->post('/userlogin', 'FrontuserController:userloginFunc');

$app->post('/upcmgtoactivematchweb', 'FrontController:listmatchesfrontFunc');

//$app->post('/listmatchesfrontweb','FrontController:listmatchesfrontFunc');
$app->post('/sociallogin', 'FrontuserController:socialloginFunc');
$app->post('/userregister', 'FrontuserController:userregisterFunc');
$app->post('/otpverify', 'FrontuserController:otpverifyFunc');
$app->post('/resendotp', 'FrontuserController:resendotpFunc');
$app->post('/resetpassword', 'FrontuserController:resetpasswordFunc');

$app->get('/getcountry', 'CommonController:funcGetcountry');
$app->get('/getstate', 'CommonController:funcGetstate');
$app->get('/getgametype', 'CommonController:funcGetgametype');
$app->get('/slider', 'CommonController:funcSlider');
//Arun  $app->post('/getplayertype', 'CommonController:getplayertype');

//Arun   
$app->get('/sendNotificationBYTime','AdminController:sendNotificationBYTime');

$app->post('/getplayertype', 'CommonController:funcGetplayertype');
$app->get('/getplayertypeadmin/{gameid}', 'CommonController:funcGetplayertypeAdmin');
$app->get('/gettime', 'CommonController:getTime');
$app->get('/getplaywebset', 'CommonController:getPlayWebSetting');
$app->get('/appversion/{atype}', 'CommonController:funcGetAppversion');
$app->post('/appversionupdate', 'CommonController:setAppVersion');
$app->get('/getmatchdata', 'CommonController:getMatchData');
$app->post('/mailcron', 'CommonController:mailSendCron');



$app->post('/checkifsc', 'CommonController:funcCheckIfsc');

$app->get('/gettest', 'CommonController:getTest');   //Test

$app->get('/ucmatch/{gameid}/{matchid}', 'CommonController:getAPIData');
$app->get('/dmfantasypoints/{matchid}', 'CommonController:dmFantasyPointsFun');
//CMS Pages
$app->get('/cmspages/{slug}', 'CommonController:cmsPages');
$app->get('/verifymail/{vrkey}', 'FrontuserController:verifymail');

$app->get('/sendmail', 'CommonController:sendMail');
$app->get('/sendsms', 'CommonController:sendSMS');

$app->post('/getglobalpoints', 'CommonController:funcGetglobalpoints');
//$app->post('/getmatchgpoints','CommonController:funcGetmatchgpoints');
//$app->post('/verifychecksum','CommonController:verifychecksumFunc');

//For nodejs
$app->post('/getlivematches', 'CommonController:getLiveMatches');
$app->post('/getcontestteamandplayernode', 'CommonController:getContestTeamAndPlayer');

// Callback paytm
$app->post('/walletcallback', 'FrontuserController:walletCallback');
$app->post('/fantasyptssytm', 'CommonController:fantasyPointsSystem');
$app->post('/getcmspage', 'AdminuserController:funcGetpage');

$app->post('/sitegetintouch', 'CommonController:getInTouch');

// Razorpay Web hook
$app->post('/razorpaycallback', 'PaymentController:razorpayCallback');
$app->get('/razorpay/success', 'PaymentController:razorpaySuccess');

//rummy
$app->post('/getrmymny', 'RummyController:getRummyMoney');



 $app->group('/api', function (\Slim\App $app) {
     $app->post('/menus', 'AdminuserController:menusFunc');
     $app->post('/changepassword', 'FrontuserController:changepasswordFunc');
     $app->post('/dashstaticscount', 'AdminreportsController:dashStaticsCount');
     $app->get('/getusertype', 'AdminuserController:funcGetusertype');
     $app->post('/addpages', 'AdminuserController:funcAddpages');
     //Get Page By Slug
     $app->post('/getpage', 'AdminuserController:funcGetpage');
     // Sub admin
     $app->post('/addsubadmin', 'AdminuserController:funcAddsubadmin');
     $app->post('/editsubadmin', 'AdminuserController:funcEditsubadmin');
     $app->post('/deletesubadmin', 'AdminuserController:funcDeletesubadmin');
     $app->post('/listsubadmin', 'AdminuserController:funcListsubadmin');
     $app->post('/updatesubadminstatus', 'AdminuserController:updateSubAdminStatusFunc');
     //$app->post('/singlesubadmin','AdminuserController:funcSinglesubadmin');

     // Roles
     $app->post('/addrole', 'AdminuserController:funcAddrole');
     $app->post('/editrole', 'AdminuserController:funcEditrole');
     $app->post('/listroles', 'AdminuserController:funcListroles');
     $app->post('/assignrole', 'AdminuserController:funcAssignrole');

     // Resoures
     $app->post('/addresoures', 'AdminuserController:funcAddresoures');
     $app->post('/editresoures', 'AdminuserController:funcEditresoures');
     $app->post('/listresoures', 'AdminuserController:funcListresoures');

     /* --User-- */
     $app->post('/getassignrole', 'AdminuserController:funcGetassignrole');
     //$app->post('/editassignrole','AdminuserController:funcEditassignrole');
     $app->post('/getusers', 'AdminuserController:getusersFunc');
     $app->post('/getbotusers', 'AdminuserController:getBotUsersFunc');     
     $app->post('/getuserscash', 'AdminuserController:getUsersCashFunc');
     $app->post('/updateuserscash', 'AdminuserController:updateUsersCashFunc');
 
     $app->post('/getuserinfo', 'AdminuserController:getuserinfoFunc');
     //Arun     
     $app->post('/edituserinfo', 'AdminuserController:editusersinfoFunc');
     //
     $app->post('/updateuserinfo', 'AdminuserController:updateUserInfoFunc');
    
     $app->post('/updatekycstatus', 'AdminuserController:updateKycStatusFunc');
     $app->post('/updateuserstatus', 'AdminuserController:updateuserstatusFunc');
    
     /*Communication*/
     $app->post('/getnotification', 'AdminuserController:getNotificationFunc');
     $app->post('/getcontactus', 'AdminuserController:getContactusFunc');

     /* -----------Admin Controller------------ */
     /* --Players-- */
     $app->post('/addplayers', 'AdminController:funcAddplayers');
     $app->post('/editplayers', 'AdminController:funcEditPlayers');
     $app->post('/deleteplayer', 'AdminController:funcDeletePlayers');

     $app->post('/addplayerimg', 'AdminController:funcAddplayerimg');
     $app->post('/getplayer', 'AdminController:funcGetplayer');
     $app->post('/getplayerimg', 'AdminController:getplayerimgFunc');
    
     /*  --User info-- */
     $app->post('/getuserpancards', 'AdminController:funcGetuserpancards');
     $app->post('/getuserbankdetails', 'AdminController:getuserbankdetailsFunc');
    
     /*  --Teams--  */
     $app->post('/addteam', 'AdminController:addteamFunc');
     $app->post('/editteam', 'AdminController:editteamFunc');
     $app->post('/listteam', 'AdminController:listteamFunc');
     $app->post('/listplayerbyteam', 'AdminController:listplayerbyteamFunc');
     $app->post('/addplayertoteam', 'AdminController:addplayertoteamFunc');
    
     /* IsUsed */
     $app->post('/removeplayerfromteam', 'AdminController:removeplayerfromteamFunc');
        
     /*  --Settings--  */
     $app->post('/addglobalpoints', 'AdminController:addglobalpointsFunc');
     $app->post('/getglobalpoints', 'AdminController:getglobalpointsFunc');
     //$app->post('/updatecapvcappoint','AdminController:updatecapvcappointFunc');
     //$app->post('/getcapvcappoint','AdminController:getcapvcappointFunc');
     $app->post('/addglobalsetting', 'AdminController:addglobalsettingFunc');
     $app->post('/getglobalsetting', 'AdminController:getglobalsettingFunc');
     $app->post('/uploadmultipleimg', 'AdminController:uploadmultipleimgFunc');
    
     /* --Contest-- */
     $app->post('/addcontests', 'AdminController:addcontestsFunc');
     $app->post('/editcontests', 'AdminController:editcontestsFunc');

     $app->post('/listcontests', 'AdminController:listcontestsFunc');
     $app->post('/contestUpdateStatus', 'AdminController:contestUpdateStatusFunc');
     $app->post('/addcontestsmeta', 'AdminController:addcontestsmetaFunc');
     $app->post('/addpoolprizebreaks', 'AdminController:addpoolprizebreaksFunc');
     $app->post('/addpoolandprizebreak', 'AdminController:addPoolAndPrizeBreak');
     $app->post('/deletepool', 'AdminController:funcDeletePool');
    
     $app->post('/getcontestsmeta', 'AdminController:getcontestsmetaFunc');
     $app->post('/getpoolprizebreaks', 'AdminController:getpoolprizebreaksFunc');
    
     /*  --Assign contest--  */
     $app->post('/assignconteststomatch', 'AdminController:assignconteststomatchFunc');
     $app->post('/assigncontestspooltomatch', 'AdminController:assigncontestspooltomatchFunc');
     $app->post('/assignfavpools', 'AdminController:assignContestsUpdate');
     $app->post('/copypoolstatus', 'AdminController:copyPoolStatusFun');


     $app->post('/getmatchcontestlist', 'AdminController:getmatchcontestlistFunc');
     $app->post('/getmatchcontestpoollist', 'AdminController:getmatchcontestpoollistFunc');

    
     /* --Reports-- */
     $app->post('/onepagereportfilter', 'AdminreportsController:onePageReportFilter');
     $app->post('/matchfilter', 'AdminreportsController:matchFilter');
     $app->post('/contestfilter', 'AdminreportsController:contestFilter');
     $app->post('/contestpoolfilter', 'AdminreportsController:contestPoolFilter');
     $app->post('/onepagereport', 'AdminreportsController:onePageReport');


     $app->post('/teamplrs', 'FrontController:getuserteamplayerFunc');
     $app->post('/updateteamuseradmin', 'FrontController:updateteamuserAdmin');
    
     /*Get Withrow request*/
     $app->post('/getwithdrawalreq', 'AdminreportsController:getwithdrawalReq');
     $app->post('/withdrawstatus', 'AdminreportsController:withdrawStatus');     
     $app->post('/withdrawcancel', 'AdminreportsController:withdrawCancel');

     /* Refferal commission */
     $app->post('/updatereferalcommisions', 'AdminuserController:funcUpdateReferalCommisions');
     $app->post('/getreferalcommisions', 'AdminuserController:funcGetReferalCommisions');
    
     /*Slider*/
     $app->post('/addslider', 'CommonController:addSlider');
     $app->post('/getslider', 'CommonController:getSlider');
     $app->post('/deleteslider', 'CommonController:deleteSlider');
    
     /* Notifications for All Users */
     //$app->post('/addnotification','CommonController:addNotificationGlobal');
     $app->post('/delnotification', 'CommonController:delNotificationGlobal');
     //$app->post('/notificationlist','FrontController:getNotification');

     $app->post('/addnotification', 'AdminController:addNotificationGlobal');
     $app->post('/notificationlist', 'AdminController:getNotification');

     /* Update web settings */
     $app->post('/updateplaywebset', 'CommonController:updatePlayWebSetting');
    
                
     /*  --Adminmatches Controller--- */
     //$app->post('/getmastertblmatch','AdminmatchesController:getmastertblmatchFunc');
     $app->post('/addmatch', 'AdminmatchesController:addmatchFunc');
     $app->post('/editmatch', 'AdminmatchesController:editmatchFunc');
     //Arun
     $app->post('/playingout', 'AdminmatchesController:playingoutFunc');
     //
     $app->post('/getmatchedit', 'AdminmatchesController:getmatcheditFunc');
     $app->post('/listmatches', 'AdminmatchesController:listmatchesFunc');
     $app->post('/getmatchteam', 'AdminmatchesController:getmatchteamFunc');
     $app->post('/getmastertblmatchupcomming', 'AdminmatchesController:getmastertblmatchupcommingFunc');
     //Arun  
     $app->post('/editmastertblmatchupcomming', 'AdminmatchesController:editmastertblmatchupcommingFunc');   
     $app->post('/appversionupdate', 'CommonController:setAppVersion');   
     $app->post('/getversionupdate', 'CommonController:getAppVersion');
     //
     $app->post('/upcmgtoactivematch', 'AdminmatchesController:activeToUpcommingMatch');
     $app->post('/getactivematch', 'AdminmatchesController:getActiveMatches');
     $app->post('/matchtimeupdate', 'AdminmatchesController:matchTimeUpldate');
     $app->post('/listmatchesadmin', 'AdminmatchesController:listmatchesAdminFunc');
     $app->post('/cancelMatch', 'CommonController:matchCancelRefund');
    
    
     $app->post('/getactivematchscore', 'AdminmatchesController:getactiveMatchScore');
    
     $app->post('/getcompletedmatches', 'AdminmatchesController:getCompletedMatches');

     $app->post('/livematcheslist', 'CommonController:getLiveMatches');

     //Domestic Matches
     $app->post('/addlocalmatch', 'AdminmatchesController:addLocalMatch');
     $app->post('/getlocalmatch', 'AdminmatchesController:getLocalMatches');
     $app->post('/getlocalmatchteam', 'AdminmatchesController:getLocalMatchteam');
     $app->post('/addscore', 'AdminmatchesController:addScore');
     $app->post('/getscore', 'AdminmatchesController:getScore');

     // Data Clear
     $app->post('/dataclear', 'CommonController:dbClearFunction');

     $app->post('/addpridiction', 'AdminController:addpridictionFunction');
     $app->post('/getpridiction', 'AdminController:getpridictionFunction');

     /* Dev-Manoj */
     $app->post('/promocode', 'AdminuserController:getPromoCode');
     $app->post('/promocode/settings', 'AdminuserController:getPromoCodeSettings');
     $app->post('/promocode/create', 'AdminuserController:createPromoCode');
     $app->post('/promocode/edit', 'AdminuserController:editPromoCode');    
     $app->post('/promocode/{id}', 'AdminuserController:getPromoCode');
     $app->post('/promocoupon', 'AdminuserController:getPromoCodeCoupon');

     $app->post('/bonuslevel', 'AdminuserController:getBonusLevles');
     $app->post('/bonuslevel/create', 'AdminuserController:createBonusLevel');
     $app->post('/bonuslevel/edit', 'AdminuserController:editBonusLevel');
     $app->post('/bonuslevel/{id}', 'AdminuserController:getBonusLevles');
     /* Match Series */
     $app->post('/add-series', 'AdminmatchesController:addSeries');
     $app->post('/edit-series', 'AdminmatchesController:editSeries');
     $app->post('/get-series', 'AdminmatchesController:getSeries');
     $app->post('/delete-series', 'AdminmatchesController:deleteSeries');

     $app->post('/matchplayerdetails', 'AdminmatchesController:matchPlayerDetails');

     //Private Contest
    
     $app->post('/privatecontestadd', 'PrivatecontestController:adminCreatePvtCnst');
     $app->post('/privatecontestedit/{id}', 'PrivatecontestController:adminEditPvtCnst');
     /*$app->post('/privatecontest','PrivatecontestController:getNumOfWinners');
     $app->post('/privatecontest/{id}','PrivatecontestController:getNumOfWinners');*/
     $app->post('/privatecontest', 'CommonController:getNumOfWinners');
     $app->post('/privatecontest/{id}', 'CommonController:getNumOfWinners');
     /* middleware */
 })->add(function ($req, $res, $next) {
     $loginuser 	= $req->getAttribute('decoded_token_data');
     $luid 		= $loginuser['id'] ;
    
     $uri    = $req->getUri()->getPath();
     $uriArr = explode('/', $uri);
     if ($uriArr[0] == 'api' && $loginuser['userdata']->usertype != ADMIN && $uri != 'api/menus') {
         if ($this->security->isAutherized($luid, $uri)) {
             $resArr['code']  	= 0;
             $resArr['error'] 	= false;
             $resArr['msg'] 	= 'You are not Authorized';
             return $res->withJson($resArr, 401);
         }
     }
     return $response = $next($req, $res);
 }); // End group rought



//++++++++++++++++++++++++++++++++++++
//      	--API FRONT--
//====================================

$app->group('/frontapi', function (\Slim\App $app) {
    /*page api (start)*/
    //Arun   
    $app->post('/getappstatus', 'CommonController:getAppStatus');
    //
    $app->post('/userlogout', 'FrontuserController:userlogoutFunc');
    $app->post('/userprofile', 'FrontuserController:userprofileFunc');
    $app->post('/changepassword', 'FrontuserController:changepasswordFunc');
    $app->post('/getprofile', 'FrontuserController:getprofileFunc');
    $app->post('/getrefercode', 'FrontuserController:getrefercodeFunc');
    
    /*Update Bank/Pan Details*/
    $app->post('/updatepancard', 'FrontController:updatepancardFunc');
    $app->post('/getpancard', 'FrontController:getpancardFunc');
    $app->post('/updatebankdetails', 'FrontuserController:updateBankDetails');
    $app->post('/generatechecksum', 'FrontuserController:generatechecksumFunc');
    $app->post('/gettxbyorderid', 'FrontuserController:gettxbyorderidFunc');
    
    $app->post('/createteamuserbot', 'FrontController:createteamuserbotFunc');
    $app->post('/getcontest', 'FrontController:getcontest');


    $app->post('/listmatchesfront', 'FrontController:listmatchesfrontFunc');
    $app->post('/getsinglematch', 'FrontController:getsinglematchFunc');
    $app->post('/matchcontestlistfront', 'FrontController:matchcontestlistfrontFunc');
    $app->post('/getmatchteamfront', 'FrontController:getmatchteamfrontFunc');
    $app->post('/getplayerpoints', 'FrontController:getPlayerPointsInfo');
    $app->post('/createteamuser', 'FrontController:createteamuserFunc');
    $app->post('/updateteamuser', 'FrontController:updateteamuserFunc');
    $app->post('/getuserteamcheckvali', 'FrontController:getuserteamcheckvaliFunc');
    $app->post('/getuserteam', 'FrontController:getuserteamFunc');
    $app->post('/getuserteamplayer', 'FrontController:getuserteamplayerFunc');
    $app->post('/joincontest', 'FrontController:joincontestFunc');
    
    //$app->post('/joincontestnew','FrontController:joincontestNewFunc');

    $app->post('/switchteam', 'FrontController:switchteamFunc');
    $app->post('/getjoinedcontest', 'FrontController:getjoinedcontestFunc');
    //$app->post('/addbalance','FrontController:addbalanceFunc');
    $app->post('/getuserbalance', 'FrontController:getuserbalanceFunc');
    $app->post('/mymatches', 'FrontController:mymatchesFunc');
    $app->post('/getcontestdetails', 'FrontController:getcontestdetailsFunc');
    $app->post('/getcontestjoinedteamsall', 'FrontController:getcontestjoinedteamsallFunc');
    $app->post('/getjoinedteam', 'FrontController:getjoinedteamFunc');
    $app->post('/getcontestteamandplayer', 'FrontController:getContestTeamAndPlayer');
    //Get bank details
    $app->post('/getbankdetails', 'FrontController:funcGetbankdetails');
    //get transactions Frontend
    $app->post('/gettransactions', 'FrontController:gettransactions');
        
    //$app->post('/updateplyrpoints','FrontController:updatePlayerPoints');
    //Mobile api
    $app->post('/walletaddbalance', 'FrontuserController:walletRecharge');
    $app->post('/sendverifyemail', 'FrontuserController:sendVerifyEmail');
    $app->post('/withdrawreq', 'FrontController:withdrawRequest');
    $app->post('/getnotification', 'FrontController:getNotification');
    
    // spin wheel price added
    $app->post('/applyspinbonus', 'FrontuserController:applySpinBonus');
    $app->post('/getspinbonus', 'FrontuserController:getSpinBonus');
    $app->post('/getuserspinbonus', 'FrontuserController:getUserSpinBonus');
    


    
    //Created By Manoj Saini
    /*** Create and set promo code in promotions table ***/
    $app->post('/promocode/validchk', 'FrontuserController:getPromoCodeChk');
    $app->post('/notificationupdate', 'FrontController:statusAndDeleteNotify');
    $app->post('/leaderboardcount', 'FrontController:leaderBoardCount');
    
    // phoeniixx Api leaderboard series name
    $app->post('/leaderboardseries', 'FrontController:leaderboardseriesFunc');
    $app->post('/leaderboardseriesrank', 'FrontController:leaderboardseriesrankFunc');

    $app->post('/appsettings', 'FrontController:getAppSettings');

    //Private Contest
    $app->post('/createpvtcntst', 'PrivatecontestController:createPrivateContest');
    $app->post('/pvtcnstprzbrk', 'PrivatecontestController:prizeBreakUp');
    $app->post('/pvtcntstcheck', 'PrivatecontestController:checkPrivateContest');


    //razorpay payment api
    $app->post('/razorpay/createorder', 'PaymentController:orderRazorpay');
    $app->post('/razorpay/payment', 'PaymentController:paymentRazorpay');

    //Rummy Money transfer
    $app->post('/rmymnytrns', 'RummyController:balanceTrnsRummy');
})->add(function ($req, $res, $next) {
    $loginuser 	= $req->getAttribute('decoded_token_data');
    $uid 		= $loginuser['id'] ;
    $ip         = \Security::getIpAddr();

    if (!$this->security->isAutherizedUser($uid, $ip)) {
        $resArr['code']  	= 2;
        $resArr['error'] 	= false;
        $resArr['msg'] 		= 'You are not Authorized';
        return $res->withJson($resArr, 401);
    }
    return $response = $next($req, $res);
}); // End group rought ;

$app->get('/leaderboarddownload/{matchid}/{poolcontestid}', 'FrontController:getLeaderBoardPDF');
