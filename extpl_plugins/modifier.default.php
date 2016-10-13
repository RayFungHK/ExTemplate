<?php
function extpl_modifier_default($value, $text = '') {
	return (!$value) ? $text : $value;
}
?>