<?php
require_once('gb/gb.php');	// Общие функции системы

html_header('Статистика по запросам к базе данных');
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Статистика по запросам к базе данных</h1>
<table class="stat">
	<caption>Распределение по периодам</caption>
<thead><tr>
	<th>Период</th>
	<th>Запросов всего</th>
	<th>Чёткий поиск (без подстановок)</th>
	<th>Положительных результатов</th>
	<th>Поиск по фамилии</th>
	<th>Поиск по имени/отчеству</th>
	<th>Поиск по месту</th>
	<th>Поиск по № списка</th>
	<th>Поиск по № страницы</th>
	<th>Поиск по вероисповеданию</th>
	<th>Поиск по семейному положению</th>
	<th>Поиск по событиям</th>
	<th>Поиск по званию</th>
	<th>Поиск по периоду</th>
	</tr></thead>
<tbody>
<?php
$even = 0;

// TODO: Rename source_pg → source_pg
$result = gbdb()->query("SELECT
 CASE WHEN `Год` IS NULL 
      THEN 'Всего'
      ELSE
           CASE WHEN `Месяц` IS NULL 
                THEN CONCAT('Итого за ',`Год`,' год')
                ELSE
                     CASE WHEN `Дата` IS NULL 
                          THEN CONCAT('Итого за ',`Месяц`)
                          ELSE `Дата`
                          END
                END
      END 'Дата'

 ,`Запросов всего`
 ,`Чёткий поиск (без подстановок)`  
 ,`Положительных результатов` 
 ,`Поиск по фамилии` 
 ,`Поиск по имени/отчеству` 
 ,`Поиск по месту` 
 ,`Поиск по № списка` 
 ,`Поиск по № страницы` 
 ,`Поиск по вероисповеданию` 
 ,`Поиск по семейному положению` 
 ,`Поиск по событиям` 
 ,`Поиск по званию` 
 ,`Поиск по периоду` 

FROM
(
SELECT
  DATE_FORMAT(datetime,'%Y')                                         `Год`
 ,DATE_FORMAT(datetime,'%m-%Y')                                      `Месяц`
 ,DATE_FORMAT(datetime,'%d/%m/%Y')                                   `Дата`
 ,COUNT(*)                                                           `Запросов всего`
 ,SUM(CASE WHEN (INSTR(query,'%')=0) 
            AND (INSTR(query,'_')=0) 
            AND (INSTR(query,'*')=0) 
            AND (INSTR(query,'&')=0) THEN 1 ELSE 0 END)              `Чёткий поиск (без подстановок)`  
 ,SUM(CASE WHEN records_found >0 THEN 1 ELSE 0 END)                  `Положительных результатов` 

 ,SUM(CASE WHEN (INSTR(url,'surname='       ) >0) THEN 1 ELSE 0 END) `Поиск по фамилии` 
 ,SUM(CASE WHEN (INSTR(url,'?name='         ) >0)
             OR (INSTR(url,'&name='         ) >0) THEN 1 ELSE 0 END) `Поиск по имени/отчеству` 
 ,SUM(CASE WHEN (INSTR(url,'place='         ) >0)
             OR (INSTR(url,'region='        ) >0) THEN 1 ELSE 0 END) `Поиск по месту` 
 ,SUM(CASE WHEN (INSTR(url,'source_pg='       ) >0) THEN 1 ELSE 0 END) `Поиск по № страницы` 
 ,SUM(CASE WHEN (INSTR(url,'religion%5B%5D=') >0) THEN 1 ELSE 0 END) `Поиск по вероисповеданию` 
 ,SUM(CASE WHEN (INSTR(url,'marital%5B%5D=' ) >0) THEN 1 ELSE 0 END) `Поиск по семейному положению` 
 ,SUM(CASE WHEN (INSTR(url,'reason%5B%5D='  ) >0) THEN 1 ELSE 0 END) `Поиск по событиям` 
 ,SUM(CASE WHEN (INSTR(url,'rank%5B%5D='    ) >0) THEN 1 ELSE 0 END) `Поиск по званию` 
 ,SUM(CASE WHEN (INSTR(url,'date_from='     ) >0)
            AND (INSTR(url,'date_to='       ) >0) THEN 1 ELSE 0 END) `Поиск по периоду` 
FROM
 ?_logs
GROUP BY
  DATE_FORMAT(datetime,'%Y'),DATE_FORMAT(datetime,'%m-%Y'),DATE_FORMAT(datetime,'%d/%m/%Y') WITH rollup
) xx
WHERE 1=1");
while($row = $result->fetch_array(MYSQLI_NUM)){
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" 
 		               // . format_date($row[0]) . "</td>\n\t" 
 		               // . esc_html(date_create($row[0])->format('d-M-Y'))      . "</td>\n\t"
 		               // . "<td class='align-right'>" . format_num($row[0]) . "</td>\n"
 		                .                       esc_html($row[0]) . "</td>\n\t"
 		                . "<td class='align-right'>" . format_num($row[1]) . "</td>\n"
 		                . "<td class='align-right'>" . format_num($row[2]) . "</td>\n"
 						. "<td class='align-right'>" . format_num($row[3]) . "</td>\n"
 						. "<td class='align-right'>" . format_num($row[4]) . "</td>\n"
 						. "<td class='align-right'>" . format_num($row[5]) . "</td>\n"
				 		. "<td class='align-right'>" . format_num($row[6]) . "</td>\n"
	            		. "<td class='align-right'>" . format_num($row[7]) . "</td>\n"
            			. "<td class='align-right'>" . format_num($row[8]) . "</td>\n"
						. "<td class='align-right'>" . format_num($row[9]) . "</td>\n"
						. "<td class='align-right'>" . format_num($row[10]). "</td>\n"
 		                . "<td class='align-right'>" . format_num($row[11]). "</td>\n"
 		                . "<td class='align-right'>" . format_num($row[12]). "</td>\n"
 						. "<td class='align-right'>" . format_num($row[13]). "</td>\n"
 		                . "</tr>";
}
$result->free();
?>
</tbody></table>
<?php
html_footer();
