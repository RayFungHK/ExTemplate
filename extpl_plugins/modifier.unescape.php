<?php
function extpl_modifier_unescape($value, $type = '') {
	if ($type == 'htmlall') {
		return html_entity_decode($value, ENT_NOQUOTES);
	} elseif ($type == 'html') {
		return htmlspecialchars_decode($value, ENT_QUOTES);
	} elseif ($type == 'url') {
		return rawurldecode($value);
	}
	return $value;
}
?>