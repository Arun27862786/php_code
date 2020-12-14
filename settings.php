<?php
return [
    'settings' => [
		'webname'=>"Fancy11",
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
        'adminmail'=>"support1@fancy11.com",

        // Renderer settings
        'twilio' => [
            'account_sid' => '',
            'auth_token' => '',
            'twilio_number' => '',
            'ccode' => '+91'
        ],
        'txtlcl' => [
            'apiKey' => 'gOouL5XOw6g-thjMVsfqN',
            'sender' => '',
            'ccode'  => '91'
        ],
        'mysms' => [
            "username"   => "fancy11",
            "password"   => "328596957",
            "sender"     => "FANCEY",
        ],
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
            'twig_path' => __DIR__ . '/../templates/twigview'
        ],
        'database' => [
            'host' => 'localhost',
            'user' => 'fancyusr',
            'pass' => 'FancyTestdf9d97BC963B()#Jjt',
            'dbname' => 'fancydb'
        ],
        'mongo' => [
            'host' => 'localhost',
            'port' => '27017',
            'user' => 'fancyusr',
            'pass' => 'fancytest1f$dfD$h!7',
            'dbname'=> 'fancydb'
        ],
        'razorpay' => [
           // 'apikey' => 'rzp_live_5WsbECCaOBAFwv', //live
           // 'apikey' => 'rzp_test_FNLr2QQyTboeWj',//test            
            'apikey' => 'rzp_test_2akds9ynth1XSc',//arun
          //  'apisecret' => 'tN0EazPxnbRn3opXe2AmGsPY', //live
           // 'apisecret' => '2ywfFvDacmliwczP5CJDp1rn', //test
           'apisecret' => 'jtZcXuGnrNPQjrSooURMPWpX', //arun
            'multiplyamt'=> 100
        ],
        'jwt' => [
            'secret' => 'bsrf1cba11bcf118c9fd299688sdfdsfdsfdsfgghfjgbfabcy'
        ],
        'fcm' => [
            'serverKey' => 'AIzaSyBwgywBF5FdC6YsqDQopWRivYq1guY_6w4'
        ],
         'code' => [
            'errcode' => 200,
            'rescode' => 200,
            'defaultPaginLimit'=>10,
            'pageLimitFront'=>20,
            'imgSizeLimit'=>100,  // in KB
            'panImgLimit'=>500,  // in KB
            'bankImgLimit'=>500,  // in KB
        ],
        'path' => [
            'baseurl' => "localhost",
            'dummyplrimg' => "plr.jpeg",
            'gameDataApi' => "http://167.71.226.16:3000",
 	    'dmfantasypoint' =>  "http://101.53.130.31:8889/getPlayerpoints",
	    'rummyApi'  =>  "http://172.105.58.150/v1/apis/"
        ],
        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
        'msg' => [
            'otp' => 'OTPCODE is the OTP for your Fancy11 account. NEVER SHARE YOUR OTP WITH ANYONE. Fancy11 will never call or message to ask for the OTP.',
        ],
    ],
];


/*
0-OK
1-ERROR
2-Token not found
3-user not verified
*/
