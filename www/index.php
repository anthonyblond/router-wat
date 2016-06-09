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
// $list->update();

require 'html_wrapper_top.html';

$qvars = [];
parse_str($_SERVER['QUERY_STRING'], $qvars);

if (empty($qvars)) {
    // Nothing to do
} elseif ($qvars['action'] == 'add') {
    $address = $qvars['address'];
    $comment = $qvars['comment'];

    $parts = explode("/",$address);
    if (count($parts) > 1) {
        // Assume next bit is subnet
        $validIp = filter_var($parts[0], FILTER_VALIDATE_IP) && is_numeric($parts[1]) && intval($parts[1]) == $parts[1];
        if (!$validIp) {
            echo '<div class="alert alert-danger" role="alert"><strong>Failed!</strong> '.$address.' is not a valid IP and subnet mask. (Use CIDR notation)</div>';
        }
    } else {
        $validIp = filter_var($address, FILTER_VALIDATE_IP);
        if (!$validIp) {
            echo '<div class="alert alert-danger" role="alert"><strong>Failed!</strong> '.$address.' is not a valid IP</div>';
        }
    }
    if ($validIp) {
        $list->add($address, $comment, true);
        $retval = $list->update();
        if ($retval == 0) {
            echo '<div class="alert alert-success" role="alert"><strong>Success!</strong> Added '.$address.' to whitelist</div>';
        } else {
            echo '<div class="alert alert-warning" role="alert"><strong>Hold your horses!</strong> Unable to add '.$address.' to whitelist, insufficient time has passed since last update. Need to wait another '.$retval.' seconds.</div>';
        }
    }
} elseif ($qvars['action'] == 'remove') {

}

$timeUntilUpdateAllowed = $list->timeUntilUpdateAllowed();
?>

<div class="panel panel-default">
    <div class="panel-heading"><h1 class="panel-title">Add an IP address to list</h1></div>
    <div class="panel-body">
        <form class="form-inline <?= $timeUntilUpdateAllowed == 0 ? '' : ' hidden'; ?>" id="form-add-address" method="get" action="<?= $_SERVER['REQUEST_URI'] ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="input-address">IP address: &nbsp;&nbsp;</label>
                <input type="text" class="form-control" name="address" id="input-address" placeholder="IP address goes here">
            </div>
            <div class="form-group">
                <label for="input-comment">&nbsp;&nbsp;Campaign office:&nbsp;&nbsp; </label>
                <input type="text" class="form-control" name="comment" id="input-comment" placeholder="Unique name">
            </div>
            &nbsp;&nbsp;
            <button type="submit" class="btn btn-primary">Add new IP</button>
            <span class="help-block">If specified campaign office name already exists, it's IP will be updated.</span>
        </form>
        <div id="wait-time-warning" class="<?= $timeUntilUpdateAllowed == 0 ? 'hidden' : ''; ?>">
            Not enough time has ellapsed since the last address was added.<br/>
            You need to wait another <span id="wait-time-left"><?= $timeUntilUpdateAllowed; ?></span> seconds<br/>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><h1 class="panel-title">IP addresses in whitelist</h1></div>

    <table class="table" id="addresses">
        <tr>
            <th>IP</th>
            <th>Campaign office</th>
        </tr>
<?php
foreach ($list->getAddresses() as $address) {
    echo "<tr>\n";
    echo "<td>$address[address]</td>\n";
    echo "<td>$address[comment]</td>\n";
    echo "</tr>\n";
}
?>
    </table>
</div>

<?php
require 'html_wrapper_botom.html';

?>
