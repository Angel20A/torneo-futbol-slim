<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class UploadService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    private string $publicPath;

    public function __construct(string $publicPath = '')
    {
        $this->publicPath = rtrim($publicPath ?: dirname(__DIR__, 2) . '/public', '/\\');
    }

    /**
     * Sube un archivo de imagen y retorna su ruta pública relativa (ej. /uploads/equipos/abc.png)
     *
     * @param UploadedFileInterface|null $file
     * @param string $subfolder 'equipos' o 'jugadores'
     * @param string|null $oldPath Ruta previa para reemplazo
     * @return string|null
     * @throws InvalidArgumentException|RuntimeException
     */
    public function upload(?UploadedFileInterface $file, string $subfolder, ?string $oldPath = null): ?string
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $oldPath;
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir el archivo (código: ' . $file->getError() . ').');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException('El archivo supera el tamaño máximo permitido de 5 MB.');
        }

        $clientFilename = $file->getClientFilename() ?? '';
        $extension = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException(
                'Formato de imagen no permitido. Formatos válidos: ' . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }

        $targetDir = $this->publicPath . '/uploads/' . trim($subfolder, '/');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("No se pudo crear el directorio de destino: {$targetDir}");
        }

        $uniqueName = sprintf('%s_%s.%s', $subfolder, bin2hex(random_bytes(8)), $extension);
        $targetFilePath = $targetDir . '/' . $uniqueName;

        $file->moveTo($targetFilePath);

        // Si se subió con éxito y había una foto previa distinta, eliminamos la anterior de forma segura
        if ($oldPath) {
            $this->deleteFile($oldPath);
        }

        return '/uploads/' . trim($subfolder, '/') . '/' . $uniqueName;
    }

    /**
     * Elimina un archivo físico si pertenece a la carpeta /uploads/
     */
    public function deleteFile(?string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }

        // Aseguramos que la ruta comience con /uploads/
        if (!str_starts_with($relativePath, '/uploads/')) {
            return;
        }

        $realTarget = $this->publicPath . $relativePath;
        if (file_exists($realTarget) && is_file($realTarget)) {
            @unlink($realTarget);
        }
    }
}
