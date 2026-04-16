<?php

namespace App\Enums;

enum LlmRequestStatus: string
{
    case Success = 'success';
    case Error = 'error';
    case Timeout = 'timeout';
    case EmptyResponse = 'empty_response';
}
