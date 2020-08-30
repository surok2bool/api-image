<?php


namespace App\Service\ImageUploader;


use App\Service\ImageUploader\UploaderException\FileExistException;
use Imagick;
use ImagickException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploader extends AbstractUploader
{
    const ORIGINAL_PATH = 'origin';
    const HANDLED_PATH = 'handled';

    protected $originPath = '/data/images/origin/';
    protected $handledPath = '/data/images/handled/';

    /**
     * @var string $filename
     */
    protected $filename;

    /**
     * @var Imagick $originFile
     */
    protected $originFile;

    /**
     * @var Imagick $handledFile
     */
    protected $handledFile;

    /**
     * @param UploadedFile $file
     * @throws ImagickException
     * @return void
     */
    public function setFile(UploadedFile $file): void
    {
        parent::setFile($file);
        $this->originFile = new Imagick($file->getRealPath());
    }

    /**
     * Логика подготовки файла перед сохранением - в частности, класс PngImageUploader будет расширять возможности
     * этого метода, добавляя к нему изменение формата и имени.
     *
     * @return void
     */
    public function prepareFile(): void
    {
        $this->handledFile = $this->originFile->clone();
        $this->handledFile->thumbnailImage(125, 0);
        $this->filename = $this->file->getClientOriginalName();
    }

    /**
     * Возможно, целесообразно было бы разделить логику сохранения оригинального и измененного файла, но пока что
     * мне представляется излишним
     *
     * @return bool
     */
    public function saveFile(): bool
    {
        $origin = $this->originFile->writeImage(
            $_SERVER['DOCUMENT_ROOT']
            . $this->originPath
            . $this->filename
        );

        $handled = $this->handledFile->writeImage(
            $_SERVER['DOCUMENT_ROOT']
            . $this->handledPath
            . $this->filename
        );

        return $origin && $handled;
    }

    /**
     * Проверку будем осуществлять только для оригинальных файлов
     *
     * @throws FileExistException
     * @return void
     */
    public function checkFile(): void
    {
        if(file_exists($_SERVER['DOCUMENT_ROOT'] . $this->originPath . $this->filename)) {
            throw new FileExistException('This file has already been uploaded early');
        }
    }
}