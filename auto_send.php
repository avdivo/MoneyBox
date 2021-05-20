<?php
///////////////////////////////////////////////////////////////////////////////////////////////////////////
/*Автоматизация перевода в копилки
Обработка выполненных ремонтов, передача данных о переводе денег от заказов в копилки, хранение информации
- Автоматический запуск по времени
- Сбор и хранение данных о выполненных заказах (даты, суммы). Учет переводов в копилки
- Поиск в коментариях к заказам информации о переводах в копилки, сохранение, передача команд о переводах исполнителю
- Отправка предупреждений об отсутствии информации о переводах Телеграмм боту
- Предоставление пользователю через собственный интерфейс информации о выполненных заказах и переводах в копилки
- Предоставление Телеграмм боту по требованию информации о выполненных заказах и переводах в копилки

АЛГОРИТМ ПРОГРАММЫ

1. Прием и выполнение команд Телеграмм бота. 
2. Получение в БД АСЦ информации о выполненных заказах и выполнение команд о переводах из комментариев к заказам.
    - Номер заказа
    - Дата и время выдачи заказа
    - Команды о переворах в копилки (в виде команд)
    - Сумма выручки и чистая прибыть от заказа
    Запрашивается информация о заказах после последнего сохраненного, если сохраненных нет то с текущей даты.
3. Перебор заказов. Выполнение команд из комментариев к заказу, с соблюдением их приодитета над автоматическими, 
    а так же соблюдение приоритета переводов из бюджета над всеми.
    Команды считываются из всех строк комментария к заказу. 
    - Добавляются в очередь выполнения начирая с последних (новых). Добавляются с особенностью, 
        команды, которые осуществляют перевод из бюджета записываются в начало массива, остальные в конец.
    - Если встречается одинаковая команда с той что уже есть в очереди (где совпадатет тип, источник и приемник)
         то она игнорируется. 
    - Если встречается команда отмены ignore this, то все последующие команды игнорируются.
    - Если встречается команда отмены ignore all, то игнорируется вся очередь, в том числе автоматические команды.
    - Последними, на тех же условиях добавляются команды из sendAuto, таким образом соблюдается приоритет.
    - Сожранение данных для отчета.
4. Отчет в Телеграмм бот о выполненных действиях: номер заказа - комментарий. В комментарии выполненные команды,
    полная отмена (не выполнялись команды вообще, даже зачисление всей суммы в кассу, авто команды тоже отменены), 
    не выполненная команда по причине сбоя или нехватки денег в копилке.
5. Выдача ответов на запросы Телеграм бота, если они были и выход из программы.
6. Вывод интерфейса пользователя, если вызов программы осуществлялся не автоматически и не ботом.


НАСТРОЙКИ хранятся в таблице БД

run TINYINT (1) = 1 - программа обрабатывает все запросы, если 0 ничего не делает, ждет включения
runTime TINYINT (1) = 1 - разрешить автоматическое выполнение программы (по времени)
runBot TINYINT (1) = 1 - разрешить отвечать запросам и обрабатывать команды Телеграмм бота
runManual TINYINT (1) = 1 - разрешить выполнение при входе в программу пользователя
orderSend TINYINT (1) = 1 - выполнять команды из комментариев заказов
messageOrder TINYINT (1) = 1 - включить отчет об обработке заказов
scanDate (DATETIME) - дата и время с которой начинается чтение данных из программы АСЦ. Сюда сохраняется дата 
            выдачи заказа обработанного последним и в следующий раз будет поиск следующих.
            Если поле пустое, то дата и время ставятся текущими
commandAuto (VARCHAR 254) = 'send 0-1=100% send 1-3=100' - команды автоматически выполняются для каждого заказа
                    
КОМАНДЫ общие

send - перевод из копилки в копилку. После слова идет пробел, далее параметры. Перевод процентов от выручки
prof - перевод из копилки в копилку. После слова идет пробел, далее параметры. Перевод процентов от прибыли

Параметры:
первая цифра - номер копилки (пользовательский) источника
тире - объязательный символ
вторая цифра - номер копилки (пользовательский) приемника
равно - объязательный символ
процент  - не объязательный символ. Процент обозначает что сумма после равно -
            это процент перевода от чистой прибыли заказа, иначе это просто сумма.

ignore this - отменить все команды оставленные ранее в комментариях
ignore all - отменить все команды, в том числе автоматические

КОМАНДЫ только для Телеграмм бота

start - начать работу программы
stop - остановить работу программы
runTime (1/0) - включить выключить соответствующую настройку
orderSend (1/0) - включить выключить соответствующую настройку
messageOrder (1/0) - включить выключить соответствующую настройку
*/////////////////////////////////////////////////////////////////////////////////////////////////////////
/*
$haystack = Array ('send 100-20=60', 'send 100-20=60',
 'ignore all',
 'ignore this',
 'debt 100-2=60',
  'send 100-20=60%',
  'send 10-20=60' )
;
*//*
$on = 'send 1-2=60';
$found = preg_split('/\s|=|%/', $on);

//$pattern = '/(send|debt) \d+\-\d+\=\d+%?|(ignore this|ignore all)/';
$pattern = '/debt 1/';

//$matches  = preg_grep ($pattern, $haystack);

//$ar = $matches[0];
print_r($found);
//echo ('<br>');
//print_r($matches);
die;
*/



//Чтение настроек
$query ="SELECT `run`, `runTime`, `runBot`, `runManual`, `orderSend`, `messageOrder`, `scanDate`, `commandAuto`
FROM `auto_send`";
$result = mysqli_query($link, $query) or die("Ошибка чтения настроек.");
$options = mysqli_fetch_assoc($result); //Сохраняем настройки в массиве

//Можно ли выполняться программе?
$run = $options['runManual']; //Если программа запущена пользователем, то зависит от этой настнойки
if ($who == 4) $run = $options['runTime'] && $run; //Может зависеть от настройки для таймера
$run = $options['run'] && $run; //Все зависит от общего разрешения

//Выполнение программы разрешено
if ($run) {
    //До окончания работы Автомата автором записей делаем autoSend
    $whoCopy = $who;
    $who = 4; //Указывапем что работает автомат

    //Подключение к БД ASC
    include ("connect2.php");
    
    //Если дата последнего выполненного и обработанного заказа не указана, то ставим текущую дату
    if ($options['scanDate'] == '0000-00-00 00:00:00') $options['scanDate'] = date("Y-m-d 00:00:00");

    // Вычитываем из таблици сегодняшние выданные заказы
    $query ="SELECT `id`, `out_date`, `real_repair_cost`, (`real_repair_cost`)-(`parts_cost`) AS `profit` 
    FROM `workshop` WHERE `state`='8' AND `out_date` > '{$options['scanDate']}'";

    $result = mysqli_query($linkASC, $query) or die("Ошибка подключения к базе АСЦ" . mysqli_error($linkASC));

    //Выполненные заказы найдены, обрабатываем их
    if(mysqli_num_rows($result))
    {
        //Передаем объект запроса к БД и команды автовыполнения для каждого заказа
        $dateLastOrder = orders($result, $options['commandAuto']);

        $query ="UPDATE `auto_send` SET `scanDate` = '$dateLastOrder'";
        $result = mysqli_query($link, $query) or die("Ошибка записи настроек.");

    }    

    //Возвращяем пользователя
    $who = $whoCopy;

}


//Функция выполняет перебор и выполнение команд из комментариев заказов полученных в
//$result - это объект возвращенный БД в котором должны быть следующие данные для каждого заказа:
//`id`, `out_date`, `real_repair_cost`, (`real_repair_cost`)-(`parts_cost`) AS `profit`
//А так же команды поля commandAuto из настроек (команды автовыполняющиеся для каждого заказа)
//Возвращяет дату последнего обработанного заказа
function orders($result, $commandAuto)
{

    //Подключаемся к БД
    global $link;
    global $linkASC;
    
    //Узнаем пользовательский номер Бюджета
    //Чтение номера из БД
    $query ="SELECT `nn` FROM `box` WHERE `id`='1'";
    $result3 = mysqli_query($link, $query) or die("Ошибка чтения номера бюджета.");
    $str = mysqli_fetch_assoc($result3); //Сохраняем в массиве
    $budget = $str['nn']; //Замисываем в переменную 

    $pattern = '/(send|prof) \d+\-\d+\=\d+%?|(ignore this|ignore all)/'; //Маска команд для поиска 
    $patternBudget = '/(send|prof) ' . $budget . '/'; ////Маска перевода из бюджета

    while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка заказ
        
        $query ="SELECT `text` FROM `comments` WHERE `remont`='{$row['id']}' ORDER BY `id` DESC";
        $result2 = mysqli_query($linkASC, $query) or die("Ошибка подключения к базе АСЦ" . mysqli_error($linkASC));
        
        toTbot(date('Заказ ' . $row['id'])); //Вызываем функцию отправки сообщения в Telegram с номером заказа

        //Получив комментарии ищем в них команды и записываем их в массив
        //Предварительно проверяем наличие подобной команды уже в массиве 
        //(подобные по части send 1-2 или prof 1-0)
        //Команды перевода из бюджета 0 в копилки (send 0 или prof 0) пишем в начало массива, остальные в конец
        //Бюджет не объязательно 0, может быть другой номер заданный пользователем, он в переменной $budget
        $command = []; //Массив команд
        $comment = []; //Запишем все строки комментариев
        //Переришем все строки запроса в массив и в конец добавим commandAuto
        while ($text = $result2->fetch_assoc()) {
            array_push($comment, $text['text']); //Добавим строки в конец массива
        }
        array_push($comment, $commandAuto); //Добавим автоматические команды в конец

        //Если встретим отмену, нужно будет полпустить все строки и выполнить последнюю
        $last = count($comment); //Количество строк в массиве
        $i = 0; //Счетчик итераций
        $continue = false; //Если true, пропустит все итерации кроме последней

        foreach ($comment as $text) {

            if (++$i != $last && $continue) continue; //Пропускать итерации до последней, если включена перемотка

            //Ищем в комментариях строки команд перевода
            //Вида send 1-2=1000 или prof 1-0=100
            //А так же отмены операций вида ignore this или ignore all
            //Ищем команды по маске $pattern в $text['text'] и записываем в массив $matches
            preg_match_all ($pattern , $text , $matches); 
            $fromString = $matches[0]; //Переносим в одномерный массив из многомерного
            //Теперь в массиве $fromString должны быть команды из одной строки
            //Добавим их поочереди в основной массв заказа $command если там нет уже такой команды

            if (count($fromString)){
                //Если команды найдены 
                foreach ($fromString as $value) { 
                    //В $value одна полная команда
                    if ($value == 'ignore this') {
                        $continue = true; //Включаем перемотку
                        break; //Все дальнейшие команды этого заказа отменяем, выполняем имеющиеся
                    }
                    if ($value == 'ignore all') {
                        //Отменяем вообще все команды этого заказа, записываем это в отчет
                        //$command = []; //Очищаем массив команд для этого заказа
                        break 2;
                    }
                    
                    //Получим из команды часть, обрезав 'send 1-2' по которой будем искать
                    preg_match('/(send|prof) \d+\-\d+/', $value, $part); //Строка для поиска в $part[0]
                    $partFind = '/' . $part[0] . '/'; //Маска поиска в массиве
                    
                    //Ищем $partFind в массиве $command
                    $matches  = preg_grep ($partFind, $command); 
                    //Если в массиве нет такого перевода то добавляем его туда
                    if (!count($matches)){ 
                        //$command += 
                        //Если перевод из Бюджета, элемент записывается в начало массива
                        //$foundBudget[0] - false если не из Бюджета
                        preg_match($patternBudget, $value, $foundBudget); 
                
                        if ($foundBudget){
                            //Перевод из Бюджета, записываем команду в начало массива
                            array_unshift($command, $value);
                        } else {
                            //Перевод не из Бюджета, записываем команду в конец массива
                            array_push($command, $value);
                        }

                    }

                }
                

            }

            
        } //Конец перебора строк комментариев заказа
        
        //В массиве $command команды для выполнения переводов
        //Перебираем его и выполняем их поочереди
        foreach ($command as $value) { 
            //Команды могут быть с переводом %, этот процент от общей суммы нужно перевести
            //Находим такие команды, вычисляем сумму перевода, округляем ее до 100, если перевод не 100%
            //В функцию передаем уже команды с суммой

            //Если в команде есть знак %, то в массиве будет 4 элемента, 
            // 0 - команда
            // 1 - параметры до =
            // 2 - процент перевода или сумма
            // 3 - пустой, но по его существованию узнаем о том что это перевод %
            $found = preg_split('/\s|=|%/', $value);

            if (isset($found[3])) {
                //send - переводит процент от всей суммы
                //prof - переводит процент от чистой прибыли
                //в зависимости от этого записываем в переменную нужную сумму
                $income = ($found[0] == 'send') ? $row['real_repair_cost'] : $row['profit'];

                //Вычисляем проценты и округляем до целого
                $summa = round($income / 100 * $found[2], 0, PHP_ROUND_HALF_UP);
                if ($found[2] != 100) $summa = round($summa, -2); //Округляем до 100
                //Готовим команду для передачи на выполнение
                $value = $found[0] . ' ' . $found[1] . '=' . $summa; 
            }

            $status = oneCommand($value, $row['id']); //Выполняем перевод
            $status = ($status == '-1') ? 'Fail' : 'Ok'; //Какое сообщение отправить в отчет
            toTbot("$value : $status"); //Вызываем функцию отправки сообщения в Telegram
        }

        $dateLastOrder = $row['out_date']; //Запоминаем дату последнего обработанного заказа    

    } //Конец перебора заказов
    
    return $dateLastOrder;
}

?>