<?php
///////////////////////////////////////////////////////////////////////////////////////////////////////////
/*При входе в основную программу проводится аутентификация с помощью куки, получить которые можно на этой странице, 
если правильно указать пароль и имя пользователя.
Хэш пароля хранится в БД
Так же возможен вход для Телеграмм бота, для чего $_POST['from'] должен быть 'tbot', 
а $_POST['user'] и $_POST['pass'] содержат данные пользователя
*/////////////////////////////////////////////////////////////////////////////////////////////////////////

//------------------------------------------------------------------------------------------
$who = false; //Пользователя нет, вход не выполнен
$access = 0; //Это не пользователь и не Телеграмм бот
//Проверяем, есть ли у пользователя авторизация. Если нет, запрашиваем пароль или выходим если это bot
if (isset($_COOKIE['MoneyBox'])) $access = 1; //Куки MoneyBox существует, читаем данные из него
if (isset($_POST['from'])) if ($_POST['from'] == 'tbot') $access = 2; //Вход Телеграмм бота, данные в POST

if ($access) { //Куки MoneyBox существует или переход из Телеграмм бота
    //Проверяем есть ли такой пользователь и разрешен ли ему вход
    //Параметр куки состоит из 2 частей: id_pass
    //id Идентификатор пользователя в таблице user, pass - его пароль в MD5
	if ($access == 1) {
		$temp = explode('_', $_COOKIE['MoneyBox']); //Разбиваем строку на id и пароль в массиве temp
		$who = $temp[0];
	}
    //tbot передал данные для аутентификации в POST, берем их оттуда
	if ($access == 2) {
		$temp[1] = $_POST['pass']; //Записываем пароль
		$who = $_POST['user'];
	}

	//Читаем пароль пользователя из БД
    $query ="SELECT `pass` FROM `user` WHERE `id` = '$who'";
            $result = mysqli_query($link, $query); 
            if (!$result) die ("Не удалось получить пароль пользоветеля.");
            //Получаем первую запись из массива? это пароль
            $row = mysqli_fetch_array($result);
            $pass = $row[0]; 

	//Если пароль из куки (вторая часть после разделителя) совпадает с тем что в БД для этого пользователя
    //то в $who останется номер работающенго пользователя. 
    //Если не совпадает то запишем туда false
	if ($temp[1] != $pass) $who = false;
} 

//Лазейка для ботов
if ($argv[1] == 'timer') $who = 4; //Автоматичекская проверка новых выполненных заказов

if (!$who){
	//Войти пытался бот, пароль не подошел, сообщаем об этом и выходим, ему форма не нужна
	if ($access == 2) {
		echo ('pass_fail');
		die;
	}

	//Если пароль не пришел, выводим форму для ввода пароля
	if (isset($_POST['pas']))
	{
		$pas = $_POST['pas'];
	} 

	//Если пароль правильный и существует отправляем куки на компьютер, если нет, выводим форму запроса пароля
	if (!empty($pas)) {
		//Если пароль не пустой то и имя пользователя должно иметься, по нему будем искать пользователя в БД
		$name = $_POST['name'];
		// создать MD5 хэш пароля
		$pas = md5($pas);

			//Читаем id, имя пользователя и пароль из БД
			$query ="SELECT `id`, `name`, `pass` FROM `user` WHERE `name` = '$name'";
			$result = mysqli_query($link, $query); 
			if (!$result) die ("Не удалось данные пользоветеля.");
			//Получаем первую запись из массива 0 - id, 1 - имя, 2 - пароль (MD5)
			$row = mysqli_fetch_array($result); 
			
		//Совпадает ли присланный пароль с записанным в БД для этого пользователя
		if ($row[2] == $pas) {
			
			//Если совпадает, записываем хэш пароля в качестве куки
			$y2k = mktime(0,0,0,1,1,2022); //До 2022 года запишем куки
			$identif = $row[0] . '_' . $row[2]; //id_пароль - такой идентификатор эраним в куки
			setcookie('MoneyBox', $identif, $y2k);
			
			//Выводим страницу с переходом в программу
			echo "
				<!DOCTYPE html PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'>
				<html><head>
				
				<meta content='text/html; charset=UTF-8' http-equiv='content-type'>
				<meta name='viewport' content='width=device-width'>
				<meta http-equiv='refresh' content='2;URL=index.php'>
				<title></title>
				
				</head>
				<body>
				<br><br>
				<h1>Имя пользователя и пароль приняты</h1>
				</body>
				</html>
			";
			exit();
		}
		
		$pas = "";
	}

	if (empty($pas)) {
	//Выводим форму запроса пароля
	echo "
			<!DOCTYPE html PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'>
			<html><head>
			
			<meta content='text/html; charset=UTF-8' http-equiv='content-type'>
			<meta name='viewport' content='width=device-width'>
			
			<title></title>
			
			</head>
			<body>
			<form action='index.php' method='post'> 
				<input name='name' id='name' type='text'>
				<input name='pas' id='pas' type='password'>
				<input value='Отправить' type='submit'>
			</form>
			<br><br>
			</body>
			</html>
			";
	}

	exit();

}
?>