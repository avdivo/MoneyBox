<?php
///////////////////////////////////////////////////////////////////////////////////////////////////////////
/*Получает команды для исполнения от Телеграмм бота которые вводит пользователь
Выполняет команды, возвращяет ответы
*/////////////////////////////////////////////////////////////////////////////////////////////////////////

//------------------------------------------------------------------------------------------
if (isset($_POST['from'])) {
	if ($_POST['from'] == 'tbot') { //Командв от Телеграмм бота, данные в POST
		echo (commands($_POST['mes'], 'Telegram'));
		die;
	}
	if ($_POST['from'] == 'quickSend') { //Командв от веб страницы быстрой отправки
		echo (commands($_POST['mes'], 'quickSend'));
		$page = file_get_contents('keyboard.html');
		echo $page;
		die;
	}
} 

if ($who == 4) { //Командв по таймеру (CRON)

	switch ($argv[2]) {
		case "autoSend":
			die; //autoSend уже выполнена, выходим
		break;

		case "day":
			$result = commands('day', 'timer'); //Отчет после работы с результатами за день
			toTbot($result); //Выводим отчет в Телеграмм
		break;

		case "null":
			//Обнуление копилок
			//Если указан 3 параметр в CRON после time и null то считаепм его номером копилки
			if (isset($argv[3])) $result = commands('null ' . $argv[3], 'timer');
			else $result = commands('null', 'timer'); 
			toTbot($result); //Выводим отчет в Телеграмм
			break;

		case "newMonth":
			//Переход на новый месяц. Пересчет истории в статистику
			//Обнуление копилок
			$result = include ("calc_stat.php"); //Если вернет -1 то есть какая то проблема с  пересчетом
			if ($result == '-1') toTbot('Ошибка при пересчете Истории в Статистику.'); //Выводим отчет в Телеграмм
			else {
				toTbot('Обновлена статистика. Очищена История.'); //Выводим отчет в Телеграмм
				$result = commands('null', 'timer'); //Обнуление копилок
				toTbot($result); //Выводим отчет в Телеграмм
			}
		break;


	
	}
}



//Выполнение команд
function commands($command, $addition)
{

	//Подключаемся к БД
	global $link;

	//Распознаем команду
	//Делим строку по пробелам, первый элемент - это команда, второй, если есть - параметры
	$found = preg_split('/\s/', $command);

	switch ($found[0]) {
		case "send":

			$status = oneCommand($command, $addition); //Выполняем перевод
			$status = ($status == '-1') ? 'Fail' : 'Ok'; //Какое сообщение отправить в отчет
			return "$command : $status"; //Возвращяем результат выполнения
		break;

		case "debt":

			$status = oneCommand($command, $addition); //Выполняем перевод
			$status = ($status == '-1') ? 'Fail' : 'Ok'; //Какое сообщение отправить в отчет
			return "$command : $status"; //Возвращяем результат выполнения
		break;

		case "day":
			//Отчет за день. Может быть параметр - чтсло текущего месяца
			//Дает отчет за это число, если проишло большее текущего, то за текущий день
			//Возвращяет Приход в кассу (и стало), Чистая прибыль (и стало), В копилку №10 и №11 (и стало)
			
			//Вычисляем дату
			$nowDate = new DateTime(); //Текущая дата
			$date = $nowDate->format( 'Y-m' ) . '-'; //Текущий год и месяц
			$nowDay = $nowDate->format( 'd' ) * 1; //Текущее число в виде цыфры

			//Если параметр есть формируем из него число и проверяем  чтобы был правильным
			if (isset($found[1])) {
				$day = $found[1] * 1;
				if ($day >= $nowDay || $day == 0) $day = $nowDay;
			} else $day = $nowDay; //Текущее число в виде цыфры

			//Формируем дату
			$nowDayFormat = '0' . $day;
			$nowDayFormat = $date . substr($nowDayFormat, -2); //Число с 0 спереди, если надо

			//Получаем массив с id копилок где ключи их nn (пользовательские номера)
			$nnTOid = MoneyBox::getIdBox();
			$idTOname = MoneyBox::getNamesBox(); //Названия копилок с ключами id

			//Копилки для которых делаем отчет
			$kassa = $nnTOid['1']; //Касса 
			$clear = $nnTOid['100']; //Чистая прибыль 
			$num10 = $nnTOid['10']; //№10
			$num11 = $nnTOid['11']; //№11

			//Отчет. На конце перевод строки (%0A)
			$report = 'Отчет за ' . $day . ' число %0A%0A'; 

			//Для каждой копилки делаем объект статистики на нужную дату и выбираем нужные данные
			$income = statistics::incomeDay($kassa, $nowDayFormat);
			$report .= 'Приход в ' . $idTOname[$kassa] . ' - ' . $income . '%0A';

			$income = statistics::incomeDay($clear, $nowDayFormat);
			$report .= $idTOname[$clear] . ' - ' . $income . '%0A';

			$income = statistics::incomeDay($num10, $nowDayFormat);
			$report .= 'Приход в ' . $idTOname[$num10] . ' - ' . $income . '%0A';

			$income = statistics::incomeDay($num11, $nowDayFormat);
			$report .= 'Приход в ' . $idTOname[$num11] . ' - ' . $income;

			return $report;
		break;

		case "cast":
			//Список всех копилок. Номера, названия, суммы

			$allBox = MoneyBox::getNumNameSum(); //Получаем готовый массив id - (nn, name, summa)
			
			//Отчет. На конце перевод строки (%0A)
			$report = 'Список копилок %0A%0A'; 

			//Перебираем список копилок и заносим в переменную $report отчет
			foreach ($allBox as $key => $value) {
				$report .= '(' . $value['nn'] . ') ' . $value['name'] . ' --- ' . $value['summa'] . '%0A';
			}
			
			return $report;
		break;

		case "summa":
			//Список копилок с активами, суммы в них и общая сумма

			$allBox = MoneyBox::getNumNameSum(); //Получаем готовый массив id - (nn, name, summa)
			
			//Получаем массив с id копилок где ключи их nn (пользовательские номера)
			$nnTOid = MoneyBox::getIdBox();

			$summa = 0; //Всего в копилках денег

			$cast = [
					$nnTOid['1'], 			//Касса
					$nnTOid['2'], 			//Личные
					$nnTOid['50'], 			//Дима и Сергей	
					$nnTOid['10'], 			//Операция Ы
					$nnTOid['11'], 			//Тихаряшки
					$nnTOid['15'], 			//Домашняя
					];

			//Отчет. На конце перевод строки (%0A)
			$report = 'Активы %0A%0A'; 

			//Перебираем список копилок и заносим в переменную $report отчет
			foreach ($cast as $key) {
				$report .= '(' . $allBox[$key]['nn'] . ') ' . $allBox[$key]['name'] . ' --- ' . $allBox[$key]['summa'] . '%0A';
				$summa += $allBox[$key]['summa'];
			}
			
			$report .= '%0A' . 'Сумма ' . $summa;

			return $report;
		break;

		case "null":
			//Обнуляем копилки, которые при переходе на новый месяц должны начинаться с 0
			//Суммы из этих копилок передаем в Телеграмм

			$allBox = MoneyBox::getNumNameSum(); //Получаем готовый массив id - (nn, name, summa)
			
			//Получаем массив с id копилок где ключи их nn (пользовательские номера)
			$nnTOid = MoneyBox::getIdBox();

			//Отчет. На конце перевод строки (%0A)
			$report = 'Обнуление копилок: %0A%0A'; 

			//Если в параметрах передан номер, обнуляем только указаннукю копилку
			if (empty($found[1])) {
				$cast = [
					$nnTOid['100'], 		//Чистая прибыль
					$nnTOid['3'], 			//Продукты
					$nnTOid['4'], 			//Прочее
					$nnTOid['5'], 			//Счета
					$nnTOid['20'], 			//Фирма
					$nnTOid['30'], 			//Запчасти
					];

			} else {
				$cast = [];
				if (isset($nnTOid[$found[1]])) $cast = [$nnTOid[$found[1]]]; else $report = 'Нет копилки с таким номером'; 
			}

			//Перебираем список копилок , обнуляем в них суммы методом setNullBox 
			//И заносим в переменную $report отчет
			foreach ($cast as $key) {
				if (Transactions::setNullBox($key) != '-1') {
					$report .= '(' . $allBox[$key]['nn'] . ') ' . $allBox[$key]['name'] . ' --- ' . $allBox[$key]['summa'] . ' : OK %0A';
				} else {
					$report .= '(' . $allBox[$key]['nn'] . ') ' . $allBox[$key]['name'] . ' --- ' . $allBox[$key]['summa'] . ' : fail %0A';
				}
				
			}

			return $report;
		break;

		case "addorder":
			
			//Добавление заказа в программу. Номер заказа в $found[1] посредством программы autosend
			//Готовим вызов процедуры выполнения команд из комментариев заказа и автокоманд
			//Чтение настроек, только автокоманды
			$query ="SELECT `commandAuto` FROM `auto_send`";
			$result = mysqli_query($link, $query) or die("Ошибка чтения настроек.");
			$options = mysqli_fetch_assoc($result); //Сохраняем настройки в массиве

			//Подключаемся к БД ASC и читаем заказы
			include ("connect2.php");
			
		    // Вычитываем из таблици нужный заказ
			$query ="SELECT `id`, `out_date`, `real_repair_cost`, (`real_repair_cost`)-(`parts_cost`) AS `profit` 
			FROM `workshop` WHERE `state`='8' AND `id` = '{$found[1]}'";
		
			$result = mysqli_query($linkASC, $query) or die("Ошибка подключения к базе АСЦ" . mysqli_error($linkASC));
		
			//Выполненные заказы найдены, обрабатываем их
			if(mysqli_num_rows($result))
			{
				//Передаем объект запроса к БД и команды автовыполнения для каждого заказа
				$dateLastOrder = orders($result, $options['commandAuto']);

			} else return 'Не найден заказ с таким номером';
			return 'Заказ ' . $found[1] . ' обработан.';

		break;

		case "delorder":
			
			//Удаление указанного заказа из истории. Всех переводос связанных с ним. Номер заказа в $found[1]
			//Чтение из истории номеров записей, которые нужно удалить
			//Отмена из в порядке обратном добавлению
			$query ="SELECT `id` FROM `history` WHERE `addition` = {$found[1]} ORDER BY `id` DESC";
			$result = mysqli_query($link, $query) or die("Ошибка чтения записей истории.");
			
			//Записи связанные с заказом найдены, удаляем их
			if(mysqli_num_rows($result))
			{
				//Перебираем записи истории и отменяем их процедурой cancel из history
				while ($row = $result->fetch_assoc()) {
					$status = cancel($row['id']); //Отменяем запись
					if ($status == '-1') {
						toTbot("Запись в истории {$row['id']} Не удалена"); //Вызываем функцию отправки сообщения в Telegram
					}
				}
			} else return 'Не найден заказ с таким номером';
			return 'Заказ ' . $found[1] . ' обработан.';
			//return $options['commandAuto'];

		break;

		case "update":
			//Команда обновляет заказ. Используя команды delorder и addorder удаляет и снова записывает данныне
			
			$status = commands('delorder ' . correctSummBox($id, $summa), 'Telegram'); //Удаляем заказ
			toTbot($status); //Вызываем функцию отправки сообщения в Telegram
			commands('addorder ' . $found[1], 'Telegram'); //Добавляем заказ

		break;

		case "correct":
			//Команда корректирует сумму в копилке 
			//Параметр $found[1] должен иметь вид: 2+100, 2-100. Пользовательский номер копилки и сумма 
			//прибавляется или отнимается
			
			preg_match('/^\d+/', $found[1], $box); //Получим из команды номер копилки в $box[0]
			preg_match('/[\+|-]\d+$/', $found[1], $summa); //Получим из команды сумму с + или - в $summa[0]

			//Получаем массив с id копилок где ключи их nn (пользовательские номера)
			$nnTOid = MoneyBox::getIdBox();
			unset ($nnTOid['0']); //Удаляем бюджет из массива

			//Проверяем имеется ли копилка с таким номером и если нет сообщяем об этом
			if (!isset($nnTOid[$box[0]])) return ('Нет копилки с таким номером.');
			$summa = Transactions::correctSummBox($nnTOid[$box[0]], $summa[0]); //Корректируем сумму и получаем новую
			if ($summa == '-1') return ('Коррекция не выполнена.');
			return ('Коррекция выполнена. Сумма в копилке ' . $summa);

		break;
		

	}


}
?>
