
    //Не запоминать историю
    if ( window.history.replaceState ) {
        window.history.replaceState( null, null, window.location.href );
    }

    var display = ''; //Сумма на дисплее клавиатуры
    var send = ''; //Команда для перевода

    var keyboard = document.getElementById('keyboard'); //Клавиатура
    var icons = document.getElementById('icons'); //Иконки переводов
    icons.style.visibility = 'visible';      //Иконки переводов показать
    keyboard.style.visibility = 'hidden';  //Клавиатура скрыть

    //Вызывается при клике на кнопку на экране
    function key (value)
    {
        var key = value.id; //Получаем id кнопки
        switch (key) {
            case 'x':
                icons.style.visibility = 'visible';      //Иконки переводов показать
                keyboard.style.visibility = 'hidden';  //Клавиатура скрыть
                display = ''; //Сумма на дисплее клавиатуры
                send = ''; //Команды нет
                //Очищаем дисплей
                document.getElementById('display').innerHTML = display;
            break;
            case 'ok':
                color ('ok');
                ok();
            break;
            default:
                color (key);
                displayUpdaye (key);        
        }

    }

    //---------------------------------------------------------------------------------------------
    //Смена цвета при нажатии кнопки и щелчек
    function color (value)
    {
        var element = document.getElementById(value);
        element.style.backgroundColor = "#FF0000";
        setTimeout(colorDel, 200, element); // Запуск таймера свечения кнопки. Элемент (кнопку) передаем в параметре
    } 
    //Удаление цвера с кнопки по таймеру
    function colorDel (value)
    {
        value.style.backgroundColor = '';
    }
    //---------------------------------------------------------------------------------------------
    
    //---------------------------------------------------------------------------------------------  
    //Обновление дисплея в соответствии с пришедшим параметром
    //Цифра - добавляется в конец. 'x' - удаляет 1 символ
    function displayUpdaye (value)
    {
        if (value == 'del') {
            display = display.substring(0, display.length - 1); //Удаляем последний элемент с дисплея
            value = '';
        } else {
            if (display.length > 8) value = ''; //Ограничиваем длину строки
            if (value == '0' && display == '') value = ''; //Если нажат 0, то проверяем чтоб он не был первым
            display = display + value; //Добавляем введенную цифру на дисплей
        }
        //Обновляем дисплей
        document.getElementById('display').innerHTML = display;
    }

    //---------------------------------------------------------------------------------------------  
    //Отлавливаем нажатия клавиш на клавиатуре
    document.addEventListener('keydown', function(event){

       if (event.key == 'Enter') {
           //Нажат Ввод
           color ('ok');
           ok();
       }
       if (event.key == 'Escape') {
            //Нажат Выход
            color ('x');
            icons.style.visibility = 'visible';      //Иконки переводов показать
            keyboard.style.visibility = 'hidden';  //Клавиатура скрыть
            display = ''; //Сумма на дисплее клавиатуры
            send = ''; //Команды нет
            //Очищаем дисплей
            document.getElementById('display').innerHTML = display;
       }
       if (event.key == 'Backspace') {
           //Нажата Удаление
           color ('del');
           displayUpdaye ('del');
       }

       //Нажата ли цыфра
       var value = event.keyCode ;
       if ((value >= 48 && value <= 57) || (value >= 96 && value <= 105)) {
           //Нажата Цифра
           color (event.key);
           displayUpdaye (event.key);
       }
    });

    //---------------------------------------------------------------------------------------------  
    //Выбор перевода, открытие клавиатуры
    //В параметре получаем команду для перевода (типа send 1-2=)
    function select (value)
    {
        send = value; //Команда записана
        display = ''; //Команда для перевода
        icons.style.visibility = 'hidden';      //Иконки переводов скрыть
        keyboard.style.visibility = 'visible';  //Клавиатура показать
    }

    //---------------------------------------------------------------------------------------------  
    //Нажата кнопка Ок. Совершаем перевод
    //В Глобальной переменной send строка для перевода (типа send 1-2=)
    //В глобальной переменной display сумма, если она не 0, дополняем ею команду и отправляем на выполнение
    function ok()
    {
        if (display == '' || send == '') return; //Если нечего отправлять, то не реагируем
        //Собираем парамерты POST запроса
        document.getElementById('mes').value = send + display;
        document.getElementById('data').submit(); //Отправка формы

    }

    //---------------------------------------------------------------------------------------------  
