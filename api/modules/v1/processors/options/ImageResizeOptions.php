<?php

namespace api\modules\v1\processors\options;

use yii\web\BadRequestHttpException;

class ImageResizeOptions
{
    public string $mode; // proportional | custom

    // proportional
    public ?string $type = null; // width | height
    public ?int $value = null;

    // custom
    public ?int $width = null;
    public ?int $height = null;

    public static function fromArray(array $data): self
    {
        if (empty($data['mode'])) {
            throw new BadRequestHttpException('resize.mode is required');
        }

        $opt = new self();
        $opt->mode = $data['mode'];

        if ($opt->mode === 'proportional') {
            $p = $data['proportional'] ?? null;
            if (!$p || empty($p['type']) || empty($p['value'])) {
                throw new BadRequestHttpException('Invalid proportional resize options');
            }

            $opt->type = $p['type'];
            $opt->value = (int)$p['value'];

        } elseif ($opt->mode === 'custom') {
            $c = $data['custom'] ?? null;
            if (!$c || empty($c['width']) || empty($c['height'])) {
                throw new BadRequestHttpException('Invalid custom resize options');
            }

            $opt->width = (int)$c['width'];
            $opt->height = (int)$c['height';

        } else {
            throw new BadRequestHttpException('Unknown resize mode');
        }

        return $opt;
    }
}
