<?php

namespace Module\Upload\Enum;

enum FileStatus : int
{
    case UPLOADED = 2;
    case UPLOADING = 1;
    case ABORTED = 0;
    case PROCESSING = 3;
    case WATERMARK_PROCESSED = 4;
    case THUMBNAIL_PROCESSED = 5;
    case ENCODING_PROCESSED = 6;
    case BLUR_PROCESSED = 7;

    case FINISHED = 8;
    case DELETED = 9;
    case ENCODING_ERROR = 10;
}