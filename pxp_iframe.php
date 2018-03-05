<?php
define("CLIENTAREA", true);
define("FORCESSL", true);

include("init.php");
$whmcs->load_function('clientarea');

$pagetitle = $_LANG['clientareatitle'] . " - Pay via PayXpert";
initialiseClientArea($pagetitle, '', 'Pay via PayXpert');

$whmcsVer = substr($CONFIG['Version'], 0, 1);
$smartyvalues["whmcsVer"] = $whmcsVer;

# Check login status
if ($_SESSION['uid'] && isset($_POST['data']) && $iframe = decrypt($_POST['data'])) {
    if ($iframe) {
        $smartyvalues['iframe'] = $iframe;
    } else { // User is logged in but they shouldn't be here (i.e. they weren't here from an invoice)
        header("Location: " . $CONFIG['SystemURL'] . "/clientarea.php?action=details");
    }
} else {
    header("Location: " . $CONFIG['SystemURL'] . "/");
}

outputClientArea('/modules/gateways/payxpert/templates/iframe.tpl');
