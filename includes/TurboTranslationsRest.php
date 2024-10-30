<?php
require_once "TurboTranslations.php";
require_once "TurboTranslationsPolylang.php";
class TurboTranslationsRest extends WP_REST_CONTROLLER
{
    protected $namespace = "turbotranslations/v2";

    public function __construct()
    {
        register_rest_route($this->namespace, "/callback", [
            "methods" => "POST, GET",
            "callback" => [$this, "tt_callback"],
            "permission_callback" => "__return_true",
        ]);
    }

    public function tt_callback(WP_REST_REQUEST $request)
    {
        $params = $request->get_params();
        error_log(
            "Visited: " .
                date("Y-m-d H:i:s") .
                ". Visitor IP address: " .
                getIPAddress() .
                " Hash: " .
                $params["t"]
        );
        try {
            TurboTranslations::__constructStatic();
            $published = TurboTranslations::publishTranslatedContent(
                $params["t"]
            );
            return new WP_REST_RESPONSE([], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return new WP_REST_RESPONSE([], 500);
        }
    }
}

function getIPAddress()
{
    //whether ip is from the share internet
    if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    }
    //whether ip is from the proxy
    elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    }
    //whether ip is from the remote address
    else {
        $ip = $_SERVER["REMOTE_ADDR"];
    }
    return $ip;
}
