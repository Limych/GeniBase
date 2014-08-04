<?php
require_once('../gb/common.php');	// Общие функции системы
 
// define('DEBUG', 1);	// Признак режима отладки



// Узнаём, какой у нас самый большой номер региона
$result = db_query('SELECT MAX(id) FROM dic_region');
$r = $result->fetch_array(MYSQL_NUM);
$result->free();
$max_id = $r[0];



html_header();

// Применение изменений
if($_POST['reg_from']){
	$result = db_query('SELECT * FROM dic_region WHERE id = ' . intval($_POST['reg_from']));
	$reg_from = $result->fetch_object();
	$result->free();
	
	$result = db_query('SELECT * FROM dic_region WHERE id = ' . intval($_POST['reg_to']));
	$reg_to = $result->fetch_object();
	$result->free();
	
	if($reg_from && $reg_to){
		db_query('UPDATE `persons` SET `region_id` = ' . $reg_to->id . ' WHERE `region_id` = ' . $reg_from->id);
		db_query('DELETE FROM `dic_region` WHERE `id` = ' . $reg_from->id);
		db_query('UPDATE `dic_region` SET `region_ids` = "" WHERE `id` = ' . $reg_from->parent_id);

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
db_close();
?>