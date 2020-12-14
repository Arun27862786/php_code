<?php
namespace Apps\Controllers;

use MongoDB\BSON\ObjectId;
use Razorpay\Api\Api;
use Swift_TransportException;

class CommonController
{

	protected $container;

	public function __construct($container){
	      $this->container = $container;
	}

	public function __get($property)
	{
	    if ($this->container->{$property}) {
	        return $this->container->{$property};
	    }
	}


//phoeniixx

public function setAppVersion($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $mongo  = $settings['settings']['mongo'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(["id","maintenance_status",'maintenance_reasons','apk_status','apk_new_feature','apk_version','ios_status','ios_new_feature','ios_version'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $id    		        	= $input['id'];
        $maintenance_status    	= $input['maintenance_status'];
        $maintenance_reasons  	= $input['maintenance_reasons'];
        $apk_status  	        = $input['apk_status'];
        $apk_new_feature		= $input['apk_new_feature'];
        $apk_version		    = $input['apk_version'];
        $ios_status		        = $input['ios_status'];
        $ios_new_feature		= $input['ios_new_feature'];
        $ios_version		    = $input['ios_version'];
          
        try {
			$sql = "UPDATE maintenance SET maintenance_status = :maintenance_status,maintenance_reasons = :maintenance_reasons,apk_status = :apk_status,apk_new_feature = :apk_new_feature,apk_version = :apk_version,ios_status = :ios_status,ios_new_feature = :ios_new_feature,ios_version = :ios_version WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':maintenance_status', $maintenance_status);
            $stmt->bindParam(':maintenance_reasons', $maintenance_reasons);
            $stmt->bindParam(':apk_status', $apk_status);
            $stmt->bindParam(':apk_new_feature', $apk_new_feature); 
            $stmt->bindParam(':apk_version', $apk_version);
            $stmt->bindParam(':ios_status', $ios_status);
            $stmt->bindParam(':ios_new_feature', $ios_new_feature);
            $stmt->bindParam(':ios_version', $ios_version);		
            $stmt->bindParam(':id', $id);			
            if ($stmt->execute()) { 
                $resArr['code']   = 0;
                $resArr['error']  = false;
                $resArr['msg']    = 'Record Updated.';
            } else {
                $resArr['code']   = 1;
                $resArr['error']  = true;
                $resArr['msg']    = 'Record not Updated.';
            }
        } catch (\Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


	public function getAppVersion($request, $response)
    {  
		global $settings,$baseurl;
        $code    	= $settings['settings']['code']['rescode'];
        $errcode 	= $settings['settings']['code']['errcode'];
        $resArr = [];
		try {
            $pimgUrl = $baseurl.'/uploads/players/';
            $sql = "select * from maintenance";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $res =  $stmt->fetchAll();

           

            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Maintenance Mode.';
                $resArr['data']  = ["list"=>$res];
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }   return $response->withJson($resArr, $code);
	}


	public function getAppStatus($request, $response)
    { 
		global $settings,$baseurl;
        $code    	= $settings['settings']['code']['rescode'];
        $errcode 	= $settings['settings']['code']['errcode'];
		$resArr = []; 
        $input      = 	$request->getParsedBody(); 
        $check 		= $this->security->validateRequired(['atype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
 
            $atype  =  $input["atype"];
		try {
			if($atype == 'android'){
				$sql = "SELECT maintenance_status,maintenance_reasons,apk_status,apk_new_feature,apk_version FROM maintenance";
				$stmt    =  $this->db->prepare($sql); 
				$stmt->execute();
				$res =  $stmt->fetch();
			}
			elseif($atype == 'ios'){
				$sql = "SELECT maintenance_status,maintenance_reasons,ios_status,ios_new_feature,ios_version FROM maintenance";
				$stmt    =  $this->db->prepare($sql); 
				$stmt->execute();
				$res =  $stmt->fetch();
			}
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Update Satus.';
                $resArr['data']  = ["list"=>$res];
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }   return $response->withJson($resArr, $code);
	}

//phoeniixx




	
	// Country List
	public function funcGetcountry($request,$response) {
		
		$sql = "SELECT name,sortname,phonecode FROM countries ORDER BY name ";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$country = $sth->fetchAll();
		 
		if($country){
		   	$resArr['code']  = 0;
		   	$resArr['error'] = false;
		   	$resArr['msg']   = 'Country list.';
		   	$resArr['data'] =  $country;
		}else{
		    $resArr['code']  = 1;
		    $resArr['error'] = true;
		    $resArr['msg']   = 'No Record Found.';
		}

		return $response->withJson($resArr);
   }


   	// state List
   	public function funcGetstate($request,$response) {
		
		$input = $request->getParsedBody();
		$countryid = 101; //India
	    
	    $check =  $this->security->validateRequired([],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}
	
    	$sql = "SELECT id,name FROM states WHERE country_id = :id";	
		$sth = $this->db->prepare($sql);
		$sth->bindParam(":id",$countryid);
		$sth->execute();
		$state = $sth->fetchAll();		 
		if($state){
		   	$resArr['code']  = 0;
		   	$resArr['error'] = false;
		   	$resArr['msg']   = 'state list.';
		   	$resArr['data'] =  $state;
		}else{
		    $resArr['code']  = 1;
		    $resArr['error'] = true;
		    $resArr['msg']   = 'No Record Found.';
		}

		return $response->withJson($resArr); 
   }

   // Game Type
   	public function funcGetgametype($request,$response) {

	    global $baseurl;
		$input = $request->getParsedBody();

		try{

			$iconUrl = $baseurl.'/uploads/icons/';    	
	    	$sql = "SELECT id,gname,CONCAT('".$iconUrl."',icon) as icon FROM games ORDER BY id";	
			$sth = $this->db->prepare($sql);
			$sth->execute();
			$res = $sth->fetchAll();	

			if($res){
			   	$resArr['code']  = 0;
			   	$resArr['error'] = false;
			   	$resArr['msg']   = 'Game type';
			   	$resArr['data']  =  $this->security->removenull($res);
			}else{
			    $resArr['code']  = 1;
			    $resArr['error'] = true;
			    $resArr['msg']   = 'No Record Found.';
			}    
	    }
	    catch(\PDOException $e)
	    {    
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
	    } 

		return $response->withJson($resArr); 
   }

   // Slider api
	public function funcSlider($request,$response) 
	{
	    global $baseurl;
		$input = $request->getParsedBody();
		try{
			$iconUrl = $baseurl.'/uploads/slider/';    	
	    	$sql = "SELECT id,title,img,CONCAT('".$iconUrl."',img) as img FROM sliders WHERE status=:status";	
			$sth = $this->db->prepare($sql);
			$sth->execute(["status"=>ACTIVESTATUS]);
			$res = $sth->fetchAll();		 
			if($res){
			   	$resArr['code']  = 0;
			   	$resArr['error'] = false;
			   	$resArr['msg']   = 'Slider';
			   	$resArr['data']  =  $this->security->removenull($res);
			}else{
			    $resArr['code']  = 1;
			    $resArr['error'] = true;
			    $resArr['msg']   = 'No Record Found.';
			}    
	    }
	    catch(\PDOException $e)
	    {    
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
	    } 
		return $response->withJson($resArr); 
   }


   // get Global Settings 
   	public function funcGetglobalpoints($request,$response) 
   	{
        global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
        $input   = $request->getParsedBody();

	    $check =  $this->security->validateRequired(['gametype'],$input);

        if(isset($check['error'])) {
            return $response->withJson($check);
        }
           
       $gametype= $input['gametype'];

       $checkGm = $this->security->isGameTypeExistByName($gametype);
       if($checkGm){
       	$resArr = $this->security->errorMessage("Invalid gametype");
		return $response->withJson($resArr,$errcode); 
       }
   
        if(isset($input["mtype"])){
           $mtype = $input["mtype"];     
           if(!in_array($mtype,['Test','ODI','Twenty20'])){
           	$resArr = $this->security->errorMessage("mtype should be Test/ODI/Twenty20");
			return $response->withJson($resArr,$errcode); 
           }
        }

    try{

	       $dir    = __DIR__ . '/../settings/'.GLOBALPOINTSKEY;  
           $addset = @file_get_contents($dir);

            if ($addset === false) {
			        $resArr = $this->security->errorMessage("Record not found,contact to webmaster.");
					return $response->withJson($resArr,$errcode);
		    } else {
		        $res = json_decode($addset, true);
		      }

           unset($res[GLOBALPOINTSKEY][$gametype]['capsetting']);
		      

	        if(!empty($mtype)){

	          	if(empty($res[GLOBALPOINTSKEY][$gametype][$mtype])){
	          	 	 $resArr = $this->security->errorMessage("Record not found.");
						return $response->withJson($resArr,$errcode);
	          	}            
	          	$res1 = [];
	            $res1[GLOBALPOINTSKEY][][$gametype][$mtype] = $res[GLOBALPOINTSKEY][$gametype][$mtype];
	            $res = $res1;
	              //capsetting
	          }

	         if(!empty($res))
	         { 
	            $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Global points setting.';	
				$resArr['data']  = $res[GLOBALPOINTSKEY] ;	
		     }else{
	            $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = 'Record not found,contact to webmaster.';	
		     }
		}
	    catch(Exception $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }                    	
   		return $response->withJson($resArr,$code);			    
	}


    // Get matchgpoints        
	/*public function funcGetmatchgpoints($request,$response) {

		$input = $request->getParsedBody();
		$check = $this->security->validateRequired(['matchid'],$input);
		if(isset($check['error'])) {
	       return $response->withJson($check);
	    }

	    $matchid = $input['matchid'];

	try{
    	$sql = "SELECT wicket,catch,run,six,four FROM matchpoints WHERE matchid=:matchid";	
		$sth = $this->db->prepare($sql);
		$sth->execute(["matchid"=>$matchid]);
		$res = $sth->fetch();		 
		if($res){
		   	$resArr['code']  = 0;
		   	$resArr['error'] = false;
		   	$resArr['msg']   = 'Match points.';
		   	$resArr['data']  =  $this->security->removenull($res);
		}else{
		    $resArr['code']  = 1;
		    $resArr['error'] = true;
		    $resArr['msg']   = 'No Record Found.';
		}
    }
    catch(\PDOException $e)
    {    
		$resArr['code']  = 1;
		$resArr['error'] = true;
		$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
		$code = $errcode;
    } 

		return $response->withJson($resArr); 
   }*/


   // get playertype
   public function funcGetplayertype($request,$response) {
	global $settings,$baseurl;
	 $code    = $settings['settings']['code']['rescode'];
	 $errcode = $settings['settings']['code']['errcode'];      
	$input = $request->getParsedBody();

	$check =  $this->security->validateRequired(['gameid'],$input);
	if(isset($check['error'])) {
	   return $response->withJson($check);
	}

	$gameid = $input['gameid'];
  
try{

	$iconUrl = $baseurl.'/uploads/icons/';
	$sql = "SELECT id,name,fullname,min,max,CONCAT('".$iconUrl."',playertype.icon) as icon FROM playertype WHERE gameid=:gameid ORDER BY id";
	$sth = $this->db->prepare($sql);
	$sth->execute(["gameid"=>$gameid]);
	$res = $sth->fetchAll();
	$mset = $this->security->getMatchSetting($gameid);
	$gset = \Security::getglobalsetting();

	if(!empty($res) && !empty($mset)){

		   $resArr['code']  = 0;
		   $resArr['error'] = false;
		   $resArr['msg']   = 'Player Type';
// Arun			$resArr['data']  =  ["credits"=>$gset['totalpoints'],"maxteam"=>$gset['maxteam'],"mxfromteam"=>$mset['mxfromteam'],"tmsize"=>$mset['tmsize'],"list"=>$res];
		$resArr['data']  =  ["credits"=>$gset['totalpoints'],"maxteam"=>11,"mxfromteam"=>$mset['mxfromteam'],"tmsize"=>$mset['tmsize'],"list"=>$res];
	}else{
		$resArr['code']  = 1;
		$resArr['error'] = true;
		$resArr['msg']   = 'No Record Found.';
	}

	}
	catch(\PDOException $e)
	{    
		$resArr['code']  = 1;
		$resArr['error'] = true;
		$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
		$code = $errcode;
	} 

	return $response->withJson($resArr); 
}

   public function funcGetplayertypeAdmin($request,$response,$args) {
        global $settings,$baseurl;
	 	$code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];      
		//$input = $request->getParsedBody();

	    $gameid = $args['gameid'];
		//$gameid = $input['gameid'];
		//$gameid = 1;	  
	try{
		    $iconUrl = $baseurl.'/uploads/icons/';
	    	$sql = "SELECT id,name,fullname,min,max,CONCAT('".$iconUrl."',playertype.icon) as icon FROM playertype WHERE gameid=:gameid";
	    	$sth = $this->db->prepare($sql);
			$sth->execute(["gameid"=>$gameid]);
			$res = $sth->fetchAll();
			//$mset = $this->security->getMatchSetting($gameid);

			if(!empty($res)){
			   	$resArr['code']  = 0;
			   	$resArr['error'] = false;
			   	$resArr['msg']   = 'Player Type';
			   	$resArr['data']  =  $res;
			}else{
			    $resArr['code']  = 1;
			    $resArr['error'] = true;
			    $resArr['msg']   = 'No Record Found.';
			}
		}
	    catch(\PDOException $e)
	    {    
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
	    }

		return $response->withJson($resArr); 
   }


   public function verifychecksumFunc($request,$response) 
   {   

    global $settings,$baseurl;     
	$code       =  	$settings['settings']['code']['rescode'];
	$errcode    =  	$settings['settings']['code']['errcode']; 
   
    $input      = 	$request->getParsedBody();                         
    $dataResArr = 	[];          	                
	
    /*
    $check = $this->security->validateRequired(['orderid','custid','industrytypeid','channelid','txnamount'],$input);	if(isset($check['error'])) {
       return $response->withJson($check);
    }   
    */     

    try{ 
        
        $paytmChecksum = "";
		$paramList = array();
		$isValidChecksum = "FALSE";

		$paramList = $input;
		$paytmChecksum = isset($input["CHECKSUMHASH"]) ? $input["CHECKSUMHASH"] : "";

		$isValidChecksum = verifychecksum_e($paramList, PAYTM_MERCHANT_KEY, $paytmChecksum);
        
        $paramList['isValidChecksum'] = $isValidChecksum;
        
        $orderid = $paramList['ORDERID']; 
        $checkOrder = $this->security->getOrderById($orderid);

        if(empty($checkOrder)){ 
        	$resArr = $this->security->errorMessage("Invalid orderid");
            return $response->withJson($resArr,$errcode);      
        }

        $uid 	 = $checkOrder['userid'];
        $orderid = $checkOrder['orderid'];
        $amount = $paramList['TXNAMOUNT'];
        $pmode = $paramList['PAYMENTMODE'];
        $txid = $paramList['TXNID'];
        $status = $paramList['TXN_SUCCESS'];
        $gatewayname = $paramList['GATEWAYNAME'];
        $txdate = strtotime($paramList['TXNDATE']);
        $created = time();
        
        $params = ["userid"=>$uid,"amount"=>$amount,"pmode"=>$pmode,"txid"=>$txid,"status"=>$status,"created"=>$created,'gatewayname'=>$gatewayname,'orderid'=>$orderid,'txdate'=>$txdate];
        $stmt = $this->db->prepare("INSERT INTO transactions (userid,amount,pmode,txid,status,created,gatewayname,orderid,txdate) VALUES (:userid,:amount,:pmode,:txid,:status,:created,:gatewayname,:orderid,:txdate)");           	
        	$stmt->execute($params);

        if($isValidChecksum){
        	$getUserWalletBalance = $this->security->getUserWalletBalance($uid);  
	        $balance = $getUserWalletBalance['walletbalance'] + $amount ;        
	        $updateUserWalletBalance = $this->security->updateUserWalletBalance($uid,$balance);
	        if(!$updateUserWalletBalance){
	            $resArr = $this->security->errorMessage("There is some problem in wallet balance update. contact to webmaster");
			   return $response->withJson($resArr,$errcode);
	        }	
        }
                               
   		$resArr['code']  = 0;
		$resArr['error'] = false;
		$resArr['msg']   = "Transaction";
		$resArr['data']  = $paramList;
    }
    catch(Exception $e)
    {    
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
    }            
        return $response->withJson($resArr,$code);
   }

    // get Contest Team And Player for node js
	public function getContestTeamAndPlayer($request, $response)
	{

        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['matchid'],$input);	
	    if(isset($check['error'])) {
           return $response->withJson($check);
        }
    	$matchid  =  $input["matchid"];    	
      	   	       
    try{
                
        $stmt 	= $this->db->prepare("SELECT mtype FROM matches WHERE matchid=:matchid");  
        $stmt->execute(["matchid"=>$matchid]);
        $mRes 	= $stmt->fetch();

        $params = ["matchid"=>$matchid]; 
        $sql = "SELECT jc.uteamid,jc.matchid FROM joincontests jc INNER JOIN userteams ut ON jc.uteamid=ut.id WHERE jc.matchid=:matchid GROUP BY jc.uteamid";

        $stmt 	= $this->db->prepare($sql);  
        $stmt->execute($params);
        $teams 	= $stmt->fetchAll();

        foreach ($teams as $team) {
        	$sql2 = "SELECT pid,iscap,isvcap  FROM userteamplayers WHERE userteamid=:userteamid ";
		    $stmt2   = $this->db->prepare($sql2);
		    $params2 = ["userteamid"=>$team['uteamid']];
		    $stmt2->execute($params2);
		    $resPlr	 = $stmt2->fetchAll(); 		   
		    $team['players'] = $resPlr;
		    $dataResArr[] = $team;
        }
        if(!empty($dataResArr))
        {                        	
    		$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Match team's player.";
			$resArr['details']= ['mtype'=>$mRes['mtype']];
			$resArr['data']  = $dataResArr;
        }else{             
            $resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = "Record not found.";			
        } 
    }
    catch(\PDOException $e)
    {    
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
    }            
        return $response->withJson($resArr,$code);
   }

    
    // get time
	public function getTime($request, $response)
	{	
		global $settings;        
      	$code    = $settings['settings']['code']['rescode'];

      	$t 	 = time();
      	$res = ["time"=>$t];

		$resArr['code']  = 0;
		$resArr['error'] = false;
		$resArr['msg']   = "Time";
		$resArr['data']  = $res;
              
    	return $response->withJson($resArr,$code);
    }

    // get Global Settings 	
 	public function getPlayWebSetting($request,$response)
 	{
        global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
        //$input   = $request->getParsedBody();

    try{

        $dir    = __DIR__ . '/../settings/'.PLAYWEBSETTING;  
        $addset = @file_get_contents($dir);
        if ($addset === false) {
		        $resArr = $this->security->errorMessage("Record not found,contact to webmaster.");
				return $response->withJson($resArr,$errcode);
	    } else {
	        $res = json_decode($addset, true);
	    }

         if(!empty($res))
         { 
            $resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = 'Play Web setting.';	
			$resArr['data']  = $res ;	
	     }else{
            $resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = 'Record not found,contact to webmaster.';	
	     }
	}
    catch(Exception $e)
    {    
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
    }                 
   	
   	return $response->withJson($resArr,$code);			  
  
  } 


  	//Update Play Web Setting Settings
	public function updatePlayWebSetting($request,$response)
 	{
        global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
	    $check =  $this->security->validateRequired(['supportemail','supportphone','supportlbl'],$input);

        if(isset($check['error'])) {
            return $response->withJson($check);
        }           
       		
       		$supportemail   = $input['supportemail'];
       		$supportphone  	= $input["supportphone"];    
       		$supportlbl 	= $input["supportlbl"];    
       	  
            try{

		       $dir = __DIR__ . '/../settings/'.PLAYWEBSETTING;  
               $addset = @file_get_contents($dir);
               
               if($addset === false){
               
                   $datas['playwebsetting'] = 
                         [
                           'supportemail' 	=> $supportemail,    
				           'supportphone'  	=> $supportphone,    				           
				           'supportlbl'    	=> $supportlbl    				           			
				         ];     
			   }else{

			        $datas = json_decode($addset, true);
			        $datas['playwebsetting'] = 
                         [
                           'supportemail' 	=> $supportemail,    
				           'supportphone'  	=> $supportphone,    				           
				           'supportlbl'    	=> $supportlbl    				           			
				         ];
			   }

            if (file_put_contents($dir, json_encode($datas)) )
            { 
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = 'Record Updated successfully.';	

		    }else{
                    $resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg'] = 'Record not Updated, There is some problem';	
		    }
		}
	    catch(Exception $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }                 
       return $response->withJson($resArr,$code);
			  
	}

	
	public function matchCancelRefund($request, $response)
	{	
		global $settings;        
     	$code 	 = $settings['settings']['code']['rescode'];
     	$errcode = $settings['settings']['code']['errcode'];
		
		$resArr= [];

		$input = $request->getParsedBody();
	    $check =  $this->security->validateRequired(['matchid'],$input);

        if(isset($check['error'])) {
            return $response->withJson($check);
        }       		
        	$matchid   = $input['matchid'];   
       		$sql   = "SELECT matchid FROM matches WHERE matchid=:matchid AND mstatus='li' ";
	      	$stmt 	= $this->db->prepare($sql);
	      	$stmt->execute(['matchid'=>$matchid]);
	      	$checkMatch 	=  $stmt->fetch();

	      	if(empty($checkMatch)){
	      		$resArr = $this->security->errorMessage("Invalid matchid.");
				return $response->withJson($resArr,$errcode);
	      	}

		try{
    			
    		$this->db->beginTransaction();	
			$i = 0;
			$a = 0;

      		$sql   = "SELECT id,userid,fees FROM joincontests 
					  where matchid=:matchid 
					  AND poolcontestid not IN
					  (select contestmetaid FROM matchcontestpool WHERE matchid=:matchid AND iscancel=1)";

	      	$stmt 	= $this->db->prepare($sql);
	      	$stmt->execute(['matchid'=>$matchid]);
	      	$joinRes 	=  $stmt->fetchAll();

	      	if(!empty($joinRes)){
	      		foreach ($joinRes as $joinRow) 
	      		{	
	      			
	      			$data['refundamt']=$joinRow['fees'];
	      			$data['matchid']=$matchid;
	      			$data['userid'] =$joinRow['userid'];
	      			$this->getMatchCancelMailData($data);
	      			/*      				
	      			$i = $i + 1;
	      			$a = $a + $joinRow['fees'];
	      			echo $i.' '.$joinRow['userid'].' '.$joinRow['id'].' '.$joinRow['fees'].'</br>';
	      			*/  				
	      			$stmt 	= $this->db->prepare("CALL refundFeesCancelPool(:userid,:docid,:fees)");
	      			$stmt->execute(['userid'=>$joinRow['userid'],'docid'=>$joinRow['id'],'fees'=>$joinRow['fees']]);
	      			
	      		}	
	      	}	      	
      		
      		//echo $a;
      		//die('out');
      		
      		$sql    = "UPDATE matches SET mstatus='cl' where matchid=:matchid ";
	      	$stmt 	= $this->db->prepare($sql);
	      	$stmt->execute(['matchid'=>$matchid]);

	      	$sql   = "UPDATE matchcontestpool SET iscancel=1 WHERE matchid=:matchid ";
	      	$stmt 	= $this->db->prepare($sql);
	      	$stmt->execute(['matchid'=>$matchid]);

	      	$completematches = $this->mdb->completematches;
          	$res = $completematches->findOne(['matchid'=>$matchid]);
  			
  			$matchscores = $this->mdb->matchscores;
          	$res2 = $matchscores->findOne(['matchid'=>$matchid]);

          	$processmatches = $this->mdb->processmatches;
          	$res3 = $processmatches->findOne(['matchid'=>$matchid]);

          	if(!empty($res)){
            	$completematches->updateOne(['matchid'=>$matchid],['$set'=>["ismatchcomplete"=>2]]);
          	}
          	if(!empty($res2)){
            	$matchscores->updateOne(['matchid'=>$matchid],['$set'=>["winner_team"=>"no result"]]);
          	}
          	if(!empty($res3)){
            	$processmatches->updateOne(['matchid'=>$matchid],['$set'=>["mongosavestatus"=>6,"filesavestatus"=>1,"pointstatus"=>2,"pointteamstatus"=>2,"ppointstatus"=>1]]);
          	}	

	      	$this->db->commit();

		$resArr['code']  = 0;
		$resArr['error'] = false;
		$resArr['msg']   = "Time";
		$resArr['data']  = "match canceled";		

      }catch(\PDOException $e)
	    {    
	    	$this->db->rollBack();
	        $resArr['code']  = 1;
	        $resArr['error'] = true;
	        $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
	        $code = $errcode;
	        
	    }             

	    return $response->withJson($resArr,$code);
    
    }


    public function getMatchCancelMailData($input){

    	global $settings;
    	$webname=$settings['settings']['webname'];
    	$userid=$input['userid'];
    	$matchid=$input['matchid'];
    	$refundamt=$input['refundamt'];
    	$title=ucwords($webname." Cancel Match");
    	$message="Refund amount on cancellation of match!";
    	try{
    	$collection= $this->mdb->notifyandmails;
    	$users="SELECT email,phone,teamname,name,devicetype,devicetoken from users urs inner join userprofile up on urs.id=up.userid where urs.id=$userid";

    	$stmt 	= $this->db->prepare($users);
      	$stmt->execute();
      	$users 	=  $stmt->fetch();

		$match="SELECT m.seriesname,m.matchname,m.team1,m.team2,m.team1logo,m.team2logo,m.mdate FROM matches m where m.matchid='".$matchid."'";

		$stmt 	= $this->db->prepare($match);
      	$stmt->execute();
      	$match 	=  $stmt->fetch();
      	$matchs=['refundamt'=>$refundamt,'team_a'=>$match['team1'],'team_b'=>$match['team2'],'series'=>$match['seriesname'],'mdata'=>$match['mdate']];
      	$nofimail['userid']=$input['userid'];
      	$nofimail['type']=CANCEL_MATCH_NOTIFY;
      	$nofimail['email']=$users['email'];
      	$nofimail['phone']=$users['phone'];
      	$nofimail['devicetype']=$users['devicetype'];
      	$nofimail['created']=time();
      	$nofimail['maildata']=['name'=>($users['name'])?$users['name']:$users['teamname'],'email'=>$users['email'],'subject'=>$title,"webname"=>$webname,"content"=>$message,"template"=>"matchcancel.php","matchs"=>$matchs];

      	if($users['devicetype']!='web'){

      		$nofimail['notify']=['token'=>[$users['devicetoken']],'title'=>$title,'devicetype'=>$users['devicetype'],'ntype'=>CANCEL_MATCH_NOTIFY,'message'=>$message,'notify_id'=>1];

      	}

	$collection->insertOne($nofimail);      	
      }catch(Exception $e){
      	return true;
      }

    	return true;
    }

    //Get Test
    public function getTest($request, $response)
	{	
		global $settings;        
     	$code = $settings['settings']['code']['rescode'];
		

	//	$this->security->sendmail($this->mailer);
     	$devicetype = 'android';
     	/*d_8EmKrOqwE:APA91bHG-Dsg6gFQZmHR53P-_o78f_yoPY7pDuGoY8jOKnoOvA_sLEH64zN1gi3x-KhUVWci7mkhMsKE9S1m40J4pdJ6nid81oPXnmUFoQwM-u-2XuKsbK1Ir5KIiVcfV4GQMl77-cfX*/
     	//$token = 'f5jpoOfUXYg:APA91bF3nQt52i9PiLrZRXJqmroKuzTnlTVWJc1gHDhVwzNJcKZ7mIiAduURN_mfiVZB1P7K0LjHEimcZ1eEQf5yCsBGkFzD2LE4lrBbIlApD898R-O-MgyxUwHiOqa998ao_CDOiXds';
     	$token = 'd_8EmKrOqwE:APA91bHG-Dsg6gFQZmHR53P-_o78f_yoPY7pDuGoY8jOKnoOvA_sLEH64zN1gi3x-KhUVWci7mkhMsKE9S1m40J4pdJ6nid81oPXnmUFoQwM-u-2XuKsbK1Ir5KIiVcfV4GQMl77-cfX';
     	$msg = 'Test 123';
     	$title = "test title";

     	$this->security->createPushNotification($devicetype,$token,$msg,$title);  die;

     	/*echo $t = strtotime("2019-02-23T12:00:00.000Z");
     	$t1 = date('y-m-d');
     	$a = time(); 		
	 	date_default_timezone_set('Asia/Kolkata');	 	
	 	$t2 = date('Y-m-d h:m:s',$a); 		
 		$dt =  date('Y-m-d h:i:s',strtotime(date('Y-m-d h:m:s')));
 		$dt =  date('Y-m-d h:m:s');
 		$res = ["t1"=>$t1,"t2"=>$t2,"date"=>$dt];*/
 		//$this->security->ones();

 		$res = ["t1"=>1];

		$resArr['code']  = 0;
		$resArr['error'] = false;
		$resArr['msg']   = "Time";
		$resArr['data']  = $res;
              
    	return $response->withJson($resArr,$code);
    }

     // Active maches
    public function getLiveMatches($request,$response)
    {
      
      global $settings,$baseurl;        
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];     

      $input = $request->getParsedBody();           
       
      try{   

       $sql = "SELECT matchid,matchname,team1,team2 FROM matches WHERE mstatus=:mstatus";    

        $stmt = $this->db->prepare($sql);
        $stmt->execute(["mstatus"=>LIVE]);
        $res =  $stmt->fetchAll();

       if(!empty($res)){  
              $resArr['code']  = 0;
              $resArr['error'] = false;
              $resArr['msg']   = 'List live matches.';  
              $resArr['data']  = \Security::removenull($res);  
        }else{
              $resArr['code']  = 1;
              $resArr['error'] = true;
              $resArr['msg']   = 'Record not found.'; 
        }
    }
    catch(\PDOException $e)
    {    
        $resArr['code']  = 1;
        $resArr['error'] = true;
        $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
        $code = $errcode;
    }             
    
    return $response->withJson($resArr,$code); 
  }



   // fantasy Points System
   	public function fantasyPointsSystem($request,$response)
   	{
        global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
        $input   = $request->getParsedBody();

	    $check =  $this->security->validateRequired(['gametype'],$input);

        if(isset($check['error'])) {
            return $response->withJson($check);
        }
           
       $gametype= $input['gametype'];

       $checkGm = $this->security->isGameTypeExistByName($gametype);
       if($checkGm){
       	$resArr = $this->security->errorMessage("Invalid gametype");
		return $response->withJson($resArr,$errcode); 
       }
   
        if(isset($input["mtype"])){
           $mtype = $input["mtype"];     
           if(!in_array($mtype,['Test','ODI','Twenty20','kabaddi','football'])){
           	$resArr = $this->security->errorMessage("mtype should be Test/ODI/Twenty20");
			return $response->withJson($resArr,$errcode); 
           }
        }

    try{
	       $dir    = __DIR__ . '/../settings/'.GLOBALPOINTSKEY;  
           $addset = @file_get_contents($dir);

           $dirfantasytxt    = __DIR__ . '/../settings/fantasytxt';  
           $fantasytxt = json_decode(@file_get_contents($dirfantasytxt),true);
            if ($addset === false) {
			        $resArr = $this->security->errorMessage("Record not found,contact to webmaster.");
					return $response->withJson($resArr,$errcode);
		    } else {
		        $res1 = json_decode($addset, true);
		    }
           unset($res1[GLOBALPOINTSKEY][$gametype]['capsetting']);		      
	        $arrData = [];
	        $detailData = [];

	        if(isset($mtype) && !empty($mtype)){
	          	$res[$mtype] = $res1[GLOBALPOINTSKEY][$gametype][$mtype];
	        }else{
	          	$res = $res1[GLOBALPOINTSKEY][$gametype];	
	        }
	          
	        
	        if($gametype == 'cricket'){
	        	$detailData = $fantasytxt['cricket'];
          	foreach ($res as $r => $row ) {								          		 
          		$arrData[$r]['batting']['playing'] 		= $row['playing'];	
          		$arrData[$r]['batting']['run'] 			= $row['run'];	
          		$arrData[$r]['batting']['four'] 		= $row['four'];	
          		$arrData[$r]['batting']['six'] 			= $row['six'];	
          		$arrData[$r]['batting']['fifty'] 		= $row['fifty'];
          		$arrData[$r]['batting']['hundred'] 		= $row['hundred'];	
          		$arrData[$r]['batting']['duck'] 		= $row['duck'];
          		$arrData[$r]['bowling']['wicket'] 		= $row['wicket'];
          		$arrData[$r]['bowling']['fourwhb'] 		= $row['fourwhb'];	
          		$arrData[$r]['bowling']['fivewhb'] 		= $row['fivewhb'];	
          		$arrData[$r]['bowling']['mdnover'] 		= $row['mdnover'];
          		$arrData[$r]['fielding']['catch'] 		= $row['catch'];
          		$arrData[$r]['fielding']['stumped']  	= $row['stumped'];
          		$arrData[$r]['fielding']['thrower']  	= $row['thrower'];
          		$arrData[$r]['fielding']['catcher']  	= $row['catcher'];
          		$arrData[$r]['strikerate']['srone']    	= $row['srone'];
          		$arrData[$r]['strikerate']['srtwo']    	= $row['srtwo'];
          		$arrData[$r]['strikerate']['srthree']  	= $row['srthree'];
          		$arrData[$r]['strikerate']['srmball']  	= $row['srmball'];
          		$arrData[$r]['economyrate']['erone']    = $row['erone'];
          		$arrData[$r]['economyrate']['ertwo']    = $row['ertwo'];
          		$arrData[$r]['economyrate']['erthree']  = $row['erthree'];
          		$arrData[$r]['economyrate']['erfour']   = $row['erfour'];
          		$arrData[$r]['economyrate']['erfive']   = $row['erfive'];
          		$arrData[$r]['economyrate']['ersix']    = $row['ersix'];
          		$arrData[$r]['economyrate']['ermover']  = $row['ermover'];
          		 
          	}
          }
          if($gametype == 'kabaddi'){
          	$detailData = $fantasytxt['cricket'];
			$arrData = $res;          	
          }

          if($gametype == 'football'){
          	$detailData = $fantasytxt['football'];
			$arrData = $res;          	
          }

	         if(!empty($arrData))
	         { 
	            $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Fantasy point system .';	
				$resArr['data']  = $arrData ;
				$resArr['details']  = $detailData ;	
		     }else{
	            $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = 'Record not found,contact to webmaster.';	
		     }
		}
	    catch(Exception $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }                    	
   		return $response->withJson($resArr,$code);			    
	}



	 // CMS Pages
    public function cmsPages($request,$response,$args)
    {      
       global $settings;        
       $code    = $settings['settings']['code']['rescode'];
       $errcode = $settings['settings']['code']['errcode'];

       $slug = $args['slug'];	

       $sql = "SELECT slug,title,content FROM pages WHERE status=:status AND slug=:slug";    

       $stmt = $this->db->prepare($sql);
       $stmt->execute(["slug"=>$slug,"status"=>ACTIVESTATUS]);
       $res =  $stmt->fetch();                	      	
    	return $this->renderer->render($response, "/cms.php", ['data' => $res]);
   }


   // Slider api
	public function getSlider($request,$response) 
	{
	    global $baseurl;
		$input = $request->getParsedBody();
		try{
			$iconUrl = $baseurl.'/uploads/slider/';    	
	    	$sql = "SELECT id,title,img,CONCAT('".$iconUrl."',img) as imgurl,status,gameid FROM sliders ";	
			$sth = $this->db->prepare($sql);
			$sth->execute();
			$res = $sth->fetchAll();		 
			if($res){
			   	$resArr['code']  = 0;
			   	$resArr['error'] = false;
			   	$resArr['msg']   = 'Slider';
			   	$resArr['data']  = $res;
			}else{
			    $resArr['code']  = 1;
			    $resArr['error'] = true;
			    $resArr['msg']   = 'No Record Found.';
			}    
	    }
	    catch(\PDOException $e)
	    {    
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
	    } 
		return $response->withJson($resArr); 
   }

   	//---Add Team---
	public function addSlider($request,$response) 
	{
        	global $settings;
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode'];
	        $input = $request->getParsedBody();
		    $check =  $this->security->validateRequired(['title','img','status','gameid','atype'],$input);
	        if(isset($check['error'])) {
	            return $response->withJson($check);
	        }
           
           	$title  = $input["title"];                    
       		$img = $input["img"];    
       		$status = $input["status"];    
       		$gameid = $input["gameid"];  
       		$atype  = $input["atype"];  
       		$msg = '';

       		if(!in_array($status,['0','1'])){
           		$resArr = $this->security->errorMessage("Status should be 0/1.");
				return $response->withJson($resArr,$errcode);	
         	}
         	if(!in_array($atype,['add','edit'])){
           		$resArr = $this->security->errorMessage("Status should be add/edit.");
				return $response->withJson($resArr,$errcode);	
         	}

         	$checkGame = $this->security->isGameTypeExistById($gameid); 
		    if(empty($checkGame)){
		        	$resArr = $this->security->errorMessage("Invalid game id");
					return $response->withJson($resArr,$errcode);
		    }  

         try{
            
            $data =  ['title'=>$title,'img'=>$img,'gameid'=>$gameid,'status'=>$status]; 

           if($atype=='add')
           {
        	$stmt = $this->db->prepare("INSERT INTO sliders (title,img,gameid,status) VALUES (:title,:img,:gameid,:status)");
        	$msg = "Slider added successfully.";
           }else{

           	if(!isset($input['id']) || empty($input['id']))
           	{
		        	$resArr = $this->security->errorMessage("Invalid id");
					return $response->withJson($resArr,$errcode);
		    }
          	$stmt = $this->db->prepare("UPDATE sliders SET title=:title,img=:img,gameid=:gameid,status=:status WHERE id=:id");  
           	$data =  $data + ["id"=>$input['id']];

           	$msg = "Slider updated successfully.";
           } 

		   if($stmt->execute($data)){
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = $msg;	
		     }else{
                    $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg'] = 'Slider not updated, There is some problem';	
		     }
		}
	    catch(\PDOException $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }             
        return $response->withJson($resArr,$code);			  
    }

    //--Delete Slider--
	public function deleteSlider($request,$response) 
	{
        	global $settings;
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode'];
	        $input = $request->getParsedBody();
		    $check =  $this->security->validateRequired(['id'],$input);
	        if(isset($check['error'])) {
	            return $response->withJson($check);
	        }
           
           	$id  = $input["id"];                           		
        
         try{            

            $data =  ['id'=>$id]; 
          	$stmt = $this->db->prepare("DELETE FROM sliders WHERE id=:id");  
           	$data =  $data + ["id"=>$input['id']];

		   if($stmt->execute($data)){
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Banner delated successfully';	
		     }else{
                    $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg'] = 'Banner not deleted';	
		     }
		}
	    catch(\PDOException $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }             
        return $response->withJson($resArr,$code);			  
    }


    //---Add Notification---
	public function addNotificationGlobal($request,$response) 
	{
    	global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
	    $check =  $this->security->validateRequired(['title','message','atype'],$input);
        if(isset($check['error'])) {
            return $response->withJson($check);
        }
           
	       	$title   = $input["title"];                    
	   		$message = $input["message"];    
	   		$img     = '';
	   		$atype   = $input["atype"];  
	   		$created = time(); 
	   		$sbsql = ""; 

	   		$data =  ['title'=>$title,'message'=>$message];

	   		if(!empty($input["img"])){

	   			$sbsql = " ,img=:img ";
	   			$img = $input["img"];
	   			$data =  ['title'=>$title,'message'=>$message,'img'=>$img];
	   		}
	   		     
	     	if(!in_array($atype,['add','edit'])){
	       		$resArr = $this->security->errorMessage("Invalid atype");
				return $response->withJson($resArr,$errcode);	
	     	}
	     	
         try{
            
           if($atype=='add')
           {
           	$data =  ['title'=>$title,'message'=>$message,'img'=>$img];

        	$data = $data + ['created'=>$created];

        	$stmt = $this->db->prepare("INSERT INTO notificationglobal (title,message,img,created) VALUES (:title,:message,:img,:created)");
        	$msg = "Notification sent successfully.";

        	$this->sendNotiToUser($title,$message);


           }else{

           	if(!isset($input['id']) || empty($input['id']))
           	{
	        	$resArr = $this->security->errorMessage("Invalid id");
				return $response->withJson($resArr,$errcode);
		    }

		    $sql = "UPDATE notificationglobal SET title=:title,message=:message".$sbsql." WHERE id=:id";

          	$stmt = $this->db->prepare($sql);  
           	$data =  $data + ["id"=>$input['id']];
           	$msg = "Notification updated successfully.";

           } 
		   if($stmt->execute($data)){
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = $msg;	
		     }else{
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] 	 = "data not processes";	
		     }
		}
	    catch(\PDOException $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }             
        return $response->withJson($resArr,$code);			  
    }

   
/*
    public function addNotificationGlobal($request,$response) 
	{
    	global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        if(isset($input['id']) && !empty($input['id']) && isset($input['atype']) && in_array($input['atype'],['statusUpdate','delete'])) {
        	if(!isset($input['sendAll']) || !in_array($input['sendAll'],[0,1])){
	     		$resArr = $this->security->errorMessage("Invalid or empty field sendAll!");
				return $response->withJson($resArr,$errcode);
	     	}
        	$res=$this->statusAndDeleteNotify($input);
        	return $response->withJson($res['resArr'],$code);
        }else{
	    	$check =  $this->security->validateRequired(['title','message','atype'],$input);
		}
        if(isset($check['error'])) {
            return $response->withJson($check);
        }
 	       	$title   = $input["title"];                    
	   		$message = $input["message"];     
	   		$img     = '';
	   		$atype   = $input["atype"];  
	   		$userid	 = (isset($input['userid']) && !empty($input['userid']))?json_decode($input['userid']):null;//$input['userid'];//(isset($input["userid"]) && !empty($input['userid']))?(explode(",",$input['userid'])):[];
	   		$created = time(); 
	   		$sbsql = ""; 
	   		$data =  ['title'=>$title,'message'=>$message,'ntype'=>ADMIN_NOTIFY];
	   		if(!in_array($atype,['add','edit'])){
	       		$resArr = $this->security->errorMessage("Invalid atype");
				return $response->withJson($resArr,$errcode);	
	     	}
	   		if(!empty($input["img"])){
	   			$sbsql = " ,img=:img ";
	   			$img = $input["img"];
	   			$data['img']=$img;
	   		}
	   		$data['userid']=(object)[];
	   		if(!empty($userid)) { 
	   			$countids=count((array)($userid));
	   			if($countids>0){
		   			$data['userid']=$userid;
		   			$data['sendAll']=0;
	   			}else{
	   				$data['sendAll']=1;	
	   			}
	   		}else{
	   			$data['sendAll']=1;
	   		}
	   		
         try{
            $collection = $this->mdb->notification;

           if($atype=='add') 
           {
        	$data['created']=$created;       	
            $res=$collection->insertOne($data);
        	$msg = "Notification sent successfully.";
        	//$this->sendNotiToUser($title,$message);
           }else{

           	if(!isset($input['id']) || empty($input['id']))
           	{
	        	$resArr = $this->security->errorMessage("Invalid id");
				return $response->withJson($resArr,$errcode);
		    }

		    $id= new ObjectId($input['id']);
		    if($atype=='delete'){
			    $res=$collection->deleteOne(["_id"=>$id]);
	           	$msg = "Notification deleted successfully.";
           	}else{
           		$res=$collection->updateOne(["_id"=>$id],['$set'=>$data]); 
	           	$msg = "Notification updated successfully.";
           	}
           } 
           if(!empty($res)){
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = $msg;	
		     }else{
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] 	 = "data not processes";	
		     }
		}
	    catch(\PDOException $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }             
        return $response->withJson($resArr,$code);			  
    } 

    public function statusAndDeleteNotify($input)
    { 
    	global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
     try{ 
            $atype=$input['atype'];
            $notifyType=$input['sendAll'];
            $collection = $this->mdb->notification;
		    $id= new ObjectId($input['id']);
		    $userid= "2072";
		    if($atype=='delete'){
				    $res=$collection->deleteOne(["_id"=>$id]);
		           	$msg = "Notification deleted successfully.";
           	}else{
	           		$whereData=["_id"=>$id];
	           		if(empty($notifyType) || $notifyType==0){
			    		$whereData=$whereData + ['userid.'.$userid=>['$in'=>[0,1]]];
					}
					$res=$collection->findOne($whereData);
	           		if($res){
	           			$userids=(array)$res['userid'];
	           			$newobject=[$userid=>1];
	           			
	           			if(count($userids)>0){
	           				$useridsNew=$newobject+$userids;
	           			}else{
	           				$useridsNew=$newobject;
	           			}
	           			$data['userid']=(object)$useridsNew;
		           		$res=$collection->updateOne($whereData,['$set'=>$data]); 
			           	$msg = "Notification updated successfully.";
		           	}
	           	}
	        if(!empty($res)){
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = $msg;	
		     }else{
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] 	 = "Invalid Notification";	
		     }
		}
	    catch(\PDOException $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }             
        return ['resArr'=>$resArr,'code'=>$code];	
     
    }
    //Get Notification	
	public function getNotification($request, $response){
            global $settings,$baseurl;            
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode'];			
			/*$loginuser 	= 	$request->getAttribute('decoded_token_data');
    		$uid 		= 	$loginuser['id'];  	         
            $input = $request->getParsedBody();               
            $whereData     = [];
            $selected=['_id'=>1,'img'=>1,'title'=>1,'message'=>1,'created'=>1];
            $rstSort=['created'=>(-1)];
            $projection = ['skip'=>0,'limit'=>10,'sort'=>$rstSort,'projection'=>$selected];
            if(isset($input['page']) && !empty($input['page']))
            {
              	$limit  = (isset($input['limit']) && !empty($input['limit']))?$input['limit']:''; //2
             	$page   = $input['page'];  //0   LIMIT $page,$offset
             	if(empty($limit)){
              	  $limit  = $settings['settings']['code']['defaultPaginLimit'];
             	}
             	$offset = ($page-1)*$limit;	
             	$projection=['skip'=>$offset,'limit'=>$limit];
            }                        
         try{  
         		$collection = $this->mdb->notification; 
         	//FROM_UNIXTIME
            if(!empty($input['search']) && isset($input['search'])){
                $search = $input['search']; 	
             	//$searchSql = " WHERE  title LIKE :search OR message LIKE :search  ";
             	$whereData = $whereData+["title"=>"/".$search."/"];
            }
			$imgUrl = $baseurl.'/uploads/notifications/';    	
			    if(isset($input['userid']) && !empty($input['userid'])){
			    	$userid=$input['userid'];
			    	$whereData=$whereData + ['userid.'.$userid=>['$in'=>[0,1]]];
			    	$projection['projection']=$selected+['userid.'.$userid=>1];
				}
				
			    $res=$collection->find($whereData,$projection)->toArray();
			  //  print_r($whereData); die;
			   if(!empty($res)){  
                    $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg']   = 'Notification List.';	
					$resArr['data']  = ["total"=>$collection->count(),"list"=>$res,'imgUrl'=> $imgUrl];						
			     }else{
                    $resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg']   = 'Record not found.';	
			     }
			}
		    catch(\PDOException $e)
		    {    
					$resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
					$code = $errcode;
		    }                 
            return $response->withJson($resArr,$code);	
	}

*/

    public function sendNotiToUser($title,$msg){

    	$sql = "SELECT id,email,phone,status,devicetype,devicetoken FROM users WHERE status = 1 AND usertype=:usertype AND devicetype != 'web'";
    	$sth = $this->db->prepare($sql);        
        $sth->execute(["usertype"=>USER]);
        $users = $sth->fetchAll();   

        foreach ($users as $user) {
        	$this->security->createPushNotification($user['devicetype'],$user['devicetoken'],$msg,$title);        	
        }

    }

    //---Delete Notification---
	public function delNotificationGlobal($request,$response)
	{
    	global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
	    $check =  $this->security->validateRequired(['id'],$input);
        if(isset($check['error'])) {
            return $response->withJson($check);
        }
           
	       	$id  = $input['id'];                    	   		     	
        try{
            
            $data =  ['id'=>$id];
          	$stmt = $this->db->prepare("DELETE FROM notificationglobal WHERE id=:id");  
           	           	
		   	if($stmt->execute($data)){
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = 'Delete notification successfully.';	
		    }else{
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] 	 = "Invalid request.";	
		    }
		}
	    catch(\PDOException $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }
        return $response->withJson($resArr,$code);
    }

   public function sendMail($request,$response,$args)
   {    
   		global $settings;
	    $code    = $settings['settings']['code']['rescode'];
	    $errcode = $settings['settings']['code']['errcode'];
	    	    	    
	    //try{
		    $user['link']="adsfds";
	   		$this->mailer->sendMessage('/mail/verifyemail.php', ['data' => $user], function($message) use($user) {
		    	$name = " ";	    			    		
		        $message->setTo('martin@mailinator.com',$name);
		        $message->setSubject('Verify email address Local Test')
		        ;
		    });
   		/*}catch(Swift_TransportException $STe){
   			$string = date("Y-m-d H:i:s")  . ' - ' . $STe->getMessage() . PHP_EOL;
			file_put_contents("./../logs/errorlog_mail.txt", $string, FILE_APPEND);			
   		}*/
	    $response->getBody()->write('Mail sent!');
	    return $response;
   }
	
	public function getInTouch($request,$response,$args)
   	{    
   		global $settings;        
	    $code    	= $settings['settings']['code']['rescode'];
	    $errcode 	= $settings['settings']['code']['errcode'];
	    $adminmail 	= $settings['settings']['adminmail'];

	    $resArr = [];
	    $post = $request->getParsedBody();
	    $check =  $this->security->validateRequired(['name','email','message'],$post);
        if(isset($check['error'])) {
            return $response->withJson($check);
        }
	    $post['adminmail'] = $adminmail ;
	  
   		$this->mailer->sendMessage('/mail/getintouch.php', ['data' => $post], 

   			function($message) use($post) {
	    		$name = " ";	 
	    		$frommail = $post['email'];  
	    		$fromname = $post['name']; 		    		 
	    		$adminmail = $post['adminmail']; 		    		 
	    		$message->setFrom($frommail,$fromname);
	        	$message->setTo($adminmail,$name);
	        	$message->setSubject(APP_NAME.' Get in touch');
	    	});	 

	  	$resArr['code']  = 0;
		$resArr['error'] = false;
		$resArr['msg'] 	 = "Message sent successfully.";	
   		
   		return $response->withJson($resArr,$code);	
	    
	    /* $response->getBody()->write('Mail sent!');    
	    return $response;  */   
   	}


   	//---Add Notification---
	public function dbClearFunction($request,$response) 
	{
    	global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
	    $check =  $this->security->validateRequired(['dtfrom','dtto','atype'],$input);
        if(isset($check['error'])) {
            return $response->withJson($check);
        }
           	
           	$dtClear = false;
	       	$dtfrom  = $input["dtfrom"];                    
	   		$dtto 	 = $input["dtto"];    
	   		$atype   = $input["atype"];  

	   		 $dtfrom  = strtotime($dtfrom.' '.FROMDTTIME); 
	   		 $dtto    = strtotime($dtto.' '.TODTTIME); 

	   		// echo  date('Y-m-d h:i:s',$dtfrom); 
	   		// echo  date('Y-m-d h:i:s',$dtto); die;

	   			   		
	     	if(!in_array($atype,['matches','transactions']))
	     	{
	       		$resArr = $this->security->errorMessage("Invalid atype");
				return $response->withJson($resArr,$errcode);	
	     	}
	     	
         try{
            
           // $this->db->beginTransaction();
           // $data =  ['title'=>$title,'message'=>$message,'img'=>$img];
           if($atype=='matches')
           {

           	$sql = "SELECT matchid FROM matches WHERE mdategmt >=:dtfrom AND  mdategmt <=:dtto   "; 
        	$stmt = $this->db->prepare($sql);
        	$stmt->execute(["dtfrom"=>$dtfrom,"dtto"=>$dtto]);
        	$matches = $stmt->fetchAll();

        	if(!empty($matches))
        	{

        		$dtClear = true;

        		foreach ($matches as $match) 
	        	{        		
	        		$matchid = $match['matchid'];

	        	/*	echo $delSql  = "DELETE m,mm,mc,mcp,mpp,mppt,mp,jc,ut,utp FROM matches m 
	        		LEFT JOIN matchmeta mm ON m.matchid=mm.matchid 
	        		LEFT JOIN matchcontest mc ON m.matchid=mc.matchid 
	        		LEFT JOIN matchcontestpool mcp ON m.matchid=mcp.matchid 
	        		LEFT JOIN matchplrpts mpp ON m.matchid=mpp.matchid 
	        		LEFT JOIN matchplrptstotal mppt ON m.matchid=mppt.matchid
	        	//	LEFT JOIN matchpoints mp ON m.matchid=mp.matchid 
	        		LEFT JOIN joincontests jc ON m.matchid=jc.matchid 
	        		LEFT JOIN userteams ut ON m.matchid=ut.matchid 
	        		LEFT JOIN userteamplayers utp ON ut.id=utp.userteamid 
	        		WHERE m.matchid=:matchid";

	        		$stmt = $this->db->prepare($delSql);
	        		$a = $stmt->execute(["matchid"=>$matchid]); */



	        		$stmt = $this->db->prepare("DELETE FROM matchmeta WHERE matchid=:matchid");
	        		$stmt->execute(["matchid"=>$matchid]);

	        		$stmt = $this->db->prepare("DELETE FROM matchcontest WHERE matchid=:matchid");
	        		$stmt->execute(["matchid"=>$matchid]);

	        		$stmt = $this->db->prepare("DELETE FROM matchcontestpool WHERE matchid=:matchid");
	        		$stmt->execute(["matchid"=>$matchid]);

	        		$stmt = $this->db->prepare("DELETE FROM matchplrpts WHERE matchid=:matchid");
	        		$stmt->execute(["matchid"=>$matchid]);

	        		$stmt = $this->db->prepare("DELETE FROM matchplrptstotal WHERE matchid=:matchid");
	        		$stmt->execute(["matchid"=>$matchid]);

	        		$stmt = $this->db->prepare("DELETE FROM joincontests WHERE matchid=:matchid");
	        		$stmt->execute(["matchid"=>$matchid]);

	        		

	        	$stmt   = $this->db->prepare("SELECT id FROM userteams WHERE matchid=:matchid");
	        	$stmt->execute(["matchid"=>$matchid]);
	        	$uteams = $stmt->fetchAll();

	        	foreach ($uteams as $uteam ) {
	        		$uteamid = $uteam['id'];
	        		$stmt = $this->db->prepare("DELETE FROM userteamplayers WHERE userteamid=:uteamid");
	        		$stmt->execute(["uteamid"=>$uteamid]);

	        		$stmt = $this->db->prepare("DELETE FROM userteams WHERE id=:uteamid");
	        		$stmt->execute(["uteamid"=>$uteamid]);
	        	}

	        	$stmt = $this->db->prepare("DELETE FROM matches WHERE matchid=:matchid");
	        	$stmt->execute(["matchid"=>$matchid]);


	        	}
        	}
        	
           } else if($atype=='transactions'){
								
	        	$sql = " SELECT id FROM transactions WHERE txdate >=:dtfrom AND  txdate <=:dtto "; 
        		$stmt = $this->db->prepare($sql);
        		$stmt->execute(["dtfrom"=>$dtfrom,"dtto"=>$dtto]);
        		$txns = $stmt->fetchAll();

        		print_r($txns); die;
	        	
	        	foreach ($txns as $txn ) {
	        		
	        		$tid = $txn['id'];
	        		$stmt = $this->db->prepare("DELETE FROM transactionchild WHERE tid=:tid");
	        		$stmt->execute(["tid"=>$tid]);

	        		$stmt = $this->db->prepare("DELETE FROM transactions WHERE id=:uteamid");
	        		$stmt->execute(["tid"=>$tid]);

	        	}
	        	
	        	$stmt = $this->db->prepare("DELETE FROM orders WHERE created >=:dtfrom AND  created <=:dtto");
	        	$stmt->execute(["dtfrom"=>$dtfrom,"dtto"=>$dtto]);
           }

		   if(true)
		   {
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   =  "Data clear successfully";	
		    
		    }else{

                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] 	 = "Data not processes";	
		    }
		}
	    catch(\PDOException $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
				//$this->db->rollBack();
	    }             
        return $response->withJson($resArr,$code);			  
    }


    public function sendSMS($request,$response,$args)
   {      
      	
		$message = "send sms from fantasi.. *****";
		\Security::sendSms('4454454',$message);	    
    	$response->getBody()->write('SMS sent!');    
    	return $response;    
	}


	public function funcCheckIfsc($request,$response,$args)
   	{    
   		global $settings;        
	    $code    	= $settings['settings']['code']['rescode'];
	    $errcode 	= $settings['settings']['code']['errcode'];
	   
	    $resArr = [];
        $input  = $request->getParsedBody();
	    $check  =  $this->security->validateRequired(['ifsccode'],$input);
        if(isset($check['error'])) {
            return $response->withJson($check);
        }
               	        
       	$ifsccode    = $input["ifsccode"];     
       /*
       	$colIfscData = $this->mdb->ifscdata;
        $resIfsc = $colIfscData->findOne(['IFSC'=>$ifsccode]);
        if(empty($resIfsc)){
        	$resArr = $this->security->errorMessage("Invalid IFSC Code");
            return $response->withJson($resArr,$errcode);  
        } */             	   	
        $rst=$this->security->ifsccodeCheck($ifsccode);
		return $response->withJson($rst,$code);		    
   	}

   	public function funcGetAppversion($request,$response,$args) {
		
		global $settings;        
	    $code    	= $settings['settings']['code']['rescode'];
	    $errcode 	= $settings['settings']['code']['errcode'];
	   
	    $resArr = [];
               
       	$atype 	= $args['atype'];     
       	
        if(!in_array($atype,['android','ios'])){
        	$resArr = $this->security->errorMessage("Invalid atype");
            return $response->withJson($resArr,$errcode);  
        }    

        $appver = $this->mdb->appversion;
        $res = $appver->findOne(['apptype'=>$atype]);
		unset($res['_id']);

	  	$resArr['code']  = 0;
		$resArr['error'] = false;
		$resArr['msg'] 	 = "App version";	
		$resArr['data']  = $res ;	

		
		return $response->withJson($resArr,$code);	
   } 

  
  	public function getMatchData($request,$response) {
		
		global $settings,$baseurl;       
	    $code    	= $settings['settings']['code']['rescode'];
	    $errcode 	= $settings['settings']['code']['errcode'];
	    $resArr = [];
	    $showmatchkey=0;
        $live=0;
	    try{
		        $collection = $this->mdb->completematches;
		        $matchid= $_GET['matchid'];
		        if(isset($_GET['responsetype']) && $_GET['responsetype']=='score'){
		        	if(isset($_GET['matchid']) && empty($_GET['matchid'])) {
						$resArr = $this->security->errorMessage("Match Id Required");
            			return $response->withJson($resArr,$errcode);
		        	}
		        	//$rtrnKeys=['_id'=>0,'inningsdetail'=>1,'matchstarted'=>1,'status'=>1,'status_overview'=>1,'type'=>1,'match'=>1];
		        	$showmatchkey=1;
		        	$matchData=['matchid'=>$matchid];
		        	$msg="Invalid Match Id";
		        }else if(isset($_GET['responsetype']) && $_GET['responsetype']=='live'){
		        	$matchData=['status'=>'started'];
		        	
		        	$msg="Record Not Found";
		        	$live=1;
		        }else{
		        	if(isset($_GET['matchid']) && empty($_GET['matchid'])) {
						$resArr = $this->security->errorMessage("Match Id Required");
            			return $response->withJson($resArr,$errcode);
		        	}
		        	$msg="Invalid Match Id";
		        	$matchData=['matchid'=>$matchid];
		        }
		        
		        //['$project'=>$rtrnKeys]
		        $res=$collection->aggregate([['$match'=>$matchData]])->toArray();
		        if(!empty($res)){
		        	$logoUrl  = $baseurl.'/uploads/teamlogo/';
		        	

		        	if($live){
		        		foreach ($res as $key => $value) {
		        			$matchid=$value['matchid'];
		        			
				        	$sql = " SELECT * FROM matches WHERE matchid=:matchid"; 
			        		$stmt = $this->db->prepare($sql);
			        		$stmt->execute(["matchid"=>$matchid]);
			        		$match = $stmt->fetch();
			        		if($match['gameid']==FOOTBALLID){ 
			        		
			        			$inningsdetailf['b']=["1"=>$value["match"]['score']['away']];
			        			$inningsdetailf['a']=["1"=>$value['match']['score']['home']];
			        			$inningsdetailf['team_a']=["gamekey"=>'away',
			        			'logo'=>$logoUrl.$match['team1logo'],
			        			'short_name'=>$value["teams"][$value["match"]['match']['away']]["code"],
			        			'full_name'=>$value["match"]["teams"][$value["match"]['match']['away']]["name"]];
			        			$inningsdetailf['team_b']=["gamekey"=>'home',
			        			'logo'=>$logoUrl.$match['team2logo'],
			        			'short_name'=>$value["teams"][$value["match"]['match']['home']]["code"],
			        			'full_name'=>$value["match"]['teams'][$value["match"]['match']['home']]["name"]];
			        			$result[]=['inningsdetail'=>$inningsdetailf];
			        		}else{
			        			
			        			$inningsdetail=$value['inningsdetail'];
			        			if($match['gameid']==KABADDIID){ 
			        				$inningsdetail['a']=["1"=>$value['inningsdetail']['a']];
			        				$inningsdetail['b']=["1"=>$value['inningsdetail']['b']];
			        			}
			        			$inningsdetail['team_a']=['logo'=>$logoUrl.$match['team1logo'],'short_name'=>$value["match"]['teams']["a"]["short_name"],'full_name'=>$value["match"]['teams']["a"]["name"]];
			        			$inningsdetail['team_b']=['logo'=>$logoUrl.$match['team2logo'],'short_name'=>$value["match"]['teams']["b"]["short_name"],'full_name'=>$value["match"]['teams']["b"]["name"]];
			        		
			        			unset($value['match']);
				        		$value['gameid']=$match['gameid'];
				        		$result[]=$value;
			        		}
			        		
		        		}

		        		$resArr['data']= ['list'=>$result];
		  			}else{

		  				$sql = " SELECT * FROM matches WHERE matchid=:matchid"; 
		        		$stmt = $this->db->prepare($sql);
		        		$stmt->execute(["matchid"=>$matchid]);
		        		$match = $stmt->fetch(); 

		        		if($match['gameid']!=FOOTBALLID){
		        		$inningsdetail=$res[0]['inningsdetail'];
		        		if($match['gameid']==KABADDIID){
			        		$inningsdetail['a']=["1"=>$inningsdetail['a']];
				        	$inningsdetail['b']=["1"=>$inningsdetail['b']];
			        	}
		        		$inningsdetail['team_a']=["gamekey"=>'a','logo'=>$logoUrl.$match['team1logo'],'short_name'=>$res[0]["match"]['teams']["a"]["short_name"],'full_name'=>$res[0]["match"]['teams']["a"]["name"]];
		        		$inningsdetail['team_b']=["gamekey"=>'b','logo'=>$logoUrl.$match['team2logo'],'short_name'=>$res[0]["match"]['teams']["b"]["short_name"],'full_name'=>$res[0]["match"]['teams']["b"]["name"]];
		  				}else{

		  					$inningsdetailf['b']=["1"=>$res[0]["match"]['score']['away']];
			        			$inningsdetailf['a']=["1"=>$res[0]['match']['score']['home']];
			        			$inningsdetailf['team_a']=["gamekey"=>'home',
			        			'logo'=>$logoUrl.$match['team1logo'],
			        			'short_name'=>$res[0]["match"]["teams"][$res[0]["match"]['match']['home']]["code"],
			        			'full_name'=>$res[0]["match"]["teams"][$res[0]["match"]['match']['home']]["name"]];
			        			$inningsdetailf['team_b']=["gamekey"=>'away','logo'=>$logoUrl.$match['team2logo'],'short_name'=>$res[0]["match"]['teams'][$res[0]["match"]['match']['away']]["code"],'full_name'=>$res[0]["match"]['teams'][$res[0]["match"]['match']['away']]["code"]];
			        		$res[0]['inningsdetail']=$inningsdetailf;
		  				}
						if($match['gameid']==CRICKETID){
					  		$res[0]['msg_info']=$res[0]['match']["msgs"];
					  		}
					  		else if($match['gameid']==KABADDIID){

					  		$res[0]['msg_info']=['result'=>@$res[0]['match']["result"]['str'],'info'=>'','completed'=>@$res[0]['match']["result"]['str']];
					  		}
				  		if($showmatchkey){

				  			if($match['gameid']==CRICKETID){
					  		$res[0]['msg_info']=$res[0]['match']["msgs"];
					  		}
					  		else if($match['gameid']==KABADDIID){
					  		$res[0]['msg_info']=['result'=>@$res[0]['match']["result"]['str'],'info'=>'','completed'=>@$res[0]['match']["result"]['str']];
					  		}

				  			unset($res[0]['match']);
				  		}
				  		$res[0]['gameid']=$match['gameid'];
						$resArr['data']  = $res[0];
					}

					$resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg'] 	 = "Match Details";
				}else{
					$resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg'] 	 = $msg;	
				}
			}
		    catch(\Exception $e)
		    {    
		        $resArr['error'] = true;
		        $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
		        $code = $errcode;
		    }
		
      return $response->withJson($resArr,$code);

   } 
  
  /*
   public function getLiveMatchData($request,$response) {
		
		global $settings,$baseurl;       
	    $code    	= $settings['settings']['code']['rescode'];
	    $errcode 	= $settings['settings']['code']['errcode'];
	    $resArr = [];
	    $showmatchkey=0;
        if(isset($_GET['matchid']) && !empty($_GET['matchid'])) {
	    try{
		        $matchid= $_GET['matchid'];
		        if(isset($_GET['responsetype']) && $_GET['responsetype']=='score'){
		        	//$rtrnKeys=['_id'=>0,'inningsdetail'=>1,'matchstarted'=>1,'status'=>1,'status_overview'=>1,'type'=>1,'match'=>1];
		        	$showmatchkey=1;
		        }else{
		        	//$rtrnKeys=['_id'=>0];
		        }
		        $collection = $this->mdb->completematches;
		        //['$project'=>$rtrnKeys]
		        $res=$collection->aggregate([['$match'=>['matchid'=>$matchid]]])->toArray();
		        if(!empty($res)){
		        	$logoUrl  = $baseurl.'/uploads/teamlogo/';
		        	$inningsdetail=$res[0]['inningsdetail'];

		        	$sql = " SELECT * FROM matches WHERE matchid=:matchid"; 
	        		$stmt = $this->db->prepare($sql);
	        		$stmt->execute(["matchid"=>$matchid]);
	        		$match = $stmt->fetch(); 
	        		$inningsdetail['team_a']=['logo'=>$logoUrl.$match['team1logo'],'short_name'=>$res[0]["match"]['teams']["a"]["short_name"],'full_name'=>$res[0]["match"]['teams']["a"]["name"]];
	        		$inningsdetail['team_b']=['logo'=>$logoUrl.$match['team2logo'],'short_name'=>$res[0]["match"]['teams']["b"]["short_name"],'full_name'=>$res[0]["match"]['teams']["b"]["name"]];
				  	if($showmatchkey){
				  		if($match['gameid']==1){
				  		$res[0]['msg_info']=$res[0]['match']["msgs"];
				  		}
				  		else if($match['gameid']==3){
				  		$res[0]['msg_info']=['result'=>@$res[0]['match']["result"]['str'],'info'=>'','completed'=>@$res[0]['match']["result"]['str']];
				  		}
				  		unset($res[0]['match']);

				  	} 
				  	$resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg'] 	 = "Match Details";	
					$resArr['data']  = $res[0];
				}else{
					$resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg'] 	 = "Invalid Match Id";	
				}
			}
		    catch(Exception $e)
		    {    
		        $resArr['error'] = true;
		        $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
		        $code = $errcode;
		    }
		}else{
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg'] 	 = "Match Id Required";	
		}
      return $response->withJson($resArr,$code);

   }  */
    	public function getAPIData($request,$response,$args) {
		
		global $settings,$baseurl;       
	    $code    	= $settings['settings']['code']['rescode'];
	    $errcode 	= $settings['settings']['code']['errcode'];
	    $gameDataApi 	= $settings['settings']['path']['gameDataApi'];
	    $resArr = [];
	    $showmatchkey=0;
                
        $gameid = $args['gameid']; 
        $matchid= $args['matchid'];
		$urls='';

		if($gameid == CRICKETID){
        	$urls = $gameDataApi.'/matches/matchshortdetail/'.$matchid;
		}else if($gameid == FOOTBALLID){
        	$urls = $gameDataApi.'/football/matchshortdetail/'.$matchid;
		}else if($gameid == KABADDIID){
        	$urls = $gameDataApi.'/kabaddi/matchshortdetail/'.$matchid;
		}
		
        $headers = [
             	'Accept: application/json',
    			'Content-Type: application/json'
        ];
        $ch = curl_init ();
        curl_setopt($ch, CURLOPT_URL, $urls);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec ( $ch );
        curl_close ( $ch );

		$result=json_decode($result);	

      	return $response->withJson($result,$code);

   } 

   public function dmFantasyPointsFun($request,$response,$args) {
		
			global $settings,$baseurl;       
		    $code    	= $settings['settings']['code']['rescode'];
		    $errcode 	= $settings['settings']['code']['errcode'];
		    $dmFantasyApi 	= $settings['settings']['path']['dmfantasypoint'];
		    $resArr = [];
		    
	        $matchid= $args['matchid'];
	        $urls = $dmFantasyApi.'?matchid='.$matchid;			
	        $headers = [
	             	'Accept: application/json',
	    			'Content-Type: application/json'
	        ];
	        $ch = curl_init ();
	        curl_setopt($ch, CURLOPT_URL, $urls);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec ( $ch );
	        curl_close ( $ch );

			$result=json_decode($result);							
	      	return $response->withJson($result,$code);
   }

   public function mailSendCron($request,$response){ 

   global $settings;       
   $code    	= $settings['settings']['code']['rescode'];
   $errcode 	= $settings['settings']['code']['errcode'];
   $dir    = __DIR__ . '/../settings/playwebsetting';  
   $addset = @file_get_contents($dir);
   $input = $request->getParsedBody();
    if ($addset != false) {
	    $res = json_decode($addset, true);
	    if(isset($res['mailconfig']) && !empty($res['mailconfig'])){
	    	try{

	    		$limit  = (isset($input['limit']) && !empty($input['limit']))?$input['limit']:120;
	    		$page=0;
	            if(isset($input['page']) && !empty($input['page']))
	            {
	             	$page   = $input['page'];  //0   LIMIT $page,$offset
	             	$page = ($page-1)*$limit;	
	            }
		    	$collection= $this->mdb->notifyandmails;
		    	//$allRec=$collection->find([],['limit'=>10])->toArray();
		    	$rstSort=['created'=>(-1)];
		    	$allRec=$collection->aggregate([['$skip'=>$page],['$limit'=>$limit],['$sort'=>$rstSort]])->toArray(); 
		    	if($allRec){ 
		    	foreach ($allRec as $key => $value) { 
		    	$mailData=$value['maildata'];
			    $mailData['webname']=$settings['settings']['webname'];
		    	$mailData['mailconfig']=$res['mailconfig'];
		    	$mailRst = 0;
		    	try{
		    		$mailRst = 1;
				    $this->mailer->sendMessage('/mail/'.$mailData['template'], ['data' => $mailData], function($message) use($mailData) {
			          $name    = " ";
			          $webname = $mailData['webname'];
			          if(!empty($mailData['name'])){            
			            $name = $mailData['name'];
			          }         
			            $message->setTo($mailData['email'],$name);
			            $message->setSubject($mailData['subject']);
			           
			        });

				}catch(Swift_TransportException $se){
					$mailRst = 0;
		   			$string = date("Y-m-d H:i:s")  . ' - ' . $se->getMessage() . PHP_EOL;
					file_put_contents("./../logs/errorlog_mail.txt", $string);
		   		}
		        if($mailRst){ 

		        	if(isset($value['notify']) && !empty($value['devicetype']) && $value['devicetype']!='web' && $value['notify']){
		           		$this->security->notifyUser($value["notify"]);
		           	}
		        	$collection->deleteOne(["_id"=>$value['_id']]); 
		        }
		        
			} 
				$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] 	 = "Send";

			}else{
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg'] 	 = "Record not found";
			}


		}
	    catch(\Exception $e)
	    {    
	        $resArr['error'] = true;
	        $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
	        $code = $errcode;
	    }
	    }else{
	    	$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg'] 	 = "Record not found";
	    } 
    }else{
    	$resArr['code']  = 1;
		$resArr['error'] = true;
		$resArr['msg'] 	 = "Record not found";
    }

    return $response->withJson($resArr,$code);
   }


   //List of winners from privatecontestsizes collection For used in admin 
	public function getNumOfWinners($request,$response){

		global $settings,$baseurl;
	 	$code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];         
	  	$loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$userid 	= 	$loginuser['id'];
	    $resArr =  []; 
	    $input  =  $request->getParsedBody();
	    $check  =  $this->security->validateRequired(['atype'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}
		$atype = $input['atype'];
		if(!in_array($atype, ['list','details','upsert','getbycnstsize','upsertrank'])){
			$resArr = $this->security->errorMessage("Invalid Url");
			return $response->withJson($resArr,$errcode); 
		}
		
		try{
			$collection=$this->mdb->privatecontestsizes;
			$rst=$collection->aggregate([['$sort'=>['contestsize'=>1]],
											['$project'=>['contestsize'=>1]]
											])->toArray();
			$resArr['data']  =  ['list'=>[] ,'total'=>$collection->count()];
			$id 	= isset($input['id'])?$input['id']:(string)$rst[0]['_id'];
			$privatecontestwinslebs=$this->mdb->privatecontestwinslebs;
			$id= new ObjectId($id);
			if(isset($input['contestsize']) && !empty($input['contestsize']))
			{
				$contestsize=(int)$input['contestsize'];
				$selectedSlabs=$collection->findOne(['contestsize'=>$contestsize]);
			}else
				$selectedSlabs=$collection->findOne(['_id'=>$id]);
			if($atype=='upsert'){

				$winner 	= isset($input['winner'])?$input['winner']:0;
				if($winner){
					$winnerslabs=(array)$selectedSlabs['winnerslabs'];
					if (($key = array_search($winner,(array)$selectedSlabs['winnerslabs'])) !== false) {
					    unset($winnerslabs[$key]);
					}else{
						array_push($winnerslabs,$winner);
					}
					rsort($winnerslabs);
					$collection->updateOne(["_id"=>$id],
											['$set'=>["winnerslabs"=>$winnerslabs]]);
					$selectedSlabs["winnerslabs"]=$winnerslabs; 
				}
			}

			if($atype=='upsertrank'){
				$total=0;
				$ranks 	= isset($input['ranks'])?$input['ranks']:'';
				$rankid = isset($input['rankid'])?$input['rankid']:'5555555555555';
				$rankid= new ObjectId($rankid);
				$rslabs=$privatecontestwinslebs->find(['_id'=>$rankid])->toArray();
				if($ranks && $rslabs){
					$updateranks=[];
					foreach ($ranks as $key => $item) {
						$nranks=$item;
						$total=$total+$item["percent"];
						if($key>0){
					        if($item["percent"] > $ranks[$key-1]["percent"])
					        {
					        	$rank=($item["pmin"] && $item["pmax"] && $item["pmin"]===$item["pmax"]) ? $item["pmin"] : $item["pmin"]."-".$item["pmax"];
					        	$resArr = $this->security->errorMessage("Invalid Percent ".$rank);
								return $response->withJson($resArr,$errcode);
					        }

					      } 
					      $nranks['percent']=$item["percent"];
					      $updateranks[]=$nranks;
					}

					if($total<100 || $total>100){
				        $resArr = $this->security->errorMessage("Invalid Total Percent ".$total);
						return $response->withJson($resArr,$errcode);
				    }

				    $privatecontestwinslebs->updateOne(["_id"=>$rankid],
											['$set'=>["ranks"=>$updateranks]]);

				}
			}
			
			$listAllSlabs=$privatecontestwinslebs->find(['winner'=>['$lte'=>$selectedSlabs['contestsize']]])->toArray();
			$resArr['data']['selectedSlabs']=$selectedSlabs;
			$resArr['data']['winnerslabs']=$listAllSlabs;
			if($rst){
				$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Prize BreakUp List.';	
				
			}else{
				$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Prize break up not found.';
			}
		}catch(\Exception $e){
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
		}
		return $response->withJson($resArr);
	}


	

}
?>


