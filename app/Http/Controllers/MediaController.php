<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function storeImage(Request $request) {

        if($request->hasFile('image')) {
            $file = $request->file('image');
            $mimeType = $file->getMimeType();

            $acceptedTypes = [
                "image/png",
                "image/jpg",
                "image/jpeg",
            ];

            if (!in_array($mimeType, $acceptedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload accepted image files only.'
                ]);
            }

            $s3ResponseFileName = $file->store('', 's3');

            return response()->json([
                'success' => true,
                'message' => 'Image Stored',
                'url' => $s3ResponseFileName,
            ]);

        } else {
            return response()->json([
                'success' => false,
                'message' => 'Image Missing',
            ]);
        }
    }

    public function storeMedia(Request $request) {
        if($request->hasFile('media')) {
            $file = $request->file('media');
            $mimeType = $file->getMimeType();

            $mimeType = str_replace('x-', '', $mimeType);

            $acceptedTypes = $request->exists('acceptedMimeTypes') ? json_decode(stripslashes($request->input('acceptedMimeTypes'))) : false;

            if ($acceptedTypes && !in_array($mimeType, $acceptedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload accepted file types only.'
                ]);
            }

            $filePath = '';
            $fileName = time() . '_' . $request->file('media')->getClientOriginalName();

            $error = false;

            try {

                //Upload to S3, overwriting if filename exists.
                $s3ResponseFileName = File::streamUpload($filePath, $fileName, $file, true);
            } catch (Exception $e) {
                $error =  'Caught exception: ' . $e->getMessage();
            }

            if($error) {
                return response()->json([
                    'success' => false,
                    'message' => $error,
                ]);
            }
            //$s3ResponseFileName = $file->store('', 's3');

            return response()->json([
                'success' => true,
                'message' => 'Media Stored',
                'url' => $fileName,
            ]);

        } else {
            return response()->json([
                'success' => false,
                'message' => 'Media Missing',
            ]);
        }
    }

    public function removeFromS3(Request $request) {
        $url = $request->input('url');
        if($url) {
            Storage::disk('s3')->delete($request->input('url'));
            return response()->json([
                'success' => true,
                'message' => 'deleted',
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'Missing Data',
        ], 400);
    }
}
