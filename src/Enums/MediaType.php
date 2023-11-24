<?php

namespace Finller\LaravelMedia\Enums;

enum MediaType: string
{
    case Video = 'video';
    case Image = 'image';
    case Audio = 'audio';
    case Pdf = 'pdf';
    case Other = 'other';
}
