<?php

namespace Apps\Controllers;

use \Firebase\JWT\JWT;

class AdminreportsController
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


	/*public function updateTransaction($uid,$amount,$created,$docid,$ttype,$atype,$wlt){

   	$params3 = ["userid"=>$uid,"amount"=>$amount,"created"=>$created,"docid"=>$docid,"ttype"=>$ttype,"atype"=>$atype,"wlt"=>$wlt];
  	$stmt3 = $this->db->prepare("INSERT INTO transactions SET userid=:userid,amount=:amount,txdate=:created,docid=:docid,ttype=:ttype,atype=:atype,wlt=:wlt");
  	$stmt3->execute($params3);

    }*/

	public function fetchWithdrawalsDtl($id)
	{
        $stmt = $this->db->prepare("SELECT userid,amount FROM withdrawals WHERE id=:id AND status=:status");        
		$stmt->execute(["id"=>$id,"status"=>INACTIVESTATUS]);
		$res =  $stmt->fetch();		 
		return $res;
	}

	//Get transaction by order id	
	public function getwithdrawalReq($request, $response){
            global $settings,$baseurl;            
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode'];
			      
            $input = $request->getParsedBody();               
            $params     = [];
            $searchSql  = ""; 
            $paging 	= ""; 
            if(isset($input['page']) && !empty($input['page']))
            {
              	$limit  = $input['limit']; //2
             	$page   = $input['page'];  //0   LIMIT $page,$offset
             	if(empty($limit)){
              	  $limit  = $settings['settings']['code']['defaultPaginLimit'];
             	}
             	$offset = ($page-1)*$limit;	
             	$paging = " limit ".$offset.",".$limit;
            }

         try{  
         	//FROM_UNIXTIME
            if(!empty($input['search_obj']) && isset($input['search_obj'])){
                $search_obj = $input['search_obj'];
                if($search_obj['tbl_status']=="2"){	
	             	$searchSql .= " AND w.status=:status ";
	             	$params = ["status"=>ACTIVESTATUS];
             	}else if($search_obj['tbl_status']=="1"){
             		$searchSql .= " AND w.status=:status ";
	             	$params = ["status"=>INACTIVESTATUS];
             	}

             	if($search_obj['startDate'] && $search_obj['endDate']){	
	             	$searchSql .= " AND w.created >=:startDate AND  w.created <=:endDate";
	             	$params = $params+["startDate"=>strtotime($search_obj['startDate']),"endDate"=>strtotime($search_obj['endDate'])];
             	}else if($search_obj['endDate']){
             		$searchSql .= " AND w.created<=:endDate ";
	             	$params = ["endDate"=>strtotime($search_obj['endDate'])];
             	}else if($search_obj['startDate']){
             		$searchSql .= " AND w.created>=:startDate ";
	             	$params = $params+["startDate"=>strtotime($search_obj['startDate'])];
             	}

            }

            if(!empty($input['search']) && isset($input['search'])){ 
                $search = $input['search']; 	
             	$searchSql .= " AND (u.email LIKE :search OR u.phone LIKE :search OR up.teamname LIKE :search ) ";
             	$params = $params+["search"=>"%$search%"];
            } 

            $sqlCount = "SELECT count(w.id) as total FROM withdrawals w INNER JOIN users u ON w.userid=u.id LEFT JOIN userprofile up ON w.userid=up.userid  LEFT JOIN userbankaccounts as ub ON ub.userid=w.userid WHERE 1=1 ".$searchSql;

            $stmtCount = $this->db->prepare($sqlCount);
			$stmtCount->execute($params);
			$resCount =  $stmtCount->fetch(); 		 
       		$sql = "select u.wltwin,w.id,w.userid,u.email,u.phone,w.amount,w.created,w.status,ub.ifsccode,ub.acholdername,ub.bankname,ub.acno FROM withdrawals w INNER JOIN users u ON w.userid=u.id LEFT JOIN userprofile up ON up.userid=w.userid LEFT JOIN userbankaccounts as ub ON ub.userid=w.userid WHERE 1=1 ".$searchSql." ORDER BY w.id DESC ".$paging;  
			    $stmt = $this->db->prepare($sql);
			    $stmt->execute($params);
			    $res =  $stmt->fetchAll();				
			   if(!empty($res)){

			   		foreach ($res as $key => $value) {
			   			$key = \Security::generateKey($value['userid']);
			   			$value['acno'] = \Security::my_decrypt($value['acno'], $key);
	   					$value['ifsccode'] = \Security::my_decrypt($value['ifsccode'], $key);
			   			$result[]=$value;
			   		}
                    $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg']   = 'withdrawals request';	
					$resArr['data']  = ["total"=>$resCount['total'],"list"=>$result];	
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

	public function withdrawStatus($request,$response)
	{
        global $settings;        
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];		                 
        $input = $request->getParsedBody(); 
	    $check =  $this->security->validateRequired(['id','status'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}

		$updtSql = "";
		$id = $input['id']; 
		$status = $input['status'];  
		$created = time();

		if(!in_array($status,['1']))
		{
			$resArr = $this->security->errorMessage("Invalid status");
			return $response->withJson($resArr,$errcode);	
		}

		$wdtl =$this->fetchWithdrawalsDtl($id);
		if(empty($wdtl)){
			$resArr = $this->security->errorMessage("No pending request");
			return $response->withJson($resArr,$errcode);	
		}

		$uid 		=  $wdtl['userid'];
		$amount 	=  $wdtl['amount'];
		$usrWltBal 	=  $this->security->getUserWalletBalance($uid); 
		$wltwin  	=  $usrWltBal['wltwin'];

        try{        
        	$this->db->beginTransaction();
	    	$updt = $this->db->prepare("UPDATE withdrawals SET status=:status WHERE id=:id");							    	
            if($updt->execute(["id"=>$id,"status"=>$status]))
            { 		
            
            $updtTx = $this->db->prepare("UPDATE transactions SET status=:txstatus WHERE docid=:id AND userid=:userid AND atype=:atype AND wlt=:wlt");							    	
            $updtTx->execute(["txstatus"=>TXSSUCCESS,"id"=>$id,"userid"=>$uid,"atype"=>WITHDRAW,"wlt"=>WLTWIN]);	            	            			    	
		    	/*$updtUsrWlt = $this->security->updateUserWallet($uid,"wltwin",$wltwin);*/
		    	/*$this->security->updateTransaction($uid,$amount,$created,$id,DR,WITHDRAW,WLTWIN);*/

		    	$this->db->commit();
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = 'Status Updated.';	
			}else{
                    $resArr = $this->security->errorMessage("Status not updated");						
			}
		}
	    catch(\PDOException $e)
	    {    	
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
			$this->db->rollBack();
	    }             
       return $response->withJson($resArr,$code);			  
    }

	public function dashStaticsCount($request,$response)
	{	      	          
		global $settings;
        $code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];     
	    $resArr		= []; 
	    $input 		= $request->getParsedBody();
	    $check 		=  $this->security->validateRequired([],$input);
	    if(isset($check['error'])) {
		   return $response->withJson($check);
		}		
		//$type		= 	$input['type'];      	    		
		try
		{
			$resData = [];	

			$social= "SELECT COUNT(id) as tuser ,
				(SELECT count(id) FROM users WHERE users.usertype=3 AND (users.devicetype='web' OR users.devicetype='')) as web,
				(SELECT count(id) FROM users WHERE users.devicetype!='' AND users.usertype=3 AND users.devicetype='android') as android,
				(SELECT count(id) FROM users WHERE users.devicetype!='' AND users.usertype=3 AND users.devicetype='iphone') as ios,
				(SELECT count(id) FROM users WHERE users.usertype=3 AND users.logintype='G') as google,
				(SELECT count(id) FROM users WHERE users.usertype=3 AND users.logintype='F') as fb,
				(SELECT count(id) FROM users WHERE users.usertype=1) as admin,
				(SELECT count(id) FROM users WHERE users.usertype=2) as subadmin
				FROM users"; 
			$stmt = $this->db->prepare($social);
			$stmt->execute(['usertype'=>USER]);
			$social = $stmt->fetch();

			$sql  = "SELECT count(id) as ttlusr FROM users WHERE usertype=:usertype";
            $stmt = $this->db->prepare($sql);
			$stmt->execute(['usertype'=>USER]);
			$user = $stmt->fetch();

			$sql  = "SELECT count(id) as ttlsbadmin FROM users WHERE usertype=:usertype";
            $stmt = $this->db->prepare($sql);
			$stmt->execute(['usertype'=>SUBADMIN]);
			$sbadmin = $stmt->fetch();

            	$approve	= " where (u.isbankdverify=:kycstatus AND u.ispanverify=:kycstatus AND u.isphoneverify=:kycstatus AND u.isemailverify=:kycstatus AND u.usertype=:usertype) ";
            	$paramsA	=	["kycstatus"=>ACTIVESTATUS,'usertype'=>USER];	

            	$notApprove	= " where (u.isbankdverify=:kycstatus OR u.ispanverify=:kycstatus OR u.isphoneverify=:kycstatus OR u.isemailverify=:kycstatus) AND usertype=:usertype ";
            	$paramsNa	=	["kycstatus"=>INACTIVESTATUS,'usertype'=>USER];	
			
			 $sql = "SELECT count(u.id) as approve FROM  users as u ".$approve." ORDER BY u.id DESC ";   

			    $stmt = $this->db->prepare($sql);
			    $stmt->execute($paramsA);
			    $resApprove =  $stmt->fetch();

			    $sql2 = "SELECT count(u.id) as not_approve FROM users u ".$notApprove." ORDER BY u.id DESC "  ;
			    $stmt = $this->db->prepare($sql2);
			    $stmt->execute($paramsNa);
			    $resNotApprove =  $stmt->fetch();

			     $sqlCount = "SELECT count(w.id) as total FROM withdrawals w INNER JOIN users u ON w.userid=u.id WHERE w.status=0";
	            $stmtCount = $this->db->prepare($sqlCount);
				$stmtCount->execute();
				$resWithdrawals =  $stmtCount->fetch();	

				 $sqlCount = "SELECT count(id) as total FROM notifications";
	            $stmtCount = $this->db->prepare($sqlCount);
				$stmtCount->execute();
				$resNotification =  $stmtCount->fetch();

			$sql  = "SELECT count(id) as ttlucmatch FROM matches WHERE mtype=:mtype AND status=:status";
            $stmt = $this->db->prepare($sql);
			$stmt->execute(["mtype"=>UPCOMING,"status"=>ACTIVESTATUS]);
			$ucmatch = $stmt->fetch();	
			$year=Date('Y');
			$user_type=USER;
			$barQuery="SELECT tots.*, @var := @var + tots.`monthlyUsers` as totalUsers FROM (
						SELECT 
						    YEAR(from_unixtime(u.created)) AS `year`,
						    MONTH(from_unixtime(u.created)) AS `month`,
						    MONTHNAME(from_unixtime(u.created)) AS `month_name`,
						    COUNT(*) AS `monthlyUsers`
						FROM  users u
						where u.usertype=$user_type AND YEAR(from_unixtime(u.created)) =$year 
						GROUP BY `year`, `month` ) AS tots, (SELECT @var from (select @var :=count(u.id ) from  users u where u.usertype=$user_type AND year(from_unixtime(u.created)) < $year ) as var) AS inc";
			$stmt = $this->db->prepare($barQuery);
			$stmt->execute();
			$barChatData = $stmt->fetchAll();	
			
      		if(!empty($user)) 
      		{	
            	$resArr['code']  = 0;
            	$resArr['error'] = false;
            	$resArr['msg']   = 'Results';  
            	$resArr['data']  = ["totalusr"=>$user['ttlusr'],"totalsbadmin"=>$sbadmin['ttlsbadmin'],"totalucmatch"=>$ucmatch['ttlucmatch'],'social'=>$social,
            	'approve'=>isset($resApprove['approve'])?$resApprove['approve']:0,
            	'notApproved'=>isset($resNotApprove['not_approve'])?$resNotApprove['not_approve']:0,
            	'withdrawals'=>($resWithdrawals['total'])?$resWithdrawals['total']:0,
            	'notification'=>($resNotification['total'])?$resNotification['total']:0,
            	'barChartData'=>($barChatData)?['labels'=>array_column($barChatData,'month_name'),'data'=>array_column($barChatData, 'monthlyUsers')]:[],
            	]; 
			}            	 
            else
            {
            	$resArr['code']  = 1;
            	$resArr['error'] = true;
            	$resArr['msg']   = 'Result not found.';              	
            }	
	    }
	    catch(PDOException $e)
	    {    
	        $resArr['code']  = 1;
	        $resArr['error'] = true;
	        $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
	        $code = $errcode;
	    }  
    	return $response->withJson($resArr,$code); 	
	}


	public function onePageReportFilter($request,$response)
	{	      	          
		global $settings;
        $code    	= $settings['settings']['code']['rescode'];
	 	$errcode 	= $settings['settings']['code']['errcode'];     
	    $resArr		= []; 
	    $input 		= $request->getParsedBody();
	    $check 		=  $this->security->validateRequired([],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}
		//$type		= 	$input['type'];      	    		
		try
		{
			$resData = [];
			$amounttype=$this->security->getAllDefineKeys('amounttype');
			$sql  = "SELECT id,gname FROM games";
            $stmt = $this->db->prepare($sql);
			$stmt->execute();
			$games = $stmt->fetchAll();
			$rtype = ['teamwise'=>'Team Wise','loginhis'=>'Login History','joining'=>'Joining','balancehis'=>'Balance History','transactions'=>'Transactions','profitloss'=>'Profit Loss','universalac'=>'Universal Account',"leaderboard"=>"Leaderboard Downloaded Users"];
      		if(!empty($rtype))
      		{
            	$resArr['code']  = 0;
            	$resArr['error'] = false;
            	$resArr['msg']   = 'Results';  
            	$resArr['data']  = ["games"=>$games,"rtype"=>$rtype,'amounttype'=>$amounttype]; 
			}            	 
            else
            {
            	$resArr['code']  = 1;
            	$resArr['error'] = true;
            	$resArr['msg']   = 'Result not found.';              	
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

	/*Match filter*/
	public function matchFilter($request,$response)
	{
		global $settings;
        $code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];           	          
	    $resArr		=  []; 
	    $input 		=  $request->getParsedBody();
	    $check 		=  $this->security->validateRequired([],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}	
		$filter = "";
		$params = [];
		if(isset($input['gameid']) && !empty($input['gameid']) ){
			$gameid = $input['gameid'];
			$filter = " WHERE gameid=:gameid";
			$params = $params + ["gameid"=>$gameid];
		}	   		
		try
		{
			$resData = [];	
			$sql  = "SELECT matchid,matchname,team1,team2,mdate FROM matches".$filter;
            $stmt = $this->db->prepare($sql);
			$stmt->execute($params);
			$res = $stmt->fetchAll();	
      		if(!empty($res))
      		{	
            	$resArr['code']  = 0;
            	$resArr['error'] = false;
            	$resArr['msg']   = 'Match Filter';  
            	$resArr['data']  = $res; 
			}            	 
            else
            {
            	$resArr['code']  = 1;
            	$resArr['error'] = true;
            	$resArr['msg']   = 'Result not found.';              	
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

	/* Contest FIlter */
	public function contestFilter($request,$response)
	{
		global $settings;
        $code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];           	          
	    $resArr		=  []; 
	    $input 		=  $request->getParsedBody();
	    $check 		=  $this->security->validateRequired(['matchid'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}	
		$filter = "";		
		$matchid = $input['matchid'];
		//$filter = " WHERE gameid=:gameid";
		//$params = $params + ["gameid"=>$gameid];			   		
		try
		{			
			$params=["matchid"=>$matchid];
			$sql  = "SELECT mcp.contestid,c.title FROM matchcontestpool mcp INNER JOIN contests c ON mcp.contestid=c.id WHERE mcp.matchid=:matchid GROUP BY mcp.contestid";
            $stmt = $this->db->prepare($sql);
			$stmt->execute($params);
			$res = $stmt->fetchAll();	
      		
      		if(!empty($res))
      		{	
            	$resArr['code']  = 0;
            	$resArr['error'] = false;
            	$resArr['msg']   = 'Contest Filter';  
            	$resArr['data']  = $res; 
			}            	 
            else
            {
            	$resArr = \Security::errorMessage("Result not found");
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

	public function contestPoolFilter($request,$response)
	{	
		global $settings;
        $code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];           	          
	    $resArr		=  []; 
	    $input 		=  $request->getParsedBody();
	    $check 		=  $this->security->validateRequired(['matchid','contestid'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}	
		$filter = "";		
		$matchid = $input['matchid'];
		$contestid = $input['contestid'];
		//$filter = " WHERE gameid=:gameid";
		//$params = $params + ["gameid"=>$gameid];			   		
		try
		{			
			$params =["matchid"=>$matchid,"contestid"=>$contestid];
			$sql    = "SELECT mcp.contestmetaid,cm.joinfee,cm.totalwining FROM matchcontestpool mcp INNER JOIN  contestsmeta cm ON mcp.contestmetaid=cm.id WHERE mcp.matchid=:matchid AND mcp.contestid=:contestid";
            $stmt = $this->db->prepare($sql);
			$stmt->execute($params);
			$res = $stmt->fetchAll();	
      		
      		if(!empty($res))
      		{	
            	$resArr['code']  = 0;
            	$resArr['error'] = false;
            	$resArr['msg']   = 'Contest Pool Filter';  
            	$resArr['data']  = $res; 
			}            	 
            else
            {
            	$resArr = \Security::errorMessage("Result not found");
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


	//public function 
	public function onePageReport($request,$response)
	{
		global $settings,$baseurl;
		$code    	= $settings['settings']['code']['rescode'];
		$errcode 	= $settings['settings']['code']['errcode'];         	          
	    $resArr		= []; 
	    $input 		= $request->getParsedBody();

	    $check 		=  $this->security->validateRequired(['type'],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}		
		$type		= 	$input['type'];      
		/*$userid  	= 	$input['userid'];
		$gameid  	= 	$input['gameid'];
		$matchid 	= 	$input['matchid'];
		$todate  	= 	$input['todate'];
		$fromdate  	= 	$input['fromdate'];
		$type		= 	$input['type'];      //teamwise,loginhis,joining,balancehis
		$contest	= 	$input['contest'];
		$pool		= 	$input['pool']; */
		try
		{			
			if($type == "teamwise")
			{
				$res=$this->reportTeamWise($input);		
			}
			elseif($type == "joining")
			{
				$res=$this->reportJoining($input);
			}
			elseif($type == "balancehis")
			{
				$res = $this->reportBalancehis($input);
			}
			elseif($type == "transactions")
			{
				$res = $this->reportTransactionHis($input);
			}
			elseif($type == "loginhis")
			{
				$res=$this->reportLoginhis($input);
			}
			elseif($type == "profitloss")
			{
				$res=$this->reportProfitLoss($input);
			}	
			elseif($type == "universalac")
			{
				$res=$this->reportUniversalAccount($input);
			}
			elseif($type == "leaderboard")
			{
				$res=$this->reportLeaderboard($input);
			}			
			else{						
				$res = [];
				//$this->report($input); 
			}

			if($res['total'] > 0){  	              
	            $resArr['code']  = 0;
	            $resArr['error'] = false;
	            $resArr['msg']   = 'Results';  
	            $resArr['data']  = $res;  
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

	public function reportLeaderboard($input){
		$collection = $this->mdb->leaderboard;
		$whereCond=[];
		$limit = (isset($input['limit']) && !empty($input['limit']))?(int)$input['limit']:10;
		$page  = (isset($input['page']) && !empty($input['page']))?(int)$input['page']:0;
        $page  = ($page>0)?($page-1)*$limit:0;  //0   LIMIT $page,$offset 
		
		if(!empty($input['matchid']))
			$whereCond['matchid']=$input['matchid'];
		if(!empty($input['pool']))
			$whereCond['poolid']=$input['pool'];
		if(!empty($input['contest']))
			$whereCond['contestid']=$input['contest'];
		if(!empty($input['userid']))
			$whereCond['userid']=$input['userid'];
		
		if(count($whereCond)>0)
			$cond[]=['$match'=>$whereCond];

		$cond[]=['$sort'=>['created'=>(-1)]];
		$cond[]=['$skip'=>$page];
		$cond[]=['$limit'=>$limit];
		
		$res=$collection->aggregate($cond)->toArray(); 
        /*$res = $collection->find($whereCond,['$skip'=>$page,'$limit'=>$limit,'$sort'=>$rstSort])->toArray();*/
        $dataRes['heading'] = ['teamname'=>'Teamname','matchname'=>'Matchname','dwncount'=>'Count','contestname'=>'Contest Name','poolid'=>"Pool Id"];
	    $dataRes['total'] 	= $collection->count($whereCond);
	    $dataRes['list'] 	= $res;
	    return $dataRes;

	}
   public function reportTeamWise($input)
   {		   		
   		$dataRes 	= 	[];
   		$userid  	= 	$input['userid'];
		$gameid  	= 	$input['gameid'];
		$matchid 	= 	$input['matchid'];
		$fromdate  	= 	$input['fromdate'];
		$todate  	= 	$input['todate'];
		$type		= 	$input['type'];       /* teamwise,loginhis,joining,balancehis */
		$contest	= 	$input['contest'];
		$pool		= 	$input['pool']; 

		//$searchSql  = ""; 
        $paging     = "";

        $filter = "";
		$params = [];
		if(isset($input['page']) && !empty($input['page']))
        {          
          	$limit  = $input['limit']; //2
          	$page   = $input['page'];  //0   LIMIT $page,$offset
          	if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          	}
          	$offset = ($page-1)*$limit; 
          	$paging = " limit ".$offset.",".$limit;
        }
		if(!empty($userid)){			
			$filter .= " AND u.id=:userid";
			$params = $params + ["userid"=>$userid];
		}
		if(!empty($gameid)){			
			$filter .= " AND m.gameid=:gameid";
			$params = $params + ["gameid"=>$gameid];
		}
		if(!empty($matchid)){			
			$filter .= " AND m.matchid=:matchid";
			$params = $params + ["matchid"=>$matchid];
		}
		if(!empty($input['fromdate'])){
            $fromdate = strtotime($input['fromdate']); 
            $filter  .= " AND m.mdate >= :fromdate ";
         	$params = $params + ["fromdate"=>$fromdate];
        }
        if(!empty($input['todate'])){
            $todate = strtotime($input['todate']); 
            $filter  .= " AND m.mdate <= :todate ";
         	$params = $params + ["todate"=>$todate];
        }			
         
		$sqlCount 	= "SELECT count(ut.id) as total FROM userteams ut LEFT JOIN matches m ON ut.matchid = m.matchid LEFT JOIN users u ON ut.userid=u.id  WHERE 1=1 ".$filter;
      	$stmtCount 	= $this->db->prepare($sqlCount);
      	$stmtCount->execute($params);
      	$resCount 	=  $stmtCount->fetch();

		$sql = "SELECT u.id as userid,u.email,u.phone,m.matchname,m.gametype,m.mdate,ut.teamname,ut.id as teamid FROM userteams ut LEFT JOIN matches m ON ut.matchid = m.matchid LEFT JOIN users u ON ut.userid=u.id  WHERE 1=1 ".$filter.$paging;
	    $stmt = $this->db->prepare($sql);
	    //$params  =  ["matchid"=>$matchid,"userid"=>$uid];
	    $stmt->execute($params);
	    $res =  $stmt->fetchAll();

	    $dataRes['heading'] = ['email'=>'Email','phone'=>'Phone','matchname'=>'Match Name','gametype'=>'Game Type','mdate'=>'Match Date','teamname'=>'Team Name'];
	    $dataRes['total'] 	= $resCount['total'];
	    $dataRes['list'] 	= $res;
	    return $dataRes;
    }


    public function reportJoining($input)
   {		   		   
   		$dataRes 	= 	[];
   		$userid  	= 	$input['userid'];
		$gameid  	= 	$input['gameid'];
		$matchid 	= 	$input['matchid'];
		$fromdate  	= 	$input['fromdate'];
		$todate  	= 	$input['todate'];
		$type		= 	$input['type'];       /* teamwise,loginhis,joining,balancehis */
		$contest	= 	$input['contest'];
		$pool		= 	$input['pool']; 

		//$searchSql  = ""; 
        $paging     = "";

        $filter = "";
		$params = [];
		if(isset($input['page']) && !empty($input['page']))
        {          
          	$limit  = $input['limit']; //2
          	$page   = $input['page'];  //0   LIMIT $page,$offset
          	if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          	}
          	$offset = ($page-1)*$limit; 
          	$paging = " limit ".$offset.",".$limit;
        }
		if(!empty($userid)){			
			$filter .= " AND u.id=:userid";
			$params = $params + ["userid"=>$userid];
		}
		if(!empty($gameid)){			
			$filter .= " AND m.gameid=:gameid";
			$params = $params + ["gameid"=>$gameid];
		}
		if(!empty($matchid)){			
			$filter .= " AND m.matchid=:matchid";
			$params = $params + ["matchid"=>$matchid];
		}
		if(!empty($fromdate)){
            $fromdate = strtotime($input['fromdate']); 
            $filter  .= " AND m.mdate >= :fromdate ";
         	$params = $params + ["fromdate"=>$fromdate];
        }
        if(!empty($todate)){
            $todate = strtotime($input['todate']); 
            $filter  .= " AND m.mdate <= :todate ";
         	$params = $params + ["todate"=>$todate];
        }			
        if(!empty($contest)){
            $filter  .= " AND c.id=:contest";
         	$params = $params + ["contest"=>$contest];
        }			
        if(!empty($pool)){
            $filter  .= " AND jc.poolcontestid=:pool ";
         	$params = $params + ["pool"=>$pool];
        }			
         
		$sqlCount 	= "SELECT count(jc.id) as total FROM joincontests jc LEFT JOIN matches m ON jc.matchid = m.matchid LEFT JOIN users u ON jc.userid=u.id LEFT JOIN contestsmeta cm ON jc.poolcontestid=cm.id LEFT JOIN contests c ON cm.contestid=c.id  WHERE 1=1 ".$filter;
      	$stmtCount 	= $this->db->prepare($sqlCount);
      	$stmtCount->execute($params);
      	$resCount 	=  $stmtCount->fetch();

		$sql = "SELECT u.id as userid,u.email,u.phone,m.matchname,m.gametype,m.mdate,c.title as contest,cm.joinfee,jc.ptotal,jc.winbal as win,ut.teamname,jc.uteamid as teamid FROM joincontests jc LEFT JOIN matches m ON jc.matchid = m.matchid LEFT JOIN users u ON jc.userid=u.id LEFT JOIN contestsmeta cm ON jc.poolcontestid=cm.id LEFT JOIN contests c ON cm.contestid=c.id LEFT JOIN userteams ut ON jc.uteamid=ut.id WHERE 1=1 ".$filter.$paging;
	    $stmt = $this->db->prepare($sql);	    
	    $stmt->execute($params);
	    $res =  $stmt->fetchAll();

	    $dataRes['heading'] = ['email'=>'Email','phone'=>'Phone','matchname'=>'Match Name','gametype'=>'Game Type','mdate'=>'Match Date','contest'=>'Contest','joinfee'=>'Join Fees','teamname'=>'Team Name','ptotal'=>'Total Point','win'=>'Winning Amount'];
	    $dataRes['total'] 	= $resCount['total'];
	    $dataRes['list'] 	= $res;
	    return $dataRes;
    }

    /* Balance History */
    public function reportTransactionHis($input)
   {		   		
   		$dataRes 	= 	[];
   		$userid  	= 	$input['userid'];
		$gameid  	= 	$input['gameid'];
		$matchid 	= 	$input['matchid'];
		$fromdate  	= 	$input['fromdate'];
		$todate  	= 	$input['todate'];
		$type		= 	$input['type'];       /* teamwise,loginhis,joining,balancehis */
		$contest	= 	$input['contest'];
		$pool		= 	$input['pool'];
		$amounttype	= 	(isset($input['amounttype']) && !empty($input['amounttype']))?$input['amounttype']:'';  
				
		$resDt 		= [];				
        $paging     = "";
        $filter 	= "";
		$params 	= [];
		if(isset($input['page']) && !empty($input['page']))
        {          
          	$limit  = $input['limit']; //2
          	$page   = $input['page'];  //0   LIMIT $page,$offset
          	if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          	}
          	$offset = ($page-1)*$limit; 
          	$paging = " limit ".$offset.",".$limit;
        }
		if(!empty($userid)){			
			$filter .= " AND u.id=:userid";
			$params = $params + ["userid"=>$userid];
		}
		/*if(!empty($gameid)){			
			$filter .= " AND m.gameid=:gameid";
			$params = $params + ["gameid"=>$gameid];
		}
		if(!empty($matchid)){			
			$filter .= " AND m.matchid=:matchid";
			$params = $params + ["matchid"=>$matchid];
		}*/

		if(!empty($amounttype)){			
			$filter .= " AND t.atype=:amounttype";
			$params = $params + ["amounttype"=>$amounttype];
		}

		if(!empty($fromdate)){
            $fromdate = strtotime($input['fromdate']); 
            $filter  .= " AND t.txdate >= :fromdate ";
         	$params = $params + ["fromdate"=>$fromdate];
        }
        if(!empty($todate)){
            $todate = strtotime($input['todate']); 
            $filter  .= " AND t.txdate <= :todate ";
         	$params = $params + ["todate"=>$todate];
        }	

       /* if(!empty($contest)){
            $filter  .= " AND c.id=:contest";
         	$params = $params + ["contest"=>$contest];
        }			
        if(!empty($pool)){
            $filter  .= " AND jc.poolcontestid=:pool ";
         	$params = $params + ["pool"=>$pool];
        }*/			
         
		$sqlCount = "SELECT count(t.id) as total FROM transactions t INNER JOIN users u ON t.userid=u.id WHERE 1=1 ".$filter;
        $stmtCount 	= $this->db->prepare($sqlCount);
      	$stmtCount->execute($params);
      	$resCount 	=  $stmtCount->fetch();		 

   		$sql = "SELECT u.email,u.phone,t.txid,t.amount,t.status,t.ttype,t.atype,t.wlt,t.txdate  FROM transactions t INNER JOIN users u ON t.userid=u.id WHERE 1=1 ".$filter." ORDER BY t.id DESC ".$paging;  
		$stmt = $this->db->prepare($sql);	    
	    $stmt->execute($params);
	    $res = $stmt->fetchAll();
	    $des  	 = [
							"addbal"=>"Add balance",
							"win"=>"Win amount",
							"cjoin"=>"Contest join",
							"opbal"=>"Open balance",
							"clbal"=>"Close balance",
							"wlcbns"=>"Welcome balance",
							"refbns"=>"Referral amount",
							"ntflpool"=>"Cancel pool refund",
							"withdr"=>"Withdrawal",
							"wltwin"=>"Winning Wallet",
							"wltbal"=>"Balance Wallet",
							"wltbns"=>"Bonus Wallet",			
							"TXN_PENDING"=>"Pending",			
							"TXN_SUCCESS"=>"Success"			
						];	
		foreach ($res as $row ) {
			    	$des1="";
					$des2="";
					$desmain="";
					 if($row["status"] == "TXN_PENDING" || $row["status"] == "TXN_SUCCESS")
					 {
					 	$des1 = $des[$row["atype"]];
					 	$des2 = $des[$row["status"]];
					 }
					 else
					 {
					 	$des1 = $des[$row["atype"]];
					 	$des2 = $des[$row["wlt"]];					 
					 }

					$desmain= $des1." | ".$des2;
					$row['description'] = $desmain;
			    	$resDt[] = $row; 
			    }						

	    $dataRes['heading'] = ['email'=>'Email','phone'=>'Phone','txid'=>'Transactions Id','amount'=>'Amount','status'=>'Status','ttype'=>'Transactions Type','description'=>'Description','txdate'=>'Transactions Date'];
	    $dataRes['total'] 	= $resCount['total'];	     
	    $dataRes['list'] 	= $resDt;
	    return $dataRes;
    }

     /* report Login history */
    public function reportLoginhis($input)
   {		   		
   		$dataRes 	= 	[];
   		$userid  	= 	$input['userid'];
		$gameid  	= 	$input['gameid'];
		$matchid 	= 	$input['matchid'];
		$fromdate  	= 	$input['fromdate'];
		$todate  	= 	$input['todate'];
		$type		= 	$input['type'];      
		$contest	= 	$input['contest'];
		$pool		= 	$input['pool']; 
				
        $paging     = "";
        $filter 	= "";
		$params 	= ['usertype'=>USER];
		if(isset($input['page']) && !empty($input['page']))
        {          
          	$limit  = $input['limit']; //2
          	$page   = $input['page'];  //0   LIMIT $page,$offset
          	if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          	}
          	$offset = ($page-1)*$limit; 
          	$paging = " limit ".$offset.",".$limit;
        }
		if(!empty($userid)){			
			$filter .= " AND id=:userid";
			$params = $params + ["userid"=>$userid];
		}
		/*if(!empty($gameid)){			
			$filter .= " AND m.gameid=:gameid";
			$params = $params + ["gameid"=>$gameid];
		}
		if(!empty($matchid)){			
			$filter .= " AND m.matchid=:matchid";
			$params = $params + ["matchid"=>$matchid];
		}*/

		if(!empty($fromdate)){
            $fromdate = strtotime($input['fromdate']); 
            $filter  .= " AND logindate >= :fromdate ";
         	$params = $params + ["fromdate"=>$fromdate];
        }
        if(!empty($todate)){
            $todate = strtotime($input['todate']); 
            $filter  .= " AND logindate <= :todate ";
         	$params = $params + ["todate"=>$todate];
        }	

       /* if(!empty($contest)){
            $filter  .= " AND c.id=:contest";
         	$params = $params + ["contest"=>$contest];
        }			
        if(!empty($pool)){
            $filter  .= " AND jc.poolcontestid=:pool ";
         	$params = $params + ["pool"=>$pool];
        }*/			
         
		$sqlCount = "SELECT count(id) as total FROM users WHERE usertype=:usertype ".$filter;
        $stmtCount 	= $this->db->prepare($sqlCount);
      	$stmtCount->execute($params);
      	$resCount 	=  $stmtCount->fetch();		 

   		$sql = "SELECT email,phone,logindate  FROM users  WHERE usertype=:usertype ".$filter." ORDER BY logindate DESC ".$paging;  
		$stmt = $this->db->prepare($sql);	    
	    $stmt->execute($params);
	    $res = $stmt->fetchAll();

	    $dataRes['heading'] 	= ['email'=>'Email','phone'=>'Phone','logindate'=>'Login Date'];
	    $dataRes['total'] 		= $resCount['total'];	     
	    $dataRes['list'] 		= $res;
	    return $dataRes;
    }


    /* Balance History */
    public function reportBalancehis($input)
   {		   		
   		$dataRes 	= 	[];
   		$userid  	= 	$input['userid'];
		$gameid  	= 	$input['gameid'];
		$matchid 	= 	$input['matchid'];
		$fromdate  	= 	$input['fromdate'];
		$todate  	= 	$input['todate'];
		$type		= 	$input['type'];      
		$contest	= 	$input['contest'];
		$pool		= 	$input['pool']; 
				
        $paging     = "";
        $filter 	= "";
		$params 	= ['usertype'=>USER];
		if(isset($input['page']) && !empty($input['page']))
        {          
          	$limit  = $input['limit']; //2
          	$page   = $input['page'];  //0   LIMIT $page,$offset
          	if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          	}
          	$offset = ($page-1)*$limit; 
          	$paging = " limit ".$offset.",".$limit;
        }
		if(!empty($userid)){			
			$filter .= " AND id=:userid";
			$params = $params + ["userid"=>$userid];
		}
		/*if(!empty($gameid)){			
			$filter .= " AND m.gameid=:gameid";
			$params = $params + ["gameid"=>$gameid];
		}
		if(!empty($matchid)){			
			$filter .= " AND m.matchid=:matchid";
			$params = $params + ["matchid"=>$matchid];
		}*/

		if(!empty($fromdate)){
            $fromdate = strtotime($input['fromdate']); 
            $filter  .= " AND created >= :fromdate ";
         	$params = $params + ["fromdate"=>$fromdate];
        }
        if(!empty($todate)){
            $todate = strtotime($input['todate']); 
            $filter  .= " AND created <= :todate ";
         	$params = $params + ["todate"=>$todate];
        }	

       /* if(!empty($contest)){
            $filter  .= " AND c.id=:contest";
         	$params = $params + ["contest"=>$contest];
        }			
        if(!empty($pool)){
            $filter  .= " AND jc.poolcontestid=:pool ";
         	$params = $params + ["pool"=>$pool];
        }*/			
         
		$sqlCount = "SELECT count(id) as total FROM users WHERE usertype=:usertype ".$filter;
        $stmtCount 	= $this->db->prepare($sqlCount);
      	$stmtCount->execute($params);
      	$resCount 	=  $stmtCount->fetch();		 

   		$sql = "SELECT email,phone,walletbalance,wltwin,wltbns,(walletbalance+wltwin+wltbns) as totalbal,created  FROM users  WHERE usertype=:usertype ".$filter." ORDER BY id DESC ".$paging;  
		$stmt = $this->db->prepare($sql);	    
	    $stmt->execute($params);
	    $res = $stmt->fetchAll();

	    $dataRes['heading'] = ['email'=>'Email','phone'=>'Phone','walletbalance'=>'Wallet Balance','wltwin'=>'Winning Amount','wltbns'=>'Wallet Bonus','totalbal'=>'Total Balance'];
	    $dataRes['total'] 	= $resCount['total'];	     
	    $dataRes['list'] 	= $res;
	    return $dataRes;
    }


    public function reportProfitLoss($input)
    {		   		   
   		$dataRes 	= 	[];
   		$userid  	= 	$input['userid'];
		$gameid  	= 	$input['gameid'];
		$matchid 	= 	$input['matchid'];
		$fromdate  	= 	$input['fromdate'];
		$todate  	= 	$input['todate'];
		$type		= 	$input['type']; 
		
		$contest	= 	$input['contest'];		
		$pool		= 	$input['pool']; 
		
        $paging     =   "";

        $filter 	= 	"";
		$params 	= 	[];
		
		if(isset($input['page']) && !empty($input['page']))
        {          
          	$limit  = $input['limit']; //2
          	$page   = $input['page'];  //0   LIMIT $page,$offset
          	if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          	}
          	$offset = ($page-1)*$limit; 
          	$paging = " limit ".$offset.",".$limit;
        }
        		
		if(!empty($userid)){			
			$filter .= " AND jc.userid=:userid";
			$params = $params + ["userid"=>$userid];
		}		
		if(!empty($gameid)){			
			$filter .= " AND m.gameid=:gameid";
			$params = $params + ["gameid"=>$gameid];
		}
		if(!empty($matchid)){			
			$filter .= " AND jc.matchid=:matchid";
			$params = $params + ["matchid"=>$matchid];
		}
		if(!empty($fromdate)){
            $fromdate = strtotime($input['fromdate']); 
            $filter  .= " AND m.mdate >= :fromdate ";
         	$params = $params + ["fromdate"=>$fromdate];
        }
        if(!empty($todate)){
            $todate = strtotime($input['todate']); 
            $filter  .= " AND m.mdate <= :todate ";
         	$params = $params + ["todate"=>$todate];
        }			
        if(!empty($contest)){
            $filter  .= " AND c.id=:contest";
         	$params = $params + ["contest"=>$contest];
        }
        			
        if(!empty($pool)){
            $filter  .= " AND jc.poolcontestid=:pool ";
         	$params = $params + ["pool"=>$pool];
        }

        if(empty($matchid)){			
			$paging = "";			
		}		

		$sqlCount   = "SELECT count(jc.id) as total FROM joincontests jc LEFT JOIN matches m ON jc.matchid = m.matchid LEFT JOIN contestsmeta cm ON jc.poolcontestid=cm.id LEFT JOIN contests c ON cm.contestid=c.id  WHERE 1=1 ".$filter." GROUP BY jc.poolcontestid";
      	$stmtCount 	= $this->db->prepare($sqlCount);
      	$stmtCount->execute($params);
      	$resCount 	=  $stmtCount->fetchAll();      	
      	$t = $stmtCount->rowCount(); 

	 	$sql = "SELECT m.matchname,m.mdategmt,m.gametype,c.title as contest,
	 	sum(cm.joinfee) as joinfee,mcp.iscancel,sum(jc.winbal) as winning
    	FROM joincontests jc 
    	LEFT JOIN matches m ON jc.matchid = m.matchid 
    	LEFT JOIN contestsmeta cm ON jc.poolcontestid=cm.id 
    	LEFT JOIN contests c ON cm.contestid=c.id  
    	INNER JOIN matchcontestpool mcp ON jc.poolcontestid=mcp.contestmetaid AND mcp.matchid=jc.matchid WHERE 1=1 ".$filter." GROUP BY jc.poolcontestid".$paging;

	    $stmt = $this->db->prepare($sql);
	    $stmt->execute($params);
	    $resData =  $stmt->fetchAll();

	    $totaljoin 	= 	0;
	    $tcancel 	= 	0;
	    $twinning 	= 	0;
	    $tprofit 	= 	0;
	    $tloss 		= 	0;

	    $res = [];
	    foreach($resData as $row){
			$profit = 0;				
			$loss 	= 0;	
			$cancel	= 0;	
			if($row['iscancel'] == 1){				
				$cancel = $row['joinfee'];
			}

			$bal = ($row['joinfee']-$cancel) - $row['winning'];
			if($bal > 0){
				$profit = $bal;	
			}else{
				$loss = $bal;
			}
			$row['cancel'] 	= (string) sprintf("%.2f",$cancel);
			$row['profit'] 	= (string) sprintf("%.2f",$profit);
			$row['loss'] 	= (string) sprintf("%.2f",$loss) ;

			$totaljoin 	= $totaljoin + $row['joinfee'];
			$tcancel 	= $tcancel + $row['cancel'];
			$twinning 	= $twinning + $row['winning'];
			$tprofit 	= $tprofit + $row['profit'];
			$tloss 		= $tloss + $row['loss'];
	    		    	
			$res[] 		= $row;
	    }
	   
		$tArr = ["totaljoin"=>$totaljoin,"totalcancel"=>$tcancel,"totalwinning"=>$twinning,"totalprofit"=>$tprofit,"totalloss"=>$tloss];
		$heading = ['matchname'=>'Match Name','gametype'=>'Game Type','contest'=>'Contest','joinfee'=>'Join Fees','cancel'=>'Cancel','winning'=>'Winning','profit'=>'Profit','loss'=>'Loss'];

		if(empty($matchid)){			
			$heading = ['totaljoin'=>'Total Join','totalcancel'=>'Total Cancel','totalwinning'=>'Total Winning','totalprofit'=>'Total Profit','totalloss'=>'Total Loss'];
			$res = [0=>$tArr]; 
			$t   =  1;		
		}
	    	$dataRes['heading'] 	= $heading;
	    	$dataRes['totalcal'] 	= $tArr;
	    	$dataRes['total'] 		= $t;
	    	$dataRes['list'] 		= $res;		    
	    return $dataRes;
    }


    /*--Universal Account--*/

    public function reportUniversalAccount($input)
    {	

   		$dataRes 	= 	[];
   		$userid  	= 	$input['userid'];
		$gameid  	= 	$input['gameid'];
		$matchid 	= 	$input['matchid'];
		$fromdate  	= 	$input['fromdate'];
		$todate  	= 	$input['todate'];
		$type		= 	$input['type']; 		
		$contest	= 	$input['contest'];		
		$pool		= 	$input['pool']; 
		
        $paging     =   "";

        $filter 	= 	"";
		$params 	= 	[];
		
		/*if(isset($input['page']) && !empty($input['page']))
        {          
          	$limit  = $input['limit']; 
          	$page   = $input['page'];  //0   LIMIT $page,$offset
          	if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          	}
          	$offset = ($page-1)*$limit; 
          	$paging = " limit ".$offset.",".$limit;
        }*/

		/*if(!empty($userid)){			
			$filter .= " AND jc.userid=:userid";
			$params = $params + ["userid"=>$userid];
		}		
		if(!empty($gameid)){			
			$filter .= " AND m.gameid=:gameid";
			$params = $params + ["gameid"=>$gameid];
		}
		if(!empty($matchid)){			
			$filter .= " AND jc.matchid=:matchid";
			$params = $params + ["matchid"=>$matchid];
		}
		if(!empty($fromdate)){
            $fromdate = strtotime($input['fromdate']); 
            $filter  .= " AND m.mdate >= :fromdate ";
         	$params = $params + ["fromdate"=>$fromdate];
        }
        if(!empty($todate)){
            $todate = strtotime($input['todate']); 
            $filter  .= " AND m.mdate <= :todate ";
         	$params = $params + ["todate"=>$todate];
        }			
        if(!empty($contest)){
            $filter  .= " AND c.id=:contest";
         	$params = $params + ["contest"=>$contest];
        }        			
        if(!empty($pool)){
            $filter  .= " AND jc.poolcontestid=:pool ";
         	$params = $params + ["pool"=>$pool];
        }
        if(empty($matchid)){			
			$paging = "";			
		}	 */	

		$sql   = "SELECT SUM(walletbalance) as wbal,SUM(wltwin) as wwin,SUM(wltbns) as wbns FROM users WHERE usertype=:usertype AND status=1";
      	$stmt 	= $this->db->prepare($sql);
      	$stmt->execute(['usertype'=>USER]);
      	$users 	=  $stmt->fetch();      	 
  
       	$totalBal = $users['wbal']+$users['wwin']+$users['wbns'];

    	$sqlMatch  = "SELECT matchid FROM matches WHERE mstatus=:mstatusuc OR mstatus=:mstatuslive OR mstatus=:mstatuscm";
      	$stmt 	   = $this->db->prepare($sqlMatch);
      	$stmt->execute(['mstatusuc'=>UPCOMING,'mstatuslive'=>LIVE,'mstatuscm'=>COMPLETED]);
      	$matches   =  $stmt->fetchAll();

      	$joiningAmt = 0;
      	$winningAmt = 0; 

 
      	foreach($matches as $match) 
      	{
      		$matchid = $match['matchid'];      		
    		$sql     = " SELECT IFNULL(SUM(fees),0) as fees FROM joincontests WHERE matchid=:matchid ";      		
      		$stmt 	 = $this->db->prepare($sql);
      		$stmt->execute(['matchid'=>$matchid]);
      		$res 	 =  $stmt->fetch();  
      		$joiningAmt =   $joiningAmt + $res['fees'];   

      		/*$sql     = " SELECT contestmetaid FROM matchcontestpool WHERE matchid=:matchid AND iscancel=0 ";      		
      		$stmt 	 = $this->db->prepare($sql);
      		$stmt->execute(['matchid'=>$matchid]);
      	 	$res 	 =  $stmt->fetchAll();  
      		foreach ($res as $row) 
      		{	      			
      			$sql     = "SELECT pamount FROM poolprizebreaks WHERE poolcontestid=:poolcontestid";
      			$stmt 	 = $this->db->prepare($sql);
      			$stmt->execute(['poolcontestid'=>$row['contestmetaid']]);      			
      			$res = $stmt->fetch();
      			$winningAmt = $winningAmt + $res['pamount']; 
      		}*/
      	}


      	$sqlMatch  = "SELECT matchid FROM matches WHERE mstatus=:mstatusdc";
      	$stmt 	   = $this->db->prepare($sqlMatch);
      	$stmt->execute(['mstatusdc'=>DECLARED]);
      	$matches   =  $stmt->fetchAll();

      	$joiningAmtCm = 0;
      	$winningAmtCm = 0; 

 
      	foreach($matches as $match) 
      	{
      		$matchid = $match['matchid'];      		
    		$sql     = " SELECT IFNULL(SUM(fees),0) as fees,IFNULL(SUM(winbal),0) as winbal  FROM joincontests WHERE matchid=:matchid ";      		
      		$stmt 	 = $this->db->prepare($sql);
      		$stmt->execute(['matchid'=>$matchid]);
      		$res 	 =  $stmt->fetch();  
      		$joiningAmtCm =   $joiningAmtCm + $res['fees'];   
      		$winningAmtCm =   $winningAmtCm + $res['winbal'];   

      		/*$sql     = " SELECT contestmetaid FROM matchcontestpool WHERE matchid=:matchid AND iscancel=0 ";      		
      		$stmt 	 = $this->db->prepare($sql);
      		$stmt->execute(['matchid'=>$matchid]);
      	 	$res 	 =  $stmt->fetchAll();  
      		foreach ($res as $row) 
      		{	      			
      			$sql     = "SELECT pamount FROM poolprizebreaks WHERE poolcontestid=:poolcontestid";
      			$stmt 	 = $this->db->prepare($sql);
      			$stmt->execute(['poolcontestid'=>$row['contestmetaid']]);      			
      			$res = $stmt->fetch();
      			$winningAmt = $winningAmt + $res['pamount']; 
      		}*/
      	}

	      	$lossprofit 	= $joiningAmtCm-$winningAmtCm;
	      	$unutilizedbal 	= $totalBal-$joiningAmt;
			
			$resList = ["tatalbalance"=>sprintf('%.2f',$totalBal),"usedbalance"=>sprintf('%.2f',$joiningAmt),"unutilizedbal"=>sprintf('%.2f',$unutilizedbal),"totaljoining"=>sprintf('%.2f',$joiningAmtCm),"totalwinning"=>sprintf('%.2f',$winningAmtCm),"loss-profit"=>sprintf('%.2f',$lossprofit)];
					
			$heading = ['tatalbalance'=>'Total Balance','usedbalance'=>'Used Balance','unutilizedbal'=>'Unutilized Balance','totaljoining'=>'Total Joining','totalwinning'=>'Total Winning','loss-profit'=>'Loss & Profit'];
			
	    	$dataRes['heading'] = $heading;
	    	$dataRes['total'] 	= 1;	     
	    	$dataRes['list'] 	=   array($resList); 
		    return $dataRes;
    }

	
}
?>
