<?php
require_once('IyzipayBootstrap.php');
IyzipayBootstrap::init();
class Config
{
    public static function options()
    {
        require_once('wpjobster-iyzipay-gateway.php');
        $credentials = WPJobster_Iyzipay_Loader::get_gateway_credentials();
        //echo '<pre>';print_r($credentials);exit;
        $options = new \Iyzipay\Options();
        /*$options->setApiKey("sandbox-LOeUa5IyOKAhCcnTfF2qXXtFfqAT8Px9");
        $options->setSecretKey("sandbox-QmEjkfbfBt42JmyODJXWxC01WHQ9loau");
        $options->setBaseUrl("https://sandbox-api.iyzipay.com");*/
        $options->setApiKey($credentials['iyzipay_apikey']);
        $options->setSecretKey($credentials['iyzipay_secretkey']);
        $options->setBaseUrl($credentials['iyzipay_payment_url']);
        return $options;
    }
}