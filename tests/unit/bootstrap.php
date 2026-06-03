<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BP', dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))));

require_once __DIR__ . '/stubs/MagentoStubs.php';

$moduleRoot = dirname(dirname(__DIR__));
require_once $moduleRoot . '/Service/Order/CheckoutSessionOwnership.php';
require_once $moduleRoot . '/Service/Order/PlacementGuard.php';

Bold_CheckoutPaymentBooster_Test_Stub_Mage::reset();
Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
    Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
    new Bold_CheckoutPaymentBooster_Model_Config()
);
