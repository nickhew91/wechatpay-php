<?php

namespace WeChatPay\Util;

use function basename;

use GuzzleHttp\Utils as GHU;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\CachingStream;
use Psr\Http\Message\StreamInterface;

/**
 * Util for Media(image or video) uploading.
 */
class MediaUtil
{
    /**
     * local file path
     *
     * @var string
     */
    private $filepath;

    /**
     * file content stream to upload
     * @var string
     */
    private $fileStream;

    /**
     * upload meta json string
     *
     * @var string
     */
    private $meta;

    /**
     * upload contents stream
     *
     * @var MultipartStream
     */
    private $multipart;


    /**
     * multipart stream wrapper
     *
     * @var StreamInterface
     */
    private $stream;

    /**
     * Constructor
     *
     * @param string $filepath The media file path or file name,
     *                         should be one of the
     *                         images(jpg|bmp|png)
     *                         or
     *                         video(avi|wmv|mpeg|mp4|mov|mkv|flv|f4v|m4v|rmvb)
     * @param StreamInterface $fileStream  File content stream, optional
     */
    public function __construct(string $filepath, ?StreamInterface $fileStream = null)
    {
        $this->filepath = $filepath;
        $this->fileStream = $fileStream;
        $this->composeStream();
    }

    /**
     * Compose the GuzzleHttp\Psr7\FnStream
     */
    private function composeStream(): void
    {
        $basename = basename($this->filepath);
        $stream = $this->fileStream ?? new LazyOpenStream($this->filepath, 'r');
        if (!$stream->isSeekable()) {
            $stream = new CachingStream($stream);
        }

        $json = GHU::jsonEncode([
            'filename' => $basename,
            'sha256'   => Utils::hash($stream, 'sha256'),
        ]);
        $this->meta = $json;

        $multipart = new MultipartStream([
            [
                'name'     => 'meta',
                'contents' => $json,
                'headers'  => [
                    'Content-Type' => 'application/json',
                ],
            ],
            [
                'name'     => 'file',
                'filename' => $basename,
                'contents' => $stream,
            ],
        ]);
        $this->multipart = $multipart;

        $this->stream = FnStream::decorate($multipart, [
             // for signature
            '__toString' => function () use ($json) {
                return $json;
            },
             // let the `CURL` to use `CURLOPT_UPLOAD` context
            'getSize' => function () {
                return null;
            },
        ]);
    }

    /**
     * Get the `meta` of the multipart data string
     */
    public function getMeta(): string
    {
        return $this->meta;
    }

    /**
     * Get the `GuzzleHttp\Psr7\FnStream` context
     */
    public function getStream(): StreamInterface
    {
        return $this->stream;
    }

    /**
     * Get the `Content-Type` of the `GuzzleHttp\Psr7\MultipartStream`
     */
    public function getContentType(): string
    {
        return 'multipart/form-data; boundary=' . $this->multipart->getBoundary();
    }
}
