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

            $optionsRaw = json_decode($task->options, true);

            if (!$optionsRaw) {
                throw new ImageProcessException("Task options invalid");
            }

            $assetSnapshot = $optionsRaw['asset_snapshot'] ?? null;
            $processOptions = $optionsRaw['process_options'] ?? null;

            if (!$assetSnapshot || !$processOptions) {
                throw new ImageProcessException("Missing asset_snapshot or process_options");
            }

            $inputPath = $assetSnapshot['storage_path'] ?? null;

            if (!$inputPath || !is_file($inputPath)) {
                throw new ImageProcessException("Input file not found");
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

            $outputPath = $this->buildOutputPath($task, $format);

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
                // echo "[DEBUG] 维持原尺寸\n";

                return;

            case 'custom':
                if (!$resize->custom) {
                    throw new \RuntimeException('Custom resize options missing');
                }
                $w = max(1, intval($resize->custom->width ?? $origW));
                $h = max(1, intval($resize->custom->height ?? $origH));
                // echo "[DEBUG] 自定义尺寸，w={$w}, h={$h}\n";

                $imagick->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1, false);

                return;

            case 'proportional':
                if (!$resize->proportional) {
                    throw new \RuntimeException('Proportional resize options missing');
                }
                $pro = $resize->proportional;

                $type = $pro->type ?? 'long-edge';
                $value = $pro->value ?? max($origW, $origH);

                // echo "[DEBUG] 按比例缩放，原始值 w={$origW}, h={$origH}, type={$type}, value={$value}\n";

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
                            throw new \RuntimeException('Invalid scale value: ' . $value);
                        }
                        $w = intval($origW * $scale / 100);
                        $h = intval($origH * $scale / 100);
                        break;

                    default:
                        throw new \RuntimeException('Unknown proportional type: ' . $type);
                }

                $w = max(1, $w);
                $h = max(1, $h);
                // echo "[DEBUG] 按比例缩放后尺寸 w={$w}, h={$h}\n";

                $imagick->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);

                return;

            default:
                throw new \RuntimeException('Unknown resize mode: ' . $resize->mode);
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

    private function buildOutputPath(Task $task, string $format): string
    {
        $optionsRaw = json_decode($task->options, true);
        $assetSnapshot = $optionsRaw['asset_snapshot'];
        $inputPath = $assetSnapshot['storage_path'];

        $dir = dirname($inputPath);

        $ext = $format === 'original'
            ? pathinfo($inputPath, PATHINFO_EXTENSION)
            : $format;

        return $dir . '/out_' . $task->id . '.' . $ext;
    }
}
