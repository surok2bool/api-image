<?php


namespace App\Service;


use App\Service\ImageUploader\JpegImageUploader;
use App\Service\ImageUploader\PngImageUploader;
use App\Service\ImageUploader\UploaderException\FileTypeException;
use App\Service\ImageUploader\UploaderInterface\UploaderInterface;

class FileUploaderFactory
{
    /**
     * @var JpegImageUploader $jpegImageUploader
     */
    private $jpegImageUploader;

    /**
     * @var PngImageUploader $pngImageUploader
     */
    private $pngImageUploader;

    /**
     * FileUploaderFactory constructor.
     * @param JpegImageUploader $jpegImageUploader
     * @param PngImageUploader $pngImageUploader
     */
    public function __construct(
        JpegImageUploader $jpegImageUploader,
        PngImageUploader $pngImageUploader
    )
    {
        $this->jpegImageUploader = $jpegImageUploader;
        $this->pngImageUploader = $pngImageUploader;
    }

    /**
     * @param string $type
     * @return UploaderInterface
     * @throws FileTypeException
     */
    public function getUploader(string $type): UploaderInterface
    {
        switch ($type) {
            case JpegImageUploader::TYPE:
                $uploader = $this->jpegImageUploader;
                break;
            case PngImageUploader::TYPE:
                $uploader = $this->pngImageUploader;
                break;
            default:
                throw new FileTypeException('This type is not supported');
        }
        return $uploader;
    }
}