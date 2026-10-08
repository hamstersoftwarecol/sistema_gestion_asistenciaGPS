<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Guarda selfies recibidas como data URL (base64). La imagen se vuelve a codificar con GD
 * para limitar su tamaño y descartar metadatos; se almacena en el disco privado.
 */
class SelfieStore
{
    public const MAX_BYTES = 4 * 1024 * 1024;

    public const MAX_WIDTH = 640;

    public function store(string $dataUrl, int $userId): string
    {
        if (! preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#', $dataUrl)) {
            throw new InvalidArgumentException('La selfie debe ser una imagen JPEG, PNG o WebP.');
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false || strlen($binary) > self::MAX_BYTES) {
            throw new InvalidArgumentException('La selfie no es válida o supera el tamaño permitido.');
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            throw new InvalidArgumentException('No se pudo leer la imagen de la selfie.');
        }

        $width = imagesx($image);

        if ($width > self::MAX_WIDTH) {
            $scaled = imagescale($image, self::MAX_WIDTH);
            imagedestroy($image);
            $image = $scaled;
        }

        ob_start();
        imagejpeg($image, null, 75);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $path = sprintf('selfies/%s/%d-%s.jpg', now()->format('Y/m'), $userId, Str::uuid());
        Storage::disk('local')->put($path, $jpeg);

        return $path;
    }
}
