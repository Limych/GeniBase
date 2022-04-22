<?php
require_once('gb-config.php'); // Load GeniBase
require_once('inc.php'); // Основной подключаемый файл-заплатка

html_header(__('Statistic', WW1_TXTDOM));
?>
    <p><a href="/"><?php _e('&laquo;&nbsp;Back to search', WW1_TXTDOM); ?></a></p>
    <h1><?php _e('Common database statistic', WW1_TXTDOM); ?></h1>

<?php
show_records_stat();
?>

    <table class="stat">
        <caption><?php _e('Distribution by regions of Russian Empire', WW1_TXTDOM); ?></caption>
        <thead>
        <tr>
            <th><?php _ex('Province, Uezd', 'Field name', WW1_TXTDOM); ?></th>
            <th><?php _e('Records count', WW1_TXTDOM); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $even = 0;
        region_stat();
        ?>
        </tbody>
    </table>

    <table class="stat">
        <caption><?php _e('Distribution by military rank', WW1_TXTDOM); ?></caption>
        <thead>
        <tr>
            <th><?php _ex('Military rank', 'Field name', WW1_TXTDOM); ?></th>
            <th><?php _e('Records count', WW1_TXTDOM); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $even = 0;
        $result = gbdb()->get_table('SELECT rank, COUNT(*) AS cnt FROM ?_persons GROUP BY rank ORDER BY rank');
        foreach ($result as $row) {
            $even = 1 - $even;
            if (empty($row['rank']))
                $row['rank'] = '(не указано)';
            print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . esc_html($row['rank']) . "</td>\n\t<td class='align-right'>" . format_num($row['cnt']) . "</td>\n</tr>";
        }
        ?>
        </tbody>
    </table>

    <table class="stat">
        <caption><?php _e('Distribution by events', WW1_TXTDOM); ?></caption>
        <thead>
        <tr>
            <th><?php _ex('Event type', 'Field name', WW1_TXTDOM); ?></th>
            <th><?php _ex('Event', 'Field name', WW1_TXTDOM); ?></th>
            <th><?php _e('Records count', WW1_TXTDOM); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $even = 0;
        $result = gbdb()->get_table('SELECT event_type, reason, SUM(reason_cnt) AS cnt FROM ?_dic_reasons' .
            ' GROUP BY event_type, reason ORDER BY 1,2');
        foreach ($result as $row) {
            $even = 1 - $even;
            if (empty($row['event_type'])) $row['event_type'] = '(не указано)';
            if (empty($row['reason'])) $row['reason'] = '(не указано)';
            print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t"
                . "<td>" . esc_html($row['event_type']) . "</td>\n\t"
                . "<td>" . esc_html($row['reason']) . "</td>\n\t"
                . "<td class='align-right'>" . format_num($row['cnt']) . "</td>\n"
                . "</tr>";
        }
        ?>
        </tbody>
    </table>

<?php
dic_stat(__('Distribution by religion', WW1_TXTDOM), _x('Religion', 'Field name', WW1_TXTDOM), 'religion');
dic_stat(__('Distribution by marital status', WW1_TXTDOM), _x('Marital status', 'Field name', WW1_TXTDOM), 'marital');
?>


<?php
function region_stat($parent_id = 0, $level = 1)
{
    global $even;

    $result = gbdb()->get_table('SELECT id, title, region_comment, region_cnt FROM ?_dic_regions' .
        ' WHERE parent_id = ?parent_id ORDER BY title', array('parent_id' => $parent_id));
    foreach ($result as $row) {
        $even = 1 - $even;
        print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region level_$level id_" .
            $row['id'] . "'>" . esc_html($row['title']) .
            (empty($row['region_comment']) ? '' : ' <span class="comment">' .
                esc_html($row['region_comment']) . '</span>') .
            "</td>\n\t<td class='align-right'>" . format_num($row['region_cnt']) . "</td>\n";

        region_stat($row['id'], $level + 1);
    }
}

function dic_stat($caption, $field_title, $field)
{
    ?>

    <table class="stat">
        <caption><?php print $caption; ?></caption>
        <thead>
        <tr>
            <th><?php print $field_title; ?></th>
            <th><?php _e('Records count', WW1_TXTDOM); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $result = gbdb()->get_column('SELECT ?#field, ?#field_cnt FROM ?@table' .
            ' WHERE ?#field_cnt != 0 ORDER BY ?#field',
            array(
                '@table' => "dic_{$field}s",
                '#field' => $field,
                '#field_cnt' => "{$field}_cnt",
            ), TRUE);
        $even = 0;
        foreach ($result as $field => $cnt) {
            $even = 1 - $even;
            print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . esc_html($field) .
                "</td>\n\t<td class='align-right'>" . format_num($cnt) . "</td>\n</tr>";
        }
        ?>
        </tbody>
    </table>
    <?php
}

html_footer();
