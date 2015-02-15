<?php
require_once('../gb/common.php');	// Common functions
// require_once(GB_INC_DIR . '/publish.php');	// Function for data formalizing

html_header('Редактирование регионов');
if (!isset($_POST['reg']) || empty($_POST['reg'])) {
	select_region();
}
html_footer();



function select_region(){
?>
<p>Выберите родительский регион:</p>
<form method="post">
<select>
<?php
_regions();
?>
</select>
</form>
<?php
}



function _regions($parent_id = 0, $level = 1){
	global $db;

	$result = $db->get_table('SELECT id, title, region_comment FROM dic_region WHERE parent_id = :id ORDER BY title',
			array('id' => $parent_id));
	foreach ($result as $row){
		print "\t<option value='" . $row['id'] . "'>" . htmlspecialchars($row['title']) .
				(empty($row['region_comment']) ? '' : ' <span class="comment">' .
						htmlspecialchars($row['region_comment']) .
				'</span>') . "</option>\n";
		_regions($row['id'], $level + 1);
	}
}
