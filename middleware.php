<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

global $settings;

$app->add(new \Tuupola\Middleware\JwtAuthentication([

    "path" => ["/api", "/frontapi"],
    "attribute" => "decoded_token_data",
    "secure" => true,
    "relaxed" => ["172.105.49.94"],
    "secret"    => $settings['settings']['jwt']['secret'],
    "algorithm" => ["HS256"],
    "error" => function ($response, $arguments) {
            $cd = 2;

            //if($arguments["message"] == "Expired token"){$cd=2;}

            $resArr['code']  = $cd;
            $resArr['error'] = true;
            $resArr['msg']   = $arguments["message"];
 
        return $response
            ->withHeader("Content-Type", "application/json")
          ->write(json_encode($resArr, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    }

]));
