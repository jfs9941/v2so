<?php

namespace Module\Upload\Enum;

enum FileDriver : int
{
    case S3 = 1;
    case LOCAL = 0;
}