<?php

namespace App\Actions;

use App\Models\Restaurant;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

class GenerateRestaurantQrCode
{
    public function handle(Restaurant $restaurant): string
    {
        $disk = config('image.disk');
        $path = "qrcodes/{$restaurant->id}.svg";

        if (! Storage::disk($disk)->exists($path)) {
            $url = config('app.url').'/'.$restaurant->uniqid;

            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel'   => QRCode::ECC_H,
                'scale'      => 10,
                'imageBase64' => false,
            ]);

            $svg = (new QRCode($options))->render($url);

            Storage::disk($disk)->put($path, $svg);
        }

        return Storage::disk($disk)->url($path);
    }
}
