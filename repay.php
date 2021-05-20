<?php

//Проверяем на вход с переданными параметрами 
if (isset($_GET["repay"]))
{
   
    //include "debts.php";
    //Возврат долга
    //Получаем параметр repay = номер долга, summa - сумма погашения
    $id = $_GET["repay"];
    $summa = $_GET["summa"];

    //Возращяем numDebt - номер долга, summaDebt - остаток долга
    //debtorNumber, loanerNumber - номера обоих копилок
    //сashDebtor, cashLoaner - остаток средств в обоих копилках
    //statDebtor, statLoaner - статистика по долгам для каждой копилки

    //Подготовка транзакции
    $debt = new Transactions(); //Создаем объект транзакций
    $debt->summaTrans = $summa; //Сумма долга

    //Метод debtChangeTrans устанавливает все свойства для дальнейшей работы с долгом, он должен вызываться первым
    //Так же он возвращает остаток долга
    $summaAfter = $debt->debtChangeTrans($id); //Запоминаем остаток долга
    if ($summaAfter == '-1') die ('-1'); //Меняем долговую запись. Выходим с ошибкой если не те суммы
    if ($debt->boxChangeTrans() == '-1') die ('-1'); //Меняем суммы в копилках. Выходим с ошибкой если не хватает денег
    if ($debt->saveTrans(true) == '-1') die ('-1'); //Выполняем транзакцию с записью истории. Выходим если ошибка

    //Подготовка ответа сервера о выполнении погашения долга
    //Суммы в копилках
    $sumSource = MoneyBox::getSummBox($debt->sourceTrans); //Сумма в источнике после операции
    $sumReceiver = MoneyBox::getSummBox($debt->receiverTrans); //Сумма в приемнике после операции
    //Получаем статистику копилок у объектов долгов
    $dBox = new Debts($debt->sourceTrans); //Создаем объект с долгами копилки должника
    $statSource = $dBox->getStatDebts(); //Получаем статистику долгов копилки должника
    $dBox = new Debts($debt->receiverTrans); //Создаем объект с долгами копилки кредитора
    $statReceiver = $dBox->getStatDebts(); //Получаем статистику долгов копилки кредитора
    //Создаем массив с параметрами для отправки клиенту
    $array = array("numDebt"=>$id,"summaDebt"=>$summaAfter, "сashDebtor"=>$sumSource,
    "cashLoaner"=>$sumReceiver, "statDebtor"=>$statSource, "statLoaner"=>$statReceiver,
    "debtorNumber"=>$debt->sourceTrans, "loanerNumber"=>$debt->receiverTrans);
    echo json_encode($array); //Преобразуем массив в JSON и возвращяем в браузер коду AJAX
    die;
}

?>
