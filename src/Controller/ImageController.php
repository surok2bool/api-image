<?php

namespace App\Controller;

use App\Service\ImageUploader\ImageUploader;
use App\Service\ImageUploader\UploaderException\FileExistException;
use App\Service\ImageUploader\UploaderException\FileTypeException;
use App\Service\FileUploaderFactory;
use App\Service\ImageUploader\UploaderInterface\UploaderInterface;
use PhpParser\Node\Scalar\MagicConst\File;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

    /**
     * @return JsonResponse
     */
    public function getImagesList(): JsonResponse
    {
        $finder = new Finder();

        $finder->files()->in($_SERVER['DOCUMENT_ROOT'] . '/data/images/origin/');

        $result = [];

        foreach ($finder as $file) {

            $router = $this->container->get('router');
            $originLink = $this->generateUrl(
                'get_image',
                [
                    'origin' => 'origin',
                    'filename' => $file->getFilename()
                ],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );

            $handledLink = $this->generateUrl(
                'get_image',
                [
                    'origin' => 'handled',
                    'filename' => $file->getFilename()
                ],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );

            $imageData = [
                'origin' => $originLink,
                'handled' => $handledLink,
                'date' => $file->getMTime()
            ];
            $result[] = $imageData;
        }

        return new JsonResponse($result);
    }

    /**
     * Метод просто возвращает файл (в ТЗ такого пункта не было, но поскольку в списке файлов необходимо
     * отдавать ссылки на изображения, полагаю, необходимо сделать эти ссылки рабочими)
     *
     * @param string $filename
     * @param string $origin
     * @param Request $request
     * @return Response
     */
    public function getImage(Request $request, string $origin, string $filename): Response
    {
        $availableTypes = [
            ImageUploader::HANDLED_PATH,
            ImageUploader::ORIGINAL_PATH
        ];

        $typeImage = in_array($origin, $availableTypes) ? $origin : '';
        $filename = $_SERVER['DOCUMENT_ROOT'] . '/data/images/' . $typeImage . '/' . $filename;

        if (!empty($typeImage) && file_exists($filename)) {
            $response = $this->file($filename);
        } else {
            $response = new Response();
            $response->setStatusCode(404);
            $response->setContent('File not found!');
        }
        return $response;
    }

    /**
     * Логика примерно следующая (хотя, возможно, я неверно понял задание): ищем оригинальное изображение,
     * если такой файл существует - отдаем его, ставим isOrigin -> true. Если оригинал не найден -
     * ищем измененное изображение из папки handled. Если не найден и такой файл, кидаем exception.
     *
     * @param string $filename
     * @return JsonResponse
     */
    public function getImageInfo(string $filename): JsonResponse
    {
        $originPath = $_SERVER['DOCUMENT_ROOT'] . '/data/images/origin/' . $filename;
        $handledPath = $_SERVER['DOCUMENT_ROOT'] . '/data/images/handled/' . $filename;
        $finder = new Finder();

        if (file_exists($originPath)) {
            $origin = 'origin';
            $isOrigin = true;
            $finder->name($filename)->in($_SERVER['DOCUMENT_ROOT'] . '/data/images/origin/');
        } elseif (file_exists($handledPath)) {
            $origin = 'handled';
            $isOrigin = false;
            $finder->name($filename)->in($_SERVER['DOCUMENT_ROOT'] . '/data/images/handled/');
        } else {
            throw $this->createNotFoundException('File not found');
        }

        foreach ($finder as $file) {
            $date = $file->getMTime();
        }

        $originLink = $this->generateUrl(
            'get_image',
            [
                'origin' => $origin,
                'filename' => $filename
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        $result = [
            'origin' => $originLink,
            'isOrigin' => $isOrigin,
            'date' => $date
        ];

        return new JsonResponse($result);
    }
}
