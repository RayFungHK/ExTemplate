<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('../Template.php');
$tpl = new Template('./sample.tpl');
$tpl->gotoBlock('category')->newBlock()->assign(array(
	'CATEGORY_NAME' => 'Toys'
));

$tpl->gotoBlock('productlist')->newBlock();

$tpl->gotoBlock('product')->newBlock('lego')->assign(array(
	'PRODUCT_NAME' => 'Lego Starter Pack',
	'PRICE' => '200.00'
))->newBlock('speedycar')->assign(array(
	'PRODUCT_NAME' => 'Speedy Car',
	'PRICE' => '100.00'
))->newBlock('stickyball')->assign(array(
	'PRODUCT_NAME' => 'Sticky Ball',
	'PRICE' => '30.00'
))->newBlock('papersns')->assign(array(
	'PRODUCT_NAME' => 'Paper Sword and Shield',
	'PRICE' => '300.00'
));
echo $tpl->parse();
?>
