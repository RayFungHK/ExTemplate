<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$products = array(
	'Toy' => array(
		'lego' => array(
			'name' => 'Lego Starter Pack',
			'price' => '200.00',
			'copyright' => ' by LEGO co.'
		),
		'speedycar' => array(
			'name' => 'Speedy Car',
			'price' => '100.00'
		),
		'stickyball' => array(
			'name' => 'Sticky Ball',
			'price' => '30.00'
		),
		'papersns' => array(
			'name' => 'Paper Sword and Shield',
			'price' => '300.00',
			'copyright' => ' by Factory co.'
		)
	),
	'Candy' => array(
		'gummybear_s' => array(
			'name' => 'Small Gommy Bear',
			'price' => '20.00'
		),
		'gummybear_l' => array(
			'name' => 'Large Gommy Bear',
			'price' => '20.00'
		),
		'sneaker' => array(
			'name' => 'Sneaker',
			'price' => '40.00'
		)
	)
);

include('../Template.php');
$tpl = new Template('./sample.tpl');
$tpl->gotoBlock('category');
foreach ($products as $category => $productlist) {
	$tpl->newBlock()->assign(array(
		'CATEGORY_NAME' => $category
	))->gotoBlock('productlist')->newBlock();

	if (count($productlist)) {
		$tpl->gotoBlock('product');
		foreach ($productlist as $product_code => $detail) {
			$tpl->newBlock($product_code)->assign(array(
				'PRODUCT_NAME' => $detail['name'],
				'PRICE' => $detail['price'],
				'COPYRIGHT' => isset($detail['copyright']) ? $detail['copyright'] : ''
			));
		}
	}
	$tpl->parent('category');
}


$tpl->findBlock('/category/productlist/product')->assign(function($assigned) {
	// Fix Gommy to Gummy
	$new_assigned = array();
	if (isset($assigned['PRODUCT_NAME']) && strpos($assigned['PRODUCT_NAME'], 'Gommy') !== FALSE) {
		$new_assigned['PRODUCT_NAME'] = str_replace('Gommy', 'Gummy', $assigned['PRODUCT_NAME']);
	}
	return $new_assigned;
});


echo $tpl->parse();
?>