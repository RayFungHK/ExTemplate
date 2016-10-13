<?php
function extpl_modifier_indent($value, $count = 0, $character = ' ') {
	if ($count > 0) {
		if (!$character) {
			$character = ' ';
		} else {
			$character = substr($character, 0, 1);
		}

		$value = str_repeat($character, $count) . $value;
	}
	return $value;
}
?>