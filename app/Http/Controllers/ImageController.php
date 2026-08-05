<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Glide\Api\Api;
use League\Glide\Filesystem\FileNotFoundException;
use League\Glide\Manipulators\Background;
use League\Glide\Manipulators\Blur;
use League\Glide\Manipulators\Border;
use League\Glide\Manipulators\Brightness;
use League\Glide\Manipulators\Contrast;
use League\Glide\Manipulators\Crop;
use League\Glide\Manipulators\Filter;
use League\Glide\Manipulators\Gamma;
use League\Glide\Manipulators\Orientation;
use League\Glide\Manipulators\Pixelate;
use League\Glide\Manipulators\Sharpen;
use League\Glide\Manipulators\Size;
use League\Glide\Responses\ResponseFactoryInterface;
use League\Glide\Server;

class ImageController extends Controller
{
    public function __invoke(Request $request, $path)
    {
        abort_if(str_contains($path, '..'), 404);

        $source = new Filesystem(
            new LocalFilesystemAdapter(storage_path('app'))
        );

        $cache = new Filesystem(
            new LocalFilesystemAdapter(storage_path('app/private/.cache'))
        );

        // Set image manager
        $imageManager = new ImageManager(
            new Driver
        );

        // Set manipulators
        $manipulators = [
            new Orientation,
            new Crop,
            new Size(2000 * 2000),
            new Brightness,
            new Contrast,
            new Gamma,
            new Sharpen,
            new Filter,
            new Blur,
            new Pixelate,
            new Background,
            new Border,
        ];

        // Set API
        $api = new Api($imageManager, $manipulators);

        // Setup Glide server
        $server = new Server(
            $source,
            $cache,
            $api,
        );

        // Set custom response factory
        $server->setResponseFactory(new class implements ResponseFactoryInterface
        {
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
