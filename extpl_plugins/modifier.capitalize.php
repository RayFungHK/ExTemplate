<?php
function extpl_modifier_capitalize($value, $type = false) {
	if ($type) {
		return ucwords(strtolower($value));
	} else {
		return ucfirst(strtolower($value));
	}
}
?>