<?php

require_once(dirname(__FILE__) . '/Abstract.php');

class Google_Test_CalendarTest extends Google_Test_Abstract
{
    
    /**
     * Тест Google_Calendar
     * 
     * Для выполнения теста нужно
     * указать свои логин и пароль
     * в вызове метода login();
     *
     */
    public function testMain()
    {
        $calendar = new Google_Calendar();
        
        // логин в Google
        $this->assertTrue($calendar->login('glebstarkov', '******'));
        
        // добавляем событие в календарь
        $this->assertTrue($calendar->addEvent('24.11.2010', 'New test event'));
    }
}

