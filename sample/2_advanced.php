<?php
$products = array(
	'toy' => array(
		'lego' => array(
			'name' => 'lego starter pack',
			'price' => '200.00',
			'copyright' => ' by lego co.'
		),
		'speedycar' => array(
			'name' => 'speedy car',
			'price' => '100.00'
		),
		'stickyball' => array(
			'name' => 'sticky ball',
			'price' => '30.00'
		),
		'papersns' => array(
			'name' => 'paper sword and shield',
			'price' => '300.00',
			'copyright' => ' by factory co.'
		)
	),
	'candy' => array(
		'gummybear_s' => array(
			'name' => 'small gommy bear',
			'price' => '20.00'
		),
		'gummybear_l' => array(
			'name' => 'large gommy bear',
			'price' => '20.00'
		),
		'sneaker' => array(
			'name' => 'sneaker',
			'price' => '40.00'
		)
	)
);

include('../Template.php');
$tpl = new Template('./sample.tpl');
$tpl->gotoBlock('category');
foreach ($products as $category => $productlist) {
	$tpl->newBlock()->assign(array(
		'category_name' => $category
	))->gotoBlock('productlist')->newBlock();

	if (count($productlist)) {
		$tpl->gotoBlock('product');
		foreach ($productlist as $product_code => $detail) {
			$tpl->newBlock($product_code)->assign(array(
				'product_name' => $detail['name'],
				'price' => $detail['price'],
				'copyright' => isset($detail['copyright']) ? $detail['copyright'] : ''
			));
		}
	}
	$tpl->parent('category');
}


$tpl->findBlock('/category/productlist/product')->assign(function($assigned) {
	// fix gommy to gummy
	$new_assigned = array();
	if (isset($assigned['product_name']) && strpos($assigned['product_name'], 'gommy') !== false) {
		$new_assigned['product_name'] = str_replace('gommy', 'gummy', $assigned['product_name']);
	}
	return $new_assigned;
});


echo $tpl->parse();
?>