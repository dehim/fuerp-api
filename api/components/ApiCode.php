<?php

namespace api\components;

//final：防止被继承改逻辑
final class ApiCode
{
    /** 成功 */
    public const SUCCESS = 0;

    /* ========== 1xxxx 参数 / 请求错误 ========== */
    public const PARAM_MISSING = 10001;
    public const PARAM_INVALID = 10002;
    public const PARAM_FORMAT_ERROR = 10003;
    public const JSON_PARSE_ERROR = 10004;
    public const METHOD_NOT_ALLOWED = 10005;

    /* ========== 2xxxx 认证 / 登录 ========== */
    public const UNAUTHORIZED = 20001;
    public const TOKEN_EXPIRED = 20002;
    public const TOKEN_INVALID = 20003;
    public const LOGIN_FAILED = 20004;

    /* ========== 21xxx 权限 ========== */
    public const FORBIDDEN = 21001;
    public const PERMISSION_DENIED = 21002;

    /* ========== 3xxxx 业务规则 ========== */
    public const BUSINESS_ERROR = 30001;
    public const DUPLICATE_ACTION = 30002;
    public const STATUS_NOT_ALLOWED = 30003;

    /* ========== 4xxxx 资源 ========== */
    public const NOT_FOUND = 40001;
    public const ALREADY_EXISTS = 40002;
    public const RESOURCE_CONFLICT = 40003;

    /* ========== 5xxxx 系统错误 ========== */
    public const SYSTEM_ERROR = 50001;
    public const DB_ERROR = 50002;
    public const REDIS_ERROR = 50003;
    public const THIRD_PARTY_ERROR = 50004;

    /* ========== 9xxxx 兜底 ========== */
    public const UNKNOWN_ERROR = 99999;

    /**
     * ✅ ApiCode → HTTP Status（唯一事实源）
     */
    public static function httpStatus(int $code): int
    {
        return match ((int)floor($code / 10000)) {
            0 => 200,
            1 => 400,
            2 => 401,
            21 => 403,
            3 => 422,
            4 => 404,
            5 => 500,
            default => 500,
        };
    }
}
