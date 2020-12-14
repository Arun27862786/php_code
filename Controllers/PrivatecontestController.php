<?php
namespace Apps\Controllers;
use MongoDB\BSON\ObjectId;

class PrivatecontestController
{
	protected $container;

	public function __construct($container)
	{
	      $this->container = $container; 
	}
	
	public function __get($property)
	{
	    if($this->container->{$property})
	    {
	        return $this->container->{$property};
	    }
	}

	public function createPrivateContest($request,$response){

		global $settings,$baseurl;
	 	$code    	= $settings['settings']['code']['rescode'];
	 	$errcode 	= $settings['settings']['code']['errcode'];
	  	$loginuser 	= $request->getAttribute('decoded_token_data');
    	$uid 		= $loginuser['id'];
	    $resArr 	= [];
	    $input  	= $request->getParsedBody();

	    $check  =  $this->security->validateRequired(['matchid','winningprize','contestsize','ismultiple','winners','joinfees','atype'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}

		$atype 			= $input['atype'];
		$matchid 		= $input['matchid'];
		$name 			= (!empty($input['name']))?$input['name']:NULL;
		$winningprize 	= $input['winningprize'];
		$contestsize 	= intval($input['contestsize']);
		$ctype 			= intval($input['ismultiple']);
		//$prizebreakid 	= $input['prizebreakid'];
		$winners 	    = intval($input['winners']);
		$fees 			= $input['joinfees'];
		$poolfees 		= $input['joinfees'];
		$c 				= 0;
		$m              = 0;
		$s 				= 0;
		$created 		= time();
		if($ctype){$m=1;}else{$s=1;};

		if($fees < 5){
			$resArr = $this->security->errorMessage("Invalid join fees");
			return $response->withJson($resArr,$errcode);
		}

		$pvtCntstWinSlebs = $this->mdb->privatecontestwinslebs;

		$resPvtCntstWinSlebs = $pvtCntstWinSlebs->findOne(["winner"=>$winners]);
		
		if(empty($resPvtCntstWinSlebs) || empty($resPvtCntstWinSlebs['ranks'])){

			$resArr = $this->security->errorMessage("Invalid contestsize or winners");
			return $response->withJson($resArr,$errcode);
		}

		$prizekeyvalue = (array) $resPvtCntstWinSlebs['ranks'];
		
	try{
		
		$checkMatchidForJoin = $this->security->checkMatchidForJoin($matchid);

        if(empty($checkMatchidForJoin))
        {
            $resArr = $this->security->errorMessage("Invalid matchid.");

			return $response->withJson($resArr,$errcode);            
        }

        if($this->security->checkMatchFreaz($matchid)){

        	$resArr = $this->security->errorMessage("You can't join now.");

			return $response->withJson($resArr,$errcode); 	           
        }
        

		if($contestsize < CNTSSIZEMIN || $contestsize > CNTSSIZEMAX){

			$resArr = $this->security->errorMessage("Invalid Contest Size");
			return $response->withJson($resArr,$errcode);
		}

		$resPvtCm = $this->security->getrefcommisions('pvtcntstcomission');
		
		$pvtCmVal = $resPvtCm['amount'];
		//$this->calculateJoinFees();

		$joinFeeCal  = ($winningprize / $contestsize);
		$pvtCmValCal = ($joinFeeCal * $pvtCmVal)/100;
		$joinFeeCal  = ceil($joinFeeCal + $pvtCmValCal);

		if($joinFeeCal != $fees){
			$resArr = $this->security->errorMessage("Invalid join fees");
			return $response->withJson($resArr,$errcode);
		} 

		$pvtCntstSizes = $this->mdb->privatecontestsizes;

		$resPvtCntstSizes = $pvtCntstSizes->findOne(["contestsize"=>$contestsize,"winnerslabs"=>$winners]);
		
		if(empty($resPvtCntstSizes)){
			$resArr = $this->security->errorMessage("Invalid contestsize or winners");
			return $response->withJson($resArr,$errcode);
		}
		
		$usrWltBal = $this->security->getUserWalletBalance($uid);
            
        $wltbal  =  $usrWltBal['walletbalance'];
        $wltwin  =  $usrWltBal['wltwin'];
        $wltbns  =  $usrWltBal['wltbns'];

        $wltbalPre  =  $wltbal;
        $wltwinPre  =  $wltwin;
        $wltbnsPre  =  $wltbns;
      
        $payBnsWlt 	= 0;
        $payWinWlt 	= 0;
        $payBalWlt 	= 0;
        $bnsdeduction = 0;
        
        $getPoolDetails	= $this->security->getContestDetailsPrivate();

        $contestid  = $getPoolDetails['id'];
        /*$bnsDedRes = $this->security->getrefcommisions("bnsdeduction");
        $bnsdeduction = $bnsDedRes['amount'];*/ 

        $bnsDedRes = $this->security->getContestDetails($contestid);
        $bnsdeduction = $bnsDedRes['dis_val'];
        //if($bnsDedRes['dis_val'] != 0){
        //}

        if($fees > 0){
//-------------------WLT BNS--------------------------        	
        	$prsntPay = (($fees*$bnsdeduction)/100);   
        	if($wltbns > $prsntPay )
        	{        						             
				$fees		=	$fees - $prsntPay ;
				$wltbns 	=  	$wltbns - $prsntPay ;
				$payBnsWlt 	= 	$prsntPay;			

        	}else{        	

        		$fees   		=  	$fees - $wltbns;  
				$payBnsWlt 		= 	$wltbns;
				$wltbns 		=  	0;
        	}
//-------------------WLT Bal--------------------------
        	if($fees > 0 && $wltbal > 0){        	           	   
    	   		$bal  = $wltbal - $fees ; 
				if($bal < 0){				   
                   $fees   		=  	$fees - $wltbal;  
				   $payBalWlt 	= 	$wltbal;
				   $wltbal 		=  	0;
				}else{
					$payBalWlt 	= 	$fees;
					$wltbal 	=  	$bal;
					$fees		=	0 ;
				}        	   		
        	}
//---------------------WLT WIN--------------------------------
        	if($fees > 0 && $wltwin > 0)
        	{        	   
    	   		$bal  = $wltwin - $fees ; 
				if($bal < 0){
                   
                   $fees   		=  	$fees - $wltwin;  
				   $payWinWlt 	= 	$wltwin;
				   $wltwin 		=  	0;
				   
				}else{

					$payWinWlt 	= 	$fees;
					$wltwin 	=  	$bal;
					$fees		=	0 ;
				}
        	}
        }

        $outputRes = ['wallet'=>$usrWltBal,'paybnswlt'=>(string)sprintf("%.2f", $payBnsWlt),'paybalwlt'=>(string)sprintf("%.2f", $payBalWlt),'paywinwlt'=>(string)sprintf("%.2f", $payWinWlt),'fees'=>(string)sprintf("%.2f", $fees),'bnsdeduction'=>$bnsdeduction,"entryfees"=>$poolfees]; 	

        if($atype == "prejoin")
        {
        	$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Confirmation";
			$resArr['data']  = $outputRes;

        }else{

        	$this->db->beginTransaction();
        	
        	if($fees > 0){
           		$resArr = $this->security->errorMessage("Not have balance to join this contest,add balance first");
		   		return $response->withJson($resArr,$errcode);
        	}

        	if(!isset($input["uteamid"]) || empty($input["uteamid"])){
        	  $resArr = $this->security->errorMessage("teamid missing or empty");
		   		return $response->withJson($resArr,$errcode);		
        	}

        	$uteamid 		 =  $input["uteamid"];
	        $isUserTeamExist = $this->security->isUserTeamExist($matchid,$uid,$uteamid);
	        if(empty($isUserTeamExist)){
	           $resArr = $this->security->errorMessage("Invalid teamid.");
			   return $response->withJson($resArr,$errcode);            	
	        }

	        $cd  = $this->security->generateRandomString(12);
	        $ccode = $this->security->checkUniqueCodePrivateCntst($cd);	        
	        $saveContest = $this->security->poolAndBreakPrizeAddDB($contestid,$poolfees,$winningprize,$winners,$contestsize,$c,$m,$s,$prizekeyvalue,true,$name,$ccode,$uid,$created);

	        if($saveContest['error'] == true){
	        	$resArr = $this->security->errorMessage($saveContest['msg']);
			   return $response->withJson($resArr,$errcode);
	        }

	        $poolcontestid = $saveContest['createdid'];

	        /*$checkAlreadyJoinContest = $this->security->checkAlreadyJoinContest($uid,$matchid,$poolcontestid,$uteamid);
	        if(!empty($checkAlreadyJoinContest)) {
	         	$resArr = $this->security->errorMessage("already joined this contest");
			    return $response->withJson($resArr,$errcode);	
	        }*/

	        $stmtNew = $this->db->prepare("CALL assignContest(:matchid,:poolcontestid)");	        
	        $stmtNew->execute(["matchid"=>$matchid,"poolcontestid"=>$poolcontestid]);

	        $matchDetl = $this->security->getMatchDetails($matchid);
	        $gameid    = $matchDetl['gameid'];

	        $params = ["userid"=>$uid,"matchid"=>$matchid,"poolcontestid"=>$poolcontestid,"uteamid"=>$uteamid,"fees"=>$poolfees,"created"=>$created,"gameid"=>$gameid];

	        $stmt = $this->db->prepare("INSERT INTO joincontests (userid,matchid,poolcontestid,uteamid,fees,created,gameid) VALUES (:userid,:matchid,:poolcontestid,:uteamid,:fees,:created,:gameid)");  
	  
	        if($stmt->execute($params))
	        {
		        $lastId = $this->db->lastInsertId();				 	
		            //$balance = $getUserWalletBalance['walletbalance'] - $fees;
		        //$this->security->updateTransactionFunction();

			    if($payBnsWlt > 0){
			    	$payBnsWlt = -$payBnsWlt;
			    	$updtUsrWlt = $this->security->updateUserWallet($uid,"wltbns",$wltbns);
			    	$this->security->updateTransaction($uid,$payBnsWlt,$created,$lastId,DR,CJOIN,WLTBNS,$wltbnsPre,$wltbns);
			    }

			    if($payBalWlt > 0){
			    	$payBalWlt  = -$payBalWlt;
			    	$updtUsrWlt = $this->security->updateUserWallet($uid,"walletbalance",$wltbal);
			    	$this->security->updateTransaction($uid,$payBalWlt,$created,$lastId,DR,CJOIN,WLTBAL,$wltbalPre,$wltbal);
			    }

			    if($payWinWlt > 0){
			    	$payWinWlt = -$payWinWlt;		    	
			    	$updtUsrWlt = $this->security->updateUserWallet($uid,"wltwin",$wltwin);
			    	$this->security->updateTransaction($uid,$payWinWlt,$created,$lastId,DR,CJOIN,WLTWIN,$wltwinPre,$wltwin);
			    }

			   /* $webname= $settings['settings']['webname'];
		        $titles=ucwords($webname.' Join Contest');	            
	            $matchname 	 = $matchDetl['matchname'];
	            $poolDetails = $this->security->getPoolDetails($poolcontestid);
	            $poolPrize 	 = $this->security->getPoolBreakPrize($poolcontestid);
	            $userData    = $this->security->getUserDetail($uid);
	            $poolDetails['matchname'] = $matchname;
	            $poolDetails['webname']   = $webname;
	            $poolDetails['poolprize'] = $poolPrize;
	       //     $poolDetails['userdata']  = $userData;
	            $poolDetails['template']  = 'joincontest.php';
	            $poolDetails['subject']   = $titles;
	            $poolDetails['email']     = $userData['email'];
	            $poolDetails['content']   = '';
	            $poolDetails['name']      = ($userData['name'])?$userData['name']:$userData['teamname'];
            	$notification_data	=	['token'=>[$userData['devicetoken']],'devicetype'=>$userData['devicetype'],'message'=>'','title'=>$titles,'ntype'=>CONTEST_JOIN_NOTIFY,'notify_id'=>1];
				$notiyAndMailData['email']=$userData['email'];
				$notiyAndMailData['userid']=$userData['id'];
				$notiyAndMailData['phone']=$userData['phone']; 
				$notiyAndMailData['type']=CONTEST_JOIN_NOTIFY;
				$notiyAndMailData['devicetype']=$userData['devicetype'];
				if($userData['devicetype']!='web'){
					$notiyAndMailData['notify']=$notification_data;
				}
				
				$notiyAndMailData['maildata'] = $poolDetails;
				$notiyAndMailData['created']  = $created;
				$collections = $this->mdb->notifyandmails;
    			$collections->insertOne($notiyAndMailData);

    			$this->applyRefferBonus($uid,$poolfees,$created,$lastId); */
    				           
	            $this->db->commit();
	    		$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = "Contest created successfully.";
				$resArr['data']  = ["contestcode"=>$ccode];

	        }else{

                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = "Contest not created.";
	        }
	    }    
		
		}catch(\Exception $e){

			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
		}
		return $response->withJson($resArr);
	}


	public function prizeBreakUp($request,$response){

		global $settings,$baseurl;
	 	$code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];         
	  	$loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$userid 	= 	$loginuser['id'];
	    $resArr 	=  []; 
	    $input  	=  $request->getParsedBody();

	    $check  =  $this->security->validateRequired(['tolwinprize','contestsize'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}

		$tolwinprize 	= $input['tolwinprize'] ;
		$contestsize 	= (int)$input['contestsize'] ;
		try{
			
			$collection=$this->mdb->privatecontestsizes;

			$rst=$collection->aggregate([['$match'=>['contestsize'=>$contestsize]],
				['$unwind'=>'$winnerslabs'],
				['$sort'=>['contestsize'=>1,'winnerslabs'=>-1]],
				['$lookup'=>['from'=>'privatecontestwinslebs','localField'=>'winnerslabs','foreignField'=>'winner','as'=>'winnerslabs']],
				['$group'=>['_id'=>'$contestsize','winnerslabs'=>['$push'=>['$arrayElemAt'=>['$winnerslabs',0]]]]],
			])->toArray();
			if($rst){
				$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Prize BreakUp List.';	
				$resArr['data']  =  $rst[0];
			}else{
				$resArr['code']  = 1;
				$resArr['error'] = true;
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



	/* ------ */

	public function checkPrivateContest($request, $response)
	{
        global $settings,$baseurl;
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id'];
        $input      = 	$request->getParsedBody();
        $dataResArr = 	[];
    	
	    $check = $this->security->validateRequired(['matchid','ccode'],$input);
	    if(isset($check['error'])) {
            return $response->withJson($check);
        }
    	
    	$matchid 		=  $input["matchid"];    
    	$ccode			=  $input["ccode"];
    	$created 		=  time();

    	$resCodeContest = $this->security->getContestByCode($ccode);  
    	if(empty($resCodeContest)){
    		$resArr = $this->security->errorMessage("Invalid Code.");
			return $response->withJson($resArr,$errcode);	
    	}
    	$poolcontestid = $resCodeContest['id'];
         
    try{
        
        $checkMatchidForJoin = $this->security->checkMatchidForJoin($matchid);

        if(empty($checkMatchidForJoin))
        {
            $resArr = $this->security->errorMessage("Invalid Code.");
			return $response->withJson($resArr,$errcode);            
        }

        if($this->security->checkMatchFreaz($matchid)){
        	$resArr = $this->security->errorMessage("You can't join now.");
			return $response->withJson($resArr,$errcode); 	           
        }
      
        $checkPoolForJoinContest = $this->security->checkPoolForJoinContest($matchid,$poolcontestid);
        if(!$checkPoolForJoinContest){
           $resArr = $this->security->errorMessage("Invalid Code.");
		   return $response->withJson($resArr,$errcode);            	
        }

        $getPoolDetails = $this->security->getPoolDetails($poolcontestid);
        
        $m = $getPoolDetails['m'];
        $s = $getPoolDetails['s'];

        if(empty($getPoolDetails)){
           $resArr = $this->security->errorMessage("Invalid Code.");
		   return $response->withJson($resArr,$errcode);
        }

      /*  $usrJoinTeam = $this->security->getUsrJoinTeamInPool($uid,$matchid,$poolcontestid);
        if(count($usrJoinTeam) >=1 && $s == 1){
           $resArr = $this->security->errorMessage("You can join with single team only.");
		   return $response->withJson($resArr,$errcode);              
        }*/           
    		$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Cade is valid";
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

	

}


?>
