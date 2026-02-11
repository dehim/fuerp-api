<?php

namespace api\modules\v1\processors;

use api\modules\v1\models\Task;
use api\modules\v1\processors\exceptions\ImageProcessException;
use api\modules\v1\processors\options\ImageOptions;
use Imagick;

class ImagickProcessor implements ImageProcessorInterface
{
    private const MAX_PIXELS = 60000000; // ~8000x8000
    private const MAX_MEMORY_MB = 256;

    public function process(Task $task): ImageProcessResult
    {
        if (!class_exists(Imagick::class)) {
            throw new ImageProcessException('Imagick not installed');
        }

        $raw = json_decode($task->options, true);

        if (!is_array($raw)) {
            throw new ImageProcessException('Invalid task options json');
        }

        $assetSnapshot = $raw['asset_snapshot'] ?? null;
        $processOptions = $raw['process_options'] ?? null;

        if (!$assetSnapshot || !$processOptions) {
            throw new ImageProcessException('Missing asset_snapshot or process_options');
        }

        $inputPath = $assetSnapshot['storage_path'] ?? null;

        if (!$inputPath || !is_file($inputPath)) {
            throw new ImageProcessException('Input file not found');
        }

        // ⭐ 统一交给 ImageOptions 解析
        $imageOptions = ImageOptions::fromJson(
            json_encode($processOptions, JSON_UNESCAPED_UNICODE)
        );

        $imagick = new Imagick();

        $imagick->setResourceLimit(
            Imagick::RESOURCETYPE_MEMORY,
            self::MAX_MEMORY_MB
        );
        $imagick->setResourceLimit(
            Imagick::RESOURCETYPE_MAP,
            self::MAX_MEMORY_MB
        );

        $imagick->readImage($inputPath);

        $this->guardImageSize($imagick);

        // resize
        if ($imageOptions->resize !== null) {
            $this->applyResize($imagick, $imageOptions->resize);
        }

        // quality
        if ($imageOptions->quality !== null) {
            $imagick->setImageCompressionQuality($imageOptions->quality);
        }

        // exif
        if ($imageOptions->keepExif === false) {
            $imagick->stripImage();
        }

        // format
        $format = $imageOptions->format ?? 'original';

        if ($format !== 'original') {
            $imagick->setImageFormat($format);
        }

        $outputPath = $this->buildOutputPath($task, $inputPath, $format);

        $imagick->writeImage($outputPath);
        $imagick->clear();

        return new ImageProcessResult(
            $outputPath,
            filesize($outputPath)
        );
    }

    private function applyResize(Imagick $imagick, $resize): void
    {
        $origW = $imagick->getImageWidth();
        $origH = $imagick->getImageHeight();

        switch ($resize->mode) {

            case 'original':
                return;

            case 'custom':
                $w = max(1, intval($resize->custom->width ?? $origW));
                $h = max(1, intval($resize->custom->height ?? $origH));
                $imagick->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);

                return;

            case 'proportional':
                $pro = $resize->proportional;
                $type = $pro->type ?? 'long-edge';
                $value = $pro->value ?? max($origW, $origH);

                $w = $origW;
                $h = $origH;

                switch ($type) {

                    case 'width':
                        $w = intval($value);
                        $h = intval($origH * ($w / $origW));
                        break;

                    case 'height':
                        $h = intval($value);
                        $w = intval($origW * ($h / $origH));
                        break;

                    case 'long-edge':
                        if ($origW >= $origH) {
                            $w = intval($value);
                            $h = intval($origH * ($w / $origW));
                        } else {
                            $h = intval($value);
                            $w = intval($origW * ($h / $origH));
                        }
                        break;

                    case 'short-edge':
                        if ($origW <= $origH) {
                            $w = intval($value);
                            $h = intval($origH * ($w / $origW));
                        } else {
                            $h = intval($value);
                            $w = intval($origW * ($h / $origH));
                        }
                        break;

                    case 'scale':
                        $scale = floatval($value);
                        if ($scale <= 0) {
                            throw new ImageProcessException('Invalid scale value');
                        }
                        $w = intval($origW * $scale / 100);
                        $h = intval($origH * $scale / 100);
                        break;

                    default:
                        throw new ImageProcessException('Unknown proportional type');
                }

                $imagick->resizeImage(
                    max(1, $w),
                    max(1, $h),
                    Imagick::FILTER_LANCZOS,
                    1
                );

                return;

            default:
                throw new ImageProcessException('Unknown resize mode');
        }
    }

    private function guardImageSize(Imagick $imagick): void
    {
        $w = $imagick->getImageWidth();
        $h = $imagick->getImageHeight();

        if ($w * $h > self::MAX_PIXELS) {
            throw new ImageProcessException("Image too large: {$w}x{$h}");
        }
    }

    private function buildOutputPath(
        Task $task,
        string $inputPath,
        string $format
    ): string {
        $dir = dirname($inputPath);

        $ext = $format === 'original'
            ? pathinfo($inputPath, PATHINFO_EXTENSION)
            : $format;

        return $dir . '/out_' . $task->id . '.' . $ext;
    }
}
