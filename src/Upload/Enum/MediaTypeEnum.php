<?php

namespace Module\Upload\Enum;


enum MediaTypeEnum : string
{
    case VIDEO = 'video';
    case IMAGE = 'image';
    case DOCUMENT = 'document';
}