<?php

namespace App\Controller;

use App\Service\ImageUploader\ImageUploader;
use App\Service\ImageUploader\UploaderException\FileExistException;
use App\Service\ImageUploader\UploaderException\FileTypeException;
use App\Service\FileUploaderFactory;
use Psr\Log\LoggerInterface;
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
     * @param LoggerInterface $logger
     * @param Request $request
     * @param FileUploaderFactory $uploaderFactory
     *
     * @return JsonResponse
     */
    public function index(LoggerInterface $logger, Request $request, FileUploaderFactory $uploaderFactory): JsonResponse
    {
        /**
         * @var $result - результирующий массив, в который складываем результаты загрузки файлов
         */
        $result = [];

        /**
         * @var UploadedFile $file
         */
        foreach ($request->files as $file) {
            $logger->info('Get file ' . $file->getClientOriginalName());

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
                $logger->error('Wrong type of file');
                $uploadFileInfo['error'] = $e->getMessage();
            } catch (\ImagickException $exception) {
                $logger->error('Catch Imagick Exception');
                $logger->error($exception->getMessage());
                $uploadFileInfo['error'] = $exception->getMessage();
            } catch (FileExistException $exception) {
                $logger->error('Catch FileExistException');
                $logger->error($exception->getMessage());
                $uploadFileInfo['error'] = $exception->getMessage();
            }

            $result[] = $uploadFileInfo;
        }

        $response = new JsonResponse();
        $response->setContent(json_encode($result));

        return $response;
    }

    /**
     * @param LoggerInterface $logger
     * @return JsonResponse
     */
    public function getImagesList(LoggerInterface $logger): JsonResponse
    {
        $finder = new Finder();

        /**
         * Проводим поиск только по оригинальным изображениям
         */
        $finder->files()->in($_SERVER['DOCUMENT_ROOT'] . '/data/images/origin/');

        $result = [];

        $logger->info('Found ' . $finder->count() . ' images');

        foreach ($finder as $file) {

            $logger->info('Found ' . $file->getFilename());
            $originLink = $this->generateUrl(
                'get_image',
                [
                    'origin' => 'origin',
                    'filename' => $file->getFilename()
                ],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );

            $logger->info('Original link: ' . $originLink);

            $handledLink = $this->generateUrl(
                'get_image',
                [
                    'origin' => 'handled',
                    'filename' => $file->getFilename()
                ],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );

            $logger->info('Handled link: ' . $handledLink);

            $imageData = [
                'origin' => $originLink,
                'handled' => $handledLink,
                'date' => $file->getMTime()
            ];

            $result[] = $imageData;
        }

        $logger->info('Result: ' . json_encode($result));
        return new JsonResponse($result);
    }

    /**
     * Метод просто возвращает файл (в ТЗ такого пункта не было, но поскольку в списке файлов необходимо
     * отдавать ссылки на изображения, полагаю, необходимо сделать эти ссылки рабочими)
     *
     * @param LoggerInterface $logger
     * @param string $origin
     * @param string $filename
     * @return Response
     */
    public function getImage(LoggerInterface $logger, string $origin, string $filename): Response
    {
        $logger->info('Try to get ' . $origin . ' file ' . $filename);
        $availableTypes = [
            ImageUploader::HANDLED_PATH,
            ImageUploader::ORIGINAL_PATH
        ];

        $typeImage = in_array($origin, $availableTypes) ? $origin : '';
        $filename = $_SERVER['DOCUMENT_ROOT'] . '/data/images/' . $typeImage . '/' . $filename;

        if (!empty($typeImage) && file_exists($filename)) {
            $response = $this->file($filename);
        } else {
            $logger->alert('File ' . $filename . ' not found!');
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
     * @param LoggerInterface $logger
     * @param string $filename
     * @return JsonResponse
     */
    public function getImageInfo(LoggerInterface $logger, string $filename): JsonResponse
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
            $logger->alert('File ' . $filename . ' not found!');
            throw $this->createNotFoundException('File not found');
        }

        foreach ($finder as $file) {
            $date = $file->getMTime();
        }

        $link = $this->generateUrl(
            'get_image',
            [
                'origin' => $origin,
                'filename' => $filename
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        $result = [
            'origin' => $link,
            'isOrigin' => $isOrigin,
            'date' => $date
        ];

        $logger->info('Result: ' . json_encode($result));
        return new JsonResponse($result);
    }
}
