<?php

namespace Apps\Controllers;

use \Firebase\JWT\JWT;
use Swift_TransportException;

class AdminuserController
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

    private function assignDefRole($id)
    {
        $menuid = 1;
        $stmt1 = $this->db->prepare("INSERT INTO usermenuassign (userid,menuid) VALUES (:userid,:menuid)");
        $stmt1->execute(['userid'=>$id,'menuid'=>$menuid]);

        $stmt   = $this->db->prepare("SELECT  id FROM tblresoures WHERE menuid=:menuid");
        $stmt->execute(["menuid"=>$menuid]);
        $resources = $stmt->fetchAll();
        foreach ($resources as $row) {
            $stmt = $this->db->prepare("INSERT INTO userroleassign (userid,resouresid) VALUES (:userid,:resouresid)");
            $stmt->execute(['userid'=>$id,'resouresid'=>$row['id']]);
        }
    }

    public function menusFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];

        $sql 	   	= "";
        $params		= [];
        $menusData 	= [];
        
        if ($loginuser['userdata']->usertype == ADMIN) {
            $stmt = $this->db->prepare("SELECT id,mname,title FROM menus WHERE parent=:parent AND id !=:id ");
            $stmt->execute(["parent"=>INACTIVESTATUS,"id"=>11]);
            $menus = $stmt->fetchAll();
            
            
            foreach ($menus as $menu) {
                $menu['submenu']	= $this->subMenus($menu['id']);
                $menusData[]=$menu;
            }
        } elseif ($loginuser['userdata']->usertype == SUBADMIN) {
            $stmt = $this->db->prepare("SELECT ura.resouresid,tr.menuid FROM userroleassign ura INNER JOIN tblresoures tr ON ura.resouresid=tr.id WHERE userid=:userid GROUP BY tr.menuid");
            $stmt->execute(["userid"=>$uid]);
            $results = $stmt->fetchAll();
              
            foreach ($results as $row) {
                $stmt = $this->db->prepare("SELECT id,mname,title,url,icon FROM menus WHERE id=:id");
                $stmt->execute(["id"=> $row['menuid']]);
                $menus = $stmt->fetchAll();
                foreach ($menus as $menu) {
                    $menu['submenu']	= $this->subMenus($menu['id']);
                    $menusData[]=$menu;
                }
            }
        } else {
            return;
        }
        $resArr['code']  = 0;
        $resArr['error'] = false;
        $resArr['msg']   = 'Menus';
        $resArr['data']  = $menusData;
        return $response->withJson($resArr, $code);
    }

    public function subMenus($menuid)
    {
        $stmt = $this->db->prepare("SELECT id,title as name,url,icon FROM menus WHERE parent=:parent");
        $stmt->execute(["parent"=>$menuid]);
        return $submenus = $stmt->fetchAll();
    }

    public function getMenus($uid)
    {
        $parent 	= 0;
        $sql 	   	= "";
        $params		= [];
        $menusData 	= [];
        $user = $this->security->getUserById($uid);
        if (empty($user)) {
            return;
        }
        if ($user['usertype'] == ADMIN) {
            $stmt = $this->db->prepare("SELECT id,title as name,url,icon FROM menus WHERE parent=:parent ORDER BY msort ");
            $stmt->execute(["parent"=>INACTIVESTATUS]);
            $menus = $stmt->fetchAll();
            
            foreach ($menus as $menu) {
                $children = $this->subMenus($menu['id']);
                if (!empty($children)) {
                    unset($children['id']);
                    $menu['children']	= $children;
                }
                unset($menu['id']);
                $menusData[]=$menu;
            }
        } elseif ($user['usertype'] == SUBADMIN) {
            $sql = "SELECT uma.menuid,m.title as name,m.url,m.icon FROM usermenuassign uma INNER JOIN menus m ON uma.menuid=m.id WHERE uma.userid=:userid AND m.parent=:parent ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(["userid"=>$uid,"parent"=>$parent]);
            $menus = $stmt->fetchAll();
           
            foreach ($menus as $menu) {
                $sql = "SELECT m.title as name,m.url,m.icon FROM menus m INNER JOIN usermenuassign uma ON m.id=uma.menuid WHERE m.parent=:menuid AND uma.userid=:userid";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(["menuid"=> $menu['menuid'],"userid"=>$uid]);
                $children = $stmt->fetchAll();

                if (!empty($children)) {
                    $menu['children']	= $children;
                }
                unset($menu['menuid']);
                $menusData[]=$menu;
            }
        } else {
            return;
        }
        return $menusData;
    }


    public function funcLogin($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
      
        $resArr =  [];
        $input  =  $request->getParsedBody();
        $ip 	=  \Security::getIpAddr();

        $check  =  $this->security->validateRequired(['username','password'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        try {
            $pass = $this->security->encryptPassword($input['password']);
            $sql = "SELECT id,username,usertype,isuserverify,status FROM users WHERE username= :username AND password = :password AND (usertype=:utypeadmin OR usertype=:utypesubadmin)";

            $sth = $this->db->prepare($sql);
            $sth->bindParam("username", $input['username']);
            $utypeadmin = ADMIN;
            $utypesubadmin = SUBADMIN;
        
            $sth->bindParam("utypeadmin", $utypeadmin);
            $sth->bindParam("utypesubadmin", $utypesubadmin);
            $sth->bindParam("password", $pass);
            $sth->execute();
            $user = $sth->fetch();
            if (empty($user)) {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'Invalid username Or password.';
                return $response->withJson($resArr);
            }

            if ($user['status'] != ACTIVESTATUS) {
                $resArr = $this->security->errorMessage("User may be Inactive or Blocked, contact to webmaster");
                return $response->withJson($resArr, $errcode);
            }

            $payload = array(
                    "id" => $user['id'],
                    "userdata"=> $user,
                    "authoruri" => "fantasy.com",
                    "exp" => time()+(3600 * 10),  //10 hours
                );

            $token = JWT::encode($payload, $settings['settings']['jwt']['secret'], "HS256");

            $updateusertb = $this->db->prepare("UPDATE users SET ip=:ip WHERE id = :id ");
            $id = $user['id'];
            $updateusertb->bindParam(':id', $id);
            $updateusertb->bindParam(':ip', $ip);
            $updateusertb->execute();

            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Login successfully.';
            $resArr['data']  =  ['token' => $token,'menus'=>$this->getMenus($user['id'])];
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr);
    }

    public function funcGetusertype($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
       
                       
        $input = $request->getParsedBody();
        
        $sql = "SELECT * FROM usertype where typename != :tname";
        $sth = $this->db->prepare($sql);
        $tname = "admin";
        $sth->bindParam("tname", $tname);
        $sth->execute();
        $usertypesres = $sth->fetchAll();

        $resArr['code']  = 0;
        $resArr['error'] = false;
        $resArr['msg']   = 'usertype list.';
        $resArr['data']  =  $usertypesres;
            
        return $response->withJson($resArr);
    }

    public function funcAddpages($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['title','slug','content'], $input);
        
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $title  	= $input['title'];
        $slug  		= $input['slug'];
        $content  	= $input['content'];

        if (!preg_match("/^[a-zA-Z\s]+$/", $input['title'])) {
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = "Please enter letters!";
            return $response->withJson($resArr, $errcode);
        }
        try {
            //fix page slug
            /* if(!in_array($slug, ['faqs','aboutus','support']))
             {
                    $resArr['code']  = 0;
                 $resArr['error'] = false;
                 $resArr['msg']   = "slug not match";
                  return $response->withJson($resArr,$errcode);
                }	*/

            $stmt = $this->db->prepare("update pages set title = :title, content = :content where slug = :slug ");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':slug', $slug);
            $stmt->bindParam(':content', $content);
            if ($stmt->execute()) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Update page successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Page not updated';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    public function funcUpdateReferalCommisions($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['rtype','amount'], $input);
        
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $rtype  	= $input['rtype'];
        $amount  	= $input['amount'];
        try {
            //fix page slug
            if (!in_array($rtype, ['referbonus','welcomebonus','bnsdeduction','refpercent','referbnsto','refmaxamt'])) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = "Invalid rtype";
                return $response->withJson($resArr, $errcode);
            }

            if (!preg_match('/^[0-9]+(\\.[0-9]+)?$/', $amount)) {
                $resArr = $this->security->errorMessage("Invalid amount");
                return $response->withJson($resArr, $errcode);
            }

            $stmt = $this->db->prepare("update referalcommisions set amount = :amount where rtype = :rtype ");
            if ($stmt->execute(["rtype"=>$rtype,"amount"=>$amount])) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Updated successfully.';
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

    public function funcGetReferalCommisions($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
                       
        $input = $request->getParsedBody();
        
        $sql = "SELECT rtype,amount FROM referalcommisions ";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $res = $sth->fetchAll();
        if (!empty($res)) {
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] = 'Referal Commisions';
            $resArr['data'] = $res;
        } else {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg'] = 'Record not found.';
        }
            
        return $response->withJson($resArr);
    }

    //Get Page By Slug
    public function funcGetpage($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['slug'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $slug  = $input['slug'];
            
        /*if(!in_array($slug, ['faqs','aboutus','support']))
        {
             $resArr = $this->security->errorMessage("Invalid slug");
            return $response->withJson($resArr,$errcode);
           }	*/

        try {
            //fix page slug
                              
            $stmt = $this->db->prepare("SELECT title,content FROM pages WHERE slug = :slug ");
            $stmt->bindParam(':slug', $slug);
            $stmt->execute();
            $res = $stmt->fetch();
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Page';
                $resArr['data'] = $res;
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

    public function funcAddsubadmin($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['phone','email','password','name'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
      
        $email    = strtolower(str_replace(" ", "", $input["email"]));
        $phone    = $input["phone"];
        $password = $input["password"];
        $name     = $input["name"];
        $username = $email;
       
        if (preg_match("/^.*(?=.{8,})(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).*$/", $password) === 0) {
            $resArr = $this->security->errorMessage("Password must be at least 8 characters and must contain at least one lower case letter, one upper case letter and one digit");
            return $response->withJson($resArr, $errcode);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $resArr = $this->security->errorMessage("Invalid email address");
            return $response->withJson($resArr, $errcode);
        }
        if (!filter_var($phone, FILTER_VALIDATE_INT)) {
            $resArr = $this->security->errorMessage("Invalid phone number");
            return $response->withJson($resArr, $errcode);
        }

        $ckEmail = $this->security->emailExisting($email);
        if (!empty($ckEmail)) {
            $resArr = $this->security->errorMessage("User already register with this email");
            return $response->withJson($resArr, $errcode);
        }
        $ckPhone = $this->security->phoneExisting($phone);
        if (!empty($ckPhone)) {
            $resArr = $this->security->errorMessage("User already register with this phone");
            return $response->withJson($resArr, $errcode);
        }
        $ckUsername = $this->security->checkUserVerify($username);
        if (!empty($ckUsername)) {
            $resArr = $this->security->errorMessage("User already register with this email");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $this->db->beginTransaction();
            $password = $this->security->encryptPassword($input['password']);
            $created  = time();
            $usertype = SUBADMIN ; // for subadmin

            $stmt = $this->db->prepare("INSERT INTO users (username,name, phone,email,password, created,usertype) VALUES (:username,:name, :phone, :email, :password, :created, :usertype)");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':created', $created);
            $stmt->bindParam(':usertype', $usertype);
            if ($stmt->execute()) {
                $lastId = $this->db->lastInsertId();
                $this->assignDefRole($lastId);
                
                $this->db->commit();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Subadmin added successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not inserted, There is some problem';
            }
        } catch (PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
            $this->db->rollBack();
        }
        return $response->withJson($resArr, $code);
    }


    //Edit Subadmin
    public function funcEditsubadmin($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['id','name','status'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $id     = $input['id'] ;
        $name   = $input['name'];
        $status = $input['status'];
        $roleid   = $input['roleid'];

        try {
            if (!in_array($status, [0,1])) {
                $resArr = $this->security->errorMessage("Status value not correct.");
                return $response->withJson($resArr, $errcode);
            }
            $checkRole = $this->security->checkUserRole($roleid);
            if (!$checkRole) {
                $resArr = $this->security->errorMessage("Role value not correct");
                return $response->withJson($resArr, $errcode);
            }
            $checkUsr = $this->security->checkSubAdminById($id);
            if (!$checkUsr) {
                $resArr = $this->security->errorMessage("Invalid id");
                return $response->withJson($resArr, $errcode);
            }

            $stmt = $this->db->prepare("update users set name = :name, status = :status WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id);
            $stmt2 = $this->db->prepare("update userrole set roleid = :roleid WHERE userid = :id");
            $stmt2->bindParam(':roleid', $roleid);
            $stmt2->bindParam(':id', $id);

            if ($stmt->execute() && $stmt2->execute()) {
                $res2 = $this->security->getUserById($id);
                $resRole = $this->security->getUserRoleById($id);
                if ($resRole) {
                    $res2['roleid'] = $resRole['roleid'];
                }
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Record updated successfully.';
                $resArr['data'] = $res2;
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not inserted, There is some problem';
                $code = $errcode;
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    /*delete sub admin by admin(start)*/

    public function funcDeletesubadmin($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $loginuser = $request->getAttribute('decoded_token_data');

        //print_r($loginuser); die;

        $loginusertype = $loginuser['userdata']->usertype;

        $check =  $this->security->validateRequired(['id'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $id = $input['id'];
        try {
            $checkUsr= $this->security->checkUserIdExisting($id);
            if ($checkUsr == false) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = "User id not matched";
                return $response->withJson($resArr, $errcode);
            }
            if ($loginusertype==ADMIN) {
                $stmt = $this->db->prepare("Delete from users where id = $id");
                $stmt->execute();
                $userprofile = $this->db->prepare("Delete from userprofile where userid = $id");
                if ($userprofile->execute()) {
                    $resArr['code']  = 0;
                    $resArr['error'] = false;
                    $resArr['msg'] = 'Record delete successfully.';
                    $resArr['data'] = $this->security->getUserById($id);
                } else {
                    $resArr = $this->security->errorMessage("Record not delete, There is some problem");
                    return $response->withJson($resArr, $errcode);
                }
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Only admin can delete records';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    //--List Subadmin--
    public function funcListsubadmin($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        try {
            $input  = $request->getParsedBody();
            $limit  = $input['limit']; //2
            $page   = $input['page'];  //0   LIMIT $page,$offset

            if (empty($limit)) {
                $limit  = $settings['settings']['code']['defaultPaginLimit'];
            }
             
            if (empty($page)) {
                $page = 1;
            }
            $offset = ($page-1)*$limit;

            $params = ["usertype"=>SUBADMIN];
            $serachSql = "";
             
            if (!empty($input['search']) && isset($input['search'])) {
                $search = $input['search'];
                $serachSql = " AND username LIKE :search ";
                $params = ["usertype"=>SUBADMIN,"search"=>"%$search%"];
            }
            
            $sqlCount = "select count(id) as total FROM users WHERE usertype=:usertype ".$serachSql;
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute($params);
            $resCount =  $stmtCount->fetch();
            $sql = "SELECT id,name,username,phone,email,status,usertype,created FROM users WHERE usertype=:usertype ".$serachSql." ORDER BY id DESC limit ".$offset.",".$limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();
            if ($res) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Sub admin list';
                $resArr['data'] = ["total"=>$resCount['total'],"list"=>$res];
            } else {
                $resArr['code'] = 1;
                $resArr['error']= true;
                $resArr['msg'] 	= 'Record not found';
                $code 			= $errcode;
            }
        } catch (\PDOException $e) {
            $resArr['code']  	= 1;
            $resArr['error'] 	= true;
            $resArr['msg']   	= \Security::pdoErrorMsg($e->getMessage());
            $code 				= $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }

    // Single User subadmin
    public function funcSinglesubadmin($request, $response)
    {
        try {
            $input = $request->getParsedBody();
            $check =  $this->security->validateRequired(['id'], $input);
            if (isset($check['error'])) {
                return $response->withJson($check);
            }
                 
            $id   = $input['id'] ;
            $stmt = $this->db->prepare("select id,name,username,status,usertype,created from users WHERE usertype=2 AND id=:uid ");
            $stmt->execute(['uid' => $id]);
            $res =  $stmt->fetch();
            if ($res) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Sub admin list';
                $resArr['data']  = $res;
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'No record found';
                $code = $errcode;
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }


    //---Add Role---
    public function funcAddrole($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
                               
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['rolename','radd','redit','rview','rdelete'], $input);

        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $rolename = $input["rolename"];
        $radd = $input["radd"];
        $redit = $input["redit"];
        $rview = $input["rview"];
        $rdelete = $input["rdelete"];
                   
        if (!in_array($radd, ['0','1'])) {
            $resArr = $this->security->errorMessage("radd should be 0/1");
            return $response->withJson($resArr, $errcode);
        }
        if (!in_array($redit, ['0','1'])) {
            $resArr = $this->security->errorMessage("redit should be 0/1");
            return $response->withJson($resArr, $errcode);
        }
        if (!in_array($rview, ['0','1'])) {
            $resArr = $this->security->errorMessage("rview should be 0/1");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($rdelete, ['0','1'])) {
            $resArr = $this->security->errorMessage("rdelete should be 0/1");
            return $response->withJson($resArr, $errcode);
        }

        $checkRoleNameExst = $this->security->checkRoleNameExst($rolename);
        if ($checkRoleNameExst) {
            $resArr = $this->security->errorMessage("Rolename already exists.");
            return $response->withJson($resArr, $errcode);
        }
        try {
            $stmt = $this->db->prepare("INSERT INTO roles (rolename, radd, redit, rview,rdelete) VALUES (:rolename, :radd, :redit, :rview,:rdelete)");
            $params = ['rolename'=>$rolename, 'radd'=>$radd, 'redit'=>$redit, 'rview'=>$rview,'rdelete'=>$rdelete];

            if ($stmt->execute($params)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Role added successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Role not added, There is some problem';
                $code = $errcode;
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
           
        return $response->withJson($resArr, $code);
    }


    public function funcEditrole($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['id','rolename','radd','redit','rview','rdelete'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $rid = $input["id"];
        $rolename = $input["rolename"];
        $radd = $input["radd"];
        $redit = $input["redit"];
        $rview = $input["rview"];
        $rdelete = $input["rdelete"];
           
        $checkRoleIdExist = $this->security->checkRoleIdExist($rid); // check id valid
        if ($checkRoleIdExist) {
            $resArr = $this->security->errorMessage("Invalid role id.");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($radd, ['0','1'])) {
            $resArr = $this->security->errorMessage("radd should be 0/1");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($redit, ['0','1'])) {
            $resArr = $this->security->errorMessage("redit should be 0/1");
            return $response->withJson($resArr, $errcode);
        }
        if (!in_array($rview, ['0','1'])) {
            $resArr = $this->security->errorMessage("rview should be 0/1");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($rdelete, ['0','1'])) {
            $resArr = $this->security->errorMessage("rdelete should be 0/1");
            return $response->withJson($resArr, $errcode);
        }

        $checkRoleNameExst = $this->security->checkRoleNameExst($rolename, $rid); // for edit
        if ($checkRoleNameExst) {
            $resArr = $this->security->errorMessage("Rolename already exists");
            return $response->withJson($resArr, $errcode);
        }
          
        try {
            $data = [
                'rolename' => $rolename,
                'radd' => $radd,
                'redit' => $redit,
                'rview' => $rview,
                'rdelete' => $rdelete,
                'id' => $id,
            ];
            $sql = "UPDATE roles SET rolename=:rolename,radd=:radd,redit=:redit,rview=:rview,rdelete=:rdelete WHERE id=:id";
            $stmt= $this->db->prepare($sql);
            if ($stmt->execute($data)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Role updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Role not updated, There is some problem';
                $code = $errcode;
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
   
        return $response->withJson($resArr, $code);
    }


    // List Role
    public function funcListroles($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
            
        $input = $request->getParsedBody();
               
        try {
            $stmt  = $this->db->prepare("select id,rolename,radd,redit,rdelete,rview from roles ORDER BY id DESC");
            $stmt->execute();
            $res =  $stmt->fetchAll();
            if ($res) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Roles';
                $resArr['data'] = $res;
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not found';
                $code = $errcode;
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }


    //---Add Resoures---
    public function funcAddresoures($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
               
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['menuname','url'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $menuname = $input["menuname"];
        $url = $input["url"];

        $checkMenuNameExst = $this->security->checkMenuNameExst($menuname);
        if ($checkMenuNameExst) {
            $resArr = $this->security->errorMessage("Menuname already exists.");
            return $response->withJson($resArr, $errcode);
        }
                  
        try {
            $data = [
                               'menuname'=>$menuname,
                               'url'=>$url,
                             ];
            $stmt = $this->db->prepare("INSERT INTO tblresoures (menuname, url) VALUES (:menuname, :url)");

            if ($stmt->execute($data)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Resoures added successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Resoures not added, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
           
        return $response->withJson($resArr, $code);
    }



    //---Edit Resoures---
    public function funcEditresoures($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['id','menuname','url'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $id = $input["id"];
        $menuname = $input["menuname"];
        $url = $input["url"];
                   
        $checkResouresIdExist = $this->security->checkResouresIdExist($id); // check id valid
        if ($checkResouresIdExist) {
            $resArr = $this->security->errorMessage("Invalid id.");
            return $response->withJson($resArr, $errcode);
        }

        $checkMenuNameExst = $this->security->checkMenuNameExst($menuname, $id);
        if ($checkMenuNameExst) {
            $resArr = $this->security->errorMessage("Menuname already exists.");
            return $response->withJson($resArr, $errcode);
        }
                  
        try {
            $data = [
                               'menuname'=>$menuname,
                               'url'=>$url,
                               'id'=>$id
                             ];
            $sql = "UPDATE tblresoures SET menuname=:menuname,url=:url WHERE id=:id";
            $stmt= $this->db->prepare($sql);
            if ($stmt->execute($data)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Resoures updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Resoures not added, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
           
        return $response->withJson($resArr, $code);
    }


    // List Resoures
    public function funcListresoures($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        try {
            $input = $request->getParsedBody();
            $stmt  = $this->db->prepare("select id,menuname,url from tblresoures ORDER BY id DESC");
            $stmt->execute();
            $res =  $stmt->fetchAll();
            if ($res) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Resoures';
                $resArr['data'] = $res;
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Record not found';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    //---assignrole---
    public function funcAssignrole($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
       
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['userid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $userid = $input["userid"];
        //$roleid = $input["roleid"];
        $roleid = 1;
        $menusArr = $input["menus"];

        if (empty($menusArr) || !is_array($menusArr)) {
            $resArr = $this->security->errorMessage("Select menus.");
            return $response->withJson($resArr, $errcode);
        }

        $checkSubAdminById = $this->security->checkSubAdminById($userid);
        if (empty($checkSubAdminById)) {
            $resArr = $this->security->errorMessage("invalid user id.");
            return $response->withJson($resArr, $errcode);
        }

        foreach ($menusArr as $menu) {
            $menuExist = $this->security->menuExist($menu);
            if (empty($menuExist)) {
                $resArr = $this->security->errorMessage("Invalid menu id.");
                return $response->withJson($resArr, $errcode);
            }
        }
                  
        try {
            $this->db->beginTransaction();
        
            $sqlDel  = "DELETE FROM usermenuassign WHERE userid=:userid";
            $stmtDel = $this->db->prepare($sqlDel);
            $stmtDel->execute(["userid"=>$userid]);
            $sqlDel  = "DELETE FROM userroleassign WHERE userid=:userid";
            $stmtDel = $this->db->prepare($sqlDel);
            $stmtDel->execute(["userid"=>$userid]);
            foreach ($menusArr as $menuid) {
                $data = ['userid'=>$userid,'menuid'=>$menuid];
                $stmt = $this->db->prepare("INSERT INTO usermenuassign (userid, menuid) VALUES (:userid, :menuid)");
                $stmt->execute($data);
             
                $sql    = "SELECT id FROM tblresoures WHERE menuid=:menuid";
                $stmt   =  $this->db->prepare($sql);
                $stmt->execute(["menuid"=>$menuid]);
                $resources    =  $stmt->fetchAll();

                if (!empty($resources)) {
                    foreach ($resources as $row) {
                        $resoures = $row['id'];
                        $data = ['userid'=>$userid,'roleid'=>$roleid,'resouresid'=>$resoures];
                        $stmt = $this->db->prepare("INSERT INTO userroleassign (userid, roleid,resouresid) VALUES (:userid, :roleid,:resouresid)");
                        $stmt->execute($data);
                    }
                }
            }

            $this->db->commit();
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Resoures assigned successfully';
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }

        return $response->withJson($resArr, $code);
    }

    //Get Assign Role
    public function funcGetassignrole($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
   
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['userid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $userid = $input["userid"];
                      
        $checkSubAdminById = $this->security->checkSubAdminById($userid);
        if (empty($checkSubAdminById)) {
            $resArr = $this->security->errorMessage("invalid user id.");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $sqlGet = "SELECT menuid FROM usermenuassign WHERE userid=:userid";
            $stmtGet = $this->db->prepare($sqlGet);
            $stmtGet->execute(["userid"=>$userid]);
            $resGet = $stmtGet->fetchAll();

            if (!empty($resGet)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Assigned Menu.';
                $resArr['data']  =  $this->security->removenull($resGet);
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'No resoures assigned yet';
                $resArr['data']  = '';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
       
        return $response->withJson($resArr, $code);
    }


    //---edit assignrole---
    /*public function funcEditassignrole($request,$response)
    {

            global $settings;
            $code    = $settings['settings']['code']['rescode'];
            $errcode = $settings['settings']['code']['errcode'];

             $input = $request->getParsedBody();
             $check =  $this->security->validateRequired(['id','userid','roleid','resouresid'],$input);
             if(isset($check['error'])) {
                return $response->withJson($check);
             }

            $userid = $input["userid"];
            $id = $input["id"];
            $roleid = $input["roleid"];
            $resouresid = $input["resouresid"];

            $checkRoleAssignedIdExist = $this->security->checkRoleAssignedIdExist($id);
            if($checkRoleAssignedIdExist)
            {
                $resArr = $this->security->errorMessage("Invalid id.");
                return $response->withJson($resArr,$errcode);
            }

            $checkSubAdminById = $this->security->checkSubAdminById($userid);
            if(empty($checkSubAdminById))
            {
                $resArr = $this->security->errorMessage("invalid user id.");
                return $response->withJson($resArr,$errcode);
            }
            $checkUserRole = $this->security->checkUserRole($roleid);
            if(empty($checkUserRole))
            {
                $resArr = $this->security->errorMessage("invalid roleid id.");
                return $response->withJson($resArr,$errcode);
            }

            $checkResouresIdExist = $this->security->checkResouresIdExist($resouresid);
            if($checkResouresIdExist)
            {
                $resArr = $this->security->errorMessage("Invalid resoures id.");
                return $response->withJson($resArr,$errcode);
            }

            $checkRoleAssigned = $this->security->checkRoleAssigned($userid,$roleid,$resouresid ,$id);
            if($checkRoleAssigned)
            {
                $resArr = $this->security->errorMessage("Role already assigned");
                return $response->withJson($resArr,$errcode);
            }

         try{
                 $data = [
                           'userid'=>$userid,
                           'roleid'=>$roleid,
                           'resouresid'=>$resouresid,
                        ];
           $stmt = $this->db->prepare("INSERT INTO userroleassign (userid, roleid,resouresid) VALUES (:userid, :roleid,:resouresid)");

           if($stmt->execute($data)){
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Role assigned successfully.';
             }else{
                    $resArr['code']  = 1;
                    $resArr['error'] = true;
                    $resArr['msg'] = 'Role not assigned, There is some problem';
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


    // Get Notification Func
    public function getNotificationFunc($request, $response)
    {
        global $settings;
        global $baseurl;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
                 
        $input = $request->getParsedBody();
        $searchSql 	= "";
        $params    	= [];
        if (isset($input['limit'])) {
            $limit  =	$input['limit'];
        }
        if (isset($input['page'])) {
            $page   =	$input['page'];
        }
                                  
        if (empty($limit)) {
            $limit=$settings['settings']['code']['defaultPaginLimit'];
        }
        if (empty($page)) {
            $page = 1;
        }
        $offset = ($page-1)*$limit;

        if (!empty($input['search']) && isset($input['search'])) {
            $search 	= $input['search'];
            $searchSql 	.= " AND (u.email LIKE :search or u.phone LIKE :search or up.teamname LIKE :search) ";
            $params 	= $params + ["search"=>"%$search%"];
        }
                                
        $sqlCount = "SELECT count(n.id) AS total FROM notifications n INNER JOIN users u ON n.userid=u.id LEFT JOIN userprofile up ON n.userid=up.userid WHERE 1=1 ".$searchSql." ORDER BY n.id DESC";
        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute($params);
        $resCount =  $stmtCount->fetch();
        try {
            $sql = "SELECT n.userid,u.phone,u.email,n.subject,n.message,n.created,n.readstatus,up.teamname FROM notifications n INNER JOIN users u ON n.userid=u.id LEFT JOIN userprofile up ON n.userid=up.userid WHERE 1=1 ".$searchSql." ORDER BY n.id DESC limit ".$offset.",".$limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();
            if (!empty($res)) {
                $resArr['code']  	= 0;
                $resArr['error'] 	= false;
                $resArr['msg'] 		= 'List Users.';
                $resArr['data'] 	= ["total"=>$resCount['total'],"list"=>$res];
            } else {
                $resArr['code']  	= 1;
                $resArr['error'] 	= true;
                $resArr['msg'] 		= 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    // Get Contactus Func
    public function getContactusFunc($request, $response)
    {
        global $settings;
        global $baseurl;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
                 
        $input = $request->getParsedBody();
        $searchSql 	= "";
        $params    	= [];
        if (isset($input['limit'])) {
            $limit  =	$input['limit'];
        }
        if (isset($input['page'])) {
            $page   =	$input['page'];
        }
                                  
        if (empty($limit)) {
            $limit=$settings['settings']['code']['defaultPaginLimit'];
        }
        if (empty($page)) {
            $page = 1;
        }
        $offset = ($page-1)*$limit;

        if (!empty($input['search']) && isset($input['search'])) {
            $search 	= $input['search'];
            $searchSql 	.= " AND u.username LIKE :search ";
            $params 	= $params + ["search"=>"%$search%"];
        }
                                
        $sqlCount = "SELECT count(c.id) AS total FROM contactus c INNER JOIN users u ON c.userid=u.id WHERE 1=1 ".$searchSql;
        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute($params);
        $resCount =  $stmtCount->fetch();
        try {
            $sql = "SELECT c.userid,u.phone,u.email,c.subject,c.issue,c.message,c.attachment,c.created FROM contactus c INNER JOIN users u ON c.userid=u.id WHERE 1=1 ".$searchSql." ORDER BY c.id DESC limit ".$offset.",".$limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();
            if (!empty($res)) {
                $resArr['code']  	= 0;
                $resArr['error'] 	= false;
                $resArr['msg'] 		= 'List Users.';
                $resArr['data'] 	= ["total"=>$resCount['total'],"list"=>$res];
            } else {
                $resArr['code']  	= 1;
                $resArr['error'] 	= true;
                $resArr['msg'] 		= 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    // Get user list
    public function getusersFunc($request, $response)
    {
        global $settings;
        global $baseurl;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
             
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['atype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $atype		=	$input['atype'];
        $pagiSql	=	"";
        $searchSql 	= 	"";
        $limit      =   "";
        $page 		=   "";
        $utype     	= 	USER;
        $params    	= 	["usertype"=>$utype];


        if (!in_array($atype, ["alluser","blockuser","kycapproved","kycnonapproved"])) {
            $resArr = $this->security->errorMessage("atype should be allusre/blockuser.");
            return $response->withJson($resArr, $errcode);
        }
        if (isset($input['limit'])) {
            $limit  =	$input['limit'];
        }
        if (isset($input['page'])) {
            $page   =	$input['page'];
        }

        //if(empty($page)){ $page = 1; }
            
        if (!empty($limit) && !empty($page)) {
            $offset  = ($page-1)*$limit;
            $pagiSql = ' limit '.$offset.",".$limit;
        }
                              
        /*if(empty($limit)){
            $limit=$settings['settings']['code']['defaultPaginLimit'];
        }*/
         

        if (!empty($input['search']) && isset($input['search'])) {
            $search 	= $input['search'];
            $searchSql 	.= " AND (u.email LIKE :search OR u.phone LIKE :search OR up.teamname LIKE :search) ";
            $params 	= $params + ["search"=>"%$search%"];
        }
        if ($atype == "alluser") {
            $searchSql	.= " AND u.status!=:status ";
            $params	=	$params	+ ["status"=>BLOCK];
        }
        if ($atype == "blockuser") {
            $searchSql .= " AND u.status=:status ";
            $params	=	$params	+ ["status"=>BLOCK];
        }
        if ($atype == "kycapproved") {
            $searchSql	.= " AND u.isbankdverify=:kycstatus AND u.ispanverify=:kycstatus AND u.isphoneverify=:kycstatus AND u.isemailverify=:kycstatus";
            $params	=	$params	+ ["kycstatus"=>ACTIVESTATUS];
        }
        if ($atype == "kycnonapproved") {
            $searchSql	.= " AND (u.isbankdverify=:kycstatus OR u.ispanverify=:kycstatus OR u.isphoneverify=:kycstatus OR u.isemailverify=:kycstatus) ";
            $params	=	$params	+ ["kycstatus"=>INACTIVESTATUS];
        }
                
        $sqlCount = "SELECT count(u.id) AS total FROM users u LEFT JOIN userprofile up ON u.id=up.userid WHERE u.usertype=:usertype ".$searchSql." ORDER BY u.id DESC";
        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute($params);
        $resCount =  $stmtCount->fetch();
                                                      
        try {
            $sql = "SELECT u.id,u.username,u.phone,u.email,u.devicetype,u.logintype,u.created,u.logindate,u.isuserverify,u.isphoneverify,u.isemailverify,u.isbankdverify,u.ispanverify,u.status,up.teamname FROM users u LEFT JOIN userprofile up ON u.id=up.userid WHERE u.usertype=:usertype ".$searchSql." ORDER BY u.id DESC ".$pagiSql;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();

            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'List Users.';
                $resArr['data'] = ["total"=>$resCount['total'],"list"=>$res];
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

    // Get Bot user list
    public function getBotUsersFunc($request, $response)
    {
        global $settings;
        global $baseurl;
 
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
              
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['atype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $atype		=	$input['atype'];
        $pagiSql	=	"";
        $searchSql 	= 	"";
        $limit      =   "";
        $page 		=   "";
        $utype     	= 	4;
        $params    	= 	["usertype"=>$utype];
 
 
        if (!in_array($atype, ["alluser","blockuser","kycapproved","kycnonapproved"])) {
            $resArr = $this->security->errorMessage("atype should be allusre/blockuser.");
            return $response->withJson($resArr, $errcode);
        }
        if (isset($input['limit'])) {
            $limit  =	$input['limit'];
        }
        if (isset($input['page'])) {
            $page   =	$input['page'];
        }
 
        //if(empty($page)){ $page = 1; }
             
        if (!empty($limit) && !empty($page)) {
            $offset  = ($page-1)*$limit;
            $pagiSql = ' limit '.$offset.",".$limit;
        }
                               
        /*if(empty($limit)){
            $limit=$settings['settings']['code']['defaultPaginLimit'];
        }*/
          
 
        if (!empty($input['search']) && isset($input['search'])) {
            $search 	= $input['search'];
            $searchSql 	.= " AND (u.email LIKE :search OR u.phone LIKE :search OR up.teamname LIKE :search) ";
            $params 	= $params + ["search"=>"%$search%"];
        }
        if ($atype == "alluser") {
            $searchSql	.= " AND u.status!=:status ";
            $params	=	$params	+ ["status"=>BLOCK];
        }
        if ($atype == "blockuser") {
            $searchSql .= " AND u.status=:status ";
            $params	=	$params	+ ["status"=>BLOCK];
        }
        if ($atype == "kycapproved") {
            $searchSql	.= " AND u.isbankdverify=:kycstatus AND u.ispanverify=:kycstatus AND u.isphoneverify=:kycstatus AND u.isemailverify=:kycstatus";
            $params	=	$params	+ ["kycstatus"=>ACTIVESTATUS];
        }
        if ($atype == "kycnonapproved") {
            $searchSql	.= " AND (u.isbankdverify=:kycstatus OR u.ispanverify=:kycstatus OR u.isphoneverify=:kycstatus OR u.isemailverify=:kycstatus) ";
            $params	=	$params	+ ["kycstatus"=>INACTIVESTATUS];
        }
                 
        $sqlCount = "SELECT count(u.id) AS total FROM users u LEFT JOIN userprofile up ON u.id=up.userid WHERE u.usertype=:usertype ".$searchSql." ORDER BY u.id DESC";
        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute($params);
        $resCount =  $stmtCount->fetch();
                                                       
        try {
            $sql = "SELECT u.id,u.username,u.phone,u.email,u.devicetype,u.logintype,u.created,u.logindate,u.isuserverify,u.isphoneverify,u.isemailverify,u.isbankdverify,u.ispanverify,u.status,up.teamname FROM users u LEFT JOIN userprofile up ON u.id=up.userid WHERE u.usertype=:usertype ".$searchSql." ORDER BY u.id DESC ".$pagiSql;
 
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();
 
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'List Users.';
                $resArr['data'] = ["total"=>$resCount['total'],"list"=>$res];
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

    // Get user list
    public function getUsersCashFunc($request, $response)
    {
        global $settings;
        global $baseurl;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
             
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['atype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $atype		=	$input['atype'];
        $pagiSql	=	"";
        $searchSql 	= 	"";
        $limit      =   "";
        $page 		=   "";
        $utype     	= 	USER;
        $params    	= 	["usertype"=>$utype];


        if (!in_array($atype, ["alluser","blockuser","kycapproved","kycnonapproved"])) {
            $resArr = $this->security->errorMessage("atype should be allusre/blockuser.");
            return $response->withJson($resArr, $errcode);
        }
        if (isset($input['limit'])) {
            $limit  =	$input['limit'];
        }
        if (isset($input['page'])) {
            $page   =	$input['page'];
        }

        //if(empty($page)){ $page = 1; }
            
        if (!empty($limit) && !empty($page)) {
            $offset  = ($page-1)*$limit;
            $pagiSql = ' limit '.$offset.",".$limit;
        }
                              
        /*if(empty($limit)){
            $limit=$settings['settings']['code']['defaultPaginLimit'];
        }*/
         

        if (!empty($input['search']) && isset($input['search'])) {
            $search 	= $input['search'];
            $searchSql 	.= " AND (u.email LIKE :search OR u.phone LIKE :search OR up.teamname LIKE :search) ";
            $params 	= $params + ["search"=>"%$search%"];
        }
        if ($atype == "alluser") {
            $searchSql	.= " AND u.status!=:status ";
            $params	=	$params	+ ["status"=>BLOCK];
        }
        if ($atype == "blockuser") {
            $searchSql .= " AND u.status=:status ";
            $params	=	$params	+ ["status"=>BLOCK];
        }
        if ($atype == "kycapproved") {
            $searchSql	.= " AND u.isbankdverify=:kycstatus AND u.ispanverify=:kycstatus AND u.isphoneverify=:kycstatus AND u.isemailverify=:kycstatus";
            $params	=	$params	+ ["kycstatus"=>ACTIVESTATUS];
        }
        if ($atype == "kycnonapproved") {
            $searchSql	.= " AND (u.isbankdverify=:kycstatus OR u.ispanverify=:kycstatus OR u.isphoneverify=:kycstatus OR u.isemailverify=:kycstatus) ";
            $params	=	$params	+ ["kycstatus"=>INACTIVESTATUS];
        }
                
        $sqlCount = "SELECT count(u.id) AS total FROM users u LEFT JOIN userprofile up ON u.id=up.userid WHERE u.usertype=:usertype ".$searchSql." ORDER BY u.id DESC";
        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute($params);
        $resCount =  $stmtCount->fetch();
                                                      
        try {
            $sql = "SELECT u.id,u.username,u.phone,u.email,u.walletbalance,u.wltbns,u.wltwin,up.teamname FROM users u LEFT JOIN userprofile up ON u.id=up.userid WHERE u.usertype=:usertype".$searchSql." ORDER BY u.id DESC ".$pagiSql;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $res =  $stmt->fetchAll();
 
            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'List Users.';
                $resArr['data'] = ["total"=>$resCount['total'],"list"=>$res];
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


    // show user Users cash Data
    public function updateUsersCashFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['userid','cash_type','amount','desr','wallet'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $userid = $input['userid'];
        $cash_type = $input['cash_type'];
        $amount = $input['amount'];
        $desr = $input['desr'];
        $wallet = $input['wallet'];
        $usrWltBal 	=  $this->security->getUserWalletBalance($userid);
        $currentTime = time();

        if ($wallet =="wallet_bal") {
            $prebal	=  $usrWltBal['walletbalance'];
            if ($cash_type == 'dr') {
                $walletbalance = $prebal - $amount;
                $amount = -$amount;
                $atype = 'cutbal';
            } elseif ($cash_type == 'cr') {
                $walletbalance = $prebal + $amount;
                $atype = 'addamount';
            }
            $wlt = 'wltbal';
        } elseif ($wallet =="win_amt") {
            $prebal	=  $usrWltBal['wltwin'];
            if ($cash_type == 'dr') {
                $walletbalance = $prebal - $amount;
                $amount = -$amount;
                $atype = 'cutbal';
            } elseif ($cash_type == 'cr') {
                $walletbalance = $prebal + $amount;
                $atype = 'addamount';
            }
            $wlt = 'wltwin';
        } elseif ($wallet =="wallet_bonus") {
            $prebal	=  $usrWltBal['wltbns'];
            if ($cash_type == 'dr') {
                $walletbalance = $prebal - $amount;
                $amount = -$amount;
                $atype = 'cutbal';
            } elseif ($cash_type == 'cr') {
                $walletbalance = $prebal + $amount;
                $atype = 'addamount';
            }
            $wlt = 'wltbns';
        }
        try {
            if ($wallet =="wallet_bal") {
                $stmt1 = $this->db->prepare("UPDATE users SET walletbalance = :walletbalance WHERE id=:user_id");
                $stmt1->bindParam(':walletbalance', $walletbalance);
                $stmt1->bindParam(':user_id', $userid);
                $stmt1->execute();
            } elseif ($wallet =="win_amt") {
                $stmt1 = $this->db->prepare("UPDATE users SET wltwin = :walletbalance WHERE id=:user_id");
                $stmt1->bindParam(':walletbalance', $walletbalance);
                $stmt1->bindParam(':user_id', $userid);
                $stmt1->execute();
            } elseif ($wallet =="wallet_bonus") {
                $stmt1 = $this->db->prepare("UPDATE users SET wltbns = :walletbalance WHERE id=:user_id");
                $stmt1->bindParam(':walletbalance', $walletbalance);
                $stmt1->bindParam(':user_id', $userid);
                $stmt1->execute();
            }
            $stmt = $this->db->prepare("INSERT INTO transactions (userid, amount, status, txdate, ttype, atype, wlt, prebal, curbal) VALUES (:userid, :amount, :status, :txdate, :ttype, :atype, :wlt, :prebal, :curbal)");
            $stmt->bindParam(':userid', $userid);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':status', $desr);
            $stmt->bindParam(':txdate', $currentTime);
            $stmt->bindParam(':ttype', $cash_type);
            $stmt->bindParam(':atype', $atype);
            $stmt->bindParam(':wlt', $wlt);
            $stmt->bindParam(':prebal', $prebal);
            $stmt->bindParam(':curbal', $walletbalance);

            if ($stmt->execute()) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Record inserted';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] 	 = 'Record not inserted.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
        }
        return $response->withJson($resArr, $code);
    }

    // Get user list
    public function getuserinfoFunc($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['userid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $userid		=	$input['userid'];
        try {
            $sql = "SELECT id,username,name,devicetype,logintype,created,logindate,isuserverify,isbankdverify,ispanverify,status FROM users WHERE usertype=:usertype AND id=:userid";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(["userid"=>$userid,"usertype"=>USER]);
            $res =  $stmt->fetch();
            if (!empty($res)) {
                $res['gender'] 			= "";
                $res['dob'] 			= "";
                $res['profilepic'] 		= "";
                $res['acno'] 			= "";
                $res['ifsccode'] 		= "";
                $res['bankname'] 		= "";
                $res['state'] 			= "";
                $res['acholdername'] 	= "";
                $res['image']			= "";
                $res['panname'] 		= "";
                $res['pannumber'] 		= "";
                $res['pandob'] 			= "";
                $res['panimage'] 		= "";
                $key = \Security::generateKey($userid);
                $upro = $this->security->getUserProfileDetail($userid);
                $ubank = $this->security->getbankac($userid);
                $udoc = $this->security->getdocument($userid, DOCPANCARD);

                if (!empty($upro)) {
                    $res['gender']   = $upro['gender'];
                    $res['teamname'] = $upro['teamname'];
                    $res['dob']      = $upro['dob'];
                    if (!empty($upro['profilepic'])) {
                        $res['profilepic'] = $baseurl.'/uploads/userprofiles/'.$upro['profilepic'];
                    }
                }
                if (!empty($ubank)) {
                    $res['acno'] = \Security::my_decrypt($ubank['acno'], $key);
                    $res['ifsccode'] = \Security::my_decrypt($ubank['ifsccode'], $key);
                    //Arun   $res['ifsccode'] = $ubank['ifsccode'];
                    $res['bankname'] = $ubank['bankname'];
                    $res['acholdername'] = $ubank['acholdername'];
                    $res['image'] 		 = $ubank['image'];
                    $res['state'] 		 = $ubank['state'];
                }
                if (!empty($udoc)) {
                    $res['panname'] = $udoc['panname'];
                    $res['pannumber'] = $udoc['pannumber'];
                    $res['pandob'] = $udoc['dob'];
                    $res['panimage'] = $udoc['panimage'];
                }

                $res['verify_titles']=['bankreject'=>'Confirm Bank Reject','bank'=>'Confirm Bank Verify','pancard'=>'Confirm Pan Card Verify','panreject'=>'Confirm Pan Reject'];
                $res['verify_msg']=['Select Type','Image Unclear','Mismatch in Details','Unreadable Image','Verified','Other'];
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'User info.';
                $resArr['data'] = $res;
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
    
    public function editusersinfoFunc($request, $response)
    {
        global $settings;
        global $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['userid','ifsccode','state'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $userid = $input['userid'];
        $ifsccode = $input['ifsccode'];
        $state = $input['state'];

        $checkuser = $this->security->checkUserById($userid);
        if (empty($checkuser)) {
            $resArr = $this->security->errorMessage("Invalid userid");
            return $response->withJson($resArr, $errcode);
        }
        $rst = $this->security->ifsccodeCheck($ifsccode);
        if ($rst['error'] == true) {
            $resArr = $this->security->errorMessage("Invalid IFSC code");
            return $response->withJson($resArr, $errcode);
        }
        if (strlen($ifsccode) < 4) {
            $resArr = $this->security->errorMessage("Please check acno or ifsccode length");
            return $response->withJson($resArr, $errcode);
        }

        $key = \Security::generateKey($userid);
        $ifsccode = \Security::my_encrypt($ifsccode, $key);

        try {
            $params = ["ifsccode"=>$ifsccode,"state"=>$state,"userid"=>$userid,];
            $updateusers = $this->db->prepare("UPDATE userbankaccounts SET ifsccode = :ifsccode, state =:state WHERE userid = :userid");
            if ($updateusers->execute($params)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Userinfo Updated.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = trues;
                $resArr = $this->security->errorMessage("Userinfo not Updated");
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

      // Update user info
    public function updateUserInfoFunc($request, $response)
    {
        global $settings;
        global $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['userid','name'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $userid = $input['userid'];
        $name = $input['name'];
        $checkuser = $this->security->checkUserById($userid);
        if (empty($checkuser)) {
            $resArr = $this->security->errorMessage("Invalid userid");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $params = ["userid"=>$userid,"usertype"=>USER,"name"=>$name];
            $updateusertb = $this->db->prepare("UPDATE users SET name = :name WHERE id = :userid AND usertype=:usertype");
            if ($updateusertb->execute($params)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Userinfo Updated.';
            } else {
                $resArr = $this->security->errorMessage("Userinfo not Updated");
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    // Update status user
    public function updateuserstatusFunc($request, $response)
    {
        global $settings;
        global $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['userid','status'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $userid = $input['userid'];
        $status = $input['status'];
        if (!in_array($status, [''.ACTIVESTATUS.'',''.INACTIVESTATUS.'',''.BLOCK.''])) {
            $resArr = $this->security->errorMessage("Invalid status");
            return $response->withJson($resArr, $errcode);
        }
        $checkuser = $this->security->checkUserById($userid);
        if (empty($checkuser)) {
            $resArr = $this->security->errorMessage("Invalid userid");
            return $response->withJson($resArr, $errcode);
        }
        try {
            $updateusertb = $this->db->prepare("UPDATE users SET status = :status WHERE id = :userid AND usertype=:usertype");
            $updateusertb->bindParam(':userid', $userid);
            $updateusertb->bindParam(':status', $status);
            $utype = USER;
            $updateusertb->bindParam(':usertype', $utype);
            
            if ($updateusertb->execute()) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Status Updated.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Status not updated.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    /* Update User KYC status */
    public function updateKycStatusFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $webname = $settings['settings']['webname'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['userid','status','atype','msg'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $updtSql = "";
        $userid = $input['userid'];
        $status = $input['status'];
        $atype  = $input['atype'];
        $msg    = $input['msg'];
        $params	 = ["userid"=>$userid];

        if (!in_array($status, [''.ACTIVESTATUS.'',''.INACTIVESTATUS.''])) {
            $resArr = $this->security->errorMessage("Invalid status");
            return $response->withJson($resArr, $errcode);
        }
        if (!in_array($atype, ["pancard","bank","panreject","bankreject"])) {
            $resArr = $this->security->errorMessage("Invalid atype");
            return $response->withJson($resArr, $errcode);
        }
        $checkuser = $this->security->getUserDetail($userid);
        if (empty($checkuser)) {
            $resArr = $this->security->errorMessage("Invalid userid");
            return $response->withJson($resArr, $errcode);
        }
        if ($atype == "pancard") {
            $updtSql = "ispanverify=:status";
            
            $params = $params + ["status"=>$status];
            
            $udoc = $this->security->getdocument($userid, DOCPANCARD);
            if (!empty($udoc)) {
                $stmt = $this->db->prepare("UPDATE documents SET isverified=:status WHERE userid = :userid");
                $stmt->execute($params);
            } else {
                $params = ["status"=>INACTIVESTATUS]+$params;
            }
        }

        if ($atype == "bank") {
            $updtSql = "isbankdverify=:status";
            $params = $params + ["status"=>$status];
            $ubank = $this->security->getbankac($userid);
            if (!empty($ubank)) {
                $stmt = $this->db->prepare("UPDATE userbankaccounts SET isverified=:status WHERE userid = :userid");
                $stmt->execute($params);
            } else {
                $params =  ["status"=>INACTIVESTATUS] + $params ;
            }
        }

        if ($atype == "panreject") {
            $udoc = $this->security->getdocument($userid, DOCPANCARD);
            if (!empty($udoc)) {
                $stmt = $this->db->prepare("DELETE FROM documents WHERE userid = :userid");
                $stmt->execute($params);
            }
            $updtSql = "ispanverify=:status";
            $params =  ["status"=>INACTIVESTATUS] + $params ;
        }
 
        if ($atype == "bankreject") {
            $ubank = $this->security->getbankac($userid);
            if (!empty($ubank)) {
                $stmt = $this->db->prepare("DELETE FROM userbankaccounts  WHERE userid = :userid");
                $stmt->execute($params);
            }
            $updtSql = "isbankdverify=:status";
            $params =  ["status"=>INACTIVESTATUS] + $params ;
        }
        try {
            $updateusertb = $this->db->prepare("UPDATE users SET ".$updtSql." WHERE id = :userid");
            if ($updateusertb->execute($params)) {
                if (in_array($atype, ["pancard","panreject"])) {
                    $deltype="verify pancard";
                } elseif (in_array($atype, ["bank","bankreject"])) {
                    $deltype="verify bankaccount";
                }
                $this->deleteNotifyKYC($checkuser, $deltype);
                $maildata['name']=($checkuser['name'])?$checkuser['name']:$checkuser['teamname'];
                $titles=ucwords($webname.' '.$atype).' KYC';
                $maildata['webname']=$webname;
                $maildata['template']='kyc.php';
                $maildata['subject']=$titles;
                $maildata['email']=$checkuser['email'];
                $maildata['content'] = $msg;
                $notification_data=['token'=>[$checkuser['devicetoken']],'devicetype'=>$checkuser['devicetype'],'body'=>$msg,'title'=>$titles,'ntype'=>KYC_NOTIFY,'notify_id'=>1];
                $notiyAndMailData['email']=$checkuser['email'];
                $notiyAndMailData['userid']=$checkuser['id'];
                $notiyAndMailData['phone']=$checkuser['phone'];
                $notiyAndMailData['type']=KYC_NOTIFY;
                $notiyAndMailData['devicetype']=$checkuser['devicetype'];
                if ($checkuser['devicetype']!='web') {
                    $notiyAndMailData['notify']=$notification_data;
                }
                $notiyAndMailData['maildata']=$maildata;
                $notiyAndMailData['created']=time();
                //$collections=$this->mdb->notifyandmails;
                //$collections->insertOne($notiyAndMailData);
                $this->security->notifyUser($notification_data);
                $this->sendMail($maildata);
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Status Updated.';
            } else {
                $resArr = $this->security->errorMessage("Status not updated");
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    public function sendMail($user)
    {
        global $settings;
        $dir    = __DIR__ . '/../settings/playwebsetting';
        $addset = @file_get_contents($dir);
        if ($addset != false) {
            $res = json_decode($addset, true);
            if (isset($res['mailconfig']) && !empty($res['mailconfig'])) {
                $user['webname']=$settings['settings']['webname'];
                $user['mailconfig']=$res['mailconfig'];
                try {
                    $this->mailer->sendMessage('/mail/'.$user['template'], ['data' => $user], function ($message) use ($user) {
                        $name    = " ";
                        $webname = $user['webname'];
                        if (!empty($user['name'])) {
                            $name = $user['name'];
                        }
                        $message->setTo($user['email'], $name);
                        $message->setSubject($user['subject']);
                    });
                } catch (Swift_TransportException $se) {
                    $string = date("Y-m-d H:i:s")  . ' - ' . $se->getMessage() . PHP_EOL;
                    file_put_contents("./../logs/errorlog_mail.txt", $string);
                }
            }
        }
        return true;
    }

    public function deleteNotifyKYC($checkuser, $atype=null)
    {
        if ($checkuser) {
            $params['userid']=$checkuser['id'];
            if (!empty($atype)) {
                $params['subject']=$atype;
                $delNotify = $this->db->prepare("DELETE FROM notifications WHERE userid= :userid AND subject =:subject");
                if ($delNotify->execute($params)) {
                    return true;
                }
            } elseif ($checkuser['ispanverify'] && $checkuser['isbankdverify'] && $checkuser['isemailverify'] && $checkuser['isphoneverify']) {
                $delNotify = $this->db->prepare("DELETE FROM notifications WHERE userid= :userid");
                if ($delNotify->execute($params)) {
                    return true;
                }
            }
        }
        return false;
    }


    // Update status subamin
    public function updateSubAdminStatusFunc($request, $response)
    {
        global $settings;
        global $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['userid','status'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $userid = $input['userid'];
        $status = $input['status'];
        if (!in_array($status, [''.ACTIVESTATUS.'',''.INACTIVESTATUS.''])) {
            $resArr = $this->security->errorMessage("Invalid status");
            return $response->withJson($resArr, $errcode);
        }
        $checkuser = $this->security->checkSubAdminById($userid);
        if (empty($checkuser)) {
            $resArr = $this->security->errorMessage("Invalid userid");
            return $response->withJson($resArr, $errcode);
        }
        try {
            $updateusertb = $this->db->prepare("UPDATE users SET status = :status WHERE id = :userid AND usertype=:usertype");
            $updateusertb->bindParam(':userid', $userid);
            $updateusertb->bindParam(':status', $status);
            $utype = SUBADMIN;
            $updateusertb->bindParam(':usertype', $utype);
            if ($updateusertb->execute()) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Status Updated.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Status not updated.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    /* edit Promo Code */
    public function editPromoCode($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        
        if (isset($input['id']) && isset($input['call_type']) && in_array($input['call_type'], [API_STATUS_UPDATE,API_DELETE_RECORD])) {
            return $this->deletePromoCode($request, $response);
        }

        $check =  $this->security->validateRequired(["id","pcode","sdate","edate","pc_val","pc_val_type","title","pc_max_val","pc_min_val"], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $id     =  $input['id'];
        $title     =  $input['title'];
        $pcode    	=  $input['pcode'];
        $pctype   	=  (isset($input['pctype']) && !empty($input['pctype']))?$input['pctype']:'addmny';
        $sdate    	=  strtotime(Date('Y-m-d', strtotime($input['sdate'])).' '.FROMDTTIME);
        $edate     =  strtotime(Date('Y-m-d', strtotime($input['edate'])).' '.TODTTIME);
        $pc_ofr_type	=  (isset($input['pc_ofr_type']) && !empty($input['pc_ofr_type']))?$input['pc_ofr_type']:'rwtval';
        $ofr_value	=  $input['pc_val'];
        $val_type 	=  $input['pc_val_type'];
        $desc      =  isset($input['desc'])?$input['desc']:'';
        $min_val 	=  $input['pc_min_val'];
        $max_val 	=  $input['pc_max_val'];
        $spanding_limit = $input['spanding_limit'];
        $up_to_cashback = $input['up_to_cashback'];
        $updated   =  time();
         
        if (empty($spanding_limit)) {
            $spanding_limit = 0;
        }
        if (empty($up_to_cashback)) {
            $up_to_cashback = 0;
        }
        if ($max_val< $min_val) {
            $resArr = $this->security->errorMessage("Enter max value from min value");
            return $response->withJson($resArr, $errcode);
        }

        if ($edate< $sdate) {
            $resArr = $this->security->errorMessage("Invalid End Date");
            return $response->withJson($resArr, $errcode);
        }

        $game_type=$this->security->getAllDefineKeys();
        if (!in_array($pc_ofr_type, array_keys($game_type['rewords_type']))) {
            $resArr = $this->security->errorMessage("Invalid Rewords Type");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($val_type, array_keys($game_type['offer_value_type']))) {
            $resArr = $this->security->errorMessage("Invalid Offer Value Type");
            return $response->withJson($resArr, $errcode);
        } elseif ($pc_ofr_type==$game_type['rewords_type'][REWARDS_POINTS]) {
            if ($val_type!=$game_type['offer_value_type'][POINT_VALUE]) {
                $resArr = $this->security->errorMessage("Offer Value not match with offer type ");
                return $response->withJson($resArr, $errcode);
            }
        }

        if (isset($input['gametype']) && !empty($input['gametype'])) {
            if (!in_array($input['gametype'], array_keys($game_type['game_type']))) {
                $resArr = $this->security->errorMessage("Invalid Game Type");
                return $response->withJson($resArr, $errcode);
            } else {
                $gtype=$input['gametype'];
            }
        } else {
            $gtype='all';
        }

        if (isset($input['users_limit'])) {
            if ($input['users_limit']<0) {
                $resArr = $this->security->errorMessage("Users Limit not blank");
                return $response->withJson($resArr, $errcode);
            } else {
                $userslimit=$input['users_limit'];
            }
        } else {
            $userslimit=null;
        }
        $isExist = $this->security->isPromoCodeExist($pcode, $id);
        if ($isExist) {
            $resArr = $this->security->errorMessage("Promo code already exist.");
            return $response->withJson($resArr, $errcode);
        }
          
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE promocode SET pcode=:pcode,pc_ofr_type=:pc_ofr_type,pc_val=:pc_val,pc_val_type=:pc_val_type,title=:title,updated=:updated,pc_max_val=:pc_max_val,pc_min_val=:pc_min_val,gametype=:gametype,users_limit=:users_limit,sdate=:sdate,edate=:edate,pctype=:pctype,spanding_limit=:spanding_limit,up_to_cashback=:up_to_cashback WHERE id=:id");
            $data = ["id"=>$id,"pcode"=>strtoupper($pcode),"pc_ofr_type"=>$pc_ofr_type,"pc_val"=>$ofr_value,"pc_val_type"=>$val_type,"title"=>$title,"updated"=>$updated,"pc_max_val"=>$max_val,"pc_min_val"=>$min_val,"gametype"=>$gtype,"users_limit"=>$userslimit,'sdate'=>$sdate,'edate'=>$edate,'pctype'=>$pctype,'spanding_limit'=>$spanding_limit,'up_to_cashback'=>$up_to_cashback];
                

            if ($stmt->execute($data)) {
                $this->db->commit();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Promocode Updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Promo code not updated, There is some problem';
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

    /* Create Promo Code */
    public function createPromoCode($request, $response)
    {
        global $settings;
        $keys=$key_values='';
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(["pcode","sdate","edate","pc_val","pc_val_type","title","pc_max_val","pc_min_val"], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $title     =  $input['title'];
        $pcode    	=  $input['pcode'];
        $pctype   	=  (isset($input['pctype']) && !empty($input['pctype']))?$input['pctype']:'addmny';
        $sdate    	=  strtotime(Date('Y-m-d', strtotime($input['sdate'])).' '.FROMDTTIME);
        $edate     =  strtotime(Date('Y-m-d', strtotime($input['edate'])).' '.TODTTIME);
        $pc_ofr_type	=  (isset($input['pc_ofr_type']) && !empty($input['pc_ofr_type']))?$input['pc_ofr_type']:'rwtval';
        $ofr_value	=  $input['pc_val'];
        $val_type 	=  $input['pc_val_type'];
        $desc      =  isset($input['desc'])?$input['desc']:'';
        $min_val 	=  $input['pc_min_val'];
        $max_val 	=  $input['pc_max_val'];
        $created   =  time();
        $status    =  (string)(isset($input['status']))?$input['status']:INACTIVESTATUS;
        $spanding_limit = $input['spanding_limit'];
        $up_to_cashback = $input['up_to_cashback'];
        $mony_spent = 0;
         
        if (empty($spanding_limit)) {
            $spanding_limit = 0;
        }
        if (empty($up_to_cashback)) {
            $up_to_cashback = 0;
        }
        if ($ofr_value<=0) {
            $resArr = $this->security->errorMessage("Please enter valid offer value");
            return $response->withJson($resArr, $errcode);
        }

        if ($max_val< $min_val) {
            $resArr = $this->security->errorMessage("Ma x amount should be greater than min amount");
            return $response->withJson($resArr, $errcode);
        }

        if ($edate< $sdate) {
            $resArr = $this->security->errorMessage("Invalid End Date");
            return $response->withJson($resArr, $errcode);
        }

        $game_type=$this->security->getAllDefineKeys();
        if (!in_array($pctype, array_keys($game_type['code_type']))) {
            $resArr = $this->security->errorMessage("Invalid Code Create Type");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($pc_ofr_type, array_keys($game_type['rewords_type']))) {
            $resArr = $this->security->errorMessage("Invalid  Type");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($val_type, array_keys($game_type['offer_value_type']))) {
            $resArr = $this->security->errorMessage("Invalid Offer Value Type");
            return $response->withJson($resArr, $errcode);
        } elseif ($pc_ofr_type==$game_type['rewords_type'][REWARDS_POINTS]) {
            if ($val_type!=$game_type['offer_value_type'][POINT_VALUE]) {
                $resArr = $this->security->errorMessage("Offer Value not match with offer type ");
                return $response->withJson($resArr, $errcode);
            }
        }

        if (isset($input['gametype']) && !empty($input['gametype'])) {
            if (!in_array($input['gametype'], array_keys($game_type['game_type']))) {
                $resArr = $this->security->errorMessage("Invalid Game Type");
                return $response->withJson($resArr, $errcode);
            } else {
                $gtype=$input['gametype'];
            }
        } else {
            $gtype='all';
        }

        if (isset($input['users_limit']) && !empty($input['users_limit'])) {
            if ($input['users_limit']<=0) {
                $resArr = $this->security->errorMessage("Users Limit not blank");
                return $response->withJson($resArr, $errcode);
            } else {
                $userslimit=$input['users_limit'];
            }
        } else {
            $userslimit=0;
        }

        $isExist = $this->security->isPromoCodeExist($pcode);
        if ($isExist) {
            $resArr = $this->security->errorMessage("Promo code already exist.");
            return $response->withJson($resArr, $errcode);
        }
          
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("INSERT INTO promocode (pcode,pc_ofr_type,pc_val,pc_val_type,title,created,pc_max_val,pc_min_val,gametype,users_limit,sdate,edate,pctype,spanding_limit,up_to_cashback,mony_spent)VALUES(:pcode,:pc_ofr_type,:pc_val,:pc_val_type,:title,:created,:pc_max_val,:pc_min_val,:gametype,:users_limit,:sdate,:edate,:pctype,:spanding_limit,:up_to_cashback,:mony_spent)");

            $data = ["pcode"=>strtoupper($pcode),"pc_ofr_type"=>$pc_ofr_type,"pc_val"=>$ofr_value,"pc_val_type"=>$val_type,"title"=>$title,"created"=>$created,"pc_max_val"=>$max_val,"pc_min_val"=>$min_val,"gametype"=>$gtype,"users_limit"=>$userslimit,'sdate'=>$sdate,'edate'=>$edate,'pctype'=>$pctype,'spanding_limit'=>$spanding_limit,'up_to_cashback'=>$up_to_cashback,'mony_spent'=>$mony_spent];
            //"status"=>$status
            if ($stmt->execute($data)) {
                $lastId = $this->db->lastInsertId();
                $this->db->commit();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Promocode added successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Promo code not inserted, There is some problem';
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

    /* Delete Promo Code */
    public function deletePromoCode($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(["id"], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $id     =  $input['id'];
        $status  =  (isset($input['status']) && in_array($input['status'], [0,1]))?$input['status']:INACTIVESTATUS;
        try {
            $this->db->beginTransaction();
            $data = ['id'=>$id,'status'=>$status];
            if ($input['call_type']==API_STATUS_UPDATE) {
                $msg='Updated successfully.';
                $data["deleted"]=null;
            } else {
                $msg='Deleted successfully.';
                $data["deleted"]=time();
            }

            $stmt = $this->db->prepare("UPDATE promocode SET deleted=:deleted,status=:status WHERE id=:id");
            if ($stmt->execute($data)) {
                $sql = "SELECT * FROM promocode where id=:id";
                $pcRecord = $this->db->prepare($sql);
                $pcRecord->execute(["id"=>$id]);
                $pcRecord = $pcRecord->fetchAll();
                $this->db->commit();
                $settings = $this->security->getAllDefineKeys();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = $msg;
                $resArr['data']  = ['list'=>$this->security->removenull($pcRecord),'settings'=>$settings];
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Invalid id';
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

    public function getPromoCode($request, $response)
    {
        global $settings;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $searchSql  = "";
        $params=[];
        $paging = $where_cond= "";

        //$where_cond =" where deleted=";
        if (isset($input['id']) && !empty($input['id'])) {
            $params['id']=$input['id'];
            $where_cond.=" where id=:id ";
        }
        if (!empty($input['search']) && isset($input['search'])) {
            $search = $input['search'];
            $where_cond = " WHERE (pcode LIKE :search OR title LIKE :search) ";
            $params = ["search"=>"%$search%"];
        }

        if (isset($input['page']) && !empty($input['page'])) {
            $limit  = (isset($input['limit']))?$input['limit']:''; //2
            $page   = $input['page'];  //0   LIMIT $page,$offset
          if (empty($limit)) {
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          }
            $offset = ($page-1)*$limit;
            $paging = " limit ".$offset.",".$limit;
        }
        $dataResArr  = [];
        try {
            $sql = "SELECT * FROM promocode ".$where_cond." ORDER BY id DESC  ".$paging;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $allPCodes = $stmt->fetchAll();

            if (!empty($allPCodes)) {
                $sqlCount = "SELECT count(id) as total FROM promocode ".$where_cond." ORDER BY id DESC  ";
                $stmtCount = $this->db->prepare($sqlCount);
                $stmtCount->execute($params);
                $resCount =  $stmtCount->fetch();
                $settings = $this->security->getAllDefineKeys();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'All Promo Code List.';
                $resArr['data']  = ['total'=>$resCount['total'],'list'=>$this->security->removenull($allPCodes),'settings'=>$settings];
            } else {
                $resArr['code']   = 1;
                $resArr['error']  = true;
                $resArr['msg']    = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
      
        return $response->withJson($resArr, $code);
    }
     
    public function getPromoCodeCoupon($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $promocodeid    =  $input['id'];

        if (isset($input['page']) && !empty($input['page'])) {
            $limit  = $input['limit']; //2
             $page   = $input['page'];  //0   LIMIT $page,$offset
             if (empty($limit)) {
                 $limit  = 10;
             }
            $offset = ($page-1)*$limit;
            $paging = " limit ".$offset.",".$limit;
        }
        try {
            $sql = "SELECT pc.distribution_amt, pc.created, pr.title, pr.pc_val, pr.mony_spent, u.name FROM promocodecoupon pc LEFT JOIN promocode pr ON pc.promocode_id = pr.id LEFT JOIN users u ON u.id = pc.user_id WHERE pr.id=:id ORDER BY pc.id DESC ".$paging;

            $stmt 	= $this->db->prepare($sql);
            $params = ["id"=>$promocodeid];
            $stmt->execute($params);
            $res =  $stmt->fetchAll();

            if (!empty($res)) {
                $sqlCount =   "SELECT count(id) as total FROM promocodecoupon WHERE promocode_id=:id";

                $stmtCount = $this->db->prepare($sqlCount);
                $params2 = ["id"=>$promocodeid];
                $stmtCount->execute($params2);
                $resCount =  $stmtCount->fetch();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Promo Code Coupon Details.';
                $resArr['data']  = ['total'=>$resCount['total'],'list'=>$this->security->removenull($res)];
            } else {
                $resArr['code']   = 1;
                $resArr['error']  = true;
                $resArr['msg']    = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
      
        return $response->withJson($resArr, $code);
    }

    // Promo code settings
    public function getPromoCodeSettings($request, $response)
    {
        global $settings;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        try {
            $allPCsettings = $this->security->getAllDefineKeys();

            if (!empty($allPCsettings)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'All Promo Code settings.';
                $resArr['data']  = $allPCsettings;
            } else {
                $resArr['code']   = 1;
                $resArr['error']  = true;
                $resArr['msg']    = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
      
        return $response->withJson($resArr, $code);
    }



    // List of Bonus Levels
    public function getBonusLevles($request, $response)
    {
        global $settings;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $searchSql  = "";
        $params=[];
        $paging = $where_cond= "";

        //$where_cond =" where deleted=";
        if (isset($input['id']) && !empty($input['id'])) {
            $params['id']=$input['id'];
            $where_cond.=" where id=:id ";
        }
        if (!empty($input['search']) && isset($input['search'])) {
            $search = $input['search'];
            $where_cond = " WHERE (pcode LIKE :search OR title LIKE :search) ";
            $params = ["search"=>"%$search%"];
        }

        if (isset($input['page']) && !empty($input['page'])) {
            $limit  = $input['limit']; //2
            $page   = $input['page'];  //0   LIMIT $page,$offset
          if (empty($limit)) {
              $limit  = $settings['settings']['code']['defaultPaginLimit'];
          }
            $offset = ($page-1)*$limit;
            $paging = " limit ".$offset.",".$limit;
        }
        $dataResArr  = [];
        try {
            $sql = "SELECT * FROM bonuslevel ".$where_cond." ORDER BY id ASC  ".$paging;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $allPCodes = $stmt->fetchAll();

            if (!empty($allPCodes)) {
                $sqlCount ="SELECT count(id) AS total FROM promocode ORDER BY id DESC";
                $stmtCount = $this->db->prepare($sqlCount);
                $stmtCount->execute($params);
                $resCount =  $stmtCount->fetch();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'All Levels List.';
                $resArr['data']  = ['total'=>$resCount['total'],'list'=>$this->security->removenull($allPCodes)];
            } else {
                $resArr['code']   = 1;
                $resArr['error']  = true;
                $resArr['msg']    = 'Record not found.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
      
        return $response->withJson($resArr, $code);
    }

    /* Create Bonus Level Code */
    public function createBonusLevel($request, $response)
    {
        global $settings;
        $keys=$key_values='';
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        //print_r($input); die;
        // $check =  $this->security->validateRequired(["levels"],$input);
        // print_r($check); die;
        /* if(isset($check['error']))
         {
             return $response->withJson($check);
         } */
         
        // $levelsData= json_decode($input['levels']);
        $levelsData= $input['levels'];
        if (empty($levelsData)) {
            $resArr = $this->security->errorMessage("Invalid json Data");
            return $response->withJson($resArr, $errcode);
        }
        $btype 	=  isset($input['btype'])?$input['btype']:'creconmny';
        $bns_val_type 	=  isset($input['bns_val_type'])?$input['bns_val_type']:'amtval';
        $created   =  time();
        $status =  (isset($input['status']) && in_array($input['status'], [ACTIVESTATUS,INACTIVESTATUS]))?$input['status']:INACTIVESTATUS;

        foreach ($levelsData as $value) {
            $value=(object)($value);
            if (empty($value->bmin) || empty($value->bmax) || empty($value->bval)) {
                $resArr = $this->security->errorMessage("Empty value not accepted");
                return $response->withJson($resArr, $errcode);
            } elseif ($value->bmin>$value->bmax) {
                $resArr = $this->security->errorMessage("Min value should be max value from bmax");
                return $response->withJson($resArr, $errcode);
            }

            $levelsValues.="('$btype','$bns_val_type',$value->bmin,$value->bmax,$value->bval,$status,$created),";
        }
            
      
        try {
            $this->db->beginTransaction();
            $levelsValues=substr($levelsValues, 0, -1);
            $sql="INSERT INTO bonuslevel (btype,bns_val_type,bmin,bmax,bval,status,created)VALUES$levelsValues";
            $stmt= $this->db->prepare($sql);
            if ($stmt->execute()) {
                $lastId = $this->db->lastInsertId();
                $this->db->commit();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Levels added successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'levels not inserted, There is some problem';
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

    /* Edit Bonus Level Code */
    public function editBonusLevel($request, $response)
    {
        global $settings;
        $keys=$key_values='';
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        
        $input = $request->getParsedBody();
        /* $check =  $this->security->validateRequired(["levels"],$input);
         if(isset($check['error']))
         {
             return $response->withJson($check);
         } */
         
        //$levelsData= json_decode($input['levels']);
        $levelsData= $input['levels'];
        if (empty($levelsData)) {
            $resArr = $this->security->errorMessage("Invalid json Data");
            return $response->withJson($resArr, $errcode);
        }
        $btype 	=  isset($input['btype'])?$input['btype']:'creconmny';
        $bns_val_type 	=  isset($input['bns_val_type'])?$input['bns_val_type']:'amtval';
        $updated   =  time();
        $status =  (isset($input['status']) && in_array($input['status'], [ACTIVESTATUS,INACTIVESTATUS]))?$input['status']:INACTIVESTATUS;

        foreach ($levelsData as $value) {
            $value=(object)($value);
            if (empty($value->id) || empty($value->bmin) || empty($value->bmax) || empty($value->bval)) {
                $resArr = $this->security->errorMessage("Empty value  or missing key not accepted");
                return $response->withJson($resArr, $errcode);
            } elseif ($value->bmin>$value->bmax) {
                $resArr = $this->security->errorMessage("Min value should be max value from bmax");
                return $response->withJson($resArr, $errcode);
            }

            $editLevels=$value;
        }
            
      
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE bonuslevel SET btype=:btype,bns_val_type=:bns_val_type,bmin=:bmin,bmax=:bmax,bval=:bval,status=:status,updated=:updated WHERE id=:id");
            $params['status']=$status;
            $params['bns_val_type']=$bns_val_type;
            $params['btype']=$btype;
            $params['id']=$editLevels->id;
            $params['bmin']=$editLevels->bmin;
            $params['bmax']=$editLevels->bmax;
            $params['bval']=$editLevels->bval;
            $params['updated']=$updated;
            if ($stmt->execute($params)) {
                $this->db->commit();
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'Levels Updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'levels not updated, There is some problem';
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
}
