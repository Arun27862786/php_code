<?php
namespace Apps\Controllers;

class RummyController
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

// Fantasy Front API 
   public function balanceTrnsRummy($request,$response){

		global $settings,$baseurl;
	 	$code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];         
	  	$loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$userid 	= 	$loginuser['id'];
	    $resArr 	=  []; 
	    $input  	=  $request->getParsedBody();

	    $check  =  $this->security->validateRequired(['amount'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}
		$trnsAmount 	= (float)$input['amount'] ;
		try{
			$walbal=$this->security->getUserWalletBalance($userid);
			if($walbal && $walbal['walletbalance']>=$trnsAmount){
				$userData=$this->security->getUserById($userid);
				$rumregdata = [
							"username"=>$userData['phone'],
							"userid"=>$userid,
							"amount"=>$trnsAmount,
			
					];

		$rumRes = $this->security->callCurl("getChipsFromFancy",$rumregdata,"POST");

			print_r($rumRes); die;
			
			if($rumRes['error'] != 0){
				$resArr = $this->security->errorMessage("Rummy register server issue, Contact to webmaster");
	           	return $response->withJson($resArr,$errcode);
			}
			$prebal	= $walbal['walletbalance'];
			$amount = $walbal['walletbalance']-$trnsAmount;	
			$this->security->updateUserWalletBalance($userid,$amount);
			$created=time();
			$txid= ($rumRes['data'] && $rumRes['data']['txnid'])? $rumRes['data']['txnid']:'';
			$trnsId=$this->updateTransaction($userid,$trnsAmount,$created,$txid,DR,RUMMY_MONEY,WLTBAL,$prebal,$amount);
			$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = 'Money transfered Fancy to Rummy.';	
			$resArr['data']  =  ['txnid'=>$trnsId];
			}else{
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = 'Insufficient Balance';
			}
		}catch(\Exception $e){
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
		}
		return $response->withJson($resArr);
	}


	// Rummy API
	public function getRummyMoney($request,$response){

		global $settings,$baseurl;
	 	$code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];         
	  	/*$loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$userid 	= 	$loginuser['id'];*/
	    $resArr 	=  []; 
	    $input  	=  $request->getParsedBody();

	    $check  =  $this->security->validateRequired(['username','amount'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}
		$phone 	    = (int)$input['username'];
		$trnsAmount 	= (float)$input['amount'];
		try{
			$userData=$this->security->phoneExisting($phone);
			if($userData){
			$userid=$userData['id'];
			$walbal=$this->security->getUserWalletBalance($userid);
			$prebal	= $walbal['walletbalance'];
			$amount = $walbal['walletbalance']+$trnsAmount;	
			$this->security->updateUserWalletBalance($userid,$amount);
			$created=time();
			$docid=rand();
			$trnsData=$this->updateTransaction($userid,$trnsAmount,$created,$docid,CR,RUMMY_MONEY,WLTBAL,$prebal,$amount);
			$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = 'Money transfered Rummy to Fancy.';	
			$resArr['data']  = ['txnid'=>$trnsData];
			}else{
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = 'Invalid user';
			}
		}catch(\Exception $e){
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
		}
		return $response->withJson($resArr);
	}



	public function updateTransaction($uid,$amount,$created,$txid,$ttype,$atype,$wlt,$prebal,$curbal){
    	$ref=$this->db->prepare("INSERT INTO transactions(userid,amount,txdate,txid,ttype,atype,wlt,prebal,curbal) VALUES (:userid,:amount,:txdate,:txid,:ttype,:atype,:wlt,:prebal,:curbal)");    			    		
            if($ref->execute(["userid"=>$uid,"amount"=>$amount,"txdate"=>$created,"txid"=>$txid,"ttype"=>$ttype,"atype"=>$atype,"wlt"=>$wlt,"prebal"=>$prebal,"curbal"=>$curbal]))
            	return $this->db->lastInsertId();
            else
            	return false;
        	
    }

}


?>
