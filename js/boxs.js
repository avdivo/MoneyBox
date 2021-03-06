     //------------------------------------------------------------------------------------------------------
    //Глобальные переменные
    //-------------------------------------------------------------------------------------------------------
    //Бюджет может быть разблокирован (false) или заблокирован (true)
    var budgetBlock = false;

    var zam = false; //Если включено замещение то zam содержит номер (nomer) замещенной копилки

    var arrBox = []; //Объявление массива объектов копилок

    var arrDolgi = []; //Объявление массива объектов долгов

    var unique; //Переменная который хранит номер копилки с уникальным (противоположным всем) знаком

    //Запрещение замещения когда переменная имеет значение не 0. В переменной знак на который должна переключиться копилка
    //Это нужно для проверки возможности включения желаемого знака копилки без замещения при клике на поле ввода копилки
    var zamNot = 0; 

    //---------------------------------------------------------------
    //Класс для создания объектов копилок
    class box {
        
        //Конструктор класса. Читает все данные копилки со страници и создает объект с такими свойствами
        constructor(name) {

            this.nomer = name; //Номер копилки, строчный
            this.name = document.getElementById("nam"+name).innerHTML; //Название копилки
            
            this.load(); //Установка свойств объекта znak и perevod
            var temp = document.getElementById("sum"+name).innerHTML; //Сумма в копилке читаем
            this.summa = Number(temp.split(' ').join('')); //Сумма в копилке, убираем пробелы, переводим в число


        }

        //Установка свойств объекта znak и perevod
        //Читаем Сумму перевода и знак на странице в свойства объекта
        load() {
            //Номер копилки узнаем из ее свойства nomer
            var temp = document.getElementById("butt"+this.nomer).value; //Знак читаем и записываем его как 1 - "+"", -1 - "-", 0
            this.znak = (temp.charCodeAt() == 43) ? 1 :
                    (temp.charCodeAt() == 8722) ? -1 :
                    (temp.charCodeAt() == 8194) ? 0 :'';
            this.perevod = Number(document.getElementById("box"+this.nomer).value); //Сумма перевода
        }

        //Вносим изменения свойств объекта znak и perevod на страницу
        save() {
            //Знак представлен в value как 1 - "+"", -1 - "-" или 0. Получаем UTF значение символа
            var temp = (this.znak == 1) ? "43" :
                        (this.znak == -1) ? 8722 :
                        (this.znak == 0) ? 8194 :'';
            //Номер копилки узнаем из ее свойства nomer
            //Преобразуем и записываем UTF значение символа на страницу
            document.getElementById("butt"+this.nomer).value = String.fromCharCode(temp);
            document.getElementById("box"+this.nomer).value = this.perevod;
        }

        //Очищаем свойства объекта и страницу, задаем пустые значения
        clear() {
            //Знак представлен в value как 1 - "+"", -1 - "-" или 0. Получаем UTF значение символа
            this.znak = 0;
            var temp = 8194;
            //Номер копилки узнаем из ее свойства nomer
            //Преобразуем и записываем UTF значение символа на страницу
            document.getElementById("butt"+this.nomer).value = String.fromCharCode(temp);
            this.perevod = '';
            document.getElementById("box"+this.nomer).value = this.perevod;
        }

        //Геттер (метод под видом свойства) для получения состояния блокировки поля ввода
        get read() {
            //Номер копилки узнаем из ее свойства nomer
            return document.getElementById("box"+this.nomer).readOnly; //Читаем и возвращяем состояние блокировки поля
        }
        //Сеттер (метод под видом свойства) для изменения блокировки поля ввода
        set read(value) {
            //В качестве параметра получаем состояние блокировки поля ввода и записываем его в свойство readOnly
            //Если поле переключается в активное (разблокируется для ввода) то выделяется в нем текст
            //Номер копилки узнаем из ее свойства nomer
            document.getElementById("box"+this.nomer).readOnly = value; //Записываем состояние блокировки поля
            if (!(value)) document.getElementById("box"+this.nomer).select(); //Выделяем текст в поле ввода
        }

    }

    //---------------------------------------------------------------
    //Класс для создания объектов Долгов
    class objDolgi {
        
        //Конструктор класса. Читает все долги (массив dolgi[]) со страници и создает объект с такими свойствами
        constructor(name) {
            
            this.nomer = 'dolg'+name; //Номер долга, строчный
            this.nameBox = document.getElementById(this.nomer).name; //Номер копилки, строчный
            this.firstSumm = Number(document.getElementById('dolg'+name).value); //Начальная сумма долга, строчный
            //Свойство summa (текущая сумма) реализовано через Геттер и Сеттер
        }
        
        //Выделяем сумму в поле ввода
        sel() {
            return document.getElementById(this.nomer).select(); //Выделяем текст в поле ввода
        }



        //Геттер (метод под видом свойства) для получения состояния поля ввода и проверки на превышение
        //Введенная сумма не должна превышать первоначальную сумму (которая читается при инициализации страницы)
        //И не должна превышать сумму в копилке
        //Если превышает, выдается сообщение об этом и поле очищяется
        get summ() {
            //Номер долга узнаем из ее свойства nomer, номер копилки из свойства nameBox
            var temp = Number(document.getElementById(this.nomer).value); //Cумма в поле
            if (arrBox[this.nameBox].nomer == '1') return temp; //Возвращяем сумму, проверку для 1 не выполняем
            if (this.firstSumm < temp) temp = -1; //Если введено число больше начального долга, обнуляем поле ввода
            if (arrBox[this.nameBox].summa < temp) temp = -1; //Если введено число больше суммы в копилке, обнуляем поле ввода
            if (temp == 0) document.getElementById(this.nomer).value = ''; //Очищяем поле если сумма 0
            //Если сумма в переменной очищена в результате превышения ввода очищяем поле и сообщяем об ошибке
            if (temp == -1) 
            {            
                document.getElementById(this.nomer).value = ''; //Очищяем поле если превысили набранную сумму
                alert ('Набранная сумма слишком велика.');
                temp = 0;
            }            
            return temp; //Возвращяем сумму в поле
        }

        //Сеттер (метод под видом свойства) записывает в поле полученную сумму
        set summ(value) {
            //В качестве параметра получаем сумму
            //Номер долга узнаем из ее свойства nomer
            document.getElementById(this.nomer).value = value; //Записываем в поле полученную сумму
        }

    }

    
    //------------------------------------------------------------------------
    //Выполняется после загрузки
    window.onload = function() {

        //Массив nomer - это скрытые поля в каждой копилке на странице, с их номерами
        //читаем их в массив
        var allbox = document.getElementsByName("nomer[]");
        //var arrBox = []; //Объявление массива объектов копилок
        var index;
        //Узнаем, сколько копилок на странице и перебирая создаем объект для каждой
        for (index = 0; index < allbox.length; ++index) {
            //Создаем объект читая данные со страницы
            //Записываем в ассоциированный массив с именами элементов по номерам копилок
            arrBox[allbox[index].value] = new box(allbox[index].value);
        }
        
        /*
        Номер долга служит ключом для каждого объекта долга и хранится в массиве ArrDolgi.
        Все номера долгов собираем в массив dolgi[] с авто. индексами из скрытых полей возле каждого долга
        и переписываем их в ArrDolgi в качестве ключей. Содержимое - объекты долгов.
        Свойство value этих скрытых полей совпадает с id полей содержащих сумму долга.
        Свойства Name полей с суммами это номера копилок к которым долги относятся.
        Создаем объекты для каждого долга со свойствами name (номер долга) и summa (сумма долга).
        */
        //Массив dolgi - это скрытые поля рядом с каждым долгом на странице, с их номерами
        //читаем их в массив
        var allDolgi = document.getElementsByName("dolgi[]");
        //Перебираем все долги создавая для каждого объект
        for (index = 0; index < allDolgi.length; ++index) {
            //Создаем объект читая данные со страницы
            //Записываем в ассоциированный массив с именами элементов по номерам долгов
            arrDolgi['dolg'+allDolgi[index].value] = new objDolgi(allDolgi[index].value);
        }

    }

    //--------------------------------------------------
    //Клик по знаку.
    function sign(value)
    {
        
        var budgetBlock1 = budgetBlock; //Запоминаем состояние переменной budgetBlock до изменения, для восстановления

        //Вычисляем и записываем в nextZnak знак который получится после переключения
        var nextZnak = arrBox[value].znak;
        //console.log (nextZnak + " знаков до " + kolZnak(nextZnak));
        if (++nextZnak == 2) nextZnak = -1; //Знаки "+" - 1, "-" - -1, " " - 0
        if (zamNot != 0) nextZnak = zamNot; //Если переменная zamNot содержит знак, значит переключаемся на него
        arrBox[value].znak = nextZnak; //Выполняем переключение
        arrBox[value].perevod = ''; //При переключении знака поле ввода обнуляется

        //Проверяем, было ли замещение, и нужно ли вернуть. Если замещение было, то включен zam и предыдущее переключение было уникальной
        //ныне копилки
        if ((unique == value) && (zam))
        {
            //Возврат замещения
            //localStorage — это данные, которые хранятся бессрочно и будут доступны даже после перезагрузки браузера
            //Восстанавливаем замещенную копилку и позволяем переключение
            arrBox[zam].znak = localStorage.getItem('znak'); //Возвращяем ее знак
            arrBox[zam].perevod = localStorage.getItem('perevod'); //Возвращяем ее значение перевода
            unique = zam;
            arrBox[zam].save();
            zam = false; //Замещение выключено
        }

        budget(value); //Переключаем бюджет
        
        //Сразу выполнено перекулючение, в соответствии с ним переключен бюджет
        //Далее проверяем, допустимость полученных результатов
        
            zam = false; //Замещение выключено, не важно был ли возврат, замещение хранится до следующего переключения

            //Б не может быть единственной активной копилкой, ее нельзя включать если нет других активных копилок
            //Считаем копилки с - и с +, это и есть активные 
            var plus = kolZnak("1"); //Считаем все +
            var minus = kolZnak("-1"); //Считаем все -

            //Если после переключения копилка с таким знаком стала уникальной и переключение не на 0 (не выключение копилки), запоминаем ее
            input();

            //Если не осталось Плюсов или минусов и при этом противоположный знак всего 1 т.е. всего остался 1 активный - выключаем и его
            //Если же плюсов или минусов осталось 0, а противоположных знаков больше 1, то отменяем переключение
            //После исполнения выйдет из функции
            //Может быть это первый клик по B, тогда одна копилка будет включена и это B
            if ((plus == 0) || (minus == 0))
            {
                if ((plus+minus) == 1) 
                {
                    //Находим и отключаем оставщийся, он будет уникальным
                    input();
                    arrBox[unique].clear();
                    unique = '';
                    budgetBlock = false;
                    arrBox[value].save();
                    return false; //Выключение всех
                } else if ((plus+minus) > 1)
                {
                    //Возвращяем переменные, которые могли измениться,
                    //читаем с экрана копилки, они изменились только в объектах, так что восстановятся.
                    budgetBlock = budgetBlock1;
                    arrBox[value].load();
                    arrBox['B'].load();
                    input(); //Восстанавливаем переменную unique
                    return false; //Переключение отменено
                } 

            }

            //Замещение
            //Для замещения нужно чтобы количество плюсов и количество минусов не были равны 1
            //Получается их больше, а должен быть 1 уникальный знак =1
            //При этом переключение на пустой не должно вызывать замещения
            //Не должно произойти раньше отмены, если же произойдет отмена
            if ((plus != 1) && (minus != 1) && (arrBox[value].znak != 0)) 
            {
                //Замещение

                //В случае, если нельзя включить копилку без замещения (zamNot = 1 или -1) вернем false, а все изменения отменяем
                if (zamNot != 0) 
                {
                    //Возвращяем переменные, которые могли измениться,
                    //читаем с экрана копилки, они изменились только в объектах, так что восстановятся.
                    budgetBlock = budgetBlock1;
                    arrBox[value].load();
                    arrBox['1'].load();
                    input(); //Восстанавливаем переменную unique
                    return false; //Переключение отменено
                }

                //localStorage — это данные, которые хранятся бессрочно и будут доступны даже после перезагрузки браузера
                zam = arrBox[unique].nomer; //Замещение включено, данные запомнены
                //Замещаться может только уникальный знак, а имя уникального объекта хранится в переменной unique
                //Запоминаем его знак и сумму перевода и стираем их
                localStorage.setItem('znak', arrBox[unique].znak);
                localStorage.setItem('perevod', arrBox[unique].perevod);
                arrBox[unique].clear();
                unique = value; //Уникальной стала эта копилка
            }

        arrBox[value].save();
        arrBox['1'].save();

        input(value); //Рассчитываем сумму переводов, параметр передаем любой не пустой

        return true; //Переключение произошло успешно
    }

    //Переключаем Бюджет в соответствии с внесенными изменениями
    function budget(value)
    {
        //Блокируем бюджет если переключали его, в независимости от этого разблокируем его, если он стал нейтральным
        if (value == '1') budgetBlock = true;
        //if (arrBox['1'].znak == 0) budgetBlock = false; //Блокировка может не сниматься вообще если один раз бюджет вручную установлен 

        //Не заблокированный Б всегда принимает знак которого нет ни у одной активной копилки
        if (!budgetBlock)
        {
            var plus = kolZnak("1"); //Считаем все +
            var minus = kolZnak("-1"); //Считаем все -

            //В общем подсчете знаков есть и Бюджет, его нужно изъять
            if (arrBox['1'].znak == -1) minus--;
            if (arrBox['1'].znak == 1) plus--;

            arrBox['1'].perevod = ''; //При переключении знака поле ввода обнуляется

            //Если нет плюсов , но есть минусы, то делаем Бюджет с плюсом и делаем его уникальным
            if ((plus == 0) && (minus > 0))
            {
                arrBox['1'].znak = 1;
                return;
            }

            //Если нет минусов , но есть плюсы, то делаем Бюджет с минусом и делаем его уникальным
            if ((minus == 0) && (plus > 0)) 
            {
                arrBox['1'].znak = -1;
                return;
            }

            //Остается вариант колгда есть и плюсы и минусы или нет ничего, тогда отключаем и знак Бюджета
            arrBox['1'].znak = 0;            
        }

    }

    //Функция вернет количество "+" - если получит 1 , "-" если получит -1, или не активных если получит 0
    function kolZnak(value)
    {
        var kvo = 0; //количество найденных знаков
        for (var key in arrBox) {
            //Перебираем объекты, считаем искомые знаки 
            if (arrBox[key].znak == value) {
                kvo++;
            }
        }
        return kvo;
    }



//--------------------------------------------------------------------------------------------------
    //Разрешение или запрет ввода в поле ввода и переклюяение знака копилки при щелчке по полю ввода
    //Копилка может стать активной если не была 
    //если резрешено ее переключение в + без замещения
    //Если нельзя переключить в + то пытаемся переключиться в - без замещения
    //Если переключить нельзя то и ввод не разрешен
    function inputAllowed(value)
    {
        //alert(value);
        //Если копилка нейтральная пробуем переключить ее в + или -
        //Если ввод разрешен то поле разблокируется и после потери фокуса снова блокируется
        var ok = false; //Ввод не разрешен, переключения не было

        if (arrBox[value].znak == 0) 
        {
            //arrBox[value].read = false; //Разблокировать
            //Отключаем замещение и одновременно пробуем переключить копилку в + 
            zamNot = 1;
            ok = sign(value); //Вернет true, если переключение произошло
            zamNot = -1; //Отключаем замещение и одновременно пробуем переключить копилку в -, если в + не переключился
            if (!(ok)) ok = sign(value); //Вернет true, если переключение произошло
            zamNot = 0; //Восстанавливаем нормальную работу замещения и переключения
            if (!(ok)) return; //ввод запрещен если копилку нельзя переключить из нейтральной
        }

        //Если произошло переключение без замещения (ok = true) то ввод разрешен, но еще раз проверится
        //Если копилка была активна проверим можно ли вводить в ее поле
        //Функция input с параметром (номер проверяемой копилки) вернет true, если ввод в нее разрешен и пересчитает суммы в поле уникальной копилки
        if (input(value)) arrBox[value].read = false; //Разблокируем ввод и выделяем в нем текст

    }




    //Рассчет сумм в полях ввода копилок, одновременно возвращяет false, если для переданной в параметрах копилки
    //не разрешен ввод. Или true, если разрешен.  
    //Определяет unique
    //Для 2 копилок ставит уникальной ту, для которой не осуществляется ввод, это не влияет на работу программы
    //-------------------------
    //Используется для выставления уникальной копилки устанавливает глобальную переменную unique (параметр не важен)
    //Если передан параметр то вычислит и запишет поле суммы в уникальную копилку
    //Если параметр соответствует номеру копилки, то вернет true или false, можно ли производить в поле этой копилки ввод
    function input(value)
    {
        var sumPlus = 0; //Сумма копилок с положительным знаком
        var sumMinus = 0; //Сумма копилок с отрицательным знаком
        var kvoP = 0; //количество найденных знаков +
        var kvoM = 0; //количество найденных знаков -
        var plus = ''; //Номер копилки со знаком +, если она одна, то номер и будет уникальным
        var minus = ''; //Номер копилки со знаком -, если она одна, то номер и будет уникальным

        for (var key in arrBox) {
            
            //Перебираем объекты, находим искомые знаки и складываем их поля в соответствии со знаками, заодно считаем количество знаков
            // и записываем в переменную номера копилок, если копилка с определенным знаком одна, то она и будет в переменной
            if (arrBox[key].znak == 1) 
            {
                sumPlus = sumPlus + Number(arrBox[key].perevod); //Сумма плюсов, предварительно переводим в число
                kvoP++; //Количество плюсов
                plus = arrBox[key].nomer; //Запись номера копилки
            }
            if (arrBox[key].znak == -1) 
            {
                sumMinus = sumMinus + Number(arrBox[key].perevod); //Сумма минусов, предварительно переводим в число
                kvoM++; //Количество минусов
                minus = arrBox[key].nomer; //Запись номера копилки
            }
        }

        //Какой знак один, номер той копилки и записываем уникальным если их нет, то оставляем старый
        unique = (kvoP == 1) ? plus : (kvoM == 1) ? minus : unique;    

        //В unique хранится номер копилки которая уникальная (то есть она с таким знаком одна)
        //Если количество знаков по одному, то ввод разрешен в любое поле
        //Сумма же записывается в поле копилки с уникальным знаком
        //Поэтому делаем уникальной ту копилку из 2-х в которую ввод не осуществляется
        if ((kvoP == 1) && (kvoM == 1))
        {
            //Если есть переменная value (переданный параметр), 
            //то это номер копилки для ввода, значит она не должна быть уникальной
            if (minus == value) unique = plus;
            if (plus == value) unique = minus;
        }

        //Записываем в поле копилки с уникальным знаком сумму если параметр был передан и активные копилки есть
        if (((kvoP+kvoM) > 0) && (value))
        {
            if (!(sumPlus)) sumPlus = ''; //Если сумма нулевая, запишем пустую строку
            if (!(sumMinus)) sumMinus = ''; //Если сумма нулевая, запишем пустую строку

            //Если знак результирующей копилки +, то записываем сумму минусов
            if (arrBox[unique].znak == 1) 
            {
                arrBox[unique].perevod = sumMinus; 
                arrBox[value].save(); //Показываем на экране
                arrBox[unique].save();
            }
            //Если знак результирующей копилки - (из нее будут списываться сперства), то записываем сумму плюсов
            //Но предварительно проверяем, чтоб списываемая сумма не превышала общей содержащейся в копилке
            //При превышении отменяем ввод последней цифры
            if (arrBox[unique].znak == -1)
            {
                //Чтоб при сообщении была видна рассчетная превышающая сумма покажем ее, потом вернем ту что была
                var temp = arrBox[unique].perevod;
                arrBox[unique].perevod = sumPlus; 
                arrBox[unique].save();

                //Проверка превышения суммы не распространяется на Бюджет (исключаем его)
                if ((Number(arrBox[unique].summa) < sumPlus) && (arrBox[unique].nomer != '1'))
                    {
                        //Если сумма выше содержимого копилки, убираем последнюю введенную цифру из введенной
                        let text = arrBox[value].perevod + ''; //Читаем введенную сумму, прибавляя '' для преобразования в строку
                        arrBox[value].perevod = text.slice(0, -1); //Убираем символ
                        alert('Cумма ' + sumPlus + ' больше имеющейся в копилке.');
                        arrBox[unique].perevod = temp; 
                        arrBox[value].save(); //Показываем на экране
                        arrBox[unique].save();
                        return;
                    }
                
            }
        }
        
        //Ввод разрешен в неуникальное поле
        if (unique == value) return false; //Ввод в поле запрещен

        return true; //Ввод в поле разрешен
    }


        
    //Проверка ввода цифр в поля Копилок
    function reply_change (value)
    {
        var key = event.key; //Получаем нажатую клавишу
        //alert (event.key);
        //Отменяем изменение поля ввода, если введено не цифра и не нажата управляющая клавиша
    /*    if (!((key >= '0' && key <= '9') || key == 'ArrowLeft' || key == 'ArrowRight' || key == 'Delete' || key == 'Backspace')) {
            return false;
        }
    */

        arrBox[value].load(); //Читаем введенную сумму со страници в объект

        //Если ввод происходит в поле копилки со знаком минус, проверяем не привысила ли введенная сумма общую в копилке
        //Проверка превышения суммы не распространяется на Бюджет (исключаем его)
        if (Number(arrBox[value].summa) < Number(arrBox[value].perevod) && (arrBox[value].znak == -1) && (arrBox[value].nomer != '1'))
        {
            //Если сумма выше содержимого копилки, убираем последнюю введенную цифру из введенной
            let text = arrBox[value].perevod + ''; //Читаем введенную сумму, прибавляя '' для преобразования в строку
            alert('Сумма ' + text + ' больше имеющейся в копилке.');
            arrBox[value].perevod = text.slice(0, -1); //Убираем символ
            arrBox[value].save(); //Показываем на экране
            return;
        }

        input(value); //Рассчитываем сумму перевода

    }

    
    //Получение фокуса полем долгов, выделяем сумму в нем
    function clickDolg (value)
    {
        arrDolgi[value].sel(); //Выделяем текст в поле ввода
    }

    //Проверка ввода цифр в поля долгов
    function newDigit (value)
    {
        return arrDolgi[value].summ; //Читая сумму из поля одновременно проверяем введенную ее на на превышение
    }

    //Переход на погашение долга
    //Передаем в функцию номер (имя) долга, проверяем чтоб не была пустой 
    //и отправляем на сервер его и введенную сумму
    function debt (value)
    {
        //Сумма для отправки не должна быть нулевой
        if (!(arrDolgi['dolg'+value].summ)) 
        {
            alert ("Не указана сумма погашения долга.");
            return;
        }

        //Собираем парамерты (номер и сумму) со страницы и готовим строку для GET запроса
        var param = 'repay=' + value + '&summa=' + arrDolgi['dolg'+value].summ;
        SendRequest("GET","index.php", param, Handler2); //Отправляем AJAX запрос, передаем отредактированный долг

    }

    /*Создаем функцию обработчик AJAX запроса
    Функция получает результат изменения долга. Это цифра и новая статистика, если долги еще есть */
    var Handler2 = function(Request)
    {
        var result = Request.responseText; //Возвращяем результат выполнения
        //Возращяем numDebt - номер долга, summaDebt - остаток долга
        //debtorNumber, loanerNumber - номера обоих копилок
        //сashDebtor, cashLoaner - остаток средств в обоих копилках
        //statDebtor, statLoaner - статистика по долгfv для каждой копилки
        // или -1 который сообщяет о ошибке в выполнении запроса
        if (result != -1) 
        {
            clearBox(); //Погашение долга состоялось, очищаем копилки чтоб не возникло ошибок
            
            var mass = JSON.parse(result); //JSON в массив
            var debtorNumber = mass['debtorNumber']; //Получаем номер должника
            var loanerNumber = mass['loanerNumber']; //Получаем номер кредитора
            var numDebt = mass['numDebt']; //Получаем номер долга
          
            //Меняем сумму у копилки должника
            if (debtorNumber != '1') //У бюджета суммы нет, поэтому ее не меняем
            {
                arrBox[debtorNumber].summa = mass['сashDebtor']; //Записываем сумму в копилке в массив
                document.getElementById("sum"+debtorNumber).innerHTML = mass['сashDebtor'].toLocaleString('ru-RU'); //Выводим на экран в денежном формате
            }
            //Меняем сумму у копилки кредитора
            if (loanerNumber != '1') //У бюджета суммы нет, поэтому ее не меняем
            {
                arrBox[loanerNumber].summa = mass['cashLoaner']; //Записываем сумму в копилке в массив
                document.getElementById("sum"+loanerNumber).innerHTML = mass['cashLoaner'].toLocaleString('ru-RU'); //Выводим на экран в денежном формате
            }
            
            //Обновляем сумму долга в поле ввода и в объекте
            arrDolgi['dolg'+numDebt].summ = mass['summaDebt'];

            //Если сумма долга стала 0, значит долга уже нет, нужно удалить его из списка
            if (mass['summaDebt'] == '0') 
            {
                var element = document.getElementById('dolgOne'+numDebt); //Получаем div блок с долгом
                element.remove();
                delete arrDolgi['dolg'+numDebt]; //Удаляем и из массива с объектами долгов этот элемент
            }
            //Обновляем статистику на странице в обоих копилках
            document.getElementById('dolgStat'+debtorNumber).innerHTML = mass['statDebtor'];
            document.getElementById('dolgStat'+loanerNumber).innerHTML = mass['statLoaner'];

        } else alert ("Возврат долга не выполнен.");

    }

    //Нажатие кнопки OK: подготовка и отправка массива в JSON строке
    //Перебираем все копилки, отбираем активные, преобразуем номер копилки, состояние знака и суммы перевода к виду объекта:
    //'2':'500' или '5':'-1000'...
    //Знак умножается на сумму и полученный результат присваивается свойству с номером копилки
    function send()
    {
        //Если нет активных копилок то отправка не происходит
        if ((kolZnak('1') == 0) || (kolZnak('-1') == 0))
        {
            alert ("Нет данных для отправки.");
            return;
        }

        var stringSend = {};
        var value;
        for (var key in arrBox) {
            
            //Перебираем объекты, умножаем их зеак на сумму перевода 
            //и записываем в новый объект со свойством именем копилки если результат не 0
            value = arrBox[key].znak * arrBox[key].perevod;
            if (value) stringSend[key] = value;

        }

        if (!(Object.keys(stringSend).length)) //Не отправляем пустой массив
        {
            alert ("Нет данных для отправки.");
            return;
        }
        //Добавляем в массив состояние переключателя "В долг" true - если включен
        stringSend['dolg'] = document.getElementById('dolg').checked;

        //Записываем строку в поле input для отправки в массиве transfer
        document.getElementById('transfer').value = JSON.stringify(stringSend);
        document.getElementById('data').submit(); //Отправка формы

    }

    //Очищаем все копилки от знаков и сумм переводов
    function clearBox()
    {
        //Узнаем, сколько копилок и перебирая очищаем их
        for (var key in arrBox) {
            arrBox[key].clear();
        }
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
