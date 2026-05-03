<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use League\Flysystem\FilesystemOperator;
use League\Glide\Filesystem\FileNotFoundException;
use League\Glide\Responses\ResponseFactoryInterface;

class ImageController extends Controller
{
    public function __invoke(Request $request, $path)
    {
        abort_if(str_contains($path, '..'), 404);

        $source = new \League\Flysystem\Filesystem(
            new \League\Flysystem\Local\LocalFilesystemAdapter(storage_path('app'))
        );

        $cache = new \League\Flysystem\Filesystem(
            new \League\Flysystem\Local\LocalFilesystemAdapter(storage_path('app/private/.cache'))
        );

        // Set image manager
        $imageManager = new \Intervention\Image\ImageManager(
            new \Intervention\Image\Drivers\Gd\Driver()
        );

        // Set manipulators
        $manipulators = [
            new \League\Glide\Manipulators\Orientation(),
            new \League\Glide\Manipulators\Crop(),
            new \League\Glide\Manipulators\Size(2000*2000),
            new \League\Glide\Manipulators\Brightness(),
            new \League\Glide\Manipulators\Contrast(),
            new \League\Glide\Manipulators\Gamma(),
            new \League\Glide\Manipulators\Sharpen(),
            new \League\Glide\Manipulators\Filter(),
            new \League\Glide\Manipulators\Blur(),
            new \League\Glide\Manipulators\Pixelate(),
            new \League\Glide\Manipulators\Background(),
            new \League\Glide\Manipulators\Border(),
        ];

        // Set API
        $api = new \League\Glide\Api\Api($imageManager, $manipulators);

        // Setup Glide server
        $server = new \League\Glide\Server(
            $source,
            $cache,
            $api,
        );

        // Set custom response factory
        $server->setResponseFactory(new class implements ResponseFactoryInterface {
            public function create(FilesystemOperator $cache, string $path)
            {
                $stream = $cache->readStream($path);

                return new Response(
                    stream_get_contents($stream),
                    200,
                    [
                        'Content-Type' => $cache->mimeType($path),
                        'Content-Length' => $cache->fileSize($path),
                        'Cache-Control' => 'public, max-age=31536000',
                    ],
                );
            }
        });

        try {
            return $server->getImageResponse($path, $request->all());
        } catch (FileNotFoundException $exception) {
            abort(404);
        }
    }
}
