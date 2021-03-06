<?php

namespace Fundub\LaravelSaProvider;

use DateTime;
use Fundub\LaravelSaProvider\Exceptions\SensorsAnalyticsException;
use Fundub\LaravelSaProvider\Exceptions\SensorsAnalyticsIllegalDataException;

class SensorsAnalytics
{

    private $_consumer;
    private $_super_properties;
    private $_is_win;
    private $_project_name;

    const SENSORS_ANALYTICS_SDK_VERSION = '1.10.1';

    /*
     * 为兼容旧版，实现构造函数重载
     */
    public function __construct() {
        $a = func_get_args(); //获取构造函数中的参数
        $i = count($a);
        if (method_exists($this,$f='__construct'.$i)) {
            call_user_func_array(array($this,$f),$a);
        }
    }

    /**
     * 初始化一个 SensorsAnalytics 的实例用于数据发送。
     *
     * @param AbstractConsumer $consumer
     * @param AbstractConsumer $project_name
     */
    public function __construct2($consumer, $project_name) {
        $this->_is_win = false;
        // 不支持 Windows，因为 Windows 版本的 PHP 都不支持 long
        if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
            $this->_is_win = true;
        }
        $this->_consumer = $consumer;
        $this->_project_name = $project_name;
        $this->clear_super_properties();
    }

    /**
     * 初始化一个 SensorsAnalytics 的实例用于数据发送。
     *
     * @param AbstractConsumer $consumer
     * @param AbstractConsumer $project_name
     */
    public function __construct1($consumer) {
        $this->_is_win = false;
        // 不支持 Windows，因为 Windows 版本的 PHP 都不支持 long
        if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
            $this->_is_win = true;
        }
        $this->_consumer = $consumer;
        $this->_project_name = null;
        $this->clear_super_properties();
    }

    public function getConsumer()
    {
        return $this->_consumer;
    }

    private function _assert_key_with_regex($key) {
        $name_pattern = "/^((?!^distinct_id$|^original_id$|^time$|^properties$|^id$|^first_id$|^second_id$|^users$|^events$|^event$|^user_id$|^date$|^datetime$)[a-zA-Z_$][a-zA-Z\\d_$]{0,99})$/i";
        if (!preg_match($name_pattern, $key)) {
            throw new SensorsAnalyticsIllegalDataException("key must be a valid variable key. [key='${key}']");
        }
    }

    private function _assert_key($key) {
        if (strlen($key) == 0) {
            throw new SensorsAnalyticsIllegalDataException("key must not be empty");
        }
        if (strlen($key) > 255) {
            throw new SensorsAnalyticsIllegalDataException("the max length of key is 255");
        }
    }

    private function _assert_properties($properties = array()) {
        $name_pattern = "/^((?!^distinct_id$|^original_id$|^time$|^properties$|^id$|^first_id$|^second_id$|^users$|^events$|^event$|^user_id$|^date$|^datetime$)[a-zA-Z_$][a-zA-Z\\d_$]{0,99})$/i";

        if (!$properties) {
            return;
        }

        foreach ($properties as $key => $value) {
            if (!is_string($key)) {
                throw new SensorsAnalyticsIllegalDataException("property key must be a str. [key=$key]");
            }
            if (strlen($key) > 255) {
                throw new SensorsAnalyticsIllegalDataException("the max length of property key is 256. [key=$key]");
            }

            if (!preg_match($name_pattern, $key)) {
                throw new SensorsAnalyticsIllegalDataException("property key must be a valid variable name. [key='$key']]");
            }

            // 只支持简单类型或数组或DateTime类
            if (!is_scalar($value) && !is_array($value) && !$value instanceof DateTime) {
                throw new SensorsAnalyticsIllegalDataException("property value must be a str/int/float/datetime/list. [key='$key']");
            }

            // 如果是 DateTime，Format 成字符串
            if ($value instanceof DateTime) {
                $data['properties'][$key] = $value->format("Y-m-d H:i:s.0");
            }

            if (is_string($value) && strlen($value) > 8191) {
                throw new SensorsAnalyticsIllegalDataException("the max length of property value is 8191. [key=$key]");
            }

            // 如果是数组，只支持 Value 是字符串格式的简单非关联数组
            if (is_array($value)) {
                if (array_values($value) !== $value) {
                    throw new SensorsAnalyticsIllegalDataException("[list] property must not be associative. [key='$key']");
                }

                foreach ($value as $lvalue) {
                    if (!is_string($lvalue)) {
                        throw new SensorsAnalyticsIllegalDataException("[list] property's value must be a str. [value='$lvalue']");
                    }
                }
            }
        }
    }

    private function _normalize_data($data) {
        // 检查 distinct_id
        if (!isset($data['distinct_id']) or strlen($data['distinct_id']) == 0) {
            throw new SensorsAnalyticsIllegalDataException("property [distinct_id] must not be empty");
        }
        if (strlen($data['distinct_id']) > 255) {
            throw new SensorsAnalyticsIllegalDataException("the max length of [distinct_id] is 255");
        }
        $data['distinct_id'] = strval($data['distinct_id']);

        // 检查 time
        if ($this->_is_win) { // windows use string(windows 32bit do not support int64)
            if (!is_string($data['time'])) {
                throw new SensorsAnalyticsIllegalDataException("property [time] type must be string");
            }
            $ts = $data['time'];
            $ts_num = strlen($ts);
            if (strlen($ts_num) == 15) {
                $ts = substr($ts, 0, 13);
            }

            if ($ts_num < 10 || $ts_num > 13) {
                throw new SensorsAnalyticsIllegalDataException("property [time] must be a timestamp in microseconds");
            }

            if ($ts_num == 10) {
                $ts .= "000";
            }
        } else { // linux use int
            $ts = (int)($data['time']);
            $ts_num = strlen($ts);
            if ($ts_num < 10 || $ts_num > 13) {
                throw new SensorsAnalyticsIllegalDataException("property [time] must be a timestamp in microseconds");
            }

            if ($ts_num == 10) {
                $ts *= 1000;
            }
        }
        $data['time'] = $ts;

        // 检查 Event Name
        if (isset($data['event'])) {
            $this->_assert_key_with_regex($data['event']);
        }

        // 检查 properties
        if (isset($data['properties']) && is_array($data['properties'])) {
            $this->_assert_properties($data['properties']);

            // XXX: 解决 PHP 中空 array() 转换成 JSON [] 的问题
            if (count($data['properties']) == 0) {
                $data['properties'] = new \ArrayObject();
            }
        } else {
            throw new SensorsAnalyticsIllegalDataException("property must be an array.");
        }
        return $data;
    }

    /**
     * 如果用户传入了 $time 字段，则不使用当前时间。
     *
     * @param array $properties
     * @return int/string
     */
    private function _extract_user_time(&$properties = array()) {
        if (array_key_exists('$time', $properties)) {
            $time = $properties['$time'];
            unset($properties['$time']);
            return $time;
        }
        if ($this->_is_win) { // windows return string
            return substr((microtime(true) * 1000), 0, 13);
        } else {
            return (int)(microtime(true) * 1000);
        }
    }

    /**
     * 返回埋点管理相关属性，由于该函数依赖函数栈信息，因此修改调用关系时，一定要谨慎
     */
    private function _get_lib_properties() {
        $lib_properties = array(
            '$lib' => 'php',
            '$lib_version' => self::SENSORS_ANALYTICS_SDK_VERSION,
            '$lib_method' => 'code',
        );

        if (isset($this->_super_properties['$app_version'])) {
            $lib_properties['$app_version'] = $this->_super_properties['$app_version'];
        }

        try {
            throw new \Exception("");
        } catch (\Exception $e) {
            $trace = $e->getTrace();
            if (count($trace) == 3) {
                // 脚本内直接调用
                $file = $trace[2]['file'];
                $line = $trace[2]['line'];

                $lib_properties['$lib_detail'] = "####$file##$line";
            } else if (count($trace) > 3) {
                if (isset($trace[3]['class'])) {
                    // 类成员函数内调用
                    $class = $trace[3]['class'];
                } else {
                    // 全局函数内调用
                    $class = '';
                }

                // XXX: 此处使用 [2] 非笔误，trace 信息就是如此
                $file = $trace[2]['file'];
                $line = $trace[2]['line'];
                $function = $trace[3]['function'];

                $lib_properties['$lib_detail'] = "$class##$function##$file##$line";
            }
        }

        return $lib_properties;
    }

    /**
     * 序列化 JSON
     *
     * @param $data
     * @return string
     */
    private function _json_dumps($data) {
        return json_encode($data);
    }

    /**
     * 跟踪一个用户的行为。
     *
     * @param string $distinct_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param string $event_name 事件名称。
     * @param array $properties 事件的属性。
     * @return bool
     */
    public function track($distinct_id, $is_login_id, $event_name, $properties = array()) {
        if(empty($distinct_id)) return false;
        if (!is_string($event_name)) {
            throw new SensorsAnalyticsIllegalDataException("event name must be a str.");
        }
        if (!is_bool($is_login_id)) {
            throw new SensorsAnalyticsIllegalDataException("is_login_id must be a bool.");
        }
        if ($properties) {
            $all_properties = array_merge($this->_super_properties, $properties);
        } else {
            $all_properties = array_merge($this->_super_properties, array());
        }
        return $this->_track_event('track', $event_name, $distinct_id, $is_login_id, null, $all_properties);
    }

    /**
     * 这个接口是一个较为复杂的功能，请在使用前先阅读相关说明:http://www.sensorsdata.cn/manual/track_signup.html，并在必要时联系我们的技术支持人员。
     *
     * @param string $distinct_id 用户注册之后的唯一标识。
     * @param string $original_id 用户注册前的唯一标识。
     * @param array $properties 事件的属性。
     * @return bool
     * @throws SensorsAnalyticsIllegalDataException
     */
    public function track_signup($distinct_id, $original_id, $properties = array()) {
        if ($properties) {
            $all_properties = array_merge($this->_super_properties, $properties);
        } else {
            $all_properties = array_merge($this->_super_properties, array());
        }
        // 检查 original_id
        if (!$original_id or strlen($original_id) == 0) {
            throw new SensorsAnalyticsIllegalDataException("property [original_id] must not be empty");
        }
        if (strlen($original_id) > 255) {
            throw new SensorsAnalyticsIllegalDataException("the max length of [original_id] is 255");
        }
        return $this->_track_event('track_signup', '$SignUp', $distinct_id, false, $original_id, $all_properties);
    }

    /**
     * 直接设置一个用户的 Profile，如果已存在则覆盖。
     *
     * @param string $distinct_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profiles
     * @return bool
     */
    public function profile_set($distinct_id, $is_login_id, $profiles = array()) {
        if (!is_bool($is_login_id)) {
            throw new SensorsAnalyticsIllegalDataException("is_login_id must be a bool.");
        }
        return $this->_track_event('profile_set', null, $distinct_id, $is_login_id, null, $profiles);
    }

    /**
     * 直接设置一个用户的 Profile，如果某个 Profile 已存在则不设置。
     *
     * @param string $distinct_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profiles
     * @return bool
     */
    public function profile_set_once($distinct_id, $is_login_id, $profiles = array()) {
        if (!is_bool($is_login_id)) {
            throw new SensorsAnalyticsIllegalDataException("is_login_id must be a bool.");
        }
        return $this->_track_event('profile_set_once', null, $distinct_id, $is_login_id, null, $profiles);
    }

    /**
     * 增减/减少一个用户的某一个或者多个数值类型的 Profile。
     *
     * @param string $distinct_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profiles
     * @return bool
     */
    public function profile_increment($distinct_id, $is_login_id, $profiles = array()) {
        if (!is_bool($is_login_id)) {
            throw new SensorsAnalyticsIllegalDataException("is_login_id must be a bool.");
        }
        return $this->_track_event('profile_increment', null, $distinct_id, $is_login_id, null, $profiles);
    }

    /**
     * 追加一个用户的某一个或者多个集合类型的 Profile。
     *
     * @param string $distinct_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profiles
     * @return bool
     */
    public function profile_append($distinct_id, $is_login_id, $profiles = array()) {
        if (!is_bool($is_login_id)) {
            throw new SensorsAnalyticsIllegalDataException("is_login_id must be a bool.");
        }
        return $this->_track_event('profile_append', null, $distinct_id, $is_login_id, null, $profiles);
    }

    /**
     * 删除一个用户的一个或者多个 Profile。
     *
     * @param string $distinct_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profile_keys
     * @return bool
     */
    public function profile_unset($distinct_id, $is_login_id, $profile_keys = array()) {
        if (!is_bool($is_login_id)) {
            throw new SensorsAnalyticsIllegalDataException("is_login_id must be a bool.");
        }
        if ($profile_keys != null && array_key_exists(0, $profile_keys)) {
            $new_profile_keys = array();
            foreach ($profile_keys as $key) {
                $new_profile_keys[$key] = true;
            }
            $profile_keys = $new_profile_keys;
        }
        return $this->_track_event('profile_unset', null, $distinct_id, $is_login_id, null, $profile_keys);
    }


    /**
     * 删除整个用户的信息。
     *
     * @param string $distinct_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @return bool
     */
    public function profile_delete($distinct_id, $is_login_id) {
        if (!is_bool($is_login_id)) {
            throw new SensorsAnalyticsIllegalDataException("is_login_id must be a bool.");
        }
        return $this->_track_event('profile_delete', null, $distinct_id, $is_login_id, null, array());
    }

    /**
     * 直接设置一个物品，如果已存在则覆盖。
     *
     * @param string $itemType item类型。
     * @param string $itemId item的唯一标识。
     * @param array $properties item属性
     * @return bool
     */
    public function item_set($item_type, $item_id, $properties = array()) {
        return $this->_track_item('item_set', $item_type, $item_id, $properties);
    }

    /**
     * 删除一个物品
     *
     * @param string $itemType item类型。
     * @param string $itemId item的唯一标识。
     * @param array $properties item属性
     * @return bool
     */
    public function item_delete($item_type, $item_id, $properties = array()) {
        return $this->_track_item('item_delete', $item_type, $item_id, $properties);
    }

    public function _track_item($action_type, $item_type, $item_id, $properties = array()) {
        $this->_assert_key_with_regex($item_type);
        $this->_assert_key($item_id);
        $this->_assert_properties($properties);

        $event_project = null;

        if ($properties && $properties['$project']) {
            $event_project = $properties['$project'];
            unset($properties['$project']);
        }

        $data = array(
            'type' => $action_type,
            'time' => (int)(microtime(true) * 1000),
            'properties' => $properties,
            'lib' => $this->_get_lib_properties(),
            'item_type' => $item_type,
            'item_id' => $item_id,
        );

        if ($this->_project_name) {
            $data['project'] = $this->_project_name;
        }

        if ($event_project) {
            $data['project'] = $event_project;
        }

        if (count($data['properties']) == 0) {
            $data['properties'] = new \ArrayObject();
        }

        return $this->_consumer->send($this->_json_dumps($data));
    }

    /**
     * 设置每个事件都带有的一些公共属性
     *
     * @param super_properties
     */
    public function register_super_properties($super_properties) {
        $this->_super_properties = array_merge($this->_super_properties, $super_properties);
    }

    /**
     * 删除所有已设置的事件公共属性
     */
    public function clear_super_properties() {
        $this->_super_properties = array(
            '$lib' => 'php',
            '$lib_version' => self::SENSORS_ANALYTICS_SDK_VERSION,
        );
    }

    /**
     * 对于不立即发送数据的 Consumer，调用此接口应当立即进行已有数据的发送。
     *
     */
    public function flush() {
        $this->_consumer->flush();
    }

    /**
     * 在进程结束或者数据发送完成时，应当调用此接口，以保证所有数据被发送完毕。
     * 如果发生意外，此方法将抛出异常。
     */
    public function close() {
        $this->_consumer->close();
    }

    /**
     * @param string $update_type
     * @param string $event_name
     * @param string $distinct_id
     * @param bool $is_login_id
     * @param string $original_id
     * @param array $properties
     * @return bool
     * @internal param array $profiles
     */
    public function _track_event($update_type, $event_name, $distinct_id, $is_login_id, $original_id, $properties) {
        $event_time = $this->_extract_user_time($properties);

        if ($is_login_id) {
            $properties['$is_login_id'] = true;
        }

        $data = array(
            'type' => $update_type,
            'properties' => $properties,
            'time' => $event_time,
            'distinct_id' => $distinct_id,
            'lib' => $this->_get_lib_properties(),
        );

        if ($this->_project_name) {
            $data['project'] = $this->_project_name;
        }

        if (strcmp($update_type, "track") == 0) {
            $data['event'] = $event_name;
        } else if (strcmp($update_type, "track_signup") == 0) {
            $data['event'] = $event_name;
            $data['original_id'] = $original_id;
        }

        $data = $this->_normalize_data($data);
        return $this->_consumer->send($this->_json_dumps($data));
    }
}