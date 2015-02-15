<?php
require_once('../gb/common.php');	// Общие функции системы
 
// define('DEBUG', 1);	// Признак режима отладки



// Узнаём, какой у нас самый большой номер региона
$result = $db->query('SELECT MAX(id) FROM dic_region');
$r = $result->fetch_array(MYSQL_NUM);
$result->free();
$max_id = $r[0];



html_header();

// Применение изменений
if($_POST['reg_from']){
	$reg_from = $db->get_row('SELECT id, parent_id, region FROM dic_region WHERE id = :id', array('id' => $_POST['reg_from']));
	$reg_to = $db->get_row('SELECT id, region FROM dic_region WHERE id = :id', array('id' => $_POST['reg_to']));
	
	if($reg_from && $reg_to){
		$db->set_row('persons', array('region_id' => $reg_to['id']), array('region_id' => $reg_from['id']));
		$db->query('DELETE FROM `dic_region` WHERE `id` = :id', $reg_from);
		$db->set_row('dic_region', array('region_ids' => ''), array('id' => $reg_from['id']));

		print "<p>ИСПОЛНЕНО: Регион " . $reg_from->id . " (" . $reg_from->region . ") успешно удалён, а все его записи перенесены в регион " . $reg_to->id . " (" . $reg_to->region . ").</p>";
	}
}
?>
<form method="post" class="editor">
<p>Удалить регион с ID <input type="number" name="reg_from" min="1" max="<?php print $max_id; ?>" />, а все записи из него перенести в регион <input type="number" name="reg_to" min="1" max="<?php print $max_id; ?>" />.</p>
<button>Применить изменение</button>
</form>
<?php
html_footer();
