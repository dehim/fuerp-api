<?php

namespace api\modules\v1\processors\options;

use yii\web\BadRequestHttpException;

class ImageResizeOptions
{
    public string $mode; // original | proportional | custom
    public ?object $proportional = null;
    public ?object $custom = null;

    public static function fromArray(array $data): self
    {
        if (empty($data['mode'])) {
            throw new BadRequestHttpException('resize.mode is required');
        }

        $opt = new self();
        $opt->mode = $data['mode'];

        switch ($opt->mode) {

            case 'original':
                break;

            case 'proportional':
                if (empty($data['proportional']) || !is_array($data['proportional'])) {
                    throw new BadRequestHttpException('Invalid proportional resize options');
                }
                $opt->proportional = (object)$data['proportional'];
                break;

            case 'custom':
                if (empty($data['custom']) || !is_array($data['custom'])) {
                    throw new BadRequestHttpException('Invalid custom resize options');
                }
                $opt->custom = (object)$data['custom'];
                break;

            default:
                throw new BadRequestHttpException('Unknown resize mode: ' . $opt->mode);
        }

        return $opt;
    }
}
