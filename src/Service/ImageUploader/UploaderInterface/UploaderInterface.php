<?php


namespace App\Service\ImageUploader\UploaderInterface;


use Symfony\Component\HttpFoundation\File\UploadedFile;

interface UploaderInterface
{
    /**
     * @param UploadedFile $file
     * @return void
     */
    public function setFile(UploadedFile $file);

    /**
     * @return mixed
     */
    public function prepareFile();

    /**
     * @return bool
     */
    public function saveFile();

    /**
     * @return void
     */
    public function checkFile();
}