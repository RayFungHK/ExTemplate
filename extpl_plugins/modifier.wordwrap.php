<?php
function extpl_modifier_wordwrap($value, $limit = 0, $break = "\n", $cut = false) {
	$limit = intval($limit);
	if ($limit > 0) {
		return wordwrap($value, $limit, $break, $cut);
	}
	return $value;
}
?>