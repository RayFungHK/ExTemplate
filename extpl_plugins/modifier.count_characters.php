<?php
function extpl_modifier_count_characters($value, $withoutSpace = false) {
	if ($withoutSpace) {
		return strlen(preg_replace('/\s/', '', $value));
	} else {
		return strlen($value);
	}
}
?>