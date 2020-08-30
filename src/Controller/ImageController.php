<?php

namespace App\Controller;

use App\Service\ImageUploader\UploaderException\FileExistException;
use App\Service\ImageUploader\UploaderException\FileTypeException;
use App\Service\FileUploaderFactory;
use App\Service\ImageUploader\UploaderInterface\UploaderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageController extends AbstractController
{
    /**
     * Писалось с умыслом, что возможна ситуация, когда картинки будут передаваться пачкой и разных форматов.
     * Чтобы дать пользователю наиболее полное представление о том, что происходит с загружаемыми файлами,
     * в ответ будем давать json, содержащий подробную информацию о каждом загруженном (или не загруженном)
     * файле (хотя, наверное, это уже избыточно).
     *
     * @param Request $request
     * @param FileUploaderFactory $uploaderFactory
     *
     * @return JsonResponse
     */
    public function index(Request $request, FileUploaderFactory $uploaderFactory): JsonResponse
    {
        /**
         * @var $result - результирующий массив, в который складываем результаты загрузки файлов
         */
        $result = [];

        /**
         * @var UploadedFile $file
         */
        foreach ($request->files as $file) {
            $uploadFileInfo = [
                'filename' => $file->getClientOriginalName(),
                'result' => false,
            ];

            try {
                $uploader = $uploaderFactory->getUploader($file->getMimeType());

                $uploader->setFile($file);
                $uploader->prepareFile();

                $uploader->checkFile();
                if ($uploader->saveFile()) {
                    $uploadFileInfo['result'] = true;
                }

            } catch (FileTypeException $e) {
                $uploadFileInfo['error'] = $e->getMessage();
            } catch (\ImagickException $exception) {
                $uploadFileInfo['error'] = $exception->getMessage();
            } catch (FileExistException $exception) {
                $uploadFileInfo['error'] = $exception->getMessage();
            }

            $result[] = $uploadFileInfo;
        }

        $response = new JsonResponse();
        $response->setContent(json_encode($result));

        return $response;
    }


}
