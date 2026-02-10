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
            echo "[DEBUG] process() entered\n";

            if (!class_exists(Imagick::class)) {
                throw new ImageProcessException('Imagick not installed');
            }
            echo "[DEBUG] Imagick class exists\n";

            if (!is_file($task->input_path)) {
                throw new ImageProcessException('Input file not found');
            }
            echo "[DEBUG] input file exists: {$task->input_path}\n";

            echo "[DEBUG] raw options=" . var_export($task->options, true) . "\n";
            $options = ImageOptions::fromJson($task->options);
            echo "[DEBUG] options parsed\n";
            echo "[DEBUG] options=" . json_encode($options, JSON_UNESCAPED_SLASHES) . "\n";

            $imagick = new Imagick();
            echo "[DEBUG] Imagick object created\n";

            $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::MAX_MEMORY_MB);
            $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, self::MAX_MEMORY_MB);
            echo "[DEBUG] resource limits set\n";

            $imagick->readImage($task->input_path);
            echo "[DEBUG] image read\n";

            $this->guardImageSize($imagick);
            echo "[DEBUG] image size ok\n";

            if ($options->resize !== null) {
                echo "[DEBUG] applying resize\n";
                $this->applyResize($imagick, $options->resize);
                echo "[DEBUG] resize done\n";
            }

            if ($options->quality !== null) {
                echo "[DEBUG] setting quality={$options->quality}\n";
                $imagick->setImageCompressionQuality($options->quality);
                echo "[DEBUG] quality set\n";
            }

            if ($options->keepExif === false) {
                echo "[DEBUG] stripping exif\n";
                $imagick->stripImage();
                echo "[DEBUG] exif stripped\n";
            }

            $format = $options->format ?? 'original';
            echo "[DEBUG] format={$format}\n";

            if ($format !== 'original') {
                $imagick->setImageFormat($format);
                echo "[DEBUG] format set\n";
            }

            $outputPath = $this->buildOutputPath($task, $format);
            echo "[DEBUG] outputPath={$outputPath}\n";

            $imagick->writeImage($outputPath);
            echo "[DEBUG] image written\n";

            $imagick->clear();
            echo "[DEBUG] imagick cleared\n";

            return new ImageProcessResult($outputPath, filesize($outputPath));
        } catch (\Throwable $e) {
            echo "[FATAL] " . get_class($e) . "\n";
            echo "[FATAL] " . $e->getMessage() . "\n";
            echo "[FATAL] " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo $e->getTraceAsString() . "\n";

            throw $e; // 继续让 task 失败
        }
    }

    private function applyResize(Imagick $imagick, $resize): void
    {
        $origW = $imagick->getImageWidth();
        $origH = $imagick->getImageHeight();

        switch ($resize->mode) {
            case 'original':
                echo "[DEBUG] 维持原尺寸\n";

                return;

            case 'custom':

                echo "[DEBUG] 自定义尺寸，w=" . $resize->custom->width . ",h=" . $resize->custom->height . "\n";
                $w = $resize->custom->width ?? $origW;
                $h = $resize->custom->height ?? $origH;

                // 保证尺寸大于0
                $w = max(1, intval($w));
                $h = max(1, intval($h));

                $imagick->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1, false);

                return;

            case 'proportional':
                echo "[DEBUG] 按比例缩放，原始值 w=" . $origW . ",h=" . $origH . "\n";
                $w = $origW;
                $h = $origH;
                $type = $resize->proportional->type ?? 'long-edge';
                $value = $resize->proportional->value ?? max($origW, $origH);
                echo "[DEBUG] 按比例缩放，type=" . $type . ",value=" . $value . "\n";

                if ($type === 'width') {
                    $w = intval($value);
                    $h = intval($origH * ($w / $origW));
                    echo "[DEBUG] 按比例缩放，type=" . $type . ",w=" . $w . ", h=" . $h . "\n";
                } elseif ($type === 'height') {
                    $h = intval($value);
                    $w = intval($origW * ($h / $origH));
                    echo "[DEBUG] 按比例缩放，type=" . $type . ",w=" . $w . ", h=" . $h . "\n";
                } elseif ($type === 'long-edge') {
                    if ($origW >= $origH) {
                        $w = intval($value);
                        $h = intval($origH * ($w / $origW));
                        echo "[DEBUG] 按比例缩放，type=" . $type . ",w=" . $w . ", h=" . $h . "\n";
                    } else {
                        $h = intval($value);
                        $w = intval($origW * ($h / $origH));
                        echo "[DEBUG] 按比例缩放，type=" . $type . ",w=" . $w . ", h=" . $h . "\n";
                    }
                } elseif ($type === 'short-edge') {
                    if ($origW <= $origH) {
                        $w = intval($value);
                        $h = intval($origH * ($w / $origW));
                        echo "[DEBUG] 按比例缩放，type=" . $type . ",w=" . $w . ", h=" . $h . "\n";
                    } else {
                        $h = intval($value);
                        $w = intval($origW * ($h / $origH));
                        echo "[DEBUG] 按比例缩放，type=" . $type . ",w=" . $w . ", h=" . $h . "\n";
                    }
                } elseif ($type === 'scale') {
                    $scale = floatval($value);
                    if ($scale <= 0) {
                        throw new \RuntimeException('Invalid scale value: ' . $value);
                    }
                    $w = intval($origW * $scale / 100);
                    $h = intval($origH * $scale / 100);
                    echo "[DEBUG] 按比例缩放，type=" . $type . ",w=" . $w . ", h=" . $h . "\n";
                } else {
                    throw new \RuntimeException('Unknown proportional type: ' . $type);
                }

                $w = max(1, $w);
                $h = max(1, $h);

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
        $dir = dirname($task->input_path);

        $ext = $format === 'original'
            ? pathinfo($task->input_path, PATHINFO_EXTENSION)
            : $format;

        return $dir . '/out_' . $task->id . '.' . $ext;
    }
}
