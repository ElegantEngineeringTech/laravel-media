<?php

namespace Finller\LaravelMedia\Enums;

enum GeneratedConversionState: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
