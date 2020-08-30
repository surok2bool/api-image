<?php


namespace App\Service\ImageUploader;


use App\Service\ImageUploader\UploaderInterface\UploaderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class AbstractUploader implements UploaderInterface
{
    /**
     * @var UploadedFile $file
     */
    protected $file;

    /**
     * @param UploadedFile $file
     * @return void
     */
    public function setFile(UploadedFile $file): void
    {
        $this->file = $file;
    }

    /**
     * @return void
     */
    abstract public function prepareFile();

    /**
     * @return bool
     */
    abstract public function saveFile();

    /**
     * @return void
     */
    abstract public function checkFile();
}