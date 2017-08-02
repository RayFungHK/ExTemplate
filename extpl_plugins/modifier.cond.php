<?php
function extpl_modifier_cond($value, $trueText = '', $falseText = '') {
	return ($value) ? $trueText : $falseText;
}
?>
