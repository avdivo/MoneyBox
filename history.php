<?php

//Проверяем на вход с переданными параметрами GET box_his
//Номер копилки для которой выводится история
if (isset($_GET["box_his"]))
{
    //$_GET["box_his"] должна содержать номер копилки для которой нужно показать страницу истории
    //Для вывода страницы через шаблонизатор twig нужно подготовить следующие данные:
    /*  box = []
            idBox - Уникальный ключ копилки (используется для ссылок на Редактирование и Статистику)
            nnBox - Номер копилки задаваемый пользователем
            nameBox - Название копилки
            summaBox - Сколько денег в копилке

            events = [] - нумерованный массив (начиная с 1) с названиями операций:
            'Перевод в', 'Перевод из', 'Взнос', 'Изъятие', 'Выдано в долг', 'Получено в долг из', 'Возврат долга в', 
            'Возврат долга из', 'Прощен долг', 'Прощен долг от'

        partners = [] - ассоциированный массив с названиями копилок где ключ - номер (id) копилки
        history = [] - двумерный массив, в нем ключи (номера (id) операций в истории) с именами 
                        ассоциированных массивов, в которых ключи со значениями: 
                        “id” => “идентификатор записи”,
                        “date” => “дата и время совершения операции”,
                        “event” => “номер пояснения операции в массиве events”,
		                “partner” => “название второго участника сделки (ключ копилки в массиве partners)”,
		                “summa” => “сумма перевода”
 */

    $box = new MoneyBox($_GET["box_his"]); //Создаем объект копилки с полученным номером
    //$aqrrayBox = (array) $box; //Переводим объект в массив

    $partners = MoneyBox::getNamesBox(); //Получаем номера и названия копилок в ассоциированном массиве

    //Массив с названиями операций
    $events = [1 => 'Перевод в', 'Перевод из', 'Взнос', 'Изъятие', 'Выдано в долг', 'Получено в долг из', 
    'Возврат долга в', 'Возврат долга из', 'Прощен долг', 'Прощен долг от'];

    try {
            
        //Запрос к БД на получение списка истории для выбранной копилки
        $query ="SELECT history.`id`, DATE_FORMAT(history.`date`, '%d.%m.%Y %H:%i') as `date`, history.`event`, 
        history.`source`, history.`receiver`, history.`summa`, user.`name` AS `username`
                FROM history
                INNER JOIN user ON history.`user` = user.`id`
                WHERE `source` = {$_GET["box_his"]} OR  `receiver` = {$_GET["box_his"]}
                ORDER BY history.`id` DESC";
        $result = mysqli_query($link, $query); 
        
        $history = []; //Массив, куда будем записывать историю

        //Запишем названия копилок в массив
        if ($result) {
            while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка долг
                //Заносим в массив ключ -> ассоциированный массив
                //Но в этом массиве должен быть ключ “partner”, значения в котором будут указывать на
                //значения в массиве $partners, поэтому предварительно меняем его
                if ($row['source'] == $_GET["box_his"]) $row += ['partner' => $row['receiver']];
                if ($row['receiver'] == $_GET["box_his"]) $row += ['partner' => $row['source']];

                //А в ключе event должны быть ссылки на значения массива $events, пересчитанные
                //Меняем ключ event
                /*Для получения индекса массива нужно взять номер event из таблицы истории отнять 1, 
                умножить на 2 и отнять 1, если копилка для которой пишется история является источником в операции. 
                Исключение когда event = 1 или 3, эти цифры просто соответствуют 3 и 4 соответственно. Например: 
                Выдано в долг - 4 - 1 = 3 * 2 = 6, источник эта копилка (она выдавала), 
                значит 6 - 1 = 5 - элемент в массиве events. */
                if ($row['event'] == 1 || $row['event'] == 3) {
                    if ($row['event'] == 3) $row['event'] = 4;
                    if ($row['event'] == 1) $row['event'] = 3;
                    $row['partner'] = '0';
                } else {
                    $row['event'] = ($row['event'] - 1) * 2;
                    if ($row['source'] == $_GET["box_his"])  $row['event']--;
                }

                $history[$row["id"]] = $row;    //Теперь запишем измененный массив в двумерный
            }
        }

    } catch (Exception $e) {
        echo $e->getMessage();
        die();
    }

    // Передаём в шаблон переменные и значения
    // Выводим сформированное содержание
    
    // Подгружаем и активируем авто-загрузчик Twig-а
    require_once 'vendor/autoload.php';
    Twig_Autoloader::register();

    try {
        // указывае где хранятся шаблоны
        $loader = new Twig_Loader_Filesystem('templates');
    
        // Инициализируем Twig
        $twig = new Twig_Environment($loader);
    
        // Подгружаем шаблон
        $template = $twig->loadTemplate('history.twig');
    
        // Передаём в шаблон переменные и значения
        // Выводим сформированное содержание
        //echo $template->render(array ('data' => $toTwig));
        echo $template->render(array ('box' => $box, 'partners' => $partners, 'history' => $history, 
        'events' => $events));

    } catch (Exception $e) {
        die ('Ошибка шаблонизатора Twig: ' . $e->getMessage());
    }

    die;
}
//Для AJAX запроса
//Получение переменной $_GET["cancel"] обозначает что это из истории пришла команда не отмену операции
//с номером отменяемой операции в таблице истории
//В $_GET["box"] находится номер копилки
//Назад нужно вернуть nomer - номер  удаленной записи и summa - остаток в копилке после операции
//При отмене операций 5 и 6 возвращяем еще и переменную string со строкой для замены записи в истории
//Отменяемые операции: 1. Взнос, 2.Перевод, 3. Изъятие, 4. Долг, 5. Возврат долга, 6. Простить долг
if (isset($_GET["cancel"]))
{
    $array = cancel($_GET["cancel"], $_GET["box"]);
    if ($array == '-1') die ('-1');
    echo json_encode($array); //Преобразуем массив в JSON и возвращяем в браузер коду AJAX
    die;
}

//Отмена операции из истории
//$cancelId - id записи в истории, $BoxId - для какой копилки (нужен только для возврата в AJAX)
function cancel($cancelId, $BoxId = '1')
{
    $trans = new Transactions($cancelId); //Создаем объект с описанием операции из истории

    //Для совершения обратного перевода меняем местами источник и приемник
    $temp = $trans->sourceTrans;
    $trans->sourceTrans = $trans->receiverTrans;
    $trans->receiverTrans = $temp;
    $string = 'nothing'; //Если будет создан долг, в этой строке будет его описание для страници истории

    //1-4 операции отменяются одинаково. Делаем обратный перевод и удаляем запись в истории
    //Для 4 еще удаляем долговую запись
    if ($trans->eventTrans > 0 && $trans->eventTrans < 5){
        //Подготовка запросов к БД
        //Меняем суммы в копилках. Выходим с ошибкой если не хватает денег
        if ($trans->boxChangeTrans() == '-1') return ('-1'); 

        //Удаляем долговую запись, если отменяем дачу в долг
        if ($trans->eventTrans == 4){
            //Номер долговой записи находится в свойстве additionTrans
            if ($trans->debtChangeTrans($trans->additionTrans) == '-1')  return ('-1');
        }
        //Выполняем транзакцию с удалением истории (false). Выходим если ошибка
        if ($trans->saveTrans(false) == '-1') return ('-1');

    } else {
        //5, 6 операции отменяются так: eventTrans меняем на 4. Делаем обратный перевод для 5
        //Восстанавливаем долговую запись и запись в истории. 

        if ($trans->eventTrans == '5'){
            //Меняем суммы в копилках. Выходим с ошибкой если не хватает денег
            if ($trans->boxChangeTrans() == '-1') return ('-1'); 
        }
        
        $trans->eventTrans = 4; //Указываем что это новый долг
        
        //Запишем в транзакцию удаление строки из истории
        $trans->transactions[] = "DELETE FROM `history` WHERE `id` = '$trans->idTrans'";

        //Делаем долговую запись
        if ($trans->debtChangeTrans() == '-1')  return ('-1');
        
        //Выполняем транзакцию с записью в истории (true). Выходим если ошибка
        //Получаем номер созданной долговой записи
        $id = $trans->saveTrans(true);
        if ($id == '-1') return ('-1');

        //При отмене операции 5 и 6 создается новый долг, в отличии от остальных, где только удаление
        //Старая запись из истории удаляется, но создается новая, которая должна быть отображена на странице
        //Поэтому для этих операций создаем строку для истории, которую передадим так же в браузер
        //Для замены старой записи на новую

        //Долг создан, id его получено, теперь нужно узнать if записи в истории (последняя созданная запись)
        $result = mysqli_query($link, 'SELECT LAST_INSERT_ID()'); 
        //Получаем первую запись из массива
        $row = mysqli_fetch_array($result);
        $idHis = $row[0];

        $debt = new Debt($id); //Создаем объект вновь созданного долга для получения информации о нем
        
        //Строка для замены на странице истории старой записи, которая удалится
        $string =
        "<div id='string" . $idHis . "'>" . $debt->dateDebt . " &#8658; 
        Получено в долг из <span  class='namebox'>" . $debt->loanerNameDebt . "</span> - 
        <span  class='money'>" . $debt->summaDebt . "</span>
        <div class='string' title='Отменить' onclick='cancel(`" . $idHis . "`)'>&#10060;</div>
        <span  class='userbox'></span></div>";
    }

    //Возвращяем в строке JSON nomer - номер  удаленной записи и summa - остаток в копилке после операции 
    $sumBox = MoneyBox::getSummBox($BoxId); //Сумма в копилке после операции
    
    //Создаем массив с параметрами для отправки клиенту
    return array("nomer"=>$cancelId, "summa"=>$sumBox, "string"=>$string);

}
?>