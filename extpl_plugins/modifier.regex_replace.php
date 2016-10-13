<?php
function extpl_modifier_regex_replace($value, $search = '', $replacement = '') {
	$search = trim($search);
	if ($search) {
		$replaced = @preg_replace($search, $replacement, $value);
		return (is_null($replaced)) ? $value : $replaced;
	}
	return $value;
}
?>