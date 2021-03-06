<?php


class Keep2ShareAPI {

    const ERROR_INCORRECT_PARAMS = 2;
    const ERROR_INCORRECT_PARAM_VALUE = 3;
    const ERROR_INVALID_REQUEST = 4;

    const ERROR_YOU_ARE_NEED_AUTHORIZED = 10;
    const ERROR_AUTHORIZATION_EXPIRED = 11;

    const ERROR_FILE_NOT_FOUND = 20;
    const ERROR_FILE_IS_NOT_AVAILABLE = 21;
    const ERROR_FILE_IS_BLOCKED = 22;
    const ERROR_DOWNLOAD_FOLDER_NOT_SUPPORTED = 23;

    const ERROR_CAPTCHA_REQUIRED = 30;
    const ERROR_CAPTCHA_INVALID = 31;

    const ERROR_WRONG_FREE_DOWNLOAD_KEY = 40;
    const ERROR_NEED_WAIT_TO_FREE_DOWNLOAD = 41;
    const ERROR_DOWNLOAD_NOT_AVAILABLE = 42;
    const ERROR_DOWNLOAD_PREMIUM_ONLY = 43;

    const ERROR_NO_AVAILABLE_RESELLER_CODES = 50;
    const ERROR_BUY_RESELLER_CODES = 51;

    const ERROR_CREATE_FOLDER = 60;
    const ERROR_UPDATE_FILE = 61;
    const ERROR_COPY_FILE = 62;
    const ERROR_NO_AVAILABLE_NODES = 63;
    const ERROR_DISK_SPACE_EXCEED = 64;

    const ERROR_INCORRECT_USERNAME_OR_PASSWORD = 70;
    const ERROR_LOGIN_ATTEMPTS_EXCEEDED = 71;
    const ERROR_ACCOUNT_BANNED = 72;
    const ERROR_NO_ALLOW_ACCESS_FROM_NETWORK = 73;
    const ERROR_UNKNOWN_LOGIN_ERROR = 74;
    const ERROR_ILLEGAL_SESSION_IP = 75;
    const ERROR_ACCOUNT_STOLEN = 76;
    const ERROR_NETWORK_BANNED = 77;

    protected $_ch;
    protected $_auth_token;
    protected $_allowAuth = true;
    public $baseUrl = 'http://keep2share.cc/api/v2/';
    public $username;
    public $password;
    public $verbose = false;

    public function __construct()
    {
        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_POST, true);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, 2);

        $this->_auth_token = $this->getAuthToken();
    }

    /**
     * @param null $captcha_challenge
     * @param null $captcha_response
     * @return bool|int True if success login or error code
     */
    public function login($captcha_challenge = null, $captcha_response = null)
    {
        curl_setopt($this->_ch, CURLOPT_URL, $this->baseUrl.'login');

        $params = [
            'username'=>$this->username,
            'password'=>$this->password,
        ];

        if($captcha_challenge)
            $params['captcha_challenge'] = $captcha_challenge;

        if($captcha_response)
            $params['captcha_response'] = $captcha_response;

        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, json_encode($params));
        $response = curl_exec($this->_ch);

        if($this->verbose) {
            echo '>> ' . json_encode($params), "\n";
            echo '<< ' . $response, "\n";
            echo "-------------------------\n";
        }
        $data = json_decode($response, true);

        if(!$data || !isset($data['status'])) {
            self::log('Authentication failed', 'warning');
            return false;
        }

        if($data['status'] == 'success') {
            $this->setAuthToken($data['auth_token']);
            $this->_auth_token = $data['auth_token'];
            return true;
        } else {
            self::log('Authentication failed: ' . $data['message'], 'warning');
            return $data['errorCode'];
        }
    }

    public function request($action, $params = array())
    {
        if($this->_auth_token) {
            $params['auth_token'] = $this->_auth_token;
        }

        curl_setopt($this->_ch, CURLOPT_URL, $this->baseUrl.$action);
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, json_encode($params));
        $response = curl_exec($this->_ch);

        if($this->verbose) {
            echo '>> ' . json_encode($params), "\n";
            echo '<< ' . $response, "\n";
            echo "-------------------------\n";
        }

        $data = json_decode($response, true);
        if($data['status'] == 'error' && isset($data['code']) && $data['code'] == 403) {
            if($this->_allowAuth) {
                $this->login();
                $this->_allowAuth = false;
                return $this->request($action, $params);
            } else {
                return false;
            }
        }
        return $data;
    }


    public function getCode($days, $autoBuy = true, $useExist = true)
    {
        $data = $this->request('resellerGetCode', array(
            'days'=>$days,
            'autoBuy'=>$autoBuy,
            'useExist'=>$useExist,
        ));
        if(!isset($data['status']) || $data['status'] != 'success') {
            $err = 'Error get code';
            if(isset($data['message'])) {
                $err .= ' :' . $data['message'];
            }
            self::log($err, 'warning');
            return false;
        } else {
            return $data;
        }
    }

    public function getFilesList($parent = '/', $limit = 100, $offset = 0, array $sort = [], $type = 'any',
                                 $only_available = false, $extended_info = false)
    {
        return $this->request('getFilesList', array(
            'parent'=>$parent,
            'limit'=>$limit,
            'offset'=>$offset,
            'sort'=>$sort,
            'type'=>$type,
            'only_available' => $only_available,
            'extended_info' => $extended_info,
        ));
    }

    const FILE_ACCESS_PUBLIC = 'public';
    const FILE_ACCESS_PRIVATE = 'private';
    const FILE_ACCESS_PREMIUM = 'premium';

    public function createFolder($name, $parent = '/', $access = Keep2ShareAPI::FILE_ACCESS_PUBLIC, $is_public = false)
    {
        return $this->request('createFolder', array(
            'name'=>$name,
            'parent'=>$parent,
            'access'=>$access,
            'is_public'=>$is_public,
        ));
    }

    public function updateFiles($ids = [], $new_name = null, $new_parent = null, $new_access = null, $new_is_public = null)
    {
        return $this->request('updateFiles', array(
            'ids'=>$ids,
            'new_name'=>$new_name,
            'new_parent'=>$new_parent,
            'new_access'=>$new_access,
            'new_is_public'=>$new_is_public,
        ));
    }


    public function updateFile($id, $new_name = null, $new_parent = null, $new_access = null, $new_is_public = null)
    {
        return $this->request('updateFile', array(
            'id'=>$id,
            'new_name'=>$new_name,
            'new_parent'=>$new_parent,
            'new_access'=>$new_access,
            'new_is_public'=>$new_is_public,
        ));
    }

    public function getBalance()
    {
        return $this->request('getBalance');
    }

    public function getFilesInfo(array $ids, $extended_info = false)
    {
        return $this->request('getFilesInfo', array(
            'ids'=>$ids,
            'extended_info' => $extended_info,
        ));
    }


    public function remoteUploadAdd(array $urls)
    {
        return $this->request('remoteUploadAdd', array(
            'urls'=>$urls,
        ));
    }

    public function deleteFiles(array  $ids)
    {
        return $this->request('deleteFiles', array(
            'ids' => $ids,
        ));
    }

    const REMOTE_UPLOAD_STATUS_NEW = 1;
    const REMOTE_UPLOAD_STATUS_PROCESSING = 2;
    const REMOTE_UPLOAD_STATUS_COMPLETED = 3;
    const REMOTE_UPLOAD_STATUS_ERROR = 4;
    const REMOTE_UPLOAD_STATUS_ACCEPTED = 5;

    public function remoteUploadStatus(array $ids)
    {
        return $this->request('remoteUploadStatus', array(
            'ids'=>$ids,
        ));
    }

    public function findFile($md5)
    {
        return $this->request('findFile', array(
            'md5'=>$md5,
        ));
    }

    public function createFileByHash($md5, $name, $parent = '/', $access = Keep2ShareAPI::FILE_ACCESS_PUBLIC)
    {
        return $this->request('createFileByHash', array(
            'hash'=>$md5,
            'name'=>$name,
            'parent'=>$parent,
            'access'=>$access,
        ));
    }

    public function getUploadFormData($parent_id = null, $preferred_node = null)
    {
        return $this->request('getUploadFormData', ['parent_id' => $parent_id, 'preferred_node' => $preferred_node]);
    }

    public function test()
    {
        $response = $this->request('test');
        return $response;
    }

    /**
     * @param $file
     * @param null $parent_id ID of existing folder
     * @param null $preferred_node
     * @return bool|mixed
     * @throws Exception You can use parent_id OR parent_name for specify file folder
     */
    public function uploadFile($file, $parent_id = null, $preferred_node = null)
    {
        if(!is_file($file))
            throw new Exception("File '{$file}' is not found");

        $data = $this->getUploadFormData($parent_id, $preferred_node);
        if($data['status'] == 'success') {
            $curl = curl_init();

            $postFields = $data['form_data'];
            $postFields[$data['file_field']] = new CURLFile($file);

            curl_setopt_array($curl, array(
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $data['form_action'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS =>$postFields,
            ));

            $response = curl_exec($curl);
            if($this->verbose) echo '<<', $response, "\n";
            return json_decode($response);
        } else {
            self::log('Error uploading file : ' . print_r($data, true), 'error');
            return false;
        }
    }

    public function getAccountInfo()
    {
        return $this->request('accountInfo');
    }

    public function requestCaptcha()
    {
        return $this->request('requestCaptcha');
    }

    public function getUrl($file_id, $free_download_key = null, $captcha_challenge = null, $captcha_response = null)
    {
        return $this->request('getUrl', [
            'file_id'=>$file_id,
            'free_download_key'=>$free_download_key,
            'captcha_challenge'=>$captcha_challenge,
            'captcha_response'=>$captcha_response,
        ]);
    }

    public function search($keywords, $operator = 'or', $sort = 'score',
                           $limit = 20, $offset = 0, $ip_client = null)
    {
        return $this->request('search', [
            'keywords' => $keywords,
            'operator' => $operator,
            'sort' => $sort,
            'limit' => $limit,
            'offset' => $offset,
            'ip_client' => $ip_client,
        ]);
    }

    public static function log($msg, $level)
    {
        echo $msg."\n";
    }

    public function setAuthToken($key)
    {
//        $cache = new Memcache();
//        $cache->addserver('127.0.0.1');
//        $cache->set('Keep2ShareApiAuthToken', $key, 0, 1800);

        $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . md5($this->username) . '_k2s.api.key';
        file_put_contents($temp_file, $key);
    }

    public function getAuthToken()
    {
//        $cache = new Memcache();
//        $cache->addserver('127.0.0.1');
//        return $cache->get('Keep2ShareApiAuthToken');

        $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . md5($this->username) . '_k2s.api.key';
        return is_file($temp_file)? file_get_contents($temp_file) : false;

    }

}
