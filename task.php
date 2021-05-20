<?php

$menu = [
    [
        'label' => 'Yii framework',
        'url' => 'https://yiiframework.ru'
    ],
    [
        'label' => 'More frameworks',
        'items' => [
            ['label' => 'Laravel', 'url' => 'https://laravel.com/'], 
            ['label' => 'Slim', 'url' => 'http://www.slimframework.com/'],
        ],
    ],
    [
        'label' => 'Symfony', 
        'url' => 'https://symfony.com/',
    ],

];

foreach ($menu as $val) {
    //print_r ($val);
    //echo ('<br>');
    menu($val);
}


function menu($arr) {

    if (array_key_exists('items', $arr)) {
        echo ($arr['label'] . '<br>');
        foreach ($arr['items'] as $val) {
            menu($val);
        }
        retutn;
    }
    if (!array_key_exists('items', $arr)) {
        echo ('<li><a href = "' . $arr['url'] . '">' . $arr['label'] . '</a> </li>');
    }
    retutn;
}

?>