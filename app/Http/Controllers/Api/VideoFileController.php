<?php

namespace App\Http\Controllers\Api;

use Storage;
use App\Video;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class VideoFileController extends Controller
{
    /**
     * Get a video file.
     *
     * @param Request $request
     * @param string $uuid
     *
     * @return mixed
     */
    public function show(Request $request, $uuid)
    {
        $video = Video::where('uuid', $uuid)->firstOrFail();
        $response = Storage::disk('videos')->response($uuid);
        $response->headers->set('Accept-Ranges', 'bytes');

        $range = $this->getByteRange($video, $request);

        if (!empty($range)) {
            // Range requests:
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Range_requests
            $offset = $range[0];
            $length = $range[1] - $range[0] + 1;
            $total = $video->meta['size'];
            $response->headers->set('Content-Length', $length);
            $response->headers->set('Content-Range', 'bytes '.implode('-', $range).'/'.$total);
            $response->setStatusCode(206);

            $response->setCallback(function () use ($uuid, $offset, $length) {
                $stream = Storage::disk('videos')->readStream($uuid);
                fseek($stream, $offset);
                echo fread($stream, $length);
                fclose($stream);
            });
        }

        return $response;
    }

    /**
     * Determine the byte range that should be included in the response.
     *
     * @param Video $video
     * @param Request $request
     *
     * @return array Array containing start and stop byte positions.
     */
    protected function getByteRange(Video $video, Request $request)
    {
        $range = [];
        $header = explode('=', $request->headers->get('Range'));

        if ($header[0] === 'bytes' && count($header) === 2) {
            if (strpos($header[1], ',') !== false) {
                // Multipart responses are not supported.
                return [];
            }

            $range = array_map('intval', explode('-', trim($header[1])));

            if ($range[1] === 0) {
                $range[1] = $video->meta['size'] - 1;
            }
        }

        return $range;
    }
}
