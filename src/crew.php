<?php
require_once ('gb-config.php'); // Load GeniBase
require_once ('inc.php'); // Основной подключаемый файл-заплатка

html_header(__('Project crew', WW1_TXTDOM));
print '<p><a href="/">' . __('&laquo;&nbsp;Back to search', WW1_TXTDOM) . "</a></p>\n";
print '<h1>' . __('Project crew', WW1_TXTDOM) . "</h1>\n";

crew_list(__('Project management', WW1_TXTDOM), array(
    'Николай Чернухин'
));
crew_list(__('Preparation of electronic version of the lists', WW1_TXTDOM), array(
    'Andris Jirgensons',
    'Ирина Ашевская',
    'Александр Балахнин',
    'Татьяна Беликина',
    'Василий Богатырев',
    'Надежда Вус',
    'Павел Гаврильченко',
    'Андрей Горбоносов',
    'Владимир Гришин',
    'Дмитрий Долгов',
    'Никита Дубинин',
    'Ирина Егорова',
    'Татьяна Ефименко',
    'Дмитрий Ждан',
    'Анастасия Загорулько',
    'Сергей Зарембо',
    'Юлия Зилева',
    'Всеволод Каллистов',
    'Денис Калёнов',
    'Оксана Камаева',
    'Татьяна Каракозова',
    'Дарья Карпова',
    'Андрей Кондратюк',
    'Роман Корнев',
    'Виктор Котов',
    'Андрей Кочешков',
    'Елена Кравцова',
    'Алексей Кудрявцев',
    'Елена Лаврентьева',
    'Алексей Лысиков',
    'Людмила Мальцева',
    'Валентин Мацкевич',
    'Юлия Мезенина',
    'Александр Молчанов',
    'Наталья Мясникова',
    'Ольга Назарова',
    'Елена Наумова',
    'Елена Овчинникова',
    'Валерий Осьмаков',
    'Андрей Панфилов',
    'Ольга Панькова',
    'Сергей Петров',
    'Алла Прошкина',
    'Владислав Савельев',
    'Михаил Семенов',
    'Владимир Сенчихин',
    'Ирина Сидорова',
    'Владимир Слесарев',
    'Галина Соколова',
    'Елена Соловьева',
    'Татьяна Терехова',
    'Анастасия Урнова',
    'Андрей Фомичев',
    'Александр Чайчиц',
    'Игорь Чернухин',
    'Николай Чернухин',
    'Алексей Щенников',
    'Игорь Яковлев'
));
crew_list(__('Programming', WW1_TXTDOM), array(
    'Андрей Хроленок'
));

$link = 'http://forum.svrt.ru/index.php?showtopic=7282';
print '<p class="align-center">' . sprintf(__('Do you want to <a href="%s" target="_blank">join us? Welcome!</a>', WW1_TXTDOM), $link) . "</p>\n";
html_footer();

/**
 * Print crew list.
 *
 * @param string $caption
 *            of list.
 * @param array $crew
 *            list.
 */
function crew_list($caption, $crew)
{
    // TODO: Crew list sorting
    print "<section class='crue'><h2>$caption</h2>\n";
    foreach ($crew as $person)
        print "<span class='name'>$person</span>\n";
    print "</section>\n";
}
