<?php
function extpl_modifier_date_format($value, $format = 'Y-m-d H:i:s') {
	if (is_a($value, 'DateTime')) {
		$format = ($format) ? 'Y-m-d H:i:s' : $format;
		return $value->format($extra);
	}
	return $value;
}
?>