<?php
require_once('gb-config.php');    // Load GeniBase
require_once('inc.php');    // Основной подключаемый файл-заплатка

html_header('Последние новости проекта');
?>
    <p><a href="/">« Вернуться к поиску</a></p>
    <h1><small>Последние новости проекта</small></h1>
    <table class="report">
        <thead>
        <tr>
            <th>Дата</th>
            <th>Сообщение</th>
        </tr>
        </thead>
        <tbody>
        <tr class='odd'>
            <td>16/10/2014</td>
            <td>Проведены переговоры с Кудрявцевым Алексеем Николаевичем (ник AlterLoki на форуме ВГД)</br>
                При его участии обработаны фонды Государственного архива Республики Марий Эл, на основе которых создана
                и выложена в свободный доступ Мемориальная книга. В ней отражены сведения об участниках Первой Мировой
                войны – уроженцах Царевококшайского уезда.</br>
                </br>
                Алексей Николаевич с любезностью согласился на добавление информации из этой книги в нашу базу, за что
                ему огромнейшее спасибо!
            </td>
        </tr>
        <tr class='odd'>
            <td>13/10/2014</td>
            <td>Открыто голосование на новое название проекта!</br>
                Загляните на <a href=http://forum.svrt.ru/index.php?showtopic=7450>форум</a> и проголосуйте, пожалуйста!
            </td>
        </tr>
        </tbody>
    </table>

<?php
html_footer();
