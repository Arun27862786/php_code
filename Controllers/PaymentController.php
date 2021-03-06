<?php

namespace Apps\Controllers;

use \Firebase\JWT\JWT;
use Razorpay\Api\Api;
use Swift_TransportException;

class PaymentController
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

    public function updateTransaction($uid, $amount, $created, $docid, $ttype, $atype, $wlt, $prebal, $curbal)
    {
        $ref=$this->db->prepare("INSERT INTO transactions(userid,amount,txdate,docid,ttype,atype,wlt,prebal,curbal) VALUES (:userid,:amount,:txdate,:docid,:ttype,:atype,:wlt,:prebal,:curbal)");
        $ref->execute(["userid"=>$uid,"amount"=>$amount,"txdate"=>$created,"docid"=>$docid,"ttype"=>$ttype,"atype"=>$atype,"wlt"=>$wlt,"prebal"=>$prebal,"curbal"=>$curbal]);
    }

    public function update_user_wallet($uid, $amount, $txnid, $status, $txdate, $docid, $ttype, $atype, $wlt, $gatewayname, $pmode, $banktxnid, $bankname, $respcode, $description, $paytype=null)
    {
        try {
            $sql="SELECT txid FROM transactions WHERE txid=:txid";
            $stmt= $this->db->prepare($sql);
            $stmt->execute(['txid'=>$txnid]);
            $res= $stmt->fetch();
            if (empty($res)) {
                $a=time();
                $getWlt = $this->security->getUserWalletBalance($uid);
                $pevious_bal = $getWlt["walletbalance"] ;
                $newbal 	 = $pevious_bal + $amount ;
       
                $paytype=($paytype)?$paytype:'Paytm';
                $sql = "INSERT INTO transactions SET userid=:userid, amount=:amount, txid=:txid, status=:status, txdate=:txdate,docid=:docid,ttype=:ttype,atype=:atype,wlt=:wlt,prebal=:prebal,curbal=:curbal";
                $query  = $this->db->prepare($sql);
                $params = ["userid"=>$uid,"amount"=>$amount,"txid"=>$txnid,"status"=>$status,"txdate"=>$txdate,"docid"=>$docid,"ttype"=>$ttype,"atype"=>$atype,"wlt"=>$wlt,"prebal"=>$pevious_bal,"curbal"=>$newbal];
                $query->execute($params);
                $tid = $this->db->lastInsertId();
                if ($tid) {
                    $sql2 = "INSERT INTO transactionchild SET tid=:tid, gatewayname=:gatewayname, pmode=:pmode, banktxnid=:banktxnid, bankname=:bankname,respcode=:respcode,description=:description,paytype=:paytype";
                    $stmt  = $this->db->prepare($sql2);
                    $params = ["tid"=> $tid,"gatewayname"=>$gatewayname,"pmode"=>$pmode,"banktxnid"=>$banktxnid,"bankname"=>$bankname,"respcode"=>$respcode,"description"=>$description,'paytype'=>$paytype];
                    $stmt->execute($params);
                    $updtBal = $this->security->updateUserWallet($uid, "walletbalance", $newbal);
                }
            }
        } catch (\PDOException $e) {
            return false;
        }
	}
	
    public function orderRazorpay($request, $response)
    {
        global $settings,$baseurl;
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
            $result=$this->security->isPromoCodeValid(['amount'=>$amount,'pcode'=>$promocode,'userid'=>$userid]);
            if (!empty($result) && $result['resArr']['code']==0) {
                $pcodeid = $result['resArr']['data']['id'];
            } else {
                $resArr = $this->security->errorMessage($result['resArr']['msg']);
                return $response->withJson($result['resArr'], $result['code']);
            }
        }
        
        $resArr=$this->createOrderOnRazorpay($amount, $userid, $pcodeid);
        return $response->withJson($resArr);
    }

    public function createOrderOnRazorpay($amount, $userid, $pcodeid=null)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $resArr =  [];

        try {
            $pcodeid =  ($pcodeid)?$pcodeid:'';
            $receipt = md5(uniqid(rand(), true));
            $created = time();
            $note    = ['pcodeid'=>$pcodeid];
            $razorpay= $this->razorpayPaymentApi();
            $order = $razorpay->order->create(
                [
              'receipt' => $receipt,
              'amount' => ($amount*$multiplyamt),
              'payment_capture' => 1,
              'currency' => 'INR',
              'notes'=>$note
              ]
            );
            if ($order && $order->status=='created') {
                $rorderid=$order->id;
                $data =  ['created'=>$created,'amount'=>$amount,'userid'=>$userid,'rorderid'=>$rorderid];
                $stmt = $this->db->prepare("INSERT INTO orders (userid,amount,rorderid,created) VALUES (:userid,:amount,:rorderid,:created)");
                if (!$stmt->execute($data)) {
                    $resArr = $this->security->errorMessage("There is some problem");
                    return $response->withJson($resArr, $errcode);
                }
                $user =$this->security->getUserDetail($userid);
                $order=$order->toArray();
                $order['amount']= ($order['amount']/$multiplyamt);
                $res['username']= ($user['name'])?$user['name']:$user['teamname'];
                $res['email']   = $user['email'];
                $res['phone']   = $user['phone'];
                $res['orderid']= $rorderid;
                $res['logourl'] = "https://play.fancy11.com/assets/img/logo.png";                
                $res['apikey'] = $settings['settings']['razorpay']['apikey'];
                $resArr['code']  = 0;
                $resArr['error'] = false;
                $resArr['msg']   = "Order successfully created.";
                $resArr['data']	 = $res;
//
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
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $apisecret = $settings['settings']['razorpay']['apisecret'];
        $loginuser 	= 	$request->getAttribute('decoded_token_data');
        $userid 	= 	$loginuser['id'];
        $a=time();
        $resArr =  [];
        $input  =  $request->getParsedBody();
        $check  =  $this->security->validateRequired(['razorpay_payment_id'], $input);
        if (isset($check['error'])) {
            return $response->withJson($check);
        }
        $payment_id = $input['razorpay_payment_id'];
        $sign 		= (isset($input['razorpay_signature']))?$input['razorpay_signature']:'';
        $order_id	= (isset($input['razorpay_order_id']))?$input['razorpay_order_id']:'';
        $created= time();
        $promocodeid='';
        try {
            $razorpay= $this->razorpayPaymentApi();
            $payment = $razorpay->payment->fetch($payment_id);
            if ($payment && $pdata=$payment->toArray()) {
                $localOrder=$this->security->getOrderByRazorpayId($order_id);
                $transactionchild =$this->security->chkRazorpayId($payment_id, 'Razorpay');
                $genrate_sign= hash_hmac('sha256', $order_id.'|'.$payment_id, $apisecret);
                if (empty($transactionchild)) {
                    if ($localOrder && $order_id==$pdata['order_id']) {
                        $amount = ($pdata['amount']/$multiplyamt);
                        $order_id = ($order_id)?$order_id:$pdata['order_id'];
                        $order = $razorpay->order->fetch($order_id);
                        $order = $order->toArray();
                        $resArr['code']  = 1;
                        $resArr['error'] = true;
                        $resArr['msg']   = "Payment Unauth";
                        if ($pdata['status']=='captured' && $sign==$genrate_sign) {
                            $paymentdata  = ['razorpay_signature'  => $sign,'razorpay_payment_id' => $payment_id , 'razorpay_order_id' => $order_id];
                        
                            $paymentVerify=$razorpay->utility->verifyPaymentSignature($paymentdata);
                            $transactionchild =$this->security->chkRazorpayId($payment_id, 'Razorpay');
                            if (empty($transactionchild)) {
                                $payment = $razorpay->payment->fetch($payment_id);
                                $pdata=	$payment->toArray();
                                if ($pdata['status']==='captured') {
                                    $pdata['amount'] = ($pdata['amount']/$multiplyamt);
                                    $this->setalRazorpayPayment($pdata, $localOrder, $order);
                                }
                            }
                            $data['wallet']=$this->security->getUserWalletBalance($userid);
                            $resArr['code']  = 0;
                            $resArr['error'] = false;
                            $resArr['msg']   = "Amount added successfully";
                            $resArr['data']  = $data;
                        } else {
                            $resArr['code']  = 1;
                            $resArr['error'] = true;
                            $resArr['msg']   = "Payment not added.Please try again";
                        }
                    } else {
                        $resArr['code']  = 1;
                        $resArr['error'] = true;
                        $resArr['msg']   = "Invalid Order or Payment";
                    }
                } else {
                    if ($sign==$genrate_sign) {
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
        } catch (\Exception $e) {
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
            $rstStatus=true;
            $userid     = $localOrder['userid'];
            $amount     = $pdata['amount'];
            $txnid 		= $pdata['id'];
            $status 	= $pdata['status'];
            $txdate 	= time();
            $docid		= $localOrder['id'];
            $gatewayname= $pdata['method'];
            $pmode 		= $pdata['method'];
            $banktxnid 	= ($pdata['method']==='card')?$pdata['card_id']:$txnid;
            $bankname 	= $pdata['bank'];
            $respcode 	= 123456;
            $created 	= time();
            $description="Payment successfully with amount ".$amount;
            $transactionchild =$this->security->chkRazorpayId($pdata['id'], 'Razorpay');
            if (empty($transactionchild)) {
                $this->update_user_wallet($userid, $amount, $txnid, $status, $txdate, $docid, CR, ADDBAL, WLTBAL, $gatewayname, $pmode, $banktxnid, $bankname, $respcode, $description, 'Razorpay');
                $promocodeid=(isset($order['notes']['pcodeid']))?$order['notes']['pcodeid']:'';
        
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
                        $stmt2->bindParam(':user_id', $userid);
                        $stmt2->bindParam(':created', $created);
                        $stmt2->bindParam(':date', $date);
                        $stmt2->execute();

                        $pcodeuse_lastid  =  $this->security->addUsesPromoCode(['userid' => $userid, 'pcodeid' => $res['id'], 'created' => $created]);
                        $getWlt 	= $this->security->getUserWalletBalance($userid);
                        $wltbnsPre  = $getWlt["wltbns"];
                        $wltbns 	= $wltbnsPre + $bnsamount;
                        $getWlt 	= $this->security->updateUserWallet($userid, "wltbns", $wltbns);
                        $this->updateTransaction($userid, $bnsamount, $created, $pcodeuse_lastid, CR, PROMOBNS, WLTBNS, $wltbnsPre, $wltbns);
                    }
                } elseif (empty($promocodeid)) {
                    $query  = "SELECT * FROM spinbonouspersent WHERE user_id=:user_id ORDER BY id DESC LIMIT 1";
                    $pcode  = $this->db->prepare($query);
                    $pcode->execute(['user_id' => $userid]);
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
                     
                                $pcodeuse_lastid = null;
                                $cashback = "cashback";
                                $getWlt 	= $this->security->getUserWalletBalance($userid);
                                $wltbnsPre  = $getWlt["wltbns"];
                                $wltbns 	= $wltbnsPre + $amountbns;
                                $getWlt 	= $this->security->updateUserWallet($userid, "wltbns", $wltbns);
                                $this->updateTransaction($userid, $amountbns, $created, $pcodeuse_lastid, CR, $cashback, WLTBNS, $wltbnsPre, $wltbns);
                    
                                $stmt = $this->db->prepare("UPDATE spinbonouspersent SET amount = :amount WHERE id=:id");
                                $stmt->bindParam(':amount', $amountbns);
                                $stmt->bindParam(':id', $spinbonouspersent_id);
                                $stmt->execute();
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $rstStatus=false;
        }

        return $rstStatus;
    }

    public function razorpaySuccess($request, $response)
    {

    //	$order_id='order_DKoBacxrzPwo63';
        $payment_id='pay_DKoC1Y39VaOIW2';
        $razorpay= $this->razorpayPaymentApi();
        $payment = $razorpay->payment->fetch($payment_id);
        //	$order = $razorpay->order->fetch($order_id);
        print_r($order->toArray());
    }

    public function razorpayCallback($request, $response)
    {
        global $settings,$baseurl;
        $code    = $settings['settings']['code']['rescode'];
        $errcode = $settings['settings']['code']['errcode'];
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $apisecret = $settings['settings']['razorpay']['apisecret'];
        $resArr =  [];
        $input  =  $request->getParsedBody();
        $a = time();
        $payment_id=(isset($input['payload']['payment']['entity']['id']))?$input['payload']['payment']['entity']['id']:'';
        $order_id=(isset($input['payload']['payment']['entity']['order_id']))?$input['payload']['payment']['entity']['order_id']:'';
        if ($payment_id) {
            $razorpay= $this->razorpayPaymentApi();
            $localOrder=$this->security->getOrderByRazorpayId($order_id);
            $transactionchild =$this->security->chkRazorpayId($payment_id, 'Razorpay');
            $payment = $razorpay->payment->fetch($payment_id);
            if (empty($transactionchild) && $localOrder && $payment) {
                $pdata=$payment->toArray();
                if (!empty($pdata) && is_array($pdata) && isset($pdata['status'])) {
                    if ($pdata['status']==='captured') {
                        $order = $razorpay->order->fetch($order_id);
                        $order = $order->toArray();
                        $transactionchild =$this->security->chkRazorpayId($payment_id, 'Razorpay');
                        if (empty($transactionchild)) {
                            $pdata['amount'] = ($pdata['amount']/$multiplyamt);
                            $this->setalRazorpayPayment($pdata, $localOrder, $order);
                        }
                    }
                }
            }
        }
    }
    
    public function razorpayPaymentApi()
    {
        global $settings,$baseurl;
        $multiplyamt = $settings['settings']['razorpay']['multiplyamt'];
        $apisecret = $settings['settings']['razorpay']['apisecret'];
        $apikey = $settings['settings']['razorpay']['apikey'];
        $razorpay    = new Api($apikey, $apisecret);
        return $razorpay;
    }
}
