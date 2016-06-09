<?php
define("APP_DIR", "/home/anthony/whitelister/app");
define("CONFIG_FILE", "/home/anthony/whitelister/config.json");

set_include_path(get_include_path() . PATH_SEPARATOR . APP_DIR . "/include");
require_once "PEAR2_Net_RouterOS-1.0.0b5.phar";

// The Net RouterOS library doesn't seemt to autoloading in standard way and breaks things, so
// rather than trying to register our own autoloader, just require each file. Not likely to have
// many

// From: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md
spl_autoload_register(function ($class) {
    // project-specific namespace prefix
    $prefix = 'RouterWat\\';

    // base directory for the namespace prefix
    $base_dir = APP_DIR . '/classes/RouterWat/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
},
true, // Throw true
true); // prepend, since the library we include doesn't check prefix

$list = new RouterWat\AddressList('wat_egads_whitelist');
// var_dump($list->getAll());
$list->update();

?>
