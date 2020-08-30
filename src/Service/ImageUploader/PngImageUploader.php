<?php


namespace App\Service\ImageUploader;


class PngImageUploader extends ImageUploader
{
    const TYPE = 'image/png';

    /**
     * Переопределим родительскую логику, добавив изменение формата файла и расширения в его названии
     */
    public function prepareFile(): void
    {
        $this->originFile->setImageFormat('jpeg');

        parent::prepareFile();

        $this->filename = str_replace('.png', '.jpg', $this->filename);
    }

}