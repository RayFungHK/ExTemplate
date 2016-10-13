<?php
function extpl_modifier_text_overflow($value, $length = 0, $ellipsis = '...', $includeEllipsis = false, $collapse = false) {
	if ($length > 0) {
		$maxChar = $length;
		if ($includeEllipsis) {
			$maxChar -= strlen($ellipsis);
		}

		if (strlen($value) > $maxChar) {
			if ($collapse) {
				$value = substr($value, 0, $maxChar);
				$firstLength = floor($maxChar / 2);
				$value = substr($value, 0, $firstLength) . $ellipsis . substr($value, $firstLength);
			} else {
				$value = substr($value, 0, $maxChar) . $ellipsis;
			}
		}
	}
	return $value;
}
?>