<?php

//Новая копилка / Редактирование копилки------------------------------------------------------------------
//Проверяем на вход с переданными параметрами GET box_edit
//Номер копилки для редактирования или 
//new - создание новой, get - для получения массива номеров и названий копилок для AJAX
if (isset($_GET["box_edit"]))
{
    //Для AJAX запроса формы копилки
    //Подготовка массивов c парами id-название копилки для формы создания/редактирования копилки
    if ($_GET["box_edit"] == 'get'){

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {
            
            //Запрос к БД на получение списка id и названий копилок
            $query ="SELECT `nn`, `name` FROM `box`";
            $result = mysqli_query($link, $query); 
            
            $arrayNames = []; //Массив, куда будем записывать названия копилок

            //Запишем названия копилок в массив
            if ($result) {
                while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка долг
                    $arrayNames[$row["nn"]] = $row["name"];    //Заносим в массив ключ -> название
                }
            }

        } catch (Exception $e) {
            die('-1');
        }

        echo json_encode($arrayNames); //Преобразуем массив в JSON и возвращяем в браузер коду AJAX
        die;        
        }
        
    //Если был запрос от формы выдали его, если нет, готовим вывод формы

    $box = []; //Параметры копилки. Ассоциативный массив.
        /*  idBox - Уникальный ключ копилки
            nnBox - Номер копилки задаваемый пользователем
            nameBox - Название копилки
            dateCreateBox - Дата создания копилки
            summaBox - Сколько денег в копилке
            summaPlanBox - Сумма, которую планируется накопить
            datePlanBox - Дата, к которой нужно накопить указанную сумму*/
    $debts = []; //Долги копилки. Двумерный массив Array (key -> Array (key -> value))
            /* Первые ключи - id долгов
            Внутренние массивы:
                “idDebt” => id долга”,
				“transName” => “Долг в” или ”Займ в”,
				“summa” => “сумма долга”
				“date” => “дата долга”
				“partnerNumber” => “номер должника или кредитора” */

    $nameBox = MoneyBox::getNamesBox(); //Копилки. Ассоциированный массив id копилки -> Название

    //Подготовка массивов для копилки с указанным номером
    if ($_GET["box_edit"] != 'new'){
    
        //Копилки
        unset ($nameBox[$_GET["box_edit"]]); //Удаляем из массива редактируемую копилку с полученным id 
        
        //Параметры копилки
        $boxArray = new MoneyBox($_GET["box_edit"]); //Создаем объект копилки с полученным номером
        $box = (array) $boxArray; //Переводим объект в массив
        unset ($box['statBox']); //Удаляем из массива ненужную информацию
        unset ($box['debtsBox']); //Удаляем из массива ненужную информацию
        $box['dateCreateBox'] = substr($box['dateCreateBox'], 0, 10); //Приводим даты к формату ГГГГ-ММ-ДД
        $box['datePlanBox'] = substr($box['datePlanBox'], 0, 10); //    обрезая время
        
        //Долги копилки
        $debtsObj = new Debts($_GET["box_edit"]); //Создаем объект долгов копилки
        $arrayDebts = $debtsObj->getOneBoxDebts(); //Запрашиавем все долги копилки в массиве

        //Приводим массив к нужному виду
        //Если есть долги запишем их в двумерный массив
        if ($arrayDebts) {
            foreach ($arrayDebts as $key => $value) {
                //Ghtj,hfp
                $tempArray = ['idDebt' => $value['idDeb'], 'summa' => $value['summaDebt'],
                            'date' => substr($value['dateDebt'], 0, 16)];
                
                //Определяем эта копилка источник долга? 
                //Если да то transName = Долг в и приемник записываем в партнер
                if ($value['debtorDebtNumber'] == $_GET["box_edit"]) {
                    $tempArray += ['partnerNumber' => $value['loanerDebtNumber'], 'transName' => 'Долг в'];
                }
                //Если же это должник
                //То transName = Займ из и источник записываем в партнер
                if ($value['loanerDebtNumber'] == $_GET["box_edit"]) {
                    $tempArray += ['partnerNumber' => $value['debtorDebtNumber'], 'transName' => 'Займ в'];
                }

                //Записываем массив с данными о долге в элемент другого массива
                $debts += [$key => $tempArray]; 

            }
        }
        


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
        $template = $twig->loadTemplate('editbox.twig');
        // Передаём в шаблон переменные и значения
        // Выводим сформированное содержание
        //echo $template->render(array ('data' => $toTwig));
        echo $template->render(array ('box' => $box, 'debts' => $debts, 'nameBox' => $nameBox));
    } catch (Exception $e) {
        die ('Ошибка шаблонизатора Twig: ' . $e->getMessage());
    }

    die;

}

//Создание или Редактирование копилки
//Получает массив от формы копилки методом POST
//Признак $_POST['box_edit'] == 'editORnew'
/*
nomer - номер копилки заданный пользователем
name - название копилки
dateCreate - дата и время создания копилки
summa - сумма в копилке
targetsum - Сумма, которую планируется накопить
targetdate - Дата, к которой нужно накопить указанную сумму
idBox - id (уникальный номер копилки, если пустой, то это создание копилки, иначе редактирование */
if ($_POST['box_edit'] == 'editORnew'){
    //Новая копилка
    if ($_POST['idBox'] == ''){
        //Проверяем правильность введенных данных. 
        //Сумма не меньше 0, nomer, name переданы и они уникальные
        if (!empty($_POST['nomer']) && !empty($_POST['name']) && $_POST['summa'] >= 0) {
            $query ="SELECT COUNT(1) FROM box WHERE `nn` = '{$_POST['nomer']}' OR `name` = '{$_POST['name']}'";
            $result = mysqli_fetch_array(mysqli_query($link, $query));
            if($result[0] == 0) //Имя и номер в БД не найдены
                {
                    //Создаем новую копилку
                    $query = "INSERT INTO `box` SET `nn` = '{$_POST['nomer']}', `name` = '{$_POST['name']}', 
                    `summa` = '{$_POST['summa']}', `summa_plan` = '{$_POST['targetsum']}', 
                    `date_plan` = '{$_POST['targetdate']}'";
                    
                    //Записываем копилку в БД
                    $result = mysqli_query($link, $query);

                }
        }
    } else {
        //Редактирование копилки
        $query = "UPDATE `box` SET `nn` = '{$_POST['nomer']}', `name` = '{$_POST['name']}', 
        `summa` = '{$_POST['summa']}', `summa_plan` = '{$_POST['targetsum']}', 
        `date_plan` = '{$_POST['targetdate']}' WHERE id = '{$_POST['idBox']}'";

        //Вносим изменения в БД
        $result = mysqli_query($link, $query);

    }
    return;
}

//Удаление копилки
//Признак box_delete содержит номер удаляемой копилки
if ($_GET["box_delete"] != ''){
    //Если у копилки есть входящие или исходящие долги ее удалить нельзя
    //Считаем количество долгов у копилки
    $query = "SELECT COUNT(1) FROM debts 
    WHERE debts.`debtor` = '{$_GET['box_delete']}' OR  debts.`loaner` = '{$_GET['box_delete']}'";
    $result = mysqli_fetch_array(mysqli_query($link, $query));
    if($result[0] == 0) //Долгов нет
        {
            $query = "DELETE FROM `box` WHERE `id` = '{$_GET['box_delete']}'";
            //Удаляем копилку из БД
            $result = mysqli_query($link, $query);
        }
    return;
}

//Редактирование долга (AJAX)
//Признак debtEdit содержит номер редактируемого долга
//summa - новая сумма долга, to - в какую копилку долг или из какой копилки, this - редактируемая копилка
//Редактирование происходит безусловно, без записи в историю
//Возвращяет номер редактируемого долга или -1 при неудаче
if ($_GET["debtEdit"] != ''){
    //Определяем источник или приемник долга редактируемая копилка
    //Меняем второго участника и сумму, затем сожраняем долг методом saveOrDelDebt()
    $debt = new Debt($_GET["debtEdit"]); //Создаем объект долга для заданного номера долга
    if ($debt == '-1') die ('-1'); //Если создать не удалось сообшаем об этом
    //Если редактируется копилка должника то меняем кредитора
    if ($debt->debtorNumberDebt == $_GET["this"]) $debt->loanerNumberDebt = $_GET["to"];
     //Если редактируется копилка кредитора то меняем должника
    if ($debt->loanerNumberDebt == $_GET["this"]) $debt->debtorNumberDebt = $_GET["to"];
    $debt->summaDebt = $_GET["summa"]; //Меняем сумму
    if ($debt->saveOrDelDebt() == '-1') die ('-1'); //Если редактирование не удалось сообшаем об этом
    echo ($_GET["debtEdit"]);
    die;
}

//Удаление долга (прощение) (AJAX) ----------------------------------------------------------------------------
//Проверяем на вход с переданным параметром debtDel - номер долга
//Dthyenm нужно номер долга
if (isset($_GET["debtDel"]))
{
    $debt = new Debt($_GET["debtDel"]); //Создаем объект долга для заданного номера долга
    if ($debt == '-1') die ('-1'); //Если создать не удалось сообшаем об этом
    //Вызываем метод удаления, если аргумент true
    if ($debt->saveOrDelDebt(true) == '-1') die ('-1'); //Если редактирование не удалось сообшаем об этом
    echo ($_GET["debtDel"]);
    die;
}

?>