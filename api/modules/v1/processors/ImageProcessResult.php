<?php

namespace api\modules\v1\processors;

class ImageProcessResult
{
    public string $outputPath;
    public int $outputSize;

    public function __construct(string $outputPath, int $outputSize)
    {
        $this->outputPath = $outputPath;
        $this->outputSize = $outputSize;
    }
}
