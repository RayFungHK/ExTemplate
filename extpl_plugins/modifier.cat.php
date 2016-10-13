<?php
function extpl_modifier_cat($value, $text = '') {
	if ($text) {
		$value = $value . $text;
	}
	return $value;
}
?>