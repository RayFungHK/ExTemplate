<?php
function extpl_modifier_finishing($value, $text = '') {
	if ($value) {
		return preg_replace('/(?<!\\\)\$1/', $value, $text);
	}
	return '';
}
?>