<?php
function extpl_modifier_strip_tags($value, $removeExtraSpace = false) {
	$value = strip_tags($value);
	if ($removeExtraSpace) {
		$value = preg_replace('/\s+/', ' ', $value);
	}
	return $value;
}
?>