<?php

//Вывод статистики копилки
//Подготовка массива для Twig
if (isset($_GET["box_stat"]))
{
    $box = $_GET["box_stat"]; //Номер копилки

    //Параметры копилки: свойства класса MoneyBox
    $boxArray = new MoneyBox($box); //Создаем объект копилки с полученным номером
    $forStat = (array) $boxArray; //Переводим объект в массив

    unset ($forStat['statBox']); //Удаляем из массива ненужную информацию
    unset ($forStat['debtsBox']); //Удаляем из массива ненужную информацию

    //Эти параметры будут расчитаны в процессе получения другой информации
    $forStat['incomeTotal'] = 0; //Приход за все время
    $forStat['spendingTotal'] = 0; //Расход за все время
    $forStat['maxTotal'] = 0; //Максимальное за все время
    $forStat['maxDateTotal'] = 0; //день максимальной прибыли за все время

    //Добавляем в массив информацию о статистике за все месяцы существования копилки с даты создания
    //Свойства объекта statistics для каждого месяца запишем в элемент-массив массива forStat
    //Считываем все записи со статистикой для интересующей копилки. 
    //Даты записей будут использоваться для получения информации

    //При подключениях к БД могут возникнуть ошибки, отловим их и прервем вывод статистики
    try {
            
        //Запрос к БД на получение списка дат за которые есть статистика
        //Одновременно получаем Суммы приходов, Расходов, максимальный дневной взнос и день этого взноса
        $query ="SELECT `month` FROM `statistics` WHERE `boxStat` = $box ORDER BY `month`";
        $result = mysqli_query($link, $query); 
        
        //Создаем массивы из объектов статистики с полученными датами и записываем их в элемент массива
        //Одновременно рассчитываем сумму прихода и расхода за все месяцы и находим каксимальный дневной приход и дату
        if ($result) {
            while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка дата

                $stat = new statistics($box, $row['month']); //Создаем объект статистики для указанных копилки и даты
                if (!$stat->boxStat) throw new Exception("Статистика за n месяц не получена.");
                //Приводим дату к нужному формату
                $stat->month = date("d.m.Y", strtotime($stat->month));
                $stat->maxDate = date("d.m.Y", strtotime($stat->maxDate));

                $forStat['month'][] = (array) $stat; //Переводим объект в массив. Заносим его в элемент массива

                $forStat['incomeTotal'] += $stat -> income; //Сумма прихода
                $forStat['spendingTotal'] += $stat -> spending; //Сумма расхода

                //Ищем максимальный дневной приход и его дату
                if ($forStat['maxTotal'] < $stat -> max){
                    $forStat['maxTotal'] = $stat -> max; //Запоминаем новый максимум
                    $forStat['maxDateTotal'] = $stat -> maxDate;                    
                }
            }
        }

        //Добавляем в массив информацию о доходах и расходах по дням
        //В элемент массива currentList  добавляем ассоциативные массивы с доходами и расходами по дням 
        //Для этого читаем из истории все даты в которые были доходы или расходы
        //Выбираем поотдельности суммы доходов и расходов за дени когда они есть
        //Перебираем полученные ранее даты и записываем в элемент массива доход и расход за эту дату
        
        $date = new DateTime(); //Текущая дата
        $dayFirst = $date->format( 'Y-m-01' ); //Первый день текущего месяца
        $dayLast = $date->format( 'Y-m-t' );  //Последний день текущего месяца

        //Суммы приходов за каждый день, когда они были
        $query ="SELECT DATE_FORMAT(`date`, '%Y-%m-%d') AS `date`, SUM(`summa`) AS 'income'
        FROM `history` 
        WHERE `receiver` = '$box' 
        AND `event` < 3 
        AND DATE_FORMAT(`date`, '%Y-%m-%d') BETWEEN '$dayFirst' AND '$dayLast' 
        GROUP BY DATE_FORMAT(`date`, '%Y-%m-%d')";
        
        $income = []; //Массив доходов за месяц по дням date -> summa

        $result = mysqli_query($link, $query); 

        if ($result) {
            while ($row = $result->fetch_assoc()) {  //Выбираем массив ключ -> значение, каждое значение сумма за день
                $income[$row['date']] = $row['income']; //Записываем в массив
            }
        }
        
        //Суммы расходов за каждый день, когда они были
        $query ="SELECT DATE_FORMAT(`date`, '%Y-%m-%d') AS `date`, SUM(`summa`) AS 'spending'
        FROM `history` 
        WHERE `source` = '$box' 
        AND `event` < 4 
        AND DATE_FORMAT(`date`, '%Y-%m-%d') BETWEEN '$dayFirst' AND '$dayLast' 
        GROUP BY DATE_FORMAT(`date`, '%Y-%m-%d')";
        
        $spending = []; //Массив расходов за месяц по дням date -> summa

        $result = mysqli_query($link, $query); 

        if ($result) {
            while ($row = $result->fetch_assoc()) {  //Выбираем массив ключ -> значение, каждое значение сумма за день
                $spending[$row['date']] = $row['spending']; //Записываем в массив
            }
        }
        
        //Запрос на получение из Истории дат, в которые были доходы или расходы
        $query ="SELECT DATE_FORMAT(`date`, '%Y-%m-%d') AS `date` FROM `history`
        WHERE (`receiver` = '$box' OR `source` = '$box') 
        AND `event` < 4 
        AND DATE_FORMAT(`date`, '%Y-%m-%d') BETWEEN '$dayFirst' AND '$dayLast'
        GROUP BY DATE_FORMAT(`date`, '%Y-%m-%d') 
        ORDER BY DATE_FORMAT(`date`, '%Y-%m-%d')";

        $result = mysqli_query($link, $query); 
        
        //Создаем массивы из объектов статистики с полученными датами и записываем их в элемент массива
        if ($result) {
            while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка дата

                $formatDat = date("d.m.Y", strtotime($row['date'])); // Преобразуем дату ждя вывода
                //Создаем ассоциированный массив
                $forStat['currentList'][$row['date']] = ['date' => $formatDat]; 
                
                //Если существует в массиве доходов ключ - проверяемая дата, записываем эту сумму в массив как доход
                //если не существует, записываем 0
                $forStat['currentList'][$row['date']] += (array_key_exists($row['date'], $income)) ?
                ['income' => $income[$row['date']]] : ['income' => '0'];

                //Если существует в массиве расходов ключ - проверяемая дата, записываем эту сумму в массив как расход
                //если не существует, записываем 0
                $forStat['currentList'][$row['date']] += (array_key_exists($row['date'], $spending)) ?
                ['spending' => $spending[$row['date']]] : ['spending' => '0'];
            }
        }

        //Добавляем в элемент currentMonth массива ассоциативный массив со статистикой текущего месяца,
        //которую собираем из истории методом statFromHist 
        $stat = new statistics($box); //Создаем объект статистики с пустыми свойствами
        if (!$stat->boxStat) throw new Exception("Статистика за текущий месяц не получена.");
        $stat->statFromHist(); //Заполняем свойства объекта данными из истории
        if (!$stat->boxStat) throw new Exception("Статистика за текущий месяц не получена.");
        //Приводим дату к нужному формату
        $stat->month = date("d.m.Y", strtotime($stat->month));
        $stat->maxDate = date("d.m.Y", strtotime($stat->maxDate));
        $forStat['currentMonth'] = (array) $stat; //Переводим объект в массив и записываем его в массив
       

        //Теперь производим рассчет статистических данных
        $forStat['incomeTotal'] += $stat -> income; //Приход за все время
        $forStat['spendingTotal'] += $stat -> spending; //Расход за все время

        //Проверяем максимальный дневной приход в текущем месяце и находим максимальный за все время и его дату
        if ($forStat['maxTotal'] < $stat -> max){
            $forStat['maxTotal'] = $stat -> max; //Запоминаем новый максимум
            $forStat['maxDateTotal'] = $stat -> maxDate; 
        }

        //Находим средний доход за день за все время существования копилки
        //Сумма общего дохода известна, дата создания копилки тоже, вычисляем количество дней ее существования
        //Объект $date - текущая дата
        $old = new DateTime($forStat['dateCreateBox']); //Объект с датой создания

        //Количество дней между датами считаем так:
        //Дату создания и текущую дату считаем, значит если между датами создания и текущей прошло 0 дней считаем 1
        //а если 1 и больше то прибавляем 2
        $diff = $date->diff($old)->format("%a");
        $diff += (!$diff) ? 1 : 2;

        //Если прошло 0 дней то просто записываем всю прибыль в среднее
        if (!$diff) $avgTotal = $forStat['incomeTotal'];
        else $avgTotal = $forStat['incomeTotal'] / $diff; //Вычисляем среднее
        $forStat['avgTotal'] = ceil($avgTotal); //Записываем в массив с округлением, для остальных рассчетов оставляем дробное

        //Скорости накопления
        $forStat['speedDay'] = ceil($avgTotal); //За день
        $forStat['speedWeek'] = ceil($avgTotal * 7); //За неделю
        $forStat['speedMonth'] = ceil($avgTotal * 30); //За месяц
        $forStat['speedYear'] = ceil($avgTotal * 365); //За год

        //Изначально задаем требуемую скорость накопления 0 
        $forStat['summaCurSpeed'] = 0;
 
        $old = new DateTime($forStat['datePlanBox']); //Объект с датой, к которой нужно накопить сумму
        $diff = $date->diff($old)->format("%a");
        $diff += (!$diff) ? 1 : 2; //Количество дней между датами
        
        //Узнаем, какая сумма будет накоплена к целевой дате, если дата указана и она еще не прошла
        if ($forStat['datePlanBox'] != '0000-00-00' && $old >= $date){

            $forStat['summaCurSpeed'] = $forStat['summaBox'] + $forStat['speedDay'] * $diff;

        } else $forStat['summaCurSpeed'] = '-1'; //Сообзяем, что эту информацию выводить не надо
       
        //К какой дате будет накоплена целевая сумма при текущей скорости
        //Сумма должна быть не 0 и еще не быть накоплена
        if ($forStat['summaPlanBox' ] > 0 && $forStat['summaPlanBox'] > $forStat['summaBox']) {  

            $diffSumm = $forStat['summaPlanBox'] - $forStat['summaBox']; //Осталось накопить
            //Сколько еще дней нужно при средней скорости (с защитой от деления на 0)
            $daysNeed = ($forStat['speedDay'] != 0) ? (ceil($diffSumm / $forStat['speedDay'])) : 0; 
            $nextDate = $date; //Копируем объект текущей даты, чтоб сохранить его
            //Добавляем дни к текущей дате, чтоб узнать к какой дате будет накоплена нужная сумма
            $nextDate -> modify('+' . $daysNeed . 'day'); 
            $forStat['dataCurSpeed'] = $nextDate -> format('d.m.Y'); //Записываем дату в массив

        } else $forStat['dataCurSpeed'] = '0000-00-00'; //Сообзяем, что эту информацию выводить не надо

        //Узнаем требуемую скорость накопления для выполнения плана
        //Только в том случае, когда планы по дате и сумме есть, не просрочены и не выполнены
        if ($forStat['summaCurSpeed'] != '-1' && $forStat['dataCurSpeed'] != '0000-00-00')
        $forStat['requiredSpeed'] = ceil($diffSumm / $diff); //Какая скорость нужна
        else $forStat['requiredSpeed'] = '-1'; //Сообзяем, что эту информацию выводить не надо
        
        //Приводим даты к нужному формату 
        $forStat['datePlanBox'] = date("d.m.Y", strtotime($forStat['datePlanBox']));
        $forStat['dateCreateBox'] = date("d.m.Y", strtotime($forStat['dateCreateBox'])); 



        // Подгружаем и активируем авто-загрузчик Twig-а
        require_once 'vendor/autoload.php';
        Twig_Autoloader::register();

        // указывае где хранятся шаблоны
        $loader = new Twig_Loader_Filesystem('templates');

        // Инициализируем Twig
        $twig = new Twig_Environment($loader);

        // Подгружаем шаблон
        $template = $twig->loadTemplate('statistics.twig');

        // Передаём в шаблон переменные и значения
        // Выводим сформированное содержание
        echo $template->render(array ('data' => $forStat));

        die;

    } catch (Exception $e) {
        //echo ($e);
        //При ошибках ничего не делаем, просто пропускаем на вывод главного экрана
        //die('-1');
    }
    die;
}

?>