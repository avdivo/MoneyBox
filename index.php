<?php
/* Программа MoneyBox 12.02.2021 */
/* Начало использования 01.04.2021 */

// Установка внутренней кодировки в UTF-8
mb_internal_encoding("UTF-8");
//Для выполнения кросс-доменных запросов из AJAX
header('Access-Control-Allow-Origin: *');

$who = 1; //Номер текущего пользователя

//Переменные для подключения к БД
include ("connect1.php");

//Аутентификация
include ("pass.php");

//Новая копилка / Редактирование копилки------------------------------------------------------------------
include ("box_edit.php");

//Возврат долга (AJAX) ----------------------------------------------------------------------------
include ("repay.php");

//Взнос, Перевод, Изъятие, Долг -------------------------------------------------------------------
include ("transfer.php");

//История ---------------------------------------------------------------------------------------------
include ("history.php");

//Статистика  -------------------------------------------------------------------
include ("statistics.php");

//Автоматический сбор данных из АСЦ -------------------------------------------------------------
include ("auto_send.php");

//Команды из Telegram и по таймеру (CRON) -------------------------------------------------------------
include ("commands.php");


if ($who == 4 || $who == 3) die; //Роботам дальше делать нечего


//--------------------------------- ФУНКЦИИ --------------------------------------------

//Выполняет одиночные команды перевода или дачи в долг от autoSend и Телеграмм
//Принимаются команды формата send 1-2=1000 и debt 1-2=1000
//Принимает команду и сведения для истории откуда перевод, возвращяет результат выполнения 1 или 0
function oneCommand($command, $addition) {

    //Для записи операций в БД нужно создать объект который это выполнит
    //Подготовка транзакции
    $trans = new Transactions(); //Создаем объект транзакций

    //Готовим массив для перевода пользовательских номеров копилок в их id
    //Подключаемся к БД
    global $link;

    //Запрос к БД на получение списка id и nn копилок
    $query ="SELECT `id`, `nn` FROM `box`";
    $result = mysqli_query($link, $query);
    if (!$result) return ('-1');

    $arrayNN = []; //Массив, куда будем записывать названия копилок
    
    //Запишем id копилок в массив
        while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение
            $arrayNN[$row["nn"]] = $row["id"];    //Заносим в массив nn -> id
        }
    
    //Разделяем команду, находим Источник, приемник и сумму перевода, а так же выясняем долг это или нет
    $found = preg_split('/\s|-|=/', $command); //Массив вида Array ( [0] => send [1] => 1 [2] => 2 [3] => 6000 )

    $debt = 0; //Это не в долг
    if ($found[0] == 'debt') $debt = 1; //Это в долг
    $trans->summaTrans = $found[3]; //Сумма перевода
    $trans->sourceTrans = $arrayNN[$found[1]]; //Источник перевода (его id)
    $trans->receiverTrans = $arrayNN[$found[2]]; //Приемник перевода  (его id)
    $trans->additionTrans = $addition; //Номер заказа или пометка
    if ($who == 4) $trans->fromTrans = 'autoSend'; //Перевод делает autoSend
    if ($who == 3) $trans->fromTrans = 'TelegramBot'; //Перевод делает Телеграмм

    //Выясняем код операции:
    //1. Взнос - источник Бюджет, 2. Перевод - оба Копилки, 3. Изъятие - приемник Бюджет, 4. Долг - $debt = true
    $trans->eventTrans = '2';
    if ($trans->sourceTrans == '1') $trans->eventTrans = '1';
    if ($trans->receiverTrans == '1') $trans->eventTrans = '3';

    //Подготовка запросов к БД
    if ($trans->boxChangeTrans() == '-1') return ('-1'); //Меняем суммы в копилках. Выходим с ошибкой если не хватает денег
    //Если это перевод в долг то делаем долговую запись
    if ($debt) {
        $trans->eventTrans = '4'; //Код операции - Долг
        if ($trans->debtChangeTrans() == '-1')  return ('-1'); //Делаем долговую запись. Выходим если ошибка
    }

    //Выполняем транзакцию с записью истории (true). Выходим если ошибка
    if ($trans->saveTrans(true) == '-1') return ('-1');

}

//Отправка сообщения в Телеграмм
//Ту же информацию пишем в файл toTelegram.txt
function toTbot($mesage) {

    global $who;

    $postdata = http_build_query(
        array(
            'user' => $who,
            'from' => 'MoneyBox',
            'mes' => $mesage
        )
    );

    $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postdata
        )
    );

    chdir('/home/users/a/avdivo/domains/avdivo.myjino.ru/moneybox/');
    $file = 'toTelegram.txt'; //Файл для записи
    $date = new DateTime();
    $data = '\n '. $date->format('d:m:Y H:i:s') . ' Для (' . $who . ')\n' . $mesage;
    // Пишем содержимое в файл,
    // используя флаг FILE_APPEND для дописывания содержимого в конец файла
    file_put_contents($file, $data, FILE_APPEND);
    
    $context  = stream_context_create($opts);

    //Отправляем данные в telegram bot
    $file = file_get_contents('https://remontnoutbukoff.ru/telegram/tbot.php', false, $context);

}
//------------------------------- ГЛАВНЫЙ ЭКРАН ------------------------------------------


//Создаем массив с копилками для вывода на экран через шаблонизатор Twig
//Для каждой копилки создаем объект, переводим массив и записываем его в создаваемый массив с ключом - номер копилки

//Считываем номера всех копилок кроме 1 - Бюджет
$query ="SELECT `id` FROM box WHERE `id`!='1' ORDER BY `id`";
$result = mysqli_query($link, $query)
or die("Ошибка чтения списка копилок. " . mysqli_error($link)); 

$toTwig = []; //Массив для передачи в шаблонизатор

while ($row = mysqli_fetch_row($result)) {       //Перебираем объект запроса
    $box = new MoneyBox($row[0]); //Создаем объект копилки с очередным номером
    $toTwig [$row[0]] = (array) $box; //Преобразовываем объект в массив и вкладываем в массив для вывода
    unset ($box); //Уничтожим объект перед пересозданием
}

//Считываем отдельно Бюджет, он имеет номер 1, должен быть последним в списке
$query ="SELECT `id` FROM box WHERE `id`='1' ORDER BY `id`";
$result = mysqli_query($link, $query)
or die("Ошибка чтения списка копилок. " . mysqli_error($link)); 

$row = mysqli_fetch_row($result); //Преобразуем объект запроса
unset ($box); //Уничтожим объект перед пересозданием
$box = new MoneyBox($row[0]); //Создаем объект копилки с номером 1
$toTwig [$row[0]] = (array) $box; //Преобразовываем объект в массив и вкладываем в массив для вывода

// Подгружаем и активируем авто-загрузчик Twig-а
require_once 'vendor/autoload.php';
Twig_Autoloader::register();

try {
    // указывае где хранятся шаблоны
    $loader = new Twig_Loader_Filesystem('templates');

    // Инициализируем Twig
    $twig = new Twig_Environment($loader);

    // Подгружаем шаблон
    $template = $twig->loadTemplate('boxs.twig');

    // Передаём в шаблон переменные и значения
    // Выводим сформированное содержание
    echo $template->render(array ('data' => $toTwig));

} catch (Exception $e) {
    die ('Ошибка шаблонизатора Twig: ' . $e->getMessage());
}




//-----------------------------------------------------------------------------------
//Класс MoneyBox для работы с копилками в БД

class MoneyBox
{
    public $idBox; //Уникалный ключ копилки
    public $nnBox; //Номер копилки задаваемый пользователем
    public $nameBox; //Название копилки
    public $dateCreateBox; //Дата создвния копилки
    public $summaBox; //Сколько денег в копилке
    public $summaPlanBox; //Сумма, которую планируется накопить
    public $datePlanBox; //Дата, к которой нужно накопить указанную сумму 
    public $statBox; //Статистика копилки в виде HTML сразу готовая для вставки на страницу
    public $debtsBox = []; //Двумерный массив с долгами копилки полученный из БД
        //Первый ключ - номер долга (символом)
        //Второй - ключи “idDebt”, “debtorDebtNumber”, “debtorDebtName”, “loanerDebtNumber”, “loanerDebtName”, “summa”

    //Конструктор класса, читаем из БД копилку, с номером полученным в аргументе
    function __construct($id)
    {
        $this->idBox = $id; //id по которому ищем копилку в базе данных сразу присваеваем свойству idBox

        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {

                //Считываем параметры копилки с заданным id
                $query ="SELECT `nn`, `name`, `date_create`, `summa`, `summa_plan`, `date_plan` FROM `box` WHERE `id`=$id";
                $result = mysqli_query($link, $query); 
                if (!$result) throw new Exception("Не удалось прочитать копилку из базы данных."); 	

                //Если нет строки, значит нет копилки с заданным id, сообщяем об этом
                if ($result->num_rows == 0) throw new Exception("Нет копилки с заданным идентификатором.");

                //Получаем массив из строки
                $row = mysqli_fetch_array($result);

                $this->nnBox = $row[0]; //Номер копилки задаваемый пользователем
                $this->nameBox = $row[1]; //Название копилки
                $this->dateCreateBox = $row[2]; //Дата создвния копилки
                $this->summaBox = $row[3]; //Сколько денег в копилке
                $this->summaPlanBox = $row[4]; //Сумма, которую планируется накопить
                $this->datePlanBox = $row[5]; //Дата, к которой нужно накопить указанную сумму
            
                //Создаем объект с долгами копилки
                $dBox = new Debts($id);

                //Получаем статистику долгов копилки
                $this->statBox = $dBox->getStatDebts();
                
                //Получаем массив с долгами копилки
                $this->debtsBox = $dBox->getDebts();

            } catch (Exception $e) {
                echo $e->getMessage();
                die();
            }
    }

// -------------------------- МЕТОДЫ =========================================

    //Метод возвращает сумму в копилке
    //Этот метод статический и может быть вызван без инициализации объекта следующим способом MoneyBox::getSummBox($id)
    public static function getSummBox($id) {

        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {

            //Получаем сумму в копилке
            $query ="SELECT `summa` FROM `box` WHERE `id`=$id";
            $result = mysqli_query($link, $query); 
            if (!$result) throw new Exception("Не удалось прочитать сумму в копилке из базы данных."); 	

            //Если нет строки, значит нет копилки с заданным id, сообщяем об этом
            if ($result->num_rows == 0) throw new Exception("Нет копилки с заданным идентификатором.");

            //Получаем массив из строки
            $row = mysqli_fetch_array($result);

            return $row[0]; //Возращяется лишь одно значение

        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

    }
    
    //Метод возвращает массив с номерами (id) и названиями копилок key->value
    //Этот метод статический и может быть вызван без инициализации объекта следующим способом MoneyBox::getNamesBox()
    public static function getNamesBox() {

        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {
            
            //Запрос к БД на получение списка id и названий копилок
            $query ="SELECT `id`, `name` FROM `box`";
            $result = mysqli_query($link, $query); 
            
            $arrayNames = []; //Массив, куда будем записывать названия копилок

            //Запишем названия копилок в массив
            if ($result) {
                while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка долг
                    $arrayNames[$row["id"]] = $row["name"];    //Заносим в массив ключ -> название
                }
            }

            return $arrayNames;

        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

    }


    //Метод возвращает массив с id копилок по пользователяскому номеру key->value
    //Этот метод статический и может быть вызван без инициализации объекта следующим способом MoneyBox::getIdBox()
    public static function getIdBox() {

        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {
            
            //Запрос к БД на получение списка id и названий копилок
            $query ="SELECT `id`, `nn` FROM `box`";
            $result = mysqli_query($link, $query); 
            
            $arrayNames = []; //Массив, куда будем записывать названия копилок

            //Запишем названия копилок в массив
            if ($result) {
                while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка долг
                    $arrayNames[$row["nn"]] = $row["id"];    //Заносим в массив ключ -> название
                }
            }

            return $arrayNames;

        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

    }

    //Метод возвращает двумерный массив с ключами (id) копилок с массивом внутри каждого
    //nn - пользовательский номер, name - название копилки, summa - сумма в копилке
    //Этот метод статический и может быть вызван без инициализации объекта следующим способом MoneyBox::getNamesBox()
    public static function getNumNameSum() {

        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {
            
            //Запрос к БД на получение списка id и названий копилок
            $query ="SELECT `id`, `nn`, `name` FROM `box`";
            $result = mysqli_query($link, $query); 
            
            $arrayNames = []; //Массив, куда будем записывать названия копилок

            //Запишем названия копилок в массив
            if ($result) {
                while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка долг
                    $summa = MoneyBox::getSummBox($row['id']); //Сумма в копилке
                    $fields = array('nn'=>$row['nn'], 'name'=>$row['name'], 'summa'=>$summa);
                    
                    $arrayNames[$row['id']] = $fields;    //Заносим в массив другой массив
                }
            }

            return $arrayNames;

        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

    }


}







//--------------------------------------------------------------------------------------
//Класс для работы с долгами копилки 
class Debts
{
    //Свойства
    public $idBoxDebts; //Номер копилки
    public $inDebts; //Долги входящие, сумма (этой копилке должны)
    public $outDebts; //Долги исходящие, сумма(эта копилке должна)
    public $summDebts; //Сумма в копилке с учетом долгов: = summaBox + debtsInBox - debtsOutBox

    //Конструктор класса
    function __construct($id)
    {
        $this->idBoxDebts = $id; //Номер копилки получаем в аргументе
        $this->inDebts = $this->getInDebts(); //Получаем входящие долги (этой копилке должны)
        $this->outDebts = $this->getOutDebts(); //Получаем исходящие долги (эта копилке должна)
        $this->summDebts = $this->getSummDebts(); //Получаем сумму в копилке с учетом долгов
    }

    // -------------------------- МЕТОДЫ =========================================

    //Метод возвращает двумерный массив с долгами копилки (исходящими)
    //Первый ключ - номер долга (символом)
    //Второй - ключи “idDebt”, “debtorDebtNumber”, “debtorDebtName”, “loanerDebtNumber”, “loanerDebtName”, “summa”, "dateDebt"
    /*Вид массива: Array ([key1] => Array ([key1] => “value”, [key2] => “value”, [key3] => “value”) 
                        [key2] => Array ([key1] => “value”, [key2] => “value”, [key3] => “value”)) */
    function getDebts() {

        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {
            
            //Считываем долги из БД для создания массива долгов
            $query = "SELECT debts.`id` AS `idDeb`, debts.`debtor` AS `debtorDebtNumber`,
            b1.`name` AS `debtorDebtName`, debts.`loaner` AS `loanerDebtNumber`,
            b2.`name` AS `loanerDebtName`, debts.`summa`, DATE_FORMAT(debts.`date`, '%d.%m.%Y %H:%i') AS `dateDebt`
            FROM debts 
            JOIN `box` AS b1 ON debts.`debtor` = b1.`id` JOIN `box` AS b2 ON debts.`loaner` = b2.`id`
            WHERE debts.`debtor` = $this->idBoxDebts
            ORDER BY `idDeb`";

            $result = mysqli_query($link, $query); 
            
            $arrayDebts = []; //Массив, куда будем записывать долги

            //Если есть долги запишем их в двумерный массив
            if ($result) {
                while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка долг
                    $arrayDebts[$row["idDeb"]] = $row;    //Заносим в массив ключ -> полученный массив
                }
            }

            return $arrayDebts;

        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

    }

    //Метод возвращает двумерный массив с долгами копилки (входящими и исходящими)
    //Первый ключ - номер долга (символом)
    //Второй - ключи “idDebt”, “debtorDebtNumber”, “debtorDebtName”, “loanerDebtNumber”, “loanerDebtName”, “summaDebt”, “dateDebt”, 
    /*Вид массива: Array ([key1] => Array ([key1] => “value”, [key2] => “value”, [key3] => “value”) 
    [key2] => Array ([key1] => “value”, [key2] => “value”, [key3] => “value”)) */
    function getOneBoxDebts() {

        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {
            
            //Считываем долги из БД для создания массива долгов
            $query = "SELECT debts.`id` AS `idDeb`, debts.`debtor` AS `debtorDebtNumber`,
            b1.`name` AS `debtorDebtName`, debts.`loaner` AS `loanerDebtNumber`,
            b2.`name` AS `loanerDebtName`, debts.`summa` AS `summaDebt`, DATE_FORMAT(debts.`date`, '%d.%m.%Y %H:%i') AS `dateDebt`
            FROM debts 
            JOIN `box` AS b1 ON debts.`debtor` = b1.`id` JOIN `box` AS b2 ON debts.`loaner` = b2.`id`
            WHERE debts.`debtor` = $this->idBoxDebts OR  debts.`loaner` = $this->idBoxDebts
            ORDER BY `idDeb`";

            $result = mysqli_query($link, $query); 
            
            $arrayDebts = []; //Массив, куда будем записывать долги

            //Если есть долги запишем их в двумерный массив
            if ($result) {
                while ($row = $result->fetch_assoc()) {       //Выбираем массив ключ -> значение, каждая строка долг
                    $arrayDebts[$row["idDeb"]] = $row;    //Заносим в массив ключ -> полученный массив
                }
            }

            return $arrayDebts;

        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

    }
                    
    //Метод возвращает входящие долги (этой копилке должны)
    function getInDebts() {

        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {

            //Рассчитываем входящие долги (этой копилке должны)
            $query ="SELECT SUM(`summa`) FROM `debts` WHERE `loaner` = $this->idBoxDebts";
            $result = mysqli_query($link, $query); 
            if (!$result) throw new Exception("Не удалось получить сумму долгов копилке.");
            //Получаем первую запись из массива присваиваетм ее свойству
            $row = mysqli_fetch_array($result);
            $ret = $row[0]; 
            if ($ret == '') $ret = 0;
            return $ret;

        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }
    }


    //Метод возвращает исходящие долги (эта копилке должна)
    function getOutDebts() {
        
        //Подключаемся к БД
        global $link;

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {

            //Рассчитываем исходящие долги (эта копилке должна)
            $query ="SELECT SUM(`summa`) FROM `debts` WHERE `debtor` = $this->idBoxDebts";
            $result = mysqli_query($link, $query); 
            if (!$result) throw new Exception("Не удалось получить сумму долгов копилки.");
            //Получаем первую запись из массива присваиваетм ее свойству
            $row = mysqli_fetch_array($result);
            $ret = $row[0]; 
            if ($ret == '') $ret = 0;
            return $ret;

        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

    }

    //Метод возвращает сумму в копилке с учетом долгов: = summaBox + debtsInBox - debtsOutBox
    //<tp параметров, только для созданного объекта
    function getSummDebts() {

            $summa = MoneyBox::getSummBox($this->idBoxDebts);
            return $summa + $this->inDebts - $this->outDebts;

    }

    //Метод возвращяет готовый к выводу на экран блок статистики копилки
    //Статистика подготавливается сразу в виде HTML для вставки на страницу
    function getStatDebts() {
        return "
        Сумма долгов в копилку: $this->inDebts <br>
        Сумма долгов из копилки: $this->outDebts <br>
        Сумма в копилке с учетом долгов: $this->summDebts"; 
    }



}

//--------------------------------------------------------------------------------------
//Класс для работы с долгом Создание/Удаление/Редактирование 
class Debt
{
    //Свойства
    public $idDebt; //Номер долга
    public $summaDebt; //сумма долга
    public $debtorNumberDebt; //номер должника
    public $debtorNameDebt; //название должника
    public $debtorCashDebt; //Сколько всего денег в копилке должнике
	public $loanerNumberDebt; //Номер кредитора
	public $loanerNameDebt; //название копилки кредитора
    public $loanerCashDebt; //Сколько всего денег в копилке кредитора
    public $dateDebt; //Сколько всего денег в копилке кредитора

    //Конструктор класса. Может быть создан без указания долга. Тогда свойства остаются пустыми
    function __construct($id = '0')
    {
        //Подключаемся к БД
        global $link;

        if ($id > 0) {
            //При подключении к БД могут возникнуть ошибки, отловим их
            try {

                //Запрос к БД на получение полной информации о долге (по списку свойств класса)
                $query ="SELECT debts.`id` AS `idDebt`, debts.`summa` AS `summaDebt`, debts.`debtor` AS `debtorNumberDebt`,
                b1.`name` AS `debtorNameDebt`, b1.`summa` AS `debtorCashDebt`, debts.`loaner` AS `loanerNumberDebt`,
                b2.`name` AS `loanerNameDebt`, b2.`summa` AS `loanerCashDebt`, DATE_FORMAT(debts.`date`, '%d.%m.%Y %H:%i') AS `dateDebt`
                FROM debts 
                JOIN `box` AS b1 ON debts.`debtor` = b1.`id` JOIN `box` AS b2 ON debts.`loaner` = b2.`id`
                WHERE debts.`id` = $id";

                $result = mysqli_query($link, $query); 
                if (!$result) throw new Exception("Не удалось получить информацию о долге.");

                //Выбираем массив ключ -> значение, названия ключей соответствуют свойствам
                $row = $result->fetch_assoc();

                if ($row['idDebt'] == '') throw new Exception("Нет долга с таким номером.");

                foreach ($row as $key => $value) {
                    $this->$key = $value; //Присваиваем всем свойствам значения, их имена совпадают с ключами массива
                }


            } catch (Exception $e) {
                //echo ($e); //возращяем -1 при любых ошибках с долгом
                die ("-1");
            }
        }

    }
    
    // -------------------------- МЕТОДЫ =========================================
	
    //Обновляем долг или удаляем
    //По умолчанию долг редактируется, но если передан параметр true - то удаляется
    //Удаляется так же, если сумма долга 0
    function saveOrDelDebt($del = false) { 
        
        //Подключаемся к БД
        global $link;
        global $who; //Номер текущего пользователя
        
        if ($del || $this->summaDebt == 0){ //Удаляем долг
            
            $query = "DELETE FROM `debts` WHERE `id` = '{$this->idDebt}'";
            $result = mysqli_query($link, $query); //Удаляем запись
            if (!$result) return ('-1'); //Если удалить не удалось возвращяем -1

        } else { //Редактируем долг
            
            $query = "UPDATE `debts` SET `debtor`='$this->debtorNumberDebt', 
            `loaner`='$this->loanerNumberDebt', `summa`='$this->summaDebt', 
            `user`='$who' WHERE `id` = '{$this->idDebt}'";
            $result = mysqli_query($link, $query); //Удаляем запись
            if (!$result) return ('-1'); //Если редактирование не удалось возвращяем -1

        }
    }
}


//--------------------------------------------------------------------------------------
//Класс выполняющий все операции с деньгами. Изменения сумм в копилках, долговых записей и истории. 
//При инициализации можно передать аргумент id истории, чтоб получить данные об этой операции

class Transactions
{
    //Свойства
    public $idTrans; //Номер записи
    public $fromTrans; //Идентификатор модуля который оставил запись, сама программа также имеет свой id (asc - для ASC CRM, box - для программы)
    public $eventTrans; //Код события из 1 - Взнос, 2 - Перевод, 3 - Изъятие, 4 - Долг, 5 - Возврат долга, 6 - Простить долг
    public $sourceTrans; //Источник, откуда перевод (идентификатор копилки, 1 - для бюджета)
    public $receiverTrans; //Приемник, куда перевод
    public $summaTrans; //Сумма перевода
    public $additionTrans; //Дополнительная информация (номер долга, номер заказа в БД ремонтов и т.д.)
    public $userTrans; //Пользователь совершивший операцию
    public $transactions = []; //Массив для накопления запросов к БД

    //Конструктор класса
    function __construct($id = '0')
    {
        //Подключаемся к БД
        global $link;

        //Если аргумент не передан, то историю читать не надо
        if (!$id) {
            $this->fromTrans = 'box'; //По умолчанию действия производит сама программа
            $this->additionTrans = ''; //По умолчанию пусто
            $this->summaTrans = '0'; //При инициализации ноль
        } else {
            
            $this->idTrans = $id;
            //При подключении к БД могут возникнуть ошибки, отловим их
            try {
                //Запрос к БД на получение записи истории (по списку свойств класса)
                $query = "SELECT `from_id` AS 'fromTrans', `event` AS 'eventTrans', `source` AS 'sourceTrans', 
                `receiver` AS 'receiverTrans', `summa` AS 'summaTrans', `addition` AS 'additionTrans', 
                `user` AS 'userTrans' 
                FROM `history` WHERE `id`=$id";

                $result = mysqli_query($link, $query); 
                if (!$result) throw new Exception("Не удалось получить запись из истории.");

                //Выбираем массив ключ -> значение, названия ключей соответствуют свойствам
                $row = $result->fetch_assoc();

                if ($row['eventTrans'] == '') throw new Exception("Нет записи в истории с таким id.");

                foreach ($row as $key => $value) {
                    $this->$key = $value; //Присваиваем всем свойствам значения, их имена совпадают с ключами массива
                }

            } catch (Exception $e) {
                //echo ($e); //возращяем -1 при любых ошибках с долгом
                die ("-1");
            }
        
        }
    }

    // -------------------------- МЕТОДЫ ---------------------------------------

    //Изменение суммы в копилках. Без аргументов, данные в свойствах. В массив записываются запросы к БД
    //$sourceTrans - источник, откуда перевод, $receiverTrans - приемник, куда перевод, summaTrans - Сумма
    //После работы записывает свойства sumSourceTrans и sumReceiverTrans
    function boxChangeTrans() { 

        //Отнимаем из источника. Для копилки Бюджет изменения не производятся
        if ($this->sourceTrans != '1') {
            if ((MoneyBox::getSummBox($this->sourceTrans)-$this->summaTrans) < 0) return '-1'; //Если денег не хватает
            $this->transactions[] = "UPDATE `box` SET `summa`=`summa`-'$this->summaTrans' WHERE `id` = '$this->sourceTrans'";
        }
        //Прибавляем в приемник. Для копилки Бюджет изменения не производятся
        if ($this->receiverTrans != '1') {
            $this->transactions[] = "UPDATE `box` SET `summa`=`summa`+'$this->summaTrans' WHERE `id` = '$this->receiverTrans'"; 
        }
        
    } //Конец метода



    //Изменение долговых записей. В массив записываются запросы к БД
    //Допустимость операций должна проверяться заранее. 
    //Если передан аргумент id долга, то это изменение долга
    //Если передан второй аргумент, переменная del = true - это удаление
    //Если id долга нет - то создание нового долга с аргументами в свойствах:
    //$sourceTrans - кредитор, откуда перевод, $receiverTrans - должник, куда перевод, summaTrans - Сумма
    //Возвращает остаток долга после операции
    function debtChangeTrans($id = '0', $del = false) { 

        global $who; //Номер текущего пользователя

        $debt = new Debt($id); //Создаем объект долга для заданного номера долга

        //Если аргумента нет, то $id = 0, значит нужно создать новую запись в таблице долга
        if ($id == '0') {
        
            //Создаем запрос для добавления долга в таблицу. Запись будет произведена транзакцией
            $this->transactions[] = "INSERT INTO `debts` SET `debtor`='$this->receiverTrans',
            `loaner`='$this->sourceTrans', `summa`='$this->summaTrans', `user`='$who' ";
            return $this->summaTrans;

        } else {
            //id долга передан, если $del = false, значит это погашение долга
            if (!$del){
                $this->sourceTrans = $debt->debtorNumberDebt; //Записываем в свойства копилку должника
                $this->receiverTrans = $debt->loanerNumberDebt; //Записываем в свойства копилку кредитора
                $this->eventTrans = '5'; //Возврат долга
                $this->additionTrans = $id; //Для истории, какой долг меняем

                $summaAfter = $debt->summaDebt - $this->summaTrans; //Уменьшаем долг на сумму, которую отдают
                if ($summaAfter < 0 ) return ('-1'); //Выходим с ошибкой если попытка вернуть сумму больше долга
                
                //Обновляется запись в случае, если долг гасится не полностью
                if ($summaAfter > 0 ) $this->transactions[] = "UPDATE `debts` SET `debtor`='$this->sourceTrans', 
                `loaner`='$this->receiverTrans', `summa`='$summaAfter', `user`='$who' WHERE `id` = $id ";
            }
            
            //Просто удаляем долговую запись
            if ($summaAfter == '0'){
                $this->transactions[] = "DELETE FROM `debts` WHERE `id` = '$id' ";
            }        
        }

        return ($summaAfter);
    } //Конец метода


    //Внесение изменений в БД
	//Выполняет запросы в транзакции, записанные в свойстве-массиве transactions, 
    //Если нужно, производит запись в историю о выполненной операции. 
    //Информацию для занесения в историю читает из своих свойств, которые должны быть предварительно 
    //заданы вызывающей программой.
    //Переданный аргумент если false - вместо записи удаляет запись истории с id в idTrans
    //Вернет номер созданной долговой записи, если она создавалась
    function saveTrans($history) { 

        //Подключаемся к БД
        global $link;
        global $who; //Номер текущего пользователя
    
        //Запрос необходимо производить транзакцией, поэтому запускаем ее
        mysqli_begin_transaction ($link);

        //Выполняем запросы в цикле, по очереди, если возникла ошибка, прекращяем и сообщяем о неудаче
        foreach ($this->transactions as $value) {
            if (!$result = mysqli_query($link, $value)) {
                mysqli_rollback ($link); //Где то ошибка, запросы не выполнены. Возвращаем -1
                return '-1';
            }
        }

        $this->transactions = []; //Очищаем массив
        $id = "?"; //Будет заменен на номер, или останется так, но тогда он не нужен

        //Если запросы прошли успешно, производим запись в Историю или удаление нужной записи
        if ($history){
            //Если Событие eventHistory = 0, то запись не производится.
            //Запись в историю отличается для некоторых событий. 
            //Нужно указать id созданной записи, при ее создании, для записи долга например
            //Поэтому узнаем id 

            if ($this->eventTrans > 0){
                $id = $this->additionTrans; //Для большинства операций он уже записан тут
                if ($this->eventTrans == '4'){
                    $result = mysqli_query($link, 'SELECT LAST_INSERT_ID()'); 
                    //Получаем первую запись из массива
                    $row = mysqli_fetch_array($result);
                    $id = $row[0]; 
                } 
                
                $value = "INSERT INTO `history` SET `from_id`='$this->fromTrans',
                `event`='$this->eventTrans', `source`='$this->sourceTrans', 
                `receiver`='$this->receiverTrans', `summa`='$this->summaTrans', `addition`='$id', 
                `user`='$who'";

                $result = mysqli_query($link, $value); //Выполняем запись
            }

        } else {  

            //Запрос на удаление записи из истории 
            $value = "DELETE FROM `history` WHERE `id` = '$this->idTrans'";

            $result = mysqli_query($link, $value); //Удаляем запись

        }

        //Выполняем или отменяем транзакцию
        if ($result) {
            if (mysqli_commit ($link)) return ($id); else return ('-1'); //Все запросы выполнены.
        } else {
            mysqli_rollback ($link); //Где то ошибка, запросы не выполнены. Возвращаем -1
            return ('-1');
        }
    
    }

    //Метод обнуляет сумму в копилке не пишет в историю
    //Этот метод статический и может быть вызван без инициализации объекта следующим способом MoneyBox::getSummBox($id)
    public static function setNullBox($id) {
        
        //Подключаемся к БД
        global $link;

        $query = "UPDATE `box` SET `summa`='0' WHERE `id` = '$id'";
        $result = mysqli_query($link, $query); //Обнуляем копилку
        if (!$result) return ('-1'); //Если редактирование не удалось возвращяем -1
        
    }

    //Метод корректирует сумму в копилке не пишет в историю
    //Принимает номер копилки и сумму (положительную или отрицательную)
    //Этот метод статический и может быть вызван без инициализации объекта следующим способом MoneyBox::getSummBox($id)
    public static function correctSummBox($id, $summa) {
        
        //Подключаемся к БД
        global $link;

        $newSumma = MoneyBox::getSummBox($id) + $summa; //Что будет в копилке
        //Проверяем, корректно ли изменение, результат не должен быть меньше 0
        if ((MoneyBox::getSummBox($id) + $summa) < 0) return ('-1');

        $query = "UPDATE `box` SET `summa` = '$newSumma'  WHERE `id` = '$id'";
        $result = mysqli_query($link, $query); //Изменяем сумму в копилке
        if (!$result) return ('-1'); //Если редактирование не удалось возвращяем -1
        return $newSumma; //Возвращяем новую сумму в копилке
    }


} 


//--------------------------------------------------------------------------------------
//Класс для работы со статистикой 
class statistics
{
    //Свойства
    public $boxStat; //Номер копилки для которой статистика
    public $id; //id записи в таблице статистики
    public $month; //Отчетный месяц и год (число не играет роли)
    public $income; //Приход за месяц
    public $spending; //Расход за месяц
    public $avg; //Среднее да месяц
    public $max; //Максимальное за месяц
	public $maxDate; //Дата максимальной прибыли
	public $debtsPlus; //Сумма взятых долгов за месяц
    public $debtsMinus; //Сумма погашенных долгов за месяц
    public $summa; // Сумма в копилке на конец месяца (или на момент расчета истории)

    //Конструктор класса. Принимает Объязательное значение номера копилки. Записывает его в boxStat
    //Не объязательное значение даты в формате yyyy-mm-dd
    //Конструктор читает из БД статистику указанного месяца, если ее нет или прочитать не удалось то
    //в свойство номера копилки boxStat записывает false.
    //Если дата не указана создается объект с пустыми свойствами.
    function __construct($box = false, $incDate = '')

    {
        //Подключаемся к БД
        global $link;

        $this->boxStat = $box; //Записываем номер копилки в свойство
        if (!$box) return; //Выходим, если не указан номер копилки

        if ( $incDate != '') { 
            //При подключении к БД могут возникнуть ошибки, отловим их
            try {
                
                //Запрос к БД на получение статистики по заданной копилке за указанный месяц
                $query ="SELECT `id`, `boxStat`, `month`, `summa`, `income`, `spending`, `avg`, `max`, `maxDate`, `debtsPlus`, `debtsMinus` 
                FROM `statistics`
                WHERE `boxStat` = '$box' AND `month` = '$incDate'";

                $result = mysqli_query($link, $query); 
                if (!$result) throw new Exception("Не удалось получить статистику.");

                //Выбираем массив ключ -> значение, названия ключей соответствуют свойствам
                $row = $result->fetch_assoc();

                if ($row['id'] == '') throw new Exception("Нет статистики для данной копилки за указанный месяц.");

                foreach ($row as $key => $value) {
                    $this->$key = $value; //Присваиваем всем свойствам значения, их имена совпадают с ключами массива
                }

            } catch (Exception $e) {
                $this->boxStat = false; //Записываем в свойство признак аварийного выхода
            }
        }

    }
    
    // -------------------------- МЕТОДЫ ------------------------------------------------
	
    //Сбор статистики из истории за месяц. 
    //Статистика собирается для копилки заданной в boxStat и принятой даты, если нет даты, то текущей
    //Дата передается в виде любого числа нужного месяца и года
    function statFromHist($dat = '') { 
        
        //Подключаемся к БД
        global $link;

        $date = ($dat == '') ? new DateTime() : new DateTime($dat); //Если дата не передена устанавливаем текущую
        $dayFirst = $date->format( 'Y-m-01' ); //Первый день отчетного месяца
        $dayLast = $date->format( 'Y-m-t' );  //Последний день отчетного месяца

        //Определяем количество дней прошедших в отчетном месяце. Если месяц прошел, то его последнее число
        //Если месяц текущий, то текущее число. Это и есть количество дней
        $nowDate = new DateTime();
        $nowYearMonth = $nowDate->format( 'Y-m' ); //Текущий год и месяц
        $yearMonth = $date->format( 'Y-m' ); //Год и месяц расчетного месяца
    
        if ($nowYearMonth == $yearMonth) {
            //Это текущий месяц текущего года, количество прошедших в нем дней равно текущему числу
            $passedDays = $nowDate->format( 'd' ); //Текущее число
        } else {
            $passedDays = $date->format( 't' );  //Последний день отчетного месяца и есть количество дней в нем
        }

        $this->month =  $dayFirst; //Отчетный месяц и год (число не играет роли)
        $this->summa =  MoneyBox::getSummBox($this->boxStat); //Сумма в копилке на момент расчета

        //При подключении к БД могут возникнуть ошибки, отловим их
        try {
    
        //Приход за месяц. Сумма поступлений в заданную копилку за указанный месяц.
        $query ="SELECT SUM(`summa`) AS 'income'
        FROM `history`
        WHERE `receiver` = '{$this->boxStat}' 
        AND `event` < 3 
        AND DATE_FORMAT(`date`, '%Y-%m-%d') BETWEEN '$dayFirst' AND '$dayLast'";

        $result = mysqli_query($link, $query); 
        //Получаем первую запись из массива присваиваетм ее свойству
        $row = mysqli_fetch_array($result);
        $this->income = ($row[0] != '') ? $row[0] : 0; 

        //Расход за месяц. Сумма расходов из заданной копилки за указанный месяц.
        $query ="SELECT SUM(`summa`) AS 'spending'
        FROM `history`
        WHERE `source` = '{$this->boxStat}' 
        AND `event` < 4 
        AND DATE_FORMAT(`date`, '%Y-%m-%d') BETWEEN '$dayFirst' AND '$dayLast'";

        $result = mysqli_query($link, $query); 
        //Получаем первую запись из массива присваиваетм ее свойству
        $row = mysqli_fetch_array($result);
        $this->spending = ($row[0] != '') ? $row[0] : 0; 

        //Среднее за месяц. Среднее среди сумм доходов за день, для заданной копилки, за указанный месяц        
        //Узнаем средний приход за день, разделив приход за месяц на количество дней прошедших в этом месяце
        $this->avg = ceil($this->income / $passedDays);

        //Максимальное за месяц и дата. Максимальное среди сумм расходов за день, для заданной копилки, за указанный месяц
        $query ="SELECT MAX(`sumDay`) AS 'max', `maxDate`
        FROM (
        SELECT `sumDay`, `maxDate`
        FROM (
        SELECT SUM(`summa`) AS 'sumDay', DATE_FORMAT(`date`, '%Y-%m-%d') AS `maxDate` 
        FROM `history` 
        WHERE `receiver` = '{$this->boxStat}' AND `event` < 3 AND DATE_FORMAT(`date`, '%Y-%m-%d') 
        BETWEEN '$dayFirst' AND '$dayLast'  
        GROUP BY DATE_FORMAT(`date`, '%Y-%m-%d') 
        ORDER BY `sumDay` DESC )A )A";

        $result = mysqli_query($link, $query); 
        //Получаем первую запись из массива присваиваетм ее свойству
        $row = mysqli_fetch_array($result);
        $this->max = ($row[0] != '') ? $row[0] : 0;
        $this->maxDate = $row[1];

        //Сумма взятых долгов за месяц. Сумма взятых долгов в заданную копилку за указанный месяц
        $query ="SELECT SUM(`summa`) AS 'debtsPlus'
        FROM `history`
        WHERE `receiver` = '{$this->boxStat}' 
        AND `event` = 4 
        AND DATE_FORMAT(`date`, '%Y-%m-%d') BETWEEN '$dayFirst' AND '$dayLast'";

        $result = mysqli_query($link, $query); 
        //Получаем первую запись из массива присваиваетм ее свойству
        $row = mysqli_fetch_array($result);
        $this->debtsPlus = ($row[0] != '') ? $row[0] : 0;

        //Сумма погашенных долгов за месяц. Сумма погашенных долгов из заданной копилки за указанный месяц
        $query ="SELECT SUM(`summa`) AS 'debtsMinus'
        FROM `history`
        WHERE `source` = '{$this->boxStat}' 
        AND `event` = 5 
        AND DATE_FORMAT(`date`, '%Y-%m-%d') BETWEEN '$dayFirst' AND '$dayLast'";

        $result = mysqli_query($link, $query); 
        //Получаем первую запись из массива присваиваем ее свойству
        $row = mysqli_fetch_array($result);
        $this->debtsMinus = ($row[0] != '') ? $row[0] : 0;


        } catch (Exception $e) {
            $this->boxStat = false; //Записываем в свойство признак аварийного выхода
        }

        //echo ($dayFirst . $dayLast);
    
    }

    //-------------------------------------------------------------------------------------------------
    //Прибыль за день берется из истории
    //Принимает id копилки и дату в формате %Y-%m-%d
    //Возвращяет 1 значение
    public static function incomeDay($box, $day) { 
    
        //Подключаемся к БД
        global $link;

        //Приход за месяц. Сумма поступлений в заданную копилку за указанный месяц.
        $query ="SELECT SUM(`summa`) AS 'income'
        FROM `history`
        WHERE `receiver` = '$box' 
        AND `event` < 3 
        AND DATE_FORMAT(`date`, '%Y-%m-%d') = '$day'";

        $result = mysqli_query($link, $query); 
        //Получаем первую запись из массива присваиваетм ее свойству
        $row = mysqli_fetch_array($result);

        return (($row[0] != '') ? $row[0] : 0);

    }
    
}


?>