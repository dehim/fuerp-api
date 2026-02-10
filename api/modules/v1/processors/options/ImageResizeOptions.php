<?php

namespace api\modules\v1\processors\options;

use yii\web\BadRequestHttpException;

class ImageResizeOptions
{
    public string $mode; // original | proportional | custom

    // 保存原始对象，保持 proportional 和 custom 原样
    public ?object $proportional = null;
    public ?object $custom = null;

    /**
     * 从数组构建 ImageResizeOptions
     *
     * 支持三种模式：
     * - original: 保持原图尺寸
     * - proportional: 按比例缩放
     * - custom: 自定义宽高
     *
     * @param array $data
     *
     * @throws BadRequestHttpException
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        if (empty($data['mode'])) {
            throw new BadRequestHttpException('resize.mode is required');
        }

        $opt = new self();
        $opt->mode = $data['mode'];

        switch ($opt->mode) {
            case 'proportional':
                if (empty($data['proportional']) || !is_array($data['proportional'])) {
                    throw new BadRequestHttpException('Invalid proportional resize options');
                }
                $opt->proportional = (object)$data['proportional'];
                $opt->custom = null;
                break;

            case 'custom':
                if (empty($data['custom']) || !is_array($data['custom'])) {
                    throw new BadRequestHttpException('Invalid custom resize options');
                }
                $opt->custom = (object)$data['custom'];
                $opt->proportional = null;
                break;

            case 'original':
                $opt->custom = null;
                $opt->proportional = null;
                break;

            default:
                throw new BadRequestHttpException('Unknown resize mode: ' . $opt->mode);
        }

        return $opt;
    }
}
