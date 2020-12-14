<?php
use \Firebase\JWT\JWT;
//use Twilio\Rest\Client;

use Apps\Mymethods;

class Security extends Mymethods {

  private static $instance  = null;
  
  private $db  = null;
  
  # Private key
  public static $salt = 'brLu70bRuD5WYw5wd0r6wt2vteuiniQBqE70nAuhU=';
  public static $pass = 'HH3d0fg#18GH12U';

  // Never change this salt
 
  private function __construct(){
      
  }

  public static function  getInstance (){

        if(self::$instance == null) {
            self::$instance = new Security ;
        }
        return self::$instance ;
  }
 
  function setDB($appVar)
  {
      $this->db=$appVar ;
  }
 
    
  public static function generateRandomString($length = 30)
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  public static function generateKey($uid){
      return $uid.self::$salt;
  }

  public static function my_encrypt($data, $key) {
      // Remove the base64 encoding from our key
      $encryption_key = base64_decode($key);
      // Generate an initialization vector
      $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
      // Encrypt the data using AES 256 encryption in CBC mode using our encryption key and initialization vector.
      $encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
      // The $iv is just as important as the key for decrypting, so save it with our encrypted data using a unique separator (::)
      return base64_encode($encrypted . '::' . $iv);
  }
  public static function my_decrypt($data, $key) {
      // Remove the base64 encoding from our key
      $encryption_key = base64_decode($key);
      // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
      list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
      return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
  }     
    #Get Random String - Usefull for public key
    public function genRandString($length = 0) {
        $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $str = '';
        $count = strlen($charset);
        while ($length-- > 0) {
            $str .= $charset[mt_rand(0, $count-1)];
        }
      return $str;
    }
    
    public static function encryptPassword($password) {
        global $settings;
        $settings['settings']['jwt']['secret'];
        $fixsalt = "dD6qLHBHFjO8CZZhRsDItNmeG5YqzXgS";  //Never change this salt
        return md5($fixsalt.$password);
    }
     
    public static function removenull($assocarray = array())
    {
        if (!empty($assocarray)) {
            foreach ($assocarray as $key => $value) {
                if (is_null($value)) {
                    $assocarray[$key] = "";
                }
            }
        }
        return $assocarray;
    }

    public static function pdoErrorMsg($msg)
    {
      if(ISLIVEMODE){
        $msg = "There is some data server issue, contact to webmaster.";
      }
      return $msg;        
    }

    function otpGenerate(){
      $digits = 6;
      $otp    = rand(pow(10, $digits-1), pow(10, $digits)-1);
      return $otp;
    }

    function tokenGenerate($userArr){
      global $settings;
      $user = [
                 "id"=>$userArr['id'],
                 "username"=>$userArr['username'],
                 "usertype"=>$userArr['usertype'],
                 "isuserverify"=>$userArr['isuserverify']
                ];     
      $payload = array(
          "id" => $user['id'],
              "userdata"=> $user,                
              "authoruri" => "fantasy.com",
              "exp" => time()+(3600 * 24 * 30 * 12),  //10 hours
            );
      $token = JWT::encode($payload, $settings['settings']['jwt']['secret'], "HS256");
      return $token;
    }
     
  function validateRequired($required_fields = array(), $data)
  {        
        $error = false;
        $error_fields = "";
        $request_params = $data;
        
        foreach ($required_fields as $field) {
            if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
                $error = true;
                $error_fields .= $field . ', ';
            }
        }
       
        if ($error) {
            $resp = array();
            $resp["code"] = 1;
            $resp["error"] = true;
            $resp["msg"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
                        
                return $resp;

        } else { return true; }
  }


  function  errorMessage($msg,$code=null) {
                         
      if(is_null($code)){ $code = 1; }
      $resArr['code']  = $code;
      $resArr['error'] = true;
      $resArr['msg']   = $msg;
      return $resArr;
  }

  public static function  generateOtp(){                         
      $digits = 6;
      return $otp = rand(pow(10, $digits-1), pow(10, $digits)-1);      
  }
 
  
    function checkUserNameExisting($username) {
    
        $sql = "select id,username from users Where username = :username";
        
        $sth = $this->db->prepare($sql);

        $sth->bindParam("username", $username);
        
        $sth->execute();

        $userres = $sth->fetchAll();

        if(!empty($userres)){
           return true;
        }
        return false; 
    }

    function checkUsernameExist($username) {    
        $sql = "select id,email,phone,status from users Where phone = :username OR email=:username";        
        $sth = $this->db->prepare($sql);        
        $sth->execute(["username"=>$username]);
        $res = $sth->fetch();
        return $res;
    }

    // Check user already register When OTP verifyed

    /*function isUsrAlrdyRegi($username) {    
        $sql = "SELECT id,username,usertype,isuserverify FROM users WHERE username = :username"        
        $sth = $this->db->prepare($sql);
        $sth->bindParam("username", $username);        
        $isuserverify = 1;
        $sth->bindParam("isuserverify",$isuserverify);        
        $sth->execute();
        $res = $sth->fetch();
        if(!empty($res)){
           return $res;
        }
        return false; 
    }*/

    function emailExisting($email) {  
        $sql = "SELECT id FROM users WHERE email = :email";        
        $sth = $this->db->prepare($sql);
        $sth->execute(["email"=>$email]);
        $res = $sth->fetch();
        return $res; 
    }

    function phoneExisting($phone) {  
        $sql = "SELECT id FROM users WHERE phone = :phone";        
        $sth = $this->db->prepare($sql);
        $sth->execute(["phone"=>$phone]);
        $res = $sth->fetch();
        return $res; 
    }

    function socialidExist($socialid) {  
        $sql = "SELECT id FROM users WHERE socialid = :socialid";        
        $sth = $this->db->prepare($sql);
        $sth->execute(["socialid"=>$socialid]);
        $res = $sth->fetch();
        return $res; 
    }

    function ckUserSocialLogin($email,$socialid=NULL) 
    {    
      $param = ["email"=>$email,"socialid"=>$socialid];
      $sql = "SELECT id,status FROM users WHERE email=:email AND socialid=:socialid";
      $sth = $this->db->prepare($sql);       
      $sth->execute($param);
      $res = $sth->fetch();        
      return $res; 
    }

    function checkUserRegister($username) {    
        $sql = "SELECT id,username,usertype,isuserverify,status FROM users WHERE username = :username";        
        $sth = $this->db->prepare($sql);
        $sth->bindParam("username", $username);        
        $sth->execute();
        $userres = $sth->fetch();
        if(!empty($userres)){
           return $userres;
        }
        return false; 
    }

    function checkUserKyc($userid) {    
        $sql = "SELECT id FROM users WHERE id=:userid AND isemailverify=:v AND isphoneverify=:v AND ispanverify=:v AND isbankdverify=:v";        
        $sth = $this->db->prepare($sql);
        $sth->execute(["userid"=>$userid,"v"=>ACTIVESTATUS]);
        return $res = $sth->fetch();        
    }

    function checkWithSocialIdExisting($socialid,$username) {    
        $sql = "SELECT id,username,usertype,isuserverify FROM users WHERE socialid = :socialid";        
        $sth = $this->db->prepare($sql);
        $sth->bindParam("socialid", $socialid);        
        $sth->execute();
        $res = $sth->fetch();
        return $res; 
    }

    

   /* function isUserAlreadyRegister($username) {    
        $sql = "SELECT id,username,usertype,isuserverify FROM users WHERE username = :username AND isuserverify=:isuserverify";        
        $sth = $this->db->prepare($sql);
        $sth->bindParam("username", $username);        
        $isuserverify = 1;
        $sth->bindParam("isuserverify",$isuserverify);        
        $sth->execute();
        $res = $sth->fetch();
        if(!empty($res)){
           return $res;
        }
        return false; 
    }*/


 function getUserById($id) {    
        $sql = "select id,name,username,phone,email,isemailverify,status,usertype,created from users Where id = :id";    
        $sth = $this->db->prepare($sql);
        $sth->bindParam("id", $id);    
        $sth->execute();
        $userres = $sth->fetch();
        if(!empty($userres)){
           return $userres;
        }
        return false; 
    }

 function getUserRoleById($id) {    
        $sql = "select id,userid,roleid from userrole Where userid = :id";    
        $sth = $this->db->prepare($sql);
        $sth->execute(["id"=>$id]);
        $res = $sth->fetch();
        if(!empty($res)){
           return $res;
        }
        return false; 
    }   

     function checkSubAdminById($id) {   
          $sql = "SELECT id,name,username,usertype,created FROM users WHERE id=:id AND usertype=:usertype";    
          $sth = $this->db->prepare($sql);
          $subadmin = SUBADMIN;
          $sth->execute(["id"=>$id,"usertype"=>$subadmin]);
          $res = $sth->fetch();
          if(!empty($res)){
             return $res;
          }
          return false; 
    }

    function checkUserById($id) {   
          $sql = "SELECT id,name,username,usertype FROM users WHERE id=:id AND usertype=:usertype";    
          $sth = $this->db->prepare($sql);
          $utype = USER;
          $sth->execute(["id"=>$id,"usertype"=>$utype]);
          $res = $sth->fetch();
          return $res; 
    }

 function checkUserRole($id) {    
        $sql = "select id from roles WHERE id = :id ";
        $sth = $this->db->prepare($sql);
        $sth->bindParam("id", $id);
        $sth->execute();
        $role = $sth->fetchAll();
        if(!empty($role)){
           return $role;
        }
        return false; 
    }


 //--- Roles Validation------

     function checkRoleNameExst($rolename,$id=null) {
         
         $data = ["rolename"=>$rolename];
        if($id>0 && !is_null($id)){
            
         $sql = "select id from roles Where rolename = :rolename AND id != :id ";          
         $data = ["rolename"=>$rolename,"id"=>$id ];  

        }else{
          $sql = "select id from roles Where rolename = :rolename ";    
        }      
        
        $sth = $this->db->prepare($sql);

        $sth->execute($data);

        $roles1 = $sth->fetch();

        if(!empty($roles1)){
           return true;
        }
        return false; 
 }

function checkRoleIdExist($id) {    
        $sql = "select id FROM roles Where id = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetchAll();
        if(!empty($res)){
           return false;
        }
        return true; 
    }


//--- Resource Validation------    


     function checkMenuNameExst($name,$id=null) {         
            $data = ["name"=>$name];
            if($id>0 && !is_null($id)){            
             $data = ["name"=>$name,"id"=>$id ];   
             $sql = "select id from tblresoures Where menuname = :name AND id != :id ";          
            }else{
              $sql = "select id from tblresoures Where menuname = :name ";    
            }          
            $sth = $this->db->prepare($sql);
            $sth->execute($data);
            $res = $sth->fetch();
            if(!empty($res)){
               return true;
            }
            return false; 
       }
     
     function checkResouresIdExist($id) {    
        $sql = "select id FROM tblresoures Where id = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetchAll();
        if(!empty($res)){
           return false;
        }
        return true; 
    }

//--- Role assign Validation------    

   function checkRoleAssigned($userid,$roleid,$resouresid ,$id=null) {
         
         $data = ["userid"=>$userid,"resouresid"=>$resouresid,"roleid"=>$roleid];
        
        if($id>0 && !is_null($id)){
         $sql = "select id from userroleassign WHERE userid = :userid AND resouresid = :resouresid AND roleid = :roleid  AND id != :id ";          
         $data = ["userid"=>$userid,"resouresid"=>$resouresid,"roleid"=>$roleid,"id"=>$id ];           
        }else{
          $sql = "select id from userroleassign WHERE userid = :userid AND resouresid = :resouresid AND roleid = :roleid ";              
        }

        $sth = $this->db->prepare($sql);
        $sth->execute($data);
        $roles1 = $sth->fetch();

        if(!empty($roles1)){
           return true;
        }
        return false; 
     }

   function checkRoleAssignedIdExist($id) {    
        $sql = "select id FROM userroleassign Where id = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetchAll();
        if(!empty($res)){
           return false;
        }
        return true; 
    }

//--- Check Autherisaction--

  function isAutherized($userid,$uri) { 

   $data = ["userid"=>$userid,"url"=>$uri];                
   $sql  = "SELECT uras.id,uras.userid,uras.resouresid,uras.roleid,res.menuname,res.url,r.rolename,r.radd,r.redit,r.rdelete,r.rview FROM userroleassign uras INNER JOIN tblresoures res ON  res.id=uras.resouresid INNER JOIN roles r ON r.id=uras.roleid WHERE uras.userid= :userid AND res.url=:url";        
        $sth = $this->db->prepare($sql);
        $sth->execute($data);
        $res = $sth->fetchAll();                
        if(empty($res)){
           return true;
        }
        return false; 
  }

  function isAutherizedUser($userid,$ip=null) { 

   $data = ["userid"=>$userid,"status"=>ACTIVESTATUS];                
   $sql  = "SELECT ip FROM users WHERE id = :userid AND status = :status";   

   //$sql  = "SELECT id FROM users WHERE id = :userid AND status = :status";        
        $sth = $this->db->prepare($sql);
        $sth->execute($data);
        $res = $sth->fetch();                
        if(empty($res))
        {
           return false;
        }
        /*if($res['ip']==$ip ){
           return true;
        }*/
        return true; 
  }

     function checkUserOldPassword($password) 
   {
      $sql = "select id,password from users Where password = :password";
      $sth = $this->db->prepare($sql);
      $sth->bindParam(":password", $password);
      $sth->execute();
      $userres = $sth->fetchAll();
      if(!empty($userres)){
         return true;
      }
        return false; 
    } 

    function checkUserVerify($username){
        $sql = "select id,isuserverify FROM users WHERE username = :username";        
        $sth = $this->db->prepare($sql);
        $param = ["username"=>$username];        
        $sth->execute($param);
        $res = $sth->fetch();
        if(!empty($res)){
           return $res;
        }
        return false; 
  }

  function getUserDetail($uid){
      global $baseurl;
      $res = [];
      
      $sql = "SELECT u.id,u.username,u.phone,u.email,u.name,u.logintype,u.socialid,u.usertype,up.address,up.city,up.state,u.refercode,u.status,u.isuserverify,u.isphoneverify,u.isemailverify,u.ispanverify,u.isbankdverify,u.istnameedit,up.teamname,up.gender,up.secondaryemail,up.dob,up.profilepic,u.devicetoken,u.devicetype,u.passwordplain FROM users u INNER JOIN userprofile up ON u.id = up.userid WHERE u.id = :uid";
        $sth = $this->db->prepare($sql);
        $param = ["uid"=>$uid];
        $sth->execute($param);
        $res = $sth->fetch();
        if(!empty($res['profilepic'])){
          $res['profilepic'] = $baseurl.'/uploads/userprofiles/'.$res['profilepic'];
          //$res['dob']        = date('d/m/Y',$res['dob']);
        }
        return $res; 
  } 

  function getdocument($uid,$doctype=null){
      global $baseurl;
      $sqlDtype = "";
      $param = ["uid"=>$uid];
      if(!is_null($doctype)){ 
        $param = $param + ["doctype" => $doctype ] ; 
        $sqlDtype = " AND doctype=:doctype ";
      }
      $res = [];
      $sql = "SELECT id,userid,panname,pannumber,dob,panimage,isverified FROM documents WHERE userid = :uid".$sqlDtype;  
      $sth = $this->db->prepare($sql);      
      $sth->execute($param);
      $res = $sth->fetch();   
      if(!empty($res)){
        if(!empty($res['panimage'])){
          $res['panimage'] = $baseurl.'/uploads/pancard/'.$res['panimage'];
        }
      }
       return $res; 
   }

  function getbankac($uid){
      global $baseurl;
      $imgUrl = $baseurl.'/uploads/banks/';
      $res = [];
      $sql = "SELECT id,userid,acno,ifsccode,bankname,acholdername,CONCAT('".$imgUrl."',image) as image,isverified,city,state FROM userbankaccounts WHERE userid = :uid";  
      $sth = $this->db->prepare($sql);
      $param = ["uid"=>$uid];        
      $sth->execute($param);
      $res = $sth->fetch();   
      return $res; 
  }
   

  function getUserProfileDetail($uid){
      global $baseurl;
      $sql = "SELECT teamname,gender,dob,profilepic FROM userprofile WHERE userid = :uid";  
        $sth = $this->db->prepare($sql);
        $param = ["uid"=>$uid];        
        $sth->execute($param);
        $res = $sth->fetch();
        if(!empty($res)){
           return $res;
        }
        return false; 
  }  

 
    function getUserIdByUsername($username){
        $sql = "select id,username,isuserverify FROM users WHERE username = :username";        
        $sth = $this->db->prepare($sql);
        $param = ["username"=>$username];        
        $sth->execute($param);
        $res = $sth->fetch();
        if(!empty($res)){
           return $res;
        }
        return false; 
    }  

    public function getBrowserinfo()
    {
        $browser = "";
        if(strpos($_SERVER['HTTP_USER_AGENT'],'Firefox'))
        {
           $browser = "Mozilla Firefox";
        }
        elseif(strpos($_SERVER['HTTP_USER_AGENT'],'Chrome'))
        {
            $browser = "Google Chrome";   
        }
        elseif(preg_match('/Edge/i', $_SERVER['HTTP_USER_AGENT']))
        {
            $browser = "Microsoft Edge";      
        }
        elseif(strpos($_SERVER['HTTP_USER_AGENT'],'MSIE'))
        {
            $browser = "IE";      
        }
        elseif(strpos($_SERVER['HTTP_USER_AGENT'],'Edge'))
        {
            $browser = "Edge";      
        }
        return $browser;
    } 

  function checkUserIdExisting($id) {
    
        $sql = "select id from users Where id = :id";
        
        $sth = $this->db->prepare($sql);

        $sth->bindParam("id", $id);
        
        $sth->execute();

        $userres = $sth->fetchAll();

        if(!empty($userres)){
           return true;
        }
        return false; 
    }

  function checkOtpExisting($otp) {
          $sql = "select username,otp from users Where otp = :otp";
          $sth = $this->db->prepare($sql);
          $sth->bindParam("otp",$otp);
          $sth->execute();
          $userres = $sth->fetchAll();
          if(!empty($userres)){
             return true;
          }
          return false; 
        }    



    function getPlayerById($id) {    
        $sql = "select players.id,players.playername,players.country,playerimg.playerimage,playerimg.jerseyname,playerrole.fullname 
                from ((players inner join playerimg on players.id = playerimg.playerid)
                inner join playerrole on players.playerrole = playerrole.id) where players.id = :id";    
        $sth = $this->db->prepare($sql);
        $sth->bindParam("id", $id);    
        $sth->execute();
        $playerres = $sth->fetch();
        if(!empty($playerres)){
           return $playerres;
        }
        return false; 
    }
     

    function checkRefferCode($refercode){
        $sql = "SELECT id,refercode FROM users WHERE refercode = :refercode";        
        $sth = $this->db->prepare($sql);
        $param = ["refercode"=>$refercode];        
        $sth->execute($param);
        $res = $sth->fetch();
        if(!empty($res)){
           return $res;
        }
        return false; 
    }

    function checkReferCodeExisting($ref) {
            $sql = "select id,refercode from users Where refercode = :refercode";
            $sth = $this->db->prepare($sql);
            $sth->bindParam("refercode", $ref);
            $sth->execute();
            $userres = $sth->fetch();
            if(!empty($userres)){
               return true;
            }
            return false; 
        }

    function checkPhoneExisting($phone) {
            $sql = "select id,phone from users Where phone = :phone";
            $sth = $this->db->prepare($sql);
            $sth->bindParam("phone", $phone);
            $sth->execute();
            $userres = $sth->fetchAll();
            if(!empty($userres)){
               return true;
            }
            return false; 
        }

   function checkEmailExisting($email) {
        $sql = "select id,email from users Where email = :email";
        $sth = $this->db->prepare($sql);
        $sth->bindParam("email", $email);
        $sth->execute();
        $userres = $sth->fetchAll();
        if(!empty($userres)){
           return true;
        }
        return false; 
        }      

  //----upload image---
   function uploadfile($file,$target){
      if(move_uploaded_file($file["tmp_name"], $target)) {
         return true;                   
       }
       return false;                     
   }

    function deleteRecord($tbl,$byfield,$id){
           $sql = "delete from ".$tbl." WHERE ".$byfield."=:id"; 
            $sth = $this->db->prepare($sql);
            if($sth->execute(["id"=>$id])){
              return true;  
            }
            return false; 
    }

 // player validation

    function isPlayerTypeIdExist($id) {

          $sql = "select id from playertype where id = :id";
          $sth = $this->db->prepare($sql);
          $sth->bindParam("id", $id);
          $sth->execute();
          $res = $sth->fetchAll();
          if(!empty($res)){
             return false;
          }
          return true; 
    }   

    function isPlayeridExist($pid) {
          $sql = "select pid,pname from playersmaster Where pid = :pid";
          $sth = $this->db->prepare($sql);
          $sth->bindParam("pid", $pid);
          $sth->execute();
          $userres = $sth->fetchAll();
          if(!empty($userres)){
             return true;
          }
          return false; 
      }   

//Team validation
           
        function isTeamnameExist($name,$id=null) {         
            $data = ["name"=>$name];
            if($id>0 && !is_null($id)){            
             $data = ["name"=>$name,"id"=>$id ];   
             $sql = "select id from teammaster Where teamname = :name AND id != :id ";          
            }else{
              $sql = "select id from teammaster Where teamname = :name ";    
            }          
            $sth = $this->db->prepare($sql);
            $sth->execute($data);
            $res = $sth->fetch();
            if(!empty($res)){
               return true;
            }
            return false; 
       }

       function isTeamShortnameExist($name,$id=null) {         
            $data = ["name"=>$name];
            if($id>0 && !is_null($id)){            
             $data = ["name"=>$name,"id"=>$id ];   
             $sql = "select id from teammaster Where shortname = :name AND id != :id ";          
            }else{
              $sql = "select id from teammaster Where shortname = :name ";    
            }          
            $sth = $this->db->prepare($sql);
            $sth->execute($data);
            $res = $sth->fetch();
            if(!empty($res)){
               return true;
            }
            return false; 
       }

      function isTeamIdNameExist($name,$id) 
      {                            
          $data = ["name"=>$name,"id"=>$id ];   
          $sql  = "select id from teammaster WHERE (teamname = :name OR shortname=:name) AND id = :id ";
          $sth  = $this->db->prepare($sql);
          $sth->execute($data);
          $res  = $sth->fetch();
          return $res;
       } 
     
     function checkTeamIdExist($id) {    
        $sql = "select id FROM teammaster Where id = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetchAll();
        if(!empty($res)){
           return false;
        }
        return true; 
    }

    function checkTeamPlayerExist($teamid,$pid){
        $sql = "select id FROM team WHERE teamid = :teamid AND pid = :pid ";        
        $sth = $this->db->prepare($sql);
        $param = ["teamid"=>$teamid,"pid"=>$pid];        
        $sth->execute($param);
        $res = $sth->fetchAll();
        if(!empty($res)){
           return $res;
        }
        return false;   
    }

    function checkTeamPlayerIdexist($id){
        $sql = "select id FROM team Where id = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetchAll();
        if(empty($res)){
           return true;
        }
        return false; 
    }

//match validation


    function isGameTypeExistByName($name) {
        $sql = "select id FROM games Where gname = :gname";        
        $sth = $this->db->prepare($sql);
        $param = ["gname"=>$name];        
        $sth->execute($param);
        $res = $sth->fetch();
        if(!empty($res)){
           return false;
        }
        return true; 
    }

     function isGameTypeExistById($id) {
        $sql = "select id FROM games Where id = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetch();
        return $res;
    }
   
     function isMatchAlreadyAdded($id){
        $sql = "select id FROM matches WHERE matchid = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetch();        
        if(!empty($res)){
           return true;
        }
        return false;   
    }

    function checkMidInMster($id){
        $sql = "select id FROM matchmaster WHERE unique_id = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetch();        
        return $res;   
    }

     function deleteMatch($matchid){

        $sql    = "DELETE FROM matches WHERE matchid = :matchid";        
        $sth    = $this->db->prepare($sql);
        $param  = ["matchid"=>$matchid];
        if($sth->execute($param)){
           $sql2 = "DELETE FROM matchmeta WHERE matchid = :matchid";
           $sth->execute($param);
           return true;
        }
        return false;   
    }

    function deleteMatchMeta($matchid){
        $sql    = "DELETE FROM matchmeta WHERE matchid = :matchid";        
        $sth    = $this->db->prepare($sql);
        $param  = ["matchid"=>$matchid];
        if($sth->execute($param)){
           return true;
        }
        return false;   
    }

 //contests  validation

    function isContestsExist($id){
        $sql = "select id FROM contests WHERE id = :id";        
        $sth = $this->db->prepare($sql);
        $param = ["id"=>$id];        
        $sth->execute($param);
        $res = $sth->fetchAll();
        if(!empty($res)){
           return true;
        }
        return false; 
    }

    function isConteststitleExist($name,$id=null) {         
            $data = ["name"=>$name];
            if($id>0 && !is_null($id)){            
             $data = ["name"=>$name,"id"=>$id ];   
             $sql = "select id from contests WHERE title = :name AND id != :id ";          
            }else{
              $sql = "select id from contests WHERE title = :name ";    
            }          
            $sth = $this->db->prepare($sql);
            $sth->execute($data);
            $res = $sth->fetch();
            if(!empty($res)){
               return true;
            }
            return false; 
       } 

//assign contests to match 
  function checkAssignconteststomatch($matchid,$contestid)
  {
      $sql = "select id,matchid,contestid FROM matchcontest WHERE matchid = :matchid AND contestid=:contestid";
      $sth = $this->db->prepare($sql);
      $param = ["matchid"=>$matchid,"contestid"=>$contestid];        
      $sth->execute($param);
      $res = $sth->fetch();
      if(!empty($res)){
         return $res;
      }
      return false; 
  } 

  function isCheckMatchContest($matchcontestid,$matchid,$contestid)
  {
      $sql = "select id,matchid,contestid FROM matchcontest WHERE matchid = :matchid AND contestid=:contestid AND id=:matchcontestid AND status=:status";

      $sth    = $this->db->prepare($sql);
      $status = ACTIVESTATUS;
      $param  = ["matchid"=>$matchid,"contestid"=>$contestid,"matchcontestid"=>$matchcontestid,"status"=>$status];        
      $sth->execute($param);
      $res   = $sth->fetch();
      return $res; 
  } 

  function checkMatchContest($matchid,$contestid)
  {
      $sql = "select id,matchid,contestid FROM matchcontest WHERE matchid = :matchid AND contestid=:contestid AND status=:status";

      $sth    = $this->db->prepare($sql);
      $status = ACTIVESTATUS;
      $param  = ["matchid"=>$matchid,"contestid"=>$contestid,"status"=>$status];        
      $sth->execute($param);
      $res   = $sth->fetch();
      return $res; 
  }

  function isMatchcontestidExist($matchcontestid){    
    $sql = "select id,matchid,contestid FROM matchcontest WHERE id = :matchcontestid";
    $sth = $this->db->prepare($sql);
    $param = ["matchcontestid"=>$matchcontestid];        
    $sth->execute($param);
    $res = $sth->fetch();
    if(!empty($res)){
       return $res;
    }
    return false; 
  }      
 
  function checkmatchcontestpool($matchcontestid){
       $sql = "SELECT id,matchcontestid FROM matchcontestpool WHERE matchcontestid = :matchcontestid";
       $sth = $this->db->prepare($sql);
       $param = ["matchcontestid"=>$matchcontestid];        
       $sth->execute($param);
       $res = $sth->fetch();
       if(!empty($res)){
         return $res;
       }
       return false; 
  }

  function checkMatchPoolByMidCid($matchid,$contestmetaid){

       $sql = "SELECT id FROM matchcontestpool WHERE matchid = :matchid AND contestmetaid = :contestmetaid";
       $sth = $this->db->prepare($sql);
       $param = ["matchid"=>$matchid,"contestmetaid"=>$contestmetaid];        
       $sth->execute($param);
       $res = $sth->fetch();
       return $res;
  }
 
  function deletematchcontestpool($id){
     $sql = "DELETE FROM matchcontestpool WHERE matchcontestid = :matchcontestid";
    $sth = $this->db->prepare($sql);
    $param = ["matchcontestid"=>$id];        
    if($sth->execute($param)){
      return true;
    }
    return false; 
  }

  function checkContestMetaId($id,$contestid){
    $sql = "SELECT id,contestid FROM contestsmeta WHERE id =:id AND contestid=:contestid";
    $sth = $this->db->prepare($sql);
    $param = ["id"=>$id,"contestid"=>$contestid];        
    $sth->execute($param);
    $res = $sth->fetch();
    if(!empty($res)){
       return $res;
    }
    return false; 
  }

   function checkContestMetaIdPoolBreak($id){
     $sql = "SELECT id,contestid FROM contestsmeta WHERE id =:id";
    $sth = $this->db->prepare($sql);
    $param = ["id"=>$id];        
    $sth->execute($param);
    $res = $sth->fetch();
    if(!empty($res)){
       return $res;
    }
    return false; 
  }
   

   function checkMatchContestStatus($matchid,$contestid)
   {
        $sql = "SELECT id,matchid,contestid FROM matchcontest WHERE matchid=:matchid AND contestid=:contestid AND status=:status";
        $sth   = $this->db->prepare($sql);         
        $param = ["matchid"=>$matchid,"contestid"=>$contestid,"status"=>ACTIVESTATUS];   
        $sth->execute($param);
        $res   = $sth->fetch();        
        if(!empty($res))
        {
            return $res;
        }
        return false; 
    }  
    
    function checkMatchContestPoolStatus($matchid,$contestid,$contestmetaid){

        $sql   = "SELECT id,contestmetaid FROM matchcontestpool WHERE matchid=:matchid AND contestid=:contestid AND contestmetaid=:contestmetaid";
        $sth   = $this->db->prepare($sql);         
        $param = ["matchid"=>$matchid,"contestid"=>$contestid,"contestmetaid"=>$contestmetaid];   
        $sth->execute($param);
        $res  = $sth->fetch();        
        if(!empty($res))
        {
            return $res;
        }
        return false; 
    }

    function chkMtchPoolcontest($matchid,$poolcontestid){

         $sql   = "SELECT id,contestmetaid FROM matchcontest WHERE matchcontestid=:matchcontestid AND contestmetaid=:contestmetaid";

         $sth   = $this->db->prepare($sql);         
         $param = ["matchcontestid"=>$matchcontestid,"contestmetaid"=>$contestmetaid];   
         $sth->execute($param);
         $res  = $sth->fetch();        
         if(!empty($res))
         {
            return $res;
         }
         return false; 
    }  

// front match id validation
  
      function checkMatchContestMatchid($matchid){

         $sql   = "SELECT id,matchid,contestid FROM matchcontest WHERE matchid=:matchid  AND status=:status";
         $sth   = $this->db->prepare($sql);         
         $param = ["matchid"=>$matchid,"status"=>ACTIVESTATUS];   
         $sth->execute($param);
         $res = $sth->fetchAll();        
         if(!empty($res))
         {
            return $res;
         }
         return false;          
     }  

    /* check matchid for join contest*/
      function checkMatchidForJoin($matchid){
         
         $sql1  = "SELECT matchid FROM matches WHERE matchid=:matchid";
         $sth1   = $this->db->prepare($sql1);         
         $sth1->execute(["matchid"=>$matchid]);
         $res1 = $sth1->fetch();
         if(empty($res1)){
          return false;
         }

         $sql = "SELECT id,matchid,contestid FROM matchcontest WHERE matchid=:matchid  AND status=:status";
         $sth   = $this->db->prepare($sql);         
         $param = ["matchid"=>$matchid,"status"=>ACTIVESTATUS];   
         $sth->execute($param);
         $res = $sth->fetchAll();        
         if(empty($res)){
          return false;
         }         
         return true; 
     }     

   


    function checkMatchPlayerExist($matchid,$pid){          
           $sql2      = "SELECT matchid,pid FROM matchmeta WHERE matchid=:matchid AND pid=:pid"; 
           $stmt2     = $this->db->prepare($sql2);
           $params2   = ["matchid"=>$matchid,"pid"=>$pid];
           $stmt2->execute($params2);
           $matchteam =  $stmt2->fetchAll();       
           if(!empty($matchteam)){
              return $matchteam;
           }
           return false;
    }
   
    // get Uteam By Id
    function getUteamById($teamid)
    {    
        $sql    = "SELECT id,userid,matchid FROM userteams WHERE id=:teamid";
        $stmt   =  $this->db->prepare($sql);
        $params =  ["teamid"=>$teamid];
        $stmt->execute($params);
        $teams  =  $stmt->fetch();
        return $teams;        
    }

    // Check team create limit by user
    function checkTeamCreateLimit($matchid,$uid,$userteamid=NULL)
    { 

      $ckSql = "";
      $params = ["matchid"=>$matchid,"userid"=>$uid];
      if(!is_null($userteamid)){
        $ckSql = "AND id != :userteamid ";
        $params = $params + ["userteamid"=>$userteamid];
      }

        $sql = "SELECT id,userid,matchid FROM userteams WHERE matchid=:matchid AND userid=:userid ".$ckSql;
        $stmt  =  $this->db->prepare($sql);
        $stmt->execute($params);
        $teams =  $stmt->fetchAll();
        return $teams;        
    }

    // Check team create limit by user
    function isUserTeamExist($matchid,$uid,$teamid)
    {    
        $sql    = "SELECT id,userid,matchid FROM userteams WHERE matchid=:matchid AND userid=:userid AND id=:teamid";

        $stmt   =  $this->db->prepare($sql);
        $params =  ["matchid"=>$matchid,"userid"=>$uid,"teamid"=>$teamid];
        $stmt->execute($params);
        $teams  =  $stmt->fetch();
        if(!empty($teams)){
          return $teams;
        }
        return false;
    }

    //check contest pool id
    function checkPoolForJoinContest($matchid,$poolcontestid)
    {
         $sql     = "SELECT id,contestid FROM matchcontest WHERE status=:status AND matchid=:matchid "; 
         $stmt    = $this->db->prepare($sql);
         $params  = ["status"=>ACTIVESTATUS,"matchid"=>$matchid];
         $stmt->execute($params);
         $matchContests = $stmt->fetchAll();

         if(empty($matchContests)){
           return false;
         }

         $poolsArr = [];
         foreach ($matchContests as $contest) {
           $sql2 = "SELECT contestmetaid FROM matchcontestpool WHERE matchcontestid=:matchcontestid";
           $stmt2 = $this->db->prepare($sql2);
           $params2=["matchcontestid"=>$contest['id']];
           $stmt2->execute($params2);
           $contestsMeta =  $stmt2->fetchAll();
           if(!empty($contestsMeta)){
               foreach ($contestsMeta as $pool) {
                  $poolsArr[] = $pool['contestmetaid'];
             } 
           }
         }

         if(!in_array($poolcontestid, $poolsArr)){
            return false;
         }
         return true;
     }

     //Check already Join cointest
    function checkAlreadyJoinContest($uid,$matchid,$poolcontestid,$uteamid)
    {    
        $sql    = "SELECT id FROM joincontests WHERE userid=:userid AND matchid=:matchid AND poolcontestid=:poolcontestid AND uteamid=:uteamid";
        $stmt   =  $this->db->prepare($sql);
        $params =  ["userid"=>$uid,"matchid"=>$matchid,"poolcontestid"=>$poolcontestid,"uteamid"=>$uteamid];
        $stmt->execute($params);
        $res  =  $stmt->fetch();
        if(!empty($res)){
          return $res;
        }
        return false;
    }


    function getUsrJoinTeamInPool($uid,$matchid,$poolcontestid)
    {    
      $sql    = "SELECT id FROM joincontests WHERE userid=:userid AND matchid=:matchid AND poolcontestid=:poolcontestid";
      $stmt   =  $this->db->prepare($sql);
      $stmt->execute(["userid"=>$uid,"matchid"=>$matchid,"poolcontestid"=>$poolcontestid]);
      $res  =  $stmt->fetchAll();
      return $res;      
    }

    //Get total joined team in a match contest
    function getTtlCountTmJoinedInPool($matchid,$poolcontestid)
    {    
        $sql    = "SELECT count(id) as totljoind FROM joincontests WHERE poolcontestid=:poolcontestid AND matchid=:matchid";
        $stmt   =  $this->db->prepare($sql);
        $params =  ["poolcontestid"=>$poolcontestid,"matchid"=>$matchid];
        $stmt->execute($params);
        $res    =  $stmt->fetch();

        if(!empty($res)){
          return $res;
        }
        return false;
    }

    //Check match id in joined contest
    function checkMatchInJoinedcontest($uid,$matchid,$poolcontestid=null)
    {    

        $sql    = "SELECT id FROM joincontests WHERE matchid=:matchid AND userid=:userid";
        $stmt   =  $this->db->prepare($sql);
        $params =  ["matchid"=>$matchid,"userid"=>$uid,"userid"=>$uid];
        $stmt->execute($params);
        $res    =  $stmt->fetch();
        if(!empty($res)){
          return $res;
        }
        return false;
    }
    
    
    //Get pool break prize

    function getPoolBreakPrize($poolcontestid)
    {    
        $sql = "SELECT pmin,pmax,pamount FROM poolprizebreaks ppr WHERE poolcontestid=:poolcontestid";           
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["poolcontestid"=>$poolcontestid]);
        $matches = $stmt->fetchAll();
        return $matches;
    }

     //Delete user Joined contest
    function deleteUsrJoinContest($id)
    {
        $sql     = "DELETE FROM joincontests WHERE id=:id"; 
        $stmt    = $this->db->prepare($sql);
        $params  = ["id"=>$id];
        if($stmt->execute($params)){
           return true;
        }
        return false;
     }


     public function sameTeamValidation($checkTeamCreateLimit,$userteamplayers,$iscap,$isvcap){
      foreach ($checkTeamCreateLimit as $uteam) {

            $sql = "SELECT pid,iscap,isvcap FROM userteamplayers WHERE userteamid=:userteamid";
            $stmt  =  $this->db->prepare($sql);
            $stmt->execute(["userteamid"=>$uteam['id']]);
            $teamPlrs =  $stmt->fetchAll();

            $chkPlrArr = [];
            $chkVcap = '';
            $chkCap = '';            
            foreach ($teamPlrs as $plr) {

              $chkPlrArr[] =  $plr['pid'];
              if($plr['iscap'] == 1 ){  $chkCap = $plr['pid'];  }
              if($plr['isvcap'] == 1 ){  $chkVcap = $plr['pid'];  }
            }

             sort($chkPlrArr); 
             sort($userteamplayers); 
          
            if($chkPlrArr === $userteamplayers && $iscap ==$chkCap && $isvcap == $chkVcap){
               return true;
            }                        
          }

        return false ;  
     }

   //--Create team front End--
   public function createteamuserFunction($input,$uid){
       
          $returnArr = [];

          $gameid           = $input["gameid"];//cricket
          $matchid          = $input["matchid"];   
          $userteamplayers  = $input["userteamplayers"]; 
          $iscap            = $input["iscap"]; 
          $isvcap           = $input["isvcap"]; 

         
        if(empty($userteamplayers) || !is_array($userteamplayers))
        {
           return $returnArr = ["error"=>true,"msg"=>"Please select teamplayers"];            
        }

        if($iscap == $isvcap)
        {
           return $returnArr = ["error"=>true,"msg"=>"Cap and Vcap should not be one player "];           
        }

     try{

        $this->db->beginTransaction();
            
        $checkMatchContestMatchid = $this->checkMatchContestMatchid($matchid);
        if(empty($checkMatchContestMatchid))
        {
          return $returnArr = ["error"=>true,"msg"=>"Invalid matchid"];            
        }

        if($this->checkMatchFreaz($matchid)){
           return $returnArr=["error"=>true,"msg"=>"You can't create team"];  
        }

        $checkTeamCreateLimit = $this->checkTeamCreateLimit($matchid,$uid);
        if(count($checkTeamCreateLimit) >=11){
            return $returnArr=["error"=>true,"msg"=>"Not create more then 11 teams"];              
        }

        $usrProfile = $this->getUserProfileDetail($uid);
        $countTeam  = count($checkTeamCreateLimit) + 1; 
        $teamname   = $usrProfile['teamname']."(".$countTeam.")"; 

      /*
        $checkContestMetaId = $this->checkContestMetaId($poolid,$contestid);
        if(empty($checkContestMetaId))
        {
          return $returnArr=["error"=>true,"msg"=>"Invalid pool id"];            
        }
      */
        $userteamplayers = array_unique($userteamplayers); 
      
        if(!in_array($iscap, $userteamplayers) || !in_array($isvcap, $userteamplayers)){
          return $returnArr=["error"=>true,"msg"=>"cap or vcap should be from this team"];   
        }

        $playersIds = [];
        foreach ($userteamplayers as $pid) 
        {          
          $playersIds[] =  "'".$pid."'";

          $checkMatchPlayerExist = $this->checkMatchPlayerExist($matchid,$pid); 
          if(empty($checkMatchPlayerExist)){
             return $returnArr=["error"=>true,"msg"=>"Invalid player id"];   
          }  
        } 

        $playersIds = implode(',', $playersIds);  
        
        //$sql  = "SELECT playertype,count(playertype) ptypecount FROM playersmaster WHERE pid IN ($playersIds) GROUP BY playertype ";
        $sql  = "SELECT playertype,count(playertype) ptypecount FROM matchmeta WHERE pid IN ($playersIds) AND matchid=:matchid GROUP BY playertype ";
              $stmt     = $this->db->prepare($sql);
              $stmt->execute(["matchid"=>$matchid]);
              $restplrs =  $stmt->fetchAll();  
       
        /*  WK=1, BAT=2{3-5}, AR=3{1-3}, BOWL=4{3-5} */
                    
          $resVali = $this->teamPlrSelectVali($gameid,$restplrs,$userteamplayers);
          if($resVali['error']){            
            return $resVali;
          } 

       if(count($checkTeamCreateLimit) > 0){

          if($this->sameTeamValidation($checkTeamCreateLimit,$userteamplayers,$iscap,$isvcap)){
            return  $returnArr=["error"=>true,"msg"=>"This team already created"];   
          }

        }
        
        $sql = "INSERT INTO userteams (userid,matchid,teamname,cap,vcap) VALUES (:userid,:matchid,:teamname,:cap,:vcap)";        
        $stmt = $this->db->prepare($sql);        
        $stmt->bindParam(':userid', $uid);
        $stmt->bindParam(':matchid', $matchid);
        $stmt->bindParam(':teamname', $teamname);
        $stmt->bindParam(':cap', $iscap);
        $stmt->bindParam(':vcap', $isvcap);

      /*  $stmt->bindParam(':contestid', $contestid);
        $stmt->bindParam(':poolid', $poolid);*/
        
      if($stmt->execute()){
                $lastId = $this->db->lastInsertId();  
                foreach ($userteamplayers as $userteamplayer) {
                      $iscapval = 0;
                      $isvcapval= 0;
                      if($userteamplayer == $iscap){ $iscapval   = 1; }
                      if($userteamplayer == $isvcap){ $isvcapval = 1; }   

                     $sql2 = "INSERT INTO userteamplayers (userteamid,pid,iscap,isvcap) VALUES (:userteamid,:pid,:iscap,:isvcap)";

                     $stmt2  = $this->db->prepare($sql2);
                     $params = ["userteamid"=>$lastId,"pid"=>$userteamplayer,"iscap"=>$iscapval,"isvcap"=>$isvcapval];
                     if(!$stmt2->execute($params)){
                         $stmtDel = $this->db->prepare("DELETE FROM userteams WHERE id=:id");
                         $stmt->execute(["id"=>$lastId]);
                         return $returnArr=["error"=>true,"msg"=>"Team not created, There is some problem"];              
                     }
                }
                $this->db->commit();   
               return $returnArr=["error"=>false,"msg"=>"Team created succesfully"];                
        }else{
           return $returnArr=["error"=>true,"msg"=>"Team not created, There is some problem"];
        }     
     } 
     catch(Exception $e)
     {    
        $this->db->rollBack();   
        return $returnArr=["error"=>true,"msg"=>$this->pdoErrorMsg($e->getMessage())];
     }        
   }

  
   //--Update team front End--
   public function updateteamuserFunction($input,$uid,$atype=null)
   {       
          $returnArr = [];

          $gameid           = $input["gameid"]; //cricket 
          $userteamid       = $input["userteamid"];
          $matchid          = $input["matchid"];
          $userteamplayers  = $input["userteamplayers"];
          $iscap            = $input["iscap"];
          $isvcap           = $input["isvcap"];
         
      if(empty($userteamplayers) || !is_array($userteamplayers))
      {
         return $returnArr = ["error"=>true,"msg"=>"Please select teamplayers"];            
      }

        if($iscap == $isvcap)
        {
           return $returnArr = ["error"=>true,"msg"=>"Cap ans Vcap should not be one player "];           
        }

     try{
        $this->db->beginTransaction();

        $checkMatchContestMatchid = $this->checkMatchContestMatchid($matchid);
        if(empty($checkMatchContestMatchid))
        {
          return $returnArr = ["error"=>true,"msg"=>"Invalid matchid"];            
        }

        if($atype != 'admin' && $this->checkMatchFreaz($matchid)){
           return $returnArr=["error"=>true,"msg"=>"You can't update team"];  
        } 

        $isUserTeamExist = $this->isUserTeamExist($matchid,$uid,$userteamid);
        if(empty($isUserTeamExist)){
            return $returnArr = ["error"=>true,"msg"=>"Invalid userteamid"];            
        }

        $userteamplayers = array_unique($userteamplayers); 

        if(!in_array($iscap, $userteamplayers) || !in_array($isvcap, $userteamplayers)){
          return $returnArr=["error"=>true,"msg"=>"cap or vcap should be from this team"];   
        }

        /*if(count($userteamplayers) != 11){
          return $returnArr=["error"=>true,"msg"=>"Players should be 11"];   
        }*/

        foreach ($userteamplayers as $pid) 
        {   
          $playersIds[] =  "'".$pid."'";       
          $checkMatchPlayerExist = $this->checkMatchPlayerExist($matchid,$pid); 
          if(empty($checkMatchPlayerExist)){
             return $returnArr=["error"=>true,"msg"=>"Invalid player id"];   
          }  
        } 

        $playersIds = implode(',', $playersIds);  

        $sql  = "SELECT playertype,count(playertype) ptypecount FROM matchmeta WHERE pid IN ($playersIds) AND matchid=:matchid GROUP BY playertype ";
              $stmt     = $this->db->prepare($sql);
              $stmt->execute(['matchid'=>$matchid]);
              $restplrs =  $stmt->fetchAll();  
       
        /*  WK=1, BAT=2{3-5}, AR=3{1-3}, BOWL=4{3-5} */
         /*  if(count($restplrs) != 4){
              return $returnArr=["error"=>true,"msg"=>"Choose player according to all playertype"];   
          }
        
       $ptp = $this->getPtype($gameid);

          foreach ($restplrs as $restplr) {

           if($restplr['playertype'] == 1 ){        
              $mn = $ptp['WK']['min'];
              $mx = $ptp['WK']['max'];
              if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
                 return $returnArr=["error"=>true,"msg"=>"WK should be one"];   
              }
           }
           if($restplr['playertype'] == 2 ){         
              $mn = $ptp['BAT']['min'];
              $mx = $ptp['BAT']['max'];
              if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
                 return $returnArr=["error"=>true,"msg"=>"BAT should be bitween ".$mn."-".$mx];   
              }
           }
           if($restplr['playertype'] == 3 ){         
              $mn = $ptp['AR']['min'];
              $mx = $ptp['AR']['max'];
              if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
                 return $returnArr=["error"=>true,"msg"=>"AR should be bitween ".$mn."-".$mx];   
              }
           }
           if($restplr['playertype'] == 4 ){           
              $mn = $ptp['BOWL']['min'];
              $mx = $ptp['BOWL']['max'];
              if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
                 return $returnArr=["error"=>true,"msg"=>"BOWL should be bitween ".$mn."-".$mx];   
              }
           }
        }*/

        $resVali = $this->teamPlrSelectVali($gameid,$restplrs,$userteamplayers);
          if($resVali['error']){            
            return $resVali;
          }

      $checkTeamCreateLimit = $this->checkTeamCreateLimit($matchid,$uid,$userteamid);

      if(count($checkTeamCreateLimit) > 0){
            if($this->sameTeamValidation($checkTeamCreateLimit,$userteamplayers,$iscap,$isvcap)){
              return  $returnArr=["error"=>true,"msg"=>"This team already created"];   
            }
      }
      
      $stmtDel = $this->db->prepare("DELETE FROM userteamplayers WHERE userteamid=:userteamid");

      if($stmtDel->execute(["userteamid"=>$userteamid])){
          $stmtUpdt = $this->db->prepare("UPDATE userteams SET cap = :cap,vcap = :vcap WHERE id = :userteamid ");
          $stmtUpdt->bindParam(':cap', $iscap);
          $stmtUpdt->bindParam(':vcap', $isvcap);
          $stmtUpdt->bindParam(':userteamid', $userteamid);
          $stmtUpdt->execute();
              
            foreach ($userteamplayers as $userteamplayer) {
                  $iscapval = 0;
                  $isvcapval= 0;
                  if($userteamplayer == $iscap){ $iscapval   = 1; }
                  if($userteamplayer == $isvcap){ $isvcapval = 1; }   

                 $sql2 = "INSERT INTO userteamplayers (userteamid,pid,iscap,isvcap) VALUES (:userteamid,:pid,:iscap,:isvcap)";
                 $stmt2  = $this->db->prepare($sql2);
                 $params = ["userteamid"=>$userteamid,"pid"=>$userteamplayer,"iscap"=>$iscapval,"isvcap"=>$isvcapval];
                 $stmt2->execute($params);
            }

          $this->db->commit(); 
          return $returnArr=["error"=>false,"msg"=>"Team updated succesfully"];           
        }else{
           return $returnArr=["error"=>true,"msg"=>"Team not created, There is some problem"];
        }
     }
     catch(Exception $e)
     {
        $this->db->rollBack(); 
        return $returnArr=["error"=>true,"msg"=>$this->pdoErrorMsg($e->getMessage())];
     }        
   }


   function teamPlrSelectVali($gameid,$restplrs,$userteamplayers){
    $returnArr = ["error"=>false,"msg"=>""];
    $ptp = $this->getPtype($gameid);
    if($gameid ==1){   /*Cricket*/
      if(count($userteamplayers) != 11){
          return $returnArr=["error"=>true,"msg"=>"Players should be 11"];   
      }
      if(count($restplrs) != 4)
      {
          return $returnArr=["error"=>true,"msg"=>"Choose player according to all playertype"];   
      }      
      foreach ($restplrs as $restplr){
        if($restplr['playertype'] == 1 ){         /*---WK---*/
            $mn = $ptp['WK']['min'];
            $mx = $ptp['WK']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"WK should be one"];   
            }
        }
        if($restplr['playertype'] == 2 ){         /*---BAT---*/
            $mn = $ptp['BAT']['min'];
            $mx = $ptp['BAT']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"BAT should be bitween ".$mn."-".$mx];   
            }
        }
        if($restplr['playertype'] == 3 ){         /*---AR---*/
            $mn = $ptp['AR']['min'];
            $mx = $ptp['AR']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"AR should be bitween ".$mn."-".$mx];   
            }
        }
        if($restplr['playertype'] == 4 ){           /*---BOWL---*/
            $mn = $ptp['BOWL']['min'];
            $mx = $ptp['BOWL']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"BOWL should be bitween ".$mn."-".$mx];   
            }
        }
      }
    }
    else if($gameid ==2){   /*Football*/
      if(count($userteamplayers) != 11){
          return $returnArr=["error"=>true,"msg"=>"Players should be 11"];   
      }
      if(count($restplrs) != 4)
      {
          return $returnArr=["error"=>true,"msg"=>"Choose player according to all playertype"];   
      }      
      foreach ($restplrs as $restplr){
        if($restplr['playertype'] == 8 ){         /*---GK---*/
            $mn = $ptp['GK']['min'];
            $mx = $ptp['GK']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"GK should be one"];
            }
        }
        if($restplr['playertype'] == 9 ){         /*---DEF---*/
            $mn = $ptp['DEF']['min'];
            $mx = $ptp['DEF']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"DEF should be bitween ".$mn."-".$mx];   
            }
        }
        if($restplr['playertype'] == 10 ){         /*---MID---*/
            $mn = $ptp['MID']['min'];
            $mx = $ptp['MID']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"MID should be bitween ".$mn."-".$mx];   
            }
        }
        if($restplr['playertype'] == 11 ){           /*---FWD---*/
            $mn = $ptp['FWD']['min'];
            $mx = $ptp['FWD']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"FWD should be bitween ".$mn."-".$mx];   
            }
        }
      }
    }
    else if($gameid ==3){ /*kabaddi*/
      if(count($userteamplayers) != 7){
          return $returnArr=["error"=>true,"msg"=>"Players should be 7"];   
      }
      if(count($restplrs) != 3)
      {
          return $returnArr=["error"=>true,"msg"=>"Choose player according to all playertype"];   
      }
      foreach ($restplrs as $restplr){
        if($restplr['playertype'] == 5 ){         /*---DEF---*/
            $mn = $ptp['DEF']['min'];
            $mx = $ptp['DEF']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"DEF should be one"];   
            }
        }
        if($restplr['playertype'] == 6 ){         /*---RAID---*/
            $mn = $ptp['RAID']['min'];
            $mx = $ptp['RAID']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"RAID should be bitween ".$mn."-".$mx];   
            }
        }
        if($restplr['playertype'] == 7 ){         /*---AR---*/
            $mn = $ptp['AR']['min'];
            $mx = $ptp['AR']['max'];
            if($restplr['ptypecount'] < $mn || $restplr['ptypecount'] > $mx){
               return $returnArr=["error"=>true,"msg"=>"AR should be bitween ".$mn."-".$mx];   
            }
        }       
      }
    }
    return $returnArr;
  }

/*   JOIN CONTEST   */

    // Get User Balance
    function getUserWalletBalance($uid){
           $sql2      = "SELECT walletbalance,wltwin,wltbns FROM users WHERE id=:id"; 
           $stmt2     = $this->db->prepare($sql2);
           $params2   = ["id"=>$uid];
           $stmt2->execute($params2);
           $res =  $stmt2->fetch();       
           if(!empty($res)){
              return $res;
           }
           return false;
    }
    
    function updateUserWalletBalance($uid,$balance){
           $sql2      = "UPDATE  users SET walletbalance=:walletbalance WHERE id=:id"; 
           $stmt2     = $this->db->prepare($sql2);
           $params2   = ["walletbalance"=>$balance,"id"=>$uid];
           $stmt2->execute($params2);
           $count2 = $stmt2->rowCount();
           if($count2 > 0){
              return true;
           }
           return false;
    }
 
    function updateUserWallet($uid,$wlt,$amount){
           $sql2      = "UPDATE  users SET ".$wlt."=:amount WHERE id=:id"; 
           $stmt2     = $this->db->prepare($sql2);
           $params2   = ["amount"=>$amount,"id"=>$uid];
           $stmt2->execute($params2);
           $count2 = $stmt2->rowCount();
           if($count2 > 0){
              return true;
           }
           return false;
    }
   
    function getPoolDetails($poolid){
           $sql2      = "SELECT id,contestid,joinfee,totalwining,winners,maxteams,c,m,s FROM contestsmeta WHERE id=:id AND status=:status";
           $stmt2     = $this->db->prepare($sql2);
           $status    = ACTIVESTATUS;
           $params2   = ["id"=>$poolid,"status"=>$status];
           $stmt2->execute($params2);
           $res       =  $stmt2->fetch();
           if(!empty($res)){
              return $res;
           }
           return false;
    }

    

    function getTransactionId($id){
           $sql2      = "SELECT id,txid FROM transactions WHERE txid=:id"; 
           $stmt2     = $this->db->prepare($sql2);
           $params2   = ["id"=>$id];
           $stmt2->execute($params2);
           $res       =  $stmt2->fetch();   
           if(!empty($res)){
              return $res;
           }
           return false;
    }    

 function checkPanValidation($pnumber){
          $pattern  = '/^([a-zA-Z]){5}([0-9]){4}([a-zA-Z]){1}?$/';
          $result   = preg_match($pattern, $pnumber);
          if ($result) {
              $findme = ucfirst(substr($value, 3, 1));
              $mystring = 'CPHFATBLJG';
              $pos = strpos($mystring, $findme);
              if ($pos === false) {
                  return true;
              } else {
                  return false;
              }
          } else {
              return true;
          }
    }   

 function getMatchPoints($game,$mtype){
      $dir  = __DIR__ . '/settings/'.GLOBALPOINTSKEY; 
      $addset = @file_get_contents($dir);

      if ($addset === false) {
        return false;
      } else {
          $res = json_decode($addset, true);
      }      
      $res1 = $res[GLOBALPOINTSKEY][$game][$mtype];      
      if(!empty($res1)){
        return $res1; 
      }else{
        return false; 
      }
    }   


    public static function getglobalsetting(){

      $dir  = __DIR__ . '/settings/'.GLOBALSETTING; 
      $addset = @file_get_contents($dir);

      if ($addset === false) {
        return false;
      } else {
          $res = json_decode($addset, true);
      }
      
      $res1 = $res[GLOBALSETTING];

      if(!empty($res1)){
        return $res1; 
      }else{
        return false; 
      }
    }

    function getOrderById($id){

          $sql2      = "SELECT id,userid FROM orders WHERE id=:id"; 
          $stmt2     = $this->db->prepare($sql2);
          $params2   = ["id"=>$id];
          $stmt2->execute($params2);
          $res       =  $stmt2->fetch();   
          return $res;           
    }

    function getOrderByRazorpayId($rorderid){

          $sql2      = "SELECT id,userid FROM orders WHERE rorderid=:rorderid"; 
          $stmt2     = $this->db->prepare($sql2);
          $params2   = ["rorderid"=>$rorderid];
          $stmt2->execute($params2);
          $res       =  $stmt2->fetch();   
          return $res;           
    }

    function chkRazorpayId($banktxnid,$paytype){

          $sql2      = "SELECT banktxnid FROM transactions t INNER JOIN transactionchild tch ON t.id=tch.tid WHERE tch.paytype=:paytype AND tch.banktxnid=:banktxnid"; 
          $stmt2     = $this->db->prepare($sql2);
          $params2   = ["paytype"=>$paytype,'banktxnid'=>$banktxnid];
          $stmt2->execute($params2);
          $res       =  $stmt2->fetch();   
          return $res;           
    }

    function getPtype($gameid){
        
        $sql = "SELECT id,name,fullname,min,max FROM playertype WHERE gameid=:gameid";
        $sth = $this->db->prepare($sql);
        $sth->execute(["gameid"=>$gameid]);
        $res = $sth->fetchAll();
        $resArr = [];
        if(!empty($res)){
          foreach ($res as $row) {
              $name = $row['name'];
              $min  = $row['min'];
              $max  = $row['max'];
              $resArr[$name]['min'] = $row['min'];      
              $resArr[$name]['max'] = $row['max'];      
          } 
        }                  
        return $resArr;
    }

    public function checkPidInMatch($matchid,$pid){
          $sql2      = "SELECT id,userid FROM orders WHERE id=:id"; 
          $stmt2     = $this->db->prepare($sql2);
          $params2   = ["id"=>$id];
          $stmt2->execute($params2);
          $res       =  $stmt2->fetch();   
          return $res;  
    }

    public function getUserTeamRank($params)
    {        
        $sql = "SELECT
 * from (SELECT (@sno := @sno + 1) as sno, jc.userid,jc.uteamid,ut.teamname,jc.winbal,jc.ptotal, @rank := (CASE 
WHEN @rankval = jc.ptotal THEN @rank
    WHEN (@rankval := jc.ptotal) IS NOT NULL THEN @sno
    WHEN (@rankval := jc.ptotal) IS NOT NULL THEN 1
END) AS rank
FROM joincontests jc INNER JOIN userteams ut ON jc.uteamid=ut.id, 
(SELECT @sno :=0, @rank := 0, @partval := NULL, @rankval := NULL) AS x
WHERE jc.matchid=:matchid AND jc.poolcontestid=:poolcontestid 
ORDER BY jc.ptotal desc) tbl WHERE userid =:userid ";

        $stmt    = $this->db->prepare($sql);  
        $stmt->execute($params);
        return $stmt->fetchAll();          
    }

    public function getBreakPrizeVali($pbp)
    {     
      $rtnArr = [];    
      $i=0;
      for ($i=0; $i < count($pbp); $i++) { 

          if($pbp[$i]['pmin'] <= 0 || $pbp[$i]['pmax'] <= 0 || $pbp[$i]['pmin'] > $pbp[$i]['pmax']){
            return $rtnArr = ["error"=>true,"msg"=>"Invalid rank value"]; 
          }

          if($i>0){
            if($pbp[$i-1]['pmax'] >= $pbp[$i]['pmin'] ){
              return $rtnArr = ["error"=>true,"msg"=>"Invalid rank value"]; 
            }
          }
          
          return $rtnArr = ["error"=>false,"msg"=>"Valid"];
          /*
          $prizeBreak[$i]['pmin'];
          $prizeBreak[$i]['pman'];
          $prizeBreak[$i]['pamount']; */
      }
    }

    public function getMatchSetting($gameid)
    {
        $sql2      = "SELECT mxfromteam,tmsize FROM settingmatch WHERE gameid=:gameid"; 
        $stmt2     = $this->db->prepare($sql2);
        $params2   = ["gameid"=>$gameid];
        $stmt2->execute($params2);
        $res       =  $stmt2->fetch();   
        return $res;  
    }

    public function getrefcommisions($rtype)
    {
        $sql2  = "SELECT rtype,amount FROM referalcommisions WHERE rtype=:rtype";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute(["rtype"=>$rtype]);
        return $res  =  $stmt2->fetch();
    }

    public function getTimeBeforMatch()
    {        
        return $res = ["hour"=>0,'minutes'=>0];           
    }

    public function checkMatchFreaz($matchid)
    {
        $ctime    = time(); 
        $sql      = "SELECT mdate,mdategmt,mstatus,status FROM matches WHERE matchid=:matchid"; 
        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["matchid"=>$matchid]);
        $res      =  $stmt->fetch();   
        $mdtm = $res['mdategmt'];          
        if($ctime >= $mdtm || $res['mstatus'] != UPCOMING )
        {
          return true;
        }
        return false;
    }

     public function getMatchStatus($matchid)
    {        
        $sql      = "SELECT mstatus FROM matches WHERE matchid=:matchid"; 
        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["matchid"=>$matchid]);
        $res      =  $stmt->fetch();   
        return $res;        
    }

    public function getMatchDetails($matchid)
    {        
        $sql      = "SELECT gameid,matchname FROM matches WHERE matchid=:matchid"; 
        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["matchid"=>$matchid]);
        $res      =  $stmt->fetch();   
        return $res;        
    }


  public function kycNoti($uid,$subject,$message){    
      $created = time();    
      $stmt    = $this->db->prepare("INSERT INTO notifications SET userid=:userid,subject=:subject,message=:message,created=:created");
      $stmt->execute(["userid"=>$uid,"subject"=>$subject,"message"=>$message,"created"=>$created]);
  }

  /*public function sendSms($number,$message){    
       
    global $settings;        
    $twilio         = $settings['settings']['twilio'];
    $account_sid    = $twilio['account_sid'];
    $auth_token     = $twilio['auth_token'];
    $twilio_number  = $twilio['twilio_number'];

    $client = new Client($account_sid, $auth_token);
    $client->messages->create(
        $twilio['ccode'].$number,
        array(
            'from' => $twilio_number,
            'body' => $message
        )
    );    
  }*/

  
  public function updateTransaction($uid,$amount,$created,$docid,$ttype,$atype,$wlt,$prebal,$curbal,$status=NULL){
    $subSql=' ';
    $params = ["userid"=>$uid,"amount"=>$amount,"created"=>$created,"docid"=>$docid,"ttype"=>$ttype,"atype"=>$atype,"wlt"=>$wlt,"prebal"=>$prebal,"curbal"=>$curbal];
    if(!is_null($status)){
      $subSql = ",status=:status";
      $params = $params + ["status"=>$status];
    }

    $stmt3 = $this->db->prepare("INSERT INTO transactions SET userid=:userid,amount=:amount,txdate=:created,docid=:docid,ttype=:ttype,atype=:atype,wlt=:wlt,prebal=:prebal,curbal=:curbal".$subSql);
    $stmt3->execute($params);

  }

  public function updateRefferTbl($uid,$referedby,$amount,$created){
    $params = ["userid"=>$uid,"amount"=>$amount,"created"=>$created,"referedby"=>$referedby];       
    $stmt3 = $this->db->prepare("UPDATE referralby SET givenbns=:amount,updatedate=:created WHERE userid=:userid AND referedby=:referedby");
    $stmt3->execute($params);
  }
    
  // Check team create limit by user
  function menuExist($menu)
  {    
      $sql    = "SELECT id FROM menus WHERE id=:menuid";
      $stmt   =  $this->db->prepare($sql);
      $stmt->execute(["menuid"=>$menu]);
      $res    =  $stmt->fetch();
      return $res;
  }


    public function playerDelCheck($pid)
    {        
        $sql      = "SELECT pid FROM matchmeta WHERE pid=:pid"; 
        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["pid"=>$pid]);
        $res      =  $stmt->fetch();   
        if(!empty($res)){ return true;  }

        $sql      = "SELECT pid FROM userteamplayers WHERE pid=:pid"; 
        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["pid"=>$pid]);
        $res      =  $stmt->fetch();   
        if(!empty($res)){ return true;  }                
        return false;
    }

    public function poolDelCheck($id)
    {        
        $sql      = "SELECT contestmetaid FROM matchcontestpool WHERE contestmetaid=:contestmetaid"; 
        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["contestmetaid"=>$id]);
        $res      =  $stmt->fetch();   
        if(!empty($res)){ return true;  }

        $sql      = "SELECT poolcontestid FROM joincontests WHERE poolcontestid=:poolcontestid"; 
        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["poolcontestid"=>$id]);
        $res      =  $stmt->fetch();   
        if(!empty($res)){ return true;  }                
        return false;
    }

    function checkPool($id){

      $sql = "SELECT id FROM contestsmeta WHERE id =:id ";
      $sth = $this->db->prepare($sql);
      $sth->execute(["id"=>$id]);
      $res = $sth->fetch();      
      return $res; 
    }



    public static function getIpAddr()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
           $ipaddress = '';
      
          return $ipaddress;
    }

      public static function createPushNotification($devicetype,$token,$msg,$title,$notification_data=null,$notification_id=1){

        if($devicetype == 'android'){
            $notification_data=[
                'to'=>$token,
                'data'=>[
                    'title'=>$title,
                    'body'=>$msg,
                    'notificationId'=>$notification_id,
                    'show_in_foreground'=>true,
                    'priority'=>'high',
                  //  'actions'=> 'com.fantasy.LAUNCHER',
                    'color'=>'#B1152A',
                    'autoCancel'=> true,
                    'channelId'=> 'my_default_channel',
                   // 'clickAction'=> 'com.fantasy.LAUNCHER',
                    'largeIcon'=> 'ic_launcher',
                    'lights'=> true,
                    'icon'=> 'ic_notif',
                    'notification_data' => $notification_data
                ],

                'priority'=>'high'
            ];
            self::sendFCM($notification_data);
        }
        elseif($devicetype == 'iphone'){
            $notification_data=[
                'to'=>$token,
                'content_available'=>false,
                'notification'=>[
                    'title'=>$title,
                    'body'=>$msg,
                    'notificationId'=>$notification_id,
                    'show_in_foreground'=>true,
                    'channelId'=> 'my_default_channel',
                    'sound'=> 'default',
                    'vibrate'=>'300',
                    'notification_data' => $notification_data
                ],
            ];
            self::sendFCM($notification_data);
        }
        return true;
    }


     public function notifyUser($notifyData){
    
      $sendFcmData=$notifyData;
      unset($sendFcmData['token'],$sendFcmData['devicetype']);
      if($notifyData['devicetype'] == 'android'){
            $notification_data=[
                'registration_ids'=>$notifyData['token'],
                'data'=>$sendFcmData,
                'notification'=>$sendFcmData,
                'priority'=>'high'
            ];
            self::sendFCM($notification_data);
        }
        elseif($notifyData['devicetype'] == 'iphone'){
            $notification_data=[
                'registration_ids'=>$notifyData['token'],
                'content_available'=>false,
                'notification'=>[
                    'title'=>$notifyData['title'],
                    'body'=>$notifyData['body'],
                    'notificationId'=>$notifyData['notify_id'],
                    'show_in_foreground'=>true,
                    'channelId'=> 'my_default_channel',
                    'sound'=> 'default',
                    'vibrate'=>'300',
                    'notification_data' => $notifyData
                ],
            ];
            self::sendFCM($notification_data);
        }
        return true;
    }


    public static function sendFCM($notification_data=[]){
        global $settings;                  
        $url = 'https://fcm.googleapis.com/fcm/send'; 
        $fields = json_encode ( $notification_data );
        $headers = [
            'Authorization: key='.$settings['settings']['fcm']['serverKey'],
            'Content-Type: application/json'
        ];
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POST, true );
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
        $result = curl_exec ( $ch );
        curl_close ( $ch );
        
        //file_put_contents("fcm.txt", print_r(json_encode($result),true));
        //print_r($result); 
        return true;
    }


    public static function sendMail($mailerObj)
    {

      /*$user['link']  = '/verifymail/';
      $user['email'] = 'developer@brsoftech.com';
      $user['name']  = 'br';

     $a = $mailerObj->sendMessage('/mail/verifyemail.php', ['data' => $user], function($message) use($user) {
            $name = " ";
            if(!empty($user['name'])){            
              $name = $user['name'];
            }         
            //$user['email'] = 'xxx@mailinator.com';
              $message->setTo($user['email'],$name);
              $message->setSubject('Verify email address');
        });  

      print_r($a); 
      echo "one";
      die;*/

    }

    public function poolAssignCheck($pool,$contestid,$matchid)
    {        
        $sql = "SELECT mcp.contestmetaid,count(jc.poolcontestid) as cjoin 
        FROM matchcontestpool mcp 
        LEFT JOIN  joincontests jc 
        ON mcp.contestmetaid=jc.poolcontestid AND jc.matchid=:matchid
        WHERE mcp.contestmetaid=:contestmetaid AND mcp.contestid=:contestid
        AND mcp.matchid=:matchid HAVING cjoin > 0"; 

        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["contestmetaid"=>$pool,"contestid"=>$contestid,"matchid"=>$matchid]);
        $res      =  $stmt->fetch();   

        return $res;
    }

    public function cotestAssignCheck($matchid,$contestid)
    {        
        $sql = "SELECT contestmetaid FROM matchcontestpool 
        WHERE contestid=:contestid AND matchid=:matchid "; 
        $stmt     = $this->db->prepare($sql);
        $stmt->execute(["contestid"=>$contestid,"matchid"=>$matchid]);
        $results      =  $stmt->fetchAll(); 
        if(!empty($results)){
          foreach ($results as $row) {
            $res1  = self::poolAssignCheck($row['contestmetaid'],$contestid,$matchid);
            if(!empty($res1)) return true;
          }
        }        
       return false;
    }


    public function getAllDefineKeys($key_type=NULL){ 
      $list=['game_type'=>[CRICKET=>'Cricket',FOOTBALL=>'Football',HOCKEY=>'Hockey',NBA=>'NBA','all'=>'All'],
          'code_type'=>[ADD_MONEY=>'Add Money',JOIN_FANTASY=>'Join Fantasy',CREATE_CONTEST=>'Create Contest'],
          'rewords_type'=>[REWARDS_POINTS=>'Points',REWARDS_VALUE=>'Value'],
          'offer_value_type'=>[PERCENTAGE_VALUE=>'Percentage',AMOUNT_VALUE=>'Amount'],
          'statuss'=>[ACTIVESTATUS=>'Active',INACTIVESTATUS=>'Inactive',],
          'amounttype'=>[''=>'Select Amount Type',ADDBAL=>'Add Balance',PROMOBNS=>'Promocode Bonus'], 
          ];

          //OPBAL=>'',CLBAL=>'',WIN=>'',LOSS=>'',CJOIN=>'Contest Join',WELCOMEBNS=>'Welcome Balance',REFBNS=>'Referal Bonus',NTFLPOOL=>'Cancel Pool',WITHDRAW=>'Withdraw Amount'
          // offer value type
          //POINT_VALUE=>'Rewords Point', 
          //'apiname'=>[],
      return ($key_type)?$list[$key_type]:$list;
    }

    public function isPromoCodeExist($pcode,$cid=NULL){

      if(!empty($cid)){
      $sql = "SELECT id FROM promocode WHERE pcode=:pcode and id !=:id";  
        $stmt     = $this->db->prepare($sql);
        $params["pcode"]=strtoupper($pcode);
        $params["id"]=$cid;
        $stmt->execute($params);
        $results      =  $stmt->fetchAll(); 
        if(!empty($results)){
          return $results;
        }   

      }else{ 
       $sql = "SELECT id FROM promocode WHERE pcode=:pcode ";  
        $stmt     = $this->db->prepare($sql);
        $params["pcode"]=strtoupper($pcode);
        $stmt->execute($params);
        $results      =  $stmt->fetchAll(); 
        if(!empty($results)){
          return $results;
        }    
      }    
       return false;
    }

    public function isPromoCodeValid($input=[]){

      global $settings;                 
      $code    = $settings['settings']['code']['rescode'];
      $errcode = $settings['settings']['code']['errcode'];
      $pcode  = strtoupper($input['pcode']);
      $amount = (float)$input['amount'];
      $userid = $input['userid'];
      //var_dump($input);die;
      $date   = time();        
              $sql = "SELECT id,pctype,pcode,pc_val,pc_val_type,status,users_limit,pc_min_val,pc_max_val FROM promocode where pcode=:pcode and sdate<=:sdate and edate >=:edate"; 
          $stmt = $this->db->prepare($sql);
          $stmt->execute(['pcode'=>$pcode,'sdate'=>$date,'edate'=>$date]);
          $allPCodes = $stmt->fetch();
          
       if(!empty($allPCodes)){ 

           if($allPCodes['status']!=ACTIVESTATUS){
              $resArr['code']  = 1;
              $resArr['error'] = true;
              $resArr['msg']   = 'Expire promocode'; 
              return ['resArr'=>$resArr ,'code'=>$code];
          }

          if ($amount < $allPCodes ['pc_min_val'] || $amount > $allPCodes['pc_max_val']) 
          {
            
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = "Not Applicable! Please add amount between ".$allPCodes['pc_min_val']." to ".$allPCodes['pc_max_val']; 
                return ['resArr'=>$resArr ,'code'=>$code];
          }  
 
           
            $used = "SELECT count(id) as usedcount FROM promocodeuses where pcodeid=:pcodeid "; 
            $used = $this->db->prepare($used);
            $used->execute(['pcodeid'=>$allPCodes['id']]);
            $used = $used->fetch();
            
            if(!empty($used) &&  $allPCodes['users_limit']!=0 && $allPCodes['users_limit']<=$used['usedcount']) {
              $resArr['code']    = 1;
              $resArr['error'] = true;
              $resArr['msg']   = 'Exceed used';
              return ['resArr'=>$resArr ,'code'=>$code];
            }
              $pcuses = "SELECT id FROM promocodeuses where pcodeid=:pcodeid and userid=:userid"; 
              $pcuses = $this->db->prepare($pcuses);
              $pcuses->execute(['pcodeid'=>$allPCodes['id'],'userid'=>$userid,]);
              $pcuses = $pcuses->fetch();
              if(empty($pcuses)){
                  
                    if($allPCodes['pc_val_type'] == PERCENTAGE_VALUE ){
                      $bnsamount = ($allPCodes['pc_val']*$amount)/100;    
                    }
                    if($allPCodes['pc_val_type'] == AMOUNT_VALUE ){
                      $bnsamount = $allPCodes['pc_val'];      
                    }  

                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Applied'; 
                $resArr['data']  = ['id'=>$allPCodes['id'],'bonus'=>$bnsamount];
            }else{
                $resArr['code']    = 1;
                $resArr['error'] = true;
                $resArr['msg']   = 'Already used'; 
            } 
      
        }else{
            $resArr['code']   = 1;
            $resArr['error']  = true;
            $resArr['msg']    = 'Invalid Promocode';  
        }
          
       return ['resArr'=>$resArr ,'code'=>$code];
    }

    public function addUsesPromoCode($input){

      $stmt = $this->db->prepare("INSERT INTO promocodeuses (userid,pcodeid,created)VALUES(:userid,:pcodeid,:created)");
      $data = ["userid"=>$input['userid'],"pcodeid"=>$input['pcodeid'],'created'=>$input['created']];
      $stmt->execute($data);      
      $pcodeuse_lastid  = $this->db->lastInsertId();       
      return $pcodeuse_lastid;
    }



  function setTeamName($email){
        $tmname1=explode('@',$email)[0];
        $tmname = substr($tmname1,0,5).$this->random_strings(4,$number=true);
        $sql = "select count(id) as count FROM userprofile WHERE teamname= :teamname";        
        $sth = $this->db->prepare($sql);
        $param = ["teamname"=>$tmname];        
        $sth->execute($param);
        $res = $sth->fetch();
        if(!empty($res)){
          $tmname = substr($tmname1,0,5).$this->random_strings(4,$number=true);
        }
        return $tmname; 
  }

  function checkTeamName($teamname,$userid=false){
       
        $param["teamname"]=$teamname;
        if($userid){
        $sql = "select id  FROM userprofile WHERE teamname= :teamname AND userid !=:userid";
        $param['userid']=$userid;  
        }  
        else{
           $sql = "select id FROM userprofile WHERE teamname= :teamname";
        }      
        $sth = $this->db->prepare($sql);
              
        $sth->execute($param);
        $res = $sth->fetch();
        if(!empty($res)){
          return $res;
        }
        return false; 
  }

  function random_strings($length_of_string,$number=false) 
  { 
    
      $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'; 
      // String of all alphanumeric character 
      if($number)
       $str_result = '0123456789'; 
    
      // Shufle the $str_result and returns substring 
      // of specified length 
      return substr(str_shuffle($str_result),  
                         0, $length_of_string); 
  }


    public function cleanString($string) {
      $string = str_replace(' ', '', $string); 
      return preg_replace('/[^A-Za-z0-9\-]/', '', $string); 
    }

    public function getAllUserTypeCount(){
      $sql = "SELECT count(u.id) as approve FROM  users as u ".$approve." ORDER BY u.id DESC ";   

          $stmt = $this->db->prepare($sql);
          $stmt->execute($paramsA);
          $resApprove =  $stmt->fetch();
      return true;
    }

    public function getContestDetails($id){
        $sql = "select id,dis_val FROM contests WHERE id = :id";
        $sth = $this->db->prepare($sql);
        $sth->execute(["id"=>$id]);
        $res = $sth->fetch();
        return $res;
    }

    public function getContestDetailsPrivate(){
        $sql = "select id,dis_val FROM contests WHERE isprivate = :isprivate ";
        $sth = $this->db->prepare($sql);
        $sth->execute(["isprivate"=>1]);
        $res = $sth->fetch();
        return $res;
    }

    public function checkUniqueCodePrivateCntst($code){

        $sql = "select id FROM contestsmeta WHERE ccode = :code limit 1";
        $sth = $this->db->prepare($sql);
        $sth->execute(["code"=>$code]);
        $res = $sth->fetch();
        if(!empty($res)){
          $cd  = $this->generateRandomString(12);
          $this->checkUniqueCodePrivateCntst($cd);
        }
        return $code;
    }

    public function getContestByCode($code){
        $sql = "select id,joinfee FROM contestsmeta WHERE ccode = :code limit 1";
        $sth = $this->db->prepare($sql);
        $sth->execute(["code"=>$code]);
        $res = $sth->fetch();        
        return $res;
    }

    public function poolAndBreakPrizeAddDB($contestid,$joinfee,$totalwining,$winners,$maxteams,$c,$m,$s,$prizekeyvalue,$isprivate=false,$name=null,$ccode=null,$uid=1,$created=null){
          if(is_null($created)){
            $created = time();
          }
          
          $privateVar = ($isprivate)?1:0;

      $res = ["error"=>false,"msg"=>''];

      $isContestsExist = $this->isContestsExist($contestid);
            if(!$isContestsExist)
            {
              return $res = ['error'=>true,'msg'=>"Contests id not exist."];
            }

            if(($c != '0' AND $c !='1') || ($m != '0' AND $m !='1') || ($s != '0' AND $s !='1') )
            {
              return $res = ['error'=>true,'msg'=>"Invalid Confirm/Multiple/Single"];
            }   

            if(($m != '1' && $s != '1') || $m == $s){
              return $res = ['error'=>true,'msg'=>"Invalid Confirm/Multiple/Single"];              
            } 
                          
            if($maxteams < $winners){
              return $res = ['error'=>true,'msg'=>"Winners should be greater then maxteams"];              
            }

            if($joinfee > $totalwining)
            {
              return $res = ['error'=>true,'msg'=>"Joinfee greater then totalwining"];               
            }            
        
          if(empty($prizekeyvalue) || !is_array($prizekeyvalue)){
            return $res = ['error'=>true,'msg'=>"Pool prize Invalid"];              
            }

            $ckPrizeVali = $this->getBreakPrizeVali($prizekeyvalue);
            if($ckPrizeVali['error']==true){
              return $res = ['error'=>true,'msg'=>$ckPrizeVali['msg']];  
            }

        /*try{ */
            //$this->db->beginTransaction();
              $data = [
                          "contestid"   => $contestid,                 
                        "joinfee"     => $joinfee,      
                        "totalwining" => $totalwining,
                        "winners"     => $winners,      
                        "maxteams"    => $maxteams,      
                        "c"       => $c,      
                        "m"       => $m,      
                        "s"       => $s,      
                        "userid"    => $uid,      
                        "name"      => $name,      
                        "ccode"     => $ccode,
                        "created"   => $created,
                        "isprivate"   => $privateVar
                  ];          

      $stmt = $this->db->prepare("INSERT INTO contestsmeta (contestid,joinfee,totalwining,winners,maxteams,c,m,s,created,userid,ccode,name,isprivate) VALUES(:contestid,:joinfee,:totalwining,:winners,:maxteams,:c,:m,:s,:created,:userid,:ccode,:name,:isprivate)");  
      
      if($stmt->execute($data)){

        $lastId = $this->db->lastInsertId();        
            foreach ($prizekeyvalue as $row) {
              if($isprivate){
                $r = (($totalwining*$row['percent'])/100);
                $row['pamount'] = $r;
              }
              $data = ["poolcontestid"=>$lastId,"pmin"=>$row['pmin'],"pmax"=>$row['pmax'],"pamount"=>$row['pamount']];   


              $stmt = $this->db->prepare("INSERT INTO poolprizebreaks (poolcontestid,pmin,pmax,pamount) VALUES(:poolcontestid,:pmin,:pmax,:pamount)");  
              $stmt->execute($data);                                   
            }

            //$this->db->commit();
            return $res = ['error'=>false,'msg'=>"Record added successfully.",'createdid'=>$lastId,"ccode"=>$ccode];
        }else{
                  return $res = ['error'=>true,'msg'=>"Record not added, There is some problem"];
        }
    /*}
    catch(PDOException $e)
    {
      //$this->db->rollBack();
      $ms = \Security::pdoErrorMsg($e->getMessage());
      return $res = ['error'=>true,'msg'=>$ms];      
    }*/
  }

  public function getMatchTypeCricket($matchtype){
    
    switch ($matchtype) {
      case 't10':      
        $matchtype = 't20';
        break;      
      default:
        $matchtype = $matchtype;
        break;
    }
    return $matchtype;
  }

  public function getLoginTypeRummy($loginType){
    
    switch ($loginType) {
      case 'N':
        $string = 'NORMAL';
        break;
      case 'F':
        $string = 'FACEBOOK';
        break;
      case 'G':
        $string = 'GOOGLE';
      default:
        $string = 'NORMAL';
        break;
    }
    return $string;
  }


    public function ifsccodeCheck($ifsccode){
      global $settings;        
      $code     = $settings['settings']['code']['rescode'];
      $urls="https://ifsc.razorpay.com/$ifsccode";
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
            if(!empty($result) && $result!='Not Found') { 
              $rst=json_decode($result);
            if(isset($rst->IFSC) && strtoupper($rst->IFSC)==strtoupper($ifsccode)) {
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = "IFSC code verified";  
            $resArr['data']  = $rst;
          }else{
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = "Invalid IFSC code"; 
          } 
        }else{
          $resArr['code']  = 1;
          $resArr['error'] = true;
          $resArr['msg']   = "Invalid IFSC code"; 
        }
      return $resArr;
    }


   

    

}