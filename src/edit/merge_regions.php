<?php
require_once ('../gb-config.php'); // Load GeniBase
require_once ('../inc.php'); // Основной подключаемый файл-заплатка
                             
// Узнаём, какой у нас самый большой номер региона
$result = gbdb()->query('SELECT MAX(id) FROM ?_dic_regions');
$r = $result->fetch_array(MYSQL_NUM);
$result->free();
$max_id = $r[0];

html_header('');

// Применение изменений
if (isset($_POST['reg_from'])) {
    $reg_from = gbdb()->get_row('SELECT id, parent_id, region FROM ?_dic_regions WHERE id = ?id', array(
        'id' => $_POST['reg_from']
    ));
    $reg_to = gbdb()->get_row('SELECT id, region FROM ?_dic_regions WHERE id = ?id', array(
        'id' => $_POST['reg_to']
    ));
    
    if ($reg_from && $reg_to) {
        if (! GB_DEBUG) { // В режиме отладки реальных изменений в базе не производим
            gbdb()->set_row('?_persons', array(
                'region_id' => $reg_to['id']
            ), array(
                'region_id' => $reg_from['id']
            ));
            gbdb()->query('DELETE FROM ?_dic_regions WHERE `id` = ?id', $reg_from);
            gbdb()->set_row('?_dic_regions', array(
                'region_ids' => ''
            ), array(
                'id' => $reg_from['id']
            ));
        }
        
        print "<p>ИСПОЛНЕНО: Регион $reg_from[id] ($reg_from[region]) успешно удалён,
				а все его записи перенесены в регион $reg_to[id] ($reg_to[region]).</p>";
    }
}
?>
<form method="post" class="editor">
	<p>
		Удалить регион с ID <input type="number" name="reg_from" min="1"
			max="<?php print $max_id; ?>" />, а все записи из него перенести в
		регион <input type="number" name="reg_to" min="1"
			max="<?php print $max_id; ?>" />.
	</p>
	<button>Применить изменение</button>
</form>
<?php
html_footer();
