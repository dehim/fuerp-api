<?php

namespace api\modules\v1\processors\options;

use yii\web\BadRequestHttpException;

class ImageResizeOptions
{
    public string $mode; // original | proportional | custom

    // proportional
    public ?string $type = null; // width | height | long-edge | short-edge
    public ?int $value = null;

    // custom
    public ?int $width = null;
    public ?int $height = null;

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
                $p = $data['proportional'] ?? null;
                if (!$p || empty($p['type']) || empty($p['value'])) {
                    throw new BadRequestHttpException('Invalid proportional resize options');
                }
                $opt->type = (string)$p['type'];
                $opt->value = (int)$p['value'];
                break;

            case 'custom':
                $c = $data['custom'] ?? null;
                if (!$c || empty($c['width']) || empty($c['height'])) {
                    throw new BadRequestHttpException('Invalid custom resize options');
                }
                $opt->width = (int)$c['width'];
                $opt->height = (int)$c['height'];
                break;

            case 'original':
                // 原图模式，不修改尺寸，保持默认 null
                $opt->type = null;
                $opt->value = null;
                $opt->width = null;
                $opt->height = null;
                break;

            default:
                throw new BadRequestHttpException('Unknown resize mode: ' . $opt->mode);
        }

        return $opt;
    }
}
