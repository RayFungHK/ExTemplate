<?php
function extpl_modifier_count_paragraphs($value) {
	return count(preg_split('/(\n\r){2,}|(\n+){2,}/', trim($value), -1, PREG_SPLIT_NO_EMPTY));
}
?>