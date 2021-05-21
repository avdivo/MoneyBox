
//Переход на отмену операции
//Передаем в функцию номер операции в истории
function cancel (value)
{

    //Собираем парамерты (номер операции в истории) в переменной cancel и номер копилки в переменной box
    //и готовим строку для GET запроса
    var param = 'cancel=' + value + '&box=' + {{ box.idBox }};
    SendRequest("GET","index.php", param, Handler); //Отправляем AJAX запрос, передаем отредактированный долг

}

/*Создаем функцию обработчик AJAX запроса
Функция получает результат выполнения операции в виде новой ссумы в копилке и номена операции в истории. 
Так же переменная string может быть не пустой, это значит что произошло удаление операции возврата долга или 
прощения долга, в таком случае создается новый долг и новая запись истории. Старую удаляем, а из строки 
вставляем новую в начало списка.
Или -1, если отмена не удалась*/
var Handler = function(Request)
{
    var result = Request.responseText; //Возвращяем результат выполнения
    //В переменной result приходит ответ в строке JSON с ключами 
    //nomer - номер удаленной операции в истории, для удаления строки со страницы
    //summa - сумма в копилке
    // или -1 который сообщяет о ошибке в выполнении запроса
    if (result != -1) 
    {
        var mass = JSON.parse(result); //Массив в JSON
        var nomer = mass['nomer']; //Получаем номер удаленной операции в истории
        var summa = mass['summa']; //Получаем сумму в копилке
        var string = mass['string']; //Получаем строку для замены в истории
        document.getElementById("sum").innerHTML = summa.toLocaleString('ru-RU'); //Выводим на экран в денежном формате

        var element = document.getElementById('string'+nomer); //Получаем div блок строки операции для удаления
        element.remove(); //Удаляем строку
        
        if (string != 'nothing')
        {
            var div = document.getElementById('allString');
            div.innerHTML = string + div.innerHTML;
        }
        

    } else alert ("Отмена операции не выполнена.");

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
