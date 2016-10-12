<!-- START BLOCK: category -->
<h1>{CATEGORY_NAME}</h1>
	<!-- START IFEXISTS: productlist -->
	<blockquote>
		<!-- START BLOCK: product -->
		<div>{PRODUCT_NAME} - ${PRICE}{{SWITCH COPYRIGHT::{COPYRIGHT}}}</div>
		<!-- END BLOCK: product -->
		<!-- START IFNOTEXISTS: no_product -->
		<div>No product in category</div>
		<!-- END IFNOTEXISTS: no_product -->
	</blockquote>
	<!-- END IFEXISTS: productlist -->
<!-- END BLOCK: category -->
{{DATE::<div>The time is: __datetime__</div>}}