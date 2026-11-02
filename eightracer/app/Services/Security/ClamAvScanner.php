<?php
namespace App\Services\Security;

class ClamAvScanner
{
    public function assertClean(string $path): void
    {
        $socket = null;
        $file = null;
        try {
            $endpoint = config('security.clamav_endpoint');
            if (! is_string($endpoint) || ! preg_match('#^(tcp://(127\.0\.0\.1|\[::1\]):[0-9]{1,5}|unix:///[^\x00]+)$#D', $endpoint)) {
                throw new \RuntimeException('Invalid scanner endpoint');
            }
            $socket = @stream_socket_client($endpoint, $errno, $errstr, config('security.clamav_timeout', 10));
            if (! is_resource($socket)) {
                throw new \RuntimeException('Scanner unavailable');
            }
            stream_set_timeout($socket, config('security.clamav_timeout', 10));
            $file = @fopen($path, 'rb');
            if (! is_resource($file)) {
                throw new \RuntimeException('Unreadable upload');
            }
            $this->writeAll($socket, "zINSTREAM\0");
            $total = 0;
            while (! feof($file)) {
                $chunk = fread($file, 16384);
                if ($chunk === false) {
                    throw new \RuntimeException('Read failed');
                }
                if ($chunk === '') {
                    break;
                }
                $total += strlen($chunk);
                if ($total > config('security.upload_bytes')) {
                    throw new \RuntimeException('Upload limit');
                }
                $this->writeAll($socket, pack('N', strlen($chunk)).$chunk);
            }
            $this->writeAll($socket, pack('N', 0));
            $reply = stream_get_line($socket, 4096, "\0");
            if (is_string($reply) && str_ends_with(trim($reply), ' FOUND')) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'File tidak lolos pemeriksaan keamanan.');
            }
            if ($reply !== 'stream: OK' || stream_get_meta_data($socket)['timed_out']) {
                throw new \RuntimeException('Scanner did not confirm clean');
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            throw $error;
        } catch (\Throwable) {
            abort(503, 'Pemindai file belum tersedia.');
        } finally {
            if (is_resource($file)) { fclose($file); }
            if (is_resource($socket)) { fclose($socket); }
        }
    }

    private function writeAll($socket, string $data): void
    {
        while ($data !== '') {
            $written = @fwrite($socket, $data);
            if (! $written) {
                throw new \RuntimeException('Scanner stream failed');
            }
            $data = substr($data, $written);
        }
    }
}
