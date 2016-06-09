<?php
define("APP_DIR", "/home/anthony/whitelister/app/");
define("CONFIG_FILE", "/home/anthony/whitelister/config.json");

set_include_path(get_include_path() . PATH_SEPARATOR . APP_DIR);

require_once "include/PEAR2_Net_RouterOS-1.0.0b5.phar";

use PEAR2\Net\RouterOS;


$config = json_decode(file_get_contents(CONFIG_FILE), true);

try {
    $client = new RouterOS\Client($config['router']['address'], $config['router']['username'], $config['router']['username']);

} catch (Exception $e) {
    die($e);
}
unset($config['router']['password']);

?>
