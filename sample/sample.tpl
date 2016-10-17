<!-- START BLOCK: category -->
<h1>{$category_name}</h1>
	<!-- START IFEXISTS: productlist -->
	<blockquote>
		<!-- START BLOCK: product -->
		<div>{$product_name} - ${$price}{$copyright|finishing:"$1"}</div>
		<!-- END BLOCK: product -->
		<!-- START IFNOTEXISTS: no_product -->
		<div>No product in category</div>
		<!-- END IFNOTEXISTS: no_product -->
	</blockquote>
	<!-- END IFEXISTS: productlist -->
<!-- END BLOCK: category -->
{date text="<div>The time is: __datetime__</div>"}