<?php

namespace Apps\Controllers;

use \Firebase\JWT\JWT;

class AdminmatchesController
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
  
  // get Matches day wise
  public function getmastertblmatchupcommingFunc($request,$response)
  {
      global $settings,$baseurl;
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['gameid'],$input);
        if(isset($check['error'])) {
          return $response->withJson($check);
        }
        
        $gameid     =  $input['gameid'];
        $searchSql  =  "";
        $paging     =  "";
        $currDt     =  strtotime('today');
        
        //$currDt     =  time();    
        //echo date('Y-m-d H:i:s',$currDt); die;
        
        $params = ["gameid"=>$gameid,"currdt"=>$currDt];

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
            $searchSql .= " AND (team1 LIKE :search OR team2 LIKE :search OR mtype LIKE :search) ";
            $paramsSrch = ["search"=>"%$search%"];

            $params = $paramsSrch + $params;
        }

        if(isset($input['date1']) && !empty($input['date1']) && isset($input['date1']) && !empty($input['date1']) ){
            $dt1 = strtotime($input['date1'].' '.FROMDTTIME); 
            $dt2 = strtotime($input['date2'].' '.TODTTIME);
            $searchSql .= " AND (UNIX_TIMESTAMP(dateTimeGMT) >= :date1 AND UNIX_TIMESTAMP(dateTimeGMT) <= :date2) ";
            $paramsDate = ["date1"=>$dt1,"date2"=>$dt2];                     
            $params = $paramsDate + $params;
        }
         
      $sqlCount = "SELECT count(id) as total FROM matchmaster WHERE gameid=:gameid AND (UNIX_TIMESTAMP(dateTimeGMT) >=:currdt)  ".$searchSql." ORDER BY UNIX_TIMESTAMP(dateTimeGMT)";
      $stmtCount = $this->db->prepare($sqlCount);
      $stmtCount->execute($params);
      $resCount =  $stmtCount->fetch();
   
      $sql = "SELECT id,unique_id,mdate,dateTimeGMT,UNIX_TIMESTAMP(dateTimeGMT) as dtunix,team1,team2,mtype,squad,toss_winner_team,matchStarted,isactive,seriesname FROM matchmaster WHERE gameid=:gameid AND (UNIX_TIMESTAMP(dateTimeGMT) >=:currdt) ".$searchSql." ORDER BY UNIX_TIMESTAMP(dateTimeGMT) ".$paging;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res =  $stmt->fetchAll();
        $resData = [];

        foreach ($res as $row ) {
          $resData[] = \Security::removenull($row);
        }

       if(!empty($res)){
              $resArr['code']  = 0;
              $resArr['error'] = false;
              $resArr['msg']   = 'List upcommig matches.';  
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
  }

  //Active to upcommig status update
  public function activeToUpcommingMatch($request,$response)
  {
     
     global $settings,$baseurl;     
    $code    = $settings['settings']['code']['rescode'];
    $errcode = $settings['settings']['code']['errcode'];    
    $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];               
    
    $input   = $request->getParsedBody();

    $check =  $this->security->validateRequired(['matchid','isactive'],$input);
    if(isset($check['error'])) {
      return $response->withJson($check);
    }
        
      $matchid      =  $input['matchid'];
      $isactive     =  $input['isactive'];
         
        if(!in_array($isactive,[ACTIVESTATUS,INACTIVESTATUS])){
            $resArr = $this->security->errorMessage("Status should be ".INACTIVESTATUS."/".ACTIVESTATUS.".");
            return $response->withJson($resArr,$errcode); 
        }

        $ckmid = $this->security->checkMidInMster($matchid);
        if(empty($ckmid))
        {
          $resArr = $this->security->errorMessage("Invalid matchid.");
          return $response->withJson($resArr,$errcode);
        } 

        $ckmtcreated = $this->security->isMatchAlreadyAdded($matchid);
        if($ckmtcreated)
        {
          $resArr = $this->security->errorMessage("This match already created for contest.Don't change status this match.");
          return $response->withJson($resArr,$errcode);
        }
           
       
    try{        

        $params  =  ["isactive"=>$isactive,"matchid"=>$matchid];
        $sql     =  "UPDATE matchmaster SET isactive=:isactive WHERE unique_id=:matchid";
        $stmt    =  $this->db->prepare($sql);

      if($stmt->execute($params))
      {
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] = 'Status updated successfully.';  
      }else{

            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg'] = 'Status not updated, There is some problem';  
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
    
    // Active maches
    public function getActiveMatches($request,$response)
    {
      global $settings,$baseurl;        
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];     

      $input = $request->getParsedBody();     
      $check =  $this->security->validateRequired(['gameid'],$input);
      
      if(isset($check['error'])) {
        return $response->withJson($check);
      }
          
      $gameid   =  $input['gameid'];
      $searchSql= ""; 
      $paging   = ""; 
      $currDt   = strtotime('today'); 

      $params  = ["gameid"=>$gameid,"currdt"=>$currDt,"isactive"=>ACTIVESTATUS];

      if(isset($input['page']) && !empty($input['page'])){
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
          $searchSql .= " AND (mm.team1 LIKE :search OR mm.team2 LIKE :search OR mm.mtype LIKE :search) ";
          $paramsSrch = ["search"=>"%$search%"];

          $params = $paramsSrch + $params;
        }

        if(isset($input['date1']) && !empty($input['date1']) && isset($input['date1']) && !empty($input['date1']) ){
          $dt1 = strtotime($input['date1'].' '.FROMDTTIME); 
          $dt2 = strtotime($input['date2'].' '.TODTTIME); 
          $searchSql .= " AND (UNIX_TIMESTAMP(mm.dateTimeGMT) >= :date1 AND UNIX_TIMESTAMP(mm.dateTimeGMT) <= :date2) ";
          $paramsDate = ["date1"=>$dt1,"date2"=>$dt2];                     

          $params = $paramsDate + $params;
        }
         
      $sqlCount = "SELECT count(mm.id) as total FROM matchmaster mm WHERE mm.gameid=:gameid AND (mm.isactive=:isactive AND UNIX_TIMESTAMP(mm.dateTimeGMT) >=:currdt)  ".$searchSql." ORDER BY UNIX_TIMESTAMP(mm.dateTimeGMT)";
      $stmtCount = $this->db->prepare($sqlCount);
      $stmtCount->execute($params);
      $resCount =  $stmtCount->fetch();
   
       $sql = "SELECT mm.id,mm.unique_id,mm.mdate,mm.dateTimeGMT,mm.team1,mm.team2,mm.mtype,mm.squad,mm.toss_winner_team,mm.matchStarted,mm.isactive , 
       CASE WHEN m.matchid IS NULL THEN '0' ELSE '1' END as iscreated 
    FROM matchmaster mm LEFT JOIN matches m ON mm.unique_id=m.matchid WHERE mm.gameid=:gameid AND (mm.isactive=:isactive AND UNIX_TIMESTAMP(mm.dateTimeGMT) >=:currdt) ".$searchSql." ORDER BY UNIX_TIMESTAMP(mm.dateTimeGMT) ".$paging;    

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res =  $stmt->fetchAll();       

       if(!empty($res)){  
              $resArr['code']  = 0;
              $resArr['error'] = false;
              $resArr['msg']   = 'List Active matches.';  
              $resArr['data']  = ["total"=>$resCount['total'],"list"=>$res];  
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


   // Active maches old
    public function getactiveMatchScore($request,$response)
    {
      global $settings,$baseurl;        
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];     

        $input = $request->getParsedBody();           
        $searchSql  = ""; 
        $paging     = ""; 
        

        $params  = ["mstatus"=>UPCOMING];

        if(isset($input['page']) && !empty($input['page'])){
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
          $searchSql .= " AND (m.team1 LIKE :search OR m.team2 LIKE :search OR m.mtype LIKE :search) ";
          $paramsSrch = ["search"=>"%$search%"];
          $params = $paramsSrch + $params;
        }

        if(isset($input['date1']) && !empty($input['date1']) && isset($input['date1']) && !empty($input['date1']) ){
      $dt1 = strtotime($input['date1']); 
      $dt2 = strtotime($input['date2']); 
          $searchSql .= " AND (m.mdate >= :date1 AND m.mdate <= :date2) ";
          $paramsDate = ["date1"=>$dt1,"date2"=>$dt2];                     
          $params = $paramsDate + $params;
        }
         
      $sqlCount = "SELECT count(m.id) as total FROM matches m WHERE mstatus !=:mstatus ".$searchSql;
      $stmtCount = $this->db->prepare($sqlCount);
      $stmtCount->execute($params);
      $resCount =  $stmtCount->fetch();
   
     $sql = "SELECT m.id,m.matchid,m.mdate,m.mdategmt,m.team1,m.team2,m.mtype FROM matches m WHERE mstatus !=:mstatus ".$searchSql." ORDER BY m.mdategmt DESC ".$paging;    

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res =  $stmt->fetchAll();       
       if(!empty($res)){  
              $resArr['code']  = 0;
              $resArr['error'] = false;
              $resArr['msg']   = 'List matches.';  
              $resArr['data']  = ["total"=>$resCount['total'],"list"=>$res];  
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

    // Active maches
    public function getLocalMatches($request,$response)
    {      
      global $settings,$baseurl;        
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];     

      $input    = $request->getParsedBody();           
      $gameid   =  $input["gameid"];      
      try{ 
        
      $sql = "SELECT matchid,matchname,team1,team2,mdate,mdategmt,mtype FROM matches WHERE mstatus !=:mstatus AND  gameid=:gameid AND matchid LIKE '".DOMESTIC."%'";    
      $stmt = $this->db->prepare($sql);
      $stmt->execute(["mstatus"=>DECLARED,"gameid"=>$gameid]);
      $res =  $stmt->fetchAll();
        if(!empty($res)){  
              $resArr['code']  = 0;
              $resArr['error'] = false;
              $resArr['msg']   = 'Local matches.';  
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

  // get Matche Team
  public function getLocalMatchteam($request,$response)
  {

    global $settings,$baseurl;     
    $code    = $settings['settings']['code']['rescode'];
    $errcode = $settings['settings']['code']['errcode']; 

    $input   = $request->getParsedBody();                         
    $dataResArr = [];                           
    $check  =  $this->security->validateRequired(['matchid'],$input);                          
          if(isset($check['error'])) {
              return $response->withJson($check);
          }
          $matchid  = $input["matchid"];   
          $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
          if(!$isMatchAlreadyAdded)
          {
              $resArr = $this->security->errorMessage("Invalid matchid");
              return $response->withJson($resArr,$errcode);
          }
        
        try{  

        $stmt2 = $this->db->prepare("SELECT DISTINCT(teamid),teamname FROM matchmeta WHERE matchid=:matchid");
        $stmt2->execute(["matchid"=>$matchid]);
        $res =  $stmt2->fetchAll();          

        $resData = [];

  foreach ($res as $row) {                 

      $tm = $row['teamid'];
      $tmname = $row['teamname'];

      $pimgUrl = $baseurl.'/uploads/players/';                        
      $sql2 = "SELECT mt.pid,mt.teamname,mt.playertype,CONCAT('".
      $pimgUrl."',mt.playerimg) as pimg,pm.fullname,pm.pname
        FROM matchmeta mt INNER JOIN playersmaster pm 
  ON pm.pid=mt.pid LEFT JOIN playertype pt ON pt.id=mt.playertype WHERE mt.matchid=:matchid AND teamid=:teamid ";    
      $stmt2 = $this->db->prepare($sql2);
      $params2 = ["matchid"=>$matchid,"teamid"=>$tm];
      $stmt2->execute($params2);
      $matchteam =  $stmt2->fetchAll(); 

      $resData[$tmname] =  $matchteam;
    } 
                  
       if(!empty($resData)) {                                                                    
        $resArr['code']  = 0;
        $resArr['error'] = false;
        $resArr['msg']   = 'Match team.'; 
        $resArr['data']  = $this->security->removenull($resData); 

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

  // get Master Matche 
  /*public function getmastertblmatchFunc($request,$response)
  {
        global $settings,$baseurl;     
    $code   = $settings['settings']['code']['rescode'];
    $errcode= $settings['settings']['code']['errcode']; 

        $input  = $request->getParsedBody();                         
       
       

        $params = [];
        $serachSql = "";
         
        if(!empty($input['search']) && isset($input['search'])){
            $search = $input['search'];   
          $serachSql = " WHERE team1 LIKE :search OR team2 LIKE :search OR mtype LIKE :search ";
          $params = ["search"=>"%$search%"];
        }

    try{     
                        
        $sql  = "SELECT id,unique_id,mdate,dateTimeGMT,team1,team2,mtype,squad,toss_winner_team,matchStarted,isactive FROM matchmaster ".$serachSql." ORDER BY id DESC";                
      $stmt   = $this->db->prepare($sql);
      $stmt->execute($params);
      $res  =  $stmt->fetchAll();
             
      if(!empty($res)) {                                                                    
            $resArr['code']  = 0;
      $resArr['error'] = false;
      $resArr['msg']   = 'Matches.';  
      $resArr['data']  = $this->security->removenull($res); 
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
        
 }*/

  public function addPlayer($input) 
  {                
      
      $resArr     =  []; 
      $gameid     =  $input['gameid']; 
      $fullname   =  $input['fullname']; 
      $pname      =  $input['pname']; 
      
      $country    =  $input['country'];
      $playertype =  $input['playertype'];
      //$iscap      =  intval($input['iscap']);
      //$isvcap     =  intval($input['isvcap']);
      $pimg       =  $input['pimgnames'];
      $atype      =  'international';
      $created    =  time();
      $ttype      =  '';
       
      /*if(!in_array($atype,['international','domestic'])){
           $resArr = $this->security->errorMessage("Invalid atype");
       return $response->withJson($resArr,$errcode);
      }*/ 

      if($this->security->isPlayerTypeIdExist($playertype)){
          //$resArr = $this->security->errorMessage("Invalid player type.");
          return $resArr = ['error'=>true,'msg'=>"Invalid player type"];
      }      
      /*$checkGame = $this->security->isGameTypeExistById($gameid); 
      if(empty($checkGame)){
            $resArr = $this->security->errorMessage("Invalid game id");
        return $response->withJson($resArr,$errcode);
      }*/
      //$pimgnameArr = explode(',', $pimgnames);     
         
   try{
          $this->db->beginTransaction();

          if($atype == 'domestic')
          {
            $stmt = $this->db->prepare("INSERT INTO playerslocal (gameid, fullname,pname,country,playertype,iscap,isvcap) VALUES (:gameid, :fullname,:pname,:country,:playertype,:iscap,:isvcap)");
            $params = ["gameid"=>$gameid,"fullname"=>$fullname,"pname"=>$pname,"country"=>$country,"playertype"=>$playertype,"iscap"=>$iscap,"isvcap"=>$isvcap];
            $stmt->execute($params);
            $pid = DOMESTIC.'-'.$this->db->lastInsertId();    
            $ttype = DOMESTIC;
          }else{
            if(!isset($input['pid']) || empty($input['pid']))
            {            
              return $resArr = ['error'=>true,'msg'=>"pid missing or empty"];
            }
            $pid    = $input['pid'];
            $ttype  = INTERNATIONAL;          
          }

          $stmt = $this->db->prepare("INSERT INTO playersmaster (pid,gameid, fullname,pname,country,created,playertype,iscap,isvcap,ttype) VALUES (:pid,:gameid, :fullname,:pname,:country,:created,:playertype,:iscap,:isvcap,:ttype)");
          $stmt->bindParam(':pid', $pid);
          $stmt->bindParam(':gameid', $gameid);
          $stmt->bindParam(':fullname', $fullname);
          $stmt->bindParam(':pname', $pname);
          $stmt->bindParam(':created', $created);
          $stmt->bindParam(':country', $country);
          $stmt->bindParam(':playertype', $playertype);
          $stmt->bindParam(':iscap', $iscap);
          $stmt->bindParam(':isvcap', $isvcap);
          $stmt->bindParam(':ttype', $ttype);
      
        if($stmt->execute())
        {
          $lastId = $this->db->lastInsertId();                   
            //foreach ($pimgnameArr as $pimg) { 
          $pimg = str_replace(" ", "", $pimg);              
          $player = $this->db->prepare("INSERT INTO playerimg (pid,playerimage) VALUES (:pid, :playerimage)");
          $player->bindParam(':pid', $pid);
          $player->bindParam(':playerimage',$pimg);
          $player->execute();
          //}            
          $this->db->commit();
          return $resArr = ['error'=>false,'msg'=>"Record added successfully"];        
        }
        else{
              return $resArr = ['error'=>false,'msg'=>"Record not inserted, There is some problem"];
            }
      }
      catch(\PDOException $e)
      {    
        $ms = \Security::pdoErrorMsg($e->getMessage());
        return $resArr = ['error'=>false,'msg'=>$ms];                 
      }                  
      
      return $resArr;
  }

	// Add match 
  public function addmatchFunc($request,$response)
  {
      global $settings;
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];

      $input = $request->getParsedBody();
      $check =  $this->security->validateRequired(['matchid','matchname','team1','team2','team1logo','team2logo','gametype','gameid','mtype','mstarted','mdate','mdategmt','seriesid','seriesname'],$input);   

          if(isset($check['error'])) {
              return $response->withJson($check);
          }

          $t1 = 0;
          $t2 = 0;
          $plrtmlmt = 11;
         
          $matchid     = $input["matchid"];
          $seriesid    = $input["seriesid"];
          $seriesname  = $input["seriesname"];
          $matchname   = $input["matchname"];
          $team1       = $input["team1"];
          $team2       = $input["team2"];
          $team1logo   = str_replace(" ", "", $input["team1logo"]);
          $team2logo   = str_replace(" ", "", $input["team2logo"]);
          $gametype    = $input["gametype"];
          $gameid      = $input["gameid"];
          $mtype       = $input["mtype"];
          $mstarted    = $input["mstarted"];
          $dt1         = new \DateTime($input["mdate"]);
          $mdate       = $dt1->getTimestamp();
          $dt2         = new \DateTime($input["mdategmt"]);
          $mdategmt    = $dt2->getTimestamp();
          $players     = $input["players"];   

          //$tmdiff      = \Security::getTimeBeforMatch();
          //$h           = $tmdiff['hour'];
          //$m           = $tmdiff['minutes'];

          //$mdategmt    = strtotime('-'.$h.' hour -'.$m.' minutes',$mdategmt);          

         // print_r($input); die;

        if(empty($players) || !is_array($players)){
            $resArr = $this->security->errorMessage("Please select players.");
            return $response->withJson($resArr,$errcode);                 
        } 
      
        $gsetting = $this->security->getglobalsetting();

        if(empty($gsetting)){
            $resArr = $this->security->errorMessage("Global settings empty.");
            return $response->withJson($resArr,$errcode);                   
        }      
        $totalpoints = $gsetting["totalpoints"];  

     /* if(!in_array($mtype,['Test','ODI','Twenty20','T20I'])){
          $resArr = $this->security->errorMessage("Invalid mtype.");
          return $response->withJson($resArr,$errcode); 
      }*/

        $isGameTypeExistByName = $this->security->isGameTypeExistByName($gametype);
        if($isGameTypeExistByName)
        {
          $resArr = $this->security->errorMessage("Invalid game type");
          return $response->withJson($resArr,$errcode);
        }           

        $isGameTypeExistById = $this->security->isGameTypeExistById($gameid);
        if(empty($isGameTypeExistById))
        {
          $resArr = $this->security->errorMessage("Invalid game type");
          return $response->withJson($resArr,$errcode);
        }     
  
        if(!in_array($mstarted,['0','1'])){
          $resArr = $this->security->errorMessage("Invalid mstarted value.");
          return $response->withJson($resArr,$errcode); 
        }                                    
            
        $checkMidInMster = $this->security->checkMidInMster($matchid);
        if(empty($checkMidInMster))
        {
          $resArr = $this->security->errorMessage("Invalid matchid.");
          return $response->withJson($resArr,$errcode);
        }

        $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
        if($isMatchAlreadyAdded)
        {
          $resArr = $this->security->errorMessage("match already added.");
          return $response->withJson($resArr,$errcode);
        }
            
          /*  $isTeamNameExist = $this->security->isTeamShortnameExist($team1);
            if(!$isTeamNameExist)
            {
              $resArr = $this->security->errorMessage("Invalid team1.");
              return $response->withJson($resArr,$errcode);
            }
            
            $isTeamNameExist = $this->security->isTeamShortnameExist($team2);
            if(!$isTeamNameExist)
            {
              $resArr = $this->security->errorMessage("Invalid team2.");
              return $response->withJson($resArr,$errcode);
            } */
            

          $plrT1 = [] ;
          $plrT2 = [] ;

          foreach ($players as $plr) 
          {    
            
            if(!in_array($plr['teamname'], [$team1,$team2])){                   
                $resArr = $this->security->errorMessage("Invalid team name .");
                return $response->withJson($resArr,$errcode); 
            }

            if($plr['teamname'] == $team1)
            {  
              $plrT1[] = $plr['pid']; 
              $t1      = $t1+1;
            }
            elseif($plr['teamname'] == $team2)
            {
              $plrT2[] = $plr['pid'];  
              $t2      = $t2+1;
            }

            if($this->security->isPlayerTypeIdExist($plr['ptype'])){
              $resArr = $this->security->errorMessage("Invalid player type.");
              return $response->withJson($resArr,$errcode);
            }
                                                            
            $isPlayeridExist = $this->security->isPlayeridExist($plr['pid']);
            if(!$isPlayeridExist)
            {
              $playerData = [
                              "pid"=>$plr['pid'],
                              "gameid"=>$gameid,
                              "fullname"=>$plr['fullname'],
                              "pname"=>$plr['pname'],
                              "country"=>$plr['country'],
                              "playertype"=>$plr['ptype'],
                              "pimgnames"=>$plr['playerimg']
                            ];
              $resAddplr = $this->addPlayer($playerData);                

              if($resAddplr['error'] == true){
                $resArr = $this->security->errorMessage($resAddplr['msg']);
                return $response->withJson($resArr,$errcode);      
              }
            }
          }

            if($t1 < $plrtmlmt || $t2 < $plrtmlmt){
              $resArr = $this->security->errorMessage("Choose at least minimum player in a team.");
              return $response->withJson($resArr,$errcode);  
            } 
            
            foreach ($plrT1 as $p) {            
                if(in_array($p,$plrT2)){
                  $resArr = $this->security->errorMessage("Should not be same player in both team in a match.");
                  return $response->withJson($resArr,$errcode); 
                }
            }
                                      
        try{

            $this->db->beginTransaction();
            $data = [
                       'matchid'=>$matchid,
                       'seriesid'=>$seriesid,
                       'seriesname'=>$seriesname,
                       'matchname'=>$matchname,
                       'team1'=>$team1,
                       'team2'=>$team2,
                       'team1logo'=>$team1logo,
                       'team2logo'=>$team2logo,
                       'gametype'=>$gametype,
                       'gameid'=>$gameid,
                       'totalpoints'=>$totalpoints,
                       'mtype'=>$mtype,
                       'mstarted'=>$mstarted,
                       'mdate'=>$mdate,
                       'mdategmt'=>$mdategmt
                ];          
                                     
            $stmt = $this->db->prepare("INSERT INTO   matches (matchid,matchname,team1,team2,team1logo,team2logo,gametype,gameid, totalpoints,mtype,mstarted,mdate,mdategmt,seriesid,seriesname) VALUES (:matchid,:matchname,:team1,:team2,:team1logo,:team2logo,:gametype,:gameid,:totalpoints,:mtype,:mstarted,:mdate,:mdategmt,:seriesid,:seriesname)");

            if($stmt->execute($data)){
              
                  $lastInsertId = $this->db->lastInsertId();                                          
                  if(!empty($players)){
                      foreach ($players as $player) 
                      {                                                                                     
                         $data2 = [
                             'matchid'=>$matchid,
                             'teamname'=>$player['teamname'],
                             'teamid'=>$player['teamid'],
                             'playerimg'=>$player['playerimg'],
                             'playertype'=>$player['ptype'],
                             'pid'=>$player['pid'],
                             'pts'=>$player['pts'],
                             'credit'=>$player['credit'],
                             'isplaying'=>$player['isplaying']
                            ]; 

                        $stmt2 = $this->db->prepare("INSERT INTO matchmeta (matchid,pid,teamname,teamid,playerimg,playertype,pts,credit,isplaying) VALUES (:matchid,:pid,:teamname,:teamid,:playerimg,:playertype,:pts,:credit,:isplaying)");      
                        $stmt2->execute($data2);
                        $stmt3 = $this->db->prepare("UPDATE playersmaster SET credit=:credit,playertype=:playertype WHERE pid=:pid");
                        $stmt3->execute(['credit'=>$player['credit'],'playertype'=>$player['ptype'],'pid'=>$player['pid']]);
                      }
                  }
              $this->db->commit();
              $resArr['code']  = 0;
              $resArr['error'] = false;
              $resArr['msg'] = 'Record added successfully.';  
            }else{
              $resArr['code']  = 1;
              $resArr['error'] = true;
              $resArr['msg'] = 'Record not added, There is some problem'; 
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
  }



  //Match player details 
  public function matchPlayerDetails($request,$response)
  {
      global $settings,$baseurl;
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];

      $input = $request->getParsedBody();
      $check =  $this->security->validateRequired(['matchid','gameid','seriesid'],$input);    

      if(isset($check['error'])) {
          return $response->withJson($check);
      }

      $matchid     = $input["matchid"];
      $players     = explode(",",$input["players"]);
      $gameid      = $input["gameid"];
      $seriesid    = $input["seriesid"];
      $matchtype   = (isset($input["matchtype"]))?$input["matchtype"]:'';


        //$matchtype = $this->security->getMatchTypeCricket($matchtype);

        $isGameTypeExistById = $this->security->isGameTypeExistById($gameid);
        if(empty($isGameTypeExistById))
        {
          $resArr = $this->security->errorMessage("Invalid game type");
          return $response->withJson($resArr,$errcode);
        }

        if(empty($players) && !is_array($players))
        {
          $resArr = $this->security->errorMessage("Players data missing");
          return $response->withJson($resArr,$errcode);
        }

        try
        {
            $dtArr = [];
            foreach ($players as $pid)
            {

              $pimgUrl  = $baseurl.'/uploads/players/';   
              $sql = "SELECT pm.pid,pm.fullname,pm.pname,pm.playertype,pm.credit,pt.name as ptype 
              FROM playersmaster pm 
              INNER JOIN playertype pt ON pm.playertype=pt.id WHERE pm.pid = :pid AND pm.gameid=:gameid" ; 

              $stmt    = $this->db->prepare($sql);
              $params  = ["pid"=>$pid,"gameid"=>$gameid];
              $stmt->execute($params);
              $player  =  $stmt->fetch();

              if(!empty($player)){

                $sql = "SELECT CONCAT('".$pimgUrl."',playerimage) as img,playerimage from playerimg where pid=:pid AND status=1 LIMIT 1" ; 
                $stmt   = $this->db->prepare($sql);
                $params = ["pid"=>$player['pid']];
                $stmt->execute($params);
                $imgRes   =  $stmt->fetch();  

                $pid       = (string)$player['pid'];
                $seriesid  = (string)$seriesid;                
                $condition = [ 'pid'=>$pid,'seriesid'=>$seriesid ];
                if(!empty($matchtype)){
                  $matchtype = (string)$matchtype;
                  $condition = $condition + ['matchtype'=>$matchtype];
                }
                $playerpoints = $this->mdb->playerpoints;                

                $resPoints = $playerpoints->aggregate([      
                  [ '$match' =>  $condition],
                  [ '$group' => [ '_id' => ['pid' => '$pid'], 'pts' => ['$sum' => '$totalpoints']  ] ],
                  [ '$limit' => 1 ]
                ]);

                $resPoints = iterator_to_array($resPoints);
                if($resPoints){
                  $player['pts'] = ($resPoints[0]['pts'])?$resPoints[0]['pts']:'0'; 
                }else{
                  $player['pts'] = '0';
                }
                
                $player['pimg']           = $imgRes['img'];
                $player['playerimage']    = $imgRes['playerimage'];  
                if(empty($imgRes['playerimage'])){
                  $player['pimg']         = $pimgUrl.$settings['settings']['path']['dummyplrimg'];
                  $player['playerimage']  = $settings['settings']['path']['dummyplrimg'];  
                }
                $dtArr[$pid] = $player;
              }else{
                $dtArr[$pid] =  [
                                  "pid"=> $pid,
                                  "fullname"=> "",
                                  "pname"=> "",
                                  "playertype"=> "",
                                  "credit"=> "0",
                                  "ptype"=> "",
                                  "pts"=> "0",
                                  "pimg"=> "",
                                  "playerimage"=> ""    
                                ];
              }
                    
            }
              if(!empty($dtArr)){
                    $resArr['code']  = 0;
                    $resArr['error'] = false;
                    $resArr['msg'] = 'player details';  
                    $resArr['data'] = $this->security->removenull($dtArr);  
              }else{
                    $resArr['code']  = 0;
                    $resArr['error'] = false;
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

  //EditMatch
  public function editmatchFunc($request,$response)
  {
    global $settings;
    $code    = $settings['settings']['code']['rescode'];
    $errcode = $settings['settings']['code']['errcode'];

      $input = $request->getParsedBody();
      $check =  $this->security->validateRequired(['matchid','matchname','team1','team2','team1logo','team2logo','gametype','mtype','seriesid'],$input);  

      if(isset($check['error'])) {
          return $response->withJson($check);
      }

        $t1 = 0;
        $t2 = 0;
        $plrtmlmt = 11;

        $matchid     = $input["matchid"];
        $seriesid    = $input["seriesid"];
        //$seriesname  = $input["seriesname"];
        $matchname   = $input["matchname"];
        $team1       = $input["team1"];  
        $team2       = $input["team2"];
        
        if(isset($input["team1logo"])){
         $team1logo   = str_replace(" ", "", $input["team1logo"]);        
        }
        if(isset($input["team1logo"])){
         $team2logo   = str_replace(" ", "", $input["team2logo"]);      
        }

        $gametype    =  $input["gametype"]; 
        $gameid      =  $input["gameid"];               
        $mtype       =  $input["mtype"];
        //$mstarted    =  $input["mstarted"];
        //$dt1     =  new \DateTime($input["mdate"]);
       //$mdate      =  $dt1->getTimestamp();
       //$dt2      =  new \DateTime($input["mdategmt"]);
       //$mdategmt   =  $dt2->getTimestamp();
        $players     =  $input["players"];  
           
       $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
       if(!$isMatchAlreadyAdded)
       {
        $resArr = $this->security->errorMessage("Invalid match id");
        return $response->withJson($resArr,$errcode);
       }    

        if(empty($players)){
          $resArr = $this->security->errorMessage("Please select players.");
          return $response->withJson($resArr,$errcode);                 
        } 

        $gsetting = $this->security->getglobalsetting();
        if(empty($gsetting)){
            $resArr = $this->security->errorMessage("Global settings empty.");
            return $response->withJson($resArr,$errcode);                   
        }
        $totalpoints = $gsetting["totalpoints"];  
        
        $isGameTypeExistByName = $this->security->isGameTypeExistByName($gametype);
        if($isGameTypeExistByName)
        {
          $resArr = $this->security->errorMessage("Invalid game type");
          return $response->withJson($resArr,$errcode);
        }

        $isGameTypeExistById = $this->security->isGameTypeExistById($gameid);
        if(empty($isGameTypeExistById))
        {
          $resArr = $this->security->errorMessage("Invalid game type");
          return $response->withJson($resArr,$errcode);
        }

                       
        foreach ($players as $plr)
        {   
          
            if($plr['teamname'] == $team1)
            {
               $t1 = $t1+1;
            }
            elseif($plr['teamname'] == $team2)
            {
              $t2 = $t2+1;
            }

            if(!in_array($plr['teamname'], [$team1,$team2])){
                $resArr = $this->security->errorMessage("Invalid team name for player.");
                return $response->withJson($resArr,$errcode); 
            }
            if($this->security->isPlayerTypeIdExist($plr['ptype']))
            {
              $resArr = $this->security->errorMessage("Invalid player type.");
              return $response->withJson($resArr,$errcode);
            }
            
            $isPlayeridExist = $this->security->isPlayeridExist($plr['pid']);
            if(!$isPlayeridExist)
            {              
              $resArr = $this->security->errorMessage("Invalid player id.");
              return $response->withJson($resArr,$errcode);
            }
        }

        if($t1 < $plrtmlmt || $t2 < $plrtmlmt){
          $resArr = $this->security->errorMessage("Choose at least minimum player in a team.");
          return $response->withJson($resArr,$errcode);  
        }
                                      
      try{ 

          $this->db->beginTransaction();
            $data = [
                       'matchid'=>$matchid,
                       'seriesid'=>$seriesid,
                       //'seriesname'=>$seriesname,
                       'matchname'=>$matchname,
                       'team1'=>$team1,
                       'team2'=>$team2,
                       'team1logo'=>$team1logo,
                       'team2logo'=>$team2logo,
                       'gametype'=>$gametype,
                       'gameid'=>$gameid,
                       'totalpoints'=>$totalpoints,                      
                       'mtype'=>$mtype
                       /*'mstarted'=>$mstarted,
                       'mdate'=>$mdate,
                       'mdategmt'=>$mdategmt*/
                ];          
         
          $stmt = $this->db->prepare("UPDATE matches SET matchname=:matchname,team1=:team1,team2=:team2,team1logo=:team1logo,team2logo=:team2logo,gametype=:gametype,gameid=:gameid,totalpoints=:totalpoints,mtype=:mtype,seriesid=:seriesid WHERE matchid=:matchid"); 

          if($stmt->execute($data))
          {
              $this->security->deleteMatchMeta($matchid);//Delete match meta

                if(!empty($players))
                {
                    foreach ($players as $player) 
                    {  
                      $data2 = [
                            'matchid'=>$matchid,
                            'teamname'=>$player['teamname'],
                            'teamid'=>$player['teamid'],
                            'playerimg'=>$player['playerimg'],
                            'playertype'=>$player['ptype'],
                            'pid'=>$player['pid'],
                            'pts'=>$player['pts'],
                            'credit'=>$player['credit'],
                            'isplaying'=>$player['isplaying']
                          ];                 
                      $stmt2 = $this->db->prepare("INSERT INTO matchmeta (matchid,pid,teamname,teamid,playerimg,playertype,pts,credit,isplaying) VALUES (:matchid,:pid,:teamname,:teamid,:playerimg,:playertype,:pts,:credit,:isplaying)");      
                      $stmt2->execute($data2);

                      $stmt3 = $this->db->prepare("UPDATE playersmaster SET credit=:credit WHERE pid=:pid");
                      $stmt3->execute(['credit'=>$player['credit'],'pid'=>$player['pid']]);                         
                    }
                }
                $this->db->commit();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Record updated successfully.';  

            }else{
              $resArr['code']  = 1;
              $resArr['error'] = true;
              $resArr['msg']   = 'Record not updated, There is some problem';

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


// Get Edit Match
	public function getmatcheditFunc($request,$response)
	{
        global $settings,$baseurl;                 
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];

        
        $input = $request->getParsedBody();
	      $check =  $this->security->validateRequired(['matchid'],$input);	
        if(isset($check['error'])) {
            return $response->withJson($check);
        }

       	$matchid  = $input["matchid"];

       	$isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
	      if(!$isMatchAlreadyAdded)
	      {
				  $resArr = $this->security->errorMessage("Invalid match id");
				  return $response->withJson($resArr,$errcode);
	      }      

        $dataResArr = [];          	                  
        try{
            $logoUrl  = $baseurl.'/uploads/teamlogo/';
            $sql = "SELECT matchid,matchname,team1,team2,CONCAT('".$logoUrl."',team1logo) as team1logourl,team1logo as team1logoname, CONCAT('".$logoUrl."',team2logo) as team2logourl,team2logo as team2logoname,gametype,gameid,seriesid,seriesname,totalpoints,mtype FROM matches WHERE matchid=:matchid" ; 
		    $stmt = $this->db->prepare($sql);
		    $stmt->execute(["matchid"=>$matchid]);
		    $matches =  $stmt->fetch();	
         
            $pimgUrl = $baseurl.'/uploads/players/';
            $sql2  = "SELECT mt.pid,mt.teamname,mt.teamid,mt.pts,mt.credit,CONCAT('".$pimgUrl."',mt.playerimg) as pimg,mt.playerimg,mt.playertype,mt.isplaying,pt.name as ptype,pm.pname FROM matchmeta mt LEFT JOIN playertype pt ON pt.id=mt.playertype LEFT JOIN playersmaster pm ON mt.pid=pm.pid WHERE mt.matchid=:matchid" ;
		    $stmt2 = $this->db->prepare($sql2);
		    $params2 = ["matchid"=>$matchid];
		    $stmt2->execute($params2);
		    $matchteam =  $stmt2->fetchAll();

		   if(!empty($matches)) {

		        $matches['players'] = $matchteam;                                                                       
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Matches List.';	
				$resArr['data']  = $this->security->removenull($matches);

		    }else{
                $resArr['code']  = 1;
				$resArr['error'] = true;
				$resArr['msg'] 	 = 'Record not found.';	
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

	// List Matches Created
	public function listmatchesFunc($request,$response)
	{
        global $settings,$baseurl;                 
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();   
             
            $params = ["mstatus"=>UPCOMING];
            $subSql = '';
		        if(isset($input['gameid'])){
              $gameid= $input['gameid'];
              $subSql = ' AND gameid=:gameid ';
              $params = $params+["gameid"=>$gameid];
            }
       	 
        $dataResArr = [];          	                  
        try{
            
            $logoUrl  = $baseurl.'/uploads/teamlogo/';                             
            $sql = "SELECT matchid,matchname,team1,team2,CONCAT('".$logoUrl."',team1logo) as team1logo,CONCAT('".$logoUrl."',team2logo) as team2logo,gametype,gameid,totalpoints,mdate,mdategmt FROM matches WHERE mstatus=:mstatus ".$subSql." ORDER BY id DESC" ; 
		    $stmt = $this->db->prepare($sql);
		    $stmt->execute($params);
		    $matches =  $stmt->fetchAll();		    
		    if(!empty($matches)) {                                                                    
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Matches List.';	
				$resArr['data']  = $this->security->removenull($matches);	

		    }else{
          $resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg'] 	 = 'Record not found.';	
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


	// get Matche Team
	public function getmatchteamFunc($request,$response)
	{

    global $settings,$baseurl;     
		$code    = $settings['settings']['code']['rescode'];
		$errcode = $settings['settings']['code']['errcode']; 

    $input 	 = $request->getParsedBody();                         
    $dataResArr = [];          	                
		$check  =  $this->security->validateRequired(['matchid'],$input);				    			         
	        if(isset($check['error'])) {
	            return $response->withJson($check);
	        }
          $matchid  = $input["matchid"];   
   	      $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
          if(!$isMatchAlreadyAdded)
          {
				      $resArr = $this->security->errorMessage("Invalid matchid");
				      return $response->withJson($resArr,$errcode);
          }
        
        try{

            $pimgUrl = $baseurl.'/uploads/players/';
            $sql2 = "SELECT mt.matchid,mt.pid,mt.teamname,mt.pts,mt.credit,pm.fullname,pm.pname,
        	 pm.playertype,pt.name as ptype,CONCAT('".$pimgUrl."',t.playerimage) as pimg FROM matchmeta mt INNER JOIN playersmaster pm 
ON pm.pid=mt.pid INNER JOIN playertype pt ON pt.id=pm.playertype LEFT JOIN team t ON mt.pid=t.pid AND t.teamid=1  WHERE mt.matchid=:matchid" ;
		    $stmt2 = $this->db->prepare($sql2);
		    $params2 = ["matchid"=>$matchid];
		    $stmt2->execute($params2);
		    $matchteam =  $stmt2->fetchAll(); 
            
		   if(!empty($matchteam)) {                                                                    
                $resArr['code']  = 0;
				$resArr['error'] = false;
				$resArr['msg']   = 'Match team.';	
				$resArr['data']  = $this->security->removenull($matchteam);	

		    }else{
                    $resArr['code']  = 1;
					$resArr['error'] = true;
					$resArr['msg'] 	 = 'Record not found.';	
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


    // Completed maches
    public function getCompletedMatches($request,$response)
    {
      
      global $settings,$baseurl;        
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];     

        $input = $request->getParsedBody();           
        $searchSql  = ""; 
        $paging   = ""; 
        //$currDt   = strtotime('today');   

        $params  = ["mstatus"=>COMPLETED];
        if(isset($input['page']) && !empty($input['page'])){
            $limit  = $input['limit']; //2
            $page   = $input['page'];  //0   LIMIT $page,$offset
          if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          }
          $offset = ($page-1)*$limit; 
          $paging = " limit ".$offset.",".$limit;
        }
       
     try{  

        if(!empty($input['search']) && isset($input['search'])){
            $search = $input['search'];             
          $searchSql .= " AND (m.team1 LIKE :search OR m.team2 LIKE :search OR m.mtype LIKE :search) ";
          $paramsSrch = ["search"=>"%$search%"];

          $params = $paramsSrch + $params;
        }

    if(isset($input['date1']) && !empty($input['date1']) && isset($input['date1']) && !empty($input['date1']) ){
        $dt1 = strtotime($input['date1']); 
        $dt2 = strtotime($input['date2']); 
          $searchSql .= " AND (UNIX_TIMESTAMP(m.mdate) >= :date1 AND UNIX_TIMESTAMP(m.mdate) <= :date2) ";
          $paramsDate = ["date1"=>$dt1,"date2"=>$dt2];                     
          $params = $paramsDate + $params;
      }
         
      $sqlCount = "SELECT count(m.id) as total FROM matches m  WHERE m.mstatus=:mstatus ".$searchSql." ORDER BY UNIX_TIMESTAMP(m.mdategmt) DESC ";
      $stmtCount = $this->db->prepare($sqlCount);
      $stmtCount->execute($params);
      $resCount =  $stmtCount->fetch();
   
      $sql = "SELECT m.matchid,m.matchname,m.team1,m.team2,m.gameid,
      m.mtype,m.mdate,m.mdategmt,m.mstatus FROM matches m  WHERE m.mstatus=:mstatus ".$searchSql." ORDER BY UNIX_TIMESTAMP(m.mdategmt) DESC ".$paging;    
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res =  $stmt->fetchAll();
       
       if(!empty($res)){  
              $resArr['code']  = 0;
              $resArr['error'] = false;
              $resArr['msg']   = 'List Active matches.';  
              $resArr['data']  = ["total"=>$resCount['total'],"list"=>$res];  
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

  //---Add Team---
  public function addLocalMatch($request,$response) 
  {
          global $settings;
          $code    = $settings['settings']['code']['rescode'];
          $errcode = $settings['settings']['code']['errcode'];

          $input = $request->getParsedBody();
          $check =  $this->security->validateRequired(['gameid','mdate','dateTimeGMT','team1','team2','mtype'],$input);
          if(isset($check['error'])) {
              return $response->withJson($check);
          }
          
          $gameid = $input["gameid"];  
          $mdate  = $input["mdate"];  
          $dateTimeGMT = $input["dateTimeGMT"];  
          $team1  = $input["team1"];  
          $team2  = $input["team2"];  
          $mtype  = $input["mtype"]; 

          $dt = strtotime(date('Y-m-d' ,time()));
          //$dt = strtotime("+1 day", time());

          if(strtotime($mdate) < $dt || strtotime($dateTimeGMT) < $dt){
              $resArr = $this->security->errorMessage("Invalid match date");
              return $response->withJson($resArr,$errcode);                  
          }

          if(!in_array($mtype,['Test','ODI','Twenty20'])){
              $resArr = $this->security->errorMessage("Invalid mtype.");
              return $response->withJson($resArr,$errcode);                  
          }

          $checkGame = $this->security->isGameTypeExistById($gameid); 
          if(empty($checkGame)){
                $resArr = $this->security->errorMessage("Invalid game id");
            return $response->withJson($resArr,$errcode);
          }
        
    try{
          $this->db->beginTransaction();
          $data = [
                    'mdate'=>$mdate,
                    'dateTimeGMT'=>$dateTimeGMT,
                    'team1'=>$team1,
                    'team2'=>$team2,
                    'mtype'=>$mtype,
                    'gameid'=>$gameid
                  ]; 

        $stmt = $this->db->prepare("INSERT INTO matchlocal (mdate,dateTimeGMT,team1,team2,mtype,gameid) VALUES (:mdate,:dateTimeGMT,:team1,:team2,:mtype,:gameid)");

        if($stmt->execute($data)){
                
             $lastId = DOMESTIC.'-'.$this->db->lastInsertId(); 
            $data   = [
                    'matchid'=>$lastId,
                    'mdate'=>$mdate,
                    'dateTimeGMT'=>$dateTimeGMT,
                    'team1'=>$team1,
                    'team2'=>$team2,
                    'mtype'=>$mtype,
                    'gameid'=>$gameid
                  ]; 
            $stmt = $this->db->prepare("INSERT INTO matchmaster (unique_id,mdate,dateTimeGMT,team1,team2,mtype,gameid) VALUES (:matchid,:mdate,:dateTimeGMT,:team1,:team2,:mtype,:gameid)");

            $stmt->execute($data);

            $this->db->commit();
            $resArr['code']   = 0;
            $resArr['error']  = false;
            $resArr['msg']    = 'Record added successfully.';  
         }else{
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record not added, There is some problem'; 
         }
      }
      catch(\PDOException $e)
      {    
        $this->db->rollBack();
        $resArr['code']  = 1;
        $resArr['error'] = true;
        $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
        $code = $errcode;
      }             
       return $response->withJson($resArr,$code);        
    }

  //--- Add Score ---
  public function addScore($request,$response) 
  {
          global $settings;
          $code    = $settings['settings']['code']['rescode'];
          $errcode = $settings['settings']['code']['errcode'];
          $mongo  = $settings['settings']['mongo'];

          $input = $request->getParsedBody();

          $check =  $this->security->validateRequired(["matchid",'type'],$input);
          if(isset($check['error'])) {
              return $response->withJson($check);
          }

          $matchid  = $input['matchid'];
          $match    = $input['match']; 
          $type     = $input['type']; 
          $ttype    = 'domestic'; 
          
          if( !isset($match) || empty($match)){
              $resArr = $this->security->errorMessage("match score empty or missing");
              return $response->withJson($resArr,$errcode);
          }
  try{
          //$collection = $this->mdb->completematches;
          $collection = $this->mdb->dmcompletematches;

          $res = $collection->findOne(['matchid'=>$matchid]);

          if(!empty($res)){
            $collection->updateOne(['matchid'=>$matchid],['$set'=>["type"=>$type,"ttype"=>$ttype,"match"=>$match,"gameid"=>1,"inningsdetail"=>$match['inningsdetail'],"matchstarted"=>$match['matchstarted'],"status_overview"=>$match['status_overview'],"status"=>$match['status']]]);
          }else{            
            $data = ['matchid'=>$matchid,"type"=>$type,"ttype"=>$ttype,'match'=>$match,"gameid"=>1,"inningsdetail"=>$match['inningsdetail'],"matchstarted"=>$match['matchstarted'],"status_overview"=>$match['status_overview'],"status"=>$match['status']];
            $collection->insertOne($data);
          }
          $res = $collection->findOne(['matchid'=>$matchid]);
                 
        if(!empty($res)){          
          $resArr['code']   = 0;
          $resArr['error']  = false;
          $resArr['msg']    = 'Record updated.';                
          $resArr['data']   = ['matchid'=>$res['matchid'],'match'=>$res['match']];
        }else{
          $resArr['code']   = 1;
          $resArr['error']  = true;
          $resArr['msg']    = 'Record not found.';                
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

  //--- Get Score ---
  public function getScore($request,$response) 
  {
          global $settings;
          $code    = $settings['settings']['code']['rescode'];
          $errcode = $settings['settings']['code']['errcode'];
          $mongo  = $settings['settings']['mongo'];
          $input = $request->getParsedBody();
          $check =  $this->security->validateRequired(["matchid"],$input);
          if(isset($check['error'])) {
              return $response->withJson($check);
          }
          $matchid  = $input['matchid'];
  try{

          //$collection = $this->mdb->completematches;
          $collection = $this->mdb->dmcompletematches;
          $res = $collection->findOne(['matchid'=>$matchid]);
         
        if(!empty($res)){          
          $resArr['code']   = 0;
          $resArr['error']  = false;
          $resArr['msg']    = 'Srore.';                
          $resArr['data']   = ['matchid'=>$res['matchid'],'match'=>$res['match']];
        }else{
          $resArr['code']   = 1;
          $resArr['error']  = true;
          $resArr['msg']    = 'Record not found.';                
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

  public function matchTimeUpldate($request, $response)
  { 
    global $settings;
    $code    = $settings['settings']['code']['rescode'];
    $errcode = $settings['settings']['code']['errcode'];
    $resArr  = [];
    
    $input = $request->getParsedBody();
    $check =  $this->security->validateRequired(['matchid','mtime'],$input);

        if(isset($check['error'])) {
            return $response->withJson($check);
        }           
          
          $matchid   = $input['matchid'];   
          $mtime     = strtotime($input['mtime']);   
          $ctime     = time();
          
          if($ctime>$mtime){
              $resArr = $this->security->errorMessage("Invalid time");
              return $response->withJson($resArr,$errcode);
          }

          $sql   = "SELECT matchid FROM matches WHERE matchid=:matchid AND mstatus=:mstatus ";
          $stmt   = $this->db->prepare($sql);
          $stmt->execute(['matchid'=>$matchid,'mstatus'=>UPCOMING]);
          $checkMatch   =  $stmt->fetch();

          if(empty($checkMatch)){
              $resArr = $this->security->errorMessage("Invalid matchid.");
              return $response->withJson($resArr,$errcode);
          }

    try{          
          $sql    = "UPDATE matches SET mdategmt=:mtime where matchid=:matchid ";
          $stmt   = $this->db->prepare($sql);
          
          if($stmt->execute(['matchid'=>$matchid,'mtime'=>$mtime]))
          {
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = "Match Time updated";
          }else{
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = "Time not updated";
          }        
      }catch(\PDOException $e)
      {    
          $resArr['code']  = 1;
          $resArr['error'] = true;
          $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
          $code = $errcode;
      }

      return $response->withJson($resArr,$code);                 
  }


  public function listmatchesAdminFunc($request, $response)
  { 
        global $settings,$baseurl;                 

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $limit   = $settings['settings']['code']['pageLimitFront'];
        $input   = $request->getParsedBody();    
        
      $check =  $this->security->validateRequired(['gameid','atype'],$input);          
      if(isset($check['error'])) {
            return $response->withJson($check);
        }

        $gameid   = $input['gameid'];
        $atype    = $input['atype'];  
      
        /*$page     = intval($input['page']);  
        if(empty($page)){ $page = 1; }
        $offset = ($page-1)*$limit;                                    
        */

        $dataResArr  = [];   
                       
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
        $sqlStr = " (m.mstatus=:mstatus OR m.mstatus=:dc OR m.mstatus=:cl ) ";
        $sqlOrder = " m.mdategmt DESC";
        $params = $params + ["mstatus"=>$mstatus,"dc"=>DECLARED,"cl"=>CANCELED];    
      }
                                
        try{ 

            $logoUrl = $baseurl.'/uploads/teamlogo/';   
            $sql = "SELECT m.matchid,m.matchname,m.team1,m.team2,CONCAT('".$logoUrl."',m.team1logo) as team1logo,CONCAT('".$logoUrl."',m.team2logo) as team2logo ,m.gametype,m.gameid,m.totalpoints,m.mtype,m.mdate,m.mdategmt,mstatus FROM matches m INNER JOIN matchcontest mc ON m.matchid=mc.matchid WHERE  m.status=:status AND m.gameid=:gameid AND ".$sqlStr." AND mc.status=:status GROUP BY mc.matchid ORDER BY".$sqlOrder; 
          
               //limit ".$offset.",".$limit ;
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute($params);
        $matches = $stmt->fetchAll();

       if(!empty($matches)){                                                                   
        
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Matches List.'; 
            $resArr['data']  = $this->security->removenull($matches);     
        
        }else{
            $resArr['code']   = 1;
            $resArr['error']  = true;
            $resArr['msg']    = 'Record not found.';  
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


  public function addSeries($request, $response){
    global $settings;                 
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input   = $request->getParsedBody();    
      $check =  $this->security->validateRequired(['sname','startdate','enddate'],$input);          
      if(isset($check['error'])) {
            return $response->withJson($check);
        }
        $sname     = $input['sname'];
        $startdate = $input['startdate'];  
        $enddate   = $input['enddate'];

        $dataResArr  = [];                         
        try{        
        $data   = [
                    'sname'=>$sname,
                    'startdate'=>strtotime($startdate),
                    'enddate'=>strtotime($enddate),
                  ]; 
            $stmt = $this->db->prepare("INSERT INTO series (sname,startdate,enddate) VALUES (:sname,:startdate,:enddate)");

       if(!empty($stmt->execute($data))){
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Series Add successfully.';          
        }else{
            $resArr['code']   = 1;
            $resArr['error']  = true;
            $resArr['msg']    = 'Series not added.';  
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

public function editSeries($request, $response){


    global $settings;                 

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input   = $request->getParsedBody();    
        
      $check =  $this->security->validateRequired(['id','sname','startdate','enddate'],$input);          
      if(isset($check['error'])) {
            return $response->withJson($check);
        }
        $sname     = $input['sname'];
        $startdate = strtotime($input['startdate']);  
        $enddate   = strtotime($input['enddate']);
        $id   = $input['id'];
        $dataResArr  = [];                         
        try{        
        $data   = [
                    'sname'=>$sname,
                    'startdate'=>$startdate,
                    'enddate'=>$enddate,
                    'id'=>$id,
                  ]; 
$sql    = "UPDATE series SET sname=:sname,startdate=:startdate,enddate=:enddate where id=:id ";
          $stmt   = $this->db->prepare($sql);
       if(!empty($stmt->execute($data))){                                                                   
        
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Series updated successfully.'; 
            //$resArr['data']  = $this->security->removenull($matches);     
        
        }else{
            $resArr['code']   = 1;
            $resArr['error']  = true;
            $resArr['msg']    = 'Series not updated.';  
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

 public function deleteSeries($request, $response){


    global $settings;                 

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input   = $request->getParsedBody();    
        
      $check =  $this->security->validateRequired(['id'],$input);          
      if(isset($check['error'])) {
            return $response->withJson($check);
        }
        $id   = $input['id'];
        $dataResArr  = [];                         
        try{        
        $data   = [
                    'deleted'=>time(),
                    'id'=>$id,
                  ]; 
$sql    = "UPDATE series SET deleted=:deleted where id=:id ";
          $stmt   = $this->db->prepare($sql);
       if(!empty($stmt->execute($data))){                                                                   
        
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Series deleted successfully.'; 
            //$resArr['data']  = $this->security->removenull($matches);     
        
        }else{
            $resArr['code']   = 1;
            $resArr['error']  = true;
            $resArr['msg']    = 'Series not deleted.';  
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

 public function getSeries($request, $response){
    global $settings;                 

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();           
        $searchSql  = ""; $params=[];
        $paging = $where_cond= ""; 

        //$where_cond =" where deleted=";
        if(isset($input['id']) && !empty($input['id'])){
          $params['id']=$input['id'];
          $where_cond.=" where id=:id ";
        }

        if(isset($input['page']) && !empty($input['page'])){
            $limit  = $input['limit']; //2
            $page   = $input['page'];  //0   LIMIT $page,$offset
          if(empty($limit)){
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          }
          $offset = ($page-1)*$limit; 
          $paging = " limit ".$offset.",".$limit;
        }
        $dataResArr  = [];                                   
        try{ 
            $sql = "SELECT * FROM series ".$where_cond.$paging; 
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $series = $stmt->fetchAll();

       if(!empty($series)){                                                                   
        
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Series List.'; 
            $resArr['data']  = $this->security->removenull($series);     
        
        }else{
            $resArr['code']   = 1;
            $resArr['error']  = true;
            $resArr['msg']    = 'Record not found.';  
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



	
}
?>
