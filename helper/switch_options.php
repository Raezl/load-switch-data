<?php

class SwitchOptions
{

    public static function filter($type, $operator, $childs_type, $childs_colunm, $childs_operator, $childs_value): array
    {
        return array(
            'type' => $type,
            'operator' => $operator,
            'childs' => array(
                array(
                    'type' => $childs_type,
                    'column' => $childs_colunm,
                    'operator' => $childs_operator,
                    'value' => $childs_value
                )
            )
        );
    }

    public static function sort($column, $dir)
    {
        return array(
            array(
                'column' => $column,
                'dir' => $dir
            )
        );
    }

}