<?php

namespace api\modules\v1\processors\options;

use yii\web\BadRequestHttpException;

class ImageOptions
{
    public int $quality = 80;
    public string $format = 'original';
    public bool $keepExif = true;
    public ?ImageResizeOptions $resize = null;

    public static function fromJson(?string $json): self
    {
        if (empty($json)) {
            throw new BadRequestHttpException('Task options is empty');
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('Invalid options json');
        }

        $opt = new self();

        if (isset($data['quality'])) {
            $opt->quality = max(1, min(100, (int)$data['quality']));
        }

        if (!empty($data['format'])) {
            $opt->format = (string)$data['format'];
        }

        if (isset($data['keepExif'])) {
            $opt->keepExif = (bool)$data['keepExif'];
        }

        if (!empty($data['resize'])) {
            $opt->resize = ImageResizeOptions::fromArray($data['resize']);
        }

        return $opt;
    }
}
