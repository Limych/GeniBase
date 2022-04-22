<?php
require_once('gb-config.php'); // Load GeniBase
require_once('inc.php'); // Основной подключаемый файл-заплатка

html_header('Таблица перекодировки транслита');
?>
    <p><a href="/">« Вернуться к поиску</a></p>
    <h1>Таблица перекодировки транслита</h1>
    <p>Перекодировка производится в&nbsp;соответствии со&nbsp;стандартом
        <nobr>ГОСТ 16876&ndash;71,</nobr>
        в&nbsp;который мы&nbsp;добавили небольшие дополнения, отражающие привычки набора транслита многими современными
        пользователями.
    </p>
    <table style="margin: 1em auto">
        <tr>
            <th>Русский символ</th>
            <th>Соответствие в латинице</th>
        </tr>
        <tr class="even">
            <td>А</td>
            <td>A</td>
        </tr>
        <tr class="odd">
            <td>Б</td>
            <td>B</td>
        </tr>
        <tr class="even">
            <td>В</td>
            <td>V</td>
        </tr>
        <tr class="odd">
            <td>Г</td>
            <td>G</td>
        </tr>
        <tr class="even">
            <td>Д</td>
            <td>D</td>
        </tr>
        <tr class="odd">
            <td>Е</td>
            <td>E</td>
        </tr>
        <tr class="even">
            <td>Ё</td>
            <td>Jo, или Yo</td>
        </tr>
        <tr class="odd">
            <td>Ж</td>
            <td>Zh</td>
        </tr>
        <tr class="even">
            <td>З</td>
            <td>Z</td>
        </tr>
        <tr class="odd">
            <td>И</td>
            <td>I</td>
        </tr>
        <tr class="even">
            <td>Й</td>
            <td>Jj, или J</td>
        </tr>
        <tr class="odd">
            <td>К</td>
            <td>K</td>
        </tr>
        <tr class="even">
            <td>Кс</td>
            <td>X</td>
        </tr>
        <tr class="odd">
            <td>Л</td>
            <td>L</td>
        </tr>
        <tr class="even">
            <td>М</td>
            <td>M</td>
        </tr>
        <tr class="odd">
            <td>Н</td>
            <td>N</td>
        </tr>
        <tr class="even">
            <td>О</td>
            <td>O</td>
        </tr>
        <tr class="odd">
            <td>П</td>
            <td>P</td>
        </tr>
        <tr class="even">
            <td>Р</td>
            <td>R</td>
        </tr>
        <tr class="odd">
            <td>С</td>
            <td>S</td>
        </tr>
        <tr class="even">
            <td>Т</td>
            <td>T</td>
        </tr>
        <tr class="odd">
            <td>У</td>
            <td>U</td>
        </tr>
        <tr class="even">
            <td>Ф</td>
            <td>F</td>
        </tr>
        <tr class="odd">
            <td>Х</td>
            <td>Kh, или H</td>
        </tr>
        <tr class="even">
            <td>Ц</td>
            <td>C</td>
        </tr>
        <tr class="odd">
            <td>Ч</td>
            <td>Ch</td>
        </tr>
        <tr class="even">
            <td>Ш</td>
            <td>Sh</td>
        </tr>
        <tr class="odd">
            <td>Щ</td>
            <td>Shh, или Sch</td>
        </tr>
        <tr class="even">
            <td>Ъ</td>
            <td>"</td>
        </tr>
        <tr class="odd">
            <td>Ы</td>
            <td>Y</td>
        </tr>
        <tr class="even">
            <td>Ь</td>
            <td>'</td>
        </tr>
        <tr class="odd">
            <td>Э</td>
            <td>Eh</td>
        </tr>
        <tr class="even">
            <td>Ю</td>
            <td>Ju, или Yu</td>
        </tr>
        <tr class="odd">
            <td>Я</td>
            <td>Ja, или Ya</td>
        </tr>
    </table>
<?php
html_footer();
