<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

final class ImageSnapshotService
{
    public function __construct(private \Module $module)
    {
    }

    /** @return array{id_image:int,path:string} */
    public function snapshot(int $idOrder, int $idProduct): array
    {
        $cover = \Product::getCover($idProduct);
        if (!is_array($cover) || empty($cover['id_image'])) {
            return ['id_image' => 0, 'path' => ''];
        }
        $idImage = (int) $cover['id_image'];
        $source = _PS_IMG_DIR_ . 'p/' . \Image::getImgFolderStatic($idImage) . $idImage . '.jpg';
        if (!is_file($source)) {
            return ['id_image' => $idImage, 'path' => ''];
        }

        try {
            $directory = $this->storageDirectory();
            $this->ensureDirectory($directory);
            $filename = sprintf('fs-%d-%s.jpg', $idOrder, bin2hex(random_bytes(12)));
            $destination = $directory . DIRECTORY_SEPARATOR . $filename;
            $temporary = $destination . '.part';
            if (!@copy($source, $temporary) || !@rename($temporary, $destination)) {
                @unlink($temporary);
                return ['id_image' => $idImage, 'path' => ''];
            }
            @chmod($destination, 0600);

            return ['id_image' => $idImage, 'path' => 'private/orders/' . $filename];
        } catch (\Throwable $exception) {
            \PrestaShopLogger::addLog(
                'fabricssamples image snapshot: ' . $exception->getMessage(),
                2,
                null,
                'Product',
                $idProduct,
                true
            );
            return ['id_image' => $idImage, 'path' => ''];
        }
    }

    public function url(string $relativePath): string
    {
        if (!str_starts_with($relativePath, 'private/orders/')) {
            $legacy = ltrim($relativePath, '/');
            return $legacy !== '' && is_file($this->module->getLocalPath() . $legacy)
                ? $this->module->getPathUri() . str_replace('%2F', '/', rawurlencode($legacy))
                : '';
        }
        $filename = basename($relativePath);
        if ($this->resolve($filename) === '') {
            return '';
        }
        $expires = time() + 3600;
        return \Context::getContext()->link->getModuleLink($this->module->name, 'snapshot', [
            'file' => $filename,
            'expires' => $expires,
            'signature' => $this->signature($filename, $expires),
        ], true);
    }

    public function resolve(string $filename): string
    {
        if (!preg_match('/^fs-[1-9][0-9]*-[a-f0-9]{24}\.jpg$/', $filename)) {
            return '';
        }
        $path = $this->storageDirectory() . DIRECTORY_SEPARATOR . $filename;
        return is_file($path) ? $path : '';
    }

    public function validSignature(string $filename, int $expires, string $signature): bool
    {
        return $expires >= time() && $expires <= time() + 86400
            && $signature !== ''
            && hash_equals($this->signature($filename, $expires), $signature);
    }

    public function storageDirectory(): string
    {
        return rtrim((string) _PS_ROOT_DIR_, '/\\') . DIRECTORY_SEPARATOR . 'var'
            . DIRECTORY_SEPARATOR . 'fabricssamples' . DIRECTORY_SEPARATOR . 'orders';
    }

    public function purge(): bool
    {
        $directory = $this->storageDirectory();
        if (!is_dir($directory)) {
            return true;
        }
        $ok = true;
        foreach (glob($directory . DIRECTORY_SEPARATOR . 'fs-*.jpg') ?: [] as $file) {
            $ok = @unlink($file) && $ok;
        }
        return $ok;
    }

    private function signature(string $filename, int $expires): string
    {
        $key = defined('_COOKIE_KEY_') ? (string) _COOKIE_KEY_ : '';
        return hash_hmac('sha256', $filename . '|' . $expires, $key);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo crear el almacén privado de imágenes históricas.');
        }
        @chmod(dirname($directory), 0700);
        @chmod($directory, 0700);
    }
}
