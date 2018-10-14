<?php

use php\gui\layout\UXVBox;
use php\gui\layout\UXHBox;
use php\framework\Logger;
use php\time\Time;
use php\lang\Thread;
use php\util\Regex;
use php\lib\str;
use php\gui\UXImageView;
use php\gui\UXLabelEx;
use php\gui\UXLabel;
use Exception;
use php\gui\UXTableColumn;
use php\gui\UXTableView;

/**
 * Класс для сортировки данных в таблице
 * 
 * @author Ts.Saltan
 * @version 0.2
 * @todo Отключение сортировки
 * @todo Сохранение группового выделения  
 */
class TableSorting 
{
    /**
     * @var UXTableView
     */
    private $table;    
    
    /**
     * -1 - по убыванию
     * 0 - не используется
     * 1 - по возрастанию
     * @var int
     */
    private $direction = -1;    
    
    /**
     * ID последнего сортируемого столбца
     * @var string
     */
    private $lastSortId = null;
     
    public function __construct(UXTableView $table){
        $this->table = $table;
    }
    
    /**
     * Установить правило сортировки содержимого столбца
     * @param string|array $columnId Идентификатор (или массив идентификаторов) столбца
     * @param callable $rule Функция-правило сортировки, на входе принимает элементы $a и $b, если возвращает 1 - строки поменяются местами
     */
    public function setSortingRule($columnId, callable $rule){
        if(is_array($columnId)){
            foreach($columnId as $c){
                $this->setSortingRule($c, $rule);
            }
            return;
        }
        
        /** @var UXTableColumn $column **/
        $column = $this->getColumnById($columnId);
        $column->sortable = false;
        $column->data('sorting-callback', $rule);
        
        $box = new UXHBox;
        if(!is_null($column->graphic)){
            $box->add($column->graphic);
        }
        
        // Имитация заголовка таблицы
        $label = new UXLabelEx($column->text);
        
        // Индикатор сортировки
        $sort = new UXLabelEx;
        $sort->font = $label->font->withSize($label->font->size - 3);
        $sort->classes->add('sorting-indicator');
        $sort->id = 'sorting-indicator-' . $this->table->id . '-' . $column->id;
        $sort->paddingLeft = 3;
        $sort->opacity = 0.8;
        
        $box->add($label);
        $box->add($sort);
        $box->alignment = 'CENTER';
        $box->anchors = [
            'top' => true,
            'bottom' => true,
            'left' => true,
            'right' => true,
        ];
        
        $column->text = null;
        $column->graphic = $box;
        
        $box->on('click', function() use ($column){            
            $this->sortColumn($column);
        });
    }
    
    /**
     * Установить сортировку по умолчанию 
     * @param string|array $columnId Идентификатор столбца
     */
    public function setDefaultRule($columnId){
        return $this->setSortingRule($columnId, [$this, 'defaultRule']);
    }
    
    /**
     * Отсортировать столбец по сохранённому ранее правилу
     */
    private function sortColumn(UXTableColumn $column){ 
        $this->direction++;        
        if($this->direction > 1) $this->direction = -1;
        
        $this->sortColumnByRule($column->id, $column->data('sorting-callback'));
    }
    
    /**
     * Отобразить индикатор сортировки
     */
    private function setSortingIndicator(string $columnId){
        $sortId = 'sorting-indicator-' . $this->table->id . '-' . $columnId;
        $indicators = $this->table->lookupAll('.sorting-indicator');
        
        foreach($indicators as $indicator){
            /** @var UXLabelEx $indicator **/
            if($indicator->id == $sortId){
                $indicator->text = 
                    $this->direction == -1 ? '▼' : 
                    $this->direction ==  1 ? '▲' :
                    '' ;
            }
            else $indicator->text = '';
        }
    }    
    
    /**
     * Получить столбец по его id
     * @return UXTableColumn
     */
    private function getColumnById($id) : UXTableColumn {
        /** @var UXTableColumn $column **/
        foreach($this->table->columns as $column){
            if($column->id == $id) return $column;
        }
        
        throw new Exception('Column with id = "'.$id.'" does not exist');
    }
    
    /**
     * Отсортировать столбец по правилу-функции
     */
    private function sortColumnByRule(string $columnId, callable $rule){
        if($columnId != $this->lastSortId) $this->direction = 1;
        $this->lastSortId = $columnId;
        
        //$startTime = Time::millis(); // дебаг :)
        $itemsNum = $this->table->items->count();
        $items = $this->table->items->toArray();
        $sIndex = $this->table->selectedIndex;
        $fIndex = $this->table->focusedIndex;
        
        // Лучше сортировать в потоке, чтоб не тормозило
        (new Thread(function() use ($columnId, $rule, $itemsNum, $items, $sIndex, $fIndex, $startTime){        
            $maxIters = pow($itemsNum, 2); // Теоретически, это максимальное кол-во итераций, необходимых для сортировки, если требуется больше, то функция сортировки некорректная
            $iters = 0;
            
            //Logger::info('Sorting started!');
             
            for($i = 0; $i < $itemsNum-1; ++$i){
                $a = $items[$i];
                $b = $items[$i+1];
                $result = call_user_func_array($rule, [$a[$columnId], $b[$columnId]]);                
                $result = ($this->direction > 0) ? $result : $result * -1; // Корректируем с направлением сортировки
                if($result > 0){
                    // Меняем ячейки местами   
                    $items[$i] = $b;     
                    $items[$i+1] = $a;     
                     
                    // Сохраняем индексы сфокусирванных и выбранных ячеек
                    $sIndex += ($sIndex == $i) ? 1 : (($sIndex == $i + 1) ? -1 : 0);
                    $fIndex += ($fIndex == $i) ? 1 : (($fIndex == $i + 1) ? -1 : 0);
                    
                    $i = max($i-2, -1); // Чтоб постоянно не обнулять i и не делать перебор всей таблицы, делаем шаг назад, до измененных строк
                }
                
                if($iters++ > $maxIters){
                    Logger::warn('A lot of iterations ('.$iters.') / invalid sorting function?');
                    break;
                }
            }
            /*Logger::info('Iterations: ' . $iters);
            Logger::info('Before ui: ' . (Time::millis() - $startTime) . ' ms');
            $startTime = Time::millis(); //*/
            
            uiLater(function() use ($columnId, $items, $sIndex, $fIndex, $startTime){
                $this->table->items->clear();
                $this->table->items->addAll($items); // Быстрее заменить все элементы сразу                
                $this->table->selectedIndex = $sIndex;
                $this->table->focusedIndex = $fIndex;
                
                $this->setSortingIndicator($columnId);
                
                //Logger::warn('After ui: ' . (Time::millis() - $startTime) . ' ms');
            });
        }))->start();
    }
    
    /**
     * Правило по умолчанию: попытаемся распарсить числовые значения
     */
    public function defaultRule($a, $b) : int {
        if(is_numeric($a) and is_numeric($b)){
            $a = floatval($a);
            $b = floatval($b);
            
            $return = ($a > $b) ? 1 : 
                      ($a < $b) ? -1 : 0;
        } else {
            $return = str::compare($a, $b);
        }                  
               
        return $return;
    }
}
