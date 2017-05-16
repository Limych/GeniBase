<?php

/**
 * @group	class.gb-dbase.php
 */
class Tests_GB_DBase extends GB_UnitTestCase
{
    // gbdb()->table_unescape are skipped — it's synonim for gbdb()->field_unescape
    function test_field_unescape()
    {
        $data = array(
            '`field1`' => 'field1',
            '```field2```' => '`field2`',
            '```field``3`' => '`field`3'
        );
        foreach ($data as $src => $res)
            $this->assertEquals($res, gbdb()->field_unescape($src));
        
        $src = array_keys($data);
        $res = array_values($data);
        $this->assertEquals($res, gbdb()->field_unescape($src));
    }

    function test_table_escape()
    {
        $data = array(
            'table1' => '`' . DB_PREFIX . 'table1`',
            '```table2```' => '`' . DB_PREFIX . '``table2```',
            '```table3`````' => '`' . DB_PREFIX . '``table3`````'
        );
        foreach ($data as $src => $res)
            $this->assertEquals($res, gbdb()->table_escape($src));
        
        $src = array_keys($data);
        $res = array_values($data);
        $this->assertEquals($res, gbdb()->table_escape($src, true));
        $res = implode(', ', array_values($data));
        $this->assertEquals($res, gbdb()->table_escape($src));
        $this->assertEquals($res, gbdb()->table_escape($src, false));
    }

    function test_field_escape()
    {
        $data = array(
            'field`name' => '`field``name`',
            '`field``2`' => '```field````2```'
        );
        foreach ($data as $src => $res)
            $this->assertEquals($res, gbdb()->field_escape($src));
        
        $src = array_keys($data);
        $res = array_values($data);
        $this->assertEquals($res, gbdb()->field_escape($src, true));
        $res = implode(', ', array_values($data));
        $this->assertEquals($res, gbdb()->field_escape($src));
        $this->assertEquals($res, gbdb()->field_escape($src, false));
    }

    function test_data_escape()
    {
        $src = array(
            'text',
            "text\0\n\r'\"\x1Atext",
            123,
            NULL,
            12.34,
            1.0
        );
        $res = array(
            '"text"',
            '"text\\0\\n\\r\\\'\\"\\Ztext"',
            123,
            'NULL',
            '12.34',
            1
        );
        foreach (array_keys($src) as $key)
            $this->assertEquals($res[$key], gbdb()->data_escape($src[$key]));
        
        $this->assertEquals($res, gbdb()->data_escape($src, TRUE));
        $res = implode(', ', $res);
        $this->assertEquals($res, gbdb()->data_escape($src, FALSE));
        $this->assertEquals($res, gbdb()->data_escape($src));
    }

    function test_make_condition()
    {
        // GB_DBase::make_regex()
        $src = 'Ё??ч?к*.+';
        $res1 = '(Е|Ё)(..){2}ч(..)к(..)+\\\\.\\\\+';
        $res2 = '[[:<:]]' . $res1 . '[[:>:]]';
        $this->assertEquals($res1, GB_DBase::make_regex($src, FALSE));
        $this->assertEquals($res2, GB_DBase::make_regex($src, TRUE));
        $this->assertEquals($res2, GB_DBase::make_regex($src));
        
        // GB_DBase::make_condition()
        $src = 'Ё??ч?к*_%';
        $res1 = 'Ё__ч_к_%\\_\\%';
        $res2 = '%' . $res1 . '%';
        $this->assertEquals($res2, GB_DBase::make_condition($src, FALSE));
        $this->assertEquals($res1, GB_DBase::make_condition($src, TRUE));
        $this->assertEquals($res1, GB_DBase::make_condition($src));
    }

    function test_prepare_query()
    {
        $src = 'SELECT ?#field FROM ?_table_$1, ?@table WHERE ?#field = ?data OR ?#field IN (?data2)';
        $sub = array(
            '@table' => 'table',
            '#field' => 'field',
            'data' => 'data',
            'data2' => array(
                'data2-1',
                'data2-2',
                null,
                4
            )
        );
        $res = 'SELECT `field` FROM `' . DB_PREFIX . 'table_$1`, `' . DB_PREFIX . 'table` WHERE `field` = "data" OR `field` IN ("data2-1", "data2-2", NULL, 4)';
        $this->assertEquals($res, gbdb()->prepare_query($src, $sub));
    }

    function test_set_row_insert()
    {
        $modes = array(
            FALSE => 'INSERT',
            GB_DBase::MODE_INSERT => 'INSERT',
            GB_DBase::MODE_IGNORE => 'INSERT IGNORE',
            GB_DBase::MODE_REPLACE => 'REPLACE'
        );
        $data = array(
            array(
                'field1' => NULL,
                'field`2' => 123
            ),
            array(
                'field1' => 'data',
                'field`2' => 12.3
            )
        );
        foreach ($modes as $mode => $q_start) {
            $res = $q_start . ' INTO ?_table (`field1`, `field``2`) VALUES (NULL, 123)';
            $this->assertEquals($res, gbdb()->_set_row_insert('?_table', $data[0], $mode));
        }
        foreach ($modes as $mode => $q_start) {
            $res = $q_start . ' INTO ?_table (`field1`, `field``2`) VALUES (NULL, 123), ("data", 12.3)';
            $this->assertEquals($res, gbdb()->_set_row_insert('?_table', $data, $mode));
        }
    }

    function test_set_row_update()
    {
        $data = array(
            'field1' => NULL,
            'field`2' => 123,
            '`field3`' => 'data'
        );
        $key = array(
            'id' => 'unique_key'
        );
        $key2 = array(
            'id' => 'unique_key',
            'key2' => 456
        );
        $key3 = array(
            'field`2'
        );
        $key4 = array(
            'field1',
            'field`2'
        );
        $key5 = array(
            'id'
        );
        
        // UPDATE mode
        $res = 'UPDATE ?_table SET `field1` = NULL, `field``2` = 123, ```field3``` = "data" WHERE `id` = "unique_key"';
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, 'unique_key', GB_DBase::MODE_UPDATE));
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, $key, GB_DBase::MODE_UPDATE));
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, 'unique_key', FALSE));
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, $key, FALSE));
        $res .= ' AND `key2` = 456';
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, $key2, GB_DBase::MODE_UPDATE));
        
        // DUPLICATE mode
        $res = 'INSERT INTO ?_table SET `field1` = NULL, `field``2` = 123, ```field3``` = "data", `id` = "unique_key" ON DUPLICATE KEY UPDATE `field1` = NULL, `field``2` = 123, ```field3``` = "data"';
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, 'unique_key', GB_DBase::MODE_DUPLICATE));
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, $key, GB_DBase::MODE_DUPLICATE));
        $res = 'INSERT INTO ?_table SET `id` = "unique_key", `key2` = 456';
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', array(), $key2, GB_DBase::MODE_DUPLICATE));
        $res = 'INSERT INTO ?_table SET `field1` = NULL, `field``2` = 123, ```field3``` = "data" ON DUPLICATE KEY UPDATE `field1` = NULL, ```field3``` = "data"';
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, $key3, GB_DBase::MODE_DUPLICATE));
        $res = 'INSERT INTO ?_table SET `field1` = NULL, `field``2` = 123, ```field3``` = "data" ON DUPLICATE KEY UPDATE ```field3``` = "data"';
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, $key4, GB_DBase::MODE_DUPLICATE));
        $res = 'INSERT INTO ?_table SET `field1` = NULL, `field``2` = 123, ```field3``` = "data" ON DUPLICATE KEY UPDATE `field1` = NULL, `field``2` = 123, ```field3``` = "data"';
        $this->assertEquals($res, gbdb()->_set_row_update('?_table', $data, $key5, GB_DBase::MODE_DUPLICATE));
    }

    function test_set_row_failures()
    {
        $data = array(
            'field1' => '123',
            'field2' => NULL
        );
        $key = array(
            'id' => 'unique_id'
        );
        
        $suppress = gbdb()->suppress_errors();
        
        $this->assertFalse(gbdb()->_set_row_insert('?_table', $data, 'qwe'));
        $this->assertNotEmpty(gbdb()->last_error);
        $this->assertFalse(gbdb()->_set_row_insert('?_table', $data, 'insert'));
        $this->assertNotEmpty(gbdb()->last_error);
        $this->assertFalse(gbdb()->_set_row_insert('?_table', $data, GB_DBase::MODE_UPDATE));
        $this->assertNotEmpty(gbdb()->last_error);
        $this->assertFalse(gbdb()->_set_row_update('?_table', $data, $key, 'qwe'));
        $this->assertNotEmpty(gbdb()->last_error);
        $this->assertFalse(gbdb()->_set_row_update('?_table', $data, $key, 'update'));
        $this->assertNotEmpty(gbdb()->last_error);
        $this->assertFalse(gbdb()->_set_row_update('?_table', $data, $key, GB_DBase::MODE_INSERT));
        $this->assertNotEmpty(gbdb()->last_error);
        
        gbdb()->suppress_errors($suppress);
    }

    function test_split_queries()
    {
        $src = "SELECT * WHERE `field1` = \"data1;data2\"\";\"-- First comment; comment2\n" . "AND `field;2` = 'data3;data 4' AND `field3` = '\\';data5'#3th comment;\n" . "AND `field6` = ''';'/*Another comment;*/;/*Comment; 4*/CREATE TABLE";
        $res = array(
            "SELECT * WHERE `field1` = \"data1;data2\"\";\"-- First comment; comment2\n" . "AND `field;2` = 'data3;data 4' AND `field3` = '\\';data5'#3th comment;\n" . "AND `field6` = ''';'/*Another comment;*/",
            "/*Comment; 4*/CREATE TABLE"
        );
        $this->assertEquals($res, gbdb()->split_queries($src));
    }

    function test_remove_comments()
    {
        $src = "SELECT * WHERE `field1` = 'data1-- data2'-- First comment\n" . "AND `field2` = \"data3#data 4\"#2nd comment\n" . "AND `field3` = '/*'';Not a comment*/'/*Another comment;*/;/*Comment; 4*/SELECT";
        $res = "SELECT * WHERE `field1` = 'data1-- data2' AND `field2` = \"data3#data 4\" " . "AND `field3` = '/*'';Not a comment*/' ; SELECT";
        $this->assertEquals($res, gbdb()->remove_comments($src));
    }

    function test_create_table_patch()
    { // !!! NEED MySQL
                                             // TODO
    }
}
