
//------------------------------------------------------------------------------------------------------
//Глобальные переменные
//-------------------------------------------------------------------------------------------------------

    //В массив busyList считываем с сервера номера занятых копилок, после загрузки страницы записываем их 
    //в раздел Номер копилки вместо слова Занятые
    var busyList = [];

    //В массив busyNameList считываем с сервера Названия копилок
    var busyNameList = [];

    //В эти переменные записываются данные при начальной загрузке
    var numBox; //Номер копилки при загрузке 
    var namBox; //Название копилки при загрузке 
    var datBox; //Дата создания копилки при загрузке 
    var sumBox; //Сумма в копилке при загрузке 
    var sumPlan; //Сумма по плану которую накопить
    var datPlan; //Дата по плану к которой накопить




    /*Создаем функцию обработчик AJAX запроса
    Функция получает массив пар nn-название занятых номеров названий копилок посредством JSON*/
    var Handler = function(Request)
    {		
        var result = Request.responseText;

        if (array == '-1'){
            alert ('Ошибка подготовки формы');
            document.location='index.php'; //Уходим на главную страницу
        }

        var array = JSON.parse(result); //Переводим из JSON в массив

        //Перебираем массив и делим на 2 в один ключи в другой названия
        for (var key in array) {
            //Перебираем объекты записываем ключи и значения в разные массивы
            busyList.push(key);
            busyNameList.push(array[key]);
        }

        //При редактировании удаляем из массива элемент с номером редактируемой копилки
        var obj = document.getElementById('idBox');
        if (obj.value != '')  busyList.splice(busyList.indexOf(numBox), 1); //splice удаляет элемент
        
        //При редактировании удаляем из массива элемент с названием редактируемой копилки
        var obj = document.getElementById('idBox');
        if (obj.value != '')  busyNameList.splice(busyNameList.indexOf(namBox), 1); //splice удаляет элемент
    }

    /*Записывает номера в строку для вывода, а если среди них найдется номер совпадающий с номером в поле ввода номера
    то он выделяется цвером и шрифтом
    Далее строка выводится в список Занятые*/
    function busy ()
    {
        var list = 'Занятые: ';
        var searchNum = document.getElementById('nomer').value; //Номер в поле ввода номера

        var one;
        for (var index = 0; index < busyList.length; ++index) {

            //Если очередной элемент равен введенному то при выводе окрашиваем его и увеличиваем шрифт
            one = busyList[index];
            if (one == searchNum) {
                one = "<font color='#ff0000' size='+2'>" + one + "</font>";
            }

            list = list + one + ', ';

        }

        list = list.slice(0, -2); //Удаляем 2 последних символа Запятую и пробел
        //Если цикл не выполнялся, то убираем целиком слово и впишем пустую строку
        if (index==0) list = '';

        
        document.getElementById('busyList').innerHTML = list;
    }


    /*Выводит Слово Занято или Свободно рядом с полем Название копилки
    Сверяет введенное в поле название с массивом названий копилок и выводит Занято если находит там его
    При редактировании название редактируемой копилки не считается занятым*/
    function busyName ()
    {
    
        var list = 'Свободно';

        if (busyNameList.indexOf (document.getElementById('name').value) != -1) list = 'Занято';//Поиск названия в массиве
        if (document.getElementById('name').value == '') list = '';//Ничего не пишем, если поле пустое

        document.getElementById('nameList').innerHTML = list;
    }

//--------------------------------- ВЫПОЛНЯЕТСЯ ПОСЛЕ ЗАГРУЗКИ ---------------------------------------

//Выполняется после загрузки
window.onload = function() {

    //Независимо, редактирование или новая копилка запоминаем состояния полей при загрузке
    numBox = document.getElementById('nomer').value; //Номер копилки при загрузке 
    namBox = document.getElementById('name').value; //Название копилки при загрузке 
    //Дата создания. Для редактирования минимальная та - что задана ранее, для новой - текущая

    var obj = document.getElementById('idBox');
    if (obj.value != '') document.getElementById('oper').innerHTML = 'Редактирование копилки';

    if (obj.value == '') 
    {
        //Заполняем поле даты
        var d = new Date(); //Текущие дата
        //var da = d.getFullYear() + '-' + zeroPadded(d.getMonth()+1);// + '-' + zeroPadded(d.getDate());
        document.getElementById('dateCreate').value = d.getFullYear() + '-' + zeroPadded(d.getMonth()+1) + '-' + zeroPadded(d.getDate());
    } 

    datBox = document.getElementById('dateCreate').value; //Дата создания копилки при загрузке 
    sumBox = document.getElementById('summa').value; //Сумма в копилке при загрузке 
    sumPlan = document.getElementById('targetsum').value; //Сумма по плану которую накопить
    datPlan = document.getElementById('targetdate').value; //Дата по плану к которой накопить
    //Отправляем запрос на получение и подготовку массива занятых номеров и названий
    //Синхронный вызов, чтобы массив успел определиться - false
    SendRequest("GET","index.php","box_edit=get",Handler, false); 
    
    //Вывод на страницу списка занятых номеров
    busy();
    //Вывод сообщения свободно ли имя
    busyName();

    //Ограничиваем дату создания минимальным значением, оно указано в поле ввода даты
    document.getElementById('dateCreate').min = datBox = document.getElementById('dateCreate').value; 

}

//Ведущий ноль
function zeroPadded(val) {
if (val >= 10)
    return val;
else
    return '0' + val;
};

//--------------------------------------------------
//Получение фокуса полем долгов, выделяем сумму в нем
function clickDolg (value)
{
    arrDolgi[value].sel(); //Выделяем текст в поле ввода
}

//Проверка ввода цифр в поле номера копилки
function newNomer (value)
{
    //Вывод на страницу списка занятых номеров
    busy();
}

//Проверка ввода символов в поле названия копилки
function newName (value)
{
    //Вывод на страницу Сообщения свободно ли введенное имя
    busyName();
}


//Нажатие кнопки OK: подготовка и отправка формы
function send()
{
    //Если номер копилки занят то не отправляем, но с условием, это не проверяется для 
    //редактирования, когда это номер редактируемой копилки, он ведь тоже занят, но пропускается

        if (busyList.indexOf (document.getElementById('nomer').value) != -1) //Поиск номера в массиве
        {
            alert ("Этот номер копилки занят.");
            return;
        }

        if (document.getElementById('nomer').value == '') //Номера не набран
        {
            alert ("Нужно указать номер копилки.");
            return;
        }
        
        if (document.getElementById('nameList').innerHTML != 'Свободно') //Если название занято или пустое не пропускаем
        {
            alert ("Проверьте название копилки.");
            return;
        }

        if (document.getElementById('dateCreate').value == '') //Если дата создания не заполнена не пропускаем
        {
            alert ("Проверьте дату создания копилки.");
            return;
        }

        if (document.getElementById('summa').value == '') //Если сумма не заполнена ставим туда 0
        {
            document.getElementById('summa').value = 0;
        }
    
        document.getElementById('save').submit(); //Отправка формы
}


//Удаление копилки. Если есть долги удалить нельзя
function deleteBox ()
{
    //Если у копилки долги значит это редактирование
    //У новой их не будет, значит тега details с id = dolgiAll не будет, так же как у редактируемой без долгов
    //Долги при редактировании не позволяют удалять копилку, запрещаем, если они есть
    var element = document.getElementById('dolgiAll');
    if(!element)
    {
        if (confirm('ВНИМАНИЕ! \nВы точно хотите удалить эту копилку?'))
        document.location='index.php?box_delete={{ box.idBox }}';
    } else {
        alert ('Копилку с долгами удалить нельзя.')
    }

}


//Кнопка Сброс
function clickClear ()
{
    //Возвращяем значения полей запомненные при загрузке
    document.getElementById('nomer').value = numBox; //Номер копилки при загрузке 
    document.getElementById('name').value = namBox; //Название копилки при загрузке 
    document.getElementById('dateCreate').value = datBox; //Дата создания копилки при загрузке 
    document.getElementById('summa').value = sumBox; //Сумма в копилке при загрузке 
    document.getElementById('targetsum').value = sumPlan; //Сумма по плану которую накопить
    document.getElementById('targetdate').value = datPlan; //Дата по плану к которой накопить

    //Вывод на страницу списка занятых номеров
    busy();
    
    //Вывод на страницу Сообщения свободно ли введенное имя
    busyName();

}

//-------------------------------- По кнопкам совершаются действия над долгами через AJAX---------------
//Кнопки Сохранить у долгов
//Передаем параметры debtEdit = номер долга, summa - новая сумма долга, to - в какую копилку долг
function debtSave (value)
{
    if (document.getElementById('save'+value).className == 'debtNo') return; //Если кнопка не активна - выходим
    //Собираем парамерты со страницы и готовим строку для GET запроса
    
    //Если после редактирования сумма долга стала равна 0, то удаляем долг
    if (document.getElementById('dolg'+value).value == 0) {
        //Уточняем, удалить ли долг
        if (!confirm('ВНИМАНИЕ! \nДолг будет удален. \nВы точно хотите удалить этот долг?')) return;

        //Отправка зпроса на удаление долга
        var param = 'debtDel=' + value; //Параметр для GET запроса
        SendRequest("GET","index.php", param, Handler3); //Отправляем AJAX запрос, передаем долг на удаление
        return;
    };

    var param = 'debtEdit=' + value + '&summa=' + document.getElementById('dolg'+value).value + 
    '&to=' + document.getElementById('spis'+value).value + '&this=' + document.getElementById('idBox').value;
    SendRequest("GET","index.php", param, Handler2); //Отправляем AJAX запрос, передаем отредактированный долг
    
}

/*Создаем функцию обработчик AJAX запроса
Функция получает результат изменения долга */
var Handler2 = function(Request)
{
    var result = Request.responseText; //Возвращяем результат выполнения
    //В переменной result приходит ответ номер обработанного долга
    // или -1 который сообщяет о результате выполнения запроса
    //Если запрос выполнен блокируем кнопку сохранения
    if (result != -1) 
    {
        document.getElementById('save'+result).className = 'debtNo'; //Новый класс кнопке
        
    } else alert ("Редактирование не выполнено.");

}

//Кнопки Удалить у долгов
function debtDel(value)
{
    //Уточняем, удалить ли долг
    if (!confirm('ВНИМАНИЕ! \nВы точно хотите удалить этот долг?')) return;

    //Отправка зпроса на удаление долга
    var param = 'debtDel=' + value; //Параметр для GET запроса
    SendRequest("GET","index.php", param, Handler3); //Отправляем AJAX запрос, передаем долг на удаление

}

/*Создаем функцию обработчик AJAX запроса
Функция получает результат удаления долга */
var Handler3 = function(Request)
{
    var result = Request.responseText; //Возвращяем результат выполнения
    //В переменной result приходит ответ номер обработанного долга
    // или -1 который сообщяет о результате выполнения запроса
    //Если запрос выполнен удаляем со страницы долг
    if (result != -1) 
    {
        var element = document.getElementById('dolgBox'+result); //Удаляем div блок с долгом
        element.remove();

        element = document.getElementById('dolgiAll');
        var num1Kol = element.getElementsByClassName('num1'); //Количество элементов с классом num1
        //внутри тега details. Если их нет то удаляем и сам details
        if (num1Kol.length == 0) 
        {
            element.remove(); 
        }


    } else alert ("Удаление не выполнено.");

}

//Активация кнопок сохранить при изменении из параметров
function change (value)
{
    document.getElementById('save'+value).className = 'debt'; //Новый класс кнопке 
}

// ------------------------------ AJAX -------------------------------------------------
//Функция для упрощения обмена данными с сервером. https://habr.com/ru/post/14246/
//--------------------------------------------------------------------------------------
//Пример вызова
/*
function ReadFile(filename, container)
{
//Создаем функцию обработчик
var Handler = function(Request)
{
    document.getElementById(container).innerHTML = Request.responseText;
}

//Отправляем запрос
SendRequest("GET",filename,"",Handler);

}
*/

//Создание объекта XMLHttpRequest в разных браузерах
function CreateRequest()
{
var Request = false;

if (window.XMLHttpRequest)
{
    //Gecko-совместимые браузеры, Safari, Konqueror
    Request = new XMLHttpRequest();
}
else if (window.ActiveXObject)
{
    //Internet explorer
    try
    {
         Request = new ActiveXObject("Microsoft.XMLHTTP");
    }    
    catch (CatchException)
    {
         Request = new ActiveXObject("Msxml2.XMLHTTP");
    }
}

if (!Request)
{
    alert("Невозможно создать XMLHttpRequest");
}

return Request;
} 


/*Для создания запроса к серверу мы создадим небольшую функцию,
которая будет по функциональности объединять в себе функции для GET и POST запросов.*/

/*
Функция посылки запроса к файлу на сервере
r_method  - тип запроса: GET или POST
r_path    - путь к файлу
r_args    - аргументы вида a=1&b=2&c=3...
r_handler - функция-обработчик ответа от сервера
r_syn     - true - асинхронный запрос AJAX, false - синхронный
*/
function SendRequest(r_method, r_path, r_args, r_handler, r_syn)
{
//Если переменная r_syn не определена делаем ее по умолчанию true
if (r_syn === undefined) r_syn = true;

//Создаём запрос
var Request = CreateRequest();

//Проверяем существование запроса еще раз
if (!Request)
{
    return;
}

    //Назначаем пользовательский обработчик
    Request.onreadystatechange = function()
    {
        //Если обмен данными завершен
        if (Request.readyState == 4)
        {
            if (Request.status == 200)
            {
                //Передаем управление обработчику пользователя
                r_handler(Request);
            }
            else
            {
                //Оповещаем пользователя о произошедшей ошибке
                alert ('Ошибка соединения ' + Request.status);
            }
        }
        else
        {
            //Оповещаем пользователя о загрузке
        }
    
    }

//Проверяем, если требуется сделать GET-запрос
if (r_method.toLowerCase() == "get" && r_args.length > 0)
r_path += "?" + r_args;

//Инициализируем соединение
Request.open(r_method, r_path, r_syn);
if (r_method.toLowerCase() == "post")
{
    //Если это POST-запрос
    
    //Устанавливаем заголовок
    Request.setRequestHeader("Content-Type","application/x-www-form-urlencoded; charset=utf-8");
    //Посылаем запрос
    Request.send(r_args);
}
else
{
    //Если это GET-запрос
    
    //Посылаем нуль-запрос
    Request.send(null);
}
} 

