<?php
include('../Template.php');
$tpl = new Template('./sample.tpl');
$tpl->gotoBlock('category')->newBlock()->assign(array(
	'category_name' => 'toys'
));

$tpl->gotoBlock('productlist')->newBlock();

$tpl->gotoBlock('product')->newBlock('lego')->assign(array(
	'product_name' => 'lego starter pack',
	'price' => '200.00'
))->newBlock('speedycar')->assign(array(
	'product_name' => 'speedy car',
	'price' => '100.00'
))->newBlock('stickyball')->assign(array(
	'product_name' => 'sticky ball',
	'price' => '30.00'
))->newBlock('papersns')->assign(array(
	'product_name' => 'paper sword and shield',
	'price' => '300.00'
));

$tpl->findBlock('/category/productlist/product[stickyball]')->assign(array(
	'product_name' => 'stick ball (discounted)',
	'price' => '22.00'
));

echo $tpl->parse();
?>