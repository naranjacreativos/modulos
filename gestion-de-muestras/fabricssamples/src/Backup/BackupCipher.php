<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Backup;

final class BackupCipher
{
    private const MAGIC = "FSB21401";
    private const CHUNK_SIZE = 1048576;
    private const CIPHER = 'aes-256-gcm';

    /** @return array{plaintext_size:int,plaintext_sha256:string} */
    public function encrypt(string $source, string $destination): array
    {
        $this->assertAvailable();
        $input = @fopen($source, 'rb');
        $output = @fopen($destination, 'wb');
        if ($input === false || $output === false) {
            $this->close($input);
            $this->close($output);
            throw new \RuntimeException('No se pudo abrir el flujo de cifrado de la copia.');
        }

        $size = (int) filesize($source);
        $sha256 = (string) hash_file('sha256', $source);
        $header = json_encode([
            'cipher' => self::CIPHER,
            'chunk_size' => self::CHUNK_SIZE,
            'plaintext_size' => $size,
            'plaintext_sha256' => $sha256,
            'created_at' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($header)) {
            $this->close($input);
            $this->close($output);
            throw new \RuntimeException('No se pudo crear la cabecera cifrada.');
        }

        try {
            $this->writeAll($output, self::MAGIC . pack('N', strlen($header)) . $header);
            $index = 0;
            while (!feof($input)) {
                $plain = fread($input, self::CHUNK_SIZE);
                if ($plain === false) {
                    throw new \RuntimeException('No se pudo leer la copia durante el cifrado.');
                }
                if ($plain === '') {
                    break;
                }
                $iv = random_bytes(12);
                $tag = '';
                $ciphertext = openssl_encrypt(
                    $plain,
                    self::CIPHER,
                    $this->key(),
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag,
                    self::MAGIC . pack('N', $index),
                    16
                );
                if (!is_string($ciphertext) || strlen($tag) !== 16) {
                    throw new \RuntimeException('OpenSSL no pudo cifrar la copia de seguridad.');
                }
                $this->writeAll($output, pack('N', strlen($ciphertext)) . $iv . $tag . $ciphertext);
                ++$index;
            }
            fflush($output);
            @chmod($destination, 0600);
        } catch (\Throwable $exception) {
            $this->close($input);
            $this->close($output);
            @unlink($destination);
            throw $exception;
        }
        $this->close($input);
        $this->close($output);

        return ['plaintext_size' => $size, 'plaintext_sha256' => $sha256];
    }

    /** @return array{plaintext_size:int,plaintext_sha256:string} */
    public function decrypt(string $source, string $destination): array
    {
        $this->assertAvailable();
        $input = @fopen($source, 'rb');
        $output = @fopen($destination, 'wb');
        if ($input === false || $output === false) {
            $this->close($input);
            $this->close($output);
            throw new \RuntimeException('No se pudo abrir el flujo de descifrado de la copia.');
        }

        $hash = hash_init('sha256');
        $written = 0;
        try {
            if ($this->readExact($input, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new \RuntimeException('El archivo no es una copia cifrada válida de fabricssamples.');
            }
            $headerLength = unpack('Nlength', $this->readExact($input, 4))['length'] ?? 0;
            if ($headerLength < 20 || $headerLength > 8192) {
                throw new \RuntimeException('La cabecera de la copia cifrada no es válida.');
            }
            $header = json_decode($this->readExact($input, $headerLength), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($header) || ($header['cipher'] ?? '') !== self::CIPHER) {
                throw new \RuntimeException('El algoritmo de la copia no es compatible.');
            }

            $index = 0;
            while (!feof($input)) {
                $lengthBytes = fread($input, 4);
                if ($lengthBytes === '' || $lengthBytes === false) {
                    break;
                }
                if (strlen($lengthBytes) !== 4) {
                    throw new \RuntimeException('La copia cifrada está truncada.');
                }
                $length = unpack('Nlength', $lengthBytes)['length'] ?? 0;
                if ($length <= 0 || $length > self::CHUNK_SIZE + 64) {
                    throw new \RuntimeException('La longitud de un bloque cifrado no es válida.');
                }
                $iv = $this->readExact($input, 12);
                $tag = $this->readExact($input, 16);
                $ciphertext = $this->readExact($input, $length);
                $plain = openssl_decrypt(
                    $ciphertext,
                    self::CIPHER,
                    $this->key(),
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag,
                    self::MAGIC . pack('N', $index)
                );
                if (!is_string($plain)) {
                    throw new \RuntimeException('La autenticación criptográfica de la copia ha fallado.');
                }
                $this->writeAll($output, $plain);
                hash_update($hash, $plain);
                $written += strlen($plain);
                ++$index;
            }
            fflush($output);
            $actualHash = hash_final($hash);
            if ($written !== (int) ($header['plaintext_size'] ?? -1)
                || !hash_equals((string) ($header['plaintext_sha256'] ?? ''), $actualHash)) {
                throw new \RuntimeException('La verificación de integridad de la copia ha fallado.');
            }
            @chmod($destination, 0600);
        } catch (\Throwable $exception) {
            $this->close($input);
            $this->close($output);
            @unlink($destination);
            throw $exception;
        }
        $this->close($input);
        $this->close($output);

        return ['plaintext_size' => $written, 'plaintext_sha256' => $actualHash];
    }

    private function assertAvailable(): void
    {
        if (!extension_loaded('openssl') || !in_array(self::CIPHER, openssl_get_cipher_methods(), true)) {
            throw new \RuntimeException('OpenSSL con AES-256-GCM es obligatorio para proteger las copias.');
        }
        if (!defined('_COOKIE_KEY_') || trim((string) _COOKIE_KEY_) === '') {
            throw new \RuntimeException('No se encontró la clave criptográfica de la tienda.');
        }
    }

    private function key(): string
    {
        $material = (string) _COOKIE_KEY_ . '|fabricssamples|' . (defined('_DB_PREFIX_') ? (string) _DB_PREFIX_ : '');
        return function_exists('hash_hkdf')
            ? hash_hkdf('sha256', $material, 32, 'fabricssamples-backup-v1')
            : hash('sha256', 'fabricssamples-backup-v1|' . $material, true);
    }

    /** @param resource $stream */
    private function readExact($stream, int $length): string
    {
        $data = '';
        while (strlen($data) < $length && !feof($stream)) {
            $chunk = fread($stream, $length - strlen($data));
            if ($chunk === false) {
                throw new \RuntimeException('No se pudo leer la copia cifrada.');
            }
            $data .= $chunk;
        }
        if (strlen($data) !== $length) {
            throw new \RuntimeException('La copia cifrada está truncada.');
        }
        return $data;
    }

    /** @param resource $stream */
    private function writeAll($stream, string $data): void
    {
        $offset = 0;
        while ($offset < strlen($data)) {
            $written = fwrite($stream, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('No se pudo escribir el flujo de copia.');
            }
            $offset += $written;
        }
    }

    private function close(mixed $stream): void
    {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}
