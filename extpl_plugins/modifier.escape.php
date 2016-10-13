<?php
function extpl_modifier_escape($value, $type = '') {
	if ($type == 'quotes') {
		return addslashes($value);
	} elseif ($type == 'mail') {
		return str_replace('.', '[DOT]', str_replace('@', '[AT]', $value));
	} elseif ($type == 'url') {
		return rawurlencode($value);
	} elseif ($type == 'html') {
		return htmlspecialchars($value);
	}  elseif ($type == 'htmlall') {
		return htmlspecialchars(htmlentities($value, ENT_QUOTES));
	} else {
		return preg_replace_callback('/[\'"]/', function($matches) {
			return htmlspecialchars(htmlentities($matches[0], ENT_QUOTES));
		}, $value);
	}
}
?>