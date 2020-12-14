<?php

namespace Apps\Controllers;

use \Firebase\JWT\JWT;
use MongoDB\BSON\ObjectId;

class AdminController
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function __get($property)
    {
        if ($this->container->{$property}) {
            return $this->container->{$property};
        }
    }


    /* Add Player Api */
    public function funcAddplayers($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['gameid','fullname','pname','country','playertype','iscap','isvcap','pimgnames','atype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $pid        =  "";
        $gameid     =  $input['gameid'];
        $fullname   =  $input['fullname'];
        $pname      =  $input['pname'];
        $teamname   =  (isset($input['teamname']))?$input['teamname']:"";
        $country    =  $input['country'];
        $playertype =  $input['playertype'];
        $iscap      =  intval($input['iscap']);
        $isvcap     =  intval($input['isvcap']);
        $pimgnames  =  $input['pimgnames'];
        $atype  	 =  $input['atype'];
        $created    =  time();
        $ttype      =  '';
         
        if (!in_array($atype, ['international','domestic'])) {
            $resArr = $this->security->errorMessage("Invalid atype");
            return $response->withJson($resArr, $errcode);
        }

        if ($isvcap==1 && $iscap==1) {
            $resArr = $this->security->errorMessage("should be choose one from cap or vcap");
            return $response->withJson($resArr, $settings->code->errcode);
        }
        if (!in_array($isvcap, ['0','1'])) {
            $resArr = $this->security->errorMessage("isvcap should be 0 or 1");
            return $response->withJson($resArr, $errcode);
        }
        if (!in_array($iscap, ['0','1'])) {
            $resArr = $this->security->errorMessage("iscap should be 0 or 1");
            return $response->withJson($resArr, $errcode);
        }

        if ($this->security->isPlayerTypeIdExist($playertype)) {
            $resArr = $this->security->errorMessage("Invalid player type.");
            return $response->withJson($resArr, $errcode);
        }
        $checkGame = $this->security->isGameTypeExistById($gameid);
        if (empty($checkGame)) {
            $resArr = $this->security->errorMessage("Invalid game id");
            return $response->withJson($resArr, $errcode);
        }
        $pimgnameArr = explode(',', $pimgnames);
           
        try {
            $this->db->beginTransaction();

            if ($atype == 'domestic') {
                $stmt = $this->db->prepare("INSERT INTO playerslocal (gameid, fullname,pname,country,playertype,iscap,isvcap) VALUES (:gameid, :fullname,:pname,:country,:playertype,:iscap,:isvcap)");
                $params = ["gameid"=>$gameid,"fullname"=>$fullname,"pname"=>$pname,"country"=>$country,"playertype"=>$playertype,"iscap"=>$iscap,"isvcap"=>$isvcap];
                $stmt->execute($params);
                $pid = DOMESTIC.'-'.$this->db->lastInsertId();
                $ttype = DOMESTIC;
            } else {
                if (!isset($input['pid']) || empty($input['pid'])) {
                    $resArr = $this->security->errorMessage("pid missing or empty");
                    return $response->withJson($resArr, $errcode);
                }
                $pid = $input['pid'];
                $ttype = INTERNATIONAL;
                if ($this->security->isPlayeridExist($pid)) {
                    $resArr = $this->security->errorMessage("player id already exists");
                    return $response->withJson($resArr, $errcode);
                }
            }

            $stmt = $this->db->prepare("INSERT INTO playersmaster (pid,gameid, fullname,pname,country,created,playertype,iscap,isvcap,ttype,teamname) VALUES (:pid,:gameid, :fullname,:pname,:country,:created,:playertype,:iscap,:isvcap,:ttype,:teamname)");
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
            $stmt->bindParam(':teamname', $teamname);
        
            if ($stmt->execute()) {
                $lastId = $this->db->lastInsertId();
              
                foreach ($pimgnameArr as $pimg) {
                    $pimg = str_replace(" ", "", $pimg);
                    $player = $this->db->prepare("INSERT INTO playerimg (pid,playerimage) VALUES (:pid, :playerimage)");
                    $player->bindParam(':pid', $pid);
                    $player->bindParam(':playerimage', $pimg);
                    if (!$player->execute()) {
                        $this->security->deleteRecord('playersmaster', $lastId, $pid);  // delete last id
                        $resArr = $this->security->errorMessage("invalid image format.");
                        return $response->withJson($resArr, $errcode);
                    }
                }
                $this->db->commit();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record added successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not inserted, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
            $this->db->rollBack();
        }
        return $response->withJson($resArr, $code);
    }

    /* Edit Player */
    public function funcEditPlayers($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['pid','country','playertype','iscap','isvcap','atype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

      
        $params     =  '';
        $pid        =  $input['pid'];
        $country    =  $input['country'];
        $playertype =  $input['playertype'];
        $iscap      =  intval($input['iscap']);
        $isvcap     =  intval($input['isvcap']);
        $atype  	=  $input['atype'];

        $ttype      =  '';
         
        if (!in_array($atype, ['international','domestic'])) {
            $resArr = $this->security->errorMessage("Invalid atype");
            return $response->withJson($resArr, $errcode);
        }

        if ($isvcap==1 && $iscap==1) {
            $resArr = $this->security->errorMessage("should be choose one from cap or vcap");
            return $response->withJson($resArr, $settings->code->errcode);
        }
        if (!in_array($isvcap, ['0','1'])) {
            $resArr = $this->security->errorMessage("isvcap should be 0 or 1");
            return $response->withJson($resArr, $errcode);
        }
        if (!in_array($iscap, ['0','1'])) {
            $resArr = $this->security->errorMessage("iscap should be 0 or 1");
            return $response->withJson($resArr, $errcode);
        }

        if ($this->security->isPlayerTypeIdExist($playertype)) {
            $resArr = $this->security->errorMessage("Invalid player type.");
            return $response->withJson($resArr, $errcode);
        }

        if (!$this->security->isPlayeridExist($pid)) {
            $resArr = $this->security->errorMessage("player id not exists");
            return $response->withJson($resArr, $errcode);
        }
      
       
           
        try {
            $this->db->beginTransaction();

            if ($atype == 'domestic') {
                $pname    =  $input['pname'];
                $fullname =  $input['fullname'];
                /*	 	    	$stmt = $this->db->prepare("UPDATE playerslocal SET  fullname=:fullname,pname=:pname,country=:country,playertype=:playertype,iscap=:iscap,isvcap=:isvcap WHERE pid=:pid");*/
                $ttype = DOMESTIC;
                $stmt = $this->db->prepare("UPDATE playersmaster SET  fullname=:fullname,pname=:pname,country=:country,playertype=:playertype,iscap=:iscap,isvcap=:isvcap WHERE pid=:pid AND ttype=:ttype");

                $params = ["pid"=>$pid,"fullname"=>$fullname,"pname"=>$pname,"country"=>$country,"playertype"=>$playertype,"iscap"=>$iscap,"isvcap"=>$isvcap,"ttype"=>$ttype];
            } else {
                $ttype = INTERNATIONAL;
                $stmt = $this->db->prepare("UPDATE playersmaster SET  country=:country,playertype=:playertype,iscap=:iscap,isvcap=:isvcap WHERE pid=:pid AND ttype=:ttype");

                $params = ["pid"=>$pid,"country"=>$country,"playertype"=>$playertype,"iscap"=>$iscap,"isvcap"=>$isvcap,"ttype"=>$ttype];
            }

            if ($stmt->execute($params)) {
                if (!empty($input['pimgnames'])) {
                    $pimgnames = $input['pimgnames'];
                    $pimgnameArr = explode(',', $pimgnames);
                    foreach ($pimgnameArr as $pimg) {
                        $pimg = str_replace(" ", "", $pimg);
                        $player = $this->db->prepare("UPDATE playerimg SET playerimage=:playerimage WHERE pid=:pid LIMIT 1");
                        $player->bindParam(':pid', $pid);
                        $player->bindParam(':playerimage', $pimg);
                        $player->execute();
                    }
                }

             
                $this->db->commit();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record Updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not updated, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
            $this->db->rollBack();
        }
        return $response->withJson($resArr, $code);
    }
    

    //Delete player
    public function funcDeletePlayers($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
         
        if (!is_array($input['pids']) && empty($input['pids'])) {
            $resArr = $this->security->errorMessage("Invalid player id");
            return $response->withJson($resArr, $errcode);
        }
        $pids   =  $input['pids'];
                                   
        try {
            $fl = 0;
            foreach ($pids as $pid) {
                if (!$this->security->isPlayeridExist($pid)) {
                    $resArr = $this->security->errorMessage("player id not exists");
                    return $response->withJson($resArr, $errcode);
                }

                if ($this->security->playerDelCheck($pid)) {
                    $resArr = $this->security->errorMessage("This player is added in matches, You can't delete this player");
                    return $response->withJson($resArr, $errcode);
                }
            
                $stmt = $this->db->prepare("DELETE FROM playersmaster WHERE pid=:pid");
                $stmt->execute(['pid'=>$pid]);
                $stmt = $this->db->prepare("DELETE FROM playerslocal WHERE id=:pid");
                $stmt->execute(['pid'=>$pid]);
                $stmt = $this->db->prepare("DELETE FROM playerimg WHERE pid=:pid");
                $stmt->execute(['pid'=>$pid]);
                $fl =1;
            }
            if ($fl==1) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record deleted.';
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record not deleted.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    //Delete  Pool
    public function funcDeletePool($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        
        $check =  $this->security->validateRequired(['poolid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
                    
        $poolid = $input['poolid'];
                      
        try {
            $this->db->beginTransaction();
            if (empty($this->security->checkPool($poolid))) {
                $resArr = $this->security->errorMessage("Pool id not exists");
                return $response->withJson($resArr, $errcode);
            }

            if ($this->security->poolDelCheck($poolid)) {
                $resArr = $this->security->errorMessage("This pool used in matches, You can't delete this pool");
                return $response->withJson($resArr, $errcode);
            }
        
            $stmt = $this->db->prepare("DELETE FROM poolprizebreaks WHERE poolcontestid=:poolid");
            $stmt->execute(['poolid'=>$poolid]);

            $stmt = $this->db->prepare("DELETE FROM contestsmeta WHERE id=:poolid");
            $stmt->execute(['poolid'=>$poolid]);
            
            $this->db->commit();
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] = 'Pool deleted successfully.';
        } catch (PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
            $this->db->rollBack();
        }
        return $response->withJson($resArr, $code);
    }

    public function funcAddplayerimg($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];

    
        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['pid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $pid        =  $input['pid'];
   
        /*if(!$this->security->isPlayeridExist($pid)){
            $resArr = $this->security->errorMessage("player id not exist.");
            return $response->withJson($resArr,$errcode);
        } */


        $dir = "";

        if (empty($_FILES['img'])) {
            $resArr = $this->security->errorMessage("Please select images.");
            return $response->withJson($resArr, $errcode);
        }
        
        $dir = "./uploads/players/";
        $files =  $_FILES['img'];

        if (($files["size"]/1024) > $imgSizeLimit) {
            $resArr = $this->security->errorMessage("Image size should be less then ".$imgSizeLimit." kb.");
            return $response->withJson($resArr, $errcode);
        }

        $img = explode('.', $files["name"]);
        $ext = end($img);
        if (!in_array($ext, ['jpg','jpeg','png','PNG'])) {
            $resArr = $this->security->errorMessage("invalid image format.");
            return $response->withJson($resArr, $errcode);
        }
             
        $imgArr = [] ;
        $img = explode('.', $files["name"]);
        $ext = end($img);
        $imgname = time().'_'.$files["name"];
        $target_file = $dir . $imgname;
        if (!move_uploaded_file($files["tmp_name"], $target_file)) {
            $resArr = $this->security->errorMessage("Images not uploaded, Therer is some problem");
            return $response->withJson($resArr, $errcode);
        }
                  
        try {
            $this->db->beginTransaction();

            $player = $this->db->prepare("INSERT INTO playerimg (pid,playerimage) VALUES (:pid, :playerimage)");
            $player->bindParam(':pid', $pid);
            $player->bindParam(':playerimage', $imgname);
            $player->execute();

            $this->db->commit();
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Image added successfully.';
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
            $this->db->rollBack();
        }
           
        return $response->withJson($resArr, $code);
    }

    

    public function funcGetplayer($request, $response)
    {
        global $settings;
        global $baseurl;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
                 
        $input = $request->getParsedBody();
                 
        $params     = [];
        $searchSql  = "";
        $paging 	= "";

        if (isset($input['page']) && !empty($input['page'])) {
            $limit  = $input['limit']; //2
                     $page   = $input['page'];  //0   LIMIT $page,$offset
                     if (empty($limit)) {
                         $limit  = $settings['settings']['code']['defaultPaginLimit'];
                     }

            $offset = ($page-1)*$limit;
            $paging = " limit ".$offset.",".$limit;
        }
                 
        if (!empty($input['search']) && isset($input['search'])) {
            $search = $input['search'];
            $searchSql = " WHERE (pm.fullname LIKE :search OR pm.pname LIKE :search) ";
            $params = ["search"=>"%$search%"];
        }
                
        $sqlCount = "SELECT count(pid) as total FROM playersmaster pm".$searchSql." ORDER BY pname DESC";
        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute($params);
        $resCount =  $stmtCount->fetch();
                                                 
        try {
            $pimgUrl = $baseurl.'/uploads/players/';
            $sql = "select pm.pid,pm.fullname,pm.pname,pm.country,pm.playertype,pt.name as ptype ,pm.ttype,CONCAT('".$pimgUrl."',pi.playerimage) as pimg 
FROM playersmaster pm INNER JOIN playertype pt ON pt.id=pm.playertype LEFT JOIN playerimg pi ON pm.pid=pi.pid AND status=1 ".$searchSql." GROUP BY pi.pid ORDER BY pm.pname DESC ".$paging;


            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();

            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'List player.';
                $resArr['data']  = ["total"=>$resCount['total'],"list"=>$res];
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
        }
           
        return $response->withJson($resArr, $code);
    }


    // Get user pan cards
    public function funcGetuserpancards($request, $response)
    {
        global $settings;
        global $baseurl;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
                 
        $input = $request->getParsedBody();
                 
        $params     = [];
        $searchSql  = "";
        $paging 	= "";

        if (isset($input['page']) && !empty($input['page'])) {
            $limit  = $input['limit']; //2
                     $page   = $input['page'];  //0   LIMIT $page,$offset
                     if (empty($limit)) {
                         $limit  = $settings['settings']['code']['defaultPaginLimit'];
                     }
                                          
            $offset = ($page-1)*$limit;
            $paging = " limit ".$offset.",".$limit;
        }
        try {
            if (!empty($input['search']) && isset($input['search'])) {
                $search = $input['search'];
                $searchSql = " WHERE d.panname LIKE :search OR d.pannumber LIKE :search  ";
                $params = ["search"=>"%$search%"];
            }
                
            $sqlCount = "SELECT count(d.id) as total FROM documents d".$searchSql." ORDER BY d.id DESC";
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute($params);
            $resCount =  $stmtCount->fetch();

            /*$check =  $this->security->validateRequired([''],$input);
            if(isset($check['error'])) {
               return $response->withJson($check);
            } */
       

            $pimgUrl = $baseurl.'/uploads/pancard/';
            $sql = "select d.pannumber,d.panname,d.dob,CONCAT('".$pimgUrl."',d.panimage) as panimage,d.isverified 
FROM documents d  ".$searchSql." ORDER BY d.id DESC ".$paging;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();

            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'List pancard.';
                $resArr['data']  = ["total"=>$resCount['total'],"list"=>$res];
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
        }
           
        return $response->withJson($resArr, $code);
    }



    public function getuserbankdetailsFunc($request, $response)
    {
        global $settings;
        global $baseurl;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
             
        $input = $request->getParsedBody();
             
        $params     = [];
        $searchSql  = "";
        $paging 	= "";

        if (isset($input['page']) && !empty($input['page'])) {
            $limit  = $input['limit']; //2
                 $page   = $input['page'];  //0   LIMIT $page,$offset
                 if (empty($limit)) {
                     $limit  = $settings['settings']['code']['defaultPaginLimit'];
                 }
                                      
            $offset = ($page-1)*$limit;
            $paging = " limit ".$offset.",".$limit;
        }

        try {
            if (!empty($input['search']) && isset($input['search'])) {
                $search = $input['search'];
                $searchSql = " WHERE uba.acholdername LIKE :search OR uba.bankname LIKE :search  ";
                $params = ["search"=>"%$search%"];
            }
                
            $sqlCount = "SELECT count(uba.id) as total FROM userbankaccounts uba".$searchSql." ORDER BY uba.id DESC";
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute($params);
            $resCount =  $stmtCount->fetch();
         
            $sql = "select uba.userid,uba.acno,uba.ifsccode,uba.bankname,uba.acholdername,uba.isverified 
FROM userbankaccounts uba  ".$searchSql." ORDER BY uba.id DESC ".$paging;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();
                
            $resData = [];
            if (!empty($res)) {
                foreach ($res as $row) {
                    $key = \Security::generateKey($row['userid']);

                    $ifsc = \Security::my_decrypt($row['ifsccode'], $key);
                    $acno = \Security::my_decrypt($row['acno'], $key);
                    $row['ifsccode']	=	'xxxx'.substr($ifsc, -4);
                    $row['acno']		=	'xxxxxx'.substr($acno, -4);
                    $resData[] = $row;
                }
            }
                 
            if (!empty($resData)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'List bank details.';
                $resArr['data']  = ["total"=>$resCount['total'],"list"=>$resData];
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
        }
           
        return $response->withJson($resArr, $code);
    }


    //---Add Team---
    public function addteamFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['teamname','shortname'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
           
        $teamname  = str_replace(" ", "", $input["teamname"]);
        $shortname = str_replace(" ", "", $input["shortname"]);

        $isTeamnameExist = $this->security->isTeamnameExist($teamname);
        if ($isTeamnameExist) {
            $resArr = $this->security->errorMessage("Teamname already exists.");
            return $response->withJson($resArr, $errcode);
        }

        $isShortNameExist = $this->security->isTeamShortnameExist($shortname);
        if ($isShortNameExist) {
            $resArr = $this->security->errorMessage("Team shortname already exists.");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $data =  [
                           'teamname'=>$teamname,
                           'shortname'=>$shortname
                        ];

            $stmt = $this->db->prepare("INSERT INTO teammaster (teamname,shortname) VALUES (:teamname,:shortname)");
            if ($stmt->execute($data)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record added successfully.';
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record not added, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }


    public function editteamFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
   
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['id','teamname','shortname'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $id = $input["id"];
        $teamname  = str_replace(" ", "", $input["teamname"]);
        $shortname = str_replace(" ", "", $input["shortname"]);

        $checkTeamIdExist = $this->security->checkTeamIdExist($id);
        if ($checkTeamIdExist) {
            $resArr = $this->security->errorMessage("Invalid team id.");
            return $response->withJson($resArr, $errcode);
        }
        
        $isTeamnameExist = $this->security->isTeamnameExist($teamname, $id);
        if ($isTeamnameExist) {
            $resArr = $this->security->errorMessage("Teamname already exists.");
            return $response->withJson($resArr, $errcode);
        }
           
        $isShortNameExist = $this->security->isTeamShortnameExist($shortname, $id);
        if ($isShortNameExist) {
            $resArr = $this->security->errorMessage("Team shortname already exists.");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $data = [
                           'id'=>$id,
                           'teamname'=>$teamname,
                           'shortname'=>$shortname,
                       ];

            $sql = "UPDATE teammaster SET teamname=:teamname,shortname=:shortname WHERE id=:id";
            $stmt= $this->db->prepare($sql);
            if ($stmt->execute($data)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record updated successfully.';
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record not updated, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }


    //---list Team---
    public function listteamFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();

        $params 	= [];
        $serachSql 	= "";
         
        if (!empty($input['search']) && isset($input['search'])) {
            $search 	= $input['search'];
            $serachSql 	= " WHERE teamname LIKE :search ";
            $params 	= ["search"=>"%$search%"];
        }
                                   
        try {
            $sql = "select id,teamname,shortname from teammaster ".$serachSql." ORDER BY id DESC" ;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();

            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] 	 = 'listteam.';
                $resArr['data']  = $res;
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] 	 = 'Record not added, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    //---list Player by team---
    public function listplayerbyteamFunc($request, $response)
    {
        global $settings;
        global $baseurl;
          
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['teamid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
                
        $teamid    = $input['teamid'];
        $seriesid  = $input['seriesid'];
        //$seriesid  = 1;
        
        $checkTeamIdExist = $this->security->checkTeamIdExist($teamid);
        if ($checkTeamIdExist) {
            $resArr = $this->security->errorMessage("Invalid team id.");
            return $response->withJson($resArr, $errcode);
        }
    
        try {
            //IF(field1 IS NULL or field1 = '', 'empty', field1)
            //LEFT JOIN playerimg pi ON pm.pid=pi.pid AND status=1
            //(select CONCAT('".$pimgUrl."',playerimage) from playerimg where pid=t.pid AND status=1 LIMIT 1) as pimg,playerimage

            $pimgUrl  = $baseurl.'/uploads/players/';
            $sql = "select t.teamid,t.pid,pm.fullname,pm.pname,pm.playertype,pm.credit,pt.name as ptype from team t INNER JOIN playersmaster pm 
ON pm.pid=t.pid  INNER JOIN playertype pt ON pm.playertype=pt.id WHERE t.teamid = :teamid" ;

            $stmt 	= $this->db->prepare($sql);
            $params = ["teamid"=>$teamid];
            $stmt->execute($params);
            $players 	=  $stmt->fetchAll();
        
            $dtArr = [];
            foreach ($players as $player) {
                $sql = "SELECT CONCAT('".$pimgUrl."',playerimage) as img,playerimage from playerimg where pid=:pid AND status=1 LIMIT 1" ;
                $stmt 	= $this->db->prepare($sql);
                $params = ["pid"=>$player['pid']];
                $stmt->execute($params);
                $imgRes 	=  $stmt->fetch();

                $pid = (string)$player['pid'];
                $seriesid = (string)$seriesid;
                $playerpoints = $this->mdb->playerpoints;
                $resPoints = $playerpoints->aggregate([
              [ '$match' => [ 'pid'=>$pid,'seriesid'=>$seriesid ] ],
              [ '$group' => [ '_id' => ['pid' => '$pid'], 'pts' => ['$sum' => '$totalpoints']  ] ],
              [ '$limit' => 1 ]
            ]);

                $resPoints = iterator_to_array($resPoints) ;
                $player['pts'] 		   = ($resPoints[0]['pts'])?$resPoints[0]['pts']:0;
                $player['pimg'] 	   = $imgRes['img'];
                $player['playerimage'] = $imgRes['playerimage'];
                if (empty($imgRes['playerimage'])) {
                    $player['pimg'] = $pimgUrl.$settings['settings']['path']['dummyplrimg'];
                    $player['playerimage'] = $settings['settings']['path']['dummyplrimg'];
                }
            
                $dtArr[] = $player;
            }

            if (!empty($dtArr)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'List player by team.';
                $resArr['data'] = $this->security->removenull($dtArr);
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
   
        return $response->withJson($resArr, $code);
    }


    //-- Add player to team
    public function addplayertoteamFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['teamid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $teamid = $input["teamid"];
        $pids   = $input["pids"];

        if (empty($pids) && !is_array($pids)) {
            $resArr = $this->security->errorMessage("Select players.");
            return $response->withJson($resArr, $errcode);
        }

        $pids = array_unique($pids);

        $checkTeamIdExist = $this->security->checkTeamIdExist($teamid);
        if ($checkTeamIdExist) {
            $resArr = $this->security->errorMessage("Invalid team id.");
            return $response->withJson($resArr, $errcode);
        }
                                      
        try {
            $stmt = $this->db->prepare("DELETE FROM team WHERE teamid=:teamid ");
            $stmt->execute(["teamid"=>$teamid]);

            foreach ($pids as $pid) {
                $checkTeamPlayerExist = $this->security->checkTeamPlayerExist($teamid, $pid);
                if (!empty($checkTeamPlayerExist)) {
                    $resArr = $this->security->errorMessage("Player already exit");
                    return $response->withJson($resArr, $errcode);
                }

                $isPlayeridExist = $this->security->isPlayeridExist($pid);
                if (!$isPlayeridExist) {
                    $resArr = $this->security->errorMessage("Invalid player id.");
                    return $response->withJson($resArr, $errcode);
                }
                $data = [
                       'pid'=>$pid,
                       'teamid'=>$teamid
                    ];
                $stmt = $this->db->prepare("INSERT INTO team (teamid,pid) VALUES (:teamid,:pid)");
                $stmt->execute($data);
            }

            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] 	 = 'Record added successfully.';
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
   
        return $response->withJson($resArr, $code);
    }



 

    // Remove player from team
    public function removeplayerfromteamFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input  = $request->getParsedBody();
        $check  =  $this->security->validateRequired(['id'], $input);

        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $id  = $input["id"];
        $checkTeamPlayerIdexist = $this->security->checkTeamPlayerIdexist($id);
        if ($checkTeamPlayerIdexist) {
            $resArr = $this->security->errorMessage("Invalid teamplayer id.");
            return $response->withJson($resArr, $errcode);
        }
        try {
            $data = [
                           'id'=>$id,
                        ];
            $stmt = $this->db->prepare("DELETE from team WHERE id=:id");
            if ($stmt->execute($data)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record removed successfully.';
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record not added, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }

        return $response->withJson($resArr, $code);
    }


    //Add Global Settings
    public function addglobalpointsFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input 	 = $request->getParsedBody();

        $check =  $this->security->validateRequired(['gametype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        try {
            $gametype= $input['gametype'];
            $checkGm = $this->security->isGameTypeExistByName($gametype);
            if ($checkGm) {
                $resArr = $this->security->errorMessage("Invalid gametype");
                return $response->withJson($resArr, $errcode);
            }

            if ($gametype == 'cricket') {
                $res = $this->addCricketGlobalPointsFunc($input);
            } elseif ($gametype == 'kabaddi') {
                $res = $this->addKabaddiGlobalPointsFunc($input);
            } elseif ($gametype == 'football') {
                $res = $this->addFootballGlobalPointsFunc($input);
            }

            if ($res['error'] == true) {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] 	 = $res['msg'];
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] 	 = $res['msg'];
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code 			 = $errcode;
        }
        return $response->withJson($resArr, $code);
    }
    


    public function addCricketGlobalPointsFunc($input)
    {
        $check =  $this->security->validateRequired(['gametype','mtype','wicket','catch','run','six','four','fifty','hundred','duck','mdnover','stumped','cap','vcap','playing','fourwhb','fivewhb','runout','thrower','catcher','srone','srtwo','srthree','erone','ertwo','erthree','erfour','erfive','ersix','srmball','ermover'], $input);

        if (isset($check['error'])) {
            return $resArr = ['error'=>true,'msg'=>$check['msg']];
        }
           
        $gametype   = $input['gametype'];

        $mtype  	= $input["mtype"];
        $wicket 	= $input["wicket"];
        $catch  	= $input["catch"];
        $run    	= $input["run"];
        $six    	= $input["six"];
        $four   	= $input["four"];
        $fifty   	= $input["fifty"];
        $hundred 	= $input["hundred"];
        $duck   	= $input["duck"];
        $mdnover 	= $input["mdnover"];
        $stumped 	= $input["stumped"];
        $cap 		= $input["cap"];
        $vcap 		= $input["vcap"];
        $playing 	= $input["playing"];
        $fourwhb 	= $input["fourwhb"];
        $fivewhb 	= $input["fivewhb"];
        $runout 	= $input["runout"];

        $thrower 	= $input["thrower"];
        $catcher 	= $input["catcher"];

        $srone 		= $input["srone"];
        $srtwo 		= $input["srtwo"];
        $srthree 	= $input["srthree"];

        $erone 		= $input["erone"];
        $ertwo 		= $input["ertwo"];
        $erthree 	= $input["erthree"];
        $erfour 	= $input["erfour"];
        $erfive 	= $input["erfive"];
        $ersix 		= $input["ersix"];
        $srmball 	= $input["srmball"];
        $ermover 	= $input["ermover"];
                  
        if (!in_array($mtype, ['Test','ODI','Twenty20'])) {
            return $resArr = ['error'=>true,'msg'=>"mtype should be Test/ODI/Twenty20"] ;
        }

        $dir = __DIR__ . '/../settings/'.GLOBALPOINTSKEY;
        $addset = @file_get_contents($dir);
        if ($addset === false) {
            $datas[GLOBALPOINTSKEY][$gametype][$mtype]  =
                  [
                   'wicket' 	=> $wicket,
                   'catch'  	=> $catch,
                   'run'    	=> $run,
                   'six'    	=> $six,
                   'four'   	=> $four,
                   'fifty'   	=> $fifty,
                   'hundred' 	=> $hundred,
                   'duck'   	=> $duck,
                   'mdnover' 	=> $mdnover,
                   'stumped' 	=> $stumped,
                   'cap' 		=> $cap,
                   'vcap' 		=> $vcap,
                   'playing' 	=> $playing,
                   'fourwhb' 	=> $fourwhb,
                   'fivewhb' 	=> $fivewhb,

                   'runout' 	=> $runout,

                   'thrower' 	=> $thrower,
                   'catcher' 	=> $catcher,

                    'srone' 	=> 	$srone,
                    'srtwo' 	=> 	$srtwo,
                    'srthree' 	=> 	$srthree,
                   
                    'erone' 	=> 	$erone,
                    'ertwo' 	=> 	$ertwo,
                    'erthree' 	=> 	$erthree,
                    'erfour' 	=> 	$erfour,
                    'erfive' 	=> 	$erfive,
                    'ersix' 	=> 	$ersix,
                    'srmball' 	=> 	$srmball,
                    'ermover' 	=> 	$ermover
                ];
        } else {
            $datas = json_decode($addset, true);
            $datas[GLOBALPOINTSKEY][$gametype][$mtype]=
            [
                   'wicket' 	=> $wicket,
                   'catch'  	=> $catch,
                   'run'    	=> $run,
                   'six'    	=> $six,
                   'four'   	=> $four,
                   'fifty'   	=> $fifty,
                   'hundred' 	=> $hundred,
                   'duck'   	=> $duck,
                   'mdnover' 	=> $mdnover,
                   'stumped' 	=> $stumped,
                   'cap' 		=> $cap,
                   'vcap' 		=> $vcap,
                   'playing' 	=> $playing,
                   'fourwhb' 	=> $fourwhb,
                   'fivewhb' 	=> $fivewhb,
                   'runout' 	=> $runout,
                   'thrower' 	=> $thrower,
                   'catcher' 	=> $catcher,
                   
                    'srone' 	=> 	$srone,
                    'srtwo' 	=> 	$srtwo,
                    'srthree' 	=> 	$srthree,
                   
                    'erone' 	=> 	$erone,
                    'ertwo' 	=> 	$ertwo,
                    'erthree' 	=> 	$erthree,
                    'erfour' 	=> 	$erfour,
                    'erfive' 	=> 	$erfive,
                    'ersix' 	=> 	$ersix,
                    'srmball' 	=> 	$srmball,
                    'ermover' 	=> 	$ermover
            ];
        }

        if (file_put_contents($dir, json_encode($datas))) {
            return $resArr = ['error'=>true,'msg'=>'Record Updated successfully.'] ;
        } else {
            return $resArr = ['error'=>true,'msg'=>'Record not Updated, There is some problem.'] ;
        }
    }

    public function addKabaddiGlobalPointsFunc($input)
    {
        $check =  $this->security->validateRequired(['gametype','playing','touch','raidbonus','successtackle','unsuccessraid','supertackle','pushallout','getallout','greencard','yellowcard','redcard','makesubstitute'], $input);

        if (isset($check['error'])) {
            return $resArr = ['error'=>true,'msg'=>$check['msg']] ;
        }
           
        $gametype   		= $input['gametype'];
        $playing  			= $input["playing"];
        $touch 				= $input["touch"];
        $raidbonus  		= $input["raidbonus"];
        $successtackle    	= $input["successtackle"];
        $unsuccessraid    	= $input["unsuccessraid"];
        $supertackle   		= $input["supertackle"];
        $pushallout   		= $input["pushallout"];
        $getallout 			= $input["getallout"];
        $greencard   		= $input["greencard"];
        $yellowcard 		= $input["yellowcard"];
        $redcard 			= $input["redcard"];
        $makesubstitute 	= $input["makesubstitute"];
                  
        /*	if(!in_array($mtype,['Test','ODI','Twenty20'])){
        		return $resArr = ['error'=>true,'msg'=>"mtype should be Test/ODI/Twenty20"] ;
        	}*/

        $dir = __DIR__ . '/../settings/'.GLOBALPOINTSKEY;
        $addset = @file_get_contents($dir);
        if ($addset === false) {
            $datas[GLOBALPOINTSKEY][$gametype]['kabaddi']  =
                    [
                           "playing"  			=> $input["playing"],
                           "touch" 			=> $input["touch"],
                           "raidbonus"  		=> $input["raidbonus"],
                           "successtackle"    	=> $input["successtackle"],
                           "unsuccessraid"    	=> $input["unsuccessraid"],
                           "supertackle"   	=> $input["supertackle"],
                           "pushallout"   		=> $input["pushallout"],
                           "getallout" 		=> $input["getallout"],
                           "greencard"   		=> $input["greencard"],
                           "yellowcard" 		=> $input["yellowcard"],
                           "redcard" 			=> $input["redcard"],
                           "makesubstitute" 	=> $input["makesubstitute"]
                    ];
        } else {
            $datas = json_decode($addset, true);
            $datas[GLOBALPOINTSKEY][$gametype]['kabaddi']=
                    [
                           "playing"  			=> $input["playing"],
                           "touch" 			=> $input["touch"],
                           "raidbonus"  		=> $input["raidbonus"],
                           "successtackle"    	=> $input["successtackle"],
                           "unsuccessraid"    	=> $input["unsuccessraid"],
                           "supertackle"   	=> $input["supertackle"],
                           "pushallout"   		=> $input["pushallout"],
                           "getallout" 		=> $input["getallout"],
                           "greencard"   		=> $input["greencard"],
                           "yellowcard" 		=> $input["yellowcard"],
                           "redcard" 			=> $input["redcard"],
                           "makesubstitute" 	=> $input["makesubstitute"],
                    ];
        }

        if (file_put_contents($dir, json_encode($datas))) {
            return $resArr = ['error'=>true,'msg'=>'Record Updated successfully.'] ;
        } else {
            return $resArr = ['error'=>true,'msg'=>'Record not Updated, There is some problem.'] ;
        }
    }


    public function addFootballGlobalPointsFunc($input)
    {
        $check =  $this->security->validateRequired(['gametype','playfiftyfivemin','playlessfiftyfive','goalfor','goalmid','goalgk','goaldef','assist','cleansheetmid','cleansheetgk','penaltysavegk','yellowcard','redcard','owngoal','goalsconcededgk','penaltymissed','shotontarget','tackles','passes','goalsaved','goalsconcededdef'], $input);

        if (isset($check['error'])) {
            return $resArr = ['error'=>true,'msg'=>$check['msg']] ;
        }
           
        $gametype   		= $input['gametype'];
        $playfiftyfivemin  	= $input["playfiftyfivemin"];
        $playlessfiftyfive 	= $input["playlessfiftyfive"];
        $goalfor  			= $input["goalfor"];
        $goalmid    		= $input["goalmid"];
        $goalgk    			= $input["goalgk"];
        $goaldef   			= $input["goaldef"];
        $assist   			= $input["assist"];

        $cleansheetmid 		= $input["cleansheetmid"];
        $cleansheetgk   	= $input["cleansheetgk"];
        $cleansheetdef   	= $input["cleansheetdef"];
        $penaltysavegk 		= $input["penaltysavegk"];

        $yellowcard 		= $input["yellowcard"];
        $redcard 			= $input["redcard"];
        $owngoal 			= $input["owngoal"];
        $goalsconcededgk 	= $input["goalsconcededgk"];
        $penaltymissed 		= $input["penaltymissed"];

        $shotontarget 		= $input["shotontarget"];
        $tackles 			= $input["tackles"];
        $passes 			= $input["passes"];
        $goalsaved 			= $input["goalsaved"];
        $goalsconcededdef	= $input["goalsconcededdef"];
                         
        $dir = __DIR__ . '/../settings/'.GLOBALPOINTSKEY;
        $addset = @file_get_contents($dir);
        if ($addset === false) {
            $datas[GLOBALPOINTSKEY][$gametype]['football']  =
                    [
                           "playfiftyfivemin"  	=> $playfiftyfivemin,
                           "playlessfiftyfive" 	=> $playlessfiftyfive,
                           "goalfor"  				=> $goalfor,
                           "goalmid"    			=> $goalmid,
                           "goalgk"    			=> $goalgk,
                           "goaldef"   			=> $goaldef,
                           "assist"   				=> $assist,
                           "cleansheetmid" 		=> $cleansheetmid,
                           "cleansheetgk"   		=> $cleansheetgk,
                           "cleansheetdef"   		=> $cleansheetdef,
                           "penaltysavegk" 		=> $penaltysavegk,
                           "yellowcard" 			=> $yellowcard,
                           "redcard" 				=> $redcard,
                           "owngoal" 			    => $owngoal,
                           "goalsconcededgk" 		=> $goalsconcededgk,
                           "penaltymissed" 		=> $penaltymissed,
                           "shotontarget" 			=> $shotontarget,
                           "tackles" 				=> $tackles,
                           "passes" 				=> $passes,
                           "goalsaved" 			=> $goalsaved,
                           "goalsconcededdef"		=> $goalsconcededdef
                    ];
        } else {
            $datas = json_decode($addset, true);
            $datas[GLOBALPOINTSKEY][$gametype]['football']=
                    [
                           "playfiftyfivemin"  	=> $playfiftyfivemin,
                           "playlessfiftyfive" 	=> $playlessfiftyfive,
                           "goalfor"  				=> $goalfor,
                           "goalmid"    			=> $goalmid,
                           "goalgk"    			=> $goalgk,
                           "goaldef"   			=> $goaldef,
                           "assist"   				=> $assist,
                           "cleansheetmid" 		=> $cleansheetmid,
                           "cleansheetgk"   		=> $cleansheetgk,
                           "cleansheetdef"   		=> $cleansheetdef,
                           "penaltysavegk" 		=> $penaltysavegk,
                           "yellowcard" 			=> $yellowcard,
                           "redcard" 				=> $redcard,
                           "owngoal" 			    => $owngoal,
                           "goalsconcededgk" 		=> $goalsconcededgk,
                           "penaltymissed" 		=> $penaltymissed,
                           "shotontarget" 			=> $shotontarget,
                           "tackles" 				=> $tackles,
                           "passes" 				=> $passes,
                           "goalsaved" 			=> $goalsaved,
                           "goalsconcededdef"		=> $goalsconcededdef
                    ];
        }

        if (file_put_contents($dir, json_encode($datas))) {
            return $resArr = ['error'=>true,'msg'=>'Record Updated successfully.'] ;
        } else {
            return $resArr = ['error'=>true,'msg'=>'Record not Updated, There is some problem.'] ;
        }
    }

    // get Global Settings
    public function getglobalpointsFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input   = $request->getParsedBody();

        $check =  $this->security->validateRequired(['gametype'], $input);

        if (isset($check['error'])) {
            return $response->withJson($check);
        }
           
        $gametype = $input['gametype'];
        $resNew   = [];
        $checkGm = $this->security->isGameTypeExistByName($gametype);
        if ($checkGm) {
            $resArr = $this->security->errorMessage("Invalid gametype");
            return $response->withJson($resArr, $errcode);
        }
   
        if (isset($input["mtype"])) {
            $mtype = $input["mtype"];
            if (!in_array($mtype, ['Test','ODI','Twenty20'])) {
                $resArr = $this->security->errorMessage("mtype should be Test/ODI/Twenty20");
                return $response->withJson($resArr, $errcode);
            }
        }

        try {
            $dir    = __DIR__ . '/../settings/'.GLOBALPOINTSKEY;
            $addset = @file_get_contents($dir);

            if ($addset === false) {
                $resArr = $this->security->errorMessage("Record not found,contact to webmaster.");
                return $response->withJson($resArr, $errcode);
            } else {
                $res = json_decode($addset, true);
                $resNew[GLOBALPOINTSKEY][$gametype] = $res[GLOBALPOINTSKEY][$gametype];
            }

            //unset($res[GLOBALPOINTSKEY][$gametype]['capsetting']);
              

            if (!empty($mtype)) {
                if (empty($res[GLOBALPOINTSKEY][$gametype][$mtype])) {
                    $resArr = $this->security->errorMessage("Record not found.");
                    return $response->withJson($resArr, $errcode);
                }
                $res1 = [];
                $res1[GLOBALPOINTSKEY][][$gametype][$mtype] = $res[GLOBALPOINTSKEY][$gametype][$mtype];
                $resNew = $res1;
                //capsetting
            }

            if (!empty($resNew)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Global points setting.';
                $resArr['data']  = $resNew[GLOBALPOINTSKEY] ;
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'Record not found,contact to webmaster.';
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
    
        return $response->withJson($resArr, $code);
    }

    
    // update cap vcap point
    public function updatecapvcappointFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['gametype','cap','vcap'], $input);

        if (isset($check['error'])) {
            return $response->withJson($check);
        }
           
        $gametype= $input['gametype'];
        $cap  	= $input["cap"];
        $vcap 	= $input["vcap"];
           
        $checkGm = $this->security->isGameTypeExistByName($gametype);
        if ($checkGm) {
            $resArr = $this->security->errorMessage("Invalid gametype");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $dir = __DIR__ . '/../settings/'.GLOBALPOINTSKEY;
            $addset = @file_get_contents($dir);
               
            if ($addset === false) {
                $datas[GLOBALPOINTSKEY][$gametype]['capsetting']  =
                          [
                          'cap'=>$cap,
                          'vcap'=>$vcap
                        ];
            } else {
                $datas = json_decode($addset, true);
                $datas[GLOBALPOINTSKEY][$gametype]['capsetting']  =
                          [
                          'cap'=>$cap,
                          'vcap'=>$vcap
                        ];
            }

            if (file_put_contents($dir, json_encode($datas))) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record Updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not Updated, There is some problem';
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    // get cap vcap point
    /*public function getcapvcappointFunc($request,$response)
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

    try{

       $dir    = __DIR__ . '/../settings/'.GLOBALPOINTSKEY;
       $addset = @file_get_contents($dir);

        if ($addset === false) {
                $resArr = $this->security->errorMessage("Record not found,contact to webmaster.");
                return $response->withJson($resArr,$errcode);
        } else {
            $res = json_decode($addset, true);
        }

        if(empty($res[GLOBALPOINTSKEY][$gametype]['capsetting'])){
               $resArr = $this->security->errorMessage("Record not found.");
            return $response->withJson($resArr,$errcode);
          }

          $res = $res[GLOBALPOINTSKEY][$gametype]['capsetting'];

        if(!empty($res))
        {
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Cap Vcap setting.';
            $resArr['data']  = $res;
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

    } */

    // Add Global Settings
    public function addglobalsettingFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['totalpoints','maxteam'], $input);

        if (isset($check['error'])) {
            return $response->withJson($check);
        }
           
        $totalpoints = intval($input['totalpoints']);
        $maxteam  	= intval($input["maxteam"]);
           
        if ($maxteam < 1 || $maxteam > 15) {
            $resArr = $this->security->errorMessage("Invalid maxteam value");
            return $response->withJson($resArr, $errcode);
        }

        if ($totalpoints < 1 || $totalpoints > 200) {
            $resArr = $this->security->errorMessage("Invalid totalpoints value");
            return $response->withJson($resArr, $errcode);
        }
          
        try {
            $dir = __DIR__ . '/../settings/'.GLOBALSETTING;
            $addset = @file_get_contents($dir);
               
            if ($addset === false) {
                $datas[GLOBALSETTING]  =
                        [
                          'totalpoints'=>$totalpoints,
                          'maxteam'=>$maxteam
                        ];
            } else {
                $datas = json_decode($addset, true);
                $datas[GLOBALSETTING]  =
                        [
                          'totalpoints'=>$totalpoints,
                          'maxteam'=>$maxteam
                        ];
            }
            if (file_put_contents($dir, json_encode($datas))) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Record Updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'Record not Updated, There is some problem';
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    // get Global Settings
    public function getglobalsettingFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input   = $request->getParsedBody();

        /*$check =  $this->security->validateRequired([],$input);
        if(isset($check['error'])) {
            return $response->withJson($check);
        }*/
        try {
            $dir    = __DIR__ . '/../settings/'.GLOBALSETTING;
            $addset = @file_get_contents($dir);
            if ($addset === false) {
                $resArr = $this->security->errorMessage("Record not found,contact to webmaster.");
                return $response->withJson($resArr, $errcode);
            } else {
                $res = json_decode($addset, true);
            }
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Global setting.';
                $resArr['data']  = $res[GLOBALSETTING] ;
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'Record not found,contact to webmaster.';
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

  
    // Upload player images Multiple
    public function uploadmultipleimgFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];

        $input  = $request->getParsedBody();
        $input  = $request->getParsedBody();
        $check  =  $this->security->validateRequired(['imgtype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $imgtype = $input['imgtype'];
        $dir = "";

        if (!in_array($imgtype, ['playerimg','teamlogo','slider','noti'])) {
            $resArr = $this->security->errorMessage("Invalid imgtype.");
            return $response->withJson($resArr, $errcode);
        }
        if (empty($_FILES['images'])) {
            $resArr = $this->security->errorMessage("Please select images.");
            return $response->withJson($resArr, $errcode);
        }
            
        if ($imgtype == "playerimg") {
            $dir = "./uploads/players/";
        }
        if ($imgtype == "teamlogo") {
            $dir = "./uploads/teamlogo/";
        }
        if ($imgtype == "slider") {
            $dir = "./uploads/slider/";
        }
        if ($imgtype == "noti") {
            $dir = "./uploads/notifications/";
        }
                           
        $files =  $_FILES['images'];

        for ($i = 0; $i < count($files["name"]); $i++) {
            if (($files["size"][$i]/1024) > $imgSizeLimit) {
                $resArr = $this->security->errorMessage("Image size should be less then ".$imgSizeLimit." kb.");
                return $response->withJson($resArr, $errcode);
            }
            $img = explode('.', $files["name"][$i]);
            $ext = end($img);
            if (!in_array($ext, ['jpg','jpeg','png','PNG','JPEG','JPG'])) {
                $resArr = $this->security->errorMessage("invalid image format.");
                return $response->withJson($resArr, $errcode);
            }
        }

        try {
            $imgArr = [] ;
            for ($i = 0; $i < count($files["name"]); $i++) {
                $img = explode('.', $files["name"][$i]);
                $ext = end($img);
                $imgname = time().'_'.$files["name"][$i];
                $imgname = str_replace(" ", "", $imgname);
                $target_file = $dir . $imgname;
                if (move_uploaded_file($files["tmp_name"][$i], $target_file)) {
                    $imgArr[]= $imgname;
                } else {
                    $resArr = $this->security->errorMessage("Images not uploaded, Therer is some problem");
                    return $response->withJson($resArr, $errcode);
                }
            }
            if (!empty($imgArr)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Images uploaded successfully.';
                $resArr['data']  = $imgArr ;
            } else {
                $resArr = $this->security->errorMessage("Images not uploaded, Therer is some problem");
                return $response->withJson($resArr, $errcode);
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }

        return $response->withJson($resArr, $code);
    }



    //Add contests
    public function addcontestsFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];
      
        $input   = $request->getParsedBody();

        $loginuser = $request->getAttribute('decoded_token_data');  /* Login User id */
        $uid       = $loginuser['id'];

        $check =  $this->security->validateRequired(['title','subtitle'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        
        $title    = $input['title'];
        $subtitle = $input['subtitle'];
        $dis_val  = (isset($input['dis_val']) && !empty($input['dis_val']))?$input['dis_val']:0;
        $max_dis_val  = (isset($input['max_dis_val']) && !empty($input['max_dis_val']))?$input['max_dis_val']:0;
        $dis_type = ($dis_val)?'perval':'';
        $cpypoolstatus  = (isset($input['cpypoolstatus']) && !empty($input['cpypoolstatus']))?$input['cpypoolstatus']:0;
        $cpybfrtim  = (isset($input['cpybfrtim']) && !empty($input['cpybfrtim']))?$input['cpybfrtim']:0;
        $created  = time();

        $isConteststitleExist = $this->security->isConteststitleExist($title);
        if ($isConteststitleExist) {
            $resArr = $this->security->errorMessage("Title already exists.");
            return $response->withJson($resArr, $errcode);
        }
        
        if (empty($_FILES['contestlogo'])) {
            $resArr = $this->security->errorMessage("Please select contestlogo.");
            return $response->withJson($resArr, $errcode);
        }
        $files = $_FILES['contestlogo'];
        try {
            // image upload
            $dir = './uploads/contests/';
            $img = explode('.', $files["name"]);
            $ext = end($img);
            if (($files["size"]/1024) > $imgSizeLimit) {
                $resArr = $this->security->errorMessage("Image size should be less then ".$imgSizeLimit." kb.");
                return $response->withJson($resArr, $errcode);
            }
            if (!in_array($ext, ['jpg','jpeg','png'])) {
                $resArr = $this->security->errorMessage("invalid image format.");
                return $response->withJson($resArr, $errcode);
            }
            $imgname = time().'_'.$files["name"];
            $target_file = $dir . $imgname;
            if (!move_uploaded_file($files["tmp_name"], $target_file)) {
                $resArr = $this->security->errorMessage("Images not uploaded, There is some problem");
                return $response->withJson($resArr, $errcode);
            }
                
            $stmt = $this->db->prepare("INSERT INTO contests (userid,title,subtitle,contestlogo,dis_val,dis_type,max_dis_val,created,cpypoolstatus,cpybfrtim) VALUES (:userid,:title,:subtitle,:contestlogo,:dis_val,:dis_type,:max_dis_val,:created,:cpypoolstatus,:cpybfrtim)");
            $stmt->bindParam(':userid', $uid);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':subtitle', $subtitle);
            $stmt->bindParam(':contestlogo', $imgname);
            $stmt->bindParam(':dis_val', $dis_val);
            $stmt->bindParam(':dis_type', $dis_type);
            $stmt->bindParam(':max_dis_val', $max_dis_val);
            $stmt->bindParam(':created', $created);
            $stmt->bindParam(':cpypoolstatus', $cpypoolstatus);
            $stmt->bindParam(':cpybfrtim', $cpybfrtim);
            if ($stmt->execute()) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record added successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not added, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    
    //Edd contests
    public function editcontestsFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];
      
        $input   = $request->getParsedBody();

        $loginuser = $request->getAttribute('decoded_token_data');  /* Login User id */
        $uid       = $loginuser['id'];
        if (isset($input['atype']) && $input['atype']=='favStatusUpdate') {
            if ($this->updateStatusContest($input)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not added, There is some problem';
            }
            return $response->withJson($resArr, $code);
        }

        if (isset($input['atype']) && $input['atype']=='poolStatus') {
            if ($this->updateCopyPool($input)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not added, There is some problem';
            }
            return $response->withJson($resArr, $code);
        }

        $check =  $this->security->validateRequired(['id','title','subtitle'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        
        $id    = $input['id'];
        $title    = $input['title'];
        $subtitle = $input['subtitle'];
        $dis_val  = (isset($input['dis_val']) && !empty($input['dis_val']))?$input['dis_val']:0;
        $max_dis_val  = (isset($input['max_dis_val']) && !empty($input['max_dis_val']))?$input['max_dis_val']:0;
        $cpypoolstatus  = (isset($input['cpypoolstatus']) && !empty($input['cpypoolstatus']))?$input['cpypoolstatus']:0;
        $cpybfrtim = (isset($input['cpybfrtim']) && !empty($input['cpybfrtim']))?$input['cpybfrtim']:0;
        $created  = time();

        $isContestsExist = $this->security->isContestsExist($id);
        if (!$isContestsExist) {
            $resArr = $this->security->errorMessage("Invalid id.");
            return $response->withJson($resArr, $errcode);
        }

        $isConteststitleExist = $this->security->isConteststitleExist($title, $id);
        if ($isConteststitleExist) {
            $resArr = $this->security->errorMessage("Title already exists.");
            return $response->withJson($resArr, $errcode);
        }
        if ($dis_val>100) {
            $resArr = $this->security->errorMessage("Invalid discount value.");
            return $response->withJson($resArr, $errcode);
        }
        $files = ($_FILES && isset($_FILES['contestlogo']))?$_FILES['contestlogo']:'';
        try {
            $params = ["title"=>$title,"subtitle"=>$subtitle,"dis_val"=>$dis_val,"max_dis_val"=>$max_dis_val,"cpypoolstatus"=>$cpypoolstatus,"cpybfrtim"=>$cpybfrtim,"id"=>$id];
            $sql = "UPDATE contests SET title=:title,subtitle=:subtitle,dis_val=:dis_val,max_dis_val=:max_dis_val,cpypoolstatus=:cpypoolstatus,cpybfrtim=:cpybfrtim  WHERE id=:id";

            if (!empty($files)) {
                $dir = './uploads/contests/';
                $img = explode('.', $files["name"]);
                $ext = end($img);
                if (($files["size"]/1024) > $imgSizeLimit) {
                    $resArr = $this->security->errorMessage("Image size should be less then ".$imgSizeLimit." kb.");
                    return $response->withJson($resArr, $errcode);
                }
                if (!in_array($ext, ['jpg','jpeg','png'])) {
                    $resArr = $this->security->errorMessage("invalid image format.");
                    return $response->withJson($resArr, $errcode);
                }
                $imgname = time().'_'.$files["name"];
                $target_file = $dir . $imgname;
                if (!move_uploaded_file($files["tmp_name"], $target_file)) {
                    $resArr = $this->security->errorMessage("Images not uploaded, There is some problem");
                    return $response->withJson($resArr, $errcode);
                }
                 
                $params = ["title"=>$title,"subtitle"=>$subtitle,"dis_val"=>$dis_val,"max_dis_val"=>$max_dis_val,"contestlogo"=>$imgname,"id"=>$id];
                $sql = "UPDATE contests SET title=:title,subtitle=:subtitle,dis_val=:dis_val,max_dis_val=:max_dis_val,contestlogo=:contestlogo,cpypoolstatus=:cpypoolstatus,cpybfrtim=:cpybfrtim WHERE id=:id";
            }

            $stmt = $this->db->prepare($sql);

            if ($stmt->execute($params)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record Updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not updated, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    public function updateStatusContest($input)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $id 		= $input['id'];
        $favstatus	= ($input['favstatus'])?0:1;
        $params = ["id"=>$id,"favstatus"=>$favstatus];
        $sql = "UPDATE contests SET favstatus=:favstatus  WHERE id=:id";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            return true;
        }
        return false;
    }

    public function updateCopyPool($input)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $id 		= $input['id'];
        $cpypoolstatus	= ($input['cpypoolstatus'])?0:1;
        $params = ["id"=>$id,"cpypoolstatus"=>$cpypoolstatus];
        $sql = "UPDATE contests SET cpypoolstatus=:cpypoolstatus  WHERE id=:id";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            return true;
        }
        return false;
    }

    // List contests
    public function listcontestsFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $dataResArr = [];
        try {

          //  $sql = "SELECT c.id,c.title,c.subtitle,c.contestlogo,c.status,c.dis_val,c.dis_type,c.max_dis_val,cm.favpool,c.cpypoolstatus,c.cpybfrtim FROM contests c LEFT JOIN contestsmeta cm ON cm.contestid=c.id GROUP BY cm.contestid ORDER BY c.id DESC " ;

            $sql   = "SELECT c.id,c.title,c.subtitle,c.contestlogo,c.status,c.dis_val,c.dis_type,c.max_dis_val,IFNULL(c.cpybfrtim,'0') as cpybfrtim,IFNULL(c.cpypoolstatus,'0') as cpypoolstatus,IFNULL(cm.id,0) as favpool FROM contests c LEFT JOIN contestsmeta cm ON (c.id=cm.contestid AND cm.favpool='1') WHERE c.isprivate=0 GROUP BY c.id ORDER BY c.id DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $contests =  $stmt->fetchAll();
             
            foreach ($contests as $contest) {
                if (!empty($contest['contestlogo'])) {
                    $contest['contestlogo'] = $baseurl.'/uploads/contests/'.$contest['contestlogo'];
                }
                if ($contest['status'] == 1) {
                    $contest['status'] = true;
                } else {
                    $contest['status'] = false;
                }
               
               
                $dataResArr[] = $contest;
            }
             
            if (!empty($dataResArr)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Contests List.';
                $resArr['data']  = $this->security->removenull($dataResArr);
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] 	 = 'Record not found.';
            }
        } catch (PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }

    //update status contests
    public function contestUpdateStatusFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];
      
        $input   = $request->getParsedBody();

        /*
        $loginuser = $request->getAttribute('decoded_token_data');
        $uid       = $loginuser['id']; */

        $check =  $this->security->validateRequired(['id','status'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        
        $id      =  $input['id'];
        $status   =  $input['status'];
         
        if (!in_array($status, ['0','1'])) {
            $resArr = $this->security->errorMessage("Status should be 0/1.");
            return $response->withJson($resArr, $errcode);
        }
        $isContestsExist = $this->security->isContestsExist($id);
        if (!$isContestsExist) {
            $resArr = $this->security->errorMessage("Invalid id.");
            return $response->withJson($resArr, $errcode);
        }
             
       
        try {
            $params  =  ["status"=>$status,"id"=>$id];
            $sql     =  "UPDATE contests SET status=:status WHERE id=:id";
            $stmt    =  $this->db->prepare($sql);

            if ($stmt->execute($params)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Status updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Status not updated, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    // Add contests Meta
    public function addcontestsmetaFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['contestid','joinfee','totalwining','winners','maxteams','c','m','s'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $contestid   = $input["contestid"];
        $joinfee     = $input["joinfee"];
        $totalwining = $input["totalwining"];
        $winners     = $input["winners"];
        $maxteams    = $input["maxteams"];
        //$status      = $input["status"];
        $c      	 = $input["c"];
        $m      	 = $input["m"];
        $s      	 = $input["s"];
           
        if ($c != 1 && $m != 1 && $s != 1) {
            $resArr = $this->security->errorMessage("Choose c/m/s");
            return $response->withJson($resArr, $errcode);
        }
            
        if ($m == 1 && $s == 1) {
            $resArr = $this->security->errorMessage("Choose one from m/s");
            return $response->withJson($resArr, $errcode);
        }
            
        if ($maxteams < $winners) {
            $resArr = $this->security->errorMessage("Maxteams should be greater then winners");
            return $response->withJson($resArr, $errcode);
        }

        if ($joinfee > $totalwining) {
            $resArr = $this->security->errorMessage("Joinfee greater then totalwining");
            return $response->withJson($resArr, $errcode);
        }

        $isContestsExist = $this->security->isContestsExist($contestid);
        if (!$isContestsExist) {
            $resArr = $this->security->errorMessage("Contests id not exist.");
            return $response->withJson($resArr, $errcode);
        }
        try {
            $data = [
                        "contestid"   => $contestid,
                           "joinfee"     => $joinfee,
                           "totalwining" => $totalwining,
                           "winners"     => $winners,
                          "maxteams"    => $maxteams,
                          "c"    		=> $c,
                          "m"    		=> $m,
                          "s"    		=> $s,
                    ];
                                     
            $stmt = $this->db->prepare("INSERT INTO contestsmeta (contestid,joinfee,totalwining,winners,maxteams,c,m,s) VALUES(:contestid,:joinfee,:totalwining,:winners,:maxteams,:c,:m,:s)");
            if ($stmt->execute($data)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record added successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not added, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    // add Pool And Prize Break
    public function addPoolAndPrizeBreak($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['contestid','joinfee','totalwining','winners','maxteams','c','m','s'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $contestid   = $input["contestid"];
        $joinfee     = $input["joinfee"];
        $totalwining = $input["totalwining"];
        $winners     = $input["winners"];
        $maxteams    = $input["maxteams"];
        //$status      = $input["status"];
        $c      	 = $input["c"];
        $m      	 = $input["m"];
        $s      	 = $input["s"];

        $prizekeyvalue 	= $input["prizekeyvalue"];

        try {
            $this->db->beginTransaction();
            $res = $this->security->poolAndBreakPrizeAddDB($contestid, $joinfee, $totalwining, $winners, $maxteams, $c, $m, $s, $prizekeyvalue);

            if ($res['error'] == true) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] 	 = $res['msg'];
                $code = $errcode;
            } else {
                $this->db->commit();
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = $res['msg'];
            }
        } catch (PDOException $e) {
            $this->db->rollBack();
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] 	 = $res['msg'];
            $code = \Security::pdoErrorMsg($e->getMessage());
        }
        return $response->withJson($resArr, $code);
    }


    // get contestsMeta
    public function getcontestsmetaFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $dataResArr = [];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['contestid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $contestid  = $input["contestid"];

        $isContestsExist = $this->security->isContestsExist($contestid);
        if (!$isContestsExist) {
            $resArr = $this->security->errorMessage("Contests id not exist.");
            return $response->withJson($resArr, $errcode);
        }


        try {
            $sql = "SELECT id,contestid,joinfee,totalwining,winners,maxteams,c,m,s FROM contestsmeta WHERE contestid=:contestid" ;
            $stmt = $this->db->prepare($sql);
            $params=["contestid"=>$contestid];
            $stmt->execute($params);
            $contests =  $stmt->fetchAll();

            if (!empty($contests)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'contests team.';
                $resArr['data']  = $this->security->removenull($contests);
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] 	 = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }


    //Assign Contests to match
    public function assignconteststomatchFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        //$imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];
      
        $input = $request->getParsedBody();

        //$loginuser = $request->getAttribute('decoded_token_data');  /* Login User id */
        //$uid       = $loginuser['id'];

        $check =  $this->security->validateRequired(['matchid','contestid','status'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        
        $matchid   = $input['matchid'];
        $contestid = $input['contestid'];
        $status    = $input['status'];
            
        if (!in_array($status, ["0","1"])) {
            $resArr = $this->security->errorMessage("Status should be 0/1 ");
            return $response->withJson($resArr, $errcode);
        }
        $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
        if (!$isMatchAlreadyAdded) {
            $resArr = $this->security->errorMessage("Invalid match id");
            return $response->withJson($resArr, $errcode);
        }
        $isContestsExist = $this->security->isContestsExist($contestid);
        if (!$isContestsExist) {
            $resArr = $this->security->errorMessage("Invalid contest id.");
            return $response->withJson($resArr, $errcode);
        }

        if ($this->security->checkMatchFreaz($matchid)) {
            $resArr = $this->security->errorMessage("You can not change contests");
            return $response->withJson($resArr, $errcode);
        }

        if ($this->security->cotestAssignCheck($matchid, $contestid)) {
            $resArr = $this->security->errorMessage("You can not update contests,pools Joined");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $this->db->beginTransaction();

            $res = $this->security->checkAssignconteststomatch($matchid, $contestid);
            if (empty($res)) {
                $data = ["matchid"=>$matchid,"contestid"=>$contestid];
                $stmt = $this->db->prepare("INSERT INTO matchcontest (matchid,contestid) VALUES(:matchid,:contestid)");
            } else {
                $data = ["matchid"=>$matchid,"contestid"=>$contestid,"status"=>$status];
                $stmt = $this->db->prepare("UPDATE matchcontest SET status=:status WHERE matchid=:matchid AND contestid=:contestid ");
            }
            if ($stmt->execute($data)) {
                if ($status==0) {
                    $sql     = "DELETE FROM matchcontestpool WHERE matchid=:matchid AND contestid=:contestid";
                    $stmt    = $this->db->prepare($sql);
                    $params  = ["matchid"=>$matchid,"contestid"=>$contestid];
                    $stmt->execute($params)	;
                }

                $this->db->commit();
                $resArr['code']  	= 0;
                $resArr['error'] 	= false;
                $resArr['msg'] 	= 'Match contest updated.';
            } else {
                $resArr['code']  	= 1;
                $resArr['error'] 	= true;
                $resArr['msg'] 	= 'Match contest not updated, There is some problem';
            }
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    public function assignContestsUpdate($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['matchid','setorrm'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $resArr    = [];
        $matchid   = $input['matchid'];
        $status    = $input['setorrm'];
            
        $status = 1;
        if (!in_array($status, ["0","1"])) {
            $resArr = $this->security->errorMessage("Status should be 0/1 ");
            return $response->withJson($resArr, $errcode);
        }
        $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
        if (!$isMatchAlreadyAdded) {
            $resArr = $this->security->errorMessage("Invalid match id");
            return $response->withJson($resArr, $errcode);
        }

        if ($this->security->checkMatchFreaz($matchid)) {
            $resArr = $this->security->errorMessage("You can not change contests");
            return $response->withJson($resArr, $errcode);
        }


        try {
            $this->db->beginTransaction();
            $favpool=1;
            $sql = "SELECT cm.id,cm.contestid FROM contestsmeta cm WHERE cm.favpool=:favpool GROUP BY cm.contestid" ;
            $stmt = $this->db->prepare($sql);
            $stmt->execute(["favpool"=>$favpool]);
            $contests =  $stmt->fetchAll();

            if ($contests) {
                foreach ($contests as $contest) {
                    $contestid = $contest['contestid'];
                    $matchcontestid = '';
                    $res = $this->security->checkAssignconteststomatch($matchid, $contestid);
                    if (empty($res)) {
                        $data = ["matchid"=>$matchid,"contestid"=>$contestid];
                        $stmt1 = $this->db->prepare("INSERT INTO matchcontest (matchid,contestid) VALUES(:matchid,:contestid)");
                        $stmt1->execute($data);
                        $matchcontestid = $this->db->lastInsertId();
                    } else {
                        $matchcontestid = $res['id'];
                        $status = 1;
                        $data = ["matchid"=>$matchid,"contestid"=>$contestid,"status"=>$status];
                        $stmt1 = $this->db->prepare("UPDATE matchcontest SET status=:status WHERE matchid=:matchid AND contestid=:contestid ");
                        $stmt1->execute($data);
                    }
                    
                    $sql = "SELECT cm.id,cm.contestid FROM contestsmeta cm WHERE cm.favpool=:favpool AND cm.contestid=:contestid";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute(["favpool"=>$favpool,"contestid"=>$contestid]);
                    $contestpools = $stmt->fetchAll();
                 
                    if ($contestpools) {
                        foreach ($contestpools as $contestpool) {
                            $contestmetaid = $contestpool['id'];
                            
                            $sql = "SELECT contestmetaid FROM matchcontestpool WHERE matchid=:matchid AND contestid=:contestid AND contestmetaid=:contestmetaid";
                            $stmt = $this->db->prepare($sql);
                            $stmt->execute(["matchid"=>$matchid,"contestid"=>$contestid,"contestmetaid"=>$contestmetaid]);
                            $res2 =  $stmt->fetch();
                            
                            if (empty($res2)) {
                                $sql = "INSERT INTO matchcontestpool (matchcontestid,matchid,contestid,contestmetaid) VALUES (:matchcontestid,:matchid,:contestid,:contestmetaid)";
                                $stmt = $this->db->prepare($sql);
                                $stmt->execute(["matchcontestid"=>$matchcontestid,"matchid"=>$matchid,"contestid"=>$contestid,"contestmetaid"=>$contestmetaid]);
                            }
                        }
                    }
                }

                $this->db->commit();
                $resArr['code']  	= 0;
                $resArr['error'] 	= false;
                $resArr['msg'] 		= 'Favrate pools assigned.';
            } else {
                $resArr['code']  	= 1;
                $resArr['error'] 	= truerue;
                $resArr['msg'] 		= 'No Favrate pools.';
            }
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }
    //Assign Contests Pool to match
    public function assigncontestspooltomatchFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];
      
        $input = $request->getParsedBody();

        $loginuser = $request->getAttribute('decoded_token_data');  /* Login User id */
        $uid       = $loginuser['id'];


        $check =  $this->security->validateRequired(['matchid','contestid','matchcontestid','poolcontestid','isassign'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        
        $matchid   = $input['matchid'];
        $contestid = $input['contestid'];
        $matchcontestid = $input['matchcontestid'];
        $pool 			= $input['poolcontestid'];
        $isassign 		= $input['isassign'];

        if (isset($input['atype']) && $input['atype']=='favStatusUpdate') {
            if ($this->updateFavStatusPool($input)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not added, There is some problem';
            }
            return $response->withJson($resArr, $code);
        }

        if (!in_array($isassign, ['0','1'])) {
            $resArr = $this->security->errorMessage("isassign should be 0/1");
            return $response->withJson($resArr, $errcode);
        }

        $isMatchcontestidExist = $this->security->isMatchcontestidExist($matchcontestid);
        if (empty($isMatchcontestidExist)) {
            $resArr = $this->security->errorMessage("Invalid matchcontestid");
            return $response->withJson($resArr, $errcode);
        }

        $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
        if (!$isMatchAlreadyAdded) {
            $resArr = $this->security->errorMessage("Invalid match id");
            return $response->withJson($resArr, $errcode);
        }

        $isContestsExist = $this->security->isContestsExist($contestid);
        if (!$isContestsExist) {
            $resArr = $this->security->errorMessage("Invalid contest id.");
            return $response->withJson($resArr, $errcode);
        }

        if ($this->security->checkMatchFreaz($matchid)) {
            $resArr = $this->security->errorMessage("You can not change contests");
            return $response->withJson($resArr, $errcode);
        }
 
        $isCheckMatchContest = $this->security->isCheckMatchContest($matchcontestid, $matchid, $contestid);
        if (empty($isCheckMatchContest)) {
            $resArr = $this->security->errorMessage("Invalid matchid or not active matchcontest.");
            return $response->withJson($resArr, $errcode);
        }

        $checkContestMetaId = $this->security->checkContestMetaId($pool, $contestid);
        if (empty($checkContestMetaId)) {
            $resArr = $this->security->errorMessage("Invalid Pool id");
            return $response->withJson($resArr, $errcode);
        }

        $resPoolCheck = $this->security->poolAssignCheck($pool, $contestid, $matchid);
        if (!empty($resPoolCheck)) {
            $resArr = $this->security->errorMessage("Pool joined, can't be changed");
            return $response->withJson($resArr, $errcode);
        }
                  
        try {
            $this->db->beginTransaction();

            /*$checkmatchcontestpool = $this->security->checkmatchcontestpool($matchcontestid);
            if(!empty($checkmatchcontestpool)){
                $delRes = $this->security->deletematchcontestpool($matchcontestid);
                if(!$delRes){
                  $resArr = $this->security->errorMessage("Not updated,There is some problem.");
                  return $response->withJson($resArr,$errcode);
                }
            }*/
            $params = ["matchid"=>$matchid,"contestid"=>$contestid,"matchcontestid"=>$matchcontestid,"contestmetaid"=>$pool];
            $stmtDel = $this->db->prepare("DELETE FROM matchcontestpool WHERE matchid=:matchid AND contestid=:contestid AND matchcontestid=:matchcontestid AND contestmetaid=:contestmetaid");
            $stmtDel->execute($params);

            //$params = ["matchid"=>$matchid,"contestid"=>$contestid,"matchcontestid"=>$matchcontestid,"contestmetaid"=>$pool];
            if ($isassign == 1) {
                $stmt = $this->db->prepare("INSERT INTO matchcontestpool (matchcontestid,contestmetaid,contestid,matchid) VALUES(:matchcontestid,:contestmetaid,:contestid,:matchid)");
                $stmt->execute($params);
            }
            
            $this->db->commit();

            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] 	 = 'Contest updated successfully.';
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
            $this->db->rollBack();
        }
        return $response->withJson($resArr, $code);
    }

    public function updateFavStatusPool($input)
    {
        $pool 		= $input['poolcontestid'];
        $contestid 		= $input['contestid'];
        $favpool	= ($input['favpool'])?0:1;
        $params = ["id"=>$pool,'contestid'=>$contestid,"favpool"=>$favpool];
        $sql = "UPDATE contestsmeta SET favpool=:favpool  WHERE id=:id AND contestid=:contestid";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            /* $params = ["id"=>$pool,'contestid'=>$contestid,"favpool"=>1];
            $sql = "SELECT count(id) as id FROM contestsmeta WHERE id=:id AND contestid=:contestid AND favpool=:favpool";
            $stmt = $this->db->prepare($sql);

            if($stmt->execute($params)){
                $rst =  $stmt->fetch();
                $favstatus=($rst['id']>0)?1:0;
                $params = ["id"=>$contestid,"favstatus"=>$favstatus];
                $sql = "UPDATE contests SET favstatus=:favstatus  WHERE id=:id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            } */
            return true;
        }
        return false;
    }
    //Assign Contests Pool to match
    public function assigncontestspooltomatchFuncOld($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];
      
        $input = $request->getParsedBody();

        $loginuser = $request->getAttribute('decoded_token_data');  /* Login User id */
        $uid       = $loginuser['id'];

        $check =  $this->security->validateRequired(['matchid','contestid','matchcontestid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        
        $matchid   = $input['matchid'];
        $contestid = $input['contestid'];
        $matchcontestid = $input['matchcontestid'];
        $pools = $input['pools'];

        if (empty($pools)) {
            $resArr = $this->security->errorMessage("Pools should not be empty");
            return $response->withJson($resArr, $errcode);
        }

        $isMatchcontestidExist = $this->security->isMatchcontestidExist($matchcontestid);
        if (empty($isMatchcontestidExist)) {
            $resArr = $this->security->errorMessage("Invalid matchcontestid");
            return $response->withJson($resArr, $errcode);
        }

        $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
        if (!$isMatchAlreadyAdded) {
            $resArr = $this->security->errorMessage("Invalid match id");
            return $response->withJson($resArr, $errcode);
        }

        $isContestsExist = $this->security->isContestsExist($contestid);
        if (!$isContestsExist) {
            $resArr = $this->security->errorMessage("Invalid contest id.");
            return $response->withJson($resArr, $errcode);
        }

        if ($this->security->checkMatchFreaz($matchid)) {
            $resArr = $this->security->errorMessage("You can not change contests");
            return $response->withJson($resArr, $errcode);
        }

        $isCheckMatchContest = $this->security->isCheckMatchContest($matchcontestid, $matchid, $contestid);
        if (empty($isCheckMatchContest)) {
            $resArr = $this->security->errorMessage("Invalid matchid or not active matchcontest.");
            return $response->withJson($resArr, $errcode);
        }


        if ($this->security->cotestAssignCheck($matchid, $contestid)) {
            $resArr = $this->security->errorMessage("Pool joined, can't be changed");
            return $response->withJson($resArr, $errcode);
        }

        foreach ($pools as $pool) {

             //if($this->security->poolAssignCheck($pool,$contestid,$matchid))
                 

            $checkContestMetaId = $this->security->checkContestMetaId($pool, $contestid);
            if (empty($checkContestMetaId)) {
                $resArr = $this->security->errorMessage("Invalid Pool id");
                return $response->withJson($resArr, $errcode);
            }
        }
                  
        try {
            $checkmatchcontestpool = $this->security->checkmatchcontestpool($matchcontestid);
            if (!empty($checkmatchcontestpool)) {
                $delRes = $this->security->deletematchcontestpool($matchcontestid);
                if (!$delRes) {
                    $resArr = $this->security->errorMessage("Not updated,There is some problem.");
                    return $response->withJson($resArr, $errcode);
                }
            }
            foreach ($pools as $pool) {
                $params = ["matchid"=>$matchid,"contestid"=>$contestid,"matchcontestid"=>$matchcontestid,"contestmetaid"=>$pool];
                $stmt = $this->db->prepare("INSERT INTO matchcontestpool (matchcontestid,contestmetaid,contestid,matchid) VALUES(:matchcontestid,:contestmetaid,:contestid,:matchid)");
                if (!$stmt->execute($params)) {
                    $resArr = $this->security->errorMessage("Not updated,There is some problem.");
                    return $response->withJson($resArr, $errcode);
                }
            }
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] 	 = 'Contest assigned successfully.';
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    // get Match contest list
    public function getmatchcontestlistFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input   = $request->getParsedBody();
        $dataResArr = [];
        $input   =  $request->getParsedBody();
        $check   =  $this->security->validateRequired(['matchid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $matchid  = $input["matchid"];
        $isMatchAlreadyAdded = $this->security->isMatchAlreadyAdded($matchid);
        if (!$isMatchAlreadyAdded) {
            $resArr = $this->security->errorMessage("Invalid matchid");
            return $response->withJson($resArr, $errcode);
        }
        $dataResArr = [];
        try {
         
        //$sql   = "SELECT mc.id,mc.matchid,mc.contestid,mc.status,cm.favpool FROM matchcontest mc LEFT JOIN contestsmeta cm ON mc.contestid=cm.id WHERE mc.matchid=:matchid AND mc.status=:status";
            $sql   = "SELECT IFNULL(cpp.cpybfrtim,'0') as cpybfrtim,IFNULL(cpp.status,'0') as cpypoolstatus,cpp.order_set,cpp.bonus_amount,mc.id,mc.matchid,mc.contestid,mc.status,IFNULL(cm.id,0) as favpool FROM matchcontest mc LEFT JOIN contestsmeta cm ON (mc.contestid=cm.contestid AND cm.favpool=1) LEFT JOIN cpypoolproc cpp ON (mc.contestid=cpp.contestid AND cpp.matchid=:matchid) WHERE mc.matchid=:matchid AND mc.status=:status GROUP BY mc.contestid";
            $sth   = $this->db->prepare($sql);
            $param = ["matchid"=>$matchid,"status"=>ACTIVESTATUS];
            $sth->execute($param);
            $res   = $sth->fetchAll();

            /* $sql  = "SELECT id,title,subtitle,contestlogo FROM contests WHERE status=:status ORDER BY id DESC" ;
             $stmt = $this->db->prepare($sql);
             $params = ["status"=>ACTIVESTATUS];
             $stmt->execute($params);
             $matchContests = $stmt->fetchAll();

             foreach ($matchContests as $contest) {

               $checkMatchContestStatus = $this->security->checkMatchContestStatus($matchid,$contest['id']);
               if(!empty($checkMatchContestStatus)){
                      $contest['conteststatus'] = $checkMatchContestStatus['id']; //match contest id set
               }else{
                      $contest['conteststatus'] = 0;
               }

               if(!empty($contest['contestlogo'])){
                  $contest['contestlogo'] = $baseurl.'/uploads/contests/'.$contest['contestlogo'];
               }

               $dataResArr[] = $contest;
             }*/
         
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Match contests list.';
                $resArr['data']  = $this->security->removenull($res);
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
   
        return $response->withJson($resArr, $code);
    }

    //get match contest pool list
    /*
    $app->post('/getmatchcontestpoollist',function(Request $request, Response $response, array $args) {

            global $settings,$baseurl;
            $code    = $settings['settings']['code']['rescode'];
            $errcode = $settings['settings']['code']['errcode'];

            $input = $request->getParsedBody();
            $dataResArr = [];

            $input = $request->getParsedBody();
            $check =  $this->security->validateRequired(['matchid','contestid'],$input);
            if(isset($check['error'])) {
                return $response->withJson($check);
            }

            $matchid       = $input["matchid"];
            $contestid     = $input["contestid"];
               //$matchcontestid  = $input["matchcontestid"];

            $isContestsExist = $this->security->isContestsExist($contestid);
            if(!$isContestsExist)
            {
                $resArr = $this->security->errorMessage("Contests id not exist.");
                return $response->withJson($resArr,$errcode);
            }

            $checkMatchContest = $this->security->checkMatchContest($matchid,$contestid);
            if(empty($checkMatchContest))
            {
                $resArr = $this->security->errorMessage("Invalid contest OR Inactive matchcontest");
                return $response->withJson($resArr,$errcode);
            }

            try{

                $dataResArr = [];

                $sql   = "SELECT id,contestmetaid FROM matchcontestpool WHERE matchid=:matchid AND contestid=:contestid";
                $sth   = $this->db->prepare($sql);
                $param = ["matchid"=>$matchid,"contestid"=>$contestid];
                $sth->execute($param);
                $res  = $sth->fetchAll();


                $sql = "SELECT id,contestid,joinfee,totalwining,winners,maxteams FROM contestsmeta WHERE contestid=:contestid" ;
                $stmt = $this->db->prepare($sql);
                $params=["contestid"=>$contestid];
                $stmt->execute($params);
                $matchPools =  $stmt->fetchAll();

                foreach ($matchPools as $pool) {
                   $checkMatchContestPoolStatus = $this->security->checkMatchContestPoolStatus($matchcontestid,$pool['id']);
                   if(!empty($checkMatchContestPoolStatus)){
                          $pool['matchpoolstatus'] = 1; //match contest pool status
                   }else{
                          $pool['matchpoolstatus'] = 0;
                   }
                   $dataResArr[] = $pool;
                }


               if(!empty($res)) {
                    $resArr['code']  = 0;
                    $resArr['error'] = false;
                    $resArr['msg']   = 'Match contest pool list';
                    $resArr['data']  = $this->security->removenull($res);
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

        });*/

    //get match contest pool list
    public function getmatchcontestpoollistFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $dataResArr = [];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['matchid','contestid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $matchid         = $input["matchid"];
        $contestid       = $input["contestid"];
        //$matchcontestid  = $input["matchcontestid"];

        $isContestsExist = $this->security->isContestsExist($contestid);
        if (!$isContestsExist) {
            $resArr = $this->security->errorMessage("Contests id not exist.");
            return $response->withJson($resArr, $errcode);
        }

        $checkMatchContest = $this->security->checkMatchContest($matchid, $contestid);
        if (empty($checkMatchContest)) {
            $resArr = $this->security->errorMessage("Invalid contest OR Inactive matchcontest");
            return $response->withJson($resArr, $errcode);
        }

        /* $isMatchcontestidExist = $this->security->isMatchcontestidExist($matchcontestid);
          if(empty($isMatchcontestidExist))
          {
         $resArr = $this->security->errorMessage("Invalid matchcontestid");
         return $response->withJson($resArr,$errcode);
          }
*/

        try {
            $dataResArr = [];
            $sql = "SELECT id,contestid,joinfee,totalwining,winners,maxteams,c,m,s,favpool FROM contestsmeta WHERE contestid=:contestid " ;
            //AND cp=0
            $stmt = $this->db->prepare($sql);
            $params=["contestid"=>$contestid];
            $stmt->execute($params);
            $matchPools =  $stmt->fetchAll();
            foreach ($matchPools as $pool) {
                $checkMatchContestPoolStatus = $this->security->checkMatchContestPoolStatus($matchid, $contestid, $pool['id']);

                if (!empty($checkMatchContestPoolStatus)) {
                    $pool['matchpoolstatus'] = 1; //match contest pool status
                } else {
                    $pool['matchpoolstatus'] = 0;
                }
                $dataResArr[] = $pool;
            }

            if (!empty($dataResArr)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Match contest pool list';
                $resArr['data']  = $this->security->removenull($dataResArr);
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not found,add poolcontest to this contest';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }

    // Add pool prize break
    public function addpoolprizebreaksFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check = $this->security->validateRequired(['poolcontestid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $poolcontestid  = $input["poolcontestid"];
        $prizekeyvalue 	= $input["prizekeyvalue"];
        
        if (empty($prizekeyvalue) || !is_array($prizekeyvalue)) {
            $resArr = $this->security->errorMessage("Input prize key and value");
            return $response->withJson($resArr, $errcode);
        }

        $checkContestMetaIdPoolBreak = $this->security->checkContestMetaIdPoolBreak($poolcontestid);
        if (empty($checkContestMetaIdPoolBreak)) {
            $resArr = $this->security->errorMessage("Invalid poolcontestid");
            return $response->withJson($resArr, $errcode);
        }
                                  
        try {
            //Del record
            $sqlGet = "SELECT id FROM poolprizebreaks WHERE poolcontestid=:poolcontestid";
            $stmtGet = $this->db->prepare($sqlGet);
            $stmtGet->execute(["poolcontestid"=>$poolcontestid]);
            $resGet = $stmtGet->fetch();

            if (!empty($resGet)) {
                $dataDel = ["poolcontestid"=>$poolcontestid];
                $sqlDel  = "DELETE FROM poolprizebreaks WHERE poolcontestid=:poolcontestid";
                $stmtDel = $this->db->prepare($sqlDel);
                if (!$stmtDel->execute($dataDel)) {
                    $resArr = $this->security->errorMessage("Record not added, There is some problem");
                    return $response->withJson($resArr, $errcode);
                }
            }

            foreach ($prizekeyvalue as $row) {
                $data = ["poolcontestid"=>$poolcontestid,"pmin"=>$row['pmin'],"pmax"=>$row['pmax'],"pamount"=>$row['pamount']];
                $stmt = $this->db->prepare("INSERT INTO poolprizebreaks (poolcontestid,pmin,pmax,pamount) VALUES(:poolcontestid,:pmin,:pmax,:pamount)");
                if ($stmt->execute($data)) {
                    $resArr['code']  = 0;
                    $resArr['error'] = false;
                    $resArr['msg']	 = 'Record added successfully.';
                } else {
                    $resArr['code']  = 1;
                    $resArr['error'] = true;
                    $resArr['msg'] = 'Record not added, There is some problem';
                }
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code 			 = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }
 
    // Get pool prize break
    public function getpoolprizebreaksFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['poolcontestid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $poolcontestid        = $input["poolcontestid"];
        $checkContestMetaIdPoolBreak = $this->security->checkContestMetaIdPoolBreak($poolcontestid);
        if (empty($checkContestMetaIdPoolBreak)) {
            $resArr = $this->security->errorMessage("Invalid Pool id");
            return $response->withJson($resArr, $errcode);
        }
                                  
        try {
            $sqlGet = "SELECT poolcontestid,pmin,pmax,pamount FROM poolprizebreaks WHERE poolcontestid=:poolcontestid";
            $stmtGet = $this->db->prepare($sqlGet);
            $stmtGet->execute(["poolcontestid"=>$poolcontestid]);
            $resGet = $stmtGet->fetchAll();
            if (!empty($resGet)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Pool prize breaks';
                $resArr['data'] = $this->security->removenull($resGet);
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] 	 = 'Record not found';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }


    // Get Player Images
    public function getplayerimgFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['pid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $pid = $input["pid"];
        /*$isPlayeridExist = $this->security->isPlayeridExist($pid);
           if(!$isPlayeridExist)
           {
            $resArr = $this->security->errorMessage("Invalid player id.");
            return $response->withJson($resArr,$errcode);
           }*/

        try {
            $pimgUrl  	= $baseurl.'/uploads/players/';
            $sqlGet 	= "SELECT id,CONCAT('".$pimgUrl."',playerimage) as pimg,playerimage FROM playerimg WHERE pid=:pid";
            $stmtGet 	= $this->db->prepare($sqlGet);
            $stmtGet->execute(["pid"=>$pid]);
            $resGet 	= $stmtGet->fetchAll();

            if (!empty($resGet)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] 	 = 'Player Images';
                $resArr['data']  = $this->security->removenull($resGet);
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] 	 = 'Record not found';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }


    // Add/Update predection
    public function addpridictionFunction($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['matchid','prediction'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $matchid       = $input["matchid"];
        $prediction    = $input["prediction"];
        $created       = time();

        try {
            $dir = "./uploads/prediction/".$matchid;
            $addset = @file_get_contents($dir);
                               
            if ($addset === false) {
                $datas  =
                          [
                           'matchid' 	=> $matchid,
                           'created' 	=> $created,
                           'prediction' => $prediction,
                        ];
                $fh = fopen($dir, 'w');
            } else {
                $datas = json_decode($addset, true);
                $datas  =
                          [
                           'matchid' 	=> $matchid,
                           'prediction' => $prediction,
                        ];
            }

            if (file_put_contents($dir, json_encode($datas))) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Save successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Not save, There is some problem';
            }
        } catch (\Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    public function getpridictionFunction($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['matchid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $matchid  = $input["matchid"];

        try {
            $dir = "./uploads/prediction/".$matchid;
            $addset = @file_get_contents($dir);
                               
            if ($addset === false) {
                $datas  =
                          [
                           'matchid' 	=> $matchid,
                           'created' 	=> $created,
                           'prediction' => $prediction,
                        ];
                $fh = fopen($dir, 'w');
            } else {
                $datas = json_decode($addset, true);
                $datas  =
                          [
                           'matchid' 	=> $matchid,
                           'prediction' => $prediction,
                        ];
            }

            if (file_put_contents($dir, json_encode($datas))) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Save successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Not save, There is some problem';
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    //Copy Pool Status update
    public function copyPoolStatusFun($request, $response)
    {
        global $settings;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $searchSql  = "";
        $params=[];
        $paging = $where_cond= "";

        $check =  $this->security->validateRequired(['matchid','contestid','cpybfrtim','cpypoolstatus','order_set','bonus_amount'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $matchid    = $input['matchid'];
        $contestid  = $input['contestid'];
        $cpybfrtim  = $input['cpybfrtim'];
        $status     = $input['cpypoolstatus'];
        $order_set  = $input['order_set'];
        $bonus_amount  = (isset($input['bonus_amount']) && !empty($input['bonus_amount']))?$input['bonus_amount']:0;
        $poolid     = (isset($input['poolid']) && !empty($input['poolid']))?$input['poolid']:0;
        $addquery='';
        $dataResArr  = [];
        try {
            $data=['matchid'=>$matchid,'contestid'=>$contestid];

            if ($poolid) {
                $data['poolid']=$poolid;
                $addquery=" AND poolid=:poolid";
            }
        
            $sqlcpyproc 	= "SELECT id FROM cpypoolproc WHERE matchid=:matchid AND contestid=:contestid".$addquery;
            $stmtGet 	= $this->db->prepare($sqlcpyproc);
            $stmtGet->execute($data);
            $id 	= $stmtGet->fetch();

            $data['cpybfrtim'] 	= $cpybfrtim;
            $data['status'] 	= $status;
            $data['poolid'] 	= $poolid;
            $data['order_set']  = $order_set;
            $data['bonus_amount'] = $bonus_amount;
            $smsg=($status)?'activated':'deactivated';
            //	return $response->withJson($order);
            if ($id) {
                $data['id']= $id['id'];
                $sql     = "UPDATE cpypoolproc SET matchid=:matchid,contestid=:contestid,cpybfrtim=:cpybfrtim,status=:status,poolid=:poolid,order_set=:order_set,bonus_amount=:bonus_amount WHERE id=:id AND matchid=:matchid";
                $stmt    = $this->db->prepare($sql);
                $res=$stmt->execute($data);
                $msg="Copy Pool $smsg successfully.";
            } else {
                $stmt = $this->db->prepare("INSERT INTO cpypoolproc (matchid,contestid,cpybfrtim,poolid,status,order_set,bonus_amount) VALUES (:matchid,:contestid,:cpybfrtim,:poolid,:status,:order_set,:bonus_amount)");
                $res=$stmt->execute($data);
                $msg='Copy Pool activated successfully.';
            }
            if ($res) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = $msg ;
            } else {
                $resArr['code']   = 1;
                $resArr['error']  = true;
                $resArr['msg']    = "Record not updated";
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
      
        return $response->withJson($resArr, $code);
    }

    // add notification global

    public function addNotificationGlobal($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $pushLimit=499;
        $ios=[];
        $android=[];
        $setUserId=[];
        $imgUrl='';
        if (isset($input['id']) && !empty($input['id']) && isset($input['atype']) && in_array($input['atype'], ['statusUpdate','delete'])) {
            if (!isset($input['sendAll']) || !in_array($input['sendAll'], [0,1])) {
                $resArr = $this->security->errorMessage("Invalid or empty field sendAll!");
                return $response->withJson($resArr, $errcode);
            }
            $res=$this->statusAndDeleteNotify($input);
            return $response->withJson($res['resArr'], $code);
        } else {
            $check =  $this->security->validateRequired(['title','message','atype','addScheduler'], $input);
        }
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $title   = $input["title"];
        $message = $input["message"];
        $img     = '';
        $atype   = $input["atype"];

        $addScheduler   = $input["addScheduler"];
        $dateT   = strtotime($input["dateTime"]);
        $dateTime = date("Y-m-d h:i:a", $dateT);
        
        $userid	 = (isset($input['userid']) && !empty($input['userid']))?json_decode($input['userid']):null;//$input['userid'];//(isset($input["userid"]) && !empty($input['userid']))?(explode(",",$input['userid'])):[];
        $created = time();
        $sbsql = "";
        if ($addScheduler == 1) {
            $data =  ['title'=>$title,'message'=>$message,'ntype'=>ADMIN_NOTIFY,'addScheduler'=>$addScheduler,'dateTime'=>$dateTime];
        } else {
            $data =  ['title'=>$title,'message'=>$message,'ntype'=>ADMIN_NOTIFY,'addScheduler'=>$addScheduler];
        }
      
        if (!in_array($atype, ['add','edit'])) {
            $resArr = $this->security->errorMessage("Invalid atype");
            return $response->withJson($resArr, $errcode);
        }
        if (!empty($input["img"])) {
            $sbsql = " ,img=:img ";
            $img = $input["img"];
            $data['img']=$img;
            $imgUrl = $baseurl.'/uploads/notifications/'.$img;
        }
        $data['userid']=(object)[];
        if (!empty($userid)) {
            $countids=count((array)($userid));
            if ($countids>0) {
                $data['userid']=array_keys((array)($userid));
                $data['sendAll']=0;
                $ids['tusers']=$countids;
            } else {
                $idsQuery = "SELECT count(id) AS tusers FROM users ";
                $ids = $this->db->prepare($idsQuery);
                $ids->execute();
                $ids = $ids->fetch();

                $data['sendAll']=1;
            }
        } else {
            $idsQuery = "SELECT count(id) AS tusers FROM users ";
            $ids = $this->db->prepare($idsQuery);
            $ids->execute();
            $ids = $ids->fetch();

            $data['sendAll']=1;
        }
             
        try {
            $collection = $this->mdb->notification;
            $notification_data = ['title'=>$data['title'],'body'=>$data['message'],'ntype'=>ADMIN_NOTIFY,'notify_id'=>1,'image'=>$imgUrl];
            if ($atype=='add') {
                if (!empty($dateT)) {
                    $data['created']=$dateT;
                } else {
                    $data['created']=$created;
                }
              
                $pages=($ids['tusers']>$pushLimit)?($ids['tusers']/$pushLimit):1;

                if ($pages>1) {
                    if ($data['sendAll']==0) {
                        ksort($data['userid']);
                        $suserslimit=0;
                        $uids=array_slice($data['userid'], 0, $pushLimit);
                        for ($i=0; $i<$pages ; $i++) {
                            $offset = (($i+1)-1)*$pushLimit;
                            $paging = " limit ".$offset.",".$pushLimit;
                            $selectedUsers=array_slice($data['userid'], $suserslimit, $pushLimit);
                            //$selectedUsers=($data['sendAll']==0)?$data['userid']:0;
                            $setRslt=$this->getNotifyUsersData(['userid'=>$selectedUsers,'paging'=>'']);
                            if ($setRslt['error']==false) {
                                $ios[$i][]=$setRslt['ios'];
                                $android[$i][]=$setRslt['android'];
                                $setUserId=(!empty($setUserId))?$setRslt['setUserId']+$setUserId:$setRslt['setUserId'];
                            }
                            $suserslimit=$suserslimit+$pushLimit;
                        }
                    } else {
                        for ($i=0; $i<$pages ; $i++) {
                            $offset = (($i+1)-1)*$pushLimit;
                            $paging = " limit ".$offset.",".$pushLimit;
                            $selectedUsers=($data['sendAll']==0)?$data['userid']:0;
                            $setRslt=$this->getNotifyUsersData(['userid'=>$selectedUsers,'paging'=>$paging]);
                            if ($setRslt['error']==false) {
                                $ios[$i][]=$setRslt['ios'];
                                $android[$i][]=$setRslt['android'];
                                $setUserId=$setRslt['setUserId']+$setUserId;
                            }
                        }
                    }
                } else {
                    $selectedUsers=($data['sendAll']==0)?$data['userid']:0;
                    $setRslt=$this->getNotifyUsersData(['userid'=>$selectedUsers,'paging'=>'']);
                    if ($setRslt['error']==false) {
                        $ios[0][]=$setRslt['ios'];
                        $android[0][]=$setRslt['android'];
                        $setUserId=$setRslt['setUserId'];
                    }
                }
                $data['userid']=(Object)$setUserId;
                $res=$collection->insertOne($data);
                $msg = "Notification sent successfully.";
                if ($addScheduler == 0) {
                    for ($i=0; $i<$pages ; $i++) {
                        if (count($ios[$i][0])>0) {
                            $ios_data['token']=$ios[$i][0];
                            $ios_data['devicetype']='iphone';
                            $ios_data=$ios_data+$notification_data;
                            $this->security->notifyUser($ios_data);
                        }
                        if (count($android[$i][0])>0) {
                            $android_data['token']=$android[$i][0];
                            $android_data['devicetype']='android';
                            $android_data=$android_data+$notification_data;
                            $this->security->notifyUser($android_data);
                        }
                    }
                }
            } else {
                if (!isset($input['id']) || empty($input['id'])) {
                    $resArr = $this->security->errorMessage("Invalid id");
                    return $response->withJson($resArr, $errcode);
                }

                $id= new ObjectId($input['id']);
                if ($atype=='delete') {
                    $res=$collection->deleteOne(["_id"=>$id]);
                    $msg = "Notification deleted successfully.";
                } else {
                    $res=$collection->updateOne(["_id"=>$id], ['$set'=>$data]);
                    $msg = "Notification updated successfully.";
                }
            }
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = $msg;
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] 	 = "data not processes";
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }



    //send notification by time cron

    public function sendNotificationBYTime($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $offset=0;
        //  $rstSort=['created'=>(-1)];
        $pushLimit=499;
        $setUserId=[];

        try {
            $collection = $this->mdb->notification;
            $imgUrl = $baseurl.'/uploads/notifications/';
            $now = date("Y-m-d h:i:a", time());
            $res=$collection->find(['dateTime' => $now])->toArray();
            $res = json_decode(json_encode($res), true);
            //   $dateTime = json_decode(json_encode($dateTime), true);
			$var2 = count($res[0]['userid']);
			$pg=$var/248; 
            if ($res[0]['sendAll'] == 0) {
                $notification_data = ['title'=>$res[0]['title'],'body'=>$res[0]['message'],'ntype'=>ADMIN_NOTIFY,'notify_id'=>1,'image'=>$res[0]['imgUrl']];
                $selectedUsers= array_keys($res[0]['userid']);
                $setRslt=$this->getNotifyUsersData(['userid'=>$selectedUsers,'paging'=>'']);
                if ($setRslt['error'] !='true') {
                    $ios[0][]=$setRslt['ios'];
                    $android[0][]=$setRslt['android'];
                    $setUserId=$setRslt['setUserId'];
                }
                for ($i=0; $i<1 ; $i++) {
                    if (count($ios[$i][0])>0) {
                        $ios_data['token']=$ios[$i][0];
                        $ios_data['devicetype']='iphone';
                        $ios_data=$ios_data+$notification_data;
                        $this->security->notifyUser($ios_data);
                    }
                    if (count($android[$i][0])>0) {
                        $android_data['token']=$android[$i][0];
                        $android_data['devicetype']='android';
                        $android_data=$android_data+$notification_data;
                        $this->security->notifyUser($android_data);
                    }
                }
            } elseif ($res[0]['sendAll'] == 1) {
               $pages=($var2>$pushLimit)?($var2/$pushLimit):1;
                $notification_data = ['title'=>$res[0]['title'],'body'=>$res[0]['message'],'ntype'=>ADMIN_NOTIFY,'notify_id'=>1,'image'=>$res[0]['imgUrl']];

                for ($i=0; $i<$pages ; $i++) {
                    $offset = (($i+1)-1)*$pushLimit;
                    $paging = " limit ".$offset.",".$pushLimit;
                    $selectedUsers=($res[0]['sendAll']==0)?$res[0]['userid']:0;
                    $setRslt=$this->getNotifyUsersData(['userid'=>$selectedUsers,'paging'=>$paging]);
                 
                    if ($setRslt['error']==false) {
                        $ios[$i][]=$setRslt['ios'];
                        $android[$i][]=$setRslt['android'];
                        $setUserId=$setRslt['setUserId']+$setUserId;
                    }
				}
				 
                for ($i=0; $i<$pages ; $i++) {
                    if (count($ios[$i][0])>0) {
                        $ios_data['token']=$ios[$i][0];
                        $ios_data['devicetype']='iphone';
                        $ios_data=$ios_data+$notification_data;
                        $this->security->notifyUser($ios_data);
                    }
                    if (count($android[$i][0])>0) {
                        $android_data['token']=$android[$i][0];
                        $android_data['devicetype']='android';
                        $android_data=$android_data+$notification_data;
						$this->security->notifyUser($android_data);
                    }
                }
            }
 
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'message send successfully';
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
        }
        return $response->withJson($resArr, $code);
    }






    public function getNotifyUsersData($data)
    {
        $error=false;
        try {
            $ios=$android=[];
            $setUserId=[];
            // $sqlQuery=" Where devicetoken !='' ";
            $selectedUsers=($data['userid']>0)? " where id in (".implode(",", $data['userid']).") ":"Where 1=1";
            $idsQuery = "SELECT id,devicetoken,devicetype FROM users ".$selectedUsers.$data['paging'];
            $ids = $this->db->prepare($idsQuery);
            $ids->execute();
            $ids = $ids->fetchAll();
            $apk=0;
            $ipk=0;
            foreach ($ids as $uid => $value) { //echo $uid; print_r($value);
                switch ($value['devicetype']) {
                case 'android':
                $android[$apk]=$value['devicetoken'];
                $apk++;
                break;
                case 'iphone':
                $ios[$ipk]=$value['devicetoken'];
                $ipk++;
                break;
            }
                $useridobj[$value['id']]=0;
                $setUserId=(!empty($setUserId))?$setUserId+$useridobj:$useridobj;
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
            $error=true;
        }

        return ['ios'=>$ios,'android'=>$android,'setUserId'=>$setUserId,'error'=>$error];
    }
    public function statusAndDeleteNotify($input)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        try {
            $atype=$input['atype'];
            $notifyType=$input['sendAll'];
            $collection = $this->mdb->notification;
            $id= new ObjectId($input['id']);
            $userid= "2072";
            if ($atype=='delete') {
                $res=$collection->deleteOne(["_id"=>$id]);
                $msg = "Notification deleted successfully.";
            } else {
                $whereData=["_id"=>$id];
                if (empty($notifyType) || $notifyType==0) {
                    $whereData=$whereData + ['userid.'.$userid=>['$in'=>[0,1]]];
                }
                $res=$collection->findOne($whereData);
                if ($res) {
                    $userids=(array)$res['userid'];
                    $newobject=[$userid=>1];
                           
                    if (count($userids)>0) {
                        $useridsNew=$newobject+$userids;
                    } else {
                        $useridsNew=$newobject;
                    }
                    $data['userid']=(object)$useridsNew;
                    $res=$collection->updateOne($whereData, ['$set'=>$data]);
                    $msg = "Notification updated successfully.";
                }
            }
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = $msg;
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] 	 = "Invalid Notification";
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return ['resArr'=>$resArr,'code'=>$code];
    }
    //Get Notification
    public function getNotification($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $userid=(isset($input['userid']) && !empty($input['userid']))?$input['userid']:'';
        $whereData  = $matchdata   = [];
        $puserid=1;
        $offset=0;
        $page = (int)((isset($input['page']) && $input['page']>0)?$input['page']:1);
        $rstSort=['created'=>(-1)];
        $limit  = (isset($input['limit']) && !empty($input['limit']))?$input['limit']:10;
        $lim  = intval((isset($input['limit']) && !empty($input['limit']))?$input['limit']:10);
        $skiprecord=intval(($page>1)?(($page-1)*$lim):0);

        try {
            $collection = $this->mdb->notification;
            //FROM_UNIXTIME
            if (!empty($input['search']) && isset($input['search'])) {
                $search = $input['search'];
                $whereData = $whereData+["title"=>"/".$search."/"];
            }
            $imgUrl = $baseurl.'/uploads/notifications/';
            if (!empty($userid)) {
                $puserid=['$cond'=>['if'=>['$eq'=>['$userid.'.$userid ,1] ],'then'=>1,'else'=>0]];
                //'id'=>['$toString'=>'$_id'];
                $matchdata['$or']=[['userid.'.$userid=>['$in'=>[0,1]]],['sendAll'=>1]];
                $projectData=["_id"=>1,"title"=>1,'created'=>1,'img'=>1,'message'=>1,'addScheduler'=>1,'dateTime'=>1,'userid'=>$puserid,'sendAll'=>1];
                $res=$collection->aggregate([['$match'=>$matchdata],['$project'=>$projectData],['$skip'=>$skiprecord],['$limit'=>$lim],['$sort'=>$rstSort]])->toArray();
            } else {
                //'id'=>['$toString'=>'$_id']
                $res=$collection->aggregate([['$match'=>['ntype'=>ADMIN_NOTIFY]],['$project'=>["_id"=>1,"title"=>1,'created'=>1,'img'=>1,'message'=>1,'addScheduler'=>1,'dateTime'=>1,'userid'=>$puserid,'sendAll'=>1]],['$sort'=>$rstSort],['$skip'=>$skiprecord],['$limit'=>$lim]])->toArray();
                $resp=$collection->aggregate([['$match'=>['ntype'=>ADMIN_NOTIFY]],['$project'=>["_id"=>1,"title"=>1,'created'=>1,'img'=>1,'message'=>1,'addScheduler'=>1,'dateTime'=>1,'userid'=>$puserid,'sendAll'=>1]]])->toArray();
            }
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Notification List.';
                $resArr['data']  = ["total"=>($resp)?count($resp):1,"list"=>$res,'imgUrl'=> $imgUrl];
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
        }
        return $response->withJson($resArr, $code);
    }
}
