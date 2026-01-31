<?php

namespace api\components;

use api\exceptions\BusinessException;
use Yii;

class ApiAuth
{
    // 时间允许误差（秒）
    protected const TIMESTAMP_DIFF = 300;

    /**
     * 验证签名
     *
     * @throws BusinessException
     */
    public static function checkSignature(array $context): void
    {
        $params = $context['params'] ?? [];
        $method = $context['method'] ?? '';
        $path = $context['path'] ?? '';

        $appKey = $params['appKey'] ?? null;
        $timestamp = $params['timestamp'] ?? null;
        $signature = $params['signature'] ?? null;
        $nonce = $params['nonce'] ?? null;

        // ====== 基础参数校验 ======
        if (!$appKey || !$timestamp || !$signature) {
            throw new BusinessException(
                ApiCode::PARAM_MISSING,
                '缺少 appKey / timestamp / signature'
            );
        }

        if (!$nonce) {
            throw new BusinessException(
                ApiCode::PARAM_MISSING,
                '缺少 nonce'
            );
        }

        // ====== 客户端校验 ======
        $clients = Yii::$app->params['apiClients'] ?? [];
        if (!isset($clients[$appKey])) {
            throw new BusinessException(ApiCode::UNAUTHORIZED, '未知客户端');
        }

        // ====== 时间戳校验 ======
        if (abs(time() - (int)$timestamp) > self::TIMESTAMP_DIFF) {
            throw new BusinessException(ApiCode::UNAUTHORIZED, '请求已过期');
        }

        $appSecret = $clients[$appKey];

        // ====== 签名校验 ======
        $data = $params;
        unset($data['signature']);

        // ⚠️ 稳定排序（关键）
        ksort($data);

        $canonicalQuery = http_build_query(
            $data,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        // ====== StringToSign ======
        $stringToSign = implode("\n", [
            strtoupper($method),
            $path,
            $canonicalQuery,
        ]);

        // ====== HMAC-SHA256 ======
        $calculated = hash_hmac('sha256', $stringToSign, $appSecret);

        if (!hash_equals($calculated, $signature)) {
            throw new BusinessException(ApiCode::UNAUTHORIZED, '签名验证失败');
        }

        // ====== nonce 防重放 ======
        self::checkAndStoreNonce($appKey, $nonce);
    }

    /**
     * nonce 校验与存储（Redis）
     */
    protected static function checkAndStoreNonce(string $appKey, string $nonce): void
    {
        $redis = Yii::$app->redis;

        $key = "api:nonce:{$appKey}:{$nonce}";

        // 已使用过
        if ($redis->exists($key)) {
            throw new BusinessException(
                ApiCode::UNAUTHORIZED,
                '请求已被重放'
            );
        }

        // 写入 nonce（TTL 与 timestamp 一致）
        $redis->setex($key, self::TIMESTAMP_DIFF, 1);
    }
}
