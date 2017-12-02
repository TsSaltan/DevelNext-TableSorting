# Кастомная сортировка таблиц в DevelNext
![Screenshot](https://user-images.githubusercontent.com/3524731/33519347-f123ad26-d7b5-11e7-8df0-d94ef9dbfd46.png)
**Демо проект: [https://hub.develnext.org/project/sFRAgEmQzKNb](https://hub.develnext.org/project/sFRAgEmQzKNb)**

### Установка
Поместить файл **TableSorting.php** в свой проект

### Использование
```php
$sorting = new TableSorting($this->table); // Конструктор принимает объект с таблицей

$sorting->setDefaultRule('id'); // Устанавливает сортировку по умолчанию, в таком случае числовые значения будут от минимального в максимальному, а буквенные - по алфавиту
$sorting->setDefaultRule('col1'); 

$sorting->setDefaultRule(['id', 'col1']); // То же самое, только одной строкой

// Установка своей функции сортировки
// Функция принимает два элемента таблицы $a и $b
// Функция должна вернуть 1, 0, -1
// 1 - элемент $a больше по какому-то из значений, чем $b
// -1 - элемент $a меньше по какому-то из значений, чем $b
// 0 - элементы $a и $b равновелики
$sorting->setSortingRule('col2', function($a, $b){
	// В данном случае представлена функция сортировки по длине строки
    $la = str::length($a);
    $lb = str::length($b);
          
    return $la > $lb ? 1 :
           $la < $lb ? -1 : 0;
});
// Так же можно передать в 1й аргумент массив со столбцами, например ['col2', 'col3']
```