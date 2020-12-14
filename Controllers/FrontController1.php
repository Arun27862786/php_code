<?php
namespace Apps\Controllers;

use \Firebase\JWT\JWT;
use MongoDB\BSON\ObjectId;
use Swift_TransportException;

class FrontController
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

	
	public function sendJoinContestMail($poolData){
		try{
			$this->mailer->sendMessage('/mail/joincontest.php', ['data' => $poolData], function($message) use($poolData) {
		    		$name    = " ";
		    		$webname = $poolData['webname'];
		    		if(!empty($poolData['userdata']['name'])){	    			
		    			$name = $poolData['userdata']['name'];
		    		}	    		
		        	$message->setTo($poolData['userdata']['email'],$name);
		        	$message->setSubject('Regarding Joining a Pool');
		    	});			
			}catch(Swift_TransportException $se){
	   			$string = date("Y-m-d H:i:s")  . ' - ' . $se->getMessage() . PHP_EOL;
				file_put_contents("./../logs/errorlog_mail.txt", $string, FILE_APPEND);
	   		}
		return true;
	}
	    
/*
	public function sendMail($user){
	    $this->mailer->sendMessage('/mail/'.$user['template'], ['data' => $user], function($message) use($user) {
          $name    = " ";
          $webname = $user['webname'];
          if(!empty($user['name'])){            
            $name = $user['name'];
          }         
            $message->setTo($user['email'],$name);
            $message->setSubject($user['subject']);
        }); 
     return true;
  	} */

  	public function sendMail($user){
    	global $settings;
      	$dir    = __DIR__ . '/../settings/playwebsetting';
	   $addset = @file_get_contents($dir);
	    if ($addset != false) {
		    $res = json_decode($addset, true);
		    if(isset($res['mailconfig']) && !empty($res['mailconfig'])){
		      	$user['webname']=$settings['settings']['webname'];
		    	$user['mailconfig']=$res['mailconfig'];
		    	try{
				    $this->mailer->sendMessage('/mail/'.$user['template'], ['data' => $user], function($message) use($user) {
			          $name    = " ";
			          $webname = $user['webname'];
			          if(!empty($user['name'])){
			            $name = $user['name'];
			          }
			            $message->setTo($user['email'],$name); //
			            $message->setSubject($user['subject']);
			           
			        }); 
		        }catch(Swift_TransportException $se){
		   			$string = date("Y-m-d H:i:s")  . ' - ' . $se->getMessage() . PHP_EOL;
					file_put_contents("./../logs/errorlog_mail.txt", $string, FILE_APPEND);
		   		}
			}
		}
     return true;
  	}


	public function funcGetbankdetails($request, $response)
	{	
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];     
    try{        

    	$key = \Security::generateKey($uid);
        $getbankac = $this->security->getbankac($uid);

        if(!empty($getbankac))
        {                  
           	$ifsc = \Security::my_decrypt($getbankac['ifsccode'],$key);
            $acno = \Security::my_decrypt($getbankac['acno'],$key); 
            //$getbankac['ifsccode']	=	'xxxx'.substr($ifsc,-4);
            $getbankac['ifsccode']	=	$ifsc;
            $getbankac['acno']		=	'xxxxxxxxxx'.substr($acno,-4);
            unset($getbankac['userid']);

    		$resArr['code']  = 0;
			$resArr['error'] = false;	
			$resArr['msg']   = "Bank details.";
			$resArr['data']  = $this->security->removenull($getbankac);

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

	//Get transaction by order id	
	public function gettransactions($request, $response){
            global $settings,$baseurl;            
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode'];
			$limit  = $settings['settings']['code']['defaultPaginLimit'];
			$loginuser 	= 	$request->getAttribute('decoded_token_data');
    		$uid 		= 	$loginuser['id'];  	         
            $input = $request->getParsedBody();               
            $params     = ["userid"=>$uid];
            $searchSql  = ""; 
            $paging 	= ""; 
            if(isset($input['page']) && !empty($input['page']))
            {
              	$limit  = (isset($input['limit']))?$input['limit']:$limit; //2
             	$page   = $input['page'];  //0   LIMIT $page,$offset
             	if(empty($limit)){
              	  
             	}
             	$offset = ($page-1)*$limit;	
             	$paging = " limit ".$offset.",".$limit;
            }                        
         try{  
         	//FROM_UNIXTIME
            if(!empty($input['search']) && isset($input['search'])){
                $search = $input['search']; 	
             	$searchSql = " AND t.ttype LIKE :search OR t.txid LIKE :search  ";
             	$params = ["userid"=>$uid,"search"=>"%$search%"];
            }                
            $sqlCount = "SELECT count(t.id) as total FROM transactions t WHERE t.userid=:userid ".$searchSql." ORDER BY t.id DESC";
            $stmtCount = $this->db->prepare($sqlCount);
			$stmtCount->execute($params);
			$resCount =  $stmtCount->fetch();		 
       		$sql = "select t.id,t.txid,t.amount,t.status,t.ttype,t.atype,t.wlt,t.txdate FROM transactions t WHERE t.amount!=0 AND t.userid=:userid ".$searchSql." ORDER BY t.id DESC ".$paging;
			    $stmt = $this->db->prepare($sql);
			    $stmt->execute($params);
			    $res =  $stmt->fetchAll();
				$resData = [];
				$des  	 = [
							"addbal"=>"Add balance",
							"win"=>"Win amount",
							"cjoin"=>"Contest join",
							"opbal"=>"Open balance",
							"clbal"=>"Close balance",
							"wlcbns"=>"Welcome balance",
							"refbns"=>"Referral amount",
							"promobns"=>"Promo bonus amount",
							"ntflpool"=>"Cancel pool refund",
							"withdr"=>"Withdrawal",
							"wltwin"=>"Winning Wallet",
							"wltbal"=>"Balance Wallet",
							"wltbns"=>"Bonus Wallet",			
							"TXN_PENDING"=>"Pending",			
							"TXN_SUCCESS"=>"Success",			
							"TXN_FAILURE"=>"Failed"			
						];	

			    foreach ($res as $row ) {
			    	$des1="";
					$des2="";
					$desmain="";
					 if( $row["status"] == "TXN_FAILURE" || $row["status"] == "TXN_PENDING" || $row["status"] == "TXN_SUCCESS")
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
					$row['des'] = $desmain;
			    	$resData[] = $this->security->removenull($row); 

			    }
			   if(!empty($res)){  
                    $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg']   = 'List bank details.';	
					$resArr['data']  = ["total"=>$resCount['total'],"list"=>$resData];	
					$resArr['description']  = [
												ADDBAL=>"Add balance",
												WIN=>"Win amount",
												CJOIN=>"Contest join",
												OPBAL=>"Open balance",
												CLBAL=>"Close balance",
												WELCOMEBNS=>"Welcome balance",
												REFBNS=>"Reference amount",
												PROMOBNS=>"Promo bonus amount",
												NTFLPOOL=>"Cancel pool refund",
												WITHDRAW=>"Withdrawal",
												WLTWIN=>"Winning Wallet",
												WLTBAL=>"Balance Wallet",
												WLTBNS=>"Bonus Wallet",			
												TXSPENDING=>"Pending",			
												TXSSUCCESS=>"Success"			
												];	
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

	
	/*public function updatePlayerPoints($request, $response)
	{

    	global $settings;
        $code    = $settings['settings']['code']['rescode'];
	 	$errcode = $settings['settings']['code']['errcode'];         
	    $input = $request->getParsedBody();

		$check =  $this->security->validateRequired(['matchid','pid','points'],$input);
		if(isset($check['error'])) {
		    return $response->withJson($check);
		} 
	    
	    $matchid  	= 	$input['matchid'];
	    $pid  		= 	$input['pid'];
	    $points  	= 	$input['points'];    	   

    try{        	   
    		$isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
           	if(!$isMatchAlreadyAdded)
           	{
				$resArr = $this->security->errorMessage("Invalid matchid");
				return $response->withJson($resArr,$errcode);
           	}

           	$checkPidInMatch = $this->security->checkPidInMatch($matchid,$pid);
           	if(!$checkPidInMatch)
           	{
				$resArr = $this->security->errorMessage("Invalid player id");
				return $response->withJson($resArr,$errcode);
           	}

    	    $sql = "SELECT id FROM matchplrptstotal WHERE matchid=:matchid AND pid=:pid";
            $stmt = $this->db->prepare($sql);
			$stmt->execute(['matchid'=>$matchid,'pid'=>$pid]);
			$res = $stmt->fetch(); 
            
            if(empty($res)){

            	$stmt2 = $this->db->prepare("INSERT INTO matchplrptstotal(matchid,pid,total) VALUES (:matchid,:pid,:total)");
			    $stmt2->bindParam(':matchid', $matchid);
			    $stmt2->bindParam(':pid', $pid);
			    $stmt2->bindParam(':total', $points);

				if(!$stmt2->execute()){
					$resArr = $this->security->errorMessage("points not updated, There is some problem");
		            return $response->withJson($resArr,$errcode);    
				}
        
				$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Record updated successfully.';

            }
			else
			{
				$sql = "UPDATE matchplrptstotal SET total = :total WHERE id=:id ";
	            $stmt2 = $this->db->prepare($sql);
				$stmt2->bindParam(':id', $res['id']);
				$stmt2->bindParam(':total', $points);
				if(!$stmt2->execute()){
					$resArr = $this->security->errorMessage("points not updated, There is some problem");
		            return $response->withJson($resArr,$errcode);    
				}
				$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'points updated successfully.';
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
  	}*/

	/*update pancard*/
  	public function updatepancardFunc($request, $response)
  	{
	
		global $settings,$baseurl;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
		$imgSizeLimit = $settings['settings']['code']['panImgLimit'];
        
        $input = $request->getParsedBody();
		
		$check =  $this->security->validateRequired(['panname','pannumber','dob'],$input);
		if(isset($check['error'])) {
            return $response->withJson($check);
        }

        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];
       
        $doctype   = 'pancard';
        $panname   = $input['panname'];
        $pannumber = $input['pannumber'];
        $dob   	= strtotime($input['dob']);
        $dt 	=  date('Y-m-d').'-18 year' ; 
        $dt 	= strtotime($dt);

        if($dob > $dt){
           $resArr = $this->security->errorMessage("Invalid DOB");
		   return $response->withJson($resArr,$errcode);	
        }

        if(!preg_match ("/^[a-zA-Z\s]+$/",$panname)) {
			$resArr = $this->security->errorMessage("Enter name");
		  	return $response->withJson($resArr,$errcode);		
		}
		
        /*if($this->security->checkPanValidation($pannumber)){
          $resArr = $this->security->errorMessage("Invalid PAN number");
		  return $response->withJson($resArr,$errcode);		
        }*/
        
        if(!in_array($doctype, ['pancard'])){
            $resArr = $this->security->errorMessage("Invalid Doc type");
		    return $response->withJson($resArr,$errcode);	
        }

		if(empty($_FILES['panimage'])){
			$resArr = $this->security->errorMessage("Please select pancard image");
			return $response->withJson($resArr,$errcode);  	
		}

    	$files = $_FILES['panimage']; 

	    $dir = './uploads/pancard/';
 	    $img = explode('.',$files["name"]);
        $ext = end($img);               
        if(($files["size"]/1024) > $imgSizeLimit ){
             $resArr = $this->security->errorMessage("Image size should be less than ".$imgSizeLimit." KB.");
			 return $response->withJson($resArr,$errcode); 
        } 
        if(!in_array($ext, ['jpg','jpeg','png']))
	    {
	     	 $resArr = $this->security->errorMessage("invalid image format.");
			 return $response->withJson($resArr,$errcode);
	    }
        $imgname = time().'_'.$files["name"];
        $imgname = str_replace(" ", "",$imgname);
        $imgname = preg_replace('/[^A-Za-z0-9\-]/', '', $imgname);        
        $target_file = $dir . $imgname;

        if(!move_uploaded_file($files["tmp_name"], $target_file))
        {			                    			                       			    
            $resArr = $this->security->errorMessage("Image not uploaded, There is some problem");
            return $response->withJson($resArr,$errcode);    
        }
        	      
    try
    {
    	$this->db->beginTransaction();
        $getdocument = $this->security->getdocument($uid);
        if(empty($getdocument)){
         	
         	$params = ["userid"=>$uid,"panname"=>$panname,"pannumber"=>$pannumber,"dob"=>$dob,"panimage"=>$imgname,"doctype"=>$doctype,"isverified"=>0]; 
            $stmt = $this->db->prepare("INSERT INTO documents (userid,panname,pannumber,dob,panimage,doctype,isverified) VALUES (:userid,:panname,:pannumber,:dob,:panimage,:doctype,:isverified)");
        }else{

         	$id = $getdocument['id'];
            $params = ["panname"=>$panname,"pannumber"=>$pannumber,"dob"=>$dob,"panimage"=>$imgname,"doctype"=>$doctype,"id"=>$id,"isverified"=>0]; 
            $stmt = $this->db->prepare("UPDATE documents SET panname=:panname,pannumber=:pannumber,dob=:dob,panimage=:panimage,doctype=:doctype,isverified=:isverified WHERE id=:id");
        }        
		if($stmt->execute($params)){			
            	$params = ["userid"=>$uid,"ispanverify"=>INACTIVESTATUS]; 
            	$stmt = $this->db->prepare("UPDATE users SET ispanverify=:ispanverify WHERE id=:userid");
            	$stmt->execute($params);

            	$this->security->kycNoti($uid,"verify pancard","Pancard updated,check and confirm");
            	$this->db->commit();
	            $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Pancard details update successfully.';
		}
		else{

			$resArr = $this->security->errorMessage("Pancard details not updated, There is some problem");
		}
	}
	catch(\PDOException $e)
	{    
		$resArr['code'] 	= 1;
		$resArr['error']	= true;
		$resArr['msg']  	= \Security::pdoErrorMsg($e->getMessage());
		$code 				= $errcode;
		$this->db->rollBack();
	}      
    return $response->withJson($resArr,$code);
}
                            
 
  	/*Get pancard*/
  	public function getpancardFunc($request, $response)
  	{	
		global $settings,$baseurl;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
		$imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];
        
        $input = $request->getParsedBody();
		
		/*$check =  $this->security->validateRequired(['panname','pannumber','dob'],$input);
		if(isset($check['error'])) {
            return $response->withJson($check);
        }*/

        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];
       
        $doctype   = 'pancard';
        
        if(!in_array($doctype, ['pancard'])){
            $resArr = $this->security->errorMessage("Invalid Doc type");
		    return $response->withJson($resArr,$errcode);	
        }			     
    try
    {
        $getdocument = $this->security->getdocument($uid);
         
		if(!empty($getdocument)){

                $resData = [];
				
                $resData['panname']     =	$getdocument['panname'];
                $resData['pannumber']   =	$getdocument['pannumber'];
                $resData['dob']         =	$getdocument['dob'];
                $resData['panimage']    =	$getdocument['panimage'];  
                $resData['isverified']  =	$getdocument['isverified'];  

	            $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Pancard detail.';
				$resArr['data']  =  $resData;
		}
		else{

		    $resArr['code']  	= 1;
			$resArr['error'] 	= true;
			$resArr['msg'] 		= 'Record not found';	
		}
	}
	catch(\PDOException $e)
	{    
		$resArr['code'] 	= 1;
		$resArr['error']	= true;
		$resArr['msg']  	= \Security::pdoErrorMsg($e->getMessage());
		$code 				= $errcode;
	}      
    return $response->withJson($resArr,$code);
 }

	// List Matches Front
	public function listmatchesfrontFunc($request, $response)
	{	
        global $settings,$baseurl;                 

		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
		$limit   = $settings['settings']['code']['pageLimitFront'];
        $input   = $request->getParsedBody();    
        $paging	 = '';
    	$check =  $this->security->validateRequired(['gameid','atype'],$input);				   
    	if(isset($check['error'])) {
            return $response->withJson($check);
        }

        $gameid 	= $input['gameid'];
        $atype   	= $input['atype'];  
      
        $dataResArr  = [];   
        if(isset($input['page']) && !empty($input['page']))
        {          
           	$page   = $input['page'];  //0   LIMIT $page,$offset
            $limit  = (isset($input['limit']) && !empty($input['limit']))?$input['limit']:$settings['settings']['code']['pageLimitFront'];
          	$offset = ($page-1)*$limit; 
          	$paging = "  limit ".$offset.",".$limit;
        }           
       	$checkGm = $this->security->isGameTypeExistById($gameid);
       	if(empty($checkGm)){
	   		$resArr = $this->security->errorMessage("Invalid gameid");
			return $response->withJson($resArr,$errcode); 
        }

        if(!in_array($atype,['fixtures','live','results']))
        {
    	   $resArr = $this->security->errorMessage("Invalid atype value");
		   return $response->withJson($resArr,$errcode);	
    	} 

    	$status = ACTIVESTATUS;
    	$sqlStr = " (m.mstatus=:mstatus) ";
    	$sqlOrder = " m.mdategmt";
    	$params = ["status"=>$status,"gameid"=>$gameid];    
    	if($atype == 'fixtures'){
    		$mstatus = UPCOMING;
    		$params  = $params + ["mstatus"=>$mstatus];    
    	}                           
    	if($atype == 'live'){
    		$mstatus = LIVE;
    		$params  = $params + ["mstatus"=>$mstatus];    
    	}            
    	
    	if($atype == 'results'){
    		$mstatus = COMPLETED;
    		$sqlStr = " (m.mstatus=:mstatus OR m.mstatus=:dc OR m.mstatus=:cl) ";
    		$sqlOrder = " m.mdategmt DESC";
    		$params = $params + ["mstatus"=>$mstatus,"dc"=>DECLARED,"cl"=>CANCELED];    
    	}
    	      	                  
        try{ 
            $logoUrl = $baseurl.'/uploads/teamlogo/'; 	
            $sql = "SELECT m.matchid,m.seriesid,m.seriesname,m.matchname,m.team1,m.team2,CONCAT('".$logoUrl."',m.team1logo) as team1logo,CONCAT('".$logoUrl."',m.team2logo) as team2logo ,m.gametype,m.gameid,m.totalpoints,m.mtype,m.mdate,m.mdategmt,mstatus FROM matches m INNER JOIN matchcontest mc ON m.matchid=mc.matchid WHERE  m.status=:status AND m.gameid=:gameid AND ".$sqlStr." AND mc.status=:status GROUP BY mc.matchid ORDER BY".$sqlOrder.$paging; 
          
            $csql = "SELECT m.id FROM matches m INNER JOIN matchcontest mc ON m.matchid=mc.matchid WHERE  m.status=:status AND m.gameid=:gameid AND ".$sqlStr." AND mc.status=:status GROUP BY mc.matchid";
            $ncount = $this->db->prepare($csql);
		    $ncount->execute($params);
		    $ncount = $ncount->fetchAll();

		    $stmt = $this->db->prepare($sql);
		    $stmt->execute($params);
		    $matches = $stmt->fetchAll();
		   if(!empty($matches)){                                                                   
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Matches List.';	
				$resArr['data']  = $this->security->removenull($matches);			
				$resArr['serverdate']  = time();
				$resArr['total']  = ($ncount)?count($ncount):0;					

		    }else{
                    $resArr['code']  	= 1;
					$resArr['error'] 	= true;
					$resArr['msg'] 		= 'Record not found.';	
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


	// get single match Front
	public function getsinglematchFunc($request, $response)
	{
            global $settings,$baseurl;                 
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode'];

        	$input = $request->getParsedBody();                 
        	$dataResArr = [];   
			$check =  $this->security->validateRequired(['matchid'],$input);				    			         
	        if(isset($check['error'])) {
	            return $response->withJson($check);
	        }

           $matchid     = $input["matchid"];   

   	       $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
           if(!$isMatchAlreadyAdded)
           {
				$resArr = $this->security->errorMessage("Invalid matchid");
				return $response->withJson($resArr,$errcode);
           }  

         try{                        
             $sql = "SELECT matchid,matchname,team1,team2,team1logo,team2logo,gametype,totalpoints,mtype,mdate,mdategmt,wicket,catch,run,four,six FROM matches WHERE matchid=:matchid" ; 
		     $stmt = $this->db->prepare($sql);
		     $stmt->execute(["matchid"=>$matchid]);
		     $match =  $stmt->fetch();
       		 if(!empty($match['team1logo'])){ 
              $match['team1logo'] = $baseurl.'/uploads/teamlogo/'.$match['team1logo'];	
	       	 }	
	      	 if(!empty($match['team2logo'])){
	       	  $match['team2logo'] = $baseurl.'/uploads/teamlogo/'.$match['team2logo'];
	       	 }
	      	
		     
		   if(!empty($match)) {                                                                    
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Matches List.';	
				$resArr['data']  = $this->security->removenull($match);	
		     }else{
                    $resArr['code']  	= 1;
					$resArr['error'] 	= true;
					$resArr['msg'] 	 	= 'Record not found.';	
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

	public function totltmTotljc($matchid,$uid)
	{
		 $sql = "SELECT count(ut.id) as totltm FROM userteams ut  WHERE ut.matchid=:matchid AND ut.userid=:userid";
	    $stmt    =  $this->db->prepare($sql);
	    $stmt->execute(["matchid"=>$matchid,"userid"=>$uid]);
	    $tmRes =  $stmt->fetch();
	    	   
	    //Total Joined Contest
        $sql = "SELECT count(DISTINCT jc.poolcontestid) as totljc FROM joincontests jc WHERE jc.userid=:userid AND jc.matchid=:matchid "; 

        $stmt 	= $this->db->prepare($sql);  
        $stmt->execute(["userid"=>$uid,"matchid"=>$matchid]);
        $jcRes 	= $stmt->fetch();

         if(is_null($jcRes['totljc'])){
        	$jcRes['totljc'] = 0;
        }

        if(is_null($tmRes['totltm'])){
        	$tmRes['totltm'] = 0;
        }

       

        return ["totalteams"=>$tmRes['totltm'],"totaljc"=>$jcRes['totljc']];
	}

	//get Match contest list front
	public function matchcontestlistfrontFunc($request, $response)
	{
        global $settings,$baseurl;     
		$code       =  $settings['settings']['code']['rescode'];
		$errcode    =  $settings['settings']['code']['errcode'];                                
        $input      =  $request->getParsedBody();
        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];
		$check      =  $this->security->validateRequired(['matchid'],$input);				    		                 
        if(isset($check['error'])) {
            return $response->withJson($check);
        }

       	$matchid  = $input["matchid"];   
       	$checkMatchContestMatchid = $this->security->checkMatchContestMatchid($matchid);
       	if(empty($checkMatchContestMatchid))
       	{
			$resArr = $this->security->errorMessage("Invalid matchid");
			return $response->withJson($resArr,$errcode);
       	}

     	$dataResArr = [];  
     	$contestTtl = 0 ;        	                  
try{    
		$logoUrl = $baseurl.'/uploads/contests/';                       
        $sql = "SELECT mc.id as matchcontestid,mc.contestid,c.title,c.subtitle,CONCAT('".$logoUrl."',c.contestlogo) as contestlogo FROM matchcontest mc INNER JOIN contests c ON mc.contestid=c.id WHERE mc.status=:status AND mc.matchid=:matchid ORDER BY mc.contestid DESC"; 	     
	    $stmt = $this->db->prepare($sql);
	    $params = ["status"=>ACTIVESTATUS,"matchid"=>$matchid];
	    $stmt->execute($params);
	    $matchContests = $stmt->fetchAll();        
	    foreach ($matchContests as $contest) {      
		    $sql2 = "SELECT mcp.contestmetaid,cm.contestid,cm.id,cm.joinfee,cm.totalwining,cm.winners,cm.maxteams,(cm.maxteams-count(jc.poolcontestid)) as joinleft,cm.c,cm.m,cm.s 
		        FROM matchcontestpool mcp 
		        INNER JOIN contestsmeta cm ON mcp.contestmetaid=cm.id 
		        left join joincontests jc ON jc.poolcontestid=mcp.contestmetaid AND jc.matchid=:matchid 
		        WHERE mcp.matchcontestid=:matchcontestid  GROUP BY mcp.contestmetaid ORDER BY cm.joinfee" ; 	
		           
		    $stmt2 	 = $this->db->prepare($sql2);
		    $params2 =["matchcontestid"=>$contest['matchcontestid'],"matchid"=>$matchid];
		    $stmt2->execute($params2);
		    $contestsMeta =  $stmt2->fetchAll();

		    $contestsMetaArr =  []; 
		    foreach ($contestsMeta as $contestpool) {	    	
		    	if($contestpool['joinleft'] > 0){
		  			$contestsMetaArr[] = $contestpool; 		
		    	}	    	 
		    }

		    $contest['contestPools'] = $contestsMetaArr;	
		    $contestTtl = $contestTtl + count($contestsMetaArr);       
	       	$dataResArr[] = $contest;
    	}

    	//Total User team    		
      /*  $sql = "SELECT count(ut.id) as totltm FROM userteams ut  WHERE ut.matchid=:matchid AND ut.userid=:userid";
	    $stmt    =  $this->db->prepare($sql);
	    $stmt->execute(["matchid"=>$matchid,"userid"=>$uid]);
	    $tmRes =  $stmt->fetch();
	    	   
	    //Total Joined Contest
        $sql = "SELECT count(DISTINCT jc.poolcontestid) as totljc FROM joincontests jc WHERE jc.userid=:userid AND jc.matchid=:matchid "; 

        $stmt 	= $this->db->prepare($sql);  
        $stmt->execute(["userid"=>$uid,"matchid"=>$matchid]);
        $jcRes 	= $stmt->fetch();

         if(is_null($jcRes['totljc'])){
        	$jcRes['totljc'] = 0;
        }

        if(is_null($tmRes['totltm'])){
        	$tmRes['totltm'] = 0;
        } */
        
	   	if(!empty($dataResArr)) {                                                                    
            $resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = 'Match contests list.';	
			/*$resArr['details']  = ["totalteams"=>$tmRes['totltm'],"totaljc"=>$jcRes['totljc']];*/
			$resArr['details']  = $this->totltmTotljc($matchid,$uid);
			$resArr['data']  = $dataResArr;
						
	    }else{
                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = 'Record not found.';	
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

   // get Matche Team Front
	public function getmatchteamfrontFunc($request, $response)
	{	
	        global $settings,$baseurl;     
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode']; 

	        $input = $request->getParsedBody();                         
	        $dataResArr = [];          	                

			$check =  $this->security->validateRequired(['matchid'],$input);				    			         
	        if(isset($check['error'])) {
	            return $response->withJson($check);
	        }

           	$matchid 	= $input["matchid"];   
           	$sqlByPtype   = " ORDER BY mt.credit DESC";
           	$params2 = ["matchid"=>$matchid];

	        if(isset($input["playertype"]))
	        {
	           $playertype 	= $input["playertype"];   
	           if($this->security->isPlayerTypeIdExist($playertype))
	           {
		        $resArr = $this->security->errorMessage("Invalid player type.");
				return $response->withJson($resArr,$errcode);
		       }

		       $sqlByPtype = " AND mt.playertype=:playertype ORDER BY mt.credit DESC";
		       $params2 = ["matchid"=>$matchid,"playertype"=>$playertype];
		    }

   	       	$isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
           	if(!$isMatchAlreadyAdded)
           	{
				$resArr = $this->security->errorMessage("Invalid matchid");
				return $response->withJson($resArr,$errcode);
           	} 
	           
	    try{   

            $pimgUrl = $baseurl.'/uploads/players/';   	            				
            $sql2 = "SELECT mt.matchid,mt.pid,mt.teamname,mt.pts,mt.credit,mt.playertype,CONCAT('".$pimgUrl."',mt.playerimg) as pimg,mt.isplaying,pm.fullname,pm.pname,
        	 pt.name as ptype FROM matchmeta mt INNER JOIN playersmaster pm 
ON pm.pid=mt.pid LEFT JOIN playertype pt ON pt.id=mt.playertype  WHERE mt.matchid=:matchid ".$sqlByPtype; 

		    $stmt2   = $this->db->prepare($sql2);
		    $stmt2->execute($params2);
		    $matches =  $stmt2->fetchAll(); 
	             
		   	if(!empty($matches)) {                                                                    
	                $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg']   = 'Match team.';	
					$resArr['data']  = $this->security->removenull($matches);	
		    }else{
                    $resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg'] = 'Record not found.';	
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

	
	//Create team front
	public function createteamuserFunc($request, $response)
	{
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 

        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 

        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['gameid','matchid','iscap','isvcap'],$input);
	    if(isset($check['error'])) {
            return $response->withJson($check);
        }
        $gameid = $input['gameid'];

        $checkGm = $this->security->isGameTypeExistById($gameid);
       	if(empty($checkGm)){
	   		$resArr = $this->security->errorMessage("Invalid gameid");
			return $response->withJson($resArr,$errcode); 
        }
    	
        $res = $this->security->createteamuserFunction($input,$uid);

        if($res['error'] == true)
        {
        
        	$resArr = $this->security->errorMessage($res['msg']);
			return $response->withJson($resArr,$errcode);	
        
        }else{

            $resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = $res['msg'];
			return $response->withJson($resArr,$code);
        }
 	}


 	//update team front
	public function updateteamuserFunc($request, $response)
	{        
        global $settings,$baseurl;     

		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 

        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 

        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['gameid','userteamid','matchid','iscap','isvcap'],$input);				    		         
        if(isset($check['error'])) {
            return $response->withJson($check);
        }
        $gameid  = $input['gameid'];
        $checkGm = $this->security->isGameTypeExistById($gameid);
       	if(empty($checkGm)){
	   		$resArr = $this->security->errorMessage("Invalid gameid");
			return $response->withJson($resArr,$errcode); 
        }
    	
        $res = $this->security->updateteamuserFunction($input,$uid);
        if($res['error'] == true)
        {
            	$resArr = $this->security->errorMessage($res['msg']);
				return $response->withJson($resArr,$errcode);
        }else{
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = $res['msg'];
				return $response->withJson($resArr,$code);
        }
 	}

 	//update team front
	public function updateteamuserAdmin($request, $response)
	{        
        global $settings,$baseurl;

		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 

        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['gameid','userid','userteamid','matchid','iscap','isvcap'],$input);				    		         
        if(isset($check['error'])) {
            return $response->withJson($check);
        }

        $uid = $input['userid'];
        $gameid  = $input['gameid'];
        
        $checkGm = $this->security->isGameTypeExistById($gameid);
       	if(empty($checkGm)){
	   		$resArr = $this->security->errorMessage("Invalid gameid");
			return $response->withJson($resArr,$errcode); 
        }
    	
        $res = $this->security->updateteamuserFunction($input,$uid,'admin');
        if($res['error'] == true)
        {
            	$resArr = $this->security->errorMessage($res['msg']);
				return $response->withJson($resArr,$errcode);	
        }else{             
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = $res['msg'];
				return $response->withJson($resArr,$code);
        }
			  
 }


	//Get Get User Team for validation
	public function getuserteamcheckvaliFunc($request, $response)
	{	
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 

        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check 		= $this->security->validateRequired(['matchid'],$input);				    		         
        if(isset($check['error'])) {
            return $response->withJson($check);
        }

        try{
    	
    	$matchid  =  $input["matchid"];   
    	$checkMatchContestMatchid = $this->security->checkMatchContestMatchid($matchid);

        if(empty($checkMatchContestMatchid))
        {
            $resArr = $this->security->errorMessage("Invalid matchid.");
			return $response->withJson($resArr,$errcode);            
        }  

        //$sqlCap = "(SELECT pm.pname FROM userteamplayers utp INNER JOIN playersmaster pm ON utp.pid=pm.pid WHERE utp.userteamid=ut.id AND utp.iscap=1) as cap";
        //$sqlVcap = "(SELECT pm.pname FROM userteamplayers utp INNER JOIN playersmaster pm ON utp.pid=pm.pid WHERE utp.userteamid=ut.id AND utp.isvcap=1) as vcap";

        $sql = "SELECT id,userid,matchid,teamname FROM userteams WHERE matchid=:matchid AND userid=:userid";

	    $stmt    =  $this->db->prepare($sql);
	    $params  =  ["matchid"=>$matchid,"userid"=>$uid];
	    $stmt->execute($params);
	    $matchteams =  $stmt->fetchAll(); 
        
        $plrArr = [];
	    foreach ($matchteams as $row) 
	    {
	    	$sql2 = "SELECT pid,iscap,isvcap FROM userteamplayers WHERE userteamid=:userteamid";
		    $stmt2    =  $this->db->prepare($sql2);
		    $params2  =  ["userteamid"=>$row['id']];
		    $stmt2->execute($params2);
		    $teamplrs =  $stmt2->fetchAll();         

		    $plrArr[] = $teamplrs;
	    }
         	               
        if(!empty($plrArr))
        {
        		$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = "User Teams check vali";
				$resArr['data']  = $this->security->removenull($plrArr);
        }else{             
                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = "Record not found";
			
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

 	//Get Match Team User
	public function getuserteamFunc($request, $response)
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

        try{
    	
	    	$matchid  =  $input["matchid"];   
	    	$checkMatchContestMatchid = $this->security->checkMatchContestMatchid($matchid);

	        if(empty($checkMatchContestMatchid))
	        {
	            $resArr = $this->security->errorMessage("Invalid matchid.");
				return $response->withJson($resArr,$errcode);            
	        }  
	         $sql = "SELECT ut.id,ut.userid,ut.matchid,ut.teamname,ut.cap,ut.vcap,pm.pname as cap, pm2.pname as vcap  FROM userteams ut LEFT JOIN playersmaster pm ON ut.cap=pm.pid LEFT JOIN playersmaster pm2 ON ut.vcap=pm2.pid WHERE ut.matchid=:matchid AND ut.userid=:userid ORDER BY ut.id";

		    $stmt    =  $this->db->prepare($sql);
		    $params  =  ["matchid"=>$matchid,"userid"=>$uid];
		    $stmt->execute($params);
		    $matchteams =  $stmt->fetchAll();
		  
	        if(!empty($matchteams))
	        {
	        		$resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg']   = "User Teams";
					$resArr['data']  = ["total"=>count($matchteams),"teams"=>$this->security->removenull($matchteams)];
	        }else{             
	                $resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg']   = "Record not found";
				
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


	//Get Match user team player
	public function getuserteamplayerFunc($request, $response)
	{
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 

        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['teamid'],$input);				    		
	    if(isset($check['error'])) {
            return $response->withJson($check);
        }

    	$userteamid = $input["teamid"];          
    	//$matchid 	= $input["matchid"];          

       try{

       	$getUteamById = $this->security->getUteamById($userteamid);
       	if(empty($getUteamById)){
       	  	$resArr = $this->security->errorMessage("Invalid teamid id");
		  	return $response->withJson($resArr,$errcode);	
       	}

       	$matchid  = $getUteamById['matchid'];
        $pimgUrl  = $baseurl.'/uploads/players/';  

       // $getgpoints = \Security::getMatchPoints($game,$mtype);
       //print_r($getgpoints); die;

	    $sql2 = "SELECT utp.pid,mt.teamname,mt.pts,mt.credit,utp.iscap,utp.isvcap,pm.pname,pm.fullname,pt.fullname as ptypename,pt.name as ptype,mt.playertype,mt.isplaying,CONCAT('".$pimgUrl."',mt.playerimg) as pimg, (case when utp.iscap=1 then IFNULL(mpt.total,0)*2 else (case when utp.isvcap=1 then IFNULL(mpt.total,0)*1.5 else IFNULL(mpt.total,0) end) end) as points FROM userteamplayers utp INNER JOIN playersmaster pm ON utp.pid = pm.pid  INNER JOIN matchmeta mt ON (utp.pid=mt.pid AND mt.matchid=:matchid) INNER JOIN playertype pt ON mt.playertype=pt.id LEFT JOIN matchplrptstotal mpt ON (utp.pid=mpt.pid AND mpt.matchid=:matchid) WHERE utp.userteamid=:userteamid GROUP BY utp.pid ";
	    
	    //$sql2 = "SELECT utp.pid,mt.teamname,mt.pts,mt.credit,utp.iscap,utp.isvcap,pm.pname,pm.fullname,pt.fullname as ptypename,pt.name as ptype,mt.playertype,CONCAT('".$pimgUrl."',mt.playerimg) as pimg FROM userteamplayers utp INNER JOIN playersmaster pm ON utp.pid = pm.pid  INNER JOIN matchmeta mt ON (utp.pid=mt.pid AND mt.matchid=:matchid) INNER JOIN playertype pt ON mt.playertype=pt.id  WHERE utp.userteamid=:userteamid ";

	    $stmt2   = $this->db->prepare($sql2);
	    $params2 = ["userteamid"=>$userteamid,"matchid"=>$matchid];
	    $stmt2->execute($params2);
	    $res 	 = $stmt2->fetchAll();

	  // echo count($res); die;
  
        if(!empty($res))
        {
        		$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = "User Teams";
				$resArr['data']  = $this->security->removenull($res);
        }else{             
                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = "Record not found";			
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


	//Join contest By user
	/*public function joincontestOldFunc($request, $response)
	{	
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['poolcontestid','uteamid','matchid','fees'],$input);	

	    if(isset($check['error'])) {
            return $response->withJson($check);
        }
        
    	$poolcontestid	=  $input["poolcontestid"];
    	$uteamid 		=  $input["uteamid"];
    	$matchid 		=  $input["matchid"];
    	$fees 			=  $input["fees"];
    	$created 		=  time();
         
    try{
        
        $this->db->beginTransaction();

        $checkMatchidForJoin = $this->security->checkMatchidForJoin($matchid);

        if(empty($checkMatchidForJoin))
        {
            $resArr = $this->security->errorMessage("Invalid matchid.");
			return $response->withJson($resArr,$errcode);            
        }

        $isUserTeamExist = $this->security->isUserTeamExist($matchid,$uid,$uteamid);
        if(empty($isUserTeamExist)){
           $resArr = $this->security->errorMessage("Invalid teamid.");
		   return $response->withJson($resArr,$errcode);            	
        }

        $checkPoolForJoinContest = $this->security->checkPoolForJoinContest($matchid,$poolcontestid);
        if(empty($checkPoolForJoinContest)){
           $resArr = $this->security->errorMessage("Invalid poolcontestid.");
		   return $response->withJson($resArr,$errcode);            	
        }

        $getPoolDetails = $this->security->getPoolDetails($poolcontestid);
        if(empty($getPoolDetails)){
           $resArr = $this->security->errorMessage("Invalid poolcontestid.");
		   return $response->withJson($resArr,$errcode);
        }


        if($getPoolDetails['joinfee'] != $fees){
            $resArr = $this->security->errorMessage("Invalid pool fees.");
		    return $response->withJson($resArr,$errcode);
        }

        //check join limit
        $getTtlCountTmJoinedInPool = $this->security->getTtlCountTmJoinedInPool($matchid,$poolcontestid);
        if($getTtlCountTmJoinedInPool['totljoind'] >= $getPoolDetails['maxteams'])
        {
           $resArr = $this->security->errorMessage("This contests full");
		   return $response->withJson($resArr,$errcode);	
        } 
        
        $checkAlreadyJoinContest = $this->security->checkAlreadyJoinContest($uid,$matchid,$poolcontestid,$uteamid);
        if(!empty($checkAlreadyJoinContest)) {
         	$resArr = $this->security->errorMessage("already joined this contest");
		    return $response->withJson($resArr,$errcode);	
        }

        $getUserWalletBalance = $this->security->getUserWalletBalance($uid);             
        if($getUserWalletBalance['walletbalance'] < $fees){
           $resArr = $this->security->errorMessage("Not have balance to join this contest,add balance first");
		   return $response->withJson($resArr,$errcode);	
        }          
        
        $params = ["userid"=>$uid,"matchid"=>$matchid,"poolcontestid"=>$poolcontestid,"uteamid"=>$uteamid,"fees"=>$fees,"created"=>$created]; 

        $stmt = $this->db->prepare("INSERT INTO joincontests (userid,matchid,poolcontestid,uteamid,fees,created) VALUES (:userid,:matchid,:poolcontestid,:uteamid,:fees,:created)");  
  
        if($stmt->execute($params))
        {
            $lastId = $this->db->lastInsertId();
		 	
            $balance = $getUserWalletBalance['walletbalance'] - $fees;         
	        
	        $updateUserWalletBalance = $this->security->updateUserWalletBalance($uid,$balance);	 
	        if(!$updateUserWalletBalance){
	            // $this->security->deleteUsrJoinContest($lastId);
	           $resArr = $this->security->errorMessage("There is some problem in join cointest. contact to webmaster");
			   return $response->withJson($resArr,$errcode);
	        }
            $amount = -$fees;
	        $params3 = ["userid"=>$uid,"amount"=>$amount,"created"=>$created,"docid"=>$lastId,"ttype"=>DR,"atype"=>CJOIN];

	      	$stmt3 = $this->db->prepare("INSERT INTO transactions (userid,docid,amount,txdate,ttype,atype) VALUES (:userid,:docid,:amount,:created,:ttype,:atype)");
	      	$stmt3->execute($params3);

            $this->db->commit();
    		$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Contest joined successfully.";
				
        }else{             
                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = "Contest not joined.";
        } 
    }
    catch(PDOException $e)
    {
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
			$this->db->rollBack();
    }
        return $response->withJson($resArr,$code);
   }*/

   	//Join contest By user
	public function joincontestFunc($request, $response)
	{
        global $settings,$baseurl;
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id'];
        $input      = 	$request->getParsedBody();
        $dataResArr = 	[];
    	
	    $check = $this->security->validateRequired(['poolcontestid','matchid','fees','atype'],$input);	

	    if(isset($check['error'])) {
            return $response->withJson($check);
        }
        
    	$poolcontestid	=  $input["poolcontestid"];
    	
    	$matchid 		=  $input["matchid"];
    	$fees 			=  $input["fees"];
    	$atype 			=  $input["atype"];
    	$created 		=  time();
    	$poolfees 		=  $fees;

    	if(!in_array($atype, ["prejoin","join"])){
    		$resArr = $this->security->errorMessage("Invalid atype");
			return $response->withJson($resArr,$errcode);            	
    	}
    	         
    try{
        
        $this->db->beginTransaction();

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
      
        $checkPoolForJoinContest = $this->security->checkPoolForJoinContest($matchid,$poolcontestid);
        if(empty($checkPoolForJoinContest)){
           $resArr = $this->security->errorMessage("Invalid poolcontestid.");
		   return $response->withJson($resArr,$errcode);            	
        }

        $getPoolDetails = $this->security->getPoolDetails($poolcontestid);
        
        $m = $getPoolDetails['m'];
        $s = $getPoolDetails['s'];

        if(empty($getPoolDetails)){
           $resArr = $this->security->errorMessage("Invalid poolcontestid.");
		   return $response->withJson($resArr,$errcode);
        }

        $usrJoinTeam = $this->security->getUsrJoinTeamInPool($uid,$matchid,$poolcontestid);
        if(count($usrJoinTeam) >=1 && $s == 1){
           $resArr = $this->security->errorMessage("You can join with single team only.");
		   return $response->withJson($resArr,$errcode);              
        }

        if($getPoolDetails['joinfee'] != $fees){
            $resArr = $this->security->errorMessage("Invalid pool fees.");
		    return $response->withJson($resArr,$errcode);
        }

        //check join limit
        $getTtlCountTmJoinedInPool = $this->security->getTtlCountTmJoinedInPool($matchid,$poolcontestid);
        if($getTtlCountTmJoinedInPool['totljoind'] >= $getPoolDetails['maxteams'])
        {
           $resArr = $this->security->errorMessage("This contests full");
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
        
        /*$bnsDedRes = $this->security->getrefcommisions("bnsdeduction");
        $bnsdeduction = $bnsDedRes['amount'];*/         
        $bnsDedRes = $this->security->getContestDetails($getPoolDetails['contestid']);
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

        $outputRes = ['wallet'=>$usrWltBal,'paybnswlt'=>(string)sprintf("%.2f", $payBnsWlt),'paybalwlt'=>(string)sprintf("%.2f", $payBalWlt),'paywinwlt'=>(string)sprintf("%.2f", $payWinWlt),'fees'=>(string)sprintf("%.2f", $fees),'bnsdeduction'=>$bnsdeduction]; 
		

        if($atype == "prejoin")
        {
        	$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Join Confirmation";
			$resArr['data']  = $outputRes;

        }else{
        	if($fees > 0){
           		$resArr = $this->security->errorMessage("Not have balance to join this contest,add balance first");
		   		return $response->withJson($resArr,$errcode);
        	}

        	if(!isset($input["uteamid"]) || empty($input["uteamid"])){
        	  $resArr = $this->security->errorMessage("teamid missing or empty");
		   		return $response->withJson($resArr,$errcode);		
        	}

        	$uteamid 		=  $input["uteamid"];
	        $isUserTeamExist = $this->security->isUserTeamExist($matchid,$uid,$uteamid);
	        if(empty($isUserTeamExist)){
	           $resArr = $this->security->errorMessage("Invalid teamid.");
			   return $response->withJson($resArr,$errcode);            	
	        }

	        $checkAlreadyJoinContest = $this->security->checkAlreadyJoinContest($uid,$matchid,$poolcontestid,$uteamid);
	        if(!empty($checkAlreadyJoinContest)) {
	         	$resArr = $this->security->errorMessage("already joined this contest");
			    return $response->withJson($resArr,$errcode);	
	        }

	        $matchDetl = $this->security->getMatchDetails($matchid);
	        $gameid    = $matchDetl['gameid'];

	        $params = ["userid"=>$uid,"matchid"=>$matchid,"poolcontestid"=>$poolcontestid,"uteamid"=>$uteamid,"fees"=>$poolfees,"created"=>$created,"gameid"=>$gameid];
	        $stmt = $this->db->prepare("INSERT INTO joincontests (userid,matchid,poolcontestid,uteamid,fees,created,gameid) VALUES (:userid,:matchid,:poolcontestid,:uteamid,:fees,:created,:gameid)");  
	  
	        if($stmt->execute($params))
	        {
		        $lastId = $this->db->lastInsertId();				 	
		            //$balance = $getUserWalletBalance['walletbalance'] - $fees;
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


			    $webname= $settings['settings']['webname'];
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

    			$this->applyRefferBonus($uid,$poolfees,$created,$lastId);
    			
	            //$this->sendMail($poolDetails);
	            //file_put_contents('joimail.txt',print_r($poolDetails,true));	
	            $this->db->commit();
	    		$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = "Contest joined successfully.";

	        }else{

                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = "Contest not joined.";
	        }
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


   	public function applyRefferBonus($uid,$poolfees,$created,$lastId){
   		$sqlchekRef = "SELECT id,referedby,refmaxamt,refpercent,bnsamt,givenbns FROM referralby WHERE userid=:userid AND bnsamt>givenbns";    		
		$refstmt    = $this->db->prepare($sqlchekRef);
		$refstmt->execute(["userid"=>$uid]);
		$sqlchekRes = $refstmt->fetch();
		if(!empty($sqlchekRes)){
			$givenbns   = $sqlchekRes['givenbns'];
			$refPending = $sqlchekRes['bnsamt']-$givenbns;
			$refid 		= $sqlchekRes['id'];
			$refbyUid 	= $sqlchekRes['referedby'];
			$refmaxamt 	= $sqlchekRes['refmaxamt'];
			$refpercent = $sqlchekRes['refpercent'];

			$refbns 	= $poolfees*$refpercent/100;
			if($refbns > $refmaxamt){
				$refbns = $refmaxamt;
			}
			if($refbns > $refPending){
				$refbns = $refPending;
			}
			
			if($refbns>0)
			{
				$refUsrWltBal 	= $this->security->getUserWalletBalance($refbyUid);
				$curWltbns     	= $refUsrWltBal['wltbns'];
				$newWltbns 		= $curWltbns + $refbns;

				$givenbns 		= $givenbns + $refbns;
				$this->security->updateRefferTbl($uid,$refbyUid,$givenbns,$created);
				$updtUsrWlt = $this->security->updateUserWallet($refbyUid,"wltbns",$newWltbns);
		    	$this->security->updateTransaction($refbyUid,$refbns,$created,$lastId,CR,REFBNS,WLTBNS,$curWltbns,$newWltbns);
	    	}
		}
   }



   /*public function updateTransaction($uid,$amount,$created,$docid,$ttype,$atype,$wlt){

   	$params3 = ["userid"=>$uid,"amount"=>$amount,"created"=>$created,"docid"=>$docid,"ttype"=>$ttype,"atype"=>$atype,"wlt"=>$wlt];
  	$stmt3 = $this->db->prepare("INSERT INTO transactions SET userid=:userid,amount=:amount,txdate=:created,docid=:docid,ttype=:ttype,atype=:atype,wlt=:wlt");
  	$stmt3->execute($params3);

    }*/

	//Switch team 
	public function switchteamFunc($request, $response)
	{
	
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['matchid','poolcontestid','uteamid','switchteamid'],$input);	

	    if(isset($check['error'])) {
            return $response->withJson($check);
        }
        
    	$matchid 		=  $input["matchid"];
    	$poolcontestid	=  $input["poolcontestid"];
    	$uteamid 		=  $input["uteamid"];
    	$switchteamid 	=  $input["switchteamid"];
            
    try{
        
        $this->db->beginTransaction();
		
		if($this->security->checkMatchFreaz($matchid)){
        	$resArr = $this->security->errorMessage("You can't switch team now.");
			return $response->withJson($resArr,$errcode); 	           
        }        

	    $isUserTeamExist = $this->security->isUserTeamExist($matchid,$uid,$uteamid);
	    if(empty($isUserTeamExist)){
	       $resArr = $this->security->errorMessage("Invalid teamid.");
		   return $response->withJson($resArr,$errcode);            	
	    }

	    $isUserTeamExist = $this->security->isUserTeamExist($matchid,$uid,$switchteamid);
	    if(empty($isUserTeamExist)){
	       $resArr = $this->security->errorMessage("Invalid switchteamid.");
		   return $response->withJson($resArr,$errcode);            	
	    }
        
        $checksteam = $this->security->checkAlreadyJoinContest($uid,$matchid,$poolcontestid,$switchteamid);
	    if(!empty($checksteam)) {
	     	$resArr = $this->security->errorMessage("Switchteam already joined in this contests");
		    return $response->withJson($resArr,$errcode);	
	    }

	    $checkAlreadyJoinContest = $this->security->checkAlreadyJoinContest($uid,$matchid,$poolcontestid,$uteamid);
	    if(empty($checkAlreadyJoinContest)) {
	     	$resArr = $this->security->errorMessage("Contest not joined with this teamid");
		    return $response->withJson($resArr,$errcode);	
	    }
	
        $jcid 	= $checkAlreadyJoinContest['id'];

        $params = ["id"=>$jcid,"switchteamid"=>$switchteamid]; 

        $stmt = $this->db->prepare("UPDATE joincontests SET uteamid=:switchteamid WHERE id=:id");  
        if($stmt->execute($params))
        {                           
            $this->db->commit();
    		$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Team switched successfully.";
				
        }else{             

            $resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = "Team not swithed.";
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


  // Get Join contest By user
	public function getjoinedcontestFunc($request, $response)
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
      
        $params = ["userid"=>$uid,"matchid"=>$matchid]; 
        $sql = "SELECT jc.poolcontestid,jc.poolcontestid as contestmetaid,cm.contestid,count(jc.poolcontestid) as jointmwith,cm.joinfee,cm.totalwining,cm.winners,cm.maxteams,cm.c,cm.m,cm.s,IFNULL(mcp.iscancel,0) as iscancel FROM joincontests jc INNER JOIN contestsmeta cm ON jc.poolcontestid=cm.id LEFT JOIN matchcontestpool mcp ON (jc.poolcontestid=mcp.contestmetaid AND mcp.matchid=:matchid) WHERE jc.userid=:userid AND jc.matchid=:matchid GROUP BY jc.poolcontestid ";
                        
        $stmt 	= $this->db->prepare($sql);  
        $stmt->execute($params);
        $contests 	= $stmt->fetchAll();
        
        $resData = [];
        foreach($contests as $contest) {
        	$mxtms = $contest['maxteams'];
        	$pcid  = $contest['poolcontestid'];
        	$sql2  = "SELECT (".$mxtms."-count(jc.poolcontestid)) as joinleft FROM joincontests jc WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid" ; 
            $stmt2 	= $this->db->prepare($sql2);  
	        $stmt2->execute(["matchid"=>$matchid,"poolcontestid"=>$pcid]);
	        $res 	= $stmt2->fetch();	
	        $contest['joinleft']= $res['joinleft'];     
	        $resData[] = $contest; 	
        }
        
        if(!empty($resData))
        {                        	
    		$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Joined contest.";
			$resArr['data']  = $this->security->removenull($resData);

        }else{             

            $resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = "Not joined contests.";			
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

    //Add Balance User
    public function addbalanceFunc($request, $response)
    {
		
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['amount','txid','ptype','status'],$input);	

	    if(isset($check['error'])) {
            return $response->withJson($check);
        }
        
    	$amount		=  $input["amount"];
    	$txid 		=  $input["txid"];
    	$ptype 		=  $input["ptype"];
    	$status 	=  $input["status"];
    	$created 	=  time();         

        if(!in_array($ptype, ['paytm'])){
           $resArr = $this->security->errorMessage("Invalid ptype.");
		   return $response->withJson($resArr,$errcode);            	
        }
        if($amount < 0){
           $resArr = $this->security->errorMessage("Invalid amount");
		   return $response->withJson($resArr,$errcode);            	
        }
        if(!in_array($status, ['success'])){
           $resArr = $this->security->errorMessage("Invalid status");
		   return $response->withJson($resArr,$errcode);            	
        }

    try {

    	$this->db->beginTransaction();
     	
        $getTransactionId = $this->security->getTransactionId($txid);
        if(!empty($getTransactionId)){
           $resArr = $this->security->errorMessage("Transaction id already exists.");
		   return $response->withJson($resArr,$errcode);
        }

        $params = ["userid"=>$uid,"amount"=>$amount,"pmode"=>$ptype,"txid"=>$txid,"status"=>$status,"created"=>$created,'gatewayname'=>$gatewayname,]; 
        $stmt = $this->db->prepare("INSERT INTO transactions (userid,amount,ptype,txid,status,created) VALUES (:userid,:amount,:ptype,:txid,:status,:created)");   
       
        if($stmt->execute($params))
        {
        	
        $getUserWalletBalance = $this->security->getUserWalletBalance($uid);  
        $balance = $getUserWalletBalance['walletbalance'] + $amount ;        
        $updateUserWalletBalance = $this->security->updateUserWalletBalance($uid,$balance);
        if(!$updateUserWalletBalance){

            $resArr = $this->security->errorMessage("There is some problem in wallet balance update. contact to webmaster");
		   return $response->withJson($resArr,$errcode);
        }
                $this->db->commit();
   				$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = "Transaction save successfully.";				
        }else{             
                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = "Transaction not saved.";
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


	//Get Balance User
	public function getuserbalanceFunc($request, $response)
	{	
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                                
        $dataResArr = 	[];          	                

    try{

    		$sql = "SELECT IFNULL(sum(jc.fees),0) as feesamt FROM matches m INNER JOIN joincontests jc ON (m.matchid = jc.matchid AND jc.userid=:userid)
WHERE (m.mstatus=:mstatusuc OR m.mstatus=:mstatusli OR m.mstatus=:mstatuscm)"; 
		    $stmt = $this->db->prepare($sql);
		    $stmt->execute(["userid"=>$uid,"mstatusuc"=>UPCOMING,"mstatusli"=>LIVE,"mstatuscm"=>COMPLETED]);
		    $resexposure = $stmt->fetch();


        $wltbal = $this->security->getUserWalletBalance($uid);  
        $wltbal['exposure'] =  $resexposure['feesamt'];
       
       	$resArr['code']  = 0;
		$resArr['error'] = false;
		$resArr['msg']   = "User Wallet Balance.";				       
		$resArr['data']  = $wltbal;				       
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

    
	// My matches  Front
	public function mymatchesFunc($request, $response)
	{
        global $settings,$baseurl;                 

		$code    	= $settings['settings']['code']['rescode'];
		$errcode 	= $settings['settings']['code']['errcode'];
		$loginuser 	= $request->getAttribute('decoded_token_data');
    	$uid 		= $loginuser['id']; 
        $input   	= $request->getParsedBody();   
        $paging		= '';
        $check = $this->security->validateRequired(['gameid','atype'],$input);	

	    if(isset($check['error'])) {
            return $response->withJson($check);
        }
        
    	$atype		=  $input["atype"];
    	$gameid 	=  $input['gameid'];
    	$mstatus	=  '';
        
        $limit  = (isset($input['limit']) && !empty($input['limit']))?$input['limit']:50;
        if(isset($input['page']) && !empty($input['page']))
        {
         	$page = $input['page'];  //0   LIMIT $page,$offset
         	$page = ($page-1)*$limit;	
        }

       	$checkGm = $this->security->isGameTypeExistById($gameid);
       	if(empty($checkGm)){
	   		$resArr = $this->security->errorMessage("Invalid gameid");
			return $response->withJson($resArr,$errcode); 
        }  
    	
    	if(!in_array($atype,['fixtures','live','results'])){
    	   $resArr = $this->security->errorMessage("Invalid atype value");
		   return $response->withJson($resArr,$errcode);	
    	}    

    	/*if($atype == 'fixtures'){$mstatus = 'uc';}                           
    	if($atype == 'live'){$mstatus = 'li';}                           
    	if($atype == 'results'){$mstatus = 'cm';} */


    	$status = ACTIVESTATUS;
    	$sqlStr = " (m.mstatus=:mstatus) ";  
    	$sqlOrder = " m.mdategmt";  			
    	$params = ["userid"=>$uid,"gameid"=>$gameid];    
    	if($atype == 'fixtures'){
    		$mstatus = UPCOMING;
    		$params  = $params + ["mstatus"=>$mstatus];    
    	}                           
    	if($atype == 'live'){
    		$mstatus = LIVE;
    		$params  = $params + ["mstatus"=>$mstatus];    
    	}                	
    	if($atype == 'results'){
    		$mstatus = COMPLETED;
    		$sqlStr = " (m.mstatus=:mstatus OR m.mstatus=:dc ) ";
    		$sqlOrder = " m.mdategmt DESC";
    		$params = $params + ["mstatus"=>$mstatus,"dc"=>DECLARED];    
    	}

        $dataResArr = [];          	                  

         try{                         
            $logoUrl = $baseurl.'/uploads/teamlogo/'; 	           
            $sql = "SELECT jc.matchid as mid, m.matchid,m.matchname,m.team1,m.team2,CONCAT('".$logoUrl."',m.team1logo) as team1logo,CONCAT('".$logoUrl."',m.team2logo) as team2logo ,m.gametype,m.totalpoints,m.mtype,m.mdate,m.mdategmt,m.mstatus FROM joincontests jc INNER JOIN matches m ON jc.matchid=m.matchid  WHERE jc.userid=:userid AND m.gameid=:gameid AND ".$sqlStr." GROUP BY jc.matchid ORDER BY ".$sqlOrder.$paging; 
		    $stmt = $this->db->prepare($sql);
		    $stmt->execute($params);
		    $matches = $stmt->fetchAll();

		    $csql = "SELECT jc.id  FROM joincontests jc INNER JOIN matches m ON jc.matchid=m.matchid  WHERE jc.userid=:userid AND m.gameid=:gameid AND ".$sqlStr." GROUP BY jc.matchid ";
            $ncount = $this->db->prepare($csql);
		    $ncount->execute($params);
		    $ncount = $ncount->fetchAll();

		   	if(!empty($matches)){                                                                   
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Matches List.';	
				$resArr['data']  = $this->security->removenull($matches);			
				$resArr['serverdate']  = time();
				$resArr['total']  = ($ncount)?count($ncount):0;					
		    }else{
                    $resArr['code']  	= 1;
					$resArr['error'] 	= true;
					$resArr['msg'] 		= 'Record not found.';	
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

	// get contest details
	public function getcontestdetailsFunc($request, $response)
	{
        global $settings,$baseurl;                 
		$code    	= $settings['settings']['code']['rescode'];
		$errcode 	= $settings['settings']['code']['errcode'];
		$loginuser 	= $request->getAttribute('decoded_token_data');
    	$uid 		= $loginuser['id']; 
        $input   	= $request->getParsedBody();   
        $check = $this->security->validateRequired(['matchid','poolcontestid'],$input);	
	    if(isset($check['error'])) {
            return $response->withJson($check);
        }

    	$matchid		=  $input["matchid"];
    	$poolcontestid	=  $input["poolcontestid"];
        $dataResArr 	= [];  

        try{ 
        	$checkContest	= $this->security->checkMatchPoolByMidCid($matchid,$poolcontestid);	
        	if(empty($checkContest)) {
				$resArr = $this->security->errorMessage("Invalid matchid OR poolcontestid");
		   		return $response->withJson($resArr,$errcode); 			
        	}

        	$sql = "SELECT cm.joinfee,cm.totalwining,cm.winners,cm.maxteams,(cm.maxteams-count(jc.poolcontestid)) as joinleft,c,m,s FROM contestsmeta cm LEFT JOIN joincontests jc ON cm.id=jc.poolcontestid AND jc.matchid=:matchid WHERE cm.id=:poolcontestid";	        	
		    $stmt = $this->db->prepare($sql);
		    $stmt->execute(["poolcontestid"=>$poolcontestid,"matchid"=>$matchid]);
		    $pool = $stmt->fetch();
            $pool['brkprize'] = $this->security->getPoolBreakPrize($poolcontestid);

        $sql = "SELECT mstatus FROM matches WHERE matchid='".$matchid."'"; 
        $stmt 	= $this->db->prepare($sql);  
        $stmt->execute();
        $matches 	= $stmt->fetch();
		$pool['mstatus']=$matches['mstatus'];
        
		   	if(!empty($pool)){                                                                   
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Contest Pool Break Prize.';	
				$resArr['details']  = $this->totltmTotljc($matchid,$uid);
				$resArr['data']  = $this->security->removenull($pool);					
		    }else{
                    $resArr['code']  	= 1;
					$resArr['error'] 	= true;
					$resArr['msg'] 		= 'Record not found.';	
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


	//get contest joined all team (leaderboard)
	public function getcontestjoinedteamsallFunc($request, $response)
	{	
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                

	    $check = $this->security->validateRequired(['matchid','poolcontestid'],$input);	
	    if(isset($check['error'])) {
           return $response->withJson($check);
        }
        $paging     	= ""; 
    	$matchid    	=  $input["matchid"];    	
    	$poolcontestid  =  $input["poolcontestid"];  
    	if(isset($input['page']) && !empty($input['page']))
        {          
        	//$limit  = $input['limit']; //2
          	$page   = $input['page'];  //0   LIMIT $page,$offset
            $limit  = $settings['settings']['code']['pageLimitFront'];
          	$offset = ($page-1)*$limit; 
          	$paging = " limit ".$offset.",".$limit;
        }

    try{
    	
        $params = ["matchid"=>$matchid,"poolcontestid"=>$poolcontestid,"userid"=>$uid];
        $resMyteam = $this->security->getUserTeamRank($params);
        $msts = $this->security->getMatchStatus($matchid);

        /*if($msts['mstatus'] == UPCOMING){
        	$res = [];
        	$resCount['total'] = 0;
        }else{*/

        $sqlCount	=	"SELECT count(jc.uteamid) as total
     	FROM joincontests jc WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid AND jc.userid !=:userid ";
      	$stmtCount	=	$this->db->prepare($sqlCount);
      	$stmtCount->execute($params);
      	$resCount	=	$stmtCount->fetch();
      
     	/*$sql = "SELECT jc.uteamid,ut.teamname,jc.winbal,jc.ptotal,FIND_IN_SET( jc.ptotal, (
SELECT GROUP_CONCAT( jc.ptotal
ORDER BY jc.ptotal DESC ) 
FROM joincontests jc  WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid)
) AS rank
     	FROM joincontests jc INNER JOIN userteams ut ON jc.uteamid=ut.id WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid AND jc.userid !=:userid ORDER BY jc.ptotal DESC ".$paging;*/ 

     	/*$sql = "SELECT jc.uteamid,ut.teamname,jc.winbal,jc.ptotal, @rank := (CASE 
				WHEN @rankval = jc.ptotal THEN @rank
				    WHEN (@rankval := jc.ptotal) IS NOT NULL THEN @rank + 1
				    WHEN (@rankval := jc.ptotal) IS NOT NULL THEN 1
				END) AS rnk
				FROM joincontests jc INNER JOIN userteams ut ON jc.uteamid=ut.id, 
				(SELECT @rank := 0, @partval := NULL, @rankval := NULL) AS x
				WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid
				ORDER BY jc.ptotal DESC ".$paging;*/

		$sql = "SELECT
 * from (SELECT jc.userid,jc.uteamid,ut.teamname,jc.winbal,jc.ptotal, @rank := (CASE 
WHEN @rankval = jc.ptotal THEN @rank
    WHEN (@rankval := jc.ptotal) IS NOT NULL THEN @rank + 1
    WHEN (@rankval := jc.ptotal) IS NOT NULL THEN 1
END) AS rank
FROM joincontests jc INNER JOIN userteams ut ON jc.uteamid=ut.id, 
(SELECT @rank := 0, @partval := NULL, @rankval := NULL) AS x
WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid 
ORDER BY jc.ptotal desc) tbl WHERE userid !=:userid ".$paging;

        $stmt 	= $this->db->prepare($sql);  
        $stmt->execute($params);
        $res 	= $stmt->fetchAll();         		
  
        if(!empty($res) || !empty($resMyteam))
        {                        	
    		$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Contest teams.";			
			$resArr['data']  = ["total"=>$resCount['total'],"list"=>$res,"myteams"=>$resMyteam];         
        }else{             
            $resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = "result not found.";			
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


	// get joined team in pool By user
	public function getjoinedteamFunc($request, $response)
	{
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                    	
	    $check = $this->security->validateRequired(['matchid','poolcontestid'],$input);	
	    if(isset($check['error'])) {
           return $response->withJson($check);
        }
    	$matchid  =  $input["matchid"];    	
    	$poolcontestid  =  $input["poolcontestid"];    	    	
    	//chkMtchPoolcontest($matchid,$poolcontestid)  	         
    try{        
        $params = ["userid"=>$uid,"matchid"=>$matchid,"poolcontestid"=>$poolcontestid]; 
        $sql = "SELECT uteamid FROM joincontests WHERE userid=:userid AND matchid=:matchid AND poolcontestid=:poolcontestid ";
        $stmt 	= $this->db->prepare($sql);  
        $stmt->execute($params);
        $res 	= $stmt->fetchAll();        
        if(!empty($res))
        {                        	
    		$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = "Joined contest.";
			$resArr['data']  = $this->security->removenull($res);
        }else{             
            $resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = "Not joined contests.";			
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


    // get Contest Team And Player
	public function getContestTeamAndPlayer($request, $response)
	{
        global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
        $input      = 	$request->getParsedBody();                         
        $dataResArr = 	[];          	                
    	
	    $check = $this->security->validateRequired(['matchid','poolcontestid'],$input);	
	    if(isset($check['error'])) {
           return $response->withJson($check);
        }
    	$matchid  =  $input["matchid"];    	
    	$poolcontestid  =  $input["poolcontestid"];    	
    	         
    try{
        
        $params = ["matchid"=>$matchid,"poolcontestid"=>$poolcontestid]; 
        $sql = "SELECT jc.uteamid,ut.matchid,ut.userid,ut.teamname FROM joincontests jc INNER JOIN userteams ut ON jc.uteamid=ut.id WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid ";
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


   	public function withdrawRequest($request,$response) 
	{
		global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
		$webname = $settings['settings']['webname'];
		$loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id']; 
	    $input = $request->getParsedBody();
	    $check =  $this->security->validateRequired(['amount'],$input);
	    if(isset($check['error'])) {
	        return $response->withJson($check);
	    }

	    $chkKyc = $this->security->checkUserKyc($uid);
	    if(empty($chkKyc)){
	    	$resArr = $this->security->errorMessage("Verify KYC first");
			return $response->withJson($resArr,$errcode);
	    }

	   	$mailData['wmoney']= $amount = $input["amount"];                          		
	   	$created    = time();                           		       
	   	$withdrawLimit = $this->security->getrefcommisions("withdrawlimit")['amount']; 

	   	$usrWltBal 	= $this->security->getUserWalletBalance($uid);	   	
        $wltwin  	=  $usrWltBal['wltwin']; 
        $wltwinPre  =  $wltwin;

     	if($amount < $withdrawLimit){
       		$resArr = $this->security->errorMessage("Minimum withdrawal limit is ".$withdrawLimit);
			return $response->withJson($resArr,$errcode);	
     	}
     	if($wltwin < $amount || $wltwin < $withdrawLimit ){
       		$resArr = $this->security->errorMessage("You do not have this much amount in your winning wallet");
			return $response->withJson($resArr,$errcode);	
     	}

        try{    
	   		$this->db->beginTransaction();
            $params =  ['userid'=>$uid,'amount'=>$amount,'created'=>$created]; 
        	$stmt = $this->db->prepare("INSERT INTO withdrawals SET userid=:userid,amount=:amount,created=:created");

		    if($stmt->execute($params)){
				
							
				$lastId 	= $this->db->lastInsertId();				
				$wltwin 	= $wltwin-$amount;
				$amount 	= -$amount;			
		    	$updtUsrWlt = $this->security->updateUserWallet($uid,"wltwin",$wltwin);
		    	$this->security->updateTransaction($uid,$amount,$created,$lastId,DR,WITHDRAW,WLTWIN,$wltwinPre,$wltwin,TXSPENDING);	
		    	

		    	$userData   = $this->security->getUserDetail($uid);	

                $wmsg="Your Withdrawal Request is received, it will be processed in the next 7 working days.";
		    	$title="Withdraw Request";
	    	 	$mailData['email']   = $userData['email'];
			    $mailData['name']    = $userData['name'];
			    $mailData['webname'] = $webname;
			    $mailData['content'] = $wmsg;
			    $mailData['subject'] = $title;
			    $mailData['template'] = "withdraw_wequest.php";
			    $notify['ntype']=WITHDRAW_NOTIFY;
			    $notify['title']=$title;
			    $notify['message']=$wmsg;
			    $notify['userid']=json_encode([$uid=>0]);
			    $this->addNotification($notify); 
			
			    if($userData['devicetype']!='web'){
			    	$notification_data=['token'=>[$userData['devicetoken']],'devicetype'=>$userData['devicetype'],'body'=>$wmsg,'title'=>$title,'ntype'=>WITHDRAW_NOTIFY,'notify_id'=>1];
			    	$this->security->notifyUser($notification_data); 
				}
				$this->sendMail($mailData);
			    $this->db->commit();
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = "Withdrawal request sent";	
		    }else{
                    $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg'] = 'request not sent';	
		    }
		}
	    catch(\PDOException $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
				$this->rollBack();
	    }             
        return $response->withJson($resArr,$code);			  
    }

public function addNotification($inputData){
      
        $collection = $this->mdb->notification;
        $title   = $inputData["title"];                    
        $message = $inputData["message"];     
        $img     = '';
        $userid  = (isset($inputData['userid']) && !empty($inputData['userid']))?json_decode($inputData['userid']):null;
        $data =  ['title'=>$title,'message'=>$message,'ntype'=>$inputData["ntype"]];
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
      $data['created']=time();        
      $res=$collection->insertOne($data);
      return true;
    }
    
   /* 
    //Get Notification	
	public function getNotification($request, $response){
            global $settings,$baseurl;            
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode'];			
			/*$loginuser 	= 	$request->getAttribute('decoded_token_data');
    		$uid 		= 	$loginuser['id'];  	         
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
            if(!empty($input['search']) && isset($input['search'])){
                $search = $input['search']; 	
             	$searchSql = " WHERE  title LIKE :search OR message LIKE :search  ";
             	$params = ["search"=>"%$search%"];
            }

            $sqlCount = "SELECT count(id) as total FROM notificationglobal".$searchSql;
            $stmtCount = $this->db->prepare($sqlCount);
			$stmtCount->execute($params);
			$resCount =  $stmtCount->fetch();
			$imgUrl = $baseurl.'/uploads/notifications/';    	
       		$sql = "select id,title,message,img,img as image, created FROM notificationglobal".$searchSql." ORDER BY id DESC ".$paging;  
			    $stmt = $this->db->prepare($sql);
			    $stmt->execute($params);
			    $res =  $stmt->fetchAll();
				$resData = [];
			    foreach ($res as $row ) {		

			    	if(!empty($row['img'])){
			    		$row['img'] = $imgUrl.$row['img']; 
			    	}
			    	$resData[] = \Security::removenull($row); 
			    }
			    
			   if(!empty($res)){  
                    $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg']   = 'List bank details.';	
					$resArr['data']  = ["total"=>$resCount['total'],"list"=>$resData];						
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
	} */

	public function getAppSettings($request, $response){ 
		global $settings;
        $code    	= $settings['settings']['code']['rescode'];
	 	$errcode 	= $settings['settings']['code']['errcode'];
	 	$loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$userid 	= 	$loginuser['id'];     
	    $resArr		= []; 
	    $input 		= $request->getParsedBody();
	    $check 		=  $this->security->validateRequired([],$input);
		if(isset($check['error'])) {
		   return $response->withJson($check);
		}
		$atype=(isset($input['atype']) && in_array($input['atype'],['pvtcontest']))?$input['atype']:'countConfig';

		switch ($atype) {
			case 'pvtcontest':
				$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Notification List.';	
				$resArr['data']  = ["pvtcontest"=>['winprize'=>["min"=>0,'max'=>500],'cnstsize'=>["min"=>2,"max"=>100],"adminchrg"=>11.8]];
				break;
			default:
				$resArr=$this->countConfig($userid);
				break;
		}
        return $response->withJson($resArr,$code);
	}

	public function countConfig($userid){

		global $settings;
        $code    	= $settings['settings']['code']['rescode'];
	 	$errcode 	= $settings['settings']['code']['errcode'];
		try{
			$res=[];
			$dir    = __DIR__ . '/../settings/playwebsetting';  
		   	$addset = @file_get_contents($dir);
		    if($addset != false) {
			    	$res = json_decode($addset, true);
			    }
		 	$collection = $this->mdb->notification;
			$matchdata['$or']=[['userid.'.$userid=>0],['$and'=>[['userid.'.$userid=>['$ne'=>1]]]]]; //['sendAll'=>1]
			$matchdata=['userid.'.$userid=>0]; //['sendAll'=>1]
			$notify_count=$collection->count($matchdata);

			$resArr['code']  = 0;
			$resArr['error'] = false;
			$resArr['msg']   = 'Notification List.';	
			$resArr['data']  = ["unread_count"=>$notify_count,"config"=>$res];
		}catch(\Exception $e){
			$resArr['code']  = 1;
			$resArr['error'] = true;
			$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
			$code = $errcode;
	    }

	    return $resArr;  
	}

	public function getNotification($request, $response){ 
            global $settings,$baseurl;            
			$code    = $settings['settings']['code']['rescode'];
			$errcode = $settings['settings']['code']['errcode'];			
			$loginuser 	= 	$request->getAttribute('decoded_token_data');
    		$userid 		= 	$loginuser['id'];  	
            $input = $request->getParsedBody();               
            $whereData  = $matchdata   = [];
            $puserid=1;
            $page = (int)((isset($input['page']) && $input['page']>0)?$input['page']:1);
            $rstSort=['created'=>(-1)];
            $lim  = intval((isset($input['limit']) && !empty($input['limit']))?$input['limit']:10);
           	$skiprecord=intval(($page>1)?(($page-1)*$lim):0);                     
         try{  
         		$collection = $this->mdb->notification;   
         	//FROM_UNIXTIME
            if(!empty($input['search']) && isset($input['search'])){
                $search = $input['search']; 	
             	$whereData = $whereData+["title"=>"/".$search."/"];
            }
			$imgUrl = $baseurl.'/uploads/notifications/';    	
			    	$puserid=['$cond'=>['if'=>['$eq'=>['$userid.'.$userid ,1] ],'then'=>1,'else'=>0]];
			    	//'id'=>['$toString'=>'$_id']
			    	//$imgurl= ['$cond'=>['if'=>['$eq'=>['$$img',null]],'then'=>'','else'=>['$concat'=>[$imgUrl,'$img']]]];
			    	$imgurl=['$ifNull'=>[['$concat'=>[$imgUrl,'$img']],'']];
			    	$matchdata['$or']=[['userid.'.$userid=>['$in'=>[0,1]]]]; //['sendAll'=>1]
			    	$countquery['$or']=[['userid.'.$userid=>0],['$and'=>[['userid.'.$userid=>['$ne'=>1]]]]]; //['sendAll'=>1]
			    	$projectData=["_id"=>1,"title"=>1,'created'=>1,'img'=>$imgurl,'message'=>1,'userid'=>$puserid,'sendAll'=>1];
			    	$res=$collection->aggregate([['$match'=>$matchdata],['$project'=>$projectData],['$sort'=>$rstSort],['$skip'=>$skiprecord],['$limit'=>$lim]])->toArray(); 
			   if(!empty($res)){    
                    $resArr['code']  = 0;
					$resArr['error'] = false;
					$resArr['msg']   = 'Notification List.';	
					$resArr['data']  = ["total"=>$collection->count($matchdata),"list"=>$res,'imgUrl'=> $imgUrl,'unread_count'=>$collection->count($countquery)];						
			     }else{ 
                    $resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg']   = 'Record not found.';	
			     }
			} 
		    catch(\Exception $e) 
		    {    
					$resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
					$code = $errcode;
		    }                 
            return $response->withJson($resArr,$code);	
	} 


	public function statusAndDeleteNotify($request, $response){ 
        
        global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
		$loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$userid 		= 	$loginuser['id'];  	         
        $input = $request->getParsedBody(); 

        if(isset($input['atype']) && $input['atype']=='deleteAll'){

        	try{
        	$whereData=["userid.".$userid=>['$in'=>[0,1]]];
        	$data=["userid.".$userid=>1];
        	$collection = $this->mdb->notification;
        	if($collection->count($whereData)>0){
        		$res=$collection->updateMany($whereData,['$unset'=>$data],['$multiple'=>true]);
        		//$res=$collection->delete(["userid"=>[]]);
        		$resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = "Successfully All Notification Deleted";	
		     }else{
                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg'] 	 = "Already Empty Notification";	
		     }
        	}
		    catch(\Exception $e)
		    {    
					$resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
					$code = $errcode;
		    } 

	        return $response->withJson($resArr,$code);
        }
		$check =  $this->security->validateRequired(['id','sendAll','atype'],$input);
	
	        if(isset($check['error'])) {
	            return $response->withJson($check);
	        } 
	        $id= new ObjectId($input['id']);
	        $atype=$input['atype']; 
            $notifyType=$input['sendAll'];
	        if(!in_array($notifyType,[0,1])){
	     		$resArr = $this->security->errorMessage("Invalid or empty field sendAll!");
				return $response->withJson($resArr,$errcode);
	     	}  

	     	if(!in_array($atype,['statusUpdate','delete'])){
	       		$resArr = $this->security->errorMessage("Invalid atype");
				return $response->withJson($resArr,$errcode);	
	     	} 
     try{ 
            $collection = $this->mdb->notification;
            $whereData=["_id"=>$id];
       		if(empty($notifyType) || $notifyType==0){
	    		$whereData=$whereData + ['userid.'.$userid=>['$in'=>[0,1]]];
			}
			$res=$collection->findOne($whereData);
			if(!empty($res)){

		    if($atype=='delete'){	    		
				    $userids=(array)$res['userid'];
           			if(count($userids)>0){
           				unset($userids[$userid]);
           				if(empty($userids) || count($userids)<=0){
           					$res=$collection->deleteOne(["_id"=>$id]);
           				}else{
	           				$data['userid']=(object)$userids;
		           			$res=$collection->updateOne($whereData,['$set'=>$data]); 
	           			}
           			}else{
           				$res=$collection->deleteOne(["_id"=>$id]);
           			}
		           	$msg = "Notification deleted successfully.";
           	}else{
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
	        
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] = $msg;	
		     }else{
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg'] 	 = "Invalid Notification";	
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
 

   // get Contest Team And Player
	public function getLeaderBoardPDF($request, $response,$args)
	{
    
	    global $settings,$baseurl;     
		$code       =  	$settings['settings']['code']['rescode'];
		$errcode    =  	$settings['settings']['code']['errcode']; 
	   /* $loginuser 	= 	$request->getAttribute('decoded_token_data');
		$uid 		= 	$loginuser['id']; */
	    $dataResArr = 	[];          	
	    $input["matchid"] 		= $args['matchid'];
	    $input["poolcontestid"] = $args['poolcontestid'];                
	   /* $input      = 	$request->getParsedBody(); */                        

	   /* $check = $this->security->validateRequired(['matchid','poolcontestid'],$input);	
	    if(isset($check['error'])) {
	       return $response->withJson($check);
	    }    */	
		
		$matchid  =  $input["matchid"];    	
		$poolcontestid  =  $input["poolcontestid"];    	
		

		$params = ["matchid"=>$matchid]; 
	    $stmt 	= $this->db->prepare("SELECT id,matchname,gameid FROM matches WHERE matchid=:matchid");  
	    $stmt->execute($params);
	    $match 	= $stmt->fetch();

	    if(empty($match)){
	    	$resArr = $this->security->errorMessage("Invalid match");
			return $response->withJson($resArr,$errcode);     	
	    }

	    $chk = $this->security->checkMatchFreaz($matchid);
	    if(!$chk || empty($match)){	    	
			$resArr = $this->security->errorMessage("Can not download now");
			return $response->withJson($resArr,$errcode);        	
	    }

	    
	    $matchname =  str_replace(' ', '', $match["matchname"]);
	    $matchname =  preg_replace('/[^A-Za-z0-9]/', '', $matchname);
	    $midl =  $match["id"];

	    $fname = $matchname.'-'.base64_encode($poolcontestid.$midl).".pdf";
		$dirpath = 'uploads/leaderboard/';
		$pathfile = $dirpath.$fname;

		if(!file_exists($pathfile)) {
	    	$this->generatePdf($matchid,$poolcontestid,$match,$pathfile);		        
	    }

	    if(file_exists($pathfile)) {
	    	header('Content-Description: File Transfer');
	        header('Content-Type: application/octet-stream');
	        header('Content-Disposition: attachment; filename="'.basename($pathfile).'"');
	        header('Expires: 0');
	        header('Cache-Control: must-revalidate');
	        header('Pragma: public');
	        header('Content-Length: ' . filesize($pathfile));
	        flush(); // Flush system output buffer
	        readfile($pathfile);
	        exit;	    
	    }else{
	    		$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = 'Record not found.';	
				return $response->withJson($resArr,$code);
	    }

	die;       
   }


   //get Match contest list front
	public function getPlayerPointsInfo($request, $response)
	{
        global $settings,$baseurl;     
		$code       =  $settings['settings']['code']['rescode'];
		$errcode    =  $settings['settings']['code']['errcode'];                                
        $input      =  $request->getParsedBody();
        $loginuser  =  $request->getAttribute('decoded_token_data');
        $uid 	    =  $loginuser['id'];
		$check      =  $this->security->validateRequired(['pid','seriesid'],$input);				    		                 
        if(isset($check['error'])){
            return $response->withJson($check);
        }

       	$pid  		= $input["pid"];   
       	$seriesid   = $input["seriesid"];
       	$matchtype  = (isset($input["matchtype"]))?$input["matchtype"]:'';

       	$res = [];
     	$resArr = [];  
     	$contestTtl = 0 ;        	                  
		try{     

			/*$player['pid'] = '8917';
			$seriesid = 1;
			$playerpoints = $this->mdb->playerpoints;
			$resPoints 	  = $playerpoints->aggregate([
			  [ '$match' => [ 'pid'=>'8917' ] ],
			  [ '$group' => [ '_id' => ['pid' => '$pid'], 'pts' => ['$sum' => '$totalpoints'] ] ]
			]);
			$resPoints1 = iterator_to_array($resPoints) ;
			print_r($resPoints1);
			die;*/

			$condition = ['pid'=>$pid,'seriesid'=>$seriesid];
			if(!empty($matchtype)){
				$condition = $condition + ['matchtype'=>$matchtype];
			}

			$playerpoints = $this->mdb->playerpoints;			
          	$resData = $playerpoints->find($condition,['projection' =>['pid' => 1,'seriesid'=>1,'matchId'=>1,'totalpoints' => 1,'_id'=>0,'totalplayer'=>1,'selectedplayer'=>1,'selectplyrper'=>1,'team1'=>1,'team2'=>1]]);
          	
          	$resData = iterator_to_array($resData);          

		   	if(!empty($resData)) {

	            $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Player points.';					
				$resArr['data']  = $resData;
							
		    }else{
	                $resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg']   = 'Record not found.';	
		    }
		}
	    catch(\Exception $e)
	    {    
				$resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
				$code = $errcode;
	    }      
   
   		return $response->withJson($resArr,$code);
			  
} 



   public function generatePdf($matchid,$poolcontestid,$match,$pathfile){

   		 $params = ["poolcontestid"=>$poolcontestid]; 
			    $stmt 	= $this->db->prepare("SELECT joinfee,totalwining,winners,maxteams FROM contestsmeta WHERE id=:poolcontestid ");  
			    $stmt->execute($params);
			    $pool 	= $stmt->fetch(); 	
		    	         
		    $params = ["matchid"=>$matchid,"poolcontestid"=>$poolcontestid]; 
		    $sql = "SELECT jc.uteamid,ut.teamname FROM joincontests jc INNER JOIN userteams ut ON jc.uteamid=ut.id WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid ";
		    $stmt 	= $this->db->prepare($sql);  
		    $stmt->execute($params);
		    $teams 	= $stmt->fetchAll();

		    if(empty($teams)){
               return false;
		    }

		    foreach ($teams as $team) {
		    	$sql2 = "SELECT utp.iscap,utp.isvcap,pm.pname FROM userteamplayers utp INNER JOIN playersmaster pm ON utp.pid=pm.pid WHERE userteamid=:userteamid ORDER BY utp.iscap DESC, utp.isvcap DESC ";
			    $stmt2   = $this->db->prepare($sql2);
			    $params2 = ["userteamid"=>$team['uteamid']];
			    $stmt2->execute($params2);
			    $resPlr	 = $stmt2->fetchAll(); 		
					$resPlr['teamname'] = $team['teamname'];
					$dataResArr[] = $resPlr;
		    }

		    if(empty($dataResArr)){ 
		    	return false;
		    }

		         $html = '<table style="border-collapse: collapse">
		         <thead>
			<tr style="background:#3568b2; color:#fff;">
			<th colspan="2" style="text-align:center; color:#fff;">'.APP_NAME.' &nbsp; &nbsp; <small> Fair Play</small></th>
			<th colspan="2" style="text-align:center; color:#fff;">'.$match["matchname"].'</th>
			<th colspan="2" style="text-align:center; color:#fff;">Contest: Win Rs. '.$pool['totalwining'].'</th>
			<th colspan="2" style="text-align:center; color:#fff;">Entry Fee Rs. '.$pool['joinfee'].'</th>
			<th colspan="2" style="text-align:center; color:#fff;">Winners: '.$pool['winners'].'</th>
			</tr>	
		      <tr>
		        <th style="border: 1px solid black">User (Team)</th>
		        <th style="border: 1px solid black">Player 1 (Captain)</th>
		        <th style="border: 1px solid black">Player 2 (Vice Captain)</th>
				<th style="border: 1px solid black">Player 3</th>
				<th style="border: 1px solid black">Player 4</th>
				<th style="border: 1px solid black">Player 5</th>
				<th style="border: 1px solid black">Player 6</th>
				<th style="border: 1px solid black">Player 7</th>
				<th style="border: 1px solid black">Player 8</th>
				<th style="border: 1px solid black">Player 9</th>
				<th style="border: 1px solid black">Player 10</th>
				<th style="border: 1px solid black">Player 11</th>
		      </tr>
		    </thead><tbody>';  

		        foreach($dataResArr as $row){
				    $html .=  '<tr>
				        	   <td style="border: 1px solid black"><strong>'.$row["teamname"].'</strong></td>	
				        	   <td style="border: 1px solid black">'.$row[0]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[1]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[2]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[3]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[4]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[5]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[6]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[7]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[8]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[9]["pname"].'</td>			        	   
				        	   <td style="border: 1px solid black">'.$row[10]["pname"].'</td>			        	   
				        	   </tr>';
		        }	

		        $html .= '</tbody></table>';
		        $mpdf = new \Mpdf\Mpdf();
				$mpdf->WriteHTML($html);
				
				$mpdf->Output($pathfile,'F');        //D->downloas,F->save 				
   }


    public function leaderBoardCount($request,$response){ 
   		global $settings;
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];
		$mongo  = $settings['settings']['mongo'];
		$loginuser 	= 	$request->getAttribute('decoded_token_data');
    	$uid 		= 	$loginuser['id'];
		$input = $request->getParsedBody(); 
		$check =  $this->security->validateRequired(["matchid","poolcontestid"],$input);

		if(isset($check['error'])) {
		  return $response->withJson($check);
		}
		$matchid    	= $input['matchid'];
		$poolid    		= $input['poolcontestid'];          
  	try{
          
        $params = ["matchid"=>$matchid,'mstatus'=>"li"]; 
	    $stmt 	= $this->db->prepare("SELECT matchname FROM matches WHERE matchid=:matchid AND mstatus=:mstatus");  
	    $stmt->execute($params);
	    $match 	= $stmt->fetch();

	    if(empty($match)){
	    	$resArr = $this->security->errorMessage("Invalid match");
			return $response->withJson($resArr,$errcode);     	
	    }
	    $stmt 	= $this->db->prepare("SELECT cm.id,cm.contestid,c.title FROM contestsmeta cm INNER JOIN contests c ON c.id=cm.contestid WHERE cm.id=".$poolid);  
	    $stmt->execute();
	    $pool 	= $stmt->fetch();

	    if(empty($pool)){
	    	$resArr = $this->security->errorMessage("Invalid pool");
			return $response->withJson($resArr,$errcode);     	
	    }

	    $stmt 	= $this->db->prepare("SELECT teamname FROM userprofile WHERE userid=".$uid); 
	    $stmt->execute();
	    $user 	= $stmt->fetch();
	    $matchname    	= $match['matchname'];
	    $contestname	= $pool['title'];
	    $contestid		= $pool['contestid'];
	    $teamname		= $user['teamname'];
        $leaderboardData=["userid"=>$uid,"teamname"=>$teamname,"matchid"=>$matchid,"contestid"=>$contestid,"poolid"=>$poolid,"teamname"=>$teamname,"matchname"=>$matchname,"contestname"=>$contestname,"dwncount"=>1,"created"=>time()];
          $collection = $this->mdb->leaderboard;
          $whereCond=['matchid'=>$matchid,"poolid"=>$poolid,"contestid"=>$contestid];
          $res = $collection->findOne($whereCond);
          if(!empty($res)){
          	$leaderboardData["dwncount"]=$res["dwncount"]+1;
           $collection->updateOne($whereCond,['$set'=>$leaderboardData]);       
          $resArr['code']   = 0;
          $resArr['error']  = false;
          $resArr['msg']    = 'Leaderboard Count Updated';                
        }else{
          $collection->insertOne($leaderboardData); 
          $resArr['code']   = 0;
          $resArr['error']  = false;
          $resArr['msg']    = 'Leaderboard Updated';                
        }
          
      }
      catch(\Exception $e)
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
