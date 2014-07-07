<?php
/**
 * Обёртка для работы с API сайта vkontakte https://vk.com/dev/
 *  
 * @version 0.1
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
    private $_apiRequestUrlTemplate = 'https://api.vk.com/method/METHOD_NAME?PARAMETERS&access_token=ACCESS_TOKEN';

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
    private $_apiVersion = '5.21';

    /**
     * Компонент для отоправки запросов
     * 
     * @var sfWebBrowser
     */
    private $_webBrowser;

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
        $this->_webBrowser = Yii::app()->webBrowser;
        //$this->_webBrowser->
    }

    /**
     * Генерация ссылки по шаблону
     * 
     * @param mixed $urlTemplate
     * @param mixed $arrayReplace
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
    
    public function setAccessToken($token) {
        $this->_accessToken = $token;
    }

    private function catchError($error) {
        Yii::log( $this->_webBrowser->getResponseHeaders(), CLogger::LEVEL_ERROR );
        Yii::log( $this->_webBrowser->getResponseText(), CLogger::LEVEL_ERROR );

        switch ($error['code']) {
            case '4':
            case '5':
                if (Yii::app()->session->get('vk_access_token', false)) {
                    Yii::app()->session->remove('vk_access_token');    
                }
                break;

        }
        throw new Exception(self::getErrorText($error['code']), $error['code']);    
    }

    /**
     * Запрос к серверу API
     * 
     * @param string $methodName Название метода из списка функций API
     * @param array $arrayParameters Параметры соответствующего метода API
     * @param string $accessToken Ключ доступа, полученный в результате успешной авторизации приложения
     */
    public function queryApi($methodName, $arrayParameters, $accessToken = false) {
        if (empty($accessToken)) {
            $accessToken = $this->_accessToken;
        }
        $parameters = array();
        if (count($arrayParameters) > 0) {
            foreach ($arrayParameters as $key=>$val) {
                $parameters[] = $key . '=' . $val;
            }
            $parameters = implode('&', $parameters); 
        }

        $arrayReplace = array(
            'PARAMETERS' => $parameters,
            'METHOD_NAME' => $methodName,
            'ACCESS_TOKEN' => $accessToken
        );

        $url = $this->__prepareUrlTemplate($this->_apiRequestUrlTemplate, $arrayReplace);

        $this->_webBrowser->get($url);
        $res = $this->_webBrowser->getResponseText();
        $res =  json_decode($res, true);
        if (!empty($res['error'])) {
            Yii::log( $this->_webBrowser->getResponseHeaders(), CLogger::LEVEL_ERROR );
            Yii::log( $this->_webBrowser->getResponseText(), CLogger::LEVEL_ERROR );

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
    public function usersGet($uids = '', $fields, $accessToken) {
        if (is_array($fields)) {
            $fields = implode(',', $fields);
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






}
?>
