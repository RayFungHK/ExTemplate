<?php
function extpl_modifier_count_sentences($value) {
	return count(preg_split('/[.?!]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY));
}
?>