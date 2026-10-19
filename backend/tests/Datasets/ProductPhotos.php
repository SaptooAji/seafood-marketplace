<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;

/*
 * Photos below the product photo standard (#236), with a word the refusal
 * must contain. Shared by the Action, the API, and the /seller panel, which
 * all upload through AddProductImage.
 */
dataset('photos below the standard', [
    'shorter side of 599' => [fn (): UploadedFile => UploadedFile::fake()->image('kecil.jpg', 599, 800), '600 piksel'],
    '300 x 300' => [fn (): UploadedFile => UploadedFile::fake()->image('kecil.jpg', 300, 300), '600 piksel'],
    'portrait 1:2' => [fn (): UploadedFile => UploadedFile::fake()->image('tinggi.jpg', 600, 1200), 'Potong'],
    'landscape 16:9' => [fn (): UploadedFile => UploadedFile::fake()->image('lebar.jpg', 1600, 900), 'Potong'],
    '48 megapixels' => [fn (): UploadedFile => blankPng(8000, 6000), 'megapiksel'],
]);
