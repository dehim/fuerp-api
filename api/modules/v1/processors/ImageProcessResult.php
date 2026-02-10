<?php

namespace api\modules\v1\processors;

class ImageProcessResult
{
    public function __construct(
        public string $outputPath,
        public int    $outputSize,
    ) {
    }
}
