<?php
function extpl_modifier_strip($value, $character = '') {
	if ($character) {
		return preg_replace('/\s+/', $character, $value);
	} else {
		return preg_replace('/\s+/', ' ', $value);
	}
}
?>