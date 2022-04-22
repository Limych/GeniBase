<?php
require_once('gb-config.php'); // Load GeniBase
require_once('inc.php'); // Основной подключаемый файл-заплатка

html_header(__('Project crew', WW1_TXTDOM));
print '<p><a href="/">' . __('&laquo;&nbsp;Back to search', WW1_TXTDOM) . "</a></p>\n";
print '<h1>' . __('Project crew', WW1_TXTDOM) . "</h1>\n";

crew_list(__('Project management', WW1_TXTDOM), '1');
crew_list(__('Preparation of electronic version of the lists', WW1_TXTDOM), '3');
//crew_list(__('*'                                             , WW1_TXTDOM), '4'); //'Others assistants'
crew_list(__('Programming', WW1_TXTDOM), '2');

$link = 'http://forum.svrt.ru/index.php?s=&showtopic=7282&view=findpost&p=140062';
print '<p class="align-center">' . sprintf(__('Do you want to <a href="%s" target="_blank">join us? Welcome!</a>', WW1_TXTDOM), $link) . "</p>\n";
html_footer();

/**
 * Print crew list.
 *
 * @param string $caption Caption of list.
 * @param string $crew_type Crew type (1-management,2-programming,3-volunteer,4-assistant))
 */
function crew_list($caption, $crew_type)
{
    // TODO: Crew list sorting

    $even = 0;
    $result = gbdb()->get_table('select du.`name`, du.`surname`, if(sign(instr(du.`show_data`,"email"))=1,du.`email`,"") email'
        . ' from ?_dic_users du'
        . ' where substr(du.`roles`,' . $crew_type . ',1) =  "1" order by 2');

    if (!empty($result)) {
        print "<section class='crue'><h2>$caption</h2>\n";

        foreach ($result as $row) {
            //$even = 1-$even;

            print "<span class='name'>"
                . '<span class="name1">' . esc_html($row['name']) . '</span>'
                . '<span class="name2">' . esc_html($row['surname'])
                . (empty($row['email']) ? '' : ' <a href=mailto:' . esc_html($row['email']) . '?subject=WW1>&#9993</a> ')
                . '</span>'
                . '</span>';
        }

        print "</section>\n";
    }
}
