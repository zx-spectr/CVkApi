<?php
/**
 * Обёртка для работы с API сайта vkontakte https://vk.com/dev/
 * 
 * @link https://github.com/zx-spectr/CVkApi 
 * @version 0.2
 * @author Laptev Grigory
 */
class CVkApi extends CComponent {

    /**
     * Коды ошибок с описанием
     * 
     * @var array
     */
    public static $errors = array(
        '1' => 'Произошла неизвестная ошибка',
        '2' => 'Приложение выключено',
        '3' => 'Передан неизвестный метод',
        '4' => 'Неверная подпись',
        '5' => 'Авторизация пользователя не удалась',
        '6' => 'Слишком много запросов в секунду',
        '7' => 'Нет прав для выполнения этого действия',
        '8' => 'Неверный запрос',
        '9' => 'Слишком много однотипных действий',
        '10' => 'Произошла внутренняя ошибка сервера',
        '11' => 'В тестовом режиме приложение должно быть выключено или пользователь должен быть залогинен',
        '12' => 'Невозможно скомпилировать код.',
        '13' => 'Ошибка выполнения кода',
        '14' => 'Требуется ввод кода с картинки (Captcha)',
        '15' => 'Доступ запрещён',
        '16' => 'Требуется выполнение запросов по протоколу HTTPS, т.к. пользователь включил настройку, требующую работу через безопасное соединение',
        '17' => 'Требуется валидация пользователя',
        '20' => 'Данное действие запрещено для не Standalone приложений',
        '21' => 'Данное действие разрешено только для Standalone и Open API приложений',
        '23' => 'Метод был выключен',
        '100' => 'Один из необходимых параметров был не передан или неверен',
        '101' => 'Неверный API ID приложения',
        '103' => 'Превышено ограничение на количество переменных.',
        '113' => 'Неверный идентификатор пользователя',
        '150' => 'Неверный timestamp',
        '200' => 'Доступ к альбому запрещён',
        '201' => 'Доступ к аудио запрещён',
        '203' => 'Доступ к группе запрещён',
        '214' => 'Нет доступа к размещению записей. ',
        '219' => 'Рекламный пост уже недавно публиковался. ',
        '300' => 'Альбом переполнен',
        '500' => 'Действие запрещено. Вы должны включить переводы голосов в настройках приложения',
        '600' => 'Нет прав на выполнение данных операций с рекламным кабинетом',
        '603' => 'Произошла ошибка при работе с рекламным кабинетом',

    );

    /**
     * Получает описание ошибки по её коду
     * 
     * @param int $errorCode Код ошибки
     * @return string
     */
    public static function getErrorText($errorCode) {
        return isset(self::$errors[$errorCode]) ? self::$errors[$errorCode] : self::$errors['1'];
    }


    /**
     * Шаблон ссылки для авторизации
     * 
     * @var string
     */                                                                                                                                                         
    private $_authorizeUrlTemplate = 'https://oauth.vk.com/authorize?client_id=APP_ID&scope=PERMISSIONS&redirect_uri=REDIRECT_URI&response_type=code&display=page&v=API_VERSION';

    /**
     * Шаблон ссылки для получения токена
     * 
     * @var mixed
     */
    private $_getAccessTokenUrlTemplate = 'https://oauth.vk.com/access_token?client_id=APP_ID&client_secret=APP_SECRET&code=CODE&redirect_uri=REDIRECT_URI';

    /**
     * Шаблон ссылки для отправки API запросов на сервер
     * 
     * @var string
     */
    private $_apiRequestUrlTemplate = 'https://api.vk.com/method/METHOD_NAME?PARAMETERS&access_token=ACCESS_TOKEN&v=API_VERSION';
    private $_apiRequestUrlTemplatePost = 'https://api.vk.com/method/METHOD_NAME?access_token=ACCESS_TOKEN&v=API_VERSION';

    /**
     * Адрес, на который будет передан code. Этот адрес должен находиться в пределах домена, указанного в настройках приложения. 
     * В адресе должен содержаться используемый протокол.
     * 
     * @var string
     */
    private $_redirectUri;

    /**
     * Код приложения
     * 
     * @var sting
     */
    private $_appId;

    /**
     * запрашиваемые права доступа приложения
     * 
     * @var mixed
     */
    private $_scope;

    /**
     * Версия API
     * 
     * @var mixed
     */
    private $_apiVersion = '5.23';
    

    /**
     * Токен для доступа к API
     * 
     * @var string
     */
    public $_accessToken;

    /**
     * Код полученный при авторизации 
     * 
     * @var string
     */
    public $code;


    public function __construct($appId, $redirectUri, $scope = '') {
        $this->_appId = $appId;
        $this->_redirectUri = $redirectUri;
        $this->_scope = $scope;
    }
    
    /**
     * Инициализация CURL
     * 
     */
    private function __getCurl() {
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300); 
        return $ch;
    }

    /**
     * Генерация ссылки по шаблону
     * 
     * @param mixed $urlTemplate
     * @param mixed $arrayReplace
     *
     * @return string
     */
    private function __prepareUrlTemplate($urlTemplate, $arrayReplace) {
        return str_replace(array_keys($arrayReplace), array_values($arrayReplace), $urlTemplate);
    }

    /**
     * Редирект на страницу авторизации 
     * 
     */
    public function login() {
        $arrayReplace = array(
            'APP_ID' => $this->_appId,
            'PERMISSIONS' => $this->_scope,
            'REDIRECT_URI' => urlencode($this->_redirectUri),
            'API_VERSION' => $this->_apiVersion
        );

        $authUrl = $this->__prepareUrlTemplate($this->_authorizeUrlTemplate, $arrayReplace);
        header( 'Location: ' . $authUrl ); 
        Yii::app()->end();
    }

    /**
     * Получить токен по коду
     * 
     * @param string $code
     * @param string $appSecret
     */
    public function getAccessToken($code, $appSecret) {
        $url = $this->_getAccessTokenUrlTemplate;
        $arrayReplace = array(
            'APP_ID' => $this->_appId,
            'CODE' => $code,
            'REDIRECT_URI' => urlencode($this->_redirectUri),
            'APP_SECRET' => $appSecret
        );
        $url = $this->__prepareUrlTemplate($this->_getAccessTokenUrlTemplate, $arrayReplace);

        $this->_webBrowser->get($url);
        $res = $this->_webBrowser->getResponseText();

        $res = json_decode($res, true);
        if (!empty($res['access_token']))  {
            $this->_accessToken = $res['access_token'];
        }
        return $this->_accessToken;
    }

    /**
     * Установка токена
     * 
     * @param string $token
     */
    public function setAccessToken($token) {
        $this->_accessToken = $token;
    }
    
    /**
     * Обработка исключения
     * 
     * @param Exception $error
     */
    private function catchError($error) {
        switch ($error['code']) {
            case '4':
            case '5':
                if (Yii::app()->session->get('vk_access_token', false)) {
                    Yii::app()->session->remove('vk_access_token');    
                }
                $this->accessToken = null;
                break;

        }
        throw new Exception(self::getErrorText($error['code']), $error['code']);    
    }
    
    /**
     * Отправка GET запроса через CURL
     * 
     * @param string $url Ссылка
     * @return {JSON}
     */
    public function get($url) {
        $ch = $this->__getCurl();
        curl_setopt($ch, CURLOPT_URL, $url); 
        $res = curl_exec($ch);
        curl_close($ch);
        $res = CJSON::decode($res);
        return $res;  
    }
    
    /**
     * Отправка запроса методом POST
     * 
     * @param mixed $url Ссылка
     * @param mixed $postParams Массив параметров
     * @return {stdClass|stdClass[]}
     */
    public function post($url, $postParams) {
        $ch = $this->__getCurl();
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_POST, true); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams); 
        $res = curl_exec($ch);
        curl_close($ch);
        $res = CJSON::decode($res);
        return $res;  
    }

    /**
     * Запрос к серверу API
     * 
     * @param string $methodName Название метода из списка функций API
     * @param array $arrayParameters Параметры соответствующего метода API
     * @param string $accessToken Ключ доступа, полученный в результате успешной авторизации приложения
     *
     * @return {JSON}
     * @throws Exception
     */
    public function queryApi($methodName, $arrayParameters, $accessToken = false) {
        if (empty($accessToken)) {
            $accessToken = $this->_accessToken;
        }
        $parameters = array();
        if (count($arrayParameters) > 0) {
            foreach ($arrayParameters as $key=>$val) {
                $parameters[] = $key . '=' . urlencode($val);
            }
            $parameters = implode('&', $parameters); 
        }

        $arrayReplace = array(
            'PARAMETERS' => $parameters,
            'METHOD_NAME' => $methodName,
            'ACCESS_TOKEN' => $accessToken,
            'API_VERSION' => $this->_apiVersion
        );

        $url = $this->__prepareUrlTemplate($this->_apiRequestUrlTemplate, $arrayReplace);
        $res = $this->get($url);
        if (!empty($res['error'])) {
            throw new Exception($res['error']['error_msg'], $res['error']['code']);
        }
        return $res;
    }
    
    /**
     * Отправка запроса к API через POST
     * 
     * @param string $methodName Название метода
     * @param array $arrayParameters Параметры
     * @param string $accessToken Токен
     * @return {JSON}
     */
    public function queryApiPost($methodName, $arrayParameters, $accessToken = false) {
        if (empty($accessToken)) {
            $accessToken = $this->_accessToken;
        }
        $parameters = array();
        if (count($arrayParameters) > 0) {
            foreach ($arrayParameters as $key=>$val) {
                $parameters[] = $key . '=' . urlencode($val);
            }
            $parameters = implode('&', $parameters); 
        }

        $arrayReplace = array(
            'METHOD_NAME' => $methodName,
            'ACCESS_TOKEN' => $accessToken,
            'API_VERSION' => $this->_apiVersion
        );

        $url = $this->__prepareUrlTemplate($this->_apiRequestUrlTemplatePost, $arrayReplace); 
        $res = $this->post($url, $parameters);
        if (!empty($res['error'])) {
            throw new Exception($res['error']['error_msg'], $res['error']['code']);
        }
        return $res;   
    }
    

    /**
     * Возвращает расширенную информацию о пользователях.
     * 
     * @link https://vk.com/dev.php?method=users.get
     * 
     * @param mixed $uids перечисленные через запятую идентификаторы пользователей или их короткие имена (screen_name). По умолчанию — идентификатор текущего пользователя. (список строк, разделенных через запятую, количество элементов должно составлять не более 1000)
     * 
     * @param mixed $fields список дополнительных полей, которые необходимо вернуть.
     * @param mixed $accessToken
     */
    public function usersGet($uids = '', $fields, $accessToken = false) {
        if ($fields == '*') {
            $fields = 'sex, bdate, city, country, photo_50, photo_100, photo_200_orig, photo_200, photo_400_orig, photo_max, photo_max_orig, online, online_mobile, domain, has_mobile, contacts, connections, site, education, universities, schools, can_post, can_see_all_posts, can_see_audio, can_write_private_message, status, last_seen, common_count, relation, relatives, counters, screen_name, maiden_name, timezone, occupation';
        }
        
        if (is_array($fields)) {
            $fields = implode(',', $fields);
        } else {
            $fields = str_replace(' ', '', $fields);
        }

        $uids = explode(',', $uids);
        if (count($uids) > 1000) {
            throw new CHttpException(500, 'количество элементов идентификаторов пользователей должно составлять не более 1000');
        }
        $uids = implode(',', $uids);
        $params = array(
            'uids' => $uids,
            'fields' => $fields
        );

        if (empty($params['uids'])) {
            unset($params['uids']);
        }

        return $this->queryApi('users.get', $params, $accessToken);
    }


    /**
     * Возвращает информацию о фотографиях по их идентификаторам.
     * 
     * @link https://vk.com/dev.php?method=photos.getById
     * 
     * @param mixed $photos перечисленные через запятую идентификаторы 
     * @param mixed $extended 1 — будут возвращены дополнительные поля likes, comments, tags, can_comment, can_repost. 
     * @param mixed $photoSizes возвращать ли доступные размеры фотографии в специальном формате.
     * @param mixed $addPhotosUserId Добавить к ID фт=отографий ID пользователя
     * @param mixed $accessToken Токен
     */
    public function photosGetById($photos, $extended = false, $photoSizes = false, $addPhotosUserId = 0, $accessToken = false) {
        if (!is_array($photos)) {
            $photos = explode(',', $photos);
        }

        if (!empty($addPhotosUserId)) {
            for ($i=0;$i<count($photos);$i++) {
                $photos[$i] = $addPhotosUserId . '_' . $photos[$i];
            }
        }

        $photos = implode(',', $photos);

        $params['photos'] = $photos;
        if ($extended) {
            $params['extended'] = 1;
        }
        if ($photoSizes) {
            $params['photo_sizes'] = 1;    
        }

        return $this->queryApi('photos.getById', $params, $accessToken);
    }


    /**
     * Возвращает адрес сервера для загрузки фотографий.
     * 
     * После успешного выполнения возвращает объект, содержащий следующие поля:
     * upload_url — адрес для загрузки фотографий;
     * album_id — идентификатор альбома, в который будет загружена фотография;
     * user_id — идентификатор пользователя, от чьего имени будет загружено фото.
     * 
     * @link https://vk.com/dev/photos.getUploadServer
     *  
     * @param mixed $albumId идентификатор альбома
     * @param mixed $groupId идентификатор сообщества, которому принадлежит альбом (если необходимо загрузить фотографию в альбом сообщества)
     * @param string $accessToken Токен
     */
    public function photosGetUploadServer($albumId, $groupId = false, $accessToken = false) {
        $params =array('album_id' => $albumId);
        if ($groupId) {
            $params['group_id'] = $groupId;
        }
        return $this->queryApi('photos.getUploadServer', $params, $accessToken);
    }

    /**
     * Отправка файла на сервер
     * 
     * @link https://vk.com/dev/upload_files?f=%D0%97%D0%B0%D0%B3%D1%80%D1%83%D0%B7%D0%BA%D0%B0%20%D1%84%D0%BE%D1%82%D0%BE%D0%B3%D1%80%D0%B0%D1%84%D0%B8%D0%B9%20%D0%B2%20%D0%B0%D0%BB%D1%8C%D0%B1%D0%BE%D0%BC%20%D0%BF%D0%BE%D0%BB%D1%8C%D0%B7%D0%BE%D0%B2%D0%B0%D1%82%D0%B5%D0%BB%D1%8F
     * 
     * @param string|array $filePath полный путь до файла, либо массив путей
     * @param string $url ссылка для отправки запроса
     * @param string $fileFieldName имя параметра, содержащего файл
     * @param array $arrayOtherParams дополнительные параметры формы
     * 
     * @return {JSON} После успешного выполнения возвращает следующие данные в формате JSON: {"server": '1', "photos_list": '2,3,4', "album_id": '5', "hash": '12345abcde'}  
     */
    public function sendFile($filePath, $url, $fileFieldName = 'file', $arrayOtherParams = array()) {
        $res = false;
        if (is_array($filePath)) {
            $ch = 0;
            foreach ($filePath as $file) {
                $ch++;
                $arrayOtherParams[$fileFieldName . $ch] = "@" . $file;
            }
        } else {
            $arrayOtherParams[$fileFieldName] = "@" . $filePath;         
        }
        
        $ch = $this->__getCurl();
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $arrayOtherParams); 
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res) {
            $res = CJSON::decode($res);
        }

        return $res;   
    }
    
    /**
     * Получить содержимое файла по ссылке
     * 
     * @param string $url Ссылка
     */
    public function getFileContent($url) {
        $ch = $this->__getCurl();
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    
    /**
     * Сохранить содержимое файла по ссылке на диск
     * 
     * @param string $url Ссылка
     * @param string $filePath Имя файла для сохранения
     */
    public function getFileContentToFilePath($url, $filePath) {
        $ch = $this->__getCurl();
        $fp = fopen ($filePath, 'w');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    /**
     * Сохраняет фотографии после успешной загрузки
     * 
     * @param int $albumId идентификатор альбома, в который необходимо сохранить фотографии
     * @param mixed $groupId идентификатор сообщества, в которое необходимо сохранить фотографии
     * @param string $server параметр, возвращаемый в результате загрузки фотографий на сервер
     * @param string $photosList параметр, возвращаемый в результате загрузки фотографий на сервер
     * @param string $hash параметр, возвращаемый в результате загрузки фотографий на сервер
     * @param string $caption текст описания фотографии
     * @param string $description текст описания альбома
     * @param mixed $latitude географическая широта, заданная в градусах (от -90 до 90); 
     * @param mixed $longitude географическая долгота, заданная в градусах (от -180 до 180);
     */
    public function photoSave($albumId, $groupId, $server, $photosList, $hash, $caption = false, $description = false, $latitude = false, $longitude = false) {
        $params = array(
            'album_id' => $albumId,
            'group_id' => $groupId,
            'server' => $server,
            'photos_list' => $photosList,
            'hash' => $hash
        );

        if (!empty($caption)) {
            $params['caption'] = $caption;
        }

        if (!empty($params['description'])) {
            $params['description'] = $description;
        }

        if (!empty($params['latitude'])) {
            $params['latitude'] = $latitude;
        }
        if (!empty($params['longitude'])) {
            $params['longitude'] = $longitude;
        }

        return $this->queryApi('photos.save', $params);
    }



    /**
     * Создает пустой альбом для фотографий.
     * 
     * Для вызова этого метода Ваше приложение должно иметь права: photos.
     * 
     * @param string $title название альбома
     * @param int $groupId идентификатор сообщества, в котором создаётся альбом. Для группы privacy и comment_privacy могут принимать два значения: 0 — доступ для всех пользователей, 1 — доступ только для участников группы. 
     * @param string $description текст описания альбома
     * @param int $commentPrivacy уровень доступа к комментированию альбома. Возможные значения: 0 — все пользователи, 1 — только друзья, 2 — друзья и друзья друзей, 3 — только я. 
     * @param int $privacy уровень доступа к альбому. Возможные значения: 0 — все пользователи, 1 — только друзья, 2 — друзья и друзья друзей, 3 — только я. 
     * @param string $accessToken Токен 
     */
    public function photosCreateAlbum($title, $groupId = false, $description = false, $commentPrivacy = 3, $privacy = 3, $accessToken = false) {
        $params = array(
            'title' => $title,
            'comment_privacy' => $commentPrivacy,
            'privacy' => $privacy
        );

        if ($groupId) {
            $params['group_id'] = $groupId;
        }

        if (!empty($description)) {
            $params['description'] = $description;
        }

        return $this->queryApi('photos.createAlbum', $params, $accessToken);

    }

    /**
     * Сохраняет значение переменной, название которой передано в параметре key
     * 
     * @link https://vk.com/dev/storage.set
     * 
     * @param string $key Название переменной
     * @param mixed $value Значение
     * @param int $userId id пользователя
     * @param int $global указывается 1, если необходимо работать с глобальными переменными, а не с переменными пользователя
     * @param string $accessToken Токен
     */
    public function storageSet($key, $value, $userId, $global = 0, $accessToken = false) {
        $params = array(
            'key' => $key,
            'value' => $value,
            'user_id' => $userId
        );
        if (!empty($global)) {
            $params['global'] = $global;
        }
        return $this->queryApi('storage.set', $params, $accessToken);    
    }


    /**
     * Возвращает значение переменной, название которой передано в параметре key.
     * 
     * @link https://vk.com/dev/storage.get
     * 
     * @param string $key название переменной (строка, максимальная длина 100) 
     * @param array $keys список названий переменных. Если указан этот параметр, то параметр key не учитывается 
     * @param int $userId id пользователя 
     * @param int $global указывается 1, если необходимо работать с глобальными переменными, а не с переменными пользователя. 
     * @param string $accessToken Токен 
     */
    public function storageGet($key, $keys = array(), $userId, $global = 0, $accessToken = false) {
        if (!is_array($keys)) {
            $keys = implode(',', $keys);
        }

        $params = array(
            'key' => $key,
            'user_id' => $userId
        );

        if (!empty($keys)) {
            $params['keys'] = $keys;
        }

        if (!empty($global)) {
            $params['global'] = $global;
        }
        return $this->queryApi('storage.get', $params, $accessToken);
    }


    /**
     * Удаляет переменную, название которой передано в параметре key
     * 
     * @param string $key Название переменной
     * @param int $userId id пользователя
     * @param int $global указывается 1, если необходимо работать с глобальными переменными, а не с переменными пользователя
     * @param string $accessToken Токен
     */
    public function storageRemove($key, $userId, $global = 0, $accessToken = false) {
        $params = array(
            'key' => $key,
            'value' => '',
            'user_id' => $userId
        );
        if (!empty($global)) {
            $params['global'] = $global;
        }
        return $this->queryApi('storage.set', $params, $accessToken);   
    }

    /**
     * Возвращает информацию о городах по их идентификаторам.
     * 
     * Идентификаторы (id) могут быть получены с помощью методов users.get, places.getById, places.search, places.getCheckins.
     * Это открытый метод, не требующий access_token.  
     * 
     * @link https://vk.com/dev/database.getCitiesById
     * @param array $cityIds идентификаторы городов
     * @return {JSON}
     */
    public function databaseGetCitiesById($cityIds) {
        if (!is_array($cityIds)) {
            $cityIds = array($cityIds);
        }
        
        $params = array(
            'city_ids' => implode(',', $cityIds)
        );
        return $this->queryApi('database.getCitiesById', $params);
    }

    /**
     * Получить код пола по его ID
     * 
     * @param int $id
     * @return string
     */
    public function getSexCode($id) {
        $res = 0;
        if ($id == 1) {
            $res = 'female';
        } else if ($id == 2) {
            $res = 'male';
        }
        return $res;
    }
    
    /**
     * Сохраняет текст вики-страницы.
     * 
     * Для вызова этого метода Ваше приложение должно иметь права: pages.
     * 
     * @link https://vk.com/dev/pages.save
     * 
     * @param mixed $text новый текст страницы в вики-формате
     * @param mixed $pageId идентификатор вики-страницы. Вместо page_id может быть передан параметр title
     * @param mixed $groupId идентификатор сообщества, которому принадлежит вики-страница
     * @param mixed $userId идентификатор пользователя, создавшего вики-страницу
     * @param mixed $title название вики-страницы
     * @return {JSON}
     */
    public function pagesSave($text, $pageId, $groupId, $userId, $title, $accessToken = false) {
        $params = array(
            'Text' => $text,
            'group_id' => $groupId,
            'user_id' => $userId,
            'title' => $title
        );
        
        if (!empty($pageId)) {
            $params['page_id'] = $pageId;
        }
        return $this->queryApiPost('pages.save', $params, $accessToken);
    }
    
    
    /**
     * Универсальный метод, который позволяет запускать последовательность других методов, сохраняя и фильтруя промежуточные результаты. 
     * 
     * @param string $code код алгоритма в VKScript - формате
     * @param mixed $accessToken
     */
    public function execute($code, $accessToken = false) {
        $params = array(
            'code' => $code
        );
        return $this->queryApiPost('execute', $params, $accessToken);
    }
    
    
    public function wallPost($ownerId, $message, $arrayAttachments = array(), $friendsOnly = 0, $fromGroup = 0, $accessToken = false) {
        $arrayAttachments = implode(',', $arrayAttachments);
        $params = array(
            'owner_id' => $ownerId,
            'friends_only' => $friendsOnly,
            'from_group' => $fromGroup,
            'message' => $message,
            'attachments' => $arrayAttachments
        );
        return $this->queryApi('wall.post', $params, $accessToken);
    }

}
?>
