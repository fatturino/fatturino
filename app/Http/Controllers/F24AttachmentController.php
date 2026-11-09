<?php

namespace App\Http\Controllers;

use App\Services\DocumentStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class F24AttachmentController extends Controller
{
    public function __invoke(Request $request, DocumentStorageService $storage): Response
    {
        $path = (string) $request->query('path', '');
        $isF24Path = str_starts_with($path, 'f24/') || str_contains($path, '/f24/');
        if ($path === '' || str_contains($path, '..') || ! $isF24Path) {
            throw new NotFoundHttpException;
        }

        $contents = $storage->getContents($path);
        if ($contents === null) {
            throw new NotFoundHttpException;
        }

        $mime = 'application/octet-stream';
        if (str_ends_with(strtolower($path), '.pdf')) {
            $mime = 'application/pdf';
        } elseif (preg_match('/\.(png|jpe?g|webp|gif)$/i', $path, $m)) {
            $ext = strtolower($m[1]);
            $mime = 'image/'.($ext === 'jpg' ? 'jpeg' : $ext);
        }

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
