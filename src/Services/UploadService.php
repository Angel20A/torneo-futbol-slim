<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class UploadService
{
    public const DEFAULT_MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2 MB

    /**
     * MIME types estrictamente permitidos con su extensión canónica correspondiente.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private string $publicPath;
    private int $maxSizeBytes;

    public function __construct(string $publicPath = '', int $maxSizeBytes = self::DEFAULT_MAX_SIZE_BYTES)
    {
        $this->publicPath = rtrim($publicPath ?: dirname(__DIR__, 2) . '/public', '/\\');
        $this->maxSizeBytes = $maxSizeBytes > 0 ? $maxSizeBytes : self::DEFAULT_MAX_SIZE_BYTES;
    }

    /**
     * Sube un archivo de imagen validando su contenido binario real con finfo,
     * genera un nombre único y retorna su ruta relativa pública web (ej: /uploads/jugadores/hash.jpg).
     *
     * @param UploadedFileInterface|null $file Archivo proveniente del request PSR-7
     * @param string $subfolder Carpeta destino dentro de /uploads/ (ej: 'jugadores', 'equipos')
     * @param string|null $oldPath Ruta previa opcional a preservar si no se subió ningún archivo nuevo
     * @return string|null Ruta relativa normalizada o $oldPath si no se subió archivo
     *
     * @throws InvalidArgumentException Si el archivo excede el tamaño o no cumple con el MIME Type permitido
     * @throws RuntimeException Si ocurre un error en la subida PSR-7 o en la escritura en disco
     */
    public function upload(?UploadedFileInterface $file, string $subfolder, ?string $oldPath = null): ?string
    {
        // Si no se proporcionó archivo o el usuario no seleccionó ninguno en el formulario
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $oldPath;
        }

        // 1. Verificación de códigos de error de subida PSR-7
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->getUploadErrorMessage($file->getError()));
        }

        // 2. Validación de tamaño máximo en bytes
        $fileSize = $file->getSize();
        if ($fileSize === null || $fileSize > $this->maxSizeBytes) {
            $maxMb = round($this->maxSizeBytes / (1024 * 1024), 1);
            throw new InvalidArgumentException("El archivo excede el límite máximo permitido de {$maxMb} MB.");
        }

        // 3. Validación estricta con finfo (MIME Type real de los bytes del archivo)
        $detectedMime = $this->detectMimeType($file);
        if (!isset(self::ALLOWED_MIME_TYPES[$detectedMime])) {
            $permitidos = implode(', ', array_keys(self::ALLOWED_MIME_TYPES));
            throw new InvalidArgumentException(
                "Tipo de contenido no permitido ({$detectedMime}). Formatos aceptados exclusivamente: {$permitidos}."
            );
        }

        // Asignar extensión segura derivada del MIME type real verificado, NO del nombre enviado por el cliente
        $extension = self::ALLOWED_MIME_TYPES[$detectedMime];

        // 4. Sanitización de subdirectorio para prevenir Directory Traversal en la creación de carpetas
        $sanitizedSubfolder = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '', $subfolder) ?? '', '/');
        if ($sanitizedSubfolder === '') {
            $sanitizedSubfolder = 'general';
        }

        $targetDir = $this->publicPath . '/uploads/' . $sanitizedSubfolder;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("No fue posible crear el directorio de destino seguro: {$targetDir}");
        }

        // 5. Generación de nombre criptográficamente seguro y único
        $uniqueHash = bin2hex(random_bytes(16));
        $timestamp = time();
        $safeFilename = sprintf('%d_%s.%s', $timestamp, $uniqueHash, $extension);

        $destinationFilePath = $targetDir . '/' . $safeFilename;

        // 6. Mover el archivo a su ubicación definitiva
        $file->moveTo($destinationFilePath);

        // 7. Retornar la ruta relativa web normalizada
        return '/uploads/' . $sanitizedSubfolder . '/' . $safeFilename;
    }

    /**
     * Elimina un archivo físico del disco validando estrictamente que resida dentro
     * del directorio público /uploads/ para prevenir vulnerabilidades de Directory Traversal.
     *
     * @param string|null $relativePath Ruta relativa web del archivo (ej. /uploads/jugadores/hash.jpg)
     * @return bool True si el archivo fue eliminado exitosamente, False si no existía o la ruta era inválida
     */
    public function delete(?string $relativePath): bool
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return false;
        }

        // Normalizar separadores de directorio y remover barras iniciales
        $cleanPath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');

        // La ruta relativa debe comenzar estrictamente con 'uploads/'
        if (!str_starts_with($cleanPath, 'uploads/')) {
            return false;
        }

        $fullPath = $this->publicPath . '/' . $cleanPath;

        // Resolución de la ruta canónica en el sistema de archivos
        $realPath = realpath($fullPath);
        $realUploadsBase = realpath($this->publicPath . '/uploads');

        if ($realPath === false || $realUploadsBase === false) {
            return false;
        }

        // Mitigación estricta de Directory Traversal: debe residir dentro de /uploads/
        if (!str_starts_with($realPath, $realUploadsBase . DIRECTORY_SEPARATOR)) {
            return false;
        }

        if (!is_file($realPath)) {
            return false;
        }

        return @unlink($realPath);
    }

    /**
     * Alias de retrocompatibilidad para delete().
     */
    public function deleteFile(?string $relativePath): bool
    {
        return $this->delete($relativePath);
    }

    /**
     * Detecta el MIME Type real inspeccionando los bytes iniciales mediante PHP Fileinfo.
     */
    private function detectMimeType(UploadedFileInterface $file): string
    {
        $stream = $file->getStream();
        $tempPath = $stream->getMetadata('uri');

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        // Si existe un archivo temporal en disco accesible
        if (is_string($tempPath) && file_exists($tempPath) && is_readable($tempPath)) {
            $mime = $finfo->file($tempPath);
            return is_string($mime) ? strtolower($mime) : '';
        }

        // Si el stream está en memoria (php://temp o php://memory)
        $stream->rewind();
        $sample = $stream->read(8192); // Leer los primeros 8 KB para la firma mágica
        $mime = $finfo->buffer($sample);
        $stream->rewind();

        return is_string($mime) ? strtolower($mime) : '';
    }

    /**
     * Mapea códigos de error de subida nativos de PHP a mensajes claros.
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE   => 'El archivo excede la directiva upload_max_filesize de php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el límite MAX_FILE_SIZE especificado en el formulario HTML.',
            UPLOAD_ERR_PARTIAL    => 'El archivo se subió solo parcialmente.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor para almacenar la subida.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco del servidor.',
            UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida del archivo.',
            default               => "Error desconocido al subir el archivo (código: {$errorCode}).",
        };
    }
}
