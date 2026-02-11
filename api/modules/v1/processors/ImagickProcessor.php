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
        try {

            if (!class_exists(Imagick::class)) {
                throw new ImageProcessException('Imagick not installed');
            }

            $raw = json_decode($task->options, true);

            if (
                !$raw ||
                !isset($raw['asset_snapshot']['storage_path']) ||
                !isset($raw['process_options'])
            ) {
                throw new ImageProcessException("Invalid task options structure");
            }

            $inputPath = $raw['asset_snapshot']['storage_path'];
            $processOptions = $raw['process_options'];

            if (!is_file($inputPath)) {
                throw new ImageProcessException("Input file not found: {$inputPath}");
            }

            $options = ImageOptions::fromArray($processOptions);

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

            if ($options->resize !== null) {
                $this->applyResize($imagick, $options->resize);
            }

            if ($options->quality !== null) {
                $imagick->setImageCompressionQuality($options->quality);
            }

            if ($options->keepExif === false) {
                $imagick->stripImage();
            }

            $format = $options->format ?? 'original';

            if ($format !== 'original') {
                $imagick->setImageFormat($format);
            }

            $outputPath = $this->buildOutputPath(
                $task->id,
                $inputPath,
                $format
            );

            $imagick->writeImage($outputPath);
            $imagick->clear();

            return new ImageProcessResult(
                $outputPath,
                filesize($outputPath)
            );

        } catch (\Throwable $e) {

            throw $e;
        }
    }

    private function applyResize(Imagick $imagick, $resize): void
    {
        $origW = $imagick->getImageWidth();
        $origH = $imagick->getImageHeight();

        switch ($resize->mode) {

            case 'original':
                return;

            case 'custom':
                if (!$resize->custom) {
                    throw new \RuntimeException('Custom resize options missing');
                }

                $w = max(1, intval($resize->custom->width ?? $origW));
                $h = max(1, intval($resize->custom->height ?? $origH));

                $imagick->resizeImage(
                    $w,
                    $h,
                    Imagick::FILTER_LANCZOS,
                    1,
                    false
                );

                return;

            case 'proportional':
                if (!$resize->proportional) {
                    throw new \RuntimeException('Proportional resize options missing');
                }

                $pro = $resize->proportional;
                $type = $pro->type ?? 'long-edge';
                $value = $pro->value ?? max($origW, $origH);

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
                            throw new \RuntimeException("Invalid scale");
                        }
                        $w = intval($origW * $scale / 100);
                        $h = intval($origH * $scale / 100);
                        break;

                    default:
                        throw new \RuntimeException("Unknown resize type");
                }

                $w = max(1, $w);
                $h = max(1, $h);

                $imagick->resizeImage(
                    $w,
                    $h,
                    Imagick::FILTER_LANCZOS,
                    1
                );

                return;

            default:
                throw new \RuntimeException("Unknown resize mode");
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
        string $taskId,
        string $inputPath,
        string $format
    ): string {
        $dir = dirname($inputPath);

        $ext = $format === 'original'
            ? pathinfo($inputPath, PATHINFO_EXTENSION)
            : $format;

        return $dir . '/out_' . $taskId . '.' . $ext;
    }
}
