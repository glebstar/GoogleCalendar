<?php

/**
 * http://pear.php.net/package/HTTP_Request2
 */
require_once('HTTP/Request2.php');

require_once(dirname(__FILE__) . '/Tools.php');

/**
 * Google_Calendar
 * Класс созданный для размещения событий в календаре Google
 * 
 * @author Глеб Старков (glebstarkov@gmail.com)
 */
class Google_Calendar
{
    
    /**
     * Хранит последний ответ сервера
     *
     * @var HTTP_Request2_Response
     *
     */
    private $_response = null;
    
    /**
     * Заголовок нового события по умолчанию
     *
     * @var string 
     *
     */
    private $_defaultTitle = 'New event';
    
    
    /**
     * Время начала события по умолчанию
     *
     * @var string 
     *
     */
    private $_defaultStartTime = '09:00';
    
    
    /**
     * Длительность события по умолчанию
     * (в миллисекундах)
     *
     * @var int 
     *
     */
    private $_defaultDuration = 3600;
    
    
    /**
     * Смещение временной зоны по умолчанию
     *
     * @var string 
     *
     */
    private $_defaultOffsetTz = '+03';
    
    
    /**
     * Место проведения события по умолчанию
     *
     * @var string 
     *
     */
    private $_defaultWhere = 'Unknown place';
    
    
    /**
     * хост для создания событий
     *
     * @var string 
     *
     */
    private $_defaultUrl = 'http://www.google.com/calendar/feeds/default/private/full';
    
    
    /**
     * Строка аутентификации для Google
     * Будет автоматически заполнена
     * при успешном логине
     *
     * @var mixed 
     *
     */
    private $_auth = null;
    
    
    /**
     * Аутентификация в Google
     *
     * @param string $username Имя пользователя или e-mail
     * @param string $password Пароль
     * 
     * Ответ сервера будет доступен через $this->_getLastResponse()
     * 
     * @throws HTTP_Request2_Exception
     * @return bool Результат аутентификации
     *
     */
    public function login($username, $password)
    {
        $config = array(
                'protocol_version'=>'1.0',
                'ssl_verify_peer' => false
            );
        
        $request = new HTTP_Request2('https://www.google.com/accounts/ClientLogin', HTTP_Request2::METHOD_POST, $config);
        $request->setHeader('Content-type', 'application/x-www-form-urlencoded')
            ->addPostParameter('accountType', 'GOOGLE')
            ->addPostParameter('Email', $username)
            ->addPostParameter('Passwd', $password)
            ->addPostParameter('service', 'cl')
            ->addPostParameter('source', 'edelen-gsgc-100');
        
        $this->_send($request);
        
        $_crm = array();
        preg_match('/Auth=.+\s/', $this->_response->getBody(), $_crm);
        if ( empty($_crm) ) {
            return false;
        }
        
        $this->_auth = $_crm[0];
        
        return true;
    }
    
    
    /**
     * Добавление нового события в календарь Google
     *
     * @param string $startDate Дата начала
     *                          формат дд.мм.ГГГГ
     * @param string $body Текст события
     * @param string $title Заголовок события
     * @param string $startTime Время начала
     *                          формат: чч:мм
     * @param string $endDate Дата окончания
     *                        формат: дд.мм.ГГГГ
     * @param string $endTime Время окончания
     *                        формат: чч:мм
     * @param string $offsetTz Временная зона
     *                         формат: +ЧЧ(-ЧЧ)
     * @param string $where Место проведения события
     * 
     * @throws Exception. При некорректных форматах в параметрах, 
     *                    либо при выполнении запроса на сервер
     *                    (См. HTTP_Request2::send())
     * @return bool Результат работы
     *
     */
    public function addEvent($startDate, $body, $title=false, $startTime=false, $endDate=false, $endTime=false, $offsetTz=false, $where = false)
    {
        if ( is_null($this->_auth) ) {
            throw new Exception('First you need to authenticate. Use the login ();');
        }
        
        $_arrStartDate = $this->_getArrayCheckDate($startDate);
        $startTime = $startTime ? $startTime : $this->_defaultStartTime;
        $_arrStartTime = $this->_getArrayCheckTime($startTime);
        
        if ( $endDate ) {
            $_arrEndDate = $this->_getArrayCheckDate($endDate);
        } else {
            $endTimestamp = mktime($_arrStartTime[1], $_arrStartTime[2], 0, $_arrStartDate[2], $_arrStartDate[1], $_arrStartDate[3]) + $this->_defaultDuration;
            $endDate = date('d.m.Y', $endTimestamp);
            $_arrEndDate = $this->_getArrayCheckDate($endDate);
            $_arrEndTime = $this->_getArrayCheckTime(date('H:i', $endTimestamp));
        }
        
        if ( !isset($_arrEndTime) ) {
            $_arrEndTime = $_arrStartTime;
        }
        
        if ( mktime($_arrEndTime[1], $_arrEndTime[2], 0, $_arrEndDate[2], $_arrEndDate[1], $_arrEndDate[3]) < 
                mktime($_arrStartTime[1], $_arrStartTime[2], 0, $_arrStartDate[2], $_arrStartDate[1], $_arrStartDate[3]) ) {
            throw new Exception('End date earlier than start date.');
        }
        
        $offsetTz = $offsetTz ? $offsetTz : $this->_defaultOffsetTz;
        
        if ( !preg_match('/^[+\-]\d{2}/', $offsetTz) ) {
            throw new Exception('Incorrect offsetTz.');
        }
        
        $_pars = array();
        $_pars['title'] = $title ? $title : $this->_defaultTitle;
        $_pars['content'] = $body;
        $_pars['startTime'] = "{$_arrStartDate[3]}-{$_arrStartDate[2]}-{$_arrStartDate[1]}T{$_arrStartTime[1]}:{$_arrStartTime[2]}:00.000{$offsetTz}:00";
        $_pars['endTime'] = "{$_arrEndDate[3]}-{$_arrEndDate[2]}-{$_arrEndDate[1]}T{$_arrEndTime[1]}:{$_arrEndTime[2]}:00.000{$offsetTz}:00";
        $_pars['where'] = $where ? $where : $this->_defaultWhere;
        
        $sendBody = Google_Tools::parseTpl(dirname(__FILE__) . '/template/single_event.xml', $_pars);
        
        $request = $this->_getRequest($this->_defaultUrl, $sendBody);
        
        $this->_send($request);
        
        if ( $this->getLastResponseCode() != 302 ) {
            return false;
        }
        
        $coock = $this->_response->getCookies();
        
        $_crm = array();
        if ( isset($coock[0]) ) {
            preg_match('/^calendar=(.+)/', $coock[0]['value'], $_crm);
            if ( !empty($_crm) ) {
                $request = $this->_getRequest($this->_defaultUrl . "?gsessionid={$_crm[1]}", $sendBody);
                
                $this->_send($request);
                
                if ( $this->getLastResponseCode() != 201 ) {
                    return false;
                }
                
                return true;
            }
        }
        
        return false;
    }
    
    
    /**
     * Возвращает последний ответ сервера
     *
     * @return HTTP_Request2_Response
     *
     */
    public function getLastResponse()
    {
        return $this->_response;
    }
    
    
    /**
     * Возвращает код последнего ответа сервера
     *
     * @return mixed false, если ответа сервера не было
     *               int, если ответ был
     */
    public function getLastResponseCode()
    {
        if ( is_null($this->_response) ) {
            return false;
        }
        
        return $this->_response->getStatus();
    }
    
    
    /**
     * Выполняет запрос к серверу
     *
     * @param HTTP_Request2 $request
     * 
     * @throws HTTP_Request2_Exception
     * @return void
     *
     */
    private function _send($request)
    {
        $this->_response = $request->send();
    }
    
    
    /**
     * Проверяет дату на соответствие формату дд.мм.ГГГГ
     * И возвращает значения даты в массиве
     *
     * @param string $date
     * @throws Exception при неверном формате даты
     * @return array
     *
     */
    private function _getArrayCheckDate($date)
    {
        $_crm = array();
        if ( !preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $_crm) ) {
            throw new Exception('Incorrect date format. Required dd.mm.YYYY');
        }
        
        return $_crm;
    }
    
    
    /**
     * Проверяет время на соответствие формату чч:мм
     * и возвращает значения времени в массиве
     *
     * @param string $time
     * @throws Exception при неверном формате времени
     * @return array
     *
     */
    private function _getArrayCheckTime($time)
    {
        $_crm = array();
        if ( !preg_match('/^(\d{2}):(\d{2})$/', $time, $_crm) ) {
            throw new Exception('Incorrect time format. Required hh:ii');
        }
        
        return $_crm;
    }
    
    
    /**
     * Готовит запрос для отправки на сервер
     *
     * @param sting $url
     * @param string $content (В формате atom xml)
     *
     */
    private function _getRequest($url, $content)
    {
        $config = array(
                'protocol_version'=>'1.1',
                'ssl_verify_peer' => false
                );
        $request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST, $config);
        return $request->setHeader('Host', 'www.google.com')
            ->setHeader('Connection', 'close')
            ->setHeader('User-Agent', 'edelen-gsgc-100')
            ->setHeader('authorization', "GoogleLogin {$this->_auth}f")
            ->setHeader('Content-Type', 'application/atom+xml')
            ->setHeader('Accept-encoding', 'identity')
            ->setHeader('Content-Length', strlen($content))
            ->setBody($content);
    }
}

