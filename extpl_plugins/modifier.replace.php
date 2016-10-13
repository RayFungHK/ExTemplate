<?php
function extpl_modifier_replace($value, $search = '', $replacement = '') {
	if ($search) {
		return str_replace($search, $replacement, $value);
	}
	return $value;
}
?>